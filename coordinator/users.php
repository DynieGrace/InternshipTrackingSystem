<?php
/**
 * Coordinator: user and role management.
 *
 * Student and supervisor profiles are created from their own pages; this
 * page manages account status, coordinator accounts, and password resets.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$user = require_role(['coordinator']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $op = (string)($_POST['op'] ?? '');

        if ($op === 'create_coordinator') {
            $fullName = post_string('full_name', 120);
            $email = filter_var(post_string('email', 190), FILTER_VALIDATE_EMAIL);
            $password = (string)($_POST['password'] ?? '');
            if (mb_strlen($fullName) < 2 || !$email || strlen($password) < PASSWORD_MIN_LENGTH) {
                throw new InvalidArgumentException('Provide a full name, valid email, and a password of at least ' . PASSWORD_MIN_LENGTH . ' characters.');
            }
            if (query_value($db, 'SELECT id FROM users WHERE email = ? LIMIT 1', [$email])) {
                throw new InvalidArgumentException('That email address is already registered.');
            }
            $username = unique_username($db, $email);
            $db->prepare('INSERT INTO users (username, full_name, email, password_hash, role) VALUES (?, ?, ?, ?, "coordinator")')
                ->execute([$username, $fullName, $email, password_hash($password, PASSWORD_DEFAULT)]);
            record_activity($db, (int)$user['id'], 'create_coordinator', $email);
            flash_redirect('coordinator/users.php', 'Coordinator account created.');
        }

        if ($op === 'set_status') {
            $targetId = post_id('user_id');
            $status = post_string('status', 10);
            if (!in_array($status, ['active', 'inactive'], true)) {
                throw new InvalidArgumentException('Invalid status.');
            }
            if ($targetId === (int)$user['id']) {
                throw new InvalidArgumentException('You cannot change the status of your own account.');
            }
            $db->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, $targetId]);
            record_activity($db, (int)$user['id'], 'user_status', $status . ' #' . $targetId);
            flash_redirect('coordinator/users.php', 'Account status updated.');
        }

        if ($op === 'reset_password') {
            $targetId = post_id('user_id');
            $newPassword = (string)($_POST['new_password'] ?? '');
            if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                throw new InvalidArgumentException('The new password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.');
            }
            if (!query_one($db, 'SELECT id FROM users WHERE id = ?', [$targetId])) {
                throw new InvalidArgumentException('That account does not exist.');
            }
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $targetId]);
            $db->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$targetId]);
            notify_user($db, $targetId, 'Password reset by coordinator',
                'Your password was reset. Please sign in and change it from your profile.');
            record_activity($db, (int)$user['id'], 'admin_reset_password', '#' . $targetId);
            flash_redirect('coordinator/users.php', 'Password reset. Share the new temporary password securely.');
        }

        throw new InvalidArgumentException('Unknown action.');
    } catch (InvalidArgumentException $error) {
        flash('error', $error->getMessage());
    } catch (Throwable $error) {
        error_log('ITS coordinator users failed: ' . $error->getMessage());
        flash('error', 'The account action could not be completed.');
    }
    redirect('coordinator/users.php');
}

$title = 'User Accounts';
$active = 'users';
require __DIR__ . '/../includes/header.php';

echo panel('Create a coordinator account', 'Additional coordinators are created here, never through public registration.',
    form_start('coordinator/users.php')
    . '<input type="hidden" name="op" value="create_coordinator">'
    . '<div class="form-grid">'
    . '<label>Full name<input name="full_name" maxlength="120" required></label>'
    . '<label>Email<input name="email" type="email" maxlength="190" required></label>'
    . '<label>Temporary password<input name="password" type="password" minlength="' . PASSWORD_MIN_LENGTH . '" required></label>'
    . '</div><button class="button primary">Create coordinator</button></form>');

$roleFilter = (string)($_GET['role'] ?? '');
$params = [];
$where = '';
if (in_array($roleFilter, ['student', 'supervisor', 'coordinator'], true)) {
    $where = 'WHERE role = ?';
    $params[] = $roleFilter;
}

$filterForm = '<form method="get" action="' . e(url('coordinator/users.php')) . '" class="filter-form">'
    . '<label>Role<select name="role"><option value="">All roles</option>'
    . '<option value="student"' . ($roleFilter === 'student' ? ' selected' : '') . '>Students</option>'
    . '<option value="supervisor"' . ($roleFilter === 'supervisor' ? ' selected' : '') . '>Supervisors</option>'
    . '<option value="coordinator"' . ($roleFilter === 'coordinator' ? ' selected' : '') . '>Coordinators</option>'
    . '</select></label><button class="button secondary">Filter</button></form>';

$users = query_all(
    $db,
    "SELECT id, username, full_name, email, role, status, created_at FROM users $where ORDER BY role, full_name",
    $params
);

$rows = [];
foreach ($users as $row) {
    $isSelf = (int)$row['id'] === (int)$user['id'];
    $statusAction = $isSelf
        ? '<span class="muted">Current account</span>'
        : ($row['status'] === 'active'
            ? '<form method="post" action="' . e(url('coordinator/users.php')) . '" class="inline-review">' . csrf_field()
                . '<input type="hidden" name="op" value="set_status"><input type="hidden" name="user_id" value="' . (int)$row['id'] . '">'
                . '<input type="hidden" name="status" value="inactive"><button class="button small secondary">Deactivate</button></form>'
            : '<form method="post" action="' . e(url('coordinator/users.php')) . '" class="inline-review">' . csrf_field()
                . '<input type="hidden" name="op" value="set_status"><input type="hidden" name="user_id" value="' . (int)$row['id'] . '">'
                . '<input type="hidden" name="status" value="active"><button class="button small">Activate</button></form>');
    $reset = '<details class="correction"><summary>Reset password</summary>'
        . form_start('coordinator/users.php')
        . '<input type="hidden" name="op" value="reset_password"><input type="hidden" name="user_id" value="' . (int)$row['id'] . '">'
        . '<label>New temporary password<input name="new_password" type="password" minlength="' . PASSWORD_MIN_LENGTH . '" required></label>'
        . '<button class="button small">Reset</button></form></details>';
    $rows[] = [
        '<strong>' . e($row['full_name']) . '</strong><div class="help-text">' . e($row['username']) . ' · ' . e($row['email']) . '</div>',
        e(role_label($row['role'])),
        status_badge($row['status']),
        e(format_datetime($row['created_at'], 'M j, Y')),
        $statusAction . $reset,
    ];
}

echo panel('All accounts', 'Manage account status and reset passwords.', $filterForm
    . table_html(['User', 'Role', 'Status', 'Created', 'Actions'], $rows, 'No accounts found.'));

require __DIR__ . '/../includes/footer.php';

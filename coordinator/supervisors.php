<?php
/**
 * Coordinator: create and manage company supervisor accounts.
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

        if ($op === 'create_supervisor') {
            $fullName = post_string('full_name', 120);
            $email = filter_var(post_string('email', 190), FILTER_VALIDATE_EMAIL);
            $password = (string)($_POST['password'] ?? '');
            $companyId = post_optional_id('company_id');
            if (mb_strlen($fullName) < 2 || !$email || strlen($password) < PASSWORD_MIN_LENGTH) {
                throw new InvalidArgumentException('Provide a full name, valid email, and a password of at least ' . PASSWORD_MIN_LENGTH . ' characters.');
            }
            if (query_value($db, 'SELECT id FROM users WHERE email = ? LIMIT 1', [$email])) {
                throw new InvalidArgumentException('That email address is already registered.');
            }
            $db->beginTransaction();
            $username = unique_username($db, $email);
            $db->prepare('INSERT INTO users (username, full_name, email, password_hash, role) VALUES (?, ?, ?, ?, "supervisor")')
                ->execute([$username, $fullName, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $newUserId = (int)$db->lastInsertId();
            $db->prepare('INSERT INTO supervisors (user_id, company_id, job_title) VALUES (?, ?, ?)')
                ->execute([$newUserId, $companyId, post_string('job_title', 120)]);
            $db->commit();
            record_activity($db, (int)$user['id'], 'create_supervisor', $email);
            flash_redirect('coordinator/supervisors.php', 'Supervisor account created.');
        }

        if ($op === 'update_supervisor') {
            $supId = post_id('supervisor_id');
            $profile = query_one($db, 'SELECT user_id FROM supervisors WHERE id = ?', [$supId]);
            if (!$profile) {
                throw new InvalidArgumentException('That supervisor does not exist.');
            }
            $fullName = post_string('full_name', 120);
            $email = filter_var(post_string('email', 190), FILTER_VALIDATE_EMAIL);
            if (mb_strlen($fullName) < 2 || !$email) {
                throw new InvalidArgumentException('Enter a full name and valid email address.');
            }
            $clash = query_value($db, 'SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1', [$email, (int)$profile['user_id']]);
            if ($clash) {
                throw new InvalidArgumentException('That email address is used by another account.');
            }
            $db->beginTransaction();
            $db->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ?')->execute([$fullName, $email, (int)$profile['user_id']]);
            $db->prepare('UPDATE supervisors SET company_id = ?, job_title = ? WHERE id = ?')
                ->execute([post_optional_id('company_id'), post_string('job_title', 120), $supId]);
            $db->commit();
            record_activity($db, (int)$user['id'], 'update_supervisor', '#' . $supId);
            flash_redirect('coordinator/supervisors.php', 'Supervisor updated.');
        }

        if ($op === 'set_status') {
            $supId = post_id('supervisor_id');
            $status = post_string('status', 10);
            if (!in_array($status, ['active', 'inactive'], true)) {
                throw new InvalidArgumentException('Invalid status.');
            }
            $profile = query_one($db, 'SELECT user_id FROM supervisors WHERE id = ?', [$supId]);
            if (!$profile) {
                throw new InvalidArgumentException('That supervisor does not exist.');
            }
            $db->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, (int)$profile['user_id']]);
            record_activity($db, (int)$user['id'], 'supervisor_status', $status . ' #' . $supId);
            flash_redirect('coordinator/supervisors.php', 'Supervisor status updated.');
        }

        throw new InvalidArgumentException('Unknown action.');
    } catch (InvalidArgumentException $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        flash('error', $error->getMessage());
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('ITS coordinator supervisors failed: ' . $error->getMessage());
        flash('error', 'The supervisor record could not be saved.');
    }
    redirect('coordinator/supervisors.php');
}

$title = 'Supervisors';
$active = 'supervisors';
require __DIR__ . '/../includes/header.php';

$companies = query_all($db, 'SELECT id, name FROM companies ORDER BY name');
$companyOptions = fn(string $selected = '') => '<option value="">Not assigned</option>' . select_options($companies, 'id', 'name', $selected);

$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId ? query_one(
    $db,
    'SELECT s.*, u.full_name, u.email, u.status FROM supervisors s JOIN users u ON u.id = s.user_id WHERE s.id = ?',
    [$editId]
) : null;

if ($editId && $edit) {
    echo panel('Edit supervisor', 'Update ' . e($edit['full_name']) . '.',
        form_start('coordinator/supervisors.php')
        . '<input type="hidden" name="op" value="update_supervisor">'
        . '<input type="hidden" name="supervisor_id" value="' . (int)$edit['id'] . '">'
        . '<div class="form-grid">'
        . '<label>Full name<input name="full_name" maxlength="120" value="' . e($edit['full_name']) . '" required></label>'
        . '<label>Email<input name="email" type="email" maxlength="190" value="' . e($edit['email']) . '" required></label>'
        . '<label>Job title<input name="job_title" maxlength="120" value="' . e($edit['job_title']) . '"></label>'
        . '<label>Company<select name="company_id">' . $companyOptions((string)($edit['company_id'] ?? '')) . '</select></label>'
        . '</div><div class="stack-actions"><button class="button primary">Save changes</button>'
        . '<a class="button secondary" href="' . e(url('coordinator/supervisors.php')) . '">Cancel</a></div></form>');
} else {
    echo panel('Add a supervisor', 'Create a company supervisor account.',
        form_start('coordinator/supervisors.php')
        . '<input type="hidden" name="op" value="create_supervisor">'
        . '<div class="form-grid">'
        . '<label>Full name<input name="full_name" maxlength="120" required></label>'
        . '<label>Email<input name="email" type="email" maxlength="190" required></label>'
        . '<label>Temporary password<input name="password" type="password" minlength="' . PASSWORD_MIN_LENGTH . '" required></label>'
        . '<label>Job title<input name="job_title" maxlength="120"></label>'
        . '<label>Company<select name="company_id">' . $companyOptions() . '</select></label>'
        . '</div><button class="button primary">Create supervisor</button></form>');
}

$supervisors = query_all(
    $db,
    'SELECT s.id, s.job_title, u.full_name, u.email, u.status, c.name AS company,
            (SELECT COUNT(*) FROM internship_placements i WHERE i.supervisor_id = s.id AND i.status = "active") AS interns
     FROM supervisors s JOIN users u ON u.id = s.user_id LEFT JOIN companies c ON c.id = s.company_id
     ORDER BY u.full_name'
);
$rows = [];
foreach ($supervisors as $row) {
    $toggle = $row['status'] === 'active'
        ? '<input type="hidden" name="status" value="inactive"><button class="button small secondary">Deactivate</button>'
        : '<input type="hidden" name="status" value="active"><button class="button small">Activate</button>';
    $actions = '<a class="button small" href="' . e(url('coordinator/supervisors.php?edit=' . (int)$row['id'])) . '">Edit</a> '
        . '<form method="post" action="' . e(url('coordinator/supervisors.php')) . '" class="inline-review">' . csrf_field()
        . '<input type="hidden" name="op" value="set_status"><input type="hidden" name="supervisor_id" value="' . (int)$row['id'] . '">'
        . $toggle . '</form>';
    $rows[] = [
        '<strong>' . e($row['full_name']) . '</strong><div class="help-text">' . e($row['email']) . '</div>',
        e($row['job_title'] ?: '—'),
        e($row['company'] ?: 'Not assigned'),
        (string)$row['interns'],
        status_badge($row['status']),
        $actions,
    ];
}

echo '<div class="toolbar"><input type="search" class="grow" placeholder="Search supervisors…" data-table-filter="#supervisors-table"></div>';
echo '<div id="supervisors-table">' . table_html(['Supervisor', 'Title', 'Company', 'Interns', 'Status', 'Actions'], $rows, 'No supervisors found.') . '</div>';

require __DIR__ . '/../includes/footer.php';

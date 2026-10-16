<?php
/**
 * Supervisor profile: account details and password.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$user = require_role(['supervisor']);
$supervisor = supervisor_profile($db, (int)$user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $op = (string)($_POST['op'] ?? '');

        if ($op === 'update_account') {
            $fullName = post_string('full_name', 120);
            $username = post_string('username', 80);
            $email = filter_var(post_string('email', 190), FILTER_VALIDATE_EMAIL);
            if (mb_strlen($fullName) < 2 || $username === '' || !$email) {
                throw new InvalidArgumentException('Enter a full name, username and valid email address.');
            }
            $clash = query_value($db, 'SELECT id FROM users WHERE (email = ? OR username = ?) AND id <> ? LIMIT 1',
                [$email, $username, (int)$user['id']]);
            if ($clash) {
                throw new InvalidArgumentException('That email or username is already used by another account.');
            }
            $db->prepare('UPDATE users SET full_name = ?, username = ?, email = ? WHERE id = ?')
                ->execute([$fullName, $username, $email, (int)$user['id']]);
            $_SESSION['user']['full_name'] = $fullName;
            $_SESSION['user']['username'] = $username;
            $_SESSION['user']['email'] = $email;
            record_activity($db, (int)$user['id'], 'update_profile', 'account');
            flash_redirect('supervisor/profile.php', 'Account details updated.');
        }

        if ($op === 'change_password') {
            $current = (string)($_POST['current_password'] ?? '');
            $new = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');
            $hash = query_value($db, 'SELECT password_hash FROM users WHERE id = ?', [(int)$user['id']]);
            if (!$hash || !password_verify($current, (string)$hash)) {
                throw new InvalidArgumentException('Your current password is incorrect.');
            }
            if (strlen($new) < PASSWORD_MIN_LENGTH) {
                throw new InvalidArgumentException('The new password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.');
            }
            if ($new !== $confirm) {
                throw new InvalidArgumentException('The new passwords do not match.');
            }
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), (int)$user['id']]);
            record_activity($db, (int)$user['id'], 'change_password');
            flash_redirect('supervisor/profile.php', 'Password changed.');
        }

        if ($op === 'upload_avatar') {
            $stored = save_avatar($db, (int)$user['id'], $_FILES['avatar'] ?? []);
            $_SESSION['user']['avatar'] = $stored;
            record_activity($db, (int)$user['id'], 'upload_avatar');
            flash_redirect('supervisor/profile.php', 'Profile photo updated.');
        }

        if ($op === 'remove_avatar') {
            remove_avatar($db, (int)$user['id']);
            $_SESSION['user']['avatar'] = null;
            record_activity($db, (int)$user['id'], 'remove_avatar');
            flash_redirect('supervisor/profile.php', 'Profile photo removed.');
        }

        throw new InvalidArgumentException('Unknown action.');
    } catch (InvalidArgumentException $error) {
        flash('error', $error->getMessage());
    } catch (Throwable $error) {
        error_log('ITS supervisor profile failed: ' . $error->getMessage());
        flash('error', 'The change could not be saved.');
    }
    redirect('supervisor/profile.php');
}

$title = 'My Profile';
$active = 'profile';
require __DIR__ . '/../includes/header.php';

$company = $supervisor ? query_value($db, 'SELECT name FROM companies WHERE id = ?', [(int)($supervisor['company_id'] ?? 0)]) : null;

$accountForm = form_start('supervisor/profile.php')
    . '<input type="hidden" name="op" value="update_account">'
    . '<div class="form-grid">'
    . '<label>Full name<input name="full_name" maxlength="120" value="' . e($user['full_name']) . '" required></label>'
    . '<label>Username<input name="username" maxlength="80" value="' . e($user['username']) . '" required></label>'
    . '<label>Email<input name="email" type="email" maxlength="190" value="' . e($user['email']) . '" required></label>'
    . '<label>Company<input value="' . e($company ?: 'Not assigned') . '" disabled></label>'
    . '<label>Job title<input value="' . e($supervisor['job_title'] ?? '—') . '" disabled></label>'
    . '</div><button class="button primary">Save account details</button></form>';

$passwordForm = form_start('supervisor/profile.php')
    . '<input type="hidden" name="op" value="change_password">'
    . '<div class="form-grid">'
    . '<label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label>'
    . '<label>New password<input type="password" name="new_password" minlength="' . PASSWORD_MIN_LENGTH . '" autocomplete="new-password" required></label>'
    . '<label>Confirm new password<input type="password" name="confirm_password" minlength="' . PASSWORD_MIN_LENGTH . '" autocomplete="new-password" required></label>'
    . '</div><button class="button secondary">Change password</button></form>';

echo '<div class="two-col">'
    . panel('Profile photo', 'Your photo appears in the sidebar and header.', profile_photo_panel($user, 'supervisor/profile.php'))
    . panel('Account details', 'Your sign-in information.', $accountForm)
    . panel('Password', 'Choose a strong password of at least ' . PASSWORD_MIN_LENGTH . ' characters.', $passwordForm)
    . '</div>';

require __DIR__ . '/../includes/footer.php';

<?php
/**
 * Student profile: account details, internship profile and password.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$user = require_role(['student']);
$student = student_profile($db, (int)$user['id']);

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
            flash_redirect('student/profile.php', 'Account details updated.');
        }

        if ($op === 'update_student') {
            if (!$student) {
                throw new InvalidArgumentException('No student profile is linked to your account.');
            }
            $db->prepare('UPDATE student_profiles SET phone = ?, address = ? WHERE id = ?')
                ->execute([
                    post_string('phone', 40), post_string('address', 255), (int)$student['id'],
                ]);
            record_activity($db, (int)$user['id'], 'update_profile', 'student');
            flash_redirect('student/profile.php', 'Internship profile updated.');
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
            flash_redirect('student/profile.php', 'Password changed.');
        }

        if ($op === 'upload_avatar') {
            $stored = save_avatar($db, (int)$user['id'], $_FILES['avatar'] ?? []);
            $_SESSION['user']['avatar'] = $stored;
            record_activity($db, (int)$user['id'], 'upload_avatar');
            flash_redirect('student/profile.php', 'Profile photo updated.');
        }

        if ($op === 'remove_avatar') {
            remove_avatar($db, (int)$user['id']);
            $_SESSION['user']['avatar'] = null;
            record_activity($db, (int)$user['id'], 'remove_avatar');
            flash_redirect('student/profile.php', 'Profile photo removed.');
        }

        throw new InvalidArgumentException('Unknown action.');
    } catch (InvalidArgumentException $error) {
        flash('error', $error->getMessage());
    } catch (Throwable $error) {
        error_log('ITS student profile failed: ' . $error->getMessage());
        flash('error', 'The change could not be saved.');
    }
    redirect('student/profile.php');
}

$title = 'My Profile';
$active = 'profile';
require __DIR__ . '/../includes/header.php';

$accountForm = form_start('student/profile.php')
    . '<input type="hidden" name="op" value="update_account">'
    . '<div class="form-grid">'
    . '<label>Full name<input name="full_name" maxlength="120" value="' . e($user['full_name']) . '" required></label>'
    . '<label>Username<input name="username" maxlength="80" value="' . e($user['username']) . '" required></label>'
    . '<label>Email<input name="email" type="email" maxlength="190" value="' . e($user['email']) . '" required></label>'
    . '<label>Student ID<input value="' . e($student['student_number'] ?? '—') . '" disabled></label>'
    . '</div><button class="button primary">Save account details</button></form>';

$studentForm = $student
    ? form_start('student/profile.php')
        . '<input type="hidden" name="op" value="update_student">'
        . '<div class="form-grid">'
        . '<label>Course<input value="BS Information Technology" disabled></label>'
        . '<label>Year level<input value="4th Year" disabled></label>'
        . '<label>Contact number<input name="phone" maxlength="40" value="' . e($student['phone']) . '"></label>'
        . '<label>Address<input name="address" maxlength="255" value="' . e($student['address']) . '"></label>'
        . '</div><button class="button primary">Save internship profile</button></form>'
    : '<div class="empty-state">No student profile is linked to your account.</div>';

$passwordForm = form_start('student/profile.php')
    . '<input type="hidden" name="op" value="change_password">'
    . '<div class="form-grid">'
    . '<label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label>'
    . '<label>New password<input type="password" name="new_password" minlength="' . PASSWORD_MIN_LENGTH . '" autocomplete="new-password" required></label>'
    . '<label>Confirm new password<input type="password" name="confirm_password" minlength="' . PASSWORD_MIN_LENGTH . '" autocomplete="new-password" required></label>'
    . '</div><button class="button secondary">Change password</button></form>';

echo '<div class="two-col">'
    . panel('Profile photo', 'Your photo appears in the sidebar and header.', profile_photo_panel($user, 'student/profile.php'))
    . panel('Account details', 'Your sign-in information.', $accountForm)
    . panel('Internship profile', 'Course, year level and contact details.', $studentForm)
    . panel('Password', 'Choose a strong password of at least ' . PASSWORD_MIN_LENGTH . ' characters.', $passwordForm)
    . '</div>';

require __DIR__ . '/../includes/footer.php';

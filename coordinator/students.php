<?php
/**
 * Coordinator: add, edit, view and archive student records.
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

        if ($op === 'create_student') {
            $fullName = post_string('full_name', 120);
            $email = filter_var(post_string('email', 190), FILTER_VALIDATE_EMAIL);
            $password = (string)($_POST['password'] ?? '');
            $studentNumber = post_string('student_number', 50);
            if (mb_strlen($fullName) < 2 || !$email || strlen($password) < PASSWORD_MIN_LENGTH || $studentNumber === '') {
                throw new InvalidArgumentException('Provide a full name, valid email, a password of at least ' . PASSWORD_MIN_LENGTH . ' characters, and a student ID.');
            }
            if (query_value($db, 'SELECT id FROM users WHERE email = ? LIMIT 1', [$email])) {
                throw new InvalidArgumentException('That email address is already registered.');
            }
            if (query_value($db, 'SELECT id FROM student_profiles WHERE student_number = ? LIMIT 1', [$studentNumber])) {
                throw new InvalidArgumentException('That student ID is already registered.');
            }

            $db->beginTransaction();
            $username = unique_username($db, $email);
            $db->prepare('INSERT INTO users (username, full_name, email, password_hash, role) VALUES (?, ?, ?, ?, "student")')
                ->execute([$username, $fullName, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $newUserId = (int)$db->lastInsertId();
            $db->prepare('INSERT INTO student_profiles (user_id, student_number, course, year_level, phone, address) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$newUserId, $studentNumber, 'BS Information Technology', '4th Year', post_string('phone', 40), post_string('address', 255)]);
            $db->commit();
            record_activity($db, (int)$user['id'], 'create_student', $studentNumber);
            flash_redirect('coordinator/students.php', 'Student account created.');
        }

        if ($op === 'update_student') {
            $profileId = post_id('profile_id');
            $profile = query_one($db, 'SELECT user_id FROM student_profiles WHERE id = ?', [$profileId]);
            if (!$profile) {
                throw new InvalidArgumentException('That student record does not exist.');
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
            $db->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ?')
                ->execute([$fullName, $email, (int)$profile['user_id']]);
            $db->prepare('UPDATE student_profiles SET phone = ?, address = ? WHERE id = ?')
                ->execute([post_string('phone', 40), post_string('address', 255), $profileId]);
            $db->commit();
            record_activity($db, (int)$user['id'], 'update_student', 'profile #' . $profileId);
            flash_redirect('coordinator/students.php', 'Student record updated.');
        }

        if ($op === 'set_status') {
            $profileId = post_id('profile_id');
            $status = post_string('status', 10);
            if (!in_array($status, ['active', 'inactive'], true)) {
                throw new InvalidArgumentException('Invalid status.');
            }
            $profile = query_one($db, 'SELECT user_id FROM student_profiles WHERE id = ?', [$profileId]);
            if (!$profile) {
                throw new InvalidArgumentException('That student record does not exist.');
            }
            $db->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, (int)$profile['user_id']]);
            record_activity($db, (int)$user['id'], $status === 'inactive' ? 'archive_student' : 'reactivate_student', 'profile #' . $profileId);
            flash_redirect('coordinator/students.php', $status === 'inactive' ? 'Student archived.' : 'Student reactivated.');
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
        error_log('ITS coordinator students failed: ' . $error->getMessage());
        flash('error', 'The student record could not be saved.');
    }
    redirect('coordinator/students.php');
}

$title = 'Students';
$active = 'students';
require __DIR__ . '/../includes/header.php';

$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId ? query_one(
    $db,
    'SELECT sp.*, u.full_name, u.email, u.status, u.username
    FROM student_profiles sp JOIN users u ON u.id = sp.user_id WHERE sp.id = ?',
    [$editId]
) : null;

if ($editId && $edit) {
    $editForm = form_start('coordinator/students.php')
        . '<input type="hidden" name="op" value="update_student">'
        . '<input type="hidden" name="profile_id" value="' . (int)$edit['id'] . '">'
        . '<div class="form-grid">'
        . '<label>Full name<input name="full_name" maxlength="120" value="' . e($edit['full_name']) . '" required></label>'
        . '<label>Email<input name="email" type="email" maxlength="190" value="' . e($edit['email']) . '" required></label>'
        . '<label>Student ID<input value="' . e($edit['student_number']) . '" disabled></label>'
        . '<label>Course<input value="BS Information Technology" disabled></label>'
        . '<label>Year level<input value="4th Year" disabled></label>'
        . '<label>Contact number<input name="phone" maxlength="40" value="' . e($edit['phone']) . '"></label>'
        . '<label>Address<input name="address" maxlength="255" value="' . e($edit['address']) . '"></label>'
        . '</div><div class="stack-actions"><button class="button primary">Save changes</button>'
        . '<a class="button secondary" href="' . e(url('coordinator/students.php')) . '">Cancel</a></div></form>';
    echo panel('Edit student', 'Update ' . e($edit['student_number']) . '.', $editForm);
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$createForm = form_start('coordinator/students.php')
    . '<input type="hidden" name="op" value="create_student">'
    . '<div class="form-grid">'
    . '<label>Full name<input name="full_name" maxlength="120" required></label>'
    . '<label>Student ID<input name="student_number" maxlength="50" required></label>'
    . '<label>Email<input name="email" type="email" maxlength="190" required></label>'
    . '<label>Temporary password<input name="password" type="password" minlength="' . PASSWORD_MIN_LENGTH . '" required></label>'
    . '<label>Contact number<input name="phone" maxlength="40"></label>'
    . '<label>Address<input name="address" maxlength="255"></label>'
    . '</div><p class="help-text">Share the temporary password securely and ask the student to change it after signing in.</p>'
    . '<button class="button primary">Create student</button></form>';

echo panel('Add a student', 'Create a student account and profile.', $createForm);

$students = query_all(
    $db,
    "SELECT sp.id, sp.student_number, sp.course, sp.year_level, u.full_name, u.email, u.status,
            (SELECT c.name FROM internship_placements i JOIN companies c ON c.id = i.company_id
            WHERE i.student_id = sp.id AND i.status = 'active' LIMIT 1) AS company
    FROM student_profiles sp JOIN users u ON u.id = sp.user_id
    ORDER BY sp.student_number"
);

$rows = [];
foreach ($students as $row) {
    $actions = '<a class="button small" href="' . e(url('coordinator/students.php?edit=' . (int)$row['id'])) . '">Edit</a> ';
    if ($row['status'] === 'active') {
        $actions .= '<form method="post" action="' . e(url('coordinator/students.php')) . '" class="inline-review" data-confirm="Archive this student? They will not be able to sign in.">'
            . csrf_field() . '<input type="hidden" name="op" value="set_status">'
            . '<input type="hidden" name="profile_id" value="' . (int)$row['id'] . '">'
            . '<input type="hidden" name="status" value="inactive"><button class="button small secondary">Archive</button></form>';
    } else {
        $actions .= '<form method="post" action="' . e(url('coordinator/students.php')) . '" class="inline-review">'
            . csrf_field() . '<input type="hidden" name="op" value="set_status">'
            . '<input type="hidden" name="profile_id" value="' . (int)$row['id'] . '">'
            . '<input type="hidden" name="status" value="active"><button class="button small">Reactivate</button></form>';
    }
    $rows[] = [
        e($row['student_number']),
        '<strong>' . e($row['full_name']) . '</strong><div class="help-text">' . e($row['email']) . '</div>',
        e($row['course']) . '<div class="help-text">' . e($row['year_level']) . '</div>',
        e($row['company'] ?: 'Unplaced'),
        status_badge($row['status']),
        $actions,
    ];
}

echo '<div class="toolbar"><input type="search" class="grow" placeholder="Search students…" data-table-filter="#students-table"></div>';
echo '<div id="students-table">' . table_html(
    ['Student ID', 'Student', 'Course', 'Company', 'Account', 'Actions'], $rows, 'No students registered yet.'
) . '</div>';

require __DIR__ . '/../includes/footer.php';

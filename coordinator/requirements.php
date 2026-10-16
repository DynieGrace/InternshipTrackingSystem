<?php
/**
 * Coordinator: define internship requirements and deadlines.
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

        if ($op === 'create_requirement' || $op === 'update_requirement') {
            $title = post_string('title', 180);
            $dueDate = post_string('due_date', 10) ?: null;
            $extensions = strtolower(post_string('allowed_extensions', 120) ?: 'pdf,jpg,jpeg,png');
            $maxBytes = filter_var($_POST['max_file_bytes'] ?? 5242880, FILTER_VALIDATE_INT) ?: 5242880;

            if ($title === '' || ($dueDate && !valid_datetime($dueDate))) {
                throw new InvalidArgumentException('Enter a title and a valid due date.');
            }
            if (!preg_match('/^(pdf|jpg|jpeg|png)(,(pdf|jpg|jpeg|png))*$/', $extensions)) {
                throw new InvalidArgumentException('Allowed types may only include pdf, jpg, jpeg, and png.');
            }
            if ($maxBytes < 1024 || $maxBytes > 20 * 1048576) {
                throw new InvalidArgumentException('The size limit must be between 1 KB and 20 MB.');
            }

            if ($op === 'create_requirement') {
                $db->prepare('INSERT INTO requirements (title, description, due_date, allowed_extensions, max_file_bytes) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$title, post_string('description', 2000), $dueDate, $extensions, $maxBytes]);
                record_activity($db, (int)$user['id'], 'create_requirement', $title);
                // Notify active students of a new requirement.
                foreach (query_all($db, "SELECT id FROM users WHERE role = 'student' AND status = 'active'") as $student) {
                    notify_user($db, (int)$student['id'], 'New internship requirement', $title);
                }
                flash_redirect('coordinator/requirements.php', 'Requirement created.');
            }

            $id = post_id('requirement_id');
            $db->prepare('UPDATE requirements SET title = ?, description = ?, due_date = ?, allowed_extensions = ?, max_file_bytes = ? WHERE id = ?')
                ->execute([$title, post_string('description', 2000), $dueDate, $extensions, $maxBytes, $id]);
            record_activity($db, (int)$user['id'], 'update_requirement', $title);
            flash_redirect('coordinator/requirements.php', 'Requirement updated.');
        }

        if ($op === 'archive_requirement') {
            $id = post_id('requirement_id');
            $db->prepare('UPDATE requirements SET archived = 1, active = 0 WHERE id = ?')->execute([$id]);
            record_activity($db, (int)$user['id'], 'archive_requirement', '#' . $id);
            flash_redirect('coordinator/requirements.php', 'Requirement archived.');
        }

        if ($op === 'send_reminders') {
            // Notify placed students who still owe an upcoming or overdue requirement.
            $due = query_all(
                $db,
                "SELECT id, title, due_date FROM requirements
                 WHERE active = 1 AND archived = 0 AND due_date IS NOT NULL
                   AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                 ORDER BY due_date"
            );
            $sent = 0;
            $today = date('Y-m-d');
            foreach ($due as $requirement) {
                $overdue = $requirement['due_date'] < $today;
                $students = query_all(
                    $db,
                    "SELECT DISTINCT sp.user_id
                     FROM student_profiles sp
                     JOIN users u ON u.id = sp.user_id
                     JOIN internship_placements i ON i.student_id = sp.id AND i.status = 'active'
                     WHERE u.status = 'active'
                       AND NOT EXISTS (
                         SELECT 1 FROM document_submissions d
                         WHERE d.requirement_id = ? AND d.student_id = sp.id AND d.status = 'approved'
                       )",
                    [(int)$requirement['id']]
                );
                foreach ($students as $student) {
                    notify_user(
                        $db,
                        (int)$student['user_id'],
                        ($overdue ? 'Overdue requirement: ' : 'Requirement due soon: ') . $requirement['title'],
                        $overdue
                            ? 'This requirement was due on ' . format_datetime($requirement['due_date'], 'M j, Y') . '. Please submit it as soon as possible.'
                            : 'This requirement is due on ' . format_datetime($requirement['due_date'], 'M j, Y') . '. Please submit it before the deadline.'
                    );
                    $sent++;
                }
            }
            record_activity($db, (int)$user['id'], 'send_reminders', $sent . ' reminder(s)');
            flash_redirect('coordinator/requirements.php',
                $sent > 0 ? $sent . ' reminder notification(s) sent.' : 'No upcoming or overdue requirements with pending students.');
        }

        throw new InvalidArgumentException('Unknown action.');
    } catch (InvalidArgumentException $error) {
        flash('error', $error->getMessage());
    } catch (Throwable $error) {
        error_log('ITS coordinator requirements failed: ' . $error->getMessage());
        flash('error', 'The requirement could not be saved.');
    }
    redirect('coordinator/requirements.php');
}

$title = 'Requirements';
$active = 'requirements';
require __DIR__ . '/../includes/header.php';

$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId ? query_one($db, 'SELECT * FROM requirements WHERE id = ?', [$editId]) : null;

if ($editId && $edit) {
    echo panel('Edit requirement', 'Update "' . e($edit['title']) . '".',
        form_start('coordinator/requirements.php')
        . '<input type="hidden" name="op" value="update_requirement">'
        . '<input type="hidden" name="requirement_id" value="' . (int)$edit['id'] . '">'
        . '<div class="form-grid">'
        . '<label>Title<input name="title" maxlength="180" value="' . e($edit['title']) . '" required></label>'
        . '<label>Due date<input type="date" name="due_date" value="' . e($edit['due_date'] ?? '') . '"></label>'
        . '<label>Allowed extensions<input name="allowed_extensions" value="' . e($edit['allowed_extensions']) . '" required></label>'
        . '<label>Max size (bytes)<input type="number" name="max_file_bytes" value="' . (int)$edit['max_file_bytes'] . '" required></label>'
        . '</div><label>Description<textarea name="description" rows="3">' . e($edit['description']) . '</textarea></label>'
        . '<div class="stack-actions"><button class="button primary">Save changes</button>'
        . '<a class="button secondary" href="' . e(url('coordinator/requirements.php')) . '">Cancel</a></div></form>');
} else {
    echo panel('Add a requirement', 'Students upload PDF, JPG or PNG documents before the deadline.',
        form_start('coordinator/requirements.php')
        . '<input type="hidden" name="op" value="create_requirement">'
        . '<div class="form-grid">'
        . '<label>Title<input name="title" maxlength="180" required></label>'
        . '<label>Due date<input type="date" name="due_date"></label>'
        . '<label>Allowed extensions<input name="allowed_extensions" value="pdf,jpg,jpeg,png" required></label>'
        . '<label>Max size (bytes)<input type="number" name="max_file_bytes" value="5242880" required></label>'
        . '</div><label>Description<textarea name="description" rows="3" maxlength="2000"></textarea></label>'
        . '<button class="button primary">Add requirement</button></form>');
}

$requirements = query_all(
    $db,
    "SELECT r.*, (SELECT COUNT(*) FROM document_submissions d WHERE d.requirement_id = r.id AND d.status = 'submitted') AS pending,
            (SELECT COUNT(*) FROM document_submissions d WHERE d.requirement_id = r.id AND d.status = 'approved') AS approved
    FROM requirements r WHERE r.archived = 0 ORDER BY (r.due_date IS NULL), r.due_date ASC, r.title"
);
$rows = [];
foreach ($requirements as $row) {
    $actions = '<a class="button small" href="' . e(url('coordinator/requirements.php?edit=' . (int)$row['id'])) . '">Edit</a> '
        . '<form method="post" action="' . e(url('coordinator/requirements.php')) . '" class="inline-review" data-confirm="Archive this requirement?">' . csrf_field()
        . '<input type="hidden" name="op" value="archive_requirement"><input type="hidden" name="requirement_id" value="' . (int)$row['id'] . '">'
        . '<button class="button small secondary">Archive</button></form>';
    $rows[] = [
        '<strong>' . e($row['title']) . '</strong>' . ($row['description'] ? '<div class="help-text">' . e($row['description']) . '</div>' : ''),
        e($row['due_date'] ? format_datetime($row['due_date'], 'M j, Y') : 'No deadline'),
        e($row['allowed_extensions']),
        (string)$row['pending'] . ' pending',
        (string)$row['approved'] . ' approved',
        $actions,
    ];
}

$reminderBar = '<div class="toolbar">'
    . form_start('coordinator/requirements.php')
    . '<input type="hidden" name="op" value="send_reminders">'
    . '<button class="button secondary" type="submit" data-confirm="Send reminder notifications to students with upcoming or overdue requirements?">Send deadline reminders</button>'
    . '</form></div>';

echo panel('Active requirements', 'Deadlines, accepted file types and submission counts.', $reminderBar
    . table_html(['Requirement', 'Deadline', 'Types', 'Awaiting review', 'Approved', 'Actions'], $rows, 'No active requirements.'));

require __DIR__ . '/../includes/footer.php';

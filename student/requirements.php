<?php
/**
 * Student requirements: view deadlines, upload documents, and track the
 * Pending / Submitted / Approved / Rejected status of each requirement.
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
        if (($_POST['op'] ?? '') !== 'submit_document') {
            throw new InvalidArgumentException('Unknown action.');
        }
        if (!$student) {
            throw new InvalidArgumentException('Your student profile is not set up yet.');
        }

        $requirementId = post_id('requirement_id');
        $requirement = query_one($db, 'SELECT * FROM requirements WHERE id = ? AND active = 1 AND archived = 0', [$requirementId]);
        $file = $_FILES['document'] ?? null;

        if (!$requirement || !$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Choose an active requirement and a valid file to upload.');
        }

        $allowed = array_filter(array_map('trim', explode(',', strtolower($requirement['allowed_extensions']))));
        $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $mimeByExtension = [
            'pdf'  => ['application/pdf'],
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
        ];

        if (!in_array($extension, $allowed, true)
            || !isset($mimeByExtension[$extension])
            || !in_array($mime, $mimeByExtension[$extension], true)
            || (int)$file['size'] > (int)$requirement['max_file_bytes']) {
            throw new InvalidArgumentException(
                'The file must be one of: ' . implode(', ', $allowed) . ', and no larger than '
                . number_format($requirement['max_file_bytes'] / 1048576, 0) . ' MB.'
            );
        }

        if (!is_dir(UPLOAD_PATH) && !mkdir(UPLOAD_PATH, 0750, true) && !is_dir(UPLOAD_PATH)) {
            throw new RuntimeException('The upload directory could not be created.');
        }

        $storedName = random_token(24) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], UPLOAD_PATH . '/' . $storedName)) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        $db->prepare(
            'INSERT INTO document_submissions
             (requirement_id, student_id, stored_name, original_name, mime_type, file_size, status)
             VALUES (?, ?, ?, ?, ?, ?, "submitted")'
        )->execute([
            $requirementId, (int)$student['id'], $storedName,
            mb_substr(basename((string)$file['name']), 0, 255), $mime, (int)$file['size'],
        ]);

        foreach (query_all($db, "SELECT id FROM users WHERE role = 'coordinator' AND status = 'active'") as $coordinator) {
            notify_user($db, (int)$coordinator['id'], 'Document submitted',
                $student['student_number'] . ' submitted "' . $requirement['title'] . '" for review.');
        }
        record_activity($db, (int)$user['id'], 'submit_document', 'Requirement #' . $requirementId);
        flash_redirect('student/requirements.php', 'Document uploaded and sent to the coordinator for review.');
    } catch (InvalidArgumentException $error) {
        flash('error', $error->getMessage());
    } catch (Throwable $error) {
        error_log('ITS submit document failed: ' . $error->getMessage());
        flash('error', 'The document could not be uploaded. Please try again.');
    }
    redirect('student/requirements.php');
}

$title = 'Requirements';
$active = 'requirements';
require __DIR__ . '/../includes/header.php';

if (!$student) {
    echo panel('Requirements unavailable', 'Student profile missing.',
        '<div class="empty-state">Your student profile is not set up yet.</div>');
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$requirements = query_all(
    $db,
    "SELECT r.id, r.title, r.description, r.due_date, r.allowed_extensions, r.max_file_bytes,
            d.id AS submission_id, d.status, d.reviewer_remarks, d.original_name, d.submitted_at
     FROM requirements r
     LEFT JOIN document_submissions d
        ON d.id = (SELECT d2.id FROM document_submissions d2
                   WHERE d2.requirement_id = r.id AND d2.student_id = ? ORDER BY d2.id DESC LIMIT 1)
     WHERE r.active = 1 AND r.archived = 0
     ORDER BY (r.due_date IS NULL), r.due_date ASC, r.title ASC",
    [(int)$student['id']]
);

$uploadOptions = [];
$rows = [];
foreach ($requirements as $row) {
    $status = $row['status'] ?: 'pending';
    $fileLink = $row['submission_id']
        ? '<a class="text-link" href="' . e(url('download.php?id=' . (int)$row['submission_id'])) . '">' . e($row['original_name']) . '</a>'
        : '<span class="muted">Not submitted</span>';
    $remarks = $row['reviewer_remarks'] ? '<div class="help-text">' . e($row['reviewer_remarks']) . '</div>' : '';
    $rows[] = [
        e($row['title']) . ($row['description'] ? '<div class="help-text">' . e($row['description']) . '</div>' : ''),
        e($row['due_date'] ? format_datetime($row['due_date'], 'M j, Y') : 'No deadline'),
        status_badge($status),
        $fileLink . $remarks,
    ];
    $uploadOptions[] = ['id' => $row['id'], 'title' => $row['title']];
}

$rowCount = (int)query_value($db, "SELECT COUNT(*) FROM document_submissions WHERE student_id = ? AND status = 'submitted'", [(int)$student['id']]);
$approvedCount = (int)query_value($db, "SELECT COUNT(*) FROM document_submissions WHERE student_id = ? AND status = 'approved'", [(int)$student['id']]);

echo '<div class="stats-grid">';
echo stat_card('Requirements', (string)count($requirements), 'Active requirements', '▤');
echo stat_card('Submitted', (string)$rowCount, 'Awaiting coordinator review', '⇩');
echo stat_card('Approved', (string)$approvedCount, 'Accepted documents', '✓');
echo stat_card('Deadlines', (string)count(array_filter($requirements, fn($r) => $r['due_date'])), 'Requirements with a due date', '◷');
echo '</div>';

$uploadForm = form_start('student/requirements.php', ['enctype' => 'multipart/form-data'])
    . '<input type="hidden" name="op" value="submit_document">'
    . '<label>Requirement<select name="requirement_id" required><option value="">Choose requirement</option>'
    . select_options($uploadOptions, 'id', 'title') . '</select></label>'
    . '<label>Document (PDF, JPG or PNG; max 5 MB)<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required></label>'
    . '<button class="button primary">Upload document</button></form>';

echo panel('Submit a document', 'Uploads are checked, renamed, stored privately and reviewed by the coordinator.', $uploadForm);
echo panel('My requirements', 'Status of each requirement, showing only your latest submission.',
    table_html(['Requirement', 'Deadline', 'Status', 'Submission / remarks'], $rows, 'No active requirements.'));

require __DIR__ . '/../includes/footer.php';

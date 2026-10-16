<?php
/**
 * Coordinator: review submitted documents (approve / reject with remarks).
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
        if (($_POST['op'] ?? '') !== 'review_submission') {
            throw new InvalidArgumentException('Unknown action.');
        }
        $id = post_id('submission_id');
        $status = post_string('status', 20);
        $remarks = post_string('remarks', 1000);
        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Choose approval or rejection.');
        }
        if ($status === 'rejected' && $remarks === '') {
            throw new InvalidArgumentException('A rejection needs a reason for the student.');
        }

        $submission = query_one(
            $db,
            'SELECT d.requirement_id, d.student_id, r.title, sp.user_id
            FROM document_submissions d
            JOIN requirements r ON r.id = d.requirement_id
            JOIN student_profiles sp ON sp.id = d.student_id
            WHERE d.id = ?',
            [$id]
        );
        if (!$submission) {
            throw new InvalidArgumentException('That submission does not exist.');
        }

        $db->prepare('UPDATE document_submissions SET status = ?, reviewer_id = ?, reviewer_remarks = ?, reviewed_at = NOW() WHERE id = ?')
            ->execute([$status, (int)$user['id'], $remarks, $id]);

        notify_user($db, (int)$submission['user_id'], 'Document ' . $status,
            '"' . $submission['title'] . '" was ' . $status . ($remarks ? ': ' . $remarks : '.'));
        record_activity($db, (int)$user['id'], 'review_submission', $status . ' #' . $id);
        flash_redirect('coordinator/submissions.php', 'Document review saved.');
    } catch (InvalidArgumentException $error) {
        flash('error', $error->getMessage());
    } catch (Throwable $error) {
        error_log('ITS coordinator submissions failed: ' . $error->getMessage());
        flash('error', 'The review could not be saved.');
    }
    redirect('coordinator/submissions.php');
}

$title = 'Document Review';
$active = 'submissions';
require __DIR__ . '/../includes/header.php';

$statusFilter = (string)($_GET['status'] ?? 'submitted');
if (!in_array($statusFilter, ['submitted', 'approved', 'rejected', 'all'], true)) {
    $statusFilter = 'submitted';
}

$filterForm = '<form method="get" action="' . e(url('coordinator/submissions.php')) . '" class="filter-form">'
    . '<label>Status<select name="status">'
    . '<option value="submitted"' . ($statusFilter === 'submitted' ? ' selected' : '') . '>Submitted</option>'
    . '<option value="approved"' . ($statusFilter === 'approved' ? ' selected' : '') . '>Approved</option>'
    . '<option value="rejected"' . ($statusFilter === 'rejected' ? ' selected' : '') . '>Rejected</option>'
    . '<option value="all"' . ($statusFilter === 'all' ? ' selected' : '') . '>All</option>'
    . '</select></label><button class="button secondary">Filter</button></form>';

$params = [];
$where = '';
if ($statusFilter !== 'all') {
    $where = 'WHERE d.status = ?';
    $params[] = $statusFilter;
}

$submissions = query_all(
    $db,
    "SELECT d.id, d.original_name, d.status, d.reviewer_remarks, d.submitted_at, d.reviewed_at,
            r.title, sp.student_number, u.full_name
    FROM document_submissions d
    JOIN requirements r ON r.id = d.requirement_id
    JOIN student_profiles sp ON sp.id = d.student_id
    JOIN users u ON u.id = sp.user_id
    $where
    ORDER BY d.submitted_at DESC LIMIT 300",
    $params
);

$rows = [];
foreach ($submissions as $row) {
    $download = '<a class="text-link" href="' . e(url('download.php?id=' . (int)$row['id'])) . '">Download</a>';
    $review = '';
    if ($row['status'] === 'submitted') {
        $review = form_start('coordinator/submissions.php', ['class' => 'inline-review'])
            . '<input type="hidden" name="op" value="review_submission">'
            . '<input type="hidden" name="submission_id" value="' . (int)$row['id'] . '">'
            . '<select name="status" aria-label="Review decision"><option value="approved">Approve</option><option value="rejected">Reject</option></select>'
            . '<input name="remarks" placeholder="Reason (required to reject)" maxlength="1000">'
            . '<button class="button small">Save</button></form>';
    }
    $rows[] = [
        e($row['title']),
        e($row['student_number']) . '<div class="help-text">' . e($row['full_name']) . '</div>',
        $download . '<div class="help-text">' . e($row['original_name']) . '</div>',
        e(format_datetime($row['submitted_at'])),
        status_badge($row['status']),
        e($row['reviewer_remarks'] ?: '') . $review,
    ];
}

echo panel('Document review queue', 'Download files privately, approve them, or reject with a reason.', $filterForm
    . table_html(['Requirement', 'Student', 'File', 'Submitted', 'Status', 'Remarks / action'], $rows, 'No documents match this filter.'));

require __DIR__ . '/../includes/footer.php';

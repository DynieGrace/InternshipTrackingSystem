<?php
/**
 * Student Intern dashboard: placement, progress, requirements and
 * attendance at a glance.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$user = require_role(['student']);
$student = student_profile($db, (int)$user['id']);

$title = 'Overview';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';

if (!$student) {
    echo panel('Profile setup needed', 'Your student record is incomplete.',
        '<p class="muted">Please contact the internship coordinator to finish setting up your account.</p>');
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$studentId = (int)$student['id'];
$placement = active_placement($db, $studentId);
$totals = attendance_totals($db, $studentId);
$required = (float)($placement['required_hours'] ?? 0);
[$remaining, $percent] = progress_values($totals['approved'], $required);

$requirementStats = query_one(
    $db,
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN r.id IN (SELECT document_submissions.requirement_id FROM document_submissions
                                WHERE document_submissions.student_id = ? AND document_submissions.status = 'approved') THEN 1 ELSE 0 END) AS approved
     FROM requirements r WHERE r.active = 1 AND r.archived = 0",
    [$studentId]
);
$totalReq = (int)($requirementStats['total'] ?? 0);
$approvedReq = (int)($requirementStats['approved'] ?? 0);

$unread = (int)query_value($db, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [(int)$user['id']]);

echo '<div class="stats-grid">';
echo stat_card('Approved hours', format_hours($totals['approved']), 'Verified sessions only', '◷');
echo stat_card('Hours remaining', format_hours($remaining), format_hours($required) . ' required', '↗');
echo stat_card('Requirements', $approvedReq . ' / ' . $totalReq . ' approved', 'Submitted documents reviewed by the coordinator', '▤');
echo stat_card('Notifications', (string)$unread, 'Unread updates', '◔');
echo '</div>';

// Attendance QR, shown right after sign-in so it is easy to find.
$student = ensure_attendance_token($db, $student);
echo panel('My attendance QR code', 'Show this QR to your instructor or company supervisor to record your attendance.',
    attendance_qr_panel($student, 'my-qr'));

echo '<section class="panel progress-panel"><div class="panel-title"><div><h2>Internship progress</h2><p>Approved rendered hours divided by the required hours.</p></div><strong>'
    . number_format($percent, 1) . '%</strong></div><div class="progress-track"><span style="width:'
    . number_format($percent, 2, '.', '') . '%"></span></div>'
    . '<p class="help-text">Pending and rejected attendance records do not count toward approved hours.</p></section>';

// Placement summary
if ($placement) {
    $details = '<ul class="detail-list">'
        . '<li><span>Company</span><strong>' . e($placement['company']) . '</strong></li>'
        . '<li><span>Supervisor</span><strong>' . e($placement['supervisor_name'] ?: 'Not assigned') . '</strong></li>'
        . '<li><span>Schedule</span><strong>' . e(format_datetime($placement['start_date'], 'M j, Y')) . ' – '
        . e($placement['end_date'] ? format_datetime($placement['end_date'], 'M j, Y') : 'Open') . '</strong></li>'
        . '<li><span>Required hours</span><strong>' . e(format_hours($placement['required_hours'])) . '</strong></li>'
        . '</ul>';
    echo panel('My placement', 'Your assigned internship company and supervisor.', $details);
} else {
    echo panel('Placement pending', 'No active placement yet.',
        '<div class="empty-state">Your coordinator has not assigned you to a company yet.</div>');
}

// Upcoming deadlines
$deadlines = query_all(
    $db,
    "SELECT r.title, r.due_date,
        (SELECT status FROM document_submissions d
         WHERE d.requirement_id = r.id AND d.student_id = ? ORDER BY d.id DESC LIMIT 1) AS status
     FROM requirements r
     WHERE r.active = 1 AND r.archived = 0 AND r.due_date IS NOT NULL
     ORDER BY r.due_date ASC LIMIT 5",
    [$studentId]
);
$deadlineRows = [];
foreach ($deadlines as $row) {
    $status = $row['status'] ?: 'pending';
    $deadlineRows[] = [
        e($row['title']),
        e(format_datetime($row['due_date'], 'M j, Y')),
        status_badge($status),
    ];
}
echo panel('Upcoming deadlines', 'Requirements with a due date.',
    table_html(['Requirement', 'Due date', 'Status'], $deadlineRows, 'No deadlines have been set.'));

// Recent notifications
$notes = query_all($db, 'SELECT title, body, read_at, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 3', [(int)$user['id']]);
$noteRows = array_map(fn($n) => [e($n['title']), e($n['body']), e(format_datetime($n['created_at']))], $notes);
echo panel('Latest notifications', 'Recent updates for your workspace.',
    table_html(['Title', 'Details', 'Date'], $noteRows, 'You are all caught up.'));

echo panel('Quick actions', 'Common tasks.', '<div class="action-grid">'
    . '<a class="action-card" href="' . e(url('student/attendance.php')) . '"><strong>Record attendance</strong><span>Show your QR code to your instructor or supervisor.</span></a>'
    . '<a class="action-card" href="' . e(url('student/requirements.php')) . '"><strong>Submit requirements</strong><span>Upload documents for review.</span></a>'
    . '<a class="action-card" href="' . e(url('student/evaluations.php')) . '"><strong>View feedback</strong><span>See released supervisor evaluations.</span></a>'
    . '</div>');

require __DIR__ . '/../includes/footer.php';

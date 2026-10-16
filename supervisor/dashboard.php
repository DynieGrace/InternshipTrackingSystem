<?php
/**
 * Company Supervisor dashboard: assigned interns, attendance to verify
 * and evaluations to submit.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$user = require_role(['supervisor']);
$supervisor = supervisor_profile($db, (int)$user['id']);

$title = 'Overview';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';

if (!$supervisor) {
    echo panel('Supervisor profile missing', 'Your account needs a supervisor record.',
        '<p class="muted">Please contact the internship coordinator to finish setting up your account.</p>');
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$supId = (int)$supervisor['id'];

$internCount = (int)query_value($db, "SELECT COUNT(*) FROM internship_placements WHERE supervisor_id = ? AND status = 'active'", [$supId]);
$pendingAttendance = (int)query_value(
    $db,
    "SELECT COUNT(*) FROM attendance_records a
     JOIN internship_placements i ON i.id = a.placement_id
     WHERE i.supervisor_id = ? AND a.status = 'pending' AND a.time_out IS NOT NULL",
    [$supId]
);
$evaluationCount = (int)query_value($db, 'SELECT COUNT(*) FROM evaluations WHERE supervisor_id = ?', [$supId]);
$company = query_value($db, 'SELECT name FROM companies WHERE id = ?', [(int)($supervisor['company_id'] ?? 0)]);
$unread = (int)query_value($db, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [(int)$user['id']]);

echo '<div class="stats-grid">';
echo stat_card('Assigned interns', (string)$internCount, 'Active placements', '♙');
echo stat_card('Attendance to verify', (string)$pendingAttendance, 'Completed sessions pending review', '◷');
echo stat_card('Evaluations submitted', (string)$evaluationCount, 'Feedback records', '★');
echo stat_card('Notifications', (string)$unread, 'Unread updates', '◔');
echo '</div>';

echo panel('Placement', 'Your company and contact.',
    '<ul class="detail-list">'
    . '<li><span>Company</span><strong>' . e($company ?: 'Not assigned') . '</strong></li>'
    . '<li><span>Job title</span><strong>' . e($supervisor['job_title'] ?: '—') . '</strong></li>'
    . '</ul>');

echo panel('Quick actions', 'Common supervisor tasks.', '<div class="action-grid">'
    . '<a class="action-card" href="' . e(url('supervisor/interns.php')) . '"><strong>View assigned interns</strong><span>Only interns assigned to your account.</span></a>'
    . '<a class="action-card" href="' . e(url('supervisor/attendance.php')) . '"><strong>Verify attendance</strong><span>Approve rendered hours or flag records.</span></a>'
    . '<a class="action-card" href="' . e(url('supervisor/evaluations.php')) . '"><strong>Submit evaluations</strong><span>Score active interns against shared criteria.</span></a>'
    . '</div>');

require __DIR__ . '/../includes/footer.php';

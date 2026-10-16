<?php
/**
 * Coordinator dashboard: system-wide counts and the review queue.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$user = require_role(['coordinator']);

$title = 'Overview';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';

$students = (int)query_value($db, "SELECT COUNT(*) FROM student_profiles");
$companies = (int)query_value($db, "SELECT COUNT(*) FROM companies WHERE status = 'active'");
$placements = (int)query_value($db, "SELECT COUNT(*) FROM internship_placements WHERE status = 'active'");
$pendingDocs = (int)query_value($db, "SELECT COUNT(*) FROM document_submissions WHERE status = 'submitted'");
$pendingAttendance = (int)query_value($db, "SELECT COUNT(*) FROM attendance_records WHERE status = 'pending' AND time_out IS NOT NULL");
$unassigned = (int)query_value($db, "SELECT COUNT(*) FROM student_profiles sp WHERE NOT EXISTS (SELECT 1 FROM internship_placements i WHERE i.student_id = sp.id AND i.status = 'active')");

echo '<div class="stats-grid">';
echo stat_card('Students', (string)$students, 'Registered student interns', '♙');
echo stat_card('Companies', (string)$companies, 'Active partner companies', '⌂');
echo stat_card('Active placements', (string)$placements, 'Current internships', '⇄');
echo stat_card('Pending documents', (string)$pendingDocs, 'Awaiting review', '⇩');
echo stat_card('Attendance to verify', (string)$pendingAttendance, 'Completed sessions', '◷');
echo stat_card('Unplaced students', (string)$unassigned, 'Need a company assignment', '⚠');
echo '</div>';

echo panel('Review queue', 'Items that need your attention.', '<div class="action-grid">'
    . '<a class="action-card" href="' . e(url('coordinator/submissions.php')) . '"><strong>Review documents</strong><span>' . $pendingDocs . ' submission(s) waiting.</span></a>'
    . '<a class="action-card" href="' . e(url('coordinator/attendance.php')) . '"><strong>Verify attendance</strong><span>' . $pendingAttendance . ' completed session(s) pending.</span></a>'
    . '<a class="action-card" href="' . e(url('coordinator/placements.php')) . '"><strong>Manage placements</strong><span>' . $unassigned . ' student(s) without a company.</span></a>'
    . '</div>');

$recentDocs = query_all(
    $db,
    "SELECT d.original_name, d.submitted_at, r.title, sp.student_number, u.full_name
    FROM document_submissions d
    JOIN requirements r ON r.id = d.requirement_id
    JOIN student_profiles sp ON sp.id = d.student_id
    JOIN users u ON u.id = sp.user_id
    WHERE d.status = 'submitted'
    ORDER BY d.submitted_at DESC LIMIT 5"
);
$docRows = array_map(fn($d) => [
    e($d['title']),
    e($d['student_number']) . ' · ' . e($d['full_name']),
    e($d['original_name']),
    e(format_datetime($d['submitted_at'])),
], $recentDocs);
echo panel('Recent submissions', 'Newest first.', table_html(['Requirement', 'Student', 'File', 'Submitted'], $docRows, 'No pending submissions.'));

require __DIR__ . '/../includes/footer.php';

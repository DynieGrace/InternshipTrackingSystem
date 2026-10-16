<?php
/**
 * Supervisors see only the interns assigned to them, with progress.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$user = require_role(['supervisor']);
$supervisor = supervisor_profile($db, (int)$user['id']);

$title = 'Assigned Interns';
$active = 'interns';
require __DIR__ . '/../includes/header.php';

if (!$supervisor) {
    echo panel('Assigned interns', 'Supervisor profile missing.',
        '<div class="empty-state">Your supervisor profile is not set up yet.</div>');
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$interns = query_all(
    $db,
    "SELECT i.id AS placement_id, i.start_date, i.end_date, i.required_hours, i.status,
            sp.id AS student_id, sp.student_number, sp.course, sp.year_level, sp.phone,
            u.full_name, u.email, c.name AS company
     FROM internship_placements i
     JOIN student_profiles sp ON sp.id = i.student_id
     JOIN users u ON u.id = sp.user_id
     JOIN companies c ON c.id = i.company_id
     WHERE i.supervisor_id = ? AND i.status = 'active'
     ORDER BY u.full_name",
    [(int)$supervisor['id']]
);

$rows = [];
foreach ($interns as $intern) {
    $totals = attendance_totals($db, (int)$intern['student_id']);
    [$remaining, $percent] = progress_values($totals['approved'], (float)$intern['required_hours']);
    $progress = '<div class="progress-track" style="min-width:120px"><span style="width:' . number_format($percent, 2, '.', '') . '%"></span></div>'
        . '<span class="help-text">' . number_format($percent, 1) . '%</span>';
    $rows[] = [
        e($intern['student_number']),
        '<strong>' . e($intern['full_name']) . '</strong><div class="help-text">' . e($intern['email']) . '</div>',
        e($intern['course']) . '<div class="help-text">' . e($intern['year_level']) . '</div>',
        e(format_hours($totals['approved'])) . ' / ' . e(format_hours($intern['required_hours'])),
        $progress,
        e(format_datetime($intern['start_date'], 'M j, Y')),
    ];
}

echo panel('Assigned interns', 'Only interns assigned to your supervisor account are shown.',
    table_html(['Student ID', 'Student', 'Course', 'Approved / required', 'Progress', 'Started'], $rows,
        'No interns are currently assigned to you.'));

require __DIR__ . '/../includes/footer.php';

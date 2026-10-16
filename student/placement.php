<?php
/**
 * Student placement: assigned company, supervisor and required hours.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$user = require_role(['student']);
$student = student_profile($db, (int)$user['id']);

$title = 'Placement';
$active = 'placement';
require __DIR__ . '/../includes/header.php';

if (!$student) {
    echo panel('Placement unavailable', 'Student profile missing.',
        '<div class="empty-state">Your student profile is not set up yet.</div>');
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$placement = active_placement($db, (int)$student['id']);

if (!$placement) {
    echo panel('Internship placement', 'No active placement yet.',
        '<div class="empty-state">Your coordinator has not assigned you to a company. Please check back later.</div>');
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$totals = attendance_totals($db, (int)$student['id']);
[$remaining, $percent] = progress_values($totals['approved'], (float)$placement['required_hours']);

$details = '<ul class="detail-list">'
    . '<li><span>Company</span><strong>' . e($placement['company']) . '</strong></li>'
    . '<li><span>Company address</span><strong>' . e($placement['company_address'] ?: '—') . '</strong></li>'
    . '<li><span>Company contact</span><strong>' . e($placement['contact_person'] ?: '—')
        . ($placement['contact_email'] ? ' · ' . e($placement['contact_email']) : '') . '</strong></li>'
    . '<li><span>Supervisor</span><strong>' . e($placement['supervisor_name'] ?: 'Not assigned')
        . ($placement['supervisor_title'] ? ' · ' . e($placement['supervisor_title']) : '') . '</strong></li>'
    . '<li><span>Supervisor email</span><strong>' . e($placement['supervisor_email'] ?: '—') . '</strong></li>'
    . '<li><span>Start date</span><strong>' . e(format_datetime($placement['start_date'], 'F j, Y')) . '</strong></li>'
    . '<li><span>End date</span><strong>' . e($placement['end_date'] ? format_datetime($placement['end_date'], 'F j, Y') : 'Open-ended') . '</strong></li>'
    . '<li><span>Required hours</span><strong>' . e(format_hours($placement['required_hours'])) . '</strong></li>'
    . '<li><span>Approved hours</span><strong>' . e(format_hours($totals['approved'])) . '</strong></li>'
    . '<li><span>Hours remaining</span><strong>' . e(format_hours($remaining)) . '</strong></li>'
    . '<li><span>Status</span><strong>' . status_badge($placement['status']) . '</strong></li>'
    . '</ul>';

echo '<div class="two-col">'
    . panel('Internship placement', 'Your assigned company and supervisor.', $details)
    . '<section class="panel progress-panel"><div class="panel-title"><div><h2>Progress</h2><p>Approved hours of your required total.</p></div><strong>'
        . number_format($percent, 1) . '%</strong></div><div class="progress-track"><span style="width:'
        . number_format($percent, 2, '.', '') . '%"></span></div></section>'
    . '</div>';

require __DIR__ . '/../includes/footer.php';

<?php
/**
 * Personal student ID card + attendance QR for the signed-in student.
 * The student displays it; the coordinator or supervisor scans it.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$user = require_role(['student']);
$profile = student_profile($db, (int)$user['id']);
$identity = $profile ? student_identity($db, (int)$profile['id']) : null;

$title = 'MyQR';
$active = 'my-qr';
require __DIR__ . '/../includes/header.php';

if (!$identity) {
    echo panel('ID card unavailable', 'Your student profile is not set up yet.',
        '<div class="empty-state">Please contact your internship coordinator to finish setting up your account.</div>');
} else {
    $student = $identity['student'];
    $actions = '<div class="id-actions">'
        . '<button class="button primary" type="button" data-download-id>Download ID card (PNG)</button>'
        . '<span class="help-text">Save it on your phone and show it to your coordinator or supervisor.</span>'
        . '</div>';

    $note = '<p class="help-text student-id-note"><strong>How it works:</strong> your instructor (coordinator) or your '
        . 'company supervisor / authorized personnel scans this card to record your time in and time out. '
        . 'You do not scan it yourself. If the camera cannot read it, give them the token below.</p>'
        . '<code class="student-id-token">' . e((string)$student['attendance_token']) . '</code>';

    echo panel(
        'My student ID',
        'Your digital intern ID. The QR beside your name is what gets scanned for attendance.',
        $actions . student_id_card($identity, ['qr_id' => 'my-id-qr']) . $note
    );
}

require __DIR__ . '/../includes/footer.php';

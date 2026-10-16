<?php
/**
 * Student evaluations: feedback released by the coordinator.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$user = require_role(['student']);
$student = student_profile($db, (int)$user['id']);

$title = 'Evaluations';
$active = 'evaluations';
require __DIR__ . '/../includes/header.php';

if (!$student) {
    echo panel('Evaluations unavailable', 'Student profile missing.',
        '<div class="empty-state">Your student profile is not set up yet.</div>');
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$evaluations = query_all(
    $db,
    "SELECT e.id, e.evaluated_at, e.comments, sup_user.full_name AS supervisor
     FROM evaluations e
     LEFT JOIN supervisors sup ON sup.id = e.supervisor_id
     LEFT JOIN users sup_user ON sup_user.id = sup.user_id
     WHERE e.student_id = ? AND e.released = 1
     ORDER BY e.evaluated_at DESC",
    [(int)$student['id']]
);

if (!$evaluations) {
    echo panel('My evaluations', 'Only evaluations released by the coordinator are shown.',
        '<div class="empty-state">No evaluations have been released yet.</div>');
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$cards = '';
foreach ($evaluations as $evaluation) {
    $scores = query_all(
        $db,
        'SELECT c.name, s.rating, c.weight FROM evaluation_scores s
         JOIN evaluation_criteria c ON c.id = s.criteria_id
         WHERE s.evaluation_id = ? ORDER BY c.id',
        [(int)$evaluation['id']]
    );
    $weighted = 0.0;
    $weightTotal = 0.0;
    $criteriaRows = [];
    foreach ($scores as $score) {
        $weighted += (float)$score['rating'] * (float)$score['weight'];
        $weightTotal += (float)$score['weight'];
        $criteriaRows[] = [e($score['name']), e($score['weight']), e($score['rating']) . ' / 5'];
    }
    $average = $weightTotal > 0 ? number_format($weighted / $weightTotal, 2) . ' / 5' : '—';

    $card = '<ul class="detail-list">'
        . '<li><span>Supervisor</span><strong>' . e($evaluation['supervisor'] ?: 'Company supervisor') . '</strong></li>'
        . '<li><span>Date</span><strong>' . e(format_datetime($evaluation['evaluated_at'])) . '</strong></li>'
        . '<li><span>Weighted average</span><strong>' . e($average) . '</strong></li>'
        . '</ul>'
        . table_html(['Criterion', 'Weight', 'Rating'], $criteriaRows)
        . '<p class="help-text"><strong>Comments:</strong> ' . e($evaluation['comments'] ?: '—') . '</p>';

    $cards .= panel('Evaluation · ' . format_datetime($evaluation['evaluated_at'], 'M j, Y'), 'Released feedback.', $card);
}

echo $cards;

require __DIR__ . '/../includes/footer.php';

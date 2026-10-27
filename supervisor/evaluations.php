<?php
/**
 * Supervisor evaluations: score assigned interns against the shared
 * criteria and review previously submitted evaluations.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$user = require_role(['supervisor']);
$supervisor = supervisor_profile($db, (int)$user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if (!$supervisor || ($_POST['op'] ?? '') !== 'save_evaluation') {
            throw new InvalidArgumentException('The evaluation could not be saved.');
        }
        $placementId = post_id('placement_id');
        $placement = query_one(
            $db,
            "SELECT id, student_id FROM internship_placements
            WHERE id = ? AND supervisor_id = ? AND status = 'active'",
            [$placementId, (int)$supervisor['id']]
        );
        if (!$placement) {
            throw new InvalidArgumentException('That intern is not assigned to you.');
        }

        $criteria = query_all($db, 'SELECT id FROM evaluation_criteria WHERE active = 1 ORDER BY id');
        if (!$criteria) {
            throw new InvalidArgumentException('No active evaluation criteria are configured.');
        }

        $db->beginTransaction();
        $db->prepare('INSERT INTO evaluations (placement_id, student_id, supervisor_id, comments) VALUES (?, ?, ?, ?)')
            ->execute([$placementId, (int)$placement['student_id'], (int)$supervisor['id'], post_string('comments', 5000)]);
        $evaluationId = (int)$db->lastInsertId();

        $scoreInsert = $db->prepare('INSERT INTO evaluation_scores (evaluation_id, criteria_id, rating) VALUES (?, ?, ?)');
        foreach ($criteria as $criterion) {
            $rating = filter_var($_POST['rating'][$criterion['id']] ?? null, FILTER_VALIDATE_INT);
            if (!$rating || $rating < 1 || $rating > 5) {
                throw new InvalidArgumentException('Please rate every criterion from 1 to 5.');
            }
            $scoreInsert->execute([$evaluationId, (int)$criterion['id'], (int)$rating]);
        }
        $db->commit();

        $studentUserId = query_value($db, 'SELECT user_id FROM student_profiles WHERE id = ?', [(int)$placement['student_id']]);
        if ($studentUserId) {
            notify_user($db, (int)$studentUserId, 'New supervisor evaluation',
                'Your company supervisor submitted an evaluation. It becomes visible once released.');
        }
        record_activity($db, (int)$user['id'], 'save_evaluation', 'placement #' . $placementId);
        flash_redirect('supervisor/evaluations.php', 'Evaluation submitted.');
    } catch (InvalidArgumentException $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        flash('error', $error->getMessage());
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('ITS supervisor evaluation failed: ' . $error->getMessage());
        flash('error', 'The evaluation could not be saved.');
    }
    redirect('supervisor/evaluations.php');
}

$title = 'Evaluations';
$active = 'evaluations';
require __DIR__ . '/../includes/header.php';

if (!$supervisor) {
    echo panel('Evaluations', 'Supervisor profile missing.',
        '<div class="empty-state">Your supervisor profile is not set up yet.</div>');
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$assignments = query_all(
    $db,
    "SELECT i.id, CONCAT(sp.student_number, ' · ', u.full_name) AS label
    FROM internship_placements i
    JOIN student_profiles sp ON sp.id = i.student_id
    JOIN users u ON u.id = sp.user_id
    WHERE i.supervisor_id = ? AND i.status = 'active'
    ORDER BY u.full_name",
    [(int)$supervisor['id']]
);
$criteria = query_all($db, 'SELECT id, name, weight FROM evaluation_criteria WHERE active = 1 ORDER BY id');

$ratingFields = '';
foreach ($criteria as $criterion) {
    $ratingFields .= '<label>' . e($criterion['name']) . ' (1–5)'
        . '<select name="rating[' . (int)$criterion['id'] . ']" required><option value="">Rate</option>'
        . '<option>1</option><option>2</option><option>3</option><option>4</option><option>5</option></select></label>';
}

$form = form_start('supervisor/evaluations.php')
    . '<input type="hidden" name="op" value="save_evaluation">'
    . '<label>Assigned intern<select name="placement_id" required><option value="">Choose intern</option>'
    . select_options($assignments, 'id', 'label') . '</select></label>'
    . '<div class="form-grid">' . $ratingFields . '</div>'
    . '<label>Comments<textarea name="comments" rows="4" maxlength="5000"></textarea></label>'
    . '<button class="button primary">Submit evaluation</button></form>';

echo panel('Submit an intern evaluation', 'Ratings and comments are saved to the database and released by the coordinator.', $form);

$evaluations = query_all(
    $db,
    "SELECT e.id, e.evaluated_at, e.comments, e.released, sp.student_number, u.full_name
    FROM evaluations e
    JOIN student_profiles sp ON sp.id = e.student_id
    JOIN users u ON u.id = sp.user_id
    WHERE e.supervisor_id = ?
    ORDER BY e.evaluated_at DESC",
    [(int)$supervisor['id']]
);

$rows = [];
foreach ($evaluations as $evaluation) {
    $scores = query_all(
        $db,
        'SELECT c.name, s.rating, c.weight FROM evaluation_scores s
        JOIN evaluation_criteria c ON c.id = s.criteria_id WHERE s.evaluation_id = ?',
        [(int)$evaluation['id']]
    );
    $weighted = 0.0;
    $weightTotal = 0.0;
    foreach ($scores as $score) {
        $weighted += (float)$score['rating'] * (float)$score['weight'];
        $weightTotal += (float)$score['weight'];
    }
    $average = $weightTotal > 0 ? number_format($weighted / $weightTotal, 2) . ' / 5' : '—';
    $rows[] = [
        e($evaluation['student_number']) . '<div class="help-text">' . e($evaluation['full_name']) . '</div>',
        e(format_datetime($evaluation['evaluated_at'])),
        e($average),
        e($evaluation['comments'] ?: '—'),
        $evaluation['released'] ? status_badge('approved') : status_badge('pending'),
    ];
}

echo panel('Evaluation history', 'Everything you have submitted.', 
    table_html(['Student', 'Date', 'Weighted average', 'Comments', 'Release status'], $rows, 'No evaluations submitted yet.'));

require __DIR__ . '/../includes/footer.php';

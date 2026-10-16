<?php
/**
 * Coordinator: view supervisor evaluations, release them to students, and
 * manage the shared evaluation criteria.
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
        $op = (string)($_POST['op'] ?? '');

        if ($op === 'release_evaluation') {
            $id = post_id('evaluation_id');
            $evaluation = query_one(
                $db,
                'SELECT e.id, e.student_id, sp.user_id FROM evaluations e
                JOIN student_profiles sp ON sp.id = e.student_id WHERE e.id = ?',
                [$id]
            );
            if (!$evaluation) {
                throw new InvalidArgumentException('That evaluation does not exist.');
            }
            $db->prepare('UPDATE evaluations SET released = 1 WHERE id = ?')->execute([$id]);
            notify_user($db, (int)$evaluation['user_id'], 'Evaluation released',
                'Your supervisor evaluation is now available in the Evaluations page.');
            record_activity($db, (int)$user['id'], 'release_evaluation', '#' . $id);
            flash_redirect('coordinator/evaluations.php', 'Evaluation released to the student.');
        }

        if ($op === 'add_criterion') {
            $name = post_string('name', 120);
            $weight = filter_var($_POST['weight'] ?? 1, FILTER_VALIDATE_FLOAT);
            if ($name === '' || $weight === false || $weight < 0) {
                throw new InvalidArgumentException('Enter a criterion name and a weight of zero or more.');
            }
            if (query_value($db, 'SELECT id FROM evaluation_criteria WHERE name = ? LIMIT 1', [$name])) {
                throw new InvalidArgumentException('That criterion already exists.');
            }
            $db->prepare('INSERT INTO evaluation_criteria (name, weight) VALUES (?, ?)')->execute([$name, $weight]);
            record_activity($db, (int)$user['id'], 'add_criterion', $name);
            flash_redirect('coordinator/evaluations.php', 'Criterion added.');
        }

        if ($op === 'set_criterion') {
            $id = post_id('criteria_id');
            $active = (string)($_POST['active'] ?? '1') === '1' ? 1 : 0;
            $db->prepare('UPDATE evaluation_criteria SET active = ? WHERE id = ?')->execute([$active, $id]);
            record_activity($db, (int)$user['id'], 'criterion_status', ($active ? 'activate' : 'deactivate') . ' #' . $id);
            flash_redirect('coordinator/evaluations.php', 'Criterion updated.');
        }

        throw new InvalidArgumentException('Unknown action.');
    } catch (InvalidArgumentException $error) {
        flash('error', $error->getMessage());
    } catch (Throwable $error) {
        error_log('ITS coordinator evaluations failed: ' . $error->getMessage());
        flash('error', 'The evaluation action could not be completed.');
    }
    redirect('coordinator/evaluations.php');
}

$title = 'Evaluations';
$active = 'evaluations';
require __DIR__ . '/../includes/header.php';

// Criteria management
$criteria = query_all($db, 'SELECT * FROM evaluation_criteria ORDER BY id');
$criteriaRows = [];
foreach ($criteria as $criterion) {
    $toggle = $criterion['active']
        ? '<form method="post" action="' . e(url('coordinator/evaluations.php')) . '" class="inline-review">' . csrf_field()
            . '<input type="hidden" name="op" value="set_criterion"><input type="hidden" name="criteria_id" value="' . (int)$criterion['id'] . '">'
            . '<input type="hidden" name="active" value="0"><button class="button small secondary">Deactivate</button></form>'
        : '<form method="post" action="' . e(url('coordinator/evaluations.php')) . '" class="inline-review">' . csrf_field()
            . '<input type="hidden" name="op" value="set_criterion"><input type="hidden" name="criteria_id" value="' . (int)$criterion['id'] . '">'
            . '<input type="hidden" name="active" value="1"><button class="button small">Activate</button></form>';
    $criteriaRows[] = [e($criterion['name']), e($criterion['weight']), status_badge($criterion['active'] ? 'active' : 'inactive'), $toggle];
}

$criteriaForm = form_start('coordinator/evaluations.php')
    . '<input type="hidden" name="op" value="add_criterion">'
    . '<div class="form-grid"><label>Criterion name<input name="name" maxlength="120" required></label>'
    . '<label>Weight<input type="number" name="weight" value="1" step="0.5" min="0" required></label></div>'
    . '<button class="button primary">Add criterion</button></form>';

echo '<div class="two-col">'
    . panel('Evaluation criteria', 'Used by supervisors when scoring interns.', $criteriaForm
        . table_html(['Criterion', 'Weight', 'Status', 'Action'], $criteriaRows, 'No criteria configured.'))
    . '</div>';

// Evaluations
$evaluations = query_all(
    $db,
    'SELECT e.id, e.evaluated_at, e.comments, e.released, sp.student_number, u.full_name,
            sup_user.full_name AS supervisor
    FROM evaluations e
    JOIN student_profiles sp ON sp.id = e.student_id
    JOIN users u ON u.id = sp.user_id
    LEFT JOIN supervisors sup ON sup.id = e.supervisor_id
    LEFT JOIN users sup_user ON sup_user.id = sup.user_id
    ORDER BY e.evaluated_at DESC'
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
    $release = $evaluation['released']
        ? status_badge('approved')
        : form_start('coordinator/evaluations.php', ['class' => 'inline-review'])
            . '<input type="hidden" name="op" value="release_evaluation">'
            . '<input type="hidden" name="evaluation_id" value="' . (int)$evaluation['id'] . '">'
            . '<button class="button small">Release</button></form>';
    $rows[] = [
        e($evaluation['student_number']) . '<div class="help-text">' . e($evaluation['full_name']) . '</div>',
        e($evaluation['supervisor'] ?: '—'),
        e(format_datetime($evaluation['evaluated_at'])),
        e($average),
        e($evaluation['comments'] ?: '—'),
        $release,
    ];
}

echo panel('Supervisor evaluations', 'Students only see evaluations you release.', 
    table_html(['Student', 'Supervisor', 'Date', 'Weighted average', 'Comments', 'Release'], $rows, 'No evaluations submitted yet.'));

require __DIR__ . '/../includes/footer.php';

<?php
/**
 * Coordinator: create, review and archive internship placements.
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

        if ($op === 'create_placement') {
            $studentId = post_id('student_id');
            $companyId = post_id('company_id');
            $supervisorId = post_optional_id('supervisor_id');
            $requiredHours = filter_var($_POST['required_hours'] ?? null, FILTER_VALIDATE_FLOAT);
            $start = post_string('start_date', 10);
            $end = post_string('end_date', 10) ?: null;

            if (!query_one($db, 'SELECT id FROM student_profiles WHERE id = ?', [$studentId])
                || !query_one($db, 'SELECT id FROM companies WHERE id = ?', [$companyId])) {
                throw new InvalidArgumentException('Choose a valid student and company.');
            }
            if (query_one($db, "SELECT id FROM internship_placements WHERE student_id = ? AND status = 'active'", [$studentId])) {
                throw new InvalidArgumentException('That student already has an active placement.');
            }
            if (!$requiredHours || $requiredHours <= 0 || !valid_datetime($start)
                || ($end && (!valid_datetime($end) || $end < $start))) {
                throw new InvalidArgumentException('Enter a valid start date, an optional end date after it, and a required-hours value above zero.');
            }

            $db->prepare(
                'INSERT INTO internship_placements (student_id, company_id, supervisor_id, start_date, end_date, required_hours, status)
                 VALUES (?, ?, ?, ?, ?, ?, "active")'
            )->execute([$studentId, $companyId, $supervisorId, $start, $end, $requiredHours]);

            $studentUserId = query_value($db, 'SELECT user_id FROM student_profiles WHERE id = ?', [$studentId]);
            if ($studentUserId) {
                notify_user($db, (int)$studentUserId, 'Internship placement assigned',
                    'Your coordinator assigned a company and supervisor. View the details in your Placement page.');
            }
            record_activity($db, (int)$user['id'], 'create_placement', 'student #' . $studentId);
            flash_redirect('coordinator/placements.php', 'Placement saved.');
        }

        if ($op === 'update_placement') {
            $id = post_id('placement_id');
            $supervisorId = post_optional_id('supervisor_id');
            $requiredHours = filter_var($_POST['required_hours'] ?? null, FILTER_VALIDATE_FLOAT);
            $start = post_string('start_date', 10);
            $end = post_string('end_date', 10) ?: null;
            if (!$requiredHours || $requiredHours <= 0 || !valid_datetime($start)
                || ($end && (!valid_datetime($end) || $end < $start))) {
                throw new InvalidArgumentException('Enter valid dates and required hours.');
            }
            $db->prepare('UPDATE internship_placements SET supervisor_id = ?, required_hours = ?, start_date = ?, end_date = ? WHERE id = ?')
                ->execute([$supervisorId, $requiredHours, $start, $end, $id]);
            record_activity($db, (int)$user['id'], 'update_placement', '#' . $id);
            flash_redirect('coordinator/placements.php', 'Placement updated.');
        }

        if ($op === 'set_status') {
            $id = post_id('placement_id');
            $status = post_string('status', 12);
            if (!in_array($status, ['active', 'completed', 'inactive'], true)) {
                throw new InvalidArgumentException('Invalid placement status.');
            }
            $db->prepare('UPDATE internship_placements SET status = ? WHERE id = ?')->execute([$status, $id]);
            record_activity($db, (int)$user['id'], 'placement_status', $status . ' #' . $id);
            flash_redirect('coordinator/placements.php', 'Placement status updated.');
        }

        if ($op === 'archive_placement') {
            $id = post_id('placement_id');
            $db->prepare('UPDATE internship_placements SET archived = 1 WHERE id = ?')->execute([$id]);
            record_activity($db, (int)$user['id'], 'archive_placement', '#' . $id);
            flash_redirect('coordinator/placements.php', 'Placement archived.');
        }

        throw new InvalidArgumentException('Unknown action.');
    } catch (InvalidArgumentException $error) {
        flash('error', $error->getMessage());
    } catch (Throwable $error) {
        error_log('ITS coordinator placements failed: ' . $error->getMessage());
        flash('error', 'The placement could not be saved.');
    }
    redirect('coordinator/placements.php');
}

$title = 'Placements';
$active = 'placements';
require __DIR__ . '/../includes/header.php';

$editId = (int)($_GET['edit'] ?? 0);

if ($editId) {
    $placement = query_one(
        $db,
        'SELECT i.*, sp.student_number, u.full_name FROM internship_placements i
         JOIN student_profiles sp ON sp.id = i.student_id JOIN users u ON u.id = sp.user_id
         WHERE i.id = ?',
        [$editId]
    );
    $supervisors = query_all($db, 'SELECT s.id, CONCAT(u.full_name, IFNULL(CONCAT(" · ", c.name), "")) AS label FROM supervisors s JOIN users u ON u.id = s.user_id LEFT JOIN companies c ON c.id = s.company_id ORDER BY u.full_name');
    if ($placement) {
        echo panel('Edit placement', e($placement['student_number']) . ' · ' . e($placement['full_name']),
            form_start('coordinator/placements.php')
            . '<input type="hidden" name="op" value="update_placement">'
            . '<input type="hidden" name="placement_id" value="' . (int)$placement['id'] . '">'
            . '<div class="form-grid">'
            . '<label>Supervisor<select name="supervisor_id"><option value="">Not assigned</option>' . select_options($supervisors, 'id', 'label', (string)($placement['supervisor_id'] ?? '')) . '</select></label>'
            . '<label>Required hours<input type="number" min="1" step="0.5" name="required_hours" value="' . e($placement['required_hours']) . '" required></label>'
            . '<label>Start date<input type="date" name="start_date" value="' . e($placement['start_date']) . '" required></label>'
            . '<label>End date<input type="date" name="end_date" value="' . e($placement['end_date'] ?? '') . '"></label>'
            . '</div><div class="stack-actions"><button class="button primary">Save changes</button>'
            . '<a class="button secondary" href="' . e(url('coordinator/placements.php')) . '">Cancel</a></div></form>');
        require __DIR__ . '/../includes/footer.php';
        exit;
    }
}

$unplaced = query_all(
    $db,
    "SELECT sp.id, CONCAT(sp.student_number, ' · ', u.full_name) AS label
     FROM student_profiles sp JOIN users u ON u.id = sp.user_id
     WHERE NOT EXISTS (SELECT 1 FROM internship_placements i WHERE i.student_id = sp.id AND i.status = 'active')
     ORDER BY sp.student_number"
);
$companies = query_all($db, 'SELECT id, name FROM companies WHERE status = "active" ORDER BY name');
$supervisors = query_all($db, 'SELECT s.id, CONCAT(u.full_name, IFNULL(CONCAT(" · ", c.name), "")) AS label FROM supervisors s JOIN users u ON u.id = s.user_id LEFT JOIN companies c ON c.id = s.company_id ORDER BY u.full_name');

echo panel('Create a placement', 'Assign an unplaced student to a company and supervisor.',
    form_start('coordinator/placements.php')
    . '<input type="hidden" name="op" value="create_placement">'
    . '<div class="form-grid">'
    . '<label>Student<select name="student_id" required><option value="">Choose student</option>' . select_options($unplaced, 'id', 'label') . '</select></label>'
    . '<label>Company<select name="company_id" required><option value="">Choose company</option>' . select_options($companies, 'id', 'name') . '</select></label>'
    . '<label>Supervisor<select name="supervisor_id"><option value="">Not assigned</option>' . select_options($supervisors, 'id', 'label') . '</select></label>'
    . '<label>Required hours<input type="number" min="1" step="0.5" name="required_hours" value="486" required></label>'
    . '<label>Start date<input type="date" name="start_date" value="' . e(date('Y-m-d')) . '" required></label>'
    . '<label>End date<input type="date" name="end_date"></label>'
    . '</div><button class="button primary">Save placement</button></form>');

$placements = query_all(
    $db,
    'SELECT i.id, i.start_date, i.end_date, i.required_hours, i.status, i.archived,
            sp.id AS student_id, sp.student_number, u.full_name, c.name AS company,
            sup_user.full_name AS supervisor
     FROM internship_placements i
     JOIN student_profiles sp ON sp.id = i.student_id
     JOIN users u ON u.id = sp.user_id
     JOIN companies c ON c.id = i.company_id
     LEFT JOIN supervisors sup ON sup.id = i.supervisor_id
     LEFT JOIN users sup_user ON sup_user.id = sup.user_id
     WHERE i.archived = 0
     ORDER BY i.start_date DESC'
);

$rows = [];
foreach ($placements as $row) {
    $totals = attendance_totals($db, (int)$row['student_id']);
    [, $percent] = progress_values($totals['approved'], (float)$row['required_hours']);
    $actions = '<a class="button small" href="' . e(url('coordinator/placements.php?edit=' . (int)$row['id'])) . '">Edit</a> '
        . '<form method="post" action="' . e(url('coordinator/placements.php')) . '" class="inline-review" data-confirm="Archive this placement?">' . csrf_field()
        . '<input type="hidden" name="op" value="archive_placement"><input type="hidden" name="placement_id" value="' . (int)$row['id'] . '">'
        . '<button class="button small secondary">Archive</button></form>';
    $rows[] = [
        e($row['student_number']) . '<div class="help-text">' . e($row['full_name']) . '</div>',
        e($row['company']),
        e($row['supervisor'] ?: '—'),
        e(format_datetime($row['start_date'], 'M j, Y')) . ' – ' . e($row['end_date'] ? format_datetime($row['end_date'], 'M j, Y') : 'open'),
        e(format_hours($totals['approved'])) . ' / ' . e(format_hours($row['required_hours'])) . '<div class="help-text">' . number_format($percent, 1) . '%</div>',
        status_badge($row['status']),
        $actions,
    ];
}

echo '<div class="toolbar"><input type="search" class="grow" placeholder="Search placements…" data-table-filter="#placements-table"></div>';
echo '<div id="placements-table">' . table_html(['Student', 'Company', 'Supervisor', 'Period', 'Hours', 'Status', 'Actions'], $rows, 'No placements have been created.') . '</div>';

require __DIR__ . '/../includes/footer.php';

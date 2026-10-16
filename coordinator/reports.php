<?php
/**
 * Coordinator: printable reports with date filters and CSV export.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$user = require_role(['coordinator']);

$report = (string)($_GET['report'] ?? 'students');
$from = (string)($_GET['from'] ?? '');
$to = (string)($_GET['to'] ?? '');
$from = valid_datetime($from) ? $from : '';
$to = valid_datetime($to) ? $to : '';

$titles = [
    'students'     => 'Student internship profiles',
    'attendance'   => 'Attendance records',
    'hours'        => 'Rendered internship hours',
    'requirements' => 'Requirement completion',
    'progress'     => 'Internship progress',
    'evaluations'  => 'Supervisor evaluations',
];
if (!isset($titles[$report])) {
    $report = 'students';
}

/**
 * Build [headers, rows] for a report type. $from/$to filter by date where
 * a report has a relevant date column.
 */
function build_report(PDO $db, string $report, string $from, string $to): array
{
    $dateFilter = '';
    $params = [];
    if ($from !== '') {
        $dateFilter .= ' AND DATE(a.time_in) >= ?';
        $params[] = $from;
    }
    if ($to !== '') {
        $dateFilter .= ' AND DATE(a.time_in) <= ?';
        $params[] = $to;
    }

    switch ($report) {
        case 'attendance':
            $sql = "SELECT sp.student_number, u.full_name, a.time_in, a.time_out, a.rendered_hours, a.status
                    FROM attendance_records a
                    JOIN student_profiles sp ON sp.id = a.student_id
                    JOIN users u ON u.id = sp.user_id
                    WHERE 1 = 1 $dateFilter
                    ORDER BY a.time_in DESC";
            $rows = array_map(fn($r) => [
                $r['student_number'], $r['full_name'], format_datetime($r['time_in']),
                $r['time_out'] ? format_datetime($r['time_out']) : 'In progress',
                format_hours($r['rendered_hours']), ucfirst($r['status']),
            ], query_all($db, $sql, $params));
            return [['Student ID', 'Student', 'Time in', 'Time out', 'Hours', 'Status'], $rows];

        case 'hours':
            $sql = "SELECT sp.student_number, u.full_name,
                        COALESCE(SUM(CASE WHEN a.status = 'approved' THEN a.rendered_hours END), 0) AS approved,
                        COALESCE(SUM(CASE WHEN a.status = 'pending' THEN a.rendered_hours END), 0) AS pending
                    FROM student_profiles sp
                    JOIN users u ON u.id = sp.user_id
                    LEFT JOIN attendance_records a ON a.student_id = sp.id AND a.time_out IS NOT NULL $dateFilter
                    GROUP BY sp.id
                    ORDER BY approved DESC";
            $rows = array_map(fn($r) => [
                $r['student_number'], $r['full_name'], format_hours($r['approved']), format_hours($r['pending']),
            ], query_all($db, $sql, $params));
            return [['Student ID', 'Student', 'Approved hours', 'Pending hours'], $rows];

        case 'requirements':
            $sql = "SELECT sp.student_number, u.full_name,
                        (SELECT COUNT(*) FROM requirements r WHERE r.active = 1 AND r.archived = 0) AS total,
                        (SELECT COUNT(*) FROM document_submissions d WHERE d.student_id = sp.id AND d.status = 'approved') AS approved,
                        (SELECT COUNT(*) FROM document_submissions d WHERE d.student_id = sp.id AND d.status = 'submitted') AS pending
                    FROM student_profiles sp JOIN users u ON u.id = sp.user_id
                    ORDER BY sp.student_number";
            $rows = array_map(fn($r) => [
                $r['student_number'], $r['full_name'], $r['approved'] . ' / ' . $r['total'],
                $r['pending'],
            ], query_all($db, $sql));
            return [['Student ID', 'Student', 'Approved / required', 'Submitted (pending review)'], $rows];

        case 'progress':
            $sql = "SELECT sp.student_number, u.full_name, c.name AS company,
                        i.required_hours,
                        COALESCE((SELECT SUM(a.rendered_hours) FROM attendance_records a
                                WHERE a.student_id = sp.id AND a.status = 'approved'), 0) AS approved
                    FROM internship_placements i
                    JOIN student_profiles sp ON sp.id = i.student_id
                    JOIN users u ON u.id = sp.user_id
                    JOIN companies c ON c.id = i.company_id
                    WHERE i.archived = 0
                    ORDER BY sp.student_number";
            $rows = [];
            foreach (query_all($db, $sql) as $r) {
                [, $percent] = progress_values((float)$r['approved'], (float)$r['required_hours']);
                $rows[] = [$r['student_number'], $r['full_name'], $r['company'],
                    format_hours($r['approved']) . ' / ' . format_hours($r['required_hours']),
                    number_format($percent, 1) . '%'];
            }
            return [['Student ID', 'Student', 'Company', 'Approved / required', 'Progress'], $rows];

        case 'evaluations':
            $sql = "SELECT sp.student_number, u.full_name, sup_user.full_name AS supervisor, e.evaluated_at, e.released,
                        (SELECT ROUND(AVG(s.rating), 2) FROM evaluation_scores s WHERE s.evaluation_id = e.id) AS avg_rating
                    FROM evaluations e
                    JOIN student_profiles sp ON sp.id = e.student_id
                    JOIN users u ON u.id = sp.user_id
                    LEFT JOIN supervisors sup ON sup.id = e.supervisor_id
                    LEFT JOIN users sup_user ON sup_user.id = sup.user_id
                    ORDER BY e.evaluated_at DESC";
            $rows = array_map(fn($r) => [
                $r['student_number'], $r['full_name'], $r['supervisor'] ?: '—',
                format_datetime($r['evaluated_at']), $r['avg_rating'] !== null ? $r['avg_rating'] . ' / 5' : '—',
                $r['released'] ? 'Released' : 'Pending',
            ], query_all($db, $sql));
            return [['Student ID', 'Student', 'Supervisor', 'Date', 'Average', 'Visibility'], $rows];

        case 'students':
        default:
            $sql = "SELECT sp.student_number, u.full_name, u.email, sp.course, sp.year_level,
                        i.required_hours, c.name AS company, sup_user.full_name AS supervisor, i.status
                    FROM student_profiles sp
                    JOIN users u ON u.id = sp.user_id
                    LEFT JOIN internship_placements i ON i.student_id = sp.id AND i.status = 'active'
                    LEFT JOIN companies c ON c.id = i.company_id
                    LEFT JOIN supervisors sup ON sup.id = i.supervisor_id
                    LEFT JOIN users sup_user ON sup_user.id = sup.user_id
                    ORDER BY sp.student_number";
            $rows = array_map(fn($r) => [
                $r['student_number'], $r['full_name'], $r['email'], $r['course'], $r['year_level'],
                $r['company'] ?: 'Unplaced', $r['supervisor'] ?: '—',
                $r['required_hours'] !== null ? format_hours($r['required_hours']) : '—',
                $r['status'] ? ucfirst($r['status']) : '—',
            ], query_all($db, $sql));
            return [['Student ID', 'Student', 'Email', 'Course', 'Year', 'Company', 'Supervisor', 'Required hours', 'Status'], $rows];
    }
}

[$headers, $rows] = build_report($db, $report, $from, $to);

// CSV export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="its-' . $report . '-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, array_map('csv_cell', $row));
    }
    fclose($out);
    exit;
}

$title = 'Reports';
$active = 'reports';
require __DIR__ . '/../includes/header.php';

$options = '';
foreach ($titles as $key => $label) {
    $options .= '<option value="' . e($key) . '"' . ($report === $key ? ' selected' : '') . '>' . e($label) . '</option>';
}

$filter = '<form method="get" action="' . e(url('coordinator/reports.php')) . '" class="filter-form">'
    . '<label>Report<select name="report">' . $options . '</select></label>'
    . '<label>From<input type="date" name="from" value="' . e($from) . '"></label>'
    . '<label>To<input type="date" name="to" value="' . e($to) . '"></label>'
    . '<button class="button primary">Generate</button>'
    . '<a class="button secondary" href="' . e(url('coordinator/reports.php?report=' . urlencode($report) . '&from=' . urlencode($from) . '&to=' . urlencode($to) . '&export=csv')) . '">Export CSV</a>'
    . '<button class="button secondary" type="button" data-print>Print</button></form>';

$printHeader = '<div class="print-header"><h1>' . e(APP_NAME) . '</h1>'
    . '<p>' . e($titles[$report]) . ($from || $to ? ' · ' . e($from ?: 'start') . ' to ' . e($to ?: 'today') : '') . '</p></div>';

$tableRows = array_map(fn($row) => array_map('e', $row), $rows);

echo $printHeader . panel($titles[$report], 'Date filters apply where the report includes a date. '
    . count($rows) . ' record(s).', $filter . table_html($headers, $tableRows, 'No data matches this report.'));

require __DIR__ . '/../includes/footer.php';

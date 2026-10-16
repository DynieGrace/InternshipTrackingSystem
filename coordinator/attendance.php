<?php
/**
 * Coordinator: generate shared QR attendance sessions, verify records,
 * and correct records with an audit trail.
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

        if ($op === 'scan_student_qr') {
            // The instructor scans the QR code the student displays.
            $token = post_string('token', 64);
            $student = query_one(
                $db,
                'SELECT sp.id, sp.student_number, u.full_name
                FROM student_profiles sp JOIN users u ON u.id = sp.user_id
                WHERE sp.attendance_token = ? LIMIT 1',
                [$token]
            );
            if (!$student) {
                throw new InvalidArgumentException('That QR code is not recognised. Ask the student to show their attendance QR.');
            }
            $placement = active_placement($db, (int)$student['id']);
            if (!$placement) {
                throw new InvalidArgumentException('That student has no active placement.');
            }

            $open = query_one($db, 'SELECT id FROM attendance_records WHERE student_id = ? AND time_out IS NULL ORDER BY id DESC LIMIT 1', [(int)$student['id']]);
            if ($open) {
                $db->prepare(
                    "UPDATE attendance_records
                    SET time_out = NOW(),
                        rendered_hours = ROUND(GREATEST(0, TIMESTAMPDIFF(SECOND, time_in, NOW()) / 3600), 2),
                        status = 'pending'
                    WHERE id = ? AND time_out IS NULL"
                )->execute([(int)$open['id']]);
                $message = 'Time-out recorded for ' . $student['full_name'] . '.';
            } else {
                $db->prepare("INSERT INTO attendance_records (placement_id, student_id, time_in, status) VALUES (?, ?, NOW(), 'pending')")
                    ->execute([(int)$placement['id'], (int)$student['id']]);
                $message = 'Time-in recorded for ' . $student['full_name'] . '.';
            }
            record_activity($db, (int)$user['id'], 'coordinator_qr_attendance', 'student #' . (int)$student['id']);
            flash_redirect('coordinator/attendance.php', $message);
        }

        if ($op === 'review_attendance') {
            $id = post_id('attendance_id');
            $status = post_string('status', 20);
            if (!in_array($status, ['approved', 'rejected'], true)) {
                throw new InvalidArgumentException('Choose a valid decision.');
            }
            $db->prepare('UPDATE attendance_records SET status = ?, verified_by = ?, verified_at = NOW() WHERE id = ? AND time_out IS NOT NULL')
                ->execute([$status, (int)$user['id'], $id]);
            record_activity($db, (int)$user['id'], 'review_attendance', $status . ' #' . $id);
            flash_redirect('coordinator/attendance.php', 'Attendance decision saved.');
        }

        if ($op === 'correct_attendance') {
            $id = post_id('attendance_id');
            $reason = post_string('reason', 500);
            $timeIn = post_string('time_in', 19);
            $timeOut = post_string('time_out', 19);
            $in = DateTime::createFromFormat('Y-m-d\TH:i', $timeIn);
            $out = DateTime::createFromFormat('Y-m-d\TH:i', $timeOut);
            if ($reason === '' || !$in || !$out || $out <= $in) {
                throw new InvalidArgumentException('Enter a reason and a valid time-out later than the time-in.');
            }
            $hours = round(($out->getTimestamp() - $in->getTimestamp()) / 3600, 2);
            $db->prepare(
                "UPDATE attendance_records
                SET time_in = ?, time_out = ?, rendered_hours = ?, status = 'pending',
                    correction_reason = ?, corrected_by = ?, corrected_at = NOW()
                WHERE id = ?"
            )->execute([$in->format('Y-m-d H:i:s'), $out->format('Y-m-d H:i:s'), $hours, $reason, (int)$user['id'], $id]);
            record_activity($db, (int)$user['id'], 'correct_attendance', '#' . $id . ': ' . $reason);
            flash_redirect('coordinator/attendance.php', 'Attendance corrected and returned to pending review.');
        }

        throw new InvalidArgumentException('Unknown action.');
    } catch (InvalidArgumentException $error) {
        flash('error', $error->getMessage());
    } catch (Throwable $error) {
        error_log('ITS coordinator attendance failed: ' . $error->getMessage());
        flash('error', 'The attendance request could not be completed.');
    }
    redirect('coordinator/attendance.php');
}

$title = 'Attendance';
$active = 'attendance';
require __DIR__ . '/../includes/header.php';

// The instructor scans the student's personal QR to record attendance.
// Students never scan their own code.
$scan = form_start('coordinator/attendance.php', ['class' => 'qr-manual'])
    . '<input type="hidden" name="op" value="scan_student_qr">'
    . '<label>Student QR token<input name="token" maxlength="64" placeholder="Scan or type the student token" required></label>'
    . '<button class="button primary">Record attendance</button></form>'
    . '<div class="scanner"><button class="button secondary" type="button" data-start-scan>Start camera scanner</button>'
    . '<video playsinline muted></video><p class="help-text" data-scan-status>Camera access is requested only after you start the scanner. '
    . 'A manual token entry is always available. The action times out an open session, or starts a new one.</p></div>';

echo panel('Scan a student\'s attendance QR', 'Scan the QR the student displays (or enter their token). The server records time in or time out.', $scan);

// Filters
$from = (string)($_GET['from'] ?? '');
$to = (string)($_GET['to'] ?? '');
$statusFilter = (string)($_GET['status'] ?? '');
$conditions = [];
$params = [];
if ($from !== '' && valid_datetime($from)) {
    $conditions[] = 'DATE(a.time_in) >= ?';
    $params[] = $from;
}
if ($to !== '' && valid_datetime($to)) {
    $conditions[] = 'DATE(a.time_in) <= ?';
    $params[] = $to;
}
if (in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
    $conditions[] = 'a.status = ?';
    $params[] = $statusFilter;
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$filterForm = '<form method="get" action="' . e(url('coordinator/attendance.php')) . '" class="filter-form">'
    . '<label>From<input type="date" name="from" value="' . e($from) . '"></label>'
    . '<label>To<input type="date" name="to" value="' . e($to) . '"></label>'
    . '<label>Status<select name="status"><option value="">All</option>'
    . '<option value="pending"' . ($statusFilter === 'pending' ? ' selected' : '') . '>Pending</option>'
    . '<option value="approved"' . ($statusFilter === 'approved' ? ' selected' : '') . '>Approved</option>'
    . '<option value="rejected"' . ($statusFilter === 'rejected' ? ' selected' : '') . '>Rejected</option>'
    . '</select></label><button class="button secondary">Apply</button>'
    . '<a class="button secondary" href="' . e(url('coordinator/attendance.php')) . '">Reset</a></form>';

$records = query_all(
    $db,
    "SELECT a.id, a.time_in, a.time_out, a.rendered_hours, a.status, a.correction_reason,
            sp.student_number, u.full_name
    FROM attendance_records a
    JOIN student_profiles sp ON sp.id = a.student_id
    JOIN users u ON u.id = sp.user_id
    $where
    ORDER BY a.time_in DESC LIMIT 300",
    $params
);

$rows = [];
foreach ($records as $row) {
    $actions = '';
    if ($row['status'] === 'pending' && $row['time_out']) {
        $actions .= form_start('coordinator/attendance.php', ['class' => 'inline-review'])
            . '<input type="hidden" name="op" value="review_attendance">'
            . '<input type="hidden" name="attendance_id" value="' . (int)$row['id'] . '">'
            . '<select name="status" aria-label="Attendance decision"><option value="approved">Approve</option><option value="rejected">Reject</option></select>'
            . '<button class="button small">Save</button></form>';
    }
    if ($row['time_out']) {
        $actions .= '<details class="correction"><summary>Correct record</summary>'
            . form_start('coordinator/attendance.php')
            . '<input type="hidden" name="op" value="correct_attendance">'
            . '<input type="hidden" name="attendance_id" value="' . (int)$row['id'] . '">'
            . '<label>Time in<input type="datetime-local" name="time_in" value="' . e(date('Y-m-d\TH:i', strtotime($row['time_in']))) . '" required></label>'
            . '<label>Time out<input type="datetime-local" name="time_out" value="' . e(date('Y-m-d\TH:i', strtotime($row['time_out']))) . '" required></label>'
            . '<label>Reason<input name="reason" maxlength="500" required></label>'
            . '<button class="button small">Correct</button></form></details>';
    }
    $rows[] = [
        e($row['student_number']) . '<div class="help-text">' . e($row['full_name']) . '</div>',
        e(format_datetime($row['time_in'])),
        e($row['time_out'] ? format_datetime($row['time_out']) : 'In progress'),
        e(format_hours($row['rendered_hours'])),
        status_badge($row['status']),
        e($row['correction_reason'] ?: '') . $actions,
    ];
}

echo panel('Attendance records', 'Corrections return a record to pending review and are written to the audit log.', $filterForm
    . table_html(['Student', 'Time in', 'Time out', 'Hours', 'Status', 'Actions / note'], $rows, 'No attendance records match the filter.'));

require __DIR__ . '/../includes/footer.php';

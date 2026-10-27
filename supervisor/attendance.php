<?php
/**
 * Supervisor attendance: scan an intern's QR token to record time in/out,
 * and verify rendered hours for assigned interns only.
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
        if (!$supervisor) {
            throw new InvalidArgumentException('Your supervisor profile is not set up yet.');
        }
        $op = (string)($_POST['op'] ?? '');

        if ($op === 'scan_intern') {
            $token = post_string('token', 64);
            $student = query_one(
                $db,
                "SELECT sp.id, sp.attendance_token
                FROM student_profiles sp
                JOIN internship_placements i ON i.student_id = sp.id AND i.status = 'active'
                WHERE sp.attendance_token = ? AND i.supervisor_id = ? LIMIT 1",
                [$token, (int)$supervisor['id']]
            );
            if (!$student) {
                throw new InvalidArgumentException('That QR code does not belong to an intern assigned to you.');
            }
            $placement = active_placement($db, (int)$student['id']);
            $open = query_one($db, 'SELECT id FROM attendance_records WHERE student_id = ? AND time_out IS NULL ORDER BY id DESC LIMIT 1', [(int)$student['id']]);

            if ($open) {
                $db->prepare(
                    "UPDATE attendance_records
                    SET time_out = NOW(),
                        rendered_hours = ROUND(GREATEST(0, TIMESTAMPDIFF(SECOND, time_in, NOW()) / 3600), 2),
                        status = 'pending'
                    WHERE id = ? AND time_out IS NULL"
                )->execute([(int)$open['id']]);
                $message = 'Time-out recorded for the intern. Hours await verification.';
            } else {
                $db->prepare("INSERT INTO attendance_records (placement_id, student_id, time_in, status) VALUES (?, ?, NOW(), 'pending')")
                    ->execute([(int)$placement['id'], (int)$student['id']]);
                $message = 'Time-in recorded for the intern.';
            }
            record_activity($db, (int)$user['id'], 'supervisor_qr_attendance', 'student #' . (int)$student['id']);
            remember_scan_result((int)$student['id'], $message);
            redirect('supervisor/attendance.php');
        }

        if ($op === 'review_attendance') {
            $id = post_id('attendance_id');
            $status = post_string('status', 20);
            if (!in_array($status, ['approved', 'rejected'], true)) {
                throw new InvalidArgumentException('Choose a valid decision.');
            }
            $record = query_one(
                $db,
                "SELECT a.id FROM attendance_records a
                JOIN internship_placements i ON i.id = a.placement_id
                WHERE a.id = ? AND i.supervisor_id = ? AND a.time_out IS NOT NULL",
                [$id, (int)$supervisor['id']]
            );
            if (!$record) {
                throw new InvalidArgumentException('That attendance record is not assigned to you.');
            }
            $db->prepare('UPDATE attendance_records SET status = ?, verified_by = ?, verified_at = NOW() WHERE id = ?')
                ->execute([$status, (int)$user['id'], $id]);
            record_activity($db, (int)$user['id'], 'review_attendance', $status . ' #' . $id);
            flash_redirect('supervisor/attendance.php', 'Attendance decision saved.');
        }

        throw new InvalidArgumentException('Unknown action.');
    } catch (InvalidArgumentException $error) {
        flash('error', $error->getMessage());
    } catch (Throwable $error) {
        error_log('ITS supervisor attendance failed: ' . $error->getMessage());
        flash('error', 'The attendance request could not be completed.');
    }
    redirect('supervisor/attendance.php');
}

$title = 'Attendance Review';
$active = 'attendance';
require __DIR__ . '/../includes/header.php';

// A successful scan shows the intern's ID card in a popup.
if ($scanResult = take_scan_result()) {
    echo scan_result_modal($db, $scanResult);
}

if (!$supervisor) {
    echo panel('Attendance review', 'Supervisor profile missing.',
        '<div class="empty-state">Your supervisor profile is not set up yet.</div>');
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$scan = form_start('supervisor/attendance.php', ['class' => 'qr-manual'])
    . '<input type="hidden" name="op" value="scan_intern">'
    . '<label>Intern QR token<input name="token" maxlength="64" placeholder="Scan or type the intern token" required></label>'
    . '<button class="button primary">Record attendance</button></form>'
    . '<div class="scanner"><button class="button secondary" type="button" data-start-scan>Start camera scanner</button>'
    . '<video playsinline muted></video><p class="help-text" data-scan-status>Camera access is requested only after you start the scanner. '
    . 'A manual token entry is always available.</p></div>';

echo panel('Record intern attendance', 'Scan an assigned intern\'s QR code, or enter the token. '
    . 'The action automatically times out an open session or starts a new one.', $scan);

$records = query_all(
    $db,
    "SELECT a.id, a.time_in, a.time_out, a.rendered_hours, a.status, a.correction_reason,
            sp.student_number, u.full_name
    FROM attendance_records a
    JOIN internship_placements i ON i.id = a.placement_id
    JOIN student_profiles sp ON sp.id = a.student_id
    JOIN users u ON u.id = sp.user_id
    WHERE i.supervisor_id = ?
    ORDER BY a.time_in DESC LIMIT 200",
    [(int)$supervisor['id']]
);

$rows = [];
foreach ($records as $row) {
    $action = '';
    if ($row['status'] === 'pending' && $row['time_out']) {
        $action = form_start('supervisor/attendance.php', ['class' => 'inline-review'])
            . '<input type="hidden" name="op" value="review_attendance">'
            . '<input type="hidden" name="attendance_id" value="' . (int)$row['id'] . '">'
            . '<select name="status" aria-label="Attendance decision"><option value="approved">Approve</option><option value="rejected">Reject</option></select>'
            . '<button class="button small">Save</button></form>';
    }
    $rows[] = [
        e($row['student_number']) . '<div class="help-text">' . e($row['full_name']) . '</div>',
        e(format_datetime($row['time_in'])),
        e($row['time_out'] ? format_datetime($row['time_out']) : 'In progress'),
        e(format_hours($row['rendered_hours'])),
        status_badge($row['status']),
        $action ?: e($row['correction_reason'] ?: '—'),
    ];
}

echo panel('Attendance records', 'Only completed, approved sessions count toward progress.',
    table_html(['Student', 'Time in', 'Time out', 'Hours', 'Status', 'Action / note'], $rows, 'No attendance records yet.'));

require __DIR__ . '/../includes/footer.php';

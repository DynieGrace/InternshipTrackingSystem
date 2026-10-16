<?php
/**
 * Shared notifications handling and rendering for all roles.
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/** Handle mark-read POST actions, then redirect back to the caller's page. */
function notifications_handle_post(PDO $db, array $user, string $selfPath): void
{
    $op = (string)($_POST['op'] ?? '');
    if ($op === 'mark_read') {
        $id = post_id('notification_id');
        $db->prepare('UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?')
            ->execute([$id, (int)$user['id']]);
        redirect($selfPath);
    }
    if ($op === 'mark_all_read') {
        $db->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL')
            ->execute([(int)$user['id']]);
        flash_redirect($selfPath, 'All notifications marked as read.');
    }
    throw new InvalidArgumentException('Unknown action.');
}

/** Build the notifications panel markup for the current user. */
function notifications_render(PDO $db, array $user): string
{
    $rows = query_all(
        $db,
        'SELECT id, title, body, read_at, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 200',
        [(int)$user['id']]
    );

    $items = [];
    foreach ($rows as $row) {
        $action = $row['read_at']
            ? '<span class="muted">Read</span>'
            : '<form method="post" action="" class="inline-review">' . csrf_field()
                . '<input type="hidden" name="op" value="mark_read">'
                . '<input type="hidden" name="notification_id" value="' . (int)$row['id'] . '">'
                . '<button class="button small">Mark read</button></form>';
        $items[] = [
            ($row['read_at'] ? '' : '<strong>') . e($row['title']) . ($row['read_at'] ? '' : '</strong>'),
            e($row['body']),
            e(format_datetime($row['created_at'])),
            $action,
        ];
    }

    $header = '<form method="post" action="" class="toolbar"><div class="grow"></div>' . csrf_field()
        . '<input type="hidden" name="op" value="mark_all_read">'
        . '<button class="button secondary" type="submit"' . ($rows ? '' : ' disabled') . '>Mark all as read</button></form>';

    return panel('Your notifications', 'Updates for your internship workspace.',
        $header . table_html(['Notification', 'Details', 'Date', 'Status'], $items, 'You are all caught up.'));
}

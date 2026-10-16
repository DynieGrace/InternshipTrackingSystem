<?php
/**
 * Shared application shell: <head>, sidebar navigation and top bar.
 *
 * Pages set $title (page heading) and $active (nav key) before including
 * this file. The signed-in user is available as $user.
 */
declare(strict_types=1);

$user = require_login();
$role = $user['role'];

/** Sidebar navigation per role: key => [file, label, icon]. */
$navMenus = [
    'student' => [
        'dashboard'     => ['student/dashboard.php', 'Overview', '▦'],
        'profile'       => ['student/profile.php', 'My Profile', '☰'],
        'placement'     => ['student/placement.php', 'Placement', '⌂'],
        'requirements'  => ['student/requirements.php', 'Requirements', '▤'],
        'attendance'    => ['student/attendance.php', 'Attendance & Hours', '◷'],
        'evaluations'   => ['student/evaluations.php', 'Evaluations', '★'],
        'notifications' => ['student/notifications.php', 'Notifications', '◔'],
    ],
    'supervisor' => [
        'dashboard'     => ['supervisor/dashboard.php', 'Overview', '▦'],
        'interns'       => ['supervisor/interns.php', 'Assigned Interns', '♙'],
        'attendance'    => ['supervisor/attendance.php', 'Attendance Review', '◷'],
        'evaluations'   => ['supervisor/evaluations.php', 'Evaluations', '★'],
        'notifications' => ['supervisor/notifications.php', 'Notifications', '◔'],
        'profile'       => ['supervisor/profile.php', 'My Profile', '☰'],
    ],
    'coordinator' => [
        'dashboard'     => ['coordinator/dashboard.php', 'Overview', '▦'],
        'students'      => ['coordinator/students.php', 'Students', '♙'],
        'companies'     => ['coordinator/companies.php', 'Companies', '⌂'],
        'supervisors'   => ['coordinator/supervisors.php', 'Supervisors', '⚑'],
        'placements'    => ['coordinator/placements.php', 'Placements', '⇄'],
        'requirements'  => ['coordinator/requirements.php', 'Requirements', '▤'],
        'submissions'   => ['coordinator/submissions.php', 'Document Review', '⇩'],
        'attendance'    => ['coordinator/attendance.php', 'Attendance', '◷'],
        'evaluations'   => ['coordinator/evaluations.php', 'Evaluations', '★'],
        'announcements' => ['coordinator/announcements.php', 'Announcements', '✎'],
        'users'         => ['coordinator/users.php', 'User Accounts', '⚙'],
        'reports'       => ['coordinator/reports.php', 'Reports', '▣'],
        'profile'       => ['coordinator/profile.php', 'My Profile', '☰'],
        'notifications' => ['coordinator/notifications.php', 'Notifications', '◔'],
    ],
];

$menu = $navMenus[$role] ?? [];
$active = $active ?? 'dashboard';
$title = $title ?? 'Dashboard';
$displayName = $user['full_name'] ?: $user['username'];

$unread = 0;
if (isset($db) && $db instanceof PDO) {
    $unread = (int)query_value($db, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [(int)$user['id']]);
}
$flash = take_flash();
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#F97316">
  <title><?= e($title) ?> | <?= e(APP_NAME) ?></title>
  <script>
    /* Apply the saved theme before paint to avoid a flash. */
    (function () {
      try {
        var t = localStorage.getItem("its-theme");
        if (t === "dark" || t === "light") document.documentElement.dataset.theme = t;
      } catch (e) {}
    })();
  </script>
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
  <script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</head>
<body class="app-page">
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= e(url(dashboard_path_for_role($role))) ?>">
      <img class="brand-mark-img" src="<?= e(url('assets/images/logo.png')) ?>" alt="<?= e(APP_NAME) ?> logo">
      <span><strong><?= e(APP_SHORT_NAME) ?></strong><small><?= e(role_label($role)) ?></small></span>
    </a>
    <div class="nav-label">WORKSPACE</div>
    <nav aria-label="Main navigation">
      <?php foreach ($menu as $key => $item): ?>
        <a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= e(url($item[0])) ?>">
          <span aria-hidden="true"><?= e($item[2]) ?></span><?= e($item[1]) ?>
          <?= $key === 'notifications' && $unread ? '<i>' . (int)$unread . '</i>' : '' ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom">
      <div class="user-chip">
        <?= avatar_html($user) ?>
        <span><strong><?= e($displayName) ?></strong><small><?= e(role_label($role)) ?></small></span>
      </div>
      <button type="button" class="theme-toggle" data-theme-toggle>
        <span data-theme-icon>☀</span><span data-theme-label>Dark Mode</span>
      </button>
      <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?>
        <button class="logout-button" type="submit">↪ <span>Sign out</span></button>
      </form>
    </div>
  </aside>

  <main class="main-content">
    <header class="topbar">
      <div>
        <button class="mobile-menu" type="button" aria-label="Toggle navigation" data-menu-toggle>☰</button>
        <p class="breadcrumb">WORKSPACE <span>/</span> <?= e($title) ?></p>
        <h1><?= e($title) ?></h1>
        <p class="page-subtitle"><?= e(role_label($role)) ?> workspace</p>
      </div>
      <div class="topbar-side">
        <time><?= e(date('D, M j, Y')) ?></time>
        <?= avatar_html($user, 'avatar large') ?>
      </div>
    </header>

    <section class="content-area">
      <?php if ($flash): ?>
        <div class="notice <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
      <?php endif; ?>

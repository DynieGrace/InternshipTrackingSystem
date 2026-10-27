<?php
/**
 * Shared chrome for the public (signed-out) pages: login, register,
 * forgot password and reset password.
 *
 * Set $authTitle and (optionally) $authSubtitle before including.
 */
declare(strict_types=1);

$authTitle = $authTitle ?? 'Sign in';
$authSubtitle = $authSubtitle ?? 'Sign in to your workspace';
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#F97316">
  <title><?= e($authTitle) ?> | <?= e(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=<?= e(APP_VERSION) ?>">
  <script src="<?= e(url('assets/js/app.js')) ?>?v=<?= e(APP_VERSION) ?>" defer></script>
</head>
<body class="auth-page">
<main class="auth-layout">
  <section class="auth-brand">
    <img src="<?= e(url('assets/images/logo.png')) ?>" alt="<?= e(APP_NAME) ?> logo" class="auth-logo">
    <p class="eyebrow">INTERNSHIP MANAGEMENT PORTAL</p>
    <h1>Build your future,<br><span>one day at a time.</span></h1>
    <p>One workspace for your internship placement, attendance, documents, and progress.</p>
    <div class="auth-brand-meta">STUDENT · SUPERVISOR · COORDINATOR</div>
  </section>
  <section class="auth-card">
    <div class="auth-card-heading">
      <span class="mini-mark">ITS</span>
      <div><strong><?= e(APP_SHORT_NAME) ?></strong><small><?= e($authSubtitle) ?></small></div>
    </div>

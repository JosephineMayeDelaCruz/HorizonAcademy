<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PORTAL LAYOUT — Header & Sidebar Partial
 * ═══════════════════════════════════════════════════════════
 * 
 * Expected Variables (from including page):
 *   $pageTitle  (string) Browser title
 *   $navItems   (array) Navigation items [label, href, icon, active]
 *   $topbarUser (array) Current user [name, role]
 */

// Import authentication to access helpers.
require_once __DIR__ . '/../config/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Horizon Academy') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Caveat:wght@600;700&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<div class="bg-blobs"><span></span><span></span><span></span><span></span><span></span></div>

<div class="page portal-page active">
<div class="portal-wrap">

  <aside class="sidebar">
    <div class="sidebar-logo">
      <span class="logo-icon">🎓</span>
      <div class="sidebar-logo-text">
        <div class="t1">Horizon Academy</div>
        <div class="t2">Portal</div>
      </div>
    </div>

    <?php foreach ($navItems as $item): ?>
      <a href="<?= htmlspecialchars($item['href']) ?>"
         class="nav-item <?= $item['active'] ? 'active' : '' ?>">
        <?= $item['icon'] ?>
        <?= htmlspecialchars($item['label']) ?>
      </a>
    <?php endforeach; ?>

    <div class="sidebar-spacer"></div>
    <a href="<?= url('auth/logout.php') ?>" class="sidebar-logout">
      🚪
      Logout
    </a>
  </aside>

  <div class="main-area">
    <div class="topbar">
      <?php
      // Flash message in topbar area
      $flash = getFlash();
      if (!empty($flash['msg'])):
      ?>
      <div class="flash-msg flash-<?= $flash['type'] ?? 'success' ?>">
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
      <?php endif; ?>
      <div class="user-badge">
        <div class="user-avatar">
          👤
        </div>
        <div class="user-info">
          <div class="uname"><?= htmlspecialchars($topbarUser['name'] ?? '') ?></div>
          <div class="urole"><?= htmlspecialchars(ucfirst($topbarUser['role'] ?? '')) ?></div>
        </div>
      </div>
    </div>

    <div class="content">


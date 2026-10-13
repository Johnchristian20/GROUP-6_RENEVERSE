<?php
$pageTitle = $pageTitle ?? 'Library Management';
$basePath = $basePath ?? '';
$activeNav = 'overview';
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
foreach (['books', 'branches', 'members', 'loans', 'reports', 'requests'] as $navSection) {
  if (strpos($scriptPath, '/' . $navSection . '/') !== false) {
    $activeNav = $navSection;
    break;
  }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | The Reading Room</title>
  <link rel="stylesheet" href="<?= e($basePath) ?>assets/css/style.css">
</head>

<body>
  <div class="app-shell">

    <aside class="sidebar" id="appSidebar">
      <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Minimize sidebar" aria-expanded="true"
        title="Minimize sidebar">‹</button>
      <a class="brand" href="<?= e($basePath) ?>dashboard.php">
        <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24">
            <path
              d="M3 5.5c3.5-1.5 6.5-.8 9 1.3v13c-2.5-2.1-5.5-2.8-9-1.3zM21 5.5c-3.5-1.5-6.5-.8-9 1.3v13c2.5-2.1 5.5-2.8 9-1.3z" />
          </svg></span>
        <span><strong>The Reading Room</strong><small>Library Management</small></span>
      </a>
      <div class="nav-label">WORKSPACE</div>
      <nav class="nav-links" aria-label="Main navigation">
        <a class="<?= $activeNav === 'overview' ? 'active' : '' ?>" <?= $activeNav === 'overview' ? 'aria-current="page"' : '' ?> href="<?= e($basePath) ?>dashboard.php"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
            <rect x="3" y="3" width="8" height="8" rx="1.5" />
            <rect x="13" y="3" width="8" height="5" rx="1.5" />
            <rect x="13" y="10" width="8" height="11" rx="1.5" />
            <rect x="3" y="13" width="8" height="8" rx="1.5" />
          </svg><span>Overview</span></a>
        <a class="<?= $activeNav === 'books' ? 'active' : '' ?>" <?= $activeNav === 'books' ? 'aria-current="page"' : '' ?>
          href="<?= e($basePath) ?>books/index.php"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z" />
            <path d="M4 5.5v16M8 7h8M8 11h8" />
          </svg><span>Book catalogue</span></a>
        <a class="<?= $activeNav === 'branches' ? 'active' : '' ?>" <?= $activeNav === 'branches' ? 'aria-current="page"' : '' ?> href="<?= e($basePath) ?>branches/index.php"><svg class="nav-icon" viewBox="0 0 24 24"
            aria-hidden="true">
            <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-5h6v5M9 9h.01M15 9h.01M9 12h.01M15 12h.01" />
          </svg><span>Branches</span></a>
        <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
          <a class="<?= $activeNav === 'members' ? 'active' : '' ?>" <?= $activeNav === 'members' ? 'aria-current="page"' : '' ?> href="<?= e($basePath) ?>members/index.php"><svg class="nav-icon" viewBox="0 0 24 24"
              aria-hidden="true">
              <circle cx="9" cy="8" r="3.5" />
              <path
                d="M2.5 21v-1.5A6.5 6.5 0 0 1 9 13h.5a6.5 6.5 0 0 1 6.5 6.5V21M16 4.8a3.5 3.5 0 0 1 0 6.5M18 14a5.5 5.5 0 0 1 3.5 5.2V21" />
            </svg><span>Members</span></a>
          <a class="<?= $activeNav === 'loans' ? 'active' : '' ?>" <?= $activeNav === 'loans' ? 'aria-current="page"' : '' ?>
            href="<?= e($basePath) ?>loans/index.php"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5zM4 5.5v16M8 7h8M8 11h6" />
              <path d="m15 15 2 2 4-4" />
            </svg><span>Book loans</span></a>
          <a class="<?= $activeNav === 'reports' ? 'active' : '' ?>" <?= $activeNav === 'reports' ? 'aria-current="page"' : '' ?> href="<?= e($basePath) ?>reports/index.php"><svg class="nav-icon" viewBox="0 0 24 24"
              aria-hidden="true">
              <path d="M4 20V4M4 20h17" />
              <rect x="7" y="12" width="3" height="5" rx=".5" />
              <rect x="13" y="8" width="3" height="9" rx=".5" />
              <rect x="19" y="5" width="2" height="12" rx=".5" />
            </svg><span>Reports &amp; exports</span></a>
        <?php endif; ?>
        <div class="nav-divider"></div>
        <a class="<?= $activeNav === 'requests' ? 'active' : '' ?>" <?= $activeNav === 'requests' ? 'aria-current="page"' : '' ?> href="<?= e($basePath) ?>requests/index.php"><svg class="nav-icon" viewBox="0 0 24 24"
            aria-hidden="true">
            <path d="M6 3h12a2 2 0 0 1 2 2v16l-8-4-8 4V5a2 2 0 0 1 2-2z" />
            <path d="M9 8h6M9 11h6" />
          </svg><span>Book requests</span></a>
      </nav>
      <div class="sidebar-bottom">
        <div class="sidebar-note"><span class="note-icon">✦</span><strong>A quiet place to grow</strong>
          <p>Every book opens a new door.</p>
        </div>


        <a class="logout-link" href="<?= e($basePath) ?>logout.php" data-confirm-logout="true" aria-label="Sign out"
          title="Sign out">
          <span class="nav-icon logout-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
              <path d="M10 17l5-5-5-5" />
              <path d="M15 12H3" />
              <path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6" />
            </svg>
          </span>
          <span class="logout-label">Sign out</span>
        </a>

      </div>
    </aside>
    <main class="main-area">
      <header class="topbar">
        <div>
          <div class="eyebrow">LIBRARY WORKSPACE</div>
          <h1><?= e($pageTitle) ?></h1>
        </div>
        <div class="profile-chip"><span
            class="avatar"><?= e(strtoupper(substr($_SESSION['first_name'] ?? 'U', 0, 1))) ?></span><span><strong><?= e(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?></strong><small><?= e($_SESSION['role'] ?? 'User') ?></small></span>
        </div>
      </header>
      <div class="page-content">
<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/auth.php';
$u = current_user();
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/app.css" rel="stylesheet">
</head>

<body>
  <nav class="navbar navbar-expand-lg bg-body-tertiary mb-3 shadow-sm">
    <div class="container-fluid">
      <a class="navbar-brand" href="index.php"><?= APP_NAME ?></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav"
        aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="nav">
        <?php if ($u): ?>
          <form class="d-flex ms-2 me-3" method="get" action="index.php" role="search">
            <input type="hidden" name="page" value="search">
            <input class="form-control form-control-sm me-2" name="q" placeholder="Search..."
              value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
          </form>
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item"><a class="nav-link" href="index.php?page=dashboard">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="index.php?page=children">Children</a></li>
            <li class="nav-item"><a class="nav-link" href="index.php?page=staff">Staff</a></li>
            <li class="nav-item"><a class="nav-link" href="index.php?page=attendance">Attendance</a></li>
            <li class="nav-item"><a class="nav-link" href="index.php?page=incidents">Incidents</a></li>
            <li class="nav-item"><a class="nav-link" href="index.php?page=medications">Medications</a></li>
            <li class="nav-item"><a class="nav-link" href="index.php?page=visitors">Visitors</a></li>
            <li class="nav-item"><a class="nav-link" href="index.php?page=complaints">Complaints</a></li>
            <li class="nav-item"><a class="nav-link" href="index.php?page=documents">Documents</a></li>
            <li class="nav-item"><a class="nav-link" href="index.php?page=reports">Reports</a></li>
            <li class="nav-item"><a class="nav-link" href="index.php?page=search">Search</a></li>
            <?php if (has_role('manager')): ?>
              <li class="nav-item"><a class="nav-link" href="index.php?page=audit">Audit</a></li>
            <?php endif; ?>
            <?php if (has_role('admin')): ?>
              <li class="nav-item"><a class="nav-link" href="index.php?page=users">Users</a></li>
            <?php endif; ?>
          </ul>
          <div class="d-flex">
            <span class="navbar-text me-3"><?= htmlspecialchars($u['name']) ?>
              (<?= htmlspecialchars($u['role']) ?>)</span>
            <a class="btn btn-outline-secondary btn-sm" href="index.php?page=logout">Logout</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </nav>
  <main class="container mb-5">
<?php
require_once __DIR__ . '/../partials/header.php';
if (current_user()) { header('Location: index.php?page=dashboard'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  if (login($_POST['email'] ?? '', $_POST['password'] ?? '')) {
    header('Location: index.php?page=dashboard'); exit;
  } else {
    $err = 'Invalid credentials';
  }
}
?>
<div class="row justify-content-center">
  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h1 class="h5 mb-3">Sign in</h1>
        <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
        <form method="post">
          <?php csrf_input(); ?>
          <div class="mb-3"><label class="form-label">Email</label><input class="form-control" name="email" value="admin@example.com"></div>
          <div class="mb-3"><label class="form-label">Password</label><input type="password" class="form-control" name="password" value="admin123"></div>
          <button class="btn btn-primary w-100">Login</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>

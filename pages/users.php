<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_role_any(['admin']); // admin only

$db = get_db();
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  if (isset($_POST['create'])) {
    try {
      $name = trim($_POST['name']);
      $email = trim($_POST['email']);
      $role = $_POST['role'];
      $password = $_POST['password'];
      if ($name==='' || $email==='' || $password==='') throw new Exception('Missing fields');
      $st = $db->prepare('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)');
      $st->execute([$name,$email,password_hash($password, PASSWORD_DEFAULT),$role]);
      flash('User created');
      header('Location: index.php?page=users'); exit;
    } catch (Throwable $e) { $err = $e->getMessage(); }
  }
  if (isset($_POST['update'])) {
    try {
      $id = (int)$_POST['id'];
      $name = trim($_POST['name']);
      $email = trim($_POST['email']);
      $role = $_POST['role'];
      $is_active = isset($_POST['is_active']) ? 1 : 0;
      $db->prepare('UPDATE users SET name=?, email=?, role=?, is_active=? WHERE id=?')->execute([$name,$email,$role,$is_active,$id]);
      if (!empty($_POST['password'])) {
        $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $id]);
      }
      flash('User updated');
      header('Location: index.php?page=users'); exit;
    } catch (Throwable $e) { $err = $e->getMessage(); }
  }
  if (isset($_POST['delete'])) {
    $id=(int)$_POST['id'];
    if ($id === (current_user()['id'] ?? -1)) { $err = 'Cannot delete your own account.'; }
    else {
      $db->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
      flash('User deleted');
      header('Location: index.php?page=users'); exit;
    }
  }
}

$allowedSort=['name'=>'u.name','email'=>'u.email','role'=>'u.role','active'=>'u.is_active','created'=>'u.created_at'];
list($p,$per,$offset)=page_params(20);
$order=order_by_clause($allowedSort,'created');
$total=(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$st=$db->prepare('SELECT u.* FROM users u'.$order.' LIMIT ? OFFSET ?');
$st->bindValue(1,$per,PDO::PARAM_INT); $st->bindValue(2,$offset,PDO::PARAM_INT); $st->execute();
$rows=$st->fetchAll();

$edit=null;
if (isset($_GET['edit'])) { $st=$db->prepare('SELECT * FROM users WHERE id=?'); $st->execute([(int)$_GET['edit']]); $edit=$st->fetch(); }
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4">Users</h1></div>
<?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-md-7">
    <div class="card shadow-sm"><div class="card-body table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr><th><?= sort_link('name','Name') ?></th><th><?= sort_link('email','Email') ?></th><th><?= sort_link('role','Role') ?></th><th><?= sort_link('active','Active') ?></th><th><?= sort_link('created','Created') ?></th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['name']) ?></td>
              <td><?= htmlspecialchars($r['email']) ?></td>
              <td><span class="badge bg-<?= $r['role']==='admin'?'danger':($r['role']==='manager'?'primary':'secondary') ?>"><?= htmlspecialchars($r['role']) ?></span></td>
              <td><?= $r['is_active']?'Yes':'No' ?></td>
              <td><?= htmlspecialchars($r['created_at']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary" href="index.php?page=users&edit=<?= (int)$r['id'] ?>">Edit</a>
                <?php if ((int)$r['id'] !== (int)(current_user()['id'] ?? -1)): ?>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete user?');"><?php csrf_input(); ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger" name="delete" value="1">Delete</button></form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="d-flex justify-content-between align-items-center"><div class="small text-muted">Total: <?= $total ?></div><?php render_pagination($total,$per,$p); ?></div>
    </div></div>
  </div>
  <div class="col-md-5">
    <div class="card shadow-sm"><div class="card-body">
      <h2 class="h6"><?= $edit?'Edit user':'Add user' ?></h2>
      <form method="post"><?php csrf_input(); if($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <div class="row g-2">
          <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required></div>
          <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" name="email" value="<?= htmlspecialchars($edit['email'] ?? '') ?>" required></div>
          <div class="col-md-6"><label class="form-label">Role</label><select class="form-select" name="role"><?php foreach(['staff','manager','admin'] as $r): ?><option value="<?= $r ?>" <?= ($edit['role'] ?? '')===$r?'selected':'' ?>><?= ucfirst($r) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label"><?= $edit?'New ':'' ?>Password</label><input class="form-control" name="password" <?= $edit?'':'required' ?>></div>
          <?php if ($edit): ?><div class="col-md-6 form-check mt-4"><input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?= ($edit['is_active'] ?? 1)?'checked':'' ?>><label for="is_active" class="form-check-label">Active</label></div><?php endif; ?>
        </div>
        <div class="mt-3">
          <?php if ($edit): ?><button class="btn btn-primary" name="update" value="1">Save</button> <a class="btn btn-secondary" href="index.php?page=users">Cancel</a><?php else: ?><button class="btn btn-primary" name="create" value="1">Add user</button><?php endif; ?>
        </div>
      </form>
    </div></div>
  </div>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>

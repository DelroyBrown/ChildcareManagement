<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/audit.php';
$db = get_db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  if (isset($_POST['create'])) { if (!has_role('manager')) { http_response_code(403); die('Forbidden'); }
    $st=$db->prepare('INSERT INTO staff (name,email,phone,role,qualifications,dbs_check_date,training_completed,start_date) VALUES (?,?,?,?,?,?,?,?)');
    $st->execute([$_POST['name'],$_POST['email']?:null,$_POST['phone']?:null,$_POST['role']?:null,$_POST['qualifications']?:null,$_POST['dbs_check_date']?:null,$_POST['training_completed']?:null,$_POST['start_date']?:null]);
    $staff_id = (int)$db->lastInsertId();

    // Create login?
    $wants_login = isset($_POST['create_login']);
    $user_email  = isset($_POST['user_email']) ? trim($_POST['user_email']) : '';
    $user_pass   = $_POST['user_password'] ?? '';
    $user_role   = $_POST['user_role'] ?? 'staff';

    if ($wants_login) {
      if ($user_email === '' || $user_pass === '') {
        flash('Staff added but login not created: email and password required', 'danger');
      } else {
        try {
          $db->prepare('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)')
             ->execute([$_POST['name'], $user_email, password_hash($user_pass, PASSWORD_DEFAULT), $user_role]);
          if (function_exists('log_event')) {
            log_event('create','user',(int)$db->lastInsertId(), ['from_staff'=>$staff_id,'role'=>$user_role]);
          }
          flash('Staff member added and login created');
        } catch (Throwable $e) {
          flash('Staff added but login not created: '.$e->getMessage(), 'danger');
        }
      }
    } else {
      flash('Staff member added');
    }

    header('Location: index.php?page=staff'); exit;
  }
  if (isset($_POST['delete'])) { if (!has_role('manager')) { http_response_code(403); die('Forbidden'); } $st=$db->prepare('DELETE FROM staff WHERE id=?'); $st->execute([$_POST['id']]); flash('Staff deleted'); header('Location: index.php?page=staff'); exit; }
}

$allowedSort=['name'=>'name','role'=>'role','dbs'=>'dbs_check_date'];
list($p,$per,$offset)=page_params(20);
$order=order_by_clause($allowedSort,'name');
$total=(int)$db->query('SELECT COUNT(*) FROM staff')->fetchColumn();
$st=$db->prepare('SELECT * FROM staff'.$order.' LIMIT ? OFFSET ?');
$st->bindValue(1,$per,PDO::PARAM_INT); $st->bindValue(2,$offset,PDO::PARAM_INT); $st->execute();
$rows=$st->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4">Staff</h1></div>
<div class="row g-3">
  <div class="col-md-7">
    <div class="card shadow-sm"><div class="card-body table-responsive">
      <table class="table table-sm align-middle"><thead><tr><th><?= sort_link("name","Name") ?></th><th>Email</th><th>Phone</th><th><?= sort_link("role","Role") ?></th><th><?= sort_link("dbs","DBS") ?></th><th></th><th>Login</th></tr></thead><tbody>
        <?php foreach ($rows as $r): ?>
          <tr><td><?= htmlspecialchars($r['name']) ?></td><td><?= htmlspecialchars($r['email']) ?></td><td><?= htmlspecialchars($r['phone']) ?></td><td><?= htmlspecialchars($r['role']) ?></td><td><?= htmlspecialchars(format_human_date($r['dbs_check_date'])) ?></td>
          <td class="text-end">
            <?php if (has_role('manager')): ?><form method="post" onsubmit="return confirm('Delete staff?');"><?php csrf_input(); ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger" name="delete" value="1">Delete</button></form><?php endif; ?>
          </td></tr>
        <?php endforeach; ?>
      </tbody></table>
      <div class="d-flex justify-content-between align-items-center"><div class="small text-muted">Total: <?= $total ?></div><?php render_pagination($total,$per,$p); ?></div>
    </div></div>
  </div>
  <div class="col-md-5">
    <div class="card shadow-sm"><div class="card-body">
      <?php if (has_role('manager')): ?><h2 class="h6">Add staff</h2>
      <p class="text-muted small">Optionally create a user login and set access level (staff/manager/admin).</p>
      <form method="post"><?php csrf_input(); ?>
        <div class="row g-2">
          <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" required></div>
          <div class="col-md-6"><label class="form-label">Role</label><input class="form-control" name="role"></div>
          <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" name="email"></div>
          <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone"></div>
          <div class="col-md-6"><label class="form-label">DBS check date</label><input type="date" class="form-control" name="dbs_check_date"></div>
          <div class="col-md-6"><label class="form-label">Start date</label><input type="date" class="form-control" name="start_date"></div>
          <div class="col-12"><label class="form-label">Qualifications / Training</label><textarea class="form-control" name="training_completed" rows="2"></textarea></div>
          <hr>
          <div class="col-12 form-check"><input class="form-check-input" type="checkbox" id="create_login" name="create_login"><label class="form-check-label" for="create_login">Create login now</label></div>
          <div class="col-md-6"><label class="form-label">Login Email</label><input class="form-control" name="user_email" placeholder="user@site.com"></div>
          <div class="col-md-6"><label class="form-label">Password</label><input class="form-control" name="user_password" placeholder="Temporary password"></div>
          <div class="col-md-6"><label class="form-label">Access level</label><select class="form-select" name="user_role"><option value="staff">Staff (no delete)</option><option value="manager">Manager</option><option value="admin">Admin</option></select></div>
        </div>
        <div class="mt-3"><button class="btn btn-primary" name="create" value="1">Add staff</button></div>
      </form><?php else: ?><div class="alert alert-info mb-0">Read-only for your role.</div><?php endif; ?>
    </div></div>
  </div>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>

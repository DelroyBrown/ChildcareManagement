<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
$db = get_db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  if (isset($_POST['create'])) {
    $st=$db->prepare('INSERT INTO incidents (child_id,date_time,type,severity,status,location,injury,restraint_used,description,action_taken,reported_to,staff_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $st->execute([$_POST['child_id'],$_POST['date_time'],$_POST['type'],$_POST['severity']?:null,$_POST['status']?:'open',$_POST['location']?:null,$_POST['injury']?:null,isset($_POST['restraint_used'])?1:0,$_POST['description']?:null,$_POST['action_taken']?:null,$_POST['reported_to']?:null,$_POST['staff_id']?:null]);
    flash('Incident added'); header('Location: index.php?page=incidents'); exit;
  }
  if (isset($_POST['delete'])) { if (!has_role('manager')) { http_response_code(403); die('Forbidden'); } $st=$db->prepare('DELETE FROM incidents WHERE id=?'); $st->execute([$_POST['id']]); flash('Incident deleted'); header('Location: index.php?page=incidents'); exit; }
}

$children=$db->query('SELECT id, first_name, last_name FROM children ORDER BY last_name')->fetchAll();
$staff=$db->query('SELECT id, name FROM staff ORDER BY name')->fetchAll();
$allowedSort=['date'=>'i.date_time','type'=>'i.type','child'=>'child_name','severity'=>'i.severity','status'=>'i.status'];
list($p,$per,$offset)=page_params(20);
$order=order_by_clause($allowedSort,'date');
$total=(int)$db->query('SELECT COUNT(*) FROM incidents')->fetchColumn();
$st=$db->prepare('SELECT i.*, CONCAT(c.first_name," ",c.last_name) AS child_name, s.name AS staff_name FROM incidents i JOIN children c ON c.id=i.child_id LEFT JOIN staff s ON s.id=i.staff_id' . $order . ' LIMIT ? OFFSET ?');
$st->bindValue(1,$per,PDO::PARAM_INT); $st->bindValue(2,$offset,PDO::PARAM_INT); $st->execute();
$rows=$st->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4">Incidents</h1></div>
<div class="row g-3">
  <div class="col-md-7">
    <div class="card shadow-sm"><div class="card-body table-responsive">
      <table class="table table-sm align-middle"><thead><tr><th><?= sort_link("date","Date") ?></th><th><?= sort_link("child","Child") ?></th><th><?= sort_link("type","Type") ?></th><th><?= sort_link("severity","Severity") ?></th><th><?= sort_link("status","Status") ?></th><th>Staff</th><th></th></tr></thead><tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars(format_human_datetime($r['date_time'])) ?></td>
            <td><?= htmlspecialchars($r['child_name']) ?></td>
            <td><?= htmlspecialchars($r['type']) ?></td>
            <td><?= htmlspecialchars($r['severity'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['status'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['staff_name'] ?? '') ?></td>
            <td class="text-end">
              <?php if (has_role('manager')): ?><form method="post" onsubmit="return confirm('Delete incident?');"><?php csrf_input(); ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger" name="delete" value="1">Delete</button></form><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody></table><div class="d-flex justify-content-between align-items-center"><div class="small text-muted">Total: <?= $total ?></div><?php render_pagination($total,$per,$p); ?></div>
    </div></div>
  </div>
  <div class="col-md-5">
    <div class="card shadow-sm"><div class="card-body">
      <h2 class="h6">Add incident</h2>
      <form method="post"><?php csrf_input(); ?>
        <div class="row g-2">
          <div class="col-md-6"><label class="form-label">Child</label>
            <select class="form-select" name="child_id" required>
              <?php foreach ($children as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['last_name'].', '.$c['first_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6"><label class="form-label">Date/time</label><input type="datetime-local" class="form-control" name="date_time" value="<?= date('Y-m-d\TH:i') ?>" required></div>
          <div class="col-md-6"><label class="form-label">Type</label><input class="form-control" name="type" required></div>
          <div class="col-md-3"><label class="form-label">Severity</label><select class="form-select" name="severity"><option value="">—</option><option>low</option><option>med</option><option>high</option></select></div>
          <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option>open</option><option>review</option><option>closed</option></select></div>
          <div class="col-md-6"><label class="form-label">Location</label><input class="form-control" name="location"></div>
          <div class="col-md-6"><label class="form-label">Injury</label><input class="form-control" name="injury"></div>
          <div class="col-md-12 form-check"><input type="checkbox" class="form-check-input" id="restraint_used" name="restraint_used"><label for="restraint_used" class="form-check-label">Restraint used</label></div>
          <div class="col-md-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"></textarea></div>
          <div class="col-md-12"><label class="form-label">Action taken</label><textarea class="form-control" name="action_taken" rows="2"></textarea></div>
          <div class="col-md-6"><label class="form-label">Reported to</label><input class="form-control" name="reported_to"></div>
          <div class="col-md-6"><label class="form-label">Staff involved</label>
            <select class="form-select" name="staff_id"><option value="">—</option><?php foreach ($staff as $s): ?><option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select>
          </div>
        </div>
        <div class="mt-3"><button class="btn btn-primary" name="create" value="1">Add incident</button></div>
      </form>
    </div></div>
  </div>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>

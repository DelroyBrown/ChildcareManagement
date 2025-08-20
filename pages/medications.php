<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';

$db = get_db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  if (isset($_POST['create'])) {
    $st=$db->prepare('INSERT INTO medications (child_id,med_name,dosage,frequency,administered_at,administered_by,notes) VALUES (?,?,?,?,?,?,?)');
    $st->execute([$_POST['child_id'], trim($_POST['med_name']??''), trim($_POST['dosage']??''), trim($_POST['frequency']??''), $_POST['administered_at']?:null, trim($_POST['administered_by']??''), ($_POST['notes']??null)?:null ]);
    flash('Medication record added'); header('Location: index.php?page=medications'); exit;
  }
  if (isset($_POST['delete'])) {
    if (!has_role('manager')) { http_response_code(403); die('Forbidden'); }
    $st=$db->prepare('DELETE FROM medications WHERE id=?'); $st->execute([$_POST['id']]);
    flash('Medication record deleted'); header('Location: index.php?page=medications'); exit;
  }
}

$children=$db->query('SELECT id, first_name, last_name FROM children ORDER BY last_name, first_name')->fetchAll();

$allowedSort=['date'=>'m.administered_at','child'=>'child_name','med'=>'m.med_name','dose'=>'m.dosage'];
list($p,$per,$offset)=page_params(20);
$order=order_by_clause($allowedSort,'date');

$sqlCount = 'SELECT COUNT(*) FROM medications m LEFT JOIN children c ON c.id=m.child_id';
$total = (int)$db->query($sqlCount)->fetchColumn();

$sql = 'SELECT m.*, CONCAT(c.first_name," ",c.last_name) AS child_name
        FROM medications m
        LEFT JOIN children c ON c.id=m.child_id' . $order . ' LIMIT ? OFFSET ?';
$st=$db->prepare($sql); $st->bindValue(1,$per,PDO::PARAM_INT); $st->bindValue(2,$offset,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4">Medications</h1></div>

<div class="row g-3">
  <div class="col-md-7">
    <div class="card shadow-sm"><div class="card-body table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr>
          <th><?= sort_link('date','Administered at') ?></th>
          <th><?= sort_link('child','Child') ?></th>
          <th><?= sort_link('med','Medication') ?></th>
          <th><?= sort_link('dose','Dosage') ?></th>
          <th>Administered by</th>
          <th></th>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['administered_at']) ?></td>
              <td><?= htmlspecialchars($r['child_name'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['med_name']) ?></td>
              <td><?= htmlspecialchars($r['dosage']) ?></td>
              <td><?= htmlspecialchars($r['administered_by'] ?? '') ?></td>
              <td class="text-end">
                <?php if (has_role('manager')): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete record?');"><?php csrf_input(); ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger" name="delete" value="1">Delete</button></form>
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
      <h2 class="h6">Add medication record</h2>
      <form method="post"><?php csrf_input(); ?>
        <div class="row g-2">
          <div class="col-md-6"><label class="form-label">Child</label>
            <select class="form-select" name="child_id" required><option value="">—</option><?php foreach ($children as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['last_name'].', '.$c['first_name']) ?></option><?php endforeach; ?></select>
          </div>
          <div class="col-md-6"><label class="form-label">Administered at</label><input type="datetime-local" class="form-control" name="administered_at" value="<?= date('Y-m-d\TH:i') ?>"></div>
          <div class="col-md-6"><label class="form-label">Medication</label><input class="form-control" name="med_name" required></div>
          <div class="col-md-6"><label class="form-label">Dosage</label><input class="form-control" name="dosage"></div>
          <div class="col-md-6"><label class="form-label">Frequency</label><input class="form-control" name="frequency"></div>
          <div class="col-md-6"><label class="form-label">Administered by</label><input class="form-control" name="administered_by"></div>
          <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
        </div>
        <div class="mt-3"><button class="btn btn-primary" name="create" value="1">Save</button></div>
      </form>
    </div></div>
  </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

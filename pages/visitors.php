<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';

$db = get_db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  if (isset($_POST['create'])) {
    $st=$db->prepare('INSERT INTO visitors (child_id,visitor_name,relationship,visit_date,id_checked,notes) VALUES (?,?,?,?,?,?)');
    $st->execute([$_POST['child_id'], trim($_POST['visitor_name']??''), trim($_POST['relationship']??''), $_POST['visit_date']?:null, isset($_POST['id_checked'])?1:0, ($_POST['notes']??null)?:null ]);
    flash('Visit logged'); header('Location: index.php?page=visitors'); exit;
  }
  if (isset($_POST['delete'])) {
    if (!has_role('manager')) { http_response_code(403); die('Forbidden'); }
    $st=$db->prepare('DELETE FROM visitors WHERE id=?'); $st->execute([$_POST['id']]);
    flash('Visit deleted'); header('Location: index.php?page=visitors'); exit;
  }
}

$children=$db->query('SELECT id, first_name, last_name FROM children ORDER BY last_name, first_name')->fetchAll();

$allowedSort=['date'=>'v.visit_date','child'=>'child_name','name'=>'v.visitor_name','rel'=>'v.relationship'];
list($p,$per,$offset)=page_params(20);
$order=order_by_clause($allowedSort,'date');

$sqlCount = 'SELECT COUNT(*) FROM visitors v LEFT JOIN children c ON c.id=v.child_id';
$total = (int)$db->query($sqlCount)->fetchColumn();

$sql = 'SELECT v.*, CONCAT(c.first_name," ",c.last_name) AS child_name
        FROM visitors v
        LEFT JOIN children c ON c.id=v.child_id' . $order . ' LIMIT ? OFFSET ?';
$st=$db->prepare($sql); $st->bindValue(1,$per,PDO::PARAM_INT); $st->bindValue(2,$offset,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4">Visitors</h1></div>

<div class="row g-3">
  <div class="col-md-7">
    <div class="card shadow-sm"><div class="card-body table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr>
          <th><?= sort_link('date','Date') ?></th>
          <th><?= sort_link('child','Child') ?></th>
          <th><?= sort_link('name','Visitor') ?></th>
          <th><?= sort_link('rel','Relationship') ?></th>
          <th>ID Checked</th>
          <th></th>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['visit_date']) ?></td>
              <td><?= htmlspecialchars($r['child_name'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['visitor_name']) ?></td>
              <td><?= htmlspecialchars($r['relationship']) ?></td>
              <td><?= !empty($r['id_checked']) ? 'Yes' : 'No' ?></td>
              <td class="text-end">
                <?php if (has_role('manager')): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete visit?');"><?php csrf_input(); ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger" name="delete" value="1">Delete</button></form>
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
      <h2 class="h6">Add visit</h2>
      <form method="post"><?php csrf_input(); ?>
        <div class="row g-2">
          <div class="col-md-6"><label class="form-label">Child</label>
            <select class="form-select" name="child_id" required><option value="">—</option><?php foreach ($children as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['last_name'].', '.$c['first_name']) ?></option><?php endforeach; ?></select>
          </div>
          <div class="col-md-6"><label class="form-label">Date</label><input type="date" class="form-control" name="visit_date" value="<?= date('Y-m-d') ?>"></div>
          <div class="col-md-6"><label class="form-label">Visitor name</label><input class="form-control" name="visitor_name" required></div>
          <div class="col-md-6"><label class="form-label">Relationship</label><input class="form-control" name="relationship"></div>
          <div class="col-md-6 form-check mt-4"><input type="checkbox" class="form-check-input" id="id_checked" name="id_checked"><label for="id_checked" class="form-check-label">ID checked</label></div>
          <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
        </div>
        <div class="mt-3"><button class="btn btn-primary" name="create" value="1">Save</button></div>
      </form>
    </div></div>
  </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

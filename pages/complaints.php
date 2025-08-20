<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';

$db = get_db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  if (isset($_POST['create'])) {
    $st=$db->prepare('INSERT INTO complaints (child_id,date_received,complainant_name,details,outcome,closed_date) VALUES (?,?,?,?,?,?)');
    $st->execute([$_POST['child_id']?:null,$_POST['date_received']?:null,trim($_POST['complainant_name']??''),trim($_POST['details']??''),($_POST['outcome']??null)?:null,($_POST['closed_date']??null)?:null]);
    flash('Complaint recorded'); header('Location: index.php?page=complaints'); exit;
  }
  if (isset($_POST['delete'])) {
    if (!has_role('manager')) { http_response_code(403); die('Forbidden'); }
    $st=$db->prepare('DELETE FROM complaints WHERE id=?'); $st->execute([$_POST['id']]);
    flash('Complaint deleted'); header('Location: index.php?page=complaints'); exit;
  }
}

$children=$db->query('SELECT id, first_name, last_name FROM children ORDER BY last_name, first_name')->fetchAll();

$allowedSort=['date'=>'cmp.date_received','child'=>'child_name','status'=>'cmp.closed_date','created'=>'cmp.id'];
list($p,$per,$offset)=page_params(20);
$order=order_by_clause($allowedSort,'date');

$sqlCount = 'SELECT COUNT(*) FROM complaints cmp LEFT JOIN children c ON c.id=cmp.child_id';
$total = (int)$db->query($sqlCount)->fetchColumn();

$sql = 'SELECT cmp.*, CONCAT(c.first_name," ",c.last_name) AS child_name
        FROM complaints cmp
        LEFT JOIN children c ON c.id=cmp.child_id' . $order . ' LIMIT ? OFFSET ?';
$st=$db->prepare($sql); $st->bindValue(1,$per,PDO::PARAM_INT); $st->bindValue(2,$offset,PDO::PARAM_INT); $st->execute();
$rows=$st->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4">Complaints</h1></div>

<div class="row g-3">
  <div class="col-md-7">
    <div class="card shadow-sm"><div class="card-body table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr>
          <th><?= sort_link('date','Date received') ?></th>
          <th><?= sort_link('child','Child') ?></th>
          <th>Complainant</th>
          <th>Outcome</th>
          <th><?= sort_link('status','Closed') ?></th>
          <th></th>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['date_received']) ?></td>
              <td><?= htmlspecialchars($r['child_name'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['complainant_name']) ?></td>
              <td><?= htmlspecialchars($r['outcome'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['closed_date'] ?? '') ?></td>
              <td class="text-end">
                <?php if (has_role('manager')): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete complaint?');"><?php csrf_input(); ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger" name="delete" value="1">Delete</button></form>
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
      <h2 class="h6">Add complaint</h2>
      <form method="post"><?php csrf_input(); ?>
        <div class="row g-2">
          <div class="col-md-6"><label class="form-label">Child (optional)</label>
            <select class="form-select" name="child_id"><option value="">—</option><?php foreach ($children as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['last_name'].', '.$c['first_name']) ?></option><?php endforeach; ?></select>
          </div>
          <div class="col-md-6"><label class="form-label">Date received</label><input type="date" class="form-control" name="date_received" value="<?= date('Y-m-d') ?>"></div>
          <div class="col-md-12"><label class="form-label">Complainant</label><input class="form-control" name="complainant_name"></div>
          <div class="col-12"><label class="form-label">Details</label><textarea class="form-control" name="details" rows="3"></textarea></div>
          <div class="col-md-6"><label class="form-label">Outcome</label><input class="form-control" name="outcome"></div>
          <div class="col-md-6"><label class="form-label">Closed date</label><input type="date" class="form-control" name="closed_date"></div>
        </div>
        <div class="mt-3"><button class="btn btn-primary" name="create" value="1">Save</button></div>
      </form>
    </div></div>
  </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_role_any(['manager']); // manager+

$db = get_db();
$entity = $_GET['entity'] ?? '';
$user = isset($_GET['user']) ? (int)$_GET['user'] : 0;

$where=[]; $params=[];
if($entity!==''){ $where[]='a.entity=?'; $params[]=$entity; }
if($user){ $where[]='a.user_id=?'; $params[]=$user; }
$whereSql = $where ? (' WHERE '.implode(' AND ',$where)) : '';

$allowedSort=['time'=>'a.created_at','user'=>'u.name','entity'=>'a.entity','event'=>'a.event_type'];
list($p,$per,$offset)=page_params(20); $order=order_by_clause($allowedSort,'time');

$totalSt=$db->prepare('SELECT COUNT(*) FROM audit_log a LEFT JOIN users u ON u.id=a.user_id'.$whereSql);
$totalSt->execute($params); $total=(int)$totalSt->fetchColumn();

$sql = 'SELECT a.*, u.name as user_name FROM audit_log a LEFT JOIN users u ON u.id=a.user_id' . $whereSql . $order . ' LIMIT ? OFFSET ?';
$st=$db->prepare($sql);
$i=1; foreach($params as $pv){ $st->bindValue($i++, $pv); }
$st->bindValue($i++, $per, PDO::PARAM_INT); $st->bindValue($i++, $offset, PDO::PARAM_INT);
$st->execute(); $rows=$st->fetchAll();

$users=$db->query('SELECT id, name FROM users ORDER BY name')->fetchAll();
?>
<h1 class="h4 mb-3">Audit Log</h1>
<form class="row g-2 mb-3">
  <input type="hidden" name="page" value="audit">
  <div class="col-md-3"><label class="form-label">Entity</label><input class="form-control" name="entity" value="<?= htmlspecialchars($entity) ?>" placeholder="children, incidents, user..."></div>
  <div class="col-md-3"><label class="form-label">User</label><select class="form-select" name="user"><option value="0">All</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $user===(int)$u['id']?'selected':'' ?>><?= htmlspecialchars($u['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><label class="form-label d-block">&nbsp;</label><button class="btn btn-secondary">Filter</button></div>
</form>
<div class="card shadow-sm"><div class="card-body table-responsive">
  <table class="table table-sm align-middle">
    <thead><tr><th><?= sort_link('time','When') ?></th><th><?= sort_link('user','User') ?></th><th><?= sort_link('entity','Entity') ?></th><th><?= sort_link('event','Event') ?></th><th>Meta</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): $meta=$r['meta'] ? json_decode($r['meta'], true) : []; ?>
        <tr>
          <td><?= htmlspecialchars($r['created_at']) ?></td>
          <td><?= htmlspecialchars($r['user_name'] ?? '—') ?></td>
          <td><?= htmlspecialchars($r['entity']) ?> #<?= (int)$r['entity_id'] ?></td>
          <td><span class="badge bg-dark"><?= htmlspecialchars($r['event_type']) ?></span></td>
          <td class="small text-pre-wrap"><?= htmlspecialchars(json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="d-flex justify-content-between align-items-center"><div class="small text-muted">Total: <?= $total ?></div><?php render_pagination($total,$per,$p); ?></div>
</div></div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>

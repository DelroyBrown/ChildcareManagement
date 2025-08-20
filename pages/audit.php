<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_role_any(['manager']); // manager+

$db = get_db();

// Filters
$entity = isset($_GET['entity']) ? trim($_GET['entity']) : '';
$user_id = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$event_type = isset($_GET['event']) ? trim($_GET['event']) : '';

$where = []; $params = [];
if ($entity !== '') { $where[] = 'a.entity = ?'; $params[] = $entity; }
if ($user_id) { $where[] = 'a.user_id = ?'; $params[] = $user_id; }
if ($event_type !== '') { $where[] = 'a.event_type = ?'; $params[] = $event_type; }
$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

// Sorting + pagination
$allowedSort=['time'=>'a.created_at','user'=>'u.name','entity'=>'a.entity','event'=>'a.event_type'];
list($p,$per,$offset)=page_params(20);
$order=order_by_clause($allowedSort,'time');

// Totals
$totalSt = $db->prepare('SELECT COUNT(*) FROM audit_log a LEFT JOIN users u ON u.id=a.user_id' . $whereSql);
$totalSt->execute($params); $total=(int)$totalSt->fetchColumn();

// Rows
$sql = 'SELECT a.*, u.name AS user_name FROM audit_log a LEFT JOIN users u ON u.id=a.user_id' . $whereSql . $order . ' LIMIT ? OFFSET ?';
$st=$db->prepare($sql);
$i=1; foreach ($params as $pv) { $st->bindValue($i++, $pv); }
$st->bindValue($i++, $per, PDO::PARAM_INT); $st->bindValue($i++, $offset, PDO::PARAM_INT);
$st->execute(); $rows=$st->fetchAll();

// For filter dropdown
$users = $db->query('SELECT id, name FROM users ORDER BY name')->fetchAll();

// Simple name caches to avoid repeated queries
$childNameCache = []; $staffNameCache = [];
function child_label_cached(int $id) {
  static $cache = [];
  if(isset($cache[$id])) return $cache[$id];
  try {
    $db = get_db();
    $st = $db->prepare('SELECT first_name, last_name FROM children WHERE id=?');
    $st->execute([$id]);
    $c = $st->fetch();
    $cache[$id] = $c ? trim($c['first_name'].' '.$c['last_name']) : null;
  } catch (Throwable $e) { $cache[$id] = null; }
  return $cache[$id];
}
function staff_label_cached(int $id) {
  static $cache = [];
  if(isset($cache[$id])) return $cache[$id];
  try {
    $db = get_db();
    $st = $db->prepare('SELECT name FROM staff WHERE id=?');
    $st->execute([$id]);
    $s = $st->fetch();
    $cache[$id] = $s ? $s['name'] : null;
  } catch (Throwable $e) { $cache[$id] = null; }
  return $cache[$id];
}

?>
<h1 class="h4 mb-3">Audit Log</h1>
<form class="row g-2 mb-3">
  <input type="hidden" name="page" value="audit">
  <div class="col-md-3">
    <label class="form-label">Entity</label>
    <input class="form-control" name="entity" placeholder="children, incidents, user..." value="<?= htmlspecialchars($entity) ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label">User</label>
    <select class="form-select" name="user">
      <option value="0">All</option>
      <?php foreach ($users as $u): ?>
        <option value="<?= (int)$u['id'] ?>" <?= $user_id===(int)$u['id']?'selected':'' ?>><?= htmlspecialchars($u['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label">Event</label>
    <select class="form-select" name="event">
      <?php foreach ([''=>'All','create'=>'create','update'=>'update','delete'=>'delete'] as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $event_type===$k?'selected':'' ?>><?= htmlspecialchars($v) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2 d-flex align-items-end">
    <button class="btn btn-secondary w-100">Filter</button>
  </div>
</form>

<div class="card shadow-sm"><div class="card-body table-responsive">
  <table class="table table-sm align-middle">
    <thead>
      <tr>
        <th><?= sort_link('time','When') ?></th>
        <th><?= sort_link('user','User') ?></th>
        <th><?= sort_link('entity','Entity') ?></th>
        <th><?= sort_link('event','Event') ?></th>
        <th>Meta</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <?php $meta = $r['meta'] ? json_decode($r['meta'], true) : []; if (!is_array($meta)) { $meta = []; } ?>
        <?php
          $pieces = [];
          // child_id
          if (isset($meta['child_id']) && (int)$meta['child_id'] > 0) {
            $cid = (int)$meta['child_id'];
            $cname = child_label_cached($cid);
            $clabel = $cname ? $cname.' (#'.$cid.')' : 'Child #'.$cid;
            $pieces[] = 'Child: <a href="index.php?page=child_profile&id='.$cid.'">'.htmlspecialchars($clabel).'</a>';
            unset($meta['child_id']);
          }
          // from_staff
          if (isset($meta['from_staff']) && (int)$meta['from_staff'] > 0) {
            $sid = (int)$meta['from_staff'];
            $sname = staff_label_cached($sid);
            $slabel = $sname ? $sname.' (#'.$sid.')' : 'Staff #'.$sid;
            $pieces[] = 'From staff: <a href="index.php?page=staff&edit='.$sid.'">'.htmlspecialchars($slabel).'</a>';
            unset($meta['from_staff']);
          }
          // role
          if (isset($meta['role'])) {
            $role = (string)$meta['role'];
            $cls = $role==='admin' ? 'danger' : ($role==='manager' ? 'primary' : 'secondary');
            $pieces[] = 'Role: <span class="badge bg-'.$cls.'">'.htmlspecialchars($role).'</span>';
            unset($meta['role']);
          }
          // title
          if (isset($meta['title'])) {
            $pieces[] = 'Title: '.htmlspecialchars((string)$meta['title']);
            unset($meta['title']);
          }
          // Render remaining meta key/values compactly
          foreach ($meta as $k => $v) {
            if (is_scalar($v)) {
              $pieces[] = htmlspecialchars($k).': '.htmlspecialchars((string)$v);
            } else {
              // leave for raw
            }
          }
          $metaPretty = implode(' · ', $pieces);
          $rawId = 'raw'.(int)$r['id'];
          $rawJson = json_encode($r['meta'] ? json_decode($r['meta'], true) : new stdClass(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        ?>
        <tr>
          <td><?= htmlspecialchars($r['created_at']) ?></td>
          <td><?= htmlspecialchars($r['user_name'] ?? '—') ?></td>
          <td><?= htmlspecialchars($r['entity']) ?> #<?= (int)$r['entity_id'] ?></td>
          <td><span class="badge bg-dark"><?= htmlspecialchars($r['event_type']) ?></span></td>
          <td>
            <?php if ($metaPretty): ?>
              <div><?= $metaPretty ?></div>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
            <a class="small text-decoration-none" data-bs-toggle="collapse" href="#<?= $rawId ?>" role="button" aria-expanded="false" aria-controls="<?= $rawId ?>">Raw</a>
            <div class="collapse" id="<?= $rawId ?>"><pre class="mt-1 small bg-light p-2 border rounded"><?= htmlspecialchars($rawJson) ?></pre></div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="d-flex justify-content-between align-items-center">
    <div class="small text-muted">Total: <?= $total ?></div>
    <?php render_pagination($total,$per,$p); ?>
  </div>
</div></div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

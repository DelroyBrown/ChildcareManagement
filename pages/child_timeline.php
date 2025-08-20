<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_role_any(['staff','manager','admin']);

$db = get_db();
$child_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($child_id <= 0) { header('Location: index.php?page=children'); exit; }

// fetch child header
$child = null;
$st = $db->prepare('SELECT * FROM children WHERE id=?');
$st->execute([$child_id]);
$child = $st->fetch();
if (!$child) { ?>
  <div class="alert alert-warning">Child not found.</div>
  <?php require_once __DIR__ . '/../partials/footer.php'; exit;
}

// helper to run a select and ignore table-missing errors
function fetch_rows($sql, $params = []) {
  try {
    $db = get_db();
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
  } catch (Throwable $e) {
    // Table/column may not exist in this install; just skip this source
    return [];
  }
}

// compute a safe timestamp from a row using candidate keys
function pick_time(array $row, array $candidates) {
  foreach ($candidates as $k) {
    if (array_key_exists($k, $row) && !empty($row[$k])) {
      $ts = strtotime((string)$row[$k]);
      if ($ts !== false) return $ts;
    }
  }
  return null;
}

$events = [];

// Incidents
foreach (fetch_rows('SELECT i.* FROM incidents i WHERE i.child_id = ?', [$child_id]) as $r) {
  $ts = pick_time($r, ['occurred_at','incident_date','date','created_at']);
  $events[] = ['kind'=>'incident', 'time_ts'=>$ts, 'row'=>$r];
}

// Medications
foreach (fetch_rows('SELECT m.* FROM medications m WHERE m.child_id = ?', [$child_id]) as $r) {
  $ts = pick_time($r, ['administered_at','date','created_at']);
  $events[] = ['kind'=>'medication', 'time_ts'=>$ts, 'row'=>$r];
}

// Visitors
foreach (fetch_rows('SELECT v.* FROM visitors v WHERE v.child_id = ?', [$child_id]) as $r) {
  $ts = pick_time($r, ['visit_date','date','created_at']);
  $events[] = ['kind'=>'visit', 'time_ts'=>$ts, 'row'=>$r];
}

// Complaints
foreach (fetch_rows('SELECT c.* FROM complaints c WHERE c.child_id = ?', [$child_id]) as $r) {
  $ts = pick_time($r, ['date','created_at']);
  $events[] = ['kind'=>'complaint', 'time_ts'=>$ts, 'row'=>$r];
}

// Documents
foreach (fetch_rows('SELECT d.* FROM documents d WHERE d.child_id = ?', [$child_id]) as $r) {
  $ts = pick_time($r, ['uploaded_at','created_at']);
  $events[] = ['kind'=>'document', 'time_ts'=>$ts, 'row'=>$r];
}

// Attendance (optional)
foreach (fetch_rows('SELECT a.* FROM attendance a WHERE a.child_id = ?', [$child_id]) as $r) {
  $ts = pick_time($r, ['date','created_at']);
  $events[] = ['kind'=>'attendance', 'time_ts'=>$ts, 'row'=>$r];
}

// Sort newest first, nulls last
usort($events, function($a,$b){
  $ta = $a['time_ts'] ?? null; $tb = $b['time_ts'] ?? null;
  if ($ta === $tb) return 0;
  if ($ta === null) return 1;
  if ($tb === null) return -1;
  return ($ta < $tb) ? 1 : -1;
});

// Pagination (fallback if helper missing)
$per = isset($_GET['per']) ? max(10, (int)$_GET['per']) : 50;
$p = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$total = count($events);
$offset = ($p-1)*$per;
$chunk = array_slice($events, $offset, $per);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4">Timeline — <?= htmlspecialchars($child['first_name'].' '.$child['last_name']) ?></h1>
  <a class="btn btn-outline-secondary btn-sm" href="index.php?page=child_profile&id=<?= (int)$child_id ?>">Back to Profile</a>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <?php if (!$events): ?>
      <div class="text-muted">No timeline items yet.</div>
    <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($chunk as $e): $r=$e['row']; $dt = $e['time_ts'] ? date('Y-m-d H:i', $e['time_ts']) : '—'; ?>
          <li class="list-group-item">
            <div class="d-flex justify-content-between">
              <div>
                <?php if ($e['kind']==='incident'): ?>
                  <strong>Incident:</strong> <?= htmlspecialchars($r['type'] ?? 'Incident') ?> — <?= htmlspecialchars($r['description'] ?? '') ?>
                  <?php if (!empty($r['severity'])): ?> <span class="badge text-bg-danger"><?= htmlspecialchars($r['severity']) ?></span><?php endif; ?>
                <?php elseif ($e['kind']==='medication'): ?>
                  <strong>Medication:</strong> <?= htmlspecialchars($r['med_name'] ?? 'Medication') ?> — <?= htmlspecialchars($r['dose'] ?? '') ?>
                <?php elseif ($e['kind']==='visit'): ?>
                  <strong>Visit:</strong> <?= htmlspecialchars($r['visitor_name'] ?? 'Visitor') ?> (<?= htmlspecialchars($r['relationship'] ?? '') ?>)
                <?php elseif ($e['kind']==='complaint'): ?>
                  <strong>Complaint:</strong> <?= htmlspecialchars($r['summary'] ?? ($r['details'] ?? 'Complaint')) ?>
                <?php elseif ($e['kind']==='document'): ?>
                  <strong>Document:</strong> <?= htmlspecialchars($r['title'] ?? 'Document') ?><?php if (!empty($r['category'])): ?> (<?= htmlspecialchars($r['category']) ?>)<?php endif; ?>
                  <?php if (!empty($r['file_path'])): ?> — <a href="<?= htmlspecialchars($r['file_path']) ?>" target="_blank">Open</a><?php endif; ?>
                <?php elseif ($e['kind']==='attendance'): ?>
                  <strong>Attendance:</strong> <?= htmlspecialchars($r['status'] ?? 'Recorded') ?>
                <?php endif; ?>
              </div>
              <div class="text-nowrap small text-muted"><?= $dt ?></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="small text-muted">Total: <?= (int)$total ?></div>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <?php
              $pages = max(1, (int)ceil($total / $per));
              $base = 'index.php?page=child_timeline&id='.(int)$child_id.'&per='.$per.'&p=';
              for ($i=1;$i<=$pages;$i++):
            ?>
              <li class="page-item <?= $i===$p?'active':'' ?>"><a class="page-link" href="<?= $base.$i ?>"><?= $i ?></a></li>
            <?php endfor; ?>
          </ul>
        </nav>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

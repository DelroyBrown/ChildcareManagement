<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
$db = get_db();

$month = $_GET['month'] ?? date('Y-m');
list($y,$m) = explode('-', $month);
$start = sprintf('%04d-%02d-01', $y, $m);
$end = date('Y-m-d', strtotime("$start +1 month"));

// Queries (robust pack)
$attendance = $db->prepare("
SELECT c.id, CONCAT(c.first_name,' ',c.last_name) AS child,
SUM(a.status='present') AS present,
SUM(a.status='absent') AS absent,
SUM(a.status='excused') AS excused,
SUM(a.status='offsite') AS offsite,
COUNT(*) AS total
FROM children c
LEFT JOIN attendance a ON a.child_id=c.id AND a.date >= ? AND a.date < ?
GROUP BY c.id, child ORDER BY child
"); $attendance->execute([$start,$end]); $att = $attendance->fetchAll();

$incType = $db->prepare("SELECT type, COUNT(*) AS cnt FROM incidents WHERE date_time >= ? AND date_time < ? GROUP BY type ORDER BY cnt DESC");
$incType->execute([$start,$end]); $incTypes = $incType->fetchAll();

$incSeverity = $db->prepare("SELECT COALESCE(severity,'unspecified') AS severity, COUNT(*) AS cnt FROM incidents WHERE date_time >= ? AND date_time < ? GROUP BY severity ORDER BY FIELD(severity,'high','med','low','unspecified')");
$incSeverity->execute([$start,$end]); $incSev = $incSeverity->fetchAll();

$incByChild = $db->prepare("SELECT CONCAT(c.first_name,' ',c.last_name) AS child, COUNT(*) AS cnt FROM incidents i JOIN children c ON c.id=i.child_id WHERE i.date_time >= ? AND i.date_time < ? GROUP BY child ORDER BY cnt DESC, child");
$incByChild->execute([$start,$end]); $incChild = $incByChild->fetchAll();

$medByChild = $db->prepare("SELECT CONCAT(c.first_name,' ',c.last_name) AS child, COUNT(*) AS cnt FROM medications m JOIN children c ON c.id=m.child_id WHERE m.administered_at >= ? AND m.administered_at < ? GROUP BY child ORDER BY cnt DESC");
$medByChild->execute([$start,$end]); $medChild = $medByChild->fetchAll();

$visChild = $db->prepare("SELECT CONCAT(c.first_name,' ',c.last_name) AS child, COUNT(*) AS cnt FROM visitors v JOIN children c ON c.id=v.child_id WHERE v.visit_date >= ? AND v.visit_date < ? GROUP BY child ORDER BY cnt DESC");
$visChild->execute([$start,$end]); $visChildRows = $visChild->fetchAll();

$topVisitors = $db->prepare("SELECT visitor_name, COUNT(*) AS cnt FROM visitors WHERE visit_date >= ? AND visit_date < ? GROUP BY visitor_name ORDER BY cnt DESC LIMIT 10");
$topVisitors->execute([$start,$end]); $topVisitorRows = $topVisitors->fetchAll();

$complaints = $db->prepare("SELECT COALESCE(outcome,'open') AS status, COUNT(*) AS cnt FROM complaints WHERE date_received >= ? AND date_received < ? GROUP BY status ORDER BY cnt DESC");
$complaints->execute([$start,$end]); $complaintRows = $complaints->fetchAll();

$dbsSoon = $db->query("SELECT name, dbs_check_date FROM staff WHERE dbs_check_date IS NOT NULL AND dbs_check_date <= (CURDATE() + INTERVAL 60 DAY) ORDER BY dbs_check_date ASC")->fetchAll();
?>
<h1 class="h4 mb-3">Reports</h1>
<form class="row g-3 align-items-end mb-3">
  <div class="col-auto"><label class="form-label">Month</label><input type="month" name="month" class="form-control" value="<?= htmlspecialchars($month) ?>"></div>
  <div class="col-auto"><button class="btn btn-secondary">Run</button></div>
</form>

<div class="row g-3">
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-body">
    <h2 class="h6">Attendance summary (<?= htmlspecialchars(date('F Y', strtotime($start))) ?>)</h2>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Child</th><th>Present</th><th>Absent</th><th>Excused</th><th>Offsite</th><th>Rate</th></tr></thead><tbody>
    <?php foreach ($att as $r): $total=max(1,(int)$r['total']); $rate=round(((int)$r['present']+(int)$r['offsite'])/$total*100); ?>
      <tr><td><?= htmlspecialchars($r['child']) ?></td><td><?= (int)$r['present'] ?></td><td><?= (int)$r['absent'] ?></td><td><?= (int)$r['excused'] ?></td><td><?= (int)$r['offsite'] ?></td><td><?= $rate ?>%</td></tr>
    <?php endforeach; ?></tbody></table></div>
  </div></div></div>
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-body">
    <h2 class="h6">Incidents</h2>
    <div class="row">
      <div class="col-md-6"><h3 class="h6">By type</h3><table class="table table-sm"><thead><tr><th>Type</th><th>Count</th></tr></thead><tbody><?php foreach ($incTypes as $r): ?><tr><td><?= htmlspecialchars($r['type']) ?></td><td><?= (int)$r['cnt'] ?></td></tr><?php endforeach; ?></tbody></table></div>
      <div class="col-md-6"><h3 class="h6">By severity</h3><table class="table table-sm"><thead><tr><th>Severity</th><th>Count</th></tr></thead><tbody><?php foreach ($incSev as $r): ?><tr><td><?= htmlspecialchars($r['severity']) ?></td><td><?= (int)$r['cnt'] ?></td></tr><?php endforeach; ?></tbody></table></div>
    </div>
    <h3 class="h6 mt-3">By child</h3><table class="table table-sm"><thead><tr><th>Child</th><th>Count</th></tr></thead><tbody><?php foreach ($incChild as $r): ?><tr><td><?= htmlspecialchars($r['child']) ?></td><td><?= (int)$r['cnt'] ?></td></tr><?php endforeach; ?></tbody></table>
  </div></div></div>
</div>

<div class="row g-3 mt-1">
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-body">
    <h2 class="h6">Medication by child</h2><table class="table table-sm"><thead><tr><th>Child</th><th>Count</th></tr></thead><tbody><?php foreach ($medChild as $r): ?><tr><td><?= htmlspecialchars($r['child']) ?></td><td><?= (int)$r['cnt'] ?></td></tr><?php endforeach; ?></tbody></table>
  </div></div></div>
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-body">
    <h2 class="h6">Visits</h2>
    <div class="row">
      <div class="col-md-6"><h3 class="h6">By child</h3><table class="table table-sm"><thead><tr><th>Child</th><th>Count</th></tr></thead><tbody><?php foreach ($visChildRows as $r): ?><tr><td><?= htmlspecialchars($r['child']) ?></td><td><?= (int)$r['cnt'] ?></td></tr><?php endforeach; ?></tbody></table></div>
      <div class="col-md-6"><h3 class="h6">Top visitors</h3><table class="table table-sm"><thead><tr><th>Visitor</th><th>Count</th></tr></thead><tbody><?php foreach ($topVisitorRows as $r): ?><tr><td><?= htmlspecialchars($r['visitor_name']) ?></td><td><?= (int)$r['cnt'] ?></td></tr><?php endforeach; ?></tbody></table></div>
    </div>
  </div></div>
</div>

<div class="card shadow-sm mt-3"><div class="card-body">
  <h2 class="h6">Complaints status</h2><table class="table table-sm"><thead><tr><th>Status</th><th>Count</th></tr></thead><tbody><?php foreach ($complaintRows as $r): ?><tr><td><?= htmlspecialchars($r['status']) ?></td><td><?= (int)$r['cnt'] ?></td></tr><?php endforeach; ?></tbody></table>
</div></div>

<div class="card shadow-sm mt-3"><div class="card-body">
  <h2 class="h6">DBS checks within 60 days</h2><table class="table table-sm"><thead><tr><th>Staff</th><th>DBS check date</th></tr></thead><tbody><?php foreach ($dbsSoon as $r): ?><tr><td><?= htmlspecialchars($r['name']) ?></td><td><?= htmlspecialchars(format_human_date($r['dbs_check_date'])) ?></td></tr><?php endforeach; if (!$dbsSoon): ?><tr><td colspan="2" class="text-muted">No upcoming DBS checks in the next 60 days.</td></tr><?php endif; ?></tbody></table>
</div></div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

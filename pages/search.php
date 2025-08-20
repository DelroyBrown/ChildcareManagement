<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
$db = get_db();

$q = trim($_GET['q'] ?? '');
$like = '%' . $q . '%';
$likeLoose = '%' . str_replace(' ','%',$q) . '%';

$children=$incidents=$meds=$visits=$docs=[];
if ($q!=='') {
  $st=$db->prepare("SELECT id, first_name, last_name, local_authority, guardian_name FROM children WHERE CONCAT(first_name,' ',last_name) LIKE ? OR guardian_name LIKE ? OR local_authority LIKE ? ORDER BY last_name, first_name LIMIT 50");
  $st->execute([$likeLoose,$like,$like]); $children=$st->fetchAll();

  $st=$db->prepare("SELECT i.id, i.date_time, i.type, i.description, CONCAT(c.first_name,' ',c.last_name) AS child FROM incidents i JOIN children c ON c.id=i.child_id WHERE i.type LIKE ? OR i.description LIKE ? OR CONCAT(c.first_name,' ',c.last_name) LIKE ? ORDER BY i.date_time DESC LIMIT 50");
  $st->execute([$like,$like,$likeLoose]); $incidents=$st->fetchAll();

  $st=$db->prepare("SELECT m.id, m.administered_at, m.med_name, m.dose, CONCAT(c.first_name,' ',c.last_name) AS child FROM medications m JOIN children c ON c.id=m.child_id WHERE m.med_name LIKE ? OR CONCAT(c.first_name,' ',c.last_name) LIKE ? ORDER BY m.administered_at DESC LIMIT 50");
  $st->execute([$like,$likeLoose]); $meds=$st->fetchAll();

  $st=$db->prepare("SELECT v.id, v.visit_date, v.visitor_name, v.relationship, CONCAT(c.first_name,' ',c.last_name) AS child FROM visitors v JOIN children c ON c.id=v.child_id WHERE v.visitor_name LIKE ? OR v.relationship LIKE ? OR CONCAT(c.first_name,' ',c.last_name) LIKE ? ORDER BY v.visit_date DESC LIMIT 50");
  $st->execute([$like,$like,$likeLoose]); $visits=$st->fetchAll();

  $st=$db->prepare("SELECT d.id, d.uploaded_at, d.title, d.category, CONCAT(c.first_name,' ',c.last_name) AS child FROM documents d LEFT JOIN children c ON c.id=d.child_id WHERE d.title LIKE ? OR d.category LIKE ? OR CONCAT(c.first_name,' ',c.last_name) LIKE ? ORDER BY d.uploaded_at DESC LIMIT 50");
  $st->execute([$like,$like,$likeLoose]); $docs=$st->fetchAll();
}
?>
<h1 class="h4 mb-3">Search</h1>
<form class="row g-3 mb-3">
  <div class="col-md-8"><input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search children, incidents, meds, visitors, documents..."></div>
  <div class="col-auto"><button class="btn btn-primary">Search</button></div>
</form>

<?php if ($q===''): ?><div class="text-muted">Enter a keyword to search.</div><?php else: ?>
<div class="row g-3">
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-body">
    <h2 class="h6">Children</h2><ul class="list-group list-group-flush"><?php foreach ($children as $c): ?><li class="list-group-item"><a href="index.php?page=child_timeline&id=<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></a><div class="small text-muted"><?= htmlspecialchars($c['guardian_name'] ?? '') ?> — <?= htmlspecialchars($c['local_authority'] ?? '') ?></div></li><?php endforeach; if (!$children): ?><li class="list-group-item text-muted">No matches</li><?php endif; ?></ul>
  </div></div></div>
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-body">
    <h2 class="h6">Incidents</h2><ul class="list-group list-group-flush"><?php foreach ($incidents as $i): ?><li class="list-group-item"><strong><?= htmlspecialchars($i['type']) ?></strong> — <?= htmlspecialchars($i['child']) ?><div class="small text-muted"><?= htmlspecialchars(format_human_datetime($i['date_time'])) ?></div><div class="small"><?= nl2br(htmlspecialchars($i['description'])) ?></div></li><?php endforeach; if (!$incidents): ?><li class="list-group-item text-muted">No matches</li><?php endif; ?></ul>
  </div></div></div>
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-body">
    <h2 class="h6">Medication</h2><ul class="list-group list-group-flush"><?php foreach ($meds as $m): ?><li class="list-group-item"><strong><?= htmlspecialchars($m['med_name']) ?></strong> <?= htmlspecialchars($m['dose']) ?> — <?= htmlspecialchars($m['child']) ?><div class="small text-muted"><?= htmlspecialchars(format_human_datetime($m['administered_at'])) ?></div></li><?php endforeach; if (!$meds): ?><li class="list-group-item text-muted">No matches</li><?php endif; ?></ul>
  </div></div></div>
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-body">
    <h2 class="h6">Visitors</h2><ul class="list-group list-group-flush"><?php foreach ($visits as $v): ?><li class="list-group-item"><strong><?= htmlspecialchars($v['visitor_name']) ?></strong> (<?= htmlspecialchars($v['relationship']) ?>) — <?= htmlspecialchars($v['child']) ?><div class="small text-muted"><?= htmlspecialchars(format_human_date($v['visit_date'])) ?></div></li><?php endforeach; if (!$visits): ?><li class="list-group-item text-muted">No matches</li><?php endif; ?></ul>
  </div></div></div>
  <div class="col-md-12"><div class="card shadow-sm"><div class="card-body">
    <h2 class="h6">Documents</h2><ul class="list-group list-group-flush"><?php foreach ($docs as $d): ?><li class="list-group-item d-flex justify-content-between"><span><strong><?= htmlspecialchars($d['title']) ?></strong> (<?= htmlspecialchars($d['category'] ?? 'General') ?>) — <?= htmlspecialchars($d['child'] ?? '—') ?></span><span class="small text-muted"><?= htmlspecialchars($d['uploaded_at']) ?></span></li><?php endforeach; if (!$docs): ?><li class="list-group-item text-muted">No matches</li><?php endif; ?></ul>
  </div></div></div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>

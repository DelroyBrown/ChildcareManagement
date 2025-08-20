<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_role_any(['staff','manager','admin']);
$db = get_db();

$q = trim($_GET['q'] ?? '');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4">Search</h1>
  <form class="d-flex" method="get" action="index.php">
    <input type="hidden" name="page" value="search">
    <input class="form-control form-control-sm me-2" name="q" placeholder="Name, keyword..." value="<?= htmlspecialchars($q) ?>">
    <button class="btn btn-sm btn-primary">Search</button>
  </form>
</div>
<?php if ($q === ''): ?>
  <div class="alert alert-info">Type a child name or keyword and press Search.</div>
  <?php require_once __DIR__ . '/../partials/footer.php'; exit; ?>
<?php endif; ?>
<?php
$like = '%'.$q.'%';
// Children
$children = $db->prepare("SELECT id, first_name, last_name, dob, gender FROM children WHERE first_name LIKE ? OR last_name LIKE ? ORDER BY first_name ASC");
$children->execute([$like,$like]);
$children = $children->fetchAll();

// Incidents (no hard-coded date column)
$incidents = [];
try {
  $st = $db->prepare("SELECT i.id, i.child_id, i.type, i.severity, i.status, i.description FROM incidents i WHERE i.type LIKE ? OR i.description LIKE ? ORDER BY i.id DESC LIMIT 25");
  $st->execute([$like,$like]);
  $incidents = $st->fetchAll();
} catch (Throwable $e) {}

// Documents
$documents = [];
try {
  $st = $db->prepare("SELECT d.id, d.child_id, d.title, d.category FROM documents d WHERE d.title LIKE ? OR d.category LIKE ? ORDER BY d.id DESC LIMIT 25");
  $st->execute([$like,$like]);
  $documents = $st->fetchAll();
} catch (Throwable $e) {}

// Visits
$visits = [];
try {
  $st = $db->prepare("SELECT v.id, v.child_id, v.visitor_name, v.relationship FROM visitors v WHERE v.visitor_name LIKE ? OR v.relationship LIKE ? ORDER BY v.id DESC LIMIT 25");
  $st->execute([$like,$like]);
  $visits = $st->fetchAll();
} catch (Throwable $e) {}

// Medications
$meds = [];
try {
  $st = $db->prepare("SELECT m.id, m.child_id, m.med_name, m.dose FROM medications m WHERE m.med_name LIKE ? OR m.dose LIKE ? ORDER BY m.id DESC LIMIT 25");
  $st->execute([$like,$like]);
  $meds = $st->fetchAll();
} catch (Throwable $e) {}

// Complaints
$complaints = [];
try {
  $st = $db->prepare("SELECT c.id, c.child_id, c.summary FROM complaints c WHERE c.summary LIKE ? OR c.details LIKE ? ORDER BY c.id DESC LIMIT 25");
  $st->execute([$like,$like]);
  $complaints = $st->fetchAll();
} catch (Throwable $e) {}

function h($s){ return htmlspecialchars((string)$s); }
?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6 mb-3">Children</h2>
        <?php if (!$children): ?>
          <div class="text-muted">No matching children.</div>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($children as $c): $cid=(int)$c['id']; ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <a href="#" class="link-primary view-child" data-child="<?= $cid ?>"><?= h($c['first_name'].' '.$c['last_name']) ?></a>
                  <div class="small text-muted"><?= h($c['gender']) ?> · DOB: <?= date('F jS Y', strtotime($c['dob'])) ?></div>
                </div>
                <div class="btn-group btn-group-sm">
                  <a class="btn btn-outline-secondary" href="index.php?page=child_profile&id=<?= $cid ?>">Profile</a>
                  <a class="btn btn-outline-secondary" href="index.php?page=child_timeline&id=<?= $cid ?>">Timeline</a>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6 mb-3">Incidents</h2>
        <?php if (!$incidents): ?><div class="text-muted">No incidents.</div><?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($incidents as $i): ?>
              <li class="list-group-item">
                <strong><?= h($i['type']) ?></strong> — <?= h($i['description']) ?>
                <div class="small text-muted">Child ID: <?= (int)$i['child_id'] ?><?= $i['severity'] ? ' · Severity: '.h($i['severity']) : '' ?><?= $i['status'] ? ' · Status: '.h($i['status']) : '' ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6 mb-3">Documents</h2>
        <?php if (!$documents): ?><div class="text-muted">No documents.</div><?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($documents as $d): ?>
              <li class="list-group-item">
                <strong><?= h($d['title']) ?></strong> <?= $d['category']? '(' . h($d['category']) . ')':'' ?>
                <div class="small text-muted">Child ID: <?= (int)$d['child_id'] ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6 mb-3">Visits</h2>
        <?php if (!$visits): ?><div class="text-muted">No visits.</div><?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($visits as $v): ?>
              <li class="list-group-item">
                <strong><?= h($v['visitor_name']) ?></strong> — <?= h($v['relationship']) ?>
                <div class="small text-muted">Child ID: <?= (int)$v['child_id'] ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6 mb-3">Medications</h2>
        <?php if (!$meds): ?><div class="text-muted">No medications.</div><?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($meds as $m): ?>
              <li class="list-group-item">
                <strong><?= h($m['med_name']) ?></strong> — <?= h($m['dose']) ?>
                <div class="small text-muted">Child ID: <?= (int)$m['child_id'] ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6 mb-3">Complaints</h2>
        <?php if (!$complaints): ?><div class="text-muted">No complaints.</div><?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($complaints as $c): ?>
              <li class="list-group-item">
                <?= h($c['summary']) ?>
                <div class="small text-muted">Child ID: <?= (int)$c['child_id'] ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Child quick-view modal -->
<div class="modal fade" id="childQuickView" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Child</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="childQuickContent" class="small text-muted">Loading…</div>
      </div>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.view-child').forEach(function(link){
  link.addEventListener('click', function(ev){
    ev.preventDefault();
    const id = this.getAttribute('data-child');
    fetch('index.php?page=child_profile&id='+encodeURIComponent(id)+'&partial=1', {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(r => r.text())
      .then(html => {
        document.getElementById('childQuickContent').innerHTML = html;
        const modal = new bootstrap.Modal(document.getElementById('childQuickView'));
        modal.show();
      })
      .catch(() => {
        // Fallback to full page
        window.location = 'index.php?page=child_profile&id='+encodeURIComponent(id);
      });
  });
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

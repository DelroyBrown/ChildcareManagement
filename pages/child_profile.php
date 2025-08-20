<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_login();
$db = get_db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$st=$db->prepare('SELECT * FROM children WHERE id=?'); $st->execute([$id]); $child=$st->fetch();
if (!$child) { http_response_code(404); echo '<div class="p-3">Child not found.</div>'; exit; }

$inc=$db->prepare('SELECT date_time, type, severity, description, action_taken FROM incidents WHERE child_id=? ORDER BY date_time DESC LIMIT 10'); $inc->execute([$id]); $incidents=$inc->fetchAll();
$vis=$db->prepare('SELECT visit_date, visitor_name, relationship, id_checked, notes FROM visitors WHERE child_id=? ORDER BY visit_date DESC LIMIT 10'); $vis->execute([$id]); $visitors=$vis->fetchAll();
$med=$db->prepare('SELECT med_name, dose, administered_at, administered_by, notes FROM medications WHERE child_id=? ORDER BY administered_at DESC LIMIT 10'); $med->execute([$id]); $meds=$med->fetchAll();
$comp=$db->prepare('SELECT date_received, complainant_name, outcome, details FROM complaints WHERE child_id=? ORDER BY date_received DESC LIMIT 10'); $comp->execute([$id]); $complaints=$comp->fetchAll();
$att=$db->prepare("SELECT status, COUNT(*) cnt FROM attendance WHERE child_id=? AND date >= (CURDATE() - INTERVAL 60 DAY) GROUP BY status"); $att->execute([$id]); $attrows=$att->fetchAll(PDO::FETCH_KEY_PAIR);
$docs=$db->prepare('SELECT id, title, category, file_path, uploaded_at FROM documents WHERE child_id=? ORDER BY uploaded_at DESC, id DESC LIMIT 10'); $docs->execute([$id]); $documents=$docs->fetchAll();
?>
<div class="modal-header">
  <h5 class="modal-title">Child Profile — <?= htmlspecialchars($child['first_name'].' '.$child['last_name']) ?></h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
  <div class="row g-3">
    <div class="col-md-12">
      <div class="card shadow-sm"><div class="card-body">
        <h6 class="text-muted mb-2">Core Details</h6>
        <div class="row">
          <div class="col-md-3"><strong>DOB:</strong> <?= htmlspecialchars(format_human_date($child['dob'])) ?></div>
          <div class="col-md-2"><strong>Gender:</strong> <?= htmlspecialchars($child['gender']) ?></div>
          <div class="col-md-3"><strong>Admission:</strong> <?= htmlspecialchars(format_human_date($child['admission_date'])) ?></div>
          <div class="col-md-4"><strong>Local Authority:</strong> <?= htmlspecialchars($child['local_authority']) ?></div>
        </div>
        <div class="row mt-2">
          <div class="col-md-4"><strong>Guardian:</strong> <?= htmlspecialchars($child['guardian_name']) ?></div>
          <div class="col-md-4"><strong>Contact:</strong> <?= htmlspecialchars($child['guardian_contact']) ?></div>
          <div class="col-md-4"><strong>Social Worker:</strong> <?= htmlspecialchars($child['social_worker']) ?></div>
        </div>
        <div class="row mt-2">
          <div class="col-md-4"><strong>Placement Type:</strong> <?= htmlspecialchars($child['placement_type']) ?></div>
          <div class="col-md-8"><strong>Medical Notes:</strong> <?= nl2br(htmlspecialchars($child['medical_notes'])) ?></div>
        </div>
        <?php if (!empty($child['care_plan'])): ?><div class="mt-2"><strong>Care Plan:</strong><br><?= nl2br(htmlspecialchars($child['care_plan'])) ?></div><?php endif; ?>
        <?php if (!empty($child['risk_flags'])): ?><div class="mt-2"><?php foreach (explode(',', $child['risk_flags']) as $f): $f=trim($f); if($f==='') continue; ?><span class="badge bg-danger me-1"><?= htmlspecialchars($f) ?></span><?php endforeach; ?></div><?php endif; ?>
      </div></div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm h-100"><div class="card-body">
        <h6 class="text-muted mb-2">Attendance (last 60 days)</h6>
        <div class="d-flex gap-3 flex-wrap">
          <span class="badge bg-success">Present: <?= (int)($attrows['present'] ?? 0) ?></span>
          <span class="badge bg-danger">Absent: <?= (int)($attrows['absent'] ?? 0) ?></span>
          <span class="badge bg-secondary">Excused: <?= (int)($attrows['excused'] ?? 0) ?></span>
          <span class="badge bg-warning text-dark">Offsite: <?= (int)($attrows['offsite'] ?? 0) ?></span>
        </div>
      </div></div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm h-100"><div class="card-body">
        <h6 class="text-muted mb-2">Recent Medication</h6>
        <?php if ($meds): ?><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Medication</th><th>Dose</th><th>By</th></tr></thead><tbody>
          <?php foreach ($meds as $m): ?><tr><td><?= htmlspecialchars(format_human_datetime($m['administered_at'])) ?></td><td><?= htmlspecialchars($m['med_name']) ?></td><td><?= htmlspecialchars($m['dose']) ?></td><td><?= htmlspecialchars($m['administered_by']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php else: ?><div class="text-muted">No medication records.</div><?php endif; ?>
      </div></div>
    </div>

    <div class="col-md-12">
      <div class="card shadow-sm"><div class="card-body">
        <h6 class="text-muted mb-2">Recent Incidents</h6>
        <?php if ($incidents): ?><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Action Taken</th></tr></thead><tbody>
          <?php foreach ($incidents as $i): ?><tr><td><?= htmlspecialchars(format_human_datetime($i['date_time'])) ?></td><td><?= htmlspecialchars($i['type'] . ($i['severity']? ' ('.$i['severity'].')':'')) ?></td><td><?= nl2br(htmlspecialchars($i['description'])) ?></td><td><?= nl2br(htmlspecialchars($i['action_taken'])) ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php else: ?><div class="text-muted">No incident records.</div><?php endif; ?>
      </div></div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm h-100"><div class="card-body">
        <h6 class="text-muted mb-2">Recent Visits</h6>
        <?php if ($visitors): ?><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Visitor</th><th>Relationship</th><th>ID</th></tr></thead><tbody>
          <?php foreach ($visitors as $v): ?><tr><td><?= htmlspecialchars(format_human_date($v['visit_date'])) ?></td><td><?= htmlspecialchars($v['visitor_name']) ?></td><td><?= htmlspecialchars($v['relationship']) ?></td><td><?= $v['id_checked']?'Yes':'No' ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php else: ?><div class="text-muted">No recent visits.</div><?php endif; ?>
      </div></div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm h-100"><div class="card-body">
        <h6 class="text-muted mb-2">Documents</h6>
        <?php if ($documents): ?><ul class="list-group list-group-flush">
          <?php foreach ($documents as $d): ?><li class="list-group-item px-0 d-flex justify-content-between align-items-center">
            <span><strong><?= htmlspecialchars($d['title']) ?></strong> <span class="text-muted small">(<?= htmlspecialchars($d['category'] ?? 'General') ?>)</span><br><span class="small text-muted"><?= htmlspecialchars($d['uploaded_at']) ?></span></span>
            <?php if (is_file(__DIR__.'/../'.$d['file_path'])): ?><a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($d['file_path']) ?>" target="_blank">Open</a><?php else: ?><span class="badge bg-warning text-dark">Missing file</span><?php endif; ?>
          </li><?php endforeach; ?>
        </ul><?php else: ?><div class="text-muted">No documents uploaded.</div><?php endif; ?>
        <a class="btn btn-sm btn-outline-primary mt-2" href="index.php?page=documents&child_id=<?= (int)$id ?>">Manage Documents</a>
      </div></div>
    </div>
  </div>
</div>
<div class="modal-footer">
  <a href="index.php?page=child_timeline&id=<?= (int)$id ?>" class="btn btn-outline-secondary">View full timeline</a>
  <a href="index.php?page=children&edit=<?= (int)$id ?>" class="btn btn-primary">Edit Child</a>
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>

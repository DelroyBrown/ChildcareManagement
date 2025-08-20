<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
$db = get_db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$st=$db->prepare('SELECT * FROM children WHERE id=?'); $st->execute([$id]); $child=$st->fetch();
if (!$child) { http_response_code(404); die('Child not found'); }

$events = [];
// Attendance
$st = $db->prepare('SELECT date as dt, status as label, notes FROM attendance WHERE child_id=? ORDER BY date DESC LIMIT 500'); $st->execute([$id]);
foreach ($st as $r) { $events[] = ['when'=>$r['dt'],'type'=>'Attendance','title'=>ucfirst($r['label']),'details'=>$r['notes']??'']; }
// Incidents
$st = $db->prepare('SELECT date_time as dt, type, severity, description, action_taken FROM incidents WHERE child_id=? ORDER BY date_time DESC LIMIT 500'); $st->execute([$id]);
foreach ($st as $r) { $events[] = ['when'=>$r['dt'],'type'=>'Incident','title'=>$r['type'].($r['severity']?' ('.$r['severity'].')':''),'details'=>trim(($r['description']??'').\"\\nAction: \".($r['action_taken']??''))]; }
// Meds
$st = $db->prepare('SELECT administered_at as dt, med_name, dose, administered_by, notes FROM medications WHERE child_id=? ORDER BY administered_at DESC LIMIT 500'); $st->execute([$id]);
foreach ($st as $r) { $events[] = ['when'=>$r['dt'],'type'=>'Medication','title'=>$r['med_name'].($r['dose']?' ('.$r['dose'].')':''),'details'=>'By: '.($r['administered_by']??'').($r['notes']?'\\n'.$r['notes']:'')]; }
// Visitors
$st = $db->prepare('SELECT visit_date as dt, visitor_name, relationship, id_checked, notes FROM visitors WHERE child_id=? ORDER BY visit_date DESC LIMIT 500'); $st->execute([$id]);
foreach ($st as $r) { $events[] = ['when'=>$r['dt'],'type'=>'Visit','title'=>$r['visitor_name'].($r['relationship']?' ('.$r['relationship'].')':''),'details'=>($r['id_checked']?'ID checked':'ID not checked').($r['notes']?'\\n'.$r['notes']:'')]; }
// Complaints
$st = $db->prepare('SELECT date_received as dt, complainant_name, outcome, details FROM complaints WHERE child_id=? ORDER BY date_received DESC LIMIT 500'); $st->execute([$id]);
foreach ($st as $r) { $events[] = ['when'=>$r['dt'],'type'=>'Complaint','title'=>'Complaint'.($r['complainant_name']?' by '.$r['complainant_name']:''),'details'=>($r['outcome']?'Outcome: '.$r['outcome'].'\\n':'').($r['details']??'')]; }
// Documents
$st = $db->prepare('SELECT uploaded_at as dt, title, category, file_path FROM documents WHERE child_id=? ORDER BY uploaded_at DESC LIMIT 500'); $st->execute([$id]);
foreach ($st as $r) { $events[] = ['when'=>$r['dt'],'type'=>'Document','title'=>$r['title'].($r['category']?' ('.$r['category'].')':''),'details'=>$r['file_path']]; }

usort($events, fn($a,$b)=>strcmp($b['when'],$a['when']));
?>
<h1 class="h4 mb-3">Timeline — <?= htmlspecialchars($child['first_name'].' '.$child['last_name']) ?></h1>
<div class="mb-3">
  <a class="btn btn-secondary btn-sm" href="index.php?page=children">Back to Children</a>
  <a class="btn btn-outline-primary btn-sm" href="index.php?page=export_csv&type=timeline&child_id=<?= (int)$id ?>">Export CSV</a>
</div>

<div class="card shadow-sm"><div class="card-body">
  <div class="row mb-3"><div class="col-md-12"><input class="form-control" id="timelineFilter" placeholder="Filter by keyword or type (e.g., incident, medication, absent)..."></div></div>
  <ul class="list-group" id="timelineList">
    <?php foreach ($events as $e): ?>
      <li class="list-group-item">
        <div class="d-flex justify-content-between">
          <div><span class="badge bg-dark me-2"><?= htmlspecialchars($e['type']) ?></span><strong><?= htmlspecialchars($e['title']) ?></strong></div>
          <span class="text-muted"><?= htmlspecialchars($e['when']) ?></span>
        </div>
        <?php if (trim($e['details'])!==''): ?><div class="mt-1 small text-pre-wrap"><?= nl2br(htmlspecialchars($e['details'])) ?></div><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div></div>
<script>
document.getElementById('timelineFilter').addEventListener('input', function(){ const q=this.value.toLowerCase(); document.querySelectorAll('#timelineList li').forEach(li=>{ li.style.display = li.innerText.toLowerCase().includes(q)?'':'none'; }); });
</script>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>

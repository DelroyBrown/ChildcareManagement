<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
$db = get_db();

$date = $_GET['date'] ?? date('Y-m-d');

if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  foreach ($_POST['status'] as $child_id=>$status) {
    $notes = $_POST['notes'][$child_id] ?? null;
    $st = $db->prepare("INSERT INTO attendance (child_id,date,status,notes) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), notes=VALUES(notes)");
    $st->execute([(int)$child_id, $date, $status, $notes]);
  }
  flash('Attendance saved'); header('Location: index.php?page=attendance&date=' . urlencode($date)); exit;
}

$children = $db->query('SELECT id, first_name, last_name FROM children ORDER BY last_name, first_name')->fetchAll();
$existing = $db->prepare('SELECT * FROM attendance WHERE date=?'); $existing->execute([$date]); $map=[];
foreach ($existing as $r) { $map[(int)$r['child_id']] = $r; }
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4">Attendance</h1>
  <form class="d-flex" method="get">
    <input type="hidden" name="page" value="attendance">
    <input type="date" class="form-control me-2" name="date" value="<?= htmlspecialchars($date) ?>">
    <button class="btn btn-secondary">Go</button>
  </form>
</div>

<form method="post"><?php csrf_input(); ?>
  <div class="card shadow-sm"><div class="card-body table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>Child</th><th>Status</th><th>Notes</th></tr></thead><tbody>
        <?php foreach ($children as $c): $r = $map[(int)$c['id']] ?? null; $st = $r['status'] ?? ''; ?>
          <tr>
            <td><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></td>
            <td>
              <select class="form-select form-select-sm" name="status[<?= (int)$c['id'] ?>]">
                <option value="" <?= $st===''?'selected':'' ?>>—</option>
                <?php foreach (['present','absent','excused','offsite'] as $opt): ?>
                  <option value="<?= $opt ?>" <?= $st===$opt?'selected':'' ?>><?= ucfirst($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input class="form-control form-control-sm" name="notes[<?= (int)$c['id'] ?>]" value="<?= htmlspecialchars($r['notes'] ?? '') ?>"></td>
          </tr>
        <?php endforeach; ?>
      </tbody></table>
  </div></div>
  <div class="mt-3"><button class="btn btn-primary">Save attendance</button></div>
</form>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>

<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
$db = get_db();

$counts = [
  'children' => (int)$db->query("SELECT COUNT(*) FROM children")->fetchColumn(),
  'staff' => (int)$db->query("SELECT COUNT(*) FROM staff")->fetchColumn(),
  'incidents' => (int)$db->query("SELECT COUNT(*) FROM incidents WHERE date_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
  'medications' => (int)$db->query("SELECT COUNT(*) FROM medications WHERE administered_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
];
?>
<div class="row g-3">
  <?php foreach ($counts as $label=>$n): $href = 'index.php?page=' . ($label==='children'?'children':($label==='staff'?'staff':($label==='incidents'?'incidents':($label==='medications'?'medications':'')))); ?>
  <div class="col-md-3">
    <a href="<?= $href ?>" class="text-decoration-none text-reset">
      <div class="card shadow-sm hover-shadow">
        <div class="card-body">
          <div class="text-muted text-uppercase small"><?= htmlspecialchars($label) ?></div>
          <div class="display-6"><?= (int)$n ?></div>
          <div class="small text-muted"><?= $label==='incidents'||$label==='medications' ? 'Last 30 days' : '&nbsp;' ?></div>
        </div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<div class="mt-4 card shadow-sm">
  <div class="card-body table-responsive">
    <h2 class="h5 mb-3">All Children</h2>
    <?php $children = $db->query('SELECT id, first_name, last_name, dob, local_authority, risk_flags FROM children ORDER BY last_name, first_name')->fetchAll(); ?>
    <table class="table table-sm align-middle">
      <thead><tr><th>Name</th><th>DOB</th><th>Local Authority</th><th>Risk</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($children as $c): ?>
          <tr>
            <td><a href="#" class="child-profile-link" data-child-id="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></a></td>
            <td><?= htmlspecialchars(format_human_date($c['dob'])) ?></td>
            <td><?= htmlspecialchars($c['local_authority'] ?? '') ?></td>
            <td>
              <?php if (!empty($c['risk_flags'])): foreach (explode(',', $c['risk_flags']) as $flag): $flag=trim($flag); if ($flag==='') continue; ?>
                <span class="badge bg-danger"><?= htmlspecialchars($flag) ?></span>
              <?php endforeach; endif; ?>
            </td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="index.php?page=child_timeline&id=<?= (int)$c['id'] ?>">Timeline</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Child Profile Modal container -->
<div class="modal fade" id="childProfileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content" id="childProfileContent">
      <div class="modal-body p-5 text-center">Loading…</div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

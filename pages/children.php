<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
$db = get_db();

// Create/Update/Delete
if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  if (isset($_POST['create'])) { if (!has_role('manager')) { http_response_code(403); die('Forbidden'); }
    $st=$db->prepare('INSERT INTO children (first_name,last_name,dob,gender,admission_date,guardian_name,guardian_contact,social_worker,local_authority,placement_type,care_plan,medical_notes,risk_flags,gp_name,gp_phone,nhs_number,sen_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $st->execute([$_POST['first_name'],$_POST['last_name'],$_POST['dob'],$_POST['gender']?:null,$_POST['admission_date']?:null,$_POST['guardian_name']?:null,$_POST['guardian_contact']?:null,$_POST['social_worker']?:null,$_POST['local_authority']?:null,$_POST['placement_type']?:null,$_POST['care_plan']?:null,$_POST['medical_notes']?:null,$_POST['risk_flags']?:null,$_POST['gp_name']?:null,$_POST['gp_phone']?:null,$_POST['nhs_number']?:null,$_POST['sen_status']?:null]);
    flash('Child created'); header('Location: index.php?page=children'); exit;
  }
  if (isset($_POST['update'])) { if (!has_role('manager')) { http_response_code(403); die('Forbidden'); }
    $st=$db->prepare('UPDATE children SET first_name=?,last_name=?,dob=?,gender=?,admission_date=?,guardian_name=?,guardian_contact=?,social_worker=?,local_authority=?,placement_type=?,care_plan=?,medical_notes=?,risk_flags=?,gp_name=?,gp_phone=?,nhs_number=?,sen_status=? WHERE id=?');
    $st->execute([$_POST['first_name'],$_POST['last_name'],$_POST['dob'],$_POST['gender']?:null,$_POST['admission_date']?:null,$_POST['guardian_name']?:null,$_POST['guardian_contact']?:null,$_POST['social_worker']?:null,$_POST['local_authority']?:null,$_POST['placement_type']?:null,$_POST['care_plan']?:null,$_POST['medical_notes']?:null,$_POST['risk_flags']?:null,$_POST['gp_name']?:null,$_POST['gp_phone']?:null,$_POST['nhs_number']?:null,$_POST['sen_status']?:null,$_POST['id']]);
    flash('Changes saved'); header('Location: index.php?page=children'); exit;
  }
  if (isset($_POST['delete'])) { if (!has_role('admin')) { http_response_code(403); die('Forbidden'); }
    $st=$db->prepare('DELETE FROM children WHERE id=?'); $st->execute([$_POST['id']]);
    flash('Child deleted'); header('Location: index.php?page=children'); exit;
  }
}

$edit=null;
if (isset($_GET['edit'])) { $st=$db->prepare('SELECT * FROM children WHERE id=?'); $st->execute([$_GET['edit']]); $edit=$st->fetch(); }

$allowedSort=['name'=>'last_name, first_name','dob'=>'dob','la'=>'local_authority','admit'=>'admission_date'];
list($p,$per,$offset)=page_params(20);
$order=order_by_clause($allowedSort,'name');
$total=(int)$db->query('SELECT COUNT(*) FROM children')->fetchColumn();
$st=$db->prepare('SELECT * FROM children'.$order.' LIMIT ? OFFSET ?');
$st->bindValue(1,$per,PDO::PARAM_INT); $st->bindValue(2,$offset,PDO::PARAM_INT); $st->execute();
$rows=$st->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4">Children</h1>
  <a class="btn btn-secondary" href="index.php?page=dashboard">Back</a>
</div>

<div class="row g-3">
  <div class="col-md-7">
    <div class="card shadow-sm">
      <div class="card-body table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th><?= sort_link("name","Name") ?></th><th><?= sort_link("dob","DOB") ?></th><th><?= sort_link("la","LA") ?></th><th>Risk</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($rows as $c): ?>
              <tr>
                <td><a href="#" class="child-profile-link" data-child-id="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></a></td>
                <td><?= htmlspecialchars(format_human_date($c['dob'])) ?></td>
                <td><?= htmlspecialchars($c['local_authority'] ?? '') ?></td>
                <td><?php if (!empty($c['risk_flags'])): foreach (explode(',', $c['risk_flags']) as $f): $f=trim($f); if ($f==='') continue; ?><span class="badge bg-danger"><?= htmlspecialchars($f) ?></span> <?php endforeach; endif; ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="index.php?page=child_timeline&id=<?= (int)$c['id'] ?>">Timeline</a>
                  <?php if (has_role('manager')): ?><a class="btn btn-sm btn-outline-secondary" href="index.php?page=children&edit=<?= (int)$c['id'] ?>">Edit</a><?php endif; ?>
                  <?php if (has_role('admin')): ?><form method="post" class="d-inline" onsubmit="return confirm('Delete child?');">
                    <?php csrf_input(); ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" name="delete" value="1">Delete</button>
                  </form><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="d-flex justify-content-between align-items-center"><div class="small text-muted">Total: <?= $total ?></div><?php render_pagination($total,$per,$p); ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <?php if (has_role('manager')): ?><h2 class="h6"><?= $edit?'Edit Child':'Add Child' ?></h2>
        <form method="post">
          <?php csrf_input(); if($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
          <div class="row g-2">
            <div class="col-md-6"><label class="form-label">First name</label><input class="form-control" name="first_name" value="<?= htmlspecialchars($edit['first_name'] ?? '') ?>" required></div>
            <div class="col-md-6"><label class="form-label">Last name</label><input class="form-control" name="last_name" value="<?= htmlspecialchars($edit['last_name'] ?? '') ?>" required></div>
            <div class="col-md-4"><label class="form-label">DOB</label><input type="date" class="form-control" name="dob" value="<?= htmlspecialchars($edit['dob'] ?? '') ?>" required></div>
            <div class="col-md-4"><label class="form-label">Gender</label><input class="form-control" name="gender" value="<?= htmlspecialchars($edit['gender'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label">Admission</label><input type="date" class="form-control" name="admission_date" value="<?= htmlspecialchars($edit['admission_date'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">Guardian</label><input class="form-control" name="guardian_name" value="<?= htmlspecialchars($edit['guardian_name'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">Contact</label><input class="form-control" name="guardian_contact" value="<?= htmlspecialchars($edit['guardian_contact'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">Social worker</label><input class="form-control" name="social_worker" value="<?= htmlspecialchars($edit['social_worker'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">Local Authority</label><input class="form-control" name="local_authority" value="<?= htmlspecialchars($edit['local_authority'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">Placement</label><input class="form-control" name="placement_type" value="<?= htmlspecialchars($edit['placement_type'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">SEN status</label><input class="form-control" name="sen_status" value="<?= htmlspecialchars($edit['sen_status'] ?? '') ?>"></div>
            <div class="col-md-12"><label class="form-label">Care plan</label><textarea class="form-control" name="care_plan" rows="2"><?= htmlspecialchars($edit['care_plan'] ?? '') ?></textarea></div>
            <div class="col-md-12"><label class="form-label">Medical notes</label><textarea class="form-control" name="medical_notes" rows="2"><?= htmlspecialchars($edit['medical_notes'] ?? '') ?></textarea></div>
            <div class="col-md-12"><label class="form-label">Risk flags (comma-separated)</label><input class="form-control" name="risk_flags" value="<?= htmlspecialchars($edit['risk_flags'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label">GP name</label><input class="form-control" name="gp_name" value="<?= htmlspecialchars($edit['gp_name'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label">GP phone</label><input class="form-control" name="gp_phone" value="<?= htmlspecialchars($edit['gp_phone'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label">NHS number</label><input class="form-control" name="nhs_number" value="<?= htmlspecialchars($edit['nhs_number'] ?? '') ?>"></div>
          </div>
          <div class="mt-3">
            <?php if ($edit): ?>
              <button class="btn btn-primary" name="update" value="1">Save changes</button>
              <a class="btn btn-secondary" href="index.php?page=children">Cancel</a>
            <?php else: ?>
              <button class="btn btn-primary" name="create" value="1">Add child</button>
            <?php endif; ?>
          </div>
        </form><?php else: ?><div class="alert alert-info mb-0">Read-only for your role.</div><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal container (if not on dashboard) -->
<div class="modal fade" id="childProfileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content" id="childProfileContent">
      <div class="modal-body p-5 text-center">Loading…</div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

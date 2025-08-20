<?php
// tools/apply_patch_v4.php — safer (no heredocs)
error_reporting(E_ALL);
ini_set('display_errors', 1);

function patch_file($path, callable $fn)
{
  if (!file_exists($path)) {
    throw new Exception("Missing file: $path");
  }
  $orig = file_get_contents($path);
  $new = $fn($orig);
  if ($new !== $orig) {
    file_put_contents($path, $new);
    echo "Patched: " . htmlspecialchars($path) . "<br>";
  } else {
    echo "No changes needed: " . htmlspecialchars($path) . "<br>";
  }
}
function write_file($path, $content)
{
  $dir = dirname($path);
  if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
  }
  file_put_contents($path, $content);
  echo "Added: " . htmlspecialchars($path) . "<br>";
}

$root = realpath(__DIR__ . '/..');
if (!$root) {
  die("Could not resolve app root");
}

// 1) migrate_mysql.php — add audit_log + is_active + created_at
patch_file($root . '/app/migrate_mysql.php', function ($s) {
  if (strpos($s, 'CREATE TABLE IF NOT EXISTS audit_log') === false) {
    $append = "\n\n// --- schema updates (v4) ---\n";
    $append .= "try { \$db->exec(\"ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1\"); } catch (Throwable \$e) { }\n";
    $append .= "try { \$db->exec(\"ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\"); } catch (Throwable \$e) { }\n";
    $append .= "\$db->exec(\"CREATE TABLE IF NOT EXISTS audit_log (\n";
    $append .= "  id INT AUTO_INCREMENT PRIMARY KEY,\n";
    $append .= "  user_id INT NULL,\n";
    $append .= "  event_type VARCHAR(40) NOT NULL,\n";
    $append .= "  entity VARCHAR(40) NOT NULL,\n";
    $append .= "  entity_id INT NOT NULL,\n";
    $append .= "  meta TEXT NULL,\n";
    $append .= "  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
    $append .= "  INDEX (entity, entity_id),\n";
    $append .= "  INDEX (user_id),\n";
    $append .= "  INDEX (event_type),\n";
    $append .= "  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL\n";
    $append .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\");\n";
    $s .= $append;
  }
  return $s;
});

// 2) auth.php — block login for inactive users
patch_file($root . '/app/auth.php', function ($s) {
  // If the file already checks is_active, skip
  if (strpos($s, "['is_active']") !== false || strpos($s, 'is_active') !== false) {
    return $s;
  }
  // Replace: if($u && password_verify($password,$u['password_hash'])){
  // With:   if($u && (($u['is_active'] ?? 1)) && password_verify($password,$u['password_hash'])){
  $pattern = '/if\(\$u\s*&&\s*password_verify\(\$password,\s*\$u\[[\'"]password_hash[\'"]\]\)\)\s*\{/';
  $replacement = 'if($u && (($u[\'is_active\'] ?? 1)) && password_verify($password,$u[\'password_hash\'])){';
  $new = preg_replace($pattern, $replacement, $s, 1);
  return $new ? $new : $s; // if not matched, leave file as-is
});


// 3) header nav — add Audit (manager) and Users (admin)
patch_file($root . '/partials/header.php', function ($s) {
  if (strpos($s, "page=users") === false) {
    $s = str_replace(
      '<li class="nav-item"><a class="nav-link" href="index.php?page=documents">Documents</a></li>',
      '<li class="nav-item"><a class="nav-link" href="index.php?page=documents">Documents</a></li>' . "\n" .
      '        <?php if (has_role(\'manager\')): ?><li class="nav-item"><a class="nav-link" href="index.php?page=audit">Audit</a></li><?php endif; ?>' . "\n" .
      '        <?php if (has_role(\'admin\')): ?><li class="nav-item"><a class="nav-link" href="index.php?page=users">Users</a></li><?php endif; ?>',
      $s
    );
  }
  return $s;
});

// 4) index router — allow pages users & audit
patch_file($root . '/index.php', function ($s) {
  if (strpos($s, "'users','audit'") === false) {
    $s = str_replace(
      "['','dashboard','children','staff','attendance','incidents','medications','visitors','complaints','documents','reports','search','child_profile','child_timeline','export_csv','login','logout']",
      "['','dashboard','children','staff','attendance','incidents','medications','visitors','complaints','documents','reports','search','child_profile','child_timeline','export_csv','users','audit','login','logout']",
      $s
    );
  }
  return $s;
});

// 5) staff.php — optional login creation with role select (manager+)
patch_file($root . '/pages/staff.php', function ($s) {
  if (strpos($s, 'Create login') === false) {
    if (strpos($s, "require_once __DIR__ . '/../app/audit.php';") === false) {
      $s = str_replace(
        "require_once __DIR__ . '/../app/db.php';",
        "require_once __DIR__ . '/../app/db.php';\nrequire_once __DIR__ . '/../app/audit.php';",
        $s
      );
    }
    $s = str_replace(
      "if (isset(\$_POST['create'])) {",
      "if (isset(\$_POST['create'])) { if (!has_role('manager')) { http_response_code(403); die('Forbidden'); }",
      $s
    );
    $s = str_replace(
      "flash('Staff member added'); header('Location: index.php?page=staff'); exit;",
      "\$staff_id=(int)\$db->lastInsertId();\n" .
      "    if (isset(\$_POST['create_login']) && !empty(\$_POST['user_email']) && !empty(\$_POST['user_password'])) {\n" .
      "      try { \$db->prepare('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)')->execute([\$_POST['name'], \$_POST['user_email'], password_hash(\$_POST['user_password'], PASSWORD_DEFAULT), \$_POST['user_role']]); log_event('create','user',(int)\$db->lastInsertId(), ['from_staff'=>\$staff_id,'role'=>\$_POST['user_role']]); flash('Staff member added and login created'); } catch (Throwable \$e) { flash('Staff added but login not created: '.$e->getMessage(), 'danger'); }\n" .
      "    } else { flash('Staff member added'); }\n" .
      "    header('Location: index.php?page=staff'); exit;",
      $s
    );
    $s = str_replace(
      '<h2 class="h6">Add staff</h2>',
      '<h2 class="h6">Add staff</h2>' . "\n" . '      <p class="text-muted small">Optionally create a login and set access level.</p>',
      $s
    );
    $s = str_replace(
      '<div class="col-12"><label class="form-label">Qualifications / Training</label><textarea class="form-control" name="training_completed" rows="2"></textarea></div>',
      '<div class="col-12"><label class="form-label">Qualifications / Training</label><textarea class="form-control" name="training_completed" rows="2"></textarea></div>' . "\n" .
      '          <hr>' . "\n" .
      '          <div class="col-12 form-check"><input type="checkbox" class="form-check-input" id="create_login" name="create_login"><label class="form-check-label" for="create_login">Create login</label></div>' . "\n" .
      '          <div class="col-md-6"><label class="form-label">Login Email</label><input class="form-control" name="user_email" placeholder="user@site.com"></div>' . "\n" .
      '          <div class="col-md-6"><label class="form-label">Password</label><input class="form-control" name="user_password" placeholder="Temporary password"></div>' . "\n" .
      '          <div class="col-md-6"><label class="form-label">Access level</label><select class="form-select" name="user_role"><option value="staff">Staff (no delete)</option><option value="manager">Manager</option><option value="admin">Admin</option></select></div>',
      $s
    );
  }
  return $s;
});

// 6) Drop-in files: app/audit.php, pages/users.php, pages/audit.php
$audit_php =
  "<?php\n" .
  "require_once __DIR__ . '/db.php';\n" .
  "require_once __DIR__ . '/auth.php';\n" .
  "function log_event(string \$event, string \$entity, int \$entity_id, array \$meta = []): void {\n" .
  "  try {\n" .
  "    \$db = get_db();\n" .
  "    \$u = current_user();\n" .
  "    \$uid = \$u['id'] ?? null;\n" .
  "    \$stmt = \$db->prepare(\"INSERT INTO audit_log (user_id, event_type, entity, entity_id, meta) VALUES (?,?,?,?,?)\");\n" .
  "    \$stmt->execute([\$uid, \$event, \$entity, \$entity_id, json_encode(\$meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);\n" .
  "  } catch (Throwable \$e) {}\n" .
  "}";
write_file($root . '/app/audit.php', $audit_php);

$users_php =
  "<?php\n" .
  "require_once __DIR__ . '/../partials/header.php';\n" .
  "require_once __DIR__ . '/../app/db.php';\n" .
  "require_once __DIR__ . '/../app/audit.php';\n" .
  "require_role_any(['admin']);\n" .
  "\$db=get_db(); \$err='';\n" .
  "if(\$_SERVER['REQUEST_METHOD']==='POST'){ check_csrf();\n" .
  "  if(isset(\$_POST['create'])){ try{\n" .
  "    \$name=trim(\$_POST['name']); \$email=trim(\$_POST['email']); \$role=\$_POST['role']; \$password=\$_POST['password'];\n" .
  "    if(\$name===''||\$email===''||\$password==='') throw new Exception('Missing fields');\n" .
  "    \$db->prepare('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)')->execute([\$name,\$email,password_hash(\$password,PASSWORD_DEFAULT),\$role]);\n" .
  "    log_event('create','user',(int)\$db->lastInsertId(), ['email'=>\$email,'role'=>\$role]); flash('User created'); header('Location: index.php?page=users'); exit;\n" .
  "  }catch(Throwable \$e){ \$err=\$e->getMessage(); }}\n" .
  "  if(isset(\$_POST['update'])){ try{\n" .
  "    \$id=(int)\$_POST['id']; \$name=trim(\$_POST['name']); \$email=trim(\$_POST['email']); \$role=\$_POST['role']; \$active=isset(\$_POST['is_active'])?1:0;\n" .
  "    \$db->prepare('UPDATE users SET name=?,email=?,role=?,is_active=? WHERE id=?')->execute([\$name,\$email,\$role,\$active,\$id]);\n" .
  "    if(!empty(\$_POST['password'])){ \$db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash(\$_POST['password'],PASSWORD_DEFAULT),\$id]); }\n" .
  "    log_event('update','user',\$id, ['role'=>\$role,'active'=>\$active]); flash('User updated'); header('Location: index.php?page=users'); exit;\n" .
  "  }catch(Throwable \$e){ \$err=\$e->getMessage(); }}\n" .
  "  if(isset(\$_POST['delete'])){ \$id=(int)\$_POST['id']; if(\$id===(int)(current_user()['id']??-1)){ \$err='Cannot delete your own account.'; } else { \$db->prepare('DELETE FROM users WHERE id=?')->execute([\$id]); log_event('delete','user',\$id,[]); flash('User deleted'); header('Location: index.php?page=users'); exit; }}\n" .
  "}\n" .
  "\$allowedSort=['name'=>'u.name','email'=>'u.email','role'=>'u.role','active'=>'u.is_active','created'=>'u.created_at']; list(\$p,\$per,\$offset)=page_params(20); \$order=order_by_clause(\$allowedSort,'created');\n" .
  "\$total=(int)\$db->query('SELECT COUNT(*) FROM users')->fetchColumn(); \$st=\$db->prepare('SELECT u.* FROM users u'.\$order.' LIMIT ? OFFSET ?'); \$st->bindValue(1,\$per,PDO::PARAM_INT); \$st->bindValue(2,\$offset,PDO::PARAM_INT); \$st->execute(); \$rows=\$st->fetchAll();\n" .
  "\$edit=null; if(isset(\$_GET['edit'])){ \$st=\$db->prepare('SELECT * FROM users WHERE id=?'); \$st->execute([(int)\$_GET['edit']]); \$edit=\$st->fetch(); }\n" .
  "?>\n" .
  "<div class=\"d-flex justify-content-between align-items-center mb-3\"><h1 class=\"h4\">Users</h1></div>\n" .
  "<?php if(\$err): ?><div class=\"alert alert-danger\"><?= htmlspecialchars(\$err) ?></div><?php endif; ?>\n" .
  "<div class=\"row g-3\">\n" .
  "  <div class=\"col-md-7\">\n" .
  "    <div class=\"card shadow-sm\"><div class=\"card-body table-responsive\">\n" .
  "      <table class=\"table table-sm align-middle\">\n" .
  "        <thead><tr><th><?= sort_link('name','Name') ?></th><th><?= sort_link('email','Email') ?></th><th><?= sort_link('role','Role') ?></th><th><?= sort_link('active','Active') ?></th><th><?= sort_link('created','Created') ?></th><th></th></tr></thead>\n" .
  "        <tbody>\n" .
  "          <?php foreach(\$rows as \$r): ?>\n" .
  "            <tr>\n" .
  "              <td><?= htmlspecialchars(\$r['name']) ?></td>\n" .
  "              <td><?= htmlspecialchars(\$r['email']) ?></td>\n" .
  "              <td><span class=\"badge bg-<?= \$r['role']==='admin'?'danger':(\$r['role']==='manager'?'primary':'secondary') ?>\"><?= htmlspecialchars(\$r['role']) ?></span></td>\n" .
  "              <td><?= \$r['is_active']?'Yes':'No' ?></td>\n" .
  "              <td><?= htmlspecialchars(\$r['created_at']) ?></td>\n" .
  "              <td class=\"text-end\">\n" .
  "                <a class=\"btn btn-sm btn-outline-secondary\" href=\"index.php?page=users&edit=<?= (int)\$r['id'] ?>\">Edit</a>\n" .
  "                <?php if ((int)\$r['id'] !== (int)(current_user()['id'] ?? -1)): ?>\n" .
  "                <form method=\"post\" class=\"d-inline\" onsubmit=\"return confirm('Delete user?');\"><?php csrf_input(); ?><input type=\"hidden\" name=\"id\" value=\"<?= (int)\$r['id'] ?>\"><button class=\"btn btn-sm btn-outline-danger\" name=\"delete\" value=\"1\">Delete</button></form>\n" .
  "                <?php endif; ?>\n" .
  "              </td>\n" .
  "            </tr>\n" .
  "          <?php endforeach; ?>\n" .
  "        </tbody>\n" .
  "      </table>\n" .
  "      <div class=\"d-flex justify-content-between align-items-center\"><div class=\"small text-muted\">Total: <?= \$total ?></div><?php render_pagination(\$total,\$per,\$p); ?></div>\n" .
  "    </div></div>\n" .
  "  </div>\n" .
  "  <div class=\"col-md-5\">\n" .
  "    <div class=\"card shadow-sm\"><div class=\"card-body\">\n" .
  "      <h2 class=\"h6\"><?= \$edit?'Edit user':'Add user' ?></h2>\n" .
  "      <form method=\"post\"><?php csrf_input(); if(\$edit): ?><input type=\"hidden\" name=\"id\" value=\"<?= (int)\$edit['id'] ?>\"><?php endif; ?>\n" .
  "        <div class=\"row g-2\">\n" .
  "          <div class=\"col-md-6\"><label class=\"form-label\">Name</label><input class=\"form-control\" name=\"name\" value=\"<?= htmlspecialchars(\$edit['name'] ?? '') ?>\" required></div>\n" .
  "          <div class=\"col-md-6\"><label class=\"form-label\">Email</label><input class=\"form-control\" name=\"email\" value=\"<?= htmlspecialchars(\$edit['email'] ?? '') ?>\" required></div>\n" .
  "          <div class=\"col-md-6\"><label class=\"form-label\">Role</label><select class=\"form-select\" name=\"role\"><?php foreach(['staff','manager','admin'] as \$r): ?><option value=\"<?= \$r ?>\" <?= (\$edit['role'] ?? '')===$r?'selected':'' ?>><?= ucfirst(\$r) ?></option><?php endforeach; ?></select></div>\n" .
  "          <div class=\"col-md-6\"><label class=\"form-label\"><?= \$edit?'New ':'' ?>Password</label><input class=\"form-control\" name=\"password\" <?= \$edit?'':'required' ?>></div>\n" .
  "          <?php if(\$edit): ?><div class=\"col-md-6 form-check mt-4\"><input type=\"checkbox\" class=\"form-check-input\" id=\"is_active\" name=\"is_active\" <?= (\$edit['is_active'] ?? 1)?'checked':'' ?>><label for=\"is_active\" class=\"form-check-label\">Active</label></div><?php endif; ?>\n" .
  "        </div>\n" .
  "        <div class=\"mt-3\"><?php if(\$edit): ?><button class=\"btn btn-primary\" name=\"update\" value=\"1\">Save</button> <a class=\"btn btn-secondary\" href=\"index.php?page=users\">Cancel</a><?php else: ?><button class=\"btn btn-primary\" name=\"create\" value=\"1\">Add user</button><?php endif; ?></div>\n" .
  "      </form>\n" .
  "    </div></div>\n" .
  "  </div>\n" .
  "</div>\n" .
  "<?php require_once __DIR__ . '/../partials/footer.php'; ?>";
write_file($root . '/pages/users.php', $users_php);

$audit_page =
  "<?php\n" .
  "require_once __DIR__ . '/../partials/header.php';\n" .
  "require_once __DIR__ . '/../app/db.php';\n" .
  "require_role_any(['manager']);\n" .
  "\$db=get_db(); \$entity=\$_GET['entity']??''; \$user=isset(\$_GET['user'])?(int)\$_GET['user']:0;\n" .
  "\$where=[]; \$params=[]; if(\$entity!==''){ \$where[]='a.entity=?'; \$params[]=\$entity; } if(\$user){ \$where[]='a.user_id=?'; \$params[]=\$user; }\n" .
  "\$whereSql = \$where ? (' WHERE '.implode(' AND ',\$where)) : '';\n" .
  "\$allowedSort=['time'=>'a.created_at','user'=>'u.name','entity'=>'a.entity','event'=>'a.event_type']; list(\$p,\$per,\$offset)=page_params(20); \$order=order_by_clause(\$allowedSort,'time');\n" .
  "\$totalSt=\$db->prepare('SELECT COUNT(*) FROM audit_log a LEFT JOIN users u ON u.id=a.user_id'.\$whereSql); \$totalSt->execute(\$params); \$total=(int)\$totalSt->fetchColumn();\n" .
  "\$sql='SELECT a.*, u.name as user_name FROM audit_log a LEFT JOIN users u ON u.id=a.user_id'.\$whereSql.\$order.' LIMIT ? OFFSET ?';\n" .
  "\$st=\$db->prepare(\$sql); \$i=1; foreach(\$params as \$pv){ \$st->bindValue(\$i++, \$pv); } \$st->bindValue(\$i++, \$per, PDO::PARAM_INT); \$st->bindValue(\$i++, \$offset, PDO::PARAM_INT); \$st->execute(); \$rows=\$st->fetchAll();\n" .
  "\$users=\$db->query('SELECT id, name FROM users ORDER BY name')->fetchAll();\n" .
  "?>\n" .
  "<h1 class=\"h4 mb-3\">Audit Log</h1>\n" .
  "<form class=\"row g-2 mb-3\">\n" .
  "  <input type=\"hidden\" name=\"page\" value=\"audit\">\n" .
  "  <div class=\"col-md-3\"><label class=\"form-label\">Entity</label><input class=\"form-control\" name=\"entity\" value=\"<?= htmlspecialchars(\$entity) ?>\" placeholder=\"children, incidents, user...\"></div>\n" .
  "  <div class=\"col-md-3\"><label class=\"form-label\">User</label><select class=\"form-select\" name=\"user\"><option value=\"0\">All</option><?php foreach(\$users as \$u): ?><option value=\"<?= (int)\$u['id'] ?>\" <?= \$user===(int)\$u['id']?'selected':'' ?>><?= htmlspecialchars(\$u['name']) ?></option><?php endforeach; ?></select></div>\n" .
  "  <div class=\"col-md-2\"><label class=\"form-label d-block\">&nbsp;</label><button class=\"btn btn-secondary\">Filter</button></div>\n" .
  "</form>\n" .
  "<div class=\"card shadow-sm\"><div class=\"card-body table-responsive\">\n" .
  "  <table class=\"table table-sm align-middle\">\n" .
  "    <thead><tr><th><?= sort_link('time','When') ?></th><th><?= sort_link('user','User') ?></th><th><?= sort_link('entity','Entity') ?></th><th><?= sort_link('event','Event') ?></th><th>Meta</th></tr></thead>\n" .
  "    <tbody>\n" .
  "      <?php foreach(\$rows as \$r): \$meta=\$r['meta'] ? json_decode(\$r['meta'], true) : []; ?>\n" .
  "        <tr>\n" .
  "          <td><?= htmlspecialchars(\$r['created_at']) ?></td>\n" .
  "          <td><?= htmlspecialchars(\$r['user_name'] ?? '—') ?></td>\n" .
  "          <td><?= htmlspecialchars(\$r['entity']) ?> #<?= (int)\$r['entity_id'] ?></td>\n" .
  "          <td><span class=\"badge bg-dark\"><?= htmlspecialchars(\$r['event_type']) ?></span></td>\n" .
  "          <td class=\"small text-pre-wrap\"><?= htmlspecialchars(json_encode(\$meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></td>\n" .
  "        </tr>\n" .
  "      <?php endforeach; ?>\n" .
  "    </tbody>\n" .
  "  </table>\n" .
  "  <div class=\"d-flex justify-content-between align-items-center\"><div class=\"small text-muted\">Total: <?= \$total ?></div><?php render_pagination(\$total,\$per,\$p); ?></div>\n" .
  "</div></div>\n" .
  "<?php require_once __DIR__ . '/../partials/footer.php'; ?>";
write_file($root . '/pages/audit.php', $audit_page);

echo "<hr><strong>Patch complete.</strong> Visit any page to trigger the DB migration (adds audit_log & user flags).";

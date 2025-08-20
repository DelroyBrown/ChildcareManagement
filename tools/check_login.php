<?php
// tools/check_login.php (delete after use)
require_once __DIR__ . '/../app/db.php';
$email = $_POST['email'] ?? '';
$pass  = $_POST['password'] ?? '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $db = get_db();
  $st = $db->prepare("SELECT email, password_hash, role, is_active FROM users WHERE email=? LIMIT 1");
  $st->execute([trim($email)]);
  $u = $st->fetch();
  if(!$u){ echo "No user for that email"; exit; }
  echo "Found user: {$u['email']} (role={$u['role']}, active=".(($u['is_active']??1)?'1':'0').")<br>";
  echo password_verify($pass, $u['password_hash']) ? "✅ Password matches" : "❌ Password does NOT match";
  exit;
}
?>
<form method="post" style="max-width:420px;margin:2rem;font-family:sans-serif">
  <h3>Test a login</h3>
  <input name="email" placeholder="email" style="width:100%;margin:.25rem 0" />
  <input name="password" placeholder="password" style="width:100%;margin:.25rem 0" />
  <button>Test</button>
</form>

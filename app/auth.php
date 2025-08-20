<?php
// app/auth.php
require_once __DIR__ . '/db.php';

function current_user(){ return $_SESSION['user'] ?? null; }
function require_login(){ if(!current_user()){ header('Location: index.php?page=login'); exit; } }
function has_role(string $role): bool {
  $u = current_user(); if(!$u) return false; if($u['role']==='admin') return true; return $u['role']===$role;
}
function require_role_any(array $roles){ $u=current_user(); if(!$u){ http_response_code(403); die('Forbidden'); } if($u['role']==='admin') return; if(!in_array($u['role'],$roles,true)){ http_response_code(403); die('Forbidden'); } }

function login(string $email,string $password): bool {
  $email = trim($email);
  $db=get_db(); $st=$db->prepare('SELECT * FROM users WHERE email=? LIMIT 1'); $st->execute([$email]); $u=$st->fetch();
  if($u && (($u['is_active'] ?? 1)) && password_verify($password,$u['password_hash'])){ $_SESSION['user']=['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']]; return true; }
  return false;
}
function logout(){ $_SESSION=[]; session_destroy(); }

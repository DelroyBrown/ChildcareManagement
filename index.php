<?php
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/migrate_mysql.php';

$page = $_GET['page'] ?? 'dashboard';
$public = ['login'];
$allowed = ['','dashboard','children','staff','attendance','incidents','medications','visitors','complaints','documents','reports','search','child_profile','child_timeline','export_csv','users','audit','login','logout'];
if ($page==='') $page='dashboard';
if (!in_array($page, $allowed, true)) $page='dashboard';

if (!in_array($page, $public, true)) require_login();

if ($page==='login') { require __DIR__ . '/pages/login.php'; exit; }
if ($page==='logout') { logout(); header('Location: index.php?page=login'); exit; }

require __DIR__ . '/pages/'.$page.'.php';

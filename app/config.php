<?php
// app/config.php
declare(strict_types=1);
session_start();
date_default_timezone_set('Europe/London');

define('APP_NAME', 'Child Care Home Manager');

// ---- DB CONFIG ----
// Adjust if your MySQL differs (phpMyAdmin/XAMPP defaults shown)
define('DB_HOST', getenv('APP_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('APP_DB_NAME') ?: 'childcare_home');
define('DB_USER', getenv('APP_DB_USER') ?: 'root');
define('DB_PASS', getenv('APP_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
function csrf_input() {
  $t = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES);
  echo '<input type="hidden" name="csrf_token" value="'.$t.'">';
}
function check_csrf() {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(400); die('Invalid CSRF token.');
  }
}

// Helpers
function format_human_date(?string $date): string {
  if (!$date) return '';
  try { $d = new DateTime($date); return $d->format('F jS Y'); } catch (Throwable $e) { return (string)$date; }
}
function format_human_datetime(?string $dt): string {
  if (!$dt) return '';
  try { $d = new DateTime($dt); return $d->format('F jS Y H:i'); } catch (Throwable $e) { return (string)$dt; }
}
function flash(string $message, string $type='success'): void { $_SESSION['flash'][] = ['msg'=>$message,'type'=>$type]; }
function consume_flash(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }


// -------- Sorting & Pagination helpers --------
function build_url(array $overrides=[]): string {
  $q = array_merge($_GET, $overrides);
  return 'index.php?' . http_build_query($q);
}
function sort_link(string $key, string $label): string {
  $cur = $_GET['sort'] ?? '';
  $sd = strtolower($_GET['sd'] ?? ($_GET['dir'] ?? 'asc')); // compat: accept ?dir= too
  $next = ($cur === $key && $sd === 'asc') ? 'desc' : 'asc';
  $icon = ($cur === $key) ? ($sd === 'asc' ? ' &uarr;' : ' &darr;') : '';
  $url = build_url(['sort'=>$key, 'sd'=>$next, 'p'=>1]);
  return '<a class="text-decoration-none" href="'.$url.'">'.$label.$icon.'</a>';
}
function order_by_clause(array $allowed, string $defaultKey): string {
  $sort = $_GET['sort'] ?? $defaultKey;
  $sd = strtolower($_GET['sd'] ?? ($_GET['dir'] ?? 'asc')); // compat: accept ?dir= too
  $dirSql = $sd === 'desc' ? 'DESC' : 'ASC';
  $expr = $allowed[$sort] ?? $allowed[$defaultKey] ?? $defaultKey;
  return ' ORDER BY ' . $expr . ' ' . $dirSql . ' ';
}
function page_params(int $per=20): array {
  $p = max(1, (int)($_GET['p'] ?? 1));
  $offset = ($p - 1) * $per;
  return [$p, $per, $offset];
}
function render_pagination(int $total, int $per, int $p): void {
  $pages = max(1, (int)ceil($total / $per));
  if ($pages <= 1) return;
  echo '<nav><ul class=\"pagination pagination-sm\">';
  for ($i=1; $i<=$pages; $i++) {
    $active = $i === $p ? ' active' : '';
    $url = build_url(['p'=>$i]);
    echo '<li class=\"page-item'.$active.'\"><a class=\"page-link\" href=\"'.$url.'\">'.$i.'</a></li>';
  }
  echo '</ul></nav>';
}

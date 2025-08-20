<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_login();
$db = get_db();

$type = $_GET['type'] ?? '';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=export_' . $type . '_' . date('Ymd_His') . '.csv');
$out = fopen('php://output', 'w');

if ($type === 'timeline') {
  $child_id = (int)($_GET['child_id'] ?? 0);
  $st = $db->prepare('SELECT first_name, last_name FROM children WHERE id=?'); $st->execute([$child_id]); $child = $st->fetch();
  if (!$child) { fputcsv($out, ['Invalid child']); exit; }
  fputcsv($out, ['Timeline for', $child['first_name'].' '.$child['last_name']]);
  fputcsv($out, ['When','Type','Title','Details']);
  $rows = [];

  $st=$db->prepare('SELECT date as dt, status as lab, notes FROM attendance WHERE child_id=?'); $st->execute([$child_id]);
  foreach ($st as $r) $rows[] = [$r['dt'], 'Attendance', ucfirst($r['lab']), $r['notes']];
  $st=$db->prepare('SELECT date_time as dt, type, severity, description, action_taken FROM incidents WHERE child_id=?'); $st->execute([$child_id]);
  foreach ($st as $r) $rows[] = [$r['dt'], 'Incident', $r['type'].($r['severity']?' ('.$r['severity'].')':''), trim(($r['description']??'')." | Action: ".($r['action_taken']??''))];
  $st=$db->prepare('SELECT administered_at as dt, med_name, dose, administered_by, notes FROM medications WHERE child_id=?'); $st->execute([$child_id]);
  foreach ($st as $r) $rows[] = [$r['dt'], 'Medication', $r['med_name'].($r['dose']?' ('.$r['dose'].')':''), 'By: '.($r['administered_by']??'').($r['notes']?" | ".$r['notes']:'')];
  $st=$db->prepare('SELECT visit_date as dt, visitor_name, relationship, id_checked, notes FROM visitors WHERE child_id=?'); $st->execute([$child_id]);
  foreach ($st as $r) $rows[] = [$r['dt'], 'Visit', $r['visitor_name'].($r['relationship']?' ('.$r['relationship'].')':''), ($r['id_checked']?'ID checked':'ID not checked').($r['notes']?" | ".$r['notes']:'')];
  $st=$db->prepare('SELECT date_received as dt, complainant_name, outcome, details FROM complaints WHERE child_id=?'); $st->execute([$child_id]);
  foreach ($st as $r) $rows[] = [$r['dt'], 'Complaint', 'Complaint'.($r['complainant_name']?' by '.$r['complainant_name']:''), ($r['outcome']?'Outcome: '.$r['outcome'].' | ':'').($r['details']??'')];
  $st=$db->prepare('SELECT uploaded_at as dt, title, category, file_path FROM documents WHERE child_id=?'); $st->execute([$child_id]);
  foreach ($st as $r) $rows[] = [$r['dt'], 'Document', $r['title'].($r['category']?' ('.$r['category'].')':''), $r['file_path']];

  usort($rows, fn($a,$b)=>strcmp($b[0], $a[0]));
  foreach ($rows as $row) fputcsv($out, $row);
  exit;
}
fputcsv($out, ['Unsupported export type']);

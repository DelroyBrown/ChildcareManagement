<?php
// app/audit.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function log_event(string $event, string $entity, int $entity_id, array $meta = []): void {
  try {
    $db = get_db();
    $u = current_user();
    $uid = $u['id'] ?? null;
    $stmt = $db->prepare("INSERT INTO audit_log (user_id, event_type, entity, entity_id, meta) VALUES (?,?,?,?,?)");
    $stmt->execute([$uid, $event, $entity, $entity_id, json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
  } catch (Throwable $e) {
    // logging should never break app
  }
}
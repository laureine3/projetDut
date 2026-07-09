<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/auth.php";

requireRole('admin');

header('Content-Type: application/json');

$stmt = $pdo->query("
SELECT action_type, description, logged_at
FROM audit_logs
ORDER BY logged_at DESC
LIMIT 5
");

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
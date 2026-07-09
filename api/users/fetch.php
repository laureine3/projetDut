<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/auth.php";

requireRole('admin');

header('Content-Type: application/json');

$stmt = $pdo->query("
SELECT id, name, email, role, is_active
FROM users
ORDER BY created_at DESC
");

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
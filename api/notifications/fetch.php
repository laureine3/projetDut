<?php
session_start();
require_once "../../config/database.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT id, title, message, type, created_at
    FROM notifications
    WHERE (user_id = :user_id OR user_id IS NULL)
    AND is_read = 0
    ORDER BY created_at DESC
");

$stmt->execute([
    'user_id' => $userId
]);

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($notifications);
<?php
session_start();
require_once "../../config/database.php";

if (!isset($_SESSION['user_id'])) exit;

$stmt = $pdo->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
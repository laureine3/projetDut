<?php
session_start();
require_once '../../config/database.php';

$id = $_POST['id'];
$decision = $_POST['decision'];
$comment = $_POST['comment'];

$status = $decision === 'validated' ? 'validated' : 'rejected';

$pdo->prepare("UPDATE documents SET status=?, processed_at=NOW() WHERE id=?")
    ->execute([$status, $id]);

$pdo->prepare("INSERT INTO audit_logs (operator_id, action_type, target_type, target_id, description)
VALUES (?, 'AGENT_DECISION', 'document', ?, ?)")
->execute([$_SESSION['user_id'], $id, $comment]);

header("Location: documents.php");
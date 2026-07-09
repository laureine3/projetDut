<?php
require_once __DIR__ . '/database.php';

function logAudit($operatorId, $actionType, $targetId, $description)
{
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (
            operator_id,
            action_type,
            target_id,
            description,
            logged_at
        ) VALUES (?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $operatorId,
        $actionType,
        $targetId,
        $description
    ]);
}
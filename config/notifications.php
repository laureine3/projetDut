<?php

function createNotification($pdo, $user_id, $title, $message, $type = "info")
{
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $title, $message, $type]);
}

function createGlobalNotification($pdo, $title, $message, $type = "info")
{
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type)
        VALUES (NULL, ?, ?, ?)
    ");
    $stmt->execute([$title, $message, $type]);
}

function getAdminCount($pdo)
{
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM users 
        WHERE role='admin' AND is_active=1
    ");
    return $stmt->fetchColumn();
}
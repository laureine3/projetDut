<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/auth.php";

requireLogin();

if (!isset($_FILES['profile_image'])) {
    echo json_encode(["status"=>"error"]);
    exit;
}

$file = $_FILES['profile_image'];

$allowed = ['image/jpeg','image/png','image/webp'];

if (!in_array($file['type'], $allowed)) {
    echo json_encode(["status"=>"error","message"=>"Format non autorisé"]);
    exit;
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$newName = "user_" . $_SESSION['user_id'] . "_" . time() . "." . $ext;

$destination = "../../uploads/profiles/" . $newName;

move_uploaded_file($file['tmp_name'], $destination);

$stmt = $pdo->prepare("UPDATE users SET profile_image=? WHERE id=?");
$stmt->execute([$newName, $_SESSION['user_id']]);

echo json_encode(["status"=>"success"]);
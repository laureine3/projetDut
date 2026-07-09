<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/auth.php";

requireRole('admin');

header('Content-Type: application/json');

$data = [];

/* UTILISATEURS */
$data['totalUsers'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$data['totalAdmins'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
$data['totalAgents'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role='agent' AND is_active=1")->fetchColumn();

/* DOCUMENTS */
$data['totalDocuments'] = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
$data['pendingDocuments'] = $pdo->query("SELECT COUNT(*) FROM documents WHERE status='pending'")->fetchColumn();
$data['validatedDocuments'] = $pdo->query("SELECT COUNT(*) FROM documents WHERE status='validated'")->fetchColumn();
$data['rejectedDocuments'] = $pdo->query("SELECT COUNT(*) FROM documents WHERE status='rejected'")->fetchColumn();

echo json_encode($data);
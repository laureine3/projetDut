<?php
session_start();

require_once "../../config/database.php";
require_once "../../config/security.php";

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
exit;
}

$page = max(1,intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page-1)*$limit;

$where = [];
$params = [];

if(!empty($_GET['operator'])){
$where[]="a.operator_id=?";
$params[]=$_GET['operator'];
}

if(!empty($_GET['action'])){
$where[]="a.action_type=?";
$params[]=$_GET['action'];
}

if(!empty($_GET['start'])){
$where[]="DATE(a.logged_at)>=?";
$params[]=$_GET['start'];
}

if(!empty($_GET['end'])){
$where[]="DATE(a.logged_at)<=?";
$params[]=$_GET['end'];
}

$whereSQL = count($where) ? "WHERE ".implode(" AND ",$where) : "";

$count = $pdo->prepare("
SELECT COUNT(*)
FROM audit_logs a
$whereSQL
");

$count->execute($params);
$total = $count->fetchColumn();

$pages = ceil($total/$limit);

$query = $pdo->prepare("
SELECT a.*,u.name operator
FROM audit_logs a
LEFT JOIN users u ON a.operator_id=u.id
$whereSQL
ORDER BY a.logged_at DESC
LIMIT $limit OFFSET $offset
");

$query->execute($params);

$logs = $query->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
"logs"=>$logs,
"pages"=>$pages
]);
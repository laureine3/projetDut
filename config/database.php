<?php
header('Content-type: text/html; charset=utf-8');

$host = "localhost";
$dbname = "gestidoc_bd";
$username = "root";
$password = ""; // laisse vide si tu n’as pas mis de mot de passe MySQL

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Erreur connexion BD : " . $e->getMessage());
}
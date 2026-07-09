<?php
// session_start();
// require_once __DIR__ . '/../../config/database.php';

// // 🔐 Sécurité
// if (!isset($_SESSION["user_id"])) {
//     header("Location: ../../auth/login.php");
//     exit;
// }

// if ($_SESSION["user_role"] !== "agent" && $_SESSION["user_role"] !== "admin") {
//     echo "Accès refusé.";
//     exit;
// }

// if (!isset($_GET["id"])) {
//     echo "Document introuvable.";
//     exit;
// }

// $document_id = intval($_GET["id"]);

// // 🔎 Requête complète avec OCR + catégorie + service
// $stmt = $pdo->prepare("
//     SELECT 
//         d.*,
//         u.name AS user_name,
//         dc.name AS category_name,
//         s.name AS service_name,
//         o.extracted_text AS ocr_text
//     FROM documents d
//     JOIN users u ON d.user_id = u.id
//     LEFT JOIN document_categories dc ON d.detected_category_id = dc.id
//     LEFT JOIN services s ON d.detected_service_id = s.id
//     LEFT JOIN ocr_results o ON d.id = o.document_id
//     WHERE d.id = ?
// ");
// $stmt->execute([$document_id]);
// $document = $stmt->fetch(PDO::FETCH_ASSOC);

// if (!$document) {
//     echo "Document non trouvé.";
//     exit;
// }

// // 🔁 Traitement validation / rejet
// if ($_SERVER["REQUEST_METHOD"] === "POST") {

//     $action = $_POST["action"];
//     $currentPath = __DIR__ . '/../../' . $document["file_path"];

//     if ($action === "validate") {
//         $newStatus = "validated";
//         $newFolder = "uploads/validated/";
//     } else {
//         $newStatus = "rejected";
//         $newFolder = "uploads/rejected/";
//     }

//     if (!is_dir(__DIR__ . '/../../' . $newFolder)) {
//         mkdir(__DIR__ . '/../../' . $newFolder, 0777, true);
//     }

//     $newPath = __DIR__ . '/../../' . $newFolder . $document["file_name"];

//     if (rename($currentPath, $newPath)) {

//         $update = $pdo->prepare("
//             UPDATE documents 
//             SET status = ?, 
//                 file_path = ?, 
//                 validated_by = ?, 
//                 validated_at = NOW()
//             WHERE id = ?
//         ");

//         $update->execute([
//             $newStatus,
//             $newFolder . $document["file_name"],
//             $_SESSION["user_id"],
//             $document_id
//         ]);

//         header("Location: dashboard.php");
//         exit;

//     } else {
//         echo "Erreur lors du déplacement du fichier.";
//     }
// }
?>

<!-- <!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Vérification Document</title>
<style>
body { font-family: Arial; padding: 20px; background: #f9f9f9; }
.container { display: flex; gap: 30px; }
.left, .right { background: white; padding: 20px; border-radius: 5px; }
.left { width: 40%; }
.right { width: 60%; }
.btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; }
.validate { background: #27ae60; color: white; }
.reject { background: #c0392b; color: white; }
textarea { width: 100%; height: 250px; }
a { text-decoration: none; }
</style>
</head>
<body>

<h2>📄 Vérification Document</h2> -->

<!-- <p><strong>Utilisateur :</strong> <?= htmlspecialchars($document["user_name"]); ?></p>
<p><strong>Fichier :</strong> <?= htmlspecialchars($document["file_name"]); ?></p>
<p><strong>Catégorie détectée :</strong> <?= htmlspecialchars($document["category_name"] ?? "Non détecté"); ?></p>
<p><strong>Service détecté :</strong> <?= htmlspecialchars($document["service_name"] ?? "Non détecté"); ?></p>
<p><strong>Date upload :</strong> <?= htmlspecialchars($document["uploaded_at"]); ?></p>

<hr>

<div class="container">

<div class="left">
    <h3>Aperçu</h3> -->
    <?php
    // $fileUrl = "../../" . $document["file_path"];
    // $ext = strtolower(pathinfo($document["file_name"], PATHINFO_EXTENSION));

    // if (in_array($ext, ["jpg", "jpeg", "png"])) {
    //     echo "<img src='$fileUrl' style='max-width:100%;'>";
    // } elseif ($ext === "pdf") {
    //     echo "<iframe src='$fileUrl' width='100%' height='400px'></iframe>";
    // } else {
    //     echo "Aperçu non disponible.";
    // }
    ?>
<!-- </div>

<div class="right">
    <h3>Texte OCR</h3>
    <textarea readonly><?= htmlspecialchars($document["ocr_text"] ?? "Aucun texte détecté."); ?></textarea>

    <form method="POST" style="margin-top:20px;">
        <button class="btn validate" name="action" value="validate">Valider</button>
        <button class="btn reject" name="action" value="reject">Rejeter</button>
    </form>
</div>

</div>

<br>!
<a href="dashboard.php">⬅ Retour au dashboard</a>

</body>
</html> -->
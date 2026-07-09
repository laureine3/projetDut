<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "user") {
    header("Location: ../../auth/login.php");
    exit;
}

$message = "";

/* ================================
   Fonction Audit Générique
================================ */
function addAuditLog($pdo, $operator_id, $operator_role, $action, $target_id, $target_name, $description)
{

    $stmt = $pdo->prepare("
        INSERT INTO audit_logs
        (operator_id, operator_role, action_type, target_type, target_id, target_name, description, logged_at)
        VALUES (?, ?, ?, 'document', ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $operator_id,
        $operator_role,
        $action,
        $target_id,
        $target_name,
        $description
    ]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!isset($_FILES["document"]) || $_FILES["document"]["error"] !== 0) {
        $message = "Erreur lors de l'upload.";
    } else {

        $fileName = basename($_FILES["document"]["name"]);
        $fileTmp = $_FILES["document"]["tmp_name"];
        $fileSize = $_FILES["document"]["size"];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];

        if (!in_array($fileType, $allowed)) {
            $message = "Format non autorisé.";
        } else {

            $uploadDir = __DIR__ . "/../../uploads/new/";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $uniqueName = time() . "_" . $fileName;
            $newFilePath = $uploadDir . $uniqueName;

            if (move_uploaded_file($fileTmp, $newFilePath)) {

                $stmt = $pdo->prepare("
                    INSERT INTO documents 
                    (user_id, file_name, file_path, file_type, file_size, status, uploaded_at) 
                    VALUES (?, ?, ?, ?, ?, 'new', NOW())
                ");

                $stmt->execute([
                    $_SESSION["user_id"],
                    $uniqueName,
                    "uploads/new/" . $uniqueName,
                    $fileType,
                    $fileSize
                ]);

                $documentId = $pdo->lastInsertId();

                /* ===== AUDIT LOG ===== */
              /*  $audit = $pdo->prepare("
               "     INSERT INTO audit_logs
                    (operator_id, operator_role, action_type, target_type, target_id, description)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $audit->execute([
                    $_SESSION["user_id"],
                    $_SESSION["role"],
                    'UPLOAD',
                    'DOCUMENT',
                    $documentId,
                    'Document uploadé et envoyé en traitement OCR'
                ]);*/

                /* ===== AUDIT LOG ===== */
                addAuditLog(
                    $pdo,
                    $_SESSION["user_id"],
                    $_SESSION["role"],
                    'UPLOAD',
                    $documentId,
                    $uniqueName,
                    'Document uploadé et envoyé en traitement OCR'
                );

                /* ================================
                   Redirection vers OCR
                ================================ */
               header("Location: ../../api/process-ocr.php?id=" . $documentId);
                exit;
            } else {
                $message = "Impossible de déplacer le fichier.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="upload.css">
    <title>Upload Document - GestiDoc</title>
    <style>
        
    </style>
   
</head>

<body>

    <main>
        <div class="container">

            <div class="top-bar">
                <div>👋<strong>Bonjour <?= htmlspecialchars($_SESSION["name"]); ?></strong></div>
                <a href="../../auth/logout.php" class="logout">Déconnexion</a>
            </div>

            <div class="title-box">

    <div class="title-icon">📂</div>

    <h2>Uploader un document</h2>

    <p class="subtitle">
        Déposez votre document administratif pour lancer
        automatiquement l'analyse OCR.
    </p>

</div>

            <?php if ($message): ?>
                <div class="message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form id="uploadForm" method="POST" enctype="multipart/form-data">
                <p id="upload-status"></p>
                <label class="drop-zone">

    <input
        type="file"
        name="document"
        id="documentInput"
        required
    >

    <div class="drop-content">

        <div class="upload-icon">📄</div>

        <h3>Glissez votre document ici</h3>

        <p>ou cliquez pour sélectionner un fichier</p>

        <span>PDF • JPG • PNG</span>

    </div>

</label>
               <button class="upload-btn" type="submit">
                  ⬆ Uploader le document
              </button>
              <div class="info-box">

   
</div>
            </form>

        </div>
    </main>
    <?php if (isset($_GET['status'])): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {

                const status = document.getElementById("upload-status");

                status.innerText = "Traitement terminé ✔";

                setTimeout(function() {
                    window.location.href = window.location.pathname;
                }, 200);

            });
        </script>
    <?php endif; ?>

    <script>
        document.getElementById("documentInput").addEventListener("change", function() {

    const preview = document.getElementById("preview");

    preview.innerHTML = "";

    const file = this.files[0];

    if (!file) return;


    let content = "";


    if (file.type.startsWith("image/")) {

        content = `
            <div class="file-card">

                <div class="file-left">

                    <img src="${URL.createObjectURL(file)}">

                    <div>
                        <h4>${file.name}</h4>

                        <p>
                            ${(file.size / 1024).toFixed(1)} Ko
                        </p>
                    </div>

                </div>

            </div>
        `;

    } 
    
    else if (file.type === "application/pdf") {


        content = `

            <div class="file-card">

                <div class="file-left">

                    <div class="pdf-icon">
                        📄
                    </div>


                    <div>

                        <h4>${file.name}</h4>

                        <p>
                            ${(file.size / 1024).toFixed(1)} Ko
                        </p>

                    </div>


                </div>


            </div>

        `;

    }
    preview.innerHTML = content;

});

        document.getElementById("uploadForm").addEventListener("submit", function() {

            const status = document.getElementById("upload-status");
            status.innerText = "Traitement en cours...";

            this.querySelector("button").disabled = true;

        });
    </script>

</body>

</html>
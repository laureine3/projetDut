<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID manquant");
}

$documentId = intval($id);

/* =========================
   1️⃣ Récupération document
========================= */

$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$documentId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    die("Document introuvable");
}

$originalPath = realpath(__DIR__ . '/../' . $document['file_path']);
if (!$originalPath || !file_exists($originalPath)) {
    die("Fichier introuvable");
}

/* =========================
   2️⃣ Charger catégories
========================= */

$categories = $pdo->query("
    SELECT id, keywords, default_service_id
    FROM document_categories
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   3️⃣ Charger cachets
========================= */

$stamps = $pdo->query("
    SELECT service_id, file_name
    FROM stamps
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   4️⃣ JSON temporaires
========================= */

$categoriesFile = __DIR__ . '/categories_tmp.json';
$stampsFile = __DIR__ . '/stamps_tmp.json';

file_put_contents($categoriesFile, json_encode($categories, JSON_UNESCAPED_UNICODE));
file_put_contents($stampsFile, json_encode($stamps, JSON_UNESCAPED_UNICODE));

/* =========================
   5️⃣ Appel Python
========================= */

$pythonScript = realpath(__DIR__ . '/../python/ocr_module.py');

//$command = 'python "' . $pythonScript . '" '
   //. '"' . $originalPath . '" '
   //. '"' . $categoriesFile . '" '
   //. '"' . $stampsFile . '"';
$pythonExe = realpath(__DIR__ . '/../venv/Scripts/python.exe');

 $command = '"' . $pythonExe . '" "' . $pythonScript . '" '
         . '"' . $originalPath . '" '
         . '"' . $categoriesFile . '" '
         . '"' . $stampsFile . '"';

  //echo "<pre>$command</pre>";
//die();       

$output = shell_exec($command . " 2>&1");

if (!$output) {
    die("Erreur exécution Python");
}

/*  Nettoyage sortie */
$output = trim($output);
$firstBrace = strpos($output, '{');
if ($firstBrace !== false) {
    $output = substr($output, $firstBrace);
}

/* 🔥 Correction encodage */
$output = mb_convert_encoding($output, 'UTF-8', 'UTF-8');

/* 🔥 Decode */
$result = json_decode($output, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "<pre>";
    echo "===== ERREUR JSON =====\n";
    echo json_last_error_msg();
    echo "\n\nContenu reçu:\n";
    echo $output;
    echo "\n===== FIN =====";
    echo "</pre>";
    exit;
}

/* =========================
   6️⃣ Transaction
========================= */

$pdo->beginTransaction();

try {

    $status = 'pending';
    $is_incoherent = !empty($result['is_incoherent']) ? 1 : 0;

    if ($result['status'] === 'error') {

        $status = 'error';
        $is_incoherent = 1;

        $pdo->prepare("
            UPDATE documents SET
                status = ?,
                is_incoherent = ?,
                processed_at = NOW()
            WHERE id = ?
        ")->execute([$status, $is_incoherent, $documentId]);

        moveDocument($pdo, $documentId, $document['file_path'], $status);

        $pdo->commit();

        echo "<pre>Document classé en ERROR</pre>";
        exit;
    }

    /* ===== Résultats ===== */

    $detected_category_id = $result['detected_category_id'];
    $detected_service_id = $result['detected_service_id'];
    $confidence_level = $result['confidence_level'];

    if ($confidence_level === 'HIGH' && !$is_incoherent) {
        $status = 'validated';
    }

    $final_score = isset($result['score_global'])
        ? $result['score_global']
        : $result['confidence_score'];

    /* ===== Update document ===== */

    $update = $pdo->prepare("
        UPDATE documents SET
            detected_category_id = ?,
            detected_service_id = ?,
            confidence_score = ?,
            category_confidence = ?,
            service_confidence = ?,
            confidence_level = ?,
            is_incoherent = ?,
            status = ?,
            processed_at = NOW()
        WHERE id = ?
    ");

    $update->execute([
        $detected_category_id,
        $detected_service_id,
        $final_score,
        $result['category_confidence'],
        $result['service_confidence'],
        $confidence_level,
        $is_incoherent,
        $status,
        $documentId
    ]);

    /* ===== OCR result ===== */

    $ocr_score = isset($result['confidence_score'])
        ? $result['confidence_score']
        : 0.90;

    $insertOcr = $pdo->prepare("
        INSERT INTO ocr_results
        (document_id, extracted_text, confidence_score, processed_at)
        VALUES (?, ?, ?, NOW())
    ");

    $insertOcr->execute([
        $documentId,
        $result['extracted_text'],
        $ocr_score
    ]);

    /* ===== Déplacement ===== */

    moveDocument($pdo, $documentId, $document['file_path'], $status);

    /* ===== Audit log ===== */

    $operatorId = $_SESSION['user_id'] ?? null;
    $operatorRole = $_SESSION['role'] ?? 'system';

    $description = "OCR effectué. "
        . "Catégorie: $detected_category_id, "
        . "Service: $detected_service_id, "
        . "Niveau: $confidence_level";

    if ($is_incoherent) {
        $description .= " (Incohérence détectée)";
    }

    $insertLog = $pdo->prepare("
        INSERT INTO audit_logs
        (operator_id, operator_role, action_type, target_type, target_id, description, logged_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $insertLog->execute([
        $operatorId,
        $operatorRole,
        'OCR_PROCESS',
        'document',
        $documentId,
        $description
    ]);

    $pdo->commit();

    header("Location: ../public/user/upload_document.php?status=" . $status);
    exit;
} catch (Exception $e) {

    $pdo->rollBack();

    echo "<pre>";
    echo "===== ERREUR SQL =====\n";
    echo $e->getMessage();
    echo "\n===== FIN =====";
    echo "</pre>";
    exit;
}

/* =========================
   MOVE DOCUMENT + UPDATE PATH
========================= */

function moveDocument($pdo, $documentId, $relativePath, $newStatus)
{
    $baseDir = realpath(__DIR__ . '/../uploads/');
    $filename = basename($relativePath);

    $oldPath = realpath(__DIR__ . '/../' . $relativePath);
    $newDir = $baseDir . '/' . $newStatus;

    if (!is_dir($newDir)) {
        mkdir($newDir, 0777, true);
    }

    $newPath = $newDir . '/' . $filename;

    if (!rename($oldPath, $newPath)) {
        throw new Exception("Échec déplacement fichier");
    }

    $newRelativePath = 'uploads/' . $newStatus . '/' . $filename;

    $pdo->prepare("
        UPDATE documents SET file_path = ?
        WHERE id = ?
    ")->execute([$newRelativePath, $documentId]);

    return $newRelativePath;
}
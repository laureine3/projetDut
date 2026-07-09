<?php
require_once "../config/auth.php";
requireAnyRole(['admin','agent']);
require_once "../config/database.php";


$userInitial = !empty($user['name']) ? strtoupper(substr($user['name'], 0, 1)) : '?';

$userId = $_SESSION['user_id'];
$message = "";
$messageType = "";

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $newName  = trim($_POST['name']);
    $newEmail = trim($_POST['email']);
    $newPass  = trim($_POST['password']);

    try {

        $updateFields = [];
        $params = [];

        /* ===== NOM ===== */
        if (!empty($newName)) {
            $updateFields[] = "name = ?";
            $params[] = $newName;
            $_SESSION['name'] = $newName;
        }

        /* ===== EMAIL UNIQUE ===== */
        if (!empty($newEmail) && $newEmail !== $user['email']) {

            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$newEmail, $userId]);

            if ($check->rowCount() > 0) {
                throw new Exception("Email déjà utilisé.");
            }

            $updateFields[] = "email = ?";
            $params[] = $newEmail;
            $_SESSION['email'] = $newEmail;
        }

        /* ===== PASSWORD ===== */
        if (!empty($newPass)) {
            $updateFields[] = "password = ?";
            $params[] = password_hash($newPass, PASSWORD_DEFAULT);
        }

        /* ===== IMAGE VALIDATION ===== */
        if (!empty($_FILES['profile_img']['name'])) {

            $allowedTypes = ['image/jpeg','image/png','image/jpg'];
            $fileType = $_FILES['profile_img']['type'];

            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception("Type d'image non autorisé (JPG, PNG uniquement).");
            }

            $uploadDir = "../uploads/profile/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = time() . "_" . basename($_FILES['profile_img']['name']);
            $targetPath = $uploadDir . $fileName;

            move_uploaded_file($_FILES['profile_img']['tmp_name'], $targetPath);

            $updateFields[] = "profile_image = ?";
            $params[] = $fileName;
        }

        if (!empty($updateFields)) {

            $params[] = $userId;

            $sql = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $log = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, created_at)
                VALUES (?, ?, NOW())
            ");
            $log->execute([$userId, "Mise à jour du profil"]);

            $message = "Profil mis à jour avec succès.";
            $messageType = "success";
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = "error";
    }
}

$dashboardLink = "/projetDUT/public/" . $_SESSION['role'] . "/dashboard.php";
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Profil</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ── Variables ── */
:root {
    --ink:        #0d1b2a;
    --ink-2:      #2c3e52;
    --ink-muted:  #7f8fa4;
    --line:       #e8ecf1;
    --surface:    #ffffff;
    --bg:         #f5f7fa;
    --blue:       #2563eb;
    --blue-soft:  #eff4ff;
    --blue-dark:  #1d4ed8;
    --green:      #059669;
    --green-soft: #ecfdf5;
    --amber:      #d97706;
    --amber-soft: #fffbeb;
    --red:        #dc2626;
    --red-soft:   #fef2f2;
    --shadow-sm:  0 1px 4px rgba(13,27,42,.06);
    --shadow:     0 4px 16px rgba(13,27,42,.08);
    --r:          10px;
}
 
/* ── Reset ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
 
body {
    font-family: 'DM Sans', "Segoe UI", sans-serif;
    background: var(--surface);
    color: var(--ink);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}
 
/* ══════════════════════════
   NAV
══════════════════════════ */
nav {
    padding: 15px 30px;
    background: linear-gradient(145deg, var(--ink) 0%, #1a3a8f 60%, #2563eb 100%);
    box-shadow: var(--shadow-sm);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
 
.logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}
 
.logo-icon {
    background: var(--blue);
    color: white;
    width: 34px; height: 34px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
}
 
.logo-text {
    font-family: 'DM Serif Display', serif;
    font-weight: 400;
    font-size: 18px;
    color: var(--surface);
    letter-spacing: -.2px;
}
 
.logo-text span { color: #93b4fd; }
 
/* ══════════════════════════
   LAYOUT DEUX BLOCS
══════════════════════════ */
.container {
    display: flex;
    flex: 1;
    height: calc(100vh - 60px);
    overflow: hidden;
}
 
/* ── BLOC GAUCHE ── */
.left-panel {
    width: 45%;
    background: linear-gradient(145deg, var(--ink) 0%, #1a3a8f 60%, #2563eb 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 48px 40px;
    position: relative;
    overflow: hidden;
    flex-shrink: 0;
}
 
/* Grille décorative gauche */
.left-panel::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
}
 
/* Orbe décorative */
.left-panel::after {
    content: '';
    position: absolute;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(59,130,246,.2), transparent 70%);
    bottom: -100px; right: -100px;
    border-radius: 50%;
    pointer-events: none;
}
 
.left {
    position: relative;
    z-index: 1;
    max-width: 360px;
    width: 100%;
}
 
.left-content {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 32px;
}
 
/* ✅ Image qui ne déborde plus */
.left img {
    width: 160px;
    max-width: 100%;
    height: auto;
    object-fit: contain;
    display: block;
    border-radius: 12px;
    filter: drop-shadow(0 8px 24px rgba(0,0,0,.3));
}
 
.left h1 {
    font-family: 'DM Serif Display', serif;
    font-size: 28px;
    font-weight: 400;
    color: #ffffff;
    line-height: 1.2;
    margin-bottom: 12px;
    letter-spacing: -.5px;
}
 
.accent { color: #93b4fd; }
 
.desc {
    margin: 12px 0 20px;
    color: rgba(255,255,255,.55);
    font-size: 14px;
    line-height: 1.7;
}
 
.left ul {
    list-style: none;
}
 
.left li {
    margin: 10px 0;
    color: rgba(255,255,255,.7);
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}
 
.left li i {
    color: #93b4fd;
    font-size: 14px;
    width: 16px;
    text-align: center;
}
 
/* ── BLOC DROIT ── */
.right-panel {
    width: 55%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg);
    padding: 32px 40px;
    overflow-y: auto;
}
 
/* ── FORM BOX ── */
.form-box {
    background: var(--surface);
    padding: 40px 36px;
    border-radius: 14px;
    box-shadow: var(--shadow);
    border: 1px solid var(--line);
    width: 100%;
    max-width: 420px;
}
 
.form-box h2 {
    margin-bottom: 6px;
    color: var(--ink);
    font-family: 'DM Serif Display', serif;
    font-size: 22px;
    font-weight: 400;
    letter-spacing: -.3px;
}
 
.form-box-subtitle {
    font-size: 13px;
    color: var(--ink-muted);
    margin-bottom: 24px;
}
 
.form-divider {
    height: 1px;
    background: var(--line);
    margin-bottom: 22px;
}
 
/* ── Avatar actuel ── */
.avatar-section {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 22px;
    padding: 14px 16px;
    background: var(--bg);
    border-radius: 10px;
    border: 1px solid var(--line);
}
 
.avatar {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--line);
    flex-shrink: 0;
}
 
.avatar-fallback {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--blue);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'DM Serif Display', serif;
    font-size: 22px;
    flex-shrink: 0;
}
 
.avatar-info strong {
    display: block;
    font-size: 14px;
    color: var(--ink);
    font-weight: 600;
}
 
.avatar-info span {
    font-size: 12px;
    color: var(--ink-muted);
    text-transform: capitalize;
}
 
/* ── Labels / champs ── */
.field-label {
    display: block;
    font-size: 11.5px;
    font-weight: 600;
    color: var(--ink-muted);
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 6px;
}
 
.field-group { margin-bottom: 16px; }
 
/* ── Inputs ── */
input {
    width: 100%;
    padding: 10px 13px;
    margin-top: 0;
    margin-bottom: 0;
    border-radius: 9px;
    border: 1.5px solid var(--line);
    font-size: 14px;
    font-family: 'DM Sans', "Segoe UI", sans-serif;
    color: var(--ink);
    background: var(--bg);
    outline: none;
    transition: border-color .2s, box-shadow .2s, background .2s;
}
 
input:focus {
    border-color: var(--blue);
    background: var(--surface);
    box-shadow: 0 0 0 4px rgba(37,99,235,.1);
}
 
input::placeholder { color: var(--ink-muted); font-size: 13.5px; }
 
input[type="file"] {
    padding: 8px 12px;
    font-size: 13px;
    cursor: pointer;
}
 
/* ── Password box ── */
.password-box {
    display: flex;
    align-items: center;
    position: relative;
}
 
.password-box input {
    padding-right: 42px;
}
 
.password-box span {
    position: absolute;
    right: 13px;
    cursor: pointer;
    color: var(--ink-muted);
    font-size: 15px;
    transition: color .2s;
    margin-left: -30px;
    user-select: none;
}
 
.password-box span:hover { color: var(--blue); }
 
/* ── Bouton ── */
.btn-primary {
    width: 100%;
    background: var(--blue);
    color: white;
    padding: 12px;
    border: none;
    border-radius: var(--r);
    cursor: pointer;
    transition: 0.2s;
    font-size: 14.5px;
    font-weight: 600;
    font-family: 'DM Sans', "Segoe UI", sans-serif;
    box-shadow: 0 4px 16px rgba(37,99,235,.3);
    margin-top: 6px;
}
 
.btn-primary:hover {
    background: var(--blue-dark);
    transform: translateY(-1px);
    box-shadow: 0 8px 24px rgba(37,99,235,.4);
}
 
/* ── Avatar image ── */
/* (class déjà définie plus haut dans .avatar-section) */
 
/* ── Back ── */
.back {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 16px;
    text-align: center;
    color: var(--blue);
    text-decoration: none;
    font-size: 13.5px;
    justify-content: center;
    font-weight: 500;
    transition: .2s;
}
 
.back:hover { text-decoration: underline; }
 
/* ── Toast ── */
#toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: var(--surface);
    padding: 12px 18px;
    border-radius: var(--r);
    box-shadow: 0 8px 24px rgba(13,27,42,.15);
    border: 1px solid var(--line);
    font-size: 13.5px;
    font-weight: 500;
    display: none;
    z-index: 999;
    transition: .3s;
}
 
#toast.success { border-left: 3px solid var(--green); color: var(--green); }
#toast.error   { border-left: 3px solid var(--red);   color: var(--red); }
 
/* ── Responsive ── */
@media (max-width: 900px) {
    .container { flex-direction: column; height: auto; }
    .left-panel { display: none; }
    .right-panel { width: 100%; padding: 28px 20px; }
}
 
</style>
</head>
<body>
 
<?php $active_page = "profile"; ?>
 
<!-- NAV -->
<nav>
    <a class="logo" href="#">
        <div class="logo-icon"><i class="fa-solid fa-file-shield"></i></div>
        <span class="logo-text">Gesti<span>Doc</span></span>
    </a>
</nav>
 
<!-- CONTAINER DEUX BLOCS -->
<div class="container">
 
    <!-- ── BLOC GAUCHE ── -->
    <div class="left-panel">
        <div class="left">
 
            <div class="left-content">
                <img src="/projetDUT/img/logout.png" alt="illustration">
            </div>
 
            <h1>
                Bienvenue sur <br>
                <span class="accent">GestiDOC</span>
            </h1>
 
            <p class="desc">
                Gérez vos documents intelligemment avec OCR et automatisation.
            </p>
 
            <ul>
                <li><i class="fas fa-magic"></i> Extraction automatique</li>
                <li><i class="fas fa-search"></i> Recherche rapide</li>
                <li><i class="fas fa-shield-halved"></i> Sécurité avancée</li>
                <li><i class="fas fa-clock"></i> Gain de temps</li>
            </ul>
 
        </div>
    </div>
 
    <!-- ── BLOC DROIT ── -->
    <div class="right-panel">
 
        <div class="form-box">
 
            <h2>Mon Profil</h2>
            <p class="form-box-subtitle">Modifiez vos informations personnelles</p>
            <div class="form-divider"></div>
 
            <!-- Avatar actuel -->
            <div class="avatar-section">
                <?php if (!empty($user['profile_image'])): ?>
                    <img class="avatar" src="/projetDUT/uploads/profile/<?= $user['profile_image'] ?>" alt="avatar">
                <?php else: ?>
                    <div class="avatar-fallback"><?= $userInitial ?></div>
                <?php endif; ?>
                <div class="avatar-info">
                    <strong><?= htmlspecialchars($user['name']) ?></strong>
                    <span><?= htmlspecialchars($user['role']) ?></span>
                </div>
            </div>
 
            <form method="POST" enctype="multipart/form-data">
 
                <div class="field-group">
                    <label class="field-label"><i class="fas fa-image"></i> Photo de profil</label>
                    <input type="file" name="profile_img" accept="image/jpeg,image/png">
                </div>
 
                <div class="field-group">
                    <label class="field-label">Nom</label>
                    <input type="text" name="name" placeholder="<?= htmlspecialchars($user['name']) ?>">
                </div>
 
                <div class="field-group">
                    <label class="field-label">Email</label>
                    <input type="text" name="email" placeholder="<?= htmlspecialchars($user['email']) ?>">
                </div>
 
                <div class="field-group">
                    <label class="field-label">Mot de passe</label>
                    <div class="password-box">
                        <input type="password" name="password" id="password" placeholder="Nouveau mot de passe">
                        <span onclick="togglePassword()" title="Afficher/Masquer">
                            <i class="fa-solid fa-eye" id="eye-icon"></i>
                        </span>
                    </div>
                </div>
 
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk" style="margin-right:7px;"></i>
                    Mettre à jour
                </button>
 
            </form>
 
            <a class="back" href="<?= $dashboardLink ?>">
                <i class="fas fa-arrow-left"></i> Retour au dashboard
            </a>
 
        </div>
 
    </div>
 
</div>
 
<div id="toast"></div>

</body>

<script>
function togglePassword() {
    const input = document.getElementById("password");
    input.type = input.type === "password" ? "text" : "password";
}

<?php if ($message): ?>
const toast = document.getElementById("toast");
toast.innerText = "<?= htmlspecialchars($message) ?>";
toast.style.padding = "10px";
toast.style.marginTop = "10px";
toast.style.border = "1px solid";
setTimeout(() => { toast.innerText = ""; }, 4000);
<?php endif; ?>
</script>

</body>
</html>
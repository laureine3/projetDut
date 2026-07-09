<?php
session_start();
require_once "../config/database.php";
require_once "../config/auth.php";

$error = "";

// Si déjà connecté → redirection automatique
if (isset($_SESSION['user_id']) && empty($_SESSION['force_password_change'])) {
    redirectByRole();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user["password"])) {

            // 🔒 Vérifier compte actif
            if ($user['is_active'] == 0) {
                $error = "Votre compte a été désactivé.";
            } else {

                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["name"]    = $user["name"];
                $_SESSION["email"]   = $user["email"];
                $_SESSION["role"]    = $user["role"];

                // 🔐 Forcer changement mot de passe
                if ($user['must_change_password'] == 1) {
                    $_SESSION['force_password_change'] = true;
                    header("Location: /projetDUT/public/change-password.php");
                    exit;
                }

                // 🚀 Redirection automatique
                redirectByRole();
            }

        } else {
            $error = "Email ou mot de passe incorrect.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Connexion - GestiDoc</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>

        /* ── Variables ── */
        :root {
            --ink:       #0d1b2a;
            --ink-muted: #7f8fa4;
            --line:      #e8ecf1;
            --bg:        #f5f7fa;
            --surface:   #ffffff;
            --blue:      #2563eb;
            --blue-dark: #1d4ed8;
            --blue-soft: #eff4ff;
            --red:       #dc2626;
            --red-soft:  #fef2f2;
            --shadow:    0 20px 60px rgba(13,27,42,.18);
            --r:         14px;
        }

        /* ── Reset ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* ── Body ── */
        body {
            font-family: 'DM Sans', Arial, sans-serif;
            background: #080c14;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            position: relative;
            overflow: hidden;
        }

        /* Orbes de fond */
        body::before,
        body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            pointer-events: none;
            filter: blur(80px);
            z-index: 0;
        }
        body::before {
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(37,99,235,.18), transparent 70%);
            top: -100px; right: -100px;
        }
        body::after {
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(6,182,212,.1), transparent 70%);
            bottom: -80px; left: -80px;
        }

        /* Grille décorative */
        .bg-grid {
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
            background-size: 50px 50px;
            pointer-events: none;
            z-index: 0;
        }

        /* ── Login container ── */
        .login-container {
            background: white;
            padding: 44px 40px;
            border-radius: var(--r);
            width: 400px;
            box-shadow: var(--shadow);
            position: relative;
            z-index: 1;
            animation: slideUp .5s cubic-bezier(.34,1.56,.64,1) both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(28px) scale(.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* ── Logo ── */
        .auth-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 26px;
        }

        .auth-logo-icon {
            width: 40px; height: 40px;
            background: var(--blue);
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 17px;
            box-shadow: 0 4px 14px rgba(37,99,235,.35);
        }

        .auth-logo-text {
            font-family: 'DM Serif Display', serif;
            font-size: 22px;
            color: var(--ink);
        }

        .auth-logo-text span { color: var(--blue); }

        /* ── Titre h2 ── */
        h2 {
            text-align: center;
            margin-bottom: 6px;
            font-family: 'DM Serif Display', serif;
            font-size: 24px;
            font-weight: 400;
            color: var(--ink);
            letter-spacing: -.3px;
        }

        .auth-subtitle {
            text-align: center;
            font-size: 13px;
            color: var(--ink-muted);
            margin-bottom: 26px;
        }

        .auth-divider {
            height: 1px;
            background: var(--line);
            margin-bottom: 24px;
        }

        /* ── Labels ── */
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
            padding: 11px 14px;
            margin-bottom: 0;
            border-radius: 9px;
            border: 1.5px solid var(--line);
            font-size: 14px;
            font-family: 'DM Sans', Arial, sans-serif;
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

        /* ── Bouton submit ── */
        button {
            width: 100%;
            padding: 12px;
            border: none;
            background: var(--blue);
            color: white;
            font-weight: 600;
            font-size: 14.5px;
            font-family: 'DM Sans', Arial, sans-serif;
            border-radius: 9px;
            cursor: pointer;
            margin-top: 6px;
            box-shadow: 0 4px 16px rgba(37,99,235,.3);
            transition: background .2s, transform .2s, box-shadow .2s;
        }

        button:hover {
            background: var(--blue-dark);
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(37,99,235,.4);
        }

        button:active { transform: translateY(0); }

        /* ── Erreur ── */
        .error {
            color: var(--red);
            margin-bottom: 18px;
            text-align: center;
            font-size: 13.5px;
            font-weight: 500;
            background: var(--red-soft);
            border: 1px solid rgba(220,38,38,.2);
            padding: 10px 14px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }

        /* ── Lien ── */
        .link {
            margin-top: 20px;
            text-align: center;
            font-size: 13.5px;
            color: var(--ink-muted);
        }

        .link a {
            text-decoration: none;
            color: var(--blue);
            font-weight: 500;
        }

        .link a:hover { text-decoration: underline; }

        /* ── Badge sécurité ── */
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 22px;
            font-size: 11.5px;
            color: var(--ink-muted);
        }

        .security-badge i { color: #10b981; font-size: 11px; }

        /* ── Password box ── */
        .password-box {
            position: relative;
            width: 100%;
        }

        /* ✅ padding-right pour que le texte ne passe pas sous l'œil */
        .password-box input {
            width: 100%;
            padding: 11px 42px 11px 14px;
        }

        .password-box i {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--ink-muted);
            font-size: 15px;
            transition: color .2s;
            user-select: none;
        }

        .password-box i:hover { color: var(--blue); }

    </style>
</head>

<body>

    <div class="bg-grid"></div>

    <div class="login-container">

        <!-- Logo GestiDoc -->
        <div class="auth-logo">
            <div class="auth-logo-icon"><i class="fa-solid fa-file-shield"></i></div>
            <div class="auth-logo-text">Gesti<span>Doc</span></div>
        </div>

        <h2>Connexion</h2>
        <p class="auth-subtitle">Accédez à votre espace de gestion documentaire</p>
        <div class="auth-divider"></div>

        <?php if ($error): ?>
            <div class="error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="field-group">
                <label class="field-label">Adresse email</label>
                <input type="email" name="email" placeholder="exemple@email.com" required>
            </div>

            <div class="field-group">
                <label class="field-label">Mot de passe</label>
                <div class="password-box">
                    <!-- ✅ id="password" sans espace -->
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                    <i class="fa-solid fa-eye" id="eye"></i>
                </div>
            </div>

            <button type="submit">
                <i class="fa-solid fa-arrow-right-to-bracket" style="margin-right:7px;"></i>
                Se connecter
            </button>
        </form>

        <div class="link">
            <a href="register.php">Pas encore inscrit ? Créer un compte</a>
        </div>

        <div class="security-badge">
            <i class="fa-solid fa-lock"></i>
            Connexion sécurisée
        </div>

    </div>

    <script>
        console.log("click");
       /* eye.style.color = "red"*/
       document.addEventListener("DOMContentLoaded", function () {

            /* ✅ CORRECTIONS :
               1. "pasword" → "password"  (faute de frappe dans le nom de variable)
               2. getElementById("password") sans espace (l'espace dans l'id original cassait tout)
               3. La variable s'appelle "password" pas "pasword"                         */
            const password = document.getElementById("password");
            const eye = document.getElementById("eye");

            eye.addEventListener("click", function () {

             if (password.type === "password") {
                password.type = "text";
                eye.className = "fa-solid fa-eye-slash";
                /*eye.classList.add("fa-eye-slash");*/
            } else {
                 password.type = "password";
                 eye.className = "fa-solid fa-eye";
                /*eye.classList.remove("fa-eye-slash");
                eye.classList.add("fa-eye");*/
            }
            });
        });
    </script>
</body>

</html>
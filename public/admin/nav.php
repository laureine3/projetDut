<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
     :root {
  --ink:        #03376e;
  --ink-2:      #2c3e52;
  --ink-muted:  #7f8fa4;
  --line:       #e8ecf1;
  --surface:    #ffffff;
  --bg:         #f5f7fa;
  --blue:       #2563eb;
  --blue-soft:  #eff4ff;
  --green:      #059669;
  --green-soft: #ecfdf5;
  --amber:      #d97706;
  --amber-soft: #fffbeb;
  --red:        #dc2626;
  --red-soft:   #fef2f2;
  --sidebar-w:  230px;
  --r:          10px;
  --shadow-sm:  0 1px 4px rgba(13,27,42,.06);
  --shadow:     0 4px 16px rgba(13,27,42,.08);
}
        /* RESET */
        /*body {
            border: 10px solid red !important
        }*/
.sidebar {
  width:var(--sidebar-w);
  background:var(--ink);
  min-height:100vh;
  position:fixed;
  top:0; left:0;
  display:flex;
  flex-direction:column;
  z-index:200;
}

.s-logo {
  display:flex; align-items:center; gap:10px;
  padding:22px 20px 18px;
  border-bottom:1px solid rgba(255,255,255,.07);
}
.s-logo-mark {
  width:34px; height:34px;
  background:var(--blue);
  border-radius:7px;
  display:flex; align-items:center; justify-content:center;
  color:#fff; font-size:15px;
}
.s-logo-name {
  font-family:'DM Serif Display',serif;
  font-size:17px; color:#fff; letter-spacing:-.3px;
}
.s-logo-name span { color:#93b4fd; }

.s-section {
  padding:18px 12px 4px;
  font-size:10.5px;
  color:rgba(255,255,255,.3);
  letter-spacing:1px;
  text-transform:uppercase;
  font-weight:500;
}

.s-nav { list-style:none; padding:0 10px; }
.s-nav li { margin:2px 0; }
.s-nav a {
  display:flex; align-items:center; gap:9px;
  padding:9px 11px;
  border-radius:7px;
  color:rgba(255,255,255,.55);
  text-decoration:none;
  font-size:13.5px;
  transition:.2s;
  cursor:pointer;
}
.s-nav a i { width:16px; text-align:center; font-size:13px; }
.s-nav a:hover { background:rgba(255,255,255,.07); color:rgba(255,255,255,.9); }
/*.s-nav a.active { background:var(--blue); color:#fff; font-weight:500; }*/

.s-bottom {
  margin-top:auto;
  padding:14px 10px;
  border-top:1px solid rgba(255,255,255,.07);
}
.s-bottom a {
  display:flex; align-items:center; gap:9px;
  padding:9px 11px; border-radius:7px;
  color:rgba(255,255,255,.4);
  text-decoration:none; font-size:13.5px;
  transition:.2s;
}
.s-bottom a:hover { color:var(--red); background:rgba(220,38,38,.08); }

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: 'Segoe UI', sans-serif;
}


/* RESPONSIVE */
@media (max-width: 768px) {
  .sidebar {
    width: 100%;
    height: auto;
    position: relative;
    flex-direction: row;
    flex-wrap: wrap;
    justify-content: center;
  }

  .sidebar a {
    flex: 1 1 40%;
    text-align: center;
  }
}
    </style>
</head>
<body>
   
  <aside class="sidebar">
  <div class="s-logo">
    <div class="s-logo-mark"><i class="fa-solid fa-file-shield"></i></div>
    <div class="s-logo-name">Gesti<span>Doc</span></div>
    <h2>Admin</h2>
  </div>

        <div class="s-section">Principal</div>
  <ul class="s-nav">
    <li><a href="dashboard.php" class="active"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
    <li><a href="documents.php"><i class="fa-solid fa-folder-open"></i> Documents</a></li>
    <li><a href="users.php"><i class="fa-solid fa-users"></i> Utilisateurs</a></li>
  </ul>

  <div class="s-section">Analyse</div>
  <ul class="s-nav">
    <li><a href="audit_logs.php"><i class="fa-solid fa-scroll"></i> Audit</a></li>
    <li><a href="notifications.php"><i class="fa-solid fa-chart-line"></i> Notifications</a></li>
  </ul>

  <div class="s-section">Compte</div>
  <ul class="s-nav">
    <li><a href="../profile.php"><i class="fa-solid fa-user"></i> Profil</a></li>
  </ul>

   <div class="s-bottom">
    <a href="../../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>
  
</aside>
</body>
</html>
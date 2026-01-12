<?php
session_start();
include "../db.php";
include "includes/i18n.php";

if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit;
}

$user_id = (int)$_SESSION['user_id'];

/* default (wajib ada sebelum POST supaya tidak undefined) */
$language = $_SESSION['app_language'] ?? 'English';
$currency = $_SESSION['app_currency'] ?? 'IDR';

/* autosave via POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $lang = $_POST['language'] ?? $language;   // fallback ke default
  $curr = $_POST['currency'] ?? $currency;   // fallback ke default

  $allowedLang = ['English','Indonesia'];
  $allowedCurr = ['IDR','USD','SGD'];

  if (!in_array($lang, $allowedLang, true)) $lang = 'English';
  if (!in_array($curr, $allowedCurr, true)) $curr = 'IDR';

  $_SESSION['app_language'] = $lang;
  $_SESSION['app_currency'] = $curr;

  header("Location: setting.php?saved=1");
  exit;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(__('html_lang')) ?>">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars(__('settings_title')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    .setting-page{ padding-bottom:110px; }

    .setting-header{
      display:flex; align-items:center; justify-content:center;
      margin: 6px 0 12px;
    }
    .setting-header h3{
      margin:0; font-size:16px; font-weight:800; color:#111;
    }

    .setting-cards{
      display:flex; flex-direction:column; gap:12px; margin-top:10px;
    }

    .set-card{
      background: linear-gradient(180deg, #008b85 0%, #007a74 100%);
      border-radius: 18px;
      padding: 10px 10px;
      display:flex; align-items:center; justify-content:space-between;
      box-shadow: 0 10px 18px rgba(0,139,133,.18);
      border: 1px solid rgba(255,255,255,.12);
    }
    .set-left{ display:flex; flex-direction:column; gap:4px; }
    .set-left .title{
      color:#eaffff; font-weight:800; font-size:13px; letter-spacing:.2px;
    }
    .set-left .sub{
      color:rgba(234,255,255,.75); font-weight:600; font-size:11px;
    }

    .set-right{
      display:flex; align-items:center; gap:8px;
      background: rgba(255,255,255,.10);
      border: 1px solid rgba(255,255,255,.22);
      border-radius: 999px;
      padding: 8px 12px;
      min-width: 118px;
      justify-content:space-between;
    }
    .set-right select{
      appearance:none; -webkit-appearance:none; -moz-appearance:none;
      background:transparent; border:none; outline:none;
      color:#fff; font-weight:800; font-size:12px;
      width: 100%;
      cursor:pointer;
    }
    .set-right i{
      color:#fff; font-size:12px; opacity:.95; pointer-events:none;
    }

    .setting-illus{
      margin-top: 18px;
      display:flex; align-items:center; justify-content:center;
    }
    .setting-illus img{
      width: 78%;
      max-width: 300px;
      height:auto;
      display:block;
    }

    .saved-toast{
      margin-top:10px;
      background:#e6fffb;
      border:1px solid #bfeee5;
      color:#007a74;
      border-radius:12px;
      padding:10px 12px;
      font-size:12px;
      font-weight:800;
      text-align:center;
    }

    .logout-inline{
      margin-top: 10px;
      display:flex;
      justify-content:right;
    }

    .logout-icon{
      width:32px;
      height:32px;
      display:flex;
      align-items:center;
      justify-content:center;
      border-radius:10px;
      background:#980404;
      border:1px solid #e2e8f0;
      color:#e2e8f0;
      cursor:pointer;
      transition: all .15s ease;
    }
    .logout-icon i{ font-size:16px; }
    .logout-icon:hover{
      background:#e2e8f0;
      border-color:#980404;
      transform: translateY(-1px);
    }
    .logout-icon:active{ transform: translateY(0); }

    .modal-overlay{
      position:fixed;
      inset:0;
      display:none;
      align-items:center;
      justify-content:center;
      padding:18px;
      background: rgba(15, 23, 42, .40);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      z-index:9999;
    }
    .modal-overlay.show{ display:flex; }

    .modal-card{
      width:100%;
      max-width: 360px;
      background: rgba(255,255,255,.92);
      border: 1px solid rgba(255,255,255,.55);
      border-radius: 18px;
      box-shadow: 0 22px 60px rgba(2, 6, 23, .22);
      padding: 16px;
      display:flex;
      flex-direction:column;
      gap:12px;
      animation: pop .14s ease-out;
    }
    @keyframes pop{
      from{ transform: translateY(6px) scale(.98); opacity:0; }
      to{ transform: translateY(0) scale(1); opacity:1; }
    }

    .modal-icon{
      width:44px;
      height:44px;
      border-radius:14px;
      display:flex;
      align-items:center;
      justify-content:center;
      background: rgba(0, 139, 133, .10);
      border: 1px solid rgba(0, 139, 133, .18);
      color:#007a74;
    }

    .modal-title{
      font-size:14px;
      font-weight:900;
      color:#0f172a;
    }
    .modal-sub{
      margin-top:4px;
      font-size:12px;
      font-weight:700;
      color: rgba(15, 23, 42, .65);
    }

    .modal-actions{
      display:flex;
      gap:10px;
      margin-top:4px;
    }

    .btn-ghost{
      flex:1;
      padding:10px 12px;
      border-radius:12px;
      border:1px solid rgba(15, 23, 42, .12);
      background: rgba(15, 23, 42, .04);
      color:#0f172a;
      font-weight:900;
      cursor:pointer;
    }
    .btn-danger{
      flex:1;
      padding:10px 12px;
      border-radius:12px;
      border:1px solid rgba(239, 68, 68, .22);
      background: rgba(239, 68, 68, .12);
      color:#b91c1c;
      font-weight:900;
      text-align:center;
      text-decoration:none;
    }
    .btn-ghost:hover{ background: rgba(15, 23, 42, .06); }
    .btn-danger:hover{ background: rgba(239, 68, 68, .16); }
  </style>
</head>

<body>
<div class="container setting-page">

  <div class="setting-header">
    <h3><?= htmlspecialchars(__('settings_title')) ?></h3>
  </div>

  <?php if (isset($_GET['saved'])): ?>
    <div class="saved-toast"><?= htmlspecialchars(__('saved')) ?></div>
  <?php endif; ?>

  <form method="POST" action="setting.php" id="setForm">
    <div class="setting-cards">

      <div class="set-card">
        <div class="set-left">
          <div class="title"><?= htmlspecialchars(__('language_title')) ?></div>
          <div class="sub"><?= htmlspecialchars(__('language_sub')) ?></div>
        </div>
        <div class="set-right">
          <select name="language" onchange="document.getElementById('setForm').submit()">
            <option value="English"   <?= ($language==='English')?'selected':''; ?>>English</option>
            <option value="Indonesia" <?= ($language==='Indonesia')?'selected':''; ?>>Indonesia</option>
          </select>
          <i class="fa-solid fa-chevron-down"></i>
        </div>
      </div>

      <div class="set-card">
        <div class="set-left">
          <div class="title"><?= htmlspecialchars(__('currency_title')) ?></div>
          <div class="sub"><?= htmlspecialchars(__('currency_sub')) ?></div>
        </div>
        <div class="set-right">
          <select name="currency" onchange="document.getElementById('setForm').submit()">
            <option value="IDR" <?= ($currency==='IDR')?'selected':''; ?>>IDR</option>
            <option value="USD" <?= ($currency==='USD')?'selected':''; ?>>USD</option>
            <option value="SGD" <?= ($currency==='SGD')?'selected':''; ?>>SGD</option>
          </select>
          <i class="fa-solid fa-chevron-down"></i>
        </div>
      </div>

    </div>
  </form>

  <div class="logout-inline">
    <button type="button" class="logout-icon" id="logoutBtn" aria-label="<?= htmlspecialchars(__('aria_logout')) ?>">
      <i class="fa-solid fa-right-from-bracket"></i>
    </button>
  </div>

  <div class="setting-illus">
    <img src="assets/img/setting.png" alt="Setting" onerror="this.src='assets/img/dashboard.png'">
  </div>

</div>

<?php include "partials/bottom_nav.php"; ?>

<!-- MODAL LOGOUT -->
<div class="modal-overlay" id="logoutModal" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="logoutTitle">
    <div class="modal-icon">
      <i class="fa-solid fa-right-from-bracket"></i>
    </div>
    <div class="modal-text">
      <div class="modal-title" id="logoutTitle"><?= htmlspecialchars(__('logout_ask')) ?></div>
      <div class="modal-sub"><?= htmlspecialchars(__('logout_sub')) ?></div>
    </div>

    <div class="modal-actions">
      <button type="button" class="btn-ghost" id="cancelLogout"><?= htmlspecialchars(__('cancel')) ?></button>
      <a href="logout.php" class="btn-danger" id="confirmLogout"><?= htmlspecialchars(__('logout')) ?></a>
    </div>
  </div>
</div>

<script>
  const modal = document.getElementById('logoutModal');
  const openBtn = document.getElementById('logoutBtn');
  const cancelBtn = document.getElementById('cancelLogout');

  function openModal(){
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
  }
  function closeModal(){
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
  }

  openBtn.addEventListener('click', openModal);
  cancelBtn.addEventListener('click', closeModal);

  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('show')) closeModal();
  });
</script>
</body>
</html>

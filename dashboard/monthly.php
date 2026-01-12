<?php
session_start();
include "../db.php";
include "includes/i18n.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
$user_id = (int)$_SESSION['user_id'];

/* =====================
   PERIOD YYYY-MM
===================== */
if (isset($_GET['period']) && preg_match('/^\d{4}-\d{2}$/', $_GET['period'])) {
  [$year, $month] = explode('-', $_GET['period']);
  if (!checkdate((int)$month, 1, (int)$year)) { $month=date('m'); $year=date('Y'); }
} else { $month=date('m'); $year=date('Y'); }

$monthInt = (int)$month;
$yearInt  = (int)$year;

function formatRp($n){ return 'Rp' . number_format((float)$n,0,',','.'); }

/* =========================================================
   ✅ FIX ICON: Monthly Expense harus pakai icon EXPAND
   (samain dengan add_transaction.php / expand)
   Folder: assets/Expanse/
========================================================= */
function categoryIconExpand($categoryName){
  $name = strtolower(trim((string)$categoryName));

  $map = [
    'food'       => 'espanse_food.png',
    'social'     => 'espanse_social.png',
    'traffic'    => 'espanse_traffic.png',
    'shopping'   => 'espanse_shopping.png',
    'grocery'    => 'espanse_grocery.png',
    'education'  => 'espanse_education.png',
    'bills'      => 'espanse_bills.png',
    'rentals'    => 'espanse_rentals.png',
    'medical'    => 'medical.png',
    'investment' => 'invesment.png',
    'gift'       => 'gift.png',
    'other'      => 'espanse_bills.png',
  ];

  return 'assets/Expanse/' . ($map[$name] ?? 'espanse_bills.png');
}

/* =====================
   TOTAL BALANCE
   FIX: monthly_expense TIDAK BOLEH mengurangi saldo.
   Jadi expense real hanya type='expand'
===================== */
$qIncome = "SELECT COALESCE(SUM(amount),0) AS total
            FROM transactions
            WHERE user_id=? AND type='income'
              AND MONTH(transaction_date)=? AND YEAR(transaction_date)=?";
$stmt = mysqli_prepare($conn, $qIncome);
mysqli_stmt_bind_param($stmt, "iii", $user_id, $monthInt, $yearInt);
mysqli_stmt_execute($stmt);
$income = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0;
mysqli_stmt_close($stmt);

$qExpense = "SELECT COALESCE(SUM(amount),0) AS total
             FROM transactions
             WHERE user_id=? AND type='expand'
               AND MONTH(transaction_date)=? AND YEAR(transaction_date)=?";
$stmt = mysqli_prepare($conn, $qExpense);
mysqli_stmt_bind_param($stmt, "iii", $user_id, $monthInt, $yearInt);
mysqli_stmt_execute($stmt);
$expense = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0;
mysqli_stmt_close($stmt);

$balance = $income - $expense;

/* =====================
   TOTAL ESTIMATE = SUM(amount_limit)
===================== */
$qEstimate = "SELECT COALESCE(SUM(amount_limit),0) AS total
              FROM budgets
              WHERE user_id=? AND month=? AND year=?";
$stmt = mysqli_prepare($conn, $qEstimate);
mysqli_stmt_bind_param($stmt, "iii", $user_id, $monthInt, $yearInt);
mysqli_stmt_execute($stmt);
$totalEstimate = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0;
mysqli_stmt_close($stmt);

/* =====================
   LIST LIMIT + SPENT (spent dari transaksi expand)
===================== */
$qList = "
  SELECT
    b.budget_id,
    b.amount_limit,
    c.category_id,
    c.category_name,
    COALESCE(SUM(t.amount),0) AS spent
  FROM budgets b
  JOIN categories c ON c.category_id = b.category_id
  LEFT JOIN transactions t
    ON t.user_id = b.user_id
   AND t.category_id = b.category_id
   AND t.type = 'expand'
   AND MONTH(t.transaction_date) = b.month
   AND YEAR(t.transaction_date) = b.year
  WHERE b.user_id = ?
    AND b.month = ?
    AND b.year = ?
  GROUP BY b.budget_id, b.amount_limit, c.category_id, c.category_name
  ORDER BY c.category_name ASC
";
$stmtL = mysqli_prepare($conn, $qList);
mysqli_stmt_bind_param($stmtL, "iii", $user_id, $monthInt, $yearInt);
mysqli_stmt_execute($stmtL);
$res = mysqli_stmt_get_result($stmtL);

$hasLimit = (mysqli_num_rows($res) > 0);

/* =====================
   TOAST (mendekati limit & limit tercapai)
===================== */
$NEAR_THRESHOLD = 0.8; // 80%
$rows = [];
$toastItems = [];

if ($hasLimit) {
  while ($r = mysqli_fetch_assoc($res)) {
    $rows[] = $r;

    $limit = (float)($r['amount_limit'] ?? 0);
    $spent = (float)($r['spent'] ?? 0);
    if ($limit <= 0) continue;

    $ratio = $spent / $limit;
    $pct = (int)round($ratio * 100);

    if ($ratio >= 1) {
      $msg = __('toast_limit_reached');
      $msg = str_replace(['{category}','{pct}'], [(string)$r['category_name'], (string)$pct], $msg);

      $toastItems[] = [
        'level' => 'danger',
        'category_id' => (int)$r['category_id'],
        'category_name' => (string)$r['category_name'],
        'spent' => $spent,
        'limit' => $limit,
        'message' => $msg
      ];
    } elseif ($ratio >= $NEAR_THRESHOLD) {
      $msg = __('toast_limit_near');
      $msg = str_replace(['{category}','{pct}'], [(string)$r['category_name'], (string)$pct], $msg);

      $toastItems[] = [
        'level' => 'warn',
        'category_id' => (int)$r['category_id'],
        'category_name' => (string)$r['category_name'],
        'spent' => $spent,
        'limit' => $limit,
        'message' => $msg
      ];
    }
  }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(__('html_lang')) ?>">
<head>
  <meta charset="UTF-8" />
  <title><?= htmlspecialchars(__('monthly_title')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    .pill-tabs{
      margin:12px 0 10px;
      background:#9bb37d;
      border-radius:999px;
      padding:3px;
      display:flex;
      gap:3px;
    }
    .pill-tabs a{
      flex:1;
      text-align:center;
      padding:10px 0;
      font-size:12px;
      color:#fff;
      text-decoration:none;
      border-radius:999px;
      font-weight:600;
    }
    .pill-tabs a.active{
      background:#008b85;
      font-weight:700;
    }

    .mini-summary{
      margin:12px 0 10px;
      background:#fff;
      border-radius:12px;
      padding:10px 12px;
      border:1px solid #d7ece6;
      display:flex;
      justify-content:space-between;
      align-items:center;
      font-size:12px;
      font-weight:600;
    }
    .mini-summary .val{color:#9bb37d;font-weight:800}

    .date-title{
      margin:12px 0 6px;
      color:#008b85;
      font-weight:700;
      font-size:12px;
    }

    .limit-row{
      background:#fff;
      border:1px solid #d7ece6;
      border-radius:12px;
      padding:10px 10px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:10px;
    }
    .limit-left{display:flex;gap:10px;align-items:center}
    .limit-ico{
      width:44px;height:44px;border-radius:12px;
      display:flex;align-items:center;justify-content:center;
      background:#e6fffb;
      overflow:hidden;
      flex:0 0 auto;
    }
    .limit-ico img{width:26px;height:26px;object-fit:contain}

    .limit-info strong{display:block;font-size:12px;font-weight:800}
    .limit-info small{display:block;font-size:10px;color:#7a8a84;margin-top:2px}

    .limit-right{text-align:right}
    .limit-right .lim{font-size:11px;font-weight:700;color:#008b85}
    .limit-right .used{font-size:10px;margin-top:2px;color:#7a8a84;font-weight:600}
    .limit-right .over{color:#ff3b3b;font-weight:800}

    /* TOAST */
    .toast-wrap{
      position:fixed;
      top:14px;
      left:50%;
      transform:translateX(-50%);
      width:min(92vw, 420px);
      z-index:9999;
      display:flex;
      flex-direction:column;
      gap:10px;
      pointer-events:none;
    }
    .toast{
      pointer-events:auto;
      background:#fff;
      border:1px solid #d7ece6;
      border-left:6px solid #9bb37d;
      border-radius:14px;
      padding:12px 12px;
      box-shadow: 0 10px 18px rgba(0,0,0,.08);
      display:flex;
      justify-content:space-between;
      gap:10px;
      align-items:flex-start;
      animation: toastIn .25s ease-out;
    }
    .toast.warn{ border-left-color:#f2b705; }
    .toast.danger{ border-left-color:#ff3b3b; }

    .toast .t-title{
      font-size:12px;
      font-weight:900;
      color:#123;
      line-height:1.2;
    }
    .toast .t-desc{
      font-size:11px;
      margin-top:4px;
      font-weight:700;
      color:#667;
      line-height:1.25;
    }
    .toast .t-close{
      border:0;
      background:transparent;
      font-size:16px;
      cursor:pointer;
      color:#667;
      padding:0 2px;
      line-height:1;
    }
    @keyframes toastIn{
      from{ transform:translateY(-6px); opacity:0; }
      to{ transform:translateY(0); opacity:1; }
    }
    @keyframes toastOut{
      from{ transform:translateY(0); opacity:1; }
      to{ transform:translateY(-6px); opacity:0; }
    }
  </style>
</head>

<body>

<div class="toast-wrap" id="toastWrap" aria-live="polite" aria-atomic="true"></div>

<div class="container">

  <h3 class="dashboard-title"><?= htmlspecialchars(__('dashboard_title')) ?></h3>

  <!-- BALANCE CARD -->
  <div class="balance-card">
    <div class="balance-header">
      <span><?= htmlspecialchars(__('total_balance')) ?></span>

      <form method="GET" class="month-select">
        <select name="period" onchange="this.form.submit()">
          <?php
          for ($y=2024;$y<=2030;$y++){
            for ($m=1;$m<=12;$m++){
              $value=sprintf("%04d-%02d",$y,$m);
              $label=date("M Y", mktime(0,0,0,$m,1,$y));
              $selected=((int)$year===$y && (int)$month===$m)?"selected":"";
              echo "<option value='$value' $selected>".htmlspecialchars($label)."</option>";
            }
          }
          ?>
        </select>
      </form>
    </div>

    <div class="balance-value">
      Rp <span id="balanceText"><?php echo number_format((float)$balance,0,',','.'); ?></span>
      <i class="fa-solid fa-eye" id="toggleBalance"></i>
    </div>

    <a class="download-link" href="#"><?= htmlspecialchars(__('download_report')) ?></a>
  </div>

  <!-- tabs -->
  <div class="pill-tabs">
    <a href="home.php?tab=income&period=<?php echo htmlspecialchars("$year-$month"); ?>">
      <?= htmlspecialchars(__('tab_income')) ?>
    </a>
    <a href="home.php?tab=expand&period=<?php echo htmlspecialchars("$year-$month"); ?>">
      <?= htmlspecialchars(__('tab_expand')) ?>
    </a>
    <a class="active" href="monthly.php?period=<?php echo htmlspecialchars("$year-$month"); ?>">
      <?= htmlspecialchars(__('tab_monthly_expense')) ?>
    </a>
  </div>

  <!-- Total Estimate -->
  <div class="mini-summary">
    <div><?= htmlspecialchars(__('total_estimate')) ?></div>
    <div class="val"><?php echo formatRp($totalEstimate); ?></div>
  </div>

  <?php if (!$hasLimit): ?>
    <div class="empty">
      <img src="assets/img/dashboard.png" class="empty-img" alt="Dashboard">
      <p><?= htmlspecialchars(__('no_limit_yet')) ?></p>
    </div>
  <?php else: ?>

    <div class="date-title"><?php echo htmlspecialchars(date('D, d F')); ?></div>

    <?php foreach($rows as $row): ?>
      <?php
        $limit = (float)($row['amount_limit'] ?? 0);
        $spent = (float)($row['spent'] ?? 0);
        $over  = ($spent > $limit && $limit > 0);

        // ✅ icon path pakai mapping expanse
        $iconPath = categoryIconExpand($row['category_name'] ?? '');
        $fallback = 'assets/Expanse/espanse_bills.png';
      ?>
      <div class="limit-row">
        <div class="limit-left">
          <div class="limit-ico">
            <img
              src="<?php echo htmlspecialchars($iconPath); ?>"
              alt=""
              onerror="this.src='<?php echo htmlspecialchars($fallback); ?>';"
            >
          </div>
          <div class="limit-info">
            <strong><?php echo htmlspecialchars($row['category_name']); ?> <?= htmlspecialchars(__('limit_label')) ?></strong>
            <small>
              <?= htmlspecialchars(__('used')) ?>: <?php echo formatRp($spent); ?>
              <?php if ($limit > 0): ?>
                • <?= htmlspecialchars(__('remaining')) ?>: <?php echo formatRp(max(0, $limit - $spent)); ?>
              <?php endif; ?>
            </small>
          </div>
        </div>
        <div class="limit-right">
          <div class="lim"><?php echo number_format($limit,0,',','.'); ?></div>
          <div class="used <?php echo $over ? 'over' : ''; ?>">
            <?php echo $over ? htmlspecialchars(__('over_limit')) : ''; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

  <?php endif; ?>

</div>

<?php include "partials/bottom_nav.php"; ?>

<script>
  const toggle = document.getElementById('toggleBalance');
  const text = document.getElementById('balanceText');
  if (toggle && text) {
    let hidden = false;
    const real = text.textContent;
    toggle.addEventListener('click', () => {
      hidden = !hidden;
      text.textContent = hidden ? "••••••" : real;
    });
  }

  const TOASTS = <?php echo json_encode($toastItems, JSON_UNESCAPED_UNICODE); ?>;
  const PERIOD = "<?php echo htmlspecialchars("$year-$month"); ?>";
  const USER_ID = "<?php echo (int)$user_id; ?>";
  const toastWrap = document.getElementById('toastWrap');

  function formatRpJs(n){
    try{
      return 'Rp' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(n || 0));
    }catch(e){
      return 'Rp' + String(n || 0);
    }
  }
  function escapeHtml(str){
    return String(str ?? '')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }
  function toastKey(item){
    return `toast:uid:${USER_ID}:period:${PERIOD}:cat:${item.category_id}:level:${item.level}`;
  }
  function shouldShow(item){
    return !localStorage.getItem(toastKey(item));
  }
  function markShown(item){
    localStorage.setItem(toastKey(item), String(Date.now()));
  }
  function removeToast(el){
    if (!el) return;
    el.style.animation = 'toastOut .2s ease-in forwards';
    setTimeout(() => {
      if (el && el.parentNode) el.parentNode.removeChild(el);
    }, 220);
  }
  function createToast(item, autoCloseMs = 6500){
    if (!toastWrap) return;

    const el = document.createElement('div');
    el.className = `toast ${item.level}`;
    el.innerHTML = `
      <div>
        <div class="t-title">${escapeHtml(item.message)}</div>
        <div class="t-desc"><?= htmlspecialchars(__('toast_used_from')) ?>: ${formatRpJs(item.spent)} / ${formatRpJs(item.limit)}</div>
      </div>
      <button class="t-close" type="button" aria-label="Close">&times;</button>
    `;
    el.querySelector('.t-close').addEventListener('click', () => removeToast(el));
    toastWrap.appendChild(el);

    if (autoCloseMs > 0){
      setTimeout(() => removeToast(el), autoCloseMs);
    }
  }

  (function showToasts(){
    if (!Array.isArray(TOASTS) || TOASTS.length === 0) return;

    const sorted = [...TOASTS].sort((a,b) => {
      const pa = (a.level === 'danger') ? 0 : 1;
      const pb = (b.level === 'danger') ? 0 : 1;
      return pa - pb;
    });

    let shown = 0;
    for (const t of sorted){
      if (shown >= 2) break;
      if (shouldShow(t)){
        createToast(t);
        markShown(t);
        shown++;
      }
    }
  })();
</script>

</body>
</html>
<?php
if (isset($stmtL) && $stmtL) mysqli_stmt_close($stmtL);
if (isset($conn) && $conn) mysqli_close($conn);
?>

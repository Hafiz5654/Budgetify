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
$periodStr = sprintf("%04d-%02d", $yearInt, $monthInt);

function formatRp($n){ return 'Rp' . number_format((float)$n,0,',','.'); }

/* =====================
   TOTAL INCOME & EXPAND (bulan aktif)
   - monthly_expense tidak dihitung sebagai expense real
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

$qExpand = "SELECT COALESCE(SUM(amount),0) AS total
            FROM transactions
            WHERE user_id=? AND type='expand'
              AND MONTH(transaction_date)=? AND YEAR(transaction_date)=?";
$stmt = mysqli_prepare($conn, $qExpand);
mysqli_stmt_bind_param($stmt, "iii", $user_id, $monthInt, $yearInt);
mysqli_stmt_execute($stmt);
$expand = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0;
mysqli_stmt_close($stmt);

$balance = (float)$income - (float)$expand;

/* =====================
   PERCENT
===================== */
$incomeF = (float)$income;
$expandF = (float)$expand;

if ($incomeF <= 0) {
  $spentPct = 0;
} else {
  $spentPct = ($expandF / $incomeF) * 100.0;
  if ($spentPct < 0) $spentPct = 0;
  if ($spentPct > 100) $spentPct = 100;
}
$remainingPct = 100 - $spentPct;

$spentPctRound = (int)round($spentPct);
$remainingPctRound = (int)round($remainingPct);

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(__('html_lang')) ?>">
<head>
  <meta charset="UTF-8" />
  <title><?= htmlspecialchars(__('budget_title')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    .page-title{
      display:flex;
      justify-content:center;
      align-items:center;
      margin:10px 0 6px;
      font-weight:900;
      font-size:16px;
      color:#123;
      letter-spacing:.2px;
    }

    .budget-shell{
      margin-top:14px;
      padding:12px 0 4px;
    }

    .donut-wrap{
      margin:26px 0 10px;
      display:flex;
      justify-content:center;
      align-items:center;
    }
    .donut{
      --p: 0;
      width:200px;
      height:200px;
      border-radius:50%;
      background: conic-gradient(#008b85 calc(var(--p) * 1%), #e6fffb 0);
      position:relative;
      display:flex;
      justify-content:center;
      align-items:center;
      filter: drop-shadow(0 10px 18px rgba(0,0,0,.06));
      transform: translateZ(0);
    }
    .donut::before{
      content:"";
      position:absolute;
      width:124px;
      height:124px;
      border-radius:50%;
      background:#fff;
      box-shadow: inset 0 0 0 1px #e9f4f1;
    }

    .donut-center{
      position:relative;
      text-align:center;
      z-index:2;
      line-height:1.1;
    }
    .donut-center .lbl{
      font-size:11px;
      font-weight:900;
      color:#7a8a84;
      display:flex;
      align-items:center;
      justify-content:center;
      gap:6px;
    }
    .donut-center .lbl i{ color:#008b85; }
    .donut-center .pct{
      margin-top:6px;
      font-size:22px;
      font-weight:1000;
      color:#123;
      letter-spacing:.3px;
    }
    .donut-center .sub{
      margin-top:6px;
      font-size:11px;
      font-weight:800;
      color:#7a8a84;
    }

    .summary-strip{
      margin:12px 0 0;
      background:#fff;
      border:1px solid #d7ece6;
      border-radius:14px;
      padding:12px 12px;
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:10px;
    }
    .sum-left strong{
      display:block;
      font-size:12px;
      font-weight:1000;
      color:#123;
    }
    .sum-left small{
      display:block;
      margin-top:4px;
      font-size:11px;
      font-weight:800;
      color:#7a8a84;
      line-height:1.3;
    }
    .sum-right{
      text-align:right;
      font-weight:1000;
      color:#008b85;
      font-size:12px;
      white-space:nowrap;
    }

    .progress-card{
      margin-top:10px;
      background:#fff;
      border:1px solid #d7ece6;
      border-radius:14px;
      padding:12px 12px;
    }
    .prog-top{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:10px;
      font-size:11px;
      font-weight:1000;
      color:#123;
    }
    .prog-top .tag{
      color:#008b85;
      background:#e6fffb;
      padding:6px 10px;
      border-radius:999px;
      font-weight:1000;
      display:flex;
      align-items:center;
      gap:6px;
    }
    .bar{
      margin-top:10px;
      width:100%;
      height:10px;
      border-radius:999px;
      background:#e6fffb;
      overflow:hidden;
      position:relative;
    }
    .bar > span{
      display:block;
      height:100%;
      width:0%;
      border-radius:999px;
      background:#008b85;
      transition: width 900ms cubic-bezier(.2,.9,.2,1);
    }
    .prog-bottom{
      margin-top:8px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      font-size:11px;
      font-weight:900;
      color:#7a8a84;
    }

    .btn-wide{
      margin-top:16px;
      width:100%;
      border:none;
      border-radius:999px;
      padding:14px 0;
      background:#008b85;
      color:#fff;
      font-weight:1000;
      font-size:13px;
      text-align:center;
      display:block;
      text-decoration:none;
      box-shadow: 0 10px 18px rgba(0,139,133,.18);
      transform: translateZ(0);
      transition: transform .15s ease;
    }
    .btn-wide:active{ transform: scale(.98); }

    .note{
      margin:10px 0 0;
      text-align:center;
      font-size:11px;
      font-weight:800;
      color:#7a8a84;
    }

    .fade-up{
      animation: fadeUp .35s ease-out;
    }
    @keyframes fadeUp{
      from{ opacity:0; transform: translateY(8px); }
      to{ opacity:1; transform: translateY(0); }
    }
  </style>
</head>

<body>
<div class="container">

  <div class="page-title"><?= htmlspecialchars(__('budget_title')) ?></div>

  <!-- BALANCE CARD -->
  <div class="balance-card fade-up">
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

     <a
  href="report.php?period=<?= htmlspecialchars("$year-$month") ?>"
  class="download-link"
>
  Download Report
</a>
  </div>

  <div class="budget-shell">
    <!-- DONUT -->
    <div class="donut-wrap fade-up">
      <div class="donut" id="donut" role="img" aria-label="<?= htmlspecialchars(__('aria_budget_donut')) ?>">
        <div class="donut-center">
          <div class="lbl"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars(__('remaining')) ?></div>
          <div class="pct"><span id="remainPct">0</span>%</div>
          <div class="sub"><?= htmlspecialchars(__('spent')) ?>: <span id="spentPct">0</span>%</div>
        </div>
      </div>
    </div>

    <!-- SUMMARY STRIP -->
    <div class="summary-strip fade-up">
      <div class="sum-left">
        <strong><?= htmlspecialchars(__('expenses_expand')) ?></strong>
        <small>
          <?= htmlspecialchars(__('month_label')) ?>: <?php echo htmlspecialchars(date("F Y", mktime(0,0,0,$monthInt,1,$yearInt))); ?><br>
          <?= htmlspecialchars(__('income_label')) ?>: <?php echo formatRp($incomeF); ?>
        </small>
      </div>
      <div class="sum-right">
        <?php echo formatRp($expandF); ?>
      </div>
    </div>

    <!-- PROGRESS CARD -->
    <div class="progress-card fade-up">
      <div class="prog-top">
        <div><?= htmlspecialchars(__('income_usage_progress')) ?></div>
        <div class="tag"><i class="fa-solid fa-bolt"></i> <?php echo (int)$spentPctRound; ?>%</div>
      </div>
      <div class="bar" aria-hidden="true"><span id="barFill"></span></div>
      <div class="prog-bottom">
        <span>0%</span>
        <span>100%</span>
      </div>
    </div>

    <a class="btn-wide fade-up" href="#"><?= htmlspecialchars(__('budget_detail')) ?></a>

    <div class="note fade-up">
      <?= htmlspecialchars(__('budget_note_expand_only')) ?>
    </div>
  </div>

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

  const spentPctTarget = <?php echo json_encode((float)$spentPct); ?>;
  const remainPctTarget = <?php echo json_encode((float)$remainingPct); ?>;

  const donut = document.getElementById('donut');
  const barFill = document.getElementById('barFill');
  const remainPctEl = document.getElementById('remainPct');
  const spentPctEl = document.getElementById('spentPct');

  function clamp(v, min, max){ return Math.min(max, Math.max(min, v)); }

  requestAnimationFrame(() => {
    barFill.style.width = clamp(spentPctTarget, 0, 100) + '%';
    donut.style.setProperty('--p', String(clamp(spentPctTarget, 0, 100)));
  });

  function animateNumber(el, to, duration=700){
    const start = performance.now();
    const from = 0;
    const end = clamp(to, 0, 100);

    function tick(now){
      const t = clamp((now - start) / duration, 0, 1);
      const eased = 1 - Math.pow(1 - t, 3);
      const val = Math.round(from + (end - from) * eased);
      el.textContent = String(val);
      if (t < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  animateNumber(remainPctEl, remainPctTarget);
  animateNumber(spentPctEl, spentPctTarget);
</script>

</body>
</html>

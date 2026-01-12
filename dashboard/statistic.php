<?php
session_start();
include "../db.php";
include "includes/i18n.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
$user_id = (int)$_SESSION['user_id'];

/* =====================
   PERIOD YYYY-MM (bulan aktif)
===================== */
if (isset($_GET['period']) && preg_match('/^\d{4}-\d{2}$/', $_GET['period'])) {
  [$year, $month] = explode('-', $_GET['period']);
  if (!checkdate((int)$month, 1, (int)$year)) { $month=date('m'); $year=date('Y'); }
} else { $month=date('m'); $year=date('Y'); }

$monthInt = (int)$month;
$yearInt  = (int)$year;
$periodStr = sprintf("%04d-%02d", $yearInt, $monthInt);

/* =====================
   TAB (income/expand)
===================== */
$tab = $_GET['tab'] ?? 'income';
$valid_tabs = ['income','expand'];
if (!in_array($tab, $valid_tabs, true)) $tab = 'income';

function formatRp($n){ return 'Rp' . number_format((float)$n,0,',','.'); }

/* =====================
   SELF FILE (anti notfound)
===================== */
$SELF = basename($_SERVER['PHP_SELF']);

/* =====================
   Helper: minggu dalam 1 bulan
   Week 1: 1-7
   Week 2: 8-14
   Week 3: 15-21
   Week 4: 22-28
   Week 5: 29-akhir bulan (jika ada)
===================== */
$daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $monthInt, $yearInt);
$weekRanges = [
  1 => [1, 7],
  2 => [8, 14],
  3 => [15, 21],
  4 => [22, 28],
];
if ($daysInMonth >= 29) $weekRanges[5] = [29, $daysInMonth];

$labels = [];
foreach ($weekRanges as $wk => $_) $labels[] = __('week_label') . " " . $wk;

/* =====================
   TOTAL BULAN AKTIF (range tanggal)
===================== */
$monthStart = sprintf("%04d-%02d-01", $yearInt, $monthInt);
$monthEnd   = date('Y-m-d', strtotime($monthStart . ' +1 month')); // end exclusive

// income bulan aktif
$qIncomeMonth = "SELECT COALESCE(SUM(amount),0) AS total
                 FROM transactions
                 WHERE user_id=? AND type='income'
                   AND transaction_date >= ? AND transaction_date < ?";
$stmt = mysqli_prepare($conn, $qIncomeMonth);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $monthStart, $monthEnd);
mysqli_stmt_execute($stmt);
$incomeMonth = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0;
mysqli_stmt_close($stmt);

// expand bulan aktif
$qExpandMonth = "SELECT COALESCE(SUM(amount),0) AS total
                 FROM transactions
                 WHERE user_id=? AND type='expand'
                   AND transaction_date >= ? AND transaction_date < ?";
$stmt = mysqli_prepare($conn, $qExpandMonth);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $monthStart, $monthEnd);
mysqli_stmt_execute($stmt);
$expandMonth = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0;
mysqli_stmt_close($stmt);

// saldo bulan aktif
$balance = $incomeMonth - $expandMonth;

/* =====================
   DATA GRAFIK: SUM per minggu (range tanggal)
===================== */
$incomeWeeks = [];
$expandWeeks = [];
foreach ($weekRanges as $wk => [$d1, $d2]) {
  $start = sprintf("%04d-%02d-%02d", $yearInt, $monthInt, $d1);
  $endExclusive = date('Y-m-d', strtotime(sprintf("%04d-%02d-%02d", $yearInt, $monthInt, $d2) . ' +1 day'));

  // income
  $q = "SELECT COALESCE(SUM(amount),0) AS total
        FROM transactions
        WHERE user_id=? AND type='income'
          AND transaction_date >= ? AND transaction_date < ?";
  $st = mysqli_prepare($conn, $q);
  mysqli_stmt_bind_param($st, "iss", $user_id, $start, $endExclusive);
  mysqli_stmt_execute($st);
  $incomeWeeks[$wk] = (float)(mysqli_fetch_assoc(mysqli_stmt_get_result($st))['total'] ?? 0);
  mysqli_stmt_close($st);

  // expand
  $q = "SELECT COALESCE(SUM(amount),0) AS total
        FROM transactions
        WHERE user_id=? AND type='expand'
          AND transaction_date >= ? AND transaction_date < ?";
  $st = mysqli_prepare($conn, $q);
  mysqli_stmt_bind_param($st, "iss", $user_id, $start, $endExclusive);
  mysqli_stmt_execute($st);
  $expandWeeks[$wk] = (float)(mysqli_fetch_assoc(mysqli_stmt_get_result($st))['total'] ?? 0);
  mysqli_stmt_close($st);
}

/* dataset sesuai tab + teks bilingual */
if ($tab === 'income'){
  $titleTab = __('tab_income');               // Income / Pemasukan
  foreach ($weekRanges as $wk => $_) $series[] = $incomeWeeks[$wk] ?? 0;
  $totalTabThisMonth = $incomeMonth;
} else {
  $titleTab = __('tab_expand');               // Expense / Pengeluaran
  foreach ($weekRanges as $wk => $_) $series[] = $expandWeeks[$wk] ?? 0;
  $totalTabThisMonth = $expandMonth;
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(__('html_lang')) ?>">
<head>
  <meta charset="UTF-8" />
  <title><?= htmlspecialchars(__('statistic_title')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

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
      font-weight:900;
    }
    .pill-tabs a.active{ background:#008b85; }

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
      font-weight:900;
    }
    .mini-summary .val{color:#008b85;font-weight:900}

    .chart-card{
      background:#fff;
      border:1px solid #d7ece6;
      border-radius:14px;
      padding:12px 12px 10px;
      margin-top:10px;
    }
    .chart-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      margin-bottom:10px;
    }
    .chart-head .left{
      display:flex; align-items:center; gap:10px;
      color:#008b85; font-weight:900; font-size:12px;
    }
    .chart-head .ico{
      width:32px;height:32px;border-radius:10px;
      background:#e6fffb;
      display:flex;align-items:center;justify-content:center;
      color:#008b85;
    }
    .chart-head .right{
      display:flex;align-items:center;gap:8px;
      font-size:12px;font-weight:900;color:#223;
    }
    .chart-wrap{height:270px;}
  </style>
</head>

<body>
<div class="container">

  <h3 class="dashboard-title"><?= htmlspecialchars(__('statistic_title')) ?></h3>

  <!-- BALANCE CARD -->
  <div class="balance-card">
    <div class="balance-header">
      <span><?= htmlspecialchars(__('total_balance')) ?></span>

      <form method="GET" class="month-select">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
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

  <!-- TABS -->
  <div class="pill-tabs">
    <a class="<?php echo $tab==='income'?'active':''; ?>"
       href="<?php echo htmlspecialchars($SELF); ?>?tab=income&period=<?php echo htmlspecialchars($periodStr); ?>">
      <?= htmlspecialchars(__('tab_income')) ?>
    </a>

    <a class="<?php echo $tab==='expand'?'active':''; ?>"
       href="<?php echo htmlspecialchars($SELF); ?>?tab=expand&period=<?php echo htmlspecialchars($periodStr); ?>">
      <?= htmlspecialchars(__('tab_expand')) ?>
    </a>
  </div>

  <!-- SUMMARY -->
  <div class="mini-summary">
    <div>
      <?= htmlspecialchars($titleTab); ?> - <?php echo htmlspecialchars(date("F Y", mktime(0,0,0,$monthInt,1,$yearInt))); ?>
    </div>
    <div class="val"><?php echo formatRp($totalTabThisMonth); ?></div>
  </div>

  <!-- CHART -->
  <div class="chart-card">
    <div class="chart-head">
      <div class="left">
        <div class="ico"><i class="fa-solid fa-chart-line"></i></div>
        <div><?= htmlspecialchars($titleTab); ?> <?= htmlspecialchars(__('per_week')) ?></div>
      </div>
      <div class="right">
        <span><?php echo htmlspecialchars(date("M", mktime(0,0,0,$monthInt,1,$yearInt))); ?></span>
        <i class="fa-regular fa-calendar" style="color:#008b85"></i>
      </div>
    </div>

    <div class="chart-wrap">
      <canvas id="lineChart"></canvas>
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

  const labels = <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>;
  const dataSeries = <?php echo json_encode($series, JSON_UNESCAPED_UNICODE); ?>;

  function formatRpJs(n){
    const v = Number(n || 0);
    try{
      return 'Rp' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(v);
    }catch(e){
      return 'Rp' + String(v);
    }
  }

  const ctx = document.getElementById('lineChart');
  const maxVal = Math.max(...dataSeries.map(v => Number(v || 0)), 0);
  const suggestedMax = (maxVal > 0) ? (maxVal * 1.15) : 100000;

  new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Total',
        data: dataSeries,
        tension: 0.35,
        fill: false,
        pointRadius: 4,
        borderWidth: 3
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (c) => formatRpJs(c.raw) } }
      },
      scales: {
        x: { grid: { display: false } },
        y: {
          beginAtZero: true,
          suggestedMax: suggestedMax,
          grid: { drawBorder: false },
          ticks: { callback: (v) => formatRpJs(v) }
        }
      }
    }
  });
</script>

</body>
</html>

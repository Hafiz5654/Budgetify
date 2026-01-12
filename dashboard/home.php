<?php
session_start();
include "../db.php";
include "includes/i18n.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

/* =====================
   PERIOD YYYY-MM
===================== */
if (isset($_GET['period']) && preg_match('/^\d{4}-\d{2}$/', $_GET['period'])) {
    [$year, $month] = explode('-', $_GET['period']);
    if (!checkdate((int)$month, 1, (int)$year)) {
        $month = date('m');
        $year  = date('Y');
    }
} else {
    $month = date('m');
    $year  = date('Y');
}

$monthInt = (int)$month;
$yearInt  = (int)$year;

/* =====================
   TAB ACTIVE (income/expand/monthly_expense)
===================== */
$tab = $_GET['tab'] ?? 'income';
$valid_tabs = ['income','expand','monthly_expense'];
if (!in_array($tab, $valid_tabs, true)) $tab = 'income';

/* =====================
   TOTALS
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
             WHERE user_id=? AND type IN ('expand','monthly_expense')
               AND MONTH(transaction_date)=? AND YEAR(transaction_date)=?";
$stmt = mysqli_prepare($conn, $qExpense);
mysqli_stmt_bind_param($stmt, "iii", $user_id, $monthInt, $yearInt);
mysqli_stmt_execute($stmt);
$expense = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0;
mysqli_stmt_close($stmt);

$balance = $income - $expense;

/* =====================
   LIST PER TAB
===================== */
$qTrans = "
    SELECT 
        t.transaction_id,
        t.amount,
        t.transaction_date,
        t.transaction_time,
        t.note,
        t.type,
        c.category_name
    FROM transactions t
    JOIN categories c ON t.category_id = c.category_id
    WHERE t.user_id = ?
      AND MONTH(t.transaction_date) = ?
      AND YEAR(t.transaction_date) = ?
";

if ($tab === 'income') {
    $qTrans .= " AND t.type='income' ";
} elseif ($tab === 'expand') {
    $qTrans .= " AND t.type='expand' ";
} else {
    $qTrans .= " AND t.type='monthly_expense' ";
}

$qTrans .= " ORDER BY t.transaction_date DESC, t.transaction_time DESC";

$stmt = mysqli_prepare($conn, $qTrans);
mysqli_stmt_bind_param($stmt, "iii", $user_id, $monthInt, $yearInt);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$hasData = (mysqli_num_rows($res) > 0);

function formatRp($n){
    return 'Rp' . number_format((float)$n, 0, ',', '.');
}
function slugIcon($name){
    $s = strtolower(trim((string)$name));
    $s = preg_replace('/[^a-z0-9]+/','_', $s);
    return trim($s,'_');
}

/* =====================
   ✅ ICON PATH (samain dengan add_transaction.php)
===================== */
function categoryIcon($categoryName, $transactionType){
    $name = strtolower(trim((string)$categoryName));

    // INCOME
    if ($transactionType === 'income') {
        $map = [
            'salary'        => 'income_salary.png',
            'business'      => 'income_business.png',
            'interest'      => 'income_interest.png',
            'extra income'  => 'income_extraincome.png',
            'invest'        => 'income_invest.png',
            'investment'    => 'income_invest.png',
            'other'         => 'other.png',
        ];
        return 'assets/Income/' . ($map[$name] ?? 'other.png');
    }

    // EXPAND + MONTHLY_EXPENSE (pakai folder Expanse)
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
   SUMMARY per tab (tanpa include db ulang)
===================== */
$totalExpand  = 0;
$totalMonthly = 0;

if ($tab === 'expand') {
    $qs="SELECT COALESCE(SUM(amount),0) AS total
         FROM transactions
         WHERE user_id=? AND type='expand'
         AND MONTH(transaction_date)=? AND YEAR(transaction_date)=?";
    $st=mysqli_prepare($conn,$qs);
    mysqli_stmt_bind_param($st,"iii",$user_id,$monthInt,$yearInt);
    mysqli_stmt_execute($st);
    $totalExpand=mysqli_fetch_assoc(mysqli_stmt_get_result($st))['total'] ?? 0;
    mysqli_stmt_close($st);
}

if ($tab === 'monthly_expense') {
    $qs="SELECT COALESCE(SUM(amount),0) AS total
         FROM transactions
         WHERE user_id=? AND type='monthly_expense'
         AND MONTH(transaction_date)=? AND YEAR(transaction_date)=?";
    $st=mysqli_prepare($conn,$qs);
    mysqli_stmt_bind_param($st,"iii",$user_id,$monthInt,$yearInt);
    mysqli_stmt_execute($st);
    $totalMonthly=mysqli_fetch_assoc(mysqli_stmt_get_result($st))['total'] ?? 0;
    mysqli_stmt_close($st);
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(__('html_lang')); ?>">
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars(__('dashboard_title')); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

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
    .mini-summary .val-red{color:#ff3b3b;font-weight:800}
    .mini-summary .val-green{color:#008b85;font-weight:800}

    .date-title{
      margin:12px 0 6px;
      color:#008b85;
      font-weight:700;
      font-size:12px;
    }

    .tx-row{
      background:#fff;
      border:1px solid #d7ece6;
      border-radius:12px;
      padding:10px 10px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:10px;
    }
    .tx-left{display:flex;gap:10px;align-items:center}
    .tx-ico{
      width:44px;height:44px;border-radius:12px;
      display:flex;align-items:center;justify-content:center;
      background:#e6fffb;
      overflow:hidden;
      flex:0 0 auto;
    }
    .tx-ico img{width:26px;height:26px;object-fit:contain}
    .tx-info strong{display:block;font-size:12px}
    .tx-info small{display:block;font-size:10px;color:#7a8a84;margin-top:2px}
    .tx-right{text-align:right}
    .tx-right .amt-red{color:#ff3b3b;font-weight:800;font-size:12px}
    .tx-right .amt-green{color:#008b85;font-weight:800;font-size:12px}

    .income-row{
      background:#fff;
      border:1px solid #d7ece6;
      border-radius:14px;
      padding:12px 12px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:12px;
    }
    .income-left{display:flex;gap:12px;align-items:center}
    .income-ico{
      width:44px;height:44px;border-radius:12px;
      display:flex;align-items:center;justify-content:center;
      background:#e6fffb;
      overflow:hidden;
      flex:0 0 auto;
    }
    .income-ico img{width:26px;height:26px;object-fit:contain}
    .income-info strong{display:block;font-size:12px;font-weight:700}
    .income-info small{display:block;font-size:10px;color:#7a8a84;margin-top:2px}
    .income-right{text-align:right}
    .income-right .amt{color:#008b85;font-weight:800;font-size:12px}
    .income-right .time{margin-top:2px;font-size:10px;color:#7a8a84;font-weight:600}
  </style>
</head>
<body>

<div class="container">

  <h3 class="dashboard-title"><?php echo htmlspecialchars(__('dashboard_title')); ?></h3>

  <!-- BALANCE CARD -->
  <div class="balance-card">
    <div class="balance-header">
      <span><?php echo htmlspecialchars(__('total_balance')); ?></span>

      <form method="GET" class="month-select">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
        <select name="period" onchange="this.form.submit()">
          <?php
          for ($y = 2024; $y <= 2030; $y++) {
              for ($m = 1; $m <= 12; $m++) {
                  $value = sprintf("%04d-%02d", $y, $m);
                  $label = date("M Y", mktime(0, 0, 0, $m, 1, $y));
                  $selected = ($yearInt === $y && $monthInt === $m) ? "selected" : "";
                  echo "<option value='$value' $selected>" . htmlspecialchars($label) . "</option>";
              }
          }
          ?>
        </select>
      </form>
    </div>

    <div class="balance-value">
      Rp <span id="balanceText"><?php echo number_format($balance, 0, ',', '.'); ?></span>
      <i class="fa-solid fa-eye" id="toggleBalance"></i>
    </div>

    <a
  href="report.php?period=<?= htmlspecialchars("$year-$month") ?>"
  class="download-link"
>
  Download Report
</a>

  </div>

  <!-- PILL TABS -->
  <div class="pill-tabs">
    <a class="<?php echo $tab==='income'?'active':''; ?>"
       href="home.php?tab=income&period=<?php echo htmlspecialchars("$year-$month"); ?>">
      <?php echo htmlspecialchars(__('tab_income')); ?>
    </a>

    <a class="<?php echo $tab==='expand'?'active':''; ?>"
       href="home.php?tab=expand&period=<?php echo htmlspecialchars("$year-$month"); ?>">
      <?php echo htmlspecialchars(__('tab_expand')); ?>
    </a>

    <a class="<?php echo $tab==='monthly_expense'?'active':''; ?>"
       href="monthly.php?period=<?php echo htmlspecialchars("$year-$month"); ?>">
      <?php echo htmlspecialchars(__('tab_monthly_expense')); ?>
    </a>
  </div>

  <?php if (!$hasData): ?>

    <div class="empty">
      <img src="assets/img/dashboard.png" class="empty-img" alt="Dashboard">
      <p><?php echo htmlspecialchars(__('empty_state')); ?></p>
    </div>

  <?php else: ?>

    <?php if ($tab === 'income'): ?>
      <div class="mini-summary">
        <div><?php echo htmlspecialchars(__('total_income')); ?></div>
        <div class="val-green"><?php echo formatRp($income); ?></div>
      </div>
    <?php elseif ($tab === 'expand'): ?>
      <div class="mini-summary">
        <div><?php echo htmlspecialchars(__('total_expenditure')); ?></div>
        <div class="val-red"><?php echo formatRp($totalExpand); ?></div>
      </div>
    <?php else: ?>
      <div class="mini-summary">
        <div><?php echo htmlspecialchars(__('total_monthly_expense')); ?></div>
        <div class="val-red"><?php echo formatRp($totalMonthly); ?></div>
      </div>
    <?php endif; ?>

    <?php
      $currentDate = null;
      while ($row = mysqli_fetch_assoc($res)) :
        $dateKey = $row['transaction_date'];
        if ($currentDate !== $dateKey) {
            $currentDate = $dateKey;
            echo '<div class="date-title">'.htmlspecialchars(date('D, d F', strtotime($currentDate))).'</div>';
        }

        $timeLabel = date('H:i', strtotime($row['transaction_time']));
        $catName = (string)($row['category_name'] ?? '');
        $txType  = (string)($row['type'] ?? '');

        // ✅ icon same source as add_transaction:
        // - income => assets/Income/...
        // - expand/monthly => assets/Expanse/...
        $icon = categoryIcon($catName, ($txType === 'income' ? 'income' : 'expand'));
        $fallback = ($txType === 'income') ? 'assets/Income/other.png' : 'assets/Expanse/espanse_bills.png';

        $isIncome = ($txType === 'income');
    ?>

      <?php if ($tab === 'expand'): ?>
        <div class="tx-row">
          <div class="tx-left">
            <div class="tx-ico">
              <img
                src="<?php echo htmlspecialchars($icon); ?>"
                alt=""
                onerror="this.src='<?php echo htmlspecialchars($fallback); ?>';"
              >
            </div>
            <div class="tx-info">
              <strong><?php echo htmlspecialchars($catName.": ".$timeLabel); ?></strong>
              <small><?php echo htmlspecialchars($row['note']); ?></small>
            </div>
          </div>
          <div class="tx-right">
            <div class="amt-red">- <?php echo formatRp($row['amount']); ?></div>
          </div>
        </div>

      <?php elseif ($tab === 'income'): ?>
        <div class="income-row">
          <div class="income-left">
            <div class="income-ico">
              <img
                src="<?php echo htmlspecialchars($icon); ?>"
                alt=""
                onerror="this.src='<?php echo htmlspecialchars($fallback); ?>';"
              >
            </div>
            <div class="income-info">
              <strong><?php echo htmlspecialchars($catName); ?></strong>
              <small><?php echo htmlspecialchars($row['note']); ?></small>
            </div>
          </div>
          <div class="income-right">
            <div class="amt">+ <?php echo formatRp($row['amount']); ?></div>
            <div class="time"><?php echo htmlspecialchars($timeLabel); ?></div>
          </div>
        </div>

      <?php else: ?>
        <!-- monthly_expense list: samakan juga icon-nya biar konsisten -->
        <div class="transaction-card">
          <div class="left">
            <div class="icon <?php echo $isIncome ? 'income' : 'expense'; ?>">
              <img
                src="<?php echo htmlspecialchars($icon); ?>"
                alt=""
                style="width:26px;height:26px;object-fit:contain;"
                onerror="this.src='<?php echo htmlspecialchars($fallback); ?>';"
              >
            </div>
            <div class="info">
              <strong><?php echo htmlspecialchars($catName); ?></strong><br>
              <small><?php echo htmlspecialchars($row['note']); ?></small>
            </div>
          </div>

          <div class="right">
            <span class="amount <?php echo $isIncome ? 'income' : 'expense'; ?>">
              <?php echo $isIncome ? '+' : '-'; ?> <?php echo formatRp($row['amount']); ?>
            </span><br>
            <small><?php echo htmlspecialchars($timeLabel); ?></small>
          </div>
        </div>
      <?php endif; ?>

    <?php endwhile; ?>

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
</script>

</body>
</html>
<?php
// close list statement & connection at the end
if (isset($stmt) && $stmt) mysqli_stmt_close($stmt);
if (isset($conn) && $conn) mysqli_close($conn);
?>

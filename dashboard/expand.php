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

$type = 'expand';

$qSum = "SELECT COALESCE(SUM(amount),0) AS total
         FROM transactions
         WHERE user_id=? AND type=? AND MONTH(transaction_date)=? AND YEAR(transaction_date)=?";
$stmt = mysqli_prepare($conn, $qSum);
mysqli_stmt_bind_param($stmt, "isii", $user_id, $type, $monthInt, $yearInt);
mysqli_stmt_execute($stmt);
$total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0;
mysqli_stmt_close($stmt);

$qTrans = "
  SELECT t.transaction_id,t.amount,t.transaction_date,t.transaction_time,t.note,t.type,
         c.category_name
  FROM transactions t
  JOIN categories c ON t.category_id=c.category_id
  WHERE t.user_id=? AND t.type=? AND MONTH(t.transaction_date)=? AND YEAR(t.transaction_date)=?
  ORDER BY t.transaction_date DESC, t.transaction_time DESC
";
$stmtT = mysqli_prepare($conn, $qTrans);
mysqli_stmt_bind_param($stmtT, "isii", $user_id, $type, $monthInt, $yearInt);
mysqli_stmt_execute($stmtT);
$res = mysqli_stmt_get_result($stmtT);

function formatRp($n){ return 'Rp' . number_format((float)$n,0,',','.'); }
function slugIcon($name){
  $slug = strtolower(trim((string)$name));
  $slug = preg_replace('/[^a-z0-9]+/','_', $slug);
  return trim($slug,'_');
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(__('html_lang')) ?>">
<head>
  <meta charset="UTF-8" />
  <title><?= htmlspecialchars(__('expand_title')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
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
    .mini-summary .val{color:#ff3b3b;font-weight:700}
    .date-title{
      margin:10px 0 6px;
      color:#008b85;
      font-weight:600;
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
    }
    .tx-ico img{width:26px;height:26px;object-fit:contain}
    .tx-info strong{display:block;font-size:12px}
    .tx-info small{display:block;font-size:10px;color:#7a8a84;margin-top:2px}
    .tx-right{text-align:right}
    .tx-right .amt{color:#ff3b3b;font-weight:800;font-size:12px}
    .tx-right .lim{font-size:10px;color:#7a8a84;margin-top:2px}
  </style>
</head>
<body>
<div class="container">

  <h3 class="dashboard-title"><?= htmlspecialchars(__('dashboard_title')) ?></h3>

  <!-- Card atas -->
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
              $selected=((int)$yearInt===$y && (int)$monthInt===$m)?"selected":"";
              echo "<option value='$value' $selected>".htmlspecialchars($label)."</option>";
            }
          }
          ?>
        </select>
      </form>
    </div>
    <div class="balance-value">
      Rp <span><?php echo number_format((float)$total,0,',','.'); ?></span>
    </div>
    <a class="download-link" href="#"><?= htmlspecialchars(__('download_report')) ?></a>
  </div>

  <div class="tab-group">
    <a href="income.php?period=<?php echo htmlspecialchars("$yearInt-$monthInt");?>" class="tab"><?= htmlspecialchars(__('tab_income')) ?></a>
    <a href="expand.php?period=<?php echo htmlspecialchars("$yearInt-$monthInt");?>" class="tab active"><?= htmlspecialchars(__('tab_expand')) ?></a>
    <a href="monthly.php?period=<?php echo htmlspecialchars("$yearInt-$monthInt");?>" class="tab"><?= htmlspecialchars(__('tab_monthly_expense')) ?></a>
  </div>

  <div class="mini-summary">
    <div><?= htmlspecialchars(__('total_expenditure')) ?></div>
    <div class="val"><?php echo formatRp($total); ?></div>
  </div>

  <?php if (mysqli_num_rows($res) === 0): ?>
    <div class="empty">
      <img src="assets/img/dashboard.png" class="empty-img" alt="Dashboard">
      <p><?= htmlspecialchars(__('no_expense_yet')) ?></p>
    </div>
  <?php else: ?>
    <?php
      $cur=null;
      while($row=mysqli_fetch_assoc($res)):
        if($cur!==$row['transaction_date']){
          $cur=$row['transaction_date'];
          echo '<div class="date-title">'.htmlspecialchars(date('D, d F',strtotime($cur))).'</div>';
        }

        $slug = slugIcon($row['category_name'] ?? '');
        $timeLabel = date('H:i', strtotime($row['transaction_time']));
    ?>
      <div class="tx-row">
        <div class="tx-left">
          <div class="tx-ico">
            <img src="assets/icons/<?php echo htmlspecialchars($slug); ?>.png" alt="">
          </div>
          <div class="tx-info">
            <strong><?php echo htmlspecialchars($row['category_name'].": ".$timeLabel); ?></strong>
            <small><?php echo htmlspecialchars($row['note']); ?></small>
          </div>
        </div>
        <div class="tx-right">
          <div class="amt">- <?php echo formatRp($row['amount']); ?></div>
          <div class="lim"><?= htmlspecialchars(__('limit_label')) ?>: -</div>
        </div>
      </div>
    <?php endwhile; ?>
  <?php endif; ?>

</div>

<?php include "partials/bottom_nav.php"; ?>

</body>
</html>
<?php
if (isset($stmtT) && $stmtT) mysqli_stmt_close($stmtT);
if (isset($conn) && $conn) mysqli_close($conn);
?>

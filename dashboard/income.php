<?php
session_start();
include "../db.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
$user_id = (int)$_SESSION['user_id'];

if (isset($_GET['period']) && preg_match('/^\d{4}-\d{2}$/', $_GET['period'])) {
  [$year, $month] = explode('-', $_GET['period']);
  if (!checkdate((int)$month, 1, (int)$year)) { $month=date('m'); $year=date('Y'); }
} else { $month=date('m'); $year=date('Y'); }

$type = 'income';

$qSum = "SELECT COALESCE(SUM(amount),0) AS total
         FROM transactions
         WHERE user_id=? AND type=? AND MONTH(transaction_date)=? AND YEAR(transaction_date)=?";
$stmt = mysqli_prepare($conn, $qSum);
mysqli_stmt_bind_param($stmt, "isii", $user_id, $type, $month, $year);
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
mysqli_stmt_bind_param($stmtT, "isii", $user_id, $type, $month, $year);
mysqli_stmt_execute($stmtT);
$res = mysqli_stmt_get_result($stmtT);

function formatRp($n){ return 'Rp' . number_format((float)$n,0,',','.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Income</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="container">

  <h3 class="dashboard-title">Smart Budget - Expense Tracker</h3>

  <div class="balance-card">
    <div class="balance-header">
      <span>Total Balance</span>
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
      Rp <span><?php echo number_format((float)$total,0,',','.'); ?></span>
    </div>
    <a class="download-link" href="#">Download Report</a>
  </div>

  <div class="tab-group">
    <a href="income.php?period=<?php echo htmlspecialchars("$year-$month");?>" class="tab active">Income</a>
    <a href="expand.php?period=<?php echo htmlspecialchars("$year-$month");?>" class="tab">Expand</a>
    <a href="monthly.php?period=<?php echo htmlspecialchars("$year-$month");?>" class="tab">Monthly Expense</a>
  </div>

  <?php if (mysqli_num_rows($res) === 0): ?>
    <div class="empty">
      <img src="assets/img/dashboard.png" class="empty-img" alt="Dashboard">
      <p>No income yet</p>
    </div>
  <?php else: ?>
    <div class="transaction-list">
      <?php
      $cur=null;
      while($row=mysqli_fetch_assoc($res)):
        if($cur!==$row['transaction_date']){
          $cur=$row['transaction_date'];
          echo '<div style="margin:10px 0 6px;color:#008b85;font-weight:600;font-size:12px;">'
              .htmlspecialchars(date('D, d F',strtotime($cur))).'</div>';
        }
      ?>
      <div class="transaction-card">
        <div class="left">
          <div class="icon income"><i class="fa-solid fa-wallet"></i></div>
          <div class="info">
            <strong><?php echo htmlspecialchars($row['category_name']); ?></strong><br>
            <small><?php echo htmlspecialchars($row['note']); ?></small>
          </div>
        </div>
        <div class="right">
          <span class="amount income">+ <?php echo formatRp($row['amount']); ?></span><br>
          <small><?php echo date('H:i', strtotime($row['transaction_time'])); ?></small>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>

</div>

<div class="bottom-nav">
  <div class="nav-group left">
    <a href="home.php" class="nav-item"><i class="fa-solid fa-house"></i><span>Home</span></a>
    <a href="income.php" class="nav-item stat active"><i class="fa-solid fa-chart-line"></i><span>Statistic</span></a>
  </div>
  <div class="nav-plus"><a href="add_transaction.php"><i class="fa-solid fa-plus"></i></a></div>
  <div class="nav-group right">
    <a href="budget.php" class="nav-item budget"><i class="fa-solid fa-wallet"></i><span>Budget</span></a>
    <a href="setting.php" class="nav-item"><i class="fa-solid fa-gear"></i><span>Setting</span></a>
  </div>
</div>
</body>
</html>
<?php mysqli_stmt_close($stmtT); ?>

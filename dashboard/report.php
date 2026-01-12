<?php
session_start();
include "../db.php";
include "includes/i18n.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
$user_id = (int)$_SESSION['user_id'];

/* =====================
   PERIOD YYYY-MM
===================== */
$period = $_GET['period'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $period)) $period = date('Y-m');

[$year, $month] = explode('-', $period);
$year = (int)$year;
$month = (int)$month;
if (!checkdate($month, 1, $year)) {
  $year = (int)date('Y');
  $month = (int)date('m');
  $period = sprintf("%04d-%02d", $year, $month);
}

$export = $_GET['export'] ?? ''; // pdf | excel | ''
$print  = isset($_GET['print']) ? 1 : 0;

/* =====================
   HELPERS
===================== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rp($n){ return 'Rp' . number_format((float)$n, 0, ',', '.'); }

/* =====================
   TOTALS
   NOTE: monthly_expense TIDAK mengurangi saldo,
         saldo real expense hanya expand (seperti monthly.php kamu)
===================== */
$qIncome = "SELECT COALESCE(SUM(amount),0) AS total
            FROM transactions
            WHERE user_id=? AND type='income'
              AND MONTH(transaction_date)=? AND YEAR(transaction_date)=?";
$st = mysqli_prepare($conn, $qIncome);
mysqli_stmt_bind_param($st, "iii", $user_id, $month, $year);
mysqli_stmt_execute($st);
$income = (float)(mysqli_fetch_assoc(mysqli_stmt_get_result($st))['total'] ?? 0);
mysqli_stmt_close($st);

$qExpense = "SELECT COALESCE(SUM(amount),0) AS total
             FROM transactions
             WHERE user_id=? AND type='expand'
               AND MONTH(transaction_date)=? AND YEAR(transaction_date)=?";
$st = mysqli_prepare($conn, $qExpense);
mysqli_stmt_bind_param($st, "iii", $user_id, $month, $year);
mysqli_stmt_execute($st);
$expense = (float)(mysqli_fetch_assoc(mysqli_stmt_get_result($st))['total'] ?? 0);
mysqli_stmt_close($st);

$balance = $income - $expense;

$qEstimate = "SELECT COALESCE(SUM(amount_limit),0) AS total
              FROM budgets
              WHERE user_id=? AND month=? AND year=?";
$st = mysqli_prepare($conn, $qEstimate);
mysqli_stmt_bind_param($st, "iii", $user_id, $month, $year);
mysqli_stmt_execute($st);
$estimate = (float)(mysqli_fetch_assoc(mysqli_stmt_get_result($st))['total'] ?? 0);
mysqli_stmt_close($st);

/* =====================
   TRANSACTIONS (all types in selected period)
===================== */
$qTx = "
  SELECT
    t.transaction_id,
    t.transaction_date,
    t.transaction_time,
    t.type,
    t.amount,
    t.note,
    c.category_name
  FROM transactions t
  JOIN categories c ON c.category_id = t.category_id
  WHERE t.user_id=?
    AND MONTH(t.transaction_date)=?
    AND YEAR(t.transaction_date)=?
  ORDER BY t.transaction_date ASC, t.transaction_time ASC
";
$st = mysqli_prepare($conn, $qTx);
mysqli_stmt_bind_param($st, "iii", $user_id, $month, $year);
mysqli_stmt_execute($st);
$res = mysqli_stmt_get_result($st);
$transactions = [];
while($r = mysqli_fetch_assoc($res)) $transactions[] = $r;
mysqli_stmt_close($st);

/* =====================
   REPORT PER KATEGORI (income / expand)
===================== */
$qByCat = "
  SELECT
    t.type,
    c.category_name,
    COALESCE(SUM(t.amount),0) AS total
  FROM transactions t
  JOIN categories c ON c.category_id=t.category_id
  WHERE t.user_id=?
    AND MONTH(t.transaction_date)=?
    AND YEAR(t.transaction_date)=?
  GROUP BY t.type, c.category_name
  ORDER BY t.type ASC, total DESC
";
$st = mysqli_prepare($conn, $qByCat);
mysqli_stmt_bind_param($st, "iii", $user_id, $month, $year);
mysqli_stmt_execute($st);
$res2 = mysqli_stmt_get_result($st);
$byCategory = [];
while($r = mysqli_fetch_assoc($res2)) $byCategory[] = $r;
mysqli_stmt_close($st);

/* =====================
   BUDGETS (limit vs spent from expand)
===================== */
$qBud = "
  SELECT
    c.category_name,
    b.amount_limit,
    COALESCE(SUM(t.amount),0) AS spent
  FROM budgets b
  JOIN categories c ON c.category_id=b.category_id
  LEFT JOIN transactions t
    ON t.user_id=b.user_id
   AND t.category_id=b.category_id
   AND t.type='expand'
   AND MONTH(t.transaction_date)=b.month
   AND YEAR(t.transaction_date)=b.year
  WHERE b.user_id=? AND b.month=? AND b.year=?
  GROUP BY c.category_name, b.amount_limit
  ORDER BY c.category_name ASC
";
$st = mysqli_prepare($conn, $qBud);
mysqli_stmt_bind_param($st, "iii", $user_id, $month, $year);
mysqli_stmt_execute($st);
$res3 = mysqli_stmt_get_result($st);
$budgets = [];
while($r = mysqli_fetch_assoc($res3)) $budgets[] = $r;
mysqli_stmt_close($st);

/* =====================
   EXPORT HANDLERS (PDF / EXCEL)
===================== */
function renderReportHtml($period, $income, $expense, $balance, $estimate, $transactions, $byCategory, $budgets, $isForExport = false){
  $title = "Budgetify Report - " . $period;
  $backUrl = "report.php?period=" . $period;

  ob_start();
  ?>
  <!DOCTYPE html>
  <html lang="id">
  <head>
    <meta charset="UTF-8">
    <title><?= h($title) ?></title>
    <style>
      *{box-sizing:border-box;font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
      body{margin:0;background:#f3faf7;color:#1d1d1d}
      .wrap{max-width:900px;margin:0 auto;padding:18px 16px 26px}

      .topbar{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:14px}
      .title{font-size:16px;font-weight:800;color:#0b7d73}
      .period{font-size:12px;font-weight:700;color:#6c7a76}

      .top-actions{display:flex;gap:8px;align-items:center}
      .tbtn{
        border:1px solid #d7ece6;
        background:#fff;
        color:#0b7d73;
        padding:9px 12px;
        border-radius:999px;
        font-weight:900;
        font-size:12px;
        text-decoration:none;
        cursor:pointer;
      }
      .tbtn.primary{background:#008b85;color:#fff;border-color:#008b85}

      .cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:12px 0 14px}
      .card{
        background:#fff;border:1px solid #d7ece6;border-radius:16px;padding:12px 12px;
        box-shadow:0 6px 18px rgba(13, 117, 104, .06);
      }
      .card .k{font-size:12px;font-weight:800;color:#6c7a76}
      .card .v{margin-top:6px;font-size:16px;font-weight:900}
      .v.green{color:#008b85}
      .v.red{color:#ff3b3b}

      .section{
        background:#fff;border:1px solid #d7ece6;border-radius:16px;padding:12px 12px;margin-top:12px;
        box-shadow:0 6px 18px rgba(13, 117, 104, .06);
      }
      .section h3{margin:0 0 10px;font-size:13px;font-weight:900;color:#0b7d73}

      table{width:100%;border-collapse:collapse}
      th,td{padding:10px 8px;border-bottom:1px solid #e8f3ef;text-align:left;font-size:12px;vertical-align:top}
      th{color:#0b7d73;font-weight:900;background:#f6fffb}
      .muted{color:#6c7a76;font-weight:700}
      .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:800}
      .pill.income{background:#e9fbf4;color:#008b85}
      .pill.expand{background:#fff1f1;color:#ff3b3b}
      .pill.monthly{background:#eef6ff;color:#2b5cff}
      .right{text-align:right}
      .small{font-size:11px}
      .warn{color:#ff3b3b;font-weight:900}
      .foot{margin-top:10px;font-size:11px;color:#6c7a76;font-weight:700}

      <?php if($isForExport): ?>
        body{background:#fff}
        .wrap{max-width:100%;padding:0}
      <?php endif; ?>

      @media print{
        body{background:#fff}
        .wrap{max-width:100%;padding:0}
        .top-actions{display:none !important;}
      }
    </style>
  </head>
  <body>
  <div class="wrap">
    <div class="topbar">
      <div>
        <div class="title"><?= h($title) ?></div>
        <div class="period">Period: <?= h($period) ?></div>
      </div>

      <?php if(!$isForExport): ?>
        <div class="top-actions">
          <a class="tbtn" href="<?= h($backUrl) ?>">← Back</a>
          <button class="tbtn primary" type="button" onclick="window.print()">Print</button>
        </div>
      <?php endif; ?>
    </div>

    <div class="cards">
      <div class="card">
        <div class="k">Total Income</div>
        <div class="v green"><?= rp($income) ?></div>
      </div>
      <div class="card">
        <div class="k">Total Expense (Expand)</div>
        <div class="v red"><?= rp($expense) ?></div>
      </div>
      <div class="card">
        <div class="k">Balance</div>
        <div class="v <?= ($balance>=0?'green':'red') ?>"><?= rp($balance) ?></div>
      </div>
      <div class="card">
        <div class="k">Total Estimate (Budgets)</div>
        <div class="v green"><?= rp($estimate) ?></div>
      </div>
    </div>

    <div class="section">
      <h3>Report per Kategori</h3>
      <table>
        <thead>
          <tr>
            <th style="width:120px">Type</th>
            <th>Category</th>
            <th class="right">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($byCategory)): ?>
            <tr><td colspan="3" class="muted">No data.</td></tr>
          <?php else: foreach($byCategory as $r):
            $t = (string)$r['type'];
            $cls = ($t==='income')?'income':(($t==='expand')?'expand':'monthly');
          ?>
            <tr>
              <td><span class="pill <?= $cls ?>"><?= h($t) ?></span></td>
              <td><?= h($r['category_name']) ?></td>
              <td class="right"><?= rp($r['total']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="section">
      <h3>Budgets (Limit vs Spent)</h3>
      <table>
        <thead>
          <tr>
            <th>Category</th>
            <th class="right">Limit</th>
            <th class="right">Spent</th>
            <th class="right">Remaining</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($budgets)): ?>
            <tr><td colspan="4" class="muted">No budgets set for this period.</td></tr>
          <?php else: foreach($budgets as $b):
            $limit = (float)$b['amount_limit'];
            $spent = (float)$b['spent'];
            $rem = max(0, $limit-$spent);
            $over = ($limit>0 && $spent>$limit);
          ?>
            <tr>
              <td><?= h($b['category_name']) ?></td>
              <td class="right"><?= rp($limit) ?></td>
              <td class="right"><?= rp($spent) ?></td>
              <td class="right">
                <?php if($over): ?>
                  <span class="warn">Over limit!</span>
                <?php else: ?>
                  <?= rp($rem) ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
      <div class="foot">Spent dihitung dari transaksi type <b>expand</b>.</div>
    </div>

    <div class="section">
      <h3>All Transactions (Data Asli Input)</h3>
      <table>
        <thead>
          <tr>
            <th style="width:110px">Date</th>
            <th style="width:70px">Time</th>
            <th style="width:120px">Type</th>
            <th>Category</th>
            <th>Note</th>
            <th class="right" style="width:130px">Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($transactions)): ?>
            <tr><td colspan="6" class="muted">No transactions.</td></tr>
          <?php else: foreach($transactions as $t):
            $type = (string)$t['type'];
            $cls = ($type==='income')?'income':(($type==='expand')?'expand':'monthly');
          ?>
            <tr>
              <td><?= h($t['transaction_date']) ?></td>
              <td><?= h(substr((string)$t['transaction_time'],0,5)) ?></td>
              <td><span class="pill <?= $cls ?>"><?= h($type) ?></span></td>
              <td><?= h($t['category_name']) ?></td>
              <td class="small"><?= h($t['note']) ?></td>
              <td class="right"><?= rp($t['amount']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  </div>
  </body>
  </html>
  <?php
  return ob_get_clean();
}

/* ===== Export PDF ===== */
if ($export === 'pdf') {
  require_once __DIR__ . "/../vendor/autoload.php";
  $html = renderReportHtml($period, $income, $expense, $balance, $estimate, $transactions, $byCategory, $budgets, true);

  $dompdf = new Dompdf\Dompdf([
    'isRemoteEnabled' => true,
    'isHtml5ParserEnabled' => true
  ]);
  $dompdf->loadHtml($html);
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->render();

  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="Budgetify_Report_'.$period.'.pdf"');
  echo $dompdf->output();
  exit;
}

/* ===== Export Excel ===== */
if ($export === 'excel') {
  require_once __DIR__ . "/../vendor/autoload.php";

  $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();

  // Sheet 1: Summary
  $sheet = $spreadsheet->getActiveSheet();
  $sheet->setTitle('Summary');
  $sheet->fromArray([
    ['Period', $period],
    ['Total Income', $income],
    ['Total Expense (Expand)', $expense],
    ['Balance', $balance],
    ['Total Estimate (Budgets)', $estimate],
  ], NULL, 'A1');

  // Sheet 2: Per Category
  $sheet2 = $spreadsheet->createSheet();
  $sheet2->setTitle('Per Category');
  $sheet2->fromArray([['Type','Category','Total']], NULL, 'A1');
  $row = 2;
  foreach($byCategory as $r){
    $sheet2->setCellValue("A{$row}", (string)$r['type']);
    $sheet2->setCellValue("B{$row}", (string)$r['category_name']);
    $sheet2->setCellValue("C{$row}", (float)$r['total']);
    $row++;
  }

  // Sheet 3: Budgets
  $sheet3 = $spreadsheet->createSheet();
  $sheet3->setTitle('Budgets');
  $sheet3->fromArray([['Category','Limit','Spent','Remaining','Over?']], NULL, 'A1');
  $row = 2;
  foreach($budgets as $b){
    $limit = (float)$b['amount_limit'];
    $spent = (float)$b['spent'];
    $rem = max(0, $limit-$spent);
    $over = ($limit>0 && $spent>$limit) ? 'YES' : 'NO';
    $sheet3->fromArray([[ (string)$b['category_name'], $limit, $spent, $rem, $over ]], NULL, "A{$row}");
    $row++;
  }

  // Sheet 4: Transactions
  $sheet4 = $spreadsheet->createSheet();
  $sheet4->setTitle('Transactions');
  $sheet4->fromArray([['Date','Time','Type','Category','Note','Amount']], NULL, 'A1');
  $row = 2;
  foreach($transactions as $t){
    $sheet4->fromArray([[
      (string)$t['transaction_date'],
      substr((string)$t['transaction_time'],0,5),
      (string)$t['type'],
      (string)$t['category_name'],
      (string)$t['note'],
      (float)$t['amount'],
    ]], NULL, "A{$row}");
    $row++;
  }

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="Budgetify_Report_'.$period.'.xlsx"' );

  $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
  $writer->save('php://output');
  exit;
}

/* ===== Print friendly (FIX cancel print -> back normal) ===== */
if ($print) {
  echo renderReportHtml($period, $income, $expense, $balance, $estimate, $transactions, $byCategory, $budgets, false);
  ?>
  <script>
    (function(){
      const backUrl = <?= json_encode("report.php?period=".$period) ?>;

      window.focus();
      setTimeout(() => window.print(), 200);

      // Setelah print / cancel -> balik normal
      window.addEventListener("afterprint", () => {
        window.location.replace(backUrl);
      });

      // Fallback
      setTimeout(() => {
        if (document.visibilityState === "visible") {
          window.location.replace(backUrl);
        }
      }, 3000);
    })();
  </script>
  <?php
  exit;
}

/* =====================
   NORMAL VIEW (UI)
===================== */
?>
<!DOCTYPE html>
<html lang="<?= h(__('html_lang')) ?>">
<head>
  <meta charset="UTF-8">
  <title>Report</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    *{box-sizing:border-box;font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
    body{margin:0;background:#f3faf7;color:#1d1d1d}
    .wrapper{max-width:420px;margin:0 auto;padding:18px 16px 24px}

    .header{
      display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:4px;margin-bottom:10px
    }
    .header h2{font-size:16px;margin:0;font-weight:900;color:#0b7d73}
    .period{font-size:12px;font-weight:800;color:#6c7a76}

    .backbar{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 12px;}
    .backbtn{
      flex:1; min-width:110px;
      background:#fff;
      border:1px solid #d7ece6;
      color:#0b7d73;
      text-decoration:none;
      font-weight:900;
      font-size:12px;
      padding:10px 12px;
      border-radius:999px;
      display:flex;
      gap:8px;
      align-items:center;
      justify-content:center;
      cursor:pointer;
    }
    .backbtn i{color:#0b7d73}

    .actions{display:flex;gap:10px;flex-wrap:wrap;margin:8px 0 12px;}
    .btn{
      flex:1; min-width:120px;
      border:none;
      border-radius:999px;
      padding:12px 12px;
      font-weight:900;
      font-size:12px;
      cursor:pointer;
      text-decoration:none;
      text-align:center;
      display:flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      box-shadow:0 10px 24px rgba(0,0,0,.06);
    }
    .btn.primary{background:#008b85;color:#fff}
    .btn.soft{background:#e9fbf4;color:#008b85;border:1px solid #cfeee3}
    .btn.red{background:#fff1f1;color:#ff3b3b;border:1px solid #ffd7d7}

    .cards{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin:12px 0 12px}
    .card{
      background:#fff;border:1px solid #d7ece6;border-radius:16px;padding:12px;
      box-shadow:0 6px 18px rgba(13, 117, 104, .06);
    }
    .k{font-size:12px;font-weight:800;color:#6c7a76}
    .v{margin-top:6px;font-size:15px;font-weight:900}
    .v.green{color:#008b85}
    .v.red{color:#ff3b3b}

    .section{
      background:#fff;border:1px solid #d7ece6;border-radius:16px;padding:12px;margin-top:12px;
      box-shadow:0 6px 18px rgba(13, 117, 104, .06);
    }
    .section h3{margin:0 0 10px;font-size:13px;font-weight:900;color:#0b7d73}

    .row{
      display:flex;justify-content:space-between;gap:10px;
      padding:10px 0;border-bottom:1px solid #e8f3ef
    }
    .row:last-child{border-bottom:0}
    .left{min-width:0}
    .right{text-align:right;font-weight:900}
    .muted{color:#6c7a76;font-weight:700;font-size:11px;margin-top:2px}

    .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:900}
    .pill.income{background:#e9fbf4;color:#008b85}
    .pill.expand{background:#fff1f1;color:#ff3b3b}
    .pill.monthly{background:#eef6ff;color:#2b5cff}
  </style>
</head>
<body>

<div class="wrapper">

  <div class="header">
    <div>
      <h2>Report</h2>
      <div class="period">Period: <?= h($period) ?></div>
    </div>
  </div>

  <!-- Back buttons (FIX: real back button) -->
  <div class="backbar">
    <button class="backbtn" type="button" onclick="history.back()">
      <i class="fa-solid fa-arrow-left"></i> Back
    </button>
    <a class="backbtn" href="home.php">
      <i class="fa-solid fa-house"></i> Home
    </a>
  </div>

  <!-- Action buttons -->
  <div class="actions">
    <a class="btn primary" href="report.php?period=<?= h($period) ?>&print=1"><i class="fa-solid fa-print"></i> Print</a>
    <a class="btn soft" href="report.php?period=<?= h($period) ?>&export=pdf"><i class="fa-solid fa-file-pdf"></i> PDF</a>
    <a class="btn soft" href="report.php?period=<?= h($period) ?>&export=excel"><i class="fa-solid fa-file-excel"></i> Excel</a>
  </div>

  <div class="cards">
    <div class="card">
      <div class="k">Total Income</div>
      <div class="v green"><?= rp($income) ?></div>
    </div>
    <div class="card">
      <div class="k">Total Expense (Expand)</div>
      <div class="v red"><?= rp($expense) ?></div>
    </div>
    <div class="card">
      <div class="k">Balance</div>
      <div class="v <?= ($balance>=0?'green':'red') ?>"><?= rp($balance) ?></div>
    </div>
    <div class="card">
      <div class="k">Total Estimate</div>
      <div class="v green"><?= rp($estimate) ?></div>
    </div>
  </div>

  <!-- Per kategori -->
  <div class="section">
    <h3>Report per Kategori</h3>
    <?php if(empty($byCategory)): ?>
      <div class="muted">No data.</div>
    <?php else: foreach($byCategory as $r):
      $t = (string)$r['type'];
      $cls = ($t==='income')?'income':(($t==='expand')?'expand':'monthly');
    ?>
      <div class="row">
        <div class="left">
          <div><span class="pill <?= $cls ?>"><?= h($t) ?></span> <b><?= h($r['category_name']) ?></b></div>
        </div>
        <div class="right"><?= rp($r['total']) ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Budgets -->
  <div class="section">
    <h3>Budgets (Limit vs Spent)</h3>
    <?php if(empty($budgets)): ?>
      <div class="muted">No budgets set for this period.</div>
    <?php else: foreach($budgets as $b):
      $limit = (float)$b['amount_limit'];
      $spent = (float)$b['spent'];
      $rem = max(0, $limit-$spent);
      $over = ($limit>0 && $spent>$limit);
    ?>
      <div class="row">
        <div class="left">
          <div><b><?= h($b['category_name']) ?></b></div>
          <div class="muted">
            Limit <?= rp($limit) ?> • Spent <?= rp($spent) ?> • Remaining <?= $over ? 'Over limit!' : rp($rem) ?>
          </div>
        </div>
        <div class="right"><?= $over ? '<span style="color:#ff3b3b">!</span>' : '' ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Transactions -->
  <div class="section">
    <h3>All Transactions</h3>
    <?php if(empty($transactions)): ?>
      <div class="muted">No transactions.</div>
    <?php else: foreach($transactions as $t):
      $type = (string)$t['type'];
      $cls = ($type==='income')?'income':(($type==='expand')?'expand':'monthly');
      $time = substr((string)$t['transaction_time'], 0, 5);
    ?>
      <div class="row">
        <div class="left">
          <div><span class="pill <?= $cls ?>"><?= h($type) ?></span> <b><?= h($t['category_name']) ?></b></div>
          <div class="muted"><?= h($t['transaction_date']) ?> • <?= h($time) ?><?= $t['note'] ? ' • '.h($t['note']) : '' ?></div>
        </div>
        <div class="right"><?= rp($t['amount']) ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>

</div>

</body>
</html>

<?php
if (isset($conn) && $conn) mysqli_close($conn);
?>

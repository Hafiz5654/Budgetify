<?php
session_start();
include "../db.php";
include "includes/i18n.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$message = "";
$amount_error = "";

/* =====================
   TYPE dari tab
===================== */
$transaction_type = $_GET['type'] ?? 'income';
$allowedTypes = ['income','expand','monthly_expense'];
if (!in_array($transaction_type, $allowedTypes, true)) {
  $transaction_type = 'income';
}

/* =====================
   ICON PATH (income & expanse)
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

    // EXPAND + MONTHLY
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
   AMBIL KATEGORI (GLOBAL)
===================== */
$category_type_for_picker = ($transaction_type === 'monthly_expense') ? 'expand' : $transaction_type;

// list kategori pengeluaran sesuai design
$designExpandCategories = [
    'Food','Social','Traffic','Shopping',
    'Grocery','Education','Bills','Rentals',
    'Medical','Investment','Gift','Other'
];

if ($category_type_for_picker === 'expand') {

    $placeholders = implode(',', array_fill(0, count($designExpandCategories), '?'));
    $fieldPlaceholders = implode(',', array_fill(0, count($designExpandCategories), '?'));

    $queryCategory = "
        SELECT category_id, category_name, type, user_id
        FROM categories
        WHERE user_id IS NULL
          AND type = ?
          AND category_name IN ($placeholders)
        ORDER BY FIELD(category_name, $fieldPlaceholders)
    ";

    $stmtCategory = mysqli_prepare($conn, $queryCategory);
    if (!$stmtCategory) die("Error preparing category query: " . mysqli_error($conn));

    $params = array_merge([$category_type_for_picker], $designExpandCategories, $designExpandCategories);
    $types  = "s" . str_repeat("s", count($designExpandCategories)) . str_repeat("s", count($designExpandCategories));
    mysqli_stmt_bind_param($stmtCategory, $types, ...$params);

} else {

    // INCOME
    $queryCategory = "
      SELECT category_id, category_name, type, user_id
      FROM categories
      WHERE user_id IS NULL
        AND type = ?
      ORDER BY category_name ASC
    ";

    $stmtCategory = mysqli_prepare($conn, $queryCategory);
    if (!$stmtCategory) die("Error preparing category query: " . mysqli_error($conn));
    mysqli_stmt_bind_param($stmtCategory, "s", $category_type_for_picker);
}

mysqli_stmt_execute($stmtCategory);
$qCategory = mysqli_stmt_get_result($stmtCategory);

$categories = [];
while ($row = mysqli_fetch_assoc($qCategory)) {
    $categories[] = $row;
}
mysqli_stmt_close($stmtCategory);

/* =====================
   KHUSUS INCOME: urutan sesuai desain
   ✅ TANPA dummy, jadi baris bawah mulai dari kiri
===================== */
if ($category_type_for_picker === 'income' && !empty($categories)) {

  // buang "Extra" kalau ada (bukan Extra Income)
  $categories = array_values(array_filter($categories, function($c){
    $n = strtolower(trim((string)$c['category_name']));
    return $n !== 'extra';
  }));

  $order = [
    'business'     => 1,
    'extra income' => 2,
    'interest'     => 3,
    'invest'       => 4,
    'investment'   => 4,
    'salary'       => 5,
    'other'        => 6,
  ];

  usort($categories, function($a, $b) use ($order){
    $an = strtolower(trim((string)$a['category_name']));
    $bn = strtolower(trim((string)$b['category_name']));
    $ai = $order[$an] ?? 999;
    $bi = $order[$bn] ?? 999;
    if ($ai === $bi) return strcmp($an, $bn);
    return $ai <=> $bi;
  });
}

/* =====================
   SIMPAN TRANSAKSI
===================== */
if (isset($_POST['save'])) {

    $type = $_POST['transaction_type'] ?? 'income';
    if (!in_array($type, $allowedTypes, true)) {
        $message = __('err_invalid_type');
    } else {

        $amount_raw = trim($_POST['amount'] ?? '');
        $amount_clean = str_replace(['.', ',', ' '], '', $amount_raw);

        if ($amount_clean === '' || !ctype_digit($amount_clean) || (int)$amount_clean <= 0) {
            $amount_error = "Harap Masukan Nominal";
        } else {

            $amount = number_format((float)$amount_clean, 2, '.', '');
            $category_id = (int)($_POST['category_id'] ?? 0);

            $note = trim($_POST['note'] ?? '');
            if (mb_strlen($note) > 255) $note = mb_substr($note, 0, 255);

            $date = trim($_POST['transaction_date'] ?? '');
            $time = trim($_POST['transaction_time'] ?? '');

            $dateTimeString = $date . ' ' . $time;
            $dateTime = DateTime::createFromFormat('Y-m-d H:i', $dateTimeString);
            if (!$dateTime || $dateTime->format('Y-m-d H:i') !== $dateTimeString) {
                $message = __('err_invalid_datetime');
            } else {

                // monthly_expense diverifikasi sebagai 'expand'
                $verifyType = ($type === 'monthly_expense') ? 'expand' : $type;

                $queryVerifyCat = "
                    SELECT category_id
                    FROM categories
                    WHERE category_id = ?
                      AND user_id IS NULL
                      AND type = ?
                    LIMIT 1
                ";
                $stmtVerify = mysqli_prepare($conn, $queryVerifyCat);
                if (!$stmtVerify) {
                    $message = "Error preparing verification query: " . mysqli_error($conn);
                } else {
                    mysqli_stmt_bind_param($stmtVerify, "is", $category_id, $verifyType);
                    mysqli_stmt_execute($stmtVerify);
                    $resultVerify = mysqli_stmt_get_result($stmtVerify);

                    if (mysqli_num_rows($resultVerify) === 0) {
                        $message = __('err_invalid_category');
                    } else {

                        // monthly_expense -> budgets
                        if ($type === 'monthly_expense') {

                            $m = (int)date('m', strtotime($date));
                            $y = (int)date('Y', strtotime($date));

                            $qCheck = "SELECT budget_id FROM budgets WHERE user_id=? AND category_id=? AND month=? AND year=? LIMIT 1";
                            $stCheck = mysqli_prepare($conn, $qCheck);
                            mysqli_stmt_bind_param($stCheck, "iiii", $user_id, $category_id, $m, $y);
                            mysqli_stmt_execute($stCheck);
                            $ex = mysqli_fetch_assoc(mysqli_stmt_get_result($stCheck));
                            mysqli_stmt_close($stCheck);

                            if ($ex) {
                                $qUp = "UPDATE budgets SET amount_limit=? WHERE budget_id=?";
                                $stUp = mysqli_prepare($conn, $qUp);
                                mysqli_stmt_bind_param($stUp, "di", $amount, $ex['budget_id']);
                                mysqli_stmt_execute($stUp);
                                mysqli_stmt_close($stUp);
                            } else {
                                $qIns = "INSERT INTO budgets (user_id, category_id, month, year, amount_limit) VALUES (?,?,?,?,?)";
                                $stIns = mysqli_prepare($conn, $qIns);
                                mysqli_stmt_bind_param($stIns, "iiiid", $user_id, $category_id, $m, $y, $amount);
                                mysqli_stmt_execute($stIns);
                                mysqli_stmt_close($stIns);
                            }

                            mysqli_stmt_close($stmtVerify);
                            mysqli_close($conn);
                            header("Location: monthly.php?period=" . sprintf("%04d-%02d", $y, $m));
                            exit;

                        } else {

                            // income & expand -> transactions
                            $queryInsert = "
                                INSERT INTO transactions
                                    (user_id, category_id, type, amount, transaction_date, transaction_time, note)
                                VALUES
                                    (?, ?, ?, ?, ?, ?, ?)
                            ";
                            $stmtInsert = mysqli_prepare($conn, $queryInsert);
                            mysqli_stmt_bind_param(
                                $stmtInsert,
                                "iisssss",
                                $user_id,
                                $category_id,
                                $type,
                                $amount,
                                $date,
                                $time,
                                $note
                            );

                            if (mysqli_stmt_execute($stmtInsert)) {
                                mysqli_stmt_close($stmtInsert);
                                mysqli_stmt_close($stmtVerify);
                                mysqli_close($conn);
                                header("Location: home.php");
                                exit;
                            } else {
                                $message = __('err_save_failed') . ": " . mysqli_error($conn);
                            }

                            mysqli_stmt_close($stmtInsert);
                        }
                    }

                    mysqli_stmt_close($stmtVerify);
                }
            }
        }
    }
}

/* default values */
$default_date = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(__('html_lang')) ?>">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars(__('add_transaction_title')) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
*{box-sizing:border-box;font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
body{margin:0;background:#fff;color:#1d1d1d}
.wrapper{max-width:420px;margin:0 auto;padding:18px 16px 24px}

.header{display:flex;align-items:center;gap:12px;margin-top:4px;margin-bottom:8px}
.header a{display:flex;align-items:center;justify-content:center;color:#0b7d73;text-decoration:none}
.header i{font-size:20px}
.header h2{font-size:18px;margin:0;font-weight:600}

.tabs{
  margin:16px 0 14px;
  background:#9bb37d;
  border-radius:999px;
  padding:3px;
  display:flex;
  gap:3px;
}
.tabs a{
  flex:1;
  text-align:center;
  padding:10px 0;
  font-size:12px;
  color:#fff;
  text-decoration:none;
  border-radius:999px;
  transition:.15s;
  font-weight:500;
}
.tabs a.active{background:#0b7d73;font-weight:600}

.label{font-size:13px;font-weight:600;margin:10px 2px 8px}

.input-error{
  margin-top:6px;
  font-size:12px;
  font-weight:600;
  color:#d32f2f;
}

.error-input{
  outline:2px solid #d32f2f;
  background:#fff5f5;
}

.amount{
  background:#e9fbf4;
  border-radius:12px;
  padding:12px 12px;
  display:flex;
  align-items:center;
  gap:10px;
}
.amount input{
  border:none;background:transparent;outline:none;
  font-size:20px;font-weight:700;width:100%;
}
.amount .cur{font-size:12px;color:#6b6b6b;font-weight:600}

.category-grid{
  background:#e9fbf4;
  border-radius:14px;
  padding:14px;
  display:grid;
  grid-template-columns:repeat(4, 1fr);
  gap:14px;
}
.cat{cursor:pointer;user-select:none}
.cat input{display:none}
.cat-box{
  background:#fff;
  border-radius:14px;
  padding:10px 6px;
  text-align:center;
  transition:.15s;
}
.cat-icon{
  width:44px;height:44px;border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 8px;
  overflow:hidden;
  background:#f3f6f5;
}
.cat-icon img{
  width:26px;height:26px;
  object-fit:contain;
}
.cat-name{font-size:11px;line-height:1.1}
.cat input:checked + .cat-box{outline:3px solid #0b7d73}

.note-card{
  background:#dff6ee;
  border-radius:18px;
  padding:14px;
  margin-top:14px;
}
.note-input{
  width:100%;
  border:none;
  outline:none;
  background:#fff;
  border-radius:12px;
  padding:12px 12px;
  font-size:13px;
}
.pickers{margin-top:10px;display:flex;gap:10px;}
.pickers .picker{
  flex:1;
  display:flex;
  align-items:center;
  gap:10px;
  background:#fff;
  border-radius:12px;
  padding:10px 12px;
}
.pickers .picker i{color:#0b7d73}
.pickers input{border:none;outline:none;background:transparent;width:100%;font-size:13px}

.save{margin-top:18px;}
.save button{
  width:100%;
  border:none;
  border-radius:999px;
  padding:14px 0;
  background:#0b7d73;
  color:#fff;
  font-weight:700;
  font-size:14px;
}

.message{
  margin:10px 0 12px;
  padding:10px 12px;
  border-radius:12px;
  font-size:13px;
}
.message.error{background:#f8d7da; color:#721c24;}
</style>

<script>
function formatThousands(inp){
  let v = inp.value.replace(/[^\d]/g,'');
  if(!v){ inp.value=''; return; }
  inp.value = v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}
</script>
</head>
<body>
<div class="wrapper">

<div class="header">
  <a href="home.php"><i class="fa-solid fa-arrow-left"></i></a>
  <h2><?= htmlspecialchars(__('add_transaction_title')) ?></h2>
</div>

  <div class="tabs">
      <a class="<?= $transaction_type==='income'?'active':'' ?>" href="?type=income"><?= htmlspecialchars(__('tab_income')) ?></a>
      <a class="<?= $transaction_type==='expand'?'active':'' ?>" href="?type=expand"><?= htmlspecialchars(__('tab_expand')) ?></a>
      <a class="<?= $transaction_type==='monthly_expense'?'active':'' ?>" href="?type=monthly_expense"><?= htmlspecialchars(__('tab_monthly_expense')) ?></a>
  </div>

  <?php if (!empty($message)): ?>
    <div class="message error"><?= htmlspecialchars($message); ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="transaction_type" value="<?= htmlspecialchars($transaction_type); ?>">

    <div class="label"><?= htmlspecialchars(__('amount_label')) ?></div>
    <div class="amount">
      <input
        type="text"
        name="amount"
        placeholder="0"
        oninput="formatThousands(this)"
        value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
        class="<?= $amount_error ? 'error-input' : '' ?>"
        required
      >
      <div class="cur">IDR</div>
    </div>

    <?php if (!empty($amount_error)): ?>
      <div class="input-error"><?= htmlspecialchars($amount_error); ?></div>
    <?php endif; ?>

    <div class="label" style="margin-top:14px;"><?= htmlspecialchars(__('category_label')) ?></div>
    <div class="category-grid">
      <?php foreach ($categories as $cat) : ?>
        <?php
          $catName = (string)($cat['category_name'] ?? '');
          $isIncome = ($transaction_type === 'income');
          $icon = categoryIcon($catName, $transaction_type);
          $fallback = $isIncome ? 'assets/Income/other.png' : 'assets/Expanse/espanse_bills.png';
        ?>
        <label class="cat">
          <input type="radio" name="category_id" value="<?= (int)$cat['category_id']; ?>" required>
          <div class="cat-box">
            <div class="cat-icon">
              <img
                src="<?= htmlspecialchars($icon); ?>"
                alt=""
                onerror="this.src='<?= htmlspecialchars($fallback); ?>';"
              >
            </div>
            <div class="cat-name"><?= htmlspecialchars($catName); ?></div>
          </div>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="note-card">
      <div class="label" style="margin:0 2px 8px;"><?= htmlspecialchars(__('note_label')) ?></div>
      <input class="note-input" type="text" name="note" placeholder="<?= htmlspecialchars(__('note_placeholder')) ?>">

      <div class="pickers">
        <div class="picker">
          <i class="fa-regular fa-calendar"></i>
          <input type="date" name="transaction_date" value="<?= htmlspecialchars($default_date); ?>" required>
        </div>
        <div class="picker">
          <i class="fa-regular fa-clock"></i>
          <input type="time" id="timeNow" name="transaction_time" required>
        </div>
      </div>
    </div>

    <div class="save">
      <button type="submit" name="save"><?= htmlspecialchars(__('save')) ?></button>
    </div>

  </form>

</div>

<script>
  // realtime time (HH:mm)
  function pad(n){ return String(n).padStart(2,'0'); }
  function setNow(){
    const d = new Date();
    document.getElementById('timeNow').value = `${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }
  setNow();
  setInterval(setNow, 1000);

  // live clear error on amount > 0
  const amountInput = document.querySelector('input[name="amount"]');
  if(amountInput){
    amountInput.addEventListener('input', () => {
      const v = amountInput.value.replace(/[^\d]/g,'');
      if(v && parseInt(v,10) > 0){
        amountInput.classList.remove('error-input');
        const err = document.querySelector('.input-error');
        if(err) err.remove();
      }
    });
  }
</script>

</body>
</html>
<?php
if (isset($conn) && $conn) mysqli_close($conn);
?>

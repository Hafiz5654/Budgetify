<?php
// includes/i18n.php
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Sumber bahasa utama:
 * - setting.php menyimpan: $_SESSION['app_language'] = 'English' / 'Indonesia'
 * - i18n butuh kode: 'en' / 'id'
 */
$appLang = $_SESSION['app_language'] ?? 'English';
$lang = ($appLang === 'Indonesia') ? 'id' : 'en';

$translations = [
  'id' => [
    'html_lang'           => 'id',
    'dashboard_title'     => 'Smart Budget - Expanse Tracker',
    'total_balance'       => 'Total Saldo',
    'download_report'     => 'Unduh Laporan',
    'tab_income'          => 'Pemasukan',
    'tab_expand'          => 'Pengeluaran',
    'tab_monthly_expense' => 'Pengeluaran Bulanan',
    'empty_state'         => 'Tambahkan pengeluaran pertama kamu untuk memulai',
    'total_income'        => 'Total Pemasukan',
    'total_expenditure'   => 'Total Pengeluaran',
    'total_monthly_expense'=> 'Total Pengeluaran Bulanan',

    'monthly_title'      => 'Monthly Expense',
'total_estimate'     => 'Total Estimasi',
'no_limit_yet'       => 'Belum ada limit, tambahkan limit pertama kamu',
'limit_label'        => 'Limit',
'used'               => 'Terpakai',
'remaining'          => 'Sisa',
'over_limit'         => 'Melebihi limit!',
'toast_limit_reached'=> 'Limit {category} tercapai! ({pct}%)',
'toast_limit_near'   => 'Limit {category} hampir habis ({pct}%)',
'toast_used_from'    => 'Terpakai dari limit',
'err_amount_required' => 'Harap Masukan Nominal',



    // bottom_nav.php
'home'      => 'Beranda',
'statistic' => 'Statistik',
'budget'    => 'Anggaran',
'setting'   => 'Pengaturan',

'statistic_title' => 'Statistik',
'per_week'        => 'per Minggu',
'week_label'      => 'Minggu',

'budget_title'            => 'Anggaran',
'aria_budget_donut'       => 'Persentase pengeluaran dari pemasukan',
'expenses_expand'         => 'Pengeluaran (Expand)',
'month_label'             => 'Bulan',
'income_label'            => 'Pemasukan',
'income_usage_progress'   => 'Progress penggunaan pemasukan',
'budget_detail'           => 'Detail Anggaran',
'budget_note_expand_only' => 'Expand dihitung dari transaksi type expand saja (bukan monthly expense).',
'spent'                   => 'Terpakai',

'add_transaction_title' => 'Tambah Transaksi',
'amount_label'          => 'Jumlah',
'category_label'        => 'Kategori',
'note_label'            => 'Catatan',
'note_placeholder'      => 'Tambah deskripsi',
'save'                  => 'Simpan',

'err_invalid_type'      => 'Error: Tipe transaksi tidak valid.',
'err_amount_positive'   => 'Error: Amount harus berupa angka positif.',
'err_invalid_datetime'  => 'Error: Format tanggal atau waktu tidak valid.',
'err_invalid_category'  => 'Error: Kategori tidak valid atau tidak sesuai tipe.',
'err_save_failed'       => 'Error menyimpan transaksi',




    // setting.php
    'settings_title'      => 'Pengaturan',
    'saved'               => 'Tersimpan ✅',
    'language_title'      => 'Bahasa',
    'language_sub'        => 'Pilih bahasa aplikasi',
    'currency_title'      => 'Mata Uang',
    'currency_sub'        => 'Pilih mata uang tampilan',
    'aria_logout'         => 'Keluar',
    'logout_ask'          => 'Keluar dari akun?',
    'logout_sub'          => 'Kamu akan keluar dari aplikasi.',
    'cancel'              => 'Batal',
    'logout'              => 'Keluar',
  ],
  'en' => [
    'html_lang'           => 'en',
    'dashboard_title'     => 'Smart Budget - Expense Tracker',
    'total_balance'       => 'Total Balance',
    'download_report'     => 'Download Report',
    'tab_income'          => 'Income',
    'tab_expand'          => 'Expense',
    'tab_monthly_expense' => 'Monthly Expense',
    'empty_state'         => 'Add your first expense to get started',
    'total_income'        => 'Total Income',
    'total_expenditure'   => 'Total Expenditure',
    'total_monthly_expense'=> 'Total Monthly Expense',

    'monthly_title'      => 'Monthly Expense',
'total_estimate'     => 'Total Estimate',
'no_limit_yet'       => 'No limit yet, add your first limit',
'limit_label'        => 'Limit',
'used'               => 'Used',
'remaining'          => 'Remaining',
'over_limit'         => 'Over limit!',
'toast_limit_reached'=> '{category} limit reached! ({pct}%)',
'toast_limit_near'   => '{category} limit is almost used up ({pct}%)',
'toast_used_from'    => 'Used from limit',
'err_amount_required' => 'Please enter an amount',



'statistic_title' => 'Statistics',
'per_week'        => 'per Week',
'week_label'      => 'Week',

'budget_title'            => 'Budget',
'aria_budget_donut'       => 'Spending percentage of income',
'expenses_expand'         => 'Expenses (Expense)',
'month_label'             => 'Month',
'income_label'            => 'Income',
'income_usage_progress'   => 'Income usage progress',
'budget_detail'           => 'Budget Detail',
'budget_note_expand_only' => 'Expenses are calculated only from transactions with type "expand" (not monthly expense).',
'spent'                   => 'Spent',

'add_transaction_title' => 'Add Transaction',
'amount_label'          => 'Amount',
'category_label'        => 'Category',
'note_label'            => 'Note',
'note_placeholder'      => 'Add a description',
'save'                  => 'Save',

'err_invalid_type'      => 'Error: Invalid transaction type.',
'err_amount_positive'   => 'Error: Amount must be a positive number.',
'err_invalid_datetime'  => 'Error: Invalid date or time format.',
'err_invalid_category'  => 'Error: Category is invalid or does not match the type.',
'err_save_failed'       => 'Failed to save transaction',





    // bottom_nav.php
'home'      => 'Home',
'statistic' => 'Statistics',
'budget'    => 'Budget',
'setting'   => 'Settings',


    // setting.php
    'settings_title'      => 'Settings',
    'saved'               => 'Saved ✅',
    'language_title'      => 'Language',
    'language_sub'        => 'Choose app language',
    'currency_title'      => 'Currency',
    'currency_sub'        => 'Choose display currency',
    'aria_logout'         => 'Logout',
    'logout_ask'          => 'Log out of your account?',
    'logout_sub'          => 'You will be signed out from the app.',
    'cancel'              => 'Cancel',
    'logout'              => 'Logout',
  ],
];

function __(string $key): string {
  global $translations, $lang;
  return $translations[$lang][$key] ?? $key;
}

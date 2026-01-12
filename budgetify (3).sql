-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 12 Jan 2026 pada 17.46
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `budgetify`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `budgets`
--

CREATE TABLE `budgets` (
  `budget_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `amount_limit` decimal(12,2) DEFAULT NULL,
  `month` int(2) DEFAULT NULL,
  `year` int(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `budgets`
--

INSERT INTO `budgets` (`budget_id`, `user_id`, `category_id`, `amount_limit`, `month`, `year`) VALUES
(3, 3, 18, 500000.00, 12, 2025),
(4, 3, 35, 500000.00, 12, 2025),
(5, 3, 13, 500000.00, 12, 2025),
(6, 5, 29, 4000000.00, 12, 2025),
(7, 3, 13, 500000.00, 11, 2025),
(8, 3, 26, 500000.00, 9, 2025);

-- --------------------------------------------------------

--
-- Struktur dari tabel `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `category_name` varchar(50) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `categories`
--

INSERT INTO `categories` (`category_id`, `user_id`, `category_name`, `type`) VALUES
(1, NULL, 'Salary', 'income'),
(2, NULL, 'Invest', 'income'),
(3, NULL, 'Business', 'income'),
(4, NULL, 'Interest', 'income'),
(5, NULL, 'Extra Income', 'income'),
(6, NULL, 'Other', 'income'),
(11, NULL, 'Extra', 'income'),
(13, NULL, 'Food', 'expand'),
(14, NULL, 'Transport', 'expand'),
(15, NULL, 'Entertainment', 'expand'),
(16, NULL, 'Utilities', 'expand'),
(17, NULL, 'Shopping', 'expand'),
(18, NULL, 'Other', 'expand'),
(19, NULL, 'Rent', 'monthly_expense'),
(20, NULL, 'Insurance', 'monthly_expense'),
(21, NULL, 'Subscription', 'monthly_expense'),
(22, NULL, 'Loan', 'monthly_expense'),
(23, NULL, 'Savings', 'monthly_expense'),
(24, NULL, 'Other', 'monthly_expense'),
(26, NULL, 'Social', 'expand'),
(27, NULL, 'Traffic', 'expand'),
(29, NULL, 'Grocery', 'expand'),
(30, NULL, 'Education', 'expand'),
(31, NULL, 'Bills', 'expand'),
(32, NULL, 'Rentals', 'expand'),
(33, NULL, 'Medical', 'expand'),
(34, NULL, 'Investment', 'expand'),
(35, NULL, 'Gift', 'expand');

-- --------------------------------------------------------

--
-- Struktur dari tabel `monthly_estimations`
--

CREATE TABLE `monthly_estimations` (
  `estimation_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `estimated_income` decimal(12,2) DEFAULT NULL,
  `estimated_expense` decimal(12,2) DEFAULT NULL,
  `estimated_saving` decimal(12,2) DEFAULT NULL,
  `month` int(2) DEFAULT NULL,
  `year` int(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `reports`
--

CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `report_type` varchar(20) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `generated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `routine_expenses`
--

CREATE TABLE `routine_expenses` (
  `routine_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `routine_name` varchar(100) DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `cycle` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `transaction_date` date DEFAULT NULL,
  `transaction_time` time NOT NULL,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `user_id`, `category_id`, `type`, `amount`, `transaction_date`, `transaction_time`, `note`) VALUES
(5, 1, 3, 'income', 8000000.00, '2025-07-12', '11:45:00', 'Gaji Bulan Ini'),
(6, 1, 1, 'income', 500000000.00, '2025-07-12', '11:45:00', 'Gaji Bulan Ini'),
(8, 3, 5, 'income', 1000000000.00, '2025-07-12', '11:45:00', 'Gaji Bulan Ini'),
(9, 3, 1, 'income', 20000000.00, '2025-12-28', '19:11:00', 'Gaji Bulan Desember'),
(10, 3, 3, 'income', 200000.00, '2025-12-28', '19:37:00', 'Keuntungan Jual Ciki'),
(11, 3, 13, 'expand', 15000.00, '2025-12-28', '20:56:00', 'Nasi Goreng'),
(14, 3, 5, 'income', 100000.00, '2025-12-29', '06:58:00', 'apa ya'),
(15, 3, 3, 'income', 500000.00, '2025-12-29', '07:41:00', 'keuntungan jualan payung'),
(16, 3, 29, 'expand', 750000.00, '2025-12-29', '07:41:00', 'belanja bulanan'),
(18, 3, 13, 'expand', 400000.00, '2025-12-29', '08:18:00', ''),
(19, 3, 13, 'expand', 100000.00, '2025-12-29', '08:18:00', ''),
(20, 5, 5, 'income', 40000.00, '2025-12-30', '07:11:00', 'Uang jajan'),
(21, 5, 13, 'expand', 35000.00, '2025-12-30', '07:12:00', 'Ayam Geprek'),
(22, 5, 1, 'income', 5000000.00, '2025-12-30', '07:14:00', 'gaji desember'),
(23, 5, 29, 'expand', 5000000.00, '2025-12-30', '07:15:00', ''),
(24, 5, 11, 'income', 3000000.00, '2025-12-30', '07:27:00', ''),
(25, 3, 5, 'income', 1500000.00, '2025-11-30', '09:28:00', 'uang jajan dari emak'),
(26, 3, 13, 'expand', 30000.00, '2025-11-30', '09:29:00', 'makan siang bang'),
(27, 3, 13, 'expand', 470000.00, '2025-11-30', '09:31:00', 'all you can eat'),
(28, 3, 3, 'income', 1500000.00, '2025-09-30', '09:50:00', 'Bussines'),
(29, 3, 26, 'expand', 500000.00, '2025-09-30', '09:51:00', ''),
(30, 3, 26, 'expand', 600000.00, '2025-09-30', '09:52:00', ''),
(31, 3, 26, 'expand', 500000.00, '2025-09-30', '09:53:00', '');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'bubub', 'bubub@gmail.com', '$2y$10$bdl.HYpA1mYDJu3zfs9r0.PU65uyOvOLmfO/3Fw98DD0ube5Fr78i', 'user', '2025-12-27 13:54:27'),
(3, 'aaliyah', 'aaliyah@gmail.com', '$2y$10$klj7vg67Kwbi0TBlGqlRZ.ofqZhDJmYQtFEZtJM7XhzLIEKWAOCYS', 'user', '2025-12-28 12:51:16'),
(4, 'jara', 'jara@gmail.com', '$2y$10$aZ3/ubjGQQ52KTFMUd.VkOBqeUeVINHj8snsVCBX7EXQmfuuiX0Dm', 'user', '2025-12-30 11:31:28'),
(5, 'Muhammad Rayyan', 'muhammadrayyan802@gmail.com', '$2y$10$mpnZp9sokJvsI7.OXG4jQ.paSlC/usDcNVct083M9F0drpXfpTYMm', 'user', '2025-12-30 13:09:36'),
(7, 'Hafiz5654', 'hafisarkamunif@gmail.com', '$2y$10$hZ1qFPIx4DuJztTbDUTGmegBbrM1wbtOwiSMbCCfWMkkBPRUm4Q2a', 'user', '2025-12-31 09:25:18');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `budgets`
--
ALTER TABLE `budgets`
  ADD PRIMARY KEY (`budget_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indeks untuk tabel `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `monthly_estimations`
--
ALTER TABLE `monthly_estimations`
  ADD PRIMARY KEY (`estimation_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `routine_expenses`
--
ALTER TABLE `routine_expenses`
  ADD PRIMARY KEY (`routine_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `budgets`
--
ALTER TABLE `budgets`
  MODIFY `budget_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT untuk tabel `monthly_estimations`
--
ALTER TABLE `monthly_estimations`
  MODIFY `estimation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `routine_expenses`
--
ALTER TABLE `routine_expenses`
  MODIFY `routine_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `budgets`
--
ALTER TABLE `budgets`
  ADD CONSTRAINT `budgets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `budgets_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);

--
-- Ketidakleluasaan untuk tabel `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Ketidakleluasaan untuk tabel `monthly_estimations`
--
ALTER TABLE `monthly_estimations`
  ADD CONSTRAINT `monthly_estimations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Ketidakleluasaan untuk tabel `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Ketidakleluasaan untuk tabel `routine_expenses`
--
ALTER TABLE `routine_expenses`
  ADD CONSTRAINT `routine_expenses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Ketidakleluasaan untuk tabel `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

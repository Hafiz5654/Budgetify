<?php
$conn = mysqli_connect("localhost", "root", "", "budgetify");

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

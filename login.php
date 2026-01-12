<?php
session_start();
include "db.php";
$error = "";

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $q = mysqli_query($conn, "SELECT * FROM users WHERE name='$username'");
    $user = mysqli_fetch_assoc($q);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id']; // ⬅️ INI PENTING
        $_SESSION['name']    = $user['name'];

        header("Location: dashboard/home.php");
        exit;
    } else {
        $error = "Username atau password salah";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Budgetify</title>
    <link rel="stylesheet" href="style.css">

    <!-- Font Awesome untuk icon mata (rapi, bukan emoji) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- PATCH kecil: tanpa ubah design, cuma fix scroll + icon eye + gambar -->
    <style>
      /* FIX: biar kalau konten kepanjangan, tombol gak ketutup */
      body { overflow-y: auto; }

      /* Wrapper aman */
      .app { position: relative; min-height: 100vh; }

      /* Gambar bumi/login.png */
      .auth-hero-img{
        position: absolute;
        top: 0;
        right: 0;
        width: 430px;
        max-width: 55vw;
        height: auto;
        pointer-events: none;
        user-select: none;
        z-index: 0;
        opacity: 1;
      }

      /* Pastikan konten di atas gambar */
      .header, .card { position: relative; z-index: 1; }

      /* === Eye icon rapih === */
      .password{
        position: relative;
      }
      .password input{
        padding-right: 44px; /* ruang untuk icon */
      }
      .toggle-password{
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #0b7d73; /* ngikut tone desain kamu */
        font-size: 14px;
        opacity: .85;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
      }
      .toggle-password:hover{ opacity: 1; }
    </style>
</head>
<body>

<div class="app">

    <!-- Gambar bumi (sesuai request) -->
    <img class="auth-hero-img" src="login.png" alt="Login Illustration">

    <div class="header">
        <h1>Hello</h1>
        <p>Welcome to Budgetify.</p>
    </div>

    <div class="card">

        <?php if ($error): ?>
            <p style="color:white; text-align:center"><?= $error ?></p>
        <?php endif; ?>

        <form method="POST">
            <h2>Login</h2>

            <input type="text" name="username" placeholder="username" required>

            <div class="password">
                <input type="password" name="password" placeholder="password" required>
                <span class="toggle-password" aria-label="Toggle password">
                  <i class="fa-regular fa-eye"></i>
                </span>
            </div>

            <button type="submit" name="login">Login</button>

            <p class="switch">
                Don't have account?
                <a href="signup.php" style="color:white">Sign Up</a>
            </p>
        </form>

    </div>
</div>

<script>
document.querySelectorAll(".toggle-password").forEach(btn => {
  btn.onclick = () => {
    const input = btn.previousElementSibling;
    const icon  = btn.querySelector("i");

    if (!input) return;

    if (input.type === "password") {
      input.type = "text";
      if (icon) {
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
      }
    } else {
      input.type = "password";
      if (icon) {
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
      }
    }
  };
});
</script>

</body>
</html>

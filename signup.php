<?php
include "db.php";
$error = "";

if (isset($_POST['signup'])) {
    $username = $_POST['username'];
    $email    = $_POST['email'];
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if ($password !== $confirm) {
        $error = "Password tidak sama";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        mysqli_query($conn, "
            INSERT INTO users (name, email, password, role, created_at)
            VALUES ('$username', '$email', '$hash', 'user', NOW())
        ");

        header("Location: login.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Budgetify</title>
    <link rel="stylesheet" href="style.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
      /* === FIX: supaya tidak ketutupan === */
      html, body { height: 90%; }
      body { overflow-y: auto; }

      .app { position: relative; min-height: 100vh; height: auto; padding-bottom: 60px; }
      .card { height: padding-bottom: 150px; }

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
      }
      .header, .card { position: relative; z-index: 1; }

      /* === Eye icon rapih === */
      .password{ position: relative; }
      .password input{ padding-right: 44px; }
      .toggle-password{
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #0b7d73;
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

    <img class="auth-hero-img" src="login.png" alt="Signup Illustration">

    <div class="header">
        <h1>Hello</h1>
        <p>Welcome to Budgetify.</p>
    </div>

    <div class="card">

        <form method="POST">
            <h2>Sign Up</h2>

            <?php if ($error): ?>
                <p style="color:white; text-align:center"><?= $error ?></p>
            <?php endif; ?>

            <input type="text" name="username" placeholder="username" required>
            <input type="email" name="email" placeholder="email" required>

            <div class="password">
                <input type="password" name="password" placeholder="password" required>
                <span class="toggle-password" aria-label="Toggle password">
                  <i class="fa-regular fa-eye"></i>
                </span>
            </div>

            <div class="password">
                <input type="password" name="confirm_password" placeholder="confirm password" required>
                <span class="toggle-password" aria-label="Toggle password">
                  <i class="fa-regular fa-eye"></i>
                </span>
            </div>

            <button type="submit" name="signup">Sign Up</button>

            <p class="switch back">
                ← <a href="login.php" style="color:white">Back to login</a>
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
      if (icon) { icon.classList.remove("fa-eye"); icon.classList.add("fa-eye-slash"); }
    } else {
      input.type = "password";
      if (icon) { icon.classList.remove("fa-eye-slash"); icon.classList.add("fa-eye"); }
    }
  };
});
</script>

</body>
</html>

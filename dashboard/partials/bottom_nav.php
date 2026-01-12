<?php // partials/bottom_nav.php ?>
<div class="bottom-nav">
  <div class="nav-group left">
    <a href="home.php" class="nav-item">
      <i class="fa-solid fa-house"></i><span><?= htmlspecialchars(__('home')) ?></span>
    </a>
    <a href="statistic.php" class="nav-item stat">
      <i class="fa-solid fa-chart-line"></i><span><?= htmlspecialchars(__('statistic')) ?></span>
    </a>
  </div>

  <div class="nav-plus">
    <a href="add_transaction.php"><i class="fa-solid fa-plus"></i></a>
  </div>

  <div class="nav-group right">
    <a href="budget.php" class="nav-item budget">
      <i class="fa-solid fa-wallet"></i><span><?= htmlspecialchars(__('budget')) ?></span>
    </a>
    <a href="setting.php" class="nav-item">
      <i class="fa-solid fa-gear"></i><span><?= htmlspecialchars(__('setting')) ?></span>
    </a>
  </div>
</div>

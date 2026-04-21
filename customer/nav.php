<?php
$pdo2 = getDB();
$uid2 = getUserId();
$unreadStmt = $pdo2->prepare("SELECT COUNT(*) FROM orders WHERE customer_id=? AND status IN ('pending','processing','shipping')");
$unreadStmt->execute([$uid2]);
$unread2 = $unreadStmt->fetchColumn();
$cartCount2 = array_sum($_SESSION['cart'] ?? []);
?>
<nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 max-w-md mx-auto">
  <div class="grid grid-cols-4 h-16">
    <a href="index.php" class="flex flex-col items-center justify-center gap-1 <?= ($activeTab??'')==='home'?'text-orange-500':'text-gray-400' ?>">
      <i data-lucide="home" class="w-5 h-5"></i><span class="text-xs">Home</span>
    </a>
    <a href="order.php" class="flex flex-col items-center justify-center gap-1 <?= ($activeTab??'')==='order'?'text-orange-500':'text-gray-400' ?>">
      <div class="relative">
        <i data-lucide="shopping-cart" class="w-5 h-5"></i>
        <?php if ($cartCount2 > 0): ?>
        <span class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 rounded-full text-white text-[9px] flex items-center justify-center font-bold"><?= $cartCount2 ?></span>
        <?php endif; ?>
      </div>
      <span class="text-xs">Pesan</span>
    </a>
    <a href="history.php" class="flex flex-col items-center justify-center gap-1 <?= ($activeTab??'')==='history'?'text-orange-500':'text-gray-400' ?>">
      <div class="relative">
        <i data-lucide="clock" class="w-5 h-5"></i>
        <?php if ($unread2 > 0): ?>
        <span class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 rounded-full text-white text-[9px] flex items-center justify-center font-bold"><?= $unread2 ?></span>
        <?php endif; ?>
      </div>
      <span class="text-xs">Riwayat</span>
    </a>
    <a href="profile.php" class="flex flex-col items-center justify-center gap-1 <?= ($activeTab??'')==='profile'?'text-orange-500':'text-gray-400' ?>">
      <i data-lucide="user" class="w-5 h-5"></i><span class="text-xs">Profil</span>
    </a>
  </div>
</nav>

<nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 max-w-md mx-auto">
  <div class="grid grid-cols-4 h-16">
    <a href="index.php" class="flex flex-col items-center justify-center gap-1 <?= ($activeTab??'')==='production'?'text-orange-500':'text-gray-400' ?>">
      <i data-lucide="chef-hat" class="w-5 h-5"></i><span class="text-xs">Produksi</span>
    </a>
    <a href="stock.php" class="flex flex-col items-center justify-center gap-1 <?= ($activeTab??'')==='stock'?'text-orange-500':'text-gray-400' ?>">
      <i data-lucide="package" class="w-5 h-5"></i><span class="text-xs">Stok</span>
    </a>
    <a href="delivery.php" class="flex flex-col items-center justify-center gap-1 <?= ($activeTab??'')==='delivery'?'text-orange-500':'text-gray-400' ?>">
      <i data-lucide="truck" class="w-5 h-5"></i><span class="text-xs">Kirim</span>
    </a>
    <a href="profile.php" class="flex flex-col items-center justify-center gap-1 <?= ($activeTab??'')==='profile'?'text-orange-500':'text-gray-400' ?>">
      <i data-lucide="user" class="w-5 h-5"></i><span class="text-xs">Profil</span>
    </a>
  </div>
</nav>

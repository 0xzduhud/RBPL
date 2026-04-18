<nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 max-w-md mx-auto">
  <div class="grid grid-cols-4 h-16">
    <a href="index.php" class="flex flex-col items-center justify-center gap-1 <?= ($activeTab??'')==='reports'?'text-orange-500':'text-gray-400' ?>">
      <i data-lucide="bar-chart-2" class="w-5 h-5"></i><span class="text-xs">Laporan</span>
    </a>
    <a href="masterdata.php" class="flex flex-col items-center justify-center gap-1 <?= ($activeTab??'')==='masterdata'?'text-orange-500':'text-gray-400' ?>">
      <i data-lucide="database" class="w-5 h-5"></i><span class="text-xs">Master</span>
    </a>
    <a href="employees.php" class="flex flex-col items-center justify-center gap-1 <?= ($activeTab??'')==='employees'?'text-orange-500':'text-gray-400' ?>">
      <i data-lucide="users" class="w-5 h-5"></i><span class="text-xs">Karyawan</span>
    </a>
    <a href="profile.php" class="flex flex-col items-center justify-center gap-1 <?= ($activeTab??'')==='profile'?'text-orange-500':'text-gray-400' ?>">
      <i data-lucide="user" class="w-5 h-5"></i><span class="text-xs">Profil</span>
    </a>
  </div>
</nav>

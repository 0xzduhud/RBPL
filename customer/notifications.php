<?php
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
requireLogin('customer');
$pageTitle = 'Notifikasi - Dimsum App';
$activeTab = 'notifications';
$pdo = getDB();
$userId = getUserId();

// Ambil riwayat order sebagai notifikasi
$orders = $pdo->prepare("SELECT order_code, status, total, created_at, updated_at FROM orders WHERE customer_id=? ORDER BY updated_at DESC LIMIT 20");
$orders->execute([$userId]);
$orders = $orders->fetchAll();

$labelMap = ['pending'=>'Menunggu Konfirmasi','processing'=>'Sedang Diproses','production'=>'Dalam Produksi','shipping'=>'Sedang Dikirim','delivered'=>'Pesanan Tiba!','cancelled'=>'Dibatalkan'];
$iconMap  = ['pending'=>'clock','processing'=>'package','production'=>'chef-hat','shipping'=>'truck','delivered'=>'check-circle','cancelled'=>'x-circle'];
$colorMap = ['pending'=>'text-yellow-500 bg-yellow-50','processing'=>'text-orange-500 bg-orange-50','production'=>'text-blue-500 bg-blue-50','shipping'=>'text-blue-600 bg-blue-50','delivered'=>'text-green-500 bg-green-50','cancelled'=>'text-red-500 bg-red-50'];

// Clear session notifications setelah dibaca
clearNotifications();
?>
<?php include '../includes/header.php'; ?>
<div class="min-h-screen bg-gray-50 pb-20"><div class="max-w-md mx-auto">
  <div class="bg-white border-b border-gray-200 p-4 sticky top-0 z-10">
    <h2 class="text-center text-lg font-semibold">Notifikasi</h2>
  </div>
  <div class="p-4 space-y-3">
    <?php if (empty($orders)): ?>
    <div class="text-center py-10 text-gray-400">
      <i data-lucide="bell-off" class="w-12 h-12 mx-auto mb-3 opacity-40"></i>
      <p class="text-sm">Belum ada notifikasi</p>
    </div>
    <?php endif; ?>
    <?php foreach ($orders as $o): $s = $o['status']; ?>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 flex items-start gap-3">
      <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 <?= $colorMap[$s] ?? 'text-gray-500 bg-gray-50' ?>">
        <i data-lucide="<?= $iconMap[$s] ?? 'bell' ?>" class="w-5 h-5"></i>
      </div>
      <div class="flex-1">
        <p class="text-sm font-medium mb-1"><?= $labelMap[$s] ?? $s ?></p>
        <p class="text-xs text-gray-500 mb-1">Pesanan <span class="font-semibold text-gray-700"><?= htmlspecialchars($o['order_code']) ?></span></p>
        <p class="text-xs text-gray-400"><?= date('d M Y, H:i', strtotime($o['updated_at'])) ?></p>
      </div>
      <p class="text-xs text-orange-500 font-medium">Rp <?= number_format($o['total'],0,',','.') ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php include 'nav.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>

<?php
require_once '../includes/auth.php';
requireLogin('customer');
$pageTitle = 'Riwayat Pesanan - Dimsum App';
$activeTab = 'history';
$pdo       = getDB();
$userId    = getUserId();
$filter    = $_GET['filter'] ?? 'all';
$detail    = $_GET['detail'] ?? null;

// Batalkan pesanan
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='cancel') {
    $oid = (int)($_POST['order_id']??0);
    $chk = $pdo->prepare("SELECT id,status FROM orders WHERE id=? AND customer_id=?");
    $chk->execute([$oid,$userId]);
    $ord = $chk->fetch();
    if ($ord && $ord['status']==='pending') {
        $pdo->prepare("UPDATE orders SET status='cancelled',updated_at=NOW() WHERE id=?")->execute([$oid]);
    }
    header("Location: history.php"); exit;
}

// Detail satu order
$orderDetail = null;
if ($detail) {
    $ds = $pdo->prepare("SELECT o.*, a.address, a.city FROM orders o LEFT JOIN addresses a ON a.id=o.address_id WHERE o.order_code=? AND o.customer_id=?");
    $ds->execute([$detail, $userId]);
    $orderDetail = $ds->fetch();
    if ($orderDetail) {
        $di = $pdo->prepare("SELECT oi.qty,oi.price,oi.subtotal,p.name,p.emoji FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE oi.order_id=?");
        $di->execute([$orderDetail['id']]);
        $orderDetail['items'] = $di->fetchAll();
    }
}

// Daftar order
$sql = "SELECT o.*, (SELECT GROUP_CONCAT(p.name,' x',oi.qty SEPARATOR ', ') FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE oi.order_id=o.id) AS items_text FROM orders o WHERE o.customer_id=?";
$params = [$userId];
if ($filter !== 'all') { $sql .= " AND o.status=?"; $params[] = $filter; }
$sql .= " ORDER BY o.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$orders = $stmt->fetchAll();

$badgeMap = ['pending'=>'bg-yellow-100 text-yellow-700','processing'=>'bg-orange-100 text-orange-700','production'=>'bg-blue-100 text-blue-700','shipping'=>'bg-blue-100 text-blue-700','delivered'=>'bg-green-100 text-green-700','cancelled'=>'bg-red-100 text-red-700'];
$labelMap = ['pending'=>'Menunggu','processing'=>'Diproses','production'=>'Produksi','shipping'=>'Sedang Dikirim','delivered'=>'Selesai','cancelled'=>'Dibatalkan'];
$iconMap  = ['pending'=>'clock','processing'=>'package','production'=>'chef-hat','shipping'=>'truck','delivered'=>'check-circle','cancelled'=>'x-circle'];
$iconCol  = ['pending'=>'text-yellow-500','processing'=>'text-orange-500','production'=>'text-blue-500','shipping'=>'text-blue-500','delivered'=>'text-green-500','cancelled'=>'text-red-500'];
?>
<?php include '../includes/header.php'; ?>
<div class="min-h-screen bg-gray-50 pb-24"><div class="max-w-md mx-auto">

  <div class="bg-white border-b border-gray-100 p-4 sticky top-0 z-10">
    <h2 class="text-center text-lg font-bold">Riwayat Pesanan</h2>
  </div>

  <div class="bg-white border-b border-gray-100 px-4 py-3">
    <div class="flex gap-2 overflow-x-auto pb-1">
      <?php foreach(['all'=>'Semua','pending'=>'Menunggu','shipping'=>'Dikirim','delivered'=>'Selesai'] as $k=>$v): ?>
      <a href="history.php?filter=<?= $k ?>" class="px-4 py-1.5 rounded-full whitespace-nowrap text-sm font-medium flex-shrink-0 <?= $filter===$k?'bg-orange-500 text-white':'bg-gray-100 text-gray-600' ?>"><?= $v ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="p-4 space-y-3">
    <?php if (empty($orders)): ?>
    <div class="text-center py-16">
      <p class="text-5xl mb-3">📭</p>
      <p class="text-gray-500 font-medium">Belum ada pesanan</p>
      <a href="order.php" class="inline-block mt-4 bg-orange-500 text-white px-6 py-2.5 rounded-xl text-sm font-semibold">Pesan Sekarang</a>
    </div>
    <?php endif; ?>

    <?php foreach ($orders as $o): $s=$o['status']; ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <!-- Header card -->
      <div class="flex items-center justify-between px-4 pt-4 pb-3 border-b border-gray-50">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-full bg-orange-50 flex items-center justify-center">
            <i data-lucide="<?= $iconMap[$s]??'circle' ?>" class="w-4 h-4 <?= $iconCol[$s]??'text-gray-400' ?>"></i>
          </div>
          <div>
            <p class="text-sm font-bold"><?= htmlspecialchars($o['order_code']) ?></p>
            <p class="text-xs text-gray-400"><?= date('d M Y · H:i',strtotime($o['created_at'])) ?></p>
          </div>
        </div>
        <span class="text-xs px-2.5 py-1 rounded-full font-medium <?= $badgeMap[$s]??'bg-gray-100 text-gray-600' ?>"><?= $labelMap[$s]??$s ?></span>
      </div>
      <!-- Item summary -->
      <div class="px-4 py-3">
        <p class="text-sm text-gray-600 line-clamp-2"><?= htmlspecialchars($o['items_text']??'-') ?></p>
      </div>
      <!-- Footer card -->
      <div class="flex items-center justify-between px-4 pb-4">
        <div>
          <p class="text-xs text-gray-400">Total</p>
          <p class="text-orange-500 font-bold text-base">Rp <?= number_format($o['total'],0,',','.') ?></p>
        </div>
        <div class="flex gap-2">
          <?php if ($s==='pending'): ?>
          <form method="POST" onsubmit="return confirm('Batalkan pesanan ini?')">
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
            <button class="px-3 py-1.5 border border-red-300 text-red-500 rounded-lg text-xs font-medium">Batalkan</button>
          </form>
          <?php endif; ?>
          <?php if ($s==='delivered'): ?>
          <a href="order.php" class="px-3 py-1.5 border border-orange-400 text-orange-500 rounded-lg text-xs font-medium">Pesan Lagi</a>
          <?php endif; ?>
          <a href="history.php?detail=<?= urlencode($o['order_code']) ?>&filter=<?= $filter ?>"
            class="px-4 py-1.5 bg-orange-500 text-white rounded-lg text-xs font-semibold">Detail</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ======= MODAL DETAIL ======= -->
<?php if ($orderDetail): $od=$orderDetail; $s=$od['status']; ?>
<div class="fixed inset-0 bg-black/60 z-50 flex items-end justify-center p-0">
  <div class="bg-white rounded-t-3xl w-full max-w-md flex flex-col" style="height:90vh">

    <!-- Handle bar -->
    <div class="flex justify-center pt-3 pb-1 flex-shrink-0">
      <div class="w-10 h-1 bg-gray-200 rounded-full"></div>
    </div>

    <!-- Header -->
    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 flex-shrink-0">
      <div>
        <p class="font-bold text-gray-800"><?= htmlspecialchars($od['order_code']) ?></p>
        <p class="text-xs text-gray-400"><?= date('d M Y, H:i',strtotime($od['created_at'])) ?></p>
      </div>
      <div class="flex items-center gap-3">
        <span class="text-xs px-2.5 py-1 rounded-full font-medium <?= $badgeMap[$s]??'bg-gray-100' ?>"><?= $labelMap[$s]??$s ?></span>
        <a href="history.php?filter=<?= $filter ?>" class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-gray-500">
          <i data-lucide="x" class="w-4 h-4"></i>
        </a>
      </div>
    </div>

    <div class="p-5 space-y-4 overflow-y-auto flex-1 pb-8">

      <!-- Tracking -->
      <div class="bg-orange-50 rounded-2xl p-4">
        <p class="text-sm font-bold text-gray-700 mb-4">📍 Tracking Pesanan</p>
        <?php
        $steps = [
          'pending'    => ['label'=>'Pesanan Dibuat',  'sub'=>'Menunggu konfirmasi', 'icon'=>'shopping-bag'],
          'processing' => ['label'=>'Diproses',        'sub'=>'Sedang disiapkan',    'icon'=>'package'],
          'shipping'   => ['label'=>'Sedang Dikirim',  'sub'=>'Dalam perjalanan',    'icon'=>'truck'],
          'delivered'  => ['label'=>'Pesanan Tiba',    'sub'=>'Sudah diterima',      'icon'=>'check-circle'],
        ];
        $stepKeys = array_keys($steps);
        $cur = $s==='cancelled' ? -1 : array_search($s, $stepKeys);
        ?>
        <div class="space-y-1">
        <?php foreach ($steps as $sk => $sv):
          $idx  = array_search($sk, $stepKeys);
          $done = $cur !== -1 && $idx <= $cur;
          $now  = $done && $idx === $cur;
        ?>
        <div class="flex items-center gap-3">
          <div class="flex flex-col items-center">
            <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 <?= $done?'bg-orange-500 text-white':'bg-white text-gray-300 border-2 border-gray-200' ?>">
              <i data-lucide="<?= $sv['icon'] ?>" class="w-4 h-4"></i>
            </div>
            <?php if ($idx < count($steps)-1): ?>
            <div class="w-0.5 h-5 <?= $done && $cur > $idx ?'bg-orange-400':'bg-gray-200' ?>"></div>
            <?php endif; ?>
          </div>
          <div class="<?= !$done?'opacity-40':'' ?> pb-3">
            <p class="text-sm font-semibold <?= $now?'text-orange-600':'text-gray-700' ?>"><?= $sv['label'] ?><?= $now?' ←':'' ?></p>
            <p class="text-xs text-gray-400"><?= $sv['sub'] ?></p>
          </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php if ($s==='cancelled'): ?>
        <div class="mt-2 p-2 bg-red-50 rounded-xl text-center text-xs text-red-500 font-medium">❌ Pesanan Dibatalkan</div>
        <?php endif; ?>
      </div>

      <!-- Item pesanan -->
      <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-100">
          <p class="text-sm font-bold text-gray-700">🛒 Item Pesanan</p>
        </div>
        <div class="p-4 space-y-3">
          <?php foreach ($od['items'] as $it): ?>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center text-xl flex-shrink-0">
              <?= $it['emoji'] ?: '🥟' ?>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($it['name']) ?></p>
              <p class="text-xs text-gray-400"><?= $it['qty'] ?> × Rp <?= number_format($it['price'],0,',','.') ?></p>
            </div>
            <p class="text-sm font-bold text-orange-500 flex-shrink-0">Rp <?= number_format($it['subtotal'],0,',','.') ?></p>
          </div>
          <?php endforeach; ?>
        </div>
        <!-- Ringkasan harga -->
        <div class="border-t border-gray-100 px-4 py-3 space-y-2">
          <div class="flex justify-between text-sm text-gray-500">
            <span>Subtotal</span><span>Rp <?= number_format($od['subtotal'],0,',','.') ?></span>
          </div>
          <div class="flex justify-between text-sm text-gray-500">
            <span>Ongkos Kirim</span><span>Rp <?= number_format($od['delivery_fee'],0,',','.') ?></span>
          </div>
          <div class="flex justify-between font-bold text-base border-t border-gray-100 pt-2">
            <span>Total</span><span class="text-orange-500">Rp <?= number_format($od['total'],0,',','.') ?></span>
          </div>
        </div>
      </div>

      <!-- Info pembayaran -->
      <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-100">
          <p class="text-sm font-bold text-gray-700">💳 Info Pembayaran</p>
        </div>
        <div class="p-4 space-y-2.5">
          <div class="flex justify-between items-center">
            <span class="text-sm text-gray-500">Metode</span>
            <span class="text-sm font-bold uppercase bg-gray-100 px-3 py-1 rounded-lg"><?= $od['payment_method'] ?></span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm text-gray-500">Status Bayar</span>
            <span class="text-sm font-bold px-3 py-1 rounded-lg <?= $od['payment_status']==='paid'?'bg-green-100 text-green-700':'bg-yellow-100 text-yellow-700' ?>">
              <?= $od['payment_status']==='paid'?'✅ Lunas':'⏳ Belum Dibayar' ?>
            </span>
          </div>
          <?php if (!empty($od['address'])): ?>
          <div class="flex justify-between items-start">
            <span class="text-sm text-gray-500">Alamat</span>
            <span class="text-sm text-right max-w-[55%] text-gray-700"><?= htmlspecialchars($od['address'].', '.$od['city']) ?></span>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($s==='delivered'): ?>
      <a href="order.php" class="block w-full bg-orange-500 text-white py-3.5 rounded-2xl text-center font-bold">🥟 Pesan Lagi</a>
      <?php endif; ?>
      <?php if ($s==='pending'): ?>
      <form method="POST" onsubmit="return confirm('Batalkan pesanan ini?')">
        <input type="hidden" name="action" value="cancel">
        <input type="hidden" name="order_id" value="<?= $od['id'] ?>">
        <button class="w-full border-2 border-red-300 text-red-500 py-3 rounded-2xl font-bold">Batalkan Pesanan</button>
      </form>
      <?php endif; ?>

    </div>
  </div>
</div>
<?php endif; ?>

<?php include 'nav.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>

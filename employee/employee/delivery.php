<?php
require_once '../includes/auth.php';
requireLogin('employee');
$pageTitle = 'Pengiriman - Dimsum App';
$activeTab = 'delivery';
$pdo       = getDB();
$filter    = $_GET['filter'] ?? 'all';

// Handle update status
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del_id'],$_POST['new_status'])) {
    $s   = $_POST['new_status'];
    $col = $s==='shipping' ? ',departure_time=NOW()' : ($s==='delivered' ? ',delivery_time=NOW()' : '');
    $pdo->prepare("UPDATE deliveries SET status=?,employee_id=?,updated_at=NOW() $col WHERE id=?")->execute([$s,getUserId(),$_POST['del_id']]);
    if ($s==='shipping')  $pdo->prepare("UPDATE orders SET status='shipping',updated_at=NOW() WHERE id=?")->execute([$_POST['order_id']]);
    if ($s==='delivered') $pdo->prepare("UPDATE orders SET status='delivered',updated_at=NOW() WHERE id=?")->execute([$_POST['order_id']]);
    header("Location: " . BASE_PATH . "/employee/delivery.php?filter=$filter"); exit;
}

$sql = "SELECT d.*, o.order_code, o.total, o.payment_method, o.payment_status, o.subtotal, o.delivery_fee,
        u.name AS customer_name, u.phone AS customer_phone, a.address, a.city,
        (SELECT GROUP_CONCAT(p.name,' x',oi.qty SEPARATOR ', ') FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE oi.order_id=o.id) AS items_text
        FROM deliveries d
        JOIN orders o ON o.id=d.order_id
        JOIN users u ON u.id=o.customer_id
        LEFT JOIN addresses a ON a.id=o.address_id";
if ($filter !== 'all') $sql .= " WHERE d.status='$filter'";
$sql .= " ORDER BY d.created_at DESC";
$deliveries = $pdo->query($sql)->fetchAll();
$stats = $pdo->query("SELECT status,COUNT(*) cnt FROM deliveries GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

// Untuk detail - ambil items lengkap
$detailId = $_GET['detail'] ?? null;
$detailDel = null;
if ($detailId) {
    foreach ($deliveries as $d) {
        if ($d['id'] == $detailId) { $detailDel = $d; break; }
    }
    if ($detailDel) {
        $items = $pdo->prepare("SELECT oi.qty,oi.price,oi.subtotal,p.name,p.emoji FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE oi.order_id=?");
        $items->execute([$detailDel['order_id']]);
        $detailDel['items'] = $items->fetchAll();
    }
}
?>
<?php include '../includes/header.php'; ?>
<div class="min-h-screen bg-gray-50 pb-24"><div class="max-w-md mx-auto">

  <div class="bg-gradient-to-r from-orange-500 to-red-500 text-white p-4 rounded-b-3xl">
    <h2 class="text-xl font-bold mb-4">Manajemen Pengiriman</h2>
    <div class="grid grid-cols-3 gap-3">
      <?php foreach(['ready'=>'Siap Kirim','shipping'=>'Dikirim','delivered'=>'Selesai'] as $k=>$v): ?>
      <div class="bg-white/20 rounded-xl p-3 text-center">
        <p class="text-2xl font-bold"><?= $stats[$k]??0 ?></p>
        <p class="text-xs opacity-80"><?= $v ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="px-4 py-3 bg-white border-b border-gray-100">
    <div class="flex gap-2 overflow-x-auto">
      <?php foreach(['all'=>'Semua','ready'=>'Siap Kirim','shipping'=>'Dikirim','delivered'=>'Selesai'] as $k=>$v): ?>
      <a href="delivery.php?filter=<?= $k ?>" class="px-4 py-1.5 rounded-full whitespace-nowrap text-sm font-medium flex-shrink-0 <?= $filter===$k?'bg-orange-500 text-white':'bg-gray-100 text-gray-600' ?>"><?= $v ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="p-4 space-y-3">
    <?php if (empty($deliveries)): ?>
    <div class="text-center py-12 text-gray-400">
      <i data-lucide="truck" class="w-12 h-12 mx-auto mb-2 opacity-30"></i>
      <p class="text-sm">Tidak ada data pengiriman</p>
    </div>
    <?php endif; ?>

    <?php $bm=['ready'=>'bg-yellow-100 text-yellow-700','shipping'=>'bg-blue-100 text-blue-700','delivered'=>'bg-green-100 text-green-700']; ?>
    <?php foreach ($deliveries as $d): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <!-- Header -->
      <div class="flex items-center justify-between px-4 pt-4 pb-3 border-b border-gray-50">
        <div>
          <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($d['del_code']) ?></p>
          <p class="text-xs text-gray-400">Order: <?= htmlspecialchars($d['order_code']) ?></p>
        </div>
        <span class="text-xs px-2.5 py-1 rounded-full font-medium <?= $bm[$d['status']]??'' ?>">
          <?= ['ready'=>'Siap Dikirim','shipping'=>'Dalam Perjalanan','delivered'=>'Terkirim'][$d['status']] ?>
        </span>
      </div>

      <!-- Info Customer -->
      <div class="px-4 py-3 bg-gray-50 mx-4 my-3 rounded-xl">
        <p class="font-semibold text-sm mb-1"><?= htmlspecialchars($d['customer_name']) ?></p>
        <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
          <i data-lucide="phone" class="w-3.5 h-3.5"></i>
          <span><?= htmlspecialchars($d['customer_phone']??'-') ?></span>
        </div>
        <div class="flex items-start gap-2 text-xs text-gray-500">
          <i data-lucide="map-pin" class="w-3.5 h-3.5 mt-0.5 flex-shrink-0"></i>
          <span><?= htmlspecialchars(trim(($d['address']??'').' '.($d['city']??''))) ?: 'Alamat belum diisi' ?></span>
        </div>
      </div>

      <!-- Item pesanan ringkas -->
      <div class="px-4 pb-3">
        <p class="text-xs text-gray-400 mb-1">Pesanan:</p>
        <p class="text-sm text-gray-700"><?= htmlspecialchars($d['items_text']??'-') ?></p>
      </div>

      <!-- Waktu -->
      <?php if ($d['status']==='shipping' && $d['departure_time']): ?>
      <div class="mx-4 mb-3 px-3 py-2 bg-blue-50 rounded-xl text-xs text-blue-700 flex items-center gap-2">
        <i data-lucide="clock" class="w-3.5 h-3.5"></i> Berangkat: <?= date('d M H:i',strtotime($d['departure_time'])) ?>
      </div>
      <?php endif; ?>
      <?php if ($d['status']==='delivered' && $d['delivery_time']): ?>
      <div class="mx-4 mb-3 px-3 py-2 bg-green-50 rounded-xl text-xs text-green-700 flex items-center gap-2">
        <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Terkirim: <?= date('d M H:i',strtotime($d['delivery_time'])) ?>
      </div>
      <?php endif; ?>

      <!-- Tombol Aksi -->
      <div class="flex gap-2 px-4 pb-4">
        <?php if ($d['status']==='ready'): ?>
        <a href="delivery.php?detail=<?= $d['id'] ?>&filter=<?= $filter ?>"
          class="flex-1 border border-orange-400 text-orange-500 py-2 rounded-xl text-sm text-center font-medium">
          📋 Detail
        </a>
        <form method="POST" class="flex-1">
          <input type="hidden" name="del_id" value="<?= $d['id'] ?>">
          <input type="hidden" name="order_id" value="<?= $d['order_id'] ?>">
          <input type="hidden" name="new_status" value="shipping">
          <button class="w-full bg-orange-500 text-white py-2 rounded-xl text-sm font-semibold">🚀 Mulai Kirim</button>
        </form>

        <?php elseif ($d['status']==='shipping'): ?>
        <a href="delivery.php?detail=<?= $d['id'] ?>&filter=<?= $filter ?>"
          class="flex-1 border border-blue-400 text-blue-500 py-2 rounded-xl text-sm text-center font-medium">
          📋 Detail
        </a>
        <form method="POST" class="flex-1">
          <input type="hidden" name="del_id" value="<?= $d['id'] ?>">
          <input type="hidden" name="order_id" value="<?= $d['order_id'] ?>">
          <input type="hidden" name="new_status" value="delivered">
          <button class="w-full bg-green-500 text-white py-2 rounded-xl text-sm font-semibold">✅ Tandai Terkirim</button>
        </form>

        <?php else: ?>
        <a href="delivery.php?detail=<?= $d['id'] ?>&filter=<?= $filter ?>"
          class="flex-1 bg-gray-100 text-gray-600 py-2 rounded-xl text-sm text-center font-medium">
          📋 Lihat Detail
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ===== MODAL DETAIL PENGIRIMAN ===== -->
<?php if ($detailDel): $d=$detailDel; ?>
<div class="fixed inset-0 bg-black/60 z-50 flex items-end justify-center">
  <div class="bg-white rounded-t-3xl w-full max-w-md flex flex-col" style="height:92vh">
    <div class="flex-shrink-0 px-5 pt-3 pb-4 border-b border-gray-100">
      <div class="flex justify-center mb-3"><div class="w-10 h-1 bg-gray-200 rounded-full"></div></div>
      <div class="flex items-center justify-between">
        <div>
          <p class="font-bold"><?= htmlspecialchars($d['del_code']) ?></p>
          <p class="text-xs text-gray-400">Order: <?= htmlspecialchars($d['order_code']) ?></p>
        </div>
        <a href="delivery.php?filter=<?= $filter ?>"
          class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-gray-500">
          <i data-lucide="x" class="w-4 h-4"></i>
        </a>
      </div>
    </div>

    <div class="flex-1 overflow-y-auto p-5 space-y-4">
      <!-- Status -->
      <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
        <span class="text-sm text-gray-600">Status Pengiriman</span>
        <span class="text-sm font-bold px-3 py-1 rounded-xl <?= $bm[$d['status']]??'' ?>">
          <?= ['ready'=>'Siap Dikirim','shipping'=>'Dalam Perjalanan','delivered'=>'✅ Terkirim'][$d['status']] ?>
        </span>
      </div>

      <!-- Info Customer -->
      <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
        <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-100">
          <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">👤 Info Pelanggan</p>
        </div>
        <div class="p-4 space-y-2">
          <div class="flex justify-between"><span class="text-sm text-gray-500">Nama</span><span class="text-sm font-semibold"><?= htmlspecialchars($d['customer_name']) ?></span></div>
          <div class="flex justify-between"><span class="text-sm text-gray-500">Telepon</span><span class="text-sm font-semibold"><?= htmlspecialchars($d['customer_phone']??'-') ?></span></div>
          <div class="flex justify-between items-start gap-2"><span class="text-sm text-gray-500 flex-shrink-0">Alamat</span><span class="text-sm font-semibold text-right"><?= htmlspecialchars(trim(($d['address']??'').' '.($d['city']??''))) ?: '-' ?></span></div>
        </div>
      </div>

      <!-- Item Pesanan -->
      <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
        <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-100">
          <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">🛒 Item Pesanan</p>
        </div>
        <div class="p-4 space-y-3">
          <?php foreach ($d['items'] as $it): ?>
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-orange-50 rounded-xl flex items-center justify-center text-lg flex-shrink-0"><?= $it['emoji']?:'🥟' ?></div>
            <div class="flex-1">
              <p class="text-sm font-semibold"><?= htmlspecialchars($it['name']) ?></p>
              <p class="text-xs text-gray-400"><?= $it['qty'] ?> × Rp <?= number_format($it['price'],0,',','.') ?></p>
            </div>
            <p class="text-sm font-bold text-orange-500">Rp <?= number_format($it['subtotal'],0,',','.') ?></p>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="border-t border-gray-50 px-4 py-3 space-y-1.5">
          <div class="flex justify-between text-sm text-gray-400"><span>Subtotal</span><span>Rp <?= number_format($d['subtotal'],0,',','.') ?></span></div>
          <div class="flex justify-between text-sm text-gray-400"><span>Ongkir</span><span>Rp <?= number_format($d['delivery_fee'],0,',','.') ?></span></div>
          <div class="flex justify-between text-sm font-bold"><span>Total</span><span class="text-orange-500">Rp <?= number_format($d['total'],0,',','.') ?></span></div>
        </div>
      </div>

      <!-- Pembayaran -->
      <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
        <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-100">
          <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">💳 Pembayaran</p>
        </div>
        <div class="p-4 space-y-2">
          <div class="flex justify-between items-center">
            <span class="text-sm text-gray-500">Metode</span>
            <span class="text-sm font-bold bg-gray-100 px-3 py-1 rounded-lg uppercase"><?= $d['payment_method'] ?></span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm text-gray-500">Status</span>
            <span class="text-sm font-bold px-3 py-1 rounded-lg <?= $d['payment_status']==='paid'?'bg-green-100 text-green-700':'bg-yellow-100 text-yellow-700' ?>">
              <?= $d['payment_status']==='paid'?'✅ Lunas':'⏳ Belum Dibayar' ?>
            </span>
          </div>
        </div>
      </div>

      <!-- Waktu -->
      <?php if ($d['departure_time'] || $d['delivery_time']): ?>
      <div class="bg-white rounded-2xl border border-gray-100 p-4 space-y-2">
        <?php if ($d['departure_time']): ?>
        <div class="flex justify-between text-sm"><span class="text-gray-500">Waktu Berangkat</span><span class="font-semibold"><?= date('d M Y, H:i',strtotime($d['departure_time'])) ?></span></div>
        <?php endif; ?>
        <?php if ($d['delivery_time']): ?>
        <div class="flex justify-between text-sm"><span class="text-gray-500">Waktu Sampai</span><span class="font-semibold text-green-600"><?= date('d M Y, H:i',strtotime($d['delivery_time'])) ?></span></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Aksi dari modal -->
      <?php if ($d['status']==='ready'): ?>
      <form method="POST">
        <input type="hidden" name="del_id" value="<?= $d['id'] ?>">
        <input type="hidden" name="order_id" value="<?= $d['order_id'] ?>">
        <input type="hidden" name="new_status" value="shipping">
        <button class="w-full bg-orange-500 text-white py-3.5 rounded-2xl font-bold">🚀 Mulai Pengiriman</button>
      </form>
      <?php elseif ($d['status']==='shipping'): ?>
      <form method="POST">
        <input type="hidden" name="del_id" value="<?= $d['id'] ?>">
        <input type="hidden" name="order_id" value="<?= $d['order_id'] ?>">
        <input type="hidden" name="new_status" value="delivered">
        <button class="w-full bg-green-500 text-white py-3.5 rounded-2xl font-bold">✅ Tandai Sudah Terkirim</button>
      </form>
      <?php else: ?>
      <div class="w-full bg-green-100 text-green-700 py-3.5 rounded-2xl font-bold text-center">✅ Pesanan Sudah Terkirim</div>
      <?php endif; ?>

    </div>
  </div>
</div>
<?php endif; ?>

<?php include 'nav.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>

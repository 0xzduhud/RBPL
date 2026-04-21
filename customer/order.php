<?php
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
requireLogin('customer');
$pageTitle = 'Pesan - Dimsum App';
$activeTab = 'order';
$pdo       = getDB();
$userId    = getUserId();

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_cart') {
        $id  = (int)$_POST['id'];
        $qty = (int)($_POST['qty'] ?? 0);
        if ($qty <= 0) unset($_SESSION['cart'][$id]);
        else           $_SESSION['cart'][$id] = $qty;
        header('Location: ' . BASE_PATH . '/customer/order.php?step=' . ($_POST['step'] ?? 'select') . '&type=' . ($_POST['type_filter'] ?? 'matang'));
        exit;
    }

    if ($action === 'place_order') {
        $cart   = $_SESSION['cart'] ?? [];
        $method = $_POST['payment_method'] ?? 'cash';
        $notes  = trim($_POST['notes'] ?? '');
        if (empty($cart)) { header('Location: ' . BASE_PATH . '/customer/order.php'); exit; }

        // Hitung total
        $ids   = array_keys($cart);
        $in    = implode(',', array_fill(0, count($ids), '?'));
        $stmt  = $pdo->prepare("SELECT id, price, name, emoji FROM products WHERE id IN ($in) AND status='active'");
        $stmt->execute($ids);
        $prods = [];
        foreach ($stmt->fetchAll() as $p) $prods[$p['id']] = $p;

        $subtotal = 0;
        foreach ($cart as $pid => $qty) $subtotal += ($prods[$pid]['price'] ?? 0) * $qty;
        $delivery_fee = 15000;
        $total        = $subtotal + $delivery_fee;
        $code         = 'ORD-' . strtoupper(substr(uniqid(), -6));

        // Alamat
        $addrStmt = $pdo->prepare("SELECT id, address, city FROM addresses WHERE user_id=? AND is_default=1 LIMIT 1");
        $addrStmt->execute([$userId]);
        $addr = $addrStmt->fetch();

        $pstatus = $method === 'qris' ? 'paid' : 'pending';

        // Insert order
        $pdo->prepare("INSERT INTO orders (order_code,customer_id,address_id,delivery_method,payment_method,payment_status,status,subtotal,delivery_fee,total,notes) VALUES (?,?,?,'delivery',?,?,?,?,?,?,?)")
            ->execute([$code, $userId, $addr['id']??null, $method, $pstatus, 'pending', $subtotal, $delivery_fee, $total, $notes]);
        $orderId = $pdo->lastInsertId();

        // Insert items + kurangi stok
        $ins = $pdo->prepare("INSERT INTO order_items (order_id,product_id,qty,price,subtotal) VALUES (?,?,?,?,?)");
        foreach ($cart as $pid => $qty) {
            $p = $prods[$pid]['price'] ?? 0;
            $ins->execute([$orderId, $pid, $qty, $p, $p * $qty]);
            $pdo->prepare("UPDATE products SET stock=GREATEST(0,stock-?), updated_at=NOW() WHERE id=?")->execute([$qty, $pid]);
        }

        // PB-07: Catat transaksi otomatis (try-catch jika tabel belum ada)
        try {
            $txCode = 'TRX-' . strtoupper(substr(uniqid(), -8));
            $pdo->prepare("INSERT INTO transactions (order_id,transaction_code,amount,payment_method,payment_status,notes) VALUES (?,?,?,?,?,?)")
                ->execute([$orderId, $txCode, $total, $method, $pstatus, 'Auto-recorded on order placement']);
        } catch (Exception $e) { /* tabel belum dibuat, skip */ }

        // Auto-create production order
        $prodCode = 'PROD-' . strtoupper(substr(uniqid(), -6));
        $deadline = date('Y-m-d H:i:s', strtotime('+2 hours'));
        $pdo->prepare("INSERT INTO production_orders (prod_code,order_id,status,priority,progress,deadline) VALUES (?,?,'pending','high',0,?)")
            ->execute([$prodCode, $orderId, $deadline]);
        $prodId = $pdo->lastInsertId();

        $insPI = $pdo->prepare("INSERT INTO production_items (production_order_id,product_id,qty) VALUES (?,?,?)");
        foreach ($cart as $pid => $qty) $insPI->execute([$prodId, $pid, $qty]);

        // Auto-create delivery
        $delCode = 'DEL-' . strtoupper(substr(uniqid(), -6));
        $pdo->prepare("INSERT INTO deliveries (del_code,order_id,status,estimated_time) VALUES (?,?,'ready','30-60 menit')")
            ->execute([$delCode, $orderId]);

        // PB-08: Notifikasi
        addNotification("Pesanan $code berhasil! Estimasi 30-60 menit.", 'success');

        $_SESSION['cart']       = [];
        $_SESSION['last_order'] = $code;
        header("Location: " . BASE_PATH . "/customer/order.php?step=confirm&order=" . urlencode($code));
        exit;
    }
}

// ── Data ──────────────────────────────────────────────────────
$step       = $_GET['step']  ?? 'select';
$typeFilter = $_GET['type']  ?? 'matang';
$cart       = $_SESSION['cart'] ?? [];

$matang  = $pdo->query("SELECT p.*, c.type AS cat_type FROM products p JOIN categories c ON c.id=p.category_id WHERE c.type='matang' AND p.status='active' ORDER BY p.name")->fetchAll();
$frozen  = $pdo->query("SELECT p.*, c.type AS cat_type FROM products p JOIN categories c ON c.id=p.category_id WHERE c.type='frozen' AND p.status='active' ORDER BY p.name")->fetchAll();
$allProds = array_merge($matang, $frozen);
$prodById = [];
foreach ($allProds as $p) $prodById[$p['id']] = $p;

$subtotal    = 0;
$deliveryFee = 15000;
foreach ($cart as $id => $qty)
    if (isset($prodById[$id])) $subtotal += $prodById[$id]['price'] * $qty;
$total     = $subtotal + $deliveryFee;
$cartCount = array_sum($cart);
$list      = $typeFilter === 'frozen' ? $frozen : $matang;

// Alamat customer
$addrRow = $pdo->prepare("SELECT * FROM addresses WHERE user_id=? AND is_default=1 LIMIT 1");
$addrRow->execute([$userId]);
$alamat  = $addrRow->fetch();
?>
<?php include '../includes/header.php'; ?>
<div class="min-h-screen bg-gray-50 pb-20"><div class="max-w-md mx-auto">

<?php /* ======== STEP: SELECT ======== */ if ($step === 'select'): ?>
<div class="bg-white border-b border-gray-200 p-4 sticky top-0 z-10 flex items-center gap-3">
  <a href="index.php" class="text-gray-400"><i data-lucide="arrow-left" class="w-5 h-5"></i></a>
  <h2 class="text-lg font-semibold flex-1 text-center">Pesan Dimsum</h2>
  <span class="w-5"></span>
</div>

<div class="p-4">
  <div class="flex gap-2 mb-5">
    <a href="order.php?type=matang" class="flex-1 py-3 rounded-xl border-2 text-center text-sm font-medium transition-all <?= $typeFilter==='matang'?'border-orange-500 bg-orange-50 text-orange-700':'border-gray-200 text-gray-500' ?>">
      🥟 Dimsum Matang
    </a>
    <a href="order.php?type=frozen" class="flex-1 py-3 rounded-xl border-2 text-center text-sm font-medium transition-all <?= $typeFilter==='frozen'?'border-orange-500 bg-orange-50 text-orange-700':'border-gray-200 text-gray-500' ?>">
      ❄️ Dimsum Frozen
    </a>
  </div>

  <?php if (empty($list)): ?>
  <div class="text-center py-12 text-gray-400">
    <p class="text-4xl mb-3">🥟</p>
    <p class="text-sm">Menu belum tersedia.</p>
    <p class="text-xs mt-1">Silakan hubungi pemilik untuk menambahkan menu.</p>
  </div>
  <?php endif; ?>

  <div class="space-y-3 mb-32">
    <?php foreach ($list as $p):
      $inCart = $cart[$p['id']] ?? 0;
      $habis  = $p['stock'] <= 0;
    ?>
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 <?= $habis?'opacity-60':'' ?>">
      <div class="flex gap-3 mb-3">
        <div class="w-16 h-16 bg-orange-50 rounded-xl flex items-center justify-center text-4xl flex-shrink-0">
          <?= $p['emoji'] ?: '🥟' ?>
        </div>
        <div class="flex-1 min-w-0">
          <h4 class="font-semibold text-gray-800 mb-0.5"><?= htmlspecialchars($p['name']) ?></h4>
          <?php if ($p['description']): ?>
          <p class="text-xs text-gray-400 mb-1 line-clamp-2"><?= htmlspecialchars($p['description']) ?></p>
          <?php endif; ?>
          <div class="flex items-center justify-between">
            <p class="text-orange-500 font-bold">Rp <?= number_format($p['price'],0,',','.') ?></p>
            <span class="text-xs <?= $habis?'text-red-500 bg-red-50':($p['stock']<=$p['min_stock']?'text-yellow-600 bg-yellow-50':'text-green-600 bg-green-50') ?> px-2 py-0.5 rounded-full">
              <?= $habis ? 'Habis' : ($p['stock']<=$p['min_stock']?'Terbatas ('.$p['stock'].')':'Tersedia') ?>
            </span>
          </div>
        </div>
      </div>

      <?php if (!$habis): ?>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-1 bg-gray-100 rounded-xl p-1">
          <form method="POST" class="inline">
            <input type="hidden" name="action" value="update_cart">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="hidden" name="qty" value="<?= max(0,$inCart-1) ?>">
            <input type="hidden" name="step" value="select">
            <input type="hidden" name="type_filter" value="<?= $typeFilter ?>">
            <button type="submit" class="w-9 h-9 bg-white rounded-lg flex items-center justify-center shadow-sm <?= $inCart<=0?'opacity-30':'' ?>" <?= $inCart<=0?'disabled':'' ?>>
              <i data-lucide="minus" class="w-4 h-4 text-orange-500"></i>
            </button>
          </form>
          <span class="w-8 text-center font-bold text-sm"><?= $inCart ?></span>
          <form method="POST" class="inline">
            <input type="hidden" name="action" value="update_cart">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="hidden" name="qty" value="<?= $inCart+1 ?>">
            <input type="hidden" name="step" value="select">
            <input type="hidden" name="type_filter" value="<?= $typeFilter ?>">
            <button type="submit" class="w-9 h-9 bg-orange-500 rounded-lg flex items-center justify-center shadow-sm">
              <i data-lucide="plus" class="w-4 h-4 text-white"></i>
            </button>
          </form>
        </div>
        <?php if ($inCart > 0): ?>
        <p class="text-sm font-semibold text-orange-500">= Rp <?= number_format($p['price']*$inCart,0,',','.') ?></p>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="bg-red-50 rounded-xl py-2 text-center text-xs text-red-500 font-medium">Stok Habis</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($cartCount > 0): ?>
<div class="fixed bottom-20 left-0 right-0 max-w-md mx-auto px-4 z-40">
  <a href="order.php?step=detail" class="flex items-center justify-between w-full bg-orange-500 text-white px-5 py-4 rounded-2xl shadow-xl">
    <span class="bg-white/20 text-white text-xs font-bold px-2 py-1 rounded-lg"><?= $cartCount ?> item</span>
    <span class="font-semibold">Lihat Keranjang</span>
    <span class="font-bold">Rp <?= number_format($subtotal,0,',','.') ?></span>
  </a>
</div>
<?php endif; ?>

<?php /* ======== STEP: DETAIL ======== */ elseif ($step === 'detail'): ?>
<div class="bg-white border-b border-gray-200 p-4 sticky top-0 z-10 flex items-center gap-3">
  <a href="order.php?step=select" class="text-gray-400"><i data-lucide="arrow-left" class="w-5 h-5"></i></a>
  <h2 class="text-lg font-semibold">Detail Pesanan</h2>
</div>

<div class="p-4 space-y-4 mb-36">
  <?php if (empty($cart)): ?>
  <div class="text-center py-10 text-gray-400"><p class="text-4xl mb-2">🛒</p><p>Keranjang kosong</p><a href="order.php" class="mt-3 inline-block text-orange-500 underline text-sm">Pilih Menu</a></div>
  <?php else: ?>

  <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-4 py-3 bg-gray-50 border-b border-gray-100">
      <h3 class="font-semibold text-sm">🛒 Pesanan Anda</h3>
    </div>
    <div class="p-4 space-y-3">
      <?php foreach ($cart as $id => $qty): if (!isset($prodById[$id])) continue; $p = $prodById[$id]; ?>
      <div class="flex items-center gap-3">
        <span class="text-2xl"><?= $p['emoji'] ?: '🥟' ?></span>
        <div class="flex-1">
          <p class="text-sm font-medium"><?= htmlspecialchars($p['name']) ?></p>
          <p class="text-xs text-gray-400">Rp <?= number_format($p['price'],0,',','.') ?> × <?= $qty ?></p>
        </div>
        <div class="text-right">
          <p class="text-sm font-bold text-orange-500">Rp <?= number_format($p['price']*$qty,0,',','.') ?></p>
          <div class="flex items-center gap-1 mt-1">
            <form method="POST" class="inline"><input type="hidden" name="action" value="update_cart"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="qty" value="<?= max(0,$qty-1) ?>"><input type="hidden" name="step" value="detail">
              <button class="w-6 h-6 bg-gray-100 rounded text-xs flex items-center justify-center"><i data-lucide="minus" class="w-3 h-3"></i></button></form>
            <span class="text-xs font-bold w-4 text-center"><?= $qty ?></span>
            <form method="POST" class="inline"><input type="hidden" name="action" value="update_cart"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="qty" value="<?= $qty+1 ?>"><input type="hidden" name="step" value="detail">
              <button class="w-6 h-6 bg-orange-500 rounded text-xs flex items-center justify-center"><i data-lucide="plus" class="w-3 h-3 text-white"></i></button></form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-gray-200 p-4">
    <h3 class="font-semibold text-sm mb-3">📍 Alamat Pengiriman</h3>
    <?php if ($alamat && $alamat['address']): ?>
    <p class="text-sm text-gray-700"><?= htmlspecialchars($alamat['address'].', '.$alamat['city']) ?></p>
    <?php else: ?>
    <p class="text-sm text-gray-400 italic">Belum ada alamat. <a href="profile.php" class="text-orange-500 underline">Tambah di profil</a></p>
    <?php endif; ?>
    <p class="text-xs text-gray-400 mt-1">Estimasi: 30-60 menit</p>
  </div>

  <div class="bg-white rounded-2xl border border-gray-200 p-4">
    <h3 class="font-semibold text-sm mb-2">📝 Catatan (opsional)</h3>
    <textarea id="notesField" rows="2" placeholder="cth: jangan pedas, tambah saus sambal..." class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-orange-500 outline-none resize-none text-gray-700"></textarea>
  </div>

  <div class="bg-white rounded-2xl border border-gray-200 p-4">
    <h3 class="font-semibold text-sm mb-3">💰 Ringkasan</h3>
    <div class="space-y-2 text-sm">
      <div class="flex justify-between"><span class="text-gray-500">Subtotal (<?= $cartCount ?> item)</span><span>Rp <?= number_format($subtotal,0,',','.') ?></span></div>
      <div class="flex justify-between"><span class="text-gray-500">Ongkos Kirim</span><span>Rp <?= number_format($deliveryFee,0,',','.') ?></span></div>
      <div class="flex justify-between pt-2 border-t border-gray-100 font-bold"><span>Total</span><span class="text-orange-500 text-base">Rp <?= number_format($total,0,',','.') ?></span></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if ($cartCount > 0): ?>
<div class="fixed bottom-20 left-0 right-0 max-w-md mx-auto bg-white border-t border-gray-200 p-4 z-40">
  <div class="flex justify-between mb-3 text-sm"><span class="text-gray-500">Total Bayar</span><span class="text-orange-500 font-bold text-lg">Rp <?= number_format($total,0,',','.') ?></span></div>
  <button onclick="goPayment()" class="w-full bg-orange-500 text-white py-3 rounded-xl font-semibold">Pilih Metode Bayar →</button>
</div>
<script>
function goPayment(){
  const notes = document.getElementById('notesField')?.value||'';
  sessionStorage.setItem('orderNotes', notes);
  window.location.href='order.php?step=payment';
}
</script>
<?php endif; ?>

<?php /* ======== STEP: PAYMENT ======== */ elseif ($step === 'payment'): ?>
<div class="bg-white border-b border-gray-200 p-4 sticky top-0 z-10 flex items-center gap-3">
  <a href="order.php?step=detail" class="text-gray-400"><i data-lucide="arrow-left" class="w-5 h-5"></i></a>
  <h2 class="text-lg font-semibold">Pilih Pembayaran</h2>
</div>

<div class="p-4 space-y-4 mb-36">
  <!-- Ringkasan mini -->
  <div class="bg-orange-50 rounded-2xl p-4 flex justify-between items-center">
    <div><p class="text-xs text-gray-500">Total Pembayaran</p><p class="text-xl font-bold text-orange-500">Rp <?= number_format($total,0,',','.') ?></p></div>
    <div class="text-3xl">💳</div>
  </div>

  <!-- QRIS -->
  <div id="qrisOpt" onclick="selPay('qris')"
    class="w-full p-4 rounded-2xl border-2 border-orange-500 bg-orange-50 cursor-pointer transition-all">
    <div class="flex items-center gap-3">
      <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
        <i data-lucide="qr-code" class="w-6 h-6 text-blue-600"></i>
      </div>
      <div class="flex-1">
        <p class="font-semibold">QRIS</p>
        <p class="text-xs text-gray-500">Bayar lewat scan QR — langsung lunas</p>
      </div>
      <div id="qrisChk" class="w-6 h-6 bg-orange-500 rounded-full flex items-center justify-center">
        <i data-lucide="check" class="w-3 h-3 text-white"></i>
      </div>
    </div>
  </div>

  <!-- QR Code tampil -->
  <div id="qrisBox" class="bg-white rounded-2xl border border-gray-200 p-5 text-center">
    <p class="text-sm font-semibold mb-3 text-gray-700">Scan QR untuk membayar</p>
    <div class="bg-gray-50 rounded-xl p-4 mb-3 inline-block">
      <div class="w-40 h-40 mx-auto border-4 border-gray-200 rounded-xl flex items-center justify-center bg-white">
        <i data-lucide="qr-code" class="w-28 h-28 text-gray-700"></i>
      </div>
    </div>
    <p class="text-xs text-gray-400 mb-1">Total</p>
    <p class="text-2xl font-bold text-orange-500 mb-1">Rp <?= number_format($total,0,',','.') ?></p>
    <p class="text-xs text-gray-400">Buka aplikasi m-banking atau e-wallet</p>
  </div>

  <!-- CASH -->
  <div id="cashOpt" onclick="selPay('cash')"
    class="w-full p-4 rounded-2xl border-2 border-gray-200 bg-white cursor-pointer transition-all">
    <div class="flex items-center gap-3">
      <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
        <i data-lucide="banknote" class="w-6 h-6 text-green-600"></i>
      </div>
      <div class="flex-1">
        <p class="font-semibold">Tunai (COD)</p>
        <p class="text-xs text-gray-500">Bayar langsung ke kurir saat pesanan tiba</p>
      </div>
      <div id="cashChk" class="hidden w-6 h-6 bg-orange-500 rounded-full items-center justify-center">
        <i data-lucide="check" class="w-3 h-3 text-white"></i>
      </div>
    </div>
  </div>

  <!-- COD info -->
  <div id="cashBox" class="hidden bg-amber-50 rounded-2xl border border-amber-200 p-4">
    <div class="flex gap-3">
      <span class="text-2xl">💵</span>
      <div>
        <p class="font-semibold text-sm mb-1">Pembayaran Tunai</p>
        <p class="text-sm text-gray-600">Siapkan uang <span class="font-bold text-orange-500">Rp <?= number_format($total,0,',','.') ?></span> saat kurir tiba.</p>
        <p class="text-xs text-gray-400 mt-1">Pastikan ada uang pas untuk kemudahan transaksi.</p>
      </div>
    </div>
  </div>
</div>

<div class="fixed bottom-20 left-0 right-0 max-w-md mx-auto bg-white border-t border-gray-200 p-4 z-40">
  <form method="POST" id="orderForm">
    <input type="hidden" name="action" value="place_order">
    <input type="hidden" name="payment_method" id="payMethod" value="qris">
    <input type="hidden" name="notes" id="notesHidden" value="">
    <button type="submit" id="btnKonfirmasi"
      class="w-full bg-orange-500 text-white py-3 rounded-xl font-bold text-base">
      ✅ Konfirmasi Pesanan — Rp <?= number_format($total,0,',','.') ?>
    </button>
  </form>
</div>

<script>
// Ambil catatan dari sessionStorage
document.getElementById('notesHidden').value = sessionStorage.getItem('orderNotes') || '';

function selPay(t) {
  const isQris = t === 'qris';
  document.getElementById('qrisOpt').className = 'w-full p-4 rounded-2xl border-2 cursor-pointer transition-all ' + (isQris ? 'border-orange-500 bg-orange-50' : 'border-gray-200 bg-white');
  document.getElementById('cashOpt').className = 'w-full p-4 rounded-2xl border-2 cursor-pointer transition-all ' + (!isQris ? 'border-orange-500 bg-orange-50' : 'border-gray-200 bg-white');
  document.getElementById('qrisChk').className = (isQris ? 'flex' : 'hidden') + ' w-6 h-6 bg-orange-500 rounded-full items-center justify-center';
  document.getElementById('cashChk').className = (!isQris ? 'flex' : 'hidden') + ' w-6 h-6 bg-orange-500 rounded-full items-center justify-center';
  document.getElementById('qrisBox').className = (isQris ? '' : 'hidden ') + 'bg-white rounded-2xl border border-gray-200 p-5 text-center';
  document.getElementById('cashBox').className = (!isQris ? '' : 'hidden ') + 'bg-amber-50 rounded-2xl border border-amber-200 p-4';
  document.getElementById('payMethod').value = t;
  lucide.createIcons();
}
</script>

<?php /* ======== STEP: CONFIRM ======== */ elseif ($step === 'confirm'): ?>
<div class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-orange-50 to-red-50">
  <div class="bg-white rounded-3xl p-6 max-w-sm w-full text-center shadow-2xl">
    <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
      <span class="text-5xl">✅</span>
    </div>
    <h2 class="text-xl font-bold mb-1 text-gray-800">Pesanan Berhasil!</h2>
    <p class="text-gray-500 text-sm mb-5">Pesanan kamu sudah masuk & sedang diproses</p>

    <div class="bg-orange-50 rounded-2xl p-4 mb-5 text-left space-y-2">
      <div class="flex justify-between text-sm">
        <span class="text-gray-500">No. Pesanan</span>
        <span class="font-bold text-orange-600"><?= htmlspecialchars($_GET['order']??'-') ?></span>
      </div>
      <div class="flex justify-between text-sm">
        <span class="text-gray-500">Status</span>
        <span class="text-yellow-600 font-medium">⏳ Menunggu Produksi</span>
      </div>
      <div class="flex justify-between text-sm">
        <span class="text-gray-500">Estimasi</span>
        <span class="font-medium">30-60 menit</span>
      </div>
    </div>

    <!-- Progress visual -->
    <div class="flex items-center justify-between mb-6 px-2">
      <?php
      $trackSteps = [
        ['icon'=>'shopping-bag','label'=>'Pesan','done'=>true],
        ['icon'=>'chef-hat','label'=>'Produksi','done'=>false],
        ['icon'=>'truck','label'=>'Kirim','done'=>false],
        ['icon'=>'check-circle','label'=>'Tiba','done'=>false],
      ];
      ?>
      <?php foreach ($trackSteps as $i => $ts): ?>
      <div class="flex flex-col items-center flex-1">
        <div class="w-8 h-8 rounded-full flex items-center justify-center mb-1 <?= $ts['done']?'bg-orange-500 text-white':'bg-gray-200 text-gray-400' ?>">
          <i data-lucide="<?= $ts['icon'] ?>" class="w-4 h-4"></i>
        </div>
        <p class="text-xs <?= $ts['done']?'text-orange-500 font-medium':'text-gray-400' ?>"><?= $ts['label'] ?></p>
      </div>
      <?php if ($i < count($trackSteps)-1): ?>
      <div class="h-px bg-gray-200 flex-1 mb-4"></div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <a href="history.php" class="block border-2 border-orange-500 text-orange-500 py-3 rounded-xl font-semibold text-sm">Lacak Pesanan</a>
      <a href="index.php" class="block bg-orange-500 text-white py-3 rounded-xl font-semibold text-sm">Kembali</a>
    </div>
  </div>
</div>
<?php endif; ?>

</div></div>
<?php include 'nav.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>

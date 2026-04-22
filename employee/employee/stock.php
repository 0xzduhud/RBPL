<?php
require_once '../includes/auth.php';
requireLogin('employee');
$pageTitle = 'Stok - Dimsum App';
$activeTab = 'stock';
$pdo       = getDB();
$tab       = $_GET['tab'] ?? 'materials';
$msg       = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $type   = $_POST['type']   ?? '';
    $id     = (int)($_POST['item_id'] ?? 0);
    $change = (int)($_POST['change'] ?? 0);
    $reason = $_POST['reason'] ?? 'Manual update';
    if ($type === 'product') {
        $pdo->prepare("UPDATE products SET stock=GREATEST(0,stock+?), updated_at=NOW() WHERE id=?")->execute([$change,$id]);
    } else {
        $pdo->prepare("UPDATE raw_materials SET stock=GREATEST(0,stock+?), updated_at=NOW() WHERE id=?")->execute([$change,$id]);
    }
    $pdo->prepare("INSERT INTO stock_logs (type,item_id,change,reason,user_id) VALUES (?,?,?,?,?)")->execute([$type,$id,$change,$reason,getUserId()]);
    $msg = ($change > 0 ? '✅ Stok berhasil ditambah.' : '✅ Stok berhasil dikurangi.');
    header("Location: " . BASE_PATH . "/employee/stock.php?tab=$tab&msg=".urlencode($msg)); exit;
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

$data = [];
if ($tab === 'materials') {
    $data = $pdo->query("SELECT * FROM raw_materials ORDER BY name")->fetchAll();
    foreach ($data as &$d) { $d['emoji'] = '🧂'; $d['status'] = $d['stock'] < $d['min_stock'] ? 'low' : 'good'; }
} else {
    $data = $pdo->query("SELECT * FROM products WHERE status='active' ORDER BY name")->fetchAll();
    foreach ($data as &$d) { $d['status'] = $d['stock'] < $d['min_stock'] ? 'low' : 'good'; }
}
$lowCount = count(array_filter($data, fn($i)=>$i['status']==='low'));
?>
<?php include '../includes/header.php'; ?>
<div class="min-h-screen bg-gray-50 pb-24"><div class="max-w-md mx-auto">

  <div class="bg-gradient-to-r from-orange-500 to-red-500 text-white p-4 rounded-b-3xl">
    <h2 class="text-xl font-bold mb-3">Manajemen Stok</h2>
    <?php if ($lowCount>0): ?>
    <div class="bg-white/20 rounded-xl p-3 flex items-center gap-3">
      <i data-lucide="alert-triangle" class="w-5 h-5 flex-shrink-0"></i>
      <div><p class="text-sm font-semibold">⚠️ <?= $lowCount ?> item stok menipis!</p><p class="text-xs opacity-80">Segera lakukan restock</p></div>
    </div>
    <?php else: ?>
    <div class="bg-white/20 rounded-xl p-3 flex items-center gap-3">
      <i data-lucide="check-circle" class="w-5 h-5"></i>
      <p class="text-sm">Semua stok dalam kondisi aman</p>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($msg): ?>
  <div class="mx-4 mt-4 p-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-700"><?= $msg ?></div>
  <?php endif; ?>

  <div class="bg-white border-b border-gray-100 px-4 py-3">
    <div class="flex gap-2">
      <a href="stock.php?tab=materials" class="flex-1 py-2.5 rounded-xl text-center text-sm font-semibold <?= $tab==='materials'?'bg-orange-500 text-white':'bg-gray-100 text-gray-600' ?>">🧂 Bahan Baku</a>
      <a href="stock.php?tab=products"  class="flex-1 py-2.5 rounded-xl text-center text-sm font-semibold <?= $tab==='products' ?'bg-orange-500 text-white':'bg-gray-100 text-gray-600' ?>">📦 Produk Jadi</a>
    </div>
  </div>

  <div class="p-4 space-y-3">
    <?php if (empty($data)): ?>
    <div class="text-center py-12 text-gray-400">
      <p class="text-4xl mb-2"><?= $tab==='materials'?'🧂':'📦' ?></p>
      <p class="text-sm font-medium">Belum ada data <?= $tab==='materials'?'bahan baku':'produk' ?></p>
      <?php if ($tab==='materials'): ?>
      <p class="text-xs mt-1 text-gray-300">Jalankan setup_products.php untuk isi data</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php foreach ($data as $item):
      $pct = $item['min_stock'] > 0 ? min(($item['stock']/$item['min_stock'])*100,100) : 100;
      $low = $item['status']==='low';
    ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <!-- Header item -->
      <div class="flex items-center gap-3 p-4 pb-3">
        <div class="w-11 h-11 bg-orange-50 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">
          <?= isset($item['emoji']) ? $item['emoji'] : '📦' ?>
        </div>
        <div class="flex-1 min-w-0">
          <p class="font-bold text-sm text-gray-800 truncate"><?= htmlspecialchars($item['name']) ?></p>
          <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $low?'bg-red-100 text-red-600':'bg-green-100 text-green-600' ?>">
            <?= $low?'⚠️ Stok Menipis':'✅ Stok Aman' ?>
          </span>
        </div>
        <button onclick="bukaUpdate(<?= $item['id'] ?>,'<?= $tab==='materials'?'material':'product' ?>','<?= addslashes($item['name']) ?>',<?= $item['stock'] ?>,'<?= $item['unit'] ?>')"
          class="w-9 h-9 bg-orange-50 text-orange-500 rounded-xl flex items-center justify-center flex-shrink-0">
          <i data-lucide="edit-2" class="w-4 h-4"></i>
        </button>
      </div>

      <!-- Stok info -->
      <div class="grid grid-cols-2 gap-0 border-t border-gray-50">
        <div class="px-4 py-2.5 border-r border-gray-50 text-center">
          <p class="text-xs text-gray-400">Tersedia</p>
          <p class="text-base font-bold <?= $low?'text-red-500':'text-gray-800' ?>"><?= $item['stock'] ?> <span class="text-xs font-normal text-gray-400"><?= $item['unit'] ?></span></p>
        </div>
        <div class="px-4 py-2.5 text-center">
          <p class="text-xs text-gray-400">Minimum</p>
          <p class="text-base font-bold text-gray-500"><?= $item['min_stock'] ?> <span class="text-xs font-normal text-gray-400"><?= $item['unit'] ?></span></p>
        </div>
      </div>

      <!-- Progress bar -->
      <div class="px-4 pb-3">
        <div class="w-full bg-gray-100 rounded-full h-2">
          <div class="<?= $low?'bg-red-400':'bg-green-400' ?> h-2 rounded-full transition-all" style="width:<?= $pct ?>%"></div>
        </div>
      </div>

      <!-- Tombol cepat -->
      <div class="grid grid-cols-2 gap-2 px-4 pb-4">
        <form method="POST">
          <input type="hidden" name="type" value="<?= $tab==='materials'?'material':'product' ?>">
          <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
          <input type="hidden" name="change" value="-1">
          <input type="hidden" name="reason" value="Kurangi manual">
          <button class="w-full border border-orange-400 text-orange-500 py-2 rounded-xl text-sm font-semibold flex items-center justify-center gap-1">
            <i data-lucide="minus" class="w-3.5 h-3.5"></i> Kurangi
          </button>
        </form>
        <form method="POST">
          <input type="hidden" name="type" value="<?= $tab==='materials'?'material':'product' ?>">
          <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
          <input type="hidden" name="change" value="10">
          <input type="hidden" name="reason" value="Restock +10">
          <button class="w-full bg-orange-500 text-white py-2 rounded-xl text-sm font-semibold flex items-center justify-center gap-1">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i> Restock +10
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Modal update stok custom -->
<div id="modalStok" class="hidden fixed inset-0 bg-black/60 z-50 flex items-end justify-center">
  <div class="bg-white rounded-t-3xl w-full max-w-md flex flex-col" style="height:75vh">
    <div class="flex-shrink-0 px-5 pt-3 pb-4 border-b border-gray-100">
      <div class="flex justify-center mb-3"><div class="w-10 h-1 bg-gray-200 rounded-full"></div></div>
      <div class="flex justify-between items-center">
        <h3 class="text-lg font-bold">Update Stok</h3>
        <button type="button" onclick="document.getElementById('modalStok').classList.add('hidden')" class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
      </div>
    </div>
    <form method="POST" class="flex flex-col flex-1 min-h-0">
      <input type="hidden" name="type" id="tipeItem">
      <input type="hidden" name="item_id" id="idItem">
      <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3">
        <div class="bg-orange-50 rounded-xl p-3">
          <p class="text-sm font-bold text-gray-700" id="namaItem">-</p>
          <p class="text-sm text-gray-500 mt-0.5">Stok saat ini: <span id="stokSaatIni" class="font-bold text-orange-500">-</span></p>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-600 block mb-1">Jumlah Perubahan *</label>
          <input name="change" id="jumlahChange" type="number" placeholder="cth: 50 atau -10" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none">
          <p class="text-xs text-gray-400 mt-1">Angka positif = tambah, negatif = kurangi</p>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-600 block mb-1">Keterangan</label>
          <input name="reason" placeholder="cth: Restock dari supplier PT Jaya" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none">
        </div>
      </div>
      <div class="flex-shrink-0 px-5 py-4 border-t border-gray-100">
        <button type="submit" class="w-full bg-orange-500 text-white py-3.5 rounded-xl font-bold text-base">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<?php include 'nav.php'; ?>
</div>
<script>
function bukaUpdate(id, type, nama, stok, unit) {
  document.getElementById('idItem').value = id;
  document.getElementById('tipeItem').value = type;
  document.getElementById('namaItem').textContent = nama;
  document.getElementById('stokSaatIni').textContent = stok + ' ' + unit;
  document.getElementById('jumlahChange').value = '';
  document.getElementById('modalStok').classList.remove('hidden');
  lucide.createIcons();
}
document.getElementById('modalStok').addEventListener('click', function(e) {
  if(e.target===this) this.classList.add('hidden');
});
</script>
<?php include '../includes/footer.php'; ?>

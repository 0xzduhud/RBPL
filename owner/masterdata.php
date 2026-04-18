<?php
require_once '../includes/auth.php';
requireLogin('owner');
$pageTitle = 'Master Data - Dimsum App';
$activeTab = 'masterdata';
$pdo       = getDB();
$cat       = $_GET['cat'] ?? 'products';
$msg       = '';
$msgType   = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    // ── PRODUK ──────────────────────────────────────────────
    if ($act === 'add_product') {
        $catId = $pdo->query("SELECT id FROM categories WHERE type='".$_POST['type']."' LIMIT 1")->fetchColumn();
        if (!$catId) { $pdo->prepare("INSERT INTO categories (name,type) VALUES (?,?)")->execute([$_POST['type']==='matang'?'Dimsum Matang':'Dimsum Frozen',$_POST['type']]); $catId=$pdo->lastInsertId(); }
        $pdo->prepare("INSERT INTO products (category_id,name,description,price,stock,min_stock,unit,emoji,status) VALUES (?,?,?,?,?,?,?,?,'active')")
            ->execute([$catId,$_POST['name'],$_POST['desc'],$_POST['price'],$_POST['stock']??0,$_POST['min_stock']??10,$_POST['unit']??'pcs',$_POST['emoji']??'🥟']);
        $msg = '✅ Produk "'.$_POST['name'].'" berhasil ditambahkan!';
    }
    if ($act === 'edit_product') {
        $catId = $pdo->query("SELECT id FROM categories WHERE type='".$_POST['type']."' LIMIT 1")->fetchColumn();
        $pdo->prepare("UPDATE products SET category_id=?,name=?,description=?,price=?,stock=?,min_stock=?,unit=?,emoji=?,updated_at=NOW() WHERE id=?")
            ->execute([$catId,$_POST['name'],$_POST['desc'],$_POST['price'],$_POST['stock'],$_POST['min_stock'],$_POST['unit']??'pcs',$_POST['emoji']??'🥟',$_POST['id']]);
        $msg = '✅ Produk berhasil diupdate!';
    }
    if ($act === 'delete_product') {
        $pdo->prepare("UPDATE products SET status='inactive' WHERE id=?")->execute([$_POST['id']]);
        $msg = '🗑️ Produk berhasil dihapus.';
    }

    // ── KARYAWAN ─────────────────────────────────────────────
    if ($act === 'add_employee') {
        $uname = trim($_POST['username']??'');
        $cek = $pdo->prepare("SELECT id FROM users WHERE username=?"); $cek->execute([$uname]);
        if ($cek->fetch()) { $msg = '❌ Username sudah digunakan!'; $msgType='error'; }
        elseif (!$uname || !$_POST['password']) { $msg = '❌ Username dan password wajib diisi!'; $msgType='error'; }
        else {
            $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (username,password,name,phone,role,status) VALUES (?,?,?,?,'employee','active')")
                ->execute([$uname,$hash,trim($_POST['name']),trim($_POST['phone']??'')]);
            $msg = '✅ Karyawan "'.$_POST['name'].'" berhasil ditambahkan!';
        }
    }
    if ($act === 'edit_employee') {
        $pdo->prepare("UPDATE users SET name=?,phone=?,updated_at=NOW() WHERE id=? AND role='employee'")->execute([trim($_POST['name']),trim($_POST['phone']??''),$_POST['id']]);
        if (!empty($_POST['password'])) {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['password'],PASSWORD_BCRYPT),$_POST['id']]);
        }
        $msg = '✅ Data karyawan berhasil diupdate!';
    }
    if ($act === 'toggle_employee') {
        $newStatus = $_POST['current_status']==='active' ? 'inactive' : 'active';
        $pdo->prepare("UPDATE users SET status=? WHERE id=? AND role='employee'")->execute([$newStatus,$_POST['id']]);
        $msg = '✅ Status karyawan berhasil diubah.';
    }

    // ── SUPPLIER ─────────────────────────────────────────────
    if ($act === 'add_supplier') {
        if (!trim($_POST['name']??'')) { $msg='❌ Nama supplier wajib diisi!'; $msgType='error'; }
        else {
            $pdo->prepare("INSERT INTO suppliers (name,product,contact,email,address) VALUES (?,?,?,?,?)")
                ->execute([trim($_POST['name']),trim($_POST['product']??''),trim($_POST['contact']??''),trim($_POST['email']??''),trim($_POST['address']??'')]);
            $msg = '✅ Supplier "'.$_POST['name'].'" berhasil ditambahkan!';
        }
    }
    if ($act === 'edit_supplier') {
        $pdo->prepare("UPDATE suppliers SET name=?,product=?,contact=?,email=?,address=? WHERE id=?")
            ->execute([trim($_POST['name']),trim($_POST['product']??''),trim($_POST['contact']??''),trim($_POST['email']??''),trim($_POST['address']??''),$_POST['id']]);
        $msg = '✅ Supplier berhasil diupdate!';
    }
    if ($act === 'delete_supplier') {
        $pdo->prepare("DELETE FROM suppliers WHERE id=?")->execute([$_POST['id']]);
        $msg = '🗑️ Supplier berhasil dihapus.';
    }

    // ── PELANGGAN ─────────────────────────────────────────────
    if ($act === 'toggle_customer') {
        $newStatus = $_POST['current_status']==='active' ? 'inactive' : 'active';
        $pdo->prepare("UPDATE users SET status=? WHERE id=? AND role='customer'")->execute([$newStatus,$_POST['id']]);
        $msg = '✅ Status pelanggan berhasil diubah.';
    }

    if (!$msg) $msg = '';
    header("Location: " . BASE_PATH . "/owner/masterdata.php?cat=$cat&msg=".urlencode($msg)."&mt=".($msgType==='error'?'e':'s'));
    exit;
}

$msg     = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
$isError = ($_GET['mt']??'s') === 'e';

// Data
$products  = $pdo->query("SELECT p.*, c.type as cat_type FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.status='active' ORDER BY c.type,p.name")->fetchAll();
$employees = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM production_orders WHERE employee_id=u.id AND status='completed') AS done FROM users u WHERE u.role='employee' ORDER BY u.name")->fetchAll();
$customers = $pdo->query("SELECT u.*, COUNT(o.id) AS orders FROM users u LEFT JOIN orders o ON o.customer_id=u.id WHERE u.role='customer' GROUP BY u.id ORDER BY u.name")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="min-h-screen bg-gray-50 pb-24"><div class="max-w-md mx-auto">

  <!-- Header -->
  <div class="bg-gradient-to-r from-orange-500 to-red-500 text-white p-4 rounded-b-3xl">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-bold">Master Data</h2>
      <?php if ($cat !== 'customers'): ?>
      <button onclick="openTambah()"
        class="flex items-center gap-1.5 bg-white text-orange-500 px-4 py-2 rounded-xl text-sm font-bold shadow-sm hover:bg-orange-50 transition-colors">
        <i data-lucide="plus" class="w-4 h-4"></i>
        Tambah Data
      </button>
      <?php endif; ?>
    </div>
    <div class="grid grid-cols-3 gap-2">
      <?php foreach(['products'=>'Produk','customers'=>'Pelanggan','suppliers'=>'Supplier'] as $k=>$v): ?>
      <a href="masterdata.php?cat=<?= $k ?>"
        class="py-2 rounded-xl text-center text-xs font-semibold transition-all <?= $cat===$k?'bg-white text-orange-500':'bg-white/20 text-white' ?>">
        <?= $v ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Alert -->
  <?php if ($msg): ?>
  <div class="mx-4 mt-4 p-3 <?= $isError?'bg-red-50 border-red-200 text-red-700':'bg-green-50 border-green-200 text-green-700' ?> border rounded-xl text-sm">
    <?= $msg ?>
  </div>
  <?php endif; ?>

  <div class="p-4 space-y-3">

  <!-- ======= TAB: PRODUK ======= -->
  <?php if ($cat==='products'): ?>
    <?php if (empty($products)): ?>
    <div class="text-center py-12 text-gray-400"><p class="text-4xl mb-2">📦</p><p class="text-sm">Belum ada produk</p></div>
    <?php endif; ?>
    <?php
    $grouped = ['matang'=>[],'frozen'=>[]];
    foreach ($products as $p) $grouped[$p['cat_type']??'matang'][] = $p;
    foreach ($grouped as $type => $items):
      if (empty($items)) continue;
    ?>
    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mt-2"><?= $type==='matang'?'🥟 Dimsum Matang':'❄️ Dimsum Frozen' ?></p>
    <?php foreach ($items as $p): ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="flex items-center gap-3 p-4">
        <div class="w-12 h-12 bg-orange-50 rounded-xl flex items-center justify-center text-2xl flex-shrink-0"><?= $p['emoji']?:'🥟' ?></div>
        <div class="flex-1 min-w-0">
          <p class="font-bold text-sm text-gray-800 truncate"><?= htmlspecialchars($p['name']) ?></p>
          <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($p['description']??'-') ?></p>
          <p class="text-orange-500 font-bold text-sm mt-0.5">Rp <?= number_format($p['price'],0,',','.') ?></p>
        </div>
        <div class="flex gap-1 flex-shrink-0">
          <button onclick='editProduk(<?= json_encode($p) ?>)'
            class="w-8 h-8 bg-blue-50 text-blue-500 rounded-lg flex items-center justify-center">
            <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
          </button>
          <form method="POST" onsubmit="return confirm('Hapus produk ini?')" class="inline">
            <input type="hidden" name="action" value="delete_product">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button class="w-8 h-8 bg-red-50 text-red-500 rounded-lg flex items-center justify-center">
              <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            </button>
          </form>
        </div>
      </div>
      <div class="flex border-t border-gray-50">
        <div class="flex-1 px-4 py-2 text-center border-r border-gray-50">
          <p class="text-xs text-gray-400">Stok</p>
          <p class="text-sm font-bold <?= $p['stock']<=$p['min_stock']?'text-red-500':'text-green-600' ?>"><?= $p['stock'] ?> <?= $p['unit'] ?></p>
        </div>
        <div class="flex-1 px-4 py-2 text-center border-r border-gray-50">
          <p class="text-xs text-gray-400">Min Stok</p>
          <p class="text-sm font-bold text-gray-600"><?= $p['min_stock'] ?></p>
        </div>
        <div class="flex-1 px-4 py-2 text-center">
          <p class="text-xs text-gray-400">Tipe</p>
          <p class="text-xs font-bold text-gray-600"><?= $p['cat_type'] ?></p>
        </div>
      </div>
    </div>
    <?php endforeach; endforeach; ?>

  <!-- ======= TAB: KARYAWAN ======= -->
  <?php elseif ($cat==='employees'): ?>
    <div class="flex justify-between items-center mb-1">
      <p class="text-sm text-gray-500"><?= count($employees) ?> karyawan terdaftar</p>
    </div>
    <?php if (empty($employees)): ?>
    <div class="text-center py-12 text-gray-400"><p class="text-4xl mb-2">👷</p><p class="text-sm">Belum ada karyawan</p></div>
    <?php endif; ?>
    <?php foreach ($employees as $e): ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
      <div class="flex items-start gap-3 mb-3">
        <div class="w-11 h-11 bg-orange-100 rounded-full flex items-center justify-center flex-shrink-0">
          <i data-lucide="user" class="w-5 h-5 text-orange-500"></i>
        </div>
        <div class="flex-1 min-w-0">
          <p class="font-bold text-sm text-gray-800"><?= htmlspecialchars($e['name']) ?></p>
          <p class="text-xs text-gray-400">@<?= htmlspecialchars($e['username']) ?></p>
          <p class="text-xs text-gray-400">📞 <?= htmlspecialchars($e['phone']??'-') ?></p>
        </div>
        <div class="flex gap-1 flex-shrink-0">
          <button onclick='editKaryawan(<?= json_encode($e) ?>)'
            class="w-8 h-8 bg-blue-50 text-blue-500 rounded-lg flex items-center justify-center">
            <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
          </button>
          <form method="POST" class="inline">
            <input type="hidden" name="action" value="toggle_employee">
            <input type="hidden" name="id" value="<?= $e['id'] ?>">
            <input type="hidden" name="current_status" value="<?= $e['status'] ?>">
            <button class="w-8 h-8 <?= $e['status']==='active'?'bg-red-50 text-red-500':'bg-green-50 text-green-500' ?> rounded-lg flex items-center justify-center">
              <i data-lucide="<?= $e['status']==='active'?'user-x':'user-check' ?>" class="w-3.5 h-3.5"></i>
            </button>
          </form>
        </div>
      </div>
      <div class="flex gap-2">
        <span class="flex-1 text-center text-xs py-1.5 rounded-lg <?= $e['status']==='active'?'bg-green-100 text-green-700':'bg-gray-100 text-gray-500' ?> font-medium">
          <?= $e['status']==='active'?'✅ Aktif':'⛔ Nonaktif' ?>
        </span>
        <span class="flex-1 text-center text-xs py-1.5 rounded-lg bg-orange-50 text-orange-600 font-medium">
          <?= $e['done'] ?> produksi selesai
        </span>
        <span class="flex-1 text-center text-xs py-1.5 rounded-lg bg-gray-50 text-gray-500 font-medium">
          Sejak <?= date('Y',strtotime($e['created_at'])) ?>
        </span>
      </div>
    </div>
    <?php endforeach; ?>

  <!-- ======= TAB: PELANGGAN ======= -->
  <?php elseif ($cat==='customers'): ?>
    <p class="text-sm text-gray-500"><?= count($customers) ?> pelanggan terdaftar</p>
    <?php if (empty($customers)): ?>
    <div class="text-center py-12 text-gray-400"><p class="text-4xl mb-2">👥</p><p class="text-sm">Belum ada pelanggan</p></div>
    <?php endif; ?>
    <?php foreach ($customers as $c): ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
          <i data-lucide="user" class="w-5 h-5 text-blue-500"></i>
        </div>
        <div class="flex-1">
          <p class="font-bold text-sm"><?= htmlspecialchars($c['name']) ?></p>
          <p class="text-xs text-gray-400">@<?= htmlspecialchars($c['username']) ?> · <?= htmlspecialchars($c['phone']??'-') ?></p>
        </div>
        <form method="POST" class="inline">
          <input type="hidden" name="action" value="toggle_customer">
          <input type="hidden" name="id" value="<?= $c['id'] ?>">
          <input type="hidden" name="current_status" value="<?= $c['status'] ?>">
          <button class="text-xs px-3 py-1.5 rounded-lg font-medium <?= $c['status']==='active'?'bg-red-50 text-red-500':'bg-green-50 text-green-500' ?>">
            <?= $c['status']==='active'?'Nonaktifkan':'Aktifkan' ?>
          </button>
        </form>
      </div>
      <div class="flex gap-2 text-xs">
        <span class="flex-1 text-center py-1.5 rounded-lg <?= $c['status']==='active'?'bg-green-100 text-green-700':'bg-gray-100 text-gray-500' ?> font-medium">
          <?= $c['status']==='active'?'Aktif':'Nonaktif' ?>
        </span>
        <span class="flex-1 text-center py-1.5 rounded-lg bg-orange-50 text-orange-600 font-medium">
          <?= $c['orders'] ?> pesanan
        </span>
      </div>
    </div>
    <?php endforeach; ?>

  <!-- ======= TAB: SUPPLIER ======= -->
  <?php elseif ($cat==='suppliers'): ?>
    <?php if (empty($suppliers)): ?>
    <div class="text-center py-12 text-gray-400"><p class="text-4xl mb-2">🏭</p><p class="text-sm">Belum ada supplier</p></div>
    <?php endif; ?>
    <?php foreach ($suppliers as $s): ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
      <div class="flex items-start justify-between gap-3 mb-3">
        <div class="flex items-start gap-3 flex-1 min-w-0">
          <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center flex-shrink-0">
            <i data-lucide="truck" class="w-5 h-5 text-purple-500"></i>
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-bold text-sm text-gray-800"><?= htmlspecialchars($s['name']) ?></p>
            <p class="text-xs text-orange-500 font-medium"><?= htmlspecialchars($s['product']??'-') ?></p>
            <p class="text-xs text-gray-400">📞 <?= htmlspecialchars($s['contact']??'-') ?></p>
            <?php if ($s['email']): ?><p class="text-xs text-gray-400">✉️ <?= htmlspecialchars($s['email']) ?></p><?php endif; ?>
            <?php if ($s['address']): ?><p class="text-xs text-gray-400">📍 <?= htmlspecialchars($s['address']) ?></p><?php endif; ?>
          </div>
        </div>
        <div class="flex gap-1 flex-shrink-0">
          <button onclick='editSupplier(<?= json_encode($s) ?>)'
            class="w-8 h-8 bg-blue-50 text-blue-500 rounded-lg flex items-center justify-center">
            <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
          </button>
          <form method="POST" onsubmit="return confirm('Hapus supplier ini?')" class="inline">
            <input type="hidden" name="action" value="delete_supplier">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button class="w-8 h-8 bg-red-50 text-red-500 rounded-lg flex items-center justify-center">
              <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            </button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

  </div><!-- end space-y-3 -->

</div>


<!-- ===== MODAL PRODUK ===== -->
<div id="modalProduk" class="hidden fixed inset-0 bg-black/60 z-50 flex items-end justify-center">
  <div class="bg-white rounded-t-3xl w-full max-w-md flex flex-col" style="height:92vh">
    <div class="flex-shrink-0 px-5 pt-3 pb-4 border-b border-gray-100">
      <div class="flex justify-center mb-3"><div class="w-10 h-1 bg-gray-200 rounded-full"></div></div>
      <div class="flex justify-between items-center">
        <h3 id="judul_produk" class="text-lg font-bold">Tambah Produk</h3>
        <button type="button" onclick="tutup('modalProduk')" class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
      </div>
    </div>
    <form method="POST" class="flex flex-col flex-1 min-h-0">
      <input type="hidden" name="action" id="aksi_produk" value="add_product">
      <input type="hidden" name="id" id="id_produk">
      <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3">
        <div><label class="text-xs font-semibold text-gray-600 block mb-1">Nama Produk *</label>
          <input name="name" id="nama_produk" required placeholder="Dimsum Ayam" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
        <div><label class="text-xs font-semibold text-gray-600 block mb-1">Deskripsi</label>
          <input name="desc" id="desc_produk" placeholder="Deskripsi singkat..." class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="text-xs font-semibold text-gray-600 block mb-1">Harga (Rp) *</label>
            <input name="price" id="harga_produk" type="number" required placeholder="25000" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
          <div><label class="text-xs font-semibold text-gray-600 block mb-1">Tipe *</label>
            <select name="type" id="tipe_produk" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none">
              <option value="matang">🥟 Matang</option><option value="frozen">❄️ Frozen</option></select></div>
          <div><label class="text-xs font-semibold text-gray-600 block mb-1">Stok</label>
            <input name="stock" id="stok_produk" type="number" placeholder="100" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
          <div><label class="text-xs font-semibold text-gray-600 block mb-1">Min. Stok</label>
            <input name="min_stock" id="minstok_produk" type="number" placeholder="20" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
          <div><label class="text-xs font-semibold text-gray-600 block mb-1">Satuan</label>
            <select name="unit" id="unit_produk" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none">
              <option value="pcs">pcs</option><option value="pack">pack</option><option value="porsi">porsi</option></select></div>
          <div><label class="text-xs font-semibold text-gray-600 block mb-1">Emoji</label>
            <input name="emoji" id="emoji_produk" placeholder="🥟" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
        </div>
      </div>
      <div class="flex-shrink-0 px-5 py-4 border-t border-gray-100">
        <button type="submit" class="w-full bg-orange-500 text-white py-3.5 rounded-xl font-bold text-base">Simpan Produk</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== MODAL KARYAWAN ===== -->
<div id="modalKaryawan" class="hidden fixed inset-0 bg-black/60 z-50 flex items-end justify-center">
  <div class="bg-white rounded-t-3xl w-full max-w-md flex flex-col" style="height:88vh">
    <div class="flex-shrink-0 px-5 pt-3 pb-4 border-b border-gray-100">
      <div class="flex justify-center mb-3"><div class="w-10 h-1 bg-gray-200 rounded-full"></div></div>
      <div class="flex justify-between items-center">
        <h3 id="judul_karyawan" class="text-lg font-bold">Tambah Karyawan</h3>
        <button type="button" onclick="tutup('modalKaryawan')" class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
      </div>
    </div>
    <form method="POST" class="flex flex-col flex-1 min-h-0">
      <input type="hidden" name="action" id="aksi_karyawan" value="add_employee">
      <input type="hidden" name="id" id="id_karyawan">
      <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3">
        <div><label class="text-xs font-semibold text-gray-600 block mb-1">Nama Lengkap *</label>
          <input name="name" id="nama_karyawan" required placeholder="Joko Widodo" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
        <div id="row_username"><label class="text-xs font-semibold text-gray-600 block mb-1">Username * <span class="text-gray-400 font-normal">(untuk login)</span></label>
          <input name="username" id="username_karyawan" placeholder="joko123" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
        <div><label class="text-xs font-semibold text-gray-600 block mb-1">No. HP</label>
          <input name="phone" id="phone_karyawan" placeholder="081234567890" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
        <div><label class="text-xs font-semibold text-gray-600 block mb-1" id="label_pwd">Password *</label>
          <div class="relative">
            <input type="password" name="password" id="pwd_karyawan" placeholder="Min. 6 karakter" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 pr-10 text-sm focus:ring-2 focus:ring-orange-500 outline-none">
            <button type="button" onclick="togglePwd('pwd_karyawan')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"><i data-lucide="eye" class="w-4 h-4" id="eye_karyawan"></i></button>
          </div></div>
      </div>
      <div class="flex-shrink-0 px-5 py-4 border-t border-gray-100">
        <button type="submit" class="w-full bg-orange-500 text-white py-3.5 rounded-xl font-bold text-base">Simpan Karyawan</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== MODAL SUPPLIER ===== -->
<div id="modalSupplier" class="hidden fixed inset-0 bg-black/60 z-50 flex items-end justify-center">
  <div class="bg-white rounded-t-3xl w-full max-w-md flex flex-col" style="height:92vh">
    <div class="flex-shrink-0 px-5 pt-3 pb-4 border-b border-gray-100">
      <div class="flex justify-center mb-3"><div class="w-10 h-1 bg-gray-200 rounded-full"></div></div>
      <div class="flex justify-between items-center">
        <h3 id="judul_supplier" class="text-lg font-bold">Tambah Supplier</h3>
        <button type="button" onclick="tutup('modalSupplier')" class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
      </div>
    </div>
    <form method="POST" class="flex flex-col flex-1 min-h-0">
      <input type="hidden" name="action" id="aksi_supplier" value="add_supplier">
      <input type="hidden" name="id" id="id_supplier">
      <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3">
        <div><label class="text-xs font-semibold text-gray-600 block mb-1">Nama Supplier *</label>
          <input name="name" id="nama_supplier" required placeholder="PT Tepung Jaya" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
        <div><label class="text-xs font-semibold text-gray-600 block mb-1">Produk yang Disuplai</label>
          <input name="product" id="produk_supplier" placeholder="Tepung Terigu, Daging Ayam" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
        <div><label class="text-xs font-semibold text-gray-600 block mb-1">No. Kontak</label>
          <input name="contact" id="kontak_supplier" placeholder="021-12345678" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
        <div><label class="text-xs font-semibold text-gray-600 block mb-1">Email</label>
          <input name="email" id="email_supplier" type="email" placeholder="supplier@email.com" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
        <div><label class="text-xs font-semibold text-gray-600 block mb-1">Alamat</label>
          <textarea name="address" id="alamat_supplier" rows="2" placeholder="Jl. Raya ..." class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none resize-none"></textarea></div>
      </div>
      <div class="flex-shrink-0 px-5 py-4 border-t border-gray-100">
        <button type="submit" class="w-full bg-orange-500 text-white py-3.5 rounded-xl font-bold text-base">Simpan Supplier</button>
      </div>
    </form>
  </div>
</div>

<?php include 'nav.php'; ?>
</div>

<script>
const cat = '<?= $cat ?>';

function openTambah() {
  if (cat === 'products') {
    document.getElementById('judul_produk').textContent = 'Tambah Produk';
    document.getElementById('aksi_produk').value = 'add_product';
    document.getElementById('id_produk').value = '';
    ['nama_produk','desc_produk','harga_produk','stok_produk','minstok_produk','emoji_produk'].forEach(id => { const el=document.getElementById(id); if(el) el.value=''; });
    document.getElementById('tipe_produk').value = 'matang';
    document.getElementById('unit_produk').value = 'pcs';
    document.getElementById('emoji_produk').value = '🥟';
    document.getElementById('modalProduk').classList.remove('hidden');
  } else if (cat === 'employees') {
    document.getElementById('judul_karyawan').textContent = 'Tambah Karyawan';
    document.getElementById('aksi_karyawan').value = 'add_employee';
    document.getElementById('id_karyawan').value = '';
    document.getElementById('row_username').classList.remove('hidden');
    document.getElementById('label_pwd').textContent = 'Password *';
    document.getElementById('username_karyawan').required = true;
    document.getElementById('pwd_karyawan').required = true;
    ['nama_karyawan','username_karyawan','phone_karyawan','pwd_karyawan'].forEach(id => { const el=document.getElementById(id); if(el) el.value=''; });
    document.getElementById('modalKaryawan').classList.remove('hidden');
  } else if (cat === 'suppliers') {
    document.getElementById('judul_supplier').textContent = 'Tambah Supplier';
    document.getElementById('aksi_supplier').value = 'add_supplier';
    document.getElementById('id_supplier').value = '';
    ['nama_supplier','produk_supplier','kontak_supplier','email_supplier','alamat_supplier'].forEach(id => { const el=document.getElementById(id); if(el) el.value=''; });
    document.getElementById('modalSupplier').classList.remove('hidden');
  }
  lucide.createIcons();
}

function editProduk(p) {
  document.getElementById('judul_produk').textContent = 'Edit Produk';
  document.getElementById('aksi_produk').value = 'edit_product';
  document.getElementById('id_produk').value = p.id;
  document.getElementById('nama_produk').value = p.name;
  document.getElementById('desc_produk').value = p.description || '';
  document.getElementById('harga_produk').value = p.price;
  document.getElementById('tipe_produk').value = p.cat_type || 'matang';
  document.getElementById('stok_produk').value = p.stock;
  document.getElementById('minstok_produk').value = p.min_stock;
  document.getElementById('unit_produk').value = p.unit || 'pcs';
  document.getElementById('emoji_produk').value = p.emoji || '🥟';
  document.getElementById('modalProduk').classList.remove('hidden');
  lucide.createIcons();
}

function editKaryawan(e) {
  document.getElementById('judul_karyawan').textContent = 'Edit Karyawan';
  document.getElementById('aksi_karyawan').value = 'edit_employee';
  document.getElementById('id_karyawan').value = e.id;
  document.getElementById('nama_karyawan').value = e.name;
  document.getElementById('phone_karyawan').value = e.phone || '';
  document.getElementById('pwd_karyawan').value = '';
  document.getElementById('row_username').classList.add('hidden');
  document.getElementById('label_pwd').textContent = 'Password Baru (kosongkan jika tidak diubah)';
  document.getElementById('username_karyawan').required = false;
  document.getElementById('pwd_karyawan').required = false;
  document.getElementById('modalKaryawan').classList.remove('hidden');
  lucide.createIcons();
}

function editSupplier(s) {
  document.getElementById('judul_supplier').textContent = 'Edit Supplier';
  document.getElementById('aksi_supplier').value = 'edit_supplier';
  document.getElementById('id_supplier').value = s.id;
  document.getElementById('nama_supplier').value = s.name;
  document.getElementById('produk_supplier').value = s.product || '';
  document.getElementById('kontak_supplier').value = s.contact || '';
  document.getElementById('email_supplier').value = s.email || '';
  document.getElementById('alamat_supplier').value = s.address || '';
  document.getElementById('modalSupplier').classList.remove('hidden');
  lucide.createIcons();
}

function tutup(id) { document.getElementById(id).classList.add('hidden'); }

function togglePwd(id) {
  const i = document.getElementById(id);
  const eid = 'eye_' + id.split('_')[1];
  const e = document.getElementById(eid);
  i.type = i.type === 'password' ? 'text' : 'password';
  if(e) e.setAttribute('data-lucide', i.type === 'password' ? 'eye' : 'eye-off');
  lucide.createIcons();
}

['modalProduk','modalKaryawan','modalSupplier'].forEach(id => {
  document.getElementById(id).addEventListener('click', function(e) { if(e.target===this) tutup(id); });
});
</script>
<?php include '../includes/footer.php'; ?>

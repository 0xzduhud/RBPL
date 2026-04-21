<?php
require_once 'includes/auth.php';
$pdo = getDB();
$log = [];

// 1. Tabel transactions
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        transaction_code VARCHAR(30) NOT NULL UNIQUE,
        amount DECIMAL(10,2) NOT NULL,
        payment_method ENUM('qris','cash') NOT NULL,
        payment_status ENUM('pending','paid','failed') DEFAULT 'pending',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    )");
    $log[] = ['ok', 'Tabel transactions siap'];
} catch(Exception $e) { $log[] = ['warn', 'Transactions: '.$e->getMessage()]; }

// 2. Categories
$catCount = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
if ($catCount == 0) {
    $pdo->exec("INSERT INTO categories (name,type) VALUES ('Dimsum Matang','matang'),('Dimsum Frozen','frozen')");
    $log[] = ['ok', 'Categories dibuat'];
} else { $log[] = ['ok', "Categories sudah ada ($catCount data)"]; }

$matang = $pdo->query("SELECT id FROM categories WHERE type='matang' LIMIT 1")->fetchColumn();
$frozen = $pdo->query("SELECT id FROM categories WHERE type='frozen' LIMIT 1")->fetchColumn();

// 3. Produk
$prodCount = $pdo->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
if ($prodCount == 0) {
    $products = [
        [$matang,'Dimsum Ayam','Dimsum ayam kukus lembut isi 5 pcs',25000,150,30,'pcs','🥟'],
        [$matang,'Dimsum Udang','Dimsum udang segar premium isi 5 pcs',30000,80,20,'pcs','🦐'],
        [$matang,'Dimsum Sayur','Dimsum sayuran sehat isi 5 pcs',20000,200,30,'pcs','🥬'],
        [$matang,'Dimsum Kepiting','Dimsum kepiting premium isi 5 pcs',35000,60,20,'pcs','🦀'],
        [$matang,'Siomay Ayam','Siomay ayam kukus dengan saus kacang',22000,100,20,'pcs','🍢'],
        [$matang,'Hakau Udang','Hakau kulit tipis isi udang segar',28000,70,20,'pcs','🥠'],
        [$frozen,'Frozen Pack Ayam (10pcs)','Frozen pack ayam siap masak',45000,60,15,'pack','📦'],
        [$frozen,'Frozen Pack Udang (10pcs)','Frozen pack udang segar',55000,40,10,'pack','📦'],
        [$frozen,'Frozen Pack Mix (10pcs)','Campuran dimsum frozen 10 pcs',50000,50,10,'pack','📦'],
        [$frozen,'Frozen Siomay (10pcs)','Siomay beku siap kukus',40000,35,10,'pack','📦'],
    ];
    $ins = $pdo->prepare("INSERT INTO products (category_id,name,description,price,stock,min_stock,unit,emoji,status) VALUES (?,?,?,?,?,?,?,?,'active')");
    foreach ($products as $p) $ins->execute($p);
    $log[] = ['ok', count($products).' produk ditambahkan'];
} else { $log[] = ['ok', "Produk sudah ada ($prodCount aktif)"]; }

// 4. Bahan baku
$matCount = $pdo->query("SELECT COUNT(*) FROM raw_materials")->fetchColumn();
if ($matCount == 0) {
    $materials = [
        ['Tepung Terigu','kg',15,20],['Daging Ayam','kg',25,10],
        ['Udang','kg',8,15],['Sayuran','kg',30,10],
        ['Bumbu Pelengkap','pack',5,8],['Kepiting','kg',6,5],
        ['Kulit Dimsum','pack',20,10],['Minyak Wijen','botol',8,3],
    ];
    $ins = $pdo->prepare("INSERT INTO raw_materials (name,unit,stock,min_stock) VALUES (?,?,?,?)");
    foreach ($materials as $m) $ins->execute($m);
    $log[] = ['ok', count($materials).' bahan baku ditambahkan'];
} else { $log[] = ['ok', "Bahan baku sudah ada ($matCount data)"]; }

// 5. Supplier
$supCount = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
if ($supCount == 0) {
    $suppliers = [
        ['PT Tepung Jaya','Tepung Terigu, Kulit Dimsum','021-12345678','tepungjaya@email.com','Jl. Industri No. 1, Jakarta'],
        ['CV Daging Segar','Daging Ayam, Udang','021-87654321','dagingsegar@email.com','Jl. Pasar No. 5, Bogor'],
        ['Toko Sayur Segar','Sayuran, Bumbu','08123456789','sayursegar@email.com','Pasar Induk, Bekasi'],
        ['UD Seafood Prima','Udang, Kepiting','08198765432','seafood@email.com','Jl. Nelayan No. 12, Muara Baru'],
    ];
    $ins = $pdo->prepare("INSERT INTO suppliers (name,product,contact,email,address) VALUES (?,?,?,?,?)");
    foreach ($suppliers as $s) $ins->execute($s);
    $log[] = ['ok', count($suppliers).' supplier ditambahkan'];
} else { $log[] = ['ok', "Supplier sudah ada ($supCount data)"]; }

$totalProd = $pdo->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$totalUser = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalMat  = $pdo->query("SELECT COUNT(*) FROM raw_materials")->fetchColumn();
$totalSup  = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-orange-50 min-h-screen flex items-center justify-center p-4">
<div class="bg-white rounded-2xl shadow-xl p-6 max-w-sm w-full">
  <div class="text-center mb-5"><div class="text-5xl mb-2">⚙️</div>
    <h2 class="text-xl font-bold">Setup Berhasil!</h2>
    <p class="text-sm text-gray-400 mt-1">Database siap digunakan</p>
  </div>
  <div class="space-y-2 mb-5">
    <?php foreach ($log as [$type,$msg]): ?>
    <div class="flex items-center gap-2 text-sm p-2.5 <?= $type==='ok'?'bg-green-50 text-green-700':'bg-yellow-50 text-yellow-700' ?> rounded-xl">
      <span><?= $type==='ok'?'✅':'⚠️' ?></span><span><?= $msg ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="grid grid-cols-2 gap-3 mb-5">
    <div class="bg-orange-50 rounded-xl p-3 text-center"><p class="text-2xl font-bold text-orange-500"><?= $totalProd ?></p><p class="text-xs text-gray-500">Produk</p></div>
    <div class="bg-green-50 rounded-xl p-3 text-center"><p class="text-2xl font-bold text-green-500"><?= $totalMat ?></p><p class="text-xs text-gray-500">Bahan Baku</p></div>
    <div class="bg-blue-50 rounded-xl p-3 text-center"><p class="text-2xl font-bold text-blue-500"><?= $totalUser ?></p><p class="text-xs text-gray-500">User</p></div>
    <div class="bg-purple-50 rounded-xl p-3 text-center"><p class="text-2xl font-bold text-purple-500"><?= $totalSup ?></p><p class="text-xs text-gray-500">Supplier</p></div>
  </div>
  <a href="index.php" class="block w-full bg-orange-500 text-white py-3 rounded-xl font-bold text-center mb-3">🚀 Mulai Gunakan Aplikasi</a>
  <p class="text-xs text-red-400 text-center">⚠️ Hapus <strong>setup_products.php</strong> setelah ini!</p>
</div>
</body></html>

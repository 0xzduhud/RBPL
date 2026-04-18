<?php
require_once '../includes/auth.php';
requireLogin('owner');
$pageTitle = 'Laporan - Dimsum App';
$activeTab = 'reports';
$pdo       = getDB();
$period    = $_GET['period'] ?? 'daily';

$dateFilter = match($period) {
    'weekly'  => "AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
    'monthly' => "AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
    default   => "AND DATE(created_at) = CURDATE()",
};

$stats = $pdo->query("SELECT 
    COALESCE(SUM(total),0) AS total_sales, 
    COUNT(*) AS total_orders, 
    COALESCE(AVG(total),0) AS avg_order
    FROM orders WHERE status='delivered' $dateFilter")->fetch();

$stockVal   = $pdo->query("SELECT COALESCE(SUM(price*stock),0) FROM products WHERE status='active'")->fetchColumn();
$pendingCnt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
$prodCnt    = $pdo->query("SELECT COUNT(*) FROM production_orders WHERE status='processing'")->fetchColumn();

// Chart: last 7 hari
$chartData   = $pdo->query("SELECT DATE(created_at) AS d, SUM(total) AS v FROM orders WHERE status='delivered' AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY d")->fetchAll();
$chartLabels = array_map(fn($r)=>date('d/m',strtotime($r['d'])), $chartData);
$chartValues = array_map(fn($r)=>round($r['v']/1000,0), $chartData);

// Produk terlaris
$prodSales = $pdo->query("SELECT p.name, p.emoji, SUM(oi.qty) AS total, SUM(oi.subtotal) AS revenue FROM order_items oi JOIN products p ON p.id=oi.product_id JOIN orders o ON o.id=oi.order_id WHERE o.status='delivered' GROUP BY p.id ORDER BY total DESC LIMIT 5")->fetchAll();
$prodLabels = array_map(fn($r)=>$r['name'], $prodSales);
$prodValues = array_map(fn($r)=>$r['total'], $prodSales);

// Laporan keuangan bulanan (PB-09)
$monthly = $pdo->query("SELECT 
    DATE_FORMAT(created_at,'%Y-%m') AS bulan,
    COUNT(*) AS total_orders,
    SUM(total) AS pendapatan,
    SUM(delivery_fee) AS ongkir,
    SUM(subtotal) AS penjualan
    FROM orders WHERE status='delivered' 
    GROUP BY DATE_FORMAT(created_at,'%Y-%m')
    ORDER BY bulan DESC LIMIT 6")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="min-h-screen bg-gray-50 pb-20"><div class="max-w-md mx-auto">
  <div class="bg-gradient-to-r from-orange-500 to-red-500 text-white p-4 rounded-b-3xl">
    <h2 class="text-xl font-semibold mb-4">Dashboard & Laporan</h2>
    <div class="flex gap-2">
      <?php foreach(['daily'=>'Harian','weekly'=>'Mingguan','monthly'=>'Bulanan'] as $k=>$v): ?>
      <a href="index.php?period=<?= $k ?>" class="flex-1 py-2 rounded-lg text-center text-sm font-medium <?= $period===$k?'bg-white text-orange-500':'bg-white/20 text-white' ?>"><?= $v ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="p-4 space-y-4">
    <!-- Stats Grid -->
    <div class="grid grid-cols-2 gap-3">
      <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mb-2"><i data-lucide="trending-up" class="w-5 h-5 text-green-600"></i></div>
        <p class="text-xs text-gray-500 mb-1">Total Penjualan</p>
        <p class="text-lg font-bold">Rp <?= number_format(($stats['total_sales']??0)/1000000,1) ?>jt</p>
      </div>
      <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mb-2"><i data-lucide="shopping-bag" class="w-5 h-5 text-blue-600"></i></div>
        <p class="text-xs text-gray-500 mb-1">Total Pesanan</p>
        <p class="text-lg font-bold"><?= $stats['total_orders']??0 ?></p>
      </div>
      <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mb-2"><i data-lucide="clock" class="w-5 h-5 text-yellow-600"></i></div>
        <p class="text-xs text-gray-500 mb-1">Pending</p>
        <p class="text-lg font-bold"><?= $pendingCnt ?></p>
      </div>
      <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mb-2"><i data-lucide="package" class="w-5 h-5 text-purple-600"></i></div>
        <p class="text-xs text-gray-500 mb-1">Nilai Stok</p>
        <p class="text-lg font-bold">Rp <?= number_format(($stockVal??0)/1000000,1) ?>jt</p>
      </div>
    </div>

    <!-- Grafik Penjualan -->
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold">Grafik Penjualan (7 hari)</h3>
      </div>
      <canvas id="salesChart" height="180"></canvas>
    </div>

    <!-- Produk Terlaris -->
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
      <h3 class="font-semibold mb-4">Produk Terlaris</h3>
      <?php if (empty($prodSales)): ?>
      <p class="text-sm text-gray-400 text-center py-4">Belum ada data penjualan</p>
      <?php endif; ?>
      <?php foreach ($prodSales as $i=>$p): ?>
      <div class="flex items-center justify-between mb-3 last:mb-0">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center font-bold text-orange-600 text-sm"><?= $i+1 ?></div>
          <div>
            <p class="text-sm font-medium"><?= htmlspecialchars($p['name']) ?></p>
            <p class="text-xs text-gray-500"><?= $p['total'] ?> terjual</p>
          </div>
        </div>
        <p class="text-sm text-orange-500 font-semibold">Rp <?= number_format($p['revenue']/1000,0) ?>k</p>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Laporan Keuangan Bulanan (PB-09) -->
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
      <h3 class="font-semibold mb-4">📊 Laporan Keuangan Bulanan</h3>
      <?php if (empty($monthly)): ?>
      <p class="text-sm text-gray-400 text-center py-4">Belum ada data keuangan</p>
      <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($monthly as $m): ?>
        <div class="border border-gray-100 rounded-xl p-3">
          <div class="flex justify-between items-center mb-2">
            <p class="font-medium text-sm"><?= date('F Y', strtotime($m['bulan'].'-01')) ?></p>
            <span class="text-xs bg-orange-50 text-orange-600 px-2 py-0.5 rounded-full"><?= $m['total_orders'] ?> pesanan</span>
          </div>
          <div class="grid grid-cols-2 gap-2 text-xs">
            <div><p class="text-gray-400">Pendapatan</p><p class="font-semibold text-green-600">Rp <?= number_format($m['pendapatan']/1000000,2) ?>jt</p></div>
            <div><p class="text-gray-400">Penjualan</p><p class="font-semibold">Rp <?= number_format($m['penjualan']/1000000,2) ?>jt</p></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include 'nav.php'; ?>
</div>
<script>
const labels = <?= json_encode($chartLabels) ?>;
const vals   = <?= json_encode($chartValues) ?>;
const pLabels= <?= json_encode($prodLabels) ?>;
const pVals  = <?= json_encode($prodValues) ?>;

new Chart(document.getElementById('salesChart').getContext('2d'),{
  type:'line',
  data:{
    labels: labels.length ? labels : ['Sen','Sel','Rab','Kam','Jum','Sab','Min'],
    datasets:[{
      data: vals.length ? vals : [0,0,0,0,0,0,0],
      borderColor:'#f97316',backgroundColor:'rgba(249,115,22,0.1)',
      borderWidth:2,fill:true,tension:0.4,pointBackgroundColor:'#f97316',pointRadius:4
    }]
  },
  options:{
    responsive:true,
    plugins:{legend:{display:false}},
    scales:{y:{ticks:{callback:v=>'Rp '+v+'k'},grid:{color:'#f3f4f6'}}}
  }
});
</script>
<?php include '../includes/footer.php'; ?>

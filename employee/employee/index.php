<?php
require_once '../includes/auth.php';
requireLogin('employee');
$pageTitle = 'Produksi - Dimsum App';
$activeTab = 'production';
$pdo       = getDB();
$filter    = $_GET['filter'] ?? 'all';
$msg       = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['prod_id'], $_POST['new_status'])) {
    $s    = $_POST['new_status'];
    $prog = $s==='completed' ? 100 : ($s==='processing' ? 10 : 0);
    $pdo->prepare("UPDATE production_orders SET status=?,progress=?,started_at=IF(?='processing' AND started_at IS NULL,NOW(),started_at),finished_at=IF(?='completed',NOW(),finished_at),updated_at=NOW() WHERE id=?")
        ->execute([$s,$prog,$s,$s,$_POST['prod_id']]);

    // Jika selesai → update order ke 'production done' dan buat notif
    if ($s === 'completed' && !empty($_POST['order_id'])) {
        $pdo->prepare("UPDATE orders SET status='shipping',updated_at=NOW() WHERE id=?")->execute([$_POST['order_id']]);
    }
    $msg = $s === 'completed' ? 'Produksi selesai! Order siap dikirim.' : 'Status produksi diperbarui.';
    header("Location: " . BASE_PATH . "/employee/index.php?filter=$filter&msg=".urlencode($msg)); exit;
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

$sql = "SELECT po.*, u.name AS employee_name FROM production_orders po LEFT JOIN users u ON u.id=po.employee_id";
if ($filter !== 'all') $sql .= " WHERE po.status='$filter'";
$sql .= " ORDER BY po.created_at DESC";
$orders = $pdo->query($sql)->fetchAll();

foreach ($orders as &$o) {
    $st = $pdo->prepare("SELECT pi.qty, p.name FROM production_items pi JOIN products p ON p.id=pi.product_id WHERE pi.production_order_id=?");
    $st->execute([$o['id']]);
    $o['items'] = $st->fetchAll();
}
$stats = $pdo->query("SELECT status,COUNT(*) as cnt FROM production_orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<?php include '../includes/header.php'; ?>
<div class="min-h-screen bg-gray-50 pb-20"><div class="max-w-md mx-auto">
  <div class="bg-gradient-to-r from-orange-500 to-red-500 text-white p-4 rounded-b-3xl">
    <h2 class="text-xl font-semibold mb-4">Manajemen Produksi</h2>
    <div class="grid grid-cols-3 gap-3">
      <?php foreach(['pending'=>'Pending','processing'=>'Diproses','completed'=>'Selesai'] as $k=>$v): ?>
      <div class="bg-white/20 rounded-lg p-3"><p class="text-2xl font-bold"><?= $stats[$k]??0 ?></p><p class="text-xs opacity-90"><?= $v ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($msg): ?>
  <div class="mx-4 mt-4 p-3 bg-green-100 border border-green-300 rounded-xl text-sm text-green-700 flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4"></i><?= $msg ?>
  </div>
  <?php endif; ?>

  <div class="p-4 bg-white border-b border-gray-200">
    <div class="flex gap-2 overflow-x-auto">
      <i data-lucide="filter" class="w-5 h-5 text-gray-600 flex-shrink-0 mt-2"></i>
      <?php foreach(['all'=>'Semua','pending'=>'Pending','processing'=>'Diproses','completed'=>'Selesai'] as $k=>$v): ?>
      <a href="index.php?filter=<?= $k ?>" class="px-4 py-2 rounded-lg whitespace-nowrap text-sm font-medium <?= $filter===$k?'bg-orange-500 text-white':'bg-gray-100 text-gray-700' ?>"><?= $v ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="p-4 space-y-3">
    <?php if (empty($orders)): ?>
    <div class="text-center py-10 text-gray-400"><i data-lucide="package" class="w-12 h-12 mx-auto mb-2 opacity-40"></i><p class="text-sm">Tidak ada order produksi</p></div>
    <?php endif; ?>
    <?php foreach ($orders as $o): ?>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
      <div class="flex items-start justify-between mb-3">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <p class="text-sm font-medium"><?= htmlspecialchars($o['prod_code']) ?></p>
            <?php $bm=['pending'=>'bg-yellow-100 text-yellow-700','processing'=>'bg-blue-100 text-blue-700','completed'=>'bg-green-100 text-green-700']; ?>
            <span class="text-xs px-2 py-0.5 rounded-full <?= $bm[$o['status']]??'' ?>"><?= ucfirst($o['status']) ?></span>
          </div>
          <?php if ($o['order_id']): ?>
          <p class="text-xs text-gray-400">Order terkait: #<?= $o['order_id'] ?></p>
          <?php endif; ?>
        </div>
        <?php $pc=['high'=>'text-red-500','medium'=>'text-orange-500','low'=>'text-green-500']; ?>
        <div class="flex items-center gap-1 <?= $pc[$o['priority']]??'' ?>">
          <i data-lucide="alert-circle" class="w-4 h-4"></i><span class="text-xs"><?= $o['priority'] ?></span>
        </div>
      </div>

      <div class="mb-3 p-3 bg-gray-50 rounded-lg">
        <?php foreach ($o['items'] as $it): ?>
        <div class="flex justify-between text-sm mb-1 last:mb-0">
          <span><?= htmlspecialchars($it['name']) ?></span>
          <span class="text-gray-600 font-medium"><?= $it['qty'] ?> pcs</span>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ($o['status']==='processing'): ?>
      <div class="mb-3">
        <div class="flex justify-between text-xs mb-1"><span class="text-gray-600">Progress</span><span class="text-orange-500 font-medium"><?= $o['progress'] ?>%</span></div>
        <div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-orange-500 h-2 rounded-full transition-all" style="width:<?= $o['progress'] ?>%"></div></div>
      </div>
      <?php endif; ?>

      <div class="flex items-center gap-2 mb-3 text-sm text-gray-500">
        <i data-lucide="clock" class="w-4 h-4"></i>
        <span>Deadline: <?= $o['deadline'] ? date('d M H:i', strtotime($o['deadline'])) : '-' ?></span>
      </div>

      <div class="flex gap-2">
        <?php if ($o['status']==='pending'): ?>
        <form method="POST" class="flex-1">
          <input type="hidden" name="prod_id" value="<?= $o['id'] ?>">
          <input type="hidden" name="new_status" value="processing">
          <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
          <button class="w-full bg-orange-500 text-white py-2.5 rounded-lg text-sm font-medium">🚀 Mulai Produksi</button>
        </form>
        <?php elseif ($o['status']==='processing'): ?>
        <form method="POST" class="flex-1">
          <input type="hidden" name="prod_id" value="<?= $o['id'] ?>">
          <input type="hidden" name="new_status" value="completed">
          <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
          <button class="w-full bg-green-500 text-white py-2.5 rounded-lg text-sm font-medium">✅ Tandai Selesai</button>
        </form>
        <?php else: ?>
        <div class="flex-1 bg-gray-100 text-gray-600 py-2.5 rounded-lg text-sm text-center flex items-center justify-center gap-2">
          <i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i> Selesai - Siap Dikirim
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php include 'nav.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>

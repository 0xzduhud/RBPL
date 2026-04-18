<?php
require_once '../includes/auth.php';
requireLogin('owner');
$pageTitle = 'Karyawan - Dimsum App';
$activeTab = 'employees';
$pdo       = getDB();
$msg       = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $act = $_POST['action'] ?? '';
    
    if ($act === 'toggle_status') {
        $pdo->prepare("UPDATE users SET status=? WHERE id=? AND role='employee'")->execute([$_POST['new_status'],$_POST['emp_id']]);
        $msg = 'Status karyawan berhasil diubah.';
    }
    if ($act === 'add_employee') {
        $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO users (username,password,name,phone,role,status) VALUES (?,?,?,?,'employee','active')")
            ->execute([$_POST['username'],$hash,$_POST['name'],$_POST['phone']]);
        $msg = 'Karyawan berhasil ditambahkan!';
    }
    if ($act === 'edit_employee') {
        $pdo->prepare("UPDATE users SET name=?,phone=?,updated_at=NOW() WHERE id=? AND role='employee'")->execute([$_POST['name'],$_POST['phone'],$_POST['emp_id']]);
        if (!empty($_POST['password'])) {
            $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash,$_POST['emp_id']]);
        }
        $msg = 'Data karyawan berhasil diupdate.';
    }

    header("Location: " . BASE_PATH . "/owner/employees.php?msg=" . urlencode($msg)); exit;
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

$employees   = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM production_orders WHERE employee_id=u.id AND status='completed') AS completed FROM users u WHERE u.role='employee' ORDER BY u.name")->fetchAll();
$activeCount = count(array_filter($employees, fn($e)=>$e['status']==='active'));
?>
<?php include '../includes/header.php'; ?>
<div class="min-h-screen bg-gray-50 pb-20"><div class="max-w-md mx-auto">
  <div class="bg-gradient-to-r from-orange-500 to-red-500 text-white p-4 rounded-b-3xl">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-bold">Manajemen Karyawan</h2>
      <button onclick="openAddEmp()" class="flex items-center gap-1.5 bg-white text-orange-500 px-4 py-2 rounded-xl text-sm font-bold shadow-sm hover:bg-orange-50 transition-colors">
        <i data-lucide="plus" class="w-4 h-4"></i> Tambah Data
      </button>
    </div>
    <div class="grid grid-cols-3 gap-3">
      <div class="bg-white/20 rounded-xl p-3 text-center"><p class="text-2xl font-bold"><?= $activeCount ?></p><p class="text-xs opacity-80">Aktif</p></div>
      <div class="bg-white/20 rounded-xl p-3 text-center"><p class="text-2xl font-bold"><?= count($employees) ?></p><p class="text-xs opacity-80">Total</p></div>
      <div class="bg-white/20 rounded-xl p-3 text-center"><p class="text-2xl font-bold"><?= count($employees)-$activeCount ?></p><p class="text-xs opacity-80">Nonaktif</p></div>
    </div>
  </div>

  <?php if ($msg): ?>
  <div class="mx-4 mt-4 p-3 bg-green-100 border border-green-300 rounded-xl text-sm text-green-700 flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4"></i><?= $msg ?>
  </div>
  <?php endif; ?>

  <div class="p-4 space-y-3">
    <?php foreach ($employees as $e): ?>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
      <div class="flex items-start justify-between mb-3">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center flex-shrink-0">
            <i data-lucide="user" class="w-6 h-6 text-orange-500"></i>
          </div>
          <div>
            <h4 class="font-medium"><?= htmlspecialchars($e['name']) ?></h4>
            <p class="text-xs text-gray-500">@<?= htmlspecialchars($e['username']) ?></p>
            <span class="text-xs px-2 py-0.5 rounded-full <?= $e['status']==='active'?'bg-green-100 text-green-700':'bg-gray-100 text-gray-500' ?>">
              <?= $e['status']==='active'?'Aktif':'Nonaktif' ?>
            </span>
          </div>
        </div>
        <div class="flex gap-1">
          <button onclick="editEmp(<?= htmlspecialchars(json_encode($e)) ?>)" class="p-2 text-blue-500 hover:bg-blue-50 rounded-lg"><i data-lucide="edit" class="w-4 h-4"></i></button>
          <form method="POST">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="emp_id" value="<?= $e['id'] ?>">
            <input type="hidden" name="new_status" value="<?= $e['status']==='active'?'inactive':'active' ?>">
            <button class="p-2 <?= $e['status']==='active'?'text-red-500 hover:bg-red-50':'text-green-500 hover:bg-green-50' ?> rounded-lg">
              <i data-lucide="<?= $e['status']==='active'?'user-x':'user-check' ?>" class="w-4 h-4"></i>
            </button>
          </form>
        </div>
      </div>
      <div class="bg-gray-50 rounded-lg p-3 space-y-1 text-sm">
        <p class="text-gray-600">📞 <?= htmlspecialchars($e['phone']??'-') ?></p>
        <p class="text-gray-600">📅 Bergabung: <?= date('d M Y',strtotime($e['created_at'])) ?></p>
        <p class="text-gray-600">✅ Produksi selesai: <span class="font-medium text-green-600"><?= $e['completed'] ?></span></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- MODAL TAMBAH/EDIT KARYAWAN -->
<div id="modalEmp" class="hidden fixed inset-0 bg-black/60 z-50 flex items-end justify-center">
  <div class="bg-white rounded-t-3xl w-full max-w-md flex flex-col" style="height:88vh">
    <div class="flex-shrink-0 px-5 pt-3 pb-4 border-b border-gray-100">
      <div class="flex justify-center mb-3"><div class="w-10 h-1 bg-gray-200 rounded-full"></div></div>
      <div class="flex justify-between items-center">
        <h3 id="modalEmpTitle" class="text-lg font-bold">Tambah Karyawan</h3>
        <button type="button" onclick="document.getElementById('modalEmp').classList.add('hidden')" class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
      </div>
    </div>
    <form method="POST" class="flex flex-col flex-1 min-h-0">
      <input type="hidden" name="action" id="empAction" value="add_employee">
      <input type="hidden" name="emp_id" id="empId">
      <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3">
        <div><label class="text-xs font-semibold text-gray-600 block mb-1">Nama Lengkap *</label>
          <input name="name" id="empName" required placeholder="Joko Widodo" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
        <div id="usernameField"><label class="text-xs font-semibold text-gray-600 block mb-1">Username * <span class="text-gray-400 font-normal">(untuk login)</span></label>
          <input name="username" id="empUsername" placeholder="joko123" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
        <div><label class="text-xs font-semibold text-gray-600 block mb-1">No. HP</label>
          <input name="phone" id="empPhone" placeholder="081234567890" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
        <div><label class="text-xs font-semibold text-gray-600 block mb-1" id="passLabel">Password *</label>
          <div class="relative">
            <input name="password" id="empPassword" type="password" placeholder="Min. 6 karakter" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 pr-10 text-sm focus:ring-2 focus:ring-orange-500 outline-none">
            <button type="button" onclick="toggleEmpPwd()" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"><i data-lucide="eye" class="w-4 h-4" id="eyeEmp"></i></button>
          </div></div>
      </div>
      <div class="flex-shrink-0 px-5 py-4 border-t border-gray-100">
        <button type="submit" class="w-full bg-orange-500 text-white py-3.5 rounded-xl font-bold text-base">Simpan Karyawan</button>
      </div>
    </form>
  </div>
</div>

<?php include 'nav.php'; ?>
</div>

<script>
function openAddEmp() {
  document.getElementById('modalEmpTitle').textContent = 'Tambah Karyawan';
  document.getElementById('empAction').value = 'add_employee';
  document.getElementById('empId').value = '';
  document.getElementById('usernameField').classList.remove('hidden');
  document.getElementById('passLabel').textContent = 'Password *';
  ['empName','empUsername','empPhone','empPassword'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('empUsername').required = true;
  document.getElementById('empPassword').required = true;
  document.getElementById('modalEmp').classList.remove('hidden');
  lucide.createIcons();
}
function editEmp(e) {
  document.getElementById('modalEmpTitle').textContent = 'Edit Karyawan';
  document.getElementById('empAction').value = 'edit_employee';
  document.getElementById('empId').value = e.id;
  document.getElementById('empName').value = e.name;
  document.getElementById('empPhone').value = e.phone || '';
  document.getElementById('empPassword').value = '';
  document.getElementById('usernameField').classList.add('hidden');
  document.getElementById('passLabel').textContent = 'Password Baru (kosongkan jika tidak diubah)';
  document.getElementById('empUsername').required = false;
  document.getElementById('empPassword').required = false;
  document.getElementById('modalEmp').classList.remove('hidden');
  lucide.createIcons();
}
function toggleEmpPwd() {
  const i = document.getElementById('empPassword');
  const e = document.getElementById('eyeEmp');
  i.type = i.type === 'password' ? 'text' : 'password';
  e.setAttribute('data-lucide', i.type === 'password' ? 'eye' : 'eye-off');
  lucide.createIcons();
}
document.getElementById('modalEmp').addEventListener('click', function(e) {
  if(e.target === this) this.classList.add('hidden');
});
</script>
<?php include '../includes/footer.php'; ?>

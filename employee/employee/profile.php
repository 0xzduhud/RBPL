<?php
require_once '../includes/auth.php';
requireLogin('employee');
$pageTitle = 'Profil - Dimsum App';
$activeTab = 'profile';
$pdo       = getDB();
$userId    = getUserId();
$msg       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'update_profile') {
        $pdo->prepare("UPDATE users SET name=?,phone=?,updated_at=NOW() WHERE id=?")->execute([trim($_POST['name']),trim($_POST['phone']),$userId]);
        $msg = 'Profil berhasil diperbarui!';
    }
    if ($act === 'change_password') {
        $usr = $pdo->prepare("SELECT password FROM users WHERE id=?"); $usr->execute([$userId]); $usr = $usr->fetch();
        if (!password_verify($_POST['old_password']??'', $usr['password'])) $msg = 'Password lama salah!';
        elseif (strlen($_POST['new_password']??'') < 6) $msg = 'Password baru minimal 6 karakter!';
        elseif ($_POST['new_password'] !== $_POST['confirm_password']) $msg = 'Konfirmasi tidak cocok!';
        else { $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['new_password'],PASSWORD_BCRYPT),$userId]); $msg = 'Password berhasil diubah!'; }
    }
    if ($act === 'logout') logout();
}

$user       = $pdo->prepare("SELECT * FROM users WHERE id=?"); $user->execute([$userId]); $user = $user->fetch();
$completed  = $pdo->prepare("SELECT COUNT(*) FROM production_orders WHERE employee_id=? AND status='completed'"); $completed->execute([$userId]); $completed = $completed->fetchColumn();
$inProgress = $pdo->prepare("SELECT COUNT(*) FROM production_orders WHERE employee_id=? AND status='processing'"); $inProgress->execute([$userId]); $inProgress = $inProgress->fetchColumn();
?>
<?php include '../includes/header.php'; ?>
<div class="min-h-screen bg-gray-50 pb-20"><div class="max-w-md mx-auto">
  <div class="bg-gradient-to-r from-orange-500 to-red-500 text-white p-6 rounded-b-3xl">
    <div class="flex items-center gap-4 mb-4">
      <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center text-3xl">👷</div>
      <div>
        <h2 class="text-xl font-bold"><?= htmlspecialchars($user['name']) ?></h2>
        <p class="text-orange-100 text-sm">Karyawan · @<?= htmlspecialchars($user['username']) ?></p>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div class="bg-white/20 rounded-xl p-3 text-center"><p class="text-2xl font-bold"><?= $completed ?></p><p class="text-xs opacity-80">Produksi Selesai</p></div>
      <div class="bg-white/20 rounded-xl p-3 text-center"><p class="text-2xl font-bold"><?= $inProgress ?></p><p class="text-xs opacity-80">Sedang Proses</p></div>
    </div>
  </div>

  <?php if ($msg): ?>
  <div class="mx-4 mt-4 p-3 bg-green-100 border border-green-300 rounded-xl text-sm text-green-700 flex gap-2">
    <i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0 mt-0.5"></i><?= htmlspecialchars($msg) ?>
  </div>
  <?php endif; ?>

  <div class="p-4 space-y-4">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <button onclick="toggle('fp')" class="w-full flex items-center justify-between p-4">
        <div class="flex items-center gap-3"><div class="w-9 h-9 bg-orange-100 rounded-xl flex items-center justify-center"><i data-lucide="user" class="w-4 h-4 text-orange-500"></i></div><span class="font-semibold text-sm">Edit Profil</span></div>
        <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
      </button>
      <div id="fp" class="hidden border-t border-gray-100 p-4">
        <form method="POST" class="space-y-3">
          <input type="hidden" name="action" value="update_profile">
          <div><label class="text-xs text-gray-500 block mb-1">Nama</label><input name="name" value="<?= htmlspecialchars($user['name']) ?>" class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
          <div><label class="text-xs text-gray-500 block mb-1">No. HP</label><input name="phone" value="<?= htmlspecialchars($user['phone']??'') ?>" class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
          <button type="submit" class="w-full bg-orange-500 text-white py-3 rounded-xl font-semibold text-sm">Simpan</button>
        </form>
      </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <button onclick="toggle('fpp')" class="w-full flex items-center justify-between p-4">
        <div class="flex items-center gap-3"><div class="w-9 h-9 bg-blue-100 rounded-xl flex items-center justify-center"><i data-lucide="lock" class="w-4 h-4 text-blue-500"></i></div><span class="font-semibold text-sm">Ganti Password</span></div>
        <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
      </button>
      <div id="fpp" class="hidden border-t border-gray-100 p-4">
        <form method="POST" class="space-y-3">
          <input type="hidden" name="action" value="change_password">
          <div><label class="text-xs text-gray-500 block mb-1">Password Lama</label><input type="password" name="old_password" class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
          <div><label class="text-xs text-gray-500 block mb-1">Password Baru</label><input type="password" name="new_password" class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
          <div><label class="text-xs text-gray-500 block mb-1">Konfirmasi</label><input type="password" name="confirm_password" class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
          <button type="submit" class="w-full bg-blue-500 text-white py-3 rounded-xl font-semibold text-sm">Ubah Password</button>
        </form>
      </div>
    </div>
    <form method="POST"><input type="hidden" name="action" value="logout">
      <button class="w-full bg-red-50 text-red-500 border border-red-200 py-4 rounded-2xl font-semibold flex items-center justify-center gap-2"><i data-lucide="log-out" class="w-5 h-5"></i> Keluar</button>
    </form>
  </div>
</div>
<?php include 'nav.php'; ?>
</div>
<script>function toggle(id){document.getElementById(id).classList.toggle('hidden');lucide.createIcons();}</script>
<?php include '../includes/footer.php'; ?>

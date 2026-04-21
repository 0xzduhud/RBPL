<?php
require_once '../includes/auth.php';
requireLogin('customer');
$pageTitle = 'Profil - Dimsum App';
$activeTab = 'profile';
$pdo       = getDB();
$userId    = getUserId();
$msg       = '';

// Handle update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name) $pdo->prepare("UPDATE users SET name=?,phone=?,updated_at=NOW() WHERE id=?")->execute([$name,$phone,$userId]);
        // Update alamat
        $address = trim($_POST['address'] ?? '');
        $city    = trim($_POST['city'] ?? '');
        $cek = $pdo->prepare("SELECT id FROM addresses WHERE user_id=? AND is_default=1 LIMIT 1");
        $cek->execute([$userId]);
        if ($cek->fetch()) {
            $pdo->prepare("UPDATE addresses SET address=?,city=? WHERE user_id=? AND is_default=1")->execute([$address,$city,$userId]);
        } else {
            $pdo->prepare("INSERT INTO addresses (user_id,label,address,city,is_default) VALUES (?,'Rumah',?,?,1)")->execute([$userId,$address,$city]);
        }
        $msg = 'Profil berhasil diperbarui!';
    }
    if ($act === 'change_password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $con = $_POST['confirm_password'] ?? '';
        $usr = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $usr->execute([$userId]); $usr = $usr->fetch();
        if (!password_verify($old, $usr['password'])) $msg = 'Password lama salah!';
        elseif (strlen($new) < 6)                     $msg = 'Password baru minimal 6 karakter!';
        elseif ($new !== $con)                         $msg = 'Konfirmasi password tidak cocok!';
        else {
            $pdo->prepare("UPDATE users SET password=?,updated_at=NOW() WHERE id=?")->execute([password_hash($new,PASSWORD_BCRYPT),$userId]);
            $msg = 'Password berhasil diubah!';
        }
    }
    if ($act === 'logout') { logout(); }
}

$user  = $pdo->prepare("SELECT * FROM users WHERE id=?"); $user->execute([$userId]); $user = $user->fetch();
$addr  = $pdo->prepare("SELECT * FROM addresses WHERE user_id=? AND is_default=1 LIMIT 1"); $addr->execute([$userId]); $addr = $addr->fetch();
$stats = $pdo->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) AS selesai, COALESCE(SUM(CASE WHEN status='delivered' THEN total ELSE 0 END),0) AS spent FROM orders WHERE customer_id=?");
$stats->execute([$userId]); $stats = $stats->fetch();
?>
<?php include '../includes/header.php'; ?>
<div class="min-h-screen bg-gray-50 pb-20"><div class="max-w-md mx-auto">
  <div class="bg-gradient-to-r from-orange-500 to-red-500 text-white p-6 rounded-b-3xl">
    <div class="flex items-center gap-4">
      <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center text-3xl">👤</div>
      <div>
        <h2 class="text-xl font-bold"><?= htmlspecialchars($user['name']) ?></h2>
        <p class="text-orange-100 text-sm">@<?= htmlspecialchars($user['username']) ?></p>
        <p class="text-orange-100 text-xs"><?= htmlspecialchars($user['phone']??'-') ?></p>
      </div>
    </div>
    <div class="grid grid-cols-3 gap-3 mt-4">
      <div class="bg-white/20 rounded-xl p-3 text-center"><p class="text-xl font-bold"><?= $stats['total'] ?></p><p class="text-xs opacity-80">Pesanan</p></div>
      <div class="bg-white/20 rounded-xl p-3 text-center"><p class="text-xl font-bold"><?= $stats['selesai'] ?></p><p class="text-xs opacity-80">Selesai</p></div>
      <div class="bg-white/20 rounded-xl p-3 text-center"><p class="text-lg font-bold"><?= number_format($stats['spent']/1000,0) ?>k</p><p class="text-xs opacity-80">Total Belanja</p></div>
    </div>
  </div>

  <?php if ($msg): ?>
  <div class="mx-4 mt-4 p-3 bg-green-100 border border-green-300 rounded-xl text-sm text-green-700 flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4"></i><?= htmlspecialchars($msg) ?>
  </div>
  <?php endif; ?>

  <div class="p-4 space-y-4">
    <!-- Edit Profil -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <button onclick="toggle('formProfil')" class="w-full flex items-center justify-between p-4">
        <div class="flex items-center gap-3"><div class="w-9 h-9 bg-orange-100 rounded-xl flex items-center justify-center"><i data-lucide="user" class="w-4 h-4 text-orange-500"></i></div><span class="font-semibold text-sm">Edit Profil</span></div>
        <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
      </button>
      <div id="formProfil" class="hidden border-t border-gray-100 p-4">
        <form method="POST" class="space-y-3">
          <input type="hidden" name="action" value="update_profile">
          <div><label class="text-xs text-gray-500 block mb-1">Nama Lengkap</label>
            <input name="name" value="<?= htmlspecialchars($user['name']) ?>" class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
          <div><label class="text-xs text-gray-500 block mb-1">No. HP</label>
            <input name="phone" value="<?= htmlspecialchars($user['phone']??'') ?>" class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
          <div><label class="text-xs text-gray-500 block mb-1">Alamat Pengiriman</label>
            <input name="address" value="<?= htmlspecialchars($addr['address']??'') ?>" placeholder="Jl. Contoh No. 123" class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
          <div><label class="text-xs text-gray-500 block mb-1">Kota</label>
            <input name="city" value="<?= htmlspecialchars($addr['city']??'') ?>" placeholder="Jakarta Selatan" class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
          <button type="submit" class="w-full bg-orange-500 text-white py-3 rounded-xl font-semibold text-sm">Simpan Perubahan</button>
        </form>
      </div>
    </div>

    <!-- Ganti Password -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <button onclick="toggle('formPwd')" class="w-full flex items-center justify-between p-4">
        <div class="flex items-center gap-3"><div class="w-9 h-9 bg-blue-100 rounded-xl flex items-center justify-center"><i data-lucide="lock" class="w-4 h-4 text-blue-500"></i></div><span class="font-semibold text-sm">Ganti Password</span></div>
        <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
      </button>
      <div id="formPwd" class="hidden border-t border-gray-100 p-4">
        <form method="POST" class="space-y-3">
          <input type="hidden" name="action" value="change_password">
          <div><label class="text-xs text-gray-500 block mb-1">Password Lama</label>
            <input type="password" name="old_password" class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
          <div><label class="text-xs text-gray-500 block mb-1">Password Baru</label>
            <input type="password" name="new_password" class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
          <div><label class="text-xs text-gray-500 block mb-1">Konfirmasi Password</label>
            <input type="password" name="confirm_password" class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-orange-500 outline-none"></div>
          <button type="submit" class="w-full bg-blue-500 text-white py-3 rounded-xl font-semibold text-sm">Ubah Password</button>
        </form>
      </div>
    </div>

    <!-- Logout -->
    <form method="POST">
      <input type="hidden" name="action" value="logout">
      <button type="submit" class="w-full bg-red-50 text-red-500 border border-red-200 py-4 rounded-2xl font-semibold flex items-center justify-center gap-2">
        <i data-lucide="log-out" class="w-5 h-5"></i> Keluar dari Akun
      </button>
    </form>
  </div>
</div>
<?php include 'nav.php'; ?>
</div>
<script>
function toggle(id) {
  const el = document.getElementById(id);
  el.classList.toggle('hidden');
  lucide.createIcons();
}
</script>
<?php include '../includes/footer.php'; ?>

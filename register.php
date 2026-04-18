<?php
require_once 'includes/auth.php';

if (isLoggedIn()) redirect("/" . getRole() . "/index.php");

$pdo     = getDB();
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$name || !$username || !$password)
        $error = 'Nama, username, dan password wajib diisi.';
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username))
        $error = 'Username hanya boleh huruf, angka, dan underscore.';
    elseif (strlen($password) < 6)
        $error = 'Password minimal 6 karakter.';
    elseif ($password !== $confirm)
        $error = 'Konfirmasi password tidak cocok.';
    else {
        $cek = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $cek->execute([$username]);
        if ($cek->fetch()) {
            $error = 'Username sudah digunakan, coba yang lain.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            // Role SELALU customer
            $pdo->prepare("INSERT INTO users (username,password,name,phone,role,status) VALUES (?,?,?,?,'customer','active')")
                ->execute([$username, $hash, $name, $phone]);
            $newId = $pdo->lastInsertId();
            // Buat alamat default
            $pdo->prepare("INSERT INTO addresses (user_id,label,address,city,is_default) VALUES (?,'Rumah','','',1)")
                ->execute([$newId]);
            $success = true;
        }
    }
}

$pageTitle = 'Daftar - Dimsum App';
?>
<?php include 'includes/header.php'; ?>
<div class="min-h-screen bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center p-4">
  <div class="w-full max-w-sm">

    <div class="text-center mb-6">
      <div class="w-16 h-16 bg-white rounded-full mx-auto mb-3 flex items-center justify-center shadow-lg">
        <span class="text-3xl">🥟</span>
      </div>
      <h1 class="text-white text-xl font-bold">Daftar Akun Customer</h1>
      <p class="text-orange-100 text-xs mt-1">Daftar untuk bisa memesan dimsum</p>
    </div>

    <div class="bg-white rounded-2xl shadow-xl p-6">

      <?php if ($success): ?>
      <div class="text-center py-4">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
          <i data-lucide="check-circle" class="w-8 h-8 text-green-500"></i>
        </div>
        <h3 class="font-bold text-gray-800 mb-1">Akun Berhasil Dibuat!</h3>
        <p class="text-sm text-gray-500 mb-4">Selamat datang! Silakan login untuk mulai memesan.</p>
        <a href="index.php" class="block w-full bg-orange-500 text-white py-3 rounded-xl font-bold text-center">
          Login Sekarang →
        </a>
      </div>

      <?php else: ?>

      <?php if ($error): ?>
      <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm flex items-center gap-2">
        <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <!-- Badge customer only -->
      <div class="mb-4 p-3 bg-orange-50 border border-orange-200 rounded-xl flex items-center gap-2">
        <span class="text-xl">🛒</span>
        <div>
          <p class="text-xs font-bold text-orange-700">Pendaftaran Customer</p>
          <p class="text-xs text-orange-600">Akun karyawan & owner dibuat oleh admin</p>
        </div>
      </div>

      <form method="POST" class="space-y-3">
        <div>
          <label class="block text-xs font-semibold mb-1 text-gray-600">Nama Lengkap *</label>
          <div class="relative">
            <i data-lucide="user" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
            <input type="text" name="name" placeholder="Budi Santoso"
              value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required
              class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-orange-500 outline-none">
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold mb-1 text-gray-600">Username *</label>
          <div class="relative">
            <i data-lucide="at-sign" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
            <input type="text" name="username" placeholder="budi123"
              value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required
              class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-orange-500 outline-none">
          </div>
          <p class="text-xs text-gray-400 mt-0.5 ml-1">Huruf, angka, underscore saja</p>
        </div>

        <div>
          <label class="block text-xs font-semibold mb-1 text-gray-600">No. HP</label>
          <div class="relative">
            <i data-lucide="phone" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
            <input type="tel" name="phone" placeholder="081234567890"
              value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
              class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-orange-500 outline-none">
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold mb-1 text-gray-600">Password * (min. 6 karakter)</label>
          <div class="relative">
            <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
            <input type="password" name="password" id="pwd1" placeholder="••••••••" required
              class="w-full pl-10 pr-10 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-orange-500 outline-none">
            <button type="button" onclick="tp('pwd1','e1')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
              <i data-lucide="eye" class="w-4 h-4" id="e1"></i>
            </button>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold mb-1 text-gray-600">Konfirmasi Password *</label>
          <div class="relative">
            <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
            <input type="password" name="confirm" id="pwd2" placeholder="••••••••" required
              class="w-full pl-10 pr-10 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-orange-500 outline-none">
            <button type="button" onclick="tp('pwd2','e2')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
              <i data-lucide="eye" class="w-4 h-4" id="e2"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="w-full bg-orange-500 text-white py-3 rounded-xl font-bold hover:bg-orange-600 transition-colors mt-2">
          Daftar Sekarang
        </button>
      </form>
      <?php endif; ?>
    </div>

    <div class="text-center mt-4">
      <p class="text-white text-sm">Sudah punya akun?
        <a href="index.php" class="underline font-bold">Masuk</a>
      </p>
    </div>
  </div>
</div>
<script>
function tp(id,eid){
  const i=document.getElementById(id);
  const e=document.getElementById(eid);
  i.type=i.type==='password'?'text':'password';
  e.setAttribute('data-lucide',i.type==='password'?'eye':'eye-off');
  lucide.createIcons();
}
</script>
<?php include 'includes/footer.php'; ?>

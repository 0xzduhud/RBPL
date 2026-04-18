<?php
require_once 'includes/auth.php';

if (isLoggedIn()) {
    redirect("/" . getRole() . "/index.php");
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $result   = attemptLogin($username, $password);
    if ($result['success']) {
        redirect("/{$result['role']}/index.php");
    } else {
        $error = $result['message'];
    }
}

$pageTitle = 'Login - Dimsum App';
?>
<?php include 'includes/header.php'; ?>
<div class="min-h-screen bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center p-4">
  <div class="w-full max-w-sm">

    <div class="text-center mb-8">
      <div class="w-20 h-20 bg-white rounded-full mx-auto mb-4 flex items-center justify-center shadow-lg">
        <span class="text-4xl">🥟</span>
      </div>
      <h1 class="text-white text-2xl font-bold mb-1">Dimsum App</h1>
      <p class="text-orange-100 text-sm">Sistem Manajemen Dimsum</p>
    </div>

    <div class="bg-white rounded-2xl shadow-xl p-6">
      <h2 class="text-lg font-bold text-center mb-1">Masuk ke Akun</h2>
      <p class="text-xs text-gray-400 text-center mb-5">Sistem akan otomatis mengarahkan sesuai role akun kamu</p>

      <?php if ($error): ?>
      <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm flex items-center gap-2">
        <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1.5 text-gray-700">Username</label>
          <div class="relative">
            <i data-lucide="user" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
            <input type="text" name="username" placeholder="Masukkan username"
              value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required
              class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none text-sm"/>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1.5 text-gray-700">Password</label>
          <div class="relative">
            <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
            <input type="password" name="password" id="pwd" placeholder="Masukkan password" required
              class="w-full pl-10 pr-10 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none text-sm"/>
            <button type="button" onclick="togglePwd()" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
              <i data-lucide="eye" class="w-4 h-4" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <button type="submit"
          class="w-full bg-orange-500 text-white py-3 rounded-xl font-bold hover:bg-orange-600 transition-colors">
          Masuk
        </button>
      </form>

      <!-- Info role -->
      <div class="mt-4 p-3 bg-gray-50 rounded-xl">
        <p class="text-xs text-gray-500 font-medium mb-2">Info akun:</p>
        <div class="flex gap-2 text-xs text-gray-500">
          <span>🛒 Customer</span>
          <span>·</span>
          <span>👷 Karyawan</span>
          <span>·</span>
          <span>👑 Owner</span>
        </div>
        <p class="text-xs text-gray-400 mt-1">Role ditentukan otomatis dari database</p>
      </div>
    </div>

    <div class="text-center mt-5">
      <p class="text-white text-sm">Belum punya akun?
        <a href="register.php" class="underline font-bold">Daftar sebagai Customer</a>
      </p>
    </div>
  </div>
</div>
<script>
function togglePwd() {
  const i = document.getElementById('pwd');
  const e = document.getElementById('eyeIcon');
  i.type = i.type === 'password' ? 'text' : 'password';
  e.setAttribute('data-lucide', i.type === 'password' ? 'eye' : 'eye-off');
  lucide.createIcons();
}
</script>
<?php include 'includes/footer.php'; ?>

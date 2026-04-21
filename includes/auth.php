<?php
session_start();
require_once __DIR__ . '/db.php';

// Auto-detect base path (e.g. /dimsum_app or empty if at root)
if (!defined('BASE_PATH')) {
    
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    
    $parts = explode('/', trim($scriptDir, '/'));
   
    $subfolders = ['customer', 'employee', 'owner'];
    if (in_array(end($parts), $subfolders)) {
        array_pop($parts);
    }
    define('BASE_PATH', '/' . implode('/', array_filter($parts)));
}

if (!defined('BASE_URL')) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
    define('BASE_URL', $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_PATH);
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}
function getRole(): ?string  { return $_SESSION['user_role'] ?? null; }
function getUserName(): string { return $_SESSION['user_name'] ?? 'User'; }
function getUserId(): ?int   { return $_SESSION['user_id'] ?? null; }

function redirect(string $path): void {
    header('Location: ' . BASE_PATH . $path);
    exit;
}

function requireLogin(?string $role = null): void {
    if (!isLoggedIn()) { redirect('/index.php'); }
    if ($role && getRole() !== $role) {
        redirect('/' . getRole() . '/index.php');
    }
}

function logout(): void {
    session_destroy();
    redirect('/index.php');
}

function attemptLogin(string $username, string $password): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT id, username, password, name, role, status FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user)                  return ['success'=>false, 'message'=>'Username tidak ditemukan.'];
    if ($user['status']!=='active') return ['success'=>false, 'message'=>'Akun tidak aktif.'];
    if (!password_verify($password, $user['password'])) return ['success'=>false, 'message'=>'Password salah.'];
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    return ['success'=>true, 'role'=>$user['role']];
}

<?php
// Helper: simpan notifikasi ke session
function addNotification(string $message, string $type = 'info'): void {
    if (!isset($_SESSION['notifications'])) $_SESSION['notifications'] = [];
    $_SESSION['notifications'][] = ['msg' => $message, 'type' => $type, 'time' => date('H:i')];
    // Simpan juga ke DB notifications jika ada user login
}

function getNotifications(): array {
    return $_SESSION['notifications'] ?? [];
}

function clearNotifications(): void {
    $_SESSION['notifications'] = [];
}

function countUnread(): int {
    return count($_SESSION['notifications'] ?? []);
}

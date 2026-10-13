<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        if (in_array(basename($scriptDir), ['books', 'loans', 'members', 'requests', 'reports'], true)) {
            $scriptDir = dirname($scriptDir);
        }
        header('Location: ' . rtrim($scriptDir, '/') . '/index.php');
        exit;
    }
}
function require_admin(): void
{
    require_login();
    if (($_SESSION['role'] ?? '') !== 'Admin') {
        http_response_code(403);
        exit('Access denied. Administrator permission is required.');
    }
}
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

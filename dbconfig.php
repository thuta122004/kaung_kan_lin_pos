<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$dbHost = 'localhost';
$dbName = 'kaungkanlin_db';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('<div style="font-family:sans-serif;padding:2rem;color:#B85B6C">Database connection failed. Check dbconfig.php and make sure schema.sql has been imported.</div>');
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function mmk($n): string { return number_format((float)$n, 0) . ' MMK'; }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid or expired form token. Please go back and reload the page.');
    }
}
function flash(string $msg, string $type = 'success'): void { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; }
function redirect(string $url): void { header('Location: ' . $url); exit; }

function status_badge(string $s): string {
    $map = [
        'Pending'   => 'bg-amber-100 text-amber-800 border-amber-200',
        'Completed' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
        'Cancelled' => 'bg-rose-100 text-rose-800 border-rose-200',
    ];
    return '<span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold border ' . ($map[$s] ?? '') . '">' . e($s) . '</span>';
}
function channel_badge(string $c): string {
    $map = [
        'Viber'    => ['fa-brands fa-viber', 'bg-purple-50 text-purple-700 border-purple-200'],
        'Telegram' => ['fa-brands fa-telegram', 'bg-sky-50 text-sky-700 border-sky-200'],
        'In-Store' => ['fa-solid fa-store', 'bg-orange-50 text-orange-700 border-orange-200'],
        'Website'  => ['fa-solid fa-globe', 'bg-teal-50 text-teal-700 border-teal-200'],
    ];
    [$icon, $cls] = $map[$c] ?? ['fa-solid fa-circle', 'bg-gray-50 text-gray-700 border-gray-200'];
    return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold border ' . $cls . '"><i class="' . $icon . '"></i>' . e($c) . '</span>';
}

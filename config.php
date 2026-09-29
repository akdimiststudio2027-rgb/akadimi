<?php
    
   error_reporting(E_ALL);
ini_set('display_errors', '1');
// أكاديمي التعليمية - إعدادات قاعدة البيانات
$db_host = 'sql113.infinityfree.com';
$db_name = 'if0_42886093_akadimi';
$db_user = 'if0_42886093';
$db_pass = 'Q4WYsKuvMy';

if (session_status() === PHP_SESSION_NONE) session_start();

function db(): PDO {
    static $pdo = null;
    global $db_host,$db_name,$db_user,$db_pass;
    if ($pdo === null) {
        $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function redirect($url){ header('Location: '.$url); exit; }
function user(){ return $_SESSION['user'] ?? null; }
function require_login(){ if (!user()) redirect('/auth/login.php'); }
function require_role($role){ require_login(); if (user()['role'] !== $role) redirect('/'); }


function admin_logged_in(): bool {
    return isset($_SESSION['admin_id']);
}
function require_admin(): void {
    if (!admin_logged_in()) {
        header('Location: /admin/login.php');
        exit;
    }
}

?>


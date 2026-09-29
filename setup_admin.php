<?php
require_once __DIR__ . '/../config/config.php';

$message = '';

try {
    db()->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $stmt = db()->prepare("SELECT id FROM admin_users WHERE username = ? LIMIT 1");
    $stmt->execute(['admin']);
    if (!$stmt->fetch()) {
        $hash = password_hash('Admin@12345', PASSWORD_DEFAULT);
        $stmt = db()->prepare("INSERT INTO admin_users (username,password_hash) VALUES (?,?)");
        $stmt->execute(['admin', $hash]);
        $message = 'تم إنشاء حساب الأدمن التجريبي.';
    } else {
        $message = 'حساب الأدمن موجود مسبقًا.';
    }
} catch (Throwable $e) {
    $message = 'خطأ: ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>إعداد الأدمن</title></head>
<body style="font-family:Tahoma;padding:40px">
<h2><?= htmlspecialchars($message) ?></h2>
<p>اسم المستخدم: <b>admin</b></p>
<p>كلمة المرور المؤقتة: <b>Admin@12345</b></p>
<p>بعد نجاح الدخول احذف الملف <b>setup_admin.php</b> من الاستضافة فورًا.</p>
<a href="/admin/login.php">الانتقال إلى تسجيل الدخول</a>
</body></html>

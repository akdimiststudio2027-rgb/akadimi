<?php

session_start();

require_once __DIR__ . '/../config/config.php';


$pdo = db();

if (empty($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
        require_once __DIR__ . '/student_auth_check.php';

}

$student_id = (int) $_SESSION['student_id'];

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        phone,
        password,
        active
    FROM students
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$student_id]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if (isset($student['active']) && (int)$student['active'] !== 1) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

/* =====================================================
   حفظ الإعدادات
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($name === '') {

        $error = 'يرجى إدخال اسم الطالب.';

    } elseif ($phone === '') {

        $error = 'يرجى إدخال رقم الهاتف.';

    } elseif ($new_password !== '' && strlen($new_password) < 6) {

        $error = 'كلمة المرور يجب أن تكون 6 أحرف أو أكثر.';

    } elseif ($new_password !== $confirm_password) {

        $error = 'كلمتا المرور غير متطابقتين.';

    } else {

        try {

            if ($new_password !== '') {

                $stmt = $pdo->prepare("
                    UPDATE students
                    SET
                        name = ?,
                        phone = ?,
                        password = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $name,
                    $phone,
                    $new_password,
                    $student_id
                ]);

            } else {

                $stmt = $pdo->prepare("
                    UPDATE students
                    SET
                        name = ?,
                        phone = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $name,
                    $phone,
                    $student_id
                ]);
            }

            $student['name'] = $name;
            $student['phone'] = $phone;

            $message = 'تم حفظ إعدادات حسابك بنجاح ✅';

        } catch (PDOException $e) {

            $error = 'حدث خطأ أثناء حفظ البيانات، حاول مرة أخرى.';
        }
    }
}

?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>إعدادات الحساب | منصة أكاديمي</title>

<style>

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
}

body {

    font-family:
        Tahoma,
        Arial,
        sans-serif;

    background: #f5f8fc;

    color: #172033;

    min-height: 100vh;

    padding-bottom: 90px;
}

.container {

    width: 100%;

    max-width: 700px;

    margin: auto;

    padding: 18px 16px;
}

.header {

    display: flex;

    align-items: center;

    gap: 14px;

    margin-bottom: 20px;
}

.back-btn {

    width: 45px;

    height: 45px;

    border-radius: 14px;

    background: white;

    border: 1px solid #e5eaf0;

    display: flex;

    align-items: center;

    justify-content: center;

    text-decoration: none;

    color: #345c49;

    font-size: 21px;
}

.header-title {

    font-size: 22px;

    font-weight: bold;
}

.header-subtitle {

    color: #8a98ab;

    font-size: 12px;

    margin-top: 5px;
}

.card {

    background: white;

    border: 1px solid #e5eaf0;

    border-radius: 24px;

    padding: 20px;

    box-shadow:
        0 8px 25px rgba(0,0,0,.04);

    margin-bottom: 16px;
}

.profile-head {

    display: flex;

    align-items: center;

    gap: 14px;

    margin-bottom: 22px;
}

.avatar {

    width: 60px;

    height: 60px;

    border-radius: 20px;

    background: #345c49;

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 25px;

    font-weight: bold;
}

.profile-name {

    font-size: 17px;

    font-weight: bold;

    color: #345c49;
}

.profile-text {

    color: #8a98ab;

    font-size: 11px;

    margin-top: 5px;
}

.form-group {

    margin-bottom: 17px;
}

label {

    display: block;

    font-size: 13px;

    font-weight: bold;

    margin-bottom: 7px;
}

input {

    width: 100%;

    height: 48px;

    border: 1px solid #dfe5ec;

    border-radius: 14px;

    padding: 0 14px;

    font-family: inherit;

    font-size: 14px;

    outline: none;

    background: #fafbfd;
}

input:focus {

    border-color: #9bac78;

    background: white;
}

.hint {

    color: #8a98ab;

    font-size: 10px;

    margin-top: 6px;
}

.message {

    background: #e8f4ed;

    color: #345c49;

    border-radius: 14px;

    padding: 12px;

    font-size: 12px;

    margin-bottom: 15px;

    text-align: center;
}

.error {

    background: #fff0f0;

    color: #a33b3b;

    border-radius: 14px;

    padding: 12px;

    font-size: 12px;

    margin-bottom: 15px;

    text-align: center;
}

.save-btn {

    width: 100%;

    height: 50px;

    border: 0;

    border-radius: 15px;

    background: #345c49;

    color: white;

    font-family: inherit;

    font-size: 14px;

    font-weight: bold;

    cursor: pointer;
}

.save-btn:active {

    transform: scale(.98);
}

.logout {

    display: block;

    text-align: center;

    text-decoration: none;

    color: #a33b3b;

    background: #fff2f2;

    border-radius: 15px;

    padding: 14px;

    font-size: 13px;

    font-weight: bold;

    margin-top: 12px;
}


/* =====================================================
   الشريط السفلي
===================================================== */

.bottom-nav {

    position: fixed;

    left: 0;

    right: 0;

    bottom: 0;

    height: 72px;

    background: rgba(255,255,255,.97);

    border-top: 1px solid #e5e9ee;

    display: flex;

    justify-content: center;

    z-index: 1000;
}

.bottom-inner {

    width: 100%;

    max-width: 600px;

    display: grid;

    grid-template-columns: repeat(5, 1fr);
}

.bottom-item {

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    gap: 4px;

    text-decoration: none;

    color: #8a98ab;

    font-size: 9px;
}

.bottom-item.active {

    color: #345c49;
}

.bottom-icon {

    font-size: 19px;
}

</style>

</head>

<body>

<div class="container">

    <header class="header">

        <a href="index.php" class="back-btn">
            ←
        </a>

        <div>

            <div class="header-title">
                إعدادات الحساب
            </div>

            <div class="header-subtitle">
                إدارة بيانات حسابك الشخصي
            </div>

        </div>

    </header>


    <div class="card">

        <div class="profile-head">

            <div class="avatar">

                <?= e(
                    mb_substr(
                        trim($student['name']),
                        0,
                        1,
                        'UTF-8'
                    )
                ) ?>

            </div>

            <div>

                <div class="profile-name">
                    <?= e($student['name']) ?>
                </div>

                <div class="profile-text">
                    حساب الطالب
                </div>

            </div>

        </div>


        <?php if ($message): ?>

            <div class="message">
                <?= e($message) ?>
            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <form method="POST">


            <div class="form-group">

                <label>
                    اسم الطالب
                </label>

                <input
                    type="text"
                    name="name"
                    value="<?= e($student['name']) ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    رقم الهاتف
                </label>

                <input
                    type="tel"
                    name="phone"
                    value="<?= e($student['phone']) ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    كلمة المرور الجديدة
                </label>

                <input
                    type="password"
                    name="new_password"
                    placeholder="اتركها فارغة إذا لا تريد تغييرها"
                >

                <div class="hint">
                    اترك الحقل فارغاً للاحتفاظ بكلمة المرور الحالية.
                </div>

            </div>


            <div class="form-group">

                <label>
                    تأكيد كلمة المرور الجديدة
                </label>

                <input
                    type="password"
                    name="confirm_password"
                    placeholder="أعد كتابة كلمة المرور"
                >

            </div>


            <button
                type="submit"
                class="save-btn"
            >
                💾 حفظ التغييرات
            </button>

        </form>


        <a
            href="logout.php"
            class="logout"
        >
            🚪 تسجيل الخروج
        </a>

    </div>

</div>


<nav class="bottom-nav">

    <div class="bottom-inner">

        <a href="index.php" class="bottom-item">

            <div class="bottom-icon">🏠</div>

            الرئيسية

        </a>


        <a href="notes.php" class="bottom-item">

            <div class="bottom-icon">📝</div>

            مفكرتي

        </a>


        <a href="settings.php" class="bottom-item active">

            <div class="bottom-icon">⚙️</div>

            الإعدادات

        </a>


        <a href="support.php" class="bottom-item">

            <div class="bottom-icon">💬</div>

            الدعم الفني

        </a>


        <a href="profile.php" class="bottom-item">

            <div class="bottom-icon">👤</div>

            حسابي

        </a>

    </div>

</nav>

</body>

</html>
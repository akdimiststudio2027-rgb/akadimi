<?php

session_start();

require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
        require_once __DIR__ . '/student_auth_check.php';

}
$pdo = db();

$studentId = (int) $_SESSION['student_id'];


/* =========================================================
   فحص ربط الجهاز
========================================================= */

$deviceToken = $_COOKIE['akadimi_device_token'] ?? '';

if ($deviceToken === '') {

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    header('Location: login.php?device_unlinked=1');
    exit;
}


$stmt = $pdo->prepare("
    SELECT device_token
    FROM students
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$studentId]);

$currentDeviceToken = $stmt->fetchColumn();


if (
    empty($currentDeviceToken) ||
    !hash_equals(
        (string) $currentDeviceToken,
        (string) $deviceToken
    )
) {

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    setcookie(
        'akadimi_device_token',
        '',
        time() - 3600,
        '/',
        '',
        false,
        true
    );

    header('Location: login.php?device_unlinked=1');
    exit;
}


/* =========================================================
   بيانات الطالب
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        phone,
        governorate_id,
        stage_id,
        curriculum_id,
        active
    FROM students
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$studentId]);

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

$pdo = db();

$studentId = (int) $_SESSION['student_id'];


/* =========================================================
   بيانات الطالب
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        phone,
        governorate_id,
        stage_id,
        curriculum_id,
        active
    FROM students
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$studentId]);

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


/*
|--------------------------------------------------------------------------
| معالجة الطلب
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $phone = trim($_POST['phone'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | التحقق من رقم الهاتف
    |--------------------------------------------------------------------------
    */
    if ($phone === '') {

        $error = 'يرجى إدخال رقم الهاتف';

    } elseif (!preg_match('/^(07)[0-9]{9}$/', $phone)) {

        $error = 'يرجى إدخال رقم هاتف عراقي صحيح';

    } else {

        /*
        |--------------------------------------------------------------------------
        | البحث عن الطالب
        |--------------------------------------------------------------------------
        */
        $stmt = $pdo->prepare("
            SELECT id, name, phone, active
            FROM students
            WHERE phone = ?
            LIMIT 1
        ");

        $stmt->execute([$phone]);

        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {

            $error = 'لا يوجد حساب مسجل بهذا الرقم';

        } elseif ((int)$student['active'] !== 1) {

            $error = 'هذا الحساب غير فعال حالياً';

        } else {

            /*
            |--------------------------------------------------------------------------
            | حذف طلبات الاستعادة القديمة
            |--------------------------------------------------------------------------
            */
            $delete = $pdo->prepare("
                DELETE FROM student_password_resets
                WHERE phone = ?
            ");

            $delete->execute([$phone]);

            /*
            |--------------------------------------------------------------------------
            | إنشاء رمز التحقق
            |--------------------------------------------------------------------------
            */
            $verification_code = (string) random_int(100000, 999999);

            /*
            |--------------------------------------------------------------------------
            | صلاحية الرمز: 5 دقائق
            |--------------------------------------------------------------------------
            */
            $expires_at = date('Y-m-d H:i:s', time() + (5 * 60));

            /*
            |--------------------------------------------------------------------------
            | حفظ طلب الاستعادة
            |--------------------------------------------------------------------------
            */
            $insert = $pdo->prepare("
                INSERT INTO student_password_resets
                (
                    student_id,
                    phone,
                    verification_code,
                    expires_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $insert->execute([
                $student['id'],
                $phone,
                $verification_code,
                $expires_at
            ]);

            /*
            |--------------------------------------------------------------------------
            | حفظ بيانات الاستعادة في الجلسة
            |--------------------------------------------------------------------------
            */
            $_SESSION['password_reset_id'] = $pdo->lastInsertId();
            $_SESSION['password_reset_phone'] = $phone;

            /*
            |--------------------------------------------------------------------------
            | الانتقال إلى صفحة التحقق
            |--------------------------------------------------------------------------
            */
            header('Location: reset_verify.php');
            exit;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>استعادة كلمة المرور - منصة أكاديمي</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Tahoma, Arial, sans-serif;
            background: #ede7d2;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 430px;
        }

        .card {
            background: #ffffff;
            border-radius: 28px;
            padding: 35px 25px;
            box-shadow: 0 15px 40px rgba(52, 92, 73, 0.12);
        }

        .logo {
            width: 90px;
            height: 90px;
            margin: 0 auto 20px;
            border-radius: 25px;
            background: #345c49;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            font-weight: bold;
        }

        h1 {
            text-align: center;
            margin: 0 0 10px;
            color: #345c49;
            font-size: 25px;
        }

        .description {
            text-align: center;
            color: #777;
            font-size: 14px;
            line-height: 1.8;
            margin-bottom: 25px;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 14px;
            margin-bottom: 18px;
            font-size: 14px;
            text-align: center;
        }

        .error {
            background: #fbe9e7;
            color: #b33a2b;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #345c49;
            font-weight: bold;
            font-size: 14px;
        }

        input {
            width: 100%;
            height: 54px;
            border: 1px solid #e0e0e0;
            border-radius: 16px;
            padding: 0 16px;
            font-size: 16px;
            outline: none;
            direction: ltr;
            text-align: right;
            background: #f8f8f8;
        }

        input:focus {
            border-color: #9bac78;
            background: #ffffff;
        }

        .btn {
            width: 100%;
            height: 54px;
            border: none;
            border-radius: 16px;
            background: #345c49;
            color: #ffffff;
            font-size: 17px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn:hover {
            opacity: 0.92;
        }

        .back {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #345c49;
            text-decoration: none;
            font-size: 14px;
        }

    </style>

</head>

<body>

<div class="container">

    <div class="card">

        <div class="logo">
            أ
        </div>

        <h1>استعادة كلمة المرور</h1>

        <div class="description">
            أدخل رقم الهاتف المرتبط بحسابك
            لإرسال رمز التحقق.
        </div>

        <?php if ($error): ?>

            <div class="alert error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="form-group">

                <label for="phone">
                    رقم الهاتف
                </label>

                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    placeholder="07XXXXXXXXX"
                    maxlength="11"
                    inputmode="numeric"
                    required
                >

            </div>

            <button type="submit" class="btn">
                متابعة
            </button>

        </form>

        <a href="login.php" class="back">
            العودة إلى تسجيل الدخول
        </a>

    </div>

</div>

</body>

</html>
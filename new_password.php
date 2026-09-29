<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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


$error = '';

/*
|--------------------------------------------------------------------------
| التحقق من وجود جلسة استعادة كلمة المرور
|--------------------------------------------------------------------------
*/
if (
    empty($_SESSION['password_reset_id']) ||
    empty($_SESSION['password_reset_verified']) ||
    $_SESSION['password_reset_verified'] !== true
) {
    header('Location: forgot_password.php');
    exit;
        require_once __DIR__ . '/student_auth_check.php';

}

$reset_id = (int) $_SESSION['password_reset_id'];

/*
|--------------------------------------------------------------------------
| جلب طلب الاستعادة
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT id, student_id, phone, verified, expires_at
    FROM student_password_resets
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$reset_id]);

$reset = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reset || (int)$reset['verified'] !== 1) {
    unset(
        $_SESSION['password_reset_id'],
        $_SESSION['password_reset_phone'],
        $_SESSION['password_reset_verified']
    );

    header('Location: forgot_password.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| التحقق من انتهاء الصلاحية
|--------------------------------------------------------------------------
*/
if (strtotime($reset['expires_at']) < time()) {

    unset(
        $_SESSION['password_reset_id'],
        $_SESSION['password_reset_phone'],
        $_SESSION['password_reset_verified']
    );

    $error = 'انتهت صلاحية طلب استعادة كلمة المرور. يرجى البدء من جديد.';
}

/*
|--------------------------------------------------------------------------
| تغيير كلمة المرور
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {

    $password = trim($_POST['password'] ?? '');
    $password_confirm = trim($_POST['password_confirm'] ?? '');

    if ($password === '' || $password_confirm === '') {

        $error = 'يرجى إدخال كلمة المرور الجديدة وتأكيدها.';

    } elseif (strlen($password) < 6) {

        $error = 'كلمة المرور يجب أن تكون 6 أحرف أو أرقام على الأقل.';

    } elseif ($password !== $password_confirm) {

        $error = 'كلمتا المرور غير متطابقتين.';

    } else {

        try {

            $pdo->beginTransaction();

            /*
            |----------------------------------------------------------------------
            | التأكد من وجود الطالب
            |----------------------------------------------------------------------
            */
            $studentStmt = $pdo->prepare("
                SELECT id
                FROM students
                WHERE id = ?
                LIMIT 1
            ");

            $studentStmt->execute([
                $reset['student_id']
            ]);

            $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {

                $pdo->rollBack();

                $error = 'لم يتم العثور على حساب الطالب.';

            } else {

                /*
                |------------------------------------------------------------------
                | تشفير كلمة المرور الجديدة
                |------------------------------------------------------------------
                */
                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                /*
                |------------------------------------------------------------------
                | تحديث كلمة المرور
                |------------------------------------------------------------------
                */
                $updateStmt = $pdo->prepare("
                    UPDATE students
                    SET password = ?
                    WHERE id = ?
                    LIMIT 1
                ");

                $updateStmt->execute([
                    $password_hash,
                    $reset['student_id']
                ]);

                /*
                |------------------------------------------------------------------
                | حذف طلب الاستعادة بعد نجاح العملية
                |------------------------------------------------------------------
                */
                $deleteStmt = $pdo->prepare("
                    DELETE FROM student_password_resets
                    WHERE id = ?
                    LIMIT 1
                ");

                $deleteStmt->execute([
                    $reset_id
                ]);

                $pdo->commit();

                /*
                |------------------------------------------------------------------
                | تنظيف جلسة الاستعادة
                |------------------------------------------------------------------
                */
                unset(
                    $_SESSION['password_reset_id'],
                    $_SESSION['password_reset_phone'],
                    $_SESSION['password_reset_verified']
                );

                /*
                |------------------------------------------------------------------
                | رسالة نجاح
                |------------------------------------------------------------------
                */
                $_SESSION['login_success'] =
                    'تم تغيير كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول.';

                header('Location: login.php');
                exit;
            }

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = 'حدث خطأ أثناء تغيير كلمة المرور. حاول مرة أخرى.';
        }
    }
}



?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>كلمة المرور الجديدة - منصة أكاديمي</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Tahoma, Arial, sans-serif;
            background: #e8e8e8;
            color: #345c49;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 430px;
        }

        .card {
            background: #ede7d2;
            border-radius: 24px;
            padding: 35px 25px;
            box-shadow: 0 12px 35px rgba(52, 92, 73, 0.12);
        }

        .logo {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: #345c49;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ede7d2;
            font-size: 35px;
            font-weight: bold;
        }

        h1 {
            text-align: center;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #607568;
            font-size: 14px;
            margin-bottom: 25px;
            line-height: 1.8;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 14px;
            border: 1px solid #c8cfbf;
            border-radius: 13px;
            background: #fff;
            color: #345c49;
            font-size: 16px;
            outline: none;
        }

        input:focus {
            border-color: #345c49;
        }

        .btn {
            width: 100%;
            border: none;
            padding: 15px;
            border-radius: 14px;
            background: #345c49;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 8px;
        }

        .btn:hover {
            opacity: .93;
        }

        .error {
            background: #f8dede;
            color: #9b3333;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 18px;
            text-align: center;
            font-size: 14px;
            line-height: 1.7;
        }

        .note {
            margin-top: 18px;
            text-align: center;
            font-size: 13px;
            color: #6b756e;
            line-height: 1.7;
        }

    </style>
</head>

<body>

<div class="container">

    <div class="card">

        <div class="logo">
            أ
        </div>

        <h1>كلمة المرور الجديدة</h1>

        <div class="subtitle">
            أدخل كلمة المرور الجديدة لحسابك
        </div>

        <?php if ($error): ?>

            <div class="error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <?php if (empty($error)): ?>

            <form method="POST">

                <div class="form-group">

                    <label for="password">
                        كلمة المرور الجديدة
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="أدخل كلمة المرور الجديدة"
                        minlength="6"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="password_confirm">
                        تأكيد كلمة المرور
                    </label>

                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        placeholder="أعد كتابة كلمة المرور"
                        minlength="6"
                        required
                    >

                </div>

                <button type="submit" class="btn">
                    حفظ كلمة المرور
                </button>

            </form>

            <div class="note">
                بعد حفظ كلمة المرور سيتم نقلك إلى صفحة تسجيل الدخول.
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
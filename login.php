<?php

session_start();

require_once __DIR__ . '/../config/config.php';

$pdo = db();

$error = '';
$registered = isset($_GET['registered']) && $_GET['registered'] === '1';

/*
|--------------------------------------------------------------------------
| اسم الكوكي الخاصة بالجهاز
|--------------------------------------------------------------------------
*/
$deviceCookieName = 'akadimi_device_token';


/*
|--------------------------------------------------------------------------
| إذا الطالب مسجل دخول
|--------------------------------------------------------------------------
*/
if (!empty($_SESSION['student_id'])) {
    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| تسجيل الدخول
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($phone === '' || $password === '') {

        $error = 'يرجى إدخال رقم الهاتف وكلمة المرور.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | جلب بيانات الطالب
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    name,
                    phone,
                    password,
                    active,
                    device_token,
                    device_bound_at
                FROM students
                WHERE phone = ?
                LIMIT 1
            ");

            $stmt->execute([$phone]);

            $student = $stmt->fetch(PDO::FETCH_ASSOC);


            /*
            |--------------------------------------------------------------------------
            | التحقق من الحساب
            |--------------------------------------------------------------------------
            */

            if (!$student) {

                $error = 'رقم الهاتف أو كلمة المرور غير صحيحة.';

            } elseif ((int)$student['active'] !== 1) {

                $error = 'حساب الطالب غير مفعل حالياً.';

            } elseif (!password_verify($password, $student['password'])) {

                $error = 'رقم الهاتف أو كلمة المرور غير صحيحة.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | الجهاز الحالي
                |--------------------------------------------------------------------------
                */

                $currentDeviceToken = $_COOKIE[$deviceCookieName] ?? '';

                /*
                |--------------------------------------------------------------------------
                | إذا الحساب غير مرتبط بأي جهاز
                |--------------------------------------------------------------------------
                */

                if (empty($student['device_token'])) {

                    /*
                    | إنشاء رمز عشوائي قوي للجهاز
                    */

                    $newDeviceToken = bin2hex(random_bytes(32));

                    /*
                    | حفظ الجهاز في قاعدة البيانات
                    */

                    $update = $pdo->prepare("
                        UPDATE students
                        SET
                            device_token = ?,
                            device_bound_at = NOW()
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $update->execute([
                        $newDeviceToken,
                        (int)$student['id']
                    ]);

                    /*
                    | حفظ رمز الجهاز في المتصفح
                    |
                    | 365 يوم
                    */

                    setcookie(
                        $deviceCookieName,
                        $newDeviceToken,
                        [
                            'expires'  => time() + (365 * 24 * 60 * 60),
                            'path'     => '/',
                            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]
                    );

                    /*
                    | تحديث القيمة محلياً
                    */

                    $currentDeviceToken = $newDeviceToken;


                /*
                |--------------------------------------------------------------------------
                | إذا الحساب مرتبط بجهاز مسبقاً
                |--------------------------------------------------------------------------
                */

                } else {

                    /*
                    | لا توجد كوكي للجهاز
                    |
                    | هذا يعني أن الطالب يحاول الدخول من متصفح/جهاز
                    | غير الجهاز المرتبط بالحساب.
                    */

                    if ($currentDeviceToken === '') {

                        $error = 'هذا الحساب مرتبط بجهاز آخر حالياً. لا يمكن تسجيل الدخول من جهاز جديد. لتغيير الجهاز، يرجى التواصل مع إدارة المنصة.';

                    /*
                    |--------------------------------------------------------------------------
                    | توجد كوكي لكن لا تطابق الجهاز المسجل
                    |--------------------------------------------------------------------------
                    */

                    } elseif (
                        !hash_equals(
                            (string)$student['device_token'],
                            (string)$currentDeviceToken
                        )
                    ) {

                        $error = 'هذا الحساب مرتبط بجهاز آخر حالياً. لا يمكن تسجيل الدخول من هذا الجهاز. لتغيير الجهاز، يرجى التواصل مع إدارة المنصة.';
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | إذا لم توجد مشكلة بالجهاز
                |--------------------------------------------------------------------------
                */

                if ($error === '') {

                    /*
                    |--------------------------------------------------------------------------
                    | تجديد جلسة الدخول
                    |--------------------------------------------------------------------------
                    */

                    session_regenerate_id(true);


                    /*
                    |--------------------------------------------------------------------------
                    | بيانات الطالب في Session
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['student_id'] = (int)$student['id'];

                    $_SESSION['student_name'] = $student['name'];

                    $_SESSION['student_phone'] = $student['phone'];


                    /*
                    |--------------------------------------------------------------------------
                    | الدخول إلى المنصة
                    |--------------------------------------------------------------------------
                    */

                    header('Location: index.php');

                    exit;
                }
            }

        } catch (PDOException $e) {

            $error = 'حدث خطأ أثناء تسجيل الدخول.';
        }
    }
}

?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">

<meta name="theme-color" content="#345c49">

<meta name="apple-mobile-web-app-capable" content="yes">

<meta name="apple-mobile-web-app-title" content="أكاديمي">

<link rel="manifest" href="/manifest.webmanifest">

<link rel="icon" href="/assets/pwa-icon.svg" type="image/svg+xml">

<link rel="apple-touch-icon" href="/assets/pwa-icon-192.png">

<script src="/assets/pwa.js" defer></script>

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    تسجيل الدخول - منصة أكاديمي
</title>

<style>

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    min-height: 100%;
}

body {

    min-height: 100vh;

    font-family:
        Tahoma,
        Arial,
        sans-serif;

    background: #e8e8e8;

    color: #345c49;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 25px 16px;
}


/* =========================================================
   الحاوية
========================================================= */

.login-container {

    width: 100%;

    max-width: 420px;
}


/* =========================================================
   الشعار
========================================================= */

.logo-box {

    width: 110px;

    height: 110px;

    margin: 0 auto 20px;

    border-radius: 32px;

    background: #345c49;

    color: #ede7d2;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 48px;

    font-weight: bold;

    box-shadow:
        0 15px 35px rgba(52, 92, 73, .20);
}


/* =========================================================
   العنوان
========================================================= */

.title {

    text-align: center;

    color: #345c49;

    font-size: 27px;

    font-weight: bold;

    margin-bottom: 8px;
}

.subtitle {

    text-align: center;

    color: #69756d;

    font-size: 14px;

    margin-bottom: 25px;
}


/* =========================================================
   البطاقة
========================================================= */

.card {

    background: #ffffff;

    border-radius: 26px;

    padding: 28px 24px;

    border: 1px solid rgba(52, 92, 73, .08);

    box-shadow:
        0 18px 45px rgba(52, 92, 73, .10);
}


/* =========================================================
   الخطأ
========================================================= */

.error {

    background: #fbe9e7;

    border: 1px solid #f1c8c3;

    color: #a53a30;

    padding: 12px 14px;

    border-radius: 13px;

    margin-bottom: 18px;

    font-size: 13px;

    text-align: center;

    line-height: 1.7;
}


/* =========================================================
   الحقول
========================================================= */

.field {

    margin-bottom: 18px;
}

label {

    display: block;

    margin-bottom: 8px;

    color: #345c49;

    font-size: 14px;

    font-weight: bold;
}

input {

    width: 100%;

    height: 54px;

    border: 1px solid #d9ded9;

    border-radius: 15px;

    padding: 0 16px;

    background: #fafafa;

    color: #345c49;

    font-family: inherit;

    font-size: 15px;

    outline: none;

    transition: .2s;
}

input:focus {

    background: #ffffff;

    border-color: #9bac78;

    box-shadow:
        0 0 0 3px rgba(155, 172, 120, .15);
}

.phone-input {

    direction: ltr;

    text-align: right;
}


/* =========================================================
   زر تسجيل الدخول
========================================================= */

.login-btn {

    width: 100%;

    height: 55px;

    border: none;

    border-radius: 15px;

    background: #345c49;

    color: #ffffff;

    font-family: inherit;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;

    margin-top: 3px;

    transition: .2s;
}

.login-btn:hover {

    background: #294a3b;
}

.login-btn:active {

    transform: scale(.99);
}


/* =========================================================
   الروابط
========================================================= */

.links {

    margin-top: 22px;

    text-align: center;
}

.success {

    background: #e8f4ec;

    border: 1px solid #c5e3ce;

    color: #286644;

    padding: 12px 14px;

    border-radius: 13px;

    margin-bottom: 18px;

    font-size: 13px;

    text-align: center;
}

.new-student {

    display: block;

    width: 100%;

    padding: 14px;

    border-radius: 14px;

    background: #ede7d2;

    color: #345c49;

    text-decoration: none;

    font-size: 14px;

    font-weight: bold;

    transition: .2s;
}

.new-student:hover {

    background: #e2dbc4;
}

.forgot {

    display: inline-block;

    margin-top: 17px;

    color: #7a877e;

    text-decoration: none;

    font-size: 13px;
}

.forgot:hover {

    color: #345c49;
}


/* =========================================================
   واتساب
========================================================= */

.whatsapp-note {

    margin-top: 20px;

    padding-top: 17px;

    border-top: 1px solid #eeeeee;

    text-align: center;

    color: #7a877e;

    font-size: 11px;

    line-height: 1.8;
}


/* =========================================================
   الهاتف
========================================================= */

@media (max-width: 480px) {

    body {

        padding: 20px 14px;
    }

    .logo-box {

        width: 92px;

        height: 92px;

        border-radius: 27px;

        font-size: 40px;
    }

    .title {

        font-size: 23px;
    }

    .card {

        padding: 24px 18px;

        border-radius: 22px;
    }
}

</style>

</head>

<body>


<div class="login-container">


    <!-- الشعار -->

    <div class="logo-box">
        أ
    </div>


    <!-- العنوان -->

    <div class="title">
        منصة أكاديمي التعليمية
    </div>


    <div class="subtitle">
        أهلاً بك، سجل دخولك للمتابعة
    </div>


    <!-- البطاقة -->

    <div class="card">


        <?php if ($error): ?>

            <div class="error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <?php if ($registered): ?>

            <div class="success">
                تم إنشاء حسابك. سجّل الدخول للمتابعة.
            </div>

        <?php endif; ?>


        <form
            method="POST"
            autocomplete="off"
        >


            <!-- رقم الهاتف -->

            <div class="field">

                <label for="phone">
                    رقم الهاتف
                </label>

                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    class="phone-input"
                    placeholder="07XXXXXXXXX"
                    maxlength="11"
                    inputmode="numeric"
                    autocomplete="tel"
                    required
                    value="<?= e($_POST['phone'] ?? '') ?>"
                >

            </div>


            <!-- كلمة المرور -->

            <div class="field">

                <label for="password">
                    كلمة المرور
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="أدخل كلمة المرور"
                    autocomplete="current-password"
                    required
                >

            </div>


            <!-- تسجيل الدخول -->

            <button
                type="submit"
                class="login-btn"
            >
                تسجيل الدخول
            </button>


        </form>


        <!-- الروابط -->

        <div class="links">


            <!-- طالب جديد -->

            <a
                href="register.php"
                class="new-student"
            >
                طالب جديد؟ الانضمام للمنصة
            </a>


            <!-- استعادة كلمة المرور -->

            <a
                href="forgot_password.php"
                class="forgot"
            >
                استعادة كلمة المرور
            </a>


        </div>


        <!-- واتساب -->

        <div class="whatsapp-note">

            عند استعادة كلمة المرور سيتم التحقق من رقم الهاتف،
            وإرسال رمز التحقق عبر واتساب المنصة بعد تفعيل الربط.

        </div>


    </div>


</div>

</body>

</html>
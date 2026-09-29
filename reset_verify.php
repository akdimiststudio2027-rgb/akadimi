<?php

session_start();

require_once __DIR__ . '/../config/config.php';


$pdo = db();

$error = '';

/*
|--------------------------------------------------------------------------
| التأكد من وجود عملية استعادة
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['password_reset_id']) ||
    empty($_SESSION['password_reset_phone'])
) {
    header('Location: forgot_password.php');
    exit;

}

$resetId = (int) $_SESSION['password_reset_id'];
$phone = $_SESSION['password_reset_phone'];


/*
|--------------------------------------------------------------------------
| جلب طلب الاستعادة
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        student_id,
        phone,
        verification_code,
        expires_at,
        attempts,
        verified
    FROM student_password_resets
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$resetId]);

$reset = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$reset) {

    unset(
        $_SESSION['password_reset_id'],
        $_SESSION['password_reset_phone']
    );

    header('Location: forgot_password.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| التحقق من الرمز
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $code = trim($_POST['code'] ?? '');

    if ($code === '') {

        $error = 'يرجى إدخال رمز التحقق.';

    } elseif (!preg_match('/^[0-9]{6}$/', $code)) {

        $error = 'رمز التحقق يجب أن يتكون من 6 أرقام.';

    } elseif ((int)$reset['verified'] === 1) {

        $error = 'تم استخدام رمز التحقق مسبقاً.';

    } elseif (strtotime($reset['expires_at']) < time()) {

        $error = 'انتهت صلاحية رمز التحقق.';

    } elseif ((int)$reset['attempts'] >= 5) {

        $error = 'تم تجاوز عدد محاولات التحقق المسموح بها.';

    } elseif ($code !== $reset['verification_code']) {

        $update = $pdo->prepare("
            UPDATE student_password_resets
            SET attempts = attempts + 1
            WHERE id = ?
        ");

        $update->execute([$resetId]);

        $error = 'رمز التحقق غير صحيح.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | الرمز صحيح
        |--------------------------------------------------------------------------
        */

        $update = $pdo->prepare("
            UPDATE student_password_resets
            SET verified = 1
            WHERE id = ?
        ");

        $update->execute([$resetId]);


        /*
        |--------------------------------------------------------------------------
        | حفظ حالة التحقق
        |--------------------------------------------------------------------------
        */

        $_SESSION['password_reset_verified'] = true;

        header('Location: new_password.php');
        exit;
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

<title>التحقق - منصة أكاديمي</title>

<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    min-height: 100vh;

    font-family:
        Tahoma,
        Arial,
        sans-serif;

    background: #ede7d2;

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

    background: #fff;

    border-radius: 28px;

    padding: 35px 25px;

    box-shadow:
        0 15px 40px rgba(52,92,73,.12);
}

.logo {

    width: 90px;

    height: 90px;

    margin: 0 auto 20px;

    border-radius: 25px;

    background: #345c49;

    color: #fff;

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

    font-size: 24px;
}

.description {

    text-align: center;

    color: #777;

    font-size: 14px;

    line-height: 1.8;

    margin-bottom: 20px;
}

.phone {

    direction: ltr;

    font-weight: bold;

    color: #345c49;
}

.alert {

    background: #fbe9e7;

    color: #b33a2b;

    border-radius: 13px;

    padding: 12px;

    margin-bottom: 18px;

    font-size: 13px;

    text-align: center;
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

    height: 56px;

    border: 1px solid #ddd;

    border-radius: 15px;

    outline: none;

    font-family: inherit;

    font-size: 23px;

    text-align: center;

    letter-spacing: 7px;

    direction: ltr;
}

input:focus {

    border-color: #345c49;

    box-shadow:
        0 0 0 3px rgba(52,92,73,.10);
}

.btn {

    width: 100%;

    height: 55px;

    border: none;

    border-radius: 16px;

    background: #345c49;

    color: #fff;

    font-family: inherit;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;
}

.btn:hover {

    opacity: .92;
}

.note {

    text-align: center;

    color: #777;

    font-size: 12px;

    line-height: 1.8;

    margin-top: 20px;
}

</style>

</head>

<body>

<div class="container">

    <div class="card">

        <div class="logo">
            أ
        </div>

        <h1>
            التحقق من الرقم
        </h1>

        <div class="description">

            أدخل رمز التحقق المكون من 6 أرقام
            الذي سيتم إرساله إلى:

            <br>

            <span class="phone">
                <?= e($phone) ?>
            </span>

        </div>


        <?php if ($error): ?>

            <div class="alert">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <form method="POST" autocomplete="off">

            <div class="form-group">

                <label>
                    رمز التحقق
                </label>

                <input
                    type="text"
                    name="code"
                    maxlength="6"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    placeholder="000000"
                    autocomplete="one-time-code"
                    required
                >

            </div>


            <button
                type="submit"
                class="btn"
            >
                تأكيد الرمز
            </button>

        </form>


        <div class="note">

            الرمز صالح لمدة 5 دقائق.

            <br>

            سيتم ربط إرسال الرمز عبر واتساب لاحقاً.

        </div>

    </div>

</div>

</body>

</html>
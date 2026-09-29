<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/config.php';

$pdo = db();

/*
|--------------------------------------------------------------------------
| إنشاء / تحديث حساب الأستاذ
|--------------------------------------------------------------------------
*/

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $password   = trim($_POST['password'] ?? '');

    if ($teacher_id <= 0) {
        $error = 'يرجى اختيار الأستاذ.';
    } elseif ($password === '') {
        $error = 'يرجى إدخال كلمة المرور.';
    } elseif (mb_strlen($password) < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف أو أكثر.';
    } else {

        /*
        |--------------------------------------------------------------
        | التأكد من وجود الأستاذ
        |--------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT id, name, phone
            FROM teachers
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$teacher_id]);

        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$teacher) {

            $error = 'الأستاذ غير موجود.';

        } else {

            /*
            |----------------------------------------------------------
            | تشفير كلمة المرور
            |----------------------------------------------------------
            */

            $password_hash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            /*
            |----------------------------------------------------------
            | تحديث الحساب
            |----------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE teachers
                SET
                    password = ?,
                    login_enabled = 1
                WHERE id = ?
            ");

            $stmt->execute([
                $password_hash,
                $teacher_id
            ]);

            $message = 'تم إنشاء / تحديث حساب الأستاذ بنجاح.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| تعطيل حساب
|--------------------------------------------------------------------------
*/

if (isset($_GET['disable'])) {

    $teacher_id = (int)$_GET['disable'];

    $stmt = $pdo->prepare("
        UPDATE teachers
        SET login_enabled = 0
        WHERE id = ?
    ");

    $stmt->execute([$teacher_id]);

    header('Location: teacher_accounts.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| تفعيل حساب
|--------------------------------------------------------------------------
*/

if (isset($_GET['enable'])) {

    $teacher_id = (int)$_GET['enable'];

    $stmt = $pdo->prepare("
        UPDATE teachers
        SET login_enabled = 1
        WHERE id = ?
    ");

    $stmt->execute([$teacher_id]);

    header('Location: teacher_accounts.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| جلب الأساتذة
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        name,
        phone,
        password,
        login_enabled
    FROM teachers
    ORDER BY id DESC
");

$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>حسابات الأساتذة - منصة أكاديمي</title>

<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Tahoma,
        Arial,
        sans-serif;

    background: #f5f7fb;

    color: #1f2937;
}

.container {

    width: 94%;

    max-width: 1200px;

    margin: 30px auto;
}


/* الهيدر */

.header {

    background: white;

    border-radius: 18px;

    padding: 22px;

    margin-bottom: 20px;

    box-shadow:
        0 5px 20px rgba(0,0,0,.05);
}

.header h1 {

    margin: 0 0 8px;

    font-size: 25px;
}

.header p {

    margin: 0;

    color: #7b8794;
}


/* البطاقات */

.card {

    background: white;

    border-radius: 18px;

    padding: 22px;

    margin-bottom: 20px;

    box-shadow:
        0 5px 20px rgba(0,0,0,.05);
}

.card h2 {

    margin-top: 0;

    margin-bottom: 18px;
}


/* التنبيهات */

.alert {

    padding: 13px 16px;

    border-radius: 10px;

    margin-bottom: 18px;

    font-size: 14px;
}

.alert-success {

    background: #ecfdf5;

    color: #047857;

    border: 1px solid #a7f3d0;
}

.alert-error {

    background: #fef2f2;

    color: #b91c1c;

    border: 1px solid #fecaca;
}


/* النموذج */

.form-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 16px;
}

.form-group {

    display: flex;

    flex-direction: column;
}

.form-group.full {

    grid-column: 1 / -1;
}

label {

    margin-bottom: 7px;

    font-weight: bold;
}

select,
input {

    width: 100%;

    padding: 12px;

    border: 1px solid #d9dee7;

    border-radius: 10px;

    font-size: 15px;

    outline: none;

    background: white;
}

select:focus,
input:focus {

    border-color: #0a9f91;
}


/* الأزرار */

.buttons {

    margin-top: 18px;

    display: flex;

    gap: 8px;

    flex-wrap: wrap;
}

.btn {

    display: inline-block;

    text-decoration: none;

    border: 0;

    padding: 11px 17px;

    border-radius: 9px;

    cursor: pointer;

    font-size: 14px;
}

.btn-save {

    background: #079f91;

    color: white;
}

.btn-enable {

    background: #059669;

    color: white;
}

.btn-disable {

    background: #dc2626;

    color: white;
}

.btn-teacher {

    background: #2563eb;

    color: white;
}


/* الجدول */

.table-wrapper {

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 800px;
}

th,
td {

    padding: 14px 10px;

    border-bottom: 1px solid #edf0f4;

    text-align: right;
}

th {

    background: #f8fafc;

    font-size: 14px;
}

td {

    font-size: 14px;
}


/* الحالة */

.status {

    display: inline-block;

    padding: 6px 11px;

    border-radius: 20px;

    font-size: 12px;
}

.status-active {

    background: #dcfce7;

    color: #166534;
}

.status-off {

    background: #fee2e2;

    color: #991b1b;
}

.has-account {

    color: #059669;

    font-weight: bold;
}

.no-account {

    color: #9ca3af;
}


/* الموبايل */

@media (max-width: 700px) {

    .container {

        width: 92%;
    }

    .form-grid {

        grid-template-columns: 1fr;
    }

    .form-group.full {

        grid-column: auto;
    }

    .header h1 {

        font-size: 21px;
    }
}

</style>

</head>

<body>

<div class="container">


    <!-- الهيدر -->

    <div class="header">

        <h1>
            👨‍🏫 حسابات الأساتذة
        </h1>

        <p>
            إنشاء وإدارة حسابات دخول الأساتذة إلى تطبيق منصة أكاديمي
        </p>

    </div>


    <?php if ($message): ?>

        <div class="alert alert-success">

            ✅ <?= e($message) ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert alert-error">

            ❌ <?= e($error) ?>

        </div>

    <?php endif; ?>


    <!-- إنشاء الحساب -->

    <div class="card">

        <h2>
            🔐 إنشاء حساب أستاذ
        </h2>

        <form method="POST">

            <div class="form-grid">


                <div class="form-group">

                    <label>
                        الأستاذ
                    </label>

                    <select
                        name="teacher_id"
                        required
                    >

                        <option value="">
                            اختر الأستاذ
                        </option>

                        <?php foreach ($teachers as $teacher): ?>

                            <option
                                value="<?= (int)$teacher['id'] ?>"
                            >

                                <?= e($teacher['name']) ?>

                                <?php if (!empty($teacher['phone'])): ?>

                                    -
                                    <?= e($teacher['phone']) ?>

                                <?php endif; ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        كلمة المرور
                    </label>

                    <input
                        type="password"
                        name="password"
                        placeholder="أدخل كلمة مرور الأستاذ"
                        minlength="6"
                        required
                    >

                </div>


            </div>


            <div class="buttons">

                <button
                    type="submit"
                    class="btn btn-save"
                >

                    🔐 إنشاء الحساب

                </button>

            </div>

        </form>

    </div>


    <!-- قائمة الحسابات -->

    <div class="card">

        <h2>
            📋 حسابات الأساتذة
        </h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            الأستاذ
                        </th>

                        <th>
                            رقم الهاتف
                        </th>

                        <th>
                            الحساب
                        </th>

                        <th>
                            الحالة
                        </th>

                        <th>
                            الإجراءات
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (!$teachers): ?>

                    <tr>

                        <td
                            colspan="6"
                            style="text-align:center;padding:30px"
                        >

                            لا يوجد أساتذة حالياً

                        </td>

                    </tr>

                <?php else: ?>


                    <?php foreach ($teachers as $teacher): ?>

                        <tr>


                            <td>

                                <?= (int)$teacher['id'] ?>

                            </td>


                            <td>

                                <strong>

                                    <?= e($teacher['name']) ?>

                                </strong>

                            </td>


                            <td>

                                <?= e($teacher['phone'] ?: '-') ?>

                            </td>


                            <td>

                                <?php if (!empty($teacher['password'])): ?>

                                    <span class="has-account">

                                        ✓ لديه حساب

                                    </span>

                                <?php else: ?>

                                    <span class="no-account">

                                        لا يوجد حساب

                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if ($teacher['login_enabled']): ?>

                                    <span class="status status-active">

                                        فعّال

                                    </span>

                                <?php else: ?>

                                    <span class="status status-off">

                                        متوقف

                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="buttons">


                                    <?php if ($teacher['login_enabled']): ?>

                                        <a
                                            href="teacher_accounts.php?disable=<?= (int)$teacher['id'] ?>"
                                            class="btn btn-disable"
                                            onclick="return confirm('هل تريد تعطيل حساب هذا الأستاذ؟');"
                                        >

                                            تعطيل

                                        </a>

                                    <?php else: ?>

                                        <a
                                            href="teacher_accounts.php?enable=<?= (int)$teacher['id'] ?>"
                                            class="btn btn-enable"
                                        >

                                            تفعيل

                                        </a>

                                    <?php endif; ?>


                                    <a
                                        href="teachers.php?edit=<?= (int)$teacher['id'] ?>"
                                        class="btn btn-teacher"
                                    >

                                        بيانات الأستاذ

                                    </a>


                                </div>

                            </td>


                        </tr>

                    <?php endforeach; ?>


                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


</div>

</body>

</html>
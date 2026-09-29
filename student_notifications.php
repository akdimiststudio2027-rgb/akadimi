<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/config.php';

$pdo = db();


/*
|--------------------------------------------------------------------------
| حذف إشعار
|--------------------------------------------------------------------------
*/

if (isset($_GET['delete'])) {

    $id = (int) $_GET['delete'];

    if ($id > 0) {

        $stmt = $pdo->prepare("
            DELETE FROM student_notifications
            WHERE id = ?
        ");

        $stmt->execute([$id]);
    }

    header("Location: student_notifications.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| إرسال إشعار
|--------------------------------------------------------------------------
*/

$message_success = '';
$message_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $student_id = (int) ($_POST['student_id'] ?? 0);

    $title = trim($_POST['title'] ?? '');

    $message = trim($_POST['message'] ?? '');

    $type = trim($_POST['type'] ?? 'general');


    /*
    |--------------------------------------------------------------------------
    | التحقق
    |--------------------------------------------------------------------------
    */

    if ($student_id <= 0) {

        $message_error = 'يرجى اختيار الطالب';

    } elseif ($title === '') {

        $message_error = 'يرجى إدخال عنوان الإشعار';

    } elseif ($message === '') {

        $message_error = 'يرجى كتابة نص الإشعار';

    } else {

        /*
        |--------------------------------------------------------------------------
        | التأكد من وجود الطالب
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT id
            FROM students
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$student_id]);

        $student_exists = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$student_exists) {

            $message_error = 'الطالب غير موجود';

        } else {

            /*
            |--------------------------------------------------------------------------
            | إضافة الإشعار
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO student_notifications
                (
                    student_id,
                    title,
                    message,
                    type,
                    is_read
                )
                VALUES (?, ?, ?, ?, 0)
            ");

            $stmt->execute([
                $student_id,
                $title,
                $message,
                $type
            ]);


            $message_success = 'تم إرسال الإشعار إلى الطالب بنجاح';

        }
    }
}


/*
|--------------------------------------------------------------------------
| جلب الطلاب
|--------------------------------------------------------------------------
*/

$students = $pdo->query("
    SELECT
        id,
        name,
        phone
    FROM students
    WHERE active = 1
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| جلب الإشعارات
|--------------------------------------------------------------------------
*/

$notifications = $pdo->query("
    SELECT
        n.*,
        s.name AS student_name,
        s.phone AS student_phone
    FROM student_notifications n

    LEFT JOIN students s
        ON s.id = n.student_id

    ORDER BY n.id DESC

    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    إدارة إشعارات الطلاب - منصة أكاديمي
</title>


<style>

/* =====================================================
   الأساسيات
===================================================== */

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

    color: #222;
}


/* =====================================================
   الحاوية
===================================================== */

.container {

    width: 95%;

    max-width: 1250px;

    margin: 30px auto;
}


/* =====================================================
   الهيدر
===================================================== */

.header {

    background: #ffffff;

    padding: 20px;

    border-radius: 14px;

    margin-bottom: 20px;

    box-shadow:
        0 4px 15px rgba(0,0,0,.06);
}

.header h1 {

    margin: 0 0 8px;

    font-size: 25px;
}

.header p {

    margin: 0;

    color: #777;
}


/* =====================================================
   البطاقات
===================================================== */

.card {

    background: #fff;

    padding: 22px;

    border-radius: 14px;

    margin-bottom: 20px;

    box-shadow:
        0 4px 15px rgba(0,0,0,.06);
}

.card h2 {

    margin-top: 0;
}


/* =====================================================
   الرسائل
===================================================== */

.alert {

    padding: 13px 16px;

    border-radius: 10px;

    margin-bottom: 18px;

    font-size: 14px;
}

.alert-success {

    background: #dcfce7;

    color: #166534;

    border: 1px solid #bbf7d0;
}

.alert-error {

    background: #fee2e2;

    color: #991b1b;

    border: 1px solid #fecaca;
}


/* =====================================================
   النموذج
===================================================== */

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 15px;
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

input,
select,
textarea {

    width: 100%;

    padding: 12px;

    border:
        1px solid #ddd;

    border-radius: 9px;

    font-size: 15px;

    outline: none;

    font-family:
        Tahoma,
        Arial,
        sans-serif;
}

textarea {

    min-height: 120px;

    resize: vertical;
}

input:focus,
select:focus,
textarea:focus {

    border-color: #08a394;
}


/* =====================================================
   الأزرار
===================================================== */

.buttons {

    margin-top: 18px;

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}

button,
.btn {

    border: 0;

    padding: 11px 18px;

    border-radius: 8px;

    cursor: pointer;

    text-decoration: none;

    display: inline-block;

    font-size: 14px;
}

.btn-save {

    background: #08a394;

    color: white;
}

.btn-delete {

    background: #dc2626;

    color: white;
}

.btn-save:hover {

    background: #078f84;
}

.btn-delete:hover {

    background: #b91c1c;
}


/* =====================================================
   الجدول
===================================================== */

.table-wrapper {

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 850px;
}

th,
td {

    padding: 13px 10px;

    border-bottom:
        1px solid #eee;

    text-align: right;

    vertical-align: middle;
}

th {

    background: #f8fafc;
}


/* =====================================================
   نوع الإشعار
===================================================== */

.type-badge {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;

    background: #eef2ff;

    color: #4338ca;
}


/* =====================================================
   حالة القراءة
===================================================== */

.read-badge {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;

    background: #dcfce7;

    color: #166534;
}

.unread-badge {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;

    background: #fef3c7;

    color: #92400e;
}


/* =====================================================
   النصوص
===================================================== */

.notification-title {

    font-weight: bold;

    margin-bottom: 5px;
}

.notification-message {

    color: #666;

    line-height: 1.7;

    max-width: 400px;
}

.student-phone {

    color: #888;

    font-size: 12px;

    margin-top: 4px;
}


/* =====================================================
   فارغ
===================================================== */

.empty {

    text-align: center;

    color: #777;

    padding: 30px;
}


/* =====================================================
   الموبايل
===================================================== */

@media (max-width: 700px) {

    .form-grid {

        grid-template-columns: 1fr;
    }

    .form-group.full {

        grid-column: auto;
    }

    .container {

        width: 94%;
    }

    .header h1 {

        font-size: 21px;
    }

}

</style>

</head>


<body>


<div class="container">


    <!-- =================================================
         الهيدر
    ================================================== -->

    <div class="header">

        <h1>
            🔔 إدارة إشعارات الطلاب
        </h1>

        <p>
            إرسال ومتابعة الإشعارات الخاصة بالطلاب
        </p>

    </div>


    <!-- =================================================
         رسائل النظام
    ================================================== -->

    <?php if ($message_success !== ''): ?>

        <div class="alert alert-success">

            ✅ <?= e($message_success) ?>

        </div>

    <?php endif; ?>


    <?php if ($message_error !== ''): ?>

        <div class="alert alert-error">

            ⚠️ <?= e($message_error) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         إرسال إشعار
    ================================================== -->

    <div class="card">

        <h2>
            📢 إرسال إشعار جديد
        </h2>


        <form method="POST">


            <div class="form-grid">


                <!-- الطالب -->

                <div class="form-group">

                    <label>
                        الطالب
                    </label>

                    <select
                        name="student_id"
                        required
                    >

                        <option value="">
                            اختر الطالب
                        </option>


                        <?php foreach ($students as $student): ?>

                            <option
                                value="<?= (int)$student['id'] ?>"
                            >

                                <?= e($student['name']) ?>

                                <?php if (!empty($student['phone'])): ?>

                                    -
                                    <?= e($student['phone']) ?>

                                <?php endif; ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- نوع الإشعار -->

                <div class="form-group">

                    <label>
                        نوع الإشعار
                    </label>

                    <select name="type">

                        <option value="general">
                            📢 عام
                        </option>

                        <option value="lesson">
                            📚 محاضرة
                        </option>

                        <option value="payment">
                            💳 دفع واشتراك
                        </option>

                        <option value="exam">
                            📝 اختبار
                        </option>

                        <option value="important">
                            ⚠️ مهم
                        </option>

                    </select>

                </div>


                <!-- العنوان -->

                <div class="form-group full">

                    <label>
                        عنوان الإشعار
                    </label>

                    <input
                        type="text"
                        name="title"
                        placeholder="مثلاً: تمت إضافة محاضرة جديدة"
                        maxlength="200"
                        required
                    >

                </div>


                <!-- الرسالة -->

                <div class="form-group full">

                    <label>
                        نص الإشعار
                    </label>

                    <textarea
                        name="message"
                        placeholder="اكتب نص الإشعار الذي سيظهر للطالب..."
                        required
                    ></textarea>

                </div>


            </div>


            <div class="buttons">

                <button
                    type="submit"
                    class="btn btn-save"
                >

                    🔔 إرسال الإشعار

                </button>

            </div>


        </form>

    </div>


    <!-- =================================================
         قائمة الإشعارات
    ================================================== -->

    <div class="card">

        <h2>
            📋 الإشعارات المرسلة
        </h2>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            الطالب
                        </th>

                        <th>
                            الإشعار
                        </th>

                        <th>
                            النوع
                        </th>

                        <th>
                            الحالة
                        </th>

                        <th>
                            التاريخ
                        </th>

                        <th>
                            الإجراء
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (!$notifications): ?>

                    <tr>

                        <td
                            colspan="7"
                            class="empty"
                        >

                            لا توجد إشعارات مرسلة حالياً

                        </td>

                    </tr>

                <?php else: ?>


                    <?php foreach ($notifications as $notification): ?>

                        <tr>


                            <td>

                                <?= (int)$notification['id'] ?>

                            </td>


                            <td>

                                <strong>

                                    <?= e(
                                        $notification['student_name']
                                        ?? 'طالب غير موجود'
                                    ) ?>

                                </strong>


                                <?php if (!empty($notification['student_phone'])): ?>

                                    <div class="student-phone">

                                        <?= e(
                                            $notification['student_phone']
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="notification-title">

                                    <?= e(
                                        $notification['title']
                                    ) ?>

                                </div>


                                <div class="notification-message">

                                    <?= nl2br(
                                        e($notification['message'])
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <span class="type-badge">

                                    <?= e(
                                        $notification['type']
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?php if ((int)$notification['is_read'] === 1): ?>

                                    <span class="read-badge">
                                        مقروء
                                    </span>

                                <?php else: ?>

                                    <span class="unread-badge">
                                        غير مقروء
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= e(
                                    $notification['created_at']
                                ) ?>

                            </td>


                            <td>

                                <a
                                    href="student_notifications.php?delete=<?= (int)$notification['id'] ?>"
                                    class="btn btn-delete"
                                    onclick="return confirm('هل أنت متأكد من حذف هذا الإشعار؟');"
                                >

                                    🗑️ حذف

                                </a>

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
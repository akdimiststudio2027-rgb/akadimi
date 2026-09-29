<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/config.php';

$pdo = db();

/*
|--------------------------------------------------------------------------
| حذف اشتراك
|--------------------------------------------------------------------------
*/

if (isset($_GET['delete'])) {

    $id = (int)$_GET['delete'];

    if ($id > 0) {

        $stmt = $pdo->prepare("
            DELETE FROM subscriptions
            WHERE id = ?
        ");

        $stmt->execute([$id]);
    }

    header("Location: subscriptions.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| تغيير حالة الاشتراك
|--------------------------------------------------------------------------
*/

if (isset($_GET['status'], $_GET['id'])) {

    $id = (int)$_GET['id'];

    $allowed = [
        'active',
        'expired',
        'cancelled'
    ];

    $status = $_GET['status'];

    if ($id > 0 && in_array($status, $allowed, true)) {

        $stmt = $pdo->prepare("
            UPDATE subscriptions
            SET status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $status,
            $id
        ]);
    }

    header("Location: subscriptions.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| بيانات التعديل
|--------------------------------------------------------------------------
*/

$editSubscription = null;

if (isset($_GET['edit'])) {

    $id = (int)$_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT *
        FROM subscriptions
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $editSubscription = $stmt->fetch(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| إضافة / تعديل اشتراك
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = (int)($_POST['id'] ?? 0);

    $student_id = (int)($_POST['student_id'] ?? 0);

    $course_id = (int)($_POST['course_id'] ?? 0);

    $starts_at = trim($_POST['starts_at'] ?? '');

    $expires_at = trim($_POST['expires_at'] ?? '');

    $status = $_POST['status'] ?? 'active';


    /*
    | التحقق من البيانات
    */

    if ($student_id <= 0) {

        die('يرجى اختيار الطالب.');

    }

    if ($course_id <= 0) {

        die('يرجى اختيار الدورة.');

    }

    if ($starts_at === '' || $expires_at === '') {

        die('يرجى تحديد تاريخ بداية ونهاية الاشتراك.');

    }


    /*
    | الحالات المسموحة
    */

    $allowedStatus = [
        'active',
        'expired',
        'cancelled'
    ];

    if (!in_array($status, $allowedStatus, true)) {

        $status = 'active';
    }


    /*
    | تعديل الاشتراك
    */

    if ($id > 0) {

        $stmt = $pdo->prepare("
            UPDATE subscriptions

            SET
                student_id = ?,
                course_id = ?,
                starts_at = ?,
                expires_at = ?,
                status = ?

            WHERE id = ?
        ");

        $stmt->execute([
            $student_id,
            $course_id,
            $starts_at,
            $expires_at,
            $status,
            $id
        ]);

    }

    /*
    | إضافة اشتراك جديد
    */

    else {

        $stmt = $pdo->prepare("
            INSERT INTO subscriptions
            (
                student_id,
                course_id,
                starts_at,
                expires_at,
                status
            )

            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $student_id,
            $course_id,
            $starts_at,
            $expires_at,
            $status
        ]);
    }


    header("Location: subscriptions.php");

    exit;
}


/*
|--------------------------------------------------------------------------
| جلب الطلاب
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        name,
        phone
    FROM students
    ORDER BY name ASC
");

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| جلب الدورات
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        title,
        price,
        duration_days
    FROM courses
    ORDER BY id DESC
");

$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| جلب الاشتراكات
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        sub.id,
        sub.student_id,
        sub.course_id,
        sub.starts_at,
        sub.expires_at,
        sub.status,

        st.name AS student_name,
        st.phone AS student_phone,

        c.title AS course_title,
        c.price AS course_price

    FROM subscriptions sub

    LEFT JOIN students st
        ON st.id = sub.student_id

    LEFT JOIN courses c
        ON c.id = sub.course_id

    ORDER BY sub.id DESC
");

$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>الاشتراكات - منصة أكاديمي</title>


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

    background: #f4f6f9;

    color: #1f2937;
}


.container {

    width: 95%;

    max-width: 1400px;

    margin: 30px auto;
}


.header {

    background: #111827;

    color: white;

    padding: 22px;

    border-radius: 15px;

    margin-bottom: 20px;
}


.header h1 {

    margin: 0 0 8px;

    font-size: 25px;
}


.header p {

    margin: 0;

    color: #d1d5db;
}


.card {

    background: white;

    padding: 22px;

    border-radius: 15px;

    margin-bottom: 20px;

    box-shadow:
        0 4px 18px rgba(0,0,0,.06);
}


.card h2 {

    margin-top: 0;
}


.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 15px;
}


.field {

    display: flex;

    flex-direction: column;

    gap: 7px;
}


.field label {

    font-weight: bold;
}


.field input,
.field select {

    width: 100%;

    padding: 12px;

    border: 1px solid #d1d5db;

    border-radius: 8px;

    background: white;

    font-size: 14px;
}


.buttons {

    display: flex;

    gap: 8px;

    flex-wrap: wrap;

    margin-top: 18px;
}


.btn {

    display: inline-block;

    padding: 10px 15px;

    border-radius: 8px;

    border: 0;

    text-decoration: none;

    cursor: pointer;

    font-size: 13px;
}


.btn-primary {

    background: #111827;

    color: white;
}


.btn-secondary {

    background: #e5e7eb;

    color: #111827;
}


.btn-edit {

    background: #2563eb;

    color: white;
}


.btn-delete {

    background: #dc2626;

    color: white;
}


.btn-active {

    background: #059669;

    color: white;
}


.btn-cancel {

    background: #f59e0b;

    color: white;
}


.table-wrap {

    overflow-x: auto;
}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 900px;
}


th {

    background: #f3f4f6;

    padding: 13px;

    text-align: right;
}


td {

    padding: 13px;

    border-bottom:
        1px solid #e5e7eb;
}


.status {

    display: inline-block;

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;
}


.status-active {

    background: #dcfce7;

    color: #166534;
}


.status-expired {

    background: #fee2e2;

    color: #991b1b;
}


.status-cancelled {

    background: #fef3c7;

    color: #92400e;
}


.actions {

    display: flex;

    gap: 5px;

    flex-wrap: wrap;
}


.empty {

    text-align: center;

    padding: 35px;

    color: #777;
}


@media(max-width:700px) {

    .form-grid {

        grid-template-columns: 1fr;
    }

    .container {

        width: 94%;

        margin: 15px auto;
    }

}

</style>

</head>


<body>


<div class="container">


    <div class="header">

        <h1>
            💳 إدارة الاشتراكات
        </h1>

        <p>
            إدارة اشتراكات الطلاب في الدورات التعليمية
        </p>

    </div>


    <!-- إضافة الاشتراك -->

    <div class="card">

        <h2>

            <?= $editSubscription
                ? '✏️ تعديل الاشتراك'
                : '➕ إضافة اشتراك جديد'
            ?>

        </h2>


        <form method="POST">


            <input
                type="hidden"
                name="id"
                value="<?= $editSubscription
                    ? (int)$editSubscription['id']
                    : 0
                ?>"
            >


            <div class="form-grid">


                <!-- الطالب -->

                <div class="field">

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

                                <?= (
                                    isset(
                                        $editSubscription['student_id']
                                    )
                                    &&
                                    $editSubscription['student_id']
                                    == $student['id']
                                )
                                ? 'selected'
                                : ''
                                ?>
                            >

                                <?= e($student['name']) ?>

                                -
                                <?= e($student['phone']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- الدورة -->

                <div class="field">

                    <label>
                        الدورة
                    </label>

                    <select
                        name="course_id"
                        required
                    >

                        <option value="">
                            اختر الدورة
                        </option>


                        <?php foreach ($courses as $course): ?>

                            <option
                                value="<?= (int)$course['id'] ?>"

                                <?= (
                                    isset(
                                        $editSubscription['course_id']
                                    )
                                    &&
                                    $editSubscription['course_id']
                                    == $course['id']
                                )
                                ? 'selected'
                                : ''
                                ?>
                            >

                                <?= e($course['title']) ?>

                                -

                                <?= e($course['price']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- تاريخ البداية -->

                <div class="field">

                    <label>
                        بداية الاشتراك
                    </label>

                    <input
                        type="datetime-local"
                        name="starts_at"
                        required

                        value="<?= $editSubscription
                            ? date(
                                'Y-m-d\TH:i',
                                strtotime(
                                    $editSubscription['starts_at']
                                )
                            )
                            : date(
                                'Y-m-d\TH:i'
                            )
                        ?>"
                    >

                </div>


                <!-- تاريخ النهاية -->

                <div class="field">

                    <label>
                        نهاية الاشتراك
                    </label>

                    <input
                        type="datetime-local"
                        name="expires_at"
                        required

                        value="<?= $editSubscription
                            ? date(
                                'Y-m-d\TH:i',
                                strtotime(
                                    $editSubscription['expires_at']
                                )
                            )
                            : ''
                        ?>"
                    >

                </div>


                <!-- الحالة -->

                <div class="field">

                    <label>
                        حالة الاشتراك
                    </label>

                    <select name="status">

                        <?php

                        $currentStatus =
                            $editSubscription['status']
                            ?? 'active';

                        ?>

                        <option
                            value="active"
                            <?= $currentStatus === 'active'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            فعال
                        </option>


                        <option
                            value="expired"
                            <?= $currentStatus === 'expired'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            منتهي
                        </option>


                        <option
                            value="cancelled"
                            <?= $currentStatus === 'cancelled'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            ملغي
                        </option>

                    </select>

                </div>


            </div>


            <div class="buttons">

                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <?= $editSubscription
                        ? '💾 حفظ التعديل'
                        : '➕ إنشاء الاشتراك'
                    ?>

                </button>


                <?php if ($editSubscription): ?>

                    <a
                        href="subscriptions.php"
                        class="btn btn-secondary"
                    >
                        إلغاء
                    </a>

                <?php endif; ?>

            </div>


        </form>

    </div>



    <!-- قائمة الاشتراكات -->

    <div class="card">

        <h2>
            📋 الاشتراكات الحالية
        </h2>


        <div class="table-wrap">

            <table>

                <thead>

                <tr>

                    <th>#</th>

                    <th>الطالب</th>

                    <th>الهاتف</th>

                    <th>الدورة</th>

                    <th>البداية</th>

                    <th>النهاية</th>

                    <th>الحالة</th>

                    <th>الإجراءات</th>

                </tr>

                </thead>


                <tbody>


                <?php if (!$subscriptions): ?>

                    <tr>

                        <td
                            colspan="8"
                            class="empty"
                        >

                            لا توجد اشتراكات حالياً.

                        </td>

                    </tr>

                <?php else: ?>


                    <?php foreach (
                        $subscriptions
                        as $subscription
                    ): ?>


                        <tr>

                            <td>
                                <?= (int)$subscription['id'] ?>
                            </td>


                            <td>

                                <strong>

                                    <?= e(
                                        $subscription['student_name']
                                        ?? '-'
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <?= e(
                                    $subscription['student_phone']
                                    ?? '-'
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $subscription['course_title']
                                    ?? '-'
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $subscription['starts_at']
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $subscription['expires_at']
                                ) ?>

                            </td>


                            <td>


                                <?php if (
                                    $subscription['status']
                                    === 'active'
                                ): ?>

                                    <span
                                        class="status status-active"
                                    >
                                        فعال
                                    </span>


                                <?php elseif (
                                    $subscription['status']
                                    === 'expired'
                                ): ?>

                                    <span
                                        class="status status-expired"
                                    >
                                        منتهي
                                    </span>


                                <?php else: ?>

                                    <span
                                        class="status status-cancelled"
                                    >
                                        ملغي
                                    </span>

                                <?php endif; ?>


                            </td>


                            <td>

                                <div class="actions">


                                    <a
                                        href="subscriptions.php?edit=<?= (int)$subscription['id'] ?>"
                                        class="btn btn-edit"
                                    >
                                        ✏️ تعديل
                                    </a>


                                    <a
                                        href="subscriptions.php?id=<?= (int)$subscription['id'] ?>&status=active"
                                        class="btn btn-active"
                                        onclick="return confirm('تفعيل هذا الاشتراك؟')"
                                    >
                                        تفعيل
                                    </a>


                                    <a
                                        href="subscriptions.php?id=<?= (int)$subscription['id'] ?>&status=cancelled"
                                        class="btn btn-cancel"
                                        onclick="return confirm('إلغاء هذا الاشتراك؟')"
                                    >
                                        إلغاء
                                    </a>


                                    <a
                                        href="subscriptions.php?delete=<?= (int)$subscription['id'] ?>"
                                        class="btn btn-delete"
                                        onclick="return confirm('هل أنت متأكد من حذف الاشتراك؟')"
                                    >
                                        🗑️ حذف
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
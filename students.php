<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

session_start();

require_once __DIR__ . '/../config/config.php';

$pdo = db();

/* =========================================================
   حماية لوحة الأدمن
========================================================= */

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

/* =========================================================
   دالة الحماية
========================================================= */

if (!function_exists('e')) {

    function e($value)
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/* =========================================================
   حذف طالب
========================================================= */

if (isset($_GET['delete'])) {

    $id = (int)$_GET['delete'];

    if ($id > 0) {

        $stmt = $pdo->prepare("
            DELETE FROM students
            WHERE id = ?
        ");

        $stmt->execute([$id]);
    }

    header("Location: students.php?success=deleted");
    exit;
}

/* =========================================================
   تفعيل / تعطيل الطالب
========================================================= */

if (isset($_GET['toggle'])) {

    $id = (int)$_GET['toggle'];

    if ($id > 0) {

        $stmt = $pdo->prepare("
            UPDATE students
            SET active =
                CASE
                    WHEN active = 1 THEN 0
                    ELSE 1
                END
            WHERE id = ?
        ");

        $stmt->execute([$id]);
    }

    header("Location: students.php?success=toggled");
    exit;
}

/* =========================================================
   الطالب المراد تعديله
========================================================= */

$editStudent = null;

if (isset($_GET['edit'])) {

    $editId = (int)$_GET['edit'];

    if ($editId > 0) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM students
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$editId]);

        $editStudent = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

/* =========================================================
   إضافة / تعديل طالب
========================================================= */

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = (int)($_POST['id'] ?? 0);

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    $governorate_id = !empty($_POST['governorate_id'])
        ? (int)$_POST['governorate_id']
        : null;

    $stage_id = !empty($_POST['stage_id'])
        ? (int)$_POST['stage_id']
        : null;

    $curriculum_id = !empty($_POST['curriculum_id'])
        ? (int)$_POST['curriculum_id']
        : null;

    $active = isset($_POST['active']) ? 1 : 0;


    if ($name === '' || $phone === '') {

        $error =
            "يرجى إدخال اسم الطالب ورقم الهاتف.";

    } else {

        try {

            /* =================================================
               تعديل
            ================================================= */

            if ($id > 0) {

                /* تغيير كلمة المرور */

                if ($password !== '') {

                    $hashedPassword =
                        password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                    $stmt = $pdo->prepare("
                        UPDATE students
                        SET
                            name = ?,
                            phone = ?,
                            password = ?,
                            governorate_id = ?,
                            stage_id = ?,
                            curriculum_id = ?,
                            active = ?
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $name,
                        $phone,
                        $hashedPassword,
                        $governorate_id,
                        $stage_id,
                        $curriculum_id,
                        $active,
                        $id
                    ]);

                } else {

                    $stmt = $pdo->prepare("
                        UPDATE students
                        SET
                            name = ?,
                            phone = ?,
                            governorate_id = ?,
                            stage_id = ?,
                            curriculum_id = ?,
                            active = ?
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $name,
                        $phone,
                        $governorate_id,
                        $stage_id,
                        $curriculum_id,
                        $active,
                        $id
                    ]);
                }

                header(
                    "Location: students.php?success=updated"
                );

                exit;
            }


            /* =================================================
               إضافة طالب
            ================================================= */

            if ($password === '') {

                $error =
                    "يرجى إدخال كلمة المرور.";

            } else {

                $hashedPassword =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                $stmt = $pdo->prepare("
                    INSERT INTO students
                    (
                        name,
                        phone,
                        password,
                        governorate_id,
                        stage_id,
                        curriculum_id,
                        active
                    )
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $name,
                    $phone,
                    $hashedPassword,
                    $governorate_id,
                    $stage_id,
                    $curriculum_id,
                    $active
                ]);

                header(
                    "Location: students.php?success=added"
                );

                exit;
            }

        } catch (PDOException $e) {

            if ($e->getCode() == 23000) {

                $error =
                    "رقم الهاتف مستخدم مسبقاً.";

            } else {

                $error =
                    "حدث خطأ أثناء حفظ بيانات الطالب.";
            }
        }
    }
}

/* =========================================================
   المحافظات
========================================================= */

$governorates = [];

try {

    $stmt = $pdo->query("
        SELECT
            id,
            name
        FROM governorates
        ORDER BY name ASC
    ");

    $governorates =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $governorates = [];
}

/* =========================================================
   المراحل
========================================================= */

$stages = [];

try {

    $stmt = $pdo->query("
        SELECT
            id,
            name
        FROM stages
        WHERE active = 1
        ORDER BY name ASC
    ");

    $stages =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $stages = [];
}

/* =========================================================
   المناهج
========================================================= */

$curricula = [];

try {

    $stmt = $pdo->query("
        SELECT
            id,
            name
        FROM curricula
        ORDER BY name ASC
    ");

    $curricula =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $curricula = [];
}

/* =========================================================
   الطلاب
========================================================= */

$stmt = $pdo->query("
    SELECT
        s.*,

        g.name AS governorate_name,

        st.name AS stage_name,

        c.name AS curriculum_name

    FROM students s

    LEFT JOIN governorates g
        ON g.id = s.governorate_id

    LEFT JOIN stages st
        ON st.id = s.stage_id

    LEFT JOIN curricula c
        ON c.id = s.curriculum_id

    ORDER BY
        s.id DESC
");

$students =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    الطلاب - منصة أكاديمي
</title>

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

    max-width: 1450px;

    margin: 30px auto;
}

.header {

    background: #345c49;

    color: white;

    padding: 22px 25px;

    border-radius: 14px;

    margin-bottom: 20px;
}

.header h1 {

    margin: 0 0 7px;

    font-size: 25px;
}

.header p {

    margin: 0;

    color: #e5ebe7;
}

.card {

    background: white;

    border-radius: 14px;

    padding: 22px;

    margin-bottom: 20px;

    box-shadow:
        0 4px 18px rgba(0,0,0,.06);
}

.card h2 {

    margin-top: 0;

    font-size: 20px;
}

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;
}

.field {

    display: flex;

    flex-direction: column;

    gap: 7px;
}

.field label {

    font-weight: bold;

    font-size: 14px;
}

.field input,
.field select {

    width: 100%;

    padding: 12px;

    border:
        1px solid #d1d5db;

    border-radius: 8px;

    font-size: 14px;

    background: white;
}

.checkbox {

    display: flex;

    align-items: center;

    gap: 8px;

    margin-top: 27px;
}

.buttons {

    margin-top: 20px;

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}

.btn {

    display: inline-block;

    padding: 10px 15px;

    border: none;

    border-radius: 8px;

    text-decoration: none;

    cursor: pointer;

    font-size: 13px;

    font-family: inherit;
}

.btn-primary {

    background: #345c49;

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

.btn-toggle {

    background: #059669;

    color: white;
}

/* إلغاء ربط الجهاز */

.btn-device {

    background: #9bac78;

    color: #26382f;

    font-weight: bold;
}

.btn-device:hover {

    background: #879a67;
}

.table-wrap {

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1100px;
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

    vertical-align: middle;
}

.actions {

    display: flex;

    gap: 6px;

    flex-wrap: wrap;
}

.status-active {

    color: #059669;

    font-weight: bold;
}

.status-disabled {

    color: #dc2626;

    font-weight: bold;
}

.device-linked {

    color: #345c49;

    font-weight: bold;
}

.device-free {

    color: #9a6b00;

    font-weight: bold;
}

.alert {

    padding: 14px;

    border-radius: 8px;

    margin-bottom: 15px;
}

.alert-error {

    background: #fee2e2;

    color: #991b1b;
}

.alert-success {

    background: #dcfce7;

    color: #166534;
}

@media (max-width: 900px) {

    .form-grid {

        grid-template-columns:
            1fr 1fr;
    }
}

@media (max-width: 600px) {

    .container {

        width: 94%;

        margin: 15px auto;
    }

    .form-grid {

        grid-template-columns:
            1fr;
    }

    .header h1 {

        font-size: 21px;
    }
}

</style>

</head>

<body>

<div class="container">


    <!-- HEADER -->

    <div class="header">

        <h1>
            👨‍🎓 إدارة الطلاب
        </h1>

        <p>
            إضافة وإدارة حسابات الطلاب في منصة أكاديمي التعليمية
        </p>

    </div>


    <!-- ERROR -->

    <?php if (!empty($error)): ?>

        <div class="alert alert-error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>


    <!-- SUCCESS -->

    <?php if (isset($_GET['success'])): ?>

        <div class="alert alert-success">

            <?php if ($_GET['success'] === 'added'): ?>

                تم إضافة الطالب بنجاح ✅

            <?php elseif ($_GET['success'] === 'updated'): ?>

                تم تعديل بيانات الطالب بنجاح ✅

            <?php elseif ($_GET['success'] === 'deleted'): ?>

                تم حذف الطالب بنجاح ✅

            <?php elseif ($_GET['success'] === 'toggled'): ?>

                تم تغيير حالة حساب الطالب ✅

            <?php elseif ($_GET['success'] === 'device_reset'): ?>

                تم إلغاء ربط جهاز الطالب بنجاح 🔓

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!-- ADD / EDIT -->

    <div class="card">

        <h2>

            <?= $editStudent
                ? '✏️ تعديل بيانات الطالب'
                : '➕ إضافة طالب جديد'
            ?>

        </h2>


        <form method="POST">

            <input
                type="hidden"
                name="id"
                value="<?= $editStudent
                    ? (int)$editStudent['id']
                    : 0
                ?>"
            >


            <div class="form-grid">


                <div class="field">

                    <label>
                        اسم الطالب
                    </label>

                    <input
                        type="text"
                        name="name"
                        required
                        value="<?= e(
                            $editStudent['name'] ?? ''
                        ) ?>"
                        placeholder="مثال: محمد علي"
                    >

                </div>


                <div class="field">

                    <label>
                        رقم الهاتف
                    </label>

                    <input
                        type="text"
                        name="phone"
                        required
                        value="<?= e(
                            $editStudent['phone'] ?? ''
                        ) ?>"
                        placeholder="07xxxxxxxxx"
                    >

                </div>


                <div class="field">

                    <label>

                        كلمة المرور

                        <?php if ($editStudent): ?>

                            <small>
                                (اتركها فارغة إذا لا تريد تغييرها)
                            </small>

                        <?php endif; ?>

                    </label>

                    <input
                        type="password"
                        name="password"
                        <?= $editStudent
                            ? ''
                            : 'required'
                        ?>
                        placeholder="كلمة مرور الطالب"
                    >

                </div>


                <div class="field">

                    <label>
                        المحافظة
                    </label>

                    <select name="governorate_id">

                        <option value="">
                            اختر المحافظة
                        </option>

                        <?php foreach (
                            $governorates as $gov
                        ): ?>

                            <option
                                value="<?= (int)$gov['id'] ?>"
                                <?= (
                                    isset(
                                        $editStudent[
                                            'governorate_id'
                                        ]
                                    )
                                    &&
                                    $editStudent[
                                        'governorate_id'
                                    ] == $gov['id']
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e($gov['name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="field">

                    <label>
                        المرحلة الدراسية
                    </label>

                    <select name="stage_id">

                        <option value="">
                            اختر المرحلة
                        </option>

                        <?php foreach (
                            $stages as $stage
                        ): ?>

                            <option
                                value="<?= (int)$stage['id'] ?>"
                                <?= (
                                    isset(
                                        $editStudent[
                                            'stage_id'
                                        ]
                                    )
                                    &&
                                    $editStudent[
                                        'stage_id'
                                    ] == $stage['id']
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e($stage['name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="field">

                    <label>
                        المنهج
                    </label>

                    <select name="curriculum_id">

                        <option value="">
                            اختر المنهج
                        </option>

                        <?php foreach (
                            $curricula as $curriculum
                        ): ?>

                            <option
                                value="<?= (int)$curriculum['id'] ?>"
                                <?= (
                                    isset(
                                        $editStudent[
                                            'curriculum_id'
                                        ]
                                    )
                                    &&
                                    $editStudent[
                                        'curriculum_id'
                                    ] == $curriculum['id']
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e($curriculum['name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>


            <label class="checkbox">

                <input
                    type="checkbox"
                    name="active"
                    value="1"
                    <?= (
                        !$editStudent
                        ||
                        !empty(
                            $editStudent['active']
                        )
                    )
                        ? 'checked'
                        : ''
                    ?>
                >

                حساب الطالب فعال

            </label>


            <div class="buttons">

                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <?= $editStudent
                        ? '💾 حفظ التعديل'
                        : '➕ إضافة الطالب'
                    ?>

                </button>


                <?php if ($editStudent): ?>

                    <a
                        href="students.php"
                        class="btn btn-secondary"
                    >
                        إلغاء
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </div>


    <!-- STUDENTS -->

    <div class="card">

        <h2>
            📋 قائمة الطلاب
        </h2>


        <div class="table-wrap">

            <table>

                <thead>

                <tr>

                    <th>#</th>

                    <th>
                        اسم الطالب
                    </th>

                    <th>
                        الهاتف
                    </th>

                    <th>
                        المحافظة
                    </th>

                    <th>
                        المرحلة
                    </th>

                    <th>
                        المنهج
                    </th>

                    <th>
                        الجهاز
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


                <?php if (!$students): ?>

                    <tr>

                        <td
                            colspan="9"
                            style="text-align:center;"
                        >
                            لا يوجد طلاب حالياً
                        </td>

                    </tr>

                <?php else: ?>


                    <?php foreach (
                        $students as $student
                    ): ?>

                        <tr>


                            <td>
                                <?= (int)$student['id'] ?>
                            </td>


                            <td>

                                <strong>

                                    <?= e(
                                        $student['name']
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <?= e(
                                    $student['phone']
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $student[
                                        'governorate_name'
                                    ] ?? '-'
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $student[
                                        'stage_name'
                                    ] ?? '-'
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $student[
                                        'curriculum_name'
                                    ] ?? '-'
                                ) ?>

                            </td>


                            <!-- DEVICE -->

                            <td>

                                <?php if (
                                    !empty(
                                        $student[
                                            'device_token'
                                        ]
                                    )
                                ): ?>

                                    <span
                                        class="device-linked"
                                    >
                                        🔒 مرتبط
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="device-free"
                                    >
                                        🔓 غير مرتبط
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <?php if (
                                    (int)$student['active']
                                    === 1
                                ): ?>

                                    <span
                                        class="status-active"
                                    >
                                        فعال
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="status-disabled"
                                    >
                                        غير فعال
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div class="actions">


                                    <a
                                        href="students.php?edit=<?= (int)$student['id'] ?>"
                                        class="btn btn-edit"
                                    >
                                        ✏️ تعديل
                                    </a>


                                    <a
                                        href="students.php?toggle=<?= (int)$student['id'] ?>"
                                        class="btn btn-toggle"
                                        onclick="return confirm('هل تريد تغيير حالة حساب هذا الطالب؟')"
                                    >
                                        🔄 الحالة
                                    </a>


                                    <?php if (
                                        !empty(
                                            $student[
                                                'device_token'
                                            ]
                                        )
                                    ): ?>

                                        <a
                                            href="student_device_action.php?id=<?= (int)$student['id'] ?>"
                                            class="btn btn-device"
                                            onclick="return confirm('هل تريد إلغاء ربط جهاز هذا الطالب؟ بعد ذلك يستطيع تسجيل الدخول من جهاز جديد.')"
                                        >
                                            🔓 إلغاء ربط الجهاز
                                        </a>

                                    <?php endif; ?>


                                    <a
                                        href="students.php?delete=<?= (int)$student['id'] ?>"
                                        class="btn btn-delete"
                                        onclick="return confirm('هل أنت متأكد من حذف هذا الطالب؟')"
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
<?php
require_once __DIR__ . '/../config/config.php';

$pdo = db();

$message = '';
$error = '';

/* =========================
   حذف محافظة
========================= */
if (isset($_GET['delete'])) {

    $id = (int) $_GET['delete'];

    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM governorates WHERE id = ?");
            $stmt->execute([$id]);

            $message = 'تم حذف المحافظة بنجاح.';
        } catch (PDOException $e) {
            $error = 'لا يمكن حذف المحافظة لأنها مرتبطة ببيانات أخرى.';
        }
    }
}

/* =========================
   إضافة / تعديل
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');

    if ($name === '') {

        $error = 'يرجى إدخال اسم المحافظة.';

    } else {

        try {

            if ($id > 0) {

                $stmt = $pdo->prepare("
                    UPDATE governorates
                    SET name = ?
                    WHERE id = ?
                ");

                $stmt->execute([$name, $id]);

                $message = 'تم تعديل المحافظة بنجاح.';

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO governorates (name)
                    VALUES (?)
                ");

                $stmt->execute([$name]);

                $message = 'تمت إضافة المحافظة بنجاح.';
            }

        } catch (PDOException $e) {

            $error = 'حدث خطأ أثناء حفظ المحافظة: ' . $e->getMessage();
        }
    }
}

/* =========================
   جلب بيانات التعديل
========================= */
$edit_governorate = null;

if (isset($_GET['edit'])) {

    $id = (int) $_GET['edit'];

    if ($id > 0) {

        $stmt = $pdo->prepare("
            SELECT id, name
            FROM governorates
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        $edit_governorate = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

/* =========================
   جلب المحافظات
========================= */
$stmt = $pdo->query("
    SELECT id, name
    FROM governorates
    ORDER BY name ASC
");

$governorates = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>المحافظات - منصة أكاديمي</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Tahoma, Arial, sans-serif;
    background: #f4f6f9;
    color: #1f2937;
}

.container {
    width: 94%;
    max-width: 1100px;
    margin: 30px auto;
}

.header {
    background: white;
    padding: 20px 25px;
    border-radius: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    box-shadow: 0 3px 15px rgba(0,0,0,.06);
}

.header h1 {
    margin: 0;
    font-size: 24px;
}

.back {
    background: #222;
    color: white;
    text-decoration: none;
    padding: 10px 17px;
    border-radius: 8px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 14px;
    margin-bottom: 20px;
    box-shadow: 0 3px 15px rgba(0,0,0,.06);
}

.card h2 {
    margin-top: 0;
}

.form {
    display: flex;
    gap: 10px;
}

.form input {
    flex: 1;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-family: inherit;
}

button,
.btn {
    border: none;
    text-decoration: none;
    padding: 11px 16px;
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
}

.save {
    background: #2563eb;
    color: white;
}

.edit {
    background: #f59e0b;
    color: white;
}

.delete {
    background: #dc2626;
    color: white;
}

.success {
    background: #dcfce7;
    color: #166534;
    padding: 13px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 13px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 14px;
    border-bottom: 1px solid #eee;
    text-align: right;
}

th {
    background: #f8fafc;
}

.empty {
    text-align: center;
    padding: 30px;
    color: #777;
}

.actions {
    display: flex;
    gap: 7px;
}

@media (max-width: 600px) {

    .header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }

    .form {
        flex-direction: column;
    }

}

</style>

</head>

<body>

<div class="container">

    <div class="header">

        <h1>📍 المحافظات</h1>

        <a href="index.php" class="back">
            العودة للوحة التحكم
        </a>

    </div>


    <?php if ($message): ?>

        <div class="success">
            <?= e($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>


    <!-- إضافة / تعديل -->

    <div class="card">

        <h2>
            <?= $edit_governorate ? '✏️ تعديل المحافظة' : '➕ إضافة محافظة' ?>
        </h2>

        <form method="POST">

            <input
                type="hidden"
                name="id"
                value="<?= $edit_governorate ? (int)$edit_governorate['id'] : 0 ?>"
            >

            <div class="form">

                <input
                    type="text"
                    name="name"
                    required
                    placeholder="اسم المحافظة"
                    value="<?= $edit_governorate ? e($edit_governorate['name']) : '' ?>"
                >

                <button type="submit" class="save">

                    <?= $edit_governorate
                        ? 'حفظ التعديل'
                        : 'إضافة المحافظة' ?>

                </button>

                <?php if ($edit_governorate): ?>

                    <a href="governorates.php" class="btn">
                        إلغاء
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </div>


    <!-- قائمة المحافظات -->

    <div class="card">

        <h2>📋 قائمة المحافظات</h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>#</th>

                        <th>اسم المحافظة</th>

                        <th>الإجراءات</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (!$governorates): ?>

                    <tr>

                        <td colspan="3" class="empty">
                            لا توجد محافظات مضافة حالياً.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($governorates as $governorate): ?>

                        <tr>

                            <td>
                                <?= (int)$governorate['id'] ?>
                            </td>

                            <td>
                                <?= e($governorate['name']) ?>
                            </td>

                            <td>

                                <div class="actions">

                                    <a
                                        class="btn edit"
                                        href="governorates.php?edit=<?= (int)$governorate['id'] ?>"
                                    >
                                        تعديل
                                    </a>

                                    <a
                                        class="btn delete"
                                        href="governorates.php?delete=<?= (int)$governorate['id'] ?>"
                                        onclick="return confirm('هل أنت متأكد من حذف هذه المحافظة؟');"
                                    >
                                        حذف
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
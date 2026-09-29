<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

if (!isset($pdo) && function_exists('db')) {
    $pdo = db();
} else {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['stage_csrf'])) {
    $_SESSION['stage_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['stage_csrf'];
$message = '';
$error = '';

/* العمليات */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'انتهت صلاحية الطلب، أعد تحميل الصفحة.';
    } else {

        $action = $_POST['action'] ?? '';

        try {

            /* إضافة */
            if ($action === 'add') {

                $name = trim((string)($_POST['name'] ?? ''));

                if ($name === '') {
                    throw new RuntimeException('اكتب اسم المرحلة.');
                }

                $stmt = $pdo->prepare(
                    "SELECT id FROM stages WHERE name = ? LIMIT 1"
                );

                $stmt->execute([$name]);

                if ($stmt->fetch()) {
                    throw new RuntimeException(
                        'هذه المرحلة موجودة مسبقاً.'
                    );
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO stages (name, active)
                     VALUES (?, 1)"
                );

                $stmt->execute([$name]);

                $message = 'تمت إضافة المرحلة بنجاح.';
            }

            /* تعديل */
            elseif ($action === 'edit') {

                $id = (int)($_POST['id'] ?? 0);
                $name = trim((string)($_POST['name'] ?? ''));

                if ($id <= 0 || $name === '') {
                    throw new RuntimeException(
                        'بيانات التعديل غير مكتملة.'
                    );
                }

                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM stages
                     WHERE name = ?
                     AND id <> ?
                     LIMIT 1"
                );

                $stmt->execute([$name, $id]);

                if ($stmt->fetch()) {
                    throw new RuntimeException(
                        'اسم المرحلة مستخدم مسبقاً.'
                    );
                }

                $stmt = $pdo->prepare(
                    "UPDATE stages
                     SET name = ?
                     WHERE id = ?"
                );

                $stmt->execute([$name, $id]);

                $message = 'تم تعديل المرحلة بنجاح.';
            }

            /* تفعيل / تعطيل */
            elseif ($action === 'toggle') {

                $id = (int)($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new RuntimeException(
                        'المرحلة غير صحيحة.'
                    );
                }

                $stmt = $pdo->prepare(
                    "UPDATE stages
                     SET active = IF(active = 1, 0, 1)
                     WHERE id = ?"
                );

                $stmt->execute([$id]);

                $message = 'تم تحديث حالة المرحلة.';
            }

            /* حذف */
            elseif ($action === 'delete') {

                $id = (int)($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new RuntimeException(
                        'المرحلة غير صحيحة.'
                    );
                }

                /* هل مرتبطة بالمناهج؟ */
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM curricula
                     WHERE stage_id = ?"
                );

                $stmt->execute([$id]);

                $curricula = (int)$stmt->fetchColumn();

                /* هل مرتبطة بالأساتذة؟ */
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM teacher_assignments
                     WHERE stage_id = ?"
                );

                $stmt->execute([$id]);

                $assignments = (int)$stmt->fetchColumn();

                if ($curricula > 0 || $assignments > 0) {

                    throw new RuntimeException(
                        'لا يمكن حذف المرحلة لأنها مرتبطة بمنهاج أو أستاذ. يمكنك تعطيلها بدلاً من حذفها.'
                    );
                }

                $stmt = $pdo->prepare(
                    "DELETE FROM stages WHERE id = ?"
                );

                $stmt->execute([$id]);

                $message = 'تم حذف المرحلة بنجاح.';
            }

        } catch (Throwable $e) {

            $error = $e->getMessage();
        }
    }
}

/* البحث */
$q = trim((string)($_GET['q'] ?? ''));

if ($q !== '') {

    $stmt = $pdo->prepare(
        "SELECT id, name, active FROM stages
         WHERE name LIKE ?
         ORDER BY id ASC"
    );

    $stmt->execute(['%' . $q . '%']);

    $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {

    $stages = $pdo->query(
        "SELECT id, name, active FROM stages
         ORDER BY id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>المراحل الدراسية - منصة أكاديمي</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Tahoma, Arial, sans-serif;
    background: #f4f6f9;
    color: #172033;
}

.top {
    height: 70px;
    background: #111827;
    color: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 28px;
}

.brand {
    font-size: 21px;
    font-weight: bold;
}

.back {
    background: #243047;
    color: white;
    text-decoration: none;
    padding: 10px 16px;
    border-radius: 10px;
}

.container {
    max-width: 1150px;
    margin: 30px auto;
    padding: 0 18px;
}

h1 {
    margin-bottom: 5px;
}

.subtitle {
    color: #6b7280;
    margin-bottom: 25px;
}

.card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 18px;
    border: 1px solid #e5e7eb;
}

.form {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 10px;
}

input {
    padding: 13px;
    border: 1px solid #d8dee8;
    border-radius: 10px;
    font-size: 15px;
}

button {
    border: 0;
    padding: 12px 17px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: bold;
}

.primary {
    background: #111827;
    color: white;
}

.secondary {
    background: #eef2f7;
}

.green {
    background: #e8f7ee;
    color: #147a3d;
}

.red {
    background: #fdecec;
    color: #b42318;
}

.alert {
    padding: 13px;
    border-radius: 10px;
    margin-bottom: 15px;
}

.success {
    background: #e9f8ef;
    color: #146c36;
}

.error {
    background: #fff0f0;
    color: #a61b1b;
}

.search {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 15px;
    border-bottom: 1px solid #edf0f4;
    text-align: right;
}

th {
    background: #f8fafc;
}

.badge {
    padding: 7px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.active {
    background: #e8f7ee;
    color: #147a3d;
}

.inactive {
    background: #f1f3f5;
    color: #69707d;
}

.actions {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

.edit-box {
    display: none;
    margin-top: 10px;
    padding: 12px;
    background: #f8fafc;
    border-radius: 10px;
}

@media(max-width:700px) {

    .form,
    .search {
        grid-template-columns: 1fr;
    }

    .top {
        padding: 0 15px;
    }

    .container {
        padding: 0 12px;
    }

}

</style>

<script>

function editStage(id) {

    document
        .querySelectorAll('.edit-box')
        .forEach(x => x.style.display = 'none');

    const box = document.getElementById('edit-' + id);

    if (box) {
        box.style.display = 'block';

        box.querySelector('input[name="name"]').focus();
    }
}

function closeEdit(id) {

    const box = document.getElementById('edit-' + id);

    if (box) {
        box.style.display = 'none';
    }
}

function confirmDelete(name) {

    return confirm(
        'هل تريد حذف مرحلة ' +
        name +
        '؟'
    );
}

</script>

</head>

<body>

<div class="top">

    <div class="brand">
        منصة أكاديمي التعليمية — الإدارة
    </div>

    <a class="back" href="index.php">
        ← لوحة التحكم
    </a>

</div>

<div class="container">

<h1>المراحل الدراسية</h1>

<div class="subtitle">
إدارة المراحل الدراسية في منصة أكاديمي
</div>

<?php if ($message): ?>

<div class="alert success">
    <?= h($message) ?>
</div>

<?php endif; ?>

<?php if ($error): ?>

<div class="alert error">
    <?= h($error) ?>
</div>

<?php endif; ?>


<div class="card">

<h3>إضافة مرحلة جديدة</h3>

<form method="post" class="form">

<input type="hidden"
       name="csrf"
       value="<?= h($csrf) ?>">

<input type="hidden"
       name="action"
       value="add">

<input
    type="text"
    name="name"
    placeholder="مثال: السادس العلمي"
    maxlength="150"
    required
>

<button class="primary">
    + إضافة المرحلة
</button>

</form>

</div>


<div class="card">

<form method="get" class="search">

<input
    type="text"
    name="q"
    value="<?= h($q) ?>"
    placeholder="ابحث عن مرحلة..."
>

<button class="primary">
    بحث
</button>

</form>

</div>


<div class="card">

<div style="overflow:auto">

<table>

<thead>

<tr>

<th>#</th>

<th>المرحلة</th>

<th>الحالة</th>

<th>الإجراءات</th>

</tr>

</thead>

<tbody>

<?php if (!$stages): ?>

<tr>

<td colspan="4"
    style="text-align:center;padding:35px">

لا توجد مراحل حالياً.

</td>

</tr>

<?php else: ?>

<?php foreach ($stages as $i => $stage): ?>

<tr>

<td>
<?= $i + 1 ?>
</td>

<td>
<strong>
<?= h($stage['name']) ?>
</strong>
</td>

<td>

<?php if ((int)$stage['active'] === 1): ?>

<span class="badge active">
مفعّلة
</span>

<?php else: ?>

<span class="badge inactive">
معطّلة
</span>

<?php endif; ?>

</td>

<td>

<div class="actions">

<button
    type="button"
    class="secondary"
    onclick="editStage(<?= (int)$stage['id'] ?>)"
>
تعديل
</button>


<form method="post">

<input type="hidden"
       name="csrf"
       value="<?= h($csrf) ?>">

<input type="hidden"
       name="action"
       value="toggle">

<input type="hidden"
       name="id"
       value="<?= (int)$stage['id'] ?>">

<button
    class="<?= (int)$stage['active'] === 1 ? 'red' : 'green' ?>"
>

<?= (int)$stage['active'] === 1
    ? 'تعطيل'
    : 'تفعيل'
?>

</button>

</form>


<form
    method="post"
    onsubmit="return confirmDelete(
        <?= json_encode(
            $stage['name'],
            JSON_UNESCAPED_UNICODE
        ) ?>
    )"
>

<input type="hidden"
       name="csrf"
       value="<?= h($csrf) ?>">

<input type="hidden"
       name="action"
       value="delete">

<input type="hidden"
       name="id"
       value="<?= (int)$stage['id'] ?>">

<button class="red">
حذف
</button>

</form>

</div>


<div
    class="edit-box"
    id="edit-<?= (int)$stage['id'] ?>"
>

<form method="post" class="form">

<input type="hidden"
       name="csrf"
       value="<?= h($csrf) ?>">

<input type="hidden"
       name="action"
       value="edit">

<input type="hidden"
       name="id"
       value="<?= (int)$stage['id'] ?>">

<input
    name="name"
    value="<?= h($stage['name']) ?>"
    maxlength="150"
    required
>

<button class="primary">
حفظ
</button>

<button
    type="button"
    class="secondary"
    onclick="closeEdit(<?= (int)$stage['id'] ?>)"
>
إلغاء
</button>

</form>

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
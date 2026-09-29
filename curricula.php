<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

if (!isset($pdo) && function_exists('db')) {
    $pdo = db();
}

if (function_exists('require_admin')) {
    require_admin();
} else {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();

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

if (empty($_SESSION['curriculum_csrf'])) {
    $_SESSION['curriculum_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['curriculum_csrf'];
$message = '';
$error = '';

/* جلب المراحل */
$stageRows = $pdo->query("
    SELECT id, name
    FROM stages
    WHERE active = 1
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* إضافة / تعديل / حذف */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'انتهت صلاحية الطلب.';
    } else {

        $action = $_POST['action'] ?? '';

        try {

            if ($action === 'add') {

                $stage_id = (int)($_POST['stage_id'] ?? 0);
                $name = trim((string)($_POST['name'] ?? ''));

                if ($stage_id <= 0 || $name === '') {
                    throw new RuntimeException(
                        'اختر المرحلة واكتب اسم المنهج.'
                    );
                }

                $check = $pdo->prepare("
                    SELECT id
                    FROM curricula
                    WHERE stage_id = ?
                    AND name = ?
                    LIMIT 1
                ");

                $check->execute([$stage_id, $name]);

                if ($check->fetch()) {
                    throw new RuntimeException(
                        'هذا المنهج موجود لهذه المرحلة مسبقاً.'
                    );
                }

                $stmt = $pdo->prepare("
                    INSERT INTO curricula
                    (stage_id, name, active)
                    VALUES (?, ?, 1)
                ");

                $stmt->execute([$stage_id, $name]);

                $message = 'تمت إضافة المنهج بنجاح.';
            }

            elseif ($action === 'edit') {

                $id = (int)($_POST['id'] ?? 0);
                $stage_id = (int)($_POST['stage_id'] ?? 0);
                $name = trim((string)($_POST['name'] ?? ''));

                if ($id <= 0 || $stage_id <= 0 || $name === '') {
                    throw new RuntimeException(
                        'بيانات التعديل غير مكتملة.'
                    );
                }

                $stmt = $pdo->prepare("
                    UPDATE curricula
                    SET stage_id = ?, name = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $stage_id,
                    $name,
                    $id
                ]);

                $message = 'تم تعديل المنهج بنجاح.';
            }

            elseif ($action === 'toggle') {

                $id = (int)($_POST['id'] ?? 0);

                $stmt = $pdo->prepare("
                    UPDATE curricula
                    SET active = IF(active = 1, 0, 1)
                    WHERE id = ?
                ");

                $stmt->execute([$id]);

                $message = 'تم تحديث حالة المنهج.';
            }

            elseif ($action === 'delete') {

                $id = (int)($_POST['id'] ?? 0);

                $stmt = $pdo->prepare("
                    DELETE FROM curricula
                    WHERE id = ?
                ");

                $stmt->execute([$id]);

                $message = 'تم حذف المنهج.';
            }

        } catch (Throwable $e) {

            $error = $e->getMessage();
        }
    }
}

/* البحث والقائمة */

$q = trim((string)($_GET['q'] ?? ''));

if ($q !== '') {

    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.name,
            c.active,
            c.stage_id,
            s.name AS stage_name
        FROM curricula c
        LEFT JOIN stages s
            ON s.id = c.stage_id
        WHERE c.name LIKE ?
        ORDER BY c.id ASC
    ");

    $stmt->execute(['%' . $q . '%']);

    $curricula = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {

    $curricula = $pdo->query("
        SELECT
            c.id,
            c.name,
            c.active,
            c.stage_id,
            s.name AS stage_name
        FROM curricula c
        LEFT JOIN stages s
            ON s.id = c.stage_id
        ORDER BY c.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>المناهج - أكاديمي</title>

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
    max-width: 1200px;
    margin: 30px auto;
    padding: 0 18px;
}

.card {
    background: white;
    padding: 22px;
    border-radius: 16px;
    margin-bottom: 18px;
    border: 1px solid #e5e7eb;
}

h1 {
    margin-bottom: 5px;
}

.subtitle {
    color: #6b7280;
    margin-bottom: 25px;
}

.form {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 10px;
}

input,
select {
    width: 100%;
    padding: 13px;
    border: 1px solid #d8dee8;
    border-radius: 10px;
    font-size: 15px;
    background: white;
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

@media(max-width:800px) {

    .form {
        grid-template-columns: 1fr;
    }

    .top {
        padding: 0 15px;
    }

}

</style>

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

<h1>إدارة المناهج الدراسية</h1>

<div class="subtitle">
ربط كل منهج بالمرحلة الدراسية الخاصة به.
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

<h3>إضافة منهج جديد</h3>

<form method="post" class="form">

<input type="hidden"
       name="csrf"
       value="<?= h($csrf) ?>">

<input type="hidden"
       name="action"
       value="add">

<select name="stage_id" required>

<option value="">
اختر المرحلة
</option>

<?php foreach ($stageRows as $stage): ?>

<option value="<?= (int)$stage['id'] ?>">

<?= h($stage['name']) ?>

</option>

<?php endforeach; ?>

</select>

<input
    type="text"
    name="name"
    placeholder="مثال: المنهج العراقي"
    maxlength="150"
    required
>

<button class="primary">
+ إضافة المنهج
</button>

</form>

</div>


<div class="card">

<form method="get" class="search">

<input
    type="text"
    name="q"
    value="<?= h($q) ?>"
    placeholder="ابحث عن منهج..."
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

<th>المنهج</th>

<th>الحالة</th>

<th>الإجراءات</th>

</tr>

</thead>

<tbody>

<?php if (!$curricula): ?>

<tr>

<td colspan="5"
    style="text-align:center;padding:35px">

لا توجد مناهج حالياً.

</td>

</tr>

<?php else: ?>

<?php foreach ($curricula as $i => $row): ?>

<tr>

<td><?= $i + 1 ?></td>

<td>
<?= h($row['stage_name'] ?? '-') ?>
</td>

<td>
<strong>
<?= h($row['name']) ?>
</strong>
</td>

<td>

<?php if ((int)$row['active'] === 1): ?>

<span class="badge active">
مفعّل
</span>

<?php else: ?>

<span class="badge inactive">
معطّل
</span>

<?php endif; ?>

</td>

<td>

<div class="actions">

<form method="post">

<input type="hidden"
       name="csrf"
       value="<?= h($csrf) ?>">

<input type="hidden"
       name="action"
       value="toggle">

<input type="hidden"
       name="id"
       value="<?= (int)$row['id'] ?>">

<button
class="<?= (int)$row['active'] === 1 ? 'red' : 'green' ?>"
>

<?= (int)$row['active'] === 1
    ? 'تعطيل'
    : 'تفعيل'
?>

</button>

</form>


<form method="post"
      onsubmit="return confirm('هل تريد حذف هذا المنهج؟')">

<input type="hidden"
       name="csrf"
       value="<?= h($csrf) ?>">

<input type="hidden"
       name="action"
       value="delete">

<input type="hidden"
       name="id"
       value="<?= (int)$row['id'] ?>">

<button class="red">
حذف
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
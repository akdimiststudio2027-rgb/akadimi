<?php

session_start();

require_once __DIR__ . '/../config/config.php';
$pdo = db();

$error = '';
$selectedStageId = (int)($_POST['stage_id'] ?? 0);
$selectedCurriculumId = (int)($_POST['curriculum_id'] ?? 0);

$registrationSetting = $pdo->prepare(
    "SELECT setting_value FROM settings WHERE setting_key = 'registration_enabled' LIMIT 1"
);
$registrationSetting->execute();
$registrationEnabled = $registrationSetting->fetchColumn() !== '0';

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
| جلب المحافظات
|--------------------------------------------------------------------------
*/
$governorates = [];
$stages = [];
$curricula = [];

try {

    $stmt = $pdo->query("
        SELECT id, name
        FROM governorates
        ORDER BY name ASC
    ");

    $governorates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT id, name
        FROM stages
        WHERE active = 1
        ORDER BY id ASC
    ");
    $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT c.id, c.name, c.stage_id, s.name AS stage_name
        FROM curricula c
        INNER JOIN stages s ON s.id = c.stage_id
        WHERE c.active = 1 AND s.active = 1
        ORDER BY c.stage_id ASC, c.id ASC
    ");
    $curricula = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $error = 'تعذر جلب بيانات التسجيل.';
}


/*
|--------------------------------------------------------------------------
| التسجيل
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$registrationEnabled) {
        $error = 'التسجيل متوقف حالياً. يرجى التواصل مع إدارة المنصة.';
    } else {

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $governorate_id = (int)($_POST['governorate_id'] ?? 0);
    $stage_id = (int)($_POST['stage_id'] ?? 0);
    $curriculum_id = (int)($_POST['curriculum_id'] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | التحقق من البيانات
    |--------------------------------------------------------------------------
    */

    if ($name === '') {

        $error = 'يرجى إدخال الاسم الكامل.';

    } elseif (mb_strlen($name, 'UTF-8') < 3) {

        $error = 'يرجى إدخال الاسم الكامل باللغة العربية.';

    } elseif ($phone === '') {

        $error = 'يرجى إدخال رقم الهاتف.';

    } elseif (!preg_match('/^(07)[0-9]{9}$/', $phone)) {

        $error = 'يرجى إدخال رقم هاتف عراقي صحيح. مثال: 07XXXXXXXXX';

    } elseif ($password === '') {

        $error = 'يرجى إدخال كلمة المرور.';

    } elseif (strlen($password) < 6) {

        $error = 'كلمة المرور يجب أن تكون 6 أحرف أو أرقام على الأقل.';

    } elseif ($password !== $password_confirm) {

        $error = 'كلمتا المرور غير متطابقتين.';

    } elseif (!in_array($gender, ['male', 'female'], true)) {

        $error = 'يرجى اختيار الجنس.';

    } elseif ($governorate_id <= 0) {

        $error = 'يرجى اختيار المحافظة.';

    } elseif ($stage_id <= 0) {

        $error = 'يرجى اختيار المرحلة الدراسية.';

    } elseif ($curriculum_id <= 0) {

        $error = 'يرجى اختيار المنهاج.';

    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT c.id
                FROM curricula c
                INNER JOIN stages s ON s.id = c.stage_id
                WHERE c.id = ?
                  AND c.stage_id = ?
                  AND c.active = 1
                  AND s.active = 1
                LIMIT 1
            ");
            $stmt->execute([$curriculum_id, $stage_id]);

            if (!$stmt->fetchColumn()) {
                $error = 'المنهاج المختار لا يتبع المرحلة الدراسية.';
            } else {

            /*
            |--------------------------------------------------------------------------
            | التأكد من أن الهاتف غير مسجل في الحسابات
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id
                FROM students
                WHERE phone = ?
                LIMIT 1
            ");

            $stmt->execute([$phone]);

            if ($stmt->fetch()) {

                $error = 'رقم الهاتف مسجل مسبقاً. يمكنك تسجيل الدخول.';

            } else {
                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare("
                    INSERT INTO students
                    (
                        name,
                        phone,
                        password,
                        gender,
                        governorate_id,
                        stage_id,
                        curriculum_id,
                        active,
                        created_at
                    )
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?, 1, NOW())
                ");

                $stmt->execute([
                    $name,
                    $phone,
                    $password_hash,
                    $gender,
                    $governorate_id,
                    $stage_id,
                    $curriculum_id
                ]);

                header('Location: login.php?registered=1');
                exit;
            }
            }

        } catch (PDOException $e) {

            $error = 'حدث خطأ أثناء إنشاء طلب التسجيل.';
        }
    }
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

<title>الانضمام إلى منصة أكاديمي</title>

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

    background: #f5f8fc;

    color: #172033;

    padding: 25px 15px;
}

.container {

    width: 100%;

    max-width: 560px;

    margin: auto;
}

.logo {

    width: 65px;

    height: 65px;

    margin: 0 auto 15px;

    border-radius: 20px;

    background: #345c49;

    color: #ede7d2;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 28px;

    font-weight: bold;

}

.title {

    text-align: center;

    font-size: 25px;

    font-weight: bold;

    margin-bottom: 7px;
}

.subtitle {

    text-align: center;

    color: #6f7d73;

    font-size: 14px;

    margin-bottom: 25px;
}

.card {

    background: white;

    border-radius: 24px;

    padding: 28px;

    border: 1px solid #e8e8e8;

    box-shadow:
        0 15px 40px rgba(52,92,73,.08);
}

.card-title {

    font-size: 20px;

    font-weight: bold;

    margin-bottom: 22px;
}

.field {

    margin-bottom: 17px;
}

label {

    display: block;

    font-size: 14px;

    font-weight: bold;

    margin-bottom: 8px;
}

input,
select {

    width: 100%;

    height: 50px;

    border: 1px solid #d8ddd8;

    border-radius: 12px;

    padding: 0 14px;

    background: white;

    font-family: inherit;

    font-size: 14px;

    outline: none;

    color: #172033;
}

input:focus,
select:focus {

    border-color: #345c49;

    box-shadow:
        0 0 0 3px rgba(52,92,73,.08);
}

.phone-input {

    direction: ltr;

    text-align: right;
}

.gender-title {

    font-size: 14px;

    font-weight: bold;

    margin-bottom: 9px;
}

.gender-options {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 12px;
}

.gender-option {

    position: relative;
}

.gender-option input {

    position: absolute;

    opacity: 0;

    pointer-events: none;
}

.gender-option label {

    height: 50px;

    margin: 0;

    border: 1px solid #d8ddd8;

    border-radius: 12px;

    display: flex;

    align-items: center;

    justify-content: center;

    cursor: pointer;

    font-weight: bold;

    color: #526157;

    background: white;
}

.gender-option input:checked + label {

    border-color: #345c49;

    background: #ede7d2;

    color: #345c49;
}

.password-note {

    font-size: 11px;

    color: #8a98ab;

    margin-top: 6px;
}

.register-btn {

    width: 100%;

    height: 53px;

    border: none;

    border-radius: 13px;

    background: #345c49;

    color: white;

    font-family: inherit;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;

    margin-top: 22px;
}

.register-btn:hover {

    background: #294b3b;
}

.error {

    background: #fff0f0;

    border: 1px solid #ffd5d5;

    color: #b42318;

    padding: 13px;

    border-radius: 10px;

    margin-bottom: 18px;

    font-size: 13px;

    text-align: center;
}

.login {

    text-align: center;

    color: #8996a8;

    margin-top: 20px;

    font-size: 14px;
}

.login a {

    color: #345c49;

    font-weight: bold;

    text-decoration: none;
}

@media (max-width: 500px) {

    .card {

        padding: 22px 18px;
    }

}

</style>

</head>

<body>


<div class="container">


    <div class="logo">
        أ
    </div>


    <div class="title">
        منصة أكاديمي التعليمية
    </div>


    <div class="subtitle">
        انضم إلى المنصة وابدأ رحلتك التعليمية
    </div>


    <div class="card">


        <div class="card-title">
            الانضمام للمنصة
        </div>


        <?php if ($error): ?>

            <div class="error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <form method="POST" autocomplete="off">


            <!-- الاسم -->

            <div class="field">

                <label>
                    الاسم الكامل
                </label>

                <input
                    type="text"
                    name="name"
                    placeholder="أدخل اسمك الكامل باللغة العربية"
                    required
                    value="<?= e($_POST['name'] ?? '') ?>"
                >

            </div>


            <!-- الهاتف -->

            <div class="field">

                <label>
                    رقم الهاتف العراقي
                </label>

                <input
                    type="tel"
                    name="phone"
                    class="phone-input"
                    placeholder="07XXXXXXXXX"
                    maxlength="11"
                    inputmode="numeric"
                    required
                    value="<?= e($_POST['phone'] ?? '') ?>"
                >

            </div>


            <!-- كلمة المرور -->

            <div class="field">

                <label>
                    كلمة المرور
                </label>

                <input
                    type="password"
                    name="password"
                    placeholder="أدخل كلمة المرور"
                    required
                >

                <div class="password-note">
                    6 أحرف أو أرقام على الأقل
                </div>

            </div>


            <!-- تأكيد كلمة المرور -->

            <div class="field">

                <label>
                    تأكيد كلمة المرور
                </label>

                <input
                    type="password"
                    name="password_confirm"
                    placeholder="أعد كتابة كلمة المرور"
                    required
                >

            </div>


            <!-- الجنس -->

            <div class="field">

                <div class="gender-title">
                    الجنس
                </div>

                <div class="gender-options">


                    <div class="gender-option">

                        <input
                            type="radio"
                            id="male"
                            name="gender"
                            value="male"
                            <?= (($_POST['gender'] ?? '') === 'male')
                                ? 'checked'
                                : '' ?>
                            required
                        >

                        <label for="male">
                            ذكر
                        </label>

                    </div>


                    <div class="gender-option">

                        <input
                            type="radio"
                            id="female"
                            name="gender"
                            value="female"
                            <?= (($_POST['gender'] ?? '') === 'female')
                                ? 'checked'
                                : '' ?>
                        >

                        <label for="female">
                            أنثى
                        </label>

                    </div>


                </div>

            </div>


            <!-- المرحلة -->

            <div class="field">

                <label for="stage_id">المرحلة الدراسية</label>

                <select name="stage_id" id="stage_id" required>
                    <option value="">اختر المرحلة</option>
                    <?php foreach ($stages as $stage): ?>
                        <option
                            value="<?= (int)$stage['id'] ?>"
                            <?= $selectedStageId === (int)$stage['id'] ? 'selected' : '' ?>
                        >
                            <?= e($stage['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            </div>


            <!-- المنهاج -->

            <div class="field">

                <label for="curriculum_id">المنهاج</label>

                <select name="curriculum_id" id="curriculum_id" required>
                    <option value="">اختر المنهاج</option>
                    <?php foreach ($curricula as $curriculum): ?>
                        <option
                            value="<?= (int)$curriculum['id'] ?>"
                            data-stage-id="<?= (int)$curriculum['stage_id'] ?>"
                            <?= $selectedCurriculumId === (int)$curriculum['id'] ? 'selected' : '' ?>
                        >
                            <?= e($curriculum['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            </div>


            <!-- المحافظة -->

            <div class="field">

                <label>
                    المحافظة
                </label>

                <select
                    name="governorate_id"
                    required
                >

                    <option value="">
                        اختر المحافظة
                    </option>

                    <?php foreach ($governorates as $governorate): ?>

                        <option
                            value="<?= (int)$governorate['id'] ?>"
                            <?= (
                                (int)($_POST['governorate_id'] ?? 0)
                                ===
                                (int)$governorate['id']
                            ) ? 'selected' : '' ?>
                        >

                            <?= e($governorate['name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <button
                type="submit"
                class="register-btn"
            >
                متابعة
            </button>


        </form>


        <div class="login">

            لديك حساب بالفعل؟

            <a href="login.php">
                تسجيل الدخول
            </a>

        </div>


    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const stageSelect = document.getElementById('stage_id');
    const curriculumSelect = document.getElementById('curriculum_id');

    function updateCurricula(resetSelection) {
        const stageId = stageSelect.value;
        let selectedCurriculumIsValid = false;

        Array.from(curriculumSelect.options).forEach(function (option) {
            if (!option.value) return;

            const belongsToStage = option.dataset.stageId === stageId;
            option.hidden = !belongsToStage;
            option.disabled = !belongsToStage;

            if (belongsToStage && option.selected) {
                selectedCurriculumIsValid = true;
            }
        });

        curriculumSelect.disabled = !stageId;
        if (!stageId || resetSelection || !selectedCurriculumIsValid) {
            curriculumSelect.value = '';
        }
    }

    stageSelect.addEventListener('change', function () {
        updateCurricula(true);
    });

    updateCurricula(false);
});
</script>


</body>

</html>
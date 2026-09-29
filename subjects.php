<?php

session_start();

require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
        require_once __DIR__ . '/student_auth_check.php';

}

$pdo = db();

$studentId = (int) $_SESSION['student_id'];

/* =========================
   بيانات الطالب
========================= */

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        stage_id,
        curriculum_id,
        active
    FROM students
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$studentId]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student || (int)$student['active'] !== 1) {
    session_destroy();
    header('Location: login.php');
    exit;
}

/* =========================
   اسم المرحلة
========================= */

$stageName = '';

if (!empty($student['stage_id'])) {

    $stmt = $pdo->prepare("
        SELECT name
        FROM stages
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        (int)$student['stage_id']
    ]);

    $stageName = $stmt->fetchColumn() ?: '';
}

/* =========================
   اسم المنهج
========================= */

$curriculumName = '';

if (!empty($student['curriculum_id'])) {

    $stmt = $pdo->prepare("
        SELECT name
        FROM curricula
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        (int)$student['curriculum_id']
    ]);

    $curriculumName = $stmt->fetchColumn() ?: '';
}

/* =========================
   المواد
   تظهر حسب منهج الطالب
   حتى إذا ما بيها أساتذة
========================= */

$subjects = [];

if (!empty($student['curriculum_id'])) {

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            curriculum_id,
            active
        FROM subjects
        WHERE active = 1
          AND curriculum_id = ?
        ORDER BY id ASC
    ");

    $stmt->execute([
        (int)$student['curriculum_id']
    ]);

    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================
   أيقونات المواد
========================= */

$subjectIcons = [
    '📘',
    '📐',
    '🧪',
    '🔬',
    '🌍',
    '📖',
    '💻',
    '🧮',
    '⚗️',
    '📚'
];

?>
<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>مواد المرحلة | منصة أكاديمي</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Tahoma, Arial, sans-serif;
    background:
        linear-gradient(
            180deg,
            #ede7d2 0%,
            #e8e8e8 45%,
            #f4f4f2 100%
        );
    color: #24362d;
    min-height: 100vh;
    padding-bottom: 90px;
}

a {
    text-decoration: none;
    color: inherit;
}

.page {
    width: 100%;
    max-width: 760px;
    margin: auto;
    padding: 15px;
}

/* HEADER */

.header {
    background: #345c49;
    color: #fff;
    border-radius: 24px;
    padding: 20px;
    margin-bottom: 18px;
    box-shadow: 0 10px 25px rgba(52,92,73,.15);
}

.header-top {
    display: flex;
    align-items: center;
    gap: 12px;
}

.back {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    background: rgba(255,255,255,.12);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.header-text {
    flex: 1;
}

.header-title {
    font-size: 20px;
    font-weight: bold;
}

.header-description {
    margin-top: 7px;
    font-size: 12px;
    color: rgba(255,255,255,.72);
    line-height: 1.7;
}

.badges {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    margin-top: 14px;
}

.badge {
    background: rgba(255,255,255,.11);
    padding: 7px 10px;
    border-radius: 10px;
    font-size: 10px;
}

/* TITLE */

.section-title {
    color: #345c49;
    font-size: 17px;
    font-weight: bold;
    margin: 22px 3px 12px;
}

/* SUBJECTS */

.subjects {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 13px;
}

.subject-card {
    background: rgba(255,255,255,.84);
    border: 1px solid rgba(52,92,73,.12);
    border-radius: 21px;
    padding: 16px 10px;
    text-align: center;
    box-shadow: 0 8px 20px rgba(52,92,73,.07);
    transition: .2s;
}

.subject-card:hover {
    transform: translateY(-3px);
    border-color: #9bac78;
}

.subject-icon {
    width: 58px;
    height: 58px;
    margin: auto;
    border-radius: 18px;
    background: #ede7d2;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
}

.subject-name {
    margin-top: 10px;
    font-size: 13px;
    font-weight: bold;
    color: #345c49;
    line-height: 1.5;
}

.subject-action {
    margin-top: 6px;
    color: #9bac78;
    font-size: 10px;
}

/* EMPTY */

.empty {
    background: rgba(255,255,255,.8);
    border-radius: 22px;
    padding: 40px 20px;
    text-align: center;
    border: 1px dashed rgba(52,92,73,.2);
}

.empty-icon {
    font-size: 42px;
}

.empty h3 {
    margin-top: 12px;
    color: #345c49;
    font-size: 17px;
}

.empty p {
    margin-top: 8px;
    color: #748078;
    font-size: 12px;
    line-height: 1.8;
}

/* BOTTOM */

.bottom-nav {
    position: fixed;
    bottom: 10px;
    right: 12px;
    left: 12px;
    max-width: 560px;
    margin: auto;
    min-height: 68px;
    background: rgba(255,255,255,.9);
    border: 1px solid rgba(52,92,73,.12);
    border-radius: 23px;
    box-shadow: 0 12px 30px rgba(52,92,73,.16);
    display: grid;
    grid-template-columns: repeat(4,1fr);
    padding: 6px;
    z-index: 100;
}

.nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    border-radius: 17px;
    color: #7b8780;
    font-size: 10px;
}

.nav-icon {
    font-size: 19px;
}

.nav-item.active {
    background: #345c49;
    color: #fff;
}

@media (max-width: 500px) {

    .subjects {
        grid-template-columns: repeat(2, 1fr);
    }

}

</style>

</head>

<body>

<div class="page">

    <section class="header">

        <div class="header-top">

            <a href="index.php" class="back">
                →
            </a>

            <div class="header-text">

                <div class="header-title">
                    مواد المرحلة
                </div>

                <div class="header-description">
                    اختر المادة لمشاهدة الأساتذة والدورات المتاحة
                </div>

            </div>

        </div>

        <div class="badges">

            <?php if ($stageName): ?>

                <div class="badge">
                    🎓 <?= htmlspecialchars($stageName, ENT_QUOTES, 'UTF-8') ?>
                </div>

            <?php endif; ?>

            <?php if ($curriculumName): ?>

                <div class="badge">
                    📚 <?= htmlspecialchars($curriculumName, ENT_QUOTES, 'UTF-8') ?>
                </div>

            <?php endif; ?>

        </div>

    </section>


    <div class="section-title">
        المواد الدراسية
    </div>


    <?php if (!empty($subjects)): ?>

        <div class="subjects">

            <?php foreach ($subjects as $index => $subject): ?>

                <?php
                $icon =
                    $subjectIcons[
                        $index % count($subjectIcons)
                    ];
                ?>

                <a
                    href="teachers.php?subject_id=<?= (int)$subject['id'] ?>"
                    class="subject-card"
                >

                    <div class="subject-icon">
                        <?= $icon ?>
                    </div>

                    <div class="subject-name">
                        <?= htmlspecialchars(
                            $subject['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                    <div class="subject-action">
                        مشاهدة الأساتذة ←
                    </div>

                </a>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty">

            <div class="empty-icon">
                📚
            </div>

            <h3>
                لا توجد مواد متاحة حالياً
            </h3>

            <p>
                لم تتم إضافة مواد لهذا المنهج بعد.
            </p>

        </div>

    <?php endif; ?>

</div>


<nav class="bottom-nav">

    <a href="index.php" class="nav-item">

        <span class="nav-icon">🏠</span>

        الرئيسية

    </a>


    <a href="subjects.php" class="nav-item active">

        <span class="nav-icon">📚</span>

        المواد

    </a>


    <a href="courses.php" class="nav-item">

        <span class="nav-icon">🎓</span>

        دوراتي

    </a>


    <a href="profile.php" class="nav-item">

        <span class="nav-icon">👤</span>

        حسابي

    </a>

</nav>

</body>

</html>
<?php

require_once __DIR__ . '/../bootstrap.php';
$identity = authenticated();

if ($identity['role'] !== 'student') {
    api_response(['error' => 'Student access required.'], 403);
}

$pdo = db();
$studentId = (int)$identity['id'];

$gradesStmt = $pdo->prepare(<<<'SQL'
    SELECT
        g.id,
        g.title,
        g.score,
        g.max_score,
        g.exam_date,
        g.notes,
        sub.name AS subject_name,
        t.name AS teacher_name
    FROM grades g
    LEFT JOIN subjects sub ON sub.id = g.subject_id
    LEFT JOIN teachers t ON t.id = g.teacher_id
    WHERE g.student_id = ?
      AND g.active = 1
    ORDER BY g.exam_date DESC, g.id DESC
    SQL);
$gradesStmt->execute([$studentId]);
$grades = $gradesStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($grades as &$grade) {
    $grade['id'] = (int)$grade['id'];
    $grade['score'] = (float)$grade['score'];
    $grade['max_score'] = (float)$grade['max_score'];
}
unset($grade);

$examsStmt = $pdo->prepare(<<<'SQL'
    SELECT
        e.id,
        e.course_id,
        e.title,
        e.exam_date,
        e.exam_time,
        e.duration,
        e.total_marks,
        e.description,
        c.title AS course_title,
        t.name AS teacher_name,
        sub.name AS subject_name
    FROM exams e
    INNER JOIN courses c ON c.id = e.course_id AND c.active = 1
    LEFT JOIN teachers t ON t.id = c.teacher_id
    LEFT JOIN subjects sub ON sub.id = c.subject_id
    WHERE e.active = 1
      AND EXISTS (
          SELECT 1
          FROM subscriptions s
          WHERE s.student_id = ?
            AND s.course_id = c.id
            AND s.status = 'active'
            AND s.starts_at <= NOW()
            AND s.expires_at > NOW()
      )
    ORDER BY e.exam_date ASC, e.exam_time ASC, e.id DESC
    SQL);
$examsStmt->execute([$studentId]);
$exams = $examsStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($exams as &$exam) {
    $exam['id'] = (int)$exam['id'];
    $exam['course_id'] = (int)$exam['course_id'];
    $exam['duration'] = (int)$exam['duration'];
    $exam['total_marks'] = (float)$exam['total_marks'];
}
unset($exam);

api_response(['grades' => $grades, 'exams' => $exams]);
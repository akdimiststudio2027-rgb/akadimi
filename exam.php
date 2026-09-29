<?php

require_once __DIR__ . '/../bootstrap.php';
$identity = authenticated();

if ($identity['role'] !== 'student') {
    api_response(['error' => 'Student access required.'], 403);
}

$examId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$examId || $examId < 1) {
    api_response(['error' => 'A valid exam id is required.'], 422);
}

$pdo = db();
$stmt = $pdo->prepare(<<<'SQL'
    SELECT
        e.id,
        e.course_id,
        e.title,
        e.questions_json,
        e.total_marks,
        c.title AS course_title
    FROM exams e
    INNER JOIN courses c ON c.id = e.course_id AND c.active = 1
    WHERE e.id = ?
      AND e.active = 1
      AND EXISTS (
          SELECT 1
          FROM subscriptions s
          WHERE s.student_id = ?
            AND s.course_id = c.id
            AND s.status = 'active'
            AND s.starts_at <= NOW()
            AND (s.expires_at IS NULL OR s.expires_at >= NOW())
      )
    LIMIT 1
    SQL);
$stmt->execute([$examId, (int)$identity['id']]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    api_response(['error' => 'الاختبار غير متاح أو لا تملك اشتراكًا فعالًا بالدورة.'], 404);
}

$questions = json_decode((string)$exam['questions_json'], true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($questions) || !$questions) {
    api_response(['error' => 'هذا الاختبار لا يحتوي على أسئلة متاحة.'], 404);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $publicQuestions = [];
    foreach ($questions as $question) {
        $options = $question['options'] ?? [];
        $publicQuestions[] = [
            'question' => (string)($question['question'] ?? ''),
            'options' => is_array($options) ? array_values($options) : [],
            'marks' => is_numeric($question['marks'] ?? null) ? (float)$question['marks'] : 1,
        ];
    }

    api_response([
        'exam' => [
            'id' => (int)$exam['id'],
            'title' => $exam['title'],
            'course_title' => $exam['course_title'],
            'total_marks' => (float)$exam['total_marks'],
        ],
        'questions' => $publicQuestions,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_response(['error' => 'Method not allowed.'], 405);
}

$input = api_input();
$answers = $input['answers'] ?? [];
if (!is_array($answers)) {
    api_response(['error' => 'Answers must be an array.'], 422);
}

$score = 0.0;
$maxScore = 0.0;
$results = [];
foreach ($questions as $index => $question) {
    $options = $question['options'] ?? [];
    $options = is_array($options) ? array_values($options) : [];
    $correct = (string)($question['correct'] ?? '');
    $marks = is_numeric($question['marks'] ?? null) ? (float)$question['marks'] : 1.0;
    $studentAnswer = isset($answers[$index]) ? (string)$answers[$index] : '';
    $isCorrect = $studentAnswer !== '' && hash_equals($correct, $studentAnswer);

    $maxScore += $marks;
    if ($isCorrect) {
        $score += $marks;
    }

    $results[] = [
        'question' => (string)($question['question'] ?? ''),
        'options' => $options,
        'student_answer' => $studentAnswer,
        'correct_answer' => $correct,
        'is_correct' => $isCorrect,
        'marks' => $marks,
    ];
}

$totalMarks = (float)$exam['total_marks'];
if ($totalMarks <= 0) {
    $totalMarks = $maxScore;
}
if ($maxScore > 0 && $totalMarks > 0) {
    $score = ($score / $maxScore) * $totalMarks;
}

api_response([
    'exam' => [
        'id' => (int)$exam['id'],
        'title' => $exam['title'],
        'course_title' => $exam['course_title'],
    ],
    'score' => round($score, 2),
    'total_marks' => $totalMarks,
    'results' => $results,
]);
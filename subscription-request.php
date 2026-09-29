<?php

require_once __DIR__ . '/../bootstrap.php';
$identity = authenticated();

if ($identity['role'] !== 'student') {
    api_response(['error' => 'Student access required.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_response(['error' => 'Method not allowed.'], 405);
}

$input = api_input();
$courseId = filter_var($input['course_id'] ?? null, FILTER_VALIDATE_INT);
$whatsapp = trim((string)($input['student_whatsapp'] ?? ''));
$notes = trim((string)($input['notes'] ?? ''));
$whatsappClean = preg_replace('/[\s\-\(\)]/', '', $whatsapp);

if (!$courseId || $courseId < 1) {
    api_response(['error' => 'A valid course_id is required.'], 422);
}
if (!$whatsappClean || !preg_match('/^(\+?964|0)?7[0-9]{9}$/', $whatsappClean)) {
    api_response(['error' => 'أدخل رقم واتساب عراقي صحيح. مثال: 07801234567'], 422);
}

$pdo = db();
$courseStmt = $pdo->prepare(<<<'SQL'
    SELECT c.id, c.price
    FROM courses c
    INNER JOIN teachers t ON t.id = c.teacher_id AND t.active = 1
    INNER JOIN students s ON s.id = ? AND s.active = 1
    WHERE c.id = ?
      AND c.active = 1
      AND t.stage_id = s.stage_id
      AND (t.curriculum_id = s.curriculum_id OR t.curriculum_id IS NULL)
    LIMIT 1
    SQL);
$courseStmt->execute([(int)$identity['id'], $courseId]);
$course = $courseStmt->fetch(PDO::FETCH_ASSOC);
if (!$course) {
    api_response(['error' => 'هذه الدورة غير متاحة لمرحلتك أو منهاجك.'], 404);
}

$activeSubscription = $pdo->prepare(<<<'SQL'
    SELECT id
    FROM subscriptions
    WHERE student_id = ?
      AND course_id = ?
      AND status = 'active'
      AND starts_at <= NOW()
      AND expires_at > NOW()
    LIMIT 1
    SQL);
$activeSubscription->execute([(int)$identity['id'], $courseId]);
if ($activeSubscription->fetchColumn()) {
    api_response(['error' => 'لديك اشتراك فعال بهذه الدورة.'], 409);
}

$pendingRequest = $pdo->prepare(<<<'SQL'
    SELECT id, request_number
    FROM subscription_requests
    WHERE student_id = ?
      AND course_id = ?
      AND status IN ('pending', 'new', 'waiting')
    ORDER BY id DESC
    LIMIT 1
    SQL);
$pendingRequest->execute([(int)$identity['id'], $courseId]);
$existingRequest = $pendingRequest->fetch(PDO::FETCH_ASSOC);
if ($existingRequest) {
    api_response([
        'error' => 'لديك طلب اشتراك قيد المراجعة لهذه الدورة.',
        'request_number' => $existingRequest['request_number'],
    ], 409);
}

$requestNumber = 'REQ-' . date('YmdHis') . '-' . random_int(100, 999);
$insert = $pdo->prepare(<<<'SQL'
    INSERT INTO subscription_requests
    (
        request_number,
        student_id,
        course_id,
        amount,
        status,
        student_whatsapp,
        notes,
        created_at,
        updated_at
    )
    VALUES (?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())
    SQL);
$insert->execute([
    $requestNumber,
    (int)$identity['id'],
    $courseId,
    $course['price'],
    $whatsappClean,
    $notes,
]);

api_response([
    'request_id' => (int)$pdo->lastInsertId(),
    'request_number' => $requestNumber,
    'status' => 'pending',
], 201);
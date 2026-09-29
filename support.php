<?php

require_once __DIR__ . '/../bootstrap.php';
$identity = authenticated();

if ($identity['role'] !== 'student') {
    api_response(['error' => 'Student access required.'], 403);
}

$pdo = db();
$studentId = (int)$identity['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare(<<<'SQL'
        SELECT id, subject, message, status, admin_reply, created_at, updated_at
        FROM support_tickets
        WHERE student_id = ?
        ORDER BY id DESC
        LIMIT 100
        SQL);
    $stmt->execute([$studentId]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tickets as &$ticket) {
        $ticket['id'] = (int)$ticket['id'];
    }
    unset($ticket);

    api_response(['tickets' => $tickets]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_response(['error' => 'Method not allowed.'], 405);
}

$input = api_input();
$subject = trim((string)($input['subject'] ?? ''));
$message = trim((string)($input['message'] ?? ''));
if ($subject === '' || $message === '') {
    api_response(['error' => 'اكتب موضوع الرسالة وتفاصيلها.'], 422);
}
if (mb_strlen($subject, 'UTF-8') > 255 || mb_strlen($message, 'UTF-8') > 10000) {
    api_response(['error' => 'الرسالة أطول من الحد المسموح.'], 422);
}

$stmt = $pdo->prepare(<<<'SQL'
    INSERT INTO support_tickets
    (student_id, subject, message, status, created_at, updated_at)
    VALUES (?, ?, ?, 'new', NOW(), NOW())
    SQL);
$stmt->execute([$studentId, $subject, $message]);

api_response(['ticket_id' => (int)$pdo->lastInsertId(), 'status' => 'new'], 201);
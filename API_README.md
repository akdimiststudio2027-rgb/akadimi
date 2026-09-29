# Mobile API

تمت إضافة API لتطبيق Android وiPhone داخل مجلد `api/`.

## Endpoints

- `GET /api/student/registration-options.php`
  - Returns active governorates, stages, and curricula.
- `POST /api/student/register.php`
  - JSON: `{ "name": "...", "phone": "...", "password": "...", "password_confirm": "...", "gender": "male|female", "governorate_id": 1, "stage_id": 1, "curriculum_id": 1, "device_id": "<64 hex characters>" }`
  - Creates the account without phone verification, binds it to the registering device, and returns a session token.
- `POST /api/student/login.php`
  - JSON: `{ "phone": "...", "password": "...", "device_id": "<64 hex characters>" }`
  - First successful login binds the account to this device ID. A different device is rejected until an admin resets the binding.
- `GET /api/student/me.php`
  - Header: `Authorization: Bearer <token>`
- `GET /api/student/courses.php`
  - Header: `Authorization: Bearer <token>`
- `GET /api/student/lessons.php?course_id=<id>`
  - Header: `Authorization: Bearer <token>`
  - Requires an active subscription to the course.
- `GET /api/student/available-courses.php`
  - Header: `Authorization: Bearer <token>`
  - Returns courses matching the student's stage and curriculum.
- `GET /api/student/teachers.php`
  - Header: `Authorization: Bearer <token>`
  - Returns teachers and course summaries matching the student's stage and curriculum, including image URLs, academic year, price, and lesson counts.
- `POST /api/student/subscription-request.php`
  - Header: `Authorization: Bearer <token>`
  - JSON: `{ "course_id": 1, "student_whatsapp": "078...", "notes": "..." }`
  - Creates a pending request and prevents duplicates or requests for active subscriptions.
- `GET /api/student/learning-tools.php`
  - Header: `Authorization: Bearer <token>`
  - Returns the student's grades and exams for active subscriptions.
- `GET|POST /api/student/exam.php?id=<id>`
  - Header: `Authorization: Bearer <token>`
  - GET returns questions without answers; POST accepts `{ "answers": { "0": "1" } }` and returns a server-scored result.
- `GET|POST /api/student/notifications.php`
  - Header: `Authorization: Bearer <token>`
  - POST actions: `mark_read` with `notification_id`, or `mark_all_read`.
- `GET|POST /api/student/notes.php`
  - Header: `Authorization: Bearer <token>`
  - POST actions: `add` with `title` and `content`, or `delete` with `note_id`.
- `GET|POST /api/student/support.php`
  - Header: `Authorization: Bearer <token>`
  - Lists the student's tickets and replies, or creates a ticket with `subject` and `message`.
- `GET /api/student/subscribed-teachers.php`
  - Header: `Authorization: Bearer <token>`
- `GET|POST /api/student/messages.php?teacher_id=<id>`
  - Header: `Authorization: Bearer <token>`
  - Only allows messaging teachers with a current active subscription.
- `POST /api/admin/login.php`
  - JSON: `{ "username": "...", "password": "..." }`
- `GET /api/admin/dashboard.php`
  - Header: `Authorization: Bearer <token>`

## إعداد الخادم

عرّف متغير بيئة سريًا باسم `AKADIMI_API_SECRET` على الاستضافة. لا تضع القيمة داخل ملفات PHP ولا ترسلها في التطبيق.

يجب رفع مجلد `api/` إلى نفس جذر الموقع، بحيث تصبح الروابط مثل:

`https://akadimi.kesug.com/api/student/login.php`

إذا تغيّر الدومين، حدّث `EXPO_PUBLIC_API_URL` في إعداد بناء تطبيق الموبايل ليشير إلى `/api` على الدومين الجديد.

الـ API يعتمد على الجداول المستخدمة فعليًا في صفحات الموقع: `students` و`admin_users` و`teachers` و`courses` و`subscriptions`.

## ملاحظة مهمة

ملف `config/schema.sql` المرفق يستخدم جدول `users`، بينما صفحات الموقع الحالية تستخدم `students` و`admin_users`. يجب توحيد مخطط قاعدة البيانات قبل التشغيل على استضافة جديدة؛ لا تستبدل قاعدة البيانات الحالية قبل أخذ نسخة احتياطية.

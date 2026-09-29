# تطبيق أكاديمي للطالب

تطبيق Expo مشترك لأجهزة Android وiOS. يتصل بواجهة الموقع عبر HTTPS، ويحفظ رمز الجلسة في التخزين الآمن للنظام.

## التشغيل

من مجلد `mobile/`:

```powershell
npx expo start
```

افتح التطبيق باستخدام Expo Go لمسح رمز QR. العنوان الافتراضي للواجهة هو `https://akadimi.kesug.com/api`. يمكن تغييره بمتغير `EXPO_PUBLIC_API_URL` عند الانتقال إلى دومين الاستضافة الجديد.

للمعاينة المحلية بدون اتصال بالخادم:

```powershell
$env:EXPO_PUBLIC_PREVIEW_MODE='1'
npx expo start --lan
```

يفتح وضع المعاينة شاشات ببيانات تجريبية ولا يستخدم تسجيل الدخول أو قاعدة البيانات.

## إعداد الخادم

ارفع مجلد `api/` إلى `htdocs/api/`، بما فيه النقاط الجديدة:

- `student/registration-options.php`
- `student/register.php`
- `student/courses.php`
- `student/lessons.php`
- `student/available-courses.php`
- `student/subscription-request.php`
- `student/learning-tools.php`
- `student/exam.php`
- `student/notifications.php`
- `student/notes.php`
- `student/support.php`
- `student/subscribed-teachers.php`
- `student/messages.php`
- وتحديثات `bootstrap.php` و`student/login.php` لدعم ربط الجهاز.

اضبط `AKADIMI_API_SECRET` كمتغير بيئة على الخادم. هذا سرّ خادمي ولا يوضع في ملفات JavaScript أو داخل التطبيق. لا تستورد ملف قاعدة البيانات التجريبي إذا كانت قاعدة الموقع الحالية هي المطلوبة.

## إنشاء حزم المتاجر

بعد تسجيل الدخول إلى حساب Expo وربط المشروع:

```powershell
npx eas-cli build --platform android --profile production
npx eas-cli build --platform ios --profile production
```

الاختبار يعرض نتيجة مصححة من الخادم لكنه لا يحفظ المحاولة كدرجة تلقائيًا، مثل سلوك موقع الطالب الحالي.

يتطلب النشر حساب Google Play Console وحساب Apple Developer، ثم رفع الحزم ومعلومات المتجر من حسابات المالك. يلزم اعتماد شعار التطبيق النهائي، ورابط سياسة خصوصية يشرح بيانات الطلاب، واستضافة API تسمح بطلبات التطبيقات، واختبار التطبيق على أجهزة فعلية قبل الإرسال. التطبيق لم يُنشر بعد ويحتاج ربطه بدومين API النهائي قبل اختبار جميع الخدمات.
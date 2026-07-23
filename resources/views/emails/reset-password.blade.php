{{-- resources/views/emails/reset-password.blade.php --}}

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور - LapGeneus</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .content {
            padding: 40px 30px;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .button:hover {
            opacity: 0.9;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #eee;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffecb5;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            font-size: 14px;
        }
        .code-box {
            background: #f0f0f0;
            padding: 15px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 5px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 LapGeneus</h1>
            <p>إعادة تعيين كلمة المرور</p>
        </div>

        <div class="content">
            <h2>مرحباً {{ $user->full_name ?? 'عميلنا العزيز' }},</h2>

            <p>لقد تلقينا طلباً لإعادة تعيين كلمة المرور لحسابك في <strong>LapGeneus</strong>.</p>

            <p>إذا كنت أنت من طلب إعادة التعيين، يرجى النقر على الزر أدناه:</p>

            <div style="text-align: center;">
                <a href="{{ $resetUrl }}" class="button">🔑 إعادة تعيين كلمة المرور</a>
            </div>

            <div class="warning">
                <strong>⚠️ ملاحظة مهمة:</strong>
                <ul style="margin: 10px 0 0 20px;">
                    <li>هذا الرابط صالح لمدة {{ config('auth.passwords.users.expire', 60) }} دقيقة فقط</li>
                    <li>إذا لم تطلب إعادة تعيين كلمة المرور، يرجى تجاهل هذا البريد</li>
                    <li>لن يتم تغيير كلمة المرور الخاصة بك إلا بعد النقر على الرابط أعلاه</li>
                </ul>
            </div>

            <p>إذا كان الزر لا يعمل، يمكنك نسخ الرابط التالي ولصقه في المتصفح:</p>
            <div class="code-box">
                {{ $resetUrl }}
            </div>

            <p>لمزيد من المساعدة، يرجى التواصل مع فريق الدعم الفني.</p>

            <p>شكراً لثقتك بنا!<br>
            <strong>فريق LapGeneus</strong></p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} LapGeneus. جميع الحقوق محفوظة.</p>
            <p>هذا بريد إلكتروني آلي، يرجى عدم الرد عليه.</p>
        </div>
    </div>
</body>
</html>

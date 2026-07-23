{{-- resources/views/emails/verify.blade.php --}}

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأكيد البريد الإلكتروني - LapGeneus</title>
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
            background: linear-gradient(135deg, #4ade80 0%, #16a34a 100%);
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
            background: linear-gradient(135deg, #4ade80 0%, #16a34a 100%);
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
        .code-box {
            background: #f0f0f0;
            padding: 15px;
            text-align: center;
            font-size: 14px;
            border-radius: 5px;
            margin: 20px 0;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ LapGeneus</h1>
            <p>تأكيد البريد الإلكتروني</p>
        </div>

        <div class="content">
            <h2>مرحباً {{ $user->full_name ?? 'عميلنا العزيز' }},</h2>

            <p>شكراً لتسجيلك في <strong>LapGeneus</strong>. لتفعيل حسابك والبدء في استخدام خدماتنا، يرجى تأكيد عنوان بريدك الإلكتروني بالنقر على الزر أدناه:</p>

            <div style="text-align: center;">
                <a href="{{ $verificationUrl }}" class="button">✉️ تأكيد البريد الإلكتروني</a>
            </div>

            <p>إذا كان الزر لا يعمل، يمكنك نسخ الرابط التالي ولصقه في المتصفح:</p>
            <div class="code-box">
                {{ $verificationUrl }}
            </div>

            <p>إذا لم تقم بإنشاء حساب في منصتنا، يرجى تجاهل هذا البريد.</p>

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

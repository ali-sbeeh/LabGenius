{{-- resources/views/emails/welcome.blade.php --}}

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مرحباً بك في LapGeneus</title>
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
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
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
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 LapGeneus</h1>
            <p>مرحباً بك في عائلتنا</p>
        </div>

        <div class="content">
            <h2>مرحباً {{ $user->full_name ?? 'عميلنا العزيز' }}،</h2>

            <p>شكراً لانضمامك إلى منصة <strong>LapGeneus</strong>. نحن سعداء جداً بوجودك معنا!</p>

            <p>يمكنك الآن استكشاف أحدث أجهزة اللابتوب والتقنيات المتطورة، والتمتع بتجربة تسوق فريدة ومميزة.</p>

            <div style="text-align: center;">
                <a href="{{ config('app.frontend_url') }}" class="button">🛒 ابدأ التسوق الآن</a>
            </div>

            <p>إذا كان لديك أي أسئلة أو استفسارات، فريق الدعم الفني لدينا دائماً جاهز لمساعدتك.</p>

            <p>نتمنى لك وقتاً ممتعاً!<br>
            <strong>فريق LapGeneus</strong></p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} LapGeneus. جميع الحقوق محفوظة.</p>
            <p>هذا بريد إلكتروني آلي، يرجى عدم الرد عليه.</p>
        </div>
    </div>
</body>
</html>

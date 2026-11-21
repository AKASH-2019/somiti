{{-- Standalone on purpose: must render even when the database is unavailable. --}}
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code')</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <meta name="theme-color" content="#0E3B2B">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #0E3B2B; font-family: 'Hind Siliguri', 'Noto Sans Bengali', sans-serif; color: #1C2A23; padding: 1rem; }
        .card { background: #fff; border-radius: .5rem; padding: 2rem; max-width: 26rem; text-align: center; }
        .code { font-size: 3rem; font-weight: 700; color: #17603F; margin: 0; }
        p { font-size: 1.1rem; }
        a { display: inline-block; margin-top: 1rem; background: #17603F; color: #fff; padding: .6rem 1.2rem; border-radius: .375rem; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <p class="code">@yield('code')</p>
        <p>@yield('message')</p>
        <a href="{{ url('/') }}">প্রথম পাতায় ফিরুন</a>
    </div>
</body>
</html>

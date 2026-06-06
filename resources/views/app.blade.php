<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ $appPayload['csrfToken'] }}">
    <meta name="theme-color" content="#0891b2">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/pwa-192.png">
    <title>EraLMS Enterprise</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app" data-page='@json($appPayload)'></div>
</body>
</html>

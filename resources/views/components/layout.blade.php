<!-- resources/views/components/layout.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Bill Summarizer' }}</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-gray-50 text-gray-900">
    <div class="p-6">
        {{ $slot }}
    </div>
    @vite('resources/js/app.js')
</body>
</html>

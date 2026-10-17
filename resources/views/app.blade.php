<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <script>
            try {
                const theme = localStorage.getItem('theme');
                document.documentElement.classList.toggle('dark', theme === 'dark' || (theme !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches));
            } catch {
                document.documentElement.classList.toggle('dark', window.matchMedia('(prefers-color-scheme: dark)').matches);
            }
        </script>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
        @viteReactRefresh
        @vite('resources/js/app.tsx')
        <x-inertia::head />
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

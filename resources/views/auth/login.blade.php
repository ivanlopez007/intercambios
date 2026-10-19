<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | Industrial Hefesto</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2563eb">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/icons/favicon-32.png" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

    @vite(['resources/css/app.css', 'resources/js/pwa.js'])
</head>

<body class="min-h-screen bg-slate-100 flex items-center justify-center p-4">
    <main class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-lg">
        <img src="/icons/icon-192.png" alt="" class="w-16 h-16 rounded-2xl mb-4">
        <h1 class="text-2xl font-bold text-slate-900">Industrial Hefesto</h1>
        <p class="mt-1 text-sm text-slate-500">Inicia sesión con tu cuenta de personal.</p>

        @if (session('status'))
        <div role="status" class="mt-5 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
        @endif

        @if ($errors->any())
        <div role="alert" class="mt-5 rounded-lg bg-rose-50 p-3 text-sm text-rose-800">
            {{ $errors->first() }}
        </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="email" class="mb-1 block text-sm font-semibold text-slate-700">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100">
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-semibold text-slate-700">Contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100">
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
                Mantener sesión iniciada
            </label>

            <button type="submit" class="w-full rounded-lg bg-blue-700 px-4 py-3 font-bold text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                Iniciar sesión
            </button>
        </form>
    </main>
</body>

</html>

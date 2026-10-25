<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>INNOVA Computers BD — React Admin Shell</title>

    <!-- React Foundation Stylesheet compiled by Vite -->
    @if(file_exists(public_path('react/main.css')))
        <link rel="stylesheet" href="{{ asset('react/main.css') }}">
    @endif
</head>
<body style="background-color: #f8fafc; margin: 0; min-height: 100vh;">
    <!-- Isolated React Admin Root Container -->
    <div id="react-root" data-api-base-url="{{ url('/api/v1') }}" data-app-mode="admin" data-base-path="/react-admin"></div>

    <!-- React 18 Foundation Bundle compiled by Vite -->
    <script type="module" src="{{ asset('react/main.js') }}"></script>
</body>
</html>

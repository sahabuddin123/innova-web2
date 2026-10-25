<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>INNOVA Computers BD — React Frontend Foundation</title>

    <!-- React Foundation Stylesheet compiled by Vite -->
    @if(file_exists(public_path('react/main.css')))
        <link rel="stylesheet" href="{{ asset('react/main.css') }}">
    @endif
</head>
<body style="background-color: #f1f5f9; margin: 0; min-height: 100vh;">
    <!-- Isolated React Mount Container -->
    <div id="react-root" data-api-base-url="{{ url('/api/v1') }}"></div>

    <!-- React 18 Foundation Bundle compiled by Vite -->
    <script type="module" src="{{ asset('react/main.js') }}"></script>
</body>
</html>

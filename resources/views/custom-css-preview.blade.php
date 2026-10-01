<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('filament-theme-studio::theme-studio.custom_css.preview') }}</title>
    <style>
        .fi-body { background: #f8fafc; color: #0f172a; font-family: system-ui, sans-serif; min-height: 100vh; padding: 2rem; }
        .fi-section { background: white; border: 1px solid #e2e8f0; border-radius: .75rem; max-width: 48rem; padding: 1.5rem; }
        .fi-btn { background: #f59e0b; border: 0; border-radius: .5rem; color: #111827; font: inherit; padding: .65rem 1rem; }
        .fi-input { border: 1px solid #94a3b8; border-radius: .5rem; font: inherit; margin-block: 1rem; padding: .65rem; width: 80%; }
        {!! $css !!}
    </style>
</head>
<body class="fi-body">
    <main class="fi-main">
        <section class="fi-section">
            <h1>{{ __('filament-theme-studio::theme-studio.custom_css.preview') }}</h1>
            <p>{{ __('filament-theme-studio::theme-studio.custom_css.preview_description') }}</p>
            <input class="fi-input" value="Theme Studio" readonly>
            <button class="fi-btn" type="button">{{ __('filament-theme-studio::theme-studio.custom_css.preview_button') }}</button>
        </section>
    </main>
</body>
</html>

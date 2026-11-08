{{-- Fallback for browsers that ignore the media attribute (e.g. Safari). --}}
<link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
{{-- Dark logo for light browser themes, light logo for dark browser themes. --}}
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}" media="(prefers-color-scheme: light)">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}" media="(prefers-color-scheme: light)">
<link rel="icon" type="image/webp" href="{{ asset('favicon-light.webp') }}" media="(prefers-color-scheme: dark)">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="theme-color" content="#0d5750">

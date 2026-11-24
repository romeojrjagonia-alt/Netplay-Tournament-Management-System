<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? 'NetPlay' }}</title>

@include('partials.favicon')

@include('partials.loading-indicator')

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

{{-- An expired session (HTTP 419) is recovered by reloading to get a fresh
     CSRF token, instead of showing the browser's "page expired" prompt. --}}
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.interceptRequest(({ onError }) => {
            onError(({ response, preventDefault }) => {
                if (response.status === 419) {
                    preventDefault();
                    window.location.reload();
                }
            });
        });
    });
</script>

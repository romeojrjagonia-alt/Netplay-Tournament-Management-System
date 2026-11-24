{{-- Dark logo for light browser themes, light logo for dark ones. Chrome does not
     reliably honour `media` on icon links, so a script swaps the href as well. --}}
<link id="app-favicon" rel="icon" type="image/png" href="{{ asset('favicon-32x32.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="theme-color" content="#0d5750">
<script>
    (() => {
        const icons = {
            light: @js(['href' => asset('favicon-32x32.png'), 'type' => 'image/png']),
            dark: @js(['href' => asset('favicon-light.webp'), 'type' => 'image/webp']),
        };
        const query = window.matchMedia('(prefers-color-scheme: dark)');
        const apply = () => {
            const link = document.getElementById('app-favicon');
            const icon = icons[query.matches ? 'dark' : 'light'];
            if (link) {
                link.type = icon.type;
                link.href = icon.href;
            }
        };
        apply();
        query.addEventListener('change', apply);
    })();
</script>

{{-- A site-wide loading indicator: a sliding bar along the top of the page plus
     a small spinner badge, shown while a page loads or navigates and while
     Livewire is fetching or saving data. Polling is ignored so background
     refreshes stay silent, and the indicator only appears after a short delay so
     quick responses don't flicker. Drawn with the <html> element's pseudo
     elements, so no layout needs to include any markup. --}}
<style>
    html.app-loading::before {
        content: '';
        position: fixed;
        inset: 0 0 auto 0;
        z-index: 2147483646;
        height: 3px;
        pointer-events: none;
        background: linear-gradient(90deg, transparent, #9cee69, #4ad8cc, transparent) 0 0 / 40% 100% no-repeat;
        animation: app-loading-bar 1.1s ease-in-out infinite;
    }

    html.app-loading::after {
        content: '';
        position: fixed;
        right: 20px;
        bottom: 20px;
        z-index: 2147483646;
        width: 26px;
        height: 26px;
        box-sizing: border-box;
        pointer-events: none;
        border: 3px solid rgb(156 238 105 / 0.3);
        border-top-color: #9cee69;
        border-radius: 9999px;
        background: rgb(11 71 66 / 0.85);
        animation: app-loading-spin 0.7s linear infinite;
    }

    @keyframes app-loading-bar {
        from { background-position: -60% 0; }
        to { background-position: 160% 0; }
    }

    @keyframes app-loading-spin {
        to { transform: rotate(360deg); }
    }

    @media (prefers-reduced-motion: reduce) {
        html.app-loading::before { animation-duration: 3s; }
        html.app-loading::after { animation-duration: 2s; }
    }
</style>

<script>
    (function () {
        const root = document.documentElement;
        const state = { page: true, navigating: false, requests: 0 };
        let showTimer = null;
        let unloadTimer = null;

        // Shown straight away on the first paint, then cleared once the page is parsed.
        root.classList.add('app-loading');

        const render = () => {
            const busy = state.page || state.navigating || state.requests > 0;

            if (! busy) {
                clearTimeout(showTimer);
                showTimer = null;
                root.classList.remove('app-loading');

                return;
            }

            if (! root.classList.contains('app-loading') && showTimer === null) {
                showTimer = setTimeout(() => {
                    showTimer = null;
                    root.classList.add('app-loading');
                }, 150);
            }
        };

        const update = (changes) => {
            Object.assign(state, changes);
            render();
        };

        document.addEventListener('DOMContentLoaded', () => update({ page: false }));
        // A page restored from the back/forward cache never re-runs the above.
        window.addEventListener('pageshow', () => update({ page: false, navigating: false }));

        // Normal links and form posts leave the page; a download keeps it, hence the safety timeout.
        window.addEventListener('beforeunload', () => {
            update({ navigating: true });
            clearTimeout(unloadTimer);
            unloadTimer = setTimeout(() => update({ navigating: false }), 10000);
        });

        // wire:navigate transitions.
        document.addEventListener('livewire:navigate', () => update({ navigating: true }));
        document.addEventListener('livewire:navigated', () => update({ navigating: false }));

        // Livewire requests (clicks, form saves, lazy loads) but not background polls.
        document.addEventListener('livewire:init', () => {
            Livewire.interceptMessage(({ message, onSend, onFinish, onCancel, onFailure, onError }) => {
                const actions = [...(message.actions ?? [])];

                if (actions.length > 0 && actions.every((action) => action.metadata?.type === 'poll')) {
                    return;
                }

                let counted = false;
                const done = () => {
                    if (counted) {
                        counted = false;
                        update({ requests: Math.max(0, state.requests - 1) });
                    }
                };

                onSend(() => {
                    counted = true;
                    update({ requests: state.requests + 1 });
                });
                onFinish(done);
                onCancel(done);
                onFailure(done);
                onError(done);
            });
        });
    })();
</script>

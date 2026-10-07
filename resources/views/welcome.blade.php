<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'NetPlay — Play. Compete. Excel'])
    </head>
    <body class="bg-emerald-950 font-sans antialiased">

        @php
            $activeTournaments = \App\Models\Tournament::query()
                ->acceptingRegistrations()
                ->orderBy('registration_end')
                ->take(3)
                ->get();

            // Annual tournaments are a fixed, client-curated list (held every year by OBC),
            // not driven by tournament records. Set 'image' to a poster URL once available.
            $annualTournaments = [
                ['name' => 'Inato Tournament', 'image' => null],
                ['name' => 'XSmash Badminton Cup', 'image' => null],
                ['name' => 'Inato Tournament', 'image' => null],
                ['name' => 'XSmash Badminton Cup', 'image' => null],
            ];
            $annualBlurb = 'Paragraph. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.';
        @endphp

        {{-- ============ NAVBAR ============ --}}
        <nav id="site-nav" class="fixed inset-x-0 top-0 z-50 transition-colors duration-300">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-3 sm:px-10">
            <a href="{{ route('home') }}" wire:navigate class="text-2xl font-bold text-white sm:text-3xl">NetPlay</a>

            <ul class="hidden items-center gap-10 text-sm text-white md:flex">
                <li><a href="{{ route('home') }}" wire:navigate class="transition hover:text-lime-300">Home Page</a></li>
                <li><a href="#tournaments" class="transition hover:text-lime-300">Tournaments</a></li>
            </ul>

            <div class="flex items-center gap-3 sm:gap-5">
                <a href="{{ route('login') }}" wire:navigate
                   class="flex h-9 items-center justify-center rounded-[36px] px-3 text-sm font-bold text-white transition hover:bg-white/10 sm:px-9">
                    Sign In
                </a>
                <a href="{{ route('register') }}" wire:navigate
                   class="flex h-9 items-center justify-center rounded-[36px] bg-white px-4 text-sm font-bold text-emerald-600 transition hover:bg-slate-100 sm:px-9">
                    Sign Up
                </a>
            </div>
        </div>
        </nav>

        {{-- Boxed page: content is capped and centered, with side margins on wide screens --}}
        <div class="mx-auto w-full max-w-6xl overflow-hidden">

        {{-- ============ HERO ============ --}}
        <section class="relative w-full overflow-hidden bg-gradient-to-b from-green-950 to-emerald-950">
            <img src="{{ asset('images/welcome/hero-bg-vector.svg') }}" alt=""
                 class="pointer-events-none absolute top-0 left-1/2 w-[1603px] max-w-none -translate-x-1/2 opacity-30"
                 aria-hidden="true">

            <div class="relative mx-auto flex max-w-6xl flex-col items-start gap-3.5 px-6 pt-32 pb-20 sm:px-10 sm:pt-44 sm:pb-28">
                <h1
                    class="bg-clip-text text-[2.15rem] leading-tight font-bold text-transparent sm:text-7xl sm:leading-[72px]"
                    style="background-image: linear-gradient(81deg, #459085 15.2%, #71bf77 40%, #9cee69 100%)"
                >
                    Play.Compete.Excel
                </h1>
                <p class="max-w-[607px] text-base leading-7 text-white sm:text-lg sm:leading-9">
                    Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                </p>
                <a href="{{ route('register') }}" wire:navigate
                   class="flex h-14 w-56 items-center justify-center rounded-3xl bg-white text-lg font-bold text-teal-700 transition hover:bg-slate-100">
                    REGISTER NOW!
                </a>
            </div>
        </section>

        {{-- ============ ANNUAL TOURNAMENTS (carousel) ============ --}}
        <section class="w-full bg-emerald-950 pb-20">
            <div class="flex h-12 w-full items-center justify-center bg-white">
                <h2 class="text-sm font-bold tracking-wide text-emerald-900 uppercase">Annual Tournaments</h2>
            </div>

            <div id="annual-carousel" class="relative mt-10">
                <div data-track
                     class="flex snap-x snap-mandatory gap-5 overflow-x-auto scroll-smooth px-6 scroll-px-6 [scrollbar-width:none] sm:scroll-px-10 sm:px-10 [&::-webkit-scrollbar]:hidden">
                    @foreach ($annualTournaments as $tournament)
                        <article class="flex w-[88%] shrink-0 snap-start flex-col items-start gap-4 rounded-lg border border-emerald-700/60 bg-emerald-950 p-4 sm:w-[calc((100%-1.25rem)/2)] lg:w-[calc((100%-2*1.25rem)/3)] xl:flex-row">
                            @if ($tournament['image'])
                                <img src="{{ $tournament['image'] }}" alt="{{ $tournament['name'] }}"
                                     class="h-32 w-full rounded object-cover xl:w-36 xl:shrink-0">
                            @else
                                <div class="h-32 w-full rounded bg-emerald-900 xl:w-36 xl:shrink-0" role="img" aria-label="{{ $tournament['name'] }}"></div>
                            @endif
                            <div class="flex min-w-0 flex-1 flex-col gap-2">
                                <h3 class="text-sm font-bold text-white">{{ $tournament['name'] }}</h3>
                                <p class="text-[10px] leading-4 text-white/80">{{ $annualBlurb }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-6 flex items-center justify-center gap-4">
                    <button type="button" data-prev aria-label="Previous"
                            class="flex size-8 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20">&larr;</button>
                    <button type="button" data-next aria-label="Next"
                            class="flex size-8 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20">&rarr;</button>
                </div>
            </div>
        </section>

        {{-- ============ ACTIVE TOURNAMENTS ============ --}}
        <section id="tournaments" class="w-full bg-emerald-950 px-6 pt-8 pb-20 sm:px-10">
            <div class="mx-auto flex max-w-6xl flex-col gap-8">
                <div class="flex items-end justify-between">
                    <div>
                        <p class="text-sm text-lime-300">Tournament List</p>
                        <h2 class="text-2xl font-bold text-white uppercase">Active Tournaments</h2>
                    </div>
                    <a href="#" class="border-b border-lime-300 text-sm text-lime-300">Explore More</a>
                </div>

                @if ($activeTournaments->isEmpty())
                    <p class="w-full text-center text-lg font-bold text-white">Coming Soon! Stay Tuned!</p>
                @else
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($activeTournaments as $tournament)
                            <article class="flex flex-col gap-2 rounded-lg bg-white p-3 shadow-[0px_4px_10px_0px_rgba(0,0,0,0.25)]">
                                @if ($tournament->poster_url)
                                    <img src="{{ $tournament->poster_url }}" alt="{{ $tournament->name }}"
                                         class="h-32 w-full rounded object-cover">
                                @else
                                    <div class="flex h-32 w-full items-center justify-center rounded bg-slate-100 text-xs text-slate-400">No poster</div>
                                @endif

                                <h3 class="text-sm font-bold text-black">{{ $tournament->name }}</h3>
                                <p class="text-[10px] leading-4 text-black/70">
                                    {{ ucfirst((string) $tournament->sport) }}@if ($tournament->format) &middot; {{ ucwords(str_replace('_', ' ', $tournament->format)) }}@endif
                                </p>

                                <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-[10px] text-black">
                                    <span class="inline-flex items-center gap-1.5">
                                        <img src="{{ asset('images/welcome/calendar-icon.svg') }}" alt="" class="size-4">
                                        {{ $tournament->date_range_label }}
                                    </span>
                                    @if ($tournament->specific_days)
                                        <span>{{ $tournament->specific_days }}</span>
                                    @endif
                                    @if ($tournament->location)
                                        <span class="inline-flex items-center gap-1.5">&#9906; {{ $tournament->location }}</span>
                                    @endif
                                    @if ($tournament->time)
                                        <span class="inline-flex items-center gap-1.5">
                                            <img src="{{ asset('images/welcome/clock-icon.svg') }}" alt="" class="size-4">
                                            {{ $tournament->time }}
                                        </span>
                                    @endif
                                </div>

                                <a href="{{ Route::has('tournaments.register') ? route('tournaments.register') : route('register') }}" wire:navigate
                                   class="mt-2 self-end rounded-full bg-emerald-900 px-8 py-1.5 text-xs font-bold text-white transition hover:bg-emerald-800">
                                    Register
                                </a>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- ============ MATCH ID PROGRESS CHECKER ============ --}}
        <section class="w-full bg-emerald-700/60 px-6 py-12 sm:px-10 sm:py-16 lg:px-16">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-10 lg:flex-row lg:gap-40">
                <div class="flex max-w-sm flex-col gap-3 text-center lg:text-left">
                    <h2 class="text-3xl leading-tight font-bold text-white sm:text-5xl">
                        Check Tournament Application Progress
                    </h2>
                    <p class="text-xs leading-4 text-white">
                        Check your application progress through entering your match ID
                    </p>
                </div>

                {{-- TODO: convert to a Volt component (livewire/application-lookup) for live validation --}}
                <form action="{{ Route::has('applications.lookup') ? route('applications.lookup') : '#' }}" method="GET" class="flex flex-col gap-1.5">
                    <label for="match_id" class="text-sm text-white">Enter Match ID</label>
                    <input id="match_id" name="match_id" type="text" placeholder="Match ID"
                           class="h-11 w-60 rounded-[10px] bg-white px-2.5 text-sm text-black placeholder:text-black/30 focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    <button type="submit"
                            class="mt-2 self-start rounded-[36px] bg-white px-9 py-1.5 text-sm font-bold text-teal-700 transition hover:bg-slate-100">
                        Search
                    </button>
                </form>
            </div>
        </section>

        {{-- ============ UMPIRE RECRUITMENT ============ --}}
        <section class="w-full bg-white px-6 py-8">
            <div class="mx-auto flex max-w-3xl flex-col items-center gap-6 sm:flex-row sm:gap-10">
                <div class="relative h-24 w-28 shrink-0">
                    <img src="{{ asset('images/welcome/racket-2.svg') }}" alt=""
                         class="absolute top-0 left-0 h-16 w-16 rotate-[19.31deg]" aria-hidden="true">
                    <img src="{{ asset('images/welcome/racket-1.svg') }}" alt=""
                         class="absolute right-0 bottom-0 h-12 w-20 rotate-[159.1deg]" aria-hidden="true">
                </div>

                <div class="flex-1 text-center sm:text-left">
                    <h2 class="text-base font-bold text-black">Apply as an Umpire</h2>
                    <p class="mt-1 text-xs text-black">Simply register through creating new account!</p>
                </div>

                <a href="{{ route('register') }}" wire:navigate
                   class="shrink-0 rounded-full bg-emerald-900 px-8 py-2.5 text-xs font-bold text-white transition hover:bg-emerald-800">
                    Register Now!
                </a>
            </div>
        </section>

        {{-- ============ FOOTER ============ --}}
        <footer class="w-full bg-teal-950 px-6 py-16 sm:px-10">
            <div class="mx-auto flex max-w-5xl flex-wrap items-start justify-between gap-16">
                <div class="flex max-w-sm flex-col gap-1.5">
                    <p class="text-5xl font-bold text-white">NetPlay</p>
                    <p class="mt-2 text-base leading-6 text-white">
                        Badminton tournament management, simplified. From registration to real-time scoring.
                    </p>
                </div>

                <div class="flex flex-col gap-6">
                    <h4 class="text-3xl font-bold text-white">Contact</h4>
                    <address class="text-base leading-6 text-white not-italic">
                        Ozamiz City, Philippines<br>
                        netplay@gmail.com
                    </address>
                </div>
            </div>

            <div class="mx-auto mt-16 max-w-5xl border-t border-white/70 pt-8">
                <p class="text-center text-base text-white">
                    &copy;2026 NETPLAY. Capstone Project by Gwen D. Aninao, Romeo S. Jagonia Jr., and Jhon Rico C. Tolosa.
                    Adviser: Mr. Honimar C. Panganiban, MSIT.
                </p>
            </div>
        </footer>
        </div>

        <script>
            (() => {
                const nav = document.getElementById('site-nav');
                const syncNav = () => nav.classList.toggle('bg-emerald-950/95', window.scrollY > 24);
                syncNav();
                window.addEventListener('scroll', syncNav, { passive: true });

                const root = document.getElementById('annual-carousel');
                const track = root.querySelector('[data-track]');
                const originals = [...track.children];

                // Seamless loop: render a clone of the set before and after the real
                // one, then silently jump back to the real set whenever scrolling settles.
                const makeClones = () => originals.map((card) => {
                    const clone = card.cloneNode(true);
                    clone.setAttribute('aria-hidden', 'true');
                    return clone;
                });
                track.prepend(...makeClones());
                track.append(...makeClones());

                const count = originals.length;
                const pad = () => parseFloat(getComputedStyle(track).paddingLeft);
                const step = () => originals[1].offsetLeft - originals[0].offsetLeft;
                const homeLeft = () => originals[0].offsetLeft - pad();
                const jump = (left) => {
                    track.style.scrollBehavior = 'auto';
                    track.style.scrollSnapType = 'none';
                    track.scrollLeft = left;
                    setTimeout(() => {
                        track.style.scrollBehavior = '';
                        track.style.scrollSnapType = '';
                    }, 50);
                };
                const normalize = () => {
                    const home = homeLeft();
                    const setWidth = step() * count;
                    if (track.scrollLeft < home - 1) {
                        jump(track.scrollLeft + setWidth);
                    } else if (track.scrollLeft >= home + setWidth - 1) {
                        jump(track.scrollLeft - setWidth);
                    } else {
                        track.style.scrollSnapType = '';
                    }
                };

                jump(homeLeft());
                window.addEventListener('resize', () => jump(homeLeft()));

                let settle;
                track.addEventListener('scroll', () => {
                    clearTimeout(settle);
                    settle = setTimeout(normalize, 120);
                }, { passive: true });

                const goBy = (direction) => {
                    const index = Math.round((track.scrollLeft - homeLeft()) / step()) + direction;
                    // Snap fights smooth programmatic scrolling; it is restored once scrolling settles.
                    track.style.scrollSnapType = 'none';
                    track.scrollTo({ left: homeLeft() + index * step() });
                };
                const next = () => goBy(1);
                const prev = () => goBy(-1);
                root.querySelector('[data-next]').addEventListener('click', next);
                root.querySelector('[data-prev]').addEventListener('click', prev);

                let timer = setInterval(next, 4000);
                const pause = () => clearInterval(timer);
                const resume = () => { clearInterval(timer); timer = setInterval(next, 4000); };
                root.addEventListener('mouseenter', pause);
                root.addEventListener('mouseleave', resume);
                root.addEventListener('touchstart', pause, { passive: true });
                root.addEventListener('touchend', resume, { passive: true });
            })();
        </script>
    </body>
</html>

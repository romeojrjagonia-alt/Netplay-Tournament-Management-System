<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'NetPlay — Play. Compete. Excel'])
    </head>
    <body class="bg-white font-sans antialiased">
        {{-- ============================================================
             NetPlay Landing Page
             Adapted from Figma (node 138-115).
             - Repeated cards are driven by @php arrays below — swap for
               real Eloquent queries (Tournament::upcoming(), Player::featured())
               once those models exist.
             - Icons are inline SVG since the Figma export only had
               placeholder shapes behind the calendar/clock glyphs.
             - The "Check Application Progress" search is a plain GET
               form for now; say the word and I'll turn it into a Volt
               component (`resources/views/livewire/application-lookup.blade.php`)
               so it can validate/search live, matching the rest of the app.
        ============================================================ --}}

        @php
            // TODO: replace with Tournament::upcoming()->take(2)->get()
            $featuredTournaments = [
                [
                    'name' => 'Inato Tournament',
                    'blurb' => 'Paragraph. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
                    'dates' => 'June 7 - 10, 2026',
                    'days' => 'SUN, MON, SAT',
                    'time' => '10:00 AM - 5:00 PM',
                    'image' => 'https://placehold.co/364x203',
                ],
                [
                    'name' => 'Inato Tournament',
                    'blurb' => 'Paragraph. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
                    'dates' => 'June 7 - 10, 2026',
                    'days' => 'SUN, MON, SAT',
                    'time' => '10:00 AM - 5:00 PM',
                    'image' => 'https://placehold.co/364x203',
                ],
            ];

            // TODO: replace with Tournament::annual()->get()
            $annualTournaments = array_fill(0, 4, [
                'name' => 'Inato Tournament',
                'blurb' => 'Paragraph. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
                'image' => 'https://placehold.co/240x147',
            ]);

            // TODO: replace with Player::featured()->take(3)->get()
            $featuredPlayers = array_fill(0, 3, [
                'name' => 'Juan Dela Cruz',
                'photo' => 'https://placehold.co/272x336',
                'awards' => ['Award 1', 'Award 2', 'Award 3'],
            ]);
        @endphp

        {{-- ============ HERO ============ --}}
        <section class="relative w-full overflow-hidden bg-gradient-to-b from-green-950 from-[22%] via-emerald-900 via-[66%] to-teal-700">

            {{-- Navbar --}}
            <nav class="flex w-full items-center justify-between px-6 py-3 sm:px-16 lg:px-40">
                <a href="{{ route('home') }}" wire:navigate class="text-3xl font-bold text-white">NetPlay</a>

                <ul class="hidden items-center gap-10 text-sm text-white md:flex">
                    <li><a href="{{ route('home') }}" wire:navigate class="transition hover:text-lime-300">Home Page</a></li>
                    @if (Route::has('tournaments.index'))
                        <li><a href="{{ route('tournaments.index') }}" wire:navigate class="transition hover:text-lime-300">Tournaments</a></li>
                    @endif
                    @if (Route::has('players.index'))
                        <li><a href="{{ route('players.index') }}" wire:navigate class="transition hover:text-lime-300">Featured Players</a></li>
                    @endif
                </ul>

                <div class="flex items-center gap-3 sm:gap-5">
                    <a href="{{ route('login') }}" wire:navigate
                       class="flex h-9 items-center justify-center rounded-[36px] px-5 text-sm font-bold text-white transition hover:bg-white/10 sm:px-9">
                        Sign In
                    </a>
                    <a href="{{ route('register') }}" wire:navigate
                       class="flex h-9 items-center justify-center rounded-[36px] bg-white px-5 text-sm font-bold text-emerald-600 transition hover:bg-slate-100 sm:px-9">
                        Sign Up
                    </a>
                </div>
            </nav>

            {{-- Hero copy --}}
            <div class="mx-auto flex max-w-[760px] flex-col items-center gap-3.5 px-6 pt-24 pb-40 text-center">
                <h1 class="text-5xl leading-tight font-bold text-slate-500 sm:text-7xl sm:leading-[72px]">
                    Play. Compete. Excel
                </h1>
                <p class="mx-auto max-w-[607px] text-lg leading-9 text-white">
                    Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                </p>
                <a href="{{ route('register') }}" wire:navigate
                   class="flex h-14 w-56 items-center justify-center rounded-3xl bg-white text-lg font-bold text-teal-700 transition hover:bg-slate-100">
                    REGISTER NOW!
                </a>
            </div>

            {{-- Umpire / referee recruitment card --}}
            <div class="relative mx-auto mb-[-96px] flex w-[calc(100%-3rem)] max-w-6xl flex-col items-center gap-6 rounded-[38px] bg-white
                        px-8 py-8 shadow-[0px_10px_4.7px_0px_rgba(0,0,0,0.25)] sm:flex-row sm:gap-16 sm:px-20 sm:py-4 lg:w-[calc(100%-181px)]">
                <div class="relative h-32 w-40 shrink-0">
                    <div class="absolute top-[93px] left-[166px] h-14 w-24 origin-top-left rotate-[159deg] bg-lime-300"></div>
                    <div class="absolute top-0 left-[25px] h-20 w-20 origin-top-left rotate-[19.41deg] bg-emerald-600"></div>
                </div>

                <div class="flex-1 text-center sm:text-left">
                    <h2 class="text-xl font-bold text-black">Umpire / Referee</h2>
                    <p class="mt-2 max-w-md text-base leading-5 text-black">
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                    </p>
                </div>

                <a href="{{ Route::has('officials.apply') ? route('officials.apply') : route('register') }}" wire:navigate
                   class="shrink-0 rounded-[20px] bg-emerald-900 px-16 py-3.5 text-base font-bold text-white transition hover:bg-emerald-800">
                    Apply Now!
                </a>
            </div>

            <div class="h-24"></div>
        </section>

        {{-- ============ TOURNAMENTS ============ --}}
        <section class="w-full bg-teal-700 px-6 pt-24 pb-16 sm:px-24">
            <div class="mx-auto flex max-w-5xl flex-col items-center gap-12">
                <div class="w-96 border-t-2 border-white"></div>

                <h2 class="rounded-2xl bg-white px-11 py-3 text-3xl font-bold text-emerald-600">
                    TOURNAMENTS
                </h2>

                <div class="flex w-full flex-col gap-9">
                    @foreach ($featuredTournaments as $tournament)
                        <article class="flex flex-col overflow-hidden rounded-[20px] bg-white shadow-[0px_4px_10px_0px_rgba(0,0,0,0.25)] outline outline-1 outline-white sm:flex-row">
                            <img src="{{ $tournament['image'] }}"
                                 alt="{{ $tournament['name'] }}"
                                 class="h-52 w-full object-cover sm:w-96 sm:rounded-tl-2xl sm:rounded-bl-2xl">

                            <div class="flex flex-1 flex-col gap-1.5 p-4">
                                <h3 class="text-lg font-bold text-black">{{ $tournament['name'] }}</h3>
                                <p class="max-w-96 text-xs leading-5 text-black">{{ $tournament['blurb'] }}</p>

                                <div class="flex flex-wrap items-center gap-2.5 text-xs font-bold text-black">
                                    <span class="inline-flex items-center gap-2">
                                        <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <rect x="2.5" y="3.5" width="15" height="14" rx="2"/>
                                            <path d="M2.5 7.5h15M6.5 2v3M13.5 2v3"/>
                                        </svg>
                                        {{ $tournament['dates'] }}
                                    </span>
                                    <span>{{ $tournament['days'] }}</span>
                                </div>

                                <div class="flex items-end gap-2.5 pb-1.5 text-xs font-bold text-black">
                                    <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <circle cx="10" cy="10" r="7.5"/>
                                        <path d="M10 6v4l2.5 2.5"/>
                                    </svg>
                                    {{ $tournament['time'] }}
                                </div>

                                <a href="{{ Route::has('tournaments.register') ? route('tournaments.register') : route('register') }}" wire:navigate
                                   class="mt-2 inline-block self-start rounded-[20px] bg-emerald-900 px-16 py-3.5 text-base font-bold text-white transition hover:bg-emerald-800">
                                    Register
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ============ MATCH ID PROGRESS CHECKER ============ --}}
        <section class="w-full bg-teal-700 px-6 py-12 sm:px-44">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-10 lg:flex-row lg:gap-40">
                <div class="flex max-w-sm flex-col gap-5 text-center lg:text-left">
                    <h2 class="text-4xl leading-tight font-bold text-white [text-shadow:0px_4px_4px_rgba(0,0,0,0.25)] sm:text-5xl">
                        Check Tournament Application Progress
                    </h2>
                    <p class="max-w-60 text-xs leading-4 text-white">
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

        {{-- ============ ANNUAL TOURNAMENTS ============ --}}
        <section class="w-full bg-gradient-to-b from-teal-700 via-slate-400 via-[68%] to-white px-6 pt-16 pb-20 sm:px-24">
            <div class="mx-auto flex max-w-5xl flex-col items-center gap-14">
                <div class="flex h-16 w-full items-center rounded-lg bg-white pl-8 sm:pl-20">
                    <h2 class="text-2xl font-bold text-emerald-600">Annual Tournaments</h2>
                </div>

                <div class="flex w-full flex-col gap-4">
                    @foreach ($annualTournaments as $tournament)
                        <article class="flex flex-col items-start gap-4 rounded-[10px] bg-teal-950/20 p-5 outline outline-1 outline-white sm:flex-row">
                            <img src="{{ $tournament['image'] }}"
                                 alt="{{ $tournament['name'] }}"
                                 class="h-36 w-full rounded object-cover sm:w-60">
                            <div class="flex flex-1 flex-col gap-2.5 py-2.5">
                                <h3 class="text-lg font-bold text-white">{{ $tournament['name'] }}</h3>
                                <p class="text-xs leading-5 text-white">{{ $tournament['blurb'] }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>

                {{-- Pagination dots — wire to real pagination state --}}
                <div class="flex items-center gap-2">
                    <span class="h-1.5 w-11 rounded-full bg-slate-500"></span>
                    <span class="h-1.5 w-2.5 rounded-full bg-zinc-300"></span>
                    <span class="h-1.5 w-2.5 rounded-full bg-zinc-300"></span>
                    <span class="h-1.5 w-2.5 rounded-full bg-zinc-300"></span>
                </div>
            </div>
        </section>

        {{-- ============ FEATURED PLAYERS ============ --}}
        <section class="w-full bg-white px-6 pt-14 pb-24 sm:px-44">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-emerald-600">FEATURED PLAYERS</h2>
                <p class="text-3xl text-emerald-600">Badminton</p>
            </div>

            <div class="mt-12 flex flex-wrap justify-center gap-12 lg:gap-24">
                @foreach ($featuredPlayers as $player)
                    <article class="relative flex w-80 flex-col items-center gap-2.5">
                        <div class="flex h-12 items-center justify-center rounded-[35px] bg-white">
                            <h3 class="text-2xl font-bold text-emerald-900">{{ $player['name'] }}</h3>
                        </div>

                        <div class="relative w-full">
                            <div class="h-[527px] w-full rounded-[20px] border border-teal-700 bg-white shadow-[0px_4px_4px_0px_rgba(0,0,0,0.25)]"></div>

                            <div class="absolute inset-x-0 top-[15px] flex flex-col items-center gap-4 px-4">
                                <img src="{{ $player['photo'] }}"
                                     alt="{{ $player['name'] }}"
                                     class="h-80 w-full rounded-2xl border-2 border-teal-700 object-cover">
                                <div class="text-center text-xs leading-8 text-black">
                                    @foreach ($player['awards'] as $award)
                                        {{ $award }}<br>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- ============ FOOTER ============ --}}
        <footer class="w-full bg-teal-950 px-6 py-16 sm:px-40">
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
    </body>
</html>
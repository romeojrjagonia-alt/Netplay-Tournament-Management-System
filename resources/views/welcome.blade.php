<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NetPlay — Play. Compete. Excel</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-['Inter'] antialiased">

@php
    $tournaments = [
        [
            'name' => 'Inato Tournament',
            'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
            'dates' => 'June 7- 10, 2026',
            'days' => 'SUN, MON, SAT',
            'time' => '10:00 AM - 5:00 PM',
            'image' => 'https://placehold.co/364x203',
        ],
        [
            'name' => 'Inato Tournament',
            'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
            'dates' => 'June 7- 10, 2026',
            'days' => 'SUN, MON, SAT',
            'time' => '10:00 AM - 5:00 PM',
            'image' => 'https://placehold.co/364x203',
        ],
    ];

    $annualTournaments = collect(range(1, 4))->map(fn () => [
        'name' => 'Inato Tournament',
        'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
        'image' => 'https://placehold.co/240x147',
    ]);

    $featuredPlayers = collect(range(1, 3))->map(fn () => [
        'name' => 'Juan Dela Cruz',
        'awards' => ['Award 1', 'Award 2', 'Award 3'],
        'image' => 'https://placehold.co/272x336',
    ]);
@endphp

<div class="w-[1440px] mx-auto flex flex-col justify-start items-start overflow-hidden">

    <div class="self-stretch h-[973px] relative bg-linear-to-b from-green-950 from-22% via-emerald-900 via-66% to-teal-700">
        <div class="w-[1603.35px] h-[686.63px] left-[-101.08px] top-[-0.84px] absolute border border-green-950"></div>

        <div class="w-[760px] left-[166px] top-[301.73px] absolute flex flex-col justify-start items-start gap-3.5">
            <div class="self-stretch flex justify-center items-center gap-2.5">
                <div class="flex-1 h-24 text-center text-slate-500 text-7xl font-bold leading-[72px]">Play.Compete.Excel</div>
            </div>
            <div class="flex justify-center items-center gap-2.5">
                <div class="w-[606.82px] text-white text-lg font-normal leading-9">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</div>
            </div>
            <div class="flex justify-start items-start">
                <a href="#" class="w-56 h-14 px-9 bg-white rounded-3xl flex justify-center items-center gap-2.5 overflow-hidden">
                    <span class="text-center text-teal-700 text-lg font-bold leading-4">REGISTER NOW!</span>
                </a>
            </div>
        </div>

        <div class="w-[1440px] px-40 py-3 left-0 top-0 absolute flex flex-col justify-start items-start">
            <div class="w-32 p-2.5 flex flex-col justify-start items-start gap-2.5">
                <div class="self-stretch h-9 relative">
                    <a href="#" class="left-0 top-0 absolute text-center text-white text-3xl font-bold leading-9">NetPlay</a>
                </div>
            </div>

            <nav class="w-96 h-14 relative">
                <a href="#" class="w-24 h-14 left-0 top-0 absolute">
                    <span class="w-24 left-0 top-0 absolute text-center text-white text-sm font-normal leading-[55px]">Home Page</span>
                </a>
                <a href="#" class="w-28 h-14 left-[121.20px] top-0 absolute">
                    <span class="w-28 left-0 top-0 absolute text-center text-white text-sm font-normal leading-[55px]">Tournaments</span>
                </a>
                <a href="#" class="w-32 h-14 left-[250.81px] top-0 absolute">
                    <span class="w-32 left-0 top-0 absolute text-center text-white text-sm font-normal leading-[55px]">Featured Players</span>
                </a>
            </nav>

            <div class="w-72 p-2.5 flex flex-col justify-start items-start gap-2.5">
                <div class="self-stretch flex justify-start items-center gap-5">
                    <a href="#" class="flex justify-start items-start">
                        <span class="h-9 px-9 bg-white/0 rounded-[36px] flex justify-center items-center gap-2.5 overflow-hidden text-center text-white text-sm font-bold leading-4">Sign In</span>
                    </a>
                    <a href="#" class="flex justify-start items-start">
                        <span class="h-9 px-9 bg-white rounded-[36px] flex justify-center items-center gap-2.5 overflow-hidden text-center text-emerald-600 text-sm font-bold leading-4">Sign Up</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="h-48 px-20 py-4 left-[90.50px] top-[761px] absolute bg-white rounded-[38px] shadow-[0px_10px_4.699999809265137px_0px_rgba(0,0,0,0.25)] flex justify-start items-center gap-40">
            <div class="w-48 p-2.5 flex flex-col justify-start items-start gap-2.5">
                <div class="w-40 h-32 relative">
                    <div class="w-24 h-14 left-[166px] top-[93.22px] absolute origin-top-left rotate-[159deg]">
                        <div class="w-28 h-20 left-0 top-0 absolute bg-lime-300"></div>
                    </div>
                    <div class="size-20 left-[24.98px] top-0 absolute origin-top-left rotate-[19.41deg] bg-emerald-600"></div>
                </div>
            </div>
            <div class="w-96 flex flex-col justify-start items-start">
                <div class="h-8 p-2.5 flex justify-center items-center gap-2.5">
                    <span class="text-black text-xl font-bold leading-[72px]">Umpire/ Referee</span>
                </div>
                <div class="w-96 p-2.5 flex justify-center items-center gap-2.5">
                    <span class="w-80 text-black text-base font-normal leading-5">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</span>
                </div>
            </div>
            <div class="w-56 p-2.5 flex flex-col justify-start items-start gap-2.5">
                <a href="#" class="flex justify-start items-start">
                    <span class="px-16 py-3.5 bg-emerald-900 rounded-[20px] flex justify-center items-center gap-2.5 text-center text-white text-base font-bold leading-4">Apply Now!</span>
                </a>
            </div>
        </div>
    </div>

    <div class="self-stretch h-[822px] relative bg-teal-700 overflow-hidden">
        <div class="w-[1440px] px-24 left-0 top-[50px] absolute flex flex-col justify-start items-center gap-12">
            <div class="w-96 h-0 outline outline-2 outline-offset-[-1px] outline-white"></div>
            <div class="w-96 h-0 outline outline-2 outline-offset-[-1px] outline-white"></div>

            <div class="px-11 bg-white rounded-2xl flex justify-center items-center gap-2.5">
                <span class="text-center text-emerald-600 text-3xl font-bold leading-[60px]">TOURNAMENTS</span>
            </div>

            <div class="self-stretch flex flex-col justify-start items-start gap-9">
                @foreach ($tournaments as $tournament)
                    <div class="self-stretch bg-white rounded-[20px] shadow-[0px_4px_10px_0px_rgba(0,0,0,0.25)] outline outline-1 outline-offset-[-1px] outline-white flex items-stretch">
                        <img class="w-96 h-auto object-cover rounded-tl-2xl rounded-bl-2xl" src="{{ $tournament['image'] }}" alt="{{ $tournament['name'] }}">
                        <div class="flex-1 p-6 flex flex-col justify-center items-start gap-[5px]">
                            <div class="text-black text-lg font-bold leading-[72px]">{{ $tournament['name'] }}</div>
                            <div class="text-black text-xs font-normal leading-5">{{ $tournament['description'] }}</div>
                            <div class="flex justify-start items-center gap-6">
                                <div class="flex justify-start items-center gap-2.5">
                                    <div class="size-6 relative overflow-hidden">
                                        <div class="size-5 left-[3.13px] top-[2px] absolute bg-black"></div>
                                    </div>
                                    <div class="text-justify text-black text-xs font-bold leading-10">{{ $tournament['dates'] }}</div>
                                </div>
                                <div class="text-justify text-black text-xs font-bold leading-10">{{ $tournament['days'] }}</div>
                            </div>
                            <div class="pb-1.5 flex justify-start items-end gap-2.5">
                                <div class="size-5 relative">
                                    <div class="size-5 left-0 top-0 absolute bg-black"></div>
                                </div>
                                <div class="text-justify text-black text-xs font-bold leading-10">{{ $tournament['time'] }}</div>
                            </div>
                            <a href="#" class="mt-2 px-16 py-3.5 bg-emerald-900 rounded-[20px] flex justify-center items-center gap-2.5 text-center text-white text-base font-bold leading-4">Register</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="self-stretch h-72 relative bg-teal-700 overflow-hidden">
        <div class="px-44 py-12 left-[2px] top-[2px] absolute flex justify-start items-center gap-40">
            <div class="w-80 flex flex-col justify-start items-start gap-5">
                <div class="w-96 h-32 text-white text-5xl font-bold leading-10 [text-shadow:_0px_4px_4px_rgb(0_0_0_/_0.25)]">Check Tournament Application Progress</div>
                <div class="w-60 h-6 text-white text-xs font-normal leading-4">Check your application progress through entering your match ID</div>
            </div>
            <form action="#" method="GET" class="h-16 px-24 flex flex-col justify-start items-start">
                <div class="w-60 flex flex-col justify-start items-start gap-1.5">
                    <label for="match_id" class="self-stretch h-3 text-white text-sm font-normal leading-4">Enter Match ID</label>
                    <input type="text" name="match_id" id="match_id" placeholder="Match ID" class="self-stretch h-7 p-2.5 bg-white rounded-[10px] flex justify-center items-center gap-2.5 text-black text-sm font-normal leading-4 placeholder:opacity-30">
                </div>
                <button type="submit" class="h-7 px-9 bg-white rounded-[36px] flex justify-center items-center gap-2.5 overflow-hidden text-center text-teal-700 text-sm font-bold leading-4">Search</button>
            </form>
        </div>
    </div>

    <div class="self-stretch h-[693px] relative bg-linear-to-b from-teal-700 via-slate-400 via-68% to-white overflow-hidden">
        <div class="w-[1440px] left-0 top-[73.22px] absolute flex flex-col justify-start items-center gap-14">
            <div class="w-[1220px] h-16 pl-20 pr-96 bg-white rounded-lg flex flex-col justify-start items-start">
                <div class="w-72 h-16 text-emerald-600 text-2xl font-bold leading-[72px]">Annual Tournaments</div>
            </div>

            <div class="w-[1209.03px] flex flex-col justify-start items-start">
                @foreach ($annualTournaments as $tournament)
                    <div class="self-stretch p-5 bg-teal-950/20 rounded-[10px] outline outline-1 outline-offset-[-1px] outline-white flex justify-start items-start gap-4">
                        <img class="w-60 h-36" src="{{ $tournament['image'] }}" alt="{{ $tournament['name'] }}">
                        <div class="w-72 self-stretch py-2.5 flex flex-col justify-start items-start gap-2.5">
                            <div class="self-stretch text-white text-lg font-bold leading-10">{{ $tournament['name'] }}</div>
                            <div class="self-stretch text-white text-xs font-normal leading-5">{{ $tournament['description'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="w-36 h-1.5 relative">
                <div class="w-11 h-1.5 left-0 top-0 absolute bg-slate-500"></div>
                <div class="w-2.5 h-1.5 left-[59.70px] top-0 absolute bg-zinc-300"></div>
                <div class="w-2.5 h-1.5 left-[86.22px] top-0 absolute bg-zinc-300"></div>
                <div class="w-2.5 h-1.5 left-[112.73px] top-0 absolute bg-zinc-300"></div>
                <div class="w-2.5 h-1.5 left-[139.25px] top-0 absolute bg-zinc-300"></div>
            </div>
        </div>
    </div>

    <div class="self-stretch h-[900px] relative bg-white overflow-hidden">
        <div class="w-[1519.09px] h-[686.63px] left-[-23.62px] top-[215.48px] absolute border border-teal-700/0"></div>
        <div class="w-96 h-12 left-[500.65px] top-[56.19px] absolute text-center text-emerald-600 text-3xl font-bold leading-[72px]">FEATURED PLAYERS</div>
        <div class="w-96 h-12 left-[500.65px] top-[108.19px] absolute text-center text-emerald-600 text-3xl font-normal leading-[72px]">Badminton</div>

        <div class="px-44 left-0 top-[192px] absolute flex justify-start items-center gap-24">
            @foreach ($featuredPlayers as $player)
                <div class="w-80 relative flex flex-col justify-start items-start gap-2.5">
                    <div class="self-stretch h-12 py-2.5 bg-white rounded-[35px] flex flex-col justify-start items-start">
                        <div class="w-64 h-10 text-center text-emerald-900 text-2xl font-bold leading-[72px]">{{ $player['name'] }}</div>
                    </div>
                    <div class="self-stretch h-[526.95px] bg-white rounded-[20px] shadow-[0px_4px_4px_0px_rgba(0,0,0,0.25)] border border-teal-700"></div>
                    <div class="w-64 left-[17px] top-[82.80px] absolute flex flex-col justify-start items-end gap-4">
                        <img class="self-stretch h-80 rounded-2xl border-2 border-teal-700" src="{{ $player['image'] }}" alt="{{ $player['name'] }}">
                        <div class="self-stretch p-2.5 flex flex-col justify-center items-center gap-2.5">
                            <div class="self-stretch text-black text-xs font-normal leading-8">
                                @foreach ($player['awards'] as $award)
                                    {{ $award }}@if (!$loop->last)<br>@endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <footer class="self-stretch h-[468px] relative bg-teal-950 overflow-hidden">
        <div class="w-[1444px] px-40 py-11 left-[-20px] top-[324px] absolute flex flex-col justify-start items-start">
            <div class="p-2.5 flex flex-col justify-end items-center gap-2.5">
                <div class="self-stretch h-0 outline outline-1 outline-offset-[-0.50px] outline-white"></div>
            </div>
            <div class="text-white text-base font-normal leading-6">©{{ now()->year }} NETPLAY. Capstone Project by Gwen D. Aninao, Romeo S. Jagonia Jr., and Jhon Rico C. Tolosa. Adviser: Mr. Honimar C. Panganiban, MSIT.</div>
        </div>

        <div class="w-[1079px] left-[163px] top-[101px] absolute flex justify-start items-center gap-96">
            <div class="w-96 flex flex-col justify-start items-start gap-1.5">
                <div class="w-48 p-2.5 shadow-[0px_2px_4px_0px_rgba(0,0,0,0.25)] flex flex-col justify-start items-start gap-2.5">
                    <div class="self-stretch h-9 relative">
                        <span class="left-[2px] top-0 absolute text-center text-white text-5xl font-bold leading-9">NetPlay</span>
                    </div>
                </div>
                <div class="p-2.5 flex justify-center items-center gap-2.5">
                    <div class="w-96 text-white text-base font-normal leading-6">Badminton tournament management, simplified. From registration to real-time scoring.</div>
                </div>
            </div>
            <div class="w-48 flex flex-col justify-start items-start gap-6">
                <div class="text-white text-3xl font-bold leading-6">Contact</div>
                <div class="h-14 text-white text-base font-normal leading-6">Ozamiz City, Philippines <br> netplay@gmail.com</div>
            </div>
        </div>
    </footer>

</div>

</body>
</html>

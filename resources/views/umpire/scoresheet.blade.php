<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Match {{ $match->id }} — Score Sheet</title>
        @vite(['resources/css/app.css'])
        <style>
            @page { size: A4 landscape; margin: 10mm; }

            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

            @media print {
                .no-print { display: none !important; }
                body { background: white !important; }
            }
        </style>
    </head>
    <body class="min-h-screen bg-[#f5f5f5] p-6 print:p-0">
        <div class="no-print mx-auto mb-4 flex max-w-6xl justify-end gap-3">
            <button type="button" onclick="window.print()" class="cursor-pointer rounded-[5px] bg-[#0b4742] px-5 py-2 text-xs font-bold text-white">
                Print
            </button>
        </div>

        <div class="mx-auto max-w-6xl rounded-[10px] bg-white p-6 shadow print:max-w-none print:rounded-none print:p-0 print:shadow-none">
            <x-umpire.rally-scoresheet :match="$match" />
        </div>

        @if (request()->boolean('print'))
            <script>window.addEventListener('load', () => window.print());</script>
        @endif
    </body>
</html>

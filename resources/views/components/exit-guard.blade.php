{{-- The close button and the "Discard your registration?" prompt for a modal whose
     content element uses the exitGuard Alpine component (resources/js/exit-guard.js).
     Place it inside that element, which must be `relative`. --}}
<button type="button" x-on:click="request()" aria-label="Close modal"
        class="absolute top-0 right-0 z-10 flex size-8 cursor-pointer items-center justify-center rounded-lg text-zinc-400 transition hover:bg-black/5 hover:text-zinc-800">
    <flux:icon.x-mark class="size-5" />
</button>

<div x-show="confirming" x-cloak x-transition.opacity
     class="absolute inset-0 z-30 flex items-center justify-center rounded-lg bg-white/95 p-6"
     role="alertdialog" aria-labelledby="exit-guard-title">
    <div class="flex max-w-sm flex-col gap-5 text-center text-black">
        <div class="flex flex-col gap-2">
            <h3 id="exit-guard-title" class="text-lg font-bold">Discard your registration?</h3>
            <p class="text-sm text-black/70">Everything you have entered so far will be lost.</p>
        </div>

        <div class="flex justify-center gap-3">
            <button type="button" x-on:click="keepEditing()"
                    class="cursor-pointer rounded-[5px] bg-[#0d5750] px-6 py-2 text-sm text-white transition hover:bg-[#0b4742]">
                Keep editing
            </button>
            <button type="button" x-on:click="discard()"
                    class="cursor-pointer rounded-[5px] border border-[#a3a3a3] px-6 py-2 text-sm text-black transition hover:bg-black/5">
                Discard
            </button>
        </div>
    </div>
</div>

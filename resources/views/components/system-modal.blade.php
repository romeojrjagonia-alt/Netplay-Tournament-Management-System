{{--
    One modal for every message the system shows the user.

    Messages: dispatch `system-message` from Livewire
        $this->dispatch('system-message', message: '…', variant: 'success'|'danger', title: '…');
    Confirmations: dispatch `system-confirm` from Alpine with the action to run
        $dispatch('system-confirm', { title: '…', message: '…', confirmLabel: 'Delete', confirm: () => $wire.delete(1) })
--}}
<div
    x-data="{
        open: false,
        confirming: false,
        variant: 'success',
        title: '',
        message: '',
        confirmLabel: 'Confirm',
        onConfirm: null,
        show(detail, confirming) {
            this.confirming = confirming;
            this.variant = detail.variant || (confirming ? 'confirm' : 'success');
            this.title = detail.title || (confirming ? 'Are you sure?' : (this.variant === 'danger' ? 'Something went wrong' : 'Success'));
            this.message = detail.message || '';
            this.confirmLabel = detail.confirmLabel || 'Confirm';
            this.onConfirm = detail.confirm || null;
            this.open = true;
            this.$nextTick(() => this.$refs.primary?.focus());
        },
        close() {
            this.open = false;
            this.onConfirm = null;
        },
        accept() {
            const action = this.onConfirm;
            this.close();
            if (action) action();
        },
    }"
    x-on:system-message.window="show($event.detail || {}, false)"
    x-on:system-confirm.window="show($event.detail || {}, true)"
    x-on:keydown.escape.window="open && close()"
>
    <template x-if="open">
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="system-modal-title">
            <div class="absolute inset-0 bg-black/70" x-on:click="close()"></div>

            <div class="relative flex w-full max-w-md flex-col gap-4 rounded-[20px] bg-white p-6 shadow-xl">
                <div class="flex items-start gap-3">
                    <span
                        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
                        x-bind:class="{
                            'bg-[#3e948d]': variant === 'success',
                            'bg-[#bd6b6c]': variant === 'danger',
                            'bg-[#b8860b]': variant === 'confirm',
                        }"
                        x-text="variant === 'success' ? '✓' : (variant === 'danger' ? '!' : '?')"
                    ></span>

                    <div class="flex min-w-0 flex-col gap-1">
                        <h2 id="system-modal-title" class="text-base font-bold text-black" x-text="title"></h2>
                        <p class="text-sm break-words text-black/80" x-text="message"></p>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <template x-if="confirming">
                        <button type="button" x-on:click="close()" class="cursor-pointer rounded-[5px] border border-[#a3a3a3] px-4 py-2 text-xs font-medium text-[#6b6b6b]">
                            Cancel
                        </button>
                    </template>

                    <button
                        type="button"
                        x-ref="primary"
                        x-on:click="confirming ? accept() : close()"
                        class="cursor-pointer rounded-[5px] px-4 py-2 text-xs font-medium text-white"
                        x-bind:class="variant === 'danger' ? 'bg-[#bd6b6c]' : 'bg-[#3e948d]'"
                        x-text="confirming ? confirmLabel : 'OK'"
                    ></button>
                </div>
            </div>
        </div>
    </template>
</div>

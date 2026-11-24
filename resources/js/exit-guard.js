/**
 * Alpine component for a registration modal that must not lose someone's
 * progress by accident. Closing it (the X, or Cancel on the first step) asks
 * "Discard your registration?" first, once something has been entered or the
 * form is past its first step. An untouched form, or a finished registration,
 * closes straight away.
 *
 * Mark the modal's content with `x-on:input` / `x-on:change` calling
 * `touch()`, and `x-on:modal-show.window` calling `reset($event)`.
 */
export default (modalName) => ({
    dirty: false,
    confirming: false,

    touch() {
        this.dirty = true;
    },

    reset(event) {
        if (event.detail?.name === modalName) {
            this.dirty = false;
            this.confirming = false;
        }
    },

    hasProgress() {
        const wire = this.$wire;

        // A finished registration has nothing left to lose.
        if (wire.submittedReferenceCode) {
            return false;
        }

        return this.dirty || Number(wire.regStep) > 1;
    },

    request() {
        if (this.hasProgress()) {
            this.confirming = true;

            return;
        }

        this.close();
    },

    keepEditing() {
        this.confirming = false;
    },

    discard() {
        this.close();
    },

    close() {
        this.confirming = false;
        this.dirty = false;
        this.$flux.modal(modalName).close();
    },
});

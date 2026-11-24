import datePicker from './date-picker';
import exitGuard from './exit-guard';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('datePicker', datePicker);
    window.Alpine.data('exitGuard', exitGuard);
});

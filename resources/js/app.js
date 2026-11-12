import datePicker from './date-picker';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('datePicker', datePicker);
});

/**
 * Alpine component behind <x-date-picker>: a popover that drills from a
 * grid of years, to the months of that year, to the days of that month. The
 * value is a Livewire property holding an ISO date ("YYYY-MM-DD"), exactly
 * what a native date input would have bound.
 */

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const pad = (number) => String(number).padStart(2, '0');

const toIso = (year, month, day) => `${year}-${pad(month + 1)}-${pad(day)}`;

const parseIso = (iso) => {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso ?? '');

    return match ? { year: +match[1], month: +match[2] - 1, day: +match[3] } : null;
};

const daysIn = (year, month) => new Date(year, month + 1, 0).getDate();

export default (options) => ({
    open: false,
    dropUp: false,
    view: 'days',
    year: 0,
    month: 0,
    yearPage: 0,
    months: MONTHS,
    weekdays: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],

    get value() {
        return this.$wire.$get(options.model) ?? '';
    },

    set value(next) {
        this.$wire.$set(options.model, next, options.live);
        // So a form watching for edits sees a date being chosen like any other input.
        this.$root.dispatchEvent(new Event('input', { bubbles: true }));
    },

    get selected() {
        return parseIso(this.value);
    },

    get min() {
        return parseIso(options.min);
    },

    get max() {
        return parseIso(options.max);
    },

    get label() {
        const selected = this.selected;

        if (! selected) {
            return '';
        }

        return `${MONTHS[selected.month]} ${selected.day}, ${selected.year}`;
    },

    get title() {
        if (this.view === 'years') {
            return `${this.yearPage}–${this.yearPage + 11}`;
        }

        return this.view === 'months' ? String(this.year) : `${MONTHS[this.month]} ${this.year}`;
    },

    toggle() {
        this.open ? this.close() : this.show();
    },

    show() {
        const now = new Date();
        const selected = this.selected;

        this.year = selected?.year ?? this.clampYear(options.start === 'years' ? now.getFullYear() - 20 : now.getFullYear());
        this.month = selected?.month ?? (options.start === 'years' ? 0 : now.getMonth());
        this.yearPage = Math.floor(this.year / 12) * 12;
        this.view = selected || options.start !== 'years' ? 'days' : 'years';

        const rect = this.$refs.trigger.getBoundingClientRect();
        this.dropUp = window.innerHeight - rect.bottom < 380 && rect.top > 380;

        this.open = true;
    },

    close() {
        this.open = false;
    },

    clampYear(year) {
        if (this.max && year > this.max.year) {
            return this.max.year;
        }

        if (this.min && year < this.min.year) {
            return this.min.year;
        }

        return year;
    },

    up() {
        this.view = this.view === 'days' ? 'months' : 'years';
    },

    previous() {
        if (this.view === 'days') {
            this.month === 0 ? ((this.month = 11), this.year--) : this.month--;
        } else if (this.view === 'months') {
            this.year--;
        } else {
            this.yearPage -= 12;
        }
    },

    next() {
        if (this.view === 'days') {
            this.month === 11 ? ((this.month = 0), this.year++) : this.month++;
        } else if (this.view === 'months') {
            this.year++;
        } else {
            this.yearPage += 12;
        }
    },

    get years() {
        return Array.from({ length: 12 }, (_, index) => this.yearPage + index);
    },

    yearDisabled(year) {
        return Boolean((this.max && year > this.max.year) || (this.min && year < this.min.year));
    },

    monthDisabled(month) {
        const key = this.year * 12 + month;

        return Boolean((this.max && key > this.max.year * 12 + this.max.month) || (this.min && key < this.min.year * 12 + this.min.month));
    },

    dayDisabled(day) {
        const iso = toIso(this.year, this.month, day);

        return Boolean((options.max && iso > options.max) || (options.min && iso < options.min));
    },

    pickYear(year) {
        if (this.yearDisabled(year)) {
            return;
        }

        this.year = year;
        this.view = 'months';
    },

    pickMonth(month) {
        if (this.monthDisabled(month)) {
            return;
        }

        this.month = month;
        this.view = 'days';
    },

    pickDay(day) {
        if (this.dayDisabled(day)) {
            return;
        }

        this.value = toIso(this.year, this.month, day);
        this.close();
    },

    get blanks() {
        return new Date(this.year, this.month, 1).getDay();
    },

    get days() {
        return Array.from({ length: daysIn(this.year, this.month) }, (_, index) => index + 1);
    },

    isSelectedYear(year) {
        return this.selected?.year === year;
    },

    isSelectedMonth(month) {
        return this.selected?.year === this.year && this.selected?.month === month;
    },

    isSelectedDay(day) {
        return this.selected?.year === this.year && this.selected?.month === this.month && this.selected?.day === day;
    },

    isToday(day) {
        const now = new Date();

        return now.getFullYear() === this.year && now.getMonth() === this.month && now.getDate() === day;
    },

    get todayDisabled() {
        const now = new Date();
        const iso = toIso(now.getFullYear(), now.getMonth(), now.getDate());

        return Boolean((options.max && iso > options.max) || (options.min && iso < options.min));
    },

    pickToday() {
        if (this.todayDisabled) {
            return;
        }

        const now = new Date();

        this.value = toIso(now.getFullYear(), now.getMonth(), now.getDate());
        this.close();
    },

    clear() {
        this.value = '';
        this.close();
    },
});

import './bootstrap';
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

const BN_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

/** Display only — never parse these back for calculations. */
window.bnDigits = (value) => String(value).replace(/[0-9]/g, (d) => BN_DIGITS[d]);

window.bnMoney = (amount) => {
    const n = Math.round(Number(amount || 0) * 100) / 100;
    const [int, dec] = Math.abs(n).toFixed(2).split('.');
    const grouped = int.length > 3
        ? int.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + int.slice(-3)
        : int;
    return (n < 0 ? '-' : '') + '৳' + window.bnDigits(grouped + (dec !== '00' ? '.' + dec : ''));
};

Alpine.plugin(focus);
window.Alpine = Alpine;
Alpine.start();

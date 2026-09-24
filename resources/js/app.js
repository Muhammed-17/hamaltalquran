import './bootstrap';
import './confirm-delete.js';
import { initFavorites } from './confirm-favorite';

window.initFavorites = initFavorites;

import Chart from 'chart.js/auto';
import Alpine from 'alpinejs';


window.Alpine = Alpine;
window.Chart = Chart;


Alpine.start();

// ✅ إغلاق أي SweetAlert عالق في كل صفحات الموقع لو رجعت من bfcache بزر الرجوع
window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        window.location.reload();
    }
});
(function () {
    'use strict';

    function pad(value) {
        return value < 10 ? '0' + value : String(value);
    }

    function init(root) {
        var day = root.querySelector('[data-tp-bd="day"]');
        var month = root.querySelector('[data-tp-bd="month"]');
        var year = root.querySelector('[data-tp-bd="year"]');
        var hidden = root.querySelector('input[type="hidden"]');

        if (!day || !month || !year || !hidden) {
            return;
        }

        function syncDays() {
            var m = parseInt(month.value, 10);
            var y = parseInt(year.value, 10);
            var max = 31;

            if (m) {
                // Sin anio elegido se asume bisiesto para permitir el 29 de febrero.
                max = new Date(y || 2000, m, 0).getDate();
            }

            Array.prototype.forEach.call(day.options, function (option) {
                var n = parseInt(option.value, 10);
                option.hidden = !!n && n > max;
                option.disabled = !!n && n > max;
            });

            if (parseInt(day.value, 10) > max) {
                day.value = String(max);
            }
        }

        function syncHidden() {
            var d = parseInt(day.value, 10);
            var m = parseInt(month.value, 10);
            var y = parseInt(year.value, 10);

            hidden.value = d && m && y ? y + '-' + pad(m) + '-' + pad(d) : '';
        }

        function onChange() {
            syncDays();
            syncHidden();
        }

        day.addEventListener('change', onChange);
        month.addEventListener('change', onChange);
        year.addEventListener('change', onChange);
        syncDays();
    }

    document.addEventListener('DOMContentLoaded', function () {
        Array.prototype.forEach.call(document.querySelectorAll('[data-tp-birthdate]'), init);
    });
}());

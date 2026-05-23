(function () {
    if (typeof window === 'undefined' || window.Swal) {
        return;
    }

    window.Swal = {
        fire: function (arg1, arg2, arg3) {
            var options = typeof arg1 === 'object' && arg1 !== null
                ? arg1
                : { title: arg1, text: arg2, icon: arg3 };
            var title = options.title ? String(options.title) : '';
            var text = options.text ? String(options.text) : '';
            var message = [title, text].filter(Boolean).join('\n');

            if (options.showCancelButton) {
                var accepted = window.confirm(message || '¿Continuar?');
                return Promise.resolve({ isConfirmed: accepted, isDismissed: !accepted });
            }

            if (message) {
                window.alert(message);
            }

            return Promise.resolve({ isConfirmed: true });
        }
    };
})();

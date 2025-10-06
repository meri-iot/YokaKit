window._ = require('lodash');

try {
    window.Popper = require('popper.js').default;
    window.$ = window.jQuery = require('jquery');
    require('jquery-ui/ui/widgets/sortable.js');
    window.toastr = require('toastr');
    window.moment = require('moment');
    require('overlayscrollbars');
    require('bootstrap');
    require('../../vendor/almasaeed2010/adminlte/dist/js/adminlte');
    require('datatables.net-bs4');
    require('daterangepicker');
    require('bootstrap-colorpicker');
    require('bootstrap-switch');
    require('chart.js/dist/chart.js');
    require('slick-carousel');
} catch (e) { }

window.moment.locale('ja');

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window.axios = require('axios');

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

/**
 * Next we will register the CSRF Token as a common header with Axios so that
 * all outgoing HTTP requests automatically have it attached. This is just
 * a simple convenience so we don't have to attach every token manually.
 */

let token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
} else {
    console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';

window.Pusher = require('pusher-js');

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
    wsHost: window.location.hostname,
    wsPort: process.env.MIX_PUSHER_PORT,
    forceTLS: false,
    disableStats: true,
    enabledTransports: ['ws'],
    authEndpoint: process.env.MIX_PUSHER_AUTH_URL,
});

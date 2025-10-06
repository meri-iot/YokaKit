const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

// mix.webpackConfig({
//     stats: {
//         children: true,
//     },
// });

mix.setResourceRoot('/yokakit')
    .copy('node_modules/chartjs-adapter-moment/dist/chartjs-adapter-moment.js', 'public/js/chartjs-adapter-moment/chartjs-adapter-moment.js')
    .js([
        'resources/js/app.js',
        'resources/js/indicator.js',
        'resources/js/payload.js',
        'resources/js/production.js'
    ], 'public/js')
    .sass('resources/sass/app.scss', 'public/css')
    .sourceMaps()
    .version();

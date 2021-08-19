const mix = require('laravel-mix');
const path = require('path');
require('laravel-mix-copy-watched');

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

mix.js('resources/js/app.js', 'public/js')
    .vue()
    .webpackConfig({
        output : {chunkFilename: 'js/[name].js?id=[chunkhash]'},
        resolve: {
            alias: {
                '@' : path.resolve('resources/js'),
                ziggy: path.resolve('vendor/tightenco/ziggy/dist')
            },
        },
    })
    .babelConfig({
        plugins: ['@babel/plugin-syntax-dynamic-import'],
    })
  .postCss('resources/css/app.css', 'public/css', [
      require('tailwindcss')
  ])

if (! mix.inProduction()) {
    mix.copyWatched(
        'vendor/seatplus/web/src/resources/js/**/*.{vue,js}',
        'resources/js',
        {base: 'vendor/seatplus/web/src/resources/js'}
    ).copyWatched(
        'vendor/seatplus/api/resources/js/**/*.{vue,js}',
        'resources/js',
        {base: 'vendor/seatplus/api/resources/js'}
    )
        /*.copyWatched(
            'vendor/seatplus/notifications/resources/js/!**!/!*.{vue,js}',
            'resources/js',
            {base: 'vendor/seatplus/notifications/resources/js'}
        )
        .copyWatched(
            'vendor/seatplus/telegram-channel/resources/js/!**!/!*.{vue,js}',
            'resources/js',
            {base: 'vendor/seatplus/telegram-channel/resources/js'}
        )*/

}

if( mix.inProduction()) {
    mix.version();
}

mix.sourceMaps()



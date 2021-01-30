const mix = require('laravel-mix');
const path = require('path');
const tailwindcss = require('tailwindcss');

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
    .sass('resources/sass/app.scss', 'public/css')
    .webpackConfig({
        output : {chunkFilename: 'js/[name].js?id=[chunkhash]'},
        resolve: {
            alias: {
                vue$: 'vue/dist/vue.runtime.esm.js',
                '@' : path.resolve('resources/js'),
            },
        },
    })
    .options({
        processCssUrls: false,
        postCss: [ tailwindcss('./tailwind.config.js') ],
    });

if (! mix.inProduction()) {
    mix.copyDirectoryWatched('packages/seatplus/web/src/resources/js/Pages/AccessControl', 'resources/js/Pages/AccessControl')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Pages/AccessControl/AclTypes', 'resources/js/Pages/AccessControl/AclTypes')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Pages/Auth', 'resources/js/Pages/Auth')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Pages/Configuration', 'resources/js/Pages/Configuration')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Pages/Dashboard', 'resources/js/Pages/Dashboard')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Pages/Character', 'resources/js/Pages/Character')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Pages/Character/Contact', 'resources/js/Pages/Character/Contact')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Pages/Character/Wallet', 'resources/js/Pages/Character/Wallet')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Pages/Corporation', 'resources/js/Pages/Corporation')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Pages/Corporation/Recruitment', 'resources/js/Pages/Corporation/Recruitment')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Pages/Corporation/Compliance', 'resources/js/Pages/Corporation/Compliance')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Shared', 'resources/js/Shared')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Shared/Transitions', 'resources/js/Shared/Transitions')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Shared/Components', 'resources/js/Shared/Components')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Shared/Components/SlideOver', 'resources/js/Shared/Components/SlideOver')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Shared/Components/Wallet/Journal', 'resources/js/Shared/Components/Wallet/Journal')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Shared/Components/Wallet/Transaction', 'resources/js/Shared/Components/Wallet/Transaction')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Shared/Layout', 'resources/js/Shared/Layout')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Shared/Layout/Eve', 'resources/js/Shared/Layout/Eve')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Shared/Layout/Cards', 'resources/js/Shared/Layout/Cards')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Shared/Layout/Cards/Table', 'resources/js/Shared/Layout/Cards/Table')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Shared/Modals', 'resources/js/Shared/Modals')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js/Pages/Configuration/Scopes', 'resources/js/Pages/Configuration/Scopes')
        .copyDirectoryWatched('packages/seatplus/web/src/resources/js', 'resources/js')
}

if( mix.inProduction()) {

}



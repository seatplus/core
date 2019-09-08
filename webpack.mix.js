const mix = require('laravel-mix');
const path = require('path')

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
if (! mix.inProduction()) {
  mix.js('resources/js/app.js', 'public/js')
      .sass('resources/sass/app.scss', 'public/css')
      .copyWatched('packages/seatplus/web/src/resources/js/components/*.{vue}', 'resources/js/components')
      //.copyDirectoryWatched('packages/seatplus/web/src/resources/js/components/', 'resources/js/components')
      .webpackConfig({
        output : {chunkFilename: 'js/[name].js?id=[chunkhash]'},
        resolve: {
          alias: {
            vue$: 'vue/dist/vue.runtime.esm.js',
            '@' : path.resolve('resources/js'),
          },
        },
      })
  //mix.copyDirectory('packages/seatplus/web/src/resources/js/components', './resources/js/components')
}



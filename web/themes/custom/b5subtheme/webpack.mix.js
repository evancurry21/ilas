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

mix.setPublicPath('./');

// Compile SCSS
mix.sass('scss/style.scss', 'css/style.css')
   .options({
     processCssUrls: false
   });

// Compile JavaScript
mix.js('js/scripts.js', 'js/scripts.min.js');

// Copy Bootstrap fonts and other assets if needed
mix.copy('node_modules/bootstrap/dist/js/bootstrap.bundle.min.js', 'js/bootstrap.bundle.min.js');

// Copy Font Awesome
mix.copy('node_modules/@fortawesome/fontawesome-free/webfonts', 'webfonts');
mix.copy('node_modules/@fortawesome/fontawesome-free/css/all.min.css', 'css/fontawesome.min.css');

// Copy Bootstrap Icons
mix.copy('node_modules/bootstrap-icons/font/bootstrap-icons.min.css', 'css/bootstrap-icons.min.css');
mix.copy('node_modules/bootstrap-icons/font/fonts', 'css/fonts');

// Version files in production
if (mix.inProduction()) {
  mix.version();
}

// Only enable source maps in development
if (!mix.inProduction()) {
  mix.sourceMaps();
}

// BrowserSync Configuration
// Only enable in development environment
// To use: Set BROWSERSYNC_PROXY environment variable to your local URL
// Example: BROWSERSYNC_PROXY=https://mysite.ddev.site npm run watch
if (!mix.inProduction() && process.env.BROWSERSYNC_PROXY) {
  mix.browserSync({
    proxy: process.env.BROWSERSYNC_PROXY,
    port: 3000,
    files: [
      'css/**/*.css',
      'js/**/*.js', 
      'templates/**/*.twig',
      'scss/**/*.scss',
      '*.theme',
      '**/*.yml'
    ],
    open: false,
    notify: false,
    reloadDelay: 50,
    injectChanges: true,
    browser: 'default',
    https: {
      rejectUnauthorized: false
    }
  });
}


// For image optimization, run: node optimize-images.js
// This is separate from the build process to avoid webpack conflicts
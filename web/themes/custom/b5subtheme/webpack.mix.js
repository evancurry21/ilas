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

// Copy Font Awesome webfonts
mix.copy('node_modules/@fortawesome/fontawesome-free/webfonts', 'webfonts');

// Version files in production
if (mix.inProduction()) {
  mix.version();
}

// Enable source maps
mix.sourceMaps();

// BrowserSync Configuration
mix.browserSync({
    proxy: 'https://ilas.ddev.site', // Your DDEV site URL
    port: 3000, // Explicitly set port to 3000
    files: [
        'css/**/*.css',
        'js/**/*.js',
        'templates/**/*.twig',
        'scss/**/*.scss',
        '*.theme',
        '**/*.yml'
    ],
    open: false, // Don't auto-open browser
    notify: false, // Disable notifications
    reloadDelay: 50, // Faster reload
    injectChanges: true, // Inject CSS changes without full reload
    browser: 'default', // Use system's default browser
    https: {
        rejectUnauthorized: false // Accept DDEV's self-signed certificate
    }
});
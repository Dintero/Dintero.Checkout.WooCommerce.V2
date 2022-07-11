module.exports = function(grunt) {
    grunt.loadNpmTasks('grunt-wp-i18n');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-move');
    grunt.initConfig({
        makepot: {
            target: {
                options: {
                    domainPath: '/languages',
                    mainFile: 'dintero-checkout-for-woocommerce',
                    potFilename: 'dintero-checkout-for-woocommerce.pot',
                    processPot(pot, options) {
                        // add header options
                        return pot;
                    },
                    type: 'wp-plugin',
                },
            },
        },
        // minify css
        cssmin: {
            target: {
                files: [{
                    expand: true,
                    cwd: './assets/css',
                    src: ['*.css', '!*.min.css'],
                    dest: './assets/css',
                    ext: '.min.css',
                }],
            },
        },
        move: {
            // move the Dintero SDK to assets
            moveJS: {
                options: {
                    ignoreMissing: true,
                },
                src: './node_modules/@dintero/checkout-web-sdk/dist/dintero-checkout-web-sdk.umd.min.js',
                dest: './assets/js/',
            },
        }
    });
};
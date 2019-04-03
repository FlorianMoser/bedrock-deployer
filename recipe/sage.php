<?php

/**
 * Deployer recipes for Roots Sage WordPress themes.
 *
 * Requires these Deployer variables to be set:
 *   theme_path: Path to theme relative release_path
 *   local_root: Absolute path to website root on local host machine
 */

namespace Deployer;

desc( 'Runs composer install on remote server' );
task( 'sage:vendors', function () {
    run( 'cd {{release_path}}/{{theme_path}} && {{bin/composer}} {{composer_options}}' );
} );

desc( 'Compiles the theme locally for production' );
task( 'sage:compile', function () {
    runLocally( "cd {{local_root}}/{{theme_path}} && yarn run build:production" );
} );

desc( 'Updates remote assets with local assets' );
task( 'sage:upload_assets', function () {
    upload( '{{local_root}}/{{theme_path}}/dist', '{{release_path}}/{{theme_path}}' );
} );

desc( 'Builds assets and uploads them on remote server' );
task( 'push:assets', [
    'sage:compile',
    'sage:upload_assets',
] );

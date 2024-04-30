<?php

/**
 * Deployer recipes for Roots Sage WordPress themes.
 *
 * Requires these Deployer variables to be set:
 *   theme_path: Path to theme relative release_path
 *   local_root: Absolute path to website root on local host machine
 */

namespace Deployer;

// Set default path of distribution folder. This is /public with Sage 10,
// and /dist with Sage 9
set('sage/dist_path', '/public');

// Build script used. Will be "build" with Sage 10 or "build:production" with Sage 9
set('sage/build_command', 'build');

desc( 'Runs composer install on remote server' );
task( 'sage:vendors', function () {
    run( 'cd {{release_path}}/{{theme_path}} && {{bin/composer}} {{composer_action}} {{composer_options}}' );
} );

desc( 'Compiles the theme locally for production' );
task( 'sage:compile', function () {
    runLocally( "cd {{local_root}}/{{theme_path}} && yarn run {{sage/build_command}}" );
} );

desc( 'Removes the folder for distributed files on the destination' );
task( 'sage:clear_assets', function () {
    run( 'rm -rf {{release_path}}/{{theme_path}}{{sage/dist_path}}' );
} );

desc( 'Updates remote assets with local assets, but without deleting previous assets on destination' );
task( 'sage:upload_assets_only', function () {
    upload( '{{local_root}}/{{theme_path}}{{sage/dist_path}}', '{{release_path}}/{{theme_path}}' );
} );

desc( 'Updates remote assets with local assets' );
task( 'sage:upload_assets', [
    'sage:clear_assets',
    'sage:upload_assets_only',
] );

desc( 'Builds assets and uploads them on remote server' );
task( 'push:assets', [
    'sage:compile',
    'sage:upload_assets',
] );

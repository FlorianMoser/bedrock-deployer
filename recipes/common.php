<?php

/**
 * Collection of common deployment tasks.
 */

namespace Deployer;

desc('Activate all plugins');
task('activate:plugins', function() {
    run( 'cd {{release_path}} && wp plugin activate --all' );
});


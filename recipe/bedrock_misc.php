<?php

/**
 * Miscellaneous Bedrock tasks.
 */

namespace Deployer;

/*
 * Runs Composer install for Bedrock
 */
desc( 'Installing Bedrock vendors' );
task( 'bedrock:vendors', function () {
    run( 'cd {{release_path}} && {{bin/composer}} {{composer_action}} {{composer_options}}' );
} );

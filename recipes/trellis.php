<?php

/**
 * Usable tasks when working with Trellis on development machine,
 * but deploying to a server, where trellis is not used.
 */

namespace Deployer;

// Remove trellis, so /site directory becomes the web root
desc( 'Removes Trellis (checked in in Git) and moves /site content up to root release path' );
task( 'trellis:remove', function () {
    run( 'mv {{release_path}}/site/* {{release_path}}' );
    run( 'rm -rf {{release_path}}/site' );
    run( 'rm -rf {{release_path}}/trellis' );
} );

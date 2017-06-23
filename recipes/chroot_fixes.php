<?php

/**
 * Deploying on shared hosting can be nearly impossible. These tasks
 * here overwrite some of the default Deployer tasks, to deploy to
 * a chroot shared hosting running Plesk.
 *
 * Load this file after loading the default Deployer recipe.
 *
 * The following problems are fixed for chroot shared hosting:
 *
 * - The shared hosting has only a limited amount of available bash commands.
 *   Some commands used by Deployer must be replaced.
 * - The Apache service has another directory structure than chrooted SSH.
 *
 * Requires these Deployer variables to be set:
 *   chroot_path_prefix: This prefix will be prepended for all Apache paths
 *   chroot_index_file: Path to web-project index file (usually index.php) relative to project root
 *
 * How to deploy to Plesk:
 *
 * 1. Create deploy path in web root, ie /deploy/path
 * 2. Set the path in Deployer using set('deploy_path', '/deploy/path')
 *    (as you would with any deployment)
 * 3. Determine the Apache path prefix. You can do so by placing a
 *    PHP file with "echo $_SERVER['DOCUMENT_ROOT']" in your web root
 *    and calling it from the frontend.
 *    It is usually something in the form of /home/httpd/vhosts/yourdomain.com
 * 4. Set this prefix in Deployer with set('chroot_path_prefix', '/home/httpd/vhosts/yourdomain.com')
 * 5. Tell Plesk the location of the new web root (the "current" symlink).
 *    In Plesk on "Hosting settings" page, set Document root to:
 *    deploy/path/current/web
 */

namespace Deployer;

// Must use absolut paths, otherwise chroot fixes won't work
set( 'use_relative_symlink', false );

/*
 * Set release path.
 *
 * Originally from deployer/recipe/deploy/release.php
 *
 * Reason: Plesk SSH has no "readlink" bash command. Use alternate method.
 *
 * Note: Must use run() function, plain PHP code is otherwise executed locally, not on the server.
 */
set( 'release_path', function () {
    $releaseExists = run( "if [ -h {{deploy_path}}/release ]; then echo 'true'; fi" )->toBool();
    if ( $releaseExists ) {
        $link = run( "ls -l {{deploy_path}}/release | sed -e 's/.* -> //'" )->toString();

        return substr( $link, 0, 1 ) === '/' ? $link : get( 'deploy_path' ) . '/' . $link;
    } else {
        return get( 'current_path' );
    }
} );


/*
 * Set current release path.
 *
 * Originally from deployer/recipe/common.php
 *
 * Reasons:
 *   - Plesk SSH has no "readlink" bash command. Use alternate method.
 *   - current_path will contain chroot prefix path after first deployment. Return path without prefix.
 *
 * Note: Must use run() function, plain PHP code is otherwise executed locally, not on the server.
 */
set( 'current_path', function () {
    $link = run( "ls -l {{deploy_path}}/current | sed -e 's/.* -> //'" )->toString();

    // If current path has chroot prefix, remove it
    if ( substr( $link, 0, strlen( get( 'chroot_path_prefix' ) ) ) == get( 'chroot_path_prefix' ) ) {
        $link = substr( $link, strlen( get( 'chroot_path_prefix' ) ) );
    }

    return substr( $link, 0, 1 ) === '/' ? $link : get( 'deploy_path' ) . '/' . $link;
} );


/*
 * Overwrite default rollback recipe (requires only changes in $releaseDir).
 *
 * Originally from deployer/recipe/deploy/rollback.php
 */
desc( 'Rollback to previous release' );
task( 'rollback', function () {
    $releases = get( 'releases_list' );

    if ( isset( $releases[1] ) ) {
        $releaseDir = "{{chroot_path_prefix}}{{deploy_path}}/releases/{$releases[1]}";

        // Symlink to old release.
        run( "cd {{deploy_path}} && {{bin/symlink}} $releaseDir current" );

        // Remove release
        run( "rm -rf {{deploy_path}}/releases/{$releases[0]}" );

        if ( isVerbose() ) {
            writeln( "Rollback to `{$releases[1]}` release was successful." );
        }
    } else {
        writeln( "<comment>No more releases you can revert to.</comment>" );
    }
} );


/*
 * Overwrite default shared recipe. Required because Plesk bash doesn't have
 * a dirname command available. We also need to fix the symlinks.
 *
 * Originally from deployer/recipe/deploy/shared.php
 */
desc( 'Creating symlinks for shared files and dirs' );
task( 'deploy:shared', function () {
    $sharedPath = "{{deploy_path}}/shared";

    // Validate shared_dir, find duplicates
    foreach ( get( 'shared_dirs' ) as $a ) {
        foreach ( get( 'shared_dirs' ) as $b ) {
            if ( $a !== $b && strpos( rtrim( $a, '/' ) . '/', rtrim( $b, '/' ) . '/' ) === 0 ) {
                throw new Exception( "Can not share same dirs `$a` and `$b`." );
            }
        }
    }

    foreach ( get( 'shared_dirs' ) as $dir ) {
        // Check if shared dir does not exists.
        if ( ! test( "[ -d $sharedPath/$dir ]" ) ) {
            // Create shared dir if it does not exist.
            run( "mkdir -p $sharedPath/$dir" );

            // If release contains shared dir, copy that dir from release to shared.
            if ( test( "[ -d $(echo {{release_path}}/$dir) ]" ) ) {
                run( "cp -rv {{release_path}}/$dir $sharedPath/" . dirname( $dir ) );
            }
        }

        // Remove from source.
        run( "rm -rf {{release_path}}/$dir" );

        // Create path to shared dir in release dir if it does not exist.
        // Symlink will not create the path and will fail otherwise.
        // Plesk fix: use PHP dirname(), as it is not available as bash command
        run( "mkdir -p " . dirname( get( 'release_path' ) . '/$dir' ) );

        // Symlink shared dir to release dir
        // Plesk fix: add chroot prefix for Apache paths
        run( "{{bin/symlink}} {{chroot_path_prefix}}$sharedPath/$dir {{release_path}}/$dir" );
    }

    foreach ( get( 'shared_files' ) as $file ) {
        $dirname = dirname( $file );

        // Create dir of shared file
        run( "mkdir -p $sharedPath/" . $dirname );

        // Check if shared file does not exists in shared.
        // and file exist in release
        if ( ! test( "[ -f $sharedPath/$file ]" ) && test( "[ -f {{release_path}}/$file ]" ) ) {
            // Copy file in shared dir if not present
            run( "cp -rv {{release_path}}/$file $sharedPath/$file" );
        }

        // Remove from source.
        run( "if [ -f $(echo {{release_path}}/$file) ]; then rm -rf {{release_path}}/$file; fi" );

        // Ensure dir is available in release
        run( "if [ ! -d $(echo {{release_path}}/$dirname) ]; then mkdir -p {{release_path}}/$dirname;fi" );

        // Touch shared
        run( "touch $sharedPath/$file" );

        // Symlink shared dir to release dir
        // Plesk fix: add chroot prefix for Apache paths
        run( "{{bin/symlink}} {{chroot_path_prefix}}$sharedPath/$file {{release_path}}/$file" );
    }
} );

/*
 * Task to change release symlink to Plesk Apache path, before it
 * is being changed to current (in deploy:symlink)
 */
task( 'to_chroot_release_path', function () {
    $finalReleasePath = get( 'chroot_path_prefix' ) . get( 'release_path' );
    run( "cd {{deploy_path}} && rm release && {{bin/symlink}} $finalReleasePath release" );
    set( 'release_path', $finalReleasePath );
} );
before( 'deploy:symlink', 'to_chroot_release_path' );

/*
 * On a shared hosting, FPM cannot be restarted. But it seems that FPM
 * resets its cache when the index file (the one that FPM cached) from
 * the previous release is modified (touched) after deployment.
 */
desc( 'Touch index file from previous release to reset FPM cache' );
task( 'reset:cache', function () {
    if ( has( 'previous_release' ) ) {
        if ( test( "[ -f {{previous_release}}/{{chroot_index_file}} ]" ) ) {
            run('touch {{previous_release}}/{{chroot_index_file}}');
        }
    }
} );
after( 'deploy:symlink', 'reset:cache' );

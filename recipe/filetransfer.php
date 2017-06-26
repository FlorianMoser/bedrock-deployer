<?php

/**
 * Provides Deployer tasks to upload and download files
 * from and to server. When not explicitly using the
 * xyz:files-no-bak, a backup of the current files is
 * created, before new files are transferred.
 *
 * Requires these Deployer variables to be set:
 *   sync_dirs: Array of paths, that will be simultaneously updated
 *              with $absoluteLocalPath => $absoluteRemotePath
 *              If a path has a trailing slash, only its content
 *              will be transferred, not the directory itself.
 */

namespace Deployer;

/*
 * Uploads all files (and directories) from local machine to
 * remote server. Overwrites existing files on server with
 * updated local files and uploads new files. Locally deleted
 * files are not deleted on server.
 */
desc( 'Upload sync directories from local to server' );
task( 'push:files-no-bak', function () {

    foreach ( get( 'sync_dirs' ) as $localDir => $serverDir ) {
        upload( $localDir, $serverDir );
    };

} );

/*
 * Downloads all files (and directories) from remote server to
 * local machine. Overwrites existing files on local machine with
 * updated server files and downloads new files. Deleted files
 * on the server are not deleted on local machine.
 */
desc( 'Download sync directories from server to local' );
task( 'pull:files-no-bak', function () {

    foreach ( get( 'sync_dirs' ) as $localDir => $serverDir ) {
        download( $serverDir, $localDir );
    };

} );

desc( 'Create backup from sync directories on server' );
task( 'backup:remote_files', function () {

    foreach ( get( 'sync_dirs' ) as $localDir => $serverDir ) {
        $backupFilename = '_backup_' . date( 'Y-m-d_H-i-s' ) . '.zip';

        // Note: sync_dirs can have a trailing slash (which means, sync only the content of the specified directory)
        if ( substr( $serverDir, - 1 ) == '/' ) {
            // Add everything from synced directory to zip, but exclude previous backups
            run( "cd {$serverDir} && zip -r {$backupFilename} . -x \"_backup_*.zip\"" );
        } else {
            $backupDir = dirname( $serverDir );
            $dir       = basename( $serverDir );
            // Add everything from synced directory to zip, but exclude previous backups
            run( "cd {$backupDir} && zip -r {$backupFilename} {$dir} -x \"_backup_*.zip\"" );
        }
    };

} );

desc( 'Create backup from sync directories on local machine' );
task( 'backup:local_files', function () {

    foreach ( get( 'sync_dirs' ) as $localDir => $serverDir ) {
        $backupFilename = '_backup_' . date( 'Y-m-d_H-i-s' ) . '.zip';

        // Note: sync_dirs can have a trailing slash (which means, sync only the content of the specified directory)
        if ( substr( $localDir, - 1 ) == '/' ) {
            // Add everything from synced directory to zip, but exclude previous backups
            runLocally( "cd {$localDir} && zip -r {$backupFilename} . -x \"_backup_*.zip\"" );
        } else {
            $backupDir = dirname( $localDir );
            $dir       = basename( $localDir );
            // Add everything from synced directory to zip, but exclude previous backups
            runLocally( "cd {$backupDir} && zip -r {$backupFilename} {$dir} -x \"_backup_*.zip\"" );
        }
    };

} );

desc( 'Upload sync directories from local to server after making backup of remote files' );
task( 'push:files', [
    'backup:remote_files',
    'push:files-no-bak',
] );

desc( 'Download sync directories from server to local machine after making backup of local files' );
task( 'pull:files', [
    'backup:local_files',
    'pull:files-no-bak',
] );

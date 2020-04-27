<?php

/**
 * Deployer recipes to push Bedrock database from local development
 * machine to a server and vice versa.
 *
 * Assumes that Bedrock runs locally on a Vagrant machine ans uses
 * "vagrant ssh" command to run WP CLI on local server.
 *
 * Will always create a DB backup on the target machine.
 *
 * Requires these Deployer variables to be set:
 *   local_root: Absolute path to website root on local host machine
 *   vagrant_dir: Absolute path to directory that contains .vagrantfile
 *   vagrant_root: Absolute path to website inside Vagrant machine (should mirror local_root)
 */

namespace Deployer;

require(__DIR__ . '/../lib/functions.php');

desc('Pulls DB from server and installs it locally, after having made a backup of local DB');
task('pull:db', function () use ($getLocalEnv, $getRemoteEnv, $urlToDomain) {

    // Export db
    $exportFilename = '_db_export_' . date('Y-m-d_H-i-s') . '.sql';
    $exportAbsFile  = get('deploy_path') . '/' . $exportFilename;
    writeln("<comment>Exporting server DB to {$exportAbsFile}</comment>");
    run("cd {{current_path}} && wp db export {$exportAbsFile}");

    // Download db export
    $downloadedExport = get('local_root') . '/' . $exportFilename;
    writeln("<comment>Downloading DB export to {$downloadedExport}</comment>");
    download($exportAbsFile, $downloadedExport);

    // Cleanup exports on server
    writeln("<comment>Cleaning up {$exportAbsFile} on server</comment>");
    run("rm {$exportAbsFile}");

    // Create backup of local DB
    $backupFilename = '_db_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $backupAbsFile  = get('local_root') . '/' . $backupFilename;
    writeln("<comment>Making backup of DB on local machine to {$backupAbsFile}</comment>");
    runLocally("cd {{vagrant_dir}} && vagrant ssh -- -t \"cd {{vagrant_root}}; wp db export {$backupFilename}\"");

    // Empty local DB
    writeln("<comment>Reset local DB</comment>");
    runLocally("cd {{vagrant_dir}} && vagrant ssh -- -t \"cd {{vagrant_root}}; wp db reset\"");

    // Import export file
    writeln("<comment>Importing {$downloadedExport}</comment>");
    runLocally("cd {{vagrant_dir}} && vagrant ssh -- -t \"cd {{vagrant_root}}; wp db import {$exportFilename}\"");

    // Load local .env file and get local WP URL
    if (!$localUrl = $getLocalEnv()) {
        return;
    }

    // Load remote .env file and get remote WP URL
    if (!$remoteUrl = $getRemoteEnv()) {
        return;
    }

    // Also get domain without protocol and trailing slash
    $localDomain = $urlToDomain($localUrl);
    $remoteDomain = $urlToDomain($remoteUrl);

    // Update URL in DB
    // In a multisite environment, the DOMAIN_CURRENT_SITE in the .env file uses the new remote domain.
    // In the DB however, this new remote domain doesn't exist yet before search-replace. So we have
    // to specify the old (remote) domain as --url parameter.
    writeln("<comment>Updating the URLs in the DB</comment>");
    runLocally("cd {{vagrant_dir}} && vagrant ssh -- -t \"cd {{vagrant_root}}; wp search-replace '{$remoteUrl}' '{$localUrl}' --skip-themes --url='{$remoteDomain}' --network\"");
    // Also replace domain (multisite WP also uses domains without protocol in DB)
    runLocally("cd {{vagrant_dir}} && vagrant ssh -- -t \"cd {{vagrant_root}}; wp search-replace '{$remoteDomain}' '{$localDomain}' --skip-themes --url='{$remoteDomain}' --network\"");

    // Cleanup exports on local machine
    writeln("<comment>Cleaning up {$downloadedExport} on local machine</comment>");
    runLocally("rm {$downloadedExport}");
});

desc('Pushes DB from local machine to server and installs it, after having made a backup of server DB');
task('push:db', function () use ($getLocalEnv, $getRemoteEnv, $urlToDomain) {

    // Export db on Vagrant server
    $exportFilename = '_db_export_' . date('Y-m-d_H-i-s') . '.sql';
    $exportAbsFile  = get('local_root') . '/' . $exportFilename;
    writeln("<comment>Exporting Vagrant DB to {$exportAbsFile}</comment>");
    runLocally("cd {{vagrant_dir}} && vagrant ssh -- -t \"cd {{vagrant_root}}; wp db export {$exportFilename}\"");

    // Upload export to server
    $uploadedExport = get('current_path') . '/' . $exportFilename;
    writeln("<comment>Uploading export to {$uploadedExport} on server</comment>");
    upload($exportAbsFile, $uploadedExport);

    // Cleanup local export
    writeln("<comment>Cleaning up {$exportAbsFile} on local machine</comment>");
    runLocally("rm {$exportAbsFile}");

    // Create backup of server DB
    $backupFilename = '_db_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $backupAbsFile  = get('deploy_path') . '/' . $backupFilename;
    writeln("<comment>Making backup of DB on server to {$backupAbsFile}</comment>");
    run("cd {{current_path}} && wp db export {$backupAbsFile}");

    // Empty server DB
    writeln("<comment>Reset server DB</comment>");
    run("cd {{current_path}} && wp db reset");

    // Import export file
    writeln("<comment>Importing {$uploadedExport}</comment>");
    run("cd {{current_path}} && wp db import {$uploadedExport}");

    // Load local .env file and get local WP URL
    if (!$localUrl = $getLocalEnv()) {
        return;
    }

    // Load remote .env file and get remote WP URL
    if (!$remoteUrl = $getRemoteEnv()) {
        return;
    }

    // Also get domain without protocol and trailing slash
    $localDomain = $urlToDomain($localUrl);
    $remoteDomain = $urlToDomain($remoteUrl);

    // Update URL in DB
    // In a multisite environment, the DOMAIN_CURRENT_SITE in the .env file uses the new remote domain.
    // In the DB however, this new remote domain doesn't exist yet before search-replace. So we have
    // to specify the old (local) domain as --url parameter.
    writeln("<comment>Updating the URLs in the DB</comment>");
    run("cd {{current_path}} && wp search-replace \"{$localUrl}\" \"{$remoteUrl}\" --skip-themes --url='{$localDomain}' --network");
    // Also replace domain (multisite WP also uses domains without protocol in DB)
    run("cd {{current_path}} && wp search-replace \"{$localDomain}\" \"{$remoteDomain}\" --skip-themes --url='{$localDomain}' --network");

    // Cleanup uploaded file
    writeln("<comment>Cleaning up {$uploadedExport} from server</comment>");
    run("rm {$uploadedExport}");
});

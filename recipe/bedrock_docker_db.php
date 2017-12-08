<?php

/**
 * Deployer recipes to push Bedrock database from local docker development
 * machine to a server and vice versa.
 *
 * Assumes that Bedrock runs locally on a docker machine and uses
 * "docker run --rm wordpress" command to run WP CLI on local instance.
 *
 * Will always create a DB backup on the target machine.
 *
 * Requires these Deployer variables to be set:
 *   local_root: Absolute path to website root on local host machine
 *   vagrant_dir: Absolute path to directory that contains .vagrantfile
 *   vagrant_root: Absolute path to website inside Vagrant machine (should mirror local_root)
 */

namespace Deployer;

use Dotenv;

/**
 * Returns the local WP URL or false, if not found.
 *
 * @return false|string
 */
$getLocalEnv = function () {
	$localEnv = new Dotenv\Dotenv( get( 'local_root' ), '.env' );
	$localEnv->overload();
	$localUrl = getenv( 'WP_HOME' );

	if ( ! $localUrl ) {
		writeln( "<error>WP_HOME variable not found in local .env file</error>" );

		return false;
	}

	return $localUrl;
};

/**
 * Returns the remote WP URL or false, if not found.
 * Downloads the remote .env file to a local tmp file
 * to extract data.
 *
 * @return false|string
 */
$getRemoteEnv = function () {
	$tmpEnvFile = get( 'local_root' ) . '/.env-remote';
	download( get( 'current_path' ) . '/.env', $tmpEnvFile );
	$remoteEnv = new Dotenv\Dotenv( get( 'local_root' ), '.env-remote' );
	$remoteEnv->overload();
	$remoteUrl = getenv( 'WP_HOME' );
	// Cleanup tempfile
	runLocally( "rm {$tmpEnvFile}" );

	if ( ! $remoteUrl ) {
		writeln( "<error>WP_HOME variable not found in remote .env file</error>" );

		return false;
	}

	return $remoteUrl;
};

/**
 * Removes the protocol and trailing slash from submitted url.
 *
 * @param $url
 * @return string
 */
$urlToDomain = function ($url) {
	return preg_replace('/^https?:\/\/(.+)/i', '$1', rtrim($url, "/"));
};


desc( 'Pulls DB from server and installs it locally, after having made a backup of local DB' );
task( 'pull:db', function () use ( $getLocalEnv, $getRemoteEnv, $urlToDomain ) {

	// Export db
	$exportFilename = '_db_export_' . date( 'Y-m-d_H-i-s' ) . '.sql';
	$exportAbsFile  = get( 'deploy_path' ) . '/' . $exportFilename;
	writeln( "<comment>Exporting server DB to {$exportAbsFile}</comment>" );
	run( "cd {{current_path}} && wp db export {$exportAbsFile}" );

	// Download db export
	$downloadedExport = get( 'local_root' ) . '/' . $exportFilename;
	writeln( "<comment>Downloading DB export to {$downloadedExport}</comment>" );
	download( $exportAbsFile, $downloadedExport );

	// Cleanup exports on server
	writeln( "<comment>Cleaning up {$exportAbsFile} on server</comment>" );
	run( "rm {$exportAbsFile}" );

	// Create backup of local DB
	$backupFilename = '_db_backup_' . date( 'Y-m-d_H-i-s' ) . '.sql';
	$backupAbsFile  = get( 'local_root' ) . '/' . $backupFilename;
	writeln( "<comment>Making backup of DB on local machine to {$backupAbsFile}</comment>" );
	runLocally( "docker-compose run --rm wordpress wp db export {$backupFilename} --allow-root" );

	// Empty local DB
	writeln( "<comment>Reset server DB</comment>" );
	runLocally( "docker-compose run --rm wordpress wp db reset --allow-root" );

	// Import export file
	// todo: change path of the downloaded file
	writeln( "<comment>Importing {$downloadedExport}</comment>" );
	runLocally( "docker-compose run --rm wordpress wp db import ../{$exportFilename} --allow-root" );

	// Load local .env file and get local WP URL
	if ( ! $localUrl = $getLocalEnv() ) {
		return;
	}

	// Load remote .env file and get remote WP URL
	if ( ! $remoteUrl = $getRemoteEnv() ) {
		return;
	}

	// Also get domain without protocol and trailing slash
	$localDomain = $urlToDomain($localUrl);
	$remoteDomain = $urlToDomain($remoteUrl);

	// Update URL in DB
	// In a multisite environment, the DOMAIN_CURRENT_SITE in the .env file uses the new remote domain.
	// In the DB however, this new remote domain doesn't exist yet before search-replace. So we have
	// to specify the old (remote) domain as --url parameter.
	writeln( "<comment>Updating the URLs in the DB</comment>" );
	runLocally( "docker-compose run --rm wordpress wp search-replace '{$remoteUrl}' '{$localUrl}' --skip-themes --url='{$remoteDomain}' --network --allow-root" );
	// Also replace domain (multisite WP also uses domains without protocol in DB)
	runLocally( "docker-compose run --rm wordpress wp search-replace '{$remoteDomain}' '{$localDomain}' --skip-themes --url='{$remoteDomain}' --network --allow-root" );

	// Cleanup exports on local machine
	writeln( "<comment>Cleaning up {$downloadedExport} on local machine</comment>" );
	runLocally( "rm {$downloadedExport}" );

} );

desc( 'Pushes DB from local machine to server and installs it, after having made a backup of server DB' );
task( 'push:db', function () use ( $getLocalEnv, $getRemoteEnv, $urlToDomain ) {

	// Export db on Vagrant server
	$exportFilename = '_db_export_' . date( 'Y-m-d_H-i-s' ) . '.sql';
	// todo: change path of the downloaded file
	$exportAbsFile  = get( 'local_root' ) . '/web/' . $exportFilename;
	writeln( "<comment>Exporting Vagrant DB to {$exportAbsFile}</comment>" );
	runLocally( "docker-compose run --rm wordpress wp db export {$exportFilename} --allow-root");

	// Upload export to server
	$uploadedExport = get( 'current_path' ) . '/' . $exportFilename;
	writeln( "<comment>Uploading export to {$uploadedExport} on server</comment>" );
	upload( $exportAbsFile, $uploadedExport );

	// Cleanup local export
	writeln( "<comment>Cleaning up {$exportAbsFile} on local machine</comment>" );
	runLocally( "rm {$exportAbsFile}" );

	// Create backup of server DB
	$backupFilename = '_db_backup_' . date( 'Y-m-d_H-i-s' ) . '.sql';
	$backupAbsFile  = get( 'deploy_path' ) . '/' . $backupFilename;
	writeln( "<comment>Making backup of DB on server to {$backupAbsFile}</comment>" );
	run( "cd {{current_path}} && wp db export {$backupAbsFile}" );

	// Empty server DB
	writeln( "<comment>Reset server DB</comment>" );
	run( "cd {{current_path}} && wp db reset" );

	// Import export file
	writeln( "<comment>Importing {$uploadedExport}</comment>" );
	run( "cd {{current_path}} && wp db import {$uploadedExport}" );

	// Load local .env file and get local WP URL
	if ( ! $localUrl = $getLocalEnv() ) {
		return;
	}

	// Load remote .env file and get remote WP URL
	if ( ! $remoteUrl = $getRemoteEnv() ) {
		return;
	}

	// Also get domain without protocol and trailing slash
	$localDomain = $urlToDomain($localUrl);
	$remoteDomain = $urlToDomain($remoteUrl);

	// Update URL in DB
	// In a multisite environment, the DOMAIN_CURRENT_SITE in the .env file uses the new remote domain.
	// In the DB however, this new remote domain doesn't exist yet before search-replace. So we have
	// to specify the old (local) domain as --url parameter.
	writeln( "<comment>Updating the URLs in the DB</comment>" );
	run( "cd {{current_path}} && wp search-replace \"{$localUrl}\" \"{$remoteUrl}\" --skip-themes --url='{$localDomain}' --network" );
	// Also replace domain (multisite WP also uses domains without protocol in DB)
	run( "cd {{current_path}} && wp search-replace \"{$localDomain}\" \"{$remoteDomain}\" --skip-themes --url='{$localDomain}' --network" );

	// Cleanup uploaded file
	writeln( "<comment>Cleaning up {$uploadedExport} from server</comment>" );
	run( "rm {$uploadedExport}" );

} );

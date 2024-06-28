<?php

namespace Deployer;

use Dotenv;

/**
 * Returns the local WP URL or false, if not found.
 *
 * @return false|string
 */
if (!isset($getLocalEnv)) {
    $getLocalEnv = function () {
        $envFile = realpath(get('local_root')) . '/.env';
        $content = file_get_contents($envFile);
        $envData = Dotenv\Dotenv::parse($content);
        $localUrl = $envData['WP_HOME'];

        if (!$localUrl) {
            writeln("<error>WP_HOME variable not found in local .env file</error>");

            return false;
        }

        return $localUrl;
    };
}

/**
 * Returns the remote WP URL or false, if not found.
 * Downloads the remote .env file to a local tmp file
 * to extract data.
 *
 * @return false|string
 */
if (!isset($getRemoteEnv)) {
    $getRemoteEnv = function () {
        $tmpEnvFile = realpath(get('local_root')) . '/.env-remote';
        download(get('current_path') . '/.env', $tmpEnvFile, [
            'flags' => '-L'
        ]);

        $content = file_get_contents($tmpEnvFile);
        $envData = Dotenv\Dotenv::parse($content);
        $remoteUrl = $envData['WP_HOME'];

        // Cleanup tempfile
        runLocally("rm {$tmpEnvFile}");

        if (!$remoteUrl) {
            writeln("<error>WP_HOME variable not found in remote .env file</error>");

            return false;
        }

        return $remoteUrl;
    };
}

/**
 * Removes the protocol and trailing slash from submitted url.
 *
 * @param $url
 * @return string
 */
if (!isset($urlToDomain)) {
    $urlToDomain = function ($url) {
        return preg_replace('/^https?:\/\/(.+)/i', '$1', rtrim($url, "/"));
    };
}


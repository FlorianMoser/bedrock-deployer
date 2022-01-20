# bedrock-deployer
[Deployer](https://deployer.org/) recipes for [Roots Bedrock](https://roots.io/bedrock/), also supports [Roots Sage](https://roots.io/sage/) and [Roots Trellis](https://roots.io/trellis/) setups.

Trellis provides a powerful deployment with Ansible. But if you would like to deploy Bedrock only while running a custom process, Deployer is a quick and simple alternative.

Maybe you are even trying to deploy Bedrock to a shared hosting. Depending on your hosting environment, this *may* be possible. Check out [florianmoser/plesk-deployer](https://github.com/FlorianMoser/plesk-deployer).

**A word of caution:** Make sure you have a backup of your local as well as your remote files, before experimenting with deployment recipes. Files might easily get overwritten when you provide wrong paths! You are solely responsible by using the recipes provided here.

## Who needs this
PHP developers who would like to deploy their Bedrock applications using Deployer.

## Installation
Use Composer:

````
$ composer require florianmoser/bedrock-deployer
````

# Recipes
This package offers several recipes to help you with your Bedrock deployment. Require each package as needed.

These are the available recipes:

## Bedrock DB
Provides tasks to export the database from the server and import it to your development machine and vice versa.

There is a recipe for Trellis / Vagrant as well as a recipe for Valet+ environments.

### Trellis / Vagrant environment

Requirements:

- Vagrant **running** on your local machine (or Trellis, of course)
- WP CLI running on your Vagrant as well as on your remote machine

Load into your deploy.php file with:

````php
require 'vendor/florianmoser/bedrock-deployer/recipe/bedrock_db.php';
````

Requires these Deployer environment variables to be set:

- local_root: Absolute path to website root directory on local host machine
- vagrant_dir: Absolute path to directory that contains .vagrantfile
- vagrant_root: Absolute path to website inside Vagrant machine (should mirror local_root)

Example:

````php
set( 'local_root', dirname( __FILE__ ) );
set( 'vagrant_dir', dirname( __FILE__ ) . '/../trellis' );
set( 'vagrant_root', '/srv/www/domain.com/current' );
````

### Valet+ environment

Requirements:

- Bedrock running on [Valet+](https://github.com/weprovide/valet-plus)

Load into your deploy.php file with:

````php
require 'vendor/florianmoser/bedrock-deployer/recipe/bedrock_valetplus_db.php';
````

Requires these Deployer environment variables to be set:

- local_root: Absolute path to website root directory on local host machine

Example:

````php
set( 'local_root', dirname( __FILE__ ) );
````

### Task pull:db
Exports database on server and imports it into your local Vagrant database, while removing previous data. Creates a backup of the local database in the local_root directory, before importing the new data.

After the import, the WordPress URLs are converted from server URL to local URL, so your WordPress installation will continue to work right after the import.

Database credentials and URLs are read from remote and local .env file. So make sure, those files are up to date.

### Task push:db
Exports database from local Vagrant database and imports it into your remote server, while removing previous data. Creates a backup of the remote database on the server in the current release directory, before importing the new data.

After the import, the WordPress URLs are converted from local URL to remote URL, so your WordPress installation will continue to work right after the import.

Database credentials and URLs are read from remote and local .env file. So make sure, those files are up to date.

## Bedrock .env
Provides tasks to manage the .env file on the server.

Load into your deploy.php file with:

````php
require 'vendor/florianmoser/bedrock-deployer/recipe/bedrock_env.php';
````

Requires no special Deployer environment variables to be set.

### Task bedrock:env
Tries to copy the .env file from a previous release to current release. If there is no previous release or if no .env file is available, the .env file is created while prompting the user for credentials.

When creating a new .env file, this task also generates the WordPress salts.

## Bedrock Miscellaneous
Provides miscellaneous Bedrock tasks.

Load into your deploy.php file with:

````php
require 'vendor/florianmoser/bedrock-deployer/recipe/bedrock_misc.php';
````

Requires no special Deployer environment variables to be set.

### Task bedrock:vendors
Runs `composer install` for Bedrock on your server.

## Common
Provides common deployment tasks.

Requirements:

- WP CLI running on your Vagrant as well as on your remote machine

Load into your deploy.php file with:

````php
require 'vendor/florianmoser/bedrock-deployer/recipe/common.php';
````

Requires no special Deployer environment variables to be set.

### Task activate:plugins
Activates all plugins on remote server.

## Filetransfer
Provides tasks to upload/download files from/to synced directories.

Load into your deploy.php file with:

````php
require 'vendor/florianmoser/bedrock-deployer/recipe/filetransfer.php';
````

Requires these Deployer environment variables to be set:

- sync_dirs: Array of paths, that will be simultaneously updated with $absoluteLocalPath => $absoluteRemotePath. If a path has a trailing slash, only its content will be transferred, not the directory itself.

Example:

````php
set( 'sync_dirs', [
    dirname( __FILE__ ) . '/web/app/uploads/' => '{{deploy_path}}/shared/web/app/uploads/',
] );
````

### Task pull:files
Will pull all files from each `$absoluteRemotePath` to each `$absoluteLocalPath`. New files will be added, existing files will be updated, but files existing locally only will not be deleted.

To ensure no files are lost, each `$absoluteLocalPath` and its content are backed up in a zip file that will be added in the `$absoluteLocalPath`. Existing backups from previous pulls are not included in a new backup.

If you prefer to pull the files without making a backup, consider using the task `pull:files-no-bak`.

### Task push:files
Will push all files from each `$absoluteLocalPath` to each `$absoluteRemotePath`. New files will be added, existing files will be updated, but files existing on remote server only will not be deleted.

To ensure no files are lost, each `$absoluteRemotePath` and its content are backed up in a zip file that will be added in the `$absoluteRemotePath`. Existing backups from previous pushes are not included in a new backup.

If you prefer to push the files without making a backup, consider using the task `push:files-no-bak`.

## Sage
Provides tasks to deploy Roots Sage theme.

Requirements:

- Roots Sage 10 (also supports Sage 9, see the included /examples/deploy.full-example.php on how to use with Sage 9)

Load into your deploy.php file with:

````php
require 'vendor/florianmoser/bedrock-deployer/recipe/sage.php';
````

Requires these Deployer environment variables to be set:

- theme_path: Path to theme, relative to release_path
- local_root: Absolute path to website root directory on local host machine

Example:

````php
set( 'local_root', dirname( __FILE__ ) );
set( 'theme_path', 'web/app/themes/theme-name' );
````

### Task sage:vendors
Runs `composer install` inside Sage theme directory.

### Task sage:assets
Compiles the Sage assets on the local machine and then uploads them to remote server in theme directory (overwriting previous assets!).

## Trellis
You will not want to use these recipes to deploy Trellis. Trellis has its own and powerful deployment process. However you might use Trellis for developing and only use these recipes to deploy Bedrock.

The downside with this method is that you will have a /site as well as a /trellis directory in your repository. So both directories will be cloned to the server.

This task deals with this situation:

### Task trellis:remove
Will delete the remote /trellis directory and move the content of /site to /. Use this task after deployment but before symlink changes.

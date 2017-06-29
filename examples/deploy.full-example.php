<?php

namespace Deployer;

require 'vendor/deployer/deployer/recipe/common.php';
require 'vendor/florianmoser/bedrock-deployer/recipe/bedrock_db.php';
require 'vendor/florianmoser/bedrock-deployer/recipe/bedrock_env.php';
require 'vendor/florianmoser/bedrock-deployer/recipe/bedrock_misc.php';
require 'vendor/florianmoser/bedrock-deployer/recipe/common.php';
require 'vendor/florianmoser/bedrock-deployer/recipe/filetransfer.php';
require 'vendor/florianmoser/bedrock-deployer/recipe/sage.php';
require 'vendor/florianmoser/bedrock-deployer/recipe/trellis.php';

// Configuration

// Common Deployer config
set( 'repository', 'ssh://git@github.org/vendor/repository.git' );
set( 'shared_dirs', [
	'web/app/uploads'
] );

// Bedrock DB config
set( 'vagrant_dir', dirname( __FILE__ ) . '/../trellis' );
set( 'vagrant_root', '/srv/www/domain.com/current' );

// Bedrock DB and Sage config
set( 'local_root', dirname( __FILE__ ) );

// Sage config
set( 'theme_path', 'web/app/themes/your-theme' );

// File transfer config
set( 'sync_dirs', [
	dirname( __FILE__ ) . '/web/app/uploads/' => '{{deploy_path}}/shared/web/app/uploads/',
] );


// Hosts

set( 'default_stage', 'staging' );

host( 'your-host.com/staging' )
	->stage( 'staging' )
	->user( 'your-username' )
	->set( 'deploy_path', '/staging.domain.com/deploy' );

host( 'your-host.com/production' )
	->stage( 'production' )
	->user( 'your-username' )
	->set( 'deploy_path', '/domain.com/deploy' );


// Tasks

// Deployment flow
desc( 'Deploy your project' );
task( 'deploy', [
	'deploy:prepare',
	'deploy:lock',
	'deploy:release',
	'deploy:update_code',
	'trellis:remove',
	'deploy:shared',
	'deploy:writable',
	'bedrock:vendors',
	'sage:vendors',
	'push:assets',
	'bedrock:env',
	'deploy:clear_paths',
	'deploy:symlink',
	'deploy:unlock',
	'cleanup',
	'success',
] );

// [Optional] if deploy fails automatically unlock.
after( 'deploy:failed', 'deploy:unlock' );

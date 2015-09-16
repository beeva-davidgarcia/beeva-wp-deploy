<?php
/**
 * Plugin Name: BEEVA WP Deployer
 * Plugin URI: http://www.beeva.com
 * Description: Despliega tu blog en GitHub y/o S3
 * Version: 0.0.1
 * Author: David García Carbayo
 * Author URI: http://www.dgcmedia.es
 */
 
remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
add_action( 'init', 'BeevaWpDeploy_init' );

function BeevaWpDeploy_init() {
	add_action( 'admin_menu', 'BeevaWpDeploy_add_admin_menu' );
	add_action( 'admin_init', 'BeevaWpDeploy_AWS_settings_init' );
	add_action( 'admin_init', 'BeevaWpDeploy_GH_settings_init' );
}

function BeevaWpDeploy_add_admin_menu(  ) { 
	add_menu_page( 'BeevaWpDeploy', 'BeevaWpDeploy', 'manage_options', 'BeevaWpDeploy', 'BeevaWpDeploy_options_page' );
}

function BeevaWpDeploy_AWS_settings_init(  ) { 
	register_setting( 'BeevaWpDeploy_AWS_settings', 'BeevaWpDeploy_AWS_settings' );

	add_settings_section(
		'BeevaWpDeploy_AWS_section', 
		__( 'Datos AWS S3:', 'BeevaWpDeploy' ),
		'BeevaWpDeploy_AWS_settings_section_callback', 
		'BeevaWpDeploy_AWS_settings'
	);

	add_settings_field( 
		'aws_run', 
		__( 'Desplegar en S3: ', 'BeevaWpDeploy' ),
		'BeevaWpDeploy_aws_run_render', 
		'BeevaWpDeploy_AWS_settings', 
		'BeevaWpDeploy_AWS_section' 
	);
	
	add_settings_field( 
		'aws_id', 
		__( 'AWS access key ID', 'BeevaWpDeploy' ), 
		'BeevaWpDeploy_aws_id_render', 
		'BeevaWpDeploy_AWS_settings', 
		'BeevaWpDeploy_AWS_section' 
	);

	add_settings_field( 
		'aws_secret', 
		__( 'AWS secret access key', 'BeevaWpDeploy' ), 
		'BeevaWpDeploy_aws_secret_render', 
		'BeevaWpDeploy_AWS_settings', 
		'BeevaWpDeploy_AWS_section' 
	);

	add_settings_field( 
		'aws_region', 
		__( 'AWS region', 'BeevaWpDeploy' ), 
		'BeevaWpDeploy_aws_region_render', 
		'BeevaWpDeploy_AWS_settings', 
		'BeevaWpDeploy_AWS_section' 
	);

	add_settings_field( 
		'aws_bucket', 
		__( 'Bucket name', 'BeevaWpDeploy' ), 
		'BeevaWpDeploy_aws_bucket_render', 
		'BeevaWpDeploy_AWS_settings', 
		'BeevaWpDeploy_AWS_section' 
	);
	
	add_settings_field( 
		'aws_error', 
		__( 'Página de error', 'BeevaWpDeploy' ),
		'BeevaWpDeploy_aws_error_render', 
		'BeevaWpDeploy_AWS_settings', 
		'BeevaWpDeploy_AWS_section' 
	);
}

function BeevaWpDeploy_GH_settings_init(  ) { 
	register_setting( 'BeevaWpDeploy_GH_settings', 'BeevaWpDeploy_GH_settings' );

	add_settings_section(
		'BeevaWpDeploy_GH_section', 
		__( 'Datos Github:', 'BeevaWpDeploy' ),
		'BeevaWpDeploy_GH_settings_section_callback', 
		'BeevaWpDeploy_GH_settings'
	);

	add_settings_field( 
		'gh_run', 
		__( 'Desplegar en GitHub: ', 'BeevaWpDeploy' ),
		'BeevaWpDeploy_GH_run_render', 
		'BeevaWpDeploy_GH_settings', 
		'BeevaWpDeploy_GH_section' 
	);
	
	add_settings_field( 
		'gh_token', 
		__( 'Token Api GitHub', 'BeevaWpDeploy' ),
		'BeevaWpDeploy_GH_token_render',
		'BeevaWpDeploy_GH_settings', 
		'BeevaWpDeploy_GH_section' 
	);
	
	add_settings_field( 
		'gh_user',
		__( 'Usuario repo', 'BeevaWpDeploy' ),
		'BeevaWpDeploy_GH_user_render',
		'BeevaWpDeploy_GH_settings', 
		'BeevaWpDeploy_GH_section' 
	);

	add_settings_field( 
		'gh_repo', 
		__( 'Repositorio', 'BeevaWpDeploy' ),
		'BeevaWpDeploy_GH_repo_render',
		'BeevaWpDeploy_GH_settings', 
		'BeevaWpDeploy_GH_section' 
	);
	
	add_settings_field( 
		'gh_branch',
		__( 'Rama', 'BeevaWpDeploy' ),
		'BeevaWpDeploy_GH_branch_render',
		'BeevaWpDeploy_GH_settings', 
		'BeevaWpDeploy_GH_section' 
	);
}


function check_aws_connection($aws_id, $aws_secret, $region, $bucket){
	$bucket_check = Cache::check_credentials($aws_id, $aws_secret, $region, $bucket);
	if($bucket_check == false){
		echo "Could not connect to the bucket. Make sure that bucket name is correct and that you defined correct AWS credentials.";
		return false;
	}else if(isset($bucket_check['error'])){
		echo "Could not authenticate with AWS ID and AWS secret key";
		return false;
	}else{
		return true;
	}
}

/*
 * AWS
 */
 
function BeevaWpDeploy_aws_run_render(  ) { 
	$options = get_option( 'BeevaWpDeploy_AWS_settings' );
	?>
	<input type="checkbox" name="BeevaWpDeploy_AWS_settings[aws_run]" <?= (isset($options['aws_run']) ? "checked" : ""); ?>><br>
	<?php

}

function BeevaWpDeploy_aws_id_render(  ) { 
	$options = get_option( 'BeevaWpDeploy_AWS_settings' );
	?>
	<input type='text' name='BeevaWpDeploy_AWS_settings[aws_id]' value='<?php echo (isset($options['aws_id']) ? $options['aws_id'] : ""); ?>'>
	<?php

}


function BeevaWpDeploy_aws_secret_render(  ) { 
	$options = get_option( 'BeevaWpDeploy_AWS_settings' );
	?>
	<input type='password' name='BeevaWpDeploy_AWS_settings[aws_key]' value='<?php echo (isset($options['aws_key']) ? $options['aws_key'] : ""); ?>'>
	<?php

}

function BeevaWpDeploy_aws_region_render() {  
	$options = get_option('BeevaWpDeploy_AWS_settings');
	?>
	<select name='BeevaWpDeploy_AWS_settings[aws_region]'>
		<option value='ap-northeast-1' <?= (isset($options['aws_region']) && $options['aws_region'] == 'ap-northeast-1' ? "selected" : ""); ?>>Asia Pacific (Tokyo)</option>
		<option value='ap-southeast-1' <?= (isset($options['aws_region']) && $options['aws_region'] == 'ap-southeast-1' ? "selected" : ""); ?>>Asia Pacific (Singapore)</option>
		<option value='ap-southeast-2' <?= (isset($options['aws_region']) && $options['aws_region'] == 'ap-southeast-2' ? "selected" : ""); ?>>Asia Pacific (Sydney)</option>
		<option value='eu-central-1' <?= (isset($options['aws_region']) && $options['aws_region'] == 'eu-central-1' ? "selected" : ""); ?>>EU (Frankfurt)</option>
		<option value='eu-west-1' <?= (isset($options['aws_region']) && $options['aws_region'] == 'eu-west-1' ? "selected" : ""); ?>>EU (Ireland)</option>
		<option value='sa-east-1' <?= (isset($options['aws_region']) && $options['aws_region'] == 'sa-east-1' ? "selected" : ""); ?>>South America (Sao Paulo)</option>
		<option value='us-east-1' <?= (isset($options['aws_region']) && $options['aws_region'] == 'us-east-1' ? "selected" : ""); ?>>US East (N. Virginia)</option>
		<option value='us-west-1' <?= (isset($options['aws_region']) && $options['aws_region'] == 'us-west-1' ? "selected" : ""); ?>>US West (N. California)</option>
		<option value='us-west-2' <?= (isset($options['aws_region']) && $options['aws_region'] == 'us-west-2' ? "selected" : ""); ?>>US West (Oregon)</option>
	</select>
	<?php
}

function BeevaWpDeploy_aws_bucket_render(  ) { 
	$options = get_option( 'BeevaWpDeploy_AWS_settings' );
	?>
	<input type='text' name='BeevaWpDeploy_AWS_settings[aws_bucket]' value='<?php echo (isset($options['aws_bucket']) ? $options['aws_bucket'] : ""); ?>'>
	<?php

}

function BeevaWpDeploy_aws_error_render(  ) { 
	$options = get_option( 'BeevaWpDeploy_AWS_settings' );
	?>
	<input type='text' name='BeevaWpDeploy_AWS_settings[404_page]' value='<?php echo (isset($options['404_page']) ? $options['404_page'] : ""); ?>'>
	<?php

}

/*
 * GH
 */
 
function BeevaWpDeploy_GH_run_render(  ) { 
	$options = get_option( 'BeevaWpDeploy_GH_settings' );
	?>
	<input type="checkbox" name="BeevaWpDeploy_GH_settings[gh_run]" <?= (isset($options['gh_run']) ? "checked" : ""); ?>><br>
	<?php
}

function BeevaWpDeploy_GH_token_render(  ) { 
	$options = get_option( 'BeevaWpDeploy_GH_settings' );
	?>
	<input type='text' name='BeevaWpDeploy_GH_settings[gh_token]' value='<?php echo (isset($options['gh_token']) ? $options['gh_token'] : ""); ?>'>
	<?php
}

function BeevaWpDeploy_GH_user_render(  ) { 
	$options = get_option( 'BeevaWpDeploy_GH_settings' );
	?>
	<input type="text" name='BeevaWpDeploy_GH_settings[gh_user]' value='<?php echo (isset($options['gh_user']) ? $options['gh_user'] : ""); ?>'>
	<?php
}

function BeevaWpDeploy_GH_repo_render(  ) {
	$options = get_option( 'BeevaWpDeploy_GH_settings' );
	?>
	<input type='text' name='BeevaWpDeploy_GH_settings[gh_repo]' value='<?php echo (isset($options['gh_repo']) ? $options['gh_repo'] : ""); ?>'>
	<?php
}

function BeevaWpDeploy_GH_branch_render(  ) {
	$options = get_option( 'BeevaWpDeploy_GH_settings' );
	?>
	<input type='text' name='BeevaWpDeploy_GH_settings[gh_branch]' value='<?php echo (isset($options['gh_branch']) ? $options['gh_branch'] : ""); ?>'>
	<?php
}

function BeevaWpDeploy_AWS_settings_section_callback(  ) { }

function BeevaWpDeploy_GH_settings_section_callback(  ) { }


function BeevaWpDeploy_options_page(  ) { 
	require(__DIR__.'/includes/settings.php');
}

add_action('admin_footer', 'setup_js');

function setup_js() {?>
	<script type="text/javascript" >
	jQuery(document).ready(function($) {
		var total_pages_aws = 0;
		var total_pages_gh = 0;
		var s3 = <?= (isset(get_option( 'BeevaWpDeploy_AWS_settings' )["aws_run"]) ? "true" : "false"); ?>;
		var gh = <?= (isset(get_option( 'BeevaWpDeploy_GH_settings' )["gh_run"]) ? "true" : "false"); ?>;
		
		$('#BeevaWpDeploy_get_files').live('click',function(){ 
			//The following code starts the animation
			$("#search_pages_loading").show();
			$(".found_pages_text").hide();
			new imageLoader(cImageSrc, 'stopAnimation()');
			new imageLoader(cImageSrc, 'startAnimation()');
			
			$.post(ajaxurl, {
				'action': 'get_files'
			}, function(response) {
				var pages_aws = JSON.parse(response);

				var pages_gh = JSON.parse(response);
				
				var pages_urls_aws = Object.keys(pages_aws);

				var pages_urls_gh = Object.keys(pages_gh);
				
				new imageLoader(cImageSrc, 'stopAnimation()');
				total_pages_aws = pages_urls_aws.length;
				total_pages_gh = pages_urls_gh.length;
				
				$("#search_pages_loading").hide();
				$(".scanning_text").hide();
				$(".found_pages_text").html(total_pages_aws+" contenidos encontrados.");
				$(".found_pages_text").css("display", "inline-block");
				
				
				if(s3){
					$("#aws_progress").show();
					upload_s3_page_chain(pages_urls_aws,pages_aws);
				}
				
				if(gh){
					$("#gh_progress").show();
					upload_gh_page_chain(pages_urls_gh, pages_gh);
				}
				
			});
		});
		
		
		var upload_s3_page_chain = function(pages_urls_aws,types){
			$("#aws_out_of").html("Ficheros subidos a S3: <b>"+(total_pages_aws-pages_urls_aws.length)+" / "+total_pages_aws)+"</b>";
			$("#aws_progress_bar").width(((total_pages_aws-pages_urls_aws.length)/total_pages_aws*100)+"%");

			if(pages_urls_aws.length !== 0){
				var fileToLoad = pages_urls_aws.pop();
				$.post(ajaxurl, {
					'action': 'upload_page_aws',
					'urls': { url : fileToLoad , type : types[fileToLoad] }
				}, function(response) {
					upload_s3_page_chain(pages_urls_aws,types);
				});
			}
		}
		
		var upload_gh_page_chain = function(pages_urls_gh){
			$("#gh_out_of").html("Ficheros commiteados en GitHub: <b>"+(total_pages_gh-pages_urls_gh.length)+" / "+total_pages_gh)+"</b>";
			$("#gh_progress_bar").width(((total_pages_gh-pages_urls_gh.length)/total_pages_gh*100)+"%");
					
			if(pages_urls_gh.length !== 0){
				var fileToCommit = pages_urls_gh.pop();
				$.post(ajaxurl, {
					'action': 'upload_page_gh',
					'urls': fileToCommit
				}, function(response) {
					upload_gh_page_chain(pages_urls_gh);
				});
			}
		}
	});
	</script>
<?php
}

require_once 'aws/aws-autoloader.php';
require_once __DIR__.'/includes/BeevaGH.php';
require_once __DIR__.'/includes/Cache.php';

add_action( 'wp_ajax_get_files', 'get_files_callback' );
function get_files_callback() {
	$aws_options = get_option( 'BeevaWpDeploy_AWS_settings' );
	$gh_options = get_option( 'BeevaWpDeploy_GH_settings' );
	$awsOk = isset($aws_options["aws_run"]) && check_aws_connection($aws_options["aws_id"], $aws_options["aws_key"], $aws_options["aws_region"], $aws_options["aws_bucket"]);
	$ghOk = isset($gh_options["gh_run"]) && BeevaGH::check($gh_options["gh_token"],$gh_options["gh_user"],$gh_options["gh_repo"],$gh_options["gh_branch"]);
	if($awsOk || $ghOk){
		$cache = new Cache($aws_options, $gh_options);
		echo json_encode($cache->get_all_page_links());
		wp_die();
	}else{
		echo "Error";
		wp_die();
	}
}


	
add_action('wp_ajax_upload_page_aws', 'upload_page_callback_aws');
add_action('wp_ajax_upload_page_gh', 'upload_page_callback_gh');

function upload_page_callback_aws() {
	$aws_options = get_option( 'BeevaWpDeploy_AWS_settings' );
	
	$urls = array($_POST['urls']);

	$cache = new Cache($aws_options, []);
	echo $cache->upload_page_contents_S3($urls);
	wp_die();
}

function upload_page_callback_gh() {
	$gh_options = get_option( 'BeevaWpDeploy_GH_settings' );
	
	$urls = array($_POST['urls']);
	
	$cache = new Cache([], $gh_options);
	echo $cache->upload_page_contents_GH($urls);
	
	wp_die();
}
?>
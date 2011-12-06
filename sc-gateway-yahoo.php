<?php

/*
Plugin Name: Social Connect - Yahoo Gateway
Plugin URI: http://wordpress.org/extend/plugins/social-connect/
Description: Allows you to login / register with Yahoo - REQUIRES Social Connect plugin
Version: 0.10
Author: Brent Shepherd, Nathan Rijksen
Author URI: http://wordpress.org/extend/plugins/social-connect/
License: GPL2
 */

require_once dirname(__FILE__) . '/openid.php';

class SC_Gateway_Yahoo
{
	
	protected static $calls = array('connect','callback');
	
	static function init()
	{
		add_action('social_connect_button_list',array('SC_Gateway_Yahoo','render_button'));
	}
	
	static function call()
	{
		if ( !isset($_GET['call']) OR !in_array($_GET['call'], array('connect','callback')))
		{
			return;
		}
		
		call_user_func(array('SC_Gateway_Yahoo', $_GET['call']));
	}
	
	static function render_button()
	{
		$image_url = plugins_url() . '/' . basename( dirname( __FILE__ )) . '/button.png';
		?>
		<a href="javascript:void(0);" title="Yahoo" class="social_connect_login_yahoo"><img alt="Yahoo" src="<?php echo $image_url ?>" /></a>
		<div id="social_connect_yahoo_auth" style="display: none;">
			<input type="hidden" name="redirect_uri" value="<?php echo( SOCIAL_CONNECT_PLUGIN_URL . '/call.php?call=connect&gateway=yahoo' ); ?>" />
		</div>
		
		<script type="text/javascript">
		(jQuery(function($) {
			var _do_yahoo_connect = function() {
				var yahoo_auth = $('#social_connect_yahoo_auth');
				var redirect_uri = yahoo_auth.find('input[type=hidden][name=redirect_uri]').val();
				window.open(redirect_uri,'','scrollbars=no,menubar=no,height=400,width=800,resizable=yes,toolbar=no,status=no');
			};
			
			$(".social_connect_login_yahoo, .social_connect_login_continue_yahoo").click(function() {
				_do_yahoo_connect();
			});
		}));
		</script>
		<?php
	}
	
	static function connect()
	{
		$openid             = new LightOpenID;
		$openid->identity   = 'me.yahoo.com';
		$openid->required   = array('namePerson', 'namePerson/friendly', 'contact/email');
		$openid->returnUrl  = SOCIAL_CONNECT_PLUGIN_URL . '/call.php?gateway=yahoo&call=callback';
		header('Location: ' . $openid->authUrl());
	}
	
	static function callback()
	{
		$openid             = new LightOpenID;
		$openid->returnUrl  = SOCIAL_CONNECT_PLUGIN_URL . '/call.php?gateway=yahoo&call=callback';
		
		try
		{
			if ( !$openid->validate())
			{
				echo 'validation failed';
				return;
			}
		}
			catch(ErrorException $e)
		{
			echo $e->getMessage();
			return;
		}
		
		$yahoo_id   = $openid->identity;
		$attributes = $openid->getAttributes();
		$email      = $attributes['contact/email'];
		$name       = $attributes['namePerson'];
		$username   = $attributes['namePerson/friendly'];
		$signature  = SC_Utils::generate_signature($yahoo_id);
		
		?>
		<html>
		<head>
		<script>
		function init() {
		  window.opener.wp_social_connect({'action' : 'social_connect', 'social_connect_provider' : 'yahoo', 
			'social_connect_openid_identity' : '<?php echo $yahoo_id ?>',
			'social_connect_signature' : '<?php echo $signature ?>',
			'social_connect_email' : '<?php echo $email ?>',
			'social_connect_name' : '<?php echo $name ?>',
			'social_connect_username' : '<?php echo $username ?>'});
			
		  window.close();
		}
		</script>
		</head>
		<body onload="init();">
		</body>
		</html>
		<?php
	}
	
	static function process_login()
	{
		$redirect_to            = SC_Utils::redirect_to();
		$provider_identity      = $_REQUEST[ 'social_connect_openid_identity' ];
		$provided_signature     = $_REQUEST[ 'social_connect_signature' ];
		
		SC_Utils::verify_signature( $provider_identity, $provided_signature, $redirect_to );
		
		$username = $_REQUEST[ 'social_connect_username' ];
		if (empty($username))
		{
			$username   = explode('@',$_REQUEST[ 'social_connect_email' ]);
			$username   = $username[0];
		}
		
		if (empty($_REQUEST[ 'social_connect_name' ]))
		{
			$name       = $username;
			$first_name = $username;
			$last_name  = '';
		}
			else
		{
			$name       = $_REQUEST[ 'social_connect_name' ];
			$names      = explode(' ',$name);
			$first_name = array_shift($names);
			$last_name  = implode(' ',$names);
		}
		
		return (object) array(
			'provider_identity' => $provider_identity,
			'email'             => $_REQUEST[ 'social_connect_email' ],
			'first_name'        => $first_name,
			'last_name'         => $last_name,
			'profile_url'       => '',
			'name'              => $name,
			'user_login'        => strtolower($username)
		);
	}
	
}

SC_Gateway_Yahoo::init();
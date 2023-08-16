<?php
/*
* WangGuard Users Info
*/
if ( !defined( 'ABSPATH' ) ) exit;
function wangguard_users_info() {
	global $wpdb,$wangguard_nonce, $wangguard_api_key, $blog_id;
	if ( !current_user_can('level_10') )
		die(__('Cheatin&#8217; uh?', 'wangguard'));
		$userID = $_GET["userID"];
		$userIP = $_GET["userIP"];
		$user_info = get_userdata($userID);
	//$blogID = $user_info->primary_blog;
	//if ( function_exists( is_multisite() ) ) {
	//$blog_details = get_blog_details( array( 'blog_id' => $blogID ) );
	//}
?>
<div class="wrap about-wrap">
			<h1><?php  printf( __( 'Info about %s' ), $user_info->user_login ); ?></h1>
			<div class="about-text"><?php __( 'This is a Beta page. We are working hard.', 'wangguard'  ); ?></div>
			<div class="wangguard-avatar"><?php  echo get_avatar( $user_info->user_email , 120 ); ?></div>
			<h2 class="nav-tab-wrapper">
				<a class="nav-tab nav-tab-active" href="">
					<?php  _e( 'General' ); ?>
				</a>
			</h2>
			<div class="changelog">
				<h3><?php  _e( 'This is a Beta Screen', 'wangguard' ); ?></h3>
				<div class="feature-section col two-col"><div>
						<p><?php
							printf( __('Username: %s<br />'), $user_info->user_login);
							printf( __('User ID: %s <br />'), $user_info->ID);
							printf( __('User IP: %s <br />'), $userIP);
							printf( __('User nicename: %s <br />'), $user_info->user_nicename);
					 ?></p>
					</div>
					<div class="last-feature"><p><?php
						printf( __('User email: %s <br />'), $user_info->user_email);
						printf( __('User registered: %s <br />'), $user_info->user_registered);
						printf( __('User nickname: %s <br />'), $user_info->nickname);
					// if ( wangguard_is_multisite() ){
					//	 echo 'Blog ID: ' . $blogID . '<br />';
					//	 echo 'User primary Blog : ' . $blog_details->blogname . '<br />';
					//	 }
					?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
?>
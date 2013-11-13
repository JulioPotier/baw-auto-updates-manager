<?php
/*
Plugin Name: BAW Auto Updates Manager
Version: 1.5
Description: You can now select which plugin and theme can be autoupdated, just check this box! &mdash;&mdash;&rarr;
Plugin URI: http://boiteaweb.fr/auto-updates-manager-gerer-facilement-mises-a-jour-automatiques-7714.html
Author: Julio Potier
Author URI: http://boiteaweb.fr
Text Domain: baw-auto-updates-manager
Domain Path: /lang
*/

defined( 'ABSPATH' ) or	die( 'Cheatin\' uh?' );

if( is_admin() ) {

define( 'BAWAUM_VERSION', '1.5' );
define( 'BAWAUM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', 'bawaum_add_l10n' );
function bawaum_add_l10n()
{
	load_plugin_textdomain( 'bawaum', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
}

add_action( 'manage_plugins_custom_column', 'bawaum_manage_plugins_custom_column', 10, 2 );
function bawaum_manage_plugins_custom_column( $column_name, $plugin_file )
{
	if( $column_name!='bawaum' )
		return;
	$plugins_ok = get_option( 'bawaum_update_plugins' );
	$url = wp_nonce_url( admin_url('admin-post.php?action=autoupdateplugin&plugin='.$plugin_file), 'autoupdate_'. $plugin_file);
	if( $plugins_ok && in_array( $plugin_file, $plugins_ok ) )
		echo '<a class="autoupdate_link" href="'.$url.'"><span class="icon-aum icon-checkbox-checked"></span></a>';
	else
		echo '<a class="autoupdate_link" href="'.$url.'"><span class="icon-aum icon-checkbox-unchecked"></span></a>';
}

add_filter( 'manage_plugins_columns', 'bawaum_manage_plugins_columns' );
function bawaum_manage_plugins_columns( $columns )
{
	if( current_user_can( 'update_plugins' ) && ( !isset( $_GET['plugin_status'] ) || $_GET['plugin_status']!='mustuse' ) )
		$columns['bawaum'] = 'AutoUpdate?';
	return $columns;
}

add_action( 'admin_post_autoupdateplugin', 'bawaum_autoupdateplugin' );
add_action( 'wp_ajax_autoupdateplugin', 'bawaum_autoupdateplugin' );
function bawaum_autoupdateplugin()
{
	$plugin_file = isset( $_GET['plugin'] ) ? $_GET['plugin'] : -1;

	check_admin_referer( 'autoupdate_'.$plugin_file );

	if( !current_user_can( 'update_plugins' ) )
		wp_die( 'You do not have permission to access this page.' );

	$plugins_ok = (array)get_option( 'bawaum_update_plugins' );
	if( is_array( $plugins_ok ) && in_array( $plugin_file, $plugins_ok ) ) {
		$plugins_ok = array_diff( $plugins_ok, (array)$plugin_file );
		$check = 'icon-checkbox-unchecked';
	}else{
		$plugins_ok[] = $plugin_file;
		$check = 'icon-checkbox-checked';
	}

	update_option( 'bawaum_update_plugins', array_unique( array_filter( $plugins_ok ) ) );

	if( !defined( 'DOING_AJAX' ) || !DOING_AJAX )
		wp_safe_redirect( remove_query_arg( 'settings-updated', wp_get_referer() ) );
	die( '[ok]'.$check );
}

add_action( 'admin_post_autoupdatetheme', 'bawaum_autoupdatetheme' );
add_action( 'wp_ajax_autoupdatetheme', 'bawaum_autoupdatetheme' );
function bawaum_autoupdatetheme()
{
	$theme_file = isset( $_GET['theme'] ) ? $_GET['theme'] : -1;

	check_admin_referer( 'autoupdate_'.$theme_file );
	
	if( !current_user_can( 'update_themes' ) )
		wp_die( 'You do not have permission to access this page.' );

	$themes_ok = get_option( 'bawaum_update_themes' );
	if( is_array( $themes_ok ) &&  in_array( $theme_file, $themes_ok ) ){
		$themes_ok = array_diff( $themes_ok, (array)$theme_file );
		$check = 'icon-checkbox-unchecked';
	}else{
		$themes_ok[] = $theme_file;
		$check = 'icon-checkbox-checked';
	}

	update_option( 'bawaum_update_themes', array_unique( array_filter( $themes_ok ) ) );
	if( !defined( 'DOING_AJAX' ) || !DOING_AJAX )
		wp_safe_redirect( remove_query_arg( 'settings-updated', wp_get_referer() ) );
	die( '[ok]'.$check );
}

add_action( 'admin_post_forceautoupdate', 'bawaum_forceautoupdate' );
function bawaum_forceautoupdate()
{
	check_admin_referer( 'force-autoupdate' );
	delete_option('auto_updater.lock');
	delete_site_transient( 'update_core' );
	set_time_limit( 0 );
	wp_maybe_auto_update();
	wp_safe_redirect( remove_query_arg( 'settings-updated', wp_get_referer() ) );
	die();
}

add_filter( 'theme_action_links', 'bawaum_theme_action_links' );
function bawaum_theme_action_links( $actions )
{
	if( !current_user_can( 'update_themes' ) )
		return $actions;

	$themes_ok = get_option( 'bawaum_update_themes' );
	if( isset( $actions['activate'] ) ) {
		$theme = preg_replace( '/<a(.*?)href="(.*?)"(.*?)>(.*?)<\/a>/', '$2', $actions['activate'] );
		parse_str( $theme, $theme );
		$theme = $theme['amp;template']; ////
		$url = wp_nonce_url( admin_url('admin-post.php?action=autoupdatetheme&theme='.$theme), 'autoupdate_'. $theme);
		if( $themes_ok && in_array( $theme, $themes_ok ) )
			$actions['autoupdatetheme'] = '<a class="autoupdate_link" href="'.$url.'"><span class="icon-aum icon-checkbox-checked"></span> AutoUpdate</a>';
		else
			$actions['autoupdatetheme'] = '<a class="autoupdate_link" href="'.$url.'"><span class="icon-aum icon-checkbox-unchecked"></span> AutoUpdate</a>';
	}
	return $actions;
}

add_action( 'load-themes.php', 'bawaum_add_option_link', PHP_INT_MAX );
function bawaum_add_option_link()
{
	if( !current_user_can( 'update_themes' ) )
		return;
	$themes_ok = get_option( 'bawaum_update_themes' );
	$theme = basename( get_template_directory_uri() );
	$url = wp_nonce_url( admin_url('admin-post.php?action=autoupdatetheme&theme=' . $theme), 'autoupdate_' . $theme);
	if( $themes_ok && in_array( $theme, $themes_ok ) )
		$action = '</a><a class="autoupdate_link" href="'.$url.'"><span class="icon-aum icon-checkbox-checked"></span> AutoUpdate</a><a>';
	else
		$action = '</a><a class="autoupdate_link" href="'.$url.'"><span class="icon-aum icon-checkbox-unchecked"></span> AutoUpdate</a><a>';
	add_theme_page( '', $action, 'update_themes', 'autoupdate', '__return_false' );
}

add_action( 'admin_print_styles-plugins.php', 'bawaum_add_style' );
add_action( 'admin_print_styles-themes.php', 'bawaum_add_style' );
function bawaum_add_style()
{
	wp_register_style( 'aum_font', BAWAUM_PLUGIN_URL . 'css/style.css' );
	wp_enqueue_style( 'aum_font' );
}

add_action( 'admin_print_styles-themes.php', 'bawaum_add_styles' );
function bawaum_add_styles()
{	?>
<style>
#menu-appearance ul li:last-child{display:none;}
</style>
<?php }

add_action( 'admin_footer-themes.php', 'bawaum_add_scripts', PHP_INT_MAX );
add_action( 'admin_footer-plugins.php', 'bawaum_add_scripts', PHP_INT_MAX );
function bawaum_add_scripts()
{	?>
<script>
	jQuery( document ).ready( function($){
		var bawaum_doing_ajax = false;
		$('.autoupdate_link').on('click', function(e){
			e.preventDefault();
			e.stopPropagation();
			if( $(this).attr( 'href' ) ){
				var url = $(this).attr( 'href' );
				var t = $(this).find('span');
			}
			$(t).toggleClass( 'icon-checkbox-unchecked icon-checkbox-checked' );
			if( bawaum_doing_ajax!=t ) {
				bawaum_doing_ajax = t;
				$.get( url.replace( 'admin-post.php', 'admin-ajax.php' ) )
					.done( function(data){
						$(t).removeClass( 'icon-checkbox-unchecked icon-checkbox-checked' );
						if( data.substring(0,4)=='[ok]' ){
							$(t).addClass( data.replace('[ok]', '') );
						}else{
							alert( "<?php _e( 'An error has occurred. Please reload the page and try again.' ); ?>" );
						}
						bawaum_doing_ajax = false;
						} );
			}
		});
	} );
</script>
<?php }

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'bawaum_settings_action_links' );
function bawaum_settings_action_links( $links )
{
	if ( current_user_can( 'update_core' ) || current_user_can( 'update_themes' ) || current_user_can( 'update_plugins' ) ) 
		array_unshift( $links, '<a href="' . admin_url( 'update-core.php#autoupdates_manager' ) . '">' . __( 'Settings' ) . '</a>' );
	return $links;
}

add_action( 'admin_init', 'bawaum_admin_init' );
function bawaum_admin_init()
{
	if( !bawaum_is_disabled() ){
		if ( current_user_can( 'manage_options' ) ) 
			register_setting( 'bawaum_settings', 'bawaum_disabled' );
		if ( current_user_can( 'update_plugins' ) ) 
			register_setting( 'bawaum_settings', 'bawaum_plugins' );
		if ( current_user_can( 'update_themes' ) ) 
			register_setting( 'bawaum_settings', 'bawaum_themes' );
		if ( current_user_can( 'update_core' ) ) {
			register_setting( 'bawaum_settings', 'bawaum_core' );
			register_setting( 'bawaum_settings', 'bawaum_core_dev' );
			register_setting( 'bawaum_settings', 'bawaum_l10n' );
		}
		if ( current_user_can( 'manage_options' ) ) 
			register_setting( 'bawaum_settings', 'bawaum_svncheckout' );
		if ( current_user_can( 'manage_options' ) ) {
			register_setting( 'bawaum_settings', 'bawaum_sendemail' );
			register_setting( 'bawaum_settings', 'bawaum_sendemail_s_u' );
			register_setting( 'bawaum_settings', 'bawaum_sendemail_f_u' );
			register_setting( 'bawaum_settings', 'bawaum_sendemail_c_u' );
			register_setting( 'bawaum_settings', 'bawaum_sendemail_debug' );
		}
	}
}

function bawaum_options_page_not_possible()
{
	bawaum_form_header();
}

function bawaum_form_header()
{ ?>
	<br id="autoupdates_manager" />
	<div class="wrap">
	<div id="icon-bawaum" class="icon32" style="background: url(<?php echo BAWAUM_PLUGIN_URL; ?>images/icon32.png) 0 0 no-repeat;"><br/></div> 
	<h2>Auto Updates Manager <small>v<?php echo BAWAUM_VERSION; ?></small></h2>
<?php }

add_action( 'core_upgrade_preamble', 'bawaum_options_page', 0 );
function bawaum_options_page()
{
	if ( current_user_can( 'update_core' ) || current_user_can( 'update_themes' ) || current_user_can( 'update_plugins' )  || current_user_can( 'manage_options' ) ) {
		add_settings_section( 'bawaum_settings_page', '', '__return_false', 'bawaum_settings' );
			if ( current_user_can( 'manage_options' ) ) 
				add_settings_field( 'bawaum_field_active', __( 'Activation', 'bawaum' ), 'bawaum_field_active', 'bawaum_settings', 'bawaum_settings_page' );
			if ( current_user_can( 'update_core' ) ) 
				add_settings_field( 'bawaum_field_core', __( 'Stable versions', 'bawaum' ), 'bawaum_field_core', 'bawaum_settings', 'bawaum_settings_page' );
			if ( current_user_can( 'update_core' ) ) 
				add_settings_field( 'bawaum_field_core_dev', __( 'Nightly builds', 'bawaum' ), 'bawaum_field_core_dev', 'bawaum_settings', 'bawaum_settings_page' );
			if ( current_user_can( 'update_plugins' ) ) 
				add_settings_field( 'bawaum_field_plugins', __( 'Plugins', 'bawaum' ), 'bawaum_field_plugins', 'bawaum_settings', 'bawaum_settings_page' );
			if ( current_user_can( 'update_themes' ) ) 
				add_settings_field( 'bawaum_field_themes', __( 'Themes', 'bawaum' ), 'bawaum_field_themes', 'bawaum_settings', 'bawaum_settings_page' );
			if ( current_user_can( 'update_core' ) ) 
				add_settings_field( 'bawaum_field_l10n', __( 'Translations', 'bawaum' ), 'bawaum_field_l10n', 'bawaum_settings', 'bawaum_settings_page' );
			if ( current_user_can( 'manage_options' ) ) 
				add_settings_field( 'bawaum_field_svn', __( 'SVN Checkouts', 'bawaum' ), 'bawaum_field_svn', 'bawaum_settings', 'bawaum_settings_page' );
			if ( current_user_can( 'manage_options' ) ) 
				add_settings_field( 'bawaum_field_email', __( 'Emails', 'bawaum' ), 'bawaum_field_email', 'bawaum_settings', 'bawaum_settings_page' );
		bawaum_form_header();
	?>

		<form action="options.php" method="post">
			<?php
				if( bawaum_is_disabled() ){
					printf( '<p>%s %s.</p><p><a target="_blank" href="http://codex.wordpress.org/Editing_wp-config.php#Disable_Plugin_and_Theme_Update_and_Installation">WordPress Help (Codex)</a></p>', __( 'ERROR:' ), __( 'This site <strong>is not</strong> able to apply these updates automatically.' ) );
				}else{
					settings_fields( 'bawaum_settings' );
					?>
					<p>
						<?php submit_button( null, 'primary', 'submit', false ); ?>
						<a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=forceautoupdate' ), 'force-autoupdate' ); ?>" class="button button-secondary"><?php _e( 'Launch an autoupdate now.', 'bawaum' ); ?></a>
					</p>
					<?php
					do_settings_sections( 'bawaum_settings' );
					submit_button();
				}
			?>
			</p>
		</form>
		</div>
	<?php
	}
}

function bawaum_is_disabled() {
	if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS )
		return true;

	if ( defined( 'WP_INSTALLING' ) )
		return true;

	$disabled = defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED;

	return apply_filters( 'automatic_updater_disabled', $disabled );
}

function bawaum_field_active()
{
	$bawaum_disabled = get_option( 'bawaum_disabled' );
	$disabled = defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED; 
	$bawaum_disabled = $bawaum_disabled===false ? $disabled : (bool)$bawaum_disabled; 
	?>
	<em><?php _e( 'If you need, you can easily disable all automatic updates.', 'bawaum' ); ?></em><br />
		<label><input type="radio" name="bawaum_disabled" value="1" <?php checked( $bawaum_disabled, true ); ?>/> <?php _e( 'Deactivate now, all future automatic updates.', 'bawaum' ); ?></label><br />
		<label><input type="radio" name="bawaum_disabled" value="0" <?php checked( $bawaum_disabled, false ); ?>/> <?php _e( 'Let the automatic updates do their job! <em>(Default)</em>', 'bawaum' ); ?></label><br />
		<em>&rsaquo; 
		<?php 
		if( $disabled )
			_e( '<code>AUTOMATIC_UPDATER_DISABLED</code> is set to TRUE.', 'bawaum' );
		elseif( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && !AUTOMATIC_UPDATER_DISABLED )
			_e( '<code>AUTOMATIC_UPDATER_DISABLED</code> is set to FALSE.', 'bawaum' );
		else
			_e( '<code>AUTOMATIC_UPDATER_DISABLED</code> is not set.', 'bawaum' );
		?><br />
		<?php
		$is_disabled = bawaum_is_disabled();
		_e( '<b>Automatic updates</b> currently are : ', 'bawaum' );
		echo $is_disabled ? _e( '<span style="color:red;font-weight:bold">DISABLED</span>', 'bawaum' ) : _e( '<span style="color:green;font-weight:bold">ENABLED</span>', 'bawaum' );
		?>
		</em>
		<?php
}

function bawaum_field_core()
{ 
	$bawaum_core = get_option( 'bawaum_core' );
	if( $bawaum_core===false ){
		$bawaum_core = 1;
		if ( defined( 'WP_AUTO_UPDATE_CORE' ) ) {
			if ( false === WP_AUTO_UPDATE_CORE ) {
				$bawaum_core = 0;
			} elseif ( true === WP_AUTO_UPDATE_CORE ) {
				$bawaum_core = 2;
			}
		}
	}
	?>
	<em><?php printf( __( 'What kind of updates do you need for the <b>%s</b>?', 'bawaum' ), __( 'core', 'bawaum' ) ); ?></em><br />
		<label><input type="radio" name="bawaum_core" value="0" <?php checked( $bawaum_core, 0 ); ?>/> <?php printf( __( 'Never update %s automatically.', 'bawaum' ), __( 'core', 'bawaum' ) ); ?></label><br />
		<label><input type="radio" name="bawaum_core" value="1" <?php checked( $bawaum_core, 1 ); ?>/> <?php _e( 'Update automatically only minor versions. <em>(Default)</em>', 'bawaum' ); ?></label><br />
		<label><input type="radio" name="bawaum_core" value="2" <?php checked( $bawaum_core, 2 ); ?>/> <?php printf( __( 'Always update all %s automatically.', 'bawaum' ), __( 'versions', 'bawaum' ) ); ?></label>
	<?php
}

function bawaum_field_svn()
{ 
	$bawaum_svncheckout = get_option( 'bawaum_svncheckout' );
	$bawaum_svncheckout = $bawaum_svncheckout === false ? 1 : $bawaum_svncheckout;
	?>
	<em><?php _e( 'Do you need to do <b>SVN Checkouts</b>?', 'bawaum' ); ?></em><br />
		<label><input type="radio" name="bawaum_svncheckout" value="0" <?php checked( $bawaum_svncheckout, 0 ); ?>/> <?php _e( 'I don\'t want to update via SVN anymore.', 'bawaum' ); ?></label><br />
		<label><input type="radio" name="bawaum_svncheckout" value="1" <?php checked( $bawaum_svncheckout, 1 ); ?>/> <?php _e( 'I want to auto update via SVN. <em>(Default)</em>', 'bawaum' ); ?></label><br />
	<?php
}

function bawaum_field_core_dev()
{ 
	$bawaum_core_dev = (int)get_option( 'bawaum_core_dev' );
	$current_is_development_version = (bool)strpos( $GLOBALS['wp_version'], '-' );
	?>
	<em><?php printf( __( 'What kind of updates do you need for the <b>%s</b>?', 'bawaum' ), __( 'nightly builds', 'bawaum' ) ); ?></em><br />
		<label><input type="radio" name="bawaum_core_dev" value="0" <?php checked( $bawaum_core_dev, 0 ); ?>/> <?php _e( 'I don\'t need or not use developer versions. <em>(Default)</em>', 'bawaum' ); ?></label><br />
		<label><input type="radio" name="bawaum_core_dev" value="1" <?php checked( $bawaum_core_dev, 1 ); ?>/> <?php _e( 'I want to auto update developer versions.', 'bawaum' ); ?></label><br />
		<em>&rsaquo; 
		<?php 
		if( $current_is_development_version )
			_e( 'You are using a development version of WordPress.', 'bawaum' );
		else
			_e( 'You aren\'t using a development version of WordPress., so no nightly builds updates can be performed.', 'bawaum' );
		?>
		</em>
	<?php
}

function bawaum_field_email()
{
	$bawaum_sendemail = get_option( 'bawaum_sendemail' );
	$bawaum_sendemail = $bawaum_sendemail===false ? 1 : $bawaum_sendemail;
	$bawaum_sendemail_s_u = (int)get_option( 'bawaum_sendemail_s_u' );
	$bawaum_sendemail_f_u = (int)get_option( 'bawaum_sendemail_f_u' );
	$bawaum_sendemail_c_u = (int)get_option( 'bawaum_sendemail_c_u' );
	$bawaum_sendemail_debug = (int)get_option( 'bawaum_sendemail_debug' );
	// if nothing is checked, "fail" will be forced.
	if( $bawaum_sendemail==1 && ($bawaum_sendemail_s_u + $bawaum_sendemail_f_u + $bawaum_sendemail_c_u ) === 0 )
		$bawaum_sendemail_f_u = 1;
	?>
	<em><?php _e( 'Do you want to receive an email for ...', 'bawaum' ); ?></em><br />
		<label><input type="radio" name="bawaum_sendemail" value="0" <?php checked( $bawaum_sendemail, 0 ); ?>/> <?php _e( 'Sorry, i don\'t want any mail.', 'bawaum' ); ?></label><br />
		<label><input type="radio" name="bawaum_sendemail" value="1" <?php checked( $bawaum_sendemail, 1 ); ?>/> <?php _e( '... this cases: <em>(All by Default, check 1 or more box below)</em>', 'bawaum' ); ?></label><br /><br />

		<label><input type="checkbox" name="bawaum_sendemail_s_u" value="1" <?php checked( $bawaum_sendemail_s_u, 1 ); ?>/> <?php _e( 'For successful updates.', 'bawaum' ); ?></label><br />
		<label><input type="checkbox" name="bawaum_sendemail_f_u" value="1" <?php checked( $bawaum_sendemail_f_u, 1 ); ?>/> <?php _e( 'For failed updates.', 'bawaum' ); ?></label><br />
		<label><input type="checkbox" name="bawaum_sendemail_c_u" value="1" <?php checked( $bawaum_sendemail_c_u, 1 ); ?>/> <?php _e( 'For critical updates.', 'bawaum' ); ?></label><br /><br />
		<label><input type="checkbox" name="bawaum_sendemail_debug" value="1" <?php checked( $bawaum_sendemail_debug, 1 ); ?>/> <?php _e( 'Send me an extra debug mail. <em>(Default if Nightly build)</em>', 'bawaum' ); ?></label>
	<?php
}

function bawaum_field_plugins()
{ 
	$bawaum_plugins = (int)get_option( 'bawaum_plugins' );
	?>
	<em><?php printf( __( 'What kind of updates do you need for the <b>%s</b>?', 'bawaum' ), __( 'plugins', 'bawaum' ) ); ?></em><br />
		<label><input type="radio" name="bawaum_plugins" value="0" <?php checked( $bawaum_plugins, 0 ); ?>/> <?php printf( __( 'Never update %s automatically. <em>(Default)</em>', 'bawaum' ), __( 'plugins', 'bawaum' ) ); ?></label><br />
		<label><input type="radio" name="bawaum_plugins" value="1" <?php checked( $bawaum_plugins, 1 ); ?>/> <?php printf( __( 'Update automatically only <a href="%s">the chosen one</a>.', 'bawaum' ), admin_url( 'plugins.php' ) ); ?></label><br />
		<label><input type="radio" name="bawaum_plugins" value="2" <?php checked( $bawaum_plugins, 2 ); ?>/> <?php printf( __( 'Always update all %s automatically.', 'bawaum' ), __( 'plugins', 'bawaum' ) ); ?></label>
	<?php
}

function bawaum_field_themes()
{ 
	$bawaum_themes = (int)get_option( 'bawaum_themes' );
	?>
	<em><?php printf( __( 'What kind of updates do you need for the <b>%s</b>?', 'bawaum' ), __( 'theme', 'bawaum' ) ); ?></em><br />
		<label><input type="radio" name="bawaum_themes" value="0" <?php checked( $bawaum_themes, 0 ); ?>/> <?php printf( __( 'Never update %s automatically. <em>(Default)</em>', 'bawaum' ), __( 'themes', 'bawaum' ) ); ?></label><br />
		<label><input type="radio" name="bawaum_themes" value="1" <?php checked( $bawaum_themes, 1 ); ?>/> <?php printf( __( 'Update automatically only <a href="%s">the chosen one</a>.', 'bawaum' ), admin_url( 'themes.php' ) ); ?></label><br />
		<label><input type="radio" name="bawaum_themes" value="2" <?php checked( $bawaum_themes, 2 ); ?>/> <?php printf( __( 'Always update all %s automatically.', 'bawaum' ), __( 'themes', 'bawaum' ) ); ?></label>
	<?php
}

function bawaum_field_l10n()
{ 
	$bawaum_l10n = get_option( 'bawaum_l10n' );
	$bawaum_l10n = $bawaum_l10n!==false ? $bawaum_l10n : 1;
	?>
	<em><?php printf( __( 'What kind of updates do you need for the <b>%s</b>?', 'bawaum' ), __( 'translations', 'bawaum' ) ); ?></em><br />
		<label><input type="radio" name="bawaum_l10n" value="0" <?php checked( $bawaum_l10n, 0 ); ?>/> <?php printf( __( 'Never update %s automatically.', 'bawaum' ), __( 'translations', 'bawaum' ) ); ?></label><br />
		<label><input type="radio" name="bawaum_l10n" value="1" <?php checked( $bawaum_l10n, 1 ); ?>/> <?php printf( __( 'Always update all %s automatically. <em>(Default)</em>', 'bawaum' ), __( 'translations', 'bawaum' ) ); ?></label>
	<?php
}

add_filter( 'automatic_updates_is_vcs_checkout', 'bawaum_updates_svn' );
function bawaum_updates_svn( $checkout )
{
	$bawaum_svncheckout = get_option( 'bawaum_svncheckout' );
	return $bawaum_svncheckout === false ? true : (bool)$bawaum_svncheckout;
}

add_filter( 'auto_update_plugin', 'bawaum_update_this_plugins', 10, 2 );
function bawaum_update_this_plugins( $update, $item )
{
	$bawaum_plugins = get_option( 'bawaum_plugins' );
	$update_this_plugins = get_option( 'bawaum_update_plugins' );
	if( $bawaum_plugins==2 )
		return true;
	if( $bawaum_plugins==1 && count( $update_this_plugins ) )
		return in_array( $item, $update_this_plugins );
	return false;
}

add_filter( 'auto_update_theme', 'bawaum_update_this_themes', 10, 2 );
function bawaum_update_this_themes( $update, $item )
{
	$bawaum_themes = get_option( 'bawaum_themes' );
	$update_this_themes = get_option( 'bawaum_update_themes' );
	if( $bawaum_themes==2 )
		return true;
	if( $bawaum_themes==1 && count( $update_this_themes ) )
		return in_array( $item, $update_this_themes );
	return false;
}

add_filter( 'auto_update_core', 'bawaum_allow_minor_auto_core_updates' );
add_filter( 'allow_minor_auto_core_updates', 'bawaum_allow_minor_auto_core_updates' );
function bawaum_allow_minor_auto_core_updates( $upgrade_minor )
{
	$update_minor_core = (int)get_option( 'bawaum_core' );
	return $update_minor_core > 0;
}

add_filter( 'auto_update_core', 'bawaum_allow_minor_auto_core_updates' );
add_filter( 'allow_major_auto_core_updates', 'bawaum_allow_major_auto_core_updates' );
function bawaum_allow_major_auto_core_updates( $upgrade_major )
{
	$update_major_core = (int)get_option( 'bawaum_core' );
	return $update_major_core === 2;
}

add_filter( 'allow_dev_auto_core_updates', 'bawaum_allow_dev_auto_core_updates' );
function bawaum_allow_dev_auto_core_updates( $upgrade_dev )
{
	$update_dev_core = (int)get_option( 'bawaum_core' );
	return $update_dev_core === 1;
}

add_filter( 'automatic_updater_disabled', 'bawaum_automatic_updater_disabled' );
function bawaum_automatic_updater_disabled( $disabled )
{
	$bawaum_disabled = (bool)get_option( 'bawaum_disabled' );
	return $bawaum_disabled;
}

add_filter( 'send_core_update_notification_email', 'bawaum_send_core_update_notification_email' );
function bawaum_send_core_update_notification_email( $notify )
{
	$bawaum_sendemail = (bool)get_option( 'bawaum_sendemail' );
	$bawaum_sendemail_s_u = (bool)get_option( 'bawaum_sendemail_s_u' );
	$bawaum_sendemail_f_u = (bool)get_option( 'bawaum_sendemail_f_u' );
	$bawaum_sendemail_c_u = (bool)get_option( 'bawaum_sendemail_c_u' );
	// Nothing were checked, "fail" is forced
	if( !$bawaum_sendemail_s_u && !$bawaum_sendemail_f_u && !$bawaum_sendemail_c_u )
		$bawaum_sendemail_f_u = true;
	$return = $bawaum_sendemail;

	if( $return && $bawaum_sendemail_s_u && 'success'==$notify )
		$return = true;
	elseif( $return && $bawaum_sendemail_f_u && 'fail'==$notify )
		$return = true;
	elseif( $return && $bawaum_sendemail_c_u && 'critical'==$notify )
		$return = true;
	return $return;
}

add_filter( 'auto_core_update_send_email', 'bawaum_auto_core_update_send_email' );
function bawaum_auto_core_update_send_email( $true )
{
	$bawaum_sendemail = (bool)get_option( 'bawaum_sendemail' );
	return $bawaum_sendemail;
}


add_filter( 'automatic_updates_send_debug_email', 'bawaum_automatic_updates_send_debug_email' );
function bawaum_automatic_updates_send_debug_email()
{
	$bawaum_sendemail_debug = (bool)get_option( 'bawaum_sendemail_debug' );
	return $bawaum_sendemail_debug;
}

add_action( 'admin_init', 'bawaum_github_plugin_updater' );
function bawaum_github_plugin_updater() {

	define( 'WP_GITHUB_FORCE_UPDATE', true );
	include_once( '/inc/updater.php' );

	include_once( '/inc/pointers.php' );

	$config = array(
		'slug' => plugin_basename( __FILE__ ),
		'proper_folder_name' => 'baw-auto-updates-manager',
		'api_url' => 'https://api.github.com/repos/BoiteAWeb/baw-auto-updates-manager',
		'raw_url' => 'https://raw.github.com/BoiteAWeb/baw-auto-updates-manager/master',
		'github_url' => 'https://github.com/BoiteAWeb/baw-auto-updates-manager',
		'zip_url' => 'https://github.com/BoiteAWeb/baw-auto-updates-manager/archive/master.zip',
		'sslverify' => true,
		'requires' => '3.7',
		'tested' => '3.8-alpha',
		'readme' => 'readme.txt',
		'access_token' => '',
	);

	new WP_GitHub_Updater( $config );

}

add_filter( 'plugins_api', 'bawaum_force_info', 11, 3 );
function bawaum_force_info( $bool, $action, $args )
{
	if( $action=='plugin_information' && $args->slug=='baw-auto-updates-manager' )
		return new stdClass();
	return $bool;
}

add_filter( 'plugins_api_result', 'bawaum_force_info_result', 10, 3 );
function bawaum_force_info_result( $res, $action, $args )
{
	if( $action=='plugin_information' && $args->slug=='baw-auto-updates-manager' && isset( $res->external ) && $res->external ) {
		$request = wp_remote_get( 'https://raw.github.com/BoiteAWeb/baw-auto-updates-manager/master/plugin_infos.txt', array( 'timeout' => 30 ) );
		if ( is_wp_error( $request ) ) {
			$res = new WP_Error('plugins_api_failed', '1) '.__( 'An unexpected error occurred. Something may be wrong with Auto Updates Manager or this server&#8217;s configuration.' ), $request->get_error_message() );
		} else {
			$res = maybe_unserialize( wp_remote_retrieve_body( $request ) );
			if ( ! is_object( $res ) && ! is_array( $res ) )
				$res = new WP_Error('plugins_api_failed', '2) '.__( 'An unexpected error occurred. Something may be wrong with Auto Updates Manager or this server&#8217;s configuration.' ), wp_remote_retrieve_body( $request ) );
		}
	}
	return $res;
}

}
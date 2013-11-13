<?php
if( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit();

delete_option( 'bawaum_disabled' );
delete_option( 'bawaum_plugins' );
delete_option( 'bawaum_themes' );
delete_option( 'bawaum_core' );
delete_option( 'bawaum_core_dev' );
delete_option( 'bawaum_l10n' );
delete_option( 'bawaum_svncheckout' );
delete_option( 'bawaum_sendemail' );
delete_option( 'bawaum_sendemail_s_u' );
delete_option( 'bawaum_sendemail_f_u' );
delete_option( 'bawaum_sendemail_c_u' );
delete_option( 'bawaum_sendemail_debug' );
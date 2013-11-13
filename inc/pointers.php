<?php
defined( 'ABSPATH' ) or	die( 'Cheatin\' uh?' );

add_action( 'admin_enqueue_scripts', 'bawaum_admin_pointers_header' );
function bawaum_admin_pointers_header()
{
   if ( bawaum_admin_pointers_check() ) {
      add_action( 'admin_print_footer_scripts', 'bawaum_admin_pointers_footer' );
      wp_enqueue_script( 'wp-pointer' );
      wp_enqueue_style( 'wp-pointer' );
   }
}

function bawaum_admin_pointers_check()
{
	$admin_pointers = bawaum_admin_pointers();
	foreach ( $admin_pointers as $pointer => $array )
	{
		if ( $array['active'] )
			return true;
	}
}

function bawaum_admin_pointers_footer()
{
	global $pagenow, $current_screen;
	$admin_pointers = bawaum_admin_pointers();
	?>
<script type="text/javascript">
/* <![CDATA[ */
( function($) {
<?php
foreach ( $admin_pointers as $pointer => $array ) {
	$ai = isset( $array['anchor_id'][$pagenow] ) ? $array['anchor_id'][$pagenow] : $array['anchor_id']['all'];
	$ai = isset( $array['anchor_id'][$current_screen->base] ) ? $array['anchor_id'][$current_screen->base] : $ai;
	if( !empty( $ai ) ) {
		?>
		$( '<?php echo $ai; ?>' ).pointer( {
			content: '<?php echo addslashes( $array['content'] ); ?>',
			position: {
				edge: '<?php echo $array['edge']; ?>',
				align: '<?php echo $array['align']; ?>'
			},
			<?php if( !empty( $array['action'] ) ): ?>
				close: function() {
					$.post( ajaxurl, {
						pointer: '<?php echo $pointer; ?>',
						action: '<?php echo $array['action']; ?>',
					} );
				},
			<?php endif; ?>
		} ).pointer( 'open' );
		<?php
	}
}
?>
} )(jQuery);
/* ]]> */
</script>
   <?php
}

function bawaum_admin_pointers()
{
   $dismissed = explode( ',', (string)get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
   $pointer_key = 'bawaum_'.str_replace( '.', '_', BAWAUM_VERSION ).'_';
   $new_pointer = array();
   $actions = apply_filters( 'bawaum_pointer_actions', array() );
   foreach( $actions as $action=>$options ) {
	   if( !in_array( $pointer_key.$action, $dismissed ) ) {
		   $new_pointer[$pointer_key.$action] = array(
		         'anchor_id' => $options['anchor_id'],
		         'edge' => $options['edge'],
		         'align' => $options['align'],
		         'active' => true,
		         'action' => $options['action'],
		   );
		   $new_pointer[$pointer_key.$action]['content'] = '<h3>' . __( 'Auto Updates Manager v'.BAWAUM_VERSION ) . '</h3><p>' . $options['content'] . '</p>';
		}
	}
   return $new_pointer;
}

add_filter( 'bawaum_pointer_actions', 'bawaum_pointer_setting' );
function bawaum_pointer_setting( $pointers )
{
	$pointers['setting'] = array(	'anchor_id'	=> array( 'update-core'=>' ', 'all'=>'#menu-dashboard' ),
									'edge' 		=> 'left',
									'align'		=> 'top',
									'action'	=> 'dismiss-wp-pointer',
									'content'	=> __( '[Dashboard] Find here the new auto-updates settings.', 'bawaum' )
								);
	return $pointers;
}
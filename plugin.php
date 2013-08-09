<?php
/*
Plugin Name: Per Post Javascript
Plugin URI:
Description: Allows custom Javascript code to be added to any post (CPT also supported)
Version: 0.2.0
Author: Zach Lanich
Author URI: https://www.ZachLanich.com
License: Undecided
*/

class Per_Post_JS {

	private $enabled_post_types = 'post, page';

	private $post_js_meta_key = 'pp_js_post_js';

	private $meta_box_id = 'pp_js_editor_box';

	private $meta_box_title = 'Custom Javascript';

	private $meta_box_noncename = 'pp_js_nonce';

	private $editor_field_name = 'pp_js_styles';

	private $editor_field_id = 'pp_js_styles';

	private $editor_id = 'pp_js_editor';

	private $options_metakey = 'pp_js_options_metakey';

	private $options_field_name_prefix = 'pp_js_option_';

	private $options = array();

	private $default_options = array(
		'load_in_footer' => 'true'
	);

	public function __construct() {

		if ( defined('PP_JS_ENABLED_POST_TYPES') ) {
			$this->enabled_post_types = PP_JS_ENABLED_POST_TYPES;
		}

		if ( is_admin() ) {
			// WP Admin

			// Create Meta Box

			add_action( 'add_meta_boxes', array( $this, '_add_meta_box' ));
			add_action( 'save_post', array( $this, '_save_meta_box' ) );

			// Load Scripts & Styles

			add_action( 'admin_enqueue_scripts', array( $this, '_load_editor_scripts' ));
			add_action( 'admin_enqueue_scripts', array( $this, '_load_editor_styles' ));
		}
		else {
			// Front End

			add_action('wp_print_scripts', array( $this, '_render_post_scripts' ), 100000);
			add_action('wp_print_footer_scripts', array( $this, '_render_post_scripts' ), 100000);
		}

	}

	public function _load_editor_scripts() {

		// Check to see if current post type is enabled

		if ( !$this->is_enabled_post_type() ) {
			return;
		}

		wp_enqueue_script('es_ace_code_editor', WP_PLUGIN_URL .'/'. basename( dirname(__FILE__) ) .'/lib/ace_code_editor/src-min-noconflict/ace.js', array(), 1.0 );
		wp_enqueue_script('jquery');
		wp_enqueue_script('pp_js_admin', WP_PLUGIN_URL .'/'. basename( dirname(__FILE__) ) .'/lib/js/pp_js_admin.js', array('es_ace_code_editor', 'jquery'), 1.0 );

	}

	public function _load_editor_styles() {

		// Check to see if current post type is enabled

		if ( !$this->is_enabled_post_type() ) {
			return;
		}

		wp_enqueue_style( 'pp_js_admin', WP_PLUGIN_URL .'/'. basename( dirname(__FILE__) ) .'/lib/css/pp_js_admin.css', array(), 1.0 );

	}

	public function _render_post_scripts() {

		global $post;

		$this->load_options( $post );

		// Check to see if current post type is enabled

		if ( !$this->is_enabled_post_type() ) {
			return;
		}

		$current_filter = current_filter();

		if (
		('true' == $this->options['load_in_footer'] && 'wp_print_footer_scripts' == $current_filter) ||
		('false' == $this->options['load_in_footer'] && 'wp_print_scripts' == $current_filter)
		) {
			$js = get_post_meta( $post->ID, $this->post_js_meta_key, true);

			echo '<script type="text/javascript">'. $js .'</script>';
			echo '<!-- HERE -->';
		}

	}

	public function _add_meta_box() {

		$screens = explode(',', $this->enabled_post_types);

		foreach ( $screens as $screen ) {
			add_meta_box(
				$this->meta_box_id,
				$this->meta_box_title,
				array( $this, '_render_meta_box' ),
				$screen
			);
		}

	}

	public function _render_meta_box( $post ) {

		$this->load_options( $post );

		echo '<p class="metabox_info">
		Here you can add custom Javascript to your post or page. This is best used for small post-specific visual enhancements or to instantiate JS objects using JS that&#8217;s already available in the &lt;head&gt;. <br />
		<span class="metabox_info_emphasis">* Note: Please use discretion -- If you find yourself using the
		same Javascript code on multiple posts or pages, you may want to consider adding them to a Javascript file and enqueueing it.</span>
		</p>';

		// Use nonce for verification
		wp_nonce_field( plugin_basename( __FILE__ ), $this->meta_box_noncename );

		$js = get_post_meta( $post->ID, $this->post_js_meta_key, true);

		echo '<input type="hidden" id="'. $this->editor_field_id .'" name="'. $this->editor_field_name .'" value="'. htmlspecialchars( $js ) .'"/>';//

		echo '<pre id="'. $this->editor_id .'"></pre>';

		// Render Editor

		echo '
		<script type="text/javascript">

			PP_JS.render_editor( "'. $this->editor_id .'", "'. $this->editor_field_id .'" );

		</script>
		';

		echo '<input type="checkbox" id="'. $this->options_field_name_prefix .'load_in_footer" name="'. $this->options_field_name_prefix .'load_in_footer" '. checked($this->options['load_in_footer'], 'true', false) .' value="true" /> Load Javascript in footer (recommended)';

	}

	public function _save_meta_box( $post_id ) {

		// Check if the current user is authorised to do this action.
		if ( 'page' == $_REQUEST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) )
				return;
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) )
				return;
		}

		// Check if the user intended to change this value.
		if ( ! isset( $_POST[ $this->meta_box_noncename ] )
		|| ! wp_verify_nonce( $_POST[ $this->meta_box_noncename ], plugin_basename( __FILE__ ) ) ) {
			return;
		}

		// Save JS to DB

		$post_id = $_POST['post_ID'];

		$js = $_POST[ $this->editor_field_name ];

		update_post_meta( $post_id, $this->post_js_meta_key, $js );


		// Save Options

		foreach( array_keys( $this->default_options ) as $option_key ) {

			if ( !array_key_exists( $this->options_field_name_prefix . $option_key, $_POST ) ) {
				$this->options[ $option_key ] = 'false';
				continue;
			}

			$this->options[ $option_key ] = $_POST[ $this->options_field_name_prefix . $option_key ];
		}

		update_post_meta( $post_id, $this->options_metakey, $this->options );

	}

	private function load_options( $post ) {

		$stored_options = get_post_meta( $post->ID, $this->options_metakey, true );

		if ( !is_array($stored_options) ) {
			$stored_options = array();
		}

		$this->options = array_merge( $this->default_options, $stored_options );

	}

	// Utility Methods

	private function is_enabled_post_type() {

		return in_array( $this->get_current_post_type(), array_map('trim', explode(',', $this->enabled_post_types) ) );

	}

	private function get_current_post_type() {

		global $post, $typenow, $current_screen;

		// We have a post so we can just get the post type from that
		if ( $post && $post->post_type ) {
			return $post->post_type;
		}

		// Check the global $typenow - set in admin.php
		elseif( $typenow ) {
			return $typenow;
		}

		// Check the global $current_screen object - set in screen.php
		elseif( $current_screen && $current_screen->post_type ) {
			return $current_screen->post_type;
		}

		// Lastly check the post_type querystring
		elseif( isset( $_REQUEST['post_type'] ) ) {
			return sanitize_key( $_REQUEST['post_type'] );
		}

		// We do not know the post type!
		return null;

	}

}

add_action('init', create_function('', '$pp_js_editor = new Per_Post_JS();'));
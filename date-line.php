<?php
/**
 * Plugin Name: Date Line
 * Plugin URI: http://news.usni.org
 * Description: Set a date line for a news story
 * Version: 0.1.0
 * Author: Christiaan Conover
 * Author URI: https://christiaanconover.com?ref=date-line-plugin-author-uri
 * License: GPLv2
 * @package usninews
 * @subpackage date-line
 **/

namespace usni\news;

/**
 * Main plugin class
 */
class Date_line {
	// Plugin constants
	const ID = 'usninews-date-line'; // Plugin ID
	const NAME = 'Date Line'; // Plugin name
	const VERSION = '0.1.0'; // Plugin version
	const WPVER = '2.7'; // Minimum version of WordPress required for this plugin
	const PREFIX = 'usninews_date_line_'; // Plugin database/method prefix
	const METAPREFIX = '_usninews_date_line'; // Post meta database prefix
	
	// Class properties
	private $options; // Plugin options and settings
	
	// Class constructor
	function __construct() {
		// Hooks and filters
		add_filter( 'the_content', array( &$this, 'show_dateline' ) );
		
		// Admin
		if ( is_admin() ) {
			// Initialize in admin
			$this->admin_initialize();
			
			// Hooks and filters
			add_action( 'add_meta_boxes', array( &$this, 'metabox') ); // Add meta box
			add_action( 'save_post', array( &$this, 'save_metabox' ) ); // Save the caption when the post is saved
			register_activation_hook( __FILE__, array( &$this, 'activate' ) ); // Plugin activation
			register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) ); // Plugin deactivation
		}
	} // End __construct()
	
	// Create the meta box
	function metabox() {
		// Specify the screens where the meta box should be available
		$screens = array( 'post', 'page' );
		
		// Iterate through the specified screens to add the meta box
		foreach ( $screens as $screen ) {
			add_meta_box(
				self::ID, // HTML ID for the meta box
				self::NAME, // Title of the meta box displayed to the us
				array( &$this, 'metabox_callback'), // Callback function for the meta box to display it to the user
				$screen, // Locations where the meta box should be shown
				'side' // Location where the meta box should be shown. This one is placed on the side.
			);
		}
	} // End metabox()

	// Featured image caption meta box callback
	function metabox_callback( $post ) {
		// Add a nonce field to verify data submissions came from our site
		wp_nonce_field( array( &$this, 'metabox' ), self::PREFIX . 'nonce' );
		
		// Retrieve the current caption as a string, if set
		$dateline = get_post_meta( $post->ID, self::METAPREFIX, true );
		
		echo '<p>Please provide a date line for this story using AP style format.</p>';
		echo '<input type="text" style="width: 100%; max-width: 100%;" id="' . self::ID . '" name="' . self::ID . '" value="' . esc_attr( $dateline ) . '">';
	} // End metabox_callback()
	
	// Save the meta box data
	function save_metabox( $post_id ) {
		/*
		Verify using the nonce that the data was submitted from our meta box on our site.
		If it wasn't, return the post ID and be on our way.
		*/
		// If no nonce was provided, return the post ID
		if ( ! isset( $_POST[self::PREFIX . 'nonce'] ) ) {
			return $post_id;
		}
		
		// Set a local variable for the nonce
		$nonce = $_POST[self::PREFIX . 'nonce'];
		
		// Verify that the nonce is valid
		if ( ! wp_verify_nonce( $nonce, array( &$this, 'metabox' ) ) ) {
			return $post_id;
		}
		
		// Make sure the user has valid permissions
		// If we're editing a page and the user isn't allowed to do that, return the post ID
		if ( 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return $post_id;
			}
		}
		// If we're editing any other post type and the user isn't allowed to do that, return the post ID
		else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return $post_id;
			}
		}
		
		// Now that we've validated nonce and permissions, let's save the caption data
		// Sanitize the caption
		$caption = sanitize_text_field( $_POST[self::ID] );
		
		// Update the caption meta field
		update_post_meta( $post_id, self::METAPREFIX, $caption );
	} // End save_metabox()
	
	// Show the date line on a post
	public function show_dateline( $content ) {
		// Get access to post object
		global $post;
		
		// If a date line is set, run the filter
		if ( $dateline = get_post_meta( $post->ID, self::METAPREFIX, true ) ) {
			// Set date line HTML output
			$dateline = '<span class="dateline p-location">' . $dateline . '</span> - ';
			
			// Add date line to the beginning of the post content
			$content = preg_filter( '/<p>/', '<p>' . $dateline, $content, 1 );
		}
		
		// Return the content
		return $content;
	} // End show_dateline()
	
	/* ===== Admin Initialization ===== */
	function admin_initialize() {
		// Get plugin options from database
		$this->options = get_option( self::PREFIX . 'options' );
		
		// Run upgrade process
		$this->upgrade();
	} // End admin_initialize()
	
	// Plugin upgrade
	function upgrade() {
		// Check whether the database-stored plugin version number is less than the current plugin version number, or whether there is no plugin version saved in the database
		if ( ! empty( $this->options['dbversion'] ) && version_compare( $this->options['dbversion'], self::VERSION, '<' ) ) {
			// Set local variable for options (always the first step in the upgrade process)
			$options = $this->options;
			
			/* Update the plugin version saved in the database (always the last step of the upgrade process) */
			// Set the value of the plugin version
			$options['dbversion'] = self::VERSION;
			
			// Save to the database
			update_option( self::PREFIX . 'options', $options );
			/* End update plugin version */
		}
	} // End upgrade()
	/*
	===== End Admin Initialization =====
	*/
	
	/*
	===== Plugin Activation and Deactivation =====
	*/
	// Plugin activation
	public function activate() {
		// Check to make sure the version of WordPress being used is compatible with the plugin
		if ( version_compare( get_bloginfo( 'version' ), self::WPVER, '<' ) ) {
	 		wp_die( 'Your version of WordPress is too old to use this plugin. Please upgrade to the latest version of WordPress.' );
	 	}
	 	
	 	// Default plugin options
	 	$options = array(
	 		'dbversion' => self::VERSION, // Current plugin version
	 	);
	 	
	 	// Add options to database
	 	add_option( self::PREFIX . 'options', $options );
	} // End activate()
	
	// Plugin deactivation
	public function deactivate() {
		// Remove the plugin options from the database
		delete_option( self::PREFIX . 'options' );
	} // End deactivate
	
	/* ===== End Plugin Activation and Deactivation ===== */
} // End main plugin class

// Create plugin object
$usninews_dateline = new \usni\news\Date_line;
?>
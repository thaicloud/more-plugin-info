<?php
/*
Plugin Name: More Plugin Info
Description: Displays additional information about each plugin on the Plugins page
Version: 1.0.0
Author: Mike Jordan
Author URI: http://brainstormmedia.com/
*/

add_action( 'init', 'MJ_More_Plugin_Info::get_instance', 11 );

class MJ_More_Plugin_Info {
	
	/**
	* @var MJ_More_Plugin_Info Instance of the class.
	*/
	private static $instance = false;
	
	/**
	* @var array results from WordPress API connection
	*/
	private $plugin_meta;
	
	/**
	* Don't use this. Use ::get_instance() instead.
	*/
	public function __construct() {
		if ( !self::$instance ) {
			$message = '<code>' . __CLASS__ . '</code> is a singleton.<br/> Please get an instantiate it with <code>' . __CLASS__ . '::get_instance();</code>';
			wp_die( $message );
		}	
	}
	
	public static function get_instance() {
		if ( !is_a( self::$instance, __CLASS__ ) ) {
			self::$instance = true;
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}
	
	/**
	* Initial setup. Called by get_instance.
	*/
	protected function init() {
		
		if ( true == get_option( 'mpi_realtime', false ) || isset( $_GET['mpi_sync'] ) ){
			add_filter( 'all_plugins', array( $this, 'plugin_meta_populate' ) );
		}else{
			$mpi_plugin_meta = get_option( 'mpi_plugin_meta' );
			if ( !empty( $mpi_plugin_meta ) ){
				$this->plugin_meta = unserialize( $mpi_plugin_meta );
			}
		}
		
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2  );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		
		$plugin_basename = plugin_basename( __FILE__ ); 
		add_filter( "plugin_action_links_$plugin_basename", array( $this, 'plugin_action_links' ) );
		
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		
		add_action( 'admin_notices', array( $this, 'admin_notices' ) ) ;
		
	}
	
	/**
    * For each plugin, use WordPress API to collect additional data 
    * and populate $plugin_meta
    *
    * @return 
    */
	function plugin_meta_populate( $plugins ){
		
		foreach ( $plugins as $slug => $plugin ){
			
			$slug = substr( $slug, 0, strpos( $slug, '/' ) );
			
			// Thanks to http://wp.tutsplus.com/tutorials/plugins/communicating-with-the-wordpress-org-plugin-api/
			// for detailing the following WP API format
			$args = (object) array( 
				'slug' => $slug, 
				'fields' => array( 
					'sections' => false, 
					'tags' => false 
				) 
			);
			$request = array( 
				'action' => 'plugin_information', 
				'timeout' => 5, 
				'request' => serialize( $args ) 
			);
			$url = 'http://api.wordpress.org/plugins/info/1.0/';
			$response = wp_remote_post( $url, array( 'body' => $request ) );
			
			if ( is_wp_error( $response ) ){
				continue;
			}
			
			$plugin_info = unserialize( $response['body'] );
			
			// If plugin exists in the repo, populate $plugin_meta accordingly
			if ( !empty( $plugin_info ) ){	
				
				$plugin['requires'] = "Requires: $plugin_info->requires";
				$plugin['tested'] = "Tested: $plugin_info->tested";
				$plugin['rating'] = "Average Rating: $plugin_info->rating";
				$plugin['num_ratings'] = "# of Ratings: $plugin_info->num_ratings";
				$plugin['added'] = "Added: $plugin_info->added";
				$plugin['donate_link'] = "<a href='$plugin_info->donate_link'>Donate</a>";
				$plugin['download_link'] = "<a href='$plugin_info->download_link'>Download</a>";
				$plugin['updated'] = "Updated: $plugin_info->last_updated";
				$plugin['downloads'] = "Downloads: $plugin_info->downloaded";
				
				$this->plugin_meta[ $slug ] = $plugin;
			}
		}
		update_option( 'mpi_plugin_meta', serialize( $this->plugin_meta ) );
		$timestamp = current_time( 'mysql' ); 
		update_option( 'mpi_sync_timestamp', $timestamp );
		
		return $plugins;
	}
	
	/**
	* If data exists, display on plugin listing (when options allow)
	*
	* @return 
	*/	
	function plugin_row_meta( $links, $slug ){
		
		$slug = substr( $slug, 0, strpos( $slug, '/' ) );	
		
		if ( !empty( $this->plugin_meta[ $slug ] ) ){	

			if ( get_option( 'mpi_downloads', 'on' ) )
				array_push( $links, $this->plugin_meta[ $slug ]['downloads'] );
			if ( get_option( 'mpi_rating', 'on' ) )
				array_push( $links, $this->plugin_meta[ $slug ]['rating'] );
			if ( get_option( 'mpi_num_ratings', 'on' ) )
				array_push( $links, $this->plugin_meta[ $slug ]['num_ratings'] );
			if ( get_option( 'mpi_added' ) )
				array_push( $links, $this->plugin_meta[ $slug ]['added'] );
			if ( get_option( 'mpi_updated' ) )
				array_push( $links, $this->plugin_meta[ $slug ]['updated'] );
			if ( get_option( 'mpi_requires' ) )
				array_push( $links, $this->plugin_meta[ $slug ]['requires'] );
			if ( get_option( 'mpi_tested' ) )
				array_push( $links, $this->plugin_meta[ $slug ]['tested'] );
			if ( get_option( 'mpi_donate_link' ) )
				array_push( $links, $this->plugin_meta[ $slug ]['donate_link'] );
			if ( get_option( 'mpi_download_link' ) )
				array_push( $links, $this->plugin_meta[ $slug ]['download_link'] );
		}
		
		// Create filter, if users want to re-order / edit output
		apply_filters( 'plugin_list_meta', $links );
		
		return $links;
	}
	
	/**
	* Add settings menu
	*/
	function admin_menu(){
		add_options_page( 'More Plugin Info', 'More Plugin Info', 'administrator', 'mpi_settings', array( $this, 'display_settings' ) );	    
	}
	
	/**
	* Display settings page 
	*/
	function display_settings(){
		echo '<div class="wrap">';
		echo '<h2>More Plugin Info</h2>';
		echo "<form name='mpi_form' method='post' action='". str_replace( '%7E', '~', $_SERVER['REQUEST_URI'] )."'>";
		settings_fields( 'mpi_settings' ); 
		do_settings_sections( 'mpi_settings' ); 
		echo '<p><input type="submit" name="Submit" class="button-primary" value="Save Changes" /></p>';
		echo '</form>';
		echo '</div>';
	}
	
	/**
	* Initialize components of settings page
	*/
	function admin_init(){
		$requires  = get_option( 'mpi_requires' );
		$tested  = get_option( 'mpi_tested' );
		$rating  = get_option( 'mpi_rating', 'on' );
		$num_ratings  = get_option( 'mpi_num_ratings', 'on' );
		$added  = get_option( 'mpi_added' );
		$donate_link  = get_option( 'mpi_donate_link' );
		$download_link  = get_option( 'mpi_download_link' );
		$updated  = get_option( 'mpi_updated' );
		$downloads  = get_option( 'mpi_downloads', 'on' );
		$realtime  = get_option( 'mpi_realtime' );
		
		add_settings_section(  
		    'mpi_general_options_section',           
		    'General Options',                    
		    array( $this, 'general_options_section_callback' ),   
			'mpi_settings'
		);
		add_settings_field(   
		    'mpi_downloads',                       
		    'Number of Downloads',                
		    array( $this, 'checkbox_callback' ),  
		    'mpi_settings',                          
		    'mpi_general_options_section',           
		    array(                               
		        'id' => 'mpi_downloads',
		  		'value' => $downloads
		    )  
		);
		add_settings_field(   
		    'mpi_rating',                        
		    'Rating',                
		    array( $this, 'checkbox_callback' ),     
		    'mpi_settings',                           
		    'mpi_general_options_section',           
		    array(                               
		        'id' => 'mpi_rating',
		  		'value' => $rating
		    )
		);
		
		register_setting( 'mpi_settings', 'mpi_downloads' );
		register_setting( 'mpi_settings', 'mpi_rating' );
	}
	
	function general_options_section_callback(){
		echo '<p>Please choose which fields you would like to be visible on the plugin listing.</p>';		
	}
	
	function data_sync_section_callback(){
		echo '<p>In order to display accurate data, you should sync your plugin data from time to time. </p>
		<p>Your plugin data was last updated: <strong>'. get_option( 'mpi_sync_timestamp', 'Never' ).'</strong></p>';		
	}
	
	function checkbox_callback( $args ){
		echo "<input type='checkbox' id='$args[id]' name='$args[id]'";
		if ( !empty( $args['value'] ) ){ echo ' checked'; } 
		echo ">";
	}
	
	/**
	* Add settings page link for this plugin
	*
	* @return 
	*/
	function plugin_action_links( $links ){
	
		$settings_link = '<a href="options-general.php?page=mpi_settings">Settings</a>'; 
		array_unshift( $links, $settings_link ); 
		
		return $links;
	}
	
	/**
	* Add sync prompt on plugin activation
	*/
	function admin_notices() {
	
		$mpi_plugin_check = get_option( 'mpi_sync_timestamp' );
		if ( empty( $mpi_plugin_check ) ){
			?>
			<div class="updated">
				<p>In order to complete your More Plugin Info setup, <a href="plugins.php?mpi_sync">please run the plugin sync</a>.<br />
				This may take a couple of minutes.</p>
			</div>
			<?php
		}
	}
}


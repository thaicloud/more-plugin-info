<?php
/*
Plugin Name: More Plugin Info
Description: Displays additional information about each plugin on the Plugins page
Version: 1.0.1
Author: Mike Jordan
Author URI: http://brainstormmedia.com/
*/

add_action( 'init', 'MJ_More_Plugin_Info::get_instance' );

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
				$this->plugin_meta = $mpi_plugin_meta ;
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
     * @return array Extended plugin data
     */
	function plugin_meta_populate( $plugins ){
		
		foreach ( $plugins as $slug => $plugin ){
			
			$slug = dirname( $slug );
			
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
				$plugin['rating'] = "Average rating: $plugin_info->rating";
				$plugin['num_ratings'] = "# of ratings: $plugin_info->num_ratings";
				$plugin['added'] = "Added: $plugin_info->added";
				$plugin['plugin_link'] = "<a target='_blank' href='http://wordpress.org/plugins/$slug'>WordPress.org page</a>";
				$plugin['donate_link'] = "<a target='_blank' href='$plugin_info->donate_link'>Donate</a>";
				$plugin['download_link'] = "<a target='_blank' href='$plugin_info->download_link'>Download</a>";
				$plugin['updated'] = "Updated: $plugin_info->last_updated";
				$plugin['downloads'] = "Downloads: $plugin_info->downloaded";
				
				$this->plugin_meta[ $slug ] = $plugin;
			}
		}
		update_option( 'mpi_plugin_meta', $this->plugin_meta );
		$timestamp = current_time( 'mysql' ); 
		update_option( 'mpi_sync_timestamp', $timestamp );
		
		return $plugins;
	}
	
	/**
	 * If data exists, display on plugin listing (when options allow)
	 *
	 * @return array Plugin meta links / info
	 */	
	function plugin_row_meta( $links, $slug ){
		
		$slug = dirname( $slug );
		
		if ( !empty( $this->plugin_meta[ $slug ] ) ){	
			
			$defaults = array(
				'downloads' => 'on',
				'rating' => 'on',
				'num_ratings' => 'on',
			);
			$settings = (array) get_option( 'mpi-settings', $defaults );

			if ( $settings['downloads'] )
				array_push( $links, $this->plugin_meta[ $slug ]['downloads'] );
			if ( $settings['rating'] )
				array_push( $links, $this->plugin_meta[ $slug ]['rating'] );
			if ( $settings['num_ratings'] )
				array_push( $links, $this->plugin_meta[ $slug ]['num_ratings'] );
			if ( $settings['added'] )
				array_push( $links, $this->plugin_meta[ $slug ]['added'] );
			if ( $settings['updated'] )
				array_push( $links, $this->plugin_meta[ $slug ]['updated'] );
			if ( $settings['requires'] )
				array_push( $links, $this->plugin_meta[ $slug ]['requires'] );
			if ( $settings['tested'] )
				array_push( $links, $this->plugin_meta[ $slug ]['tested'] );
			if ( $settings['plugin_link'] )
				array_push( $links, $this->plugin_meta[ $slug ]['plugin_link'] );
			if ( $settings['donate_link'] )
				array_push( $links, $this->plugin_meta[ $slug ]['donate_link'] );
			if ( $settings['download_link'] )
				array_push( $links, $this->plugin_meta[ $slug ]['download_link'] );
		}
		
		// Re-order and/or modify final output in each plugin listed
		apply_filters( 'plugin_list_meta', $links );
		
		return $links;
	}
	
	/**
	 * Add settings menu
	 */
	function admin_menu(){
		add_options_page( 'More Plugin Info', 'More Plugin Info', 'administrator', 'more-plugin-info', array( $this, 'display_settings' ) );	    
	}
	
	/**
	 * Display settings page 
	 */
	function display_settings(){
		echo '<div class="wrap">';
		echo '<h2>More Plugin Info</h2>';
		echo '<form name="mpi_sync_form" method="post" action="plugins.php?mpi_sync">';
		echo '<p>In order to display accurate data, you should sync your plugin data from time to time. </p>
		<p>Your plugin data was last updated: <strong>'. get_option( 'mpi_sync_timestamp', 'Never' ) .'</strong></p>';
		submit_button( 'Update Plugin Data Now' );
		echo '</form>';
		echo '<form name="mpi_form" method="post" action="options.php">';
		settings_fields( 'mpi-settings-group' ); 
		do_settings_sections( 'more-plugin-info' ); 
		submit_button( 'Save Changes' );
		echo '</form>';
		echo '</div>';
	}
	
	/**
	 * Initialize components of settings page
	 */
	function admin_init(){
		
		$defaults = array(
			'downloads' => 'on',
			'rating' => 'on',
			'num_ratings' => 'on',
		);
		$settings = (array) get_option( 'mpi-settings', $defaults );
		
		add_settings_section(  
		    'mpi_general_options_section',           
		    'General Options',                    
		    array( $this, 'general_options_section_callback' ),   
			'more-plugin-info'
		);
		
		add_settings_section(  
		    'mpi_autosync_options_section',           
		    'Automatic Sync',                    
		    array( $this, 'autosync_options_section_callback' ),   
			'more-plugin-info'
		);
		
		add_settings_field(   
		    'mpi_downloads',                       
		    'Number of Downloads',                
		    array( $this, 'checkbox_callback' ),  
		    'more-plugin-info',                          
		    'mpi_general_options_section',           
		    array(                               
		        'id' => 'mpi-settings[downloads]',
		  		'value' => $settings['downloads']
		    )  
		);
		add_settings_field(   
		    'mpi_rating',                        
		    'Rating',                
		    array( $this, 'checkbox_callback' ),     
		    'more-plugin-info',                           
		    'mpi_general_options_section',           
		    array(                               
		        'id' => 'mpi-settings[rating]',
		  		'value' => $settings['rating']
		    )
		);
		add_settings_field(   
		    'mpi_num_ratings',                        
		    'Number of Ratings',                
		    array( $this, 'checkbox_callback' ),     
		    'more-plugin-info',                           
		    'mpi_general_options_section',           
		    array(                               
		        'id' => 'mpi-settings[num_ratings]',
		  		'value' => $settings['num_ratings']
		    )
		);
		add_settings_field(   
		    'mpi_added',                        
		    'Date Added',                
		    array( $this, 'checkbox_callback' ),     
		    'more-plugin-info',                           
		    'mpi_general_options_section',           
		    array(                               
		        'id' => 'mpi-settings[added]',
		  		'value' => $settings['added']
		    )
		);
		add_settings_field(   
		    'mpi_updated',                        
		    'Last Updated Date',                
		    array( $this, 'checkbox_callback' ),     
		    'more-plugin-info',                           
		    'mpi_general_options_section',           
		    array(                               
		        'id' => 'mpi-settings[updated]',
		  		'value' => $settings['updated']
		    )
		);
		add_settings_field(   
		    'mpi_requires',                        
		    'Requires Version',                
		    array( $this, 'checkbox_callback' ),     
		    'more-plugin-info',                           
		    'mpi_general_options_section',           
		    array(                               
		        'id' => 'mpi-settings[requires]',
		  		'value' => $settings['requires']
		    )
		);
		add_settings_field(   
		    'mpi_tested',                        
		    'Tested Version',                
		    array( $this, 'checkbox_callback' ),     
		    'more-plugin-info',                           
		    'mpi_general_options_section',           
		    array(                               
		        'id' => 'mpi-settings[tested]',
		  		'value' => $settings['tested']
		    )
		);
		add_settings_field(   
		    'mpi_plugin_link',                        
		    'WordPress.org Link',                
		    array( $this, 'checkbox_callback' ),     
		    'more-plugin-info',                           
		    'mpi_general_options_section',           
		    array(                               
		        'id' => 'mpi-settings[plugin_link]',
		  		'value' => $settings['plugin_link']
		    )
		);
		add_settings_field(   
		    'mpi_donate_link',                        
		    'Donate Link',                
		    array( $this, 'checkbox_callback' ),     
		    'more-plugin-info',                           
		    'mpi_general_options_section',           
		    array(                               
		        'id' => 'mpi-settings[donate_link]',
		  		'value' => $settings['donate_link']
		    )
		);
		add_settings_field(   
		    'mpi_download_link',                        
		    'Download Link',                
		    array( $this, 'checkbox_callback' ),     
		    'more-plugin-info',                           
		    'mpi_general_options_section',           
		    array(                               
		        'id' => 'mpi-settings[download_link]',
		  		'value' => $settings['download_link']
		    )
		);
		add_settings_field(   
		    'mpi_realtime',                        
		    'Enable automatic syncing',                
		    array( $this, 'checkbox_callback' ),     
		    'more-plugin-info',                           
		    'mpi_autosync_options_section',           
		    array(                               
		        'id' => 'mpi_realtime',
		  		'value' => get_option( 'mpi_realtime' )
		    )
		);
		
		register_setting( 'mpi-settings-group', 'mpi-settings' );
		register_setting( 'mpi-settings-group', 'mpi_realtime' );
	}
	
	function general_options_section_callback(){
		echo '<p>Please choose which fields you would like to be visible on the plugin listing.</p>';		
	}
	
	function autosync_options_section_callback(){
		echo '<p>Reload all plugin data every time the Plugins page loads. </p> 
			<p>Unless you only have a couple of plugins enabled, this <strong>is not recommended</strong> as it will significantly slow 
			down page load.</p>';		
	}
	
	function data_sync_section_callback(){
		echo '<p>In order to display accurate data, you should sync your plugin data from time to time. </p>
		<p>Your plugin data was last updated: <strong>'. get_option( 'mpi_sync_timestamp', 'Never' ).'</strong></p>';		
	}
	
	function checkbox_callback( $args ){
		echo "<input type='checkbox' id='$args[id]' name='$args[id]' ". checked( $args['value'], 'on', false ) .'>';
	}
	
	/**
	 * Add settings page link for this plugin
	 *
	 * @return array 
	 */
	function plugin_action_links( $links ){
	
		$settings_link = '<a href="options-general.php?page=more-plugin-info">Settings</a>'; 
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


<?php
/*
Plugin Name: More Plugin Info
Description: Displays additional information about each plugin on the Plugins page
Version: 1.0.0
Author: Mike Jordan
Author URI: http://brainstormmedia.com/
*/

add_action('init', create_function('', 'new More_Plugin_Info();'));

class More_Plugin_Info {
	
	var $plugin_meta;

	function __construct() {
		
		if(get_option('mpi_realtime',false) == true || isset($_GET['mpi_sync'])){
			add_filter( 'all_plugins', array($this, 'plugin_meta_populate') );
		}else{
			$mpi_plugin_meta = get_option('mpi_plugin_meta');
			if(!empty($mpi_plugin_meta)){
				$this->plugin_meta = unserialize($mpi_plugin_meta);
			}
		}
		
		add_filter( "plugin_row_meta", array($this, 'plugin_meta_display'), 10, 2  );
		add_action('admin_menu', array($this, 'add_menu_item'));
		
		$plugin = plugin_basename(__FILE__); 
		add_filter("plugin_action_links_$plugin", array($this, 'settings_page_link') );
		
		add_action( 'admin_notices', array( $this, 'plugin_activation' ) ) ;
	    
	}
	
	// For each plugin, use WordPress API to collect additional data and populate $plugin_meta
	function plugin_meta_populate($plugins){
		
		foreach ($plugins as $slug => $plugin){
			
			$slug = substr($slug, 0, strpos( $slug, '/'));
			
			// Thanks to http://wp.tutsplus.com/tutorials/plugins/communicating-with-the-wordpress-org-plugin-api/
			// for detailing the following WP API format
			$args = (object) array( 'slug' => $slug, 'fields' => array('sections' => false, 'tags' => false ));
			$request = array( 'action' => 'plugin_information', 'timeout' => 5, 'request' => serialize( $args) );
			$url = 'http://api.wordpress.org/plugins/info/1.0/';
			$response = wp_remote_post( $url, array( 'body' => $request ) );
			
			if (is_wp_error($response)){
				continue;
			}
			
			$plugin_info = unserialize( $response['body'] );
			
			// If plugin exists in the repo, populate $plugin_meta accordingly
			if(!empty($plugin_info)){	
				
				$plugin['requires'] = "Requires: $plugin_info->requires";
				$plugin['tested'] = "Tested: $plugin_info->tested";
				$plugin['rating'] = "Average Rating: $plugin_info->rating";
				$plugin['num_ratings'] = "# of Ratings: $plugin_info->num_ratings";
				$plugin['added'] = "Added: $plugin_info->added";
				$plugin['donate_link'] = "<a href='$plugin_info->donate_link'>Donate</a>";
				$plugin['download_link'] = "<a href='$plugin_info->download_link'>Download</a>";
				$plugin['updated'] = "Updated: $plugin_info->last_updated";
				$plugin['downloads'] = "Downloads: $plugin_info->downloaded";
				
				$this->plugin_meta[$slug] = $plugin;
			}
		}
		update_option('mpi_plugin_meta', serialize($this->plugin_meta));
		$timestamp = current_time('mysql'); 
		update_option('mpi_sync_timestamp', $timestamp);
		
		return $plugins;
	}
	
	// If data exists, display on plugin listing (when options allow)
	function plugin_meta_display($links, $slug){
		
		$slug = substr($slug, 0, strpos( $slug, '/'));	
		
		if(!empty($this->plugin_meta[$slug])){	

			if(get_option('mpi_downloads', 'on')=='on')
				array_push($links, $this->plugin_meta[$slug]['downloads']);
			if(get_option('mpi_rating', 'on')=='on')
				array_push($links, $this->plugin_meta[$slug]['rating']);
			if(get_option('mpi_num_ratings', 'on')=='on')
				array_push($links, $this->plugin_meta[$slug]['num_ratings'] );
			if(get_option('mpi_added')=='on')
				array_push($links, $this->plugin_meta[$slug]['added'] );
			if(get_option('mpi_updated')=='on')
				array_push($links, $this->plugin_meta[$slug]['updated']);
			if(get_option('mpi_requires')=='on')
				array_push($links, $this->plugin_meta[$slug]['requires'] );
			if(get_option('mpi_tested')=='on')
				array_push($links, $this->plugin_meta[$slug]['tested'] );
			if(get_option('mpi_donate_link')=='on')
				array_push($links, $this->plugin_meta[$slug]['donate_link'] );
			if(get_option('mpi_download_link')=='on')
				array_push($links, $this->plugin_meta[$slug]['download_link'] );
		}
		
		// Create filter, if users want to re-order / edit output
		apply_filters('plugin_list_meta', $links);
		
		return $links;
	}
	
	// Add menu item to settings page
	function add_menu_item(){
		add_options_page('More Plugin Info', 'More Plugin Info', 'administrator', 'mpi_settings', array($this, 'display_settings'));	    
	}
	
	// Display settings page
	function display_settings(){
		
		if(!empty($_POST['submit_check'])) {  
			?>
			<div id="message" class="updated">
				<p>Plugin information settings updated. <a href="plugins.php">Go to Plugins</a>.</p>
			</div>
			<?php
			$requires = $_POST['mpi_requires'];
	        update_option('mpi_requires', $requires);
			$tested  = $_POST['mpi_tested'];
			update_option('mpi_tested', $tested);
			$rating = $_POST['mpi_rating'];
			update_option('mpi_rating', $rating);
			$num_ratings = $_POST['mpi_num_ratings'];
			update_option('mpi_num_ratings', $num_ratings);
			$added = $_POST['mpi_added'];
			update_option('mpi_added', $added);
			$donate_link = $_POST['mpi_donate_link'];
			update_option('mpi_donate_link', $donate_link);
			$download_link = $_POST['mpi_download_link'];
			update_option('mpi_download_link', $download_link);
			$updated = $_POST['mpi_updated'];
			update_option('mpi_updated', $updated);
			$downloads = $_POST['mpi_downloads'];
			update_option('mpi_downloads', $downloads);
			$realtime = $_POST['mpi_realtime'];
			update_option('mpi_realtime', $realtime);
		}else{
			$requires  = get_option('mpi_requires');
			$tested  = get_option('mpi_tested');
			$rating  = get_option('mpi_rating', 'on');
			$num_ratings  = get_option('mpi_num_ratings', 'on');
			$added  = get_option('mpi_added');
			$donate_link  = get_option('mpi_donate_link');
			$download_link  = get_option('mpi_download_link');
			$updated  = get_option('mpi_updated');
			$downloads  = get_option('mpi_downloads', 'on');
			$realtime  = get_option('mpi_realtime');
		}   
?>
		<div class="wrap">
		<h2>More Plugin Info Settings</h2>
			<h3>Data Sync Settings</h3>
			<p>In order to display accurate data, you should sync your plugin data from time to time. </p>
			<p>Your plugin data was last updated: <strong><?php echo get_option('mpi_sync_timestamp', 'Never'); ?></strong></p>
					
			<table class="form-table">
			<tbody>	
			<form name="mpi_sync_form" method="post" action="plugins.php?mpi_sync">
				<tr valign="top">
					<td> 
						<input type="submit" name="Submit" class="button-primary" value="Update Plugin Data Now" />
						 ( This may take a couple of minutes )<p>
					</td>
				</tr>
			</form>
			</tbody>
			</table>
			
			<h3>Display Settings</h3>
			<p>
				Select which fields should appear in the plugins listing.
			</p>
			<table class="form-table">
			<tbody>	
			<form name="mpi_form" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
				<input type="hidden" name="submit_check" value="Y">  
				<tr valign="top">
					<th scope="row">
						<label for="mpi_downloads">Number of Downloads:</label>
					</th>
					<td> 
						<input type="checkbox" name="mpi_downloads" <?php if(!empty($downloads)){ echo ' checked'; } ?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="mpi_rating">Rating:</label>
					</th>
					<td> 
						<input type="checkbox" name="mpi_rating" <?php if(!empty($rating)){ echo ' checked'; } ?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="mpi_num_ratings">Number of Ratings:</label>
					</th>
					<td> 
						<input type="checkbox" name="mpi_num_ratings" <?php if(!empty($num_ratings)){ echo ' checked'; } ?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="mpi_added">Date Added:</label>
					</th>
					<td> 
						<input type="checkbox" name="mpi_added" <?php if(!empty($added)){ echo ' checked'; } ?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="mpi_updated">Last Updated Date:</label>
					</th>
					<td> 
						<input type="checkbox" name="mpi_updated" <?php if(!empty($updated)){ echo ' checked'; } ?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="mpi_requires">Requires Version:</label>
					</th>
					<td> 
						<input type="checkbox" name="mpi_requires" <?php if(!empty($requires)){ echo ' checked'; } ?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="mpi_tested">Tested Version:</label>
					</th>
					<td> 
						<input type="checkbox" name="mpi_tested" <?php if(!empty($tested)){ echo ' checked'; } ?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="mpi_donate_link">Donate Link:</label>
					</th>
					<td> 
						<input type="checkbox" name="mpi_donate_link" <?php if(!empty($donate_link)){ echo ' checked'; } ?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="mpi_download_link">Download Link:</label>
					</th>
					<td> 
						<input type="checkbox" name="mpi_download_link" <?php if(!empty($download_link)){ echo ' checked'; } ?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="mpi_realtime"><strong>Auto-sync (not recommended):</strong></label>
					</th>
					<td> 
						<input type="checkbox" name="mpi_realtime" <?php if(!empty($realtime)){ echo ' checked'; } ?> />
					</td>
				</tr>
				
				<tr valign="top">
					<td> 
						<input type="submit" name="Submit" class="button-primary" value="Save Changes" />
					</td>
				</tr>
			</form>
			</tbody>
			</table>
		</div>
					
		<?php
	}
	
	// Add settings page link for this plugin
	function settings_page_link($links){
	
		$settings_link = '<a href="options-general.php?page=mpi_settings">Settings</a>'; 
		array_unshift($links, $settings_link); 
		
		return $links;
	}
	
	// Add sync prompt on plugin activation
	function plugin_activation() {
	
		$mpi_plugin_check = get_option('mpi_sync_timestamp');
		if(empty($mpi_plugin_check)){
			$html = '<div class="updated">';
			$html .= '<p>';
			$html .= 'In order to complete your More Plugin Info setup, <a href="plugins.php?mpi_sync">please run the plugin sync</a>.';
			$html .= '<br /> This may take a couple of minutes.';
			$html .= '</p>';
			$html .= '</div>';
		}

	  echo $html;
	
	  }
}


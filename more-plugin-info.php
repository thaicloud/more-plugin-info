<?php
/*
Plugin Name: More Plugin Info
Description: Display additional information about each plugin on the Plugins screen
Version: 1.2.0
Author: Mike Jordan
*/

new MJ_More_Plugin_Info;

class MJ_More_Plugin_Info {

	/**
	 * Constructor
	 */
	public function __construct() {
		self::setup();
	}

	/**
	 * Initial setup.
	 */
	function setup() {

		// If the mpi_sync variable is in the URL then run sync
		if ( isset( $_GET['mpi_sync'] ) ) {
			add_filter( 'all_plugins', array( $this, 'plugin_meta_populate' ) );
		}

		// Display meta on the plugin listing screen
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );

		// Add a new cron interval for weekly events
		add_filter( 'cron_schedules', array( $this, 'add_weekly_cron_schedule' ) );

		// Specify which function to run when our cron event fires
		add_action( 'mpi_sync', array( $this, 'plugin_meta_populate' ) );

		// Schedule cron job
		add_action( 'admin_init', array( $this, 'setup_cron' ) );

		// Admin menu items
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// Register setting fields
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// Add the "Settings" link that appears on the plugin listing for 'More Plugin Info'
		add_filter( "plugin_action_links_" . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

		// Admin notices
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// Add wp cli command for running sync
		if ( ! class_exists('More_Plugin_Info_WP_CLI_Command' ) && defined( 'WP_CLI' ) && WP_CLI ) {
			include __DIR__ . '/includes/class-wp-cli-commands.php';
		}
	}

	/**
	 * Add a new interval of a week
	 *
	 * @param array $schedules Currently available cron intervals.
	 *
	 * @return mixed
	 */
	function add_weekly_cron_schedule( $schedules ) {
		$schedules['weekly'] = array(
			'interval' => 604800, // 1 week in seconds
			'display'  => __( 'Once Weekly' ),
		);

		return $schedules;
	}

	/**
	 * For each plugin, use WordPress API to collect additional data
	 * and populate $plugin_meta
	 *
	 * @param array $plugins Current plugins on site.
	 *
	 * @return array Extended plugin data
	 */
	function plugin_meta_populate( $plugins = '' ) {

		if ( empty( $plugins ) ) {
			$plugins = get_plugins();
		}

		$plugin_meta = array();

		foreach ( $plugins as $slug => $plugin ) {

			$slug = dirname( $slug );

			// Send API request to plugin repo to get information about this plugin
			$args     = (object) array(
				'slug'   => esc_html( $slug ),
				'fields' => array(
					'sections' => false,
					'tags'     => false
				)
			);
			$request  = array(
				'action'  => 'plugin_information',
				'timeout' => 5,
				'request' => serialize( $args )
			);
			$url      = 'http://api.wordpress.org/plugins/info/1.0/';
			$response = wp_remote_post( $url, array( 'body' => $request ) );

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$plugin_info = unserialize( $response['body'] );

			// If plugin exists in the repo then populate option accordingly
			if ( ! empty( $plugin_info ) ) {

				$plugin['requires']      = 'Requires: ' . sanitize_text_field( $plugin_info->requires );
				$plugin['tested']        = 'Tested: ' . sanitize_text_field( $plugin_info->tested );
				$plugin['rating']        = sanitize_text_field( $plugin_info->rating );
				$plugin['num_ratings']   = '# of ratings: ' . sanitize_text_field( $plugin_info->num_ratings );
				$plugin['added']         = 'Added: ' . sanitize_text_field( $plugin_info->added );
				$plugin['plugin_link']   = '<a target="_blank" href="http://wordpress.org/plugins/' . sanitize_text_field( $slug ) . '">WordPress.org page</a>';
				$plugin['donate_link']   = '<a target="_blank" href="' . sanitize_text_field( $plugin_info->donate_link ) . '">Donate</a>';
				$plugin['download_link'] = '<a target="_blank" href="' . sanitize_text_field( $plugin_info->download_link ) . '">Download</a>';
				$plugin['updated']       = 'Updated: ' . sanitize_text_field( $plugin_info->last_updated );
				$plugin['downloads']     = 'Downloads: ' . sanitize_text_field( $plugin_info->downloaded );

				$plugin_meta[ $slug ] = $plugin;
			}
		}

		// If we have valid meta then update the mpi_plugin_meta option
		if ( ! empty( $plugin_meta ) ) {
			update_option( 'mpi_plugin_meta', $plugin_meta );
			$timestamp = current_time( 'mysql' );
			update_option( 'mpi_sync_timestamp', $timestamp );
		}

		return $plugins;
	}

	/**
	 * If plugin meta exists, display on plugin listing
	 *
	 * @param array $links Meta associated with this plugin.
	 * @param array $slug  Slug of this plugin.
	 *
	 * @return array Plugin meta links / info
	 */
	function plugin_row_meta( $links, $slug ) {

		$slug = dirname( $slug );

		$mpi_plugin_meta = get_option( 'mpi_plugin_meta' );
		if ( empty( $mpi_plugin_meta ) || ! isset( $mpi_plugin_meta[ $slug ] ) ) {
			return $links;
		}

		$defaults = array(
			'downloads'   => 'on',
			'rating'      => 'on',
			'num_ratings' => 'on',
		);
		$settings = (array) get_option( 'mpi-settings', $defaults );

		if ( isset( $settings['downloads'] ) && ! empty( $settings['downloads'] ) ) {
			array_push( $links, $mpi_plugin_meta[ $slug ]['downloads'] );
		}
		if ( isset( $settings['rating'] ) && ! empty( $settings['rating'] ) ) {

			// Non-english decimal places when the $rating is coming from a string
			$rating = str_replace( ',', '.', $mpi_plugin_meta[ $slug ]['rating'] );

			// Convert Percentage to star rating, 0..5 in .5 increments
			$rating = round( $rating / 10, 0 ) / 2;

			// Calculate the number of each type of star needed
			$full_stars  = floor( $rating );
			$half_stars  = ceil( $rating - $full_stars );
			$empty_stars = 5 - $full_stars - $half_stars;

			$rating_output = '<span class="star-rating">';
			$rating_output .= str_repeat( '<div class="star star-full"></div>', $full_stars );
			$rating_output .= str_repeat( '<div class="star star-half"></div>', $half_stars );
			$rating_output .= str_repeat( '<div class="star star-empty"></div>', $empty_stars );
			$rating_output .= '</span>';

			array_push( $links, $rating_output );

		}
		if ( isset( $settings['num_ratings'] ) && ! empty( $settings['num_ratings'] ) ) {
			array_push( $links, $mpi_plugin_meta[ $slug ]['num_ratings'] );
		}
		if ( isset( $settings['added'] ) && ! empty( $settings['added'] ) ) {
			array_push( $links, $mpi_plugin_meta[ $slug ]['added'] );
		}
		if ( isset( $settings['updated'] ) && ! empty( $settings['updated'] ) ) {
			array_push( $links, $mpi_plugin_meta[ $slug ]['updated'] );
		}
		if ( isset( $settings['requires'] ) && ! empty( $settings['requires'] ) ) {
			array_push( $links, $mpi_plugin_meta[ $slug ]['requires'] );
		}
		if ( isset( $settings['tested'] ) && ! empty( $settings['tested'] ) ) {
			array_push( $links, $mpi_plugin_meta[ $slug ]['tested'] );
		}
		if ( isset( $settings['plugin_link'] ) && ! empty( $settings['plugin_link'] ) ) {
			array_push( $links, $mpi_plugin_meta[ $slug ]['plugin_link'] );
		}
		if ( isset( $settings['donate_link'] ) && ! empty( $settings['donate_link'] ) ) {
			array_push( $links, $mpi_plugin_meta[ $slug ]['donate_link'] );
		}
		if ( isset( $settings['download_link'] ) && ! empty( $settings['download_link'] ) ) {
			array_push( $links, $mpi_plugin_meta[ $slug ]['download_link'] );
		}

		// This filter allows re-order and/or modification of final output on plugin listed
		apply_filters( 'plugin_list_meta', $links );

		return $links;
	}

	/**
	 * Add settings menu
	 */
	function admin_menu() {
		add_options_page( 'More Plugin Info', 'More Plugin Info', 'administrator', 'more-plugin-info', array(
			$this,
			'display_settings'
		) );
	}

	/**
	 * Display settings page
	 */
	function display_settings() {
		echo '<div class="wrap">';
		echo '<h2>More Plugin Info</h2>';
		echo '<form name="mpi_sync_form" method="post" action="plugins.php?mpi_sync">';
		echo '<p>By default, plugin info will automatically be updated once per week. </p>
		<p>Your plugin data was last updated: <strong>' . get_option( 'mpi_sync_timestamp', 'Never' ) . '</strong></p>';
		submit_button( 'Sync Plugin Data Now' );
		echo '</form>';
		echo '<form name="mpi_form" method="post" action="options.php">';
		settings_fields( 'mpi-settings-group' );
		do_settings_sections( 'more-plugin-info' );
		submit_button( 'Save Changes' );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * If scheduled syncs are enabled then scheduled event
	 */
	function setup_cron() {

		$mpi_sync = wp_next_scheduled( 'mpi_sync' );
		if ( ! $mpi_sync ) {
			$cron_enabled = get_option( 'mpi_cron_enable', 'on' );
			if ( 'on' === $cron_enabled ) {
				$mpi_sync_frequency = apply_filters( 'mpi_sync_frequency', 'weekly' );
				wp_schedule_event( time(), esc_html( $mpi_sync_frequency ), 'mpi_sync' );
			} else {
				$timestamp = wp_next_scheduled( 'mpi_sync' );
				wp_unschedule_event( $timestamp, 'mpi_sync' );
			}
		}
	}

	/**
	 * Initialize components of settings page
	 */
	function admin_init() {

		$defaults = array(
			'downloads'     => 'on',
			'rating'        => 'on',
			'num_ratings'   => 'on',
			'added'         => '',
			'updated'       => '',
			'requires'      => '',
			'tested'        => '',
			'plugin_link'   => '',
			'donate_link'   => '',
			'download_link' => '',
		);
		$settings = get_option( 'mpi-settings', $defaults );

		if ( empty( $settings ) || ! is_array( $settings ) ) {
			return;
		}

		add_settings_section(
			'mpi_general_options_section',
			'General Options',
			array( $this, 'general_options_section_callback' ),
			'more-plugin-info'
		);

		add_settings_section(
			'mpi_autosync_options_section',
			'Auto-Update Plugin Data',
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
				'id'    => 'mpi-settings[downloads]',
				'value' => isset( $settings['downloads'] ) ? sanitize_text_field( $settings['downloads'] ) : '',
			)
		);
		add_settings_field(
			'mpi_rating',
			'Rating',
			array( $this, 'checkbox_callback' ),
			'more-plugin-info',
			'mpi_general_options_section',
			array(
				'id'    => 'mpi-settings[rating]',
				'value' => isset( $settings['rating'] ) ? sanitize_text_field( $settings['rating'] ) : '',
			)
		);
		add_settings_field(
			'mpi_num_ratings',
			'Number of Ratings',
			array( $this, 'checkbox_callback' ),
			'more-plugin-info',
			'mpi_general_options_section',
			array(
				'id'    => 'mpi-settings[num_ratings]',
				'value' => isset( $settings['num_ratings'] ) ? sanitize_text_field( $settings['num_ratings'] ) : '',
			)
		);
		add_settings_field(
			'mpi_added',
			'Date Added',
			array( $this, 'checkbox_callback' ),
			'more-plugin-info',
			'mpi_general_options_section',
			array(
				'id'    => 'mpi-settings[added]',
				'value' => isset( $settings['added'] ) ? sanitize_text_field( $settings['added'] ) : '',
			)
		);
		add_settings_field(
			'mpi_updated',
			'Last Updated Date',
			array( $this, 'checkbox_callback' ),
			'more-plugin-info',
			'mpi_general_options_section',
			array(
				'id'    => 'mpi-settings[updated]',
				'value' => isset( $settings['updated'] ) ? sanitize_text_field( $settings['updated'] ) : '',
			)
		);
		add_settings_field(
			'mpi_requires',
			'Requires Version',
			array( $this, 'checkbox_callback' ),
			'more-plugin-info',
			'mpi_general_options_section',
			array(
				'id'    => 'mpi-settings[requires]',
				'value' => isset( $settings['requires'] ) ? sanitize_text_field( $settings['requires'] ) : '',
			)
		);
		add_settings_field(
			'mpi_tested',
			'Tested Version',
			array( $this, 'checkbox_callback' ),
			'more-plugin-info',
			'mpi_general_options_section',
			array(
				'id'    => 'mpi-settings[tested]',
				'value' => isset( $settings['tested'] ) ? sanitize_text_field( $settings['tested'] ) : '',
			)
		);
		add_settings_field(
			'mpi_plugin_link',
			'WordPress.org Link',
			array( $this, 'checkbox_callback' ),
			'more-plugin-info',
			'mpi_general_options_section',
			array(
				'id'    => 'mpi-settings[plugin_link]',
				'value' => isset( $settings['plugin_link'] ) ? sanitize_text_field( $settings['plugin_link'] ) : '',
			)
		);
		add_settings_field(
			'mpi_donate_link',
			'Donate Link',
			array( $this, 'checkbox_callback' ),
			'more-plugin-info',
			'mpi_general_options_section',
			array(
				'id'    => 'mpi-settings[donate_link]',
				'value' => isset( $settings['donate_link'] ) ? sanitize_text_field( $settings['donate_link'] ) : '',
			)
		);
		add_settings_field(
			'mpi_download_link',
			'Download Link',
			array( $this, 'checkbox_callback' ),
			'more-plugin-info',
			'mpi_general_options_section',
			array(
				'id'    => 'mpi-settings[download_link]',
				'value' => isset( $settings['download_link'] ) ? sanitize_text_field( $settings['download_link'] ) : '',
			)
		);
		add_settings_field(
			'mpi_cron_enable',
			'Enable cron job',
			array( $this, 'checkbox_callback' ),
			'more-plugin-info',
			'mpi_autosync_options_section',
			array(
				'id'    => 'mpi_cron_enable',
				'value' => sanitize_text_field( get_option( 'mpi_cron_enable', 'on' ) ),
			)
		);

		register_setting( 'mpi-settings-group', 'mpi-settings' );
		register_setting( 'mpi-settings-group', 'mpi_cron_enable' );
	}

	function general_options_section_callback() {
		echo '<p>Please choose which fields you would like to be visible on the plugin listing.</p>';
	}

	function autosync_options_section_callback() {
		echo '<p>Schedule a cron job to run once per week. </p>
			<p>Enabling this option is the best way to ensure that your plugin info is always up-to-date.</p>';
	}

	function checkbox_callback( $args ) {
		echo '<input type="checkbox" id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['id'] ) . '" ' . checked( $args['value'], 'on', false ) . '">';
	}

	/**
	 * Add settings page link for this plugin
	 *
	 * @param array $links All meta info associated with this plugin.
	 *
	 * @return array
	 */
	function plugin_action_links( $links ) {

		$settings_link = '<a href="options-general.php?page=more-plugin-info">Settings</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Add sync prompt on plugin activation
	 */
	function admin_notices() {

		$mpi_plugin_check = get_option( 'mpi_sync_timestamp' );
		if ( empty( $mpi_plugin_check ) ) {
			?>
			<div class="updated">
				<p>In order to complete your More Plugin Info setup,
					<a href="plugins.php?mpi_sync">please run the initial plugin sync</a>.<br />
					This may take a couple of minutes.</p>
			</div>
			<?php
		}
	}
}

// Clean up when deactivated
register_uninstall_hook( __FILE__, 'mj_mpi_uninstall' );

/**
 * Remove options & cron on plugin uninstall
 */
function mj_mpi_uninstall() {

	// Delete options
	delete_option( 'mpi_plugin_meta' );
	delete_option( 'mpi_sync_timestamp' );

	// Unschedule any outstanding cronjobs
	$timestamp = wp_next_scheduled( 'mpi_sync' );
	wp_unschedule_event( $timestamp, 'mpi_sync' );
}

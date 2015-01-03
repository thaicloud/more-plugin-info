<?php
/*
Plugin Name: More Plugin Info
Description: Display additional information about each plugin on the Plugins page
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
		if ( ! self::$instance ) {
			$message = '<code>' . __CLASS__ . '</code> is a singleton.<br/> Please get an instantiate it with <code>' . __CLASS__ . '::get_instance();</code>';
			wp_die( $message );
		}
	}

	public static function get_instance() {
		if ( ! is_a( self::$instance, __CLASS__ ) ) {
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

		add_filter( 'cron_schedules', array( $this, 'add_weekly_cron_schedule' ) );

		$cron_enabled = get_option( 'mpi_cron_enable', 'on' );
		$mpi_sync_frequency = apply_filters( 'mpi_sync_frequency', 'weekly' );
		$mpi_sync = wp_next_scheduled( 'mpi_sync' );

		if ( ! $mpi_sync && 'on' === $cron_enabled ) {
			wp_schedule_event( time(), esc_html( $mpi_sync_frequency ), 'mpi_sync' );
		}else if ( ! empty( $mpi_sync ) && 'on' != $cron_enabled ){
			$timestamp = wp_next_scheduled( 'mpi_sync' );
			wp_unschedule_event( $timestamp, 'mpi_sync' );
		}

		add_action( 'mpi_sync', array( $this, 'plugin_meta_populate' ) );

		if ( isset( $_GET['mpi_sync'] ) ) {
			add_filter( 'all_plugins', array( $this, 'plugin_meta_populate' ) );
		} else {
			$mpi_plugin_meta = get_option( 'mpi_plugin_meta' );
			if ( ! empty( $mpi_plugin_meta ) ) {
				$this->plugin_meta = $mpi_plugin_meta;
			}
		}

		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		$plugin_basename = plugin_basename( __FILE__ );
		add_filter( "plugin_action_links_$plugin_basename", array( $this, 'plugin_action_links' ) );

		add_action( 'admin_init', array( $this, 'admin_init' ) );

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

	}

	// Add a new interval of a week
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
	 * @return array Extended plugin data
	 */
	function plugin_meta_populate( $plugins ) {

		if ( empty( $plugins ) ) {
			$plugins = get_plugins();
		}

		foreach ( $plugins as $slug => $plugin ) {

			$slug = dirname( $slug );

			// Thanks to http://wp.tutsplus.com/tutorials/plugins/communicating-with-the-wordpress-org-plugin-api/
			// for detailing the following WP API format
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

			// If plugin exists in the repo, populate $plugin_meta accordingly
			if ( ! empty( $plugin_info ) ) {

				$plugin['requires']      = 'Requires: ' . sanitize_text_field( $plugin_info->requires );
				$plugin['tested']        = 'Tested: ' . sanitize_text_field( $plugin_info->tested );
				$plugin['rating']        = 'Average rating: ' . sanitize_text_field( $plugin_info->rating );
				$plugin['num_ratings']   = '# of ratings: ' . sanitize_text_field( $plugin_info->num_ratings );
				$plugin['added']         = 'Added: ' . sanitize_text_field( $plugin_info->added );
				$plugin['plugin_link']   = '<a target="_blank" href="http://wordpress.org/plugins/' . sanitize_text_field( $slug ) . '">WordPress.org page</a>';
				$plugin['donate_link']   = '<a target="_blank" href="' . sanitize_text_field( $plugin_info->donate_link ) . '">Donate</a>';
				$plugin['download_link'] = '<a target="_blank" href="' . sanitize_text_field( $plugin_info->download_link ) . '">Download</a>';
				$plugin['updated']       = 'Updated: ' . sanitize_text_field( $plugin_info->last_updated );
				$plugin['downloads']     = 'Downloads: ' . sanitize_text_field( $plugin_info->downloaded );

				$this->plugin_meta[ $slug ] = $plugin;

				update_option( 'mpi_plugin_meta', $this->plugin_meta );
				$timestamp = current_time( 'mysql' );
				update_option( 'mpi_sync_timestamp', $timestamp );
			}
		}

		return $plugins;
	}

	/**
	 * If data exists, display on plugin listing (when options allow)
	 *
	 * @return array Plugin meta links / info
	 */
	function plugin_row_meta( $links, $slug ) {

		$slug = dirname( $slug );

		if ( ! empty( $this->plugin_meta[ $slug ] ) ) {

			$defaults = array(
				'downloads'   => 'on',
				'rating'      => 'on',
				'num_ratings' => 'on',
			);
			$settings = (array) get_option( 'mpi-settings', $defaults );

			if ( $settings['downloads'] ) {
				array_push( $links, $this->plugin_meta[ $slug ]['downloads'] );
			}
			if ( $settings['rating'] ) {
				array_push( $links, $this->plugin_meta[ $slug ]['rating'] );
			}
			if ( $settings['num_ratings'] ) {
				array_push( $links, $this->plugin_meta[ $slug ]['num_ratings'] );
			}
			if ( $settings['added'] ) {
				array_push( $links, $this->plugin_meta[ $slug ]['added'] );
			}
			if ( $settings['updated'] ) {
				array_push( $links, $this->plugin_meta[ $slug ]['updated'] );
			}
			if ( $settings['requires'] ) {
				array_push( $links, $this->plugin_meta[ $slug ]['requires'] );
			}
			if ( $settings['tested'] ) {
				array_push( $links, $this->plugin_meta[ $slug ]['tested'] );
			}
			if ( $settings['plugin_link'] ) {
				array_push( $links, $this->plugin_meta[ $slug ]['plugin_link'] );
			}
			if ( $settings['donate_link'] ) {
				array_push( $links, $this->plugin_meta[ $slug ]['donate_link'] );
			}
			if ( $settings['download_link'] ) {
				array_push( $links, $this->plugin_meta[ $slug ]['download_link'] );
			}
		}

		// Re-order and/or modify final output in each plugin listed
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
	function admin_init() {

		$defaults = array(
			'downloads'   => 'on',
			'rating'      => 'on',
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
				'value' => sanitize_text_field( $settings['downloads'] ),
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
				'value' => sanitize_text_field( $settings['rating'] ),
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
				'value' => sanitize_text_field( $settings['num_ratings'] ),
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
				'value' => sanitize_text_field( $settings['added'] ),
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
				'value' => sanitize_text_field( $settings['updated'] ),
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
				'value' => sanitize_text_field( $settings['requires'] )
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
				'value' => sanitize_text_field( $settings['tested'] ),
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
				'value' => sanitize_text_field( $settings['plugin_link'] ),
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
				'value' => sanitize_text_field( $settings['donate_link'] ),
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
				'value' => sanitize_text_field( $settings['download_link'] ),
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

register_uninstall_hook( __FILE__, 'mj_mpi_uninstall' );

/**
 * Remove options & cron on plugin uninstall
 */
function mj_mpi_uninstall() {

	// Delete options
	delete_option( 'mpi_realtime' );
	delete_option( 'mpi_plugin_meta' );
	delete_option( 'mpi_sync_timestamp' );

	// Unschedule any outstanding cronjobs
	$timestamp = wp_next_scheduled( 'mpi_sync' );
	wp_unschedule_event( $timestamp, 'mpi_sync' );
}
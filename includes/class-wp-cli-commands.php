<?php

/**
 * wp-cli integration
 */

WP_CLI::add_command( 'more-plugin-info', 'More_Plugin_Info_WP_CLI_Command' );

class More_Plugin_Info_WP_CLI_Command extends WP_CLI_Command {

	/**
	 * Pull info about installed plugins from wordpress.org
	 *
	 * @subcommand sync
	 */
	public function sync() {

		$results = array();
		$plugins = MJ_More_Plugin_Info::plugin_meta_populate();

		if ( ! is_array( $plugins ) ) {
			WP_CLI::error( "Oops, there has been an error. :(" );
		}

		foreach ( $plugins as $key => $plugin ) {
			$slug = dirname( $key );
			if ( ! in_array( $slug, $results ) && ! empty( $slug ) && $slug != '.' ) {
				WP_CLI::line( esc_html( $slug ) . " meta has been updated." );
				array_push( $results, $slug );
			}
		}

		WP_CLI::success( "All done!" );

		return;

	}
}




<?php
// If uninstall not called from WordPress exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit();

// Delete options
delete_option( 'mpi_realtime' );
delete_option( 'mpi_plugin_meta' );
delete_option( 'mpi_sync_timestamp' );

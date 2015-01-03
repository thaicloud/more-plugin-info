=== More Plugin Info ===
Contributors: Thaicloud
Tags: plugin, meta, ratings, downloads
Author URI: http://knowmike.com
Plugin URI: http://wordpress.org/plugins/more-plugin-info/
Requires at least: 3.0
Tested up to: 4.1.0
Stable tag: 1.1.0

Connects to the WordPress.org Plugin API to display additional plugin information about
installed plugins, such as number of downloads and rating.

== Description ==

Hooks into the plugin listing to display relevant information about installed plugins, and provides a setting page for selecting which fields should be shown.

To change the plugin settings, go to Settings > More Plugin Info and select which checkboxes you would like to be included. This information may be seen on the Plugins page, at the bottom of each plugin listed.

== Installation ==

1. Upload the `more-plugin-info` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > More Plugin Info and click on the 'Update Plugin Data Now' button to pull data from WordPress.org

== Screenshots == 

1. An example plugin as seen on the plugin listing page; note the 'Average Rating' and 'Number of Ratings' values. Many additional values may be shown as well. 
2. The More Plugin Info settings page permits field toggle, as well as auto or manual sync down of plugin data from WordPress.org. 

== Changelog ==

= 1.1.0 =
* Improve security: add escape & sanitization functions

= 1.0.4 =
* Fix cron enable/disable behavior

= 1.0.3 =
* Schedule weekly cron for plugin data sync
* Add filter for changing cron frequency
* Remove auto-sync option for each load of plugins screen
* register_uninstall_hook function for removing options on uninstall
* Update readme

= 1.0.2 =
* Add option for link to wordpress.org plugin page

= 1.0.1 =
* Header error fix

= 1.0.0 =
* Initial release


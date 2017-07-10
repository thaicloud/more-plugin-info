=== More Plugin Info ===
Contributors: Thaicloud
Donate link: https://action.aclu.org/donate-aclu
Tags: plugin, meta, ratings, downloads
Author URI: http://knowmike.com
Plugin URI: http://wordpress.org/plugins/more-plugin-info/
Requires at least: 3.0
Tested up to: 4.8
Stable tag: 1.2.0

Connects to WordPress.org, using the Plugin API, to display additional plugin information about installed plugins, such as # of downloads and ratings.

== Description ==

Ever wish that the plugins page on your WordPress site had more information about the plugins that you have installed?

You shouldn't have to open up another tab and search for the plugin on wordpress.org just to find out things like:
When was it last updated? How many ratings does it have? What version of WordPress has it been tested up to?

This plugin resolves that issue- it grabs details about all of the plugins on your site from wordpress.org, and displays them right there underneath each plugin's description. It also includes a settings page, so you can choose which information you want to be shown.

There aren't a lot of unnecessary bells and whistles here- this is a secure, clean-cut way to customize the information shown on your plugins page.

Enjoy!

NEW: WP CLI Support- plugin data can now be manually updated by running 'wp more-plugin-info sync'

== Installation ==

1. Upload the `more-plugin-info` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > More Plugin Info and click on the 'Update Plugin Data Now' button to pull data from WordPress.org

== Screenshots ==

1. An example plugin as seen on the plugin listing page; note the newly added number of downloads and star rating. Many additional values may be shown as well.
2. The More Plugin Info settings page permits field toggle, as well as auto or manual sync down of plugin data from WordPress.org.

== Changelog ==

= 1.2.0 =
* Fix debug warnings & more performant code

= 1.1.2 =
* Add wp-cli command for syncing down plugin info

= 1.1.1 =
* Rating info shown as stars

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


== Upgrade Notice ==

= 1.2.0 =
* Upgrade to resolve PHP warnings & more performant code
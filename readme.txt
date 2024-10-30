=== Simple Download with password ===
Contributors: enomoto celtislab
Tags: download manager, password, protect download, secure, logging
Requires at least: 6.2
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.8.0
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html


== Description ==

Simple, easy, lightweight download manager with password protection


== Features ==

This download management plugin is designed to be light and easy to use.

1. Basically all downloads are password protected. This also prevents downloads by bots etc.
2. A single shortcode and download URL are created as the download data.
3. All types of files for download can be uploaded with the Media Uploader if the user has administrative privileges.

Functions added by addons

1. Generate a password with an expiration date.
2. Information and Update manager for WordPress themes and plugins not registered on the WordPress official website.


== How to use ==

If you want to download from your own site's posting page, etc., write the shortcode [celtisdl_download id="123"] in the content.
If you want to send download information via email, etc., send the download URL and password to the recipient.

There is no functionality to style the download button in various ways, but you can use filter hooks to change the style of the button.
Alternatively, if you are using a block editor, you can set the download URL in the link of the button block and set the button style in the block editor.

The plugin requires a download password, but there are two ways to bypass this requirement.

1. For example, if you want to download from a page such as a checkout page without requiring a password, you can register the URL of that checkout page and download without entering a password.
2. Logged-in users can download without entering a password if they have the registered user capability.



Please see the following page for details

[Documentation](https://celtislab.net/en/wp-plugin-celtis-simple-download/ )


== Installation ==

1. Upload the `celtis-simple-download` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Open the setting menu, it is possible to set the execution condition.

* File upload size limit

If you want to increase the file upload size limit, you will need to change settings such as php.ini, so please contact the hosting service or server administrator you are using.


== customize ==

Filter hooks for main customization

shortcode download button custom style filter hook
apply_filters( 'celtisdl_download_button_custom', $html, $post )

password form custom style filter hook
apply_filters( 'celtisdl_password_form_custom', $output, $post )

log view custom filter hook
apply_filters( 'celtisdl_stat_content_custom', $shtml, $stat )
apply_filters( 'celtisdl_log_content_custom', $shtml, $log )


== Changelog ==

= 0.8.0 =
* 2024-5-21
* updated sqlite-utils

= 0.7.0 =
* 2024-4-23
* updated sqlite-utils

= 0.6.0 =
* 2024-4-1
* WordPress 6.5 tested
* Supports WooCommerce download URL settings

= 0.5.2 =
* 2024-3-16
* Added processing to execute ob_end_clean() to clear the output buffer before executing wp_send_json().

= 0.5.1 =
* 2024-3-11
* Rewrite SQLite-related processing used in logs from pdo to direct SQLite3 operations.

= 0.4.3 =
* 2024-2-26
* add Prevent downloads from domain function
* refactoring the sanitization process

= 0.3.1 =
* 2024-2-14
* First release

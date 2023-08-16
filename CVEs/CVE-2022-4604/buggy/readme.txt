=== English WordPress Admin ===
Contributors: khromov
Tags: english, wpml, multilanguage
Requires at least: 3.5
Tested up to: 5.3
Stable tag: 1.5.1
License: GPL2

Lets users change their administration language to English

== Description ==
This plugin lets users change their administration language to native English (en_US locale).

This is useful during site development and for people more accustomed to the english administration panel, even if your site
is in another language. (The frontend will still use the native language.)

This plugin is fully compatible with WooCommerce and can correctly identify and translate frontend / backend AJAX requests.
If you are using this plugin with Advanced Custom Fields, please move the english-wp-admin.php file to the /wp-content/mu-plugins/ folder
(create it if it does not exist). This is an ACF limitation.

This plugin is developer friendly and small (~200 lines of code). Check the FAQ for customization examples.

**Usage**

*Basic usage*

Once you have installed and activated the plugin, navigate to any admin page and check the top admin bar. A button will
display your current locale. If you click on it, the admin will change to English locale (en_US). To switch back,
press the button again.

== Requirements ==
* PHP 5.3 or higher

== Translations ==
* None

== Installation ==
1. Upload the `english-wp-admin` folder to `/wp-content/plugins/`
2. Activate the plugin (English WordPress Admin) through the 'Plugins' menu in WordPress
3. Use the functionality via the admin bar

== Frequently Asked Questions ==

= Some plugins are still in the native language when switching to English =

To fix this, move the file /wp-content/plugins/english-wp-admin/english-wp-admin.php to /wp-content/mu-plugins/

This will ensure this plugin is loaded before all other plugins and that it sets the correct language. This is a WordPress restriction.

= Why are some URLs whitelisted? =

update-core.php is whitelisted because translation updates do not work properly if you change locale on that screen.

options-general.php is whitelisted due to Trac issue #31318 and #29362

https://core.trac.wordpress.org/ticket/31318
https://core.trac.wordpress.org/ticket/29362

= How do I whitelist a specific page from being translated =

Use the english_wordpress_admin_whitelist filter. It takes a preg-style regular expression.

    /** Whitelist /wp-admin/options-general.php?page=my_plugin **/
    add_filter('english_wordpress_admin_whitelist', function($whitelisted_urls)
    {
        $whitelisted_urls[] = '.*\/wp-admin\/options-general.php\?page=my_page$';
        return $whitelisted_urls;
    });

= How do I prevent regular users from having the option of changing the admin language? =

If you only want the first admin user to have this option, put this code in your themes function.php file:

    /** Only allow the admin user to change the admin language **/
    if(get_current_user_id() === 1) {
        add_filter('english_wordpress_admin_show_admin_bar', '__return_true');
    }
    else {
        add_filter('english_wordpress_admin_show_admin_bar', '__return_false');
    }

= How do I automatically enable this plugin for certain users? =

Use the snippet below to have admins always use the admin page in english.

    /** Enable the plugin automatically for admin users */
    if(current_user_can('manage_options')) {
        global $english_wordpress_admin_plugin;
        $english_wordpress_admin_plugin->set_cookie(1);
        add_filter('english_wordpress_admin_show_admin_bar', '__return_false');
    }

= This plugin does not solve my needs =

You can leave feature requests in the plugin support forum.

== Screenshots ==

1. The plugin admin bar

== Changelog ==

= 1.5.1 =

* Fix for PHP 5.2
* Fix for whitelisting edge case
* Code formatting touchup

= 1.5.0 =

* Fixed a bug where some plugin options pages had the native language despite switching to english
* More robust AJAX detection (properly separates frontend / backend AJAX)
* Better notifications whe visiting a whitelisted URL
* Performance fixes (Utilizing Object Cache)
* Improved ACF, WooCommerce and WPML support

= 1.4.1 =
* Minor tweaks

= 1.4 =
* Rewritten and improved frontend AJAX detection
* Added proper WPML detection
* Fixed broken language auto-update
* Fixed WooCommerce emails sent in wrong language
* Fixed bug in Settings > General admin page detection
* GitHub updater support
* Introduced URL whitelist (with new filter: english_wordpress_admin_whitelist)

= 1.4 =
* Rewritten and improved frontend AJAX detection
* Added proper WPML detection
* Fixed broken language auto-update
* Fixed WooCommerce emails sent in wrong language
* Fixed bug in Settings > General admin page detection
* GitHub updater support
* Introduced URL whitelist (with new filter: english_wordpress_admin_whitelist)

= 1.3.2 =
* Blacklisted changing language on Settings > General admin page until https://core.trac.wordpress.org/ticket/29362#comment:5 is fixed.

= 1.3.1 =
* Fixed an edge case where the "You only have English language installed." 
message would appear erroneously.

= 1.3.0 =
* When changing language, you are now sent back to the page you were originally on instead of being reverted to the dashboard.
* Fixed bug with disappearing top menu icon
* Added notice for WPML users

= 1.2.1 =
* Fix notice level error when COOKIEHASH is not defined (Certain MultiSite installations)

= 1.1 =
* Fix notice level error when WPLANG is not defined
* Added notification for sites that only have English language installed

= 1.0 =
* Initial release

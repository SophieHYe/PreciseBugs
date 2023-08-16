=== Backend Localization ===
Contributors: Kau-Boy
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=7914504
Tags: admin, backend, localization, english, l10n, i18n, translations, translation
Requires at least: 3.2
Stable tag: 2.0

This plugin enables you to run your blog in a different language than the backend of your blog. So you can serve your blog using e.g. German as the default language for the users, but keep English as the language for the administration. 

== Description ==

This plugin enables you to run your blog in a different language than the backend of your blog. So you can serve your blog using e.g. German as the default language for the users, but keep English as the language for the administration.
You can choose the language you want to use from all installed language files or you can install additional languages.

A list of all of my plugins can be found on the [WordPress Plugin page](http://kau-boys.com/wordpress-plugins "WordPress Plugins") on my blog [kau-boys.com](http://kau-boys.com). 

== Screenshots ==

1. Screenshot of the settings page
2. Screenshot of language switcher in admin menu (similar to qTranslate switcher)
3. Screenshot of language selection on login form

== Installation ==

= Installation through WordPress admin pages: = 

1. Go to the admin page `Plugins -> Add New`
2. Search for `kau-boy` and choose the plugin
3. Choose the action `install`
4. Click on `Install now`
5. Activate the plugin after install has finished (with the link or trough the plugin page)
6. You might have to edit the settings, especially the language you want to use

= Installation using WordPress admin pages: =

1. Download the plugin zip file
2. Go to the admin page `Plugins -> Add New`
3. Choose the `Upload` link under the `Install Plugins` headline
4. Browse for the zip file and click `Install Now`
5. Activate the plugin after install has finished (with the link or trough the plugin page)
6. You might have to edit the settings, especially the language you want to use

= Installation using ftp: =

1. Unzip und upload the files to your `/wp-content/plugins/` directory
2. Activate the plugin through the `Plugins` menu in WordPress
3. You might have to edit the settings, especially the language you want to use



== Frequently Asked Questions ==

= Will my language be available for this plugin? =

Every language that is supported by WordPress can be chosen. You just need a copy of the translation files in your WordPress' language folder.

= Do I need this plugin if I use qTranslate? = 

No you don't have to. I love qTranslate and use it for my own blog. qTranslate also offers the ability to switch the backend language. But if you don't want to publish the content of your blog in more than one language, you shouldn't use qTranslate. For I created this plugin for users who only wan't to switch the backend language without the overhead of any multilingual plugin. 

   
== Change Log ==

* **2.0.1** Fixing shown language in the switching message. Use htmlspecialchars() to prevent XSS vulnerabilities. Thanks again to Matt Fuller for teaching me how to do it correctly!
* **2.0** Adding some new languages. Fixing link to switch languages in backend. Thanks to Justin! Fixing XSS vulnerabilities. Many Thanks to Matt Fuller from MOZILLA!
* **1.6.1** Fixing typo in language names 
* **1.6** Add WP3 CSS class for the language select on the login form
* **1.5** Fixing the plugin for WordPress 3.0 MULTISITE feature
* **1.4** Fixing the plugin for WordPress MU and fixing the login form selection
* **1.3** Make the plugin working with PHP4. Thanks to David for the fix: www.est322.com/?p=520
* **1.2.1** Replace do_action() with add_action() for admin_menu to avoid issues with other plugins
* **1.2** Adding support for new languages 
* **1.1** Removing "short open tags" which causes error on blog that don't have "short_open_tag" set to "On"
* **1.0** Adding language switcher to admin menu and option to hide language selection on login form 
* **0.6** Saving language setting in cookie to enable different languages for multiple users
* **0.5** Adding language selection to login screen
* **0.4** Display all languages that are installed in the WordPress language folder 
* **0.3** Activate new language after saving settings (no more need to refresh)
* **0.2** Adding German translation for settings page
* **0.1** First stable release
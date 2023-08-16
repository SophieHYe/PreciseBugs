=== Simplr Registration Form Plus+ ===
Contributors: mpvanwinkle77, mpol
Donate link: http://www.mikevanwinkle.com/
Tags: registration, signup, profile, cms, users, user management, user profile
Requires at least: 3.0
Tested up to: 4.2.1
Stable tag: 2.3.5

This plugin allows users to easily add a custom user registration form anywhere on their site using simple shortcode.

== Description ==
**NEW FEATURES**
The Plus version of this plugin is now free! It includes reCAPTCHA, Facebook Connect, Custom Field UI, Moderation, Custom Confirmation messages. More info at http://mikevanwinkle.com/simplr-registration-form-plus/

This plugin creates a user interface for adding custom registration forms to any post or page on your WordPress website. Simply navigate to the edit page for post or page on which you would like the form to appear and click the registration forms button. A popup menu will appear guiding you through the options.

The plugin also creates an interface for adding/removing fields to be used in the registration form.

== Installation ==

1. Download `simplr-registration-form-plus.zip` and upload contents to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Insert forms using the TinyMCE registration button.

== Frequently Asked Questions ==

See plugin settings page for detailed instructions.

= When I click the Add Registration Form button, nothing happens. =

It is likely that you have Javascript errors in your website.
You can open the inspector of your browser, and check your messages in the console tab.

= I added a Date field, but I don't see any years. =

When you edit the field, you can give options along for the years, like '2000,2015'.

= License =

This plugin is licensed under the GPL.

For the banners we credit:

* Woman in black/yellow [Yoshiaka](http://www.freeimages.com/photo/1276430)
* Sculptures [xxl7](http://www.freeimages.com/photo/1433708)
* Toy Soldier [Zela](http://www.freeimages.com/photo/1418049)

== Screenshots ==

1. The page with the registration form on the frontend.
2. The settings page with the first tab open.
3. The modal where you can customize the shortcode that will be entered.

== Changelog ==

= 2.3.5 =
* properly escape add_query_arg inputs

= 2.3.4 =
* 2015-04-18
* Really use the Email From address for emails.

= 2.3.3 =
* 2015-04-17
* Don't include registration.php on current WordPress.
* And even more fixes...

= 2.3.2 =
* 2015-04-17
* Have defaults for years in date dropdown.
* Fix even more notices (thanks dvdm).

= 2.3.1 =
* 2015-04-17
* Fix more notices (thanks dvdm).

= 2.3.0 =
* 2015-04-07
* Make notices (messages) dismissable in WP 4.2.
* Fix PHP warnings and notices.

= 2.2.9 =
* 2015-03-27
* Fix bug when no activeEditor, add to textarea#content instead.
* Load js after html in simplr_reg_options.
* Fix typo in MCE button.
* Use different onclick listener, close.on() seems to break in IE11.
* Use correct name attributes, Firefox prefers that.
* Check capability in simplr_reg_options.
* Add button doesn't need a href value.

= 2.2.8 =
* Add settings link to main plugin page.
* Fix default field sort (first_name and last_name should be in it).
* Add username to activation-succes email.
* Add translation files and load them.
* Add nl_NL.

= 2.2.7 =
* updated modal to work with WordPress version 4.1

= 2.2.6 =
* remove references to PluginUpdate class

= 2.2.5 =
* fix backward compatibility and sreg.class.php error

= 2.2.4 =
* Fix "undefined" notices
* Fix incompatibility with login_message filter

= 2.2.3 =
* Fix moderation login bug
* Add 'simplr_activated_user' action

= 2.2.2 =
* Fix moderation comments and default email.

= 2.2.1 =
* Fix for PHP 5.2 backward compatibility

= 2.2.0 =
* bugfix: namespace the form class
* bugfix: silence some php undefined var errors
* feature: add moderation
* feature: allow user submitted vars in confirmation email
* enhancement: use new wp*media*modal instead of thickbox
* feature: integrate custom fields with admin tables

= 2.1.11 =
* bugfix: callback function bugfix

= 2.1.10 =
* bugfix: update tinymce button to accomodate wordpress 3.5

= 2.1.8.4 =
* bugfix: Thank you page routing
* bugfix: Recaptcha save

= 2.1.8.2 =
* bugfix: Critical bug on network check

= 2.1.8.1 =
* bugfix: Critical fix on admin profile

= 2.1.8 =
* bugfix: Updated mutliuser check for 3.3
* feature: Custom Thank You page
* feature: Updated styles to fit better with WordPress 3.3+
* feature: Add Chosen JS library for better UI on admin pages (plan to exten this to front end forms)
* feature: Auto*registration for FB Connect users.
* bugfix: login form on profile page if user is not logged in
* codebase: Reorganized admin form saving functions

= 2.1.7 =
* bugfix: profile bugs, checkbox "checked" and hidden profiles on backend

= 2.1.6 =
* bugfix: non*required having asteriks other undefined index bugs

= 2.1.5 =
* bugfix: fixed bugs related to 3.3 and style fixes

= 2.1.4 =
* bugfix: reCaptcha api keys save error fixed.

= 2.1.3 =
* bugfix: activation error

= 2.1.2 =
* Bugfix:Added backward compatibility to field ordering.

= 2.1.1 =
* Bugfix:Turned Off Error Reporting.

= 2.1 =
* Feature: Profile page shortcode
* Feature: Field types for checkbox and call back functions.
* Feature: Profile page redirect
* Bugfix: Fized Facebook classes to avoid conflict with other plugins

= 2.0.6.2 =
* Bugfix: FB conflicting with other plugins.

= 2.0.6.1 =
* Bugfix: old profile fields deleted on activation
* Bugfix: FB connect footer error

= 2.0.6 =
* Feature: Adds Facebook Connect
* Feature: Add error field hi*lighting
* Feature: Adds custom registration fields to user profile
* Bugfix: fixed issue with non*required custom fields
* Bugfix: fixed tinyMCE button registration issue in WP 3.2+

= 2.0.5.1 =
* Fixed FB Login Bug

= 2.0.5 =
* Added Facebook Connect Integration.
* Fixed validation bug.
* Added instruction video.
* Added Auto*Update.

= 2.0.2 =
* Fixed tinyMCE bug

= 2.0.1 =
* Premium version launch

= 1.7 =
* Added implementation button to WordPres TinyMCE Editor.
* Add new filters and hooks.
* Email validation.
* Allows user to set their own password.
* Additional security to prevent registering administrative role via plugin.

= 1.5 =
* Added filters for adding fields and validation.

= 1.1 =
* fixed stylesheet path

= 1.0 =
* Initial Version



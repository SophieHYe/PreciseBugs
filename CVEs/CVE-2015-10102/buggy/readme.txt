=== Freshdesk Official===
Contributors: hjohnpaul, sathishfreshdesk, balakumars, shreyasns
Donate link: 
Tags: freshdesk, helpdesk, support tool
Requires at least: 3.4
Tested up to: 4.1.5
Stable tag: 1.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html


Freshdesk Official plugin is a seamless way to add your helpdesk account to your website. Supports various useful functions.

==Description==

Freshdesk Official plugin enables users to achieve three important functions:

- Avoid additional login to helpdesk if the user has already logged in to WP(Single Sign On).
- Effortlessly integrate the helpdesk' s feedback widget along with solutions search to his wordpress site.
- Allows the site admin to convert comments into helpdesk tickets.
- Adding the comment link to ticket description.

1. Single Sign On:
Users can now login to their helpdesk(freshdesk) support portal using Wordpress authentication. The single sign on feature ensures that users who have already logged into their Wordpress site can start working in their Freshdesk support portal as well without having to log in separately. 

2. Feedback Pop-up Widget:
The plugin allows Wordpress users to have a Freshdesk feedback widget embedded in their wordpress site. The widget allows people visiting the site to provide feedback and search for answers.

3. Convert Comments to Tickets on helpdesk:
Users with admin access to their Wordpress sites can convert comments into “Tickets”. The marked comments are sent to the user’s support portal that can be solved or responded to later.

4. Adding the comment link-back in the ticket added:
   Comments converted to tickets in freshdesk will now have the link-back url to the actual comment in the wordpress site appended in its description.

== Installation ==
* For an automatic installation through WordPress
1. Go to the 'Add New' plugins screen in your WordPress admin area.
2. Search for 'Freshdesk Official' plugin.
3. Click 'Install Now' and activate the plugin.


Manual Installation:

1. Download the latest version of 'Freshdesk Official'  plugin from the WordPress Plugin Directory.
2. Extract the zip and Upload the freshdesk_support directory to your /wp-content/plugins directory
3. Go to the plugins management page and activate the plugin
4. You now have a new admin menu 'Freshdesk Official' in your WordPress admin menu bar. Click on it and Configure your settings as mentioned in the info comments in the screen.

== Frequently Asked Questions ==
1. Where do i find sso shared secret ?

  SSO shared secret will be available in your helpdesk account's admin -> security -> sso section.

2. How can login to my helpdesk using normal login screen after enabling sso ?

  You can always access your help desk's normal login screen using http://yourcompany.freshdesk.com/login/normal link.

3. Where can I find the feedback widget code snippet ?

  Feedback widget code has to be copied from the admin -> feedback section. You can configure the alignment and kbase search hide and much more here and copy the generate code snippet.

== Screenshots ==
1. This screen shot shows the Settings Screen.
2. This shows the comments page where a new action('Convert to ticket') added to make the comment as a ticket.
3. This shows the comments page with 'View Ticket' option showing if the comment is already converted to ticket.
4. This shows the comment link in the ticket description.

== Changelog ==
= 1.7 =
  Added option to enable/disable sso.
  Logging out of wordpress no longer logs out the Freshdesk session.
  Added validations for the settings page.
  Fixed the error showing up on the plugin settings page.
= 1.6 =
  Fix the SSO login redirect to Freshdesk and on wordpress logout, logout of Freshdesk as well.
= 1.5 =
  Includes fix for SSO and Vanity URL redirect.
= 1.4 =
  Bug fix for SSO and Vanity URL redirect.
= 1.3 =
 Added comment link to ticket description
= 1.2 =
 Fix for the error message("The plugin does not have a valid header.") on enabling plugin 
= 1.1 =
Previous revision. 
Bug Fix:
 - Freshdesk remote log-in failing for new users.
 - Sign-out from freshdesk does not logout wordpress session. 
= 1.0 =
First Release Version.

== Upgrade Notice ==
  Added option to enable/disable sso.
  Logging out of wordpress will no longer logout the Freshdesk session.
  Added validations for the settings page.
  Fixed the error showing up on the plugin settings page.
== 1.7 ==
  Added option to enable/disable sso.
  Logging out of wordpress will no longer logout the Freshdesk session.
  Added validations for the settings page.
  Fixed the error showing up on the plugin settings page.
== 1.6 ==
  Fix for SSO login redirect back to Freshdesk.
  Change logout url to Freshdesk logout url so that Freshdesk is also logged out.
== 1.5 ==
  Fixing problem with 1.4 getting listed as development version.
  Includes bug fix for SSO and Vanity URL redirect.
== 1.4 ==
  Bug fix for SSO and Vanity URL redirect.
  Now works with SSO and Vanity URL.
== 1.3 ==
 Added comment link to ticket description.
== 1.2 ==
 Fix for the error message on enabling the plugin.
== 1.1 ==
  Revision with bugs addressed.
== 1.0 ==
This is the First release version.

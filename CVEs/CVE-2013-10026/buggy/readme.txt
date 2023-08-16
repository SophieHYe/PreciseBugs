=== Mail Subscribe List ===
Contributors: webfwd
Donate link: 
Tags: mail, email, newsletter, subscribe, list, mailinglist, mail list, mailing list, campaignmonitor, mailchimp, constant contact, subscriber, subscribers, email marketing, marketing, widget, post, plugin, admin, posts, sidebar, page, pages
Requires at least: 3.0
Tested up to: 3.5.2
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Simple customisable plugin that displays a name/email form where visitors can submit their information, managable in the WordPress admin.

== Description ==

This is a **simple plugin** that allows visitors to enter their name and email address on your website, the visitos details are then added to the subscribers list which is available to view and modify in the WordPress admin area. 

This plugin can be used not only for Mailing List subscriptions but can be used generally for collecting email address and/or peoples names that are visiting your website.

The name/email form can not only be customised but can also be displayed on any WordPress page by using either the 'Subscribe Widget', WordPress shortcode [smlsubform] or from your WordPress theme by calling the php function.

I developed this plugin as I could not find any plugin that simply allows users to submit their name and email address to a simple list viewable in the WordPress admin, all the plugins that I found had lots of extra features such as 3rd party integration, mass emailing and double opt-in, my clients do not need any of these features.

Like this plugin? **Please follow me on Twitter and Facebook**

**Twitter** - https://twitter.com/webfwd

**Facebook** - https://www.facebook.com/pages/Webforward/208823852487018

= Extra Options  =

I have developed some customisable options that allow you to change the way the plugin is displayed.

Below is an explanation of what each option does:-

* "prepend"		->	Adds a paragraph of text just inside the top of the form.
* "showname"	->	If true, this with show the name label and input field for capturing the users name.
* "nametxt"		->	Text that is displayed to the left of the name input field.
* "nameholder"	->	Text that is displayed inside the name input box as a place holder.
* "emailtxt"	->	Text that is displayed to the left of the email input field.
* "emailholder"	->	Text that is displayed inside the email input box as a place holder.
* "showsubmit" 	-> 	If true, this with show the submit button, return required to submit form.
* "submittxt"	->	Text/value that will be displayed on the form submit button.
* "jsthanks"	->	If true, this will display a JavaScript Alert Thank You message instead of a paragraph above the form.
* "thankyou"	->	Thank you message that will be displayed when someone subscribes. (Will not show if blank)

= Extra Options - How to Use (Short Code Method) =

Short codes can be used simply putting the code into your wordpress page, here is an example of the shortcode in use.

<code>[smlsubform prepend="" showname=true nametxt="Name:" nameholder="Name..." emailtxt="Email:" emailholder="Email Address..." showsubmit=true submittxt="Submit" jsthanks=false thankyou="Thank you for subscribing to our mailing list"]</code>

= Extra Options - How to Use (PHP Method) =

The PHP method can be used by putting the following PHP code into your WordPress theme, here is an example of php code for your template.

<code>$args = array(
'prepend' => '', 
'showname' => true,
'nametxt' => 'Name:', 
'nameholder' => 'Name...', 
'emailtxt' => 'Email:',
'emailholder' => 'Email Address...', 
'showsubmit' => true, 
'submittxt' => 'Submit', 
'jsthanks' => false,
'thankyou' => 'Thank you for subscribing to our mailing list'
);
echo smlsubform($args);</code>

== Installation ==

1. Upload `mail-subscribe-list` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Either use the widget in your sidebar or place `[smlsubform]` in any of your pages or &lt;?php echo smlsubform(); ?&gt; in your template.

* It takes a lot of time and hard work to develop plugins, if you like my plugin then please find the time to rate it. 

== Screenshots ==

1. Admin Management
2. Example Widget in Use
3. Subscribe Widget

== Frequently Asked Questions == 

= Can this plugin be used to send emails when I publish a new post or page? =

No, this plugin was designed to collect email addresses from visitors. This plugin does not send emails at all.

== Changelog ==

= 2.0.9 =

* Bug fixes.

= 2.0.8 =

* Added extra security to the admin functions.

= 2.0.7 =

* Fix the echo of after_widget in widgets.

= 2.0.6 =

* Remove sending of activation message.

= 2.0.5 =

* Fix possibility of conflicting with other plugins and not showing the menu item in the admin.

= 2.0.4 =

* Export format changed, can now export to Google Contacts.

= 2.0.3 =

* Fixed PHP short tags issue with removing subscribers, my bad.

= 2.0.2 =

* Removed constants due to conflict with other plugins.

= 2.0.1 =

* Fix filter/shortcode big.
* Show/Hide Submit Button.

= 2.0 =

* Celebrating 5000 Downloads!
* Added some fixes to the code.
* Added the ability to use the shortcode within the Text Widget, provided by Joel Dare.
* Full Widget Support with Configurable Options!
* Added some more screenshots.

= 1.1.2 =

* Fix bug when no array is passed to smlsubform().
* Ability to import CSV file to the list.
* Changed the order of the CSV output.
* Few cosmetic changes.

= 1.1.1 =

* You can now specify the placeholder text.
* Extensions to the documentation.

= 1.1 =		

* Created a few extra options to customise the form.
* Check to see if the email address is already in the database.
* Customisable thank you for subscribing message.
* Ability to choose between a paragraph or JavaScript Alert based thank you message.
* Extended documentation.
        
= 1.0.1 =	

* Few fixes in the documentation.

= 1.0 =

* Developed Mail Subscribe List Plugin.

== Upgrade Notice == 

The current version of Mail Subscribe List requires WordPress 3.3 or higher. If you use older version of WordPress, you need to upgrade WordPress first.

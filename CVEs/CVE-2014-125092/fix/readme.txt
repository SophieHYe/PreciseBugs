=== MaxButtons: WordPress Button Generator ===
Contributors: maxfoundry, arcware, johnbhartley
Tags: button, buttons, css, css3, css3 icon, call to action, free, icon, icons, link, permalink, plugin, seo, shortcode, shortcodes, shortlinks, wordpress button plugin, wordpress button generator, css3 button plugin, css3 button generator, css wordpress button, css3 wordpress button, social media button, wordpress buttons plugin, wp button creator, create wordpress buttons, button generator, create button icon, font awesome, fontawesome
Requires at least: 3.4
Tested up to: 4.0
Stable tag: 1.26.1

A CSS3 button generator for WordPress that's powerful and so easy to use that anyone can create beautiful buttons.

== Description ==
Create great-looking CSS3 buttons that can be used on any post or page in your WordPress site. The easy to use button editor makes it a snap to generate awesome CSS3 buttons in very little time.

= Helpful Links = 

* [How to Create WordPress Buttons?](http://www.maxbuttons.com/#CSS3/?utm_source=wordpress&utm_medium=mbrepo&utm_content=how-to-create&utm_campaign=repo) 
* [How to make a WordPress Buttons?](http://www.maxbuttons.com/?utm_source=wordpress&utm_medium=mbrepo&utm_content=how-to-make&utm_campaign=repo) 
* [What is a WordPress Button Generator?](http://maxbuttons.com/tour/button-editor/?utm_source=wordpress&utm_medium=mbrepo&utm_content=what-is-generator&utm_campaign=repo)
* [How do I see my WordPress Buttons?](http://maxbuttons.com/tour/button-list/?utm_source=wordpress&utm_medium=mbrepo&utm_content=how-to-see&utm_campaign=repo)
* [What's the best way to use Font Awesome with Wordpress?](http://www.maxbuttons.com/?utm_source=wordpress&utm_medium=mbrepo&utm_content=font-awesome&utm_campaign=repo)


= WordPress Button Pack Libraries =

* [How do Button Pack libraries make me and my firm more effective?](http://www.maxbuttons.com/shop/category/button-packs/?utm_source=wordpress&utm_medium=mbrepo&utm_content=how-to-create&utm_campaign=repo)
* [What is a good Vector Icon Library to use with MaxButtons Pro?](http://maxvectors.com/)

= Highlights =

* No coding, the plugin takes care of everything
* Create unlimited number of buttons
* Buttons are built on-the-fly as you enter and select options
* Works with all modern browsers, degrades gracefully for others
* Fully CSS3 compliant with text shadowing, box shadowing, gradients, etc
* Color picker for unlimited color combinations
* Copy an existing button to use as starting point for others
* See your buttons on different color backgrounds
* Predefined defaults make getting started super easy

= Upgrade to MaxButtons Pro =

Take your buttons to the next level with [MaxButtons Pro](http://www.maxbuttons.com/pricing/?utm_source=wordpress&utm_medium=mbrepo&utm_content=MBPro&utm_campaign=repo), which gives you additional features such as:

* **Icon Support** - Put icons to the left, right, top, or bottom of your text.
* **Multi-line text** - To add a second line of text for communicating extra information.
* **Google Web Fonts** - To make your buttons stand out with beautiful typography.
* **Button Packs** - Be more productive through the use of our value priced, ready-made button sets.
* **Import/Export** - Useful for backing up and/or moving your buttons. Also, use any of the great [free icons](http://maxbuttons.com/free-icons/) listed on our site.
* **Height and Width** - Explicit options to set button height and width.
* **Shopp Integration** - Use buttons created with MaxButtons Pro as the shopping cart buttons of the Shopp e-commerce plugin.

And the best part is that you can get this awesome [CSS3 button generator](http://www.maxbuttons.com/?utm_source=wordpress&utm_medium=mbrepo&utm_content=CSS3&utm_campaign=repo) for **only $19!**

= How To Use =

1. Click the MaxButtons page from the admin menu.
1. Click the Add New button.
1. Fill out and select the options needed to build your button.
1. Once you're ready, click Save.
1. A shortcode will be generated (ex: [maxbutton id="17"] or [maxbutton name="My Button Name"]).
1. Use the shortcode anywhere in your content.

You can also pass the button text and URL as parameters in the shortcode, giving you even greater flexibility. For example, if you want to create a set of buttons that look exactly the same, except for the text and URL, you could do something like this:

[maxbutton id="17" text="Search Google" url="http://google.com"]

[maxbutton name="MaxButtons Button Name" text="Search Yahoo" url="http://yahoo.com"]

Another parameter you can give the shortcode is window, which tells the button whether or not to open the URL in a new window (by default the button opens the URL in the current window). To do so you always give the window parameter the value "new", shown below. Anything else will open the button URL in the current window.

[maxbutton id="17" window="new"]

You can also use the nofollow parameter, which will add a rel="nofollow" attribute to the button when set to true, as shown below (the default is false):

[maxbutton id="17" nofollow="true"]

NOTE: Passing parameters to the shortcode overrides those settings saved as part of the button.

== Installation ==

For automatic installation:

1. Login to your website and go to the Plugins section of your admin panel.
1. Click the Add New button.
1. Under Install Plugins, click the Upload link.
1. Select the plugin zip file from your computer then click the Install Now button.
1. You should see a message stating that the plugin was installed successfully.
1. Click the Activate Plugin link.

For manual installation:

1. You should have access to the server where WordPress is installed. If you don't, see your system administrator.
1. Copy the plugin zip file up to your server and unzip it somewhere on the file system.
1. Copy the "maxbuttons" folder into the /wp-content/plugins directory of your WordPress installation.
1. Login to your website and go to the Plugins section of your admin panel.
1. Look for "MaxButtons" and click Activate.

== Screenshots ==

1. Adding and editing a button.

== Frequently Asked Questions ==

= How do I use the shortcode in a sidebar/widget? =

Starting with version 1.4.0 widget support is built-in, so all you have to do is add the button shortcode to your widget (ex: [maxbutton id="17"] or [maxbutton name="MaxButtons Button Name"]). Prior to version 1.4.0 you had to enable widget shortcode support yourself, as described in [this forum post](http://wordpress.org/support/topic/how-to-make-shortcodes-work-in-a-widget).

= How can I add the shortcode to my post/page template? =

Simply add this code snippet to any of your theme template files:
`<?php echo do_shortcode('[maxbutton id="17"]'); ?>`

= Part of my button is cutoff, how do I fix that? =

Try enabling the container and setting its margin options. You could also fix this manually by surrounding your button shortcode with a div element with margins. For example:

`<div style="margin: 10px 10px 10px 10px;">
    <?php echo do_shortcode('[maxbutton id="17"]'); ?>
    <?php echo do_shortcode('[maxbutton name="MaxButtons Button Name"]'); ?>
</div>`

Then adjust the margin values as needed (the order is: top, right, bottom, left).

= How do I center the button on a page? =

Enable the "Wrap with Center Div" option in the Container settings.

= How do I align multiple buttons next to each other? =

Enable the container option and set the alignment property to either "display: inline-block" or "float: left". You might also want to add some margin values to put some spacing between your buttons. If that doesn't work, try using a simple HTML table:

`<table>
	<tr>
		<td>[maxbutton id="1"]</td>
		<td>[maxbutton id="2"]</td>
		<td>[maxbutton id="3"]</td>
	</tr>
</table>`

== Changelog ==
= 1.26.1 =
* Fixed an XSS vulnerability on the button creation page

= 1.26.0 =
* Placed button description in ThickBox when opened in Content Editor
* Added ability to get shortcode by button name along with button id

= 1.25.0 =
* Added Permissions so more than admin can use the buttons if desired.

= 1.24.3 =
* Small CSS tweaks including adding box-sizing and more border-style options. 
* Updated some of the notifications.

= 1.24.2 =
* Fixed button editor editor issue where button background colors weren't being reflected in real-time in Firefox and Internet Explorer.

= 1.24.1 =
* Replaced TinyMCE button with "Add Button" media button.

= 1.24.0 =
* Copy and invert normal colors to hover added.
* Settings tab added to Button edit page
* Updated phrasing

= 1.23.0 =
* Save button added to bottom of page

= 1.22.0 =
* Added Settings page
* Added "Alter Table" button for foreign text issue

= 1.21.0 =
* Replaced separate PHP page for viewing button CSS with lean modal box.

= 1.20.0 =
* Fixed vulnerability issue when viewing the button CSS page.

= 1.19.0 =
* Minor UI and style changes to better support WP 3.8.

= 1.18.0 =
* Updated Colors section in button editor to match layout of Pro version.

= 1.17.0 =
* Added shortcut links in Colors section for enhanced usability.
* Updated the shortcode so that it doesn't render the HREF or the hover colors when button URL is empty.

= 1.16.0 =
* Added gradient and opacity options.
* Changed the button output window so that the button isn't clickable.

= 1.15.0 =
* Changed MAXBUTTONS_PLUGIN_URL constant to call the plugins_url() function instead of WP_PLUGIN_URL so that the proper url scheme is used.
* Removed the MAXBUTTONS_PLUGIN_DIR constant as it was no longer used.

= 1.14.0 =
* Updated description and Go Pro page to show new price of MaxButtons Pro.

= 1.13.0 =
* Added 'exclude' parameter to shortcode to exclude button from rendering on certain posts/pages.
* Replace get_theme_data() with wp_get_theme() on the support page.

= 1.12.0 =
* Ignoring the container element on the button list pages so that the button alignment is consistent on those pages.

= 1.11.0 =
* Added TinyMCE plugin to be able to insert button shortcode from the Visual tab in the WP text editor.

= 1.10.0 =
* Added ability to externalize the button CSS code.
* Added option to use !important on button styles.

= 1.9.1 =
* Fixed issues with spacing of the system info on the Support page.

= 1.9.0 =
* Added support for localization.

= 1.8.0 =
* Added the Support page that contains system information along with a link to the support forums.

= 1.7.0 =
* Added center div wrapper option to Container section in button editor.
* Added rel="nofollow" option in button editor.
* Added status field to database table to provide ability to move buttons to trash (default = 'publish').
* Added actions for Move to Trash, Restore, and Delete Permanently.
* Added CSS3PIE for better IE support.

= 1.6.0 =
* Updated UI for button editor.
* The container is now enabled by default.
* Removed the IE-specific gradient filter and -ms-filter styles from shortcode output due to issue when used with rounded corners.
* Changed url database field to be VARCHAR(250) instead of VARCHAR(500).

= 1.5.0 =
* Added container options.

= 1.4.3 =
* Added :visited style to the shortcode output.

= 1.4.2 =
* Fixed issue in button editor where the colorpickers changed the value of the hover colorpickers.

= 1.4.1 =
* Changed some fields to use stripslashes instead of escape when saving to the database.

= 1.4.0 =
* Made the button output div in the button editor draggable.
* Updated styles and scripts to be used only on plugin admin pages instead of all admin pages.
* Added filter for widget_text to recognize and execute the button shortcode.

= 1.3.3 =
* Modified the description database field to be VARCHAR(500) instead of TEXT.
* Modified button list page to use button shortcodes to render each button.
* Updated the UI for the button list page.
* Added the button count to the button list page.
* Updated "Go Pro" page with copy for MaxButtons Pro.

= 1.3.2 =
* Added "Add New" to the admin menu.
* Fixed issue where gradient stop value wasn't used when copying a button.
* Fixed issue where new window option wasn't used when copying a button.
* Fixed issue where the gradient stop value wasn't being used in the button list.

= 1.3.1 =
* Fixed issue where gradient stop value was empty after upgrade to 1.3.0 (default value now used in this scenario).

= 1.3.0 =
* Changed the style of the output div so that it floats.
* Updated shortcode so that the <style> element is returned with the <a> element.
* Added option for gradient stop.

= 1.2.1 =
* Fixed issue when new sites are added with multisite/network.

= 1.2.0 =
* Added option for opening url in a new window.

= 1.1.0 =
* Added text and url parameters to shortcode.

= 1.0.0 =
* Initial version.

== Upgrade Notice ==

= 1.24.2 =
Please deactivate and then reactivate before using. If the save button does not work, be sure to clear your browser cache. Also, if the "copy and invert" button does not work, try a hard refresh of your browser or clear your cache.

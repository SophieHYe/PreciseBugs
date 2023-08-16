=== Plugin Name ===
Contributors: hchouhan, themeist, dreamsmedia, Benoit "LeBen" Burgener
Donate link: http://themeist.co
Tags: recommend, like, love, post, rate, rating, post rating, heart, dribbble like, tumblr like
Requires at least: 4.0
Tested up to: 4.0
Stable tag: 3.7.3
Last Updated: 2014-September-24
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin allows your visitors to simply like/recommend your posts instead of comment on it.

== Description ==

This plugin allows your visitors to simply like/recommend your posts instead of comment on it.

= Features of I Recommend This =

- A counter to display the number of "like" and to vote.
- A widget and a function to display the X most liked posts.
- Saves Cookie as well as users IP address to disable voting on the same post again.
- Displays Number of likes on Post Edit page along with sorting option. [HSG](http://profiles.wordpress.org/HSG/)
- A preference pane with some options.

** Advanced Options **

* Hide count if count is zero
* Set a default messages when count is zero, one or more
* Choose between a "Thumbs Up" or a "Heart" icon to allow post recommending.
* Disable plugin CSS to allow you to add your own styling rules
* Disable saving of IP address in the table.

** Shortcodes **

- Add the voting link to any page using shortcodes
- Display specific number of most recommended posts of all time or from a specific time period with support for custom post types.

= Examples of how the plugin has been used =

- [Flat UI Design Gallery](http://flattrendz.com)
- [Harish's blog](http://www.harishchouhan.com/blog/)
- [OnePageMania.com](http://onepagemania.com/)

= Translations =

- English (en_US) - Harish Chouhan
- French (fr_FR) - Murat [wptheme.fr](http://wptheme.fr/)
- Portuguese (pt_BR) - [Darlan ten Caten](http://i9solucoesdigitais.com.br/)
- Persian (fa_IR) - [Hossein Soroor Golshani](http://profiles.wordpress.org/HSG/)
- Spanish (es_ES) - [Andrew Kurtis - WebHostingHub](http://www.webhostinghub.com/)

If you have created your own language pack (or have an update of an existing one) you can send in your .PO and .MO files so we can bundle it into I Recommend This plugin. You can [download the latest POT file](http://plugins.svn.wordpress.org/i-recommend-this/trunk/languages/dot-en.po), and [PO files in each language](http://plugins.svn.wordpress.org/i-recommend-this/trunk/languages/).


This plugin is based exactly on Benoit "LeBen" Burgener's "I Like This" Plugin and has been modified after getting requests for the changes I had made on my website.

Please report any bugs you find via  [Support Forum](https://wordpress.org/support/plugin/i-recommend-this) or via comment on http://www.dreamsonline.net/wordpress-plugins/i-recommend-this/

> ** For Developers **
>
> If you're a developer and want to contribute, head over to [I Recommend This plugin on GitHub](https://github.com/hchouhan/I-Recommend-This)
>

= My Links =

* Twitter @[harishchouhan](https://twitter.com/harishchouhan)
* Google+ [Harish Chouhan](https://plus.google.com/u/0/103138475844539274387/)


If you love the plugin, please consider rating it and clicking on "it works" button.


== Installation ==

1. Upload the directory `/i-recommend-this/` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Click on the Settings link below the plugin name on the plugins page

To display the recomment/like link other than at the bottom of individual post, you would have to add below code in your template
`<?php if( function_exists('dot_irecommendthis') ) dot_irecommendthis(); ?>`

Shortcode
`[dot_recommends]`

To display the most recommended posts you can below code in your template
`<?php if( function_exists('dot_irecommendthis') ) echo do_shortcode("[dot_recommended_posts container='div' post_type='post' number='10' year='2013' monthnum='7']"); ?>`

Shortcode
`[dot_recommended_posts post_type='post' container='div' number='10' year='2013' monthnum='7']`

== Frequently Asked Questions ==

Take a look at the [official "I Recommend This" FAQ](http://www.dreamsonline.net/wordpress-plugins/i-recommend-this/).

You can also visit the [support center](http://www.dreamsonline.net/wordpress-plugins/i-recommend-this/) and start a discussion if needed.



== Changelog ==

= 3.7.3
* Fixed a Possible SQL injection vulnerability reported by [Oskar Adin](https://github.com/osadi) and fixed by [Danny van Kooten](https://twitter.com/DannyvanKooten).

= 3.7.2
* Updated 'dot_irecommendthis.js' file to make plugin work even when the like button is on a hidden element. Thanks to [forthewinn](http://wordpress.org/support/profile/forthewinn). [Support Ticket](http://wordpress.org/support/topic/recommendation-to-fix-usage-in-hiddenexpanding-elements)

= 3.7.1
* Spanish translation added. Thanks to Andrew Kurtis from [WebHostingHub](http://www.webhostinghub.com/)

= 3.7.0
* Added to remove resolve wrong tags set for this plugin earlier. My sincere apologies for the extra update mess.

= 2.6.5
* Removed deprecaed JQuery function live with on in dot_irecommendthis.js

= 2.6.4
* Moved enqueued JS from wp_head() to wp_footer().

= 2.6.3
* Fixed 3 undefined index errors for disable_unique_ip, link_title_new & link_title_active. Thanks to [sebabornia](http://wordpress.org/support/profile/sebabornia)

= 2.6.2
* French translation added. Thanks to Murat from [wptheme.fr](http://wptheme.fr/)

= 2.6.1
* Updates to Persian translation

= 2.6.0
* Added Persian translation - Thanks to [HSG](http://profiles.wordpress.org/HSG/)
* Now you can see number of likes on Post Edit page along with sorting option. Thanks to [HSG](http://profiles.wordpress.org/HSG/)

= 2.5.3
* Fixed PHP error in Widget.
* Converted text strings in widget to be translatable.

= 2.5.3
* Fixed textdomain problem. Added portuguese translation. Thanks to @Darlan ten Caten.

= 2.5.2
* Replaced 'before' & 'after' attributes in shortocde 'dot_recommended_posts' with a single attribute 'container'

= 2.5.1
* Change shortcode name from dot_recommends_posts to dot_recommended_posts

= 2.5.0
* Added new shortcode with multiple options to display most recommended post / post_type of all time or from a specific date

= 2.4.2
* Bug fix. Thanks to @mmaxim

= 2.4.1
* Fixed undefined index error.

= 2.4.0
* Added filter dot_irt_before_count to be able to allow custom content or icons before the count.


= 2.3.0
* Added option to hide count if count is zero
* Added option to disable saving of IP address in the database


= 2.2.0
* Added option to customize the link title. You can now remove the word recomment and add anything you like. Ideas suggested by Krystina Montemurro.


= 2.1.5
* Support URL update for new plugin details page.

= 2.1.4
* Removed 2 instances of double quotes. Thanks to [boyevul](http://profiles.wordpress.org/boyevul/)

= 2.1.3
* Fixed errors shown when Debug mode was on. Thanks to [Air](http://profiles.wordpress.org/air-1/)

= 2.1.2
* Fixed CSS Disable issue. Thanks to Nicolas Mollet.

= 2.1
* Fixed Naming Errors. Thanks to Marian Hillmar.
* Fixed Shortcode name & Added support to place like button anywhere pointing to any post. Thanks to Bryant Williams for the code.


= 2.0
* This is a major revamp. The entire plugin structure is now based on OOP
* Settings are now stored using Settings API and Settings page is created based on WordPress standards.
* This plugin contains code from "Zilla Like" plugin developed by Orman Clark of www.themezilla.com.
* Translation files are finally added.


= 1.4.3
* All deprecated functions removed. Plugin might not work on WordPress versions older than 3.


= 1.4.1
* Fixed bug that broke update.

= 1.4
* Added feature to display custom text when a post is liked.

= 1.3
* Removed 2 functions "register_widget_control()" & "register_sidebar_widget()" deprecated in version 2.8 with latest functions


= 1.2
* More bugs fixed.


= 1.1
* Fixed Bug that did not allow displaying text next to the counter.
* Updated code using branch of original plugin on GitHub


= 1.0
* Removed JQuery loading style when heart is clicked.
* Modified CSS & Images of LeBen's "I Like This" plugin based on what many users requested.
* This is the first version
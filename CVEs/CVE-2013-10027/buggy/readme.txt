=== Plugin Name ===
Contributors: wordpressdotorg, Otto42, Workshopshed, SergeyBiryukov, rmccue
Donate link: 
Tags: importer, blogger
Requires at least: 3.0
Tested up to: 3.4
Stable tag: 0.5
License: GPLv2 or later

Imports posts, comments, and categories (blogger tags) from a Blogger blog then migrates authors to Wordpress users.

== Description ==

The Blogger Importer imports your blog data from a Blogger site into a WordPress.org installation.

= Items imported =

* Categories
* Posts (published, scheduled and draft)
* Comments (not spam)

= Items not imported =

* Pages
* Images (the images will appear in your new blog but will link to the old blogspot or picassa web locations)

== Installation ==

1. Upload the `blogger-importer` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

= How to use =

1. Blogger Importer is available from the WordPress Tools->Import screen.
1. Press Authorise
1. If you are not already logged into Google you will be asked to login
1. You will be asked to grant Wordpress access to your Blogger information, to continue press Grant Access
1. You will be presented with a list of all your blogs
1. Select the appropriate blog and press the import button
1. Wait whilst the posts and comments are imported
1. Press the Set Authors button
1. Select the appropriate mapping for the authors
1. Review categories, posts and comments

You can now remove the importer plugin if you no longer need to use it.

== Frequently Asked Questions ==

= How do I re-import? =

Press the clear account information button, then re-connect to blogger and re-import, the importer is designed not to re-import the same posts. If you need to do a full re-import then delete the posts and then empty the trash before re-importing.

= How do I know which posts were imported? = 

Each of the posts loaded is tagged with a meta tags indicating where the posts were loaded from. The permalink will be set to the visible URL if the post was published or the internal ID if it was still a draft or scheduled post

* blogger_author
* blogger_blog
* blogger_permalink

= Why does it keep stopping? = 

The importer is designed not to overload blogger or your site so only imports in batches and will run for a fixed number of seconds before pausing, the admin screen will refresh every few seconds to show how many it has done so far. Press continue to continue importing posts and comments.

= After importing there are a lot of categories =

Blogger does not distinguish between tags and categories so you will likely want to review what was imported and then use the categories to tags converter

= What about pages? =

This importer does not handle blogger pages, you will need to manually transfer them.

= What about images? =

The importer will simply load the tags for the images as they appear in your source data, so you will have references to blogspot and picassa based images. If you do not migrate these with a separate tool then these will be lost when you delete your old blogger blog.

= Are the permalinks the same? =

No, Wordpress and Blogger handle the permalinks differently. However, it is possible to use the redirection plugin to map the old URLs across to the new URLs.

= What about future posts? =

The scheduled posts will be transferred and will be published as specified. However, Blogger and Wordpress handle drafts differently, Wordpress does not support dates on draft posts so you will need to use a plugin if you wish to plan your writing schedule.

= My posts and comments moved across but some things are stripped out =

The importer uses the SimplePie classes to process the data, these in turn use a Simplepie_Sanitize class to remove potentially malicious code from the source data.

== Screenshots ==

== Reference ==

* https://developers.google.com/blogger/docs/1.0/developers_guide_php
* https://developers.google.com/gdata/articles/oauth

== Changelog ==

= 0.5 =
* Merged in fix by SergeyBiryukov http://core.trac.wordpress.org/ticket/16012
* Merged in rmccue change to get_total_results to also use SimplePie from http://core.trac.wordpress.org/attachment/ticket/7652/7652-blogger.diff
* Reviewed in rmccue's changes in http://core.trac.wordpress.org/attachment/ticket/7652/7652-separate.diff issues with date handling functions so skipped those
* Moved SimplePie functions in  new class WP_SimplePie_Blog_Item incorporating get_draft_status and get_updated and convert date
* Tested comments from source blog GMT-8, destination London (currently GMT-1), comment dates transferred correctly.
* Fixed typo in oauth_get
* Added screen_icon() to all pages
* Added GeoTags as per spec on http://codex.wordpress.org/Geodata 
* Change by Otto42, rmccue to use Simplepie XML processing rather than Atomparser, http://core.trac.wordpress.org/ticket/14525 ref: http://core.trac.wordpress.org/attachment/ticket/7652/7652-blogger.diff (this also fixes http://core.trac.wordpress.org/ticket/15560)
* Change by Otto42 to use OAuth rather than AuthSub authentication, should make authentication more reliable
* Fix by Andy from Workshopshed to load comments and nested comments correctly
* Fix by Andy from Workshopshed to correctly pass the blogger start-index and max-results parameters to oAuth functions and to process more than one batch http://core.trac.wordpress.org/ticket/19096
* Fix by Andy from Workshopshed error about incorrect enqueuing of scripts also changed styles to work the same
* Change by Andy from Workshopshed testing in debug mode and wrapped ajax return into a function to suppress debug messages
* Fix by Andy from Workshopshed notices for undefined variables.
* Change by Andy from Workshopshed Added tooltip to results table to show numbers of posts and comments skipped (duplicates / missing key)
* Fix by Andy from Workshopshed incorrectly checking for duplicates based on only the date and username, this gave false positives when large numbers of comments, particularly anonymous ones.

= 0.4 =
* Fix for tracking images being added by Blogger to non-authenticated feeds http://core.trac.wordpress.org/ticket/17623

= 0.3 =
* Bugfix for 403 Invalid AuthSub Token http://core.trac.wordpress.org/ticket/14629

= 0.1 =
* Initial release

== Upgrade Notice ==

= 0.5 =

Merged in fixes found in Trac
This version is a significant re-write based on previous versions. 


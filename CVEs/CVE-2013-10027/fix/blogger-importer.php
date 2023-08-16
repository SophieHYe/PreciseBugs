<?php
/*
Plugin Name: Blogger Importer
Plugin URI: http://wordpress.org/extend/plugins/blogger-importer/
Description: Import posts, comments, and tags from a Blogger blog and migrate authors to Wordpress users.
Author: wordpressdotorg
Author URI: http://wordpress.org/
Version: 0.5
License: GPLv2
License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

if (!defined('WP_LOAD_IMPORTERS'))
    return;

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

// Load Simple Pie
require_once ABSPATH . WPINC . '/class-feed.php';
require_once 'blogger-importer-sanitize.php';
require_once 'blogger-importer-blogitem.php';

// Load OAuth library
require_once 'oauth.php';

if (!class_exists('WP_Importer'))
{
    $class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
    if (file_exists($class_wp_importer))
        require_once $class_wp_importer;
}

/**
 * How many records per GData query
 *
 * @package WordPress
 * @subpackage Blogger_Import
 * @var int
 * @since unknown
 */
define('MAX_RESULTS', 25);

/**
 * How many seconds to let the script run
 *
 * @package WordPress
 * @subpackage Blogger_Import
 * @var int
 * @since unknown
 */
define('MAX_EXECUTION_TIME', 20);

/**
 * How many seconds between status bar updates
 *
 * @package WordPress
 * @subpackage Blogger_Import
 * @var int
 * @since unknown
 */
define('STATUS_INTERVAL', 3);

/**
 * Blogger Importer
 *
 * @package WordPress
 * @subpackage Importer
 */
if (class_exists('WP_Importer'))
{
    class Blogger_Import extends WP_Importer
    {

        function Blogger_Import()
        {
            global $importer_started;
            $importer_started = time();
            if (isset($_GET['import']) && $_GET['import'] == 'blogger')
            {
                add_action('admin_print_scripts', array(&$this, 'queue_scripts'));
                add_action('admin_print_styles', array(&$this, 'queue_style'));
            }
        }

        function queue_scripts($hook)
        {
            wp_enqueue_script('jquery');
        }

        function queue_style()
        {
            wp_enqueue_style('BloggerImporter', plugins_url('/blogger-importer.css', __file__));
        }

        // Shows the welcome screen and the magic auth link.
        function greet()
        {
            $next_url = get_option('siteurl') . '/wp-admin/index.php?import=blogger&amp;noheader=true';
            $auth_url = $this->get_oauth_link();
            $title = __('Import Blogger', 'blogger-importer');
            $welcome = __('Howdy! This importer allows you to import posts and comments from your Blogger account into your WordPress site.', 'blogger-importer');
            $prereqs = __('To use this importer, you must have a Google account and an upgraded (New, was Beta) blog hosted on blogspot.com or a custom domain (not FTP).', 'blogger-importer');
            $stepone = __('The first thing you need to do is tell Blogger to let WordPress access your account. You will be sent back here after providing authorization.', 'blogger-importer');
            $auth = esc_attr__('Authorize', 'blogger-importer');

            echo "
		<div class='wrap'>
		" . screen_icon() . "
		<h2>$title</h2>
		<p>$welcome</p><p>$prereqs</p><p>$stepone</p>
			<form action='{$auth_url['url']}' method='get'>
				<p class='submit' style='text-align:left;'>
					<input type='submit' class='button' value='$auth' />
					<input type='hidden' name='oauth_token' value='{$auth_url['oauth_token']}' />
					<input type='hidden' name='oauth_callback' value='{$auth_url['oauth_callback']}' />
				</p>
			</form>
		</div>\n";
        }

        function get_oauth_link()
        {
            // Establish an Blogger_OAuth consumer
            $base_url = get_option('siteurl') . '/wp-admin';
            $request_token_endpoint = 'https://www.google.com/accounts/OAuthGetRequestToken';
            $authorize_endpoint = 'https://www.google.com/accounts/OAuthAuthorizeToken';

            $test_consumer = new Blogger_OAuthConsumer('anonymous', 'anonymous', null); // anonymous is a google thing to allow non-registered apps to work

            //prepare to get request token
            $sig_method = new Blogger_OAuthSignatureMethod_HMAC_SHA1();
            $parsed = parse_url($request_token_endpoint);
            $params = array('callback' => $base_url, 'scope' => 'http://www.blogger.com/feeds/', 'xoauth_displayname' => 'WordPress');

            $req_req = Blogger_OAuthRequest::from_consumer_and_token($test_consumer, null, "GET", $request_token_endpoint, $params);
            $req_req->sign_request($sig_method, $test_consumer, null);

            // go get the request tokens from Google
            $req_token = wp_remote_retrieve_body(wp_remote_get($req_req->to_url(), array('sslverify' => false)));

            // parse the tokens
            parse_str($req_token, $tokens);

            $oauth_token = $tokens['oauth_token'];
            $oauth_token_secret = $tokens['oauth_token_secret'];

            $callback_url = "$base_url/index.php?import=blogger&noheader=true&token=$oauth_token&token_secret=$oauth_token_secret";

            return array('url' => $authorize_endpoint, 'oauth_token' => $oauth_token, 'oauth_callback' => $callback_url);
        }

        function uh_oh($title, $message, $info)
        {
            echo "<div class='wrap'>";
            screen_icon();
            echo "<h2>$title</h2><p>$message</p><pre>$info</pre></div>";
        }

        function auth()
        {
            // we have a authorized request token now, so upgrade it to an access token
            $token = $_GET['token'];
            $token_secret = $_GET['token_secret'];

            $oauth_access_token_endpoint = 'https://www.google.com/accounts/OAuthGetAccessToken';

            // auth the token
            $test_consumer = new Blogger_OAuthConsumer('anonymous', 'anonymous', null);
            $auth_token = new Blogger_OAuthConsumer($token, $token_secret);
            $access_token_req = new Blogger_OAuthRequest("GET", $oauth_access_token_endpoint);
            $access_token_req = $access_token_req->from_consumer_and_token($test_consumer, $auth_token, "GET", $oauth_access_token_endpoint);

            $access_token_req->sign_request(new Blogger_OAuthSignatureMethod_HMAC_SHA1(), $test_consumer, $auth_token);

            $after_access_request = wp_remote_retrieve_body(wp_remote_get($access_token_req->to_url(), array('sslverify' => false)));

            parse_str($after_access_request, $access_tokens);

            $this->token = $access_tokens['oauth_token'];
            $this->token_secret = $access_tokens['oauth_token_secret'];

            wp_redirect(remove_query_arg(array('token', 'noheader')));
        }

        // get a URL using the oauth token for authentication (returns false on failure)
        function oauth_get($url, $params = null)
        {
            $test_consumer = new Blogger_OAuthConsumer('anonymous', 'anonymous', null);
            $goog = new Blogger_OAuthConsumer($this->token, $this->token_secret, null);
            $request = new Blogger_OAuthRequest("GET", $url, $params);

            //Ref: Not importing properly http://core.trac.wordpress.org/ticket/19096
            $blog_req = $request->from_consumer_and_token($test_consumer, $goog, 'GET', $url, $params);

            $blog_req->sign_request(new Blogger_OAuthSignatureMethod_HMAC_SHA1(), $test_consumer, $goog);

            $data = wp_remote_get($blog_req->to_url(), array('sslverify' => false));

            if (wp_remote_retrieve_response_code($data) == 200)
            {
                $response = wp_remote_retrieve_body($data);
            } else
            {
                $response == false;
            }

            return $response;
        }

        function show_blogs($iter = 0)
        {
            if (empty($this->blogs))
            {
                $xml = $this->oauth_get('https://www.blogger.com/feeds/default/blogs');

                // Give it a few retries... this step often flakes out the first time.
                if (empty($xml))
                {
                    if ($iter < 3)
                    {
                        return $this->show_blogs($iter + 1);
                    } else
                    {
                        $this->uh_oh(__('Trouble signing in', 'blogger-importer'), __('We were not able to gain access to your account. Try starting over.', 'blogger-importer'), '');
                        return false;
                    }
                }

                $feed = new SimplePie();
                $feed->set_raw_data($xml);
                $feed->init();

                foreach ($feed->get_items() as $item)
                {
                    $blog = array(); //reset
                    $blog['title'] = $item->get_title();
                    $blog['summary'] = $item->get_description();

                    //ID is of the form tag:blogger.com,1999:blog-417730729915399755
                    //We need that number from the end
                    $rawid = explode('-', $item->get_id());
                    $blog['id'] = $rawid[count($rawid) - 1];

                    $parts = parse_url($item->get_link(0, 'alternate'));
                    $blog['host'] = $parts['host'];
                    $blog['gateway'] = $item->get_link(0, 'edit');
                    $blog['posts_url'] = $item->get_link(0, 'http://schemas.google.com/g/2005#post');

                    //AGC:20/4/2012 Developers guide suggests that the correct feed is located as follows
                    //See https://developers.google.com/blogger/docs/1.0/developers_guide_php
                    $blog['comments_url'] = "http://www.blogger.com/feeds/{$blog['id']}/comments/default";

                    if (!empty($blog))
                    {
                        $blog['total_posts'] = $this->get_total_results($blog['posts_url']);
                        $blog['total_comments'] = $this->get_total_results($blog['comments_url']);

                        $blog['mode'] = 'init';
                        $this->blogs[] = $blog;
                    }

                }

                if (empty($this->blogs))
                {
                    $this->uh_oh(__('No blogs found', 'blogger-importer'), __('We were able to log in but there were no blogs. Try a different account next time.', 'blogger-importer'), '');
                    return false;
                }
            }

            //Should probably be using WP_LIST_TABLE here rather than manually rendering a table in html
            //http://wpengineer.com/2426/wp_list_table-a-step-by-step-guide/
            //echo '<pre>'.print_r($this,1).'</pre>';
            $start = esc_js(__('Import', 'blogger-importer'));
            $continue = esc_js(__('Continue', 'blogger-importer'));
            $stop = esc_js(__('Importing...', 'blogger-importer'));
            $authors = esc_js(__('Set Authors', 'blogger-importer'));
            $loadauth = esc_js(__('Preparing author mapping form...', 'blogger-importer'));
            $authhead = esc_js(__('Final Step: Author Mapping', 'blogger-importer'));
            $nothing = esc_js(__('Nothing was imported. Had you already imported this blog?', 'blogger-importer'));
            $stopping = ''; //Missing String used below.
            $title = __('Blogger Blogs', 'blogger-importer');
            $name = __('Blog Name', 'blogger-importer');
            $url = __('Blog URL', 'blogger-importer');
            $action = __('The Magic Button', 'blogger-importer');
            $posts = __('Posts', 'blogger-importer');
            $comments = __('Comments', 'blogger-importer');
            $noscript = __('This feature requires Javascript but it seems to be disabled. Please enable Javascript and then reload this page. Don&#8217;t worry, you can turn it back off when you&#8217;re done.',
                'blogger-importer');

            $interval = STATUS_INTERVAL * 1000;
            $init = '';
            $rows = '';

            foreach ($this->blogs as $i => $blog)
            {
                if ($blog['mode'] == 'init')
                    $value = $start;
                elseif ($blog['mode'] == 'posts' || $blog['mode'] == 'comments')
                    $value = $continue;
                else
                    $value = $authors;
                $value = esc_attr($value);
                $blogtitle = esc_js($blog['title']);
                $pdone = isset($blog['posts_done']) ? (int)$blog['posts_done'] : 0;
                $cdone = isset($blog['comments_done']) ? (int)$blog['comments_done'] : 0;
                $init .= "blogs[$i]=new blog($i,'$blogtitle','{$blog['mode']}','" . $this->get_js_status($i) . '\');';
                $pstat = "<div class='ind' id='pind$i'>&nbsp;</div><div id='pstat$i' class='stat'>$pdone/{$blog['total_posts']}</div>";
                $cstat = "<div class='ind' id='cind$i'>&nbsp;</div><div id='cstat$i' class='stat'>$cdone/{$blog['total_comments']}</div>";
                $rows .= "<tr id='blog$i'><td class='blogtitle'>$blogtitle</td><td class='bloghost'>{$blog['host']}</td><td class='bar'>$pstat</td><td class='bar'>$cstat</td><td class='submit'><input type='submit' class='button' id='submit$i' value='$value' /><input type='hidden' name='blog' value='$i' /></td></tr>\n";
            }

            echo "<div class='wrap'>". screen_icon() ."<h2>$title</h2><noscript>$noscript</noscript><table cellpadding='5px'><thead><tr><td>$name</td><td>$url</td><td>$posts</td><td>$comments</td><td>$action</td></tr></thead>\n$rows</table></div>";
            echo "
		<script type='text/javascript'>
		/* <![CDATA[ */
			var strings = {cont:'$continue',stop:'$stop',stopping:'$stopping',authors:'$authors',nothing:'$nothing'};
			var blogs = {};
			function blog(i, title, mode, status){
				this.blog   = i;
				this.mode   = mode;
				this.title  = title;
                eval('this.status='+status);
				this.button = document.getElementById('submit'+this.blog);
			};
			blog.prototype = {
				start: function() {
					this.cont = true;
					this.kick();
					this.check();
				},
				kick: function() {
					++this.kicks;
					var i = this.blog;
					jQuery.post('admin.php?import=blogger&noheader=true',{blog:this.blog},function(text,result){blogs[i].kickd(text,result)});
				},
				check: function() {
					++this.checks;
					var i = this.blog;
					jQuery.post('admin.php?import=blogger&noheader=true&status=true',{blog:this.blog},function(text,result){blogs[i].checkd(text,result)});
				},
				kickd: function(text, result) {
					if ( result == 'error' ) {
						// TODO: exception handling
						if ( this.cont )
							setTimeout('blogs['+this.blog+'].kick()', 1000);
					} else {
						if ( text == 'done' ) {
							this.stop();
							this.done();
						} else if ( text == 'nothing' ) {
							this.stop();
							this.nothing();
                            this.done();
						} else if ( text == 'continue' ) {
							this.kick();
						} else if ( this.mode = 'stopped' )
							jQuery(this.button).attr('value', strings.cont);
					}
					--this.kicks;
				},
				checkd: function(text, result) {
					if ( result == 'error' ) {
						// TODO: exception handling
					} else {
						eval('this.status='+text);
						jQuery('#pstat'+this.blog).empty().append(this.status.p1+'/'+this.status.p2);
						jQuery('#cstat'+this.blog).empty().append(this.status.c1+'/'+this.status.c2);
						this.update();
						if ( this.cont || this.kicks > 0 )
							setTimeout('blogs['+this.blog+'].check()', $interval);
					}
					--this.checks;
				},
				update: function() {
					jQuery('#pind'+this.blog).width(((this.status.p1>0&&this.status.p2>0)?(this.status.p1/this.status.p2*jQuery('#pind'+this.blog).parent().width()):1)+'px');
                    jQuery('#pstat'+this.blog).attr('title', 'Posts skipped '+this.status.p3);
					jQuery('#cind'+this.blog).width(((this.status.c1>0&&this.status.c2>0)?(this.status.c1/this.status.c2*jQuery('#cind'+this.blog).parent().width()):1)+'px');
                    jQuery('#cstat'+this.blog).attr('title', 'Comments skipped '+this.status.c3);

				},
				stop: function() {
					this.cont = false;
				},
				done: function() {
					this.mode = 'authors';
					jQuery(this.button).attr('value', strings.authors);
				},
				nothing: function() {
					this.mode = 'nothing';
					jQuery(this.button).remove();
					alert(strings.nothing);
				},
				getauthors: function() {
					if ( jQuery('div.wrap').length > 1 )
						jQuery('div.wrap').gt(0).remove();
					jQuery('div.wrap').empty().append('<h2>$authhead</h2><h3>' + this.title + '</h3>');
					jQuery('div.wrap').append('<p id=\"auth\">$loadauth</p>');
					jQuery('p#auth').load('index.php?import=blogger&noheader=true&authors=1',{blog:this.blog});
				},
				init: function() {
					this.update();
					var i = this.blog;
					jQuery(this.button).bind('click', function(){return blogs[i].click();});
					this.kicks = 0;
					this.checks = 0;
				},
				click: function() {
					if ( this.mode == 'init' || this.mode == 'stopped' || this.mode == 'posts' || this.mode == 'comments' ) {
						this.mode = 'started';
						this.start();
						jQuery(this.button).attr('value', strings.stop);
					} else if ( this.mode == 'started' ) {
						return false; // let it run...
						this.mode = 'stopped';
						this.stop();
						if ( this.checks > 0 || this.kicks > 0 ) {
							this.mode = 'stopping';
							jQuery(this.button).attr('value', strings.stopping);
						} else {
							jQuery(this.button).attr('value', strings.cont);
						}
					} else if ( this.mode == 'authors' ) {
						document.location = 'index.php?import=blogger&authors=1&blog='+this.blog;
						//this.mode = 'authors2';
						//this.getauthors();
					}
					return false;
				}
			};
			$init
			jQuery.each(blogs, function(i, me){me.init();});
		/* ]]> */
		</script>\n";
        }

        // Handy function for stopping the script after a number of seconds.
        function have_time()
        {
            global $importer_started;
            if (time() - $importer_started > MAX_EXECUTION_TIME)
                self::ajax_die('continue');
            return true;
        }

        function get_total_results($url)
        {
            $response = $this->oauth_get($url, array('max-results' => 1, 'start-index' => 2));

            $feed = new SimplePie();
            $feed->set_raw_data($response);
            $feed->init();
            $results = $feed->get_channel_tags('http://a9.com/-/spec/opensearchrss/1.0/', 'totalResults');

            $total_results = $results[0]['data'];
            unset($feed);
            return (int)$total_results;
        }

        function import_blog($blogID)
        {
            global $importing_blog;
            $importing_blog = $blogID;

            if (isset($_GET['authors']))
                return print ($this->get_author_form());

            if (isset($_GET['status']))
                self::ajax_die($this->get_js_status());

            if (isset($_GET['saveauthors']))
                self::ajax_die($this->save_authors());

            //Simpler counting for posts as we load them forwards
            if (isset($this->blogs[$importing_blog]['posts_start_index']))
                $start_index = (int)$this->blogs[$importing_blog]['posts_start_index'];
            else
                $start_index = 1;

            // This will be positive until we have finished importing posts
            if ($start_index > 0)
            {
                // Grab all the posts
                $this->blogs[$importing_blog]['mode'] = 'posts';
                do
                {

                    $index = $struct = $entries = array();

                    $url = $this->blogs[$importing_blog]['posts_url'];

                    $response = $this->oauth_get($url, array('max-results' => MAX_RESULTS, 'start-index' => $start_index));

                    if ($response == false)
                        break;

                    // parse the feed
                    $feed = new SimplePie();
                    $feed->set_item_class('WP_SimplePie_Blog_Item');
                    $feed->set_sanitize_class('Blogger_Importer_Sanitize');
                    $feed->set_raw_data($response);
                    $feed->init();

                    foreach ($feed->get_items() as $item)
                    {

                        $blogentry = new BloggerEntry();

                        $blogentry->id = $item->get_id();
                        $blogentry->published = $item->get_published();
                        $blogentry->updated = $item->get_updated();
                        $blogentry->isDraft = $item->get_draft_status($item);
                        $blogentry->title = $item->get_title();
                        $blogentry->content = $item->get_content();
                        $blogentry->author = $item->get_author()->get_name();
                        $blogentry->geotags = $item->get_geotags();

                        $linktypes = array('replies', 'edit', 'self', 'alternate');
                        foreach ($linktypes as $type)
                        {
                            $links = $item->get_links($type);

                            if (!is_null($links))
                            {
                                foreach ($links as $link)
                                {
                                    $blogentry->links[] = array('rel' => $type, 'href' => $link);
                                }
                            }
                        }

                        $cats = $item->get_categories();

                        if (!is_null($cats))
                        {
                            foreach ($cats as $cat)
                            {
                                $blogentry->categories[] = $cat->term;
                            }
                        }

                        $result = $this->import_post($blogentry);

                        //Ref: Not importing properly http://core.trac.wordpress.org/ticket/19096
                        //Simplified this section to count what is loaded rather than parsing the results again
                        $start_index++;
                    }

                    $this->blogs[$importing_blog]['posts_start_index'] = $start_index;

                    $this->save_vars();

                } while ($this->blogs[$importing_blog]['total_posts'] > $start_index && $this->have_time()); //have time function will "die" if it's out of time
            }


            if (isset($this->blogs[$importing_blog]['comments_start_index']))
                $start_index = (int)$this->blogs[$importing_blog]['comments_start_index'];
            else
                $start_index = 1;

            if ($start_index > 0 && $this->blogs[$importing_blog]['total_comments'] > 0)
            {

                $this->blogs[$importing_blog]['mode'] = 'comments';
                do
                {
                    $index = $struct = $entries = array();

                    //So we can link up the comments as we go we need to load them in reverse order
                    //Reverse the start index as the GData Blogger feed can't be sorted
                    $batch = ((floor(($this->blogs[$importing_blog]['total_comments'] - $start_index) / MAX_RESULTS) * MAX_RESULTS) + 1);

                    $response = $this->oauth_get($this->blogs[$importing_blog]['comments_url'], array('max-results' => MAX_RESULTS, 'start-index' => $batch));

                    // parse the feed
                    $feed = new SimplePie();
                    $feed->set_item_class('WP_SimplePie_Blog_Item');
                    // Use the standard "stricter" sanitize class for comments
                    $feed->set_raw_data($response);
                    $feed->init();

                    //Reverse the batch so we load the oldest comments first and hence can link up nested comments
                    $comments = array_reverse($feed->get_items());

                    if (!is_null($comments))
                    {
                        foreach ($comments as $item)
                        {

                            $blogentry = new BloggerEntry();
                            $blogentry->id = $item->get_id();
                            $blogentry->updated = $item->get_updated();
                            $blogentry->content = $item->get_content();
                            $blogentry->author = $item->get_author()->get_name();
                            $blogentry->authoruri = $item->get_author()->get_link();
                            $blogentry->authoremail = $item->get_author()->get_email();

                            $temp = $item->get_item_tags('http://purl.org/syndication/thread/1.0', 'in-reply-to');

                            foreach ($temp as $t)
                            {
                                if (isset($t['attribs']['']['source']))
                                {
                                    $blogentry->source = $t['attribs']['']['source'];
                                }
                            }

                            //Get the links
                            $linktypes = array('edit', 'self', 'alternate', 'related');
                            foreach ($linktypes as $type)
                            {
                                $links = $item->get_links($type);
                                if (!is_null($links))
                                {
                                    foreach ($links as $link)
                                    {
                                        $blogentry->links[] = array('rel' => $type, 'href' => $link);
                                    }
                                }
                            }

                            $this->import_comment($blogentry);
                            $start_index++;
                        }
                    }

                    $this->blogs[$importing_blog]['comments_start_index'] = $start_index;
                    $this->save_vars();
                } while ($this->blogs[$importing_blog]['total_comments'] > $start_index && $this->have_time());
            }

            $this->blogs[$importing_blog]['mode'] = 'authors';
            $this->save_vars();

            if (!$this->blogs[$importing_blog]['posts_done'] && !$this->blogs[$importing_blog]['comments_done'])
                self::ajax_die('nothing');

            do_action('import_done', 'blogger');
            self::ajax_die('done');
        }

        function no_apos($string)
        {
            return str_replace('&apos;', "'", $string);
        }

        function min_whitespace($string)
        {
            return preg_replace('|\s+|', ' ', $string);
        }

        function _normalize_tag($matches)
        {
            return '<' . strtolower($matches[1]);
        }

        function import_post($entry)
        {
            global $importing_blog;

            foreach ($entry->links as $link)
            {
                // save the self link as meta
                if ($link['rel'] == 'self')
                {
                    $postself = $link['href'];
                    $parts = parse_url($link['href']);
                    $entry->old_permalink = $parts['path'];
                }

                // get the old URI for the page when available
                if ($link['rel'] == 'alternate')
                {
                    $parts = parse_url($link['href']);
                    $entry->bookmark = $parts['path'];
                }

                // save the replies feed link as meta (ignore the comment form one)
                if ($link['rel'] == 'replies' && false === strpos($link['href'], '#comment-form'))
                {
                    $postreplies = $link['href'];
                }
            }

            //Check if we are double cleaning here? Does the Simplepie already do all this?
            $post_date = $entry->published;
            $post_content = trim(addslashes($this->no_apos(@html_entity_decode($entry->content, ENT_COMPAT, get_option('blog_charset')))));
            $post_title = trim(addslashes($this->no_apos($this->min_whitespace($entry->title))));

            $post_status = $entry->isDraft ? 'draft' : 'publish';

            // N.B. Clean up of $post_content is now part of the sanitize class

            // Checks for duplicates
            if (isset($this->blogs[$importing_blog]['posts'][$entry->old_permalink]))
            {
                $this->blogs[$importing_blog]['posts_skipped']++;
            } elseif ($post_id = post_exists($post_title, $post_content, $post_date))
            {
                $this->blogs[$importing_blog]['posts'][$entry->old_permalink] = $post_id;
                $this->blogs[$importing_blog]['posts_skipped']++;
            } else
            {
                $post = compact('post_date', 'post_content', 'post_title', 'post_status');

                $post_id = wp_insert_post($post);
                if (is_wp_error($post_id))
                    return $post_id;

                wp_create_categories(array_map('addslashes', $entry->categories), $post_id);

                $author = $this->no_apos(strip_tags($entry->author));

                add_post_meta($post_id, 'blogger_blog', $this->blogs[$importing_blog]['host'], true);
                add_post_meta($post_id, 'blogger_author', $author, true);

                //Use the page id if available or the blogger internal id if it's a draft
                if ($entry->isDraft | !isset($entry->bookmark))
                    add_post_meta($post_id, 'blogger_permalink', $entry->old_permalink, true);
                else
                    add_post_meta($post_id, 'blogger_permalink', $entry->bookmark, true);

                add_post_meta($post_id, '_blogger_self', $postself, true);
                
                if (isset($entry->geotags)) {
                    add_post_meta($post_id,'geo_latitude',$entry->geotags['geo_latitude']);
                    add_post_meta($post_id,'geo_longitude',$entry->geotags['geo_longitude']);
                    if (isset($entry->geotags['geo_address'])) {
                        add_post_meta($post_id,'geo_address',$entry->geotags['geo_address']);
                    }
                }

                $this->blogs[$importing_blog]['posts'][$entry->old_permalink] = $post_id;

                $this->blogs[$importing_blog]['posts_done']++;
            }
            $this->save_vars();
            return;
        }

        function import_comment($entry)
        {
            global $importing_blog;

            $parts = parse_url($entry->source);
            $entry->old_post_permalink = $parts['path']; //Will be something like this '/feeds/417730729915399755/posts/default/8397846992898424746'

            // Drop the #fragment and we have the comment's old post permalink.
            foreach ($entry->links as $link)
            {
                if ($link['rel'] == 'alternate')
                {
                    $parts = parse_url($link['href']);
                    $entry->old_permalink = $parts['fragment'];
                }
                //Parent post for nested links
                if ($link['rel'] == 'related')
                {
                    $parts = parse_url($link['href']);
                    $entry->related = $parts['path'];
                }
                if ($link['rel'] == 'self')
                {
                    $parts = parse_url($link['href']);
                    $entry->self = $parts['path'];
                }
            }

            //Check for duplicated cleanup here
            $comment_post_ID = (int)$this->blogs[$importing_blog]['posts'][$entry->old_post_permalink];
            $comment_author = addslashes($this->no_apos(strip_tags($entry->author)));
            $comment_author_url = addslashes($this->no_apos(strip_tags($entry->authoruri)));
            $comment_author_email = addslashes($this->no_apos(strip_tags($entry->authoremail)));
            $comment_date = $entry->updated;

            // Clean up content
            // Again, check if the Simplepie is already handling all of the cleaning
            $comment_content = addslashes($this->no_apos(@html_entity_decode($entry->content, ENT_COMPAT, get_option('blog_charset'))));
            $comment_content = preg_replace_callback('|<(/?[A-Z]+)|', array(&$this, '_normalize_tag'), $comment_content);
            $comment_content = str_replace('<br>', '<br />', $comment_content);
            $comment_content = str_replace('<hr>', '<hr />', $comment_content);

            // Nested comment?
            if (!is_null($entry->related))
            {
                $comment_parent = $this->blogs[$importing_blog]['comments'][$entry->related];
            }

            // if the post does not exist then we need stop and not add the comment
            if ($comment_post_ID != 0)
            {
                // Checks for duplicates
                if (isset($this->blogs[$importing_blog][$entry->id]) || $this->comment_exists($comment_post_ID, $comment_author, $comment_date))
                {
                    $this->blogs[$importing_blog]['comments_skipped']++;
                } else
                {
                    $comment = compact('comment_post_ID', 'comment_author', 'comment_author_url', 'comment_author_email', 'comment_date', 'comment_content', 'comment_parent');

                    $comment = wp_filter_comment($comment);
                    $comment_id = wp_insert_comment($comment);

                    $this->blogs[$importing_blog]['comments'][$entry->id] = $comment_id;
                    $this->blogs[$importing_blog]['comments'][$entry->self] = $comment_id; //For nested comments

                    $this->blogs[$importing_blog]['comments_done']++;
                }
            } else
            {
                $this->blogs[$importing_blog]['comments_skipped']++;
            }
            $this->save_vars();
        }

        function ajax_die($data)
        {
            ob_clean(); //Discard any debug messages or other fluff already sent
            header('Content-Type: text/plain');
            die($data);
        }


        function get_js_status($blog = false)
        {
            global $importing_blog;
            if ($blog === false)
                $blog = $this->blogs[$importing_blog];
            else
                $blog = $this->blogs[$blog];

            $p1 = isset($blog['posts_done']) ? (int)$blog['posts_done'] : 0;
            $p2 = isset($blog['total_posts']) ? (int)$blog['total_posts'] : 0;
            $p3 = isset($blog['posts_skipped']) ? (int)$blog['posts_skipped'] : 0;
            $c1 = isset($blog['comments_done']) ? (int)$blog['comments_done'] : 0;
            $c2 = isset($blog['total_comments']) ? (int)$blog['total_comments'] : 0;
            $c3 = isset($blog['comments_skipped']) ? (int)$blog['comments_skipped'] : 0;
            return "{p1:$p1,p2:$p2,p3:$p3,c1:$c1,c2:$c2,c3:$c3}";
        }

        function get_author_form($blog = false)
        {
            global $importing_blog, $wpdb, $current_user;
            if ($blog === false)
                $blog = &$this->blogs[$importing_blog];
            else
                $blog = &$this->blogs[$blog];

            if (!isset($blog['authors']))
            {
                $post_ids = array_values($blog['posts']);
                $authors = (array )$wpdb->get_col("SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = 'blogger_author' AND post_id IN (" . join(',', $post_ids) . ")");
                $blog['authors'] = array_map(null, $authors, array_fill(0, count($authors), $current_user->ID));
                $this->save_vars();
            }

            $directions = sprintf(__('All posts were imported with the current user as author. Use this form to move each Blogger user&#8217;s posts to a different WordPress user. You may <a href="%s">add users</a> and then return to this page and complete the user mapping. This form may be used as many times as you like until you activate the &#8220;Restart&#8221; function below.',
                'blogger-importer'), 'users.php');
            $heading = __('Author mapping', 'blogger-importer');
            $blogtitle = "{$blog['title']} ({$blog['host']})";
            $mapthis = __('Blogger username', 'blogger-importer');
            $tothis = __('WordPress login', 'blogger-importer');
            $submit = esc_js(__('Save Changes', 'blogger-importer'));
            $rows = '';

            foreach ($blog['authors'] as $i => $author)
                $rows .= "<tr><td><label for='authors[$i]'>{$author[0]}</label></td><td><select name='authors[$i]' id='authors[$i]'>" . $this->get_user_options($author[1]) . "</select></td></tr>";

            return "<div class='wrap'>".screen_icon()."<h2>$heading</h2><h3>$blogtitle</h3><p>$directions</p><form action='index.php?import=blogger&amp;noheader=true&saveauthors=1' method='post'><input type='hidden' name='blog' value='" .
                esc_attr($importing_blog) . "' /><table cellpadding='5'><thead><td>$mapthis</td><td>$tothis</td></thead>$rows<tr><td></td><td class='submit'><input type='submit' class='button authorsubmit' value='$submit' /></td></tr></table></form></div>";
        }

        function get_user_options($current)
        {
            global $importer_users;
            if (!isset($importer_users))
                $importer_users = (array )get_users(); //Function: get_users_of_blog() Deprecated in version 3.1. Use get_users() instead.

            $options = '';

            foreach ($importer_users as $user)
            {
                $sel = ($user->ID == $current) ? " selected='selected'" : '';
                $options .= "<option value='$user->ID'$sel>$user->display_name</option>";
            }

            return $options;
        }

        function save_authors()
        {
            global $importing_blog, $wpdb;
            $blog = &$this->blogs[$importing_blog]; //Get a reference to blogs so we don't have to write it longhand

            $authors = (array )$_POST['authors'];

            $host = $blog['host'];

            // Get an array of posts => authors
            $post_ids = (array )$wpdb->get_col($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'blogger_blog' AND meta_value = %s", $host));
            $post_ids = join(',', $post_ids);
            $results = (array )$wpdb->get_results("SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = 'blogger_author' AND post_id IN ($post_ids)");
            foreach ($results as $row)
                $authors_posts[$row->post_id] = $row->meta_value;

            foreach ($authors as $author => $user_id)
            {
                $user_id = (int)$user_id;

                // Skip authors that haven't been changed
                if ($user_id == $blog['authors'][$author][1])
                    continue;

                // Get a list of the selected author's posts
                $post_ids = (array )array_keys($authors_posts, $blog['authors'][$author][0]);
                $post_ids = join(',', $post_ids);

                $wpdb->query($wpdb->prepare("UPDATE $wpdb->posts SET post_author = %d WHERE id IN ($post_ids)", $user_id));
                $blog['authors'][$author][1] = $user_id;
            }
            $this->save_vars();

            wp_redirect('edit.php');
        }

        function restart()
        {
            global $wpdb;
            $options = get_option('blogger_importer');

            if ( check_admin_referer( 'clear-blogger-importer', 'clear-blogger-importer-nonce' ) ) {
                delete_option('blogger_importer');
                $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = 'blogger_author'");
            }
            wp_redirect('?import=blogger');
            exit;
        }

        // Step 9: Congratulate the user
        function congrats()
        {
            $blog = (int)$_GET['blog'];
            echo '<h1>' . __('Congratulations!', 'blogger-importer') . '</h1><p>' . __('Now that you have imported your Blogger blog into WordPress, what are you going to do? Here are some suggestions:',
                'blogger-importer') . '</p><ul><li>' . __('That was hard work! Take a break.', 'blogger-importer') . '</li>';
            if (count($this->import['blogs']) > 1)
                echo '<li>' . __('In case you haven&#8217;t done it already, you can import the posts from your other blogs:', 'blogger-importer') . $this->show_blogs() . '</li>';
            if ($n = count($this->import['blogs'][$blog]['newusers']))
                echo '<li>' . sprintf(__('Go to <a href="%s" target="%s">Authors &amp; Users</a>, where you can modify the new user(s) or delete them. If you want to make all of the imported posts yours, you will be given that option when you delete the new authors.',
                    'blogger-importer'), 'users.php', '_parent') . '</li>';
            echo '<li>' . __('For security, click the link below to reset this importer.', 'blogger-importer') . '</li>';
            echo '</ul>';
        }

        // Figures out what to do, then does it.
        function start()
        {
            if (isset($_POST['restart']))
                $this->restart();

            $options = get_option('blogger_importer');

            if (is_array($options))
                foreach ($options as $key => $value)
                    $this->$key = $value;

            if (isset($_REQUEST['blog']))
            {
                $blog = is_array($_REQUEST['blog']) ? array_shift($keys = array_keys($_REQUEST['blog'])) : $_REQUEST['blog'];
                $blog = (int)$blog;
                $result = $this->import_blog($blog);
                if (is_wp_error($result))
                    echo $result->get_error_message();
            } elseif (isset($_GET['token']) && isset($_GET['token_secret']))
                $this->auth();
            elseif (isset($this->token) && isset($this->token_secret))
                $this->show_blogs();
            else
                $this->greet();

            $saved = $this->save_vars();

            if ($saved && !isset($_GET['noheader']))
            {
                $restart = __('Restart', 'blogger-importer');
                $message = __('We have saved some information about your Blogger account in your WordPress database. Clearing this information will allow you to start over. Restarting will not affect any posts you have already imported. If you attempt to re-import a blog, duplicate posts and comments will be skipped.',
                    'blogger-importer');
                $submit = esc_attr__('Clear account information', 'blogger-importer');
                echo "<div class='wrap'><h2>$restart</h2><p>$message</p><form method='post' action='?import=blogger&amp;noheader=true'>";
                wp_nonce_field( 'clear-blogger-importer', 'clear-blogger-importer-nonce' ); 
                echo "<p class='submit' style='text-align:left;'><input type='submit' class='button' value='$submit' name='restart' /></p></form></div>";
            }
        }

        function save_vars()
        {
            $vars = get_object_vars($this);
            update_option('blogger_importer', $vars);

            return !empty($vars);
        }

        function comment_exists($post_id, $comment_author, $comment_date)
        {
            //Do we have 2 comments for the same author at the same time, on the same post?
            //returns comment id
            global $wpdb;

            $comment_author = stripslashes($comment_author);
            $comment_date = stripslashes($comment_date);

            return $wpdb->get_var($wpdb->prepare("SELECT comment_ID FROM $wpdb->comments
			WHERE comment_post_ID = %s and comment_author = %s AND comment_date = %s", $post_id, $comment_author, $comment_date));
        }

    }

    class BloggerEntry
    {
        var $links = array();
        var $categories = array();
    }

} // class_exists( 'WP_Importer' )

function blogger_importer_init()
{
    load_plugin_textdomain('blogger-importer', false, dirname(plugin_basename(__file__)) . '/languages');

    $blogger_import = new Blogger_Import();
    register_importer('blogger', __('Blogger', 'blogger-importer'), __('Import categories, posts and comments then maps users from a Blogger blog.', 'blogger-importer'), array($blogger_import, 'start'));

}
add_action('admin_init', 'blogger_importer_init');

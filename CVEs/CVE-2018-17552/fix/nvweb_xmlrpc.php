<?php
/* resources
http://xmlrpc.scripting.com/metaWeblogApi.html
https://codex.wordpress.org/XML-RPC_MetaWeblog_API
http://blogs.technet.com/b/larsnakkerud/metablog.ashx
*/

function nvweb_xmlrpc()
{
	/* Functions defining the behaviour of the server */
	nvweb_webget_load('menu');
	nvweb_webget_load('content');

	function metaWeblog_getUsersBlogs($args)
	{
		global $DB;

		$out = array();

		list($appkey, $username, $password) = $args;

		$user = new user();
		$error = !$user->authenticate($username, $password);

		if(!empty($error) || $user->blocked == '1')
		{
			$out = new IXR_Error(401, "User not allowed.");
		}
		else
		{
			$websites_ids = $user->websites;
			if(empty($websites_ids))
			{
				// get all websites
				$DB->query('SELECT id FROM nv_websites WHERE permission = 0'); // only public websites
				$websites_ids = $DB->result();
			}

			if(is_array($websites_ids))
			{
				foreach($websites_ids as $wid)
				{
					$website = new website();
					$website->load($wid);

					$out[] = array(
				        'blogid'    => $website->id,                            // website id
				        'url'       => $website->absolute_path(true).$website->homepage(),  // homepage
				        'blogName'  => $website->name,                          // website name
		                'isAdmin'   => false,                                   // is admin, unused
				        'xmlrpc'    => $website->absolute_path(true).'/xmlrpc'  // URL endpoint to use for XML-RPC requests
			        );
				}
			}
		}

		return $out;
	}

	function metaWeblog_getRecentPosts($args)
	{
		global $DB;
		global $session;

		$out = array();
		list($website_id, $username, $password, $number_of_posts) = $args;

		if(empty($number_of_posts))
			$number_of_posts = 50;

		// check auth
		if(metaWeblog_userAllowed($username, $password, $website_id))
		{
			$DB->query('
				SELECT id
				  FROM nv_items
				 WHERE website = '.$website_id.'
				   AND permission < 2
			  ORDER BY date_modified DESC
			     LIMIT '.$number_of_posts.'
			');
			$posts = $DB->result();

			foreach($posts as $post)
			{
				$item = new item();
				$item->load($post->id);

				if($item->embedding == 1)
					$link = nvweb_source_url('structure', $item->category, $session['lang']);
				else
					$link = $item->link($session['lang']);

				$category = new structure();
				$category->load($item->category);

				$content = $item->dictionary[$session['lang']]['section-main'];
				$content = nvweb_template_fix_download_paths($content);

				if(empty($item->date_to_display))
					$item->date_to_display = $item->date_created;

				$out[] = array(
					"postid" => $item->id,
					"userid" => $item->author,
					"dateCreated" => new IXR_Date($item->date_to_display), // iso8601
					"category" => $item->category,
					"title" => $item->dictionary[$session['lang']]['title'],
					"description" => $content,
					"url" => $link,
					"permalink" => $link,
					"mt_keywords" => $item->dictionary[$session['lang']]['tags']
				);
			}
		}
		else
		{
			$out = new IXR_Error(401, "User not allowed.");
		}
		return $out;
	}

	function metaWeblog_getCategories($args)
	{
		global $DB;
		global $session;

		$out = array();
		list($website_id, $username, $password) = $args;

		// check auth
		if(metaWeblog_userAllowed($username, $password, $website_id))
		{
			// get all public categories
			$DB->query('
				SELECT id
				  FROM nv_structure
				 WHERE website = '.intval($website_id).'
				   AND permission < 2
				ORDER BY parent ASC, position  ASC
			');
			$categories = $DB->result();

			$website = new website();
			$website->load($website_id);

			foreach($categories as $category)
			{
				$cat = new structure();
				$cat->load($category->id);

				$url = '';
				if($cat->paths[$session['lang']])
					$url = $website->absolute_path(true).$cat->paths[$session['lang']];

				$out[] = array(
					"categoryId" => $cat->id,
					"parentId" => $cat->parent,
					"categoryName" => $cat->dictionary[$session['lang']]['title'],
					"categoryDescription" => $cat->template,
					"htmlUrl" => $url,
					"rssUrl" => "",
					"title" => $cat->dictionary[$session['lang']]['title'],
					"description" => $cat->template
				);
			}
		}
		else
		{
			$out = new IXR_Error(401, "User not allowed.");
		}

		return $out;
	}


	// Makes a new post to a designated weblog using the MetaWeblog API. Returns postid as a string
	function metaWeblog_newPost($args)
	{
		global $DB;
		global $session;

		$out = array();
		list($website_id, $username, $password, $post, $publish) = $args;

		// check auth
		if(metaWeblog_userAllowed($username, $password, $website_id))
		{
			$category_name = $post['categories'];
			$category = "";

			// category name is text, we have to find the category ID
			if(is_array($category_name))
			{
				$category_name = array_shift($category_name);
				$DB->query('
					SELECT s.id
					  FROM nv_structure s
					 WHERE s.website = '.intval($website_id).'
					   AND s.id IN (
					        SELECT w.node_id
					          FROM nv_webdictionary w
					         WHERE w.website = '.intval($website_id).'
					           AND w.node_type = "structure"
					           AND w.subtype = "title"
					           AND w.text LIKE :category_name
					   )',
                    'object',
                    array(':category_name' => $category_name)
                );

				$category = $DB->result('id');
				$category = $category[0];
				if(!isset($post['post_type']) || empty($post['post_type']))
					$post['post_type'] = 'post';
			}

			$template = 'content';
			$association = 'free';
			$embedded = '1';

			if($post['post_type'] == 'post')
			{
				$template = 'blog_entry';
				$association = 'category';
				$embedded = '0';
			}

			if(empty($post['dateCreated']))
				$post['dateCreated'] = date("c", time());

			if(!isset($post['mt_text_more']))
				$post['mt_text_more'] = "";

			$item = new item();
			$item->association = $association;
			$item->template = $template;
			$item->category = $category;
			$item->embedding = $embedded;
			$item->permission = ($publish? '0' : '1');
			$item->dictionary = array($session['lang'] => array());
			$item->paths = array($session['lang'] => array());
			$item->date_to_display = strtotime($post['dateCreated']);
			$item->dictionary[$session['lang']]['title'] = html_entity_decode($post['title']);
			$item->dictionary[$session['lang']]['section-main'] = $post['description'] . $post['mt_text_more'];
			$item->dictionary[$session['lang']]['tags'] = $post['mt_keywords'];
			$item->comments_enabled_to = ($post['mt_allow_comments']=='1'? 2 : 0); // everybody or nobody
			$item->save();

			$out = $item->id;
		}
		else
		{
			$out = new IXR_Error(401, "User not allowed.");
		}

		return $out;
	}

	// Updates and existing post to a designated weblog using the MetaWeblog API. Returns true if completed.
	function metaWeblog_editPost($args)
	{
		global $DB;
		global $session;

		$out = array();
		list($post_id, $username, $password, $post, $publish) = $args;

		// check auth
		if(metaWeblog_userAllowed($username, $password))
		{
			if(!isset($post['mt_text_more']))
				$post['mt_text_more'] = "";

			$item = new item();
			$item->load($post_id);
			$item->dictionary[$session['lang']]['title'] = $post['title'];
			$item->dictionary[$session['lang']]['section-main'] = $post['description'] . $post['mt_text_more'];

			if(isset($post['mt_keywords']))
				$item->dictionary[$session['lang']]['tags'] = $post['mt_keywords'];

			if(!is_null($publish))
				$item->permission = ($publish? '0' : '1');

			$out = $item->save();
		}
		else
		{
			$out = new IXR_Error(401, "User not allowed.");
		}

		return $out;
	}

	// Retrieves an existing post using the MetaWeblog API. Returns the MetaWeblog struct.
	function metaWeblog_getPost($args)
	{
		global $DB;
		global $session;

		$out = array();
		list($post_id, $username, $password) = $args;

		$item = new item();
		$item->load(intval($post_id));
		$website_id = $item->website;

		$website = new website();
		$website->load($website_id);

		// check auth
		if(metaWeblog_userAllowed($username, $password, $website_id))
		{
			if($item->embedding == 1)
				$link = nvweb_source_url('structure', $item->category, $session['lang']);
			else
				$link = $item->link($session['lang']);

			$category = new structure();
			$category->load($item->category);

			$content = $item->dictionary[$session['lang']]['section-main'];
			$content = nvweb_template_fix_download_paths($content);

			if(empty($item->date_to_display))
				$item->date_to_display = $item->date_created;

			$out = array(
				"postid" => $item->id,
				"userid" => $item->author,
				"dateCreated" => new IXR_Date($item->date_to_display), // iso8601
				"category" => $item->category,
				"title" => $item->dictionary[$session['lang']]['title'],
				"description" => $content,
				"url" => $link,
				"permalink" => $link,
				"mt_keywords" => $item->dictionary[$session['lang']]['tags']
			);
		}
		else
		{
			$out = new IXR_Error(401, "User not allowed.");
		}

		return $out;
	}


	// Deletes a post.
	/* NOT IMPLEMENTED
	function metaWeblog_deletePost($args)
	{
		global $DB;
		global $session;

		$out = array();
		list($post_id, $username, $password) = $args;

		// check auth
		if(metaWeblog_userAllowed($username, $password, $website_id))
		{
			$out = true;
		}
		else
		{
			$out = new IXR_Error(401, "User not allowed.");
		}

		return $out;
	}
	*/

	// add a new media object
	function metaWeblog_newMediaObject($args)
	{
		global $DB;
		global $session;

		$out = array();
		list($website_id, $username, $password, $file_struct) = $args;

		// check auth
		if(metaWeblog_userAllowed($username, $password, $website_id))
		{
			$file_name_tmp = uniqid('metaweblog-upload-');
			file_put_contents(NAVIGATE_PRIVATE.'/'.$website_id.'/files/'.$file_name_tmp, $file_struct['bits']);
			$file = file::register_upload($file_name_tmp, $file_struct['name'], 0, NULL, false);
			@unlink(AVIGATE_PRIVATE.'/'.$website_id.'/files/'.$file_name_tmp); // if everything goes fine, file is renamed, so cannot be deleted here

			$out = array(
				'id' => $file->id,
				'file' => $file->name,
				'url' => file::file_url($file->id, 'inline'),
				'type' => $file->mime
			);
		}
		else
		{
			$out = new IXR_Error(401, "User not allowed.");
		}

		return $out;
	}


	// helper functions
	function metaWeblog_userAllowed($username, $password, $website_id=NULL)
	{
		$user = new user();
		$allowed = false;
		$error = !($user->authenticate($username, $password));

		if(empty($error) && $user->blocked != '1')
		{
			$websites_ids = $user->websites;

			if(!empty($website_id) && !empty($websites_ids))
				$allowed = in_array($website_id, $websites_ids);
			else
				$allowed = true;
		}

		return $allowed;
	}

	// if we have a RSD request, don't run the XMLRPC server, just give an XML respnse
	// http://cyber.law.harvard.edu/blogs/gems/tech/rsd.html
	if(isset($_GET['rsd']))
	{
		global $website;

		header('Content-Type: text/xml; charset=UTF-8', true);
		$out = array();
		$out[] = '<?xml version="1.0" encoding="UTF-8"?>';
		$out[] = '<rsd version="1.0" xmlns="http://archipelago.phrasewise.com/rsd">';
	    $out[] = '<service>';
	    $out[] = '  <engineName>Navigate CMS</engineName>';
	    $out[] = '	<engineLink>http://www.navigatecms.com</engineLink>';
	    $out[] = '  <homePageLink>'.$website->absolute_path().'</homePageLink>';
	    $out[] = '	<apis>';
	    $out[] = '      <api name="MetaWeblog" blogID="'.$website->id.'" preferred="true" apiLink="'.$website->absolute_path().'/xmlrpc" />';
	    $out[] = '      <api name="Blogger" blogID="'.$website->id.'" preferred="false" apiLink="'.$website->absolute_path().'/xmlrpc" />';
        $out[] = '	</apis>';
	    $out[] = '</service>';
		$out[] = '</rsd>';
		echo implode("\n", $out);
	}
	else
	{
		// debug
		// $request = file_get_contents('php://input');
		// file_put_contents(NAVIGATE_PATH.'/web/request.txt', $request);

		// Create the server and map the XML-RPC method names to the relevant functions
		$server = new IXR_Server(
			array(
				// metaWeblog endpoints
			    'metaWeblog.newPost' => 'metaWeblog_newPost',
			    'metaWeblog.editPost' => 'metaWeblog_editPost',
			    'metaWeblog.getPost' => 'metaWeblog_getPost',
			    'metaWeblog.deletePost' => 'metaWeblog_deletePost',
			    'metaWeblog.newMediaObject' => 'metaWeblog_newMediaObject',
			    'metaWeblog.getCategories' => 'metaWeblog_getCategories',
			    'metaWeblog.getRecentPosts' => 'metaWeblog_getRecentPosts',
			    'metaWeblog.getUsersBlogs' => 'metaWeblog_getUsersBlogs',
			    'blogger.getUsersBlogs' => 'metaWeblog_getUsersBlogs'

				// TODO: PingBack endpoints
				//'pingback.ping' => 'pingback_ping',
				//'pingback.extensions.getPingbacks' => 'pingback_extensions_getPingbacks'
			)
		);
	}
}

?>
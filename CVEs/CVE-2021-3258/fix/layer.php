<?php

class qa_html_theme_layer extends qa_html_theme_base {
	var $meta_title;
	var $meta_description;
	var $meta_keywords;
	var $metas = array();
	var $social_metas = array();
	function doctype(){
		qa_html_theme_base::doctype();
		require_once QA_INCLUDE_DIR.'qa-db-metas.php';
		// Custom Meta(title,description,keywords)
		if( ($this->template=='question') and (qa_opt('useo_meta_editor_enable')) ){
			$metas = json_decode(qa_db_postmeta_get($this->content['q_view']['raw']['postid'], 'useo-meta-info'),true);
			$this->meta_title = htmlspecialchars(@$metas['title']);
			$this->meta_description = htmlspecialchars(@$metas['description']);
			$this->meta_keywords = htmlspecialchars(@$metas['keywords']);
		}
		// Generate Social Meta Tags
		$page_url = @$this->content['canonical'];
		if(! empty($this->meta_description))
			$description = htmlspecialchars($this->meta_description);
		else
			$description = htmlspecialchars(@$this->content['description']);
		if(! empty($this->meta_title))
			$title = htmlspecialchars($this->meta_title);
		else
			$title = htmlspecialchars(@$this->content['q_view']['raw']['title']);
		
		if($this->template=='question'){
			if(qa_opt('useo_social_enable_editor')){
				$this->social_metas = json_decode(qa_db_postmeta_get($this->content['q_view']['raw']['postid'], 'useo-social-info'),true);
				if(count($this->social_metas))
					foreach ($this->social_metas as $index => $variable){
						$this->metas[$index]['content'] = htmlspecialchars($variable);
						$this->metas[$index]['type'] = '';
					}
			}
			if(qa_opt('useo_social_og_enable_auto')){ // Open Graph
				// site name
				$this->metas['og-sitename']['content'] = @$this->content['site_title'];
				$this->metas['og-sitename']['type'] = 'property="og:site_name"';
				// title
				$this->metas['og-title']['content'] = $title;
				$this->metas['og-title']['type'] = 'property="og:title"';
				// description
				$gl_length = qa_opt('useo_social_og_desc_length');
				if($gl_length<=0)
						$gl_length = 140;
				$this->metas['og-description']['content'] = useo_get_excerpt($description, 0, $gl_length);
				$this->metas['og-description']['type'] = 'property="og:description"';
				// Type
				$this->metas['og-type']['content'] = 'website';
				$this->metas['og-type']['type'] = 'property="og:type"';
				// url
				if(! empty($page_url)){
					$this->metas['og-url']['content'] = $page_url;
					$this->metas['og-url']['type'] = 'property="og:url"';
				}
				// image
				$og_image = qa_opt('useo_social_og_image');
				if(! empty($og_image)){
					$this->metas['og-image']['content'] = $og_image;
					$this->metas['og-image']['type'] = 'property="og:image"';
				}
			}
			if(qa_opt('useo_social_tc_enable')){ // Twitter Cards
				// type
				$this->metas['tc-type']['content'] = 'summary';
				$this->metas['tc-type']['type'] = 'property="twitter:card"';
				// title
				$this->metas['tc-title']['content'] = $title;
				$this->metas['tc-title']['type'] = 'property="twitter:title"';
				// description
				$useo_social_tc_desc_length = qa_opt('useo_social_og_desc_length');
				if($useo_social_tc_desc_length<=0)
					$useo_social_tc_desc_length = 120;
				$this->metas['tc-description']['content'] = useo_get_excerpt($description, 0, $useo_social_tc_desc_length);
				$this->metas['tc-description']['type'] = 'property="twitter:description"';
				// image
				$tc_image = qa_opt('useo_social_tc_image');
				if(! empty($tc_image)){
					$this->metas['tc-image']['content'] = $tc_image;
					$this->metas['tc-image']['type'] = 'property="twitter:image"';
				}
				// handler
				// twitter handler goes into "site" field of meta tag
				$tc_handler = qa_opt('useo_social_tc_handler');
				if(! empty($tc_handler)){
					$this->metas['tc-handler']['content'] = $tc_handler;
					$this->metas['tc-handler']['type'] = 'property="twitter:site"';
				}
			}
			if(qa_opt('useo_social_schema_enable')){ // Twitter Cards
				// title
				$this->metas['gp-title']['content'] = $title;
				$this->metas['gp-title']['type'] = 'itemprop="name"';
				// description
				$this->metas['gp-title']['content'] = $description;
				$this->metas['gp-title']['type'] = 'itemprop="description"';
				// type
				$gp_type = qa_opt('useo_social_schema_page_type');
				if($gp_type==2)
					$gp_page_type = 'Question';
				elseif($gp_type==3)
					$gp_page_type = 'Article';
				if( isset($gp_page_type) ){ 
					$this->metas['gp-title']['content'] = '';
					$this->metas['gp-title']['type'] = 'itemscope itemtype="http://schema.org/' . $gp_page_type . '"';
				}
				// description
				$gp_image = qa_opt('useo_social_gp_thumbnail');
				if(! empty($gp_image)){
					$this->metas['gp-image']['content'] = $gp_image;
					$this->metas['gp-image']['type'] = 'itemprop="image"';
				}
			}
		}
		// category link titles
		$useo_cat_desc_map = array();
		$categoryid_list = array();
		//prepare category navigation ids
		if(isset($this->content['navigation']['cat']) && qa_opt('useo_cat_title_nav_enable')){
			$category_nav = $this->content['navigation']['cat'];
			unset($category_nav['all']);
			foreach ($category_nav as $index => $item){
				$categoryid_list[$item['categoryid']] = $item['categoryid'];
			}
		}
		// prepare question list ids
		if(isset($this->content["q_list"]["qs"]) && qa_opt('useo_cat_title_qlist_enable')){
			foreach ($this->content["q_list"]["qs"] as $index => $item){
				if($item['raw']['categoryid'])
					$categoryid_list[$item['raw']['categoryid']] = $item['raw']['categoryid'];
			}
		}
		// get all category titles
		if(count($categoryid_list)){
			$result=qa_db_query_sub(
				'SELECT categoryid, content FROM ^categorymetas WHERE categoryid IN ($) AND title=$',
				$categoryid_list,'useo_cat_title'
			);
			$useo_cat_desc_map = qa_db_read_all_assoc($result,'categoryid');
			if(isset($this->content["q_list"]["qs"]))
				foreach ($this->content["q_list"]["qs"] as $index => $item){
					if(isset($item['raw']['categoryid']) && isset($useo_cat_desc_map[$item['raw']['categoryid']]))
						$this->content["q_list"]["qs"][$index]['where']['title'] = $useo_cat_desc_map[$item['raw']['categoryid']]['content'];
				}
		}
		// set category title for navigation
		if(count(@$this->content['navigation']['cat']) && qa_opt('useo_cat_title_nav_enable')){
			foreach ($this->content['navigation']['cat'] as $index => $item){
				if(isset($item['categoryid']) && isset($useo_cat_desc_map[$item['categoryid']]))
					$this->content['navigation']['cat'][$index]["popup"] = $useo_cat_desc_map[$item['categoryid']]['content'];
			}
		}
		// Administrator panel navigation item
		if ($this->request == 'admin/ulitmate_seo') {
			if(empty($this->content['navigation']['sub']))
				$this->content['navigation']['sub']=array();
			require_once QA_INCLUDE_DIR.'qa-app-admin.php';
			$admin_nav = qa_admin_sub_navigation();
			$this->content['navigation']['sub'] = array_merge(
				$admin_nav,
				$this->content['navigation']['sub']
			);
		}
		if ( ($this->template=='admin') or ($this->request == 'ulitmate_seo') ){
			$this->content['navigation']['sub']['ulitmate_seo'] = array(
				'label' => 'Ultimate SEO',
				'url' => qa_path_html('admin/ulitmate_seo'),
			);
			if ($this->request == 'admin/ulitmate_seo'){
				$this->content['navigation']['sub']['ulitmate_seo']['selected'] = true;
			}
		}
	}
	// add canonical links to category pages
	function head_metas()
	{
		qa_html_theme_base::head_metas();
		if(qa_opt('useo_cat_canonical_enable')){
			$cat_slugs = useo_get_current_category_slug();
			if($cat_slugs){ // it's a category page
				$path = qa_path_absolute(implode('/', $cat_slugs));
				$this->output('<link rel="canonical" href="' . $path . '">');
			}
		}
	}
	function head_script()
	{
		qa_html_theme_base::head_script();
		if ( ($this->template=='question') and (qa_get_logged_in_level() >= QA_USER_LEVEL_ADMIN) ){
			$variables = '';
			$variables .= 'useo_ajax_url = "' . USEO_URL . '/ajax.php";';
			$variables .= 'useo_postid = ' . $this->content['q_view']['raw']['postid'] .';';
			$this->output('<script>' . $variables . '</script>');
			$this->output('<script src="'.USEO_URL.'/include/seo-forms.js" type="text/javascript"></script>');
		}
	}	

	function head_title()
	{

	// Title Customization Options
		$title = '';
		switch ($this->template) {
			case 'qa':
				$title_template = qa_opt('useo_title_qa');
				if(! empty($title_template) ){
					$search = array( '%site-title%');
					$replace   = array(qa_opt('site_title'));
					$title = str_replace($search, $replace, $title_template);
				}
				break;
			case 'question':
				$category_name = '';
				if ( (isset($this->content["categoryids"])) && (!(empty($this->content["categoryids"]))))
						$category_name = $this->content["q_view"]["raw"]["categoryname"];

				if(empty($this->meta_title)){
					// title customization
					$title_template = qa_opt('useo_title_qa_item');
					if(! empty($title_template) ){
						$search = array( '%site-title%', '%question-title%', '%category-name%');
						$replace   = array(qa_opt('site_title'), htmlspecialchars(@$this->content['q_view']['raw']['title']), $category_name);
						$title = str_replace($search, $replace, $title_template);
					}
				}else{
					// meta editor
					$title = $this->meta_title;
				}
				break;
			case 'questions':
				$category_name = '';
				if( count(explode('/',$this->request)) > 1 )
					$category_name = $this->content["q_list"]["qs"][0]["raw"]["categoryname"];
				$sort = qa_get('sort');
				if(empty($sort)){
					$title_template = qa_opt('useo_title_recent');
					if(! empty($title_template) ){
						$search = array( '%site-title%', '%recent-qs-title%', '%category-name%');
						$replace   = array(qa_opt('site_title'), qa_lang_html('main/recent_qs_title'), $category_name);
						$title = str_replace($search, $replace, $title_template);
					}
				}elseif($sort=='hot'){
					$title_template = qa_opt('useo_title_hot');
					if(! empty($title_template) ){
						$search = array( '%site-title%', '%hot-qs-title%');
						$replace   = array(qa_opt('site_title'), qa_lang_html('main/hot_qs_title'));
						$title = str_replace($search, $replace, $title_template);
					}
				}elseif($sort=='votes'){
					$title_template = qa_opt('useo_title_voted');
					if(! empty($title_template) ){
						$search = array( '%site-title%', '%voted-qs-title%');
						$replace   = array(qa_opt('site_title'), qa_lang_html('main/voted_qs_title'));
						$title = str_replace($search, $replace, $title_template);
					}
				}elseif($sort=='answers'){
					$title_template = qa_opt('useo_title_answered');
					if(! empty($title_template) ){
						$search = array( '%site-title%', '%answered-qs-title%');
						$replace   = array(qa_opt('site_title'), qa_lang_html('main/answered_qs_title'));
						$title = str_replace($search, $replace, $title_template);
					}
				}elseif($sort=='views'){
					$title_template = qa_opt('useo_title_viewed');
					if(! empty($title_template) ){
						$search = array( '%site-title%', '%viewed-qs-title%');
						$replace   = array(qa_opt('site_title'), qa_lang_html('main/viewed_qs_title'));
						$title = str_replace($search, $replace, $title_template);
					}
				}
			case 'unanswered':
				$sort = qa_get('by');
				$category_name = '';
				if( count(explode('/',$this->request)) > 1 )
					$category_name = $this->content["q_list"]["qs"][0]["raw"]["categoryname"];

				if(empty($sort)){
					$title_template = qa_opt('useo_title_unanswered');
					if(! empty($title_template) ){
						$search = array( '%site-title%', '%unanswered-qs-title%', '%category-name%');
						$replace   = array(qa_opt('site_title'), qa_lang_html('main/unanswered_qs_title'), $category_name);
						$title = str_replace($search, $replace, $title_template);
					}
				}elseif($sort=='selected'){
					$title_template = qa_opt('useo_title_unselected');
					if(! empty($title_template) ){
						$search = array( '%site-title%', '%unselected-qs-title%');
						$replace   = array(qa_opt('site_title'), qa_lang_html('main/unselected_qs_title'));
						$title = str_replace($search, $replace, $title_template);
					}
				}elseif($sort=='upvotes'){
					$title_template = qa_opt('useo_title_unupvoted');
					if(! empty($title_template) ){
						$search = array( '%site-title%', '%unupvoteda-qs-title%');
						$replace   = array(qa_opt('site_title'), qa_lang_html('main/unupvoteda_qs_title'));
						$title = str_replace($search, $replace, $title_template);
					}
				}
				break;
			case 'activity':
				$title_template = qa_opt('useo_title_activity');
				if(! empty($title_template) ){
					$category_name = '';
					if( count(explode('/',$this->request)) > 1 )
						$category_name = $this->content["q_list"]["qs"][0]["raw"]["categoryname"];

					$search = array( '%site-title%', '%recent-activity-title%', '%category-name%');
					$replace   = array(qa_opt('site_title'), qa_lang_html('main/recent_activity_title'), $category_name);
					$title = str_replace($search, $replace, $title_template);
				}
				break;
			case 'ask':
				$title_template = qa_opt('useo_title_ask');
				if(! empty($title_template) ){
					$search = array( '%site-title%', '%ask-title%');
					$replace   = array(qa_opt('site_title'), qa_lang_html('question/ask_title'));
					$title = str_replace($search, $replace, $title_template);
				}
				break;
			case 'categories':
				$title_template = qa_opt('useo_title_categories');
				if(! empty($title_template) ){
					$search = array( '%site-title%', '%browse-categories%');
					$replace   = array(qa_opt('site_title'), qa_lang_html('misc/browse_categories'));
					$title = str_replace($search, $replace, $title_template);
				}
				break;
			case 'tags':
				$title_template = qa_opt('useo_title_tags');
				if(! empty($title_template) ){
					$search = array( '%site-title%', '%popular-tags%');
					$replace   = array(qa_opt('site_title'), qa_lang_html('main/popular_tags'));
					$title = str_replace($search, $replace, $title_template);
				}
				break;
			case 'tag':
				$title_template = qa_opt('useo_title_tag');
				if(! empty($title_template) ){
					$req = explode('/',$this->request);
					$tag = $req[1];
					$search = array( '%site-title%', '%questions-tagged-x%', '%current-tag%');
					$replace   = array(qa_opt('site_title'), qa_lang_html_sub('main/questions_tagged_x', qa_html($tag)), $tag );
					$title = str_replace($search, $replace, $title_template);
				}
				break;
			case 'search':
				$title_template = qa_opt('useo_title_search');
				if(! empty($title_template) ){
					$term = qa_get('q');
					$search = array( '%site-title%', '%results-for-x%', '%current-term%');
					$replace   = array(qa_opt('site_title'), qa_lang_html_sub('main/results_for_x', qa_html($term)), $term );
					$title = str_replace($search, $replace, $title_template);
				}
				break;
			case 'users':
				$title_template = qa_opt('useo_title_users');
				if(! empty($title_template) ){
					$search = array( '%site-title%', '%highest-users%');
					$replace   = array(qa_opt('site_title'), qa_lang_html('main/highest_users'));
					$title = str_replace($search, $replace, $title_template);
				}
				break;
			case 'user':
				$title_template = qa_opt('useo_title_user');
				if(! empty($title_template) ){
					$req = explode('/',$this->request);
					$user = $req[1];
					$search = array( '%site-title%', '%user-x%', '%current-user%');
					$replace   = array(qa_opt('site_title'), qa_lang_html_sub('main/results_for_x', qa_html($user)), $user );
					$title = str_replace($search, $replace, $title_template);
				}
				break;


		}

		if(empty($title))
			qa_html_theme_base::head_title();
		else
			$this->output('<title>'.$title.'</title>');
		
	// Page Meta Tags
		$noindex = qa_opt('useo_access_noindex');
		$nofollow = qa_opt('useo_access_nofollow');
		if($noindex and $nofollow)
			$this->output('<meta name="robots" content="noindex, nofollow" />');
		elseif($noindex)
			$this->output('<meta name="robots" content="noindex" />');
		else{
			// if page is not already noindex, check if it needs to be noindex. also add nofollow if necessary
			$status = 1; // content is long enough
			if( ($this->template=='question') and (qa_opt('useo_access_length_enable')) and ( (int)qa_opt('useo_access_length')>0 ) ){
				$status = 0;
				$minimum_words = (int)qa_opt('useo_access_length');
				$word_count = str_word_count($this->content['q_view']['raw']['title']) + str_word_count($this->content['q_view']['raw']['content']);
				if($word_count >= $minimum_words)
					$status = 1;
				else{
					foreach($this->content['q_view']['c_list']['cs'] as $comment)
						$word_count += str_word_count($comment['raw']['content']);
					if($word_count >= $minimum_words)
						$status = 1;
					else{
						foreach($this->content['a_list']['as'] as $answer)
							$word_count += str_word_count($answer['raw']['content']);
						if($word_count >= $minimum_words)
							$status = 1;
						else{
							foreach($this->content['a_list']['as'] as $answer)
								foreach($answer['c_list']['cs'] as $comment)
									$word_count += str_word_count($comment['raw']['content']);
							if($word_count >= $minimum_words)
								$status = 1;
						}
					}
				}
			}
			if( ($nofollow) && ($status==1) )
				$this->output('<meta name="robots" content="nofollow" />');
			elseif( ($nofollow) && ($status==0) )
				$this->output('<meta name="robots" content="noindex, nofollow" />');
			elseif($status==0)
				$this->output('<meta name="robots" content="noindex" />');
		}
	// Question Meta tags
		if($this->template=='question'){
			// setup custom meta keyword
			if (! empty($this->meta_keywords))
				$this->content['keywords'] = htmlspecialchars($this->meta_keywords);
			// setup custom meta description
			if (! empty($this->meta_description))
				$this->content['description'] = htmlspecialchars($this->meta_description);
			// if there was no custom meta description and it's supposed to read it from answers do it, otherwise don't change it
			elseif(qa_opt('useo_meta_desc_ans_enable')){
				$lenght = (int)qa_opt('useo_meta_desc_length');
				if($lenght<=0)
					$lenght = 160;
				$text = '';
				if( ($this->content['q_view']['raw']['acount'] > 0) and (qa_opt('useo_meta_desc_ans_enable')) ){
					// get Selected Answer's content
					if( (isset($this->content["q_view"]["raw"]["selchildid"])) and (qa_opt('useo_meta_desc_sel_ans_enable')) ){
						foreach($this->content['a_list']['as'] as $answer)
							if($answer['raw']['isselected'])
								$text = $answer['raw']['content'];
					}else{
					// get most voted Answer's content
						$max_vote = 0; // don't use answers with negative votes.
						foreach($this->content['a_list']['as'] as $answer){
							if($answer['raw']['netvotes'] > $max_vote){
								$text = $answer['raw']['content'];
								$max_vote = $answer['raw']['netvotes'];
							}
						}
					}
				}
				if(!(empty($text))){
					global $qa_sanitize_html_newwindow;
					$excerpt = useo_get_excerpt(qa_sanitize_html($text, $qa_sanitize_html_newwindow), 0, $lenght);
					$this->content['description'] = $excerpt;
				}
			}
			// Meta Tags and social meta tags
			if($this->template=='question'){
				if(qa_opt('useo_social_enable_editor')){
					foreach($this->metas as $key => $value)
						if( isset($this->social_metas[$key]) )
							$this->output('<meta ' . $value['type'] . ' content="' . $this->social_metas[$key] . '" />' );
						else
							$this->output('<meta ' . $value['type'] . ( $value['content'] ? ' content="' . $value['content'] . '"' : '') . ' /> ' );
				
				}elseif(qa_opt('useo_social_og_enable_auto')){
					foreach($this->metas as $key => $value)
						$this->output('<meta ' . $value['type'] . ( $value['content'] ? ' content="' . $value['content'] . '"' : '') . ' /> ' );
				}
			}
		}
	}
	function main_parts($content)
	{
		qa_html_theme_base::main_parts($content);

		if( (qa_get_logged_in_level() >= QA_USER_LEVEL_ADMIN) and ($this->template=='question') and (qa_opt('useo_meta_editor_enable')) ){
			
			$this->output('<div class="qa-widgets-main qa-widgets-main-low">');
			$this->output('<form name="useo-meta-editor" action="'.qa_self_html().'" method="post">');
			$this->output('
			<h2> Page Title And Meta Tags </h2>
			<strong>Only administrators can see this section.</strong>
			<table class="qa-form-tall-table">
				<tbody>
					<tr>
						<td class="qa-form-tall-label">
							Page Title
						</td>
					</tr>
					<tr>
						<td class="qa-form-tall-data">
							<input placeholder="' . htmlspecialchars($this->content['q_view']['raw']['title']) . '" id="useo-meta-editor-title" class="qa-form-tall-text" type="text" value="'. $this->meta_title .'" name="useo-meta-editor-title">
						</td>
					</tr>
				</tbody>

				<tbody>
					<tr>
						<td class="qa-form-tall-label">
							Description Meta Tag
						</td>
					</tr>
					<tr>
						<td class="qa-form-tall-data">
							<textarea placeholder="' . htmlspecialchars($this->content['description']) . '" id="useo-meta-editor-description" class="qa-form-tall-text" cols="40" rows="3" name="useo-meta-editor-description">'. $this->meta_description .'</textarea>
						</td>
					</tr>
				</tbody>
				<tbody id="useo-meta-keywords">
					<tr>
						<td class="qa-form-tall-label">
							Keywords Meta Tag
						</td>
					</tr>
					<tr>
						<td class="qa-form-tall-data">
							<input placeholder="' . $this->content['keywords'] . '" id="useo-meta-editor-keywords" class="qa-form-tall-text" type="text" value="'. $this->meta_keywords .'" name="useo-meta-editor-keywords">
							<div class="qa-form-tall-note">A comma separated list of your most important keywords</div>
						</td>
					</tr>
				</tbody>
				<tbody>
					<tr>
						<td  id="useo_buttons_container_meta" class="qa-form-tall-buttons" colspan="1">
							<input id="useo_save_meta" class="qa-form-tall-button qa-form-tall-button-save" type="submit" title="" value="Save Options">
						</td>
					</tr>
				</tbody>
			</table>
			');
			$this->output('</form>');
			$this->output('<hr /></div>');
		}
		if( (qa_get_logged_in_level() >= QA_USER_LEVEL_ADMIN) and ($this->template=='question') and (qa_opt('useo_social_enable_editor')) ){
			
			$this->output('<div class="qa-widgets-main qa-widgets-main-low">');
			$this->output('<form name="useo-meta-editor" action="'.qa_self_html().'" method="post">');
			$this->output('
			<h2> Social Tags Editor </h2>
			<p>Only administrators can see this section.</p>
			<h3>Open Graph</h3>
			<table class="qa-form-tall-table">
				<tbody>
					<tr>
						<td class="qa-form-tall-label">
							Site Title
						</td>
					</tr>
					<tr>
						<td class="qa-form-tall-data">
							<input placeholder="' . @$this->metas['og-sitename']['content'] . '" id="useo-og-sitename" class="qa-form-tall-text" type="text" value="'. @$this->social_metas['og-sitename'] .'" name="useo-meta-editor-title">
						</td>
					</tr>
				</tbody>
				<tbody>
					<tr>
						<td class="qa-form-tall-label">
							Page Title
						</td>
					</tr>
					<tr>
						<td class="qa-form-tall-data">
							<input placeholder="' . @$this->metas['og-title']['content'] . '" id="useo-og-title" class="qa-form-tall-text" type="text" value="'. @$this->social_metas['og-title'] .'" name="useo-meta-editor-title">
						</td>
					</tr>
				</tbody>
				<tbody>
					<tr>
						<td class="qa-form-tall-label">
							Description Meta Tag
						</td>
					</tr>
					<tr>
						<td class="qa-form-tall-data">
							<textarea placeholder="' . @$this->metas['og-description']['content'] . '" id="useo-og-description" class="qa-form-tall-text" cols="40" rows="3" name="useo-meta-editor-description">'. @$this->social_metas['og-description'] .'</textarea>
						</td>
					</tr>
				</tbody>
				<tbody>
					<tr>
						<td class="qa-form-tall-label">
							Shared Page\'s URL
						</td>
					</tr>
					<tr>
						<td class="qa-form-tall-data">
							<input placeholder="' . @$this->metas['og-url']['content'] . '" id="useo-og-url" class="qa-form-tall-text" type="text" value="'. @$this->social_metas['og-url'] .'" name="useo-meta-editor-title">
						</td>
					</tr>
				</tbody>
				<tbody>
					<tr>
						<td class="qa-form-tall-label">
							Thumbnail Image
						</td>
					</tr>
					<tr>
						<td class="qa-form-tall-data">
							<input placeholder="' . @$this->metas['og-image']['content'] . '" id="useo-og-image" class="qa-form-tall-text" type="text" value="'. @$this->social_metas['og-image'] .'" name="useo-meta-editor-title">
						</td>
					</tr>
				</tbody>
			</table>
			<h3>Twitter Cards</h3>
			<table class="qa-form-tall-table">
				<tbody>
					<tr>
						<td class="qa-form-tall-label">
							Page Title
						</td>
					</tr>
					<tr>
						<td class="qa-form-tall-data">
							<input placeholder="' . @$this->metas['tc-title']['content'] . '" id="useo-tc-title" class="qa-form-tall-text" type="text" value="'. @$this->social_metas['tc-title'] .'" name="useo-meta-editor-title">
						</td>
					</tr>
				</tbody>
				<tbody>
					<tr>
						<td class="qa-form-tall-label">
							Description
						</td>
					</tr>
					<tr>
						<td class="qa-form-tall-data">
							<textarea placeholder="' . @$this->metas['tc-description']['content'] . '" id="useo-tc-description" class="qa-form-tall-text" cols="40" rows="3" name="useo-meta-editor-description">'. @$this->social_metas['tc-description'] .'</textarea>
						</td>
					</tr>
				</tbody>
				<tbody>
					<tr>
						<td class="qa-form-tall-label">
							Thumbnail Image
						</td>
					</tr>
					<tr>
						<td class="qa-form-tall-data">
							<input placeholder="' . @$this->metas['tc-image']['content'] . '" id="useo-tc-image" class="qa-form-tall-text" type="text" value="'. @$this->social_metas['tc-image'] .'" name="useo-meta-editor-title">
						</td>
					</tr>
				</tbody>
				<tbody>
					<tr>
						<td class="qa-form-tall-label">
							Twitter Handler
						</td>
					</tr>
					<tr>
						<td class="qa-form-tall-data">
							<input placeholder="' . @$this->metas['tc-handler']['content'] . '" id="useo-tc-handler" class="qa-form-tall-text" type="text" value="'. @$this->social_metas['tc-handler'] .'" name="useo-meta-editor-title">
						</td>
					</tr>
				</tbody>
			</table>
			<h3>Google+ Schemas</h3>
			<table class="qa-form-tall-table">
				<tbody>
					<tr>
						<td class="qa-form-tall-label">
							Thumbnail Image
						</td>
					</tr>
					<tr>
						<td class="qa-form-tall-data">
							<input placeholder="' . @$this->metas['gp-image']['content'] . '" id="useo-gp-image" class="qa-form-tall-text" type="text" value="'. @$this->social_metas['gp-image'] .'" name="useo-meta-editor-title">
						</td>
					</tr>
				</tbody>
				<tbody>
					<tr>
						<td  id="useo_buttons_container_social" class="qa-form-tall-buttons" colspan="1">
							<input id="useo_save_social" class="qa-form-tall-button qa-form-tall-button-save" type="submit" title="" value="Save Options">
						</td>
					</tr>
				</tbody>

			</table>
			');
			$this->output('</form>');
			$this->output('<hr /></div>');
		}
		
	}
	function post_tag_item($taghtml, $class)
	{
		require_once QA_INCLUDE_DIR.'qa-util-string.php';
		global
			$useo_tag_desc_list, // Already filled in qa-tag-desc-overrides.php  -  All tags used in this page are listed in this array
			$plugin_tag_map;       // here it will be filled with tag's meta descriptions
		if (count(@$useo_tag_desc_list)) {
			$result=qa_db_query_sub(
				'SELECT tag, title, content FROM ^tagmetas WHERE tag IN ($)',
				array_keys($useo_tag_desc_list)
			);
			
			$useo_tag_desc_map=qa_db_read_all_assoc($result);

			$useo_tag_desc_list=null;
			
			$plugin_tag_map=array();
			foreach ($useo_tag_desc_map as &$value) {
				if ($value['title']=='title') $plugin_tag_map[$value['tag']]['title'] = $value['content'];
				if ($value['title']=='description') $plugin_tag_map[$value['tag']]['description'] = $value['content'];
				if ($value['title']=='icon') $plugin_tag_map[$value['tag']]['icon'] = $value['content'];
			}
		}

		$html = new DOMDocument();
		$html->loadHTML(mb_convert_encoding($taghtml, 'HTML-ENTITIES', 'UTF-8'));

		foreach($html->getElementsByTagName('a') as $a)
        {
			if (!empty($plugin_tag_map[$a->nodeValue]['title']))
				$a->setAttribute('title', $plugin_tag_map[$a->nodeValue]['title']);
			if (!empty($plugin_tag_map[$a->nodeValue]['icon'])){
				$element = $html->createElement('img');
				$element->setAttribute('src',$plugin_tag_map[$a->nodeValue]['icon']);
				$element->setAttribute('class','qa-tag-img');
				$element->setAttribute('width',qa_opt('useo_tag_desc_icon_width'));
				$element->setAttribute('height',qa_opt('useo_tag_desc_icon_height'));
				 $a->insertBefore($element, $a->firstChild);
			}
		}
		
		$taghtml= $html->saveHTML(); 
		qa_html_theme_base::post_tag_item($taghtml, $class);
	}
	// category link titles
	function post_meta_where($post, $class)
	{
		//$post['where']['data']
		if(isset($post['where']['data']) && isset($post['where']['title'])){
			$max_len = (int)qa_opt('useo_cat_desc_max_len');
			if($max_len <= 0)
				$max_len = 250;
			$post['where']['data'] = str_replace('<a', '<a title="' . substr($post['where']['title'],0,$max_len) . '" ', $post['where']['data']);
		}
		qa_html_theme_base::post_meta_where($post, $class);
	}
	function ranking($ranking)
	{
		if ($this->template=='tags')
		{
			global $useo_tag_desc_list; // Already filled in qa-tag-desc-overrides.php  -  All tags used in this page are listed in this array
			
			if (count(@$useo_tag_desc_list)) {
				// Get all tag meta in this query
				$result=qa_db_query_sub(
					'SELECT tag, title, content FROM ^tagmetas WHERE tag IN ($)',
					array_keys($useo_tag_desc_list)
				);
				$useo_tag_desc_map=qa_db_read_all_assoc($result);
				$useo_tag_desc_list=null;
				$plugin_tag_map=array();
				foreach ($useo_tag_desc_map as &$value) {
					if ($value['title']=='title') $plugin_tag_map[$value['tag']]['title'] = $value['content'];
					if ($value['title']=='description') $plugin_tag_map[$value['tag']]['description'] = $value['content'];
					if ($value['title']=='icon') $plugin_tag_map[$value['tag']]['icon'] = $value['content'];
				}
				// add title and icon to each tag
				$html = new DOMDocument();
				foreach ($ranking['items'] as &$item) {
					$html->loadHTML(mb_convert_encoding($item['label'], 'HTML-ENTITIES', 'UTF-8'));
					foreach($html->getElementsByTagName('a') as $a)
					{
						if (!empty($plugin_tag_map[$a->nodeValue]['title']))
							$a->setAttribute('title', $plugin_tag_map[$a->nodeValue]['title']);
						if (!empty($plugin_tag_map[$a->nodeValue]['icon'])){
							$element = $html->createElement('img');
							$element->setAttribute('src',$plugin_tag_map[$a->nodeValue]['icon']);
							$element->setAttribute('class','qa-tag-img');
							$element->setAttribute('width',qa_opt('useo_tag_desc_icon_width'));
							$element->setAttribute('height',qa_opt('useo_tag_desc_icon_height'));
							$a->insertBefore($element, $a->firstChild);
						}
					}
					$item['label']= $html->saveHTML(); 				
				}
			}
		}
		qa_html_theme_base::ranking($ranking);
	}
}


<?php
require_once(NAVIGATE_PATH.'/lib/packages/webdictionary/webdictionary.class.php');
require_once(NAVIGATE_PATH.'/lib/external/class.upload/class.upload.php');
require_once(NAVIGATE_PATH.'/web/nvweb_templates.php');

// remember, all files are saved in the private directory using ID as filename: NAVIGATE_PRIVATE

class file
{
	public $id;
	public $website;
	public $type;
	public $parent; // parent folder
	public $name;
	public $size;
	public $mime;
	public $width;
	public $height;
    public $focalpoint; // 50.00#50.00 (image center by top%, left%)
    public $title; // array ("lang" => "text")
    public $description; // image ALT; array ("lang" => "text")
	public $date_added;
	public $uploaded_by;	
	public $access; // 0 => everyone, 1 => registered and logged in, 2 => not registered or not logged in
    public $groups;
	public $permission;
	public $system;
	public $enabled;

	public function load($id)
	{
		global $DB;
		global $website;

		if(!is_numeric($id) && strpos($id, '#')===false)
		{
			// then it may be a http/https path?, so create a virtual file object
			$id = urldecode($id);
			$this->id = $id;
            $this->website = $website->id;
			$this->parent = 0;
			$this->name = basename($id);
			$this->size = @filesize($this->absolute_path());
            $mime = $this->getMime($this->absolute_path());
            $this->mime = $mime[0];
            $this->type = $mime[1];
			$dimensions = $this->image_dimensions($this->absolute_path(), $this->mime);
			$this->width = $dimensions['width'];
			$this->height = $dimensions['height'];
			$this->focalpoint = '50#50';
            $this->title = "";
            $this->description = "";
			$this->date_added = core_time();
			$this->uploaded_by = 'system';
			$this->permission = 0;
			$this->enabled = 1;
			$this->system = 0;
            $this->groups = array();
			$this->access = 0;
		}
		else
		{
            if(strpos($id, '#')!==false)
            {
                // decompose provider#reference value, f.e. youtube#3MteSlpxCpo
                list($provider, $reference) = explode('#', $id);

                switch($provider)
                {
                    case 'youtube':
                        $this->load_from_youtube($reference);
                        break;

                    case 'vimeo':
                        $this->load_from_vimeo($reference);
                        break;

                    default:
                }
            }

            // if we still haven't found the requested file...
            if(empty($this->id))
            {
                // we MUST try to load any valid id without website attached
                if($DB->query('SELECT * FROM nv_files WHERE id = '.intval($id)))
                {
                    $data = $DB->result();
                    $this->load_from_resultset($data); // there will be as many entries as languages enabled
                }
            }
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];
		
		$this->id			= $main->id;
		$this->website		= $main->website;
		$this->type			= $main->type;
		$this->parent		= $main->parent;
		$this->name			= $main->name;
		$this->size			= $main->size;
		$this->mime			= $main->mime;
		$this->width		= $main->width;
		$this->height		= $main->height;

		$this->focalpoint	= $main->focalpoint;
        $this->title        = is_array($main->title)? $main->title : json_decode($main->title, true);
        $this->description  = is_array($main->description)? $main->description : json_decode($main->description, true);

        $this->date_added	= $main->date_added;
		$this->uploaded_by	= $main->uploaded_by;	
	
		$this->access		= $main->access;			
		$this->permission	= $main->permission;
		$this->enabled		= $main->enabled;

        // to get the array of groups first we remove the "g" character
        $groups = '';
        if(!empty($main->groups))
        {
            if(!is_array($main->groups))
            {
                $groups = str_replace('g', '', $main->groups);
                $this->groups = explode(',', $groups);
            }
            else
                $this->groups = $main->groups;
        }

        if(!is_array($this->groups))
            $this->groups = array($groups);

        if($this->type=='video')
        {
            // set extra information
            $vsrc = NVWEB_OBJECT.'?type=file&id='.$this->id.'&disposition=inline';
            $this->extra        = array(
                'reference'  =>  $this->id,
                'link'      =>  $vsrc,
                'thumbnail_url' => NVWEB_OBJECT.'?type=blank',
                'duration' => '',
                'embed_code'  => '<video id="video-file-'.$this->id.'" controls="controls" preload="metadata" poster=""><source src="'.$vsrc.'" type="'.$this->mime.'" /><p>Error loading video</p></video>'
            );
        }
	}
	
	public function load_from_post()
	{
		global $DB;
        global $website;
		
		// ? ==> should be changed?
	
		//$this->parent		= $_REQUEST['parent'];
		$this->name			= $_REQUEST['name'];
		//$this->size			= $_REQUEST['size'];	// ?
        $this->type			= $_REQUEST['type'];
		$this->mime			= $_REQUEST['mime'];

        if(isset($_REQUEST['width']))
            $this->width		= $_REQUEST['width'];
        if(isset($_REQUEST['height']))
		    $this->height		= $_REQUEST['height'];

		$this->date_added	= core_time();
		//$this->uploaded_by	= $_REQUEST['uploaded_by'];		// ?
	
		$this->access		= intval($_REQUEST['access']);

        $this->groups	    = $_REQUEST['groups'];
        if($this->access < 3)
            $this->groups = array();

		$this->permission	= intval($_REQUEST['permission']);
		$this->enabled		= intval($_REQUEST['enabled']);

        $this->title = array();
        $this->description = array();
        foreach($website->languages as $language)
        {
            $lcode = $language['code'];

            if(!isset($_REQUEST['title-'.$lcode]))
                break;

            $this->title[$lcode]	= $_REQUEST['title-'.$lcode];
            $this->description[$lcode]	= $_REQUEST['description-'.$lcode];
        }
	}

    public function load_from_youtube($reference, $cache=true)
    {
        global $website;

        // check cache before trying to download oembed info
        if($cache)  $cache = 30 * 24 * 60; // 30 days
        else        $cache = 0;

        $info = nvweb_template_oembed_cache(
            'youtube',
            'https://www.youtube.com/oembed?url=http://www.youtube.com/watch?v='.$reference.'&format=json',
            $cache
        );

	    if($info == 'Not found')
		    $info = '';

        if(empty($info))
            return false;

        $this->id			= 'youtube#'.$reference;
        $this->type			= 'video';
        $this->name			= $info->title;
        $this->size			= NULL;
        $this->mime			= 'video/youtube';
        $this->width		= $info->width;
        $this->height		= $info->height;
        $this->uploaded_by	= $info->author_name;
        $this->access		= 0;
        $this->permission	= 0;
        $this->enabled		= 1;

        $vtpath = $this->video_thumbnail_retrieve('https://img.youtube.com/vi/'.$reference.'/maxresdefault.jpg', "youtube", $reference);
        if(empty($vtpath)) // for some videos, maxresdefault is not available, so try to retrieve the alternative hqdefault thumbnail
            $vtpath = $this->video_thumbnail_retrieve('https://img.youtube.com/vi/'.$reference.'/hqdefault.jpg', "youtube", $reference);

        $this->extra        = array(
            'reference'  =>  $reference,
            'link'      =>  'https://www.youtube.com/watch?v='.$reference,
            'thumbnail' =>  'https://img.youtube.com/vi/'.$reference.'/default.jpg',
            'thumbnail_big' => 'https://img.youtube.com/vi/'.$reference.'/hqdefault.jpg',
            'thumbnail_url' => str_replace('http://', 'https://', $info->thumbnail_url),
            'thumbnail_cache' => 'private/'.$website->id.'/thumbnails/video-youtube-'.$reference,
            'thumbnail_cache_absolute' => file::file_url('private/'.$website->id.'/thumbnails/video-youtube-'.$reference).'&type=image',
            'duration' => '',
            'embed_code'  => '<iframe src="https://www.youtube.com/embed/'.$reference.'?feature=oembed&rel=0&modestbranding=1" frameborder="0" allowfullscreen></iframe>'
        );
    }

    public function load_from_vimeo($reference, $cache=true)
    {
        global $website;

        if($cache)  $cache = 30 * 24 * 60; // 30 days
        else        $cache = 0;

        $info = nvweb_template_oembed_cache(
            'vimeo',
            'http://vimeo.com/api/oembed.json?url=http://vimeo.com/'.$reference.'&format=json',
            $cache
        );

        if(empty($info))
            return false;

        $this->id			= 'vimeo#'.$reference;
        $this->type			= 'video';
        $this->name			= $info->title;
        $this->size			= NULL;
        $this->mime			= 'video/vimeo';
        $this->width		= $info->width;
        $this->height		= $info->height;

        $this->uploaded_by	= $info->author_name;

        $this->access		= 0;
        $this->permission	= 0;
        $this->enabled		= 1;

        $this->video_thumbnail_retrieve($info->thumbnail_url, "vimeo", $reference);

        $this->extra        = array(
            'reference'  =>  $info->video_id,
            'link'      =>  'https://www.vimeo.com/'.$reference,
            'thumbnail_url' => str_replace('http://', 'https://', $info->thumbnail_url),
            'thumbnail_big' => str_replace('http://', 'https://', $info->thumbnail_url),
            'thumbnail_cache' => 'private/'.$website->id.'/thumbnails/video-vimeo-'.$reference,
            'thumbnail_cache_absolute' => file::file_url('private/'.$website->id.'/thumbnails/video-vimeo-'.$reference).'&type=image',
            'duration' => '',
            'embed_code'  => '<iframe src="https://player.vimeo.com/video/'.$reference.'?" frameborder="0" allowfullscreen></iframe>'
        );
    }

    public function video_thumbnail_retrieve($image_url, $provider, $reference)
    {
        global $website;

        $video_thumbnail_path = NAVIGATE_PRIVATE.'/'.$website->id.'/thumbnails/video-'.$provider.'-'.$reference;
        $video_thumbnail_data = "";

        clearstatcache();

        // check if we have the image already downloaded (recently and correctly)
        if( !file_exists($video_thumbnail_path) ||
            filemtime($video_thumbnail_path) + 7 * 86400 > time() ||
            filesize($video_thumbnail_path) < 256
        )
        {
            // download the image from the source
            $video_thumbnail_data = @file_get_contents($image_url);

            if(empty($video_thumbnail_data))
            {
                // try to retrieve the file via cURL
                $video_thumbnail_data = @core_file_curl($image_url);
                if(empty($video_thumbnail_data))
                    return;
            }

            file_put_contents($video_thumbnail_path, $video_thumbnail_data);
        }

        // return the path to the real file to be able to create a thumbnail
        return $video_thumbnail_path;
    }
	
	public function save()
	{
		if(!empty($this->id))
			return $this->update();
		else
			return $this->insert();			
	}

    /* folder types:
		folder/generic
		folder/images
		folder/audio
		folder/video
		folder/flash
		folder/documents
    */
    public static function create_folder($name, $type="folder/generic", $parent=0, $wid=0)
    {
        global $user;
	    global $website;

	    if(empty($wid))
		    $wid = $website->id;

        $file = new file();
        $file->id = 0;
	    $file->website = $wid;
        $file->mime = $type;
        $file->type = 'folder';
        $file->parent = intval($parent);
        $file->name = $name;
        $file->size = 0;
        $file->width = 0;
        $file->height = 0;
        $file->date_added = core_time();
        $file->uploaded_by = $user->id;
        $file->permission = 0;
        $file->access = 0;
        $file->system = 0;
        $file->enabled = 1;

        $file->save();

        return $file->id;
    }
	
	public function delete()
	{
		global $DB;
		global $website;
		global $user;

		if($user->permission("files.delete")=='false')
			throw new Exception(t(610, "Sorry, you are not allowed to execute this function."));

		if($this->type == 'folder')
		{
			$DB->query('SELECT id 
						  FROM nv_files
						 WHERE parent = '.intval($this->id).'
						   AND website = '.$website->id);
						  
			$all = $DB->result();
						
			for($i=0; $i < count($all); $i++)
			{
				unset($tmp);
				$tmp = new file();
				$tmp->load($all[$i]->id);
				$tmp->delete();	
			}
		}

		// remove the virtual folder/file and its data
		if(!empty($this->id))
		{
			$DB->execute('DELETE 
							FROM nv_files
						   WHERE id = '.intval($this->id).'
						     AND website = '.$website->id
						);

			if($DB->get_affected_rows() == 1 && $this->type != 'folder')
				@unlink(NAVIGATE_PRIVATE.'/'.$website->id.'/files/'.$this->id);
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
		global $website;

        $groups = '';
        if(is_array($this->groups))
        {
            $this->groups = array_unique($this->groups); // remove duplicates
            $this->groups = array_filter($this->groups); // remove empty
            if(!empty($this->groups))
                $groups = 'g'.implode(',g', $this->groups);
        }

        if($groups == 'g')
            $groups = '';

        $ok = $DB->execute('
            INSERT INTO nv_files
            (   id, website, type, parent, name, size, mime,
                width, height, focalpoint, title, description,
                date_added, uploaded_by,
                permission, access, groups, system, enabled)
            VALUES
            ( 0,
              :website, :type, :parent, :fname, :size, :mime,
              :width, :height, :focalpoint,
              :title, :description,
              :date_added, :uploaded_by,
              :permission, :access, :groups, :system, :enabled
            )',
            array(
				":website" => value_or_default($this->website, $website->id),
				":type" => $this->type,
				":parent" => $this->parent,
				":fname" => $this->name,
				":size" => $this->size,
				":mime" => $this->mime,
				":width" => value_or_default(intval($this->width), 0),
				":height" => value_or_default(intval($this->height), 0),
				":focalpoint" => value_or_default($this->focalpoint, ''),
				":title" => json_encode($this->title),
				":description" => json_encode($this->description),
				":date_added" => $this->date_added,
				":uploaded_by" => value_or_default($this->uploaded_by, 0),
				":permission" => intval($this->permission),
				":access" => intval($this->access),
				":groups" => $groups,
				":system" => value_or_default($this->system, 0),
				":enabled" => intval($this->enabled)
            )
        );
			
		if(!$ok) throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();
		
		return true;
	}
	
	public function update()
	{
		global $DB;

        $groups = '';
        if(is_array($this->groups))
        {
            $this->groups = array_unique($this->groups); // remove duplicates
            $this->groups = array_filter($this->groups); // remove empty
            if(!empty($this->groups))
                $groups = 'g'.implode(',g', $this->groups);
        }

        if($groups == 'g')
            $groups = '';

        $ok = $DB->execute('
            UPDATE nv_files
               SET
                type		=	:type,
                parent		=	:parent,
                name		=	:fname,
                size		=	:size,
                mime		=	:mime,
                width		=	:width,
                height		=	:height,
                focalpoint  =	:focalpoint,
                title       =   :title,
                description =   :description,
                date_added	=	:date_added,
                uploaded_by	=	:uploaded_by,
                permission	=	:permission,
                access		=	:access,
                groups      =   :groups,
                system		=	:system,
                enabled		=	:enabled
            WHERE id = :id
              AND website = :website_id
          ',
          array(
                ":id" => $this->id,
                ":website_id" => $this->website,
                ":type" => $this->type,
                ":parent" => $this->parent,
                ":fname" => $this->name,
                ":size" => $this->size,
                ":mime" => $this->mime,
                ":width" => value_or_default($this->width, 0),
                ":height" => value_or_default($this->height, 0),
                ":focalpoint" => value_or_default($this->focalpoint, ''),
                ":title" => json_encode($this->title),
                ":description" => json_encode($this->description),
                ":date_added" => $this->date_added,
                ":uploaded_by" => $this->uploaded_by,
                ":permission" => $this->permission,
                ":access" => value_or_default($this->access, 0),
                ":groups" => $groups,
	            ":system" => value_or_default($this->system, 0),
                ":enabled" => $this->enabled
            )
        );

		if(!$ok) throw new Exception($DB->get_last_error());
		
		return true;
	}		
	
	public static function filesOnPath($parent, $wid=NULL, $orderby="date_added DESC, name ASC")
	{
		global $DB;
		global $website;
		
		if(empty($wid))
			$wid = $website->id;
		
		$files = array();

        // folders are always ordered alphabetically
		$DB->query('  SELECT * FROM nv_files
					   WHERE parent = '.intval($parent).'
					     AND website = '.$wid.'
						 AND type = "folder"
						ORDER BY '.$orderby.'
					');
					
		$files = $DB->result();		
		
		$DB->query('  SELECT * FROM nv_files
					   WHERE parent = '.intval($parent).'
					     AND website = '.$wid.'
						 AND type != "folder"
						ORDER BY '.$orderby.'
					');
					
		$files = array_merge($files, $DB->result());		
		
		return $files;	
	}	
	
	public static function filesBySearch($text, $wid=NULL, $orderby="name ASC")
	{
		global $DB;
		global $website;

		if(empty($wid))
			$wid = $website->id;

		$DB->query('  SELECT * FROM nv_files
					   WHERE name LIKE '.protect('%'.$text.'%').'
					     AND website = '.$wid.'
					ORDER BY '.$orderby);
					
		return $DB->result();		
	}
	
	public static function filesByMedia($media, $offset=0, $limit=-1, $wid=NULL, $text="", $orderby="date_added DESC, name ASC")
	{
		global $DB;
		global $website;
		
		if(empty($wid))
			$wid = $website->id;		

		if($limit < 1)
			$limit = 2147483647;

        if(!empty($text))
            $text = ' AND name LIKE '.protect('%'.$text.'%');
		
		$DB->query('  SELECT SQL_CALC_FOUND_ROWS * FROM nv_files
					   WHERE type = '.protect($media).'
					     AND enabled = 1
						 AND website = '.$wid.'
						 '.$text.'
					ORDER BY '.$orderby.'
					   LIMIT '.$limit.' 
					  OFFSET '.$offset);

        $total = $DB->foundRows();
        $rows = $DB->result();

		return array($rows, $total);
	}
		
	public static function getFullPathTo($parent)
	{
        $path = "";

		if($parent > 0)
		{
			$folder = new file();
			$folder->load($parent);
				
			$path = file::getFullPathTo($folder->parent);	
			$path .= '/'.$folder->name;			
		}
		
		return $path;
	}
	
	public static function cacheHeaders($lastModifiedDate, $etag="")
	{		
		// expiry time 1 week, then recheck (if no change, 304 Not modified will be issued)
	    header("Expires: ".gmdate("D, d M Y H:i:s", core_time() + 60*60*24*7)." GMT");
		header("Pragma: cache");
		
		if(!empty($lastModifiedDate)) 
		{ 
			header("Last-Modified: ".gmdate("D, d M Y H:i:s", $lastModifiedDate)." GMT"); 
				
			if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && 
				strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastModifiedDate) 
			{
				if (php_sapi_name()=='CGI') 
				{
					header("Status: 304 Not Modified");
					return true;					
				} 
				else 
				{
					header("HTTP/1.0 304 Not Modified");
					return true;					
				}
			} 
			else if(!empty($etag) && (@$_SERVER['HTTP_IF_NONE_MATCH'] == $etag))
			{
				header("HTTP/1.0 304 Not Modified");
				return true;
			}
		}
		
		return false;
	}
	
	public static function getMime($filename, $absolute_path='')
	{
        // basic mimetypes
        $mime_types = array(

            // documents
            'txt' => array('text/plain', 'document'),
            'epub' => array('application/epub+zip', 'document'),

            // images
            'png' => array('image/png', 'image'),
            'jpeg' => array('image/jpeg', 'image'),
            'jpg' => array('image/jpeg', 'image'),
            'gif' => array('image/gif', 'image'),
            'tiff' => array('image/tiff', 'image'),
            'svg' => array('image/svg+xml', 'image'),
            'ico' => array('image/x-icon', 'image'),
            'webp' => array('image/webp', 'image'),

            // archives
            'zip' => array('application/zip', 'archive'),
            'rar' => array('application/x-rar-compressed', 'archive'),
            'exe' => array('application/x-msdownload', 'file'),

            // audio/video
            'mp3' => array('audio/mpeg', 'audio'),
            'wav' => array('audio/x-wav', 'audio'),			
            'qt' => array('video/quicktime', 'video'),
            'mov' => array('video/quicktime', 'video'),
            'avi' => array('video/x-msvideo', 'video'),		
            'mp4' => array('video/mp4', 'video'),	
            'webm' => array('video/webm', 'video'),
            'wmv' => array('video/x-ms-wmv', 'video'),
            'swf' => array('application/x-shockwave-flash', 'flash'),
            'flv' => array('video/x-flv', 'video'),				

            // adobe
            'pdf' => array('application/pdf', 'document'),
            'psd' => array('image/vnd.adobe.photoshop', 'image'),

            // ms office
            'doc' => array('application/msword', 'document'),
            'docx' => array('application/msword', 'document'),
            'rtf' => array('application/rtf', 'document'),
            'xls' => array('application/vnd.ms-excel', 'document'),
            'xlsx' => array('application/vnd.ms-excel', 'document'),
            'ppt' => array('application/vnd.ms-powerpoint', 'document'),
            'pptx' => array('application/vnd.ms-powerpoint', 'document')
        );

		$ext = file::getExtension($filename);

        if(function_exists('finfo_open') && !empty($absolute_path))
        {
            clearstatcache();
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $absolute_path);
            finfo_close($finfo);

            if(strpos($mimetype, ';')!==false)
                $mimetype = substr($mimetype, 0, strpos($mimetype, ';'));

            $file_type = 'file';
            foreach($mime_types as $extension => $mime_info)
            {
                if(strpos($mimetype, $mime_info[0])!==false)
                {
                    $file_type = $mime_info[1];
                    break;
                }
            }
            return array($mimetype, $file_type);
        }
        else if(array_key_exists($ext, $mime_types))
        {
            return $mime_types[$ext];
        }
        else
        {
            return array('application/octet-stream', 'file');
        }
	
	}
	
	public function refresh()
	{
	    $filepath = NAVIGATE_PRIVATE . '/' . $this->website . '/files/' . $this->id;
        clearstatcache();

        if($this->type == 'image')
        {
            $dims = $this->image_dimensions($filepath, $this->mime);
            $this->width = $dims['width'];
            $this->height = $dims['height'];
        }
		$this->size = filesize($filepath);
		
		$this->save();
		
		$thumbs = glob(NAVIGATE_PRIVATE.'/'.$this->website.'/thumbnails/*-'.$this->id);
		if(is_array($thumbs))	
		{
			foreach($thumbs as $t)	
				@unlink($t);		
		}
	}

	public function resize_uploaded_image()
	{
		global $website;

		if($this->type != 'image')
			return;

		if(file::is_animated_gif($this->absolute_path()) || $this->mime == 'image/svg+xml')
			return;

		if($website->resize_uploaded_images > 0)
		{
			$this->refresh();

            if( ($this->width > $website->resize_uploaded_images)   ||
                ($this->height > $website->resize_uploaded_images)
            )
            {
                // the image is bigger than the maximum size allowed by the website
                if($this->width > $this->height)
                {
                    // resize by width
                    $thumbnail_path = file::thumbnail($this, $website->resize_uploaded_images, 0, false);
                }
                else
                {
                    // resize by height
                    $thumbnail_path = file::thumbnail($this, 0, $website->resize_uploaded_images, false);
                }

                $size = filesize($thumbnail_path);

                // copy created thumbnail (resized image) over the original file
                @copy($thumbnail_path, $this->absolute_path());

                // remove all previous thumbnails (including the temporary resized image)
                $this->refresh();

                $this->size = $size;
                $this->save();
            }
		}
	}

	
	public static function image_dimensions($path, $mime=null)
	{
	    if($mime=='image/svg+xml')
        {
            $svg = simplexml_load_file($path);

            // SVG with fixed dimensions?
            $width = @$svg['width'];
            $height = @$svg['height'];

            // maybe SVG with proportional dimensions?
            if(empty($width) || empty($height))
            {
                list($x_start, $y_start, $x_end, $y_end) = explode(' ', $svg['viewBox']);
                $width = abs($x_end - $x_start);
                $height = abs($y_end - $y_start);
            }

            // remove unit (px, %, etc.)
            $width = preg_replace("/[^0-9,.]/", "", $width);
            $height = preg_replace("/[^0-9,.]/", "", $height);

            // no dimensions?
            if(empty($width) || empty($height))
            {
                // use default dimensions
                $width = 300;
                $height = 150;
            }

            $dimensions = array(
                'width'  => $width,
                'height' => $height
            );
        }
        else
        {
            $handle = new upload($path);
            $dimensions = array(
                'width'  => $handle->image_src_x,
                'height' => $handle->image_src_y
            );
        }
		return $dimensions;
	}

    public static function is_animated_gif($path)
	{
		$handle = fopen($path, 'rb');
		$line = fread($handle, filesize($path));
		fclose($handle);
		$frames = 0;
		
		if((substr($line, 0, 6) == "GIF89a") || (substr($line, 0, 6) == "GIF87a")) 
		{
			$frames = explode('21f904', bin2hex($line));
			$frames = sizeof($frames) - 1;
		}
		
		return ($frames > 1);
	}

    // $item: file ID or file object
	public static function thumbnail($item, $width=0, $height=0, $border=true, $ftname='', $quality=95, $scale_up_force=false, $opacity=1)
	{
        if(is_numeric($item))
        {
            $f = new file();
            $f->load($item);
            $item = $f;
        }

        if(!get_class($item)=='file')
            return;

        // precondition, the original image file must exist
        if(!file_exists($item->absolute_path()) || filesize($item->absolute_path()) < 1)
            return;

		$original  = $item->absolute_path();
		$thumbnail = '';

		$item_id = $item->id;
        if(!empty($ftname))
            $item_id = $ftname;
		else if(!is_numeric($item_id))
			$item_id = md5($item->id);
			
		if($border===true || $border==='true' || $border===1)
			$border = 1;
		else 
			$border = 0;

        // do we have the thumbnail already created for this image?

        // option A) opaque JPEG FILE
        $thumbnail_path_jpg = NAVIGATE_PRIVATE.'/'.$item->website.'/thumbnails/'.$width.'x'.$height.'-'.$border.'-'.$quality.'-'.$opacity.'-'.$item_id.'.jpg';

        if(file_exists($thumbnail_path_jpg))
        {
            // ok, a file exists, but it's older than the image file? (so the original image file has changed)
            if(filemtime($thumbnail_path_jpg) > filemtime($original))
            {
                // the thumbnail already exists and is up to date
                $thumbnail = $thumbnail_path_jpg;
            }
        }

        // option B) transparent PNG FILE
        $thumbnail_path_png = NAVIGATE_PRIVATE.'/'.$item->website.'/thumbnails/'.$width.'x'.$height.'-'.$border.'-'.$opacity.'-'.$item_id;
		if(file_exists($thumbnail_path_png))
		{
			// ok, a file exists, but it's older than the image file? (original image file has changed)
			if(filemtime($thumbnail_path_png) > filemtime($original))
			{
				// the thumbnail already exists and is up to date	
				$thumbnail = $thumbnail_path_png;
			}
		}

        // do we have to create a new thumbnail
		if(empty($thumbnail) || isset($_GET['force']) || !(file_exists($thumbnail) && filesize($thumbnail) > 0))
		{
		    if($item->mime == 'application/pdf')
            {
                // try to get thumbnail for a PDF document using Image Magick
                if(extension_loaded('imagick'))
                {
                    $im = new Imagick();
                    $im->readImage($original);
                    $im->setCompressionQuality(100);
                    $im->setImageDepth(300);
                    $im->setFormat("png");
                    $im->writeImage($thumbnail_path_png);
                    $im->destroy();
                    $original = $thumbnail_path_png;
                }
                else
                {
                    return false;
                }
            }

			$thumbnail = $thumbnail_path_png;

			$handle = new upload($original);
			$size = array(
                'width' => $handle->image_src_x,
				'height' => $handle->image_src_y
            );

			$handle->image_convert = 'png';
            $handle->file_max_size = '512M'; // maximum image size: 512M (it really depends on available memory)

			// if needed, calculate width or height with aspect ratio
            if(empty($width) && empty($height))
            {
                $width = $size['width'];
                $height = $size['height'];
            }
			else if(empty($width))
			{
			    if(!empty($size['height']))
				    $width = round(($height / $size['height']) * $size['width']);
                else
                    $width = NULL;
				return file::thumbnail($item, $width, $height, $border, $ftname, $quality, $scale_up_force, $opacity);
			}
			else if(empty($height))
			{
			    if(!empty($size['width']))
				    $height = round(($width / $size['width']) * $size['height']);
                else
                    $height = NULL;
				return file::thumbnail($item, $width, $height, $border, $ftname, $quality, $scale_up_force, $opacity);
			}

			$handle->image_x = $width;
			$handle->image_y = $height;

			if(!empty($opacity) && $opacity != 1)
            {
                // set opacity changing CSS decimal notation (0 to 1) to class.upload value (0 to 100)
                $handle->image_opacity = intval($opacity * 100);
            }

			if($size['width'] < $width && $size['height'] < $height)
			{
				// the image size is under the requested width / height? => fill around with transparent color
				$handle->image_default_color = '#FFFFFF';
				$handle->image_resize = true;
				$handle->image_ratio_no_zoom_in = true;
				$borderP = array(
						floor( ($height - $size['height']) / 2 ),
						ceil( ($width - $size['width']) / 2 ),
						ceil( ($height - $size['height']) / 2 ),
						floor( ($width - $size['width']) / 2 )
				);
				$handle->image_border = $borderP;

				if($scale_up_force)
				{
					$handle->image_border = array();
					if($height > $width)
						$handle->image_ratio_y = true;
					else
						$handle->image_ratio_x = true;
				}

				$handle->image_border_color = '#FFFFFF';
				$handle->image_border_opacity = 0;
			}
			else
			{			
				// the image size is bigger than the requested width / height, we must resize it			
				$handle->image_default_color = '#FFFFFF';
				$handle->image_resize = true;
				$handle->image_ratio_fill = true;
			}

			if($border==0)
			{
                $handle->image_border = false;
                $handle->image_ratio_no_zoom_in = false;

                if(!empty($item->focalpoint) && $handle->image_src_x > 0)
                {
                    $focalpoint = explode('#', $item->focalpoint);

                    $crop = array( 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 );

                    // calculate how the file will be scaled, by width or by height
                    if(($handle->image_src_x / $handle->image_x) > ($handle->image_src_y / $handle->image_y))
                    {
                        // Y is ok, now crop extra space on X
                        $ratio = $handle->image_y / $handle->image_src_y;
                        $image_scaled_x = intval($handle->image_src_x*($ratio));
                        $crop['left'] = max(0, round(($image_scaled_x * ($focalpoint[1]/100) - ($handle->image_x / 2)) / $ratio));
                        $crop['right'] = max(0, round(($image_scaled_x * ((100-$focalpoint[1])/100) - ($handle->image_x / 2)) / $ratio));
                    }
                    else
                    {
                        // X is ok, now crop extra space on Y
                        $ratio = $handle->image_x / $handle->image_src_x;
                        $image_scaled_y = intval($handle->image_src_y*($ratio));
                        $crop['top'] = max(0, round(($image_scaled_y * ($focalpoint[0]/100) - ($handle->image_y / 2)) / $ratio));
                        $crop['bottom'] = max(0, round(($image_scaled_y * ((100-$focalpoint[0])/100) - ($handle->image_y / 2)) / $ratio));
                    }

                    $handle->image_precrop = array($crop['top'], $crop['right'], $crop['bottom'], $crop['left']);
                }

                $handle->image_ratio_crop = true;
                $handle->image_ratio_fill = true;
			}

            $handle->png_compression = 9;
            $handle->process(dirname($thumbnail));

            if(!empty($handle->error))
                throw new Exception($handle->error);

            if(!empty($handle->file_dst_pathname))
                rename($handle->file_dst_pathname, $thumbnail);

            clearstatcache(true, $thumbnail);

            if(!file_exists($thumbnail) || filesize($thumbnail) < 1)
                return NULL;

            // try to recompress the png thumbnail file to achieve the minimum file size,
            // only if some extra apps are available
            if(extension_loaded('imagick'))
            {
                $im = new Imagick($thumbnail);
                //$image_alpha_range = $im->getImageChannelRange(Imagick::CHANNEL_ALPHA);
                //$image_alpha_extrema = $im->getImageChannelExtrema(Imagick::CHANNEL_ALPHA);
                //$image_alpha_mean = $im->getImageChannelMean(Imagick::CHANNEL_ALPHA);
                /*
                $image_is_opaque = (    $image_alpha_range['minima']==0 &&
                                        $image_alpha_range['maxima']==0 );
                */

                // is the image fully opaque?
                $image_alpha_mean = $im->getImageChannelMean(Imagick::CHANNEL_ALPHA);
                $image_is_opaque = ( $image_alpha_mean['mean']==0 || $image_alpha_mean['mean']==1)
                                   &&
                                   ( $image_alpha_mean['standardDeviation']==0 );

                // autorotate image based on EXIF data
                $im_original = new Imagick($original);
                $orientation = $im_original->getImageOrientation();
                $im_original->clear();

                switch($orientation)
                {
                    case imagick::ORIENTATION_BOTTOMRIGHT:
                        $im->rotateimage(new ImagickPixel('transparent'), 180); // rotate 180 degrees
                        break;

                    case imagick::ORIENTATION_RIGHTTOP:
                        $im->rotateimage(new ImagickPixel('transparent'), 90); // rotate 90 degrees CW
                        break;

                    case imagick::ORIENTATION_LEFTBOTTOM:
                        $im->rotateimage(new ImagickPixel('transparent'), -90); // rotate 90 degrees CCW
                        break;
                }

                // Now that it's auto-rotated, make sure the EXIF data is correct in case the EXIF gets saved with the image!
                $im->setImageOrientation(imagick::ORIENTATION_TOPLEFT);

                if(!$image_is_opaque || (!empty($opacity) && $opacity < 1))
                {
                    $im->setImageFormat('PNG32'); // Force a full RGBA image format with full semi-transparency.
                    $im->setBackgroundColor(new ImagickPixel('transparent'));
                    $im->setImageCompression(Imagick::COMPRESSION_UNDEFINED);
                    $im->setImageCompressionQuality(0);
                    $im->writeimage($thumbnail);
                }
                else
                {
                    $im->setImageFormat('JPG'); // create an OPAQUE JPG file with the given quality (default 95%)
                    $im->setImageCompressionQuality($quality);
                    $im->writeimage($thumbnail_path_jpg);
                    @unlink($thumbnail);
                    $thumbnail = $thumbnail_path_jpg;
                }
            }
            /*
           if(command_exists('pngquant')) // PNG Optimization: 8 bit with transparency
           {
               @shell_exec('pngquant -s1 --ext .pngquant '.$thumbnail);
               if(file_exists($thumbnail.'.pngquant'))
               {
                   unlink($thumbnail);
                   rename($thumbnail.'.pngquant', $thumbnail);
               }
           }
           else*/
        }
        clearstatcache(true, $thumbnail);
		return $thumbnail;
	}

    public static function thumbnails_remove($id)
    {
        $f = new file();
        $f->load($id);
        $thumbnails = glob(NAVIGATE_PRIVATE.'/'.$f->website.'/thumbnails/*x*-*-'.$id.'*');
        for($t=0; $t < count($thumbnails); $t++)
            @unlink($thumbnails[$t]);
    }
	
	public static function loadTree($id_parent=0)
	{
		global $DB;	
		global $website;
		
		$DB->query('  SELECT * FROM nv_files 
					   WHERE type = "folder"
					     AND parent = '.intval($id_parent).'
						 AND website = '.$website->id.' 
					ORDER BY parent ASC, id DESC');
		
		$result = $DB->result();
		
		return $result;
	}	
	
	public static function hierarchy($id_parent=0)
	{		
		$tree = array();
		
		if($id_parent==-1)
		{
			/*
			$tree[] = array(   'id' => '0',
							   'parent' => -1,
							   'position' => 0,
							   'permission' => 0,
							   'icon' => 0,
							   'metatags' => '',
							   'label' => $website->name,
							   'date_published' => '',
							   'date_unpublish' => '',
							   'dates' => 'x - x',
							   'children' => structure::hierarchy(0)
							);
			*/
			$obj = new structure();
			$obj->id = 0;
			$obj->label = t(18, 'Home');
			$obj->parent = -1;
			$obj->children = file::hierarchy(0);
			
			$tree[] = $obj;
			
		}
		else
		{
			$tree = file::loadTree($id_parent);
			
			for($i=0; $i < count($tree); $i++)
			{
				$children = file::hierarchy($tree[$i]->id);
				
				$tree[$i]->children = $children;
				$tree[$i]->label = $tree[$i]->name;
				if(empty($tree[$i]->label)) 
					$tree[$i]->label = '[ ? ]';
			}	
		}
		
		return $tree;
	}
	
	public static function hierarchyList($hierarchy, $selected)
	{		
		$html = array();
				
		if(!is_array($hierarchy)) $hierarchy = array();
		
		foreach($hierarchy as $node)
		{	
			$li_class = '';
			$post_html = file::hierarchyList($node->children, $selected);
			if(strpos($post_html, 'class="active"')!==false) $li_class = ' class="open" ';
					
			if(empty($html)) $html[] = '<ul>';
			if($node->id == $selected)
				$html[] = '<li '.$li_class.' value="'.$node->id.'"><span class="active">'.$node->label.'</span>';
			else
				$html[] = '<li '.$li_class.' value="'.$node->id.'"><span>'.$node->label.'</span>';

			$html[] = $post_html;
			$html[] = '</li>';
		}
		if(!empty($html)) $html[] = '</ul>';		
		
		return implode("\n", $html);
	}	
	
	public static function getExtension($filename)
	{
		$ext = explode('.',$filename);
		$ext = strtolower(array_pop($ext));
		return $ext;
	}
	
	public function absolute_path()
	{
        global $website;

		if(is_numeric($this->id))
		{
            $path = NAVIGATE_PRIVATE . '/' . $this->website . '/files/' . $this->id;
        }
		else
        {
            if(file_exists(NAVIGATE_PATH.'/themes/'.$website->theme.'/'.$this->id))
            {
                $path = NAVIGATE_PATH . '/themes/' . $website->theme . '/' . $this->id;
            }
            else if(file_exists(NAVIGATE_PATH.'/'.$this->id))
            {
                $path = NAVIGATE_PATH . '/' . $this->id;
            }
            else
            {
                $path = $this->id;
            }
        }

		return $path;
	}

    // if the file is uploaded by a form, you must set "move_uploaded_file" parameter to true
    // $tmp_name allows giving a full path to the file or just the filename, which has to be already in the private "files" folder of the current webiste
    public static function register_upload($tmp_name, $target_name, $parent, $mime=NULL, $move_uploaded_file=false)
    {
        global $website;
        global $user;
	    global $DB;

        $file = NULL;

        if($move_uploaded_file)
        {
            $uploaded_file_temp = uniqid('upload-');
            move_uploaded_file($tmp_name, NAVIGATE_PRIVATE.'/'.$website->id.'/files/'.$uploaded_file_temp);
            $tmp_name = $uploaded_file_temp;
        }

        // is the filename already absolute?
        // or the file is already uploaded into files folder?
        if(file_exists($tmp_name))
            $tmp_file_path = $tmp_name;
        else
            $tmp_file_path = NAVIGATE_PRIVATE.'/'.$website->id.'/files/'.$tmp_name;

        if(file_exists($tmp_file_path))
        {
            if(empty($mime))
                $mime = file::getMime($target_name, $tmp_file_path);

            $target_name = rawurldecode($target_name);

	        // check if the parent folder given is valid in the current website
	        if($parent > 0)
	        {
		        $DB->query('SELECT id FROM nv_files WHERE website = '.$website->id.' AND id = '.$parent);
		        $rs = $DB->result('id');
		        if(empty($rs) || $rs[0] != $parent) // parent folder invalid, put file in the root folder
			        $parent = 0;
	        }

            $file = new file();
            $file->id = 0;
            $file->website = $website->id;
            $file->mime = $mime[0];
            $file->type = $mime[1];
            $file->parent = intval($parent);
            $file->name = $target_name;
            $file->size = filesize($tmp_file_path);

            if($file->type == 'image')
            {
                $dimensions = file::image_dimensions($tmp_file_path, $file->mime);
                $file->width = $dimensions['width'];
                $file->height = $dimensions['height'];
            }

            $file->date_added = core_time();
            $file->uploaded_by = (empty($user->id)? '0' : $user->id);
            $file->permission = 0;
            $file->enabled = 1;

            $file->save();

            rename(
                $tmp_file_path,
                NAVIGATE_PRIVATE.'/'.$website->id.'/files/'.$file->id
            );

            if($file->type == 'image')
                $file->resize_uploaded_image();
        }

        return $file;
    }

    public static function mimetypes()
    {
        $mime_types_map = array(
            '123' => 'application/vnd.lotus-1-2-3',
            '3dml' => 'text/vnd.in3d.3dml',
            '3ds' => 'image/x-3ds',
            '3g2' => 'video/3gpp2',
            '3gp' => 'video/3gpp',
            '7z' => 'application/x-7z-compressed',
            'aab' => 'application/x-authorware-bin',
            'aac' => 'audio/x-aac',
            'aam' => 'application/x-authorware-map',
            'aas' => 'application/x-authorware-seg',
            'abw' => 'application/x-abiword',
            'ac' => 'application/pkix-attr-cert',
            'acc' => 'application/vnd.americandynamics.acc',
            'ace' => 'application/x-ace-compressed',
            'acu' => 'application/vnd.acucobol',
            'acutc' => 'application/vnd.acucorp',
            'adp' => 'audio/adpcm',
            'aep' => 'application/vnd.audiograph',
            'afm' => 'application/x-font-type1',
            'afp' => 'application/vnd.ibm.modcap',
            'ahead' => 'application/vnd.ahead.space',
            'ai' => 'application/postscript',
            'aif' => 'audio/x-aiff',
            'aifc' => 'audio/x-aiff',
            'aiff' => 'audio/x-aiff',
            'air' => 'application/vnd.adobe.air-application-installer-package+zip',
            'ait' => 'application/vnd.dvb.ait',
            'ami' => 'application/vnd.amiga.ami',
            'apk' => 'application/vnd.android.package-archive',
            'appcache' => 'text/cache-manifest',
            'application' => 'application/x-ms-application',
            'apr' => 'application/vnd.lotus-approach',
            'arc' => 'application/x-freearc',
            'asc' => 'application/pgp-signature',
            'asf' => 'video/x-ms-asf',
            'asm' => 'text/x-asm',
            'aso' => 'application/vnd.accpac.simply.aso',
            'asx' => 'video/x-ms-asf',
            'atc' => 'application/vnd.acucorp',
            'atom' => 'application/atom+xml',
            'atomcat' => 'application/atomcat+xml',
            'atomsvc' => 'application/atomsvc+xml',
            'atx' => 'application/vnd.antix.game-component',
            'au' => 'audio/basic',
            'avi' => 'video/x-msvideo',
            'aw' => 'application/applixware',
            'azf' => 'application/vnd.airzip.filesecure.azf',
            'azs' => 'application/vnd.airzip.filesecure.azs',
            'azw' => 'application/vnd.amazon.ebook',
            'bat' => 'application/x-msdownload',
            'bcpio' => 'application/x-bcpio',
            'bdf' => 'application/x-font-bdf',
            'bdm' => 'application/vnd.syncml.dm+wbxml',
            'bed' => 'application/vnd.realvnc.bed',
            'bh2' => 'application/vnd.fujitsu.oasysprs',
            'bin' => 'application/octet-stream',
            'blb' => 'application/x-blorb',
            'blorb' => 'application/x-blorb',
            'bmi' => 'application/vnd.bmi',
            'bmp' => 'image/x-ms-bmp',
            'book' => 'application/vnd.framemaker',
            'box' => 'application/vnd.previewsystems.box',
            'boz' => 'application/x-bzip2',
            'bpk' => 'application/octet-stream',
            'btif' => 'image/prs.btif',
            'buffer' => 'application/octet-stream',
            'bz' => 'application/x-bzip',
            'bz2' => 'application/x-bzip2',
            'c' => 'text/x-c',
            'c11amc' => 'application/vnd.cluetrust.cartomobile-config',
            'c11amz' => 'application/vnd.cluetrust.cartomobile-config-pkg',
            'c4d' => 'application/vnd.clonk.c4group',
            'c4f' => 'application/vnd.clonk.c4group',
            'c4g' => 'application/vnd.clonk.c4group',
            'c4p' => 'application/vnd.clonk.c4group',
            'c4u' => 'application/vnd.clonk.c4group',
            'cab' => 'application/vnd.ms-cab-compressed',
            'caf' => 'audio/x-caf',
            'cap' => 'application/vnd.tcpdump.pcap',
            'car' => 'application/vnd.curl.car',
            'cat' => 'application/vnd.ms-pki.seccat',
            'cb7' => 'application/x-cbr',
            'cba' => 'application/x-cbr',
            'cbr' => 'application/x-cbr',
            'cbt' => 'application/x-cbr',
            'cbz' => 'application/x-cbr',
            'cc' => 'text/x-c',
            'cct' => 'application/x-director',
            'ccxml' => 'application/ccxml+xml',
            'cdbcmsg' => 'application/vnd.contact.cmsg',
            'cdf' => 'application/x-netcdf',
            'cdkey' => 'application/vnd.mediastation.cdkey',
            'cdmia' => 'application/cdmi-capability',
            'cdmic' => 'application/cdmi-container',
            'cdmid' => 'application/cdmi-domain',
            'cdmio' => 'application/cdmi-object',
            'cdmiq' => 'application/cdmi-queue',
            'cdx' => 'chemical/x-cdx',
            'cdxml' => 'application/vnd.chemdraw+xml',
            'cdy' => 'application/vnd.cinderella',
            'cer' => 'application/pkix-cert',
            'cfs' => 'application/x-cfs-compressed',
            'cgm' => 'image/cgm',
            'chat' => 'application/x-chat',
            'chm' => 'application/vnd.ms-htmlhelp',
            'chrt' => 'application/vnd.kde.kchart',
            'cif' => 'chemical/x-cif',
            'cii' => 'application/vnd.anser-web-certificate-issue-initiation',
            'cil' => 'application/vnd.ms-artgalry',
            'cla' => 'application/vnd.claymore',
            'class' => 'application/java-vm',
            'clkk' => 'application/vnd.crick.clicker.keyboard',
            'clkp' => 'application/vnd.crick.clicker.palette',
            'clkt' => 'application/vnd.crick.clicker.template',
            'clkw' => 'application/vnd.crick.clicker.wordbank',
            'clkx' => 'application/vnd.crick.clicker',
            'clp' => 'application/x-msclip',
            'cmc' => 'application/vnd.cosmocaller',
            'cmdf' => 'chemical/x-cmdf',
            'cml' => 'chemical/x-cml',
            'cmp' => 'application/vnd.yellowriver-custom-menu',
            'cmx' => 'image/x-cmx',
            'cod' => 'application/vnd.rim.cod',
            'com' => 'application/x-msdownload',
            'conf' => 'text/plain',
            'cpio' => 'application/x-cpio',
            'cpp' => 'text/x-c',
            'cpt' => 'application/mac-compactpro',
            'crd' => 'application/x-mscardfile',
            'crl' => 'application/pkix-crl',
            'crt' => 'application/x-x509-ca-cert',
            'crx' => 'application/x-chrome-extension',
            'cryptonote' => 'application/vnd.rig.cryptonote',
            'csh' => 'application/x-csh',
            'csml' => 'chemical/x-csml',
            'csp' => 'application/vnd.commonspace',
            'css' => 'text/css',
            'cst' => 'application/x-director',
            'csv' => 'text/csv',
            'cu' => 'application/cu-seeme',
            'curl' => 'text/vnd.curl',
            'cww' => 'application/prs.cww',
            'cxt' => 'application/x-director',
            'cxx' => 'text/x-c',
            'dae' => 'model/vnd.collada+xml',
            'daf' => 'application/vnd.mobius.daf',
            'dart' => 'application/vnd.dart',
            'dataless' => 'application/vnd.fdsn.seed',
            'davmount' => 'application/davmount+xml',
            'dbk' => 'application/docbook+xml',
            'dcr' => 'application/x-director',
            'dcurl' => 'text/vnd.curl.dcurl',
            'dd2' => 'application/vnd.oma.dd2+xml',
            'ddd' => 'application/vnd.fujixerox.ddd',
            'deb' => 'application/x-debian-package',
            'def' => 'text/plain',
            'deploy' => 'application/octet-stream',
            'der' => 'application/x-x509-ca-cert',
            'dfac' => 'application/vnd.dreamfactory',
            'dgc' => 'application/x-dgc-compressed',
            'dic' => 'text/x-c',
            'dir' => 'application/x-director',
            'dis' => 'application/vnd.mobius.dis',
            'dist' => 'application/octet-stream',
            'distz' => 'application/octet-stream',
            'djv' => 'image/vnd.djvu',
            'djvu' => 'image/vnd.djvu',
            'dll' => 'application/x-msdownload',
            'dmg' => 'application/x-apple-diskimage',
            'dmp' => 'application/vnd.tcpdump.pcap',
            'dms' => 'application/octet-stream',
            'dna' => 'application/vnd.dna',
            'doc' => 'application/msword',
            'docm' => 'application/vnd.ms-word.document.macroenabled.12',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'dot' => 'application/msword',
            'dotm' => 'application/vnd.ms-word.template.macroenabled.12',
            'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
            'dp' => 'application/vnd.osgi.dp',
            'dpg' => 'application/vnd.dpgraph',
            'dra' => 'audio/vnd.dra',
            'dsc' => 'text/prs.lines.tag',
            'dssc' => 'application/dssc+der',
            'dtb' => 'application/x-dtbook+xml',
            'dtd' => 'application/xml-dtd',
            'dts' => 'audio/vnd.dts',
            'dtshd' => 'audio/vnd.dts.hd',
            'dump' => 'application/octet-stream',
            'dvb' => 'video/vnd.dvb.file',
            'dvi' => 'application/x-dvi',
            'dwf' => 'model/vnd.dwf',
            'dwg' => 'image/vnd.dwg',
            'dxf' => 'image/vnd.dxf',
            'dxp' => 'application/vnd.spotfire.dxp',
            'dxr' => 'application/x-director',
            'ecelp4800' => 'audio/vnd.nuera.ecelp4800',
            'ecelp7470' => 'audio/vnd.nuera.ecelp7470',
            'ecelp9600' => 'audio/vnd.nuera.ecelp9600',
            'ecma' => 'application/ecmascript',
            'edm' => 'application/vnd.novadigm.edm',
            'edx' => 'application/vnd.novadigm.edx',
            'efif' => 'application/vnd.picsel',
            'ei6' => 'application/vnd.pg.osasli',
            'elc' => 'application/octet-stream',
            'emf' => 'application/x-msmetafile',
            'eml' => 'message/rfc822',
            'emma' => 'application/emma+xml',
            'emz' => 'application/x-msmetafile',
            'eol' => 'audio/vnd.digital-winds',
            'eot' => 'application/vnd.ms-fontobject',
            'eps' => 'application/postscript',
            'epub' => 'application/epub+zip',
            'es3' => 'application/vnd.eszigno3+xml',
            'esa' => 'application/vnd.osgi.subsystem',
            'esf' => 'application/vnd.epson.esf',
            'et3' => 'application/vnd.eszigno3+xml',
            'etx' => 'text/x-setext',
            'eva' => 'application/x-eva',
            'event-stream' => 'text/event-stream',
            'evy' => 'application/x-envoy',
            'exe' => 'application/x-msdownload',
            'exi' => 'application/exi',
            'ext' => 'application/vnd.novadigm.ext',
            'ez' => 'application/andrew-inset',
            'ez2' => 'application/vnd.ezpix-album',
            'ez3' => 'application/vnd.ezpix-package',
            'f' => 'text/x-fortran',
            'f4v' => 'video/x-f4v',
            'f77' => 'text/x-fortran',
            'f90' => 'text/x-fortran',
            'fbs' => 'image/vnd.fastbidsheet',
            'fcdt' => 'application/vnd.adobe.formscentral.fcdt',
            'fcs' => 'application/vnd.isac.fcs',
            'fdf' => 'application/vnd.fdf',
            'fe_launch' => 'application/vnd.denovo.fcselayout-link',
            'fg5' => 'application/vnd.fujitsu.oasysgp',
            'fgd' => 'application/x-director',
            'fh' => 'image/x-freehand',
            'fh4' => 'image/x-freehand',
            'fh5' => 'image/x-freehand',
            'fh7' => 'image/x-freehand',
            'fhc' => 'image/x-freehand',
            'fig' => 'application/x-xfig',
            'flac' => 'audio/flac',
            'fli' => 'video/x-fli',
            'flo' => 'application/vnd.micrografx.flo',
            'flv' => 'video/x-flv',
            'flw' => 'application/vnd.kde.kivio',
            'flx' => 'text/vnd.fmi.flexstor',
            'fly' => 'text/vnd.fly',
            'fm' => 'application/vnd.framemaker',
            'fnc' => 'application/vnd.frogans.fnc',
            'for' => 'text/x-fortran',
            'fpx' => 'image/vnd.fpx',
            'frame' => 'application/vnd.framemaker',
            'fsc' => 'application/vnd.fsc.weblaunch',
            'fst' => 'image/vnd.fst',
            'ftc' => 'application/vnd.fluxtime.clip',
            'fti' => 'application/vnd.anser-web-funds-transfer-initiation',
            'fvt' => 'video/vnd.fvt',
            'fxp' => 'application/vnd.adobe.fxp',
            'fxpl' => 'application/vnd.adobe.fxp',
            'fzs' => 'application/vnd.fuzzysheet',
            'g2w' => 'application/vnd.geoplan',
            'g3' => 'image/g3fax',
            'g3w' => 'application/vnd.geospace',
            'gac' => 'application/vnd.groove-account',
            'gam' => 'application/x-tads',
            'gbr' => 'application/rpki-ghostbusters',
            'gca' => 'application/x-gca-compressed',
            'gdl' => 'model/vnd.gdl',
            'geo' => 'application/vnd.dynageo',
            'gex' => 'application/vnd.geometry-explorer',
            'ggb' => 'application/vnd.geogebra.file',
            'ggt' => 'application/vnd.geogebra.tool',
            'ghf' => 'application/vnd.groove-help',
            'gif' => 'image/gif',
            'gim' => 'application/vnd.groove-identity-message',
            'gml' => 'application/gml+xml',
            'gmx' => 'application/vnd.gmx',
            'gnumeric' => 'application/x-gnumeric',
            'gph' => 'application/vnd.flographit',
            'gpx' => 'application/gpx+xml',
            'gqf' => 'application/vnd.grafeq',
            'gqs' => 'application/vnd.grafeq',
            'gram' => 'application/srgs',
            'gramps' => 'application/x-gramps-xml',
            'gre' => 'application/vnd.geometry-explorer',
            'grv' => 'application/vnd.groove-injector',
            'grxml' => 'application/srgs+xml',
            'gsf' => 'application/x-font-ghostscript',
            'gtar' => 'application/x-gtar',
            'gtm' => 'application/vnd.groove-tool-message',
            'gtw' => 'model/vnd.gtw',
            'gv' => 'text/vnd.graphviz',
            'gxf' => 'application/gxf',
            'gxt' => 'application/vnd.geonext',
            'h' => 'text/x-c',
            'h261' => 'video/h261',
            'h263' => 'video/h263',
            'h264' => 'video/h264',
            'hal' => 'application/vnd.hal+xml',
            'hbci' => 'application/vnd.hbci',
            'hdf' => 'application/x-hdf',
            'hh' => 'text/x-c',
            'hlp' => 'application/winhlp',
            'hpgl' => 'application/vnd.hp-hpgl',
            'hpid' => 'application/vnd.hp-hpid',
            'hps' => 'application/vnd.hp-hps',
            'hqx' => 'application/mac-binhex40',
            'htc' => 'text/x-component',
            'htke' => 'application/vnd.kenameaapp',
            'htm' => 'text/html',
            'html' => 'text/html',
            'hvd' => 'application/vnd.yamaha.hv-dic',
            'hvp' => 'application/vnd.yamaha.hv-voice',
            'hvs' => 'application/vnd.yamaha.hv-script',
            'i2g' => 'application/vnd.intergeo',
            'icc' => 'application/vnd.iccprofile',
            'ice' => 'x-conference/x-cooltalk',
            'icm' => 'application/vnd.iccprofile',
            'ico' => 'image/x-icon',
            'ics' => 'text/calendar',
            'ief' => 'image/ief',
            'ifb' => 'text/calendar',
            'ifm' => 'application/vnd.shana.informed.formdata',
            'iges' => 'model/iges',
            'igl' => 'application/vnd.igloader',
            'igm' => 'application/vnd.insors.igm',
            'igs' => 'model/iges',
            'igx' => 'application/vnd.micrografx.igx',
            'iif' => 'application/vnd.shana.informed.interchange',
            'imp' => 'application/vnd.accpac.simply.imp',
            'ims' => 'application/vnd.ms-ims',
            'in' => 'text/plain',
            'ink' => 'application/inkml+xml',
            'inkml' => 'application/inkml+xml',
            'install' => 'application/x-install-instructions',
            'iota' => 'application/vnd.astraea-software.iota',
            'ipfix' => 'application/ipfix',
            'ipk' => 'application/vnd.shana.informed.package',
            'irm' => 'application/vnd.ibm.rights-management',
            'irp' => 'application/vnd.irepository.package+xml',
            'iso' => 'application/x-iso9660-image',
            'itp' => 'application/vnd.shana.informed.formtemplate',
            'ivp' => 'application/vnd.immervision-ivp',
            'ivu' => 'application/vnd.immervision-ivu',
            'jad' => 'text/vnd.sun.j2me.app-descriptor',
            'jam' => 'application/vnd.jam',
            'jar' => 'application/java-archive',
            'java' => 'text/x-java-source',
            'jisp' => 'application/vnd.jisp',
            'jlt' => 'application/vnd.hp-jlyt',
            'jnlp' => 'application/x-java-jnlp-file',
            'joda' => 'application/vnd.joost.joda-archive',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'jpgm' => 'video/jpm',
            'jpgv' => 'video/jpeg',
            'jpm' => 'video/jpm',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'jsonml' => 'application/jsonml+json',
            'kar' => 'audio/midi',
            'karbon' => 'application/vnd.kde.karbon',
            'kfo' => 'application/vnd.kde.kformula',
            'kia' => 'application/vnd.kidspiration',
            'kml' => 'application/vnd.google-earth.kml+xml',
            'kmz' => 'application/vnd.google-earth.kmz',
            'kne' => 'application/vnd.kinar',
            'knp' => 'application/vnd.kinar',
            'kon' => 'application/vnd.kde.kontour',
            'kpr' => 'application/vnd.kde.kpresenter',
            'kpt' => 'application/vnd.kde.kpresenter',
            'kpxx' => 'application/vnd.ds-keypoint',
            'ksp' => 'application/vnd.kde.kspread',
            'ktr' => 'application/vnd.kahootz',
            'ktx' => 'image/ktx',
            'ktz' => 'application/vnd.kahootz',
            'kwd' => 'application/vnd.kde.kword',
            'kwt' => 'application/vnd.kde.kword',
            'lasxml' => 'application/vnd.las.las+xml',
            'latex' => 'application/x-latex',
            'lbd' => 'application/vnd.llamagraphics.life-balance.desktop',
            'lbe' => 'application/vnd.llamagraphics.life-balance.exchange+xml',
            'les' => 'application/vnd.hhe.lesson-player',
            'lha' => 'application/x-lzh-compressed',
            'link66' => 'application/vnd.route66.link66+xml',
            'list' => 'text/plain',
            'list3820' => 'application/vnd.ibm.modcap',
            'listafp' => 'application/vnd.ibm.modcap',
            'lnk' => 'application/x-ms-shortcut',
            'log' => 'text/plain',
            'lostxml' => 'application/lost+xml',
            'lrf' => 'application/octet-stream',
            'lrm' => 'application/vnd.ms-lrm',
            'ltf' => 'application/vnd.frogans.ltf',
            'lua' => 'text/x-lua',
            'luac' => 'application/x-lua-bytecode',
            'lvp' => 'audio/vnd.lucent.voice',
            'lwp' => 'application/vnd.lotus-wordpro',
            'lzh' => 'application/x-lzh-compressed',
            'm13' => 'application/x-msmediaview',
            'm14' => 'application/x-msmediaview',
            'm1v' => 'video/mpeg',
            'm21' => 'application/mp21',
            'm2a' => 'audio/mpeg',
            'm2v' => 'video/mpeg',
            'm3a' => 'audio/mpeg',
            'm3u' => 'audio/x-mpegurl',
            'm3u8' => 'application/x-mpegURL',
            'm4a' => 'audio/mp4',
            'm4p' => 'application/mp4',
            'm4u' => 'video/vnd.mpegurl',
            'm4v' => 'video/x-m4v',
            'ma' => 'application/mathematica',
            'mads' => 'application/mads+xml',
            'mag' => 'application/vnd.ecowin.chart',
            'maker' => 'application/vnd.framemaker',
            'man' => 'text/troff',
            'manifest' => 'text/cache-manifest',
            'mar' => 'application/octet-stream',
            'markdown' => 'text/x-markdown',
            'mathml' => 'application/mathml+xml',
            'mb' => 'application/mathematica',
            'mbk' => 'application/vnd.mobius.mbk',
            'mbox' => 'application/mbox',
            'mc1' => 'application/vnd.medcalcdata',
            'mcd' => 'application/vnd.mcd',
            'mcurl' => 'text/vnd.curl.mcurl',
            'md' => 'text/x-markdown',
            'mdb' => 'application/x-msaccess',
            'mdi' => 'image/vnd.ms-modi',
            'me' => 'text/troff',
            'mesh' => 'model/mesh',
            'meta4' => 'application/metalink4+xml',
            'metalink' => 'application/metalink+xml',
            'mets' => 'application/mets+xml',
            'mfm' => 'application/vnd.mfmp',
            'mft' => 'application/rpki-manifest',
            'mgp' => 'application/vnd.osgeo.mapguide.package',
            'mgz' => 'application/vnd.proteus.magazine',
            'mid' => 'audio/midi',
            'midi' => 'audio/midi',
            'mie' => 'application/x-mie',
            'mif' => 'application/vnd.mif',
            'mime' => 'message/rfc822',
            'mj2' => 'video/mj2',
            'mjp2' => 'video/mj2',
            'mk3d' => 'video/x-matroska',
            'mka' => 'audio/x-matroska',
            'mkd' => 'text/x-markdown',
            'mks' => 'video/x-matroska',
            'mkv' => 'video/x-matroska',
            'mlp' => 'application/vnd.dolby.mlp',
            'mmd' => 'application/vnd.chipnuts.karaoke-mmd',
            'mmf' => 'application/vnd.smaf',
            'mmr' => 'image/vnd.fujixerox.edmics-mmr',
            'mng' => 'video/x-mng',
            'mny' => 'application/x-msmoney',
            'mobi' => 'application/x-mobipocket-ebook',
            'mods' => 'application/mods+xml',
            'mov' => 'video/quicktime',
            'movie' => 'video/x-sgi-movie',
            'mp2' => 'audio/mpeg',
            'mp21' => 'application/mp21',
            'mp2a' => 'audio/mpeg',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'mp4a' => 'audio/mp4',
            'mp4s' => 'application/mp4',
            'mp4v' => 'video/mp4',
            'mpc' => 'application/vnd.mophun.certificate',
            'mpe' => 'video/mpeg',
            'mpeg' => 'video/mpeg',
            'mpg' => 'video/mpeg',
            'mpg4' => 'video/mp4',
            'mpga' => 'audio/mpeg',
            'mpkg' => 'application/vnd.apple.installer+xml',
            'mpm' => 'application/vnd.blueice.multipass',
            'mpn' => 'application/vnd.mophun.application',
            'mpp' => 'application/vnd.ms-project',
            'mpt' => 'application/vnd.ms-project',
            'mpy' => 'application/vnd.ibm.minipay',
            'mqy' => 'application/vnd.mobius.mqy',
            'mrc' => 'application/marc',
            'mrcx' => 'application/marcxml+xml',
            'ms' => 'text/troff',
            'mscml' => 'application/mediaservercontrol+xml',
            'mseed' => 'application/vnd.fdsn.mseed',
            'mseq' => 'application/vnd.mseq',
            'msf' => 'application/vnd.epson.msf',
            'msh' => 'model/mesh',
            'msi' => 'application/x-msdownload',
            'msl' => 'application/vnd.mobius.msl',
            'msty' => 'application/vnd.muvee.style',
            'mts' => 'model/vnd.mts',
            'mus' => 'application/vnd.musician',
            'musicxml' => 'application/vnd.recordare.musicxml+xml',
            'mvb' => 'application/x-msmediaview',
            'mwf' => 'application/vnd.mfer',
            'mxf' => 'application/mxf',
            'mxl' => 'application/vnd.recordare.musicxml',
            'mxml' => 'application/xv+xml',
            'mxs' => 'application/vnd.triscape.mxs',
            'mxu' => 'video/vnd.mpegurl',
            'n-gage' => 'application/vnd.nokia.n-gage.symbian.install',
            'n3' => 'text/n3',
            'nb' => 'application/mathematica',
            'nbp' => 'application/vnd.wolfram.player',
            'nc' => 'application/x-netcdf',
            'ncx' => 'application/x-dtbncx+xml',
            'nfo' => 'text/x-nfo',
            'ngdat' => 'application/vnd.nokia.n-gage.data',
            'nitf' => 'application/vnd.nitf',
            'nlu' => 'application/vnd.neurolanguage.nlu',
            'nml' => 'application/vnd.enliven',
            'nnd' => 'application/vnd.noblenet-directory',
            'nns' => 'application/vnd.noblenet-sealer',
            'nnw' => 'application/vnd.noblenet-web',
            'npx' => 'image/vnd.net-fpx',
            'nsc' => 'application/x-conference',
            'nsf' => 'application/vnd.lotus-notes',
            'ntf' => 'application/vnd.nitf',
            'nzb' => 'application/x-nzb',
            'oa2' => 'application/vnd.fujitsu.oasys2',
            'oa3' => 'application/vnd.fujitsu.oasys3',
            'oas' => 'application/vnd.fujitsu.oasys',
            'obd' => 'application/x-msbinder',
            'obj' => 'application/x-tgif',
            'oda' => 'application/oda',
            'odb' => 'application/vnd.oasis.opendocument.database',
            'odc' => 'application/vnd.oasis.opendocument.chart',
            'odf' => 'application/vnd.oasis.opendocument.formula',
            'odft' => 'application/vnd.oasis.opendocument.formula-template',
            'odg' => 'application/vnd.oasis.opendocument.graphics',
            'odi' => 'application/vnd.oasis.opendocument.image',
            'odm' => 'application/vnd.oasis.opendocument.text-master',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'oga' => 'audio/ogg',
            'ogg' => 'audio/ogg',
            'ogv' => 'video/ogg',
            'ogx' => 'application/ogg',
            'omdoc' => 'application/omdoc+xml',
            'onepkg' => 'application/onenote',
            'onetmp' => 'application/onenote',
            'onetoc' => 'application/onenote',
            'onetoc2' => 'application/onenote',
            'opf' => 'application/oebps-package+xml',
            'opml' => 'text/x-opml',
            'oprc' => 'application/vnd.palm',
            'org' => 'application/vnd.lotus-organizer',
            'osf' => 'application/vnd.yamaha.openscoreformat',
            'osfpvg' => 'application/vnd.yamaha.openscoreformat.osfpvg+xml',
            'otc' => 'application/vnd.oasis.opendocument.chart-template',
            'otf' => 'font/opentype',
            'otg' => 'application/vnd.oasis.opendocument.graphics-template',
            'oth' => 'application/vnd.oasis.opendocument.text-web',
            'oti' => 'application/vnd.oasis.opendocument.image-template',
            'otp' => 'application/vnd.oasis.opendocument.presentation-template',
            'ots' => 'application/vnd.oasis.opendocument.spreadsheet-template',
            'ott' => 'application/vnd.oasis.opendocument.text-template',
            'oxps' => 'application/oxps',
            'oxt' => 'application/vnd.openofficeorg.extension',
            'p' => 'text/x-pascal',
            'p10' => 'application/pkcs10',
            'p12' => 'application/x-pkcs12',
            'p7b' => 'application/x-pkcs7-certificates',
            'p7c' => 'application/pkcs7-mime',
            'p7m' => 'application/pkcs7-mime',
            'p7r' => 'application/x-pkcs7-certreqresp',
            'p7s' => 'application/pkcs7-signature',
            'p8' => 'application/pkcs8',
            'pas' => 'text/x-pascal',
            'paw' => 'application/vnd.pawaafile',
            'pbd' => 'application/vnd.powerbuilder6',
            'pbm' => 'image/x-portable-bitmap',
            'pcap' => 'application/vnd.tcpdump.pcap',
            'pcf' => 'application/x-font-pcf',
            'pcl' => 'application/vnd.hp-pcl',
            'pclxl' => 'application/vnd.hp-pclxl',
            'pct' => 'image/x-pict',
            'pcurl' => 'application/vnd.curl.pcurl',
            'pcx' => 'image/x-pcx',
            'pdb' => 'application/vnd.palm',
            'pdf' => 'application/pdf',
            'pfa' => 'application/x-font-type1',
            'pfb' => 'application/x-font-type1',
            'pfm' => 'application/x-font-type1',
            'pfr' => 'application/font-tdpfr',
            'pfx' => 'application/x-pkcs12',
            'pgm' => 'image/x-portable-graymap',
            'pgn' => 'application/x-chess-pgn',
            'pgp' => 'application/pgp-encrypted',
            'pic' => 'image/x-pict',
            'pkg' => 'application/octet-stream',
            'pki' => 'application/pkixcmp',
            'pkipath' => 'application/pkix-pkipath',
            'plb' => 'application/vnd.3gpp.pic-bw-large',
            'plc' => 'application/vnd.mobius.plc',
            'plf' => 'application/vnd.pocketlearn',
            'pls' => 'application/pls+xml',
            'pml' => 'application/vnd.ctc-posml',
            'png' => 'image/png',
            'pnm' => 'image/x-portable-anymap',
            'portpkg' => 'application/vnd.macports.portpkg',
            'pot' => 'application/vnd.ms-powerpoint',
            'potm' => 'application/vnd.ms-powerpoint.template.macroenabled.12',
            'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
            'ppam' => 'application/vnd.ms-powerpoint.addin.macroenabled.12',
            'ppd' => 'application/vnd.cups-ppd',
            'ppm' => 'image/x-portable-pixmap',
            'pps' => 'application/vnd.ms-powerpoint',
            'ppsm' => 'application/vnd.ms-powerpoint.slideshow.macroenabled.12',
            'ppsx' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptm' => 'application/vnd.ms-powerpoint.presentation.macroenabled.12',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'pqa' => 'application/vnd.palm',
            'prc' => 'application/x-mobipocket-ebook',
            'pre' => 'application/vnd.lotus-freelance',
            'prf' => 'application/pics-rules',
            'ps' => 'application/postscript',
            'psb' => 'application/vnd.3gpp.pic-bw-small',
            'psd' => 'image/vnd.adobe.photoshop',
            'psf' => 'application/x-font-linux-psf',
            'pskcxml' => 'application/pskc+xml',
            'ptid' => 'application/vnd.pvi.ptid1',
            'pub' => 'application/x-mspublisher',
            'pvb' => 'application/vnd.3gpp.pic-bw-var',
            'pwn' => 'application/vnd.3m.post-it-notes',
            'pya' => 'audio/vnd.ms-playready.media.pya',
            'pyv' => 'video/vnd.ms-playready.media.pyv',
            'qam' => 'application/vnd.epson.quickanime',
            'qbo' => 'application/vnd.intu.qbo',
            'qfx' => 'application/vnd.intu.qfx',
            'qps' => 'application/vnd.publishare-delta-tree',
            'qt' => 'video/quicktime',
            'qwd' => 'application/vnd.quark.quarkxpress',
            'qwt' => 'application/vnd.quark.quarkxpress',
            'qxb' => 'application/vnd.quark.quarkxpress',
            'qxd' => 'application/vnd.quark.quarkxpress',
            'qxl' => 'application/vnd.quark.quarkxpress',
            'qxt' => 'application/vnd.quark.quarkxpress',
            'ra' => 'audio/x-pn-realaudio',
            'ram' => 'audio/x-pn-realaudio',
            'rar' => 'application/x-rar-compressed',
            'ras' => 'image/x-cmu-raster',
            'rcprofile' => 'application/vnd.ipunplugged.rcprofile',
            'rdf' => 'application/rdf+xml',
            'rdz' => 'application/vnd.data-vision.rdz',
            'rep' => 'application/vnd.businessobjects',
            'res' => 'application/x-dtbresource+xml',
            'rgb' => 'image/x-rgb',
            'rif' => 'application/reginfo+xml',
            'rip' => 'audio/vnd.rip',
            'ris' => 'application/x-research-info-systems',
            'rl' => 'application/resource-lists+xml',
            'rlc' => 'image/vnd.fujixerox.edmics-rlc',
            'rld' => 'application/resource-lists-diff+xml',
            'rm' => 'application/vnd.rn-realmedia',
            'rmi' => 'audio/midi',
            'rmp' => 'audio/x-pn-realaudio-plugin',
            'rms' => 'application/vnd.jcp.javame.midlet-rms',
            'rmvb' => 'application/vnd.rn-realmedia-vbr',
            'rnc' => 'application/relax-ng-compact-syntax',
            'roa' => 'application/rpki-roa',
            'roff' => 'text/troff',
            'rp9' => 'application/vnd.cloanto.rp9',
            'rpss' => 'application/vnd.nokia.radio-presets',
            'rpst' => 'application/vnd.nokia.radio-preset',
            'rq' => 'application/sparql-query',
            'rs' => 'application/rls-services+xml',
            'rsd' => 'application/rsd+xml',
            'rss' => 'application/rss+xml',
            'rtf' => 'text/rtf',
            'rtx' => 'text/richtext',
            's' => 'text/x-asm',
            's3m' => 'audio/s3m',
            'saf' => 'application/vnd.yamaha.smaf-audio',
            'sbml' => 'application/sbml+xml',
            'sc' => 'application/vnd.ibm.secure-container',
            'scd' => 'application/x-msschedule',
            'scm' => 'application/vnd.lotus-screencam',
            'scq' => 'application/scvp-cv-request',
            'scs' => 'application/scvp-cv-response',
            'scurl' => 'text/vnd.curl.scurl',
            'sda' => 'application/vnd.stardivision.draw',
            'sdc' => 'application/vnd.stardivision.calc',
            'sdd' => 'application/vnd.stardivision.impress',
            'sdkd' => 'application/vnd.solent.sdkm+xml',
            'sdkm' => 'application/vnd.solent.sdkm+xml',
            'sdp' => 'application/sdp',
            'sdw' => 'application/vnd.stardivision.writer',
            'see' => 'application/vnd.seemail',
            'seed' => 'application/vnd.fdsn.seed',
            'sema' => 'application/vnd.sema',
            'semd' => 'application/vnd.semd',
            'semf' => 'application/vnd.semf',
            'ser' => 'application/java-serialized-object',
            'setpay' => 'application/set-payment-initiation',
            'setreg' => 'application/set-registration-initiation',
            'sfd-hdstx' => 'application/vnd.hydrostatix.sof-data',
            'sfs' => 'application/vnd.spotfire.sfs',
            'sfv' => 'text/x-sfv',
            'sgi' => 'image/sgi',
            'sgl' => 'application/vnd.stardivision.writer-global',
            'sgm' => 'text/sgml',
            'sgml' => 'text/sgml',
            'sh' => 'application/x-sh',
            'shar' => 'application/x-shar',
            'shf' => 'application/shf+xml',
            'sid' => 'image/x-mrsid-image',
            'sig' => 'application/pgp-signature',
            'sil' => 'audio/silk',
            'silo' => 'model/mesh',
            'sis' => 'application/vnd.symbian.install',
            'sisx' => 'application/vnd.symbian.install',
            'sit' => 'application/x-stuffit',
            'sitx' => 'application/x-stuffitx',
            'skd' => 'application/vnd.koan',
            'skm' => 'application/vnd.koan',
            'skp' => 'application/vnd.koan',
            'skt' => 'application/vnd.koan',
            'sldm' => 'application/vnd.ms-powerpoint.slide.macroenabled.12',
            'sldx' => 'application/vnd.openxmlformats-officedocument.presentationml.slide',
            'slt' => 'application/vnd.epson.salt',
            'sm' => 'application/vnd.stepmania.stepchart',
            'smf' => 'application/vnd.stardivision.math',
            'smi' => 'application/smil+xml',
            'smil' => 'application/smil+xml',
            'smv' => 'video/x-smv',
            'smzip' => 'application/vnd.stepmania.package',
            'snd' => 'audio/basic',
            'snf' => 'application/x-font-snf',
            'so' => 'application/octet-stream',
            'spc' => 'application/x-pkcs7-certificates',
            'spf' => 'application/vnd.yamaha.smaf-phrase',
            'spl' => 'application/x-futuresplash',
            'spot' => 'text/vnd.in3d.spot',
            'spp' => 'application/scvp-vp-response',
            'spq' => 'application/scvp-vp-request',
            'spx' => 'audio/ogg',
            'sql' => 'application/x-sql',
            'src' => 'application/x-wais-source',
            'srt' => 'application/x-subrip',
            'sru' => 'application/sru+xml',
            'srx' => 'application/sparql-results+xml',
            'ssdl' => 'application/ssdl+xml',
            'sse' => 'application/vnd.kodak-descriptor',
            'ssf' => 'application/vnd.epson.ssf',
            'ssml' => 'application/ssml+xml',
            'st' => 'application/vnd.sailingtracker.track',
            'stc' => 'application/vnd.sun.xml.calc.template',
            'std' => 'application/vnd.sun.xml.draw.template',
            'stf' => 'application/vnd.wt.stf',
            'sti' => 'application/vnd.sun.xml.impress.template',
            'stk' => 'application/hyperstudio',
            'stl' => 'application/vnd.ms-pki.stl',
            'str' => 'application/vnd.pg.format',
            'stw' => 'application/vnd.sun.xml.writer.template',
            'sub' => 'text/vnd.dvb.subtitle',
            'sus' => 'application/vnd.sus-calendar',
            'susp' => 'application/vnd.sus-calendar',
            'sv4cpio' => 'application/x-sv4cpio',
            'sv4crc' => 'application/x-sv4crc',
            'svc' => 'application/vnd.dvb.service',
            'svd' => 'application/vnd.svd',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            'swa' => 'application/x-director',
            'swf' => 'application/x-shockwave-flash',
            'swi' => 'application/vnd.aristanetworks.swi',
            'sxc' => 'application/vnd.sun.xml.calc',
            'sxd' => 'application/vnd.sun.xml.draw',
            'sxg' => 'application/vnd.sun.xml.writer.global',
            'sxi' => 'application/vnd.sun.xml.impress',
            'sxm' => 'application/vnd.sun.xml.math',
            'sxw' => 'application/vnd.sun.xml.writer',
            't' => 'text/troff',
            't3' => 'application/x-t3vm-image',
            'taglet' => 'application/vnd.mynfc',
            'tao' => 'application/vnd.tao.intent-module-archive',
            'tar' => 'application/x-tar',
            'tcap' => 'application/vnd.3gpp2.tcap',
            'tcl' => 'application/x-tcl',
            'teacher' => 'application/vnd.smart.teacher',
            'tei' => 'application/tei+xml',
            'teicorpus' => 'application/tei+xml',
            'tex' => 'application/x-tex',
            'texi' => 'application/x-texinfo',
            'texinfo' => 'application/x-texinfo',
            'text' => 'text/plain',
            'tfi' => 'application/thraud+xml',
            'tfm' => 'application/x-tex-tfm',
            'tga' => 'image/x-tga',
            'thmx' => 'application/vnd.ms-officetheme',
            'tif' => 'image/tiff',
            'tiff' => 'image/tiff',
            'tmo' => 'application/vnd.tmobile-livetv',
            'torrent' => 'application/x-bittorrent',
            'tpl' => 'application/vnd.groove-tool-template',
            'tpt' => 'application/vnd.trid.tpt',
            'tr' => 'text/troff',
            'tra' => 'application/vnd.trueapp',
            'trm' => 'application/x-msterminal',
            'ts' => 'video/MP2T',
            'tsd' => 'application/timestamped-data',
            'tsv' => 'text/tab-separated-values',
            'ttc' => 'application/x-font-ttf',
            'ttf' => 'application/x-font-ttf',
            'ttl' => 'text/turtle',
            'twd' => 'application/vnd.simtech-mindmapper',
            'twds' => 'application/vnd.simtech-mindmapper',
            'txd' => 'application/vnd.genomatix.tuxedo',
            'txf' => 'application/vnd.mobius.txf',
            'txt' => 'text/plain',
            'u32' => 'application/x-authorware-bin',
            'udeb' => 'application/x-debian-package',
            'ufd' => 'application/vnd.ufdl',
            'ufdl' => 'application/vnd.ufdl',
            'ulx' => 'application/x-glulx',
            'umj' => 'application/vnd.umajin',
            'unityweb' => 'application/vnd.unity',
            'uoml' => 'application/vnd.uoml+xml',
            'uri' => 'text/uri-list',
            'uris' => 'text/uri-list',
            'urls' => 'text/uri-list',
            'ustar' => 'application/x-ustar',
            'utz' => 'application/vnd.uiq.theme',
            'uu' => 'text/x-uuencode',
            'uva' => 'audio/vnd.dece.audio',
            'uvd' => 'application/vnd.dece.data',
            'uvf' => 'application/vnd.dece.data',
            'uvg' => 'image/vnd.dece.graphic',
            'uvh' => 'video/vnd.dece.hd',
            'uvi' => 'image/vnd.dece.graphic',
            'uvm' => 'video/vnd.dece.mobile',
            'uvp' => 'video/vnd.dece.pd',
            'uvs' => 'video/vnd.dece.sd',
            'uvt' => 'application/vnd.dece.ttml+xml',
            'uvu' => 'video/vnd.uvvu.mp4',
            'uvv' => 'video/vnd.dece.video',
            'uvva' => 'audio/vnd.dece.audio',
            'uvvd' => 'application/vnd.dece.data',
            'uvvf' => 'application/vnd.dece.data',
            'uvvg' => 'image/vnd.dece.graphic',
            'uvvh' => 'video/vnd.dece.hd',
            'uvvi' => 'image/vnd.dece.graphic',
            'uvvm' => 'video/vnd.dece.mobile',
            'uvvp' => 'video/vnd.dece.pd',
            'uvvs' => 'video/vnd.dece.sd',
            'uvvt' => 'application/vnd.dece.ttml+xml',
            'uvvu' => 'video/vnd.uvvu.mp4',
            'uvvv' => 'video/vnd.dece.video',
            'uvvx' => 'application/vnd.dece.unspecified',
            'uvvz' => 'application/vnd.dece.zip',
            'uvx' => 'application/vnd.dece.unspecified',
            'uvz' => 'application/vnd.dece.zip',
            'vcard' => 'text/vcard',
            'vcd' => 'application/x-cdlink',
            'vcf' => 'text/x-vcard',
            'vcg' => 'application/vnd.groove-vcard',
            'vcs' => 'text/x-vcalendar',
            'vcx' => 'application/vnd.vcx',
            'vis' => 'application/vnd.visionary',
            'viv' => 'video/vnd.vivo',
            'vob' => 'video/x-ms-vob',
            'vor' => 'application/vnd.stardivision.writer',
            'vox' => 'application/x-authorware-bin',
            'vrml' => 'model/vrml',
            'vsd' => 'application/vnd.visio',
            'vsf' => 'application/vnd.vsf',
            'vss' => 'application/vnd.visio',
            'vst' => 'application/vnd.visio',
            'vsw' => 'application/vnd.visio',
            'vtt' => 'text/vtt',
            'vtu' => 'model/vnd.vtu',
            'vxml' => 'application/voicexml+xml',
            'w3d' => 'application/x-director',
            'wad' => 'application/x-doom',
            'wav' => 'audio/x-wav',
            'wax' => 'audio/x-ms-wax',
            'wbmp' => 'image/vnd.wap.wbmp',
            'wbs' => 'application/vnd.criticaltools.wbs+xml',
            'wbxml' => 'application/vnd.wap.wbxml',
            'wcm' => 'application/vnd.ms-works',
            'wdb' => 'application/vnd.ms-works',
            'wdp' => 'image/vnd.ms-photo',
            'weba' => 'audio/webm',
            'webapp' => 'application/x-web-app-manifest+json',
            'webm' => 'video/webm',
            'webp' => 'image/webp',
            'wg' => 'application/vnd.pmi.widget',
            'wgt' => 'application/widget',
            'wks' => 'application/vnd.ms-works',
            'wm' => 'video/x-ms-wm',
            'wma' => 'audio/x-ms-wma',
            'wmd' => 'application/x-ms-wmd',
            'wmf' => 'application/x-msmetafile',
            'wml' => 'text/vnd.wap.wml',
            'wmlc' => 'application/vnd.wap.wmlc',
            'wmls' => 'text/vnd.wap.wmlscript',
            'wmlsc' => 'application/vnd.wap.wmlscriptc',
            'wmv' => 'video/x-ms-wmv',
            'wmx' => 'video/x-ms-wmx',
            'wmz' => 'application/x-msmetafile',
            'woff' => 'application/x-font-woff',
            'wpd' => 'application/vnd.wordperfect',
            'wpl' => 'application/vnd.ms-wpl',
            'wps' => 'application/vnd.ms-works',
            'wqd' => 'application/vnd.wqd',
            'wri' => 'application/x-mswrite',
            'wrl' => 'model/vrml',
            'wsdl' => 'application/wsdl+xml',
            'wspolicy' => 'application/wspolicy+xml',
            'wtb' => 'application/vnd.webturbo',
            'wvx' => 'video/x-ms-wvx',
            'x32' => 'application/x-authorware-bin',
            'x3d' => 'model/x3d+xml',
            'x3db' => 'model/x3d+binary',
            'x3dbz' => 'model/x3d+binary',
            'x3dv' => 'model/x3d+vrml',
            'x3dvz' => 'model/x3d+vrml',
            'x3dz' => 'model/x3d+xml',
            'xaml' => 'application/xaml+xml',
            'xap' => 'application/x-silverlight-app',
            'xar' => 'application/vnd.xara',
            'xbap' => 'application/x-ms-xbap',
            'xbd' => 'application/vnd.fujixerox.docuworks.binder',
            'xbm' => 'image/x-xbitmap',
            'xdf' => 'application/xcap-diff+xml',
            'xdm' => 'application/vnd.syncml.dm+xml',
            'xdp' => 'application/vnd.adobe.xdp+xml',
            'xdssc' => 'application/dssc+xml',
            'xdw' => 'application/vnd.fujixerox.docuworks',
            'xenc' => 'application/xenc+xml',
            'xer' => 'application/patch-ops-error+xml',
            'xfdf' => 'application/vnd.adobe.xfdf',
            'xfdl' => 'application/vnd.xfdl',
            'xht' => 'application/xhtml+xml',
            'xhtml' => 'application/xhtml+xml',
            'xhvml' => 'application/xv+xml',
            'xif' => 'image/vnd.xiff',
            'xla' => 'application/vnd.ms-excel',
            'xlam' => 'application/vnd.ms-excel.addin.macroenabled.12',
            'xlc' => 'application/vnd.ms-excel',
            'xlf' => 'application/x-xliff+xml',
            'xlm' => 'application/vnd.ms-excel',
            'xls' => 'application/vnd.ms-excel',
            'xlsb' => 'application/vnd.ms-excel.sheet.binary.macroenabled.12',
            'xlsm' => 'application/vnd.ms-excel.sheet.macroenabled.12',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xlt' => 'application/vnd.ms-excel',
            'xltm' => 'application/vnd.ms-excel.template.macroenabled.12',
            'xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
            'xlw' => 'application/vnd.ms-excel',
            'xm' => 'audio/xm',
            'xml' => 'application/xml',
            'xo' => 'application/vnd.olpc-sugar',
            'xop' => 'application/xop+xml',
            'xpi' => 'application/x-xpinstall',
            'xpl' => 'application/xproc+xml',
            'xpm' => 'image/x-xpixmap',
            'xpr' => 'application/vnd.is-xpr',
            'xps' => 'application/vnd.ms-xpsdocument',
            'xpw' => 'application/vnd.intercon.formnet',
            'xpx' => 'application/vnd.intercon.formnet',
            'xsl' => 'application/xml',
            'xslt' => 'application/xslt+xml',
            'xsm' => 'application/vnd.syncml+xml',
            'xspf' => 'application/xspf+xml',
            'xul' => 'application/vnd.mozilla.xul+xml',
            'xvm' => 'application/xv+xml',
            'xvml' => 'application/xv+xml',
            'xwd' => 'image/x-xwindowdump',
            'xyz' => 'chemical/x-xyz',
            'xz' => 'application/x-xz',
            'yang' => 'application/yang',
            'yin' => 'application/yin+xml',
            'z1' => 'application/x-zmachine',
            'z2' => 'application/x-zmachine',
            'z3' => 'application/x-zmachine',
            'z4' => 'application/x-zmachine',
            'z5' => 'application/x-zmachine',
            'z6' => 'application/x-zmachine',
            'z7' => 'application/x-zmachine',
            'z8' => 'application/x-zmachine',
            'zaz' => 'application/vnd.zzazz.deck+xml',
            'zip' => 'application/zip',
            'zir' => 'application/vnd.zul',
            'zirz' => 'application/vnd.zul',
            'zmm' => 'application/vnd.handheld-entertainment+xml'
        );

        return $mime_types_map;
    }

    // examples:
    //  file::file_url(45, 'attachment')
    //  file::file_url('theme_images_folder/myimage001.png', 'inline')
    public static function file_url($id, $disposition="")
    {
        if(!empty($disposition))
            $disposition = '&amp;disposition=' . $disposition;

	    if(defined("NVWEB_OBJECT"))
            $url = NVWEB_OBJECT.'?id='.$id.$disposition;
	    else
		    $url = NAVIGATE_DOWNLOAD.'?id='.$id.$disposition;


        return $url;
    }

    public static function embed($provider, $reference, $extra="")
    {
        $out = '';

        if(($provider=='file' || empty($provider)) && is_numeric($reference))
        {
            $file = new file();
            $file->load($reference);
            $reference = $file;
        }

        if(is_object($reference))   // "file" object
        {
            $vsrc = NVWEB_OBJECT.'?type=file&id='.$reference->id.'&disposition=inline';

            $out = '
                <video id="video-file-'.$reference->id.'" '.$extra.' controls="controls" preload="metadata" poster="">
                    <source src="'.$vsrc.'" type="'.$reference->mime.'" />
                    <p>Error loading video</p>
                </video>
            ';
        }
        else if($provider=='youtube')
        {
            $out = '<iframe src="https://www.youtube.com/embed/'.$reference.'?feature=oembed&rel=0&modestbranding=1" frameborder="0" allowfullscreen '.$extra.'></iframe>';
        }
        else if($provider=='vimeo')
        {
            $out = '<iframe src="https://player.vimeo.com/video/'.$reference.'?" frameborder="0" allowfullscreen '.$extra.'></iframe>';
        }

        return $out;
    }

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_files WHERE website = '.protect($website->id), 'object');
        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }

	public static function __set_state(array $obj)
	{
		$tmp = new file();
		foreach($obj as $key => $val)
			$tmp->$key = $val;

		return $tmp;
	}
}

?>
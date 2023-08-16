<?php
class KapostService extends Controller implements PermissionProvider {
    /**
     * If set to true when the service is called the user agent of the request is checked to see if it is Kapost's XML-RPC user agent
     * @config KapostService.check_user_agent
     * @default true
     */
    private static $check_user_agent=true;
    
    /**
     * Authenticator to be used for authenticating the Kapost account
     * @config KapostService.authenticator_class
     * @default MemberAuthenticator
     */
    private static $authenticator_class='MemberAuthenticator';
    
    /**
     * Authenticator to be used for authenticating the Kapost account
     * @config KapostService.authenticator_username_field
     * @default Email
     */
    private static $authenticator_username_field='Email';
    
    /**
     * Authenticator to be used for authenticating the Kapost account
     * @config KapostService.kapost_media_folder
     * @default kapost-media
     */
    private static $kapost_media_folder='kapost-media';
    
    /**
     * Tells the service what to do with duplicate media assets
     * Options:
     *    smart_rename: Verifies the file is the same as the existing file and instead uses that file, otherwise it renames the file to make it unique
     *    rename: Rename the file to make it unique
     *    overwrite: Overwrite the duplicate resource
     *    ignore: Ignore's the duplicate resource and returns an error to Kapost
     * 
     * @config KapostService.duplicate_assets
     * @default smart_rename
     */
    private static $duplicate_assets='smart_rename';
    
    /**
     * Preview expiry window in minutes
     * @config KapostService.preview_expiry
     * @default 10
     */
    private static $preview_expiry=10;
    
    /**
     * Database Character Set
     * @config KapostService.database_charset
     * @default UTF-8
     */
    private static $database_charset='UTF-8';
    
    /**
     * Enables filtering of the kapost thread tags in the description field
     * @config KapostService.filter_kapost_threads
     * @default true
     */
    private static $filter_kapost_threads=false;
    
    
    private $exposed_methods=array(
                                    'blogger.getUsersBlogs',
                                    'metaWeblog.newPost',
                                    'metaWeblog.editPost',
                                    'metaWeblog.getPost',
                                    'metaWeblog.getCategories',
                                    'metaWeblog.newMediaObject',
                                    'kapost.getPreview'
                                );
    
    private static $allowed_actions=array(
                                        'preview'
                                    );
    
    
    /**
     * Handles incoming requests to the kapost service
     */
    public function index() {
        //If the request is not a post request 404
        if(!$this->request->isPOST()) {
            return ErrorPage::response_for(404);
        }
        
        //If the request is not the kapost user agent 404
        if(self::config()->check_user_agent==true && $this->request->getHeader('User-Agent')!='Kapost XMLRPC::Client') {
            return ErrorPage::response_for(404);
        }
        
        $methods=array_fill_keys($this->exposed_methods, array('function'=>array($this, 'handleRPCMethod')));
        
        //Disable Content Negotiator and send the text/xml header (which kapost expects)
        ContentNegotiator::config()->enabled=false;
        $this->response->addHeader('Content-Type', 'text/xml');
        
        $server=new xmlrpc_server($methods, false);
        $server->compress_response=true;
        
        
        if(Director::isDev()) {
            $server->setDebug(3); //Base 64 encoded debug information is included in the response
            $server->exception_handling=2; //Exception's sent to the client
        }
        
        
        //Force the internal encoding of the XMLRPC library to utf-8
        $GLOBALS['xmlrpc_internalencoding']=self::config()->database_charset;
        
        
        return $server->service($this->request->getBody(), true);
    }
    
    /**
     * Handles rendering of the preview for an object
     * @return {string} Response to send to the object
     */
    public function preview() {
        $auth=$this->request->getVar('auth');
        $token=KapostPreviewToken::get()->filter('Code', Convert::raw2xml($auth))->first();
        
        //Verify the token exists and hasn't expired yet
        if(!empty($token) && $token!==false && $token->exists() && time()-strtotime($token->Created)<self::config()->preview_expiry*60 && $token->KapostRefID==$this->urlParams['ID']) {
            $kapostObj=KapostObject::get()->filter('KapostRefID', Convert::raw2xml($this->urlParams['ID']))->sort('"Created" DESC')->first();
            if(!empty($kapostObj) && $kapostObj!==false && $kapostObj->exists()) {
                $previewController=$kapostObj->renderPreview();
                
                $this->extend('updatePreviewDisplay', $kapostObj, $previewController);
                
                return $previewController;
            }
        }
        
        
        //Token expired or object not found
        $response=ErrorPage::response_for(404);
        if(!empty($response)) {
            return $response;
        }
        
        return parent::httpError(404);
    }
    
    /**
     * Handles RPC request methods
     * @param {xmlrpcmsg} $request XML-RPC Request Object
     */
    public function handleRPCMethod(xmlrpcmsg $request) {
        $username=$request->getParam(1)->getval();
        $password=$request->getParam(2)->getval();
        
        if($this->authenticate($username, $password)) {
            $method=str_replace(array('blogger.', 'metaWeblog.', 'kapost.'), '', $request->methodname);
            
            if(!in_array($request->methodname, $this->exposed_methods) || !method_exists($this, $method)) {
                return $this->httpError(403, _t('KapostService.METHOD_NOT_ALLOWED', '_Action "{method}" is not allowed on class Kapost Service.', array('method'=>$request->methodname)));
            }
            
            
            //Pack params into call to method if they are not the authentication parameters
            $params=array();
            for($i=0;$i<$request->getNumParams();$i++) {
                if($i!=1 && $i!=2) {
                    $params[]=php_xmlrpc_decode($request->getParam($i));
                }
            }
            
            
            //Convert the custom fields to an associtive array
            if(array_key_exists(1, $params) && is_array($params[1]) && array_key_exists('custom_fields', $params[1])) {
                $params[1]['custom_fields']=$this->struct_to_assoc($params[1]['custom_fields']);
            }
            
            
            //If transactions are supported start one for newPost and editPost
            if(($method=='newPost' || $method=='editPost') && DB::getConn()->supportsTransactions()) {
                DB::getConn()->transactionStart();
            }
            
            
            //Call the method
            $response=call_user_func_array(array($this, $method), $params);
            if($response instanceof xmlrpcresp) {
                //If transactions are supported check the response and rollback in the case of a fault
                if(($method=='newPost' || $method=='editPost' || $method=='newMediaObject') && DB::getConn()->supportsTransactions()) {
                    if($response->faultCode()!=0) {
                        DB::getConn()->transactionRollback();
                    }else {
                        DB::getConn()->transactionEnd();
                    }
                }
                
                return $response; //Response is already encoded so return
            }
            
            //Encode the response
            $response=php_xmlrpc_encode($response);
            if(is_object($response) && $response instanceof xmlrpcval) {
                $response=new xmlrpcresp($response);
                
                if(($method=='newPost' || $method=='editPost' || $method=='newMediaObject') && DB::getConn()->supportsTransactions()) {
                    if($response->faultCode()!=0) {
                        DB::getConn()->transactionRollback();
                    }else {
                        DB::getConn()->transactionEnd();
                    }
                }
                
                return $response;
            }
            
            return $this->httpError(500, _t('KapostService.INVALID_RESPONSE', '_Invalid response returned from {method}, response was: {response}', array(
                                                                                                                                                        'method'=>$method,
                                                                                                                                                        'response'=>print_r($response, true)
                                                                                                                                                    )));
        }
        
        
        return $this->httpError(401, _t('KapostService.AUTH_FAIL', '_Authentication Failed, please check the App Center credentials for the SilverStripe end point.'));
    }
    
    /**
     * Checks the authentication of the api request
     * @param {string} $username Username to look up
     * @param {string} $password Password to match against
     * @return {bool} Returns boolean true if authentication passes false otherwise
     */
    protected function authenticate($username, $password) {
        $authenticator=$this->config()->authenticator_class;
        
        $member=$authenticator::authenticate(array(
                                                $this->config()->authenticator_username_field=>$username,
                                                'Password'=>$password
                                            ));
        
        return (!empty($member) && $member!==false && $member->exists()==true && Permission::check('KAPOST_API_ACCESS', 'any', $member));
    }
    
    /**
     * Converts an error to an xmlrpc response
     * @param {int} $errorCode Error code number for the error
     * @param {string} $errorMessage Error message string
     * @return {xmlrpcresp} XML-RPC response object
     */
    public function httpError($errorCode, $errorMessage=null) {
        return new xmlrpcresp(0, $errorCode+10000, $errorMessage);
    }
    
    /**
     * Gets the site config or subsites for the current site
     * @return {array} Nested array of sites
     */
    protected function getUsersBlogs($app_id) {
        if(SiteConfig::has_extension('SiteConfigSubsites')) {
            $response=array();
            
            //Disable subsite filter
            Subsite::disable_subsite_filter();
            
            $subsites=Subsite::get();
            foreach($subsites as $subsite) {
                $response[]=array(
                                'blogid'=>$subsite->ID,
                                'blogname'=>$subsite->Title
                            );
            }
            
            //Re-enable subsite filter
            Subsite::disable_subsite_filter(false);
            
            return $response;
        }
        
        
        $siteConfig=SiteConfig::current_site_config();
        return array(
                    array(
                        'blogid'=>$siteConfig->ID,
                        'blogname'=>$siteConfig->Title
                    )
                );
    }
    
    /**
     * Handles creation of a new post
     * @param {mixed} $blog_id Identifier for the current site
     * @param {array} $content Post details
     * @param {int} $publish 0 or 1 depending on whether to publish the post or not
     * @param {bool} $isPreview Is preview mode or not (defaults to false)
     */
    protected function newPost($blog_id, $content, $publish, $isPreview=false) {
        $results=$this->extend('newPost', $blog_id, $content, $publish, $isPreview);
        if($results && is_array($results)) {
            $results=array_filter($results, function($v) {return !is_null($v);});
            
            if(count($results)>0) {
                return array_shift($results);
            }
        }
        
        
        if(array_key_exists('custom_fields', $content)) {
            //Ensure the type is an extension of the KapostPage object
            if(!class_exists('Kapost'.$content['custom_fields']['kapost_custom_type']) || !('Kapost'.$content['custom_fields']['kapost_custom_type']=='KapostPage' || is_subclass_of('Kapost'.$content['custom_fields']['kapost_custom_type'], 'KapostPage'))) {
                return $this->httpError(400, _t('KapostService.TYPE_NOT_KNOWN', '_The type "{type}" is not a known type', array('type'=>$content['custom_fields']['kapost_custom_type'])));
            }
            
            $className='Kapost'.$content['custom_fields']['kapost_custom_type'];
        }else {
            //Assume we're creating a page and set the content as such
            $className='KapostPage';
        }
        
        
        $pageTitle=$content['title'];
        if(array_key_exists('custom_fields', $content) && array_key_exists('SS_Title', $content['custom_fields']) && !empty($content['custom_fields']['SS_Title'])) {
            $pageTitle=$content['custom_fields']['SS_Title'];
        }
        
        $menuTitle=$content['title'];
        if(empty($content['title']) && array_key_exists('custom_fields', $content) && array_key_exists('SS_Title', $content['custom_fields']) && !empty($content['custom_fields']['SS_Title'])) {
            $menuTitle=$content['custom_fields']['SS_Title'];
        }
        
        $obj=new $className();
        $obj->Title=$pageTitle;
        $obj->MenuTitle=$menuTitle;
        $obj->Content=(self::config()->filter_kapost_threads==true ? $this->filterKapostThreads($content['description']):$content['description']);
        $obj->MetaDescription=(array_key_exists('custom_fields', $content) && array_key_exists('SS_MetaDescription', $content['custom_fields']) ? $content['custom_fields']['SS_MetaDescription']:null);
        $obj->KapostChangeType='new';
        $obj->KapostAuthor=(array_key_exists('custom_fields', $content) ? $content['custom_fields']['kapost_author']:null);
        $obj->KapostRefID=(array_key_exists('custom_fields', $content) ? $content['custom_fields']['kapost_post_id']:null);
        $obj->ToPublish=$publish;
        $obj->IsKapostPreview=$isPreview;
        $obj->write();
        
        
        //Fallback for tests where the kapost_post_id is missing
        if(!array_key_exists('custom_fields', $content)) {
            $obj->KapostRefID=$className.'_'.$obj->ID;
            $obj->write();
        }
        
        
        //Allow extensions to adjust the new page
        $this->extend('updateNewKapostPage', $obj, $blog_id, $content, $publish, $isPreview);
        
        return $obj->KapostRefID;
    }
    
    /**
     * Handles editing of a given post
     * @param {mixed} $content_id Identifier for the post
     * @param {array} $content Post details
     * @param {int} $publish 0 or 1 depending on whether to publish the post or not
     * @param {bool} $isPreview Is preview mode or not (defaults to false)
     */
    protected function editPost($content_id, $content, $publish, $isPreview=false) {
        $results=$this->extend('editPost', $content_id, $content, $publish, $isPreview);
        if($results && is_array($results)) {
            $results=array_filter($results, function($v) {return !is_null($v);});
            
            if(count($results)>0) {
                return array_shift($results);
            }
        }
        
        
        //Ensure the type is an extension of the KapostPage object
        if(array_key_exists('custom_fields', $content) && (!class_exists('Kapost'.$content['custom_fields']['kapost_custom_type']) || !('Kapost'.$content['custom_fields']['kapost_custom_type']=='KapostPage' || is_subclass_of('Kapost'.$content['custom_fields']['kapost_custom_type'], 'KapostPage')))) {
            return $this->httpError(400, _t('KapostService.TYPE_NOT_KNOWN', '_The type "{type}" is not a known type', array('type'=>$content['custom_fields']['kapost_custom_type'])));
        }
        
        
        //Assume we're looking for a page
        //Switch Versioned to stage
        $oldReadingStage=Versioned::current_stage();
        Versioned::set_reading_mode('stage');
        
        $page=SiteTree::get()->filter('KapostRefID', Convert::raw2sql($content_id))->first();
        
        //Switch Versioned back
        Versioned::set_reading_mode($oldReadingStage);
        
        
        $pageTitle=$content['title'];
        if(array_key_exists('custom_fields', $content) && array_key_exists('SS_Title', $content['custom_fields']) && !empty($content['custom_fields']['SS_Title'])) {
            $pageTitle=$content['custom_fields']['SS_Title'];
        }
        
        $menuTitle=$content['title'];
        if(empty($content['title']) && array_key_exists('custom_fields', $content) && array_key_exists('SS_Title', $content['custom_fields']) && !empty($content['custom_fields']['SS_Title'])) {
            $menuTitle=$content['custom_fields']['SS_Title'];
        }
        
        
        $kapostObj=KapostObject::get()->filter('KapostRefID', Convert::raw2sql($content_id))->first();
        if(!empty($kapostObj) && $kapostObj!==false && $kapostObj->exists()) {
            $kapostObj->Title=$pageTitle;
            $kapostObj->MenuTitle=$menuTitle;
            $kapostObj->Content=(self::config()->filter_kapost_threads==true ? $this->filterKapostThreads($content['description']):$content['description']);
            $kapostObj->MetaDescription=(array_key_exists('custom_fields', $content) && array_key_exists('SS_MetaDescription', $content['custom_fields']) ? $content['custom_fields']['SS_MetaDescription']:null);
            $kapostObj->LinkedPageID=(!empty($page) && $page!==false && $page->exists() ? $page->ID:$kapostObj->LinkedPageID);
            $kapostObj->KapostRefID=(array_key_exists('custom_fields', $content) ? $content['custom_fields']['kapost_post_id']:null);
            $kapostObj->KapostAuthor=(array_key_exists('custom_fields', $content) ? $content['custom_fields']['kapost_author']:null);
            $kapostObj->ToPublish=$publish;
            $kapostObj->IsKapostPreview=$isPreview;
            $kapostObj->write();
            
            //Allow extensions to adjust the existing object
            $this->extend('updateEditKapostPage', $kapostObj, $content_id, $content, $publish, $isPreview);
            
            return true;
        }else {
            $className=(array_key_exists('custom_fields', $content) ? 'Kapost'.$content['custom_fields']['kapost_custom_type']:'KapostPage');
            
            $obj=new $className();
            $obj->Title=$pageTitle;
            $obj->MenuTitle=$menuTitle;
            $obj->Content=(self::config()->filter_kapost_threads==true ? $this->filterKapostThreads($content['description']):$content['description']);
            $obj->MetaDescription=(array_key_exists('custom_fields', $content) && array_key_exists('SS_MetaDescription', $content['custom_fields']) ? $content['custom_fields']['SS_MetaDescription']:null);
            $obj->KapostChangeType='edit';
            $obj->LinkedPageID=(!empty($page) && $page!==false && $page->exists() ? $page->ID:0);
            $obj->KapostRefID=(array_key_exists('custom_fields', $content) ? $content['custom_fields']['kapost_post_id']:null);
            $obj->KapostAuthor=(array_key_exists('custom_fields', $content) ? $content['custom_fields']['kapost_author']:null);
            $obj->ToPublish=$publish;
            $obj->IsKapostPreview=$isPreview;
            $obj->write();
            
            
            //Allow extensions to adjust the new page
            $this->extend('updateEditKapostPage', $obj, $content_id, $content, $publish, $isPreview);
            
            return true;
        }
        
        
        //Can't find the object so return a 404 code
        return new xmlrpcresp(0, 404, _t('KapostService.INVALID_POST_ID', '_Invalid post ID.'));
    }
    
    /**
     * Gets the details of a post from the system
     * @param {mixed} $content_id ID of the post in the system
     */
    protected function getPost($content_id) {
        $results=$this->extend('getPost', $content_id);
        if($results && is_array($results)) {
            $results=array_filter($results, function($v) {return !is_null($v);});
            
            if(count($results)>0) {
                return array_shift($results);
            }
        }
        
        
        //Switch Versioned to stage
        $oldReadingStage=Versioned::current_stage();
        Versioned::set_reading_mode('stage');
        
        $page=SiteTree::get()->filter('KapostRefID', Convert::raw2sql($content_id))->first();
        
        //Switch Versioned back
        Versioned::set_reading_mode($oldReadingStage);
        
        
        if(!empty($page) && $page!==false && $page->exists()) {
            $postMeta=array(
                        'title'=>$page->Title,
                        'description'=>$page->Content,
                        'mt_keywords'=>'',
                        'mt_excerpt'=>'',
                        'categories'=>array('ss_page'),
                        'permaLink'=>$page->AbsoluteLink(),
                        'custom_fields'=>array(
                                array('id'=>'SS_Title', 'key'=>'SS_Title', 'value'=>$page->Title),
                                array('id'=>'SS_MetaDescription', 'key'=>'SS_MetaDescription', 'value'=>$page->MetaDescription)
                            )
                    );
            
            //Allow extensions to modify the page meta
            $results=$this->extend('updatePageMeta', $page);
            if(count($results)>0) {
                for($i=0;$i<count($results);$i++) {
                    $postMeta=$this->mergeResultArray($postMeta, $results[$i]);
                }
            }
            
            return $postMeta;
        }else {
            $kapostObj=KapostObject::get()->filter('KapostRefID', Convert::raw2sql($content_id))->first();
            if(!empty($kapostObj) && $kapostObj!==false && $kapostObj->exists()) {
                $postMeta=array(
                            'title'=>$kapostObj->Title,
                            'description'=>$kapostObj->Content,
                            'mt_keywords'=>'',
                            'mt_excerpt'=>'',
                            'categories'=>array('ss_page'),
                            'permaLink'=>Controller::join_links(Director::absoluteBaseURL(), 'admin/kapost/KapostObject/EditForm/field/KapostObject/item', $kapostObj->ID, 'edit'),
                            'custom_fields'=>array(
                                    array('id'=>'SS_Title', 'key'=>'SS_Title', 'value'=>$kapostObj->Title),
                                    array('id'=>'SS_MetaDescription', 'key'=>'SS_MetaDescription', 'value'=>$kapostObj->MetaDescription)
                                )
                        );
                
                //Allow extensions to modify the page meta
                $results=$this->extend('updateObjectMeta', $kapostObj);
                if(count($results)>0) {
                    for($i=0;$i<count($results);$i++) {
                        $postMeta=$this->mergeResultArray($postMeta, $results[$i]);
                    }
                }
                
                return $postMeta;
            }
        }
        
        return new xmlrpcresp(0, 404, _t('KapostService.INVALID_POST_ID', '_Invalid post ID.'));
    }
    
    /**
     * Gets the categories
     * @param {mixed} $blog_id ID of the blog
     * @return {array} Array of categories
     */
    protected function getCategories($blog_id) {
        $categories=array();
        $pageClasses=ClassInfo::subclassesFor('SiteTree');
        foreach($pageClasses as $class) {
            if($class!='SiteTree') {
                $categories[]=array(
                                'categoryId'=>'ss_'.strtolower($class),
                                'categoryName'=>singleton($class)->i18n_singular_name(),
                                'parentId'=>0
                            );
            }
        }
        
        
        
        $results=$this->extend('getCategories', $blog_id);
        if($results && is_array($results)) {
            $results=array_filter($results, function($v) {return !is_null($v);});
            
            if(count($results)>0) {
                for($i=0;$i<count($results);$i++) {
                    $categories=array_merge($categories, $results[$i]);
                }
            }
        }
        
        return $categories;
    }
    
    /**
     * Handles media objects from kapost
     * @param {mixed} $blog_id Site Config related to this content object
     * @param {array} $content Content object to be handled
     * @return {xmlrpcresp} XML-RPC Response object
     */
    protected function newMediaObject($blog_id, $content) {
        $fileName=$content['name'];
        $validator=new Upload_Validator(array('name'=>$fileName));
        $validator->setAllowedExtensions(File::config()->allowed_extensions);
        
        //Verify we have a valid extension
        if($validator->isValidExtension()==false) {
            return $this->httpError(403, _t('KapostService.FILE_NOT_ALLOWED', '_File extension is not allowed'));
        }
        
        
        //Generate default filename
        $nameFilter=FileNameFilter::create();
        $file=$nameFilter->filter($fileName);
        while($file[0]=='_' || $file[0]=='.') {
            $file=substr($file, 1);
        }
        
        $doubleBarrelledExts=array('.gz', '.bz', '.bz2');
        
        $ext="";
        if(preg_match('/^(.*)(\.[^.]+)$/', $file, $matches)) {
            $file=$matches[1];
            $ext=$matches[2];
            
            // Special case for double-barrelled 
            if(in_array($ext, $doubleBarrelledExts) && preg_match('/^(.*)(\.[^.]+)$/', $file, $matches)) {
                $file=$matches[1];
                $ext=$matches[2].$ext;
            }
        }
        
        $origFile=$file;
        
        
        //Find the kapost media folder
        $kapostMediaFolder=Folder::find_or_make($this->config()->kapost_media_folder);
        
        if(file_exists($kapostMediaFolder->getFullPath().'/'.$file.$ext)) {
            if(self::config()->duplicate_assets=='overwrite') {
                $obj=File::get()->filter('Filename', Convert::raw2sql($kapostMediaFolder->Filename.$file.$ext))->first();
                if(!empty($obj) && $obj!==false && $obj->ID>0) {
                    //Update the Title for the image
                    $obj->Title=(!empty($content['alt']) ? $content['alt']:str_replace(array('-','_'), ' ', preg_replace('/\.[^.]+$/', '', $obj->Name)));
                    $obj->write();
                    
                    //Write the file to the file system
                    $f=fopen($kapostMediaFolder->getFullPath().'/'.$file.$ext, 'w');
                    fwrite($f, $content['bits']);
                    fclose($f);
                
                    return array(
                                'id'=>$obj->ID,
                                'url'=>$obj->getAbsoluteURL()
                            );
                }
                
                return $this->httpError(404, _t('KapostService.FILE_NOT_FOUND', '_File not found'));
            }else if(self::config()->duplicate_assets=='ignore') {
                return $this->httpError(409, _t('KapostService.DUPLICATE_FILE', '_Duplicate file detected, please rename the file and try again'));
            }else {
                if(self::config()->duplicate_assets=='smart_rename' && file_exists($kapostMediaFolder->getFullPath().'/'.$file.$ext)) {
                    $obj=File::get()->filter('Filename', Convert::raw2sql($kapostMediaFolder->Filename.$file.$ext))->first();
                    if(!empty($obj) && $obj!==false && $obj->ID>0) {
                        $fileHash=sha1_file($kapostMediaFolder->getFullPath().'/'.$file.$ext);
                        if($fileHash==sha1($content['bits'])) {
                            return array(
                                        'id'=>$obj->ID,
                                        'url'=>$obj->getAbsoluteURL()
                                    );
                        }
                    }
                }
                
                $i = 1;
                while(file_exists($kapostMediaFolder->getFullPath().'/'.$file.$ext)) {
                    $i++;
                    $oldFile=$file;
                     
                    if(strpos($file, '.')!==false) {
                        $file = preg_replace('/[0-9]*(\.[^.]+$)/', $i.'\\1', $file);
                    }else if(strpos($file, '_')!==false) {
                        $file=preg_replace('/_([^_]+$)/', '_'.$i, $file);
                    }else {
                        $file.='_'.$i;
                    }
                    
                    if($oldFile==$file && $i > 2) {
                        return $this->httpError(500, _t('KapostService.FILE_RENAME_FAIL', '_Could not fix {filename} with {attempts} attempts', array('filename'=>$file.$ext, 'attempts'=>$i)));
                    }
                }
                
                //Write the file to the file system
                $f=fopen($kapostMediaFolder->getFullPath().'/'.$file.$ext, 'w');
                fwrite($f, $content['bits']);
                fclose($f);
                
                
                //Write the file to the database
                $className=File::get_class_for_file_extension(substr($ext, 1));
                $obj=new $className();
                $obj->Name=$file.$ext;
                $obj->Title=(!empty($content['alt']) ? $content['alt']:str_replace(array('-','_'), ' ', preg_replace('/\.[^.]+$/', '', $obj->Name)));
                $obj->FileName=$kapostMediaFolder->getRelativePath().'/'.$file.$ext;
                $obj->ParentID=$kapostMediaFolder->ID;
                
                //If subsites is enabled add it to the correct subsite
                if(File::has_extension('FileSubsites')) {
                    $obj->SubsiteID=$blog_id;
                }
                
                $obj->write();
                
                
                $this->extend('updateNewMediaAsset', $blog_id, $content, $obj);
                
                
                return array(
                            'id'=>$obj->ID,
                            'url'=>$obj->getAbsoluteURL()
                        );
            }
        }else {
            //Write the file to the file system
            $f=fopen($kapostMediaFolder->getFullPath().'/'.$file.$ext, 'w');
            fwrite($f, $content['bits']);
            fclose($f);
            
            
            //Write the file to the database
            $className=File::get_class_for_file_extension(substr($ext, 1));
            $obj=new $className();
            $obj->Name=$file.$ext;
            $obj->Title=(!empty($content['alt']) ? $content['alt']:str_replace(array('-','_'), ' ', preg_replace('/\.[^.]+$/', '', $obj->Name)));
            $obj->FileName=$kapostMediaFolder->getRelativePath().'/'.$file.$ext;
            $obj->ParentID=$kapostMediaFolder->ID;
            
            //If subsites is enabled add it to the correct subsite
            if(File::has_extension('FileSubsites')) {
                $obj->SubsiteID=$blog_id;
            }
            
            $obj->write();
            
            
            $this->extend('updateNewMediaAsset', $blog_id, $content, $obj);
            
            return array(
                        'id'=>$obj->ID,
                        'url'=>$obj->getAbsoluteURL()
                    );
        }
    }
    
    /**
     * Handles rendering of the preview
     * @param {mixed} $blog_id Identifier for the current site
     * @param {array} $content Post details
     * @param {mixed} $content_id Identifier for the post
     */
    protected function getPreview($blog_id, $content, $content_id) {
        $results=$this->extend('getPreview', $blog_id, $content, $content_id);
        if($results && is_array($results)) {
            $results=array_filter($results, function($v) {return !is_null($v);});
        
            if(count($results)>0) {
                return array_shift($results);
            }
        }
        
        
        //Detect if the record already exists or not so we can decide whether to create a new record or edit an existing
        $existing=KapostObject::get()->filter('KapostRefID', Convert::raw2xml($content_id))->first();
        if(!empty($existing) && $existing!==false && $existing->exists()) {
            $resultID=$content_id;
            
            $this->editPost($content_id, $content, false, true);
        }else {
            $resultID=$this->newPost($blog_id, $content, false, true);
            
            //Find the object
            $existing=KapostObject::get()->filter('KapostRefID', Convert::raw2xml($resultID))->first();
        }
        
        //Make sure we got the kapost hash back or an id if we got an object back we assume that it's a response
        if(is_object($resultID)) {
            return $resultID;
        }
        
        
        //Generate a preview token record
        $token=new KapostPreviewToken();
        $token->Code=sha1(uniqid(time().$resultID));
        $token->KapostRefID=$resultID;
        $token->write();
        
        
        //Return the details to kapost
        return array(
                    'url'=>Controller::join_links(Director::absoluteBaseURL(), 'kapost-service/preview', $resultID, '?auth='.$token->Code),
                    'id'=>$resultID
                );
    }
    
    /**
     * Converts a struct to an associtive array based on the key value pair in the struct
     * @param {array} $struct Input struct to be converted
     * @return {array} Associtive array matching the struct
     */
    final protected function struct_to_assoc($struct) {
        $result=array();
        foreach($struct as $item) {
            if(array_key_exists('key', $item) && array_key_exists('value', $item)) {
                if(array_key_exists($item['key'], $result)) {
                    user_error('Duplicate key detected in struct entry, content overwritten by the last entry: [New: '.print_r($item, true).'] [Previous: '.print_r($result[$item['key']], true).']', E_USER_WARNING);
                }
                
                $result[$item['key']]=$item['value'];
            }else {
                user_error('Key/Value pair not detected in struct entry: '.print_r($item, true), E_USER_NOTICE);
            }
        }
        
        return $result;
    }
    
    /**
     * Merges two arrays, overwriting the keys in the left array with the right array recurrsivly. Meaning that if a value in the right array is it self an array and the key exists in the left array it recurses into it.
     * @param {array} $leftArray Left array to merge into
     * @param {array} $rightArray Right array to merge from
     * @return {array} Resulting array
     */
    private function mergeResultArray($leftArray, $rightArray) {
        foreach($rightArray as $key=>$value) {
            if(is_array($value) && array_key_exists($key, $leftArray)) {
                $leftArray[$key]=array_merge($leftArray[$key], $value);
            }else {
                $leftArray[$key]=$value;
            }
        }
        
        return $leftArray;
    }
    
    /**
     * Filters the kapost content to remove the thread tags from a Kapost WYSIWYG
     * @param {string} $html HTML to filter the tags from
     * @return {string} HTML with tags filtered
     */
    public function filterKapostThreads($html) {
        return preg_replace('/<span(\s+)thread="(.*?)"(\s+)class="thread">(.*?)<\/span>/', '$4', $html);
    }
    
    /**
     * Finds a file record based on the url of the file, this is needed because Kapost doesn't seem to send anything back other than the url in the cms
     * @param {string} $url Absolute url to the file
     * @return {File} Returns the file instance representing the url, or boolean false if it's not found
     */
    public static function find_file_by_url($url) {
        $url=Director::makeRelative($url);
        if($url) {
            $file=File::get()->filter('Filename', Convert::raw2sql($url))->first();
            if(!empty($file) && $file!==false && $file->ID>0) {
                return $file;
            }
        }
        
        return false;
    }
    
    /**
     * Return a map of permission codes to add to the dropdown shown in the Security section of the CMS.
     * @return {array} Map of permission codes
     */
    public function providePermissions() {
        return array(
                   'KAPOST_API_ACCESS'=>array(
                                               'category'=>_t('KapostService.KAPOST_BRIDGE', '_Kapost Bridge'),
                                               'name'=>_t('KapostService.PERMISSION_API_ACCESS', '_Kapost API Access'),
                                               'help'=>_t('KapostService.PERMISSION_API_ACCESS_DESC', '_Access the XML-RPC Endpoint for Kapost to communicate with')
                                           ),
                );
    }
}
?>
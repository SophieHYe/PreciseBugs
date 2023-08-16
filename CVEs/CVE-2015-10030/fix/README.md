Surpass
=====

A PHP package mainly developed for Laravel to manage uploading images using Ajax and displaying thumbnails.

(Example)  
!['Kitsilano'](http://i.imgur.com/cJ6t50G.png)

Requirements
====

jQuery, jQuery UI and 
[blueimp/jQuery-File-Upload](https://github.com/blueimp/jQuery-File-Upload)


Installation&setting for Laravel
====

After installation using composer, add the followings to the array in  app/config/app.php

    'providers' => array(  
        ...Others...,  
        'Sukohi\Surpass\SurpassServiceProvider', 
    )


    'aliases' => array(  
        ...Others...,  
        'Surpass' =>'Sukohi\Surpass\Facades\Surpass',
    )

And execute the followings.  
**Note: If you get errors after updating, also execute them.**

    php artisan migrate --package=sukohi/surpass
    
    php artisan view:publish sukohi/surpass

Usage
====

(See also a folder named "exaple" which has some files.)

**Upload  (in Controller)**

	$surpass = Surpass::path('img/uploads')
					->dir('dir_name')
					->ids([
						'input' => 'image_upload',
						'preview' => 'preview_images'
					])
					->maxFiles(5)
					->alert('You can upload up to %d files.')
					->formData([
						'key_1' => 'value_1', 
						'key_2' => 'value_2', 
						'key_3' => 'value_3'
					])
					->preview(['maxHeight' => 120])
					->css([
						'div' => 'div_class', 
						'button' => 'button_class', 
						'preview' => 'preview_class', 
						'loading' => 'loading_class'
					])
					->progress('<img src="http://example.com/img/ajax-loader.gif"><br>Uploading..')
					->callback([
						'upload' => 'alert("Uploading..");', 
						'done' => 'alert("Done.");',
						'failed' => 'alert("Failed..");', 
						'remove' => 'alert("Removed");', 
						'load' => 'alert("Loading..");',
                        'timeout' => 'alert("Timeout..");',
                        'file_type_error' => 'alert("Only image files are allowed");'
					])
					->timeout(3000) // 3 seconds
					->overwrite(false)   // When using overwriting-mode
					->resize(['maxWidth' => '100', 'maxHeight' => '50'], $force_crop = false)   // Client Resizing(See "About resizing")
					->dropZone('drop_zone_id')  // See "Drop Zone"
					->button('Remove');
	$surpass->load([1, 2, 3]);    // These are IDs of DB that you saved image(s) in the past.

	return View::make('surpass', [
			
		'surpass' => $surpass
			
	]);
	
	
*Note: method dir('dir_name') can no longer receive "/" and "." to protect from directory traversal attack.

**Upload  (in View)**

    (in View)    
    
    Note: Need to load jQuery, jQuery UI and jQuery-File-Upload(Loading order is important.See the below.)
    
    @section('content')
    
        <form>
        	<input 
        		id="image_upload" 
        		name="image_upload" 
        		title="Select Image" 
        		data-url="http://example.com/upload" 
        		data-remove-url="http://example.com/remove" 
        		accept="image/*" 
        		type="file" multiple>
        		
    		<!-- Preview(s) will be displayed here -->
        	{{ $surpass->html('preview') }}
        </form>
    @stop
    
    @section('script')
    
        <!-- Load required JS files. -->
        <script src="http://ajax.googleapis.com/ajax/libs/jquery/2.0.2/jquery.min.js"></script>
        <script src="bower_components/blueimp-file-upload/js/vendor/jquery.ui.widget.js"></script>
        <script src="bower_components/blueimp-load-image/js/load-image.all.min.js"></script>
        <script src="bower_components/blueimp-canvas-to-blob/js/canvas-to-blob.js"></script>
        <script src="bower_components/blueimp-file-upload/js/jquery.iframe-transport.js"></script>
        <script src="bower_components/blueimp-file-upload/js/jquery.fileupload.js"></script>
        <script src="bower_components/blueimp-file-upload/js/jquery.fileupload-process.js"></script>
        <script src="bower_components/blueimp-file-upload/js/jquery.fileupload-image.js"></script>
        <script src="bower_components/blueimp-tmpl/js/tmpl.min.js"></script>
    
        <!-- JS code (including script tag) will be displayed here -->
        {{ $surpass->html('js') }}
    @stop

**Upload (Ajax)**
	
	*Important: To save images you want, you need to make a specific dir which must be writable in advance.
	
    // To save an image and the data into DB

	$surpass = Surpass::path('img/uploads')
					->id('input', 'image_upload');
	$attributes = array('alt' => 'alt_value', 'title' => 'title_value');  // Skippable

	if($surpass->save($attributes = array())) {
	
	    // You can get the data of saved image like this.
	
		$load_item = $surpass->loadSaved();
		$id = $load_item->id;
		$dir = $load_item->dir;
		$filename = $load_item->filename;
		$path = $load_item->path;
		$url = $load_item->url;
		$attributes = $load_item->attributes;
		$tag = $load_item->tag;
		
		// You can save attributes also here. (Of course you can do that at other places.)
		
		$id = $load_item->id;
		$surpass->saveAttributes($id, array(
		    'key_1' => 'value_1',
		    'key_2' => 'value_2',
		    'key_3' => 'value_3'
		));
		
	}
	
	return $surpass->result();  // This will return json.
	
*Note: If uploading completed, the result data(json) has the following values.

1.result : true / false  
2.insertId  
3.path  
4.dir  
5.filename  
6.file_path  
7.extension  
8.width  
9.height  
10.mime_type  
11.saveMode : overwrite / insert

**Remove (Ajax)**

    // To remove an image and the data into DB

	$surpass = Surpass::path('img/uploads');
	
	if($surpass->remove()) {
		// Something..
	}
	
	return $surpass->result();  // This will return json.
	
**Minimal Way**

(in Contoller)
    

	$surpass = Surpass::path('img/uploads')->dir('dir_name');
	$surpass->load([1, 2, 3]);
(in View)
See above. 
	
(in Upload Ajax)

	$surpass = Surpass::path('img/uploads');
	$dir = $surpass->requestDir();
	
	if($surpass->save()) {
		// Something..
	}
	
	return $surpass->result();
(in Remove Ajax)
	
	
	$surpass = Surpass::path('img/uploads');
	
	if($surpass->remove()) {
		// Something..
	}
	
	return $surpass->result();

**Refresh**  
This method will remove all data and images that don't already exist.  

    $surpass = Surpass::path('img/uploads');
    $surpass->refresh();
	
**Remove by ID**
	
    $surpass = Surpass::path('img/uploads')
					->removeById(1);
	// or
    $surpass = Surpass::path('img/uploads')
					->removeById([1, 2, 3]);
**Load with validation**

    $ids = [1, 2, 3];
    $surpass->load($ids, $old_flag = true);
    // If $old_flag is true, $ids will be replaced with Input::old() value(s) automatically.
    
**Get image file id(s) when submitting**
	
    Surpass::imageFileId('dir_name');
    Surpass::imageFileIds('dir_name');
	
**About Saved IDs**

Afeter uploading image(s) with Ajax, the preview(s) have hidden-input-tag(s) named "surpass_ids[]" (and of course the value(s) are ID of DB saved at the time).  
So when submitting, you can receive those data as array.

**with Multiple file-inputs**

    (in Controller)
	$surpass = Surpass::path('img/uploads');
	$surpass_x = clone $surpass->dir('xxx')
					->ids([
						'input' => 'input-xxx',
						'preview' => 'preview-xxx'
					]);
	$surpass_y = clone $surpass->dir('yyy')
					->ids([
						'input' => 'input-yyy',
						'preview' => 'preview-yyy'
					]);
	return View::make('view', [
		'surpass_x' => $surpass_x,
		'surpass_y' => $surpass_y
	]);
	
	(in View)
	<input 
		id="input-xxx" 
		name="input-xxx" 
		title="Select Image" 
		data-url="http://example.com/upload" 
		data-remove-url="http://example.com/remove" 
		accept="image/*" 
		type="file" multiple>
	{{ $surpass_x->html('preview') }}
	<input 
		id="input-yyy" 
		name="input-yyy" 
		title="Select Image" 
		data-url="http://example.com/upload" 
		data-remove-url="http://example.com/remove" 
		accept="image/*" 
		type="file" multiple>
	{{ $surpass_y->html('preview') }}
    // JS
    {{ $surpass_x->html('js') }}
    {{ $surpass_y->html('js') }}

**Set filename length**

    Surpass::filenameLength(10);    // Default: 10

**Insert**

    $insert_id = Surpass::path('path')
                    ->dir('dir')
                    ->insert('file_path', $attributes = array());

    *Note: This method is to save image(s) and their data directly like seeding.  
    So, in usual you should use save() method.

**Drop Zone**

If you'd like to upload images through Drop Zone(using Drag and Drop), add a div-tag like the below.
    
    (in Controller)
    
    $surpass->dropZone('drop_zone_id');
    
    
    (in View)
    
    <div id="drop_zone_id">Drop images here!</div>

**Save Attributes**

    $id = 1;    // Here means ID of "image_files" table.
    $surpass->saveAttributes($id, array(
        'key_1' => 'value_1',
        'key_2' => 'value_2',
        'key_3' => 'value_3'
    ));
    
    *Note: The old attributes data will be removed.

License
====

This package is licensed under the MIT License.

Copyright 2014 Sukohi Kuhoh
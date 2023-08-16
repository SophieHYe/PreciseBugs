<?php
@ini_set('max_execution_time','3600');
@ini_set('max_input_time','3600');

header("Content-Type: application/json");

if(empty($_REQUEST['session_id']))
    die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "Failed to retrieve session_id."}, "id" : "id"}');

session_id($_REQUEST['session_id']);

require_once('cfg/globals.php');
require_once('cfg/common.php');
require_once(NAVIGATE_PATH.'/lib/packages/files/file.class.php');

//file_put_contents(NAVIGATE_PATH . '/private/debug.txt', print_r($_REQUEST, true).print_r($_FILES, true));

/* global variables */
global $DB;
global $user;
global $config;
global $layout;
global $website;

// create database connection
$DB = new database();
if(!$DB->connect())
	die(APP_NAME.' # ERROR<br /> '.$DB->get_last_error());

// session checking
if(empty($_SESSION['APP_USER#'.APP_UNIQUE]))
{
	$DB->disconnect();
	die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "No user logged in."}, "id" : "id"}');
}
else
{
	$user = new user();
	$user->load($_SESSION['APP_USER#'.APP_UNIQUE]);
}

// load the working website
$website = new Website();
if(!empty($_SESSION['website_active']))
	$website->load($_SESSION['website_active']);
else	
	$website->load(); // load the first available


// force loading user permissions before desconnecting from the database
$foo = $user->permission("foo");

session_write_close();
$DB->disconnect();

function navigate_upload_remove_temporary($targetDir, $maxFileAge=86400)
{
	if(is_dir($targetDir) && ($dir = opendir($targetDir)))
	{
		while (($file = readdir($dir)) !== false) 
		{
			$filePath = $targetDir . DIRECTORY_SEPARATOR . $file;
		
			// Remove temp files if they are older than the max age
			if (preg_match('/\\.tmp$/', $file) && (filemtime($filePath) < time() - $maxFileAge))
				@unlink($filePath);
		}
		closedir($dir);
	}
}

// HTTP headers for no cache etc
/*
header('Content-type: text/plain; charset=UTF-8');
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
*/

// Settings
$targetDir = NAVIGATE_PRIVATE.'/'.$website->id.'/files';
$maxFileAge = 24 * 60 * 60; // Temp file age in seconds (1 day)

// no maximum uploading/execution time
@set_time_limit(0);

// filedrop drag'n'drop engine	
if($_REQUEST['engine']=='dropzone')
{
	if($user->permission("files.upload")=="true")
	{
		$tmpfilename = tempnam($targetDir, "upload-");
		$tmpfilename = basename($tmpfilename);

		if(count($_FILES) > 0)
		{
			if(!file_exists($_FILES['upload']['tmp_name']))
				die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "Uploaded file missing."}, "id" : "id"}');

			if(empty($_SERVER['HTTP_ACCEPT_CHARSET']))
				$_SERVER['HTTP_ACCEPT_CHARSET'] = 'UTF-8';

			$original_filename = mb_convert_encoding($_FILES['upload']['name'], 'UTF-8', $_SERVER['HTTP_ACCEPT_CHARSET']);

			if(move_uploaded_file($_FILES['upload']['tmp_name'], $targetDir.'/'.$tmpfilename ) )
			{
				echo json_encode(array("filename" => $original_filename, 'temporal' => $tmpfilename));
			}
			navigate_upload_remove_temporary($targetDir, $maxFileAge);
			core_terminate();
		}
		else if(isset($_GET['up']))
		{
			if(isset($_GET['base64']))
			{
				$content = base64_decode(file_get_contents('php://input'));
			}
			else
			{
				$content = file_get_contents('php://input');
			}

			$headers = getallheaders();
			$headers = array_change_key_case($headers, CASE_UPPER);

			if( file_put_contents( $targetDir.'/'.$tmpfilename, $content ) )
			{
				echo json_encode(array("filename" => $headers['UP-FILENAME'], 'temporal' => $tmpfilename));
			}
			navigate_upload_remove_temporary($targetDir, $maxFileAge);
			core_terminate();
		}
	}
}
else if($_REQUEST['engine']=='pixlr')
{	
	if(!empty($_REQUEST['id']) && file_exists($targetDir.'/'.$_REQUEST['id']))
	{	
		if(!empty($_REQUEST['image']))
		{
			//file_put_contents( $targetDir.'/'.$_REQUEST['id'], $_REQUEST['image'] );
			//copy( $_REQUEST['image'], $targetDir.'/'.$_REQUEST['id'].'.pixlr' );

			// download the file even if the user loads a different page
			@ignore_user_abort(true);
			
			$size = core_filesize_curl($_REQUEST['image']);

			$content = str_pad('', 512, 'navigate_upload from '.$_REQUEST['image'].' ('.$size.') ');	   
			header("HTTP/1.1 200 OK");  
			header("Content-Length: ".strlen($content));  
			echo $content; // output content  
			header('Connection: close');
			
			//$image = core_http_request($_REQUEST['image']);
			$image = file_get_contents($_REQUEST['image']);
			file_put_contents($targetDir.'/'.$_REQUEST['id'].'.pixlr', $image);

			if(file_exists($targetDir.'/'.$_REQUEST['id'].'.pixlr'))
			{
				if(filesize($targetDir.'/'.$_REQUEST['id'].'.pixlr')!=$size)
				{
					@unlink($targetDir.'/'.$_REQUEST['id'].'.pixlr');
				}
				else
				{
					unlink($targetDir.'/'.$_REQUEST['id']);
					rename($targetDir.'/'.$_REQUEST['id'].'.pixlr', $targetDir.'/'.$_REQUEST['id']);
					// update file info and remove old thumbnails
	
					$DB = new database();
					$DB->connect();
	
					$file = new file();
					$file->load($_REQUEST['id']);
					$file->refresh();			
					$DB->disconnect();				
	
					core_terminate();
				}
			}
		}
	}
	echo false;
	core_terminate();
}
else if($_REQUEST['engine']=='photopea')
{
	if(!empty($_REQUEST['id']) && file_exists($targetDir.'/'.$_REQUEST['id']))
    {
		if(!empty($_POST['p']))
		{
			// download the file even if the user loads a different page
			@ignore_user_abort(true);

            $p = json_decode( $_POST["p"] );	// parse JSON
            $image = $p->versions[0]->data; // always PNG
            unset($p); // free memory
            $image = base64_decode($image);

            file_put_contents($targetDir.'/'.$_REQUEST['id'].'.photopea', $image);
            unset($image);

            $filesize = filesize($targetDir.'/'.$_REQUEST['id'].'.photopea');

            if(file_exists($targetDir.'/'.$_REQUEST['id'].'.photopea') && $filesize > 128)
			{
                unlink($targetDir.'/'.$_REQUEST['id']);
                rename($targetDir.'/'.$_REQUEST['id'].'.photopea', $targetDir.'/'.$_REQUEST['id']);

                header("HTTP/1.1 200 OK");
                header('Access-Control-Allow-Origin: *');
                echo 'Image saved! ('.core_bytes($filesize).')'; // output content
                header('Connection: close');

                // update file info and remove old thumbnails
                $DB = new database();
                $DB->connect();
                $file = new file();
                $file->load($_REQUEST['id']);
                $file->refresh();
                $DB->disconnect();
			}
			else
            {
                @unlink($targetDir.'/'.$_REQUEST['id'].'.photopea'); // too small, ignore image

                header("HTTP/1.1 200 OK");
                header('Access-Control-Allow-Origin: *');
                echo 'ERROR!'; // output content
                header('Connection: close');
            }
		}
	}
	core_terminate();
}
else if($_REQUEST['engine']=='tinymce')
{
	$DB = new database();
	$DB->connect();

    $file = file::register_upload(
        $_FILES['file']['tmp_name'],
        $_FILES['file']['name'],
        0,
	    NULL,
	    true
    );

	if(!empty($file))
		echo json_encode(array('location' => file::file_url($file->id)));
	else
		echo json_encode(false);
	
	$DB->disconnect();
	core_terminate();
}
else
{
	// plUpload engine
	if($user->permission("files.upload")=="true")
	{
		// Get parameters
		$chunk = isset($_REQUEST["chunk"]) ? $_REQUEST["chunk"] : 0;
		$chunks = isset($_REQUEST["chunks"]) ? $_REQUEST["chunks"] : 0;
		$fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : '';

		// Clean the fileName for security reasons
		$fileName = base64_encode($fileName);

		// Remove old temp files
		if (is_dir($targetDir) && ($dir = opendir($targetDir)))
		{
			navigate_upload_remove_temporary($targetDir, $maxFileAge);
		}
		else
			die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "Failed to open temp directory."}, "id" : "id"}');

		// Look for the content type header
		if (isset($_SERVER["HTTP_CONTENT_TYPE"]))
			$contentType = $_SERVER["HTTP_CONTENT_TYPE"];

		if (isset($_SERVER["CONTENT_TYPE"]))
			$contentType = $_SERVER["CONTENT_TYPE"];

		if (strpos($contentType, "multipart") !== false)
		{
			if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name']))
			{
				// Open temp file
				$out = fopen($targetDir . DIRECTORY_SEPARATOR . $fileName, ($chunk == 0 ? "wb" : "ab"));
				if ($out)
				{
					// Read binary input stream and append it to temp file
					$in = fopen($_FILES['file']['tmp_name'], "rb");

					if ($in)
					{
						while ($buff = fread($in, 4096))
							fwrite($out, $buff);
					}
					else
						die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');

					fclose($out);
					@unlink($_FILES['file']['tmp_name']);

					// save meta file info into database (need a new db connection, we do this in the caller script)
				}
				else
					die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
			}
			else
				die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
		}
		else
		{
			// Open temp file
			$out = fopen($targetDir . DIRECTORY_SEPARATOR . $fileName, $chunk == 0 ? "wb" : "ab");
			if ($out)
			{
				// Read binary input stream and append it to temp file
				$in = fopen("php://input", "rb");

				if ($in)
				{
					while ($buff = fread($in, 4096))
						fwrite($out, $buff);
				}
				else
					die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');

				fclose($out);
			}
			else
				die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
		}

		// Return JSON-RPC response
		die('{"jsonrpc" : "2.0", "result" : null, "id" : "id"}');
	}
}

?>
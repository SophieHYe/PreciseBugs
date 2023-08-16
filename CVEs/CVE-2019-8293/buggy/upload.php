<?php
    if(isset($_FILES["fileUpload"]["name"])){
        $imageFile = ($_FILES["fileUpload"]["name"]);
        $imageType = ($_FILES["fileUpload"]["type"]);
        $validext = array("jpeg","jpg","png");
        $fileExt = pathinfo($imageFile, PATHINFO_EXTENSION);
        $ready = false;
        if((($imageType == "image/jpeg") || ($imageType == "image/jpg") || ($imageType == "image/png"))&&in_array($fileExt, $validext)){
            $ready = true;
        }else{
            echo "was not an image<br>";
        }

        if($_FILES["fileUpload"]["size"] < 1000000){
            $ready = true;
            echo "file size is ".$_FILES['fileUpload']["size"]."<br>";
        }else{
            echo "file was TOO BIG!";
        }

        if($_FILES["fileUpload"]["error"]){
            echo "looks like there was an error".$_FILES['fileUpload']["error"]."<br>";
            $ready = false;
        }

        $targetPath = "images/".$imageFile;
        $sourcePath = $_FILES["fileUpload"]["tmp_name"];
        if(file_exists("images/".$imageFile)){
            echo "File already there <br>";
            $ready = false;
        }

        if($ready == true){
            move_uploaded_file($sourcePath,$targetPath);
            echo "upload successful!";
        }
    }
?>
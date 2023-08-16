<?php
    $gwd = getcwd();
    
    $path = $_GET["path"];
    $path = (!$path) ? header("location: index.php?path=". $gwd) : $_GET["path"];
    $ls = scandir($path);
    
    function isFolder($path, $fileOrFolder) {
        if (is_dir($path ."\\". $fileOrFolder) && $fileOrFolder != "." && $fileOrFolder != "..") {
            return true;
        } else {
            return false;
        }
    }
    
    function isFile($path, $fileOrFolder) {
        if (!is_dir($path ."\\". $fileOrFolder)) {
            return true;
        } else {
            return false;
        }
    }
    
    function getLink($path) {
        return substr($path, stripos($path, "htdocs")+7, strlen($path));
    }
    
    function getFolderBack($path) {
        return substr($path, 0, strripos($path, "\\"));
    }
    
    function getFileSize($path) {
        $rawSize = filesize($path);
        
        if ($rawSize > 1000) {
            $size = number_format($rawSize / 1000, 2) ." kb";
        } else if ($rawSize <= 1000) {
            return $rawSize ." bytes";
        }
        
        return $size;
    }
?>
<html>
    <head>
        <title><?php echo $path; ?></title>
        <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css">
        <script src="//netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
    </head>
    <body>
        <table class="table table-hover table-condensed" style="border: 0px;">
<?php
    echo "<tr><td colspan='3'><img src='folder.png'><a href='index.php?path=". getFolderBack($path) ."'>". getFolderBack($path) ."</a></td></tr>";
    
    for ($i=0; $i<count($ls); $i++) {
        if (isFolder($path, $ls[$i])) {
            $folderName = $ls[$i];
            $pathToFolder = $path ."\\". $folderName;
            
            echo "<tr>";
            echo "<td colspan='3'>";
            echo "<a href='http://localhost/". getLink($pathToFolder) ."' target='_blank'><img src='chrome.png'></a>";
            echo "<img src='folder.png'>";
            echo "<a href='index.php?path=". $pathToFolder ."'>". $folderName ."</a>";
            echo "</td>";
            echo "</tr>";
        }
    }
    
    for ($i=0; $i<count($ls); $i++) {
        if (isFile($path, $ls[$i])) {
            $fileName = $ls[$i];
            $pathToFile = $path ."\\". $fileName;
            
            echo "<tr>";
            echo "<td colspan='2'>";
            echo "<a href='http://localhost/". getLink($pathToFile) ."' target='_blank'><img src='chrome.png'></a>";
            echo "<img src='file.png'><a href='index.php?path=". $pathToFile ."'>". $fileName ."</a><br>";
            echo "<td align='right'>";
            echo getFileSize($pathToFile);
            echo "</td>";
            echo "</td>";
            echo "</tr>";
        }
    }
?>
        </table>
    </body>
</html>
<?php
    
    
    
?>

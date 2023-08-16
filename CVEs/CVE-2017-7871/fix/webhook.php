<?php 
use \Dropbox as dbx;

function verify(){
	echo htmlspecialchars($_GET['challenge']);
}

function webhook(){
	#'''Receive a list of changed user IDs from Dropbox and process each.'''

	//1 recup de l'header et verifie si signature dropbox
	$signature = (isset(getallheaders()['X-Dropbox-Signature'])) ? getallheaders()['X-Dropbox-Signature'] : "signature invalide" ;
	//comment vérifier la signature ? (non facultatif)

	//2 recup du json
	$data = file_get_contents("php://input"); 
	$uidList = json_decode($data);
	// file_put_contents('dblog.txt',$data."\n".$uidList);

	//3 repondre rapidement
	echo 'Lancement process_user';
	process_user();
	//nb  : on n'utilise pas les uid donc cette fonction pourrait se résumer en process_user();
}

function delta($myCustomClient ,$cursortxt, $url, $pathPrefix){
	$cursor=file_get_contents($cursortxt);
	// echo $cursor;
	$deltaPage = $myCustomClient->getDelta($cursor,$pathPrefix);
	$numAdds = 0;
	$numRemoves = 0;
	foreach ($deltaPage["entries"] as $entry) {
	    list($lcPath, $metadata) = $entry;
	    if ($metadata === null) {
	        echo "- $lcPath\n";
	        $numRemoves++;
	    } else {
	        echo "+ $lcPath\n";
	        $numAdds++;
	    }
	    $id = explode("/", substr($lcPath, strlen($pathPrefix)+1, 4))[0]; //create array separate by *
		echo $id;
	}
	file_put_contents($cursortxt,$deltaPage["cursor"]);

	if($numAdds+$numRemoves>0){
		 header('Location: lib/ajax/'.$url.'.php?id='.$id);
	}
}


function process_user(){
	#'''Call /delta for the given user ID and process any changes.'''
// creation d'un client dropbox 
	include("lib/dropboxAPI.php");
	$myCustomClient = new dbx\Client($accessToken, $clientIdentifier);

	//Articles
 	$pathPrefix="/Chargements appareil photo/ArticleTdm";
 	$cursortxt = "lib/cursor.txt";
 	$url="url";
 	delta($myCustomClient ,$cursortxt, $url, $pathPrefix);

 	//Challenge
	$pathPrefix="/Chargements appareil photo/ChallengeTdm";
 	$cursortxt = "lib/cursorC.txt";
 	$url="challenge_update";
	delta($myCustomClient , $cursortxt, $url, $pathPrefix);
}

if(isset($_GET['challenge'])){
	verify();
}elseif (isset(getallheaders()['X-Dropbox-Signature'])) {
	webhook();
}else {
	process_user();
}
?>

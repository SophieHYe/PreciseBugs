<?php 
$root = $_SERVER['DOCUMENT_ROOT']."/sigeprosi/";
include_once $root."class/class.fachadainterfaz.php";
include_once $root."class/class.Solicitud.php";

if (isset($_POST["email"]) && isset($_POST["numSol"])){
	$solicitud = new solicitud($_POST["numSol"],null,null,null,null,null,null,null,null);
	
	if ($solicitud->autocompletar()==0)
		header("Location:../principal.php?content=consultaSolicitudExitosa&nro=".$_POST["numSol"]."&email=".$_POST["email"]);
    else{
		header("Location:../principal.php?content=gestionarSolicitud&error=noExiste");
    }
    
}
?>

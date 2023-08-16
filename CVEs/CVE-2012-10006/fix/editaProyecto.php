<?php 
/*if ($_SERVER['SERVER_ADDR'] == "127.0.0.1")
                  $root = $_SERVER['DOCUMENT_ROOT']."/sigeprosi";
          else
                  $root = "/home/ps6116-02/public_html/sigeprosi";
*/
$root = $_SERVER['DOCUMENT_ROOT']."/sigeprosi";
include_once $root."class/class.fachadainterfaz.php";
require_once "../aspectos/Seguridad.php";
$seguridad = Seguridad::getInstance();
$seguridad->escapeSQL($_POST);
	$fachada = fachadaInterfaz::getInstance();
	$matriz=$fachada->buscarProyecto($_POST['nombreProy']);
	session_start();
	$_SESSION['proyecto'] = $matriz[0];
	$_SESSION['clientes'] = $fachada->buscarClientes($_POST['nombreProy']);
	$_SESSION['profesores'] = $fachada->buscarProfes($_POST['nombreProy']);
	header("Location:../principal.php?content=editaProyecto");
	//header("Location:".$root."principal.php?content=consultaSolicitudExitosa");
	//print_r ($telefonos);

?>

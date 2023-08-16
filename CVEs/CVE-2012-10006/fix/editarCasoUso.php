<?php
include "../class/class.CasoDeUso.php";
require_once "../aspectos/Seguridad.php";
$seguridad = Seguridad::getInstance();
$seguridad->escapeSQL($_POST);
$idcu = $_POST["idcu"];
$nombre = $_POST["nombre"];
$descripcion = $_POST["descripcion"];
$completitud = $_POST["completitud"];
$idEquipo =  $_POST["idequipo"];

if (isset($idcu)) {
	$cu = new casodeuso($nombre,$descripcion,$completitud,$idEquipo);
    if (($cu->actualizar($idcu)) == 0){
		echo '<script>';
		echo 'alert("Se modific\u00f3 satisfactoriamente el Caso de Uso.");';
		echo 'location.href="../principal.php?content=gestionarCasodeuso"';
		echo '</script>'; 
	}else{
		echo '<script>';
		echo 'alert("Error: No se pudo modificar los datos del Caso de Uso.");';
		echo 'location.href="../principal.php"';
		echo '</script>';
	}
	
	//$cu -> actualizar($cu->get('correoUSB'));
	
} 
?>

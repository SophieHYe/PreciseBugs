<?php 
	include_once "../class/class.fachadainterfaz.php";
	$fachada = fachadaInterfaz::getInstance();
	$fachada->registrarCoordinador($_POST["estudiantes"]);
	echo '<script>';
	echo 'alert("El coordinador fue agregado exitosamente.");';
	echo 'location.href="../principal.php?content=gestionarEquipo"';
	echo '</script>';
?>
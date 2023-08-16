<?php 
	include_once "../class/class.fachadainterfaz.php";
	$fachada = fachadaInterfaz::getInstance();
	
	if(($fachada->registrarPlanificacion($_POST["planificacion_name"],$_POST["numPlanif"],$_POST["semana"],$_POST["fecha"],$_POST["puntos"],$_POST["descripcion"],$_POST["nombreAct"]))==0){
		echo '<script>';
		echo 'alert("La planificacion fue creada exitosamente");';
		echo 'location.href="../principal.php?content=registroPlanificacion"';
		echo '</script>';
	}else{
		echo '<script>';
		echo 'alert("Error: Ya existia una planificacion con el nombre y numero introducido.");';
		echo 'location.href="../principal.php?content=registroPlanificacion"';
		echo '</script>';
	}
?>
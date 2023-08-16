<?php 
	include_once "../class/class.fachadainterfaz.php";
    require_once "../aspectos/Seguridad.php";
    $seguridad = Seguridad::getInstance();
    $seguridad->escapeSQL($_POST);
	$fachada = fachadaInterfaz::getInstance();
	$cod=$_POST["codigo"];
	$num=$_POST["tlf"];
	$telefonos=array();
	$i=0;
	foreach ($cod as $codigo){
		$telefonos[$i]=$cod[$i]."".$num[$i];
		$i++;
	}
	if(($fachada->registrarProyecto(1,$_POST["nombreProy"],$_POST["etapa"],$_POST["solicitud"],$_POST["nombre"],$_POST["apellido"],$_POST["email"],$telefonos,$_POST["rol"],$_POST["profesores"]))==0){
		echo '<script>';
		echo 'alert("El proyecto fue creado exitosamente");';
		echo 'location.href="../principal.php?content=gestionarProyecto"';
		echo '</script>';
	}else{
		echo '<script>';
		echo 'alert("Error: Ya existia un proyecto con el nombre introducido.");';
		echo 'location.href="../principal.php?content=registroProyecto"';
		echo '</script>';
	}
?>
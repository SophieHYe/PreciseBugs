<?php
   include_once "../class/class.fachadainterfaz.php";
	$fachada = fachadaInterfaz::getInstance();
	if($fachada->editarElemento($_POST["catalogo"],$_POST["nombre"],$_POST["catold"],$_POST["nomold"])==0){
	  echo '<script>';
	  echo 'location.href="../principal.php?content=gestionarCatalogo";';
		echo 'alert("El elemento fue editado exitosamente");';
		echo '</script>';
	}else{
		echo '<script>';
		echo 'alert("Error en la actualizacion del elemento");';
	  echo 'location.href="../principal.php?content=gestionarCatalogo";';
	  echo '</script>';
	}


?>

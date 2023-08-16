<?php
   include_once "../class/class.fachadainterfaz.php";
	$fachada = fachadaInterfaz::getInstance();
	if($fachada->eliminarElemento($_GET["catalogo"],$_GET["nombre"])==0){
	  echo '<script>';
	  echo 'location.href="../principal.php?content=gestionarCatalogo";';
		echo 'alert("El elemento fue eliminado exitosamente");';
		echo '</script>';
	}else{
		echo '<script>';
		echo 'alert("Error en la eliminacion del elemento");';
	  echo 'location.href="../principal.php?content=gestionarCatalogo";';
	  echo '</script>';
	}


?>

<?php 
require_once "../aspectos/Seguridad.php";
$seguridad = Seguridad::getInstance();
$seguridad->escapeSQL($_POST);
include "../class/class.fachadainterfaz.php";
$nro = $_POST["nro"];
if (isset($nro)) {
	$solicitud = new solicitud($nro,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
                 $solicitud-> autocompletar();
				 $solicitud -> set ("planteamiento",$_POST["planteamiento"]);
				 $solicitud -> set ("justificacion",$_POST["justificacion"]);
				 $solicitud -> set ("tiempo",$_POST["tiempolibre"]);
				 $solicitud -> set ("tecnologia", $_POST["recursos"]);
				 $solicitud -> set ("nroAfectados", $_POST["personas"]);
				 $solicitud -> set ("nombreUnidadAdministrativa", $_POST["department"]);
                 if (isset($_POST["group1"]))
                    $solicitud -> set ("estado", $_POST["group1"]);
	$solicitud -> actualizar($nro);
	$tel = $_POST["tlf"];
    $area = $_POST["codigo"];
	$areavieja = $_POST["codvi"];
	$telv = $_POST["telvi"];
	$i = 0;
	$j = sizeof($tel);
	while( $i < $j) {
		$telsol = new telefonosolicitud($nro,$area[$i].$tel[$i]);
		$telviejo = new telefonosolicitud($nro,$areavieja[$i].$telv[$i]); 
		if($telsol->actualizar($telviejo) != 0) {
		echo "Error actualizando el numero de telefono";
		}
		$i++;
	}
   echo '<script>';
	echo 'alert("La solicitud fue actualizada");';
   echo '</script>';
   header("Location: ../principal.php?content=gestionarSolicitud");
} 
?>

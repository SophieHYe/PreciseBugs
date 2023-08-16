<?php 
	include_once "../class/class.iteracion.php";
	include_once "../class/class.ActividadIteracion.php";
	include_once "../class/class.EsRecurso.php";
	include_once "../class/class.perteneceIteracion.php";
	include_once "../class/class.casoDeUso.php";
	include_once "../class/class.productoextraiteracion.php";
	include_once "../class/class.criteriosPEI.php";
	include_once "../class/class.criterioscasodeuso.php";
	include_once "../class/class.artefactosIteracion.php";
    require_once "../aspectos/Seguridad.php";
    $seguridad = Seguridad::getInstance();
    $seguridad->escapeSQL($_POST);
	$estatus=0;
	if(isset($_POST["estatus"])){
		$estatus=$_POST["estatus"];
		if ($estatus == "Planificada")	$estatus=0;
		else	if ($estatus == "Aprobada")	$estatus=1;
		else	$estatus=2;
	}
	$registro = new iteracion($_POST["nombreIter"],$_POST["tipoIteracion"],$_POST["objetivos"],$_POST["equipo"],$estatus);
	if($registro->actualizar($_POST["nombreIterA"])==0){
		$artefacto = new artefactosIteracion($_POST["nombreIterA"],null);
		$artefacto->eliminar();
		if (isset($_POST["artefactos"])){			
			$artefactos=$_POST["artefactos"];
			$i = 0;
			$j = sizeof($artefactos);
			while( $i < $j) {
				$artefacto = new artefactosIteracion($_POST["nombreIterA"],$artefactos[$i]);
				if($artefacto->insertar() != 0) {
					echo '<script>';
					echo 'alert("Error: No se pudo agregar artefacto a iteracion");';
					echo '</script>';
				}
				$i++;
			}
		}
		$descripciones=$_POST["descripcion"];
		$fechasInicio=$_POST["fechaInicio"];
		$fechasFin=$_POST["fechaFin"];
		$nombre=$_POST["nombreAct"];
		$ids=$_POST["nombreActA"];
		if (isset($_POST["criteriosPE"])) $cpei=$_POST["criteriosPE"];
		$i = 0;
		$j = sizeof($descripciones);
		while( $i < $j) {
			$actividad = new ActividadIteracion($_POST["nombreIterA"],$nombre[$i],$descripciones[$i],$fechasInicio[$i],$fechasFin[$i]);
			if($actividad->actualizar($ids[$i]) != 0) {
				echo '<script>';
					echo 'alert("Error: Ya existia una actividad con \nestas caracteristicas: \nNombre : '.$nombre[$i].'\nFecha Inicio:'.$fechasInicio[$i].'\n Fecha Fin: '.$fechasFin[$i].'");';
				echo '</script>';
			}
			/*
			$actividad->autocompletar();
			$idActividad=$actividad->get('id');
			$postName="estudiantes-".($i+1);
			$estudiantes=$_POST[$postName];
			$nEstudiantes= sizeof($estudiantes);
			$k=0;
			while( $k < $nEstudiantes){
				$e=new EsRecurso($estudiantes[$k],$idActividad);
				if($e->insertar() != 0){
					echo '<script>';
					echo 'alert("Error: Al agregar estudiante a actividad");';
				echo '</script>';
				}
				$k++;
			}*/
			$i++;
		}

		echo '<script>';
		echo 'alert("La iteracion fue actualizada exitosamente");';
		echo 'location.href="../principal.php"';
		echo '</script>';
	}else{
		echo '<script>';
		echo 'alert("Error: Ya existia una iteracion con el nombre  introducido.");';
		echo 'history.back();';
		echo '</script>';
	}
	/*
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
	}*/
?>
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
	$registro = new iteracion($_POST["nombreIter"],$_POST["tipoIteracion"],$_POST["objetivos"],$_POST["equipo"],0);
	if($registro->insertar()==0){
		$registro->autocompletar();
		$idIteracion=$registro->get('id');
		$descripciones=$_POST["descripcion"];
		$fechasInicio=$_POST["fechaInicio"];
		$fechasFin=$_POST["fechaFin"];
		$nombre=$_POST["nombreAct"];
		if (isset($_POST["criteriosPE"])) $cpei=$_POST["criteriosPE"];
		$i = 0;
		$j = sizeof($descripciones);
		while( $i < $j) {
			$actividad = new ActividadIteracion($idIteracion,$nombre[$i],$descripciones[$i],$fechasInicio[$i],$fechasFin[$i]);
			if($actividad->insertar() != 0) {
				echo '<script>';
					echo 'alert("Error: Ya existia una actividad con \nestas caracteristicas: \nNombre : '.$nombre[$i].'\nFecha Inicio:'.$fechasInicio[$i].'\n Fecha Fin: '.$fechasFin[$i].'");';
				echo '</script>';
			}
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
			}
			$i++;
		}
		if (isset($_POST["CU"])){			
			$casosDeUso=$_POST["CU"];
			$criterios=$_POST["criterios"];
			$i = 0;
			$j = sizeof($casosDeUso);
			while( $i < $j) {
				$casoDeUso= new casoDeUso($casosDeUso[$i],null,null,$_POST["equipo"]);
				$casoDeUso->autocompletar();
				$idCasoDeUso=$casoDeUso->get('id');
				//echo '['.$idCasoDeUso.','.$idIteracion.']';
				$p = new perteneceIteracion($idCasoDeUso,$idIteracion);
				if($p->insertar() != 0) {
					echo '<script>';
					echo 'alert("Error: Ya existia caso de uso : '.$casosDeUso[$i].' asociado a iteracion");';
					echo '</script>';
				}
				$criterio= new criterioscasodeuso($idCasoDeUso,$criterios[$i]);
				if($criterio->insertar() != 0) {
					echo '<script>';
					echo 'alert("Error:  No se pudo agregar criterio de caso de uso :"'.$casosDeUso[$i].');';
					echo '</script>';
				}
				$i++;
			}
		}
		if (isset($_POST["artefactos"])){			
			$artefactos=$_POST["artefactos"];
			$i = 0;
			$j = sizeof($artefactos);
			while( $i < $j) {
				$artefacto = new artefactosIteracion($idIteracion,$artefactos[$i]);
				if($artefacto->insertar() != 0) {
					echo '<script>';
					echo 'alert("Error: No se pudo agregar artefacto a iteracion");';
					echo '</script>';
				}
				$i++;
			}
		}
		if (isset($_POST["PE"])){
			$PE=$_POST["PE"];
			$i = 0;
			$j = sizeof($PE);
			$textPE=$_POST["textPE"];
			$ccu=$_POST["criteriosPE"];
			while( $i < $j) {
				$pe= new productoextraiteracion($idIteracion,$PE[$i],$textPE[$i]);
				if($pe->insertar() != 0) {
					echo '<script>';
					echo 'alert("Error:  No se pudo agregar producto extra :"'.$PE[$i].');';
					echo '</script>';
				}else{
					$pe->autocompletar();
					$idPE=$pe->get('id');
					$cri=new criteriosPEI($idPE,$ccu[$i]);
					if($cri->insertar() != 0) {
						echo '<script>';
						echo 'alert("Error:  No se pudo agregar criterio a producto extra :"'.$PE[$i].');';
						echo '</script>';
					}
				}
				$i++;
			}
            /*
            $i = 0;
            $j = sizeof($descripciones);
            while( $i < $j) {
                $actividad = new ActividadIteracion($idIteracion,$nombre[$i],$descripciones[$i],$fechasInicio[$i],$fechasFin[$i]);
                if($actividad->insertar() != 0) {
                    echo 'Error al agregar actividad';
                }
                $i++;
            }
            */
		}
		echo '<script>';
		echo 'alert("La iteracion fue creada exitosamente");';
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
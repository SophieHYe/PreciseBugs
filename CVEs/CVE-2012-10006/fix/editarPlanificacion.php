<?php 
	include_once "../class/class.fachadainterfaz.php";
    require_once "../aspectos/Seguridad.php";
    $seguridad = Seguridad::getInstance();
    $seguridad->escapeSQL($_POST);
	$fachada = fachadaInterfaz::getInstance();
	$p=$_POST["planificacion_name"];
	$n=$_POST["numPlanif"];
	$pV=$_POST["planificacion_name_V"];
	$nV=$_POST["numPlanif_V"];	
	$etapa = new etapa($n,$p);
	$etapaV = new etapa($nV,$pV);
	$etapaV -> autocompletar();
	$idEtapa=$etapaV->get('id');
	
	if (($etapa ->actualizar($idEtapa)) ==0){ 
		$etapa -> autocompletar();
		//$idEtapa=$etapa->get('id');
		$s=$_POST["semana"];
		$f=$_POST["fecha"];
		$ptos=$_POST["puntos"];
		$d=$_POST["descripcion"];
		$ids=$_POST["id"];
		$ns=$_POST["nombreAct"];
		$idPrincipal=$ids[0];
		//$booleano;
		$actividad =new actividad($s[0],$f[0],$d[0],$ptos[0],$idEtapa,$ns[0]);
		$booleano=(($actividad -> actualizar($idPrincipal))!=0);
		
		$i=1;
		while ($i < sizeof($ids)){
			$id=$ids[$i];
			if($id != $idPrincipal){
				$actividad =new actividad($s[$i],$f[$i],$d[$i],$ptos[$i],$idEtapa,$ns[$i]);
				$booleano=(($actividad -> actualizar($ids[$i]))!=0);
			}else{
				$actividad = new actividad($s[$i],$f[$i],$d[$i],$ptos[$i],$idEtapa,$ns[$i]);
				$booleano=(($actividad->insertar()) != 0);
			}
			$i++;
		}	
		if(!$booleano){
			echo '<script>';
			echo 'alert("La planificaci\u00f3n fue modificada exitosamente.");';
			echo 'location.href="../principal.php?content=registroPlanificacion"';
			echo '</script>';
		}else{
			echo '<script>';
			echo 'alert("Error: Ocurri\u00f3n un error durante la actualizaci\u00f3n de alguna actividad.");';
			echo 'location.href="../principal.php?content=registroPlanificacion"';
			echo '</script>';
		}
	}else{
		echo '<script>';
		echo 'alert("Error: Ya existia una planificaci\u00f3n con el nombre y numero introducido.");';
		echo 'location.href="../principal.php?content=registroPlanificacion"';
		echo '</script>';
	}
?>
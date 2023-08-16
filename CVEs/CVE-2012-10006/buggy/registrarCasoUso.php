<?php 
	/*	UNIVERSIDAD SIMON BOLIVAR
		PERIODO:			ENE-MAR 2012
		MATERIA: 			SISTEMAS DE INFORMACION II
		NOMBRE DEL ARCHIVO:	registrarCasoUso.php
	*/
    //$root = $_SERVER['DOCUMENT_ROOT']."/sigeprosi/";
    include_once "../class/class.CasoDeUso.php";
    //include_once "../class/class.listaCasoUso.php";
	$nombre = $_POST["nombre"];
    $descripcion = $_POST["descripcion"];
	$completitud = 0;
	$idEquipo = $_POST["idequipo"];
	$registro = new casodeuso($nombre,$descripcion,$completitud,$idEquipo);
	if ($registro->insertar()==0){
		echo '<script>';
		echo 'alert("El caso de uso ha sido registrado exitosamente.");';
		echo 'location.href="../principal.php?content=gestionarCasodeuso"';
		echo '</script>';	
	}else{
		echo '<script>';
		echo 'alert("Ha ocurrido un error durante la creacion del caso de uso.\n\
		Por favor comuniquese con el administrador del sistema.");';
		echo 'location.href="../principal.php"';
		echo '</script>';
	}    
?>

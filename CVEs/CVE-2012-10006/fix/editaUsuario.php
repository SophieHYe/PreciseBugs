<?php 
include "../class/class.fachadainterfaz.php";
require_once "../aspectos/Seguridad.php";
$seguridad = Seguridad::getInstance();
$seguridad->escapeSQL($_POST);
$email = $_POST["email"];
if (isset($email)) {
	$user = new Usuario(null,null,$email,null,null, null,null,null);
    $user->autocompletar();
	$user -> set ("nombre",$_POST["nombre"]);
    $user -> set ("apellido",$_POST["apellido"]);
    if (isset($_POST["correoOpt"])) $user -> set ("correoOpcional",$_POST["correoOpt"]);
    if (isset($_POST["cedula"])) $user -> set ("carnetOCedula",$_POST["cedula"]);
    if (isset($_POST["codigo"]) && isset($_POST["tlf"]))	$user -> set ("telefono",$_POST["codigo"].$_POST["tlf"]);
    if (isset($_POST["rol"])) $user -> set ("rol",$_POST["rol"]);
    if (isset($_POST["group1"])) $user -> set ("activo", $_POST["group1"]);
	$user -> actualizar($user->get('correoUSB'));
	echo '<script>';
	echo 'alert("Los datos de usuario han sido actualizados satisfactoriamente.");';
	echo 'location.href="../principal.php?content=gestionarUsuario"';
	echo '</script>';
} 
?>

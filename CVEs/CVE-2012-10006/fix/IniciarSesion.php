<?php
/*if ($_SERVER['SERVER_ADDR'] == "127.0.0.1")
                  $root = $_SERVER['DOCUMENT_ROOT']."/sigeprosi";
          else
                  $root = "/home/ps6116-02/public_html/sigeprosi";
*/
$root = $_SERVER['DOCUMENT_ROOT']."/sigeprosi";
include_once $root."/class/class.Usuario.php";
include_once $root."/snippets/generarSal.php";
include_once $root."/class/class.Encrypter.php";
include_once $root."/class/class.fachadainterfaz.php";
require_once "../aspectos/Seguridad.php";
$seguridad = Seguridad::getInstance();
$seguridad->escapeSQL($_POST);
if (isset($_POST["user"])) {
	$user = $_POST["user"];
	if (strpos($user, '@') === false) $user = $user.'@usb.ve';
	echo 'Usuario: '.$user;
    $enc = new Encrypter($_POST["pass"], generarSal($user));
    $codigo = $enc->toMD5();
	$u = new Usuario(null,null,$user,$codigo,null,null,null,null);
    if ($u->autocompletar() != 0)	header("Location: ../principal.php?content=inicio&error=noRegistrado");
	else if ($u->get('password') != $codigo)	header("Location: ../principal.php?content=inicio&error=errorPass");
	else if ($u->get('activo')!=1) header("Location: ../principal.php?content=inicio&error=noActivo");
	else {
		session_start();
		$_SESSION["correoUSB"]=$u->get("correoUSB");
		$_SESSION["nombre"] = $u->get("nombre");
		$_SESSION["apellido"] = $u->get("apellido");
		$_SESSION["admin"] = (($u->get("rol")) == 0) || (($u->get("rol")) == 1);
		$_SESSION["profesor"] = (($u->get("rol")) == 2) || (($u->get("rol")) == 1);
		$_SESSION["estudiante"] = (($u->get("rol")) == 3) || (($u->get("rol")) == 5);
		$_SESSION["coordinador"] = (($u->get("rol")) == 5);
		if ($_SESSION["coordinador"]){
			$fachada = fachadaInterfaz::getInstance();
			$_SESSION["Equipo"]=$fachada->buscarEquipoDeEstudiante($_SESSION["correoUSB"]);
		}
		$_SESSION["cliente"] = (($u->get("rol")) == 4);
		$_SESSION['autenticado'] = true;
		header("Location: ../principal.php?content=inicio");
	}
}
?>

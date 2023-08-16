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
if (isset($_POST["email"])){
    require_once "../aspectos/Seguridad.php";
    $seguridad = Seguridad::getInstance();
    $seguridad->escapeSQL($_POST);
	$email = strtolower($_POST["email"]);
	$numero = rand().rand();
	$codigo = dechex($numero);
	$enc = new Encrypter($codigo, generarSal($_POST["email"]));
	$registro = new Usuario(null,null,$_POST["email"],$enc->toMD5(),null, 1,$_POST["privilegio"],null);
	if ($registro->insertar() == 0){
		echo '<script>';
		echo 'alert("Usuario registrado exitosamente.");';
		echo 'location.href="../principal.php?content=registroUsuario"';
		echo '</script>';
	}else{
		echo '<script>';
		echo 'alert("Ya existia un usuario registrado bajo ese correo USB.");';
		echo 'location.href="../principal.php?content=registroUsuario"';
		echo '</script>';	
	}
}
?>

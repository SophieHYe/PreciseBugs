<?PHP
if($_POST['oper']=='edit')
{
    require_once "../aspectos/Seguridad.php";
    $seguridad = Seguridad::getInstance();
    $seguridad->escapeSQL($_POST);
    $id     = $_POST['id'];
    $nota   = $_POST['nota'];
    require_once "../class/class.Calificacion.php";
    $calif = new Calificacion(null,null,$nota);
    $calif->set('id',$id);
    $calif->salvar();
}
?>
<?PHP
    require_once "../aspectos/Seguridad.php";
    $seguridad = Seguridad::getInstance();
    $seguridad->escapeSQL($_GET);
    header("Content-type: text/xml;charset=utf-8");
    echo "<?xml version='1.0' encoding='utf-8' ?>";
    $page = $_GET['page'];
    $limit = $_GET['rows'];
    $sidx = $_GET['sidx'];
    if ($sidx == "invid")
        $sidx = "correoUSB";
    $sord = $_GET['sord'];
    require_once "../class/class.listaUsuarios.php";
    $total_pages = 1;
    $start = ($page - 1)*$limit;
    $baseUsuarios = new listaUsuarios();
    $result = $baseUsuarios->buscar(null,null,$sord,$sidx,$start,$limit);
    $N = sizeof($result);
    $count = $N;
    echo "<rows>";
    echo "<page>".$page."</page>";
    echo "<total>".$total_pages."</total>";
    echo "<records>".$count."</records>";
    for ($i=0; $i<$N; $i++)
    {
        $row = $result[$i];
        echo "<row id='".$i."'>";
        echo "<cell>". $row['correoUSB']."</cell>";
        echo "<cell><![CDATA[". $row['nombre']."]]></cell>";
        echo "<cell><![CDATA[". $row['apellido']."]]></cell>";
        echo "<cell>";
        if ($row['activo'] == 0)
            echo "Inactivo";
        else 
            echo "Activo";
        echo "</cell>";
        echo "<cell>";
        if ($row['rol'] == 0)
            echo "Administrador";
        else if ($row['rol'] == 2)
            echo "Profesor";
        else if ($row['rol'] == 4)
            echo "Cliente";
		else if ($row['rol'] == 5)
            echo "Estudiante/Coordinador";
        else if ($row['rol'] == 3)
            echo "Estudiante";
			else if ($row['rol'] == 1)
            echo "Administrador/Profesor";
        echo "</cell>";
        echo "</row>";
    }
    echo "</rows>";
?>
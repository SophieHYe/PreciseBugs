<?PHP
    header("Content-type: text/xml;charset=utf-8");
    echo "<?xml version='1.0' encoding='utf-8' ?>";
    $page = $_GET['page'];
    $limit = $_GET['rows'];
    $sidx = $_GET['sidx'];
    if ($sidx == "invid")
        $sidx = "nombre";
    $sord = $_GET['sord'];
    require_once "../class/class.listaProyecto.php";
    $total_pages = 1;
    $start = ($page - 1)*$limit;
    $baseProy = new listaProyecto();
    $result = $baseProy->cargar($sord,$sidx,$start,$limit);
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
        echo "<cell><![CDATA[". $row['nombre']."]]></cell>";
        echo "<cell><![CDATA[". $row['numeroSolicitud']."]]></cell>";
        echo "<cell>";
        if ($row['estado'] == 0)
            echo "Inactivo";
        else if ($row['estado'] == 1)
            echo "Activo";
        else if ($row['estado'] == 2)
            echo "Finalizado";
        else
            echo "Implantado";
        echo "</cell>";
        echo "<cell><![CDATA[". $row['etapaNombre']."]]></cell>";
        echo "</row>";
    }
    echo "</rows>";
?>
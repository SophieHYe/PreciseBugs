<?PHP
    header("Content-type: text/xml;charset=utf-8");
    echo "<?xml version='1.0' encoding='utf-8' ?>";
    $page = $_GET['page'];
    $limit = $_GET['rows'];
    $sidx = $_GET['sidx'];
    if ($sidx == "invid")
        $sidx = "nro";
    $sord = $_GET['sord'];
    require_once "../class/class.listaSolicitud.php";
    $start = ($page - 1)*$limit;
    $baseSolicitudes = new listaSolicitud();
    $result = $baseSolicitudes->buscarTodas($sord,$sidx,$start,$limit);
    $N = sizeof($result);
    $count = $N;
    $total_pages = ceil($N/$limit) + 1;
    echo "<rows>";
    echo "<page>".$page."</page>";
    echo "<total>".$total_pages."</total>";
    echo "<records>".$count."</records>";
    for ($i=0; $i<$N; $i++)
    {
        $row = $result[$i];
        echo "<row id='".$row['nro']."'>";
        echo "<cell>". $row['nro']."</cell>";
        echo "<cell><![CDATA[". $row['nombreUnidadAdministrativa']."]]></cell>";
        echo "<cell>". $row['email']."</cell>";
        $estado = $row['estado'];
        echo "<cell>";
        if ($estado == 0)
            echo "Pendiente";
        else if ($estado == 1)
            echo "Aceptada";
        else if ($estado == 2)
            echo "Aprobada";
        else
            echo "Rechazada";
        echo "</cell>";
        echo "</row>";
    }
    echo "</rows>";
?>
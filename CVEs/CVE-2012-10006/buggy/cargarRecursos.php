<?PHP
    header("Content-type: text/xml;charset=utf-8");
    echo "<?xml version='1.0' encoding='utf-8' ?>";
    $id = $_GET['id'];
    $page = $_GET['page'];
    $limit = $_GET['rows'];
    $sidx = $_GET['sidx'];
    if ($sidx == "invid")
        $sidx = "nombre";
    $sord = $_GET['sord'];
    require_once "../class/class.listaRecursos.php";
    $total_pages = 1;
    $start = ($page - 1)*$limit;
    $baseAct = new listaRecursos();
    $result = $baseAct->cargar($id,$sord,$sidx,$start,$limit);
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
        echo "<cell><![CDATA[".$row['correoUSB']."]]></cell>";
        echo "<cell><![CDATA[".$row['nombre']."]]></cell>";
        echo "<cell><![CDATA[".$row['apellido']."]]></cell>";
        echo "</row>";
    }
    echo "</rows>";
?>
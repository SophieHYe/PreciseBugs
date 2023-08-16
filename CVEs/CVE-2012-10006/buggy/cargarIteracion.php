<?PHP
    header("Content-type: text/xml;charset=utf-8");
    echo "<?xml version='1.0' encoding='utf-8' ?>";
    $equipo = $_GET["equipo"];
    $page = $_GET['page'];
    $limit = $_GET['rows'];
    $sidx = $_GET['sidx'];
    if ($sidx == "invid")
        $sidx = "nombre";
    $sord = $_GET['sord'];
    require_once "../class/class.listaIteracion.php";
    $total_pages = 1;
    $start = ($page - 1)*$limit;
    $baseAct = new listaIteracion();
    $result = $baseAct->cargar($equipo,$sord,$sidx,$start,$limit);
    $N = sizeof($result);
    $count = $N;
    echo "<rows>";
    echo "<page>".$page."</page>";
    echo "<total>".$total_pages."</total>";
    echo "<records>".$count."</records>";
    for ($i=0; $i<$N; $i++)
    {
        $row = $result[$i];
        echo "<row id='".$row['id']."'>";
        echo "<cell>".$row['id']."</cell>";
        echo "<cell>".$row['nombre']."</cell>";
        echo "<cell>".$row['tipo']."</cell>";
        // echo "<cell>";
        // switch ($row['tipo']) {
            // case 0: 
                // echo "Iniciaci&oacute;n";
                // break;
            // case 1:
                // echo "Elaboraci&oacute;n";
                // break;
            // case 2:
                // echo "Construcci&oacute;n";
                // break;
            // case 3:
                // echo "Transici&oacute;n";
                // break;
            // default:
                // echo "Desconocido";
                // break;
        // }
        // echo "</cell>";
        echo "<cell>";
        switch ($row['estado']) {
            case 0: 
                echo "Planificada";
                break;
            case 1:
                echo "Aprobada";
                break;
            case 2:
                echo "Iniciada";
                break;
            case 3:
                echo "Finalizada";
                break;
            default:
                echo "Desconocido";
                break;
        }
        echo "</cell>";
        echo "</row>";
    }
    echo "</rows>";
?>
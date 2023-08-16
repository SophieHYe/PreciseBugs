<?PHP
    header("Content-type: text/xml;charset=utf-8");
    echo "<?xml version='1.0' encoding='utf-8' ?>";
    $page = $_GET['page'];
    $limit = $_GET['rows'];
    $sidx = $_GET['sidx'];
    if ($sidx == "invid")
        $sidx = "correoUSBUsuario";
	else if($sidx == "correoUSB")	$sidx = "correoUSBUsuario";
    $sord = $_GET['sord'];
    require_once "../class/class.Equipo.php";
    $total_pages = 1;
    $start = ($page - 1)*$limit;
    $E = new Equipo($_GET["Equipo"],null);
    $result = $E->EstudiantesGrid($sord,$sidx,$start,$limit);
    $N = sizeof($result);
    $count = $N;
    echo "<rows>";
    echo "<page>".$page."</page>";
    echo "<total>".$total_pages."</total>";
    echo "<records>".$count."</records>";
    for ($i=0; $i<$N; $i++){
        $row = $result[$i];
        echo "<row id='".$i."'>";
        echo "<cell>".$row['correoUSB']."</cell>";
		 echo "<cell>".$row['nombre']."</cell>";
        echo "<cell>".$row['apellido']."</cell>";
		//echo "<row id='pertenece'>";
        echo "<cell>No</cell>";
        echo "</row>";
    }
    echo "</rows>";
?>
<?PHP
    header("Content-type: text/xml;charset=utf-8");
    echo "<?xml version='1.0' encoding='utf-8' ?>";
	include_once "../class/class.fachadainterfaz.php";
	$fachada = fachadaInterfaz::getInstance();
    $result = $fachada->buscarProfes($_GET['nombreProyecto']);
    $N = sizeof($result);
    $count = $N;
    echo "<rows>";
    echo "<page>1</page>";
    echo "<total>1</total>";
    echo "<records>".$count."</records>";
    for ($i=0; $i<$N; $i++){   
		$row = $result[$i];
        echo "<row id='".$i."'>";
        echo "<cell>". $row['correoUSB']."</cell>";
        echo "<cell><![CDATA[". $row['nombre']."]]></cell>";
        echo "<cell><![CDATA[". $row['apellido']."]]></cell>";
        echo "</row>";
    }
    echo "</rows>";
?>
<meta http-equiv="X-UA-Compatible" content="IE=EDGE" />
<html>
<head>

<style>
@import "Base.css";
</style>
</head>

<body>
<script type="text/javascript" src="PS_Info.js" ></script>
<script type="text/javascript" src="Fan_Info.js" ></script>
<script type="text/javascript" src="Temp_Info.js" ></script>
<script type="text/javascript" src="Storage_Info.js" ></script>
<script type="text/javascript" src="Memory_Info.js" ></script>
<script type="text/javascript" src="CPU_Info.js" ></script>
<script type="text/javascript" src="Network_Info.js" ></script>

<script language="JavaScript" type="text/javascript" >

<?php
        $ip=$_GET["ip"];
        $comm=$_GET["comm"];
        $id=$_GET["id"];
        $cmd="./nagios_hpeilo_engine -H $ip -C $comm -o $id -J";
        $ret = exec($cmd,$output);
        echo join("\n",$output);
?>

if (jData != "") {
	TableProcessing(serviceID);
}
else {
	NojsonData(serviceID);
}

function TableProcessing (value) {
    switch (value) {
    case 1:
		PS_Table();
        break;
    case 2:
		Fan_Table();
        break;
    case 3:
        Temp_Table();
        break;
    case 4:
        Storage_Table();
        break;
    case 5:
        Memory_Table();
        break;
    case 6:
        CPU_Table();
        break;
    case 7:
        Network_Table();
        break;
	default:  
		document.write("<h1>Some thing Went Wrong...</h1>");
    }
}

function NojsonData (value) {
    switch (value) {
    case 1:
		document.write("<h1>Power Supply Info Data is not available</h1>");
        break;
    case 2:
		document.write("<h1>Fan Info Data is not available</h1>");
        break;
    case 3:
        document.write("<h1>Temperature Info Data is not available</h1>");
        break;
    case 4:
        document.write("<h1>Storage Info Data is not available</h1>");
        break;
    case 5:
        document.write("<h1>Memory Info Data is not available</h1>");
        break;
    case 6:
        document.write("<h1>CPU Info Data is not available</h1>");
        break;
    case 7:
        document.write("<h1>Network Info Data is not available</h1>");
        break;
	default:  
		document.write("<h1>Some thing went Wrong...</h1>");
    }
}

</script>
</body>
</html>

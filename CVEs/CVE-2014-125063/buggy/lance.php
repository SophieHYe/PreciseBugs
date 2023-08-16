<?php 
	// inicia sessão para passar variaveis entre ficheiros php
	include 'includes/dbconnection.php';
	session_start();
	if(!$_SESSION['username'])
		header("Location: login.php");
	else{
		$username = $_SESSION['username'];
		$nif = $_SESSION['nif'];
	}
	// Função para limpar os dados de entrada
	function test_input($data) {
		$data = trim($data);
		$data = stripslashes($data);
		$data = htmlspecialchars($data);
		return $data; 
	}
	// Carregamento das variáveis username e pin do form HTML através do metodo POST;
	if ($_SERVER["REQUEST_METHOD"] == "POST") {
	 $lid = test_input($_POST["lid"]);
	 $lance = test_input($_POST["lance"]);
	 } 

	$ultimolance_query="SELECT MAX(lance.valor) AS max_valor 
	                    FROM lance 
	                    WHERE lance.leilao = $lid";
	$ultimolance = $connection->query($ultimolance_query);
	$teste = false;
	foreach($ultimolance as $row){
		if($row['max_valor'] == ""){
			$teste = true;
			echo("<p>");
			echo("testes");
			echo("<p>");
		}

	}
	$ultimolance = $connection->query($ultimolance_query);

	if($teste == true){
		$valorbase_query="SELECT valorbase AS min_valor 
						  FROM leilaor, leilao 
						  WHERE leilaor.lid = $lid 
						  AND leilaor.dia = leilao.dia 
						  AND leilaor.nrleilaonodia = leilao.nrleilaonodia 
						  AND leilaor.nif = leilao.nif";
		$valorbase = $connection->query($valorbase_query);
		foreach($valorbase as $row){
			$valor_min = $row["min_valor"];
		}
		$valor_max = 0;
	}else{
		foreach($ultimolance as $row1){
			$valor_max = $row1["max_valor"];
		}
		$valor_min = 0;
	}

	if($valor_max < $lance and $valor_min <= $lance){
		$lance_query="INSERT INTO lance(pessoa,leilao,valor) 
					  VALUES ($nif,$lid,$lance)";
		$result = $connection->query($lance_query);
		
		echo($lance_query);
		
		if (!$result) {
	 		echo("<div id='erro'> Não houve lance:($sql) </div>");
		}
	}else{
		echo("<div id='erro'> O valor do lance é inválido </div>");
	}
	?>
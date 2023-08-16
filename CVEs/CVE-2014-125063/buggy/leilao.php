<?php 
	include 'includes/dbconnection.php';
	// inicia sessão para passar variaveis entre ficheiros php
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
	}
	if(!$lid){
		echo("<div id='erro'> Leilão não existe! </div>");
		exit();
	}

	//regista a pessoa no leilão. Exemplificativo apenas.....
	$inscreve_query = "INSERT INTO concorrente (pessoa,leilao) 
				       VALUES ($nif,$lid)";
	$data_now = date("y-m-d");

	/*
	echo("<p>");
	echo($lid);
	echo("</p>");
	*/

	/* PREPARED STATEMENTS */
	$sqlDataLeilao = "SELECT * FROM leilaor 
					  WHERE lid =" . $lid;
	$resultado = $connection->query($sqlDataLeilao);

	/*
	echo("<p>");
	echo($sqlDataLeilao);
	echo("</p>");
	*/


	if (!$resultado) {
		echo("<p> Erro na Query:($sql) </p>");
		exit();
	}

	foreach($resultado as $row){
		$time_leilao = strtotime($row["dia"]) ;
		$dia_abertura = date("y-m-d", $time_leilao);
		$new_dia = date("y-m-d", strtotime("+" . $row["nrdias"] . " days", $time_leilao));
	}

/*
	echo("<p>");
	echo($data_now);
	echo("</p>");
	echo("<p>");
	echo($new_dia);
	echo("</p>");
	echo("<p>");
	$datediff = strtotime($new_dia) - strtotime($data_now);
	echo floor($datediff/(60*60*24));
	echo("</p>");
*/
	$datediff = strtotime($new_dia) - strtotime($data_now);
	if($data_now <= $new_dia){
		if($dia_abertura <= $data_now){
			$inscreve = $connection->query($inscreve_query);
			if (!$inscreve) {
			 	echo("<div id='erro'> Pessoa já se encontra registada neste leilão! </div>");
				exit();
			}
			echo("<div id='erro'> Pessoa ($username), nif ($nif) Registada no leilao ($lid)</div>\n");
		}else{
			echo("<div id='erro'> Leilao comeca no dia $dia_abertura </div>");
		}
		
	}
	else{
		if(!$new_dia){
			echo("<div id='erro'> Leilão não existe! </div>");
			exit();
		}

		$endDay = floor($datediff/(60*60*24));
		echo("<div id='erro'> Leilao acabou no dia $new_dia </div>");
	}

	// to be continued….
	//termina a sessão
	//session_destroy();
	?>
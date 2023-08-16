<?php
include('top.php');
include('variable.php');
?>

<div id="page">
    <div id="indhold">
        <div id="indholdText2">
            <div id="indholdDiv2">
                <div id="indholdLeft">
				
				<!-- Her følger en PHP kode der tager højde for de forskellige input man giver "navn, billet, plads", når man bestiller plads -->
                    <?php

                    $numInput = $_GET['pid'];
					$numInput = stripslashes($numInput);
                    $numInput = mysqli_real_escape_string($numInput);

                    $ticketInput = $_POST[ticketID];
                    $ticketInput = stripcslashes($ticketInput);
                    $ticketInput = mysqli_real_escape_string($ticketInput);

                    $nameInput = $_POST[playernamee];
                    $nameInput = stripcslashes($nameInput);
                    $nameInput = mysqli_real_escape_string($nameInput);

				/* Dette for loop checker, hvor den første ikke reserverede (hvide) plads er, og gør den til $currentTable */
					for ($ii = 1; $ii <= 80; $ii++)
					{
						$color = mysqli_fetch_assoc(mysqli_query($db,"SELECT Color FROM booking WHERE SeatID = $ii"));

						if ($color["Color"] == "White") {$currentTable = $ii; break;}
					}
					
                    if (isset($numInput) && is_numeric($numInput) && $numInput >= 1 && $numInput <= 80 ) {
                        $id = $numInput;

						
						/* Her ses der om pladsen allerede er taget (kigges på farven) */
                        if (isset($ticketInput)) {
															 
							$stmt = $db->prepare("SELECT COUNT(SeatID)
                                                         FROM booking
                                                         WHERE Color='White'
                                                         AND SeatID = ?");
							
							$stmt->bind_param("i", $id);
							
							$stmt->execute();
							
							$isFree = NULL;
							
							$stmt->bind_result ($isFree);
							
							$stmt->fetch();
							
							$stmt->close();
							
							/* Her ses der om ticket-ID'en er gyldig/findes */
                            if ($isFree > 0) {
                                $result = mysqli_query($db, "SELECT TicketID
                                                         FROM ticket
                                                         WHERE TicketID='$ticketInput'");
														 
                                $numResults = mysqli_num_rows($result);
								
								/* Her ses der om der allerede er blevet booket på den plads*/
                                if ($numResults > 0) {
                                    $bookingRes = mysqli_query($db, "SELECT TicketID
                                                                 FROM booking
                                                                 WHERE TicketID='$ticketInput'");
                                    $numBookRes = mysqli_num_rows($bookingRes);
									
									/* SKAL ÆNDRES SÅ MAN RESERVERER EN NY + SLETTER DEN GAMLE */
                                    if ($numBookRes > 0) {
																	
									    if ($nameInput != '' AND $id != 'Choose a seat') {
										
											mysqli_query($db, "UPDATE booking
                                                           SET PlayerName='',
                                                               TicketID='',
                                                               Color='White'
                                                           WHERE TicketID='$ticketInput'");
											
										
                                            mysqli_query($db, "UPDATE booking
                                                           SET PlayerName='$nameInput',
                                                               TicketID='$ticketInput',
                                                               Color='Red'
                                                           WHERE SeatID=$id");
                                            echo "<script type='text/javascript'>alert('You have now booked seat " . $id . " ');</script>";
                                        } else {
                                            echo "<script type='text/javascript'>alert('You have to enter your name.');</script>";
                                        }	/* test */
									
									
									
                                        echo "<script type='text/javascript'>alert('You have already booked a seat for this LAN party with your ticket ID.');</script>";
                                    }
									/* Her reserveres pladsen */
									else {
									
                                        if ($nameInput != '' AND $id != 'Choose a seat') {
                                            mysqli_query($db, "UPDATE booking
                                                           SET PlayerName='$nameInput',
                                                               TicketID='$ticketInput',
                                                               Color='Red'
                                                           WHERE SeatID=$id");
                                            echo "<script type='text/javascript'>alert('You have now booked seat " . $id . " ');</script>";
                                        } else {
                                            echo "<script type='text/javascript'>alert('You have to enter your name.');</script>";
                                        }
                                    }
                                } else {
                                    echo "<script type='text/javascript'>alert('Duuude! Your ticket ID is invalid!');</script>";
                                }
                            } else {
                                echo "<script type='text/javascript'>alert('This seat is already occupied. Choose another, man!');</script>";
                            }
                        }
                    } else {
                        header("location:?pid=$currentTable");
                    }
                    ?>

					
					
<!-- Her indsættes kantine billedet, der er baggrund for reservationssystemet -->

<img src="images/bordplan2.png" id="bordplan" alt="bord" style="position:relative; z-index: 1;">



<!-- Følgende PHP indsætter en "div" på top af billedet på hver enkelt plads fra 1-80. Koordinaterne findes på Databasen, og et loop kører derefter
     alle pladser igennem, og indsætter de mange div's -->
                    <?php
                    for ($i = 1; $i <= 80; $i++) {

                        echo ("<a href='?pid=" . $i . "'>");
                        echo ("<div id='" . $i . "'");
                        echo ("style='");
                        echo ("height:16px;");
                        echo ("width:16px;");
						echo ("z-index: 3;");
                        echo ("margin-top:");
                        $getTop = mysqli_query($db, "SELECT top
                                                    FROM booking
                                                    WHERE SEATID=" . $i);
                        
                        while ($row = mysqli_fetch_array($getTop)) {
                            echo $row['top'];
                        }
                        echo ("px;");
                        echo ("margin-left:");
                        
                        $getLeft = mysqli_query($db, "SELECT mleft
                                                    FROM booking
                                                    WHERE SEATID=" . $i);
                        
                        while ($row = mysqli_fetch_array($getLeft)) {
                            echo $row['mleft'];
                        }
                        echo ("px;"); 
                        echo ("position:absolute;");
                        echo ("background-color:");
                        $getColor = mysqli_query($db, "SELECT color
                                                       FROM booking
                                                       WHERE SEATID=" . $i);

                        while ($row = mysqli_fetch_array($getColor)) {
                            echo $row['color'];
                        }
                        echo (" ' ");
                        echo ("title='");
                        $getName = mysqli_query($db, "SELECT SeatID, PlayerName
                                                                   FROM booking
                                                                   WHERE SEATID=" . $i);

                        while ($row = mysqli_fetch_array($getName)) {
                            echo "Seat " . $row['SeatID'] . ": " . $row['PlayerName'];
                        }
                        echo (" '> ");
						echo ("<p>" . $i . "</p>");
                        echo (" </div> ");
                        echo (" </a> ");
                    }
					
		/* Denne IF sætning sørger for at det sæde man er ved at vælge bliver farvet grønt */
					 if (isset($numInput))
					 {
						$idd = $numInput;
						echo ("<div id='" . $idd . "'");
                        echo ("style='");
                        echo ("height:16px;");
                        echo ("width:16px;");
						echo ("z-index: 3;");
                        echo ("margin-top:");
                        $getTop = mysqli_query($db, "SELECT top
                                                    FROM booking
                                                    WHERE SEATID=" . $idd);
                        
                        while ($row = mysqli_fetch_array($getTop)) {
                            echo $row['top'];
                        }
                        echo ("px;");
                        echo ("margin-left:");
                        
                        $getLeft = mysqli_query($db, "SELECT mleft
                                                    FROM booking
                                                    WHERE SEATID=" . $idd);
                        
                        while ($row = mysqli_fetch_array($getLeft)) {
                            echo $row['mleft'];
                        }
                        echo ("px;"); 
                        echo ("position:absolute;");
                        echo ("background-color:green;");
						echo (" ' ");
						echo (" '> ");
						echo ("<p>" . $idd . "</p>");
                        echo (" </div> ");
					 }
					
					
					
                    ?>
	<!-- De følgen DIV's er det "usynlige" lag over billedet af kantinen -->
					<div id="zone1" Title="Stille-zone">
					</div>
					<div id="zone2" Title="Musik-zone">
					</div>
					<div id="zone3" Title="Køkken">
					</div>
					<div id="zone4" Title="Konsol-hjørnet">
					</div>
					<div id="zone5" Title="Automater">
					</div>
					<div id="zone6" Title="Automater" style="
							margin-top: -28px;
							margin-left: 180px;">
					</div>
					<div id="zone6" Title="Automater" style="
							margin-top: -118px;
							margin-left: 180px;">
					</div>
					<div id="zone7" Title="Søjle" style="
							margin-top: -622px;
							margin-left: 54px;">
					</div>					
					<div id="zone7" Title="Søjle" style="
							margin-top: -622px;
							margin-left: 108px;">
					</div>
					<div id="zone7" Title="Søjle" style="
							margin-top: -514px;
							margin-left: 108px;">
					</div>
					<div id="zone7" Title="Søjle" style="
							margin-top: -388px;
							margin-left: 108px;">
					</div>
					<div id="zone7" Title="Søjle" style="
							margin-top: -262px;
							margin-left: 108px;">
					</div>
					<div id="zone7" Title="Søjle" style="
							margin-top: -136px;
							margin-left: 108px;">
					</div>
					
                </div>
				
				
				
<!-- Her checkes der om bordreservationen er "åben" eller "lukket". Hvis det er åbent indsættes bestillingssystemet -->
			<?php
			
			if ($jaNej['yesNo'] == 'ja')
			{
            echo ("<div id='indholdRight'>
				
				<table style='margin-left: 0px;'>
				<tr>
				<td>
				<div style='background: red; height: 16px; width:16px; border-style: solid;'>
				</div>
				</td>
				<td>
				= Optaget
				</td>
				</tr>
				
				<tr>
				<td>
				<div style='background: white; height: 16px; width:16px; border-style: solid;'>
				</div>
				</td>
				<td>
				= Fri plads
				</td>
				</tr>
				
				<tr>
				<td>
				<div style='background: green; height: 16px; width:16px; border-style: solid;'>
				</div>
				</td>
				<td>
				= Nuværende valg
				</td>
				</tr>
				</table>
				</br>
				
					<h3 style='text-align: center; font-weight: bold;'> Vælg og klik på en ledig plads på billedet til
					venstre, og indsæt dit navn og billet-ID
					for at bestille. UPDATE: For at bestille en ny plads, skal du blot vælge en ny plads, indtaste navn og billet-ID igen, og du vil få tildelt en ny plads.</h3>
                    
                    <form action='' method='post'>
                        <table>
                            <tr>
                                <td>
                                    Valgt plads: 
                                </td>
                                <td>
                                    <input name='seat' value=' ");
									
			echo ($id . "'"); 
			echo ("readonly='readonly'>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    Navn:
                                </td>
                                <td>
                                    <input name='playername' type='text'>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    BilletID:
                                </td>
                                <td>
                                    <input name='ticketID' type='text'>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <button type='submit' value='Submit'>Reservér</button>
                                </td>
                            </tr>
                        </table>
                    </form>
                </div>");
			}
			
			
			else {echo ("<div id='indholdRight'>
						<h3 style='font-size: 42px;
						text-align: center;'>
						BORDRESERVATION ER LUKKET </h3> </div>");}
				?>
				
            </div>
        </div>
    </div>

</div>

<?php
include('bottom.html');
?>
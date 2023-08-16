<?php
include('isLoggedIn.php');
include('top2.php');
echo ($_SESSION['myusername']);
?>	
     
        <div id="page">
			
				<div id="indhold">
					<div id="indholdText2">
						<div id="indholdDiv2">

							<?php
								// Dette sker når der klikkes "Opdater"
								if(isset($_POST["submit"]))
								{

									$forside1stor2 = mysqli_real_escape_string(stripslashes($_POST['forside1stor2']));
									$forside1 = mysqli_real_escape_string(stripslashes($_POST['forside1']));
									$forside2stor = mysqli_real_escape_string(stripslashes($_POST['forside2stor']));
									$forside2 = mysqli_real_escape_string(stripslashes($_POST['forside2']));
									$forside3stor = mysqli_real_escape_string(stripslashes($_POST['forside3stor']));
									$forside3 = mysqli_real_escape_string(stripslashes($_POST['forside3']));
									$forside4stor = mysqli_real_escape_string(stripslashes($_POST['forside4stor']));
									$forside4 = mysqli_real_escape_string(stripslashes($_POST['forside4']));


									// Overskriften
									$forside1stor3 = $forside1stor2;
									$forside1stor3 = mysqli_escape_string($db,$forside1stor3);
									$q = "UPDATE forside SET `desc`='" . $forside1stor3 . "' WHERE num = 1;";
									mysqli_query($db, $q);
								
									// Undertekst til overskriften
									$forside1 = $forside1;
									$forside1 = mysqli_escape_string($db,$forside1);
									$q2 = "UPDATE forside SET `desc`='" . $forside1 . "' WHERE num = 2;";
									mysqli_query($db, $q2);
									
									// Overskrift lille vindue 1
									$forside2stor = $forside2stor;
									$forside2stor = mysqli_escape_string($db,$forside2stor);
									$q2 = "UPDATE forside SET `desc`='" . $forside2stor . "' WHERE num = 3;";
									mysqli_query($db, $q2);
									
									// Undertekst til vindue 1
									$forside2 = $forside2;
									$forside2 = mysqli_escape_string($db,$forside2);
									$q3 = "UPDATE forside SET `desc`='" . $forside2 . "' WHERE num = 4;";
									mysqli_query($db, $q3);
									
									// Overskrift lille vindue 2
									$forside3stor = $forside3stor;
									$forside3stor = mysqli_escape_string($db,$forside3stor);
									$q4 = "UPDATE forside SET `desc`='" . $forside3stor . "' WHERE num = 5;";
									mysqli_query($db, $q4);
								
									// Undertekst til vindue 2
									$forside3 = $forside3;
									$forside3 = mysqli_escape_string($db,$forside3);
									$q5 = "UPDATE forside SET `desc`='" . $forside3 . "' WHERE num = 6;";
									mysqli_query($db, $q5);
									
									// Overskrift lille vindue 3
									$forside4stor = $forside4stor;
									$forside4stor = mysqli_escape_string($db,$forside4stor);
									$q6 = "UPDATE forside SET `desc`='" . $forside4stor . "' WHERE num = 7;";
									mysqli_query($db, $q6);
								
									// Undertekst til vindue 3
									$forside4 = $forside4;
									$forside4 = mysqli_escape_string($db,$forside4);
									$q7 = "UPDATE forside SET `desc`='" . $forside4 . "' WHERE num = 8;";
									mysqli_query($db, $q7);
									
								}
							?>
							
							
					<!-- Her er de 8 text-vinduer man kan redigere i, inklusiv opdater knap -->
							<form action="adminindex.php" method="post">
							
							<div id="forside1stor">
								<h3>Overskrift</h3>
								<textarea name="forside1stor2" rows="1" cols="30" maxlength="50" ><?php
								$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT *  FROM forside WHERE num = 1"));
								echo $result["desc"];	
								?>
								</textarea>
							</div>
							<div id="forside1stor">
								<h3> Øverste skrift, under overskrift </h3>
								<textarea name="forside1" rows="10" cols="100" maxlength="1250"><?php
								$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT *  FROM forside WHERE num = 2"));
								echo $result["desc"];	
								?>
								</textarea>	
							</div>
							<div id="tab1">
							<h3> Under-overskrift 1 </h3>
							<textarea name="forside2stor" rows="2" cols="20"><?php
								$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT *  FROM forside WHERE num = 3"));
								echo $result["desc"];	
							?>
							</textarea>	
							<h3> Tabel 1 </h3>
							<textarea name="forside2" rows="12" cols="25"><?php
								$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT *  FROM forside WHERE num = 4"));
								echo $result["desc"];	
							?>
							</textarea>	
							</div>
							<div id="tab2">
							<h3> Under-overskrift 2 </h3>
							<textarea name="forside3stor" rows="2" cols="20"><?php
								$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT *  FROM forside WHERE num = 5"));
								echo $result["desc"];	
							?>
							</textarea>	
							<h3> Tabel 2 </h3>
							<textarea name="forside3" rows="12" cols="25"><?php
								$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT *  FROM forside WHERE num = 6"));
								echo $result["desc"];	
							?>
							</textarea>	
							</div>
							<div id="tab3">
							<h3> Under-overskrift 3 </h3>
							<textarea name="forside4stor" rows="2" cols="20"><?php
								$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT *  FROM forside WHERE num = 7"));
								echo $result["desc"];	
							?>
							</textarea>	
							<h3> Tabel 3 </h3>
							<textarea name="forside4" rows="12" cols="25"><?php
								$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT *  FROM forside WHERE num = 8"));
								echo $result["desc"];	
							?>
							</textarea>	
							</div>
							
							<br />
							<div id="button1">
							<input type="submit" value="Klik her for at opdatere!" name="submit" />
							</div>
							
							
							</form>

						</div>
					</div>
					
				</div>
			
        </div>

		
<?php
include('bottom.html');
?>











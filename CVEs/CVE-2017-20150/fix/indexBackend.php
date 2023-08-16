<?php
include('top.php');
?>	
     
        <div id="page">
			
				<div id="indhold">
					<div id="indholdText2">
						<div id="indholdDiv2">

						<h1> 
							<?php
								$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT * FROM forside1stor"));
								echo $result["desc"];								
							?>
						</h1>
						<p>	<?php
								$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT * FROM forside1"));
								echo $result["desc"];
							?>
						</p>
						
							<div id="tab1">
								<h2>
									<?php
										$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT * FROM forside2stor"));
										echo $result["desc"];
									?>
								</h2>
								
								<p> 
									<?php
										$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT * FROM forside2"));
										echo $result["desc"];	
									?>	
								</p>
							</div>
							
							<div id="tab2">
								<h2>
									<?php
										$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT * FROM forside3stor"));
										echo $result["desc"];	
									?>	
								</h2>
								<p>
									<?php
										$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT * FROM forside3"));
										echo $result["desc"];	
									?>	
								</p>
							</div>
							
							<div id="tab3">
								<h2>
									<?php
										$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT * FROM forside4stor"));
										echo $result["desc"];	
									?>	
								</h2>
								
								<p>
									<?php
										$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT * FROM forside4"));
										echo $result["desc"];	
									?>	
								</p>
							</div>
						





						</div>
					</div>
					
				</div>
			
        </div>

		
<?php
include('bottom.html');
?>

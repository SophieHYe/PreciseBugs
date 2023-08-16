<?php
/*
#########################################
#
# Copyright (C) 2016 EyesOfNetwork Team
# DEV NAME : Jean-Philippe LEVY
# VERSION : 5.0
# APPLICATION : eonweb for eyesofnetwork project
#
# LICENCE :
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
#########################################
*/

# Check optionnal module to load
if(isset($_GET["module"]) && isset($_GET["link"])) { 

	include("../include/config.php");
	include("../include/arrays.php");
	
	if(in_array($_GET["module"],$array_modules)) {
		$module=exec("rpm -q ".$_GET["module"]." |grep '.eon' |wc -l");
		# Redirect to module page if rpm installed
		if($module!=0) { header('Location: '.$_GET["link"].''); }
	}

} 
	
include("../header.php"); 
include("../side.php"); 

?>

<div id="page-wrapper">

	<div class="row">
		<div class="col-lg-12">
			<h1 class="page-header"><?php echo getLabel("label.home_about.title"); ?></h1>
		</div>
	</div>

	<div class="row">
	<?php 
		# Module not installed
		if(isset($module)) {
			message(0," : Module ".$_GET["module"]." is not installed",'warning'); 
		} 
		# Module or link not specified
		else {
			message(0," : Not allowed",'critical'); 
		}
	?>
	</div>
	
</div>

<?php include("../footer.php"); ?>

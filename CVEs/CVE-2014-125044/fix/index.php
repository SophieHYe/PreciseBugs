<html>
	<head>
		<?php include('parts/head.html.part'); ?>
	</head>
	<body class="vflex">
		<div class="header hflex">
			<?php include('parts/header.html.part'); ?>
		</div>
		<div class="center hflex">
			<div class="page">
				<?php
					$page_spec = array_merge(array('p'=>'home'), $_GET)['p'];
					
					if (strpos($page_spec, '/') === false)
					{
						include("{$_SERVER['DOCUMENT_ROOT']}/pages/$page_spec.html.part");
					}
					else
					{
						echo 'no one here but us chickens';
					}
				?>
			</div>
			<div class="sidebar">
				<?php include('parts/sidebar.html.part'); ?>
			</div>
		</div>
		<div class="footer hflex">
			<?php include('parts/footer.html.part'); ?>
		</div>
	</body>
</html>

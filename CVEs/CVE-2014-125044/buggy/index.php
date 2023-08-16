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
				<?php include('pages/' . array_merge(array('p'=>'home'), $_GET)['p'] . '.html.part'); ?>
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

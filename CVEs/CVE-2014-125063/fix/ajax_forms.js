$(document).ready(function(){
	/* Leilao Form Ajax */
	$("#leilao").submit(function(event){
		/* Stop form from submitting normally */
		event.preventDefault();
		/* Get some values from elements on the page: */
		var values = $(this).serialize();
		/* Send the data using post and put the results in a div */
		$.ajax({
			url: "leilao.php",
			type: "post",
			data: values,
			success: function(data){
				try {	
					var error = $(data).filter("#erro");
					//var error = $(data).find('#erro'); use this if div is nested
					alert(error.html());
				}
				catch(err) {
					console.log("Error not found");
				}
				populateDivTable("leilaoinscritos.php","leiloesincritos");
				populateDivTable("leilaotop.php","leiloestop");
				},
				error:function(){
					alert("failure");
					$("#result").html('There is error while submit');
				}
			});
	});

	/* Lance Form Ajax */
	$("#lance").submit(function(event){
		/* Stop form from submitting normally */
		event.preventDefault();
		/* Get some values from elements on the page: */
		var values = $(this).serialize();
		/* Send the data using post and put the results in a div */
		$.ajax({
			url: "lance.php",
			type: "post",
			data: values,
			success: function(data){
				try {
					//alert(data);
					var error = $(data).filter("#erro");
					//var error = $(data).find('#erro'); use this if div is nested
					if(error.html() != undefined)
						alert(error.html());
				}
				catch(err) {
					console.log("Error not found");
				}
				populateDivTable("leilaoinscritos.php","leiloesincritos");
				populateDivTable("leilaotop.php","leiloestop");
			},
			error:function(){
				alert("failure");
				$("#result").html('There is error while submit');
			}
		});
	});
});
jQuery(document).ready(function($) {
	$('div#jobs-admin-sort').each(function() {

		var sortList = $('ul#custom-type-list');

		sortList.sortable({
			update: function(event, ui) {
				$('#loading-animation').show(); // Show the animate loading gif while waiting

				opts = {
					url: ajaxurl, // ajaxurl is defined by WordPress and points to /wp-admin/admin-ajax.php
					type: 'POST',
					async: true,
					cache: false,
					dataType: 'json',
					data:{
						action: 'save_sort', // Tell WordPress how to handle this ajax request
						order: sortList.sortable('toArray').toString() // Passes ID's of list items in	1,3,2 format
					},
					success: function(response) {
						$('div#message').remove();
						$('#loading-animation').hide(); // Hide the loading animation
						$('div#jobs-admin-sort h2:first').after('<div id="message" class="updated below-h2"><p>Jobs sort order has been saved</p></div>');
						return;
					},
					error: function(xhr,textStatus,e) {
						$('#loading-animation').hide(); // Hide the loading animation
						$('div#jobs-admin-sort h2:first').after('<div id="message" class="error below-h2"><p>There was an error saving the sort order. Please try again later.</p></div>');
						return;
					}
				};
				$.ajax(opts);
			}
		});

	});
});
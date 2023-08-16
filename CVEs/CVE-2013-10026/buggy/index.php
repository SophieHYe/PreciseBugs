<div class="wrap">
	
	<?php screen_icon( 'themes' ); ?>
	<h2>Subscribers</h2>
	
	<div id="poststuff">
	
		<div id="post-body" class="metabox-holder columns-2">
		
			<!-- main content -->
			<div id="post-body-content">
				
			<form method="post" action="?page=<?php echo $_GET['page']; ?>">
            <input name="sml_remove" value="1" type="hidden" />
            			<?php 
						if ($_SERVER['REQUEST_METHOD']=="POST" and $_POST['sml_remove']) {
							if ($_GET['rem']) $_POST['rem'][] = $_GET['rem'];
							$count = 0;
							if (is_array($_POST['rem'])) {
								foreach ($_POST['rem'] as $id) { 
									$wpdb->query("delete from ".$wpdb->prefix."sml where id = '".$id."' limit 1"); 
									$count++; 
								}
								$message = $count." subscribers have been removed successfully.";
							}
						}
						
						if ($_SERVER['REQUEST_METHOD']=="POST" and $_POST['sml_import']) {
							$correct = 0;
							if($_FILES['file']['tmp_name']) {
								if(!$_FILES['file']['error'])  {
									$file = file_get_contents ($_FILES['file']['tmp_name']);
									$lines = preg_split('/\r\n|\r|\n/', $file);
									if (count($lines)) {
										$sql = array();
										foreach ($lines as $data) {
											$data = explode(',', $data);
											$num = count($data);
											$row++;
											
											if (is_email(trim($data[0]))) {
												$c = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."sml where sml_email LIKE '".$wpdb->escape(trim($data[0]))."' limit 1", ARRAY_A);
												if (!is_array($c)) {											
													$wpdb->query("INSERT INTO ".$wpdb->prefix."sml (sml_email, sml_name) VALUES ('".$wpdb->escape(trim($data[0]))."', '".$wpdb->escape(trim($data[1]))."')");
													$correct++;
												} else { $exists++; }
											} else { $invalid++; }
										}
										
									} else { $message = 'Oh no! Your CSV file does not apear to be valid, please check the format and upload again.'; }
								
									if (!$message) {
										$message = $correct.' records have been imported. '.($invalid?$invalid.' could not be imported due to invalid email addresses. ':'').($exists?$exists.' already exists. ':'');
									}
								
								} else {
									$message = 'Ooops! There seems to of been a problem uploading your csv';
								}
							}								
						}
						//echo $sql;
						if ($message) { echo '<div style="padding: 5px;" class="updated"><p>'.$message.'</p></div>'; }
						
            			?>
					
						<table cellspacing="0" class="wp-list-table widefat fixed subscribers">
                          <thead>
                            <tr>
                                <th style="" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox"></th>
                                <th style="" class="manage-column column-name" id="name" scope="col">Name<span class="sorting-indicator"></span></th>
                                <th style="" class="manage-column column-email" id="email" scope="col"><span>Email Address</span><span class="sorting-indicator"></span></th>
                            </thead>
                        
                            <tfoot>
                            <tr>
                                <th style="" class="manage-column column-cb check-column" scope="col"><input type="checkbox"></th>
                                <th style="" class="manage-column column-name" scope="col"><span>Name</span><span class="sorting-indicator"></span></th>
                                <th style="" class="manage-column column-email" scope="col"><span>Email Address</span><span class="sorting-indicator"></span></th>
                            </tfoot>
                        
                            <tbody id="the-list">
                            
                            <?php 
                            
								$results = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."sml");
								if (count($results)<1) echo '<tr class="no-items"><td colspan="3" class="colspanchange">No mailing list subscribers have been added.</td></tr>';
								else {
									foreach($results as $row) {
	
										echo '<tr>
													<th class="check-column" style="padding:5px 0 2px 0"><input type="checkbox" name="rem[]" value="'.$row->id.'"></th>
													<td>'.$row->sml_name.'</td>
													<td>'.$row->sml_email.'</td>
											  </tr>';
											  
											  
											  
	
									}
								}
							
							?>
                            
                                
                            </tbody>
                        </table>
                        <br class="clear">
						<input class="button" name="submit" type="submit" value="Remove Selected" /> <a class="button" href="<?php echo plugins_url( 'export-csv.php', __FILE__ ); ?>">Export as CSV</a>
				</form>
				<br class="clear">
                
                
                <div class="meta-box-sortables">
                        <div class="postbox">
                        	
                       	  <h3><span>Import your own CSV File</span></h3>
                          <div class="inside">
                
                <p>This feature allows you to import your own csv (comma seperated values) file into &quot;Mail Subscribe List&quot;.</p>

                <form id="import-csv" method="post" enctype="multipart/form-data" action="?page=<?=$_GET['page'];?>">
                <input name="sml_import" value="1" type="hidden" />
                <p><label><input name="file" type="file" value="" /> CSV File</label></p>
                <p class="description">File must contain no header row, each record on its own line and only two comma seperated collumns in the order of email address followed by name. The name field is optional.</p>
                <p>Example: joe@blogs.com,Joe Blogs</p>
                
                
                
                <p class="submit"><input type="submit" value="Upload and Import CSV File" class="button-secondary" id="submit" name="submit"></p></form>
                </div></div></div>
                
         
                
			</div> 
			
			
			<div id="postbox-container-1" class="postbox-container">
                    <div class="meta-box-sortables">
                        <div class="postbox">
                        	
                       	  <h3><span>Plugin Author</span></h3>
                            <div class="inside">
                            	
                                <p><a target="_blank" href="http://www.webfwd.co.uk/packages/wordpress-hosting/"><img style="margin-top:15px" src="<?php echo plugins_url( 'webfwdlogo.png', __FILE__ ); ?>"></a></p>

                                
                                
                                <p><em><strong style="color: #21759B">We are WordPress specialists!</strong> <br />
                                We help small and medium size businesses by developing bespoke web based applications  that improve efficiency, reduce costs and eliminate non-value added  processes. </em></p>
                                <p><em>We also have our own WordPress optimised servers and offer <strong style="color: #21759B">super fast reliable WordPress hosting just £6.00 per month.</strong></em></p>
                                <p class="voucher" style="background:#21759B; border-radius: 5px; padding:10px; text-align:center; color:#FFF; line-height:15px;"><strong style="font-size:16px">50% OFF ALL<br />WordPress Hosting Packages</strong><br />
                                Your Promotional Code:<strong style="font-size:32px; background:#FFF; color:#21759B; line-height: 38px; display:block; padding: 2px 0">2YH6EJIN4Z</strong>Discount can be applied on Checkout<a class="button" href="http://www.webfwd.co.uk/packages/wordpress-hosting/" target="_blank">Click Here to Purchase</a></p>
<p><a class="button" href="http://www.webfwd.co.uk/packages/wordpress-hosting/" target="_blank">Visit our Website</a></p>
                                
                                <p>Please follow us on - <a class="button" target="_blank" href="https://twitter.com/webfwd">Twitter</a> <a class="button" target="_blank" href="https://www.facebook.com/pages/Webforward/208823852487018">Facebook</a></p>
                            </div>
                      </div> 
                    </div> 
                </div> 
            </div>
            <br class="clear">
	</div>
	
</div> 
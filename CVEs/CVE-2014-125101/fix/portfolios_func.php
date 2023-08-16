	<?php	
	if(function_exists('current_user_can'))
	if(!current_user_can('manage_options')) {
	die('Access Denied');
}	
if(!function_exists('current_user_can')){
	die('Access Denied');
}

function showportfolio() 
  {
	  
  global $wpdb;
	$limit=0;

	$search_tag=esc_html(stripslashes($_POST['search_events_by_title']));
	$cat_row_query="SELECT id,name FROM ".$wpdb->prefix."huge_itportfolio_portfolios WHERE sl_width=0";
	$cat_row=$wpdb->get_results($cat_row_query);
	

	$query = $wpdb->prepare("SELECT COUNT(*) FROM ".$wpdb->prefix."huge_itportfolio_portfolios WHERE name LIKE '% %s %'" , $search_tag);
	
	$total = $wpdb->get_var($query);

	if(!($cat_id)){
	 $query ="SELECT  a.* ,  COUNT(b.id) AS count, g.par_name AS par_name FROM ".$wpdb->prefix."huge_itportfolio_portfolios  AS a LEFT JOIN ".$wpdb->prefix."huge_itportfolio_portfolios AS b ON a.id = b.sl_width LEFT JOIN (SELECT  ".$wpdb->prefix."huge_itportfolio_portfolios.ordering as ordering,".$wpdb->prefix."huge_itportfolio_portfolios.id AS id, COUNT( ".$wpdb->prefix."huge_itportfolio_images.portfolio_id ) AS prod_count
FROM ".$wpdb->prefix."huge_itportfolio_images, ".$wpdb->prefix."huge_itportfolio_portfolios
WHERE ".$wpdb->prefix."huge_itportfolio_images.portfolio_id = ".$wpdb->prefix."huge_itportfolio_portfolios.id
GROUP BY ".$wpdb->prefix."huge_itportfolio_images.portfolio_id) AS c ON c.id = a.id LEFT JOIN
(SELECT ".$wpdb->prefix."huge_itportfolio_portfolios.name AS par_name,".$wpdb->prefix."huge_itportfolio_portfolios.id FROM ".$wpdb->prefix."huge_itportfolio_portfolios) AS g
 ON a.sl_width=g.id WHERE a.name LIKE '%".$search_tag."%'  group by a.id  "; 
}

$rows = $wpdb->get_results($query);

$rows=open_cat_in_tree($rows);
	$query ="SELECT  ".$wpdb->prefix."huge_itportfolio_portfolios.ordering,".$wpdb->prefix."huge_itportfolio_portfolios.id, COUNT( ".$wpdb->prefix."huge_itportfolio_images.portfolio_id ) AS prod_count
FROM ".$wpdb->prefix."huge_itportfolio_images, ".$wpdb->prefix."huge_itportfolio_portfolios
WHERE ".$wpdb->prefix."huge_itportfolio_images.portfolio_id = ".$wpdb->prefix."huge_itportfolio_portfolios.id
GROUP BY ".$wpdb->prefix."huge_itportfolio_images.portfolio_id " ;
	$prod_rows = $wpdb->get_results($query);
		
foreach($rows as $row)
{
	foreach($prod_rows as $row_1)
	{
		if ($row->id == $row_1->id)
		{
			$row->ordering = $row_1->ordering;
			$row->prod_count = $row_1->prod_count;
		}
	}
}
	$cat_row=open_cat_in_tree($cat_row);
		html_showportfolios( $rows, $pageNav,$sort,$cat_row);
  }

function open_cat_in_tree($catt,$tree_problem='',$hihiih=1){

global $wpdb;
global $glob_ordering_in_cat;
static $trr_cat=array();
if(!isset($search_tag))
$search_tag='';
if($hihiih)
$trr_cat=array();
foreach($catt as $local_cat){
	$local_cat->name=$tree_problem.$local_cat->name;
	array_push($trr_cat,$local_cat);
}
return $trr_cat;

}

function editportfolio($id)
  {
	  
	  global $wpdb;
	  
	  if(isset($_GET["removeslide"])){
	     if($_GET["removeslide"] != ''){
	
		$idfordelete = $_GET["removeslide"];
	  $wpdb->query($wpdb->prepare("DELETE FROM ".$wpdb->prefix."huge_itportfolio_images  WHERE id = %d ", $idfordelete));


	
	   }
	   }

	   $query=$wpdb->prepare("SELECT * FROM ".$wpdb->prefix."huge_itportfolio_portfolios WHERE id= %d",$id);
	   $row=$wpdb->get_row($query);
	   if(!isset($row->portfolio_list_effects_s))
	   return 'id not found';
       $images=explode(";;;",$row->portfolio_list_effects_s);
	   $par=explode('	',$row->param);
	   $count_ord=count($images);
	   $cat_row=$wpdb->get_results($wpdb->prepare("SELECT * FROM ".$wpdb->prefix."huge_itportfolio_portfolios WHERE id!= %d and sl_width=0", $id));
       $cat_row=open_cat_in_tree($cat_row);
	   	  $query=$wpdb->prepare("SELECT name,ordering FROM ".$wpdb->prefix."huge_itportfolio_portfolios WHERE sl_width=%d  ORDER BY `ordering` ",$row->sl_width);
	   $ord_elem=$wpdb->get_results($query);
	   
	    $query=$wpdb->prepare("SELECT * FROM ".$wpdb->prefix."huge_itportfolio_images where portfolio_id = %d order by ordering ASC  ",$row->id);
			   $rowim=$wpdb->get_results($query);
			   
			   if(isset($_GET["addslide"])){
			   if($_GET["addslide"] == 1){
	
$table_name = $wpdb->prefix . "huge_itportfolio_images";
    $sql_2 = "
INSERT INTO 

`" . $table_name . "` ( `name`, `portfolio_id`, `description`, `image_url`, `sl_url`, `ordering`, `published`, `published_in_sl_width`) VALUES
( '', '".$row->id."', '', '', '', 'par_TV', 2, '1' )";

    $wpdb->query($sql_huge_itportfolio_images);
	

      $wpdb->query($sql_2);
	
	   }
	   }
	   
	
	   
	   $query="SELECT * FROM ".$wpdb->prefix."huge_itportfolio_portfolios order by id ASC";
			   $rowsld=$wpdb->get_results($query);
			  
			    $query = "SELECT *  from " . $wpdb->prefix . "huge_itportfolio_params ";

    $rowspar = $wpdb->get_results($query);

    $paramssld = array();
    foreach ($rowspar as $rowpar) {
        $key = $rowpar->name;
        $value = $rowpar->value;
        $paramssld[$key] = $value;
    }
	
	 $query="SELECT * FROM ".$wpdb->prefix."posts where post_type = 'post' and post_status = 'publish' order by id ASC";
			   $rowsposts=$wpdb->get_results($query);
	 
	 $rowsposts8 = '';
	 $postsbycat = '';
	 if(isset($_POST["iframecatid"])){
	 	  $query=$wpdb->prepare("SELECT * FROM ".$wpdb->prefix."term_relationships where term_taxonomy_id = %d order by object_id ASC",$_POST["iframecatid"]);
		$rowsposts8=$wpdb->get_results($query);


	 

			   foreach($rowsposts8 as $rowsposts13){
	 $query=$wpdb->prepare("SELECT * FROM ".$wpdb->prefix."posts where post_type = 'post' and post_status = 'publish' and ID = %d  order by ID ASC",$rowsposts13->object_id);
			   $rowsposts1=$wpdb->get_results($query);
			   $postsbycat = $rowsposts1;
			   
	 }
	 }
	
	   	   
    Html_editportfolio($ord_elem, $count_ord, $images, $row, $cat_row, $rowim, $rowsld, $paramssld, $rowsposts, $rowsposts8, $postsbycat);
  }
  
function add_portfolio()
{
	global $wpdb;
	
	$query="SELECT name,ordering FROM ".$wpdb->prefix."huge_itportfolio_portfolios WHERE sl_width=0 ORDER BY `ordering`";
	$ord_elem=$wpdb->get_results($query); ///////ordering elements list
	$cat_row=$wpdb->get_results("SELECT * FROM ".$wpdb->prefix."huge_itportfolio_portfolios where sl_width=0");
	$cat_row=open_cat_in_tree($cat_row);
	
	$table_name = $wpdb->prefix . "huge_itportfolio_portfolios";
    $sql_2 = "
INSERT INTO 

`" . $table_name . "` ( `name`, `sl_height`, `sl_width`, `pause_on_hover`, `portfolio_list_effects_s`, `description`, `param`, `ordering`, `published`) VALUES
( 'New portfolio', '375', '600', 'on', 'cubeH', '4000', '1000', '1', '300')";

    $wpdb->query($sql_huge_itportfolio_portfolios);

      $wpdb->query($sql_2);

   $query="SELECT * FROM ".$wpdb->prefix."huge_itportfolio_portfolios order by id ASC";
			   $rowsldcc=$wpdb->get_results($query);
			   $last_key = key( array_slice( $rowsldcc, -1, 1, TRUE ) );
			   
			   
	foreach($rowsldcc as $key=>$rowsldccs){
		if($last_key == $key){
			header('Location: admin.php?page=portfolios_huge_it_portfolio&id='.$rowsldccs->id.'&task=apply');
		}
	}
	
	html_add_portfolio($ord_elem, $cat_row);
	
}


function removeportfolio($id)
{

	global $wpdb;
	 $sql_remov_tag=$wpdb->prepare("DELETE FROM ".$wpdb->prefix."huge_itportfolio_portfolios WHERE id = %d", $id);
 if(!$wpdb->query($sql_remov_tag))
 {
	  ?>
	  <div id="message" class="error"><p>portfolio Not Deleted</p></div>
      <?php
	 
 }
 else{
 ?>
 <div class="updated"><p><strong><?php _e('Item Deleted.' ); ?></strong></p></div>
 <?php
 }
}

function apply_cat($id)
{	
		 global $wpdb;
		 if(!is_numeric($id)){
			 echo 'insert numerc id';
		 	return '';
		 }
		 if(!(isset($_POST['sl_width']) && isset($_POST["name"]) ))
		 {
			 return '';
		 }
		 $cat_row=$wpdb->get_results($wpdb->prepare("SELECT * FROM ".$wpdb->prefix."huge_itportfolio_portfolios WHERE id!= %d ", $id));
		 $corent_ord=$wpdb->get_var($wpdb->prepare('SELECT `ordering` FROM '.$wpdb->prefix.'huge_itportfolio_portfolios WHERE id = %d AND sl_width=%d',$id,$_POST['sl_width']));
		 $max_ord=$wpdb->get_var('SELECT MAX(ordering) FROM '.$wpdb->prefix.'huge_itportfolio_portfolios');
	 
            $query=$wpdb->prepare("SELECT sl_width FROM ".$wpdb->prefix."huge_itportfolio_portfolios WHERE id = %d", $id);
	        $id_bef=$wpdb->get_var($query);
      
	if(isset($_POST["content"])){
	$script_cat = preg_replace('#<script(.*?)>(.*?)</script>#is', '', stripslashes($_POST["content"]));
	}

	$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."huge_itportfolio_portfolios SET  name = %s  WHERE id = %d ", $_POST["name"], $id));
	$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."huge_itportfolio_portfolios SET  sl_width = %s  WHERE id = %d ", $_POST["name"], $id));
	$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."huge_itportfolio_portfolios SET  sl_height = %s  WHERE id = %d ", $_POST["name"], $id));
	$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."huge_itportfolio_portfolios SET  pause_on_hover = %s  WHERE id = %d ", $_POST["name"], $id));
	$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."huge_itportfolio_portfolios SET  portfolio_list_effects_s = %s  WHERE id = %d ", $_POST["name"], $id));
	$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."huge_itportfolio_portfolios SET  description = %s  WHERE id = %d ", $_POST["name"], $id));
	$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."huge_itportfolio_portfolios SET  param = %s  WHERE id = %d ", $_POST["name"], $id));
	$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."huge_itportfolio_portfolios SET  sl_position = %s  WHERE id = %d ", $_POST["name"], $id));
	$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."huge_itportfolio_portfolios SET  ordering = '1'  WHERE id = %d ", $id));

		
	$query=$wpdb->prepare("SELECT * FROM ".$wpdb->prefix."huge_itportfolio_portfolios WHERE id = %d", $id);
	   $row=$wpdb->get_row($query);

			    $query=$wpdb->prepare("SELECT * FROM ".$wpdb->prefix."huge_itportfolio_images where portfolio_id = %d order by id ASC", $row->id);
			   $rowim=$wpdb->get_results($query);
			   
			   foreach ($rowim as $key=>$rowimages){

$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."huge_itportfolio_images SET  ordering = '".$_POST["order_by_".$rowimages->id.""]."'  WHERE ID = %d ", $rowimages->id));
$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."huge_itportfolio_images SET  link_target = '".$_POST["sl_link_target".$rowimages->id.""]."'  WHERE ID = %d ", $rowimages->id));
$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."huge_itportfolio_images SET  sl_url = '".$_POST["sl_url".$rowimages->id.""]."' WHERE ID = %d ", $rowimages->id));
$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."huge_itportfolio_images SET  name = '".$_POST["titleimage".$rowimages->id.""]."'  WHERE ID = %d ", $rowimages->id));
$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."huge_itportfolio_images SET  description = '".$_POST["im_description".$rowimages->id.""]."'  WHERE ID = %d ", $rowimages->id));
$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."huge_itportfolio_images SET  image_url = '".$_POST["imagess".$rowimages->id.""]."'  WHERE ID = %d ", $rowimages->id));

}

if (isset($_POST['params'])) {
      $params = $_POST['params'];
      foreach ($params as $key => $value) {
          $wpdb->update($wpdb->prefix . 'huge_itportfolio_params',
              array('value' => $value),
              array('name' => $key),
              array('%s')
          );
      }
     
    }
	
				   if($_POST["imagess"] != ''){
				   		   $query=$wpdb->prepare("SELECT * FROM ".$wpdb->prefix."huge_itportfolio_images where portfolio_id = %d order by id ASC", $row->id);
			   $rowim=$wpdb->get_results($query);
	  foreach ($rowim as $key=>$rowimages){
	  $orderingplus = $rowimages->ordering+1;
	  $wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."huge_itportfolio_images SET  ordering = %d  WHERE ID = %d ", $orderingplus, $rowimages->id));
	  }
	
$table_name = $wpdb->prefix . "huge_itportfolio_images";
	$imagesnewuploader = explode(";;;", $_POST["imagess"]);
	
	array_pop($imagesnewuploader);
	foreach($imagesnewuploader as $imagesnewupload){
    $sql_2 = "
INSERT INTO 

`" . $table_name . "` ( `name`, `portfolio_id`, `description`, `image_url`, `sl_url`, `ordering`, `published`, `published_in_sl_width`) VALUES
( '', '".$row->id."', '', '".$imagesnewupload.";', '', 'par_TV', 2, '1' )";

   
	

      $wpdb->query($sql_2);
		}
	   }
	   
	if(isset($_POST["posthuge-it-description-length"])){
	 $wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."huge_itportfolio_portfolios SET  published = %d WHERE id = %d ", $_POST["posthuge-it-description-length"], $_GET['id']));
}
	?>
	<div class="updated"><p><strong><?php _e('Item Saved'); ?></strong></p></div>
	<?php
	
    return true;
	
}

?>
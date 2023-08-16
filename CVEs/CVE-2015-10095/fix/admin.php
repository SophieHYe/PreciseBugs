<?php
/**
 * Represents the view for the administration dashboard.
 *
 * This includes the header, options, and other information that should provide
 * The User Interface to the end user.
 *
 * @package   WooPopup
 * @author    Guillaume Kanoufi <guillaume@lostwebdesigns.com>
 * @license   GPL-2.0+
 * @link      http://example.com
 * @copyright 2014 woocommerce, popup, woopopup
 */
?>

<div class="wrap">


	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

	<div class="wrap metabox-holder columns-2">
	      <form method="post" name="options" action="options.php">
			<?php
				$options = get_option($this->options_slug);
				/*
				* Grab all value if already set
				*
				*/
				$content = $options['popup_content'];
				$page = $options['popup_page'];
				$class = $options['popup_class'];
				$theme = $options['popup_theme'];
				// $use_button = $options['popup_use_button'];
				$permanent = $options['popup_permanent'];
				$start_date = $options['start_date'];
				$end_date = $options['end_date'];
				$timezone = $options['popup_timezone'];

				/*
				* Set up hidden fields
				*
				*/
				settings_fields($this->options_slug);
			?>


			<?php
			// editor_id cannot have brackets and must be lowercase
			$editor_id = 'popup_content';
			// textarea_name in array can have brackets!
			$settings = array(
				'wpautop' => true, // use wpautop?
				'media_buttons' => true, // show insert/upload button(s)
				'textarea_name' => $this->options_slug.'[popup_content]', // set the textarea name to something different, square brackets [] can be used here
				'textarea_rows' => get_option('default_post_edit_rows', 10), // rows="..."
				'tabindex' => '',
				'editor_css' => '', // intended for extra styles for both visual and HTML editors buttons, needs to include the <style> tags, can use "scoped".
				'editor_class' => '', // add extra class(es) to the editor textarea
				'teeny' => false, // output the minimal editor config used in Press This
				'dfw' => true, // replace the default fullscreen with DFW (supported on the front-end in WordPress 3.4)
				'tinymce' => true, // load TinyMCE, can be used to pass settings directly to TinyMCE using an array()
				'quicktags' => true
			);
			wp_editor($content, $editor_id, $settings);?>

	            <table width="100%" cellpadding="10" class="form-table">
	            	<tr>
	            		<th scope="row">
	            			<label><?php _e('Choose the page you want to display your popup window (default is cart page)', $this->plugin_slug);?>:</label>
	            		</th>
	            		<td>
	            			<?php
	            				$args = array(
	            					'show_option_none' => 'All',
	            					'option_none_value' => 'all',
	            					'selected' => $page,
	            					'name' => $this->options_slug.'[popup_page]',
	            				);
	            				$pages = wp_dropdown_pages($args);
	            			?>
	            		</td>

	            	</tr>
	            	<tr>
	            		<th scope="row">
	            			<label><?php _e('Choose the prettyPhoto Modal theme color', $this->plugin_slug);?>:</label>
	            		</th>
	            		<td>
	            			<select name="<?php echo $this->options_slug;?>[popup_theme]" >
	            				light_rounded / dark_rounded / light_square / dark_square / facebook
	            				<option value="pp_default" <?php if($theme == 'pp_default') echo 'selected';?>>Default</option>
						<option value="light_rounded" <?php if($theme == 'light_rounded') echo 'selected';?>>Light Rounded</option>
						<option value="dark_rounded" <?php if($theme == 'dark_rounded') echo 'selected';?>>Dark Rounded</option>
						<option value="light_square" <?php if($theme == 'light_square') echo 'selected';?>>Light Square</option>
						<option value="dark_square" <?php if($theme == 'dark_square') echo 'selected';?>>Dark Square</option>
						<option value="facebook" <?php if($theme == 'facebook') echo 'selected';?>>Facebook</option>
	            			</select>
	            		</td>

	            	</tr>
	            	<tr>
	            	<tr>
	            		<th scope="row">
	            			<label><?php _e('If using woocommerce, you can choose from woocommerce-message classes (message, info or error) else it will add a custom class of woopopup-yourchoice (your choice being: message, info or error) so you will be able to style it in your css', $this->plugin_slug);?>:</label>
	            		</th>
	            		<td>
	            			<select name="<?php echo $this->options_slug;?>[popup_class]" >
	            				<option value="notice" <?php if($class == 'notice') echo 'selected';?>>Notice (default non woocommerce class)</option>
							<option value="message" <?php if($class == 'message') echo 'selected';?>>Message</option>
							<option value="info" <?php if($class == 'info') echo 'selected';?>>Info</option>
							<option value="error" <?php if($class == 'error') echo 'selected';?>>Error</option>
	            			</select>
	            		</td>

	            	</tr>
	            	<tr>
	                    <th scope="row">
		                    <label><?php _e('Make the popup permanent (no dates selections)', $this->plugin_slug);?>:</label>
		                </th>
		                <td>
		                    <input type="checkbox" id="woo-popup_permanent" name="<?php echo $this->options_slug;?>[popup_permanent]" value="1" <?php if($permanent == '1') echo 'checked';?>/>
	                    </td>
	                 </tr>
	                <tr class="woo-popup_dates">
	                    <th scope="row">
		                    <label><?php _e('Begining Date', $this->plugin_slug);?>:</label>
		                </th>
		                <td>
		                    <input type="text" id="woo-popup-from" class="wpopup_date" name="<?php echo $this->options_slug;?>[start_date]" value="<?php echo $start_date;?>"/>
	                    </td>
	                 </tr>
	                 <tr class="woo-popup_dates">
	                    <th scope="row">
		                    <label><?php _e('End Date', $this->plugin_slug);?>:</label>
		               </th>
		               <td>
		                    <input type="text" id="woo-popup-to" class="wpopup_date" name="<?php echo $this->options_slug;?>[end_date]" value="<?php echo $end_date;?>"/>
	                    </td>
	                </tr>
	                <tr class="woo-popup_dates">
	                		<?php $tzl = DateTimeZone::listIdentifiers();?>
	                    <th scope="row">
		                    <label><?php _e('Choose your Timezone', $this->plugin_slug);?>:</label>
		               </th>
		               <td>
		                    <select name="<?php echo $this->options_slug;?>[popup_timezone]" >
	            				<?php foreach ($tzl as $tz) :?>
	            					<option value="<?php echo $tz;?>" <?php if($timezone == $tz) echo 'selected';?>><?php echo $tz;?></option>
	            				<?php endforeach;?>
	            			</select>
	                    </td>
	                </tr>
	            </table>

	            <p class="submit">
	                <input type="submit" class="button-primary" name="Submit" value="Save Changes" />
	            </p>
            </form>

      </div>
</div>

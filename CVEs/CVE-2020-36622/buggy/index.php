<?php
/**
 * Cinnebar.
 *
 * @package Cinnebar
 * @subpackage Template
 * @author $Author$
 * @version $Id$
 */
?>
<!-- admin index page -->
<article class="main">
	<header>
    	<h1><?php echo I18n::__('admin_h1') ?></h1>
	</header>
	<form
        id="form-<?php echo $record->getMeta('type') ?>"
        class="panel panel-<?php echo $record->getMeta('type') ?> action-edit"
        method="POST"
        accept-charset="utf-8"
        enctype="multipart/form-data">
        <?php echo $form_details ?>
        <div class="buttons">
            <input
                type="submit"
                name="submit"
                accesskey="s"
                value="<?php echo I18n::__('admin_submit_setting') ?>" />
        </div>
    </form>
</article>
<!-- End of admin index page -->

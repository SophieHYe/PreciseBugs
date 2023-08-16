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
<article class="main">
    <header id="header-toolbar" class="fixable">
        <h1><?php echo I18n::__("{$type}_h1") ?></h1>
        <nav>
            <?php echo $toolbar ?>
        </nav>
    </header>
    <!-- scaffold edit form -->
    <form
        id="form-<?php echo $record->getMeta('type') ?>"
        class="panel panel-<?php echo $record->getMeta('type') ?> action-<?php echo $current_action ?>"
        method="POST"
        accept-charset="utf-8"
        enctype="multipart/form-data">
        
        <!-- form details -->
        <?php echo $form_details ?>
        <!-- end of form details -->
        
        <!-- Scaffold buttons -->
        <div class="buttons">
            <select name="next_action">
                <?php foreach ($actions[$current_action] as $action): ?>
                <option
                    value="<?php echo $action ?>"
                    <?php echo ($next_action == $action) ? 'selected="selected"' : '' ?>><?php echo I18n::__("action_{$action}_select") ?></option>
                <?php endforeach ?>
            </select>
            <input
                type="submit"
                name="submit"
                accesskey="s"
                value="<?php echo I18n::__('scaffold_submit_apply_action') ?>" />
        </div>
        <!-- End of Scaffold buttons -->
    </form>
</article>

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
<!-- Account -->
<article class="main">
    <header>
		<h1><?php echo I18n::__('account_h1') ?></h1>
		<nav>
            <?php echo $toolbar ?>
        </nav>
    </header>
    <form
        id="form-<?php echo $record->getMeta('type') ?>"
        class="panel panel-<?php echo $record->getMeta('type') ?> action-changepassword"
        method="POST"
        accept-charset="utf-8"
        enctype="multipart/form-data">        
        <!-- changepassword form -->
        <fieldset>
            <legend><?php echo I18n::__('account_legend_changepassword') ?></legend>
            <div
                class="row <?php echo $record->hasError('pw') ? 'error' : '' ?>">
                <label
                    for="user-pw">
                    <?php echo I18n::__('user_label_pw') ?>
                </label>
                <input
                    type="password"
                    id="user-pw"
                    name="pw"
                    value=""
                    required="required" />
            </div>
            <div
                class="row <?php echo $record->hasError('pwnew') ? 'error' : '' ?>">
                <label
                    for="user-pw-new">
                    <?php echo I18n::__('account_label_newpassword') ?>
                </label>
                <input
                    type="password"
                    id="user-pw-new"
                    name="pw_new"
                    value=""
                    required="required" />
            </div>
            <div
                class="row <?php echo $record->hasError('pwrepeated') ? 'error' : '' ?>">
                <label
                    for="user-pw-new">
                    <?php echo I18n::__('account_label_repeatedpassword') ?>
                </label>
                <input
                    type="password"
                    id="user-pw-repeated"
                    name="pw_repeated"
                    value=""
                    required="required" />
            </div>
        </fieldset>
        <!-- End of changepassword form -->
        <div class="buttons">
            <input type="submit" name="submit" value="<?php echo I18n::__('account_submit_changepassword') ?>" />
        </div>
    </form>
</article>
<!-- End of Login -->

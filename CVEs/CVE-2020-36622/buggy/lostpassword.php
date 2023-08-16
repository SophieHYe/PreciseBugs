<?php
/**
 * Template to aquire a new password.
 *
 * @package Cinnebar
 * @subpackage Template
 * @author $Author$
 * @version $Id$
 */
?>
<!-- Login -->
<?php if (isset($message)): ?>
<!-- notifications of the current user -->
<div
    id="notification"
    class="notification">
    <div class="alert alert-warning">
        <?php echo Flight::textile($message) ?>
    </div>
</div>
<!-- End of notifications -->
<?php endif ?>
<article class="main">
    <header>
		<h1><?php echo I18n::__('lostpassword_h1') ?></h1>
    </header>
    <form
        id="form-lostpassword"
        class="panel lostpassword"
        method="POST"
        accept-charset="utf-8">
        <fieldset>
            <legend><?php echo I18n::__('lostpassword_legend') ?></legend>
            <div
                class="row">
                <label
                    for="lostpassword-username">
                    <?php echo I18n::__('lostpassword_label_username') ?>
                </label>
                <input
                    type="email"
                    id="lostpassword-username"
                    name="dialog[uname]"
                    value="<?php echo htmlspecialchars($uname) ?>"
                    required="required"
 					autofocus="autofocus" />
            </div>
        </fieldset>
        <div class="buttons">
            <a
                href="<?php echo Url::build('/login') ?>"
                class="btn">
                <?php echo I18n::__('lostpassword_link_login') ?>
            </a>
            <input type="submit" name="submit" value="<?php echo I18n::__('lostpassword_submit') ?>" />
        </div>
    </form>
</article>
<!-- End of Login -->

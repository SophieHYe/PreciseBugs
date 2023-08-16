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
<!-- Login -->
<?php if (isset($message)): ?>
<!-- notifications of the current user -->
<div
    id="notification"
    class="notification">
    <div class="alert alert-error">
        <?php echo Flight::textile($message) ?>
    </div>
</div>
<!-- End of notifications -->
<?php endif ?>
<article class="main">
    <header>
		<h1><?php echo I18n::__('login_h1') ?></h1>
    </header>
    <form
        id="form-login"
        class="panel login"
        method="POST"
        accept-charset="utf-8">

        <input type="hidden" name="token" value="<?php echo Model::getCSRFToken() ?>" />

        <div>
            <input type="hidden" name="dialog[type]" value="<?php echo $record->getMeta('type') ?>" />
            <input type="hidden" name="dialog[id]" value="<?php echo $record->getId() ?>" />
            <input
                type="hidden"
                name="dialog[stamp]"
                value="<?php echo htmlspecialchars($record->stamp) ?>" />
            <input
                type="hidden"
                name="dialog[ipaddr]"
                value="<?php echo htmlspecialchars($record->ipaddr) ?>" />
            <input
                type="hidden"
                name="dialog[attempt]"
                value="<?php echo htmlspecialchars($record->attempt) ?>" />
            <input type="hidden" name="goto" value="<?php echo $goto ?>" />
        </div>
        <fieldset>
            <legend><?php echo I18n::__('login_legend') ?></legend>
            <div
                class="row <?php echo $record->hasError('uname') ? 'error' : '' ?>">
                <label
                    for="login-username">
                    <?php echo I18n::__('login_label_username') ?>
                </label>
                <input
                    type="text"
                    id="login-username"
                    name="dialog[uname]"
                    value="<?php echo htmlspecialchars($record->uname) ?>"
                    required="required"
 					autofocus="autofocus" />
            </div>
            <div
                class="row <?php echo $record->hasError('pw') ? 'error' : '' ?>">
                <label
                    for="login-password">
                    <?php echo I18n::__('login_label_password') ?>
                </label>
                <input
                    type="password"
                    id="login-password"
                    name="dialog[pw]"
                    value=""
                    required="required" />
            </div>
        </fieldset>
        <div class="buttons">
            <a
                href="<?php echo Url::build('/lostpassword') ?>"
                class="btn">
                <?php echo I18n::__('login_link_lostpassword') ?>
            </a>
            <input type="submit" name="submit" value="<?php echo I18n::__('login_submit') ?>" />
        </div>
    </form>
</article>
<!-- End of Login -->

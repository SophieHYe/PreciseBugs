<?php
/**
 * @file
 * Basic cart shopping cart html template
 */
?>

<?php if( empty($cart) ): ?>
  <p><?php print t('Your shopping cart is empty.'); ?></p>
<?php else: ?>
  <form accept-charset="UTF-8" id="basic-cart-cart-form" method="post">
  <div class="basic-cart-cart basic-cart-grid">
    <?php if(is_array($cart) && count($cart) >= 1): ?>
      <?php foreach($cart as $nid => $node): ?>
        <div class="basic-cart-cart-contents row">

            <div class="basic-cart-cart-quantity cell">
              <?php if(!$is_checkout): ?>
                <div class="cell"><?php print drupal_render($form['quantity_nid_' . $nid]); ?></div>
              <?php else: ?>
                <div class="cell"><?php print $node->basic_cart_quantity; ?></div>
              <?php endif; ?>
              <div class="cell basic-cart-cart-x">x</div>
            </div>

            <div class="basic-cart-cart-node-title cell">
              <?php print l($node->title, 'node/' . $node->nid); ?><br />
              <span class="basic-cart-cart-node-summary">
                <?php if(drupal_strlen($node->basic_cart_node_description) > 50): ?>
                  <?php print truncate_utf8($node->basic_cart_node_description, 50); ?> ... 
                <?php else: ?>
                  <?php print $node->basic_cart_node_description; ?>
                <?php endif; ?>
              </span>
            </div>
          
            <?php if(!$is_checkout): ?>
              <div class="basic-cart-delete-image cell">
                <?php print l('<img src="' . $base_path . drupal_get_path('module', 'basic_cart') . '/images/delete.gif" border="0" />', 'cart/remove/' . $nid, array('html' => TRUE)); ?>
              </div>
            <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if(!$is_checkout): ?>
        <div class="basic-cart-cart-checkout-button row">
          <?php print drupal_render($form['update']); ?>
          <?php print drupal_render($form['checkout']); ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php print drupal_render($form); ?>
  </form>
<?php endif; ?>

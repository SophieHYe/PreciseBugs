<?php
/**
 * @file
 * Basic cart shopping cart block
 */
?>

<?php if( empty($cart) ): ?>
  <p><?php print t('Your cart is empty.'); ?></p>
<?php else: ?>
  <div class="basic-cart-grid">
    <?php if(is_array($cart) && count($cart) >= 1): ?>
      <?php foreach($cart as $nid => $node): ?>
        <div class="basic-cart-cart-contents row">
            <div class="basic-cart-cart-quantity cell"><?php print $node->basic_cart_quantity; ?> x </div>
            <div class="basic-cart-cart-node-title cell"><?php print l($node->title, 'node/' . $node->nid); ?></div>
        </div>
      <?php endforeach; ?>
      <div class="basic-cart-cart-checkout-button basic-cart-cart-checkout-button-block row">
        <?php print l(t('View cart'), 'cart', array('attributes' => array('class' => array('button')))); ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

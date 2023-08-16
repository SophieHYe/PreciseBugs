<?php

/**
 * @file
 * Basic cart shopping cart implementation functions
 */

/**
 * Callback function for cart listing.
 */
function basic_cart_cart() {
  $cart = basic_cart_get_cart();
  if (!empty($cart)) {
    foreach ($cart as $nid => $node) {
      $body = field_get_items('node', $node, 'body');
      $description = isset($body[0]['value']) ? check_plain($body[0]['value']) : '';
      $cart[$nid]->basic_cart_node_description = $description;
    }
  }
  $form = drupal_get_form('basic_cart_cart_form');
  return theme('basic_cart_cart_render', array('cart' => $cart, 'is_checkout' => FALSE, 'form' => $form));
}

/**
 * Shopping cart form.
 */
function basic_cart_cart_form() {
  $cart = basic_cart_get_cart();
  if (is_array($cart) && !empty($cart)) {
    foreach ($cart as $nid => $node) {
      $form['quantity_nid_' . $nid] = array(
        '#type' => 'textfield',
        '#size' => 1,
        '#default_value' => $node->basic_cart_quantity,
      );
    }

    $form['update'] = array(
      '#type' => 'submit',
      '#value' => t('Update'),
    );

    $form['checkout'] = array(
      '#type' => 'submit',
      '#value' => t('Checkout'),
    );

    return $form;
  }
}

/**
 * Shopping cart form.
 */
function basic_cart_cart_form_submit($form_id, $form_state) {
  foreach ($form_state['values'] as $field => $value) {
    if (strpos($field, 'quantity_nid_') !== FALSE) {
      $nid = (int) str_replace('quantity_nid_', '', $field);
      $quantity = (int) $value;
      if ($quantity > 0) {
        $_SESSION['basic_cart']['cart'][$nid]->basic_cart_quantity = $quantity;
      }
      // If the quantity is zero, we just remove the node from the cart.
      elseif ($quantity == 0) {
        unset($_SESSION['basic_cart']['cart'][$nid]);
      }
    }
  }

  if ($form_state['values']['op'] == t('Checkout')) {
    drupal_goto('checkout');
  }
  else {
    drupal_set_message(t('Shopping cart updated.'));
  }
}

/**
 * Callback function for cart/add/.
 *
 * @param int $nid
 *   We are using the node id to store the node in the shopping cart
 */
function basic_cart_add_to_cart($nid = NULL) {
  $nid = (int) $nid;
  if ($nid > 0) {
    // If a node is added more times, just update the quantity.
    $cart = basic_cart_get_cart();
    if (!empty($cart) && in_array($nid, array_keys($cart))) {
      $_SESSION['basic_cart']['cart'][$nid]->basic_cart_quantity++;
    }
    else {
      // Slower, but easyer to implement.
      $node = node_load($nid);
      $node->basic_cart_quantity = 1;
      $_SESSION['basic_cart']['cart'][$nid] = $node;
    }

  }
  drupal_goto('cart');
}

/**
 * Callback function for cart/remove/.
 *
 * @param int $nid
 *   We are using the node id to remove the node in the shopping cart
 */
function basic_cart_remove_from_cart($nid = NULL) {
  $nid = (int) $nid;
  if ($nid > 0) {
    unset($_SESSION['basic_cart']['cart'][$nid]);
  }
  drupal_goto('cart');
}

/**
 * Function for shopping cart retrieval.
 *
 * @param int $nid
 *   We are using the node id to store the node in the shopping cart
 *
 * @return mixed
 *   Returning the shopping cart contents.
 *   An empty array if there is nothing in the cart
 */
function basic_cart_get_cart($nid = NULL) {
  if (isset($nid)) {
    return $_SESSION['basic_cart']['cart'][$nid];
  }
  if (isset($_SESSION['basic_cart']['cart'])) {
    return $_SESSION['basic_cart']['cart'];
  }
  // Empty cart.
  return array();
}

/**
 * Shopping cart reset.
 */
function basic_cart_empty_cart() {
  unset($_SESSION['basic_cart']['cart']);
}

/**
 * Checkout
 */

/**
 * Checkout form implementation.
 */
function basic_cart_checkout() {
  $cart = theme('basic_cart_cart_render', array('cart' => basic_cart_get_cart(), 'is_checkout' => TRUE));
  $form = drupal_render(drupal_get_form('basic_cart_checkout_form'));
  // If the cart is empty, we don't want to show the checkout form.
  $shopping_cart = basic_cart_get_cart();
  if (empty($shopping_cart)) {
    return $cart;
  }
  return $cart . $form;
}

/**
 * Checkout form.
 */
function basic_cart_checkout_form() {
  $form['basic_cart_checkout_name'] = array(
    '#title' => t('Name'),
    '#type' => 'textfield',
    '#required' => TRUE,
    '#description' => t('Please enter your name.'),
  );

  $form['basic_cart_checkout_email'] = array(
    '#title' => t('Email'),
    '#type' => 'textfield',
    '#required' => TRUE,
    '#description' => t('Please enter your email.'),
  );

  $form['basic_cart_checkout_phone'] = array(
    '#title' => t('Phone'),
    '#type' => 'textfield',
    '#description' => t('Please enter your phone.'),
  );

  $form['basic_cart_checkout_address'] = array(
    '#title' => t('Address'),
    '#type' => 'textfield',
    '#description' => t('Please enter your address.'),
  );

  $form['basic_cart_checkout_message'] = array(
    '#title' => t('Message'),
    '#type' => 'textarea',
    '#description' => t('If you have something to tell us, please fill the message area.'),
  );

  $form['basic_cart_checkout_submit'] = array(
    '#type' => 'submit',
    '#value' => t('Submit order'),
  );

  return $form;
}

/**
 * Checkout form validation.
 */
function basic_cart_checkout_form_validate($form, &$form_state) {
  if (!valid_email_address($form_state['values']['basic_cart_checkout_email'])) {
    form_set_error('basic_cart_checkout_email', t('Please enter a valid email address.'));
  }
}

/**
 * Checkout form submit proccess.
 * Sending the 2 mails.
 */
function basic_cart_checkout_form_submit($form, &$form_state) {
  // %ORDER_DETAILS% placeholder.
  $order_details = '';
  $cart = basic_cart_get_cart();
  foreach ($cart as $nid => $node) {
    $order_details .= $node->basic_cart_quantity . ' x ' . $node->title . "\n";
  }
  $order_details .= "\n";
  // Pleaceholder replacement.
  $search = array('%CUSTOMER_NAME', '%CUSTOMER_EMAIL', '%CUSTOMER_PHONE', '%CUSTOMER_ADDRESS', '%CUSTOMER_MESSAGE', '%ORDER_DETAILS');
  $replace = array(
    $form_state['values']['basic_cart_checkout_name'], $form_state['values']['basic_cart_checkout_email'],
    $form_state['values']['basic_cart_checkout_phone'], $form_state['values']['basic_cart_checkout_address'],
    $form_state['values']['basic_cart_checkout_message'], $order_details,
  );

  // Admin mail.
  $message_html = variable_get('basic_cart_admin_message');
  $message_html = str_replace($search, $replace, $message_html);
  $params['admin_message'] = filter_xss($message_html);

  $site_mail = variable_get('site_mail');

  // Sending mail.
  $message = drupal_mail('basic_cart', 'admin_mail', $site_mail, language_default(), $params);

  $mails_sent = 0;
  if ($message['result']) {
    $mails_sent++;
  }
  
  // User email.
  $send_user_mail = variable_get('basic_cart_send_user_message');
  if ($send_user_mail) {
    $message_html = variable_get('basic_cart_user_message');
    $message_html = str_replace($search, $replace, $message_html);
    $params['user_message'] = filter_xss($message_html);

    // Sending mail.
    $message = drupal_mail('basic_cart', 'user_mail', $form_state['values']['basic_cart_checkout_email'], language_default(), $params);

    if ($message['result']) {
      $mails_sent++;
    }
  }

  if ($mails_sent == 2) {
    basic_cart_empty_cart();
    drupal_goto('checkout/thank-you');
  }
  else {
    drupal_set_message(t('There was a problem in submitting your order. Please try again later.'), 'error');
  }
}


/**
 * Implements hook_mail().
 */
function basic_cart_mail($key, &$message, $params) {
  switch($key) {
    case 'admin_mail':
      $message['subject'] = check_plain(variable_get('basic_cart_admin_subject'));
      $message['body'][] = $params['admin_message'];
      break;

    case 'user_mail':
      $message['subject'] = check_plain(variable_get('basic_cart_user_subject'));
      $message['body'][] = $params['user_message'];
      break;
  }
}

/**
 * Callback for thank you page.
 */
function basic_cart_checkout_thank_you() {
  $title = variable_get('basic_cart_thank_you_title');
  drupal_set_title($title);
  $message = variable_get('basic_cart_thank_you_message');
  return nl2br(filter_xss($message));
}

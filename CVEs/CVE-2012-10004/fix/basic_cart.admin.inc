<?php

/**
 * @file
 * Basic cart admin settings forms
 */

/**
 * Callback for the admin configuration page
 */
function basic_cart_admin_content_type() {
  $node_types = node_type_get_types();
  if (empty($node_types)) {
    return NULL;
  }

  $options = array();
  foreach ($node_types as $node_type => $type) {
    $options[$node_type] = check_plain($type->name);
  }

  $form['content_type'] = array(
    '#title' => t('Content type selection'),
    '#type' => 'fieldset',
    '#description' => t('Please select the content types for which you wish to have the "Add to cart" option.'),
  );

  $form['content_type']['basic_cart_content_types'] = array(
    '#title' => t('Content types'),
    '#type' => 'checkboxes',
    '#options' => $options,
    '#default_value' => variable_get('basic_cart_content_types', array()),
  );

  $form['messages'] = array(
    '#title' => t('Email messages'),
    '#type' => 'fieldset',
    '#description' => t('Here you can customize the mails sent to the site administrator and customer, after an order is placed.'),
  );

  $form['messages']['basic_cart_admin_subject'] = array(
    '#title' => t('Subject'),
    '#type' => 'textfield',
    '#description' => t("Subject field for the administrator's email."),
    '#default_value' => variable_get('basic_cart_admin_subject'),
  );

  $form['messages']['basic_cart_admin_message'] = array(
    '#title' => t('Admin email'),
    '#type' => 'textarea',
    '#description' => t('This email will be sent to the site administrator just after an order is placed. Availabale tokes: %CUSTOMER_NAME, %CUSTOMER_EMAIL, %CUSTOMER_PHONE, %CUSTOMER_ADDRESS, %CUSTOMER_MESSAGE, %ORDER_DETAILS'),
    '#default_value' => variable_get('basic_cart_admin_message'),
  );

  $form['messages']['basic_cart_send_user_message'] = array(
    '#title' => t('Send an email to the customer after an order is placed'),
    '#type' => 'checkbox',
    '#default_value' => variable_get('basic_cart_send_user_message'),
  );

  $form['messages']['basic_cart_user_subject'] = array(
    '#title' => t('Subject'),
    '#type' => 'textfield',
    '#description' => t("Subject field for the user's email."),
    '#default_value' => variable_get('basic_cart_user_subject'),
  );

  $form['messages']['basic_cart_user_message'] = array(
    '#title' => t('User email'),
    '#type' => 'textarea',
    '#description' => t('This email will be sent to the user just after an order is placed. Availabale tokes: %CUSTOMER_NAME, %CUSTOMER_EMAIL, %CUSTOMER_PHONE, %CUSTOMER_ADDRESS, %CUSTOMER_MESSAGE, %ORDER_DETAILS'),
    '#default_value' => variable_get('basic_cart_user_message'),
  );

  $form['thank_you'] = array(
    '#title' => t('Thank you page'),
    '#type' => 'fieldset',
    '#description' => t('Here you can customize the thank you page.'),
  );

  $form['thank_you']['basic_cart_thank_you_title'] = array(
    '#title' => t('Title'),
    '#type' => 'textfield',
    '#description' => t('Thank you page title.'),
    '#default_value' => variable_get('basic_cart_thank_you_title'),
  );

  $form['thank_you']['basic_cart_thank_you_message'] = array(
    '#title' => t('Text'),
    '#type' => 'textarea',
    '#description' => t('Thank you page text.'),
    '#default_value' => variable_get('basic_cart_thank_you_message'),
  );

  return system_settings_form($form);
}

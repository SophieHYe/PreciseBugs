<?php
function rsvpmaker_to_stripe( $rsvp ) {

	global $post;

	$vars['description'] = $post->post_title;

	$vars['name'] = $rsvp['first'] . ' ' . $rsvp['last'];

	if ( isset( $_GET['rsvp'] ) ) {

		$vars['rsvp_id'] = (int) $_GET['rsvp'];

	} else {
		$vars['rsvp_id'] = $rsvp['id'];
	}

	$vars['rsvp_post_id'] = $post->ID;

	$include = array( 'amount', 'rsvp_id', 'email', 'event' );

	foreach ( $rsvp as $index => $value ) {

		if ( in_array( $index, $include ) ) {

			$vars[ $index ] = $value;
		}
	}
	// transform

	return rsvpmaker_stripe_form( $vars );

}



// called from Gutenberg init

function rsvpmaker_stripecharge( $atts ) {

	if ( is_admin() || wp_is_json_request() ) {

		return;
	}

	global $current_user, $rsvp_options;

	$vars['description'] = ( ! empty( $atts['description'] ) ) ? $atts['description'] : __( 'charge from', 'rsvpmaker' ) . ' ' . get_bloginfo( 'name' );

	$vars['paymentType'] = $paymentType = ( empty( $atts['paymentType'] ) ) ? 'once' : $atts['paymentType'];

	$vars['paypal'] = (empty($atts['paypal'])) ? 0 : $atts['paypal'];

	$show = ( ! empty( $atts['showdescription'] ) && ( $atts['showdescription'] == 'yes' ) ) ? true : false;

	if ( $paymentType == 'schedule' ) {

		$months = array( 'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december' );

		$index = date( 'n' ) - 1;

		if ( isset( $_GET['next'] ) ) {

			if ( $index == 11 ) {

				$index = 0;

			} else {
				$index++;
			}
		}

		$month = $months[ $index ];

		$vars['amount'] = $atts[ $month ];

		$vars['description'] = $vars['description'] . ': ' . ucfirst( $month );

		if ( ! empty( $current_user->user_email ) ) {

			$vars['email'] = $current_user->user_email;
		}

		return rsvpmaker_stripe_form( $vars, $show );

	}

	$vars['amount'] = ( ! empty( $atts['amount'] ) ) ? $atts['amount'] : '';

	if ( $paymentType != 'once' ) {

		$vars['description'] .= ' ' . $paymentType;
	}

	return rsvpmaker_stripe_form( $vars, $show );

	// return rsvpmaker_stripe_form($vars,$show);
}


$rsvpmaker_stripe_form = '';

function rsvpmaker_stripe_form( $vars, $show = false ) {
	global $rsvp_options;

	rsvpmaker_debug_log( $vars, 'rsvpmaker_stripe_form' );

	global $post, $rsvp_options, $current_user, $button, $rsvpmaker_stripe_form, $wpdb;
	if ( ! $show ) {

		$show = ( ! empty( $vars['showdescription'] ) && ( $vars['showdescription'] == 'yes' ) ) ? true : false;
	}

	$currency = ( empty( $rsvp_options['paypal_currency'] ) ) ? 'usd' : strtolower( $rsvp_options['paypal_currency'] );

	$vars['currency'] = $currency;

	$rsvpmaker_stripe_checkout_page_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_status='publish' AND  post_content LIKE '%[rsvpmaker_stripe_checkout]%' " );

	if ( empty( $rsvpmaker_stripe_checkout_page_id ) ) {

		rsvpmaker_debug_log( $rsvpmaker_stripe_checkout_page_id, 'attempting rsvpmaker_stripe_checkout_page_id' );

		$postvar['post_content'] = '<!-- wp:shortcode -->

	[rsvpmaker_stripe_checkout]

	<!-- /wp:shortcode -->

	<!-- wp:paragraph -->

<p>Secure payment processing by <a href="https://stripe.com/" target="_blank">Stripe</a>.</p>

<!-- /wp:paragraph -->

';

		$postvar['post_title'] = 'Payment';

		$postvar['post_status'] = 'publish';

		$postvar['post_author'] = 1;

		$postvar['post_type'] = 'rsvpmaker';

		$rsvpmaker_stripe_checkout_page_id = wp_insert_post( $postvar );

		update_post_meta( $rsvpmaker_stripe_checkout_page_id, '_rsvpmaker_special', 'Payment checkout page for Stripe' );

		rsvpmaker_debug_log( $rsvpmaker_stripe_checkout_page_id, 'new checkout page' );

	}

	$currency_symbol = '';

	if ( isset( $vars['currency'] ) ) {

		if ( $vars['currency'] == 'usd' ) {

			$currency_symbol = '$';

		} elseif ( $vars['currency'] == 'eur' ) {

			$currency_symbol = '€';
		}
	}

	$idempotency_key = 'stripe_' . time() . '_' . rand( 0, 100000000000 );

	update_option( $idempotency_key, $vars );

	$url = get_permalink( $rsvpmaker_stripe_checkout_page_id );
	$keys = get_rsvpmaker_stripe_keys();
	if(empty($keys['pk']) && $keys['sandbox_pk'])
		;//if Stripe not enabled
	elseif ( isset( $vars['paymentType'] ) && ( $vars['paymentType'] == 'donation' ) ) {
		if(isset($_GET['amount']))
			$vars['amount'] = sanitize_text_field($_GET['amount']); //needed when both Stripe and PayPal are active
		$output = sprintf( '<form action="%s" method="get">%s (%s, %s %s): <input type="text" name="amount" value="%s"><br />%s<br /><textarea name="stripenote" cols="80" rows="2"></textarea><br /><input type="hidden" name="txid" value="%s"><button class="stripebutton">%s</button>%s</form>', $url, __( 'Amount', 'rsvpmaker' ), esc_attr( strtoupper( $vars['currency'] ) ), __('minimum','rsvpmaker'), $rsvp_options['payment_minimum'], esc_attr( $vars['amount'] ), __('Note','rsvpmaker'), esc_attr( $idempotency_key ), __( 'Pay with Card' ), rsvpmaker_nonce('return') );
	} else {
		$output = sprintf( '<form action="%s" method="get"><input type="hidden" name="txid" value="%s"><button class="stripebutton">%s</button>%s</form>', $url, esc_attr( $idempotency_key ), __( 'Pay with Card' ), rsvpmaker_nonce('return') );
	}

	if(!empty($vars['paypal'])) {
		$output .= '<p>'. __('Credit card processing by Stripe','rsvpmaker').'</p>'.rsvpmaker_paypay_button_embed($vars);
	}

	if ( $show ) {
		if(isset($_GET['amount']))
			$vars['amount'] = sanitize_text_field($_GET['amount']); //needed when both Stripe and PayPal are active
		$output .= sprintf( '<p>%s%s %s<br />%s</p>', $currency_symbol, esc_html( $vars['amount'] ), esc_html( $rsvp_options['paypal_currency'] ), esc_html( $vars['description'] ) );
	}

	return $output;

}

function rsvpmaker_stripe_validate($public, $secret) {
	require_once 'stripe-php/init.php';
	try {
		$stripe = new \Stripe\StripeClient($secret);
		$history = $stripe->balanceTransactions->all( array( 'limit' => 10 ) );
	} catch (\Stripe\Exception\InvalidRequestException $e) {
		$output =  'Status is:' . $e->getHttpStatus() . '\n';
		$output .= 'Type is:' . $e->getError()->type . '\n';
		$output .= 'Code is:' . $e->getError()->code . '\n';
		// param is '' in this case
		$output .=  'Param is:' . $e->getError()->param . '\n';
		$output .=   'Message is:' . $e->getError()->message . '\n';
		rsvpmaker_debug_log($output,'stripe error details');
		return '<span style="color:red">'.$e->getError()->message.'</span>';
	  } catch (\Stripe\Exception\AuthenticationException $e) {
		$output =  'Status is:' . $e->getHttpStatus() . '\n';
		$output .= 'Type is:' . $e->getError()->type . '\n';
		$output .= 'Code is:' . $e->getError()->code . '\n';
		// param is '' in this case
		$output .=  'Param is:' . $e->getError()->param . '\n';
		$output .=   'Message is:' . $e->getError()->message . '\n';
		rsvpmaker_debug_log($output,'stripe error details');
		return '<span style="color:red">'.$e->getError()->message.'</span>';
	  } catch (\Stripe\Exception\ApiConnectionException $e) {
		$output =  'Status is:' . $e->getHttpStatus() . '\n';
		$output .= 'Type is:' . $e->getError()->type . '\n';
		$output .= 'Code is:' . $e->getError()->code . '\n';
		// param is '' in this case
		$output .=  'Param is:' . $e->getError()->param . '\n';
		$output .=   'Message is:' . $e->getError()->message . '\n';
		rsvpmaker_debug_log($output,'stripe error details');
		return '<span style="color:red">'.$e->getError()->message.'</span>';
	  } catch (\Stripe\Exception\ApiErrorException $e) {
		$output =  'Status is:' . $e->getHttpStatus() . '\n';
		$output .= 'Type is:' . $e->getError()->type . '\n';
		$output .= 'Code is:' . $e->getError()->code . '\n';
		// param is '' in this case
		$output .=  'Param is:' . $e->getError()->param . '\n';
		$output .=   'Message is:' . $e->getError()->message . '\n';
		rsvpmaker_debug_log($output,'stripe error details');
		return '<span style="color:red">'.$e->getError()->message.'</span>';
	  } catch (Exception $e) {
		return '<span style="color:red">Error'.var_export($e,true).'</span>';// Something else happened, completely unrelated to Stripe
	  }
	  //nothing blew up!
	  return ' <span style="color: green; font-weight: bold;">'.__('Connected','rsvpmaker').'</span>';
}

function rsvpmaker_stripe_checkout() {

	rsvpmaker_debug_log( 'rsvpmaker_stripe_checkout' );

	global $post, $rsvp_options, $current_user;

	$keys = get_rsvpmaker_stripe_keys();

	if ( empty( $_GET['txid'] ) ) {

		return;
	}

	ob_start();

	$varkey = $idempotency_key = sanitize_text_field( $_GET['txid'] );

	$vars = get_option( $idempotency_key );

	if ( empty( $vars ) ) {

		return '<p>' . __( 'No pending payment found for', 'rsvpmaker' ) . ' ' . esc_html( $idempotency_key ) . '</p>';
	}

	if ( $vars['paymentType'] == 'donation' ) {

		if ( empty( $_GET['amount'] ) ) {

			return '<p>No amount given</p>';
		}

		$vars['amount'] = sanitize_text_field( $_GET['amount'] );

	}

	if($vars['amount'] < $rsvp_options['payment_minimum']) {
		do_action('rsvpmaker_possible_card_testing',$vars);
		return '<p>Transactions of less than '.$rsvp_options['payment_minimum'].' not accepted.</p>';
	}

	if(!empty($_GET['stripenote']))
		$vars['note'] = sanitize_text_field($_GET['stripenote']);

	update_option( $idempotency_key, $vars );
	
	require_once 'stripe-php/init.php';

	if ( ! empty( $vars['email'] ) ) {

		$email = sanitize_email( $vars['email'] );

		$name = ( empty( $vars['name'] ) ) ? '' : sanitize_text_field( $vars['name'] );

	} else {

		$email = ( empty( $current_user->user_email ) ) ? '' : $current_user->user_email;

		$wpname = '';
		if ( ! empty( $current_user->ID ) ) {
			$userdata = get_userdata( $current_user->ID );
			if ( $userdata->first_name ) {
				$wpname = $userdata->first_name . ' ' . $userdata->last_name;
			} else {
				$wpname = $userdata->display_name;
			}
		}
		$name = ( empty( $wpname ) ) ? '' : $wpname;
	}

	$public = $keys['pk'];

	$secret = $keys['sk'];

	if ( strpos( $public, 'test' ) ) {

		$vars['test'] = 'TEST TRANSACTION';
	}

	$currency_symbol = '';

	if ( $vars['currency'] == 'usd' ) {

		$currency_symbol = '$';

	} elseif ( $vars['currency'] == 'eur' ) {

		$currency_symbol = '€';
	}

	$paylabel = __( 'Pay', 'rsvpmaker' ) . ' ' . $currency_symbol . esc_attr( $vars['amount'] ) . ' ' . esc_attr( strtoupper( $vars['currency'] ) );

	\Stripe\Stripe::setApiKey( $secret );

	\Stripe\Stripe::setAppInfo(
		'WordPress RSVPMaker events management plugin',
		get_rsvpversion(),
		'https://rsvpmaker.com'
	);

	rsvpmaker_debug_log( 'call to PaymentIntent' );

	$intent = \Stripe\PaymentIntent::create(
		array(

			'amount'               => $vars['amount'] * 100,

			'currency'             => $vars['currency'],

			'description'          => $vars['description'],

			'payment_method_types' => array( 'card' ),

			'statement_descriptor' => substr( 'Paid on ' . sanitize_text_field($_SERVER['SERVER_NAME']), 0, 21 ),

		),
		array( 'idempotency_key' => $idempotency_key )
	);

	update_post_meta( $post->ID, $varkey, $vars );

	$price = $vars['amount'] * 100;

	?>

<!-- Stripe library must be loaded stripe.com per https://stripe.com/docs/js/including -->

<script src="https://js.stripe.com/v3/"></script>

<!-- We'll put the success / error messages in this element -->

<div id="card-result" role="alert"></div>

<div id="stripe-checkout-form">

<form id="payee-form">

<div><input id="stripe-checkout-name" name="name" placeholder="<?php esc_html_e( 'Your Name Here', 'rsvpmaker' ); ?>" value="<?php echo esc_attr( $name ); ?>"></div>

<div><input id="stripe-checkout-email" name="email" placeholder="email@example.com" value="<?php echo esc_attr( $email ); ?>"></div>

<div id="card-element">

  <!-- Elements will create input elements here -->

</div>



<p><button id="card-button" class="stripebutton" data-secret="<?php echo esc_attr( $intent->client_secret ); ?>">

	<?php echo esc_html( $paylabel ); ?>

</button></p>

</form>

	<?php

	if ( strpos( $public, 'test' ) && ! isset( $_GET['hidetest'] ) ) {

		printf( '<p>%s</p>', __( 'Stripe is in TEST mode. To simulate a transaction, use:<br />Credit card 4111 1111 1111 1111<br />Any future date<br />Any three digit CVC code<br />Any 5-digit postal code', 'rsvpmaker' ) );
	}

	?>

</div>

<script>

var stripe = Stripe('<?php echo esc_attr($public); ?>');

var elements = stripe.elements();

var style = {

  base: {

	iconColor: '#111111',

	color: "#111111",

	fontWeight: 400,

	fontSize: '16px',

	'::placeholder': {

	color: '#333333',

	},

	'::-ms-clear': {

	backgroundColor: '#fff',

	},

	  },

	empty: {

	backgroundColor: '#fff',

	  },

	completed: {

	backgroundColor: '#eee',

	  },

};



var card = elements.create("card", { style: style });

card.mount("#card-element");



card.addEventListener('change', ({error}) => {

  const displayError = document.getElementById('card-result');

  if (error) {

	displayError.textContent = error.message;

  } else {

	displayError.textContent = '';

  }

});

var cardFields = document.getElementById('stripe-checkout-form');

var submitButton = document.getElementById('card-button');

var cardResult = document.getElementById('card-result');

var clientSecret = document.getElementById('card-button').getAttribute('data-secret');

submitButton.addEventListener('click', function(ev) {
ev.preventDefault();
var name = document.getElementById('stripe-checkout-name').value;
var email = document.getElementById('stripe-checkout-email').value;
var successurl = '<?php echo site_url( '/wp-json/rsvpmaker/v1/stripesuccess/' . $idempotency_key ); ?>';
if((name == '') || (email == '')){
	cardResult.innerHTML = 'Name and email are both required';
	return;
}
cardResult.innerHTML = '<?php esc_html_e( 'Please wait', 'rsvpmaker' ); ?>';
cardResult.style.cssText = 'background-color: #fff; padding: 10px;';

  stripe.confirmCardPayment(clientSecret, {
	payment_method: {
	  card: card,
	  billing_details: {
		name: name,
		email: email,
	  }
	}
  }).then(function(result) {
	if (result.error) {
		cardResult.innerHTML = result.error.message;
	  console.log(result.error.message);
	  console.log(result);
	} else {
	submitButton.style = 'display: none';
	cardFields.style = 'display: none';
	  if (result.paymentIntent.status === 'succeeded') {
		  console.log(result);
		cardResult.innerHTML = '<?php esc_html_e( 'Recording payment', 'rsvpmaker' ); ?> ...';
		const form = new FormData(document.getElementById('payee-form'));
		fetch(successurl, {
  method: 'POST',

  body: form,

})

		.then((response) => {

			return response.json();

		})

		.then((myJson) => {

			console.log(myJson);

			if(!myJson.name)			

				cardResult.innerHTML = '<?php esc_html_e( 'Payment processed, but may not have been recorded correctly', 'rsvpmaker' ); ?>';

			else

				cardResult.innerHTML = '<?php esc_html_e( 'Payment processed for', 'rsvpmaker' ); ?> '+myJson.name+', '+myJson.description+' <?php echo esc_attr($currency_symbol); ?>'+myJson.amount+' '+myJson.currency.toUpperCase();

		});

	  }

	}

  });

});

</script>

	<?php

	return ob_get_clean();

}



function stripe_log_by_email( $email, $months = 0 ) {

	global $wpdb;

	if ( empty( $email ) ) {

		return '';
	}

	$log = '';

	$sql = "SELECT * FROM $wpdb->postmeta WHERE meta_key='rsvpmaker_stripe_payment' AND meta_value LIKE '%" . $email . "%' ORDER BY meta_id DESC";

	$results = $wpdb->get_results( $sql );

	if ( empty( $results ) ) {

		return '';
	}

	if ( $months ) {

		$start = strtotime( '-' . $months . ' months' );
	}

	foreach ( $results as $row ) {

		$vars = unserialize( $row->meta_value );

		$timestamp = strtotime( $vars['timestamp'] );

		if ( $months && ( $timestamp < $start ) ) {
			break;
		}

		foreach ( $vars as $name => $value ) {

				$log .= $name . ': ' . $value . "\n";

		}

			$log .= "\n";

	}

	return wpautop( $log );

}

function rsvpmaker_stripe_payment_log( $vars, $confkey ) {
	global $post, $current_user, $wpdb;
	$vars['timestamp'] = rsvpmaker_date( 'r' );
	if ( ! empty( $vars['email'] ) ) {
		rsvpmaker_stripe_notify( $vars );
	}
	$rsvpmaker_stripe_checkout_page_id = get_option( 'rsvpmaker_stripe_checkout_page_id' );
	$meta_id = add_post_meta( $rsvpmaker_stripe_checkout_page_id, 'rsvpmaker_stripe_payment', $vars );
	do_action( 'rsvpmaker_stripe_payment', $vars );
	wp_schedule_single_event( time() + 300, 'stripe_balance_history_cron' ); // update stripe history table in 5 minutes
}

function rsvpmaker_stripe_notify( $vars ) {

	// $receipt = get_option('rsvpmaker_stripe_receipt');
	$receipt = true;

	if ( ! empty( $vars['rsvp_id'] ) ) {
		rsvp_confirmation_after_payment( $vars['rsvp_id'] );

		return;

	}

	$keys = get_rsvpmaker_stripe_keys();

	$public = $keys['pk'];

	$secret = $keys['sk'];

	$to = $keys['notify'];

	if ( empty( $to ) ) {

		return;
	}

	$mail['to'] = $to;

	$mail['from'] = get_option( 'admin_email' );

	$mail['fromname'] = get_option( 'blogname' );

	$mail['html'] = '';

	foreach ( $vars as $index => $value ) {

		$mail['html'] .= sprintf( '<div>%s: %s</div>', $index, esc_html( $value ) );

	}

	$mail['subject'] = 'Stripe payment from ' . $vars['name'];

	rsvpmailer( $mail );
	if ( $receipt ) {
		$mail['to']      = $vars['email'];
		$mail['subject'] = __( 'Confirming your payment', 'rsvpmaker' ) . ': ' . $vars['description'];
		rsvpmailer( $mail );
	}

}

function rsvpmaker_stripe_report() {

	global $wpdb;

	echo '<h1>Stripe and PayPal Charges</h1>';

	if ( isset( $_GET['history'] ) ) {
		stripe_balance_history( (int) $_GET['history'] );
	}
	$keys = get_rsvpmaker_stripe_keys();
	if ( !empty( $keys ) && isset( $keys['pk'] ) ) {
		printf(
			'<div style="padding: 5px; border: thin dotted #000;"><h3>Retrieve Transactions from Stripe Service</h3>
	
		<p>Includes fees, refunds, and payouts</p>
	
		<form method="get" action="%s"><input type="hidden" name="post_type" value="rsvpmaker" /><input type="hidden" name="page" value="rsvpmaker_stripe_report" />
	
		Up to <select name="history">
	
		<option value="10">10</option>
	
		<option value="20" selected="selected">20</option>
	
		<option value="50">50</option> 
	
		<option value="100">100</option> 
	
		</select>transactions<br />starting <input type="text" name="date" placeholder="YYYY-mm-dd"> (optional) <br /><input type="checkbox" name="payouts" value="1"> Show payouts to bank<br />
		%s
		<button>Get</button></form></div>',
			admin_url( 'edit.php' ),
			rsvpmaker_nonce('return')
		);
	}

	$tx = rsvpmaker_stripe_transactions( 100 );
	echo wp_kses_post( $tx['content'] );
	echo '<h3>Export Format</h3>
	<p>Formatted for copy-paste into Excel or other spreadsheet program
	<br />
	<textarea rows="20" style="width: 100%">' . $tx['export'] . '</textarea></p>';

}

function stripe_latest_logged() {
	global $wpdb;
	$keys = get_rsvpmaker_stripe_keys();
	if ( empty( $keys ) || empty( $keys['pk'] ) ) {
		return;
	}
	$stripetable = rsvpmaker_money_table();
	if ( ! $wpdb->get_results( "show tables like '$stripetable' " ) ) {
		stripe_balance_history( 200, false );
	}
	return $wpdb->get_var( "SELECT date FROM $stripetable ORDER BY date DESC" );
}

function rsvpmaker_stripe_transactions_list( $limit = 50, $where = '' ) {
	global $wpdb;
	$stripetable = rsvpmaker_money_table();
	return $wpdb->get_results( "SELECT name,email,description,date,status,transaction_id,amount,fee, format((amount - fee),2) as yield FROM $stripetable $where ORDER BY date DESC LIMIT 0, $limit " );
}

function rsvpmaker_stripe_transactions_no_user( $limit = 50, $recent = true ) {
	global $wpdb;
	$stripetable = rsvpmaker_money_table();
	$where       = ( $recent ) ? ' AND date > DATE_SUB(CURDATE(), INTERVAL 3 MONTH) ' : '';
	return $wpdb->get_results( "SELECT * FROM $stripetable WHERE user_id=0 $where ORDER BY date DESC" );
}

function rsvpmaker_stripe_latest_transaction_by_user( $user_id, $start_date = '' ) {
	global $wpdb;
	$keys = get_rsvpmaker_stripe_keys();
	if ( empty( $keys ) || empty( $keys['pk'] ) ) {
		return;
	}
	$where       = ( $start_date ) ? " AND date > '$start_date' " : '';
	$stripetable  = rsvpmaker_money_table();
	$sql         = "SELECT * FROM $stripetable WHERE user_id=$user_id $where ORDER BY date DESC";
	return $wpdb->get_row( $sql );
}

add_action( 'stripe_balance_history_cron', 'stripe_balance_history_cron' );

function stripe_balance_history_cron() {
	stripe_balance_history( 20, false );
}

function stripe_balance_history( $limit = 20, $output = true ) {
	global $wpdb;
	$keys = get_rsvpmaker_stripe_keys();
	if ( empty( $keys ) || empty( $keys['pk'] ) ) {
		return;
	}
	$public = $keys['pk'];
	$secret = $keys['sk'];

	$stripetable = rsvpmaker_money_table();

	rsvpmaker_debug_log( 'call to stripe_balance_history' );

	require_once 'stripe-php/init.php';
	\Stripe\Stripe::setApiKey( $secret );

	\Stripe\Stripe::setAppInfo(
		'WordPress RSVPMaker events management plugin',
		get_rsvpversion(),
		'https://rsvpmaker.com'
	);

	$stripe = new \Stripe\StripeClient( $secret );

	$history = $stripe->balanceTransactions->all( array( 'limit' => $limit ) );

	$charges = $stripe->charges->all( array( 'limit' => $limit * 5 ) );

	foreach ( $charges->data as $charge ) {
		$names[ $charge->balance_transaction ]        = $charge->billing_details->name;
		$emails[ $charge->balance_transaction ]       = $charge->billing_details->email;
		$descriptions[ $charge->balance_transaction ] = $charge->description;
	}

	if ( isset( $_GET['date'] ) ) {

		$startdate = strtotime( $_GET['date'] );
	}

	$table = '';

	foreach ( $history->data as $index => $data ) {

		if ( ( $data->reporting_category == 'payout' ) && empty( $_GET['payouts'] ) ) {

			continue;
		}

		if ( isset( $startdate ) && ( $data->created < $startdate ) ) {

			continue;
		}

		$amount = number_format( ( $data->amount / 100 ), 2 );

		$fee = number_format( ( $data->fee / 100 ), 2 );

		$yield = $amount - $fee;

		$date                          = date( 'Y-m-d H:i', $data->created );
		$name                          = ( empty( $names[ $data->id ] ) ) ? '' : $names[ $data->id ];
		$email                         = ( empty( $emails[ $data->id ] ) ) ? '' : $emails[ $data->id ];
		$description                   = ( empty( $descriptions[ $data->id ] ) ) ? '' : $descriptions[ $data->id ];
		$user                          = get_user_by( 'email', $email );
		$user_id                       = ( empty( $user->ID ) ) ? 0 : $user->ID;
		$tablerow[ $date . $data->id ] = "$date,$name,$email,$description,$amount,$fee,$yield\n";
		$sql                           = "select transaction_id FROM $stripetable WHERE transaction_id='$data->id'";
		$check                         = $wpdb->get_var( $sql );
		// echo '<div>check: '.$sql.'<br />'.$check.'</div>';
		if ( ! $check ) {
			$sql = $wpdb->prepare(
				"INSERT INTO $stripetable SET name=%s,email=%s,description=%s,amount=%s,fee=%s,date=%s,status='Stripe',transaction_id=%s, user_id=%d",
				$name,
				$email,
				$description,
				$amount,
				$fee,
				$date,
				$data->id,
				$user_id
			);
			// echo '<div>'.$sql.'</div>';
			$wpdb->query( $sql );
		}
		if ( $data->fee ) {

			$fees[ $data->id ] = $fee;
		}

		if ( $data->reporting_category == 'refund' ) {

			$refunds[ $data->id ] = array(
				'amount' => $amount,
				'date'   => $date,
			);
		}
		if ( $output ) {
			printf( '<p>%s %s<br />%s<br />Fee: %s %s<br />%s</p>', esc_html( $name ), esc_html( $date ), number_format( ( $data->amount / 100 ), 2 ), number_format( ( $data->fee / 100 ), 2 ), esc_html( $data->reporting_category ), esc_html( $data->id ) );
		}
	}

	if ( ! $output ) {
		return;
	}

	if ( ! empty( $tablerow ) ) {
		ksort( $tablerow );
		$table = implode( '', $tablerow );
		echo '<h3>Export Format</h3><pre>Date,Name,Amount,Fee,Yield' . "\n" . wp_kses_post( $table ) . '</pre>';
	}

	if ( ! empty( $fees ) ) {
		echo '<h2>Fees</h2>';

		$feetotal = 0;

		foreach ( $fees as $index => $fee ) {
			$feetotal += $fee;
			printf( '<p>%s %s</p>', esc_html( $index ), esc_html( $fee ) );
		}

		printf( '<p>Total Fees: %s</p>', esc_html( $feetotal ) );
	}

	if ( ! empty( $refunds ) ) {
		echo '<h2>Refunds</h2>';
		$rtotal = 0;
		foreach ( $refunds as $index => $refund ) {
			$rtotal += $refund['amount'];
			printf( '<p>%s %s %s</p>', esc_html( $index ), esc_html( $refund['amount'] ), esc_html( $refund['date'] ) );
		}
		printf( '<p>Total Refunds: %s</p>', esc_html( $rtotal ) );
	}

}

function rsvpmaker_stripe_transactions() {
	$transactions = rsvpmaker_stripe_transactions_list();
	if ( $transactions ) {
		$transaction = (array) $transactions[0];
		$th                   = '<tr>';
		$td                   = '';
		foreach ( $transaction as $column => $value ) {
			$th       .= '<th>' . $column . '</th>';
			$columns[] = $column;
		}
		$th    .= '</tr>';
		$export = implode( ',', $columns ) . "\n";
		fputcsv( $temp_memory, $columns );
		foreach ( $transactions as $index => $transaction ) {
			$row         = array();
			$line        = '';
			$td         .= '<tr>';
			$transaction = (array) $transaction;
			/*
			for future use
			if(!empty($transaction['metadata']) ) {
			//could be used for paid to toastmasters amount
			$metadata = unserialize($transaction['metadata']);
			$transaction['metadata'] = var_export($metadata,true);
			}
			*/
			foreach ( $transaction as $column => $value ) {
				$td .= '<td>' . esc_html( $value ) . '</td>';
				if ( strpos( $value, '"' ) ) {
					$value = str_replace( '"', '\"', $value );
				}
				if ( ! is_numeric( $value ) ) {
					$value = '"' . $value . '"';
				}
				$row[ $column ] = $value;
			}
			$lines[] = implode( ',', $row );
			$td     .= "<td>$yield</td></tr>\n";
		}
		krsort( $lines );
		$export .= implode( "\n", $lines );
		return array(
			'content' => '<h3>50 Most Recent Payments</h3><table>' . $th . $td . '</table>',
			'export'  => $export,
		);
	}
}

<?php
/**
 *
 *  2007-2021 PayPal
 *
 *  NOTICE OF LICENSE
 *
 *  This source file is subject to the Academic Free License (AFL 3.0)
 *  that is bundled with this package in the file LICENSE.txt.
 *  It is also available through the world-wide-web at this URL:
 *  http://opensource.org/licenses/afl-3.0.php
 *  If you did not receive a copy of the license and are unable to
 *  obtain it through the world-wide-web, please send an email
 *  to license@prestashop.com so we can send you a copy immediately.
 *
 *  DISCLAIMER
 *
 *  Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 *  versions in the future. If you wish to customize PrestaShop for your
 *  needs please refer to http://www.prestashop.com for more information.
 *
 *  @author 2007-2021 PayPal
 *  @author 202 ecommerce <tech@202-ecommerce.com>
 *  @copyright PayPal
 *  @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 */

include_once dirname(__FILE__).'/../../config/config.inc.php';
include_once _PS_ROOT_DIR_.'/init.php';
include_once _PS_MODULE_DIR_.'paypal/paypal.php';

/*
 * Instant payment notification class.
 * (wait for PayPal payment confirmation, then validate order)
 */
class PayPalIPN extends PayPal
{

    public function getIPNTransactionDetails($result)
    {
        if (is_array($result) || (strcmp(trim($result), "VERIFIED") === false)) {
            $transaction_id = pSQL($result['txn_id']);

            return array(
                'id_transaction' => $transaction_id,
                'transaction_id' => $transaction_id,
                'id_invoice' => $result['invoice'],
                'currency' => pSQL($result['mc_currency']),
                'total_paid' => (float) $result['mc_gross'],
                'shipping' => (float) $result['mc_shipping'],
                'payment_date' => pSQL($result['payment_date']),
                'payment_status' => pSQL($result['payment_status']),
            );
        } else {
            $transaction_id = pSQL(Tools::getValue('txn_id'));

            return array(
                'id_transaction' => $transaction_id,
                'transaction_id' => $transaction_id,
                'id_invoice' => pSQL(Tools::getValue('invoice')),
                'currency' => pSQL(Tools::getValue('mc_currency')),
                'total_paid' => (float) Tools::getValue('mc_gross'),
                'shipping' => (float) Tools::getValue('mc_shipping'),
                'payment_date' => pSQL(Tools::getValue('payment_date')),
                'payment_status' => pSQL(Tools::getValue('payment_status')),
            );
        }
    }

    public function confirmOrder($custom)
    {
        $result = $this->getResult();

        $payment_status = Tools::getValue('payment_status');
        $mc_gross = Tools::getValue('mc_gross');
        $txn_id = Tools::getValue('txn_id');

        $id_order = (int) Order::getOrderByCartId((int) $custom['id_cart']);
        Context::getContext()->cart = new Cart((int) $custom['id_cart']);

        $address = new Address((int) Context::getContext()->cart->id_address_invoice);
        Context::getContext()->country = new Country((int) $address->id_country);
        Context::getContext()->customer = new Customer((int) Context::getContext()->cart->id_customer);
        Context::getContext()->language = new Language((int) Context::getContext()->cart->id_lang);
        Context::getContext()->currency = new Currency((int) Context::getContext()->cart->id_currency);

        if (isset(Context::getContext()->cart->id_shop)) {
            Context::getContext()->shop = new Shop(Context::getContext()->cart->id_shop);
        }

        if (strcmp(trim($result), "VERIFIED") === false) {
            $details = $this->getIPNTransactionDetails($result);

            if ($id_order) {
                $history = new OrderHistory();
                $history->id_order = (int) $id_order;

                PayPalOrder::updateOrder($id_order, $details);
                $history->changeIdOrderState((int) Configuration::get('PS_OS_ERROR'), $history->id_order);

                $history->addWithemail();
                $history->save();
            }
        } elseif (strcmp(trim($result), "VERIFIED") === 0) {
            $details = $this->getIPNTransactionDetails($result);

            if (version_compare(_PS_VERSION_, '1.5', '<')) {
                $shop = null;
            } else {
                $shop_id = Context::getContext()->shop->id;
                $shop = new Shop($shop_id);
            }

            if ($id_order) {
                $order = new Order((int) $id_order);
                $values = $this->checkPayment($payment_status, $mc_gross, false);
                if ((int) $order->current_state == (int) $values['payment_type']) {
                    return;
                }
                $history = new OrderHistory();
                $history->id_order = (int) $id_order;
                if (PayPalOrder::getOrderById($id_order)) {
                    PayPalOrder::updateOrder($id_order, $details);
                } else {
                    PayPalOrder::saveOrder($id_order, $details);
                }
                $history->changeIdOrderState($values['payment_type'], $history->id_order);

                $history->addWithemail();
                $history->save();
                Db::getInstance()->update('order_payment', array(
                    'transaction_id' => pSQL($details['transaction_id']),
                ), 'order_reference = "'.pSQL($order->reference).'"');
            } else {
                $values = $this->checkPayment($payment_status, $mc_gross, true);
                $customer = new Customer((int) Context::getContext()->cart->id_customer);
                $this->validateOrder(Context::getContext()->cart->id, $values['payment_type'], $values['total_price'], $this->displayName, $values['message'], $details, Context::getContext()->cart->id_currency, false, $customer->secure_key, $shop);
            }
        }
    }

    public function checkPayment($payment_status, $mc_gross_not_rounded, $new_order = false)
    {
        $currency_decimals = is_array(Context::getContext()->currency) ? (int) Context::getContext()->currency['decimals'] : (int) Context::getContext()->currency->decimals;
        $this->decimals = $currency_decimals * _PS_PRICE_DISPLAY_PRECISION_;

        $mc_gross = Tools::ps_round($mc_gross_not_rounded, $this->decimals);

        $cart_details = Context::getContext()->cart->getSummaryDetails(null, true);
        $cart_hash = sha1(serialize(Context::getContext()->cart->nbProducts()));
        $custom = Tools::jsonDecode(Tools::getValue('custom'), true);

        $shipping = $cart_details['total_shipping_tax_exc'];
        $subtotal = $cart_details['total_price_without_tax'] - $cart_details['total_shipping_tax_exc'];
        $tax = $cart_details['total_tax'];

        $total_price = Tools::ps_round($shipping + $subtotal + $tax, $this->decimals);

        if (($new_order) && ($this->comp($mc_gross, $total_price, 2) !== 0)) {
            $payment_type = (int) Configuration::get('PS_OS_ERROR');
            $message = $this->l('Price paid on paypal is not the same that on PrestaShop.').'<br />';
        } elseif (($new_order) && ($custom['hash'] != $cart_hash)) {
            $payment_type = (int) Configuration::get('PS_OS_ERROR');
            $message = $this->l('Cart changed, please retry.').'<br />';
        } else {
            return $this->getDetails($payment_status) + array(
                'payment_status' => $payment_status,
                'total_price' => $total_price,
            );
        }
        return array(
            'message' => $message,
            'payment_type' => $payment_type,
            'payment_status' => $payment_status,
            'total_price' => $total_price,
        );
    }

    public function getDetails($payment_status)
    {
        if ((bool) Configuration::get('PAYPAL_CAPTURE')) {
            $payment_type = (int) Configuration::get('PS_OS_WS_PAYMENT');
            $message = $this->l('Pending payment capture.').'<br />';
        } else {
            if (strcmp($payment_status, 'Completed') === 0) {
                $payment_type = (int) Configuration::get('PS_OS_PAYMENT');
                $message = $this->l('Payment accepted.').'<br />';
            } elseif (strcmp($payment_status, 'Pending') === 0) {
                $payment_type = (int) Configuration::get('PS_OS_PAYPAL');
                $message = $this->l('Pending payment confirmation.').'<br />';
            } elseif (strcmp($payment_status, 'Refunded') === 0) {
                $payment_type = (int) Configuration::get('PS_OS_REFUND');
                $message = $this->l('Payment refunded.').'<br />';
            } else {
                $payment_type = (int) Configuration::get('PS_OS_ERROR');
                $message = $this->l('Cart changed, please retry.').'<br />';
            }
        }

        return array(
            'message' => $message,
            'payment_type' => (int) $payment_type,
        );
    }

    public function getResult()
    {
        if ((int) Configuration::get('PAYPAL_SANDBOX') == 1) {
            $action_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_notify-validate';
        } else {
            $action_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=_notify-validate';
        }

        $request = '';
        foreach ($_POST as $key => $value) {
            $value = urlencode(Tools::stripslashes($value));
            $request .= "&$key=$value";
        }


        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $action_url.$request);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        $content = curl_exec($curl);
        curl_close($curl);
        return $content;
    }
}

if (Tools::getValue('receiver_email') == Configuration::get('PAYPAL_BUSINESS_ACCOUNT')) {
    if (Tools::getIsset('custom')) {
        $ipn = new PayPalIPN();
        $custom = Tools::jsonDecode(Tools::getValue('custom'), true);
        $res = @fopen($custom['id_cart'].'.txt', 'x');
        $seconds = 0;
        if (!$res) {
            while (file_exists($custom['id_cart'].'.txt')) {
                sleep(1);
                $seconds++;
                if ($seconds >= 300) {
                    @rename($custom['id_cart'].'.txt', date('YmdHis').'_'.$custom['id_cart'].'.txt');
                }
            }
        }
        $ipn->confirmOrder($custom);
        if ($res) {
            fclose($res);
            unlink($custom['id_cart'].'.txt');
        }
    }
} elseif (Tools::isSubmit('custom') && (int)Configuration::get('PAYPAL_PAYMENT_METHOD') == HSS) {
    $custom = Tools::jsonDecode(Tools::getValue('custom'), true);
    Db::getInstance()->insert('paypal_hss_email_error', array('id_cart' => $custom['id_cart'], 'email' => Tools::getValue('receiver_email')));
}

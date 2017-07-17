<?php
/**
 * @author    G2A Team
 * @copyright Copyright (c) 2016 G2A.COM
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace g2apay;

class G2APayHelper
{
    const SANDBOX                              = 'sandbox';
    const PRODUCTION                           = 'production';
    const PRODUCTION_URL                       = 'https://checkout.pay.g2a.com/index/';
    const SANDBOX_URL                          = 'https://checkout.test.pay.g2a.com/index/';
    const DISCOUNT                             = 'commerce_discount';
    const SHIPPING                             = 'shipping';
    const REFUND_CAPTURE_LINK                  = 'admin/commerce/manage-orders/g2apay_orders/refund/';
    const COMMERCE_ORDERS_LINK                 = 'admin/commerce/orders/';
    const G2A_PAY_CONFIRMED_ORDERS_LIST_LINK   = '/admin/commerce/manage-orders/g2apay_orders';
    const G2A_IPN_DB_TABLE_NAME                = 'commerce_g2apay_ipn';
    const COMMERCE_PAYMENT_ADMIN_PAGE_NAME     = 'commerce_payment_ui_admin_page';

    /**
     * @param $params
     * @param $api_secret
     * @param null $order_id
     * @return string
     */
    public static function calculateHash($params, $api_secret, $order_id = null)
    {
        if (!is_null($order_id)) {
            $balance        = commerce_payment_order_balance($params);
            $unhashedString = $order_id . self::getValidAmount($balance['amount'])
                . $balance['currency_code'] . $api_secret;
        } else {
            $unhashedString = $params['transactionId'] . $params['userOrderId'] . $params['amount']
                . $api_secret;
        }

        return hash('sha256', $unhashedString);
    }

    /**
     * Return price in correct format.
     *
     * @param $amount
     * @return float
     */
    public static function getValidAmount($amount)
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * @param $server
     * @param $token
     * @return string
     */
    public static function getPaymentUrl($server, $token = null)
    {
        $token = $token ? 'gateway?token=' . $token : null;

        return $server === self::PRODUCTION ? self::PRODUCTION_URL . $token : self::SANDBOX_URL . $token;
    }

    /**
     * This method prepare based on order array which is send to G2A Pay.
     *
     * @param $order
     * @param $settings
     * @return array
     */
    public static function prepareVarsArray($order, $settings)
    {
        $return_url  = url('checkout/' . $order->order_id . '/payment/return/' . $order->data['payment_redirect_key'],
            array('absolute' => true));
        $cancel_url  = url('checkout/' . $order->order_id . '/payment/back/' . $order->data['payment_redirect_key'],
            array('absolute' => true));
        $balance = commerce_payment_order_balance($order);
        $vars    = array(
            'api_hash'    => $settings['api_hash'],
            'hash'        => self::calculateHash($order, $settings['api_secret'], $order->order_id),
            'order_id'    => $order->order_id,
            'amount'      => self::getValidAmount($balance['amount'] / 100),
            'currency'    => $balance['currency_code'],
            'url_failure' => $cancel_url,
            'url_ok'      => $return_url,
            'items'       => self::getItemsArray($order),
            'addresses'   => self::getAddressesArray($order),
        );

        return $vars;
    }

    /**
     * @param $order
     * @return array
     */
    public static function getItemsArray($order)
    {
        global $base_url;

        $order_lines         = field_get_items('commerce_order', $order, 'commerce_line_items');
        $itemsInfo           = array();
        foreach ($order_lines as $order_line) {
            $tmp               = null;
            $product           = null;
            $path              = '';
            $name              = '';
            $line_item         = commerce_line_item_load($order_line['line_item_id']);
            $line_item_wrapper = entity_metadata_wrapper('commerce_line_item', $line_item);
            $tmp               = field_get_items('commerce_line_item', $line_item, 'commerce_product');
            if (isset($tmp[0]['product_id'])) {
                $product = commerce_product_load($tmp[0]['product_id']);
                $path    = self::getLineItemPath($line_item_wrapper);
            } else {
                $name = self::getLineItemTitle($line_item_wrapper);
            }
            $price       = $line_item_wrapper->commerce_unit_price->amount->value() / 100;
            $itemsInfo[] = array(
                'sku'    => isset($product->sku) ? $product->sku : 1,
                'name'   => isset($product->title) ? $product->title : $name,
                'amount' => self::getValidAmount($price * $line_item->quantity),
                'qty'    => (integer) $line_item->quantity,
                'id'     => isset($product->product_id) ? $product->product_id : 1,
                'price'  => self::getValidAmount($price),
                'url'    => $base_url . DIRECTORY_SEPARATOR . $path,
            );
        }

        return $itemsInfo;
    }

    /**
     * @param $order
     * @return array
     */
    public static function getAddressesArray($order)
    {
        $addresses                 = array();
        $billing_profile_id        = $order->commerce_customer_billing['und'][0]['profile_id'];
        $commerce_billing_address  = commerce_customer_profile_load($billing_profile_id)
                                        ->commerce_customer_address['und'][0];
        $shipping_profile_id       = $order->commerce_customer_shipping['und'][0]['profile_id'];
        $commerce_shipping_address = commerce_customer_profile_load($shipping_profile_id)
                                        ->commerce_customer_address['und'][0];

        $addresses['billing'] = array(
            'firstname' => $commerce_billing_address['first_name'],
            'lastname'  => $commerce_billing_address['last_name'],
            'line_1'    => $commerce_billing_address['thoroughfare'],
            'line_2'    => is_null($commerce_billing_address['premise']) ? '' : $commerce_billing_address['premise'],
            'zip_code'  => $commerce_billing_address['postal_code'],
            'company'   => is_null($commerce_billing_address['organisation_name']) ?
                '' : $commerce_billing_address['organisation_name'],
            'city'      => $commerce_billing_address['locality'],
            'county'    => $commerce_billing_address['administrative_area'],
            'country'   => $commerce_billing_address['country'],
        );
        $addresses['shipping'] = array(
            'firstname' => $commerce_shipping_address['first_name'],
            'lastname'  => $commerce_shipping_address['last_name'],
            'line_1'    => $commerce_shipping_address['thoroughfare'],
            'line_2'    => is_null($commerce_shipping_address['premise']) ? '' : $commerce_shipping_address['premise'],
            'zip_code'  => $commerce_shipping_address['postal_code'],
            'company'   => is_null($commerce_shipping_address['organisation_name']) ?
                '' : $commerce_shipping_address['organisation_name'],
            'city'      => $commerce_shipping_address['locality'],
            'county'    => $commerce_shipping_address['administrative_area'],
            'country'   => $commerce_shipping_address['country'],
        );

        return $addresses;
    }

    /**
     * @param $line_item_wrapper
     * @return string
     */
    public static function getLineItemPath($line_item_wrapper)
    {
        if (isset($line_item_wrapper->value()->data['context']['display_path'])) {
            return $line_item_wrapper->value()->data['context']['display_path'];
        } elseif (isset($line_item_wrapper->value()->data['context']['view']['view_name'])) {
            return $line_item_wrapper->value()->data['context']['view']['view_name'];
        }

        return '';
    }

    /**
     * @param $line_item_wrapper
     * @return string
     */
    public static function getLineItemTitle($line_item_wrapper)
    {
        if ($line_item_wrapper->type->value() === self::SHIPPING) {
            $shipping_data   = $line_item_wrapper->value()->data;
            $line_item_title = $shipping_data['shipping_service']['title'];

            return $line_item_title;
        }
        if ($line_item_wrapper->type->value() === self::DISCOUNT) {
            $discount_data = $line_item_wrapper->commerce_unit_price->data->value();
            foreach ($discount_data['components'] as $key => $component) {
                if (!empty($component['price']['data']['discount_component_title'])) {
                    $line_item_title = $component['price']['data']['discount_component_title'];
                    break;
                }
            }
        }

        return $line_item_title;
    }

    /**
     * @param $order_id
     * @param $status
     */
    public static function updateOrderStatus($order_id, $status)
    {
        $order_wrapper         = entity_metadata_wrapper('commerce_order', $order_id);
        $order_wrapper->status = $status;
        $order_wrapper->save();
    }

    /**
     * @param $order_id
     * @param $transaction_id
     * @param $amount
     * @throws \Exception
     */
    public static function addTransactionConfirmation($order_id, $transaction_id, $amount)
    {
        db_insert(self::G2A_IPN_DB_TABLE_NAME)->fields(array(
            'order_id'       => $order_id,
            'transaction_id' => $transaction_id,
            'amount_paid'    => self::getValidAmount($amount) * 100,
        ))->execute();
    }

    /**
     * @param $order_id
     * @return array
     */
    public static function getIpnByOrderId($order_id)
    {
        $query = db_select(self::G2A_IPN_DB_TABLE_NAME, 't')->fields('t', array(
            'id',
            'order_id',
            'transaction_id',
            'status',
            'amount_paid',
            'amount_refunded',
        ))->condition('t.order_id', (int) $order_id)->execute();
        $value = $query->fetchAssoc();

        return $value;
    }

    /**
     * @param $order_id
     * @param $ipn_params
     * @param $status
     * @return bool
     * @throws \Exception
     */
    public static function addRefundConfirmation($order_id, $ipn_params, $status)
    {
        $ipn_db_record = self::getIpnByOrderId($order_id);
        if ($ipn_db_record) {
            $refunded_amount = $ipn_db_record['amount_refunded'] + ($ipn_params['refundedAmount'] * 100);
            db_update(self::G2A_IPN_DB_TABLE_NAME)->fields(array(
                'amount_refunded' => $refunded_amount,
                'status'          => $status,
            ))
            ->condition('order_id', $order_id, '=')
            ->execute();

            return true;
        }
        $refunded_amount = $ipn_params['refundedAmount'] * 100;
        $paid_amount     = $ipn_params['amount'] * 100;
        db_insert(self::G2A_IPN_DB_TABLE_NAME)->fields(array(
            'order_id'        => $order_id,
            'transaction_id'  => $ipn_params['transactionId'],
            'amount_paid'     => $paid_amount,
            'amount_refunded' => $refunded_amount,
            'status'          => $status,
        ))->execute();

        return true;
    }
}

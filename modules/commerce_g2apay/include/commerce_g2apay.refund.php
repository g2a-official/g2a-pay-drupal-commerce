<?php
/**
 * @author    G2A Team
 * @copyright Copyright (c) 2016 G2A.COM
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @param $form
 * @param $form_state
 * @return mixed
 */
function commerce_g2apay_refund_form($form, &$form_state)
{
    libraries_load('g2apay');
    $order_id               = arg(5);
    $form_state['order_id'] = $order_id;
    $ow                     = entity_metadata_wrapper('commerce_order', $order_id);
    $g2apay_ipn             = \g2apay\G2APayHelper::getIpnByOrderId($order_id);
    $max_refund_value       = $g2apay_ipn['amount_paid'] - $g2apay_ipn['amount_refunded'];

    $order_total = $ow->commerce_order_total->value();

    $form['amount'] = array(
        '#type'            => 'textfield',
        '#title'           => t('Refund amount (Captured amount: @total )',
            array('@total' => commerce_currency_format($order_total['amount'], $order_total['currency_code']))),
        '#default_value'   => commerce_currency_amount_to_decimal($max_refund_value, $order_total['currency_code']),
        '#size'            => 16,
    );
    $form = confirm_form($form, t('Are you sure you want to issue a refund?'),
        \g2apay\G2APayHelper::G2A_PAY_CONFIRMED_ORDERS_LIST_LINK, '', t('Refund'), t('Cancel'), 'confirm');

    return $form;
}

/**
 * Refund transaction validate.
 *
 * @param $form
 * @param $form_state
 * @return bool
 */
function commerce_g2apay_refund_form_validate($form, &$form_state)
{
    libraries_load('g2apay');
    $ow               = entity_metadata_wrapper('commerce_order', $form_state['order_id']);
    $g2apay_ipn       = \g2apay\G2APayHelper::getIpnByOrderId($form_state['order_id']);
    $max_refund_value = $g2apay_ipn['amount_paid'] - $g2apay_ipn['amount_refunded'];

    $order_total = $ow->commerce_order_total->value();

    $form_amount = str_replace(',', '.', $form['amount']['#value']);

    try {
        if (!is_numeric($form_amount) || $form_amount <= 0) {
            throw new \g2apay\G2APayException('You must specify a positive numeric amount to refund.');
        }

        if (commerce_currency_decimal_to_amount($form_amount, $order_total['currency_code']) > $max_refund_value) {
            throw new \g2apay\G2APayException('You cannot refund more than it was paid.');
        }
    } catch (\g2apay\G2APayException $e) {
        form_set_error('amount', t($e->getMessage()));

        return false;
    }
}

/**
 * Refund order.
 *
 * @param $form
 * @param $form_state
 */
function commerce_g2apay_refund_form_submit($form, &$form_state)
{
    libraries_load('g2apay');
    try {
        if ($_SERVER['REQUEST_METHOD'] !== \g2apay\G2APayClient::METHOD_POST) {
            throw new \g2apay\G2APayException(t('Invalid request method'));
        }
        $order_id = $form_state['order_id'];

        $form_amount = str_replace(',', '.', $form['amount']['#value']);
        $g2apay_ipn  = \g2apay\G2APayHelper::getIpnByOrderId($order_id);
        $order       = commerce_order_load($order_id);

        $g2a_rest = new \g2apay\G2APayRest();

        $success = $g2a_rest->refundOrder($order, $form_amount, $g2apay_ipn['transaction_id']);

        if (!$success) {
            throw new \g2apay\G2APayException(t('Online refund request failed for amount: ') . $form_amount);
        }
        drupal_set_message(t('Refund successfully for amount: ') . $form_amount);
    } catch (\g2apay\G2APayException $e) {
        drupal_set_message($e->getMessage(), 'error');
    }
    drupal_goto(\g2apay\G2APayHelper::G2A_PAY_CONFIRMED_ORDERS_LIST_LINK);
}

<?php
/**
 * @author    G2A Team
 * @copyright Copyright (c) 2016 G2A.COM
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace g2apay;

class G2APayRest
{
    /**
     * @var array REST base urls grouped by environment
     */
    protected static $REST_BASE_URLS = array(
        'production' => 'https://pay.g2a.com/rest',
        'sandbox'    => 'https://www.test.pay.g2a.com/rest',
    );

    /**
     * @param $order
     * @param $amount
     * @param $transaction_id
     * @return bool
     */
    public function refundOrder($order, $amount, $transaction_id)
    {
        try {
            $payment_method          = commerce_payment_method_instance_load('g2apay|commerce_payment_g2apay');
            $payment_method_settings = $payment_method['settings'];
            $amount                  = G2APayHelper::getValidAmount($amount);

            $data = [
                    'action' => 'refund',
                    'amount' => $amount,
                    'hash'   => $this->generateRefundHash($order, $amount, $transaction_id,
                        $payment_method_settings['api_secret']),
                ];

            $path   = sprintf('transactions/%s', $transaction_id);
            $url    = $this->getRestUrl($path, $payment_method_settings['payment_mode']);
            $client = $this->createRestClient($url, G2APayClient::METHOD_PUT, $payment_method_settings);

            $result = $client->request($data);

            return is_array($result) && isset($result['status']) && strcasecmp($result['status'], 'ok') === 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param $url
     * @param $method
     * @param $payment_method_settings
     * @return G2APayClient
     */
    protected function createRestClient($url, $method, $payment_method_settings)
    {
        $client = new G2APayClient($url);
        $client->setMethod($method);
        $client->addHeader('Authorization', $payment_method_settings['api_hash'] . ';'
            . $this->getAuthorizationHash($payment_method_settings));

        return $client;
    }

    /**
     * @param $order
     * @param $amount
     * @param $transaction_id
     * @param $api_secret
     * @return string
     */
    protected function generateRefundHash($order, $amount, $transaction_id, $api_secret)
    {
        $balance = commerce_payment_order_balance($order);
        $string  = $transaction_id . $order->order_id . G2APayHelper::getValidAmount($balance['amount'] / 100)
            . $amount . $api_secret;

        return hash('sha256', $string);
    }

    /**
     * @param string $path
     * @param $payment_mode
     * @return string
     */
    public function getRestUrl($path = '', $payment_mode)
    {
        $path     = ltrim($path, '/');
        $base_url = self::$REST_BASE_URLS[$payment_mode];

        return $base_url . '/' . $path;
    }

    /**
     * Returns generated authorization hash.
     *
     * @param $payment_method_settings
     * @return string
     */
    public function getAuthorizationHash($payment_method_settings)
    {
        $string = $payment_method_settings['api_hash'] . $payment_method_settings['merchant_email']
            . $payment_method_settings['api_secret'];

        return hash('sha256', $string);
    }
}

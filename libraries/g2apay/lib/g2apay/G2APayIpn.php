<?php
/**
 * @author    G2A Team
 * @copyright Copyright (c) 2016 G2A.COM
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace g2apay;

class G2APayIpn
{
    private $postParams;

    const STATUS_CANCELED                = 'canceled';
    const STATUS_COMPLETE                = 'complete';
    const STATUS_REFUNDED                = 'refunded';
    const STATUS_PARTIALY_REFUNDED       = 'partial_refunded';
    const SUCCESS                        = 'Success';
    const ORDER_PAID_STATUS              = 'completed';
    const ORDER_REFUNDED_STATUS          = '';
    const ORDER_PARTIALY_REFUNDED_STATUS = '';

    /**
     * G2APayIpn constructor.
     */
    public function __construct()
    {
        $this->postParams = $this->createArrayOfRequestParams();
    }

    public function processIpn()
    {
        if ($_SERVER['REQUEST_METHOD'] !== G2APayClient::METHOD_POST) {
            return 'Invalid request method';
        }

        $orderId = isset($this->postParams['userOrderId']) ? $this->postParams['userOrderId'] : false;

        if (!$orderId) {
            return 'Invalid parameters';
        }

        $order = commerce_order_load($orderId);

        if (!$this->comparePrices($this->postParams, $order)) {
            return 'Price does not match';
        }
        if (isset($this->postParams['status']) && $this->postParams['status'] === self::STATUS_CANCELED) {
            return 'Canceled';
        }
        if (!$this->isCalculatedHashMatch($this->postParams)) {
            return 'Calculated hash does not match';
        }
        if (isset($this->postParams['status']) && isset($this->postParams['transactionId'])
            && $this->postParams['status'] === self::STATUS_COMPLETE) {
            G2APayHelper::updateOrderStatus($orderId, self::ORDER_PAID_STATUS);
            G2APayHelper::addTransactionConfirmation($orderId, $this->postParams['transactionId'],
                $this->postParams['amount']);

            return self::SUCCESS;
        }
        if (isset($this->postParams['status']) && isset($this->postParams['refundedAmount'])
            && $this->postParams['status'] === self::STATUS_REFUNDED) {
            G2APayHelper::addRefundConfirmation($orderId, $this->postParams, ucfirst(self::STATUS_REFUNDED));

            return self::SUCCESS;
        }
        if (isset($this->postParams['status']) && isset($this->postParams['refundedAmount'])
            && $this->postParams['status'] === self::STATUS_PARTIALY_REFUNDED) {
            $status = str_replace('_', ' ', self::STATUS_PARTIALY_REFUNDED);
            G2APayHelper::addRefundConfirmation($orderId, $this->postParams, ucfirst($status));

            return self::SUCCESS;
        }
    }

    /**
     * Modify request from G2A Pay to array format.
     *
     * @return array
     */
    private function createArrayOfRequestParams()
    {
        $vars   = array();
        foreach ($_POST as $key => $value) {
            $vars[$key] = $value;
        }

        return $vars;
    }

    /**
     * @param $vars
     * @param $order
     * @return bool
     */
    private function comparePrices($vars, $order)
    {
        $balance = commerce_payment_order_balance($order);
        $amount  = G2APayHelper::getValidAmount($balance['amount'] / 100);
        if ($vars['amount'] == $amount) {
            return true;
        }

        return false;
    }

    /**
     * @param $vars
     * @return bool
     */
    private function isCalculatedHashMatch($vars)
    {
        $payment    = commerce_payment_method_instance_load('g2apay|commerce_payment_g2apay');
        $api_secret = $payment['settings']['api_secret'];

        return G2APayHelper::calculateHash($vars, $api_secret) === $vars['hash'];
    }
}

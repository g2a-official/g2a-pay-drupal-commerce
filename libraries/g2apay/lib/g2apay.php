<?php
/**
 * @author    G2A Team
 * @copyright Copyright (c) 2016 G2A.COM
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

if (!function_exists('curl_init')) {
    throw new Exception('G2A Pay needs the CURL PHP extension.');
}

require_once 'g2apay' . DIRECTORY_SEPARATOR . 'G2APayHelper.php';
require_once 'g2apay' . DIRECTORY_SEPARATOR . 'G2APayClient.php';
require_once 'g2apay' . DIRECTORY_SEPARATOR . 'G2APayRest.php';
require_once 'g2apay' . DIRECTORY_SEPARATOR . 'G2APayIpn.php';
require_once 'g2apay' . DIRECTORY_SEPARATOR . 'G2APayException.php';
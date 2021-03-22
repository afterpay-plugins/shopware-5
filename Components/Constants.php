<?php

namespace AfterPay\Components;

/**
 * Constants class
 *
 * @package AfterPay\Components
 */
class Constants
{

    const PAYMENTSTATUSPAID = 12;
    const PAYMENT_METHODS = array(
        "colo_afterpay_dd",
        "colo_afterpay_invoice",
        "colo_afterpay_installment",
        "colo_afterpay_campaigns"
    );
    const API_URL = "https://api.afterpay.io/api/v3/";
    const SANDBOX_API_URL = "https://api-pt.afterpay.io/api/v3/";
    const PUBLIC_SANDBOX_API_URL = "https://sandbox.afterpay.io/api/v3/";

}

<?php

namespace Common\Lib\Goodbarber\Commerce\Push\OrderTracking;

/**
 * Class PushTemplate
 * Representation of a push template
 * @package Common\Lib\Goodbarber\Commerce\Push\OrderTracking
 */
class PushTemplate
{
    /**
     * Confirmed order
     */
    const PUSH_ORDER_CONFIRMED = "PUSH_ORDER_CONFIRMED";

    /**
     * Shipped order
     */
    const PUSH_ORDER_FULFILLED = "PUSH_ORDER_FULFILLED";

    /**
     * Shipped order for local pickup
     */
    const PUSH_ORDER_FULFILLED_PICKUP = "PUSH_ORDER_FULFILLED_PICKUP";

    /**
     * Shipped order for local shipping methode
     */
    const PUSH_ORDER_FULFILLED_LOCAL = "PUSH_ORDER_FULFILLED_LOCAL";

    /**
     * Cancelled order notification
     */
    const PUSH_ORDER_CANCELLED = "PUSH_ORDER_CANCELLED";

    /**
     * Order recovery. In practice it matches with an abandoned checkout
     */
    const PUSH_ORDER_RECOVERED = "PUSH_ORDER_RECOVERED";

    /**
     * Order mercado.
     */
    const PUSH_ORDER_OFFLINE_PAYMENT = "PUSH_ORDER_OFFLINE_PAYMENT";

    /**
     * The validated and allowed push types
     * @const array
     */
    public static $ALLOWED_PUSH_TYPES = [
        self::PUSH_ORDER_CONFIRMED,
        self::PUSH_ORDER_FULFILLED,
        self::PUSH_ORDER_FULFILLED_PICKUP,
        self::PUSH_ORDER_FULFILLED_LOCAL,
        self::PUSH_ORDER_CANCELLED,
        self::PUSH_ORDER_RECOVERED,
        self::PUSH_ORDER_OFFLINE_PAYMENT
    ];

    /**
     * Title of the notification
     * @var string
     */
    public $title;

    /**
     * Push Type
     * @var string
     */
    public $push_type;

    /**
     * Message of the notification
     * @var string
     */
    public $message;

    public function __construct(\stdClass $params)
    {
        if (empty($params->push_type)) {
            throw new \Phalcon\Exception("Push type field missing");
        }

        if (!in_array($params->push_type, self::$ALLOWED_PUSH_TYPES)) {
            throw new \Phalcon\Exception("Push type not allowed");
        }

        $this->title = $params->title;
        $this->push_type = $params->push_type;
        $this->message = $params->message;
    }
}

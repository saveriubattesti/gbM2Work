<?php

namespace Common\Lib\Goodbarber\Commerce\Push\OrderTracking;

use Common\Lib\Goodbarber\Commerce\PaymentServices\PaymentServicesManager;
use Common\Models\Webzine;


/**
 * Class Tabler
 * @package Common\Lib\Goodbarber\Commerce\Push\OrderTracking
 */
class Tabler extends \Phalcon\DI\Injectable
{

    public static $TABLE_ROW_CONFIG = [
        PushTemplate::PUSH_ORDER_CONFIRMED => ["icon" => "commerce/mailing/order_confirmation.png", "label" => "GBCOMMERCE_MAIL_ORDER_CONFIRMATION_LABEL", "description" => "GBCOMMERCE_MAIL_ORDER_CONFIRMATION_DESC"],
        PushTemplate::PUSH_ORDER_FULFILLED => ["icon" => "commerce/mailing/order_fulfilled.png", "label" => "GBCOMMERCE_MAIL_ORDER_FULFILLED_LABEL", "description" => "GBCOMMERCE_MAIL_ORDER_FULFILLED_DESC"],
        PushTemplate::PUSH_ORDER_FULFILLED_PICKUP => ["icon" => "commerce/mailing/order_fulfilled_pickup.png", "label" => "GBCOMMERCE_MAIL_ORDER_FULFILLED_SHOP_PICKUP_LABEL", "description" => "GBCOMMERCE_MAIL_ORDER_FULFILLED_SHOP_PICKUP_DESC"],
        PushTemplate::PUSH_ORDER_FULFILLED_LOCAL => ["icon" => "commerce/mailing/order_fulfilled_local.png", "label" => "GBCOMMERCE_MAIL_ORDER_FULFILLED_LOCAL_LABEL", "description" => "GBCOMMERCE_MAIL_ORDER_FULFILLED_DESC"],
        PushTemplate::PUSH_ORDER_CANCELLED => ["icon" => "commerce/mailing/order_cancelled.png", "label" => "GBCOMMERCE_MAIL_ORDER_CANCELLED_LABEL", "description" => "GBCOMMERCE_MAIL_ORDER_CANCELLED_DESC"],
        PushTemplate::PUSH_ORDER_RECOVERED => ["icon" => "commerce/mailing/checkout_abandoned.png", "label" => "GBCOMMERCE_MAIL_CHECKOUT_ABANDONED_LABEL", "description" => "GBCOMMERCE_MAIL_CHECKOUT_ABANDONED_DESC"],
        PushTemplate::PUSH_ORDER_OFFLINE_PAYMENT => ["icon" => "commerce/mailing/order_offline_payment.png", "label" => "GBCOMMERCE_MAIL_ORDER_OFFLINE_PAYMENT_LABEL", "description" => "GBCOMMERCE_MAIL_ORDER_OFFLINE_PAYMENT_DESC"]
    ];

    /**
     * Returns array with all the notification templates available
     * @param Webzine $webzine
     * @return array Array formatted for utilities table
     */
    public function getPushTemplates(Webzine $webzine)
    {
        $tableArray = array();
        $tableArray["rows"] = array();

        $pushTypes = self::$TABLE_ROW_CONFIG;

        // Ckeck if commerceabandoned addon is enabled
        if (!$this->acl->isAllowed("commerceabandoned", "list")) {
            unset($pushTypes[PushTemplate::PUSH_ORDER_RECOVERED]);
        }

        // Only Mercado Pago payment service use offline_payment template
        if (!PaymentServicesManager::create($webzine)->isMercadoConnected()) {
            unset($pushTypes[PushTemplate::PUSH_ORDER_OFFLINE_PAYMENT]);
        }

        // Temp Controle sur les commandes abandonnées
        if (!$this->acl->isAllowed("commerceabandoned", "list")) {
            unset($pushTypes[PushTemplate::PUSH_ORDER_RECOVERED]);
        }

        if (!$this->acl->isAddonEnable("localpickup")) {
            unset($pushTypes[PushTemplate::PUSH_ORDER_FULFILLED_PICKUP]);
        } else {
            $pushTypes[PushTemplate::PUSH_ORDER_FULFILLED]["label"] =  "GBCOMMERCE_MAIL_ORDER_FULFILLED_PICKUP_LABEL";
            $pushTypes[PushTemplate::PUSH_ORDER_FULFILLED]["icon"] = "commerce/mailing/order_fulfilled_transport.png";
        }

        if (!$this->acl->isAddonEnable("localdelivery")) {
            unset($pushTypes[PushTemplate::PUSH_ORDER_FULFILLED_LOCAL]);
        } else {
            $pushTypes[PushTemplate::PUSH_ORDER_FULFILLED]["label"] = "GBCOMMERCE_MAIL_ORDER_FULFILLED_PICKUP_LABEL";
            $pushTypes[PushTemplate::PUSH_ORDER_FULFILLED]["icon"] = "commerce/mailing/order_fulfilled_transport.png";
        }

        foreach($pushTypes as $type => $conf) {
            $tableArray["rows"][strtolower($type)] = $this->getRow(strtolower($type), $conf);
        }

        $tableArray["header"] = $this->setHeader();
        
        return $tableArray;
    }

    /**
     * Set Style and title for table header
     * @return array
     */
    private function setHeader()
    {
        return array(
            array(
                "content" => $this->translater->getStatic("Type"),
                "class" => "type",
                "style" => "width:40%"
            ) ,
            array(
                "content" => $this->translater->getStatic("Description"),
            )
        );
    }

    /**
     * @param array $config
     * @return array
     */
    private function getRow($type, array $config)
    {        //\control::debug2R($commerceMail);
        $label = $this->translater->getStaticOnEmpty($config["label"]);
        $icon = "<span class='icon-mail'><img src='".$this->url->getStaticImage($config["icon"])."' alt='".$label."' title='".$label."' /></span>";
        $type = "<a class='text-secondary-hover' data-main-href href='".$this->url->getUrl("commerce/push/ordertracking/".$type."/edit/")."'>".$icon." ".$label."</a>";

        /*
         * Description
         */
        $description = $this->translater->getStaticOnEmpty($config["description"]);

        $row = array(
            $type,
            $description,
        );

        return $row;
    }
}

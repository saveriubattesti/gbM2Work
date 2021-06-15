<?php

namespace Engine\GBPortal\Controllers ;

use Common\Lib\Assets;
use Common\Lib\Goodbarber\Billing\BackendPricingTabler;

class PricingController extends ControllerBase
{
    public function initialize()
    {
        parent::initialize();

        $this->view->subMenuActive = "pricing";

        $this->assets->collection('inlineCss')->addCss(Assets::minifyPath("assets/css/gb_portal/inline/pricing_shop.css"));

        // On récupère la devise
        $this->view->devise = $this->getDevise();

    }

    public function shopAction() // e-Commerce Plans
    {
        $this->cookies->set("typeo", "shop");

        if ($this->hasCachePage()){
            return;
        }
        $this->view->type_plan = "shop";
        $this->view->subtitlePage = $this->translater->get("PRICING_23");
        $this->view->createLabelBtn = $this->translater->get("CREATE_SHOP_BTN"); // Créer une shopping App

        // Meta Tite and Desc
        $this->view->tagTitle = $this->translater->get("META_TITLE_35");
        $this->view->tagDescription = $this->translater->get("META_DESC_35");

        // Fil d'arianne & canonical
        $this->view->breadcrumb = [["url" => $this->url->get("pricing/shop/"), "name" => $this->translater->get("MENU_pricing")]];
        $this->view->canonicalUrl = $this->url->get("pricing/shop/") ;

        $this->_initTableForPlan("shop");

        $this->view->pick("pricing/index");
    }


    public function indexAction() // CLASSIC Plans
    {
        $this->cookies->set("typeo", "classic");

        if ($this->hasCachePage()){
            return;
        }
        $this->view->type_plan = "classic";

        // Meta Tite and Desc
        $this->view->tagTitle = $this->translater->get("META_TITLE_36");
        $this->view->tagDescription = $this->translater->get("META_DESC_36");
        $this->view->createLabelBtn = $this->translater->get("CREATE_BTN"); // Créer une app

        // Fil d'arianne & canonical
        $this->view->breadcrumb = [["url" => $this->url->get("pricing/"), "name" => $this->translater->get("MENU_pricing")]];
        $this->view->canonicalUrl = $this->url->get("pricing/") ;

        $this->view->messageUnderTable = nl2br($this->translater->get("GBPLAN_SMS_INFOS", null, "GeLangage"));

        $this->_initTableForPlan("classic");
    }

    public function resellerAction() // Reseller Plans
    {
        if ($this->hasCachePage()){
            return;
        }
        $this->view->type_plan = "reseller";
        $this->view->subtitlePage = $this->translater->get("PRICING_38");
        $this->view->createLabelBtn = $this->translater->get("CREATE_RESELLER_BTN"); // Devenez un reseller
        $this->view->createUrl = $this->url->get('create/reseller/');

        // Meta Tite and Desc
        $this->view->tagTitle = $this->translater->get("PORTAL_PRICING_RESELLER_META_TITLE");
        $this->view->tagDescription = $this->translater->get("PORTAL_PRICING_RESELLER_META_DESC");

        // Fil d'arianne & canonical
        $this->view->breadcrumb = [["url" => $this->url->get("pricing/reseller/"), "name" => $this->translater->get("MENU_pricing")]];
        $this->view->canonicalUrl = $this->url->get("pricing/reseller/") ;

        $this->view->messageUnderTable = nl2br($this->translater->get("GBPLAN_SMS_INFOS", null, "GeLangage"));
        $this->view->plans  = [ "classicshop" => $this->translater->get("PRICING_40"), "classic" => $this->translater->get("PRICING_41") ];

        $this->_initTableForPlan("reseller");

        $this->view->pick("pricing/index");
    }


    private function _initTableForPlan($plan)
    {
        $pricingTabler = new BackendPricingTabler($plan);

        // Tarifs mensuel selon la periode de reglement
        $this->view->tabTarifs = $pricingTabler->getTarifs();

        // Main features
        $this->view->tabPlan =  $pricingTabler->getMainFeatures();

        // Full features
        $this->translater->setModel("GeLangage");
        if($plan == "reseller"){
            $this->view->tabDetailedPlan = $pricingTabler->getResellerFullFeatures($this->view->devise);
        }else{
            $this->view->tabDetailedPlan = $pricingTabler->getFullFeatures($this->view->devise);
        }

        $this->translater->setModel("Langage");

        // Faq : toutes les infos dans un article du CMS
        $num_article = $this->config->faq_pricing[$plan];
        $this->view->faqs = $this->getArticleCms($num_article)["details"];
    }

}

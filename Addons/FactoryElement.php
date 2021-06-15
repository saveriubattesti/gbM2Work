<?php

namespace Engine\GBBackOffice\V4\Lib\Element;

use Common\Lib\Goodbarber\Config;
use Common\Lib\Goodbarber\Sections\Section;
use Common\Models\Webzine;
use Common\Traits\DI;
use Engine\GBBackOffice\V4\Lib\Widgets\Widget;

class FactoryElement extends \Phalcon\Mvc\User\Component
{
    use DI;

    public $apply_theme = true;
    public $correct_order = true;

    private $_listByZone = array(
        "" => [
            "navBar" => ["GBNavBarElementTypeLink"],
            "widgets" => [
                "GBWidgetTypeContent", "GBWidgetTypeNavigation", "GBWidgetTypeAds", "GBWidgetTypeSearch",
                "GBWidgetTypeSocial", "GBWidgetTypeNewsletter", "GBWidgetTypeSeparator", "GBWidgetTypeCustom",
            ],
            "widgetsShopPlan" => [
                "GBWidgetTypeCommerceproducts", "GBWidgetTypeCommercecollectionslist", "GBWidgetTypeCommercesearch", "GBWidgetTypeCommercepromo",
                "GBWidgetTypeNewsletter", "GBWidgetTypeNavigation", "GBWidgetTypeCommercelegal", "GBWidgetTypeSocial", "GBWidgetTypeSeparator", "GBWidgetTypeCustom",
                "GBWidgetTypeArticle", "GBWidgetTypeMap"
            ]
        ],
        "GBRootControllerTypeSwipe" => [
            "header" => ["GBMenuElementTypeSearchbar", "GBMenuElementTypeLogin", "GBMenuElementTypeLogo", "GBMenuElementTypeShortcuts", "GBMenuElementTypeSeparator"],
            "body" => ["GBMenuElementTypeTitlebreak", "GBMenuElementTypeDropdown", "GBMenuElementTypeMultilevel", "GBMenuElementTypeSeparator"], // "GBMenuElementTypeLink"
            "footer" => ["GBMenuElementTypeLogin", "GBMenuElementTypeShortcuts", "GBMenuElementTypeLink", "GBMenuElementTypeCopyright"]
        ],
        "GBRootControllerTypeLittleSwipe" => [
            "header" => ["GBMenuElementTypeLogin", "GBMenuElementTypeLogo"],
            "body" => [],// "GBMenuElementTypeLink"
            "footer" => ["GBMenuElementTypeLink", "GBMenuElementTypeLogin"]
        ],
        "GBRootControllerTypeTabBar" => [
            "main" => [] // "GBMenuElementTypeLink"
        ],
        "GBRootControllerTypeGrid" => [
            "header" => ["GBMenuElementTypeLogo"],
            "body" => [],// "GBMenuElementTypeLink"
            "footer" => ["GBMenuElementTypeLogin", "GBMenuElementTypeShortcuts", "GBMenuElementTypeCopyright"]
        ],
        /*"GBRootControllerTypeMosaic" => [
            "header" =>  ["GBMenuElementTypeSearchbar", "GBMenuElementTypeLogin", "GBMenuElementTypeLogo"],
            "body" =>    ["GBMenuElementTypeLink", "GBMenuElementTypeMultilevel"],
            "footer" =>  ["GBMenuElementTypeLogin", "GBMenuElementTypeLink", "GBMenuElementTypeCopyright"]
        ],*/
        "GBRootControllerTypeSlate" => [
            "header" => ["GBMenuElementTypeSearchbar", "GBMenuElementTypeLogin", "GBMenuElementTypeLogo", "GBMenuElementTypeShortcuts", "GBMenuElementTypeSeparator"],
            "body" => ["GBMenuElementTypeDropdown", "GBMenuElementTypeSeparator"], // "GBMenuElementTypeLink"
            "footer" => ["GBMenuElementTypeLogin", "GBMenuElementTypeShortcuts", "GBMenuElementTypeLink", "GBMenuElementTypeCopyright"]
        ]
    );


    private $_listSingleForAll = array(
        "GBRootControllerTypeSwipe" => ["GBMenuElementTypeSearchbar", "GBMenuElementTypeLogin", "GBMenuElementTypeLogo", "GBMenuElementTypeCopyright", "GBMenuElementTypeBag"],
        "GBRootControllerTypeLittleSwipe" => ["GBMenuElementTypeSearchbar", "GBMenuElementTypeLogin", "GBMenuElementTypeLogo", "GBMenuElementTypeCopyright", "GBMenuElementTypeBag"],
        "GBRootControllerTypeGrid" => ["GBMenuElementTypeLogin", "GBMenuElementTypeLogo", "GBMenuElementTypeCopyright", "GBMenuElementTypeShortcuts", "GBMenuElementTypeBag"],
        "GBRootControllerTypeMosaic" => ["GBMenuElementTypeSearchbar", "GBMenuElementTypeLogin", "GBMenuElementTypeLogo", "GBMenuElementTypeCopyright", "GBMenuElementTypeBag"],
        "GBRootControllerTypeSlate" => ["GBMenuElementTypeSearchbar", "GBMenuElementTypeLogin", "GBMenuElementTypeLogo", "GBMenuElementTypeCopyright", "GBMenuElementTypeBag"]
    );

    private $_listSingleForZone = array(
        "GBRootControllerTypeSwipe" => [
            "header" => ["GBMenuElementTypeShortcuts"],
            "footer" => ["GBMenuElementTypeShortcuts"]
        ],
        "GBRootControllerTypeSlate" => [
            "header" => ["GBMenuElementTypeShortcuts"],
            "footer" => ["GBMenuElementTypeShortcuts"]
        ]
    );

    private $_maxElementsPerZone = array(
        "GBRootControllerTypeGrid" => array("header" => 1, "footer" => 1),
        "GBRootControllerTypeLittleSwipe" => array("header" => 1, "footer" => 1),
        "GBRootControllerTypeSlate" => array("header" => 4, "footer" => 4)
    );

    private $_listSoon = array(
        "GBMenuElementTypeSearchbar",
        "GBMenuElementTypeDropdown",
        "GBMenuElementTypeMultilevel"
    );

    private $_maxSubLinks = array(
        "GBRootControllerTypeSwipe" => 4,
        "GBRootControllerTypeGrid" => 2,
        "GBRootControllerTypeMosaic" => 3,
        "GBRootControllerTypeSlate" => 3,
        "navBar" => 4
    );

    private $nb_elements_used;

    private $_webzine;

    // Sections ?
    private $param_type = "";
    private $param_id = 0;


    const MAX_LINK_IN_TABBAR = 5;
    const MAX_LINK_IN_NAVBAR = 2;

    public function __construct($webzine)
    {
        $this->setWebzine($webzine);
    }

    public function setWebzine($webzine)
    {
        $this->elementManager = new ElementManager($webzine, "");
        $this->_webzine = $webzine;
    }

    public function setTypeId($type_id)
    {
        $this->elementManager->type = $this->param_type = "";
        $this->elementManager->id_section = $this->param_id = 0;

        if (!empty($type_id)) {
            list($type, $id) = explode("/", $type_id);
            $this->elementManager->type = $this->param_type = $type;
            $this->elementManager->id_section = $this->param_id = $id;
        }
    }

    public function getTypeId()
    {
        if (!empty($this->param_type) && !empty($this->param_id)) {
            return $this->param_type . "/" . $this->param_id;
        }

        return "";
    }

    public function getZones()
    {
        $zones = $this->_listByZone;

        if ($this->_webzine->isShopPlan()) {
            $zones[""]["widgets"] = $zones[""]["widgetsShopPlan"];
            unset($zones[""]["widgetsShopPlan"]);
        }

        $listfilter = [];
        foreach ($zones[""]["widgets"] as $typeWidget) {

            if ($this->widgetManager->canAddWidget($this->_webzine, $typeWidget)) {
                $listfilter[] = $typeWidget;
            }
        }
        $zones[""]["widgets"] = $listfilter;

        return $zones;
    }

    public function formatePrefix()
    {
        return !empty($this->param_type) ? $this->param_type . "/" : "";
    }

    public function getParamType()
    {
        return $this->param_type;
    }

    public function getParamId()
    {
        return $this->param_id;
    }

    public function getMaxLinkInTabBar()
    {
        return self::MAX_LINK_IN_TABBAR;
    }

    public function getMaxLinkInNavBar()
    {
        return self::MAX_LINK_IN_NAVBAR;
    }

    public function getMaxSublinks()
    {
        return $this->_maxSubLinks;
    }

    private function getNotAvailable()
    {
        $retu = $this->_listSoon;

        // Controle addons
        if (!$this->acl->hasProfileAddonEnable()) {
            $retu[] = "GBMenuElementTypeLogin";
        }
        if (!$this->_webzine->isShopPlan()) {
            $retu[] = "GBMenuElementTypeBag";
        }

        return $retu;
    }

    public function createElement($nameElement, $id = null, $prefix = null)
    {
        try {
            $element = $this->getDI()->get("\Engine\GBBackOffice\V4\Lib\Element\\" . $nameElement);
        } catch (\Phalcon\DI\Exception $e) {
            $element = new StandardElement();
        }
        $element->setTypeId($this->param_type, $this->param_id);
        $element->setType($nameElement);
        if ($id) {
            $element->setId($id);
        }
        if ($prefix) {
            $element->setPrefix($prefix);
        }

        return $element;
    }

    public function getInfosByObjet($objet)
    {
        preg_match_all("#^(.*)/([^/]*)/elements/([0-9]+)/(.*)$#", $objet, $out);
        return array("id" => $out[3][0], "zone" => $out[2][0], "root_type" => $out[1][0]);
    }

    public function getMaxElementsPerZone($root_type, $zone)
    {
        if (isset($this->_maxElementsPerZone[$root_type][$zone])) {
            return $this->_maxElementsPerZone[$root_type][$zone];
        }
    }

    public function getElementsDispo($zone, $root_type = "", $control_disabled = false)
    {
        $list = array();

        $all_disabled = false;

        $elements_used = $this->getNbElementsUsed($root_type);

        // Nb Max par zone
        if (isset($this->_maxElementsPerZone[$root_type][$zone]) && count($this->getElementsUsed($zone, $root_type)) >= $this->_maxElementsPerZone[$root_type][$zone]) {
            $all_disabled = true;
        }

        // On recupère la liste les éléments disponible dans ce contexte
        $zones = $this->getZones();
        if (!empty($zones[$root_type][$zone])) {
            foreach ($zones[$root_type][$zone] as $nameElement) {
                $elem = $this->createElement($nameElement);

                // One time for all
                if (!empty($elements_used["all"][$nameElement]) && !empty($this->_listSingleForAll[$root_type]) && in_array($nameElement, $this->_listSingleForAll[$root_type])) {
                    $elem->disabled = true;
                } // One time for zone
                elseif (!empty($elements_used[$zone][$nameElement]) && !empty($this->_listSingleForZone[$root_type][$zone]) && in_array($nameElement, $this->_listSingleForZone[$root_type][$zone])) {
                    $elem->disabled = true;
                }

                if ($all_disabled) {
                    $elem->disabled = true;
                }

                // Soon
                if ((!$control_disabled || empty($elem->disabled)) && !in_array($nameElement, $this->getNotAvailable())) {
                    $list[$nameElement] = $elem;
                }

            }
        }

        return $list;
    }

    /**
     * Fonction qui retourne le nombre d'elements utilisé dans toutes les zones
     * @param string $root_type
     * @return array
     */
    private function getNbElementsUsed($root_type = "")
    {
        if (!isset($this->nb_elements_used)) {
            $list = $retu = [];

            $zones = $this->getZones();

            foreach (array_keys($zones[$root_type]) as $zone) {
                if (!isset($list[$zone])) $list[$zone] = [];
                $list[$zone] += $this->getElementsUsed($zone, $root_type);
            }

            foreach ($list as $zone => $elements) {
                foreach ($elements as $used) {
                    if (!isset($retu[$zone][$used->getType()])) $retu[$zone][$used->getType()] = 0;
                    if (!isset($retu["all"][$used->getType()])) $retu["all"][$used->getType()] = 0;

                    $retu[$zone][$used->getType()]++;
                    $retu["all"][$used->getType()]++;
                }
            }

            $this->nb_elements_used = $retu;
        }

        return $this->nb_elements_used;
    }

    /**
     * Fonction qui retourne les elements utilisé dans le context passé en parametre
     * @param $zone
     * @param string $root_type
     * @param $check_specs boolean si false on fait la difference entre main tabbar et othertabbar
     * @param null $childof
     * @return arrays
     * @childof : si renseigné c'est un sous element (ex : plusieurs link dans shortctus)
     */
    public function getElementsUsed($zone, $root_type = "", $check_specs = true, $childof = null)
    {
        $list = array();

        if ($check_specs && $zone == "navBar") {
            return $this->getElementsUsedNavBar();
        }

        if (!empty($root_type)) {

            // Cas particulier pour TabBar
            if ($check_specs && $root_type == "GBRootControllerTypeTabBar") {
                if ($zone == "other") {
                    return $this->getElementsUsedOtherTabBar();
                } else {
                    return $this->getElementsUsedMainTabBar();
                }
            }
        }

        $elements = $this->getAllForZone($zone, $root_type, $childof);


        //$this->makeElementManagerObjet($zone, $root_type, $check_specs);

        /*control::debug2($zone);
        \control::debug2R($elements);*/

        foreach ($elements as $k => $element) {

            // Correction id manquant
            if (empty($element["id"])) {
                $element["id"] = $k;
                $element["type"] = "";
            }

            $el = $this->createElement($element["type"], $element["id"], $this->elementManager->objet);
            $list[strval($element["id"])] = $el;
        }

        return $list;
    }

    public function getControledElementsUsed($zone, $root_type = "")
    {
        $elements = $this->getElementsUsed($zone, $root_type);

        $delete = 0;

        // Controle type existant
        foreach ($elements as $element) {
            if (empty($element->getType())) {
                $this->delete($element->getId(), $zone, $root_type);
                $this->_webzine->log("Delete element " . $element->getPrefix() . $element->getId() . " " . $element->getTypeId() . " because type missing");
                $delete++;
            }
        }

        if ($delete) {
            $elements = $this->getElementsUsed($zone, $root_type);
        }

        return $elements;
    }


    /**
     * Fonction qui retourne les elements utilisé pour une zone
     * @param $zone
     * @param $rootType
     * @childof : si renseigné c'est un sous element (ex : plusieurs link dans shortctus)
     * @return array
     */
    public function getAllForZone($zone, $root_type, $childof = null)
    {
        $this->makeElementManagerObjet($zone, $root_type, $childof);
        return $this->elementManager->getAll();
    }

    public function getElementsUsedMainTabBar()
    {
        $list = $this->getElementsUsed("", "GBRootControllerTypeTabBar", false);
        $all = count($list);
        if ($all > self::MAX_LINK_IN_TABBAR) {
            $list = array_slice($list, 0, self::MAX_LINK_IN_TABBAR - 1, true);
            $list["otherMenu"] = $this->createElement("GBMenuElementTypeOthermenu", "otherMenu", $this->elementManager->objet);
        }

        return $list;
    }

    public function getElementsUsedOtherTabBar()
    {
        $list = $this->getElementsUsed("", "GBRootControllerTypeTabBar", false);
        $all = count($list);
        //\control::debug2($all);
        if ($all > self::MAX_LINK_IN_TABBAR) {
            $list = array_slice($list, self::MAX_LINK_IN_TABBAR - 1, null, true);
        } else {
            return [];
        }

        return $list;
    }

    /**
     * getElementsUsedNavBar
     *
     * @return \Engine\GBBackOffice\V4\Lib\Element\StandardElement[]
     */
    public function getElementsUsedNavBar()
    {
        $retu = [];

        $activeElements = $this->getElementsUsed("navBar", "", false);

        // More button ?
        $maxLinks = $this->getMaxLinkInNavBar();
        if (count($activeElements) > $maxLinks) {
            $more_element = $this->createElement("GBNavBarElementTypeMorebutton", "moreButton", $this->formatePrefix() . "navBar");

            $index = 0;
            foreach ($activeElements as $k => $element) {
                if ($index == $maxLinks) {
                    $retu["moreButton"] = $more_element;
                } else if ($index < $maxLinks) {
                    if ($index < $maxLinks - 1) {
                        $retu[$k] = $element;
                    }
                    $index++;
                }
            }
        } else {
            $retu = $activeElements;
        }

        $element = $this->createElement("GBNavBarElementTypeMenubutton", "menuButton", $this->formatePrefix() . "navBar");
        $retu = array("menuButton" => $element) + $retu;

        $element = $this->createElement("GBNavBarElementTypeLogo", "logo", $this->formatePrefix() . "navBar");
        $retu = array("logo" => $element) + $retu;
        
        return $retu;
    }

    public function getElementsUsedOtherNavBar()
    {
        $list = [];

        $activeElements = $this->getElementsUsed("navBar", "", false);
        $maxLinks = $this->getMaxLinkInNavBar();

        $moreButton = 0;
        $total = count($activeElements);
        if ($total > $maxLinks) {
            $moreButton = 1;
        }

        $index = 0;
        foreach ($activeElements as $k => $element) {
            if ($index > $maxLinks - 1 - $moreButton) {
                $list[$k] = $element;

                // Controle de custom non present
                $objet = $element->getPrefix() . "/" . $element->getId() . "/custom";
                if ($element->getTypeId() != "") {
                    $objet = preg_replace("#^" . $element->getParamType() . "/#", "", $objet);
                }
                $custom = $element->getParam($objet . "/%");
                if (!empty($custom)) {
                    $this->paramsManager->unApplyParams($this->_webzine, Section::$defaultPlatform, $objet, $element->getParamId());
                }
            }
            $index++;
        }


        return $list;
    }

    /**
     * Retourne un objet element en fonction d'un id
     * @param $id
     * @return StandardElement|mixed
     */
    public function getElementById($id)
    {
        if ($id == "otherMenu") {
            return $this->createElement("GBMenuElementTypeOthermenu", "otherMenu", $this->formatePrefix() . "root/tabBar/elements");
        }

        if (in_array($id, ["moreButton", "menuButton", "logo"])) {
            return $this->createElement("GBNavBarElementType" . ucfirst(strtolower($id)), $id, $this->formatePrefix() . "navBar");
        }

        $orders = $this->paramsManager->get(Section::$defaultPlatform, ($this->param_type == "sections" ? $this->param_type . "/" : "") . "%elementsOrder/%", $this->param_type, $this->param_id, $this->_webzine);
        foreach ($orders as $objet => $val) {
            if ($val == $id) {
                $prefix = preg_replace("#^(.*)/elementsOrder/(.*)$#", "\\1", $objet);
                $type = $this->paramsManager->get(Section::$defaultPlatform, $prefix . "/elements/" . $id . "/type", $this->param_type, $this->param_id, $this->_webzine);
                return $this->createElement($type, $id, $prefix . "/elements");
            }
        }
    }

    /**
     * Retourne la zone d'un element
     * @param StandardElement $element
     */
    public function getRealZoneOfElement(StandardElement $element)
    {
        if ($element->getRootType() == "GBRootControllerTypeTabBar") {
            if (array_key_exists($element->getId(), $this->getElementsUsedOtherTabBar())) {
                return "other";
            }

            return "main";
        }

        return $element->getZone();
    }

    /**
     * Fonction qui cree le prefix d'un element
     * @param $zone
     * @param $rootType
     * @param $childof : si renseigné c'est un sous element (ex : plusieurs link dans shortcuts)
     * @return array
     */
    public function makeElementManagerObjet($zone, $root_type, $childof = null)
    {
        if (empty($root_type)) {
            $this->elementManager->objet = $zone . "/elements";
            if (!empty($this->param_type)) {
                $this->elementManager->objet = $this->param_type . ($zone != "" ? "/" : "") . $this->elementManager->objet;
            }
        } else {
            $jsonRootType = Config::getJsonRootType($this->_webzine, $root_type);
            if ($zone == "other" || $zone == "main") {
                $zone = "";
            }
            $this->elementManager->objet = $jsonRootType . (!empty($zone) ? "/" . $zone : "") . "/elements";
        }

        // Si c'est un sous element, on prefix par l'id puis le type (au pluriel)
        if (!empty($childof)) {
            $this->elementManager->objet .= "/" . $childof["element"]->getId() . "/" . $childof["subtype"];
        }

        return $this->elementManager->objet;
    }


    /**
     * Fonction qui ajoute un element dans la base
     * @param $type
     * @param $zone
     * @param string $root_type
     * @param $childof : si renseigné c'est un sous element (ex : plusieurs link dans shortctus)
     * @param int $after : une insertion apres l'element $after
     * @param bool $controlElementsDispo
     * @return array
     * @throws \Exception
     */
    public function add($type, $zone, $root_type = "", $childof = null, $after = 0, $controlElementsDispo = true)
    {

        $zones = $this->getZones();

        if (!isset($zones[$root_type])) {
            return;
        }

        $this->microtime->trace("Start add $type for $zone $root_type");
        if ($controlElementsDispo && empty($childof)) {
            $this->controlElementsDispo($type, $zone, $root_type);
        }

        $this->makeElementManagerObjet($zone, $root_type, $childof);

        if (empty($after)) {
            $id = $this->elementManager->add(["type" => $type]);
        } elseif ($after == 'first') {
            $id = $this->elementManager->add(["type" => $type], 'first');
        } else {
            $id = $this->elementManager->insert(["type" => $type], intval($after));
        }

        $el = $this->createElement($type, $id, $this->elementManager->objet);

        if (empty($childof)) {
            $this->nb_elements_used = null;
        } else {

        }

        if ($this->apply_theme) {
            $el->applyTheme();
        }

        /*
        $specific_objet = $this->elementManager->objet."/".$type."/%";

        if ($this->param_type == "sections") {
            $specific_objet = preg_replace("#^".$this->formatePrefix()."#", "", $specific_objet);
        }

        if ($this->apply_theme) {
            $this->paramsManager->applyDefaultParams(
                $this->webzine,
                Section::$defaultPlatform,
                $this->webzine->default_theme,
                "",
                $specific_objet,
                $this->elementManager->objet . "/" . $id . "/",
                $this->getTypeId()
            );
        }*/

        if ($this->correct_order) {
            $this->elementManager->correctOrder();
        }

        $this->microtime->trace("End add $type for $zone $root_type");

        return $el;
    }


    /**
     * Fonction qui supprime un element dans la base
     * @param $id
     * @param $zone
     * @param $rootType
     * @param $childof : si renseigné c'est un sous element (ex : plusieurs link dans shortctus)
     * @return array
     */
    public function delete($id, $zone, $root_type = "", $childof = null)
    {
        $this->makeElementManagerObjet($zone, $root_type, $childof);
        $this->elementManager->del($id, true);

        if (empty($childof)) {
            $this->nb_elements_used = null;
        }

        /**
         * Si on a changer l'ordre des elements dans le browsing (zone body ou main)
         * On recalcule l'objet root/firstSection
         */
        if (in_array($zone, array("main", "body")) && $this->paramsManager->get(Section::$defaultPlatform, "root/type") == $root_type) {
            $this->paramsManager->refreshFirstSection($this->_webzine);
        }

        $this->elementManager->correctOrder();
    }

    /**
     * Fonction qui change l'order des elements
     * @param array $order_of_ids
     * @param $zone
     * @param string $root_type
     * @return array
     */
    public function order($order_of_ids, $zone, $root_type = "")
    {
        $this->makeElementManagerObjet($zone, $root_type);
        $this->elementManager->editOrder(array("ordered" => $order_of_ids));
        $this->nb_elements_used = null;

        /**
         * Si on a changer l'ordre des elements dans le browsing (zone body ou main)
         * On recalcule l'objet root/firstSection
         */
        if (in_array($zone, array("main", "body")) && $this->paramsManager->get(Section::$defaultPlatform, "root/type") == $root_type) {
            $this->paramsManager->refreshFirstSection($this->_webzine);
        }
    }

    /**
     * Controle si un type fait partie del la bonne zone
     * @param $type
     * @param $zone
     * @param string $root_type
     * @throws \Exception
     */
    private function controlElementsDispo($type, $zone, $root_type = "")
    {
        if (empty($this->getElementsDispo($zone, $root_type, true)[$type])) {
            throw new \Exception("Element unknown ($type, $zone, $root_type)");
        }
    }


    /**
     * Retrouve les elements sublinks (pour element de type shortcut)
     */
    public function getSubLinks($element)
    {
        $zone = $this->getRealZoneOfElement($element);
        $root_type = $element->getRootType();

        $childof = ["element" => $element, "subtype" => "links"];

        // On recupere la liste des sous-liens
        $subLinks = $this->getElementsUsed($zone, $root_type, true, $childof);

        // Si aucun lien contenu dans les shortcuts on crée le 1er
        if (empty($subLinks)) {
            $this->add("GBMenuElementTypeLink", $zone, $root_type, $childof);
            $subLinks = $this->getElementsUsed($zone, $root_type, true, $childof);
        }

        // On reset l'objet initial
        $this->makeElementManagerObjet($zone, $root_type);

        //\control::debug2("id=".$id." zone=$zone rootype= $root_type cpt=".count($subLinks)."<br/>");

        return $subLinks;
    }

    public function addSubLink($element, $after = 0)
    {
        $childof = ["element" => $element, "subtype" => "links"];
        $zone = $this->getRealZoneOfElement($element);
        $root_type = $element->getRootType();

        $nb_subLinks = count($this->getElementsUsed($zone, $root_type, true, $childof));
        if ($nb_subLinks >= $this->_maxSubLinks[$root_type]) {
            return;
        }

        $el = $this->add("GBMenuElementTypeLink", $zone, $root_type, $childof, $after);

        return $el;
    }

    public function deleteSubLink($id, $element)
    {
        $childof = ["element" => $element, "subtype" => "links"];
        $this->delete($id, $this->getRealZoneOfElement($element), $element->getRootType(), $childof);
    }

    public function orderSublinks($element, $order_of_ids)
    {
        $zone = $this->getRealZoneOfElement($element);
        $root_type = $element->getRootType();

        $childof = ["element" => $element, "subtype" => "links"];
        $this->makeElementManagerObjet($zone, $root_type, $childof);
        $this->elementManager->editOrder(array("ordered" => $order_of_ids));
    }

    /**
     * Supprime un element à partir d'un objet
     */
    public function deleteByObjet($objet)
    {
        preg_match_all("#^(.*)/elements/([0-9]+)/(links/)?([0-9]+)?/?(link)?/?(.*)$#", $objet, $out);

        // Element principal
        if (!empty($out[2][0])) {
            $element = $this->getElementById($out[2][0]);
            if ($element) {
                // Child ?
                if (!empty($out[4][0])) {
                    $this->deleteSubLink($out[4][0], $element);
                } else {
                    $this->delete($out[2][0], $this->getRealZoneOfElement($element), $element->getRootType());
                }
            }
        }
    }

    public function getPrefix($template, $zone)
    {
        $prefix = Config::getJsonRootType($this->_webzine, $template);

        return $prefix . "/" . $zone;
    }

}

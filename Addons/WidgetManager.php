<?php
/**
 * Date: 08/10/15
 * Time: 09:45
 */

namespace Engine\GBBackOffice\V4\Lib\Widgets;

use Common\Lib\Goodbarber\Sections\Section;
use Common\Lib\Goodbarber\Sections\SectionManager;
use Common\Models\Liaison;
use Common\Models\Webzine;
use Engine\GBBackOffice\V4\Lib\Element\ElementManager;

class WidgetManager extends SectionManager
{
    protected $model = "\\Engine\\GBBackOffice\\V4\\Lib\\Widgets\\Widget";
    protected $default_mep = 2;
    protected $realType = "widget";

    private $sectionManager;

    /**
     * Constructeur du repository.
     * Set le webzine de contexte si disponible.
     */
    public function __construct()
    {
        parent::__construct();
        $this->sectionManager = $this->getDI()->getSectionManager();
    }

    /**
     * Ajoute un widget
     * @param Webzine $webzine le webzine
     * @param string $type le type de section
     * @param string $name le nom de la section
     * @param int $force_id_param si vide fait un auto-incremente, sinon remplace l'id_param
     * @param int $parent_node id_param du parent (si enfant)
     * @param bool|true $log indique si on logue l'ajout
     * @param null $position position de la section
     * @param boolean $force_add force l'ajout (utile pour les sections dependantes d'addon)
     * @param int $id_section ID section si cest un widget dynamic
     * @param int|null $index_subsection Categorie choisie pour le widget (null par defaut)
     * @return Section|void
     * @throws \Exception
     */
    public function add(Webzine $webzine, $type, $name = "", $force_id_param = 0, $parent_node = 0, $log = true, $position = null, $force_add = false, $id_section = 0, $index_subsection = null)
    {
        if (empty($parent_node)) {
            throw new \Exception("Widget must have a parent to be added");
        }

        $widget = parent::add($webzine, $type, $name, $force_id_param, $parent_node, $log, $position, $force_add);

        if ($widget) {
            /*
             * Fils de Home donc tjs position a 1 (on se sert de Liaison pour lordre)
             */
            $widget->update(array("position" => 1));

            // Si on passe la position, on met a jour la position
            if (!empty($position)) {
                $this->majPoid($widget, $parent_node, $position);
            }

            // Si il y a un Commercelegal dans la liste, on update la position à la fin
            if ($type != "GBWidgetTypeCommercelegal") {
                $allWidgets = $this->getAllWidgets($webzine, null, array("", "stock"));
                foreach ($allWidgets as $w) {
                    if ($w->getTypeSection() == "GBWidgetTypeCommercelegal") {
                        $this->majPoid($w, $parent_node, count($allWidgets) + 1); // +1 car on n'a pas encore regeneré le cache getAllWidgets
                    }
                }
            }

            /*
             * Params specifiques au widget
             */
            $widgetTemplater = new WidgetTemplater();
            $widgetTemplater->setCorrectTemplate($widget);

            if (!empty($id_section)) {
                $section = $this->get($id_section);
                $this->paramsManager->set("", "sections/sectionId", $id_section, "sections", $widget->id_param, 1, 0);
                $this->paramsManager->set(Section::$defaultPlatform, "sections/header/title", $section->getNom(), "sections", $widget->id_param, 1, 0);

                // Securité $index_subsection non fourni
                // Si la section en a au poins 1 on choisi la 0
                if (!isset($index_subsection) && $section->subsectionsEnabled()) {
                    $i = 0;
                    $subsections = $this->paramsManager->get("", "sections/subsections/%/title", "sections", $section->id_param);
                    foreach ($subsections as $objet => $subsection) {
                        if (empty($this->paramsManager->get("", str_replace('/title', '/disabled', $objet), "sections", $section->id_param))) {
                            $index_subsection = $i;
                            break;
                        }
                        $i++;
                    }
                }
            }

            if (isset($index_subsection)) {
                $this->paramsManager->set("", "sections/contentSource/params/category_index", $index_subsection, "sections", $widget->id_param, 1, 0);
            }

            $this->checkContentSourceParams($widget);

            if ($type == "GBWidgetTypeCommercelegal") {
                $this->setLinksInGBWidgetTypeCommercelegal($webzine, $widget);
            }
            if ($type == "GBWidgetTypeCustom") {
                $this->paramsManager->set("", "sections/sectionUrl", $webzine->getGoodbarberRoot() . "/apiv3/section/" . $widget->id_param . "/index.html?v=" . time(), "sections", $widget->id_param, 1, 0);
                $this->sectionManager->createDefaultTemplate($widget, "GBModuleTypeCustomScratch");
            }

            if ($type == "GBWidgetTypeCommercepromo") {
                $this->setTextsInGBWidgetTypeCommercepromo($webzine, $widget);
            }

            return $widget;
        }

        return false;
    }

    public function checkPositionBeforeAdd(Webzine $webzine)
    {
        $home = $this->sectionManager->getHome($webzine);

        $nb_widgets = count($this->getAll($webzine, array("", "stock")));

        // Correction des positions avant d'ajouter
        $liaisons = $home->getLiaisons();
        $pos = 1;
        foreach ($liaisons as $liaison) {
            if ($widget = $this->get($liaison->esclave_id)) {
                if ($widget->getTypeSection() != "GBWidgetTypeCommercelegal") {
                    if ($liaison->poid != $pos) {
                        $liaison->update(array("poid" => $pos));
                    }
                    $pos++;
                } else {
                    $liaison->update(array("poid" => $nb_widgets));
                }
            }
        }
    }

    /**
     * Renvoie les widgets d'une home
     * @return array
     */
    public function getAllWidgets(Webzine $webzine, $home = null, $list_etat = array(""))
    {
        if (!isset($home)) {
            $home = $this->sectionManager->getHome($webzine);
        }

        if (empty($home)) {
            return [];
        }

        $lstWidgets = $home->getChildren(true);
        $widgets = $end = [];
        // On retrouve des objets widgets à partir des sections
        foreach ($lstWidgets as $widget) {
            if (in_array($widget->etat, $list_etat)) {
                $object = $this->get($widget->id_param);
                if ($object) {
                    if ($object->getTypeSection() == "GBWidgetTypeCommercelegal") {
                        $end[$widget->id_param] = $object;
                    } else {
                        $widgets[$widget->id_param] = $object;
                    }
                }
            }
        }

        return $widgets + $end;
    }

    /**
     * Renvoie le nombre maximum de sections que peut avoir un webzine
     * @return int
     */
    public function getMaxWidgets()
    {
        $nb_maxwidgets = $this->paramsManager->get("", "nbMaxWidgets");
        if (isset($nb_maxwidgets)) {
            return $nb_maxwidgets;
        }
        return Widget::MAX_WIDGETS;
    }

    public function checkBeforeAdd(Webzine $webzine, $type, $force_add = false)
    {
        $nb_widgets = count($this->getAll($webzine, array("", "stock"), true));

        if ($nb_widgets >= $this->getMaxWidgets() && !\Control::GoodIp() && !$force_add) {
            $webzine->log("Max of nbsections reached (" . $nb_widgets . " >= " . $this->getMaxWidgets() . ")");
            return false;
        }

        return true;
    }

    protected function setNameIfEmpty($type)
    {
        $translater = $this->getDI()->getTranslater();

        $name = $translater->getStaticOnEmpty(Widget::$GB_type_widget[$type]["title"], null, "GeLangage");

        return $name;
    }

    /**
     * Modifie la position pour 1 widget
     * @param $widget
     * @param $id_parent (l'id de la home pour 1 widget)
     * @param $pos
     * @return bool
     */
    public function order($widget, $id_parent, $pos)
    {
        return $this->setPoid($widget, $id_parent, $pos);
    }

    /**
     * Met a jour les positions d'un noeud
     * @param Section $widget
     * @param $id_parent
     * @param $pos
     */
    public function majPoid(Section $widget, $id_parent, $pos)
    {
        $webzine = $widget->getWebzine();

        $this->setPoid($widget, $id_parent, $pos);

        $sql = "UPDATE liaison SET poid=poid+1 WHERE id_webzine=" . $widget->id_webzine . " AND maitre_id=" . $id_parent . " AND maitre_type='section_pere' AND esclave_type='gb_section' AND esclave_id!=" . $widget->id_param . " AND poid>=" . $pos;

        $webzine->getWriteConnection()->execute($sql);

        Liaison::deleteGlobalCache($webzine->id_webzine);
    }

    /**
     * Pas de controle pour les widgets
     * @param Webzine $webzine
     * @param $position
     * @return mixed
     */
    protected function controlPosition(Webzine $webzine, $position)
    {
        return $position;
    }

    /**
     * Regarde si les contenus des widgets dynamic sont valides pour une section en particulier
     * @param Webzine $webzine le webzine
     * @param Section $section la section
     * @param array $array_map_subsections tableau de mapping apres un changement d'ordre des subsections
     * @param array $list_widget_params list des parametres de widgets correspondant relatifs à la section
     */
    public function checkContentValid(Webzine $webzine, $section, $array_map_subsections = null)
    {
        if (!$webzine->isV4()) {
            return;
        }

        if (!empty($section)) {
            list($list_widgets, $list_widget_params) = $this->getWidgetsRelatedToSectionParams($section);
        }

        // Si aucune reference, on ne fait rien
        if (empty($list_widgets)) {
            return;
        }

        // Liste des widgets


        $errorType = "";

        if (empty($section) || empty($section->id_param) || $section->etat == "del") {
            //$errorType = "del";
            foreach ($list_widgets as $widget) {
                $widget->delete(true);
            }
        } else {
            if (!$section->isValid(false) || in_array($section->getService(), Widget::$GB_service_exception)) {
                $errorType = "notvalid";
            }

            if ($section->etat == "stock") {
                $errorType = "stock";
                foreach ($list_widgets as $widget) {
                    if ($widget->etat == "") {
                        $widget->update(array("etat" => "stock"));
                    }
                }
            }
        }

        // On retrouve la first section non disabled
        $sectionHasSubsections = !empty($section) ? $section->getNbSubsections() : 0;

        $ping = false;

        // On recupere la liste de widget reliés à cette section
        foreach ($list_widget_params as $param) {

            if ($widget = $this->get($param->id)) {

                $errorTypeWidget = $errorType;

                if (empty($errorTypeWidget)) {
                    $subsectionIndex = $this->paramsManager->get("", "sections/contentSource/params/category_index", "sections", $param->id);

                    if ($section->getTypeSection() == "GBModuleTypeCommerce") {
                        if (empty($subsectionIndex)) {
                            unset($subsectionIndex);
                            $this->paramsManager->delete("", "sections/contentSource/params/category_index", "sections", $param->id);
                            $ping = true;
                        }
                    } else {
                        // On controle si ce n'est pas une categorie [ALL]
                        $is_all = $this->paramsManager->get("", "sections/subsections/" . intval($subsectionIndex) . "/ids", "sections", $section->id_param);

                        if ($is_all == "[ALL]") {
                            unset($subsectionIndex);
                            $this->paramsManager->delete("", "sections/contentSource/params/category_index", "sections", $param->id);
                            $ping = true;
                        } else {
                            // Si la section source a au moins un filtre mais que le widget n'en n'a aucun, il faut lui ajouter
                            if (empty($subsectionIndex) && $sectionHasSubsections) {
                                $this->paramsManager->set("", "sections/contentSource/params/category_index", 0, "sections", $param->id);
                                $ping = true;
                                $subsectionIndex = 0;

                                $this->checkContentSourceParams($widget);
                            }
                        }

                        // Ensuite On teste la validité des Categories

                        // Correction de l'index categorie si on reorder
                        if (isset($array_map_subsections) && isset($subsectionIndex) && isset($array_map_subsections[$subsectionIndex]) && $array_map_subsections[$subsectionIndex] != $subsectionIndex) {
                            $subsectionIndex = $array_map_subsections[$subsectionIndex];
                            $this->paramsManager->set("", "sections/contentSource/params/category_index", $subsectionIndex, "sections", $param->id, 1, 0);
                            $ping = true;
                        }

                        if (isset($subsectionIndex)) {
                            $disabled = !empty($section) && $this->paramsManager->get("", "sections/subsections/$subsectionIndex/disabled", "sections", $section->id_param);
                            if ($disabled) {
                                //$errorTypeWidget = "subsectiondisabled";
                            } elseif (empty($section) || empty($this->paramsManager->get("", "sections/subsections/$subsectionIndex/title", "sections", $section->id_param))) {
                                //$errorTypeWidget = "subsectiondel";
                                $this->paramsManager->delete("", "sections/contentSource/params/category_index", "sections", $param->id);
                                $ping = true;
                            }
                        }
                    }
                }

                if (!empty($errorTypeWidget)) {
                    $this->paramsManager->set("", "sections/notValid", "1", $param->type, $param->id, 1, 0);
                    $this->paramsManager->set("", "sections/notValidType", $errorTypeWidget, $param->type, $param->id, 1, 0);
                } else {
                    $this->paramsManager->delete("", "sections/notValid%", $param->type, $param->id);
                }
            }

            if ($ping) {
                $this->pingApiContent($widget);
            }
        }
    }

    /**
     * @param Section $section
     * @return array
     */
    public function getWidgetsRelatedToSectionParams(Section $section)
    {
        $list_widget_params = $this->paramsManager->getParamByValue($section->getWebzine(), "", "sections/sectionId", $section->id_param);

        $list_widgets = [];
        foreach ($list_widget_params as $param) {
            if (!array_key_exists($param->id, $list_widgets) && $widget = $this->get($param->id)) {
                $list_widgets[$param->id] = $widget;
            }
        }

        return array($list_widgets, $list_widget_params);
    }

    /**
     * Regarde si les contenus des widgets dynamic sont valides
     * @param Webzine $webzine le webzine
     */
    public function checkAllContentValid(Webzine $webzine)
    {
        if (!$webzine->isV4()) {
            return;
        }

        $params = $this->paramsManager->getParamByObjet($webzine, "", "sections/sectionId");
        foreach ($params as $param) {
            $this->checkContentValid($webzine, parent::get($param->valeur), null);
        }

        // On refait un tour widget par widget
        $widgets = $this->getAll($webzine, array("", "stock", "invis"));
        foreach ($widgets as $widget) {
            $this->getContentSection($widget);
        }
    }

    public function getContentSection(Widget $widget)
    {
        // On verifie qu'il est bien en mode dynamic
        if (!$widget->isContentWidget()) {
            $this->paramsManager->delete("", "sections/notValid%", "sections", $widget->id_param);
            return;
        }

        $id_section = $this->paramsManager->get("", "sections/sectionId", "sections", $widget->id_param);
        $section = parent::get($id_section, true, false);
        if (!$section) {
            $this->paramsManager->set("", "sections/notValid", "1", "sections", $widget->id_param, 1, 0);
            $this->paramsManager->set("", "sections/notValidType", "del", "sections", $widget->id_param, 1, 0);
        }

        return $section;
    }

    public function checkContentSourceParams(Widget $widget)
    {
        $this->paramsManager->delete("", "sections/contentSource/params/geoloc", "sections", $widget->id_param);
        $this->paramsManager->delete("", "sections/contentSource/params/order", "sections", $widget->id_param);
        $id_section = $this->paramsManager->get("", "sections/sectionId", "sections", $widget->id_param);
        if ($section = parent::get($id_section)) {
            if ($section->getTypeSection() == "GBModuleTypeMaps") {
                $this->paramsManager->set("", "sections/contentSource/params/geoloc", 1, "sections", $widget->id_param, 1, 0);
            }
            if ($section->getTypeSection() == "GBModuleTypeAgenda") {
                $this->paramsManager->set("", "sections/contentSource/params/order", "ASC", "sections", $widget->id_param, 1, 0);
            }

            if ($section->getTypeSection() == "GBModuleTypeUserslist") {
                $category_index = $this->paramsManager->get("", "sections/contentSource/params/category_index", "sections", $widget->id_param);
                if (isset($category_index)) {
                    $group = $this->paramsManager->get("", "sections/subsections/" . $category_index . "/ids", "sections", $section->id_param);
                    $url = $section->getCategoriesUrl($category_index);
                    $url = str_replace("[GROUP]", $group, $url);
                    $this->paramsManager->set("", "sections/contentSource/url", $url, "sections", $widget->id_param, 1, 0);
                } else {
                    $this->paramsManager->delete("", "sections/contentSource/url", "sections", $widget->id_param);
                }
            }

            if ($section->getTypeSection() == "GBModuleTypeCommerce") {
                $collectionId = $this->paramsManager->get("", "sections/commerceSource/collectionId", "sections", $section->id_param);
                $sort = $this->paramsManager->get("", "sections/commerceSource/sort", "sections", $section->id_param);
                $this->paramsManager->set("", "sections/commerceSource/collectionId", $collectionId, "sections", $widget->id_param, 1, 0);
                $this->paramsManager->set("", "sections/commerceSource/sort", $sort, "sections", $widget->id_param, 1, 0);
            }
        }
    }

    public function checkMargins(Widget $widget)
    {
        $marginSize = $this->paramsManager->get(Section::$defaultPlatform, "sections/marginSize", "sections", $widget->id_param);
        $margins = ["small" => [5, 5, 0, 0, 10, 10, 20, 20], "medium" => [10, 10, 0, 0, 20, 20, 40, 40]];
        if (array_key_exists($marginSize, $margins)) {
            $this->paramsManager->set(Section::$defaultPlatform, "sections/margin/top", $margins[$marginSize][0], "sections", $widget->id_param, 1, 0);
            $this->paramsManager->set(Section::$defaultPlatform, "sections/margin/bottom", $margins[$marginSize][1], "sections", $widget->id_param, 1, 0);
            $this->paramsManager->set(Section::$defaultPlatform, "sections/margin/left", $margins[$marginSize][2], "sections", $widget->id_param, 1, 0);
            $this->paramsManager->set(Section::$defaultPlatform, "sections/margin/right", $margins[$marginSize][3], "sections", $widget->id_param, 1, 0);
            $this->paramsManager->set(Section::$defaultPlatform, "sections/tabletMargin/top", $margins[$marginSize][4], "sections", $widget->id_param, 1, 0);
            $this->paramsManager->set(Section::$defaultPlatform, "sections/tabletMargin/bottom", $margins[$marginSize][5], "sections", $widget->id_param, 1, 0);
            $this->paramsManager->set(Section::$defaultPlatform, "sections/desktopMargin/top", $margins[$marginSize][6], "sections", $widget->id_param, 1, 0);
            $this->paramsManager->set(Section::$defaultPlatform, "sections/desktopMargin/bottom", $margins[$marginSize][7], "sections", $widget->id_param, 1, 0);
        }
    }

    public function pingApiContent(Widget $widget)
    {
        if ($section = $this->getContentSection($widget)) {
            $url = $section->getContentUrl() . $section->getContentParams();
            $params = str_replace("&amp;", "&", $widget->getContentParams());

            $per_page = $this->paramsManager->get(Section::$defaultPlatform, "sections/nbItems", "sections", $widget->id_param);

            // Exception Agenda
            if ($widget->getTypeSection() == "GBWidgetTypeEvent") {
                $per_page = 24;
            }

            $url = $this->getDI()->getConfig()->gl_url_goodbarber_api . $url . (preg_match("#\?#", $url) ? "&" : "?") . "local=1&per_page=" . $per_page . "&" . $params;
            $obj_url = $this->getDI()->getUrl();
            list($result, $info, $curl_errno) = $obj_url->callUrl($url);

            return "changePageInPreview('refresh-widget', {widgetId: " . $widget->id_param . "});\n";

            /*
            $log = $url. " -> ".$result." ".$info." ".$curl_errno."\n\n\n";

            file_put_contents("/tmp/pingApiContent.txt", $log, FILE_APPEND);*/
        }
    }


    /**
     * Teste si on peut ajouter un type de widget
     * @param Webzine $webzine le webzine
     * @param string $typeWidget le type du widget
     * @param bool $checkSectionExiste on passe faux quand on l'ajoute en meme temps que la section pour eviter les problèmes
     * @return bool
     */
    public function canAddWidget(Webzine $webzine, $typeWidget, $checkSectionExiste = true)
    {
        if ($typeWidget != "GBWidgetTypeContent" && !isset(Widget::$GB_type_widget[$typeWidget])) {
            return false;
        }

        /*
         * Widgets Not commerce:
         */
        if (in_array($typeWidget, Widget::$GB_forbidden_for_shop) && $webzine->isShopPlan()) {
            return false;
        }


        if (in_array($typeWidget, ["GBWidgetTypeContent", "GBWidgetTypeNavigation"])) {
            return true;
        }

        /**
         * Si c'est un widget reserver au webzine avec un PLAN webapp
         */
        if (in_array($typeWidget, Widget::$GB_only_for_webapp) && !$webzine->isOnlyWebAppPlan()) {
            return false;
        }
        /**
         * Si c'est un widget unique et qu'on en a deja un alors return FALSE
         */
        if (in_array($typeWidget, Widget::$GB_unique_widget) && (count($this->getAllByType($webzine, $typeWidget))) >= 1) {
            return false;
        }

        /**
         * Si le widget est en stock et qu'on est pas en goodip on sort
         */
        if (Widget::$GB_type_widget[$typeWidget]["etat"] === 'stock' && !\Control::devGoodIp()) {
            return false;
        }

        /**
         * Si le widget est lié a une section, mais que l'app n'a pas ce type de section on affiche pas le widget
         */
        if ($checkSectionExiste && !empty(Widget::$GB_type_widget[$typeWidget]["contentType"])) {
            if (count($this->sectionManager->getAllByType($webzine, Widget::$GB_type_widget[$typeWidget]["contentType"])) == 0) {
                return false;
            }
        }

        /**
         * cas particulier
         */
        if ($typeWidget === "GBWidgetTypeAds") {
            $acl = $this->getDI()->getAcl();
            if (!$acl->isAddonEnable("adsinternal") && !$acl->isAddonEnable("adsexternal")) {
                return false;
            }
        }


        /*
         * Widgets Commerce: control on plan
         */
        if (preg_match("/^GBWidgetTypeCommerce/", $typeWidget)) {
            if (!$webzine->isShopPlan()) {
                return false;
            }
        }

        if ($webzine->isShopPlan()) {
            $acl = $this->getDI()->getAcl();
            if ($typeWidget === "GBWidgetTypeMap" && !$acl->isAddonEnable("storelocator")) {
                return false;
            }
            if ($typeWidget === "GBWidgetTypeArticle" && !$acl->isAddonEnable("blog")) {
                return false;
            }
        }

        return true;
    }

    /**
     * Set Links for Commerce sections in widget GBWidgetTypeCommercecollectionslist
     * @param Webzine $webzine
     * @param Widget $widget
     * @param array $listIdSections
     */
    public function setLinksInGBWidgetTypeCommercecollectionslist(Webzine $webzine, Widget $widget, $listIdSections = [])
    {
        if (empty($widget)) return;

        $widgetElementManager = new ElementManager($webzine, "sections/elements");
        $widgetElementManager->type = "sections";
        $widgetElementManager->id_section = $widget->id_param;

        if (!empty($listIdSections)) {
            foreach ($listIdSections as $id) {
                /** @var Section $commerceSection */
                $commerceSection = $this->sectionManager->get($id);
                if (!$commerceSection) {
                    continue;
                }

                $newUrl = $this->getDI()->getLinkManager()->generateSectionUrl(array(
                    "typeLink" => "GBLinkTypeSection",
                    "sectionId" => $commerceSection->id_param
                ));

                $elemData = [
                    "title" => $commerceSection->getNom(),
                    "description" => "",
                    "cellBackgroundImage/imageUrl" => "",
                    "buttonEnabled" => 1,
                    "buttonTitle" => $this->getDI()->getTranslater()->get("GBWIDGET_32"),
                    "link/type" => "GBLinkTypeSection",
                    "link/sectionId" => $commerceSection->id_param,
                    "link/url" => $newUrl
                ];

                $widgetElementManager->add($elemData);
            }
        }
    }

    /**
     * Set Links for Legal sections (ToS, Refund, Privacy) in widget GBWidgetTypeCommercelegal
     * @param Webzine $webzine
     * @param Widget $widget
     */
    public function setLinksInGBWidgetTypeCommercelegal(Webzine $webzine, Widget $widget)
    {
        if (empty($widget)) return;

        $widgetElementManager = new ElementManager($webzine, "sections/elements");
        $widgetElementManager->type = "sections";
        $widgetElementManager->id_section = $widget->id_param;

        $sections = $this->sectionManager->getAllByType($webzine, "GBModuleTypeCommercetos", true);

        if (!empty($sections)) {
            /** @var Section $legalSection */
            foreach ($sections as $legalSection) {
                $newUrl = $this->getDI()->getLinkManager()->generateSectionUrl(array(
                    "typeLink" => "GBLinkTypeSection",
                    "sectionId" => $legalSection->id_param
                ));

                $elemData = [
                    "title" => $legalSection->getNom(),
                    "link/type" => "GBLinkTypeSection",
                    "link/sectionId" => $legalSection->id_param,
                    "link/url" => $newUrl
                ];

                $widgetElementManager->add($elemData);
            }
        }
    }

    /**
     * Set texts in widget GBWidgetTypeCommercepromo
     * @param Webzine $webzine
     * @param Widget $widget
     */
    public function setTextsInGBWidgetTypeCommercepromo(Webzine $webzine, Widget $widget)
    {
        if (empty($widget)) return;

        $translater = $this->getDI()->getTranslater();

        $this->paramsManager->set(Section::$defaultPlatform, "sections/actionButton/title", $translater->get("GBWIDGET_28"), "sections", $widget->id_param, 1, 0);
        $this->paramsManager->set(Section::$defaultPlatform, "sections/promoBannerTitle", $translater->get("GBWIDGET_31"), "sections", $widget->id_param, 1, 0);
    }

    public function hasDesign($typeWidget)
    {
        if (in_array($typeWidget, ["GBWidgetTypeCustom", "GBWidgetTypeCustompwa", "GBWidgetTypeAds"])) {
            return false;
        }

        return true;
    }

    public function isOnlyAvailableIfContentTypeSpecified(Webzine $webzine, $typeWidget)
    {
        if (isset(Widget::$GB_type_widget[$typeWidget]["onlyContentType"])) {
            return Widget::$GB_type_widget[$typeWidget]["onlyContentType"];
        }

        // En Shop Plan, ces widgets sont dispos uniquement si on specifie le type de GBWidgetTypeContent
        // Ex: on ne peut pas ajouter un widget GBWidgetTypeContent et choisir le type de contenu après,
        // Il faut absolument choisir d'ajouter un widget de type Map, Article, etc...
        if ($webzine->isShopPlan() && in_array($typeWidget, ["GBWidgetTypeMap", "GBWidgetTypeArticle"])) {
            return true;
        }
    }
}
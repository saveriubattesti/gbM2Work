<?php

namespace Engine\GBBackOffice\V4\Listeners\Widget;


use Common\Lib\Goodbarber\Config;
use Common\Lib\Goodbarber\ConfigAd;
use Common\Lib\Goodbarber\Duplication\SectionDuplicator;
use Common\Lib\Goodbarber\Sections\Section;
use Common\Models\Webzine;
use Engine\GBBackOffice\V4\Lib\Widgets\Widget;
use Engine\GBBackOffice\V4\Lib\Widgets\WidgetSettingsUpdater;

class ContentViewListener extends \Phalcon\Mvc\User\Component
{
    public function updateInPreview($event, $view, $data = [])
    {
        $view->nb_widgets = $this->widgetManager->getAllWidgets($this->webzine, $view->sectionHome, array("", "stock"));

        $js = "";
        if ($view->nb_widgets == 1 && isset($data["just_added"])) {
            $js .= "updateParamInPreview('widgets', {});";
        }

        $silent = !empty($data["silent_binding"]);

        foreach ($view->widget->getParents() as $parent) {
            if ($view->widget->etat != "stock") {
                $js .= $this->preview->updateObjectInPreview(
                        $this->webzine,
                        "widgets/" . $view->widget->id_param,
                        [],
                        [],
                        $silent
                    ) . "\n";
            }
            $js .= $this->preview->updateParamInPreview(
                $this->webzine,
                array("objet" => "sections/" . $parent->id_param . "/widgetTargets"),
                array_keys($this->widgetManager->getAllWidgets($this->webzine, $parent)),
                [],
                $silent
            );
        }

        if (empty($data["just_js"])) {
            $js = "<script type=\"text/javascript\">" . $js . "</script>\n";
        } else {
            $js .= "\n";
        }

        return $js;
    }

    public function renderAddElement($event, $view, $data = [])
    {
        /*
         * Add widget bullet
         */
        $dataElement = [];
        $widgetsDispo = $this->factoryElement->getElementsDispo('widgets');
        if (!empty($widgetsDispo)) {
            foreach ($widgetsDispo as $type => $widget) {
                $dataElement[$type] = $this->formateDataAddElement($type, ($data["key"] + 1));
            }
        }

        $addElementArray = [
            'elementsDispo' => $widgetsDispo,
            'datas' => $dataElement,
            'btn_label' => isset($data["btn_label"]) ? $data["btn_label"] : "",
            'btn_class' => isset($data["btn_class"]) ? $data["btn_class"] : ""
        ];

        // Si on a qu'une section search on ajoute directement le widget relié a cette section sans afficher la liste
        if (array_key_exists("GBWidgetTypeSearch", $addElementArray["elementsDispo"])) {
            $searchSection = $this->sectionManager->getAllByType($this->webzine, "GBModuleTypeSearch");
            if (count($searchSection) === 1) {
                $addElementArray['datas']["GBWidgetTypeSearch"] = $this->formateDataAddElement("GBWidgetTypeSearch", ($data["key"] + 1));
            }
        }
        // On fait la meme chose pour les sections commerce search
        if (array_key_exists("GBWidgetTypeCommercesearch", $addElementArray["elementsDispo"])) {
            $commerceSearchSection = $this->sectionManager->getAllByType($this->webzine, "GBWidgetTypeCommercesearch");
            if (count($commerceSearchSection) === 1) {
                $addElementArray['datas']["GBWidgetTypeCommercesearch"] = $this->formateDataAddElement("GBWidgetTypeCommercesearch", ($data["key"] + 1));
            }
        }

        $retu = $view->getRender($view->panelPath . "/app/content", "add-widget", $addElementArray, function ($view) {
            $view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
        });

        return $retu;
    }

    /**
     * Formate params for the modal Add widget
     * @param $typeWidget
     * @param $position
     * @return array
     */
    private function formateDataAddElement($typeWidget, $position)
    {
        $widgetName = strtolower(preg_replace("/^GBWidgetType/", "", $typeWidget));

        $dynamicDisplayExceptions = ["GBWidgetTypeContent"];

        if (in_array($typeWidget, $dynamicDisplayExceptions) || (Widget::$GB_type_widget[$typeWidget]["type"] == "dynamic" && !empty(Widget::$GB_type_widget[$typeWidget]["contentType"]))) {
            $hash = '#widget-content-add';
            if ($this->widgetManager->isOnlyAvailableIfContentTypeSpecified($this->webzine, $typeWidget)) {
                $hash = '#widget-content-add-' . $widgetName;
            }
            $retu = [
                'attr' => 'onclick="$(\'#popup-add-element\').removeClass(\'open\')" data-target="#panel-carousel" data-ajax-slide-to="2" data-loading="true" data-ajax-target="#panel-carousel-item-2"',
                'href' => $this->url->getAppHashUrl($hash)
            ];
        } else {
            $retu = [
                'attr' => 'data-ajax="' . $this->url->getUrl("widget/addInTree/?type=" . $widgetName) . '" data-loading="true" data-container="#panel-carousel-item-1" data-loading-container="#panel"',
                'href' => 'javascript:void(0)'
            ];
        }

        return $retu;
    }

    public function renderSettings($event, $view, $data)
    {
        if (in_array($view->widget->getTypeSection(), \Engine\GBBackOffice\V4\Lib\Widgets\Widget::$GB_unique_widget)) {
            $data["hideDuplicate"] = true;
        }

        // Widget avec source
        if ($view->widget->isContentWidget()) {
            $errorType = $view->widget->getContentErrorType();
            if (!empty($errorType)) {
                $view->SESS_error_widget = $view->widget->getErrorMessage($errorType);
                if ($errorType == "stock") {
                    $view->SESS_error_class = "alert-warning";
                }
                //$data["disabledStatus"] = true;
                $data["hideDuplicate"] = true;
            }

            $data["showSource"] = true;

            $restriType = Widget::$GB_type_widget[$view->widget->getTypeSection()]["contentType"];

            $selectSourceArray = [];
            $tree = $this->treeManager->getTree($this->webzine, 0, 0, "tab");
            foreach ($tree as $section) {
                $selectSourceArray += $this->getSections($view, $section, $restriType);
            }

            if (!empty($selectSourceArray)) {
                $selectSourceArray = ["" => "− " . $this->translater->get("SELECT") . " −"] + $selectSourceArray;
                $view->selectSourceArray = $selectSourceArray;
            } else {
                $data["generalError"] = true;
            }
        }

        $retu = $view->getRender($view->panelPath . "/app/widget/content", "index", $data, function ($view) {
            $view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
        });

        return $retu;
    }

    public function renderSpecific($event, $view, $data)
    {
        $typeWidget = $view->widget->getTypeSection();
        $method = "initialize" . $typeWidget;
        if (method_exists($this, $method)) {
            $data = $this->$method($event, $view, $data);

            $retu = $view->getRender($view->panelPath . "/app/widget/" . $typeWidget . "/content", "index", $data, function ($view) {
                $view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
            });

            return $retu;
        }
    }

    private function initializeGBWidgetTypeCustom($event, $view, $data)
    {
        $view->dirFiles = $this->webzine->getPath("apps", "section/" . $view->widget->id_param, true);
        $view->pathFile = $view->dirFiles . "/index.html";

        if ($this->file->is_file($view->pathFile)) {
            $view->code = $this->file->file_get_contents($view->pathFile);
        }

        return $data;
    }

    private function initializeGBWidgetTypeCustompwa($event, $view, $data)
    {
        return $this->initializeGBWidgetTypeCustom($event, $view, $data);
    }

    private function modGBWidgetTypeCustom($event, $view, $data)
    {
        $js = "";

        $this->initializeGBWidgetTypeCustom($event, $view, $data);

        if ($this->request->isPost() && !empty($action)) {
            $data["action"] = $action;
        }

        $data["action"] = "saveFile";
        $data["is_widget"] = true;
        $data["val"] = $this->request->getPost("code");

        $view->section = $view->widget;
        $view->typeSection = $view->typeWidget;

        $this->eventsManager->fire("contentSpecificView:renderContentGBModuleTypePlugin", $view, $data);

        return $js;
    }

    private function modGBWidgetTypeCustompwa($event, $view, $data)
    {
        return $this->modGBWidgetTypeCustom($event, $view, $data);
    }


    private function initializeGBWidgetTypeAds($event, $view, $data)
    {
        $data["platforms"] = Config::getAllPlatformAdsDispo($this->webzine);

        $data["campagnes"] = $this->webzine->getAdsCampagne(array(
            "conditions" => "etat<>'del' AND page_iphone_app=1",
            "order" => "id_campagne DESC"
        ));

        $hasCampagne = $data["campagnes"]->count() > 0;

        $hasExternal = $this->acl->isAddonInstalled("adsexternal");

        if ($this->acl->isAddonInstalled("adsinternal")) {
            if ($this->acl->isAddonEnable("adsinternal")) {
                $title = $this->translater->get('GBWIDGET_SETTINGS_49');
                $href = $this->url->getUrl("audience/ads/internal/add/");
                $btn = $this->translater->get('ADS_INTERNAL_4');
            } else {
                $title = $this->translater->get('GBWIDGET_SETTINGS_52');
                $href = $this->url->getUrl("addons/management/");
                $btn = $this->translater->get('ACTIVER');
            }

            $view->goToAddLabel = $this->translater->get("GBWIDGET_SETTINGS_55");
            $view->goToAddLink = $this->url->getUrl("audience/ads/internal/add/");
            $view->goToAddText = $this->translater->get("ADS_INTERNAL_4");

        } else {
            if ($hasExternal) {
                if ($this->acl->isAddonEnable("adsexternal")) {
                    $title = $this->translater->get('GBWIDGET_SETTINGS_50');
                    $href = $this->url->getUrl("audience/ads/external/");
                    $btn = $this->translater->get('ACTIVER');
                } else {
                    $title = $this->translater->get('GBWIDGET_SETTINGS_52');
                    $href = $this->url->getUrl("addons/management/");
                    $btn = $this->translater->get('ACTIVER');
                }
            } else {
                $title = $this->translater->get('GBWIDGET_SETTINGS_51');
                $href = $this->url->getUrl("addons/list/");
                $btn = $this->translater->get('GBWIDGET_SETTINGS_53');
            }

            $view->goToAddLabel = $this->translater->get("GBWIDGET_SETTINGS_61");
            $view->goToAddLink = $this->url->getUrl("audience/ads/external/#platform");
            $view->goToAddText = $this->translater->get("GBWIDGET_SETTINGS_62");
        }

        if (!$hasCampagne && !$hasExternal) {
            $view->noAds = true;
            $view->noAdsTitle = $title;
            $view->noAdsHref = $href;
            $view->noAdsBtn = $btn;
            return;
        }

        $nbHomeAds = array();
        foreach ($data["platforms"] as $p => $text) {
            $campagnes = ["" => " - " . $this->translater->get("GBWIDGET_SETTINGS_54") . " - "];
            foreach ($data["campagnes"] as $campagne) {
                if (in_array($p, $campagne->platforms) || in_array($p, $campagne->platforms)) {
                    if (intval($campagne->countRegiePub(array("conditions" => "placement='home'"))) > 0) {
                        $nbHomeAds[] = $campagne->id_campagne;
                    }
                    $campagnes[$this->translater->get("ADS_INTERNAL_21")][$campagne->id_campagne] = $campagne->titre;
                }
            }

            if ($hasExternal) {
                foreach (ConfigAd::getAdsExternal($this->webzine) as $external => $infos) {
                    $whatp = ($p == "html5" ? "webapp" : $p);

                    $enabled = $this->paramsManager->get($p, "ads/external/$external/enabled");

                    if ($enabled && in_array($whatp, array_keys($infos["homeplatforms"]))) {
                        $campagnes[$this->translater->get("ADS_EXTERNAL_5")][$external] = ucfirst($external);
                    }
                }
            }

            $id = $this->paramsManager->get("", "sections/campain/" . $p . "/id", "sections", $view->widget->id_param);

            $select = $format = "";
            if (count($campagnes) > 1) {
                $select = $this->tag->selectStatic([
                        "campagnes[$p]",
                        $campagnes,
                        "class" => "form-control",
                        "value" => $id
                    ]
                );
            }

            $data["selectPlatforms"][$p] = $select;
            $data["selectFormats"][$p] = $this->showGBWidgetTypeAdsFormat($event, $view, ["platform" => $p, "service" => $id]);
        }

        $view->nbHomeAds = $nbHomeAds;

        $this->registry->inlineCss .= "
        .campaign-platform {margin: 20px 0 5px 0;} 
        .campaign-platform i { font-size: 1.6em; margin-right: 5px; }
        .campaign-platform .fa-android { color: #A4C639; }
        .campaign-platform .icon-pwa { position: relative; top: 5px; color: #e74919; }
        ";

        return $data;
    }

    private function modGBWidgetTypeAds($event, $view, $data)
    {
        $data["campagnes"] = $this->request->getPost("campagnes");
        $data["formats"] = $this->request->getPost("formats");
        $data["just_js"] = true;
        $js = $this->saveGBWidgetTypeAds($event, $view, $data);

        return $js;
    }

    public function saveGBWidgetTypeAds($event, $view, $data)
    {
        $before = serialize($this->paramsManager->get("", "sections/campain/%", "sections", $view->widget->id_param));
        $this->paramsManager->delete("", "sections/campain/%", "sections", $view->widget->id_param);

        foreach ($data["campagnes"] as $p => $val) {
            if (!empty($val)) {
                $this->paramsManager->set("", "sections/campain/" . $p . "/type", (is_numeric($val) ? "internal" : "external"), "sections", $view->widget->id_param);
                $this->paramsManager->set("", "sections/campain/" . $p . "/id", $val, "sections", $view->widget->id_param);

                $format = isset($data["formats"][$p]) ? $data["formats"][$p] : "";
                $this->paramsManager->set("", "sections/campain/" . $p . "/format", $format, "sections", $view->widget->id_param);
            }
        }

        $after = serialize($this->paramsManager->get("", "sections/campain/%", "sections", $view->widget->id_param));

        if ($before != $after) {
            $this->paramsLogger->log(Section::$defaultPlatform, "gbpublish_section_content_modified", $view->widget->getNom(), Section::$defaultPlatform, $view->widget->id_param);
        }

        $js = $this->updateInPreview($event, $view, $data);

        return $js;
    }

    public function showGBWidgetTypeAdsFormat($event, $view, $data)
    {
        $p = $data["platform"];

        $array = [];
        $allformats = ["banner" => $this->translater->get("APPS_REGIE_3"), "rectangle" => $this->translater->get("ADS_INTERNAL_29")];

        $config = ConfigAd::getAdsExternal($this->webzine);
        if (!empty($config[$data["service"]]["homeplatforms"][$p])) {
            foreach ($config[$data["service"]]["homeplatforms"][$p] as $format) {
                $array[$format] = $allformats[$format];
            }
        } elseif (is_numeric($data["service"])) {
            $array = $allformats;
            if ($p == "ipad") {
                unset($array["rectangle"]);
            }
        } else {
            return "";
        }

        $value = $this->paramsManager->get("", "sections/campain/" . $p . "/format", "sections", $view->widget->id_param);
        if (empty($value)) {
            $value = array_keys($allformats)[0];
        }

        if (count($array) == 1) {
            $format = $this->tag->hiddenField(["formats[$p]", "value" => $value]);
        } else {
            $format = $this->tag->selectStatic([
                "formats[$p]",
                $array,
                "class" => "form-control",
                "value" => $value
            ]);
        }

        return $format;
    }

    public function renderFooter($event, $view, $data)
    {
        $retu = $view->getRender($view->panelPath . "/app/widget/content", "footer", $data, function ($view) {
            $view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
        });

        return $retu;
    }

    public function renderDuplicate($event, $view, $data)
    {
        $retu = $view->getRender($view->panelPath . "/app/widget/content", "duplicate", $data, function ($view) {
            $view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
        });

        return $retu;
    }

    public function renderDeleteActionPopover($event, $view, $data)
    {
        $widget = $data["widget"];

        $more = !empty($data["fromSettings"]) ? "&fromSettings=1" : "";

        $onYesUrl = $this->url->getUrl("widget/deleteInTree/?id=" . $widget->id_param . $more);

        $onYesJs = "$('#li-widget-" . $widget->id_param . "').remove();deleteParamInPreview('widgets." . $widget->id_param . "');";
        $onYesJs .= "$.customPost('" . $onYesUrl . "');";

        $arrayDelWidget = array(
            "content" => $this->translater->get("GBWIDGET_3"),
            "onYesJs" => $onYesJs,
        );

        return !$view->sectionsManagementForbidden ? $this->ui->popoverAlert($arrayDelWidget) : "";
    }

    public function renderSource($event, $view, $data)
    {
        $data["selectedSource"] = "";

        $errorType = $view->widget->getContentErrorType();
        if (empty($errorType)) {
            $section = $this->widgetManager->getContentSection($view->widget);
            if ($section) {
                $data["selectedSource"] = $section->id_param;
            }

            $subsectionIndex = $this->paramsManager->get("", "sections/contentSource/params/category_index", "sections", $view->widget->id_param);
            if (isset($subsectionIndex)) {
                $data["selectedSource"] .= "-" . $subsectionIndex;
            }
        }

        return $view->getRender($view->panelPath . "/app/widget/content", "source", $data, function ($view) {
            $view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
        });
    }

    /*
     * @deprecated ABTESTING panel-home
     */
    public function renderTreeSections($event, $view, $data)
    {
        if (\Control::goodIp()) {
            $this->registry->inlineCssPersistant .= "#panel ul.nav.panel-design.content-list > li > nav .goodip-service { display: inline !important; }";
        }

        $view->headerBackTo = $this->url->getAppHashUrl("#content-home");

        $restriType = null;

        if (!empty($view->typeWidget)) {
            $restriType = Widget::$GB_type_widget[$view->typeWidget]["contentType"];
        }

        $selectSourceArray = [];
        $tree = $this->treeManager->getTree($this->webzine, 0, 0, "tab");
        foreach ($tree as $section) {
            $selectSourceArray += $this->getSections($view, $section, $restriType, "tree");
        }

        $view->tree = $selectSourceArray;

        // TODO ABTESTING WIDGET
        $data["split_home_content_design"] = true;

        return $view->getRender($view->panelPath . "/app/widgets/sections", "list", $data, function ($view) {
            $view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
        });
    }

    /*
     * @deprecated ABTESTING panel-home
     */
    public function renderSection($event, $view, $data)
    {
        $data = $this->initializeSection($view, $data);

        // TODO ABTESTING WIDGET
        $data["split_home_content_design"] = true;

        $retu = $view->getRender($view->panelPath . "/app/widgets/sections", "section", $data, function ($view) {
            $view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
        });

        return $retu;
    }

    private function getSections($view, Section $section, $restriType, $mode = "select")
    {
        $retu = [];

        $typeSection = $section->getTypeSection();
        if (!isset($restriType) || $restriType == $typeSection) {
            foreach (Widget::$GB_type_widget as $typeWidget => $confWidget) {
                if ($confWidget["type"] == "dynamic" && $confWidget["contentType"] == $typeSection) {
                    // If a widget exists for a contentType (Search, Commerceproducts...), do not display this content type for general Content widget
                    if (!isset($restriType) && $this->widgetManager->isOnlyAvailableIfContentTypeSpecified($this->webzine, $typeWidget)) continue;

                    $service = $section->getService();
                    if ($section->isValid() && !in_array($service, Widget::$GB_service_exception) && !in_array($typeSection, Widget::$GB_section_exception)) {
                        $key = $section->id_param;
                        $is_mcms_multi_cat = false;
                        if ($section->getService() == "mcms" && in_array($typeSection, Section::$GB_mcms_section_muti_categories)) {
                            $key .= "-0";
                            $is_mcms_multi_cat = true;
                        }
                        if ($mode == "select") {
                            $subsections = $this->getSubsections($view, $section);

                            // Si pas MCMS et sans filtre, on affiche la section
                            if (!$is_mcms_multi_cat) {
                                if (empty($subsections)) {
                                    $retu[$key] = $section->getNom();
                                }
                            } else {
                                $retu[$key] = $section->getNom();
                            }
                            $retu += $subsections;
                        } else {
                            $retu[$key] = $section;
                        }
                    }
                }
            }
        }

        foreach ($section->children as $child) {
            $retu += $this->getSections($view, $child, $restriType, $mode);
        }

        return $retu;
    }

    private function initializeSection($view, $data)
    {
        $section = $data["section"];

        $typeSection = $section->getTypeSection();

        if (!isset($data["mode"])) {
            $data["mode"] = "";
        }

        $class = $typeSection;
        $class .= ($section->etat == "stock" ? " stock" : "");

        $prefix_index_subsection = $data["mode"] == "select" ? $section->id_param . "-" : "";
        $prefix_label_subsection = $data["mode"] == "select" ? " − " : "";

        $service = $section->getService();

        $is_mcms_multi_cat = ($service == "mcms" && in_array($typeSection, Section::$GB_mcms_section_muti_categories));

        /*
         * Categories si yen a
         */
        if (!empty($this->paramsManager->get("", "sections/subsectionsEnabled", "sections", $section->id_param))) {

            if ($list_subsections = $this->paramsManager->get("", "sections/subsections/%/title", "sections", $section->id_param)) {
                foreach ($list_subsections as $objet => $value) {
                    $index_subsection = preg_replace("#^sections/subsections/([0-9]+)/title$#", "\\1", $objet);

                    if (!$is_mcms_multi_cat || $index_subsection > 0) {
                        if ($data["mode"] == "select") {
                            $prefix_label_subsection = $section->getNom() . $prefix_label_subsection;
                        }
                        $data["categories"][$prefix_index_subsection . $index_subsection] = $prefix_label_subsection . $value;
                    }
                }
            }
        }

        $hrefAdd = "#";

        $cat = ($is_mcms_multi_cat ? "'0'" : "null");

        $dataAttrAdd = "onclick=\"addWidetFromSectionsList('" . $this->url->getUrl("widget/addInTree/") . "', 'dynamic', null, " . $section->id_param . ", " . $cat . ")\"";
        $buttonLabel = "<i class=\"material-icons text-success add\">add</i> " . $this->translater->get('GBWIDGET_CHOOSE_SECTION');

        $data["class"] = $class;
        $data["hrefAdd"] = $hrefAdd;
        $data["dataAttrAdd"] = $dataAttrAdd;
        $data["buttonLabel"] = $buttonLabel;
        $data["typeSection"] = $typeSection;
        $data["infosService"] = $this->eventsManager->fire("panelContentView:getHTMLInfosService", $view, $data);;
        $data["showAddOnSection"] = empty($data["categories"]) || $is_mcms_multi_cat;

        return $data;

    }

    private function getSubsections($view, Section $section)
    {
        $data = $this->initializeSection($view, ["section" => $section, "mode" => "select"]);
        $retu = isset($data["categories"]) ? $data["categories"] : [];

        return $retu;
    }

    public function modSettings($event, $view, $data)
    {
        $js = "";

        $view->widget->setNom($data["title"]);
        $js .= $this->modEtat($event, $view, array_merge($data, ["class" => "stock"]));
        $js .= $this->modSource($view, $data);

        $typeWidget = $view->typeWidget = $view->widget->getTypeSection();
        $method = "mod" . $typeWidget;
        if (method_exists($this, $method)) {
            $js .= $this->$method($event, $view, $data);
        }

        $view->SESS_confirm = $this->translater->get('AJOUTER_EQUIPE_36');
        $home = $this->sectionManager->getHome($this->webzine);

        $js .= $this->updateInPreview($event, $view, ["just_js" => true]);

        // TODO : remove 'update-section-list', add 'refresh-widget'
        $js .= "changePageInPreview('update-section-list', {sectionId: " . $home->id_param . "});";
        //$js .= "changePageInPreview('refresh-widget', {widgetId: " . $view->widget->id_param . "});\n";

        $js .= "$('#li-widget-" . $view->widget->id_param . " b').text('" . $this->security->cleanJs($view->widget->getNom(), false) . "');\n";
        $js .= "showAlert('#panel', 'alert-success', '" . $this->security->cleanJs($view->SESS_confirm, false) . "');\n";

        $retu = $this->renderSettings($event, $view, $data);

        return $retu . "<script type=\"text/javascript\">" . $js . "</script>\n";
    }

    public function modEtat($event, $view, $data)
    {
        $js = "";

        $widgetSettingsUpdater = new WidgetSettingsUpdater();

        if (empty($data["class"])) $data["class"] = "disabled";

        $retu = $widgetSettingsUpdater->modEtat($view->widget, $data["status"]);
        if ($retu) {
            $js .= "$('#li-widget-" . $view->widget->id_param . "')." . ($view->widget->etat != "stock" ? "removeClass" : "addClass") . "('" . $data["class"] . "');\n";
        }

        return $js;
    }

    private function modSource($view, $data)
    {
        if (!$view->widget->isContentWidget()) {
            return;
        }

        $js = "";

        $source = $data["source"];
        if (preg_match("#-#", $source)) {
            list($sectionId, $indexSubsection) = explode("-", $source);
        } else {
            $sectionId = $source;
            $indexSubsection = null;
        }

        // On reset la section liée au widget
        $retu = $this->paramsManager->set("", "sections/sectionId", $sectionId, "sections", $view->widget->id_param, 1, 0);

        // Si cat on sauvegarde aussi le subsectionIndex
        if (isset($indexSubsection)) {
            $retu += $this->paramsManager->set("", "sections/contentSource/params/category_index", $indexSubsection, "sections", $view->widget->id_param, 1, 0);
        } else {
            $retu += $this->paramsManager->delete("", "sections/contentSource/params/category_index", "sections", $view->widget->id_param);
        }

        if ($retu) {
            $this->paramsLogger->log(Section::$defaultPlatform, "gbpublish_widget_content_modified", $view->widget->getNom(), Section::$defaultPlatform, $view->widget->id_param);
        }

        $this->widgetManager->checkContentValid($this->webzine, $this->sectionManager->get($sectionId));

        // Si c'est un tri auquel on n'a pas droit, on reset les params de tri
        $old_order = $this->paramsManager->get("", "sections/contentSource/params/sort", "sections", $view->widget->id_param);
        if (empty($old_order) || !array_key_exists($old_order, $view->widget->getOrderListArray())) {
            $this->paramsManager->delete("", "sections/contentSource/params/sort", "sections", $view->widget->id_param);
            $this->widgetManager->checkContentSourceParams($view->widget);
        }

        $js .= "$('#li-widget-" . $view->widget->id_param . "').removeClass('invalid');\n";
        return $js;
    }


    /**
     * Ajout d'un widget
     * @param $event
     * @param $view
     * @param $data
     * @return string
     */
    public function add($event, $view, $data = [])
    {
        $id_section = 0;
        $indexSubsection = null;


        if (isset($data["idSection"])) {
            $id_section = $data["idSection"];

            if (isset($data["indexSubsection"])) {
                $indexSubsection = $data["indexSubsection"];
            }

            $section = $this->sectionManager->get($id_section);
            $typeSection = $section->getTypeSection();

            /**
             * S'il n'y pas de widget associé a cette section, ou qu'on ne peu pas l'ajouter on sort
             */
            $typeWidget = Widget::getTypeWidgetFromSection($typeSection);
            $nameWidget = $section->getNom();
        } else {
            $typeWidget = "GBWidgetType" . ucfirst($data["type"]);
            $nameWidget = "";
        }

        if (isset($data["typeWidget"])) {
            $typeWidget = $data["typeWidget"];
        }

        if (empty($typeWidget) || !$this->widgetManager->canAddWidget($this->webzine, $typeWidget, false)) {
            return "";
        }


        $home = $this->sectionManager->getHome($this->webzine);

        $nb_widgets = count($this->widgetManager->getAll($this->webzine, array("", "stock")));

        // Correction des positions avant d'ajouter
        $this->widgetManager->checkPositionBeforeAdd($this->webzine);

        if (!isset($data["position"]) || $typeWidget == "GBWidgetTypeCommercelegal") {
            $data["position"] = $nb_widgets;
        }

//        if ($typeWidget == "GBWidgetTypeCommercelegal") {
//            $data["position"] = 99;
//        }

        $widget = $this->widgetManager->add($this->webzine, $typeWidget, $nameWidget, 0, $home->id_param, true, ($data["position"] + 1), false, $id_section, $indexSubsection);

        if ($widget) {
            if ($typeWidget == "GBWidgetTypeCommercecollectionslist" && !empty($data["listIdSections"])) {
                $this->widgetManager->setLinksInGBWidgetTypeCommercecollectionslist($this->webzine, $widget, $data["listIdSections"]);
            }

            $gotohome = isset($data["gotohome"]) ? $data["gotohome"] : true;

            $view->SESS_confirm = str_replace("[TOKEN]", $widget->getNom(), $this->translater->get("GBLOG_75"));

            $js = $this->refreshJsAfterAdd($view, $widget, $gotohome);
            if (!$gotohome) {
                $js .= "var obj = $('#li-widget-" . $widget->id_param . "'); obj.addClass('editable'); obj.find('.new_section_name').focus();\n";
            }

            return $js;
        }
    }

    private function refreshJsAfterAdd($view, $widget, $gotohome = true)
    {
        $home = $this->sectionManager->getHome($this->webzine);

        // On refresh le cache
        $widgets = $this->widgetManager->getAll($this->webzine, array("", "stock"), true);

        // On nre cupere pas le retour JS, car on met le refresh widget à la fin
        $this->widgetManager->pingApiContent($widget);

        $js = "";

        if ($gotohome) {
            $js .= "changeHashWithLoad($.getHash().match(/content/) ? '#content-home' : '#home-widget-" . $widget->id_param . "');";
            $js .= "showAlert('#tab-content-list-widgets', 'alert-success', '" . $this->security->cleanJs($view->SESS_confirm, false) . "');\n";
        }

        if (count($widgets) == 1) {
            $js .= "updateParamInPreview('widgets', {});";
        }

        $widgetTargets = array_keys($this->widgetManager->getAllWidgets($this->webzine, $home));

        $js .= $this->preview->updateObjectInPreview($this->webzine, "widgets/" . $widget->id_param);
        $js .= $this->preview->updateParamInPreview($this->webzine, array("objet" => "sections/" . $home->id_param . "/widgetTargets"), $widgetTargets);

        // On met le refresh widget à la fin
        $js.= "changePageInPreview('refresh-widget', {widgetId: " . $widget->id_param . "});\n";

        return $js;
    }

    /**
     * Copie un widget
     * @param $event
     * @param $view
     * @param $data
     */
    public function copy($event, $view, $data = [])
    {
        if ($widget = $this->widgetManager->get($data["id_widget"])) {
            if (!$this->widgetManager->checkBeforeAdd($this->webzine, $widget->getTypeSection())) {
                return;
            }

            $home = $this->sectionManager->getHome($this->webzine);

            $sectionDuplicator = new SectionDuplicator();
            $sectionDuplicator->changelog = true;
            $new_row = $sectionDuplicator->duplicate($widget, $this->webzine);
            $this->widgetManager->addParent($new_row, $home->id_param, $widget->getPos());

            $gotohome = isset($data["gotohome"]) ? $data["gotohome"] : true;

            //$js = "showAlert('#tab-content-list-widgets', 'alert-success', '" . $this->security->cleanJs(str_replace("[TOKEN]", $widget->getNom(), $this->translater->get("GBLOG_116")), false) . "');\n";
            $js = $this->refreshJsAfterAdd($view, $new_row, $gotohome);
            $js .= "changePageInPreview('widget-navigation', {widgetId: " . $widget->id_param . ", delay: 1000});\n";

            $view->SESS_confirm = str_replace("[TOKEN]", $widget->getNom(), $this->translater->get("GBLOG_116"));

            return $js;
        }
    }

    /**
     * Suppression d'un widget
     * @param $event
     * @param $view
     * @param $data
     */
    public function delete($event, $view, $data = [])
    {
        $view->widget->delete();

        // On regarde combien il y a de widgets
        $this->eventsManager->fire("widgetListView:initializeList", $view);

        $home = $this->sectionManager->getHome($this->webzine);
        $js = $this->preview->updateParamInPreview($this->webzine, array("objet" => "sections/" . $home->id_param . "/widgetTargets"), array_keys($this->widgetManager->getAllWidgets($this->webzine, $home)));

        return $js;
    }
}

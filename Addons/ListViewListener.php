<?php

namespace Engine\GBBackOffice\V4\Listeners\Widget;

use Common\Lib\Goodbarber\Sections\Section;
use Engine\GBBackOffice\V4\Lib\Widgets\Widget;

class ListViewListener extends \Phalcon\Mvc\User\Component
{
    /**
     * Initialize widgets list
     * @param $event
     * @param $view
     * @param $data
     */
    public function initializeList($event, $view, $data)
    {
        $view->home = $this->sectionManager->getHome($this->webzine);
        $view->widgets = $this->widgetManager->getAllWidgets($this->webzine, $view->home, array("", "stock"));
    }

    public function renderWidget($event, $view, $data)
    {
        $this->initializeWidget($view, $data);

        $retu = $view->getRender($view->panelPath . "/app/widgets", "widget", $data, function ($view) {
            $view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
        });

        return $retu;
    }

    /**
     * Initialize widget settings
     * @param $view
     * @param $data
     */
    private function initializeWidget($view, &$data)
    {
        /** @var Widget $widget */
        $widget = $data["widget"];

        $section = $this->widgetManager->getContentSection($widget);

        $typeWidget = $widget->getTypeSection();

        $class = $delAction = $hrefError = $btnLabelError = $msgError = "";

        /*
         * Can drag n drop widget
         */
        $can_drag = false;
        if (count($view->widgets) > 1) $can_drag = true;

        // Do not drag the widget Commercelegal (always in the last position)
        if ($can_drag && $typeWidget != "GBWidgetTypeCommercelegal") {
            $class .= " sortable ";
        }

        $delWidget_popover_on_error = "";

        /*
         * Delete widget popover
         */
        $onYesUrl = $this->url->getUrl("widget/delete/?id=" . $widget->id_param);

        $onYesJs = "deleteParamInPreview('sections." . $widget->id_param . "');";
        $onYesJs .= "$.customPost('" . $onYesUrl . "', { loading:true});";

        $arrayDelWidget = array(
            "content" => $this->translater->get("GBWIDGET_3"),
            "onYesJs" => $onYesJs,
        );

        $delWidget_popover = !$view->designManagementForbidden ? $this->ui->popoverAlert($arrayDelWidget) : "";

        /*
         * Controls on section (source, etat...)
         */
        $classError = "danger";
        if ($widget->isContentWidget()) {
            $errorType = $widget->getContentErrorType();
            $btnLabelError = $this->translater->get("GBWIDGET_12");

            $edit_widget = false;
            if (!empty($section)) {
                $hrefError = "#content-" . $section->id_param . "-settings";
            }

            switch ($errorType) {
                case "notvalid":
                    $msgError = $this->translater->get("GBWIDGET_2");
                    $class .= " invalid";
                    break;
                case "stock":
                    $msgError = $this->translater->get("GBWIDGET_35");
                    $class .= " invalid";
                    $classError = "warning";
                    break;
                //case "subsectiondisabled": $msgError = $this->translater->get("GBWIDGET_16"); $class.= " invalid"; $edit_widget = true; break;
                case "subsectiondel":
                    $msgError = $this->translater->get("GBWIDGET_15");
                    $class .= " invalid";
                    $edit_widget = true;
                    break;
                case "del":
                    $msgError = $this->translater->get("GBWIDGET_13");
                    $class .= " invalid";
                    $edit_widget = true;
                    break;
            }

            if ($edit_widget) {
                $hrefError = "#widget-$widget->id_param-source";
                $btnLabelError = $this->translater->get("SECTIONS_87");

                // Si aucune section du meme type que le contenu du widget, alors on ne peut que supprimer le widget
                if (count($this->sectionManager->getAllByType($this->webzine, Widget::$GB_type_widget[$typeWidget]["contentType"])) == 0) {
                    $hrefError = "#";
                    $btnLabelError = $this->translater->get("GBWIDGET_14");
                    $delWidget_popover_on_error = $delWidget_popover;
                }
            }
        }


        $template = $widget->getTemplate(Section::$defaultPlatform);

        /*
         * Etat du widget
         */
        $arrayEnableWidget = array(
            "etat-" . $widget->id_param,
            "class" => "switch",
            "value" => "published",
            "label" => "",
            "data-post-form" => "",
            "data-loading" => "true"
        );
        if ($widget->etat == "" || $widget->etat == "default") {
            $arrayEnableWidget["checked"] = "checked";
        } elseif ($widget->etat == "stock") {
            $class .= " disabled";
        }

        $data["can_drag"] = $can_drag;
        $data["class"] = $class;
        $data["classError"] = $classError;
        $data["hrefError"] = $hrefError;
        $data["btnLabelError"] = $btnLabelError;
        $data["msgError"] = $msgError;
        $data["delWidget_popover"] = $delWidget_popover;
        $data["delWidget_popover_on_error"] = $delWidget_popover_on_error;
        $data["section"] = $section;
        $data["template"] = $template;
        $data["arrayEnableWidget"] = $arrayEnableWidget;
        $data["urlImage"] = $widget->getUrlImgTemplate();

        // Max widgets ajoutés ?
        $data["maxWidget"] = $this->widgetManager->getMaxWidgets();
        $data["canAddWidget"] = \Control::GoodIP() || count($view->widgets) < $data["maxWidget"];
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

        // Si on a qu'une section on ajoute directement le widget relié a cette section sans afficher la liste
        foreach (["GBWidgetTypeSearch", "GBWidgetTypeCommercesearch", "GBWidgetTypeArticle", "GBWidgetTypeMap"] as $typeWidget) {
            $typeSection = Widget::$GB_type_widget[$typeWidget]["contentType"];
            if (array_key_exists($typeWidget, $addElementArray["elementsDispo"])) {
                $sections = $this->sectionManager->getAllByType($this->webzine, $typeSection);
                if (count($sections) === 1) {
                    $addElementArray['datas'][$typeWidget] = $this->formateDataAddElement($typeWidget, ($data["key"] + 1));
                }
            }
        }

        $retu = $view->getRender($view->panelPath . "/app/tooltip/add", "element", $addElementArray, function ($view) {
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

        $dynamicDisplayExceptions = ["GBWidgetTypeContent", "GBWidgetTypeCommercecollectionslist"];

        if (in_array($typeWidget, $dynamicDisplayExceptions) || (Widget::$GB_type_widget[$typeWidget]["type"] == "dynamic" && !empty(Widget::$GB_type_widget[$typeWidget]["contentType"]))) {
            $hash = '#widget-add-' . $position;
            if ($this->widgetManager->isOnlyAvailableIfContentTypeSpecified($this->webzine, $typeWidget)) {
                $hash = '#widget-add-' . $widgetName . "-" . $position;
            }
            $retu = [
                'attr' => 'data-target="#panel-carousel" data-ajax-slide-to="2" data-loading="true" data-ajax-target="#panel-carousel-item-2"',
                'href' => $hash
            ];

            // Cas Commercesearch, Map, Article : la section est unique, donc pas la peine de choisir la section
            if (in_array($typeWidget, ["GBWidgetTypeCommercesearch", "GBWidgetTypeMap", "GBWidgetTypeArticle"])) {
                $this->eventsManager->fire("widgetSectionView:renderTree", $this->view, ["typeWidget" => $typeWidget]);
                if (count($this->view->tree) == 1) {
                    $retu = [
                        'attr' => "onclick=\"addWidget('/manage/widget/add/', 'dynamic', " . $position . ", " . $this->view->tree[0]->id_param . ");\"",
                        'href' => "#"
                    ];
                }
            }

        } else {
            $retu = [
                'attr' => 'onclick="addWidget(\'' . $this->url->getUrl("widget/add/") . '\', \'' . $widgetName . '\', ' . $position . ');"',
                'href' => 'javascript:void(0)'
            ];
        }

        return $retu;
    }
}

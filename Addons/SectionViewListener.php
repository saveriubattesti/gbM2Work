<?php

namespace Engine\GBBackOffice\V4\Listeners\Widget;

use Common\Lib\Goodbarber\Sections\Section;
use Engine\GBBackOffice\V4\Lib\Widgets\Widget;
use Phalcon\Forms\Element\Select;

class SectionViewListener extends \Phalcon\Mvc\User\Component
{
    /**
     * Liste de sections pour Widget de type Dynamic
     * @param $event
     * @param $view
     * @param $data
     */
    public function renderTree($event, $view, $data)
    {
        if ($view->designManagementForbidden) {
            return;
        }

        $retu = array();

        $restriType = null;

        if (!empty($view->typeWidget)) {
            $typeWidget = $view->typeWidget;
        } elseif (!empty($data["typeWidget"])) {
            $typeWidget = $data["typeWidget"];
        }

        if ($this->webzine->isShopPlan() && empty($typeWidget)) {
            return;
        }

        if (!empty($typeWidget)) {
            $restriType = isset(Widget::$GB_type_widget[$typeWidget]["fakeContentType"]) ? Widget::$GB_type_widget[$typeWidget]["fakeContentType"] : Widget::$GB_type_widget[$typeWidget]["contentType"];
        }

        // Controle de coherence
        if (!empty($typeWidget) && in_array($typeWidget, ["GBWidgetTypeCommerceproducts", "GBWidgetTypeCommercecollectionslist"])) {
            $this->sectionManager->cleanCommerceSections($this->webzine);
        }

        $tree = $this->treeManager->getTree($this->webzine, 0, 0, "tab");
        foreach ($tree as $section) {
            $retu = array_merge($retu, $this->getSections($section, $restriType));
        }

        $view->tree = $retu;

        $file = "list";

        if (!empty($typeWidget) && $typeWidget == "GBWidgetTypeCommercecollectionslist") {
            $file = "GBWidgetTypeCommercecollectionslist/list";
        }

        $retu = $view->getRender($view->panelPath . "/app/widgets/sections", $file, $data, function ($view) {
            $view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
        });

        return $retu;
    }

    private function getSections($section, $restriType)
    {
        $retu = [];

        $typeSection = $section->getTypeSection();
        if (!isset($restriType) || $restriType == $typeSection) {
            foreach (Widget::$GB_type_widget as $typeWidget => $confWidget) {
                if (isset($confWidget["fakeContentType"])) {
                    $contentType = $confWidget["fakeContentType"];
                } else {
                    $contentType = $confWidget["contentType"];
                }

                if ($contentType == $typeSection) {
                    // If a widget exists for a contentType (Search, Commerceproducts...), do not display this content type for general Content widget
                    if (!isset($restriType) && $this->widgetManager->isOnlyAvailableIfContentTypeSpecified($section->getWebzine(), $typeWidget)) continue;

                    $service = $section->getService();
                    if ($section->isValid() && !in_array($service, Widget::$GB_service_exception) && !in_array($typeSection, Widget::$GB_section_exception)) {
                        $retu[$section->id_param] = $section;
                    }
                }
            }
        }

        foreach ($section->children as $child) {
            $retu = array_merge($retu, $this->getSections($child, $restriType));
        }

        return $retu;
    }

    public function renderSection($event, $view, $data)
    {
        $this->initializeSection($event, $view, $data);

        $file = "section";

        if (isset($data["file"])) {
            $file = $data["file"];
        }

        $retu = $view->getRender($view->panelPath . "/app/widgets/sections", $file, $data, function ($view) {
            $view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
        });

        return $retu;
    }

    public function initializeSection($event, $view, &$data)
    {
        $section = $data["section"];

        $typeSection = $section->getTypeSection();

        $class = $typeSection;
        $class .= ($view->currentId_param == $section->id_param ? " selected" : "");
        $class .= (!$section->isValid(false) ? " invalid" : "");
        $class .= ($section->etat == "stock" ? " stock" : "");

        $is_mcms_multi_cat = ($section->getService() == "mcms" && in_array($typeSection, Section::$GB_mcms_section_muti_categories));

        /*
         * Categories si yen a
         */
        $categories = array();
        if (!empty($this->paramsManager->get("", "sections/subsectionsEnabled", "sections", $section->id_param))) {

            if ($list_subsections = $this->paramsManager->get("", "sections/subsections/%/title", "sections", $section->id_param)) {
                foreach ($list_subsections as $objet => $value) {
                    $index_subsection = preg_replace("#^sections/subsections/([0-9]+)/title$#", "\\1", $objet);
                    $categories[$index_subsection] = $value;
                }
            }
        }

        $hrefAdd = "#";
        if (empty($view->widget->id_param)) {
            $class .= " empty-widget";
            $dataAttrAdd = $dataAttrRealAdd = "onclick=\"addWidget('" . $this->url->getUrl("widget/add/") . "', 'dynamic', " . intval($view->pos) . ", " . $section->id_param . ")\"";
            $buttonLabel = "<i class=\"material-icons text-success add\">add</i> <span class=\"visible-panel-open\">" . $this->translater->get('GBWIDGET_CHOOSE_SECTION') . "</span>";
            $buttonLabel .= "<span class=\"visible-panel-medium\">" . $this->translater->get("GBWIDGET_CHOOSE_SECTION_SHORT") . "</span>";

            $buttonLabelSubsection = "<i class=\"material-icons text-success add\">add</i> " . $this->translater->get("GBWIDGET_CHOOSE_SECTION_SHORT");
        } else {
            $buttonLabel = $this->translater->get('GBWIDGET_17');

            $refresh = "null";
            $url = $this->url->getUrl("widget/changeSource/");
            if (!empty($view->sectionId) && $view->sectionId == $section->id_param) {
                $refresh = "1";
                $buttonLabel = $this->translater->get('GBPREVIEW_3');
            }

            $dataAttrAdd = $dataAttrRealAdd = "onclick=\"changeSourceWidget('" . $url . "', " . intval($view->widget->id_param) . ", " . intval($section->id_param) . ", " . $refresh . ")\"";

            $buttonLabelSubsection = $buttonLabel;
        }

        $show_subsections = false;

        $nb_subsections = count($categories);
        if ($nb_subsections > 1) {
            $show_subsections = true;
        }

        if ($nb_subsections == 1 && !$is_mcms_multi_cat) {
            $show_subsections = true;
        }


        $subsectionSelect = "";
        if ($show_subsections) {
            $subsectionSelect = new Select("subsection_" . $section->id_param, $categories, ["class" => "form-control"]);
            if (!empty($view->subsectionIndex)) {
                $subsectionSelect->setDefault($view->subsectionIndex);
            }
            $hrefAdd = "#widget-list-section-$section->id_param";
            $dataAttrAdd = "data-toggle=\"collapse\" data-parent=\"#widget-list-sections\" ";
            $dataAttrAdd .= "onclick=\"$('#widget-list-sections a.add-section-menu').show(); $(this).hide(); $('#widget-list-sections .edit-section').removeClass('open'); $(this).parents('.rollover').prev('.edit-section').addClass('open');\"";
        }

        $data["class"] = $class;
        $data["infosService"] = $this->getHTMLInfosService($section);
        $data["typeSection"] = $typeSection;
        $data["section"] = $section;
        $data["subsectionSelect"] = $subsectionSelect;
        $data["hrefAdd"] = $hrefAdd;
        $data["dataAttrAdd"] = $dataAttrAdd;
        $data["dataAttrRealAdd"] = $dataAttrRealAdd;
        $data["buttonLabel"] = $buttonLabel;
        $data["buttonLabelSubsection"] = $buttonLabelSubsection;
        $data["showAddOnSection"] = count($categories) <= 1;
    }

    /**
     * Renvoie le code HTML indiquant le service de la section
     * @return string
     */
    private function getHTMLInfosService(Section $section)
    {
        $typeSection = $section->getTypeSection();
        $service = $section->getService();

        $infos_service = "";

        if ($service == "sample") {
            return "<small>Sample</small>";
        }

        if (\Control::GoodIP()) {
            if (!in_array($typeSection, Section::$GB_mcms_section_single_article)) {
                $infos_service = ucfirst($service);
            }

            if (!empty(Section::$GB_type_service[$service]["multiple"])) {
                $infos_service .= str_replace("GBModuleType", "", $typeSection);
            }
            if (empty($infos_service)) {
                $infos_service = str_replace("GBModuleType", "", $typeSection);
            }

            return "<small style=\"color:#f39c12; background: #fcf8e3\">" . $infos_service . "</small>";
        }
    }

}

Tabler.php<?php

namespace Common\Lib\Goodbarber\Addons;

use Common\Models\AppsGbAddons;
use Common\Models\AppsGbAddonsDefault;
use Common\Models\Webzine;
use Common\Lib\Goodbarber\Billing\Subscription\Subscription;

class Tabler extends \Phalcon\DI\Injectable
{
    private $_webzine;

    public $hideAddon = [];

    public function __construct(Webzine $webzine)
    {
        $this->_webzine = $webzine;
    }

    /**
     * Renvoie la liste ordonnée de tous les addons de facon recursive
     * @param null $childof le chilodf en cours
     * @param bool $all force l'affichage de tous les addons ajoutés
     * @param array $excluded_addons Exclus les addons contenus dans le tableau
     * @return array retourne un tableau
     */
    public function getTree($childof = null, $all = false, $excluded_addons = [])
    {
        $retu = [];

        $builder = $this->getDI()->getModelsManager()->createBuilder();
        $builder->columns("apps_gb_addons.*");
        $builder->from(array('apps_gb_addons' => '\Common\Models\AppsGbAddons'));
        $builder->join('\Common\Models\AppsGbAddonsDefault', 'apps_gb_addons_default.code = apps_gb_addons.code', 'apps_gb_addons_default');
        $cond = "apps_gb_addons.id_webzine=:id_webzine:";
        $bind = array("id_webzine" => $this->_webzine->id_webzine);
        if (!empty($childof)) {
            $cond .= " AND (apps_gb_addons_default.childof=:childof: OR apps_gb_addons_default.childof LIKE :childof_like_before: OR apps_gb_addons_default.childof LIKE :childof_like_after:)";
            $bind["childof"] = $childof;
            $bind["childof_like_before"] = $childof . "|%";
            $bind["childof_like_after"] = "%|" . $childof;
        } else {
            $cond .= " AND apps_gb_addons_default.childof IS NULL";
        }
        if (!empty($excluded_addons)) {
            $cond .= " AND apps_gb_addons_default.code NOT IN ('" . implode("', '", $excluded_addons) . "')";
        }
        $builder->where($cond, $bind);
        $builder->orderBy('apps_gb_addons.position');
        $addons = $builder->getquery()->execute();

        foreach ($addons as $addon) {
            if ($all || (!$addon->isHidden() && !in_array($addon->code, $this->hideAddon))) {
                $retu = array_merge($retu, [$addon->code => $addon]);

                $hide_children = [];

                // Childof multiples :
                // Si un addon est disable, mais que son fils apparait ailleurs, on ne l'affiche pas ce coup-ci
                if ($addon->enable == 0) {
                    foreach ($addon->getChildren() as $child) {
                        if (count($child->getMultipleParentsEnabled()) > 0) {
                            $hide_children[] = $child->code;
                        }
                    }
                }

                $retu = array_merge($retu, $this->getTree($addon->code, $all, $hide_children));
            }
        }

        return $retu;
    }

    public function majPos()
    {
        $i = 1;
        $list = $this->getTree(null, true);
        foreach ($list as $addon) {
            $addon->update(["position" => $i]);
            $i++;
        }
    }

    public function getTable()
    {
        $tableArray = array();
        $tableArray["rows"] = array();
        $tableArray["header"] = array(
            array(
                "class" => "description",
                "content" => $this->translater->get('GBADDONS_1'),
            ),
            array(
                "content" => $this->translater->get('ACTIVER'),
                "class" => "enable",
                "style" => "width: 200px; text-align: center"
            ),
            array(
                "content" => "&nbsp;",
                "class" => "delete",
                "style" => "width: 40px; text-align:center"
            )
        );

        $list = $this->getTree();

        // Agences ?
        $urlModifySubscription = $this->url->getUrl("settings/billing/subscription/");
        $WLAgency = $this->_webzine->getWhiteLabelAgency();
        $target = "";
        if (!empty($WLAgency)) {
            $webzinePere = \Common\Models\Webzine::findFirst($WLAgency->id_webzine_principal);
            $urlModifySubscription = $webzinePere->getDomainRoot() . $this->url->getUrl("settings/billing/subscription/") . "?ident=" . $webzinePere->getIdent();

            $target = "target='_blank'";
        }

        /**
         * Cas des parents multiples (ex: deliveryslots)
         * On regarde si plusieurs parents sont déjà installés
         * Si c'est le cas, il ne faut pas de contrôle pour les enfants
         */
        $children_with_multiple_parents_installed = [];
        foreach ($list as $addon) {
            if ($addon->hasMultipleParentsEnabled()) {
                $children_with_multiple_parents_installed[] = $addon->code;
            }
        }

        /**
         * On recupere les options correspondantes au Webzine
         */
        $subscription = new Subscription($this->_webzine, true);
        $options = $subscription->getOptionsSortedByType();

        foreach ($list as $code => $addon) {
            //\control::debug2($code);
            $default = AppsGbAddonsDefault::get($addon->code);
            $addonDefaultForWebzine = new AddonDefaultForWebzine($default, $this->_webzine);

            $canDisableAddon = $this->acl->canDisableAddon($addon->code);


            $labelprice = $suppression = "";

            $prix = $addonDefaultForWebzine->getPrix(false);

            if ($prix > 0) {
                $labelprice = "<strong style=\"margin-left:10px\">" . $addonDefaultForWebzine->getLabelPrice($prix) . "</strong>";
            }

            $in_test = false;

            // Les fils ?
            $hasChildPaid = 0;
            $children = array_intersect(array_keys($list), $default->getAllChildrenCode());
            $children = array_diff($children, [$default->code]);
            $children = array_diff($children, $children_with_multiple_parents_installed);

            $children_do_delete = $children;

            // Test enfants à supprimer si on supprime le parent
            if (!empty($children_do_delete)) {
                $text_children_do_delete = "";
                foreach ($children_do_delete as $child) {
                    if (count($list[$child]->getParentsInstalled()) == 1) {
                        $childAddonDefault = AppsGbAddonsDefault::get($list[$child]->code);
                        $text_children_do_delete .= ($text_children_do_delete != "" ? " " . $this->translater->get('ET') : "") . " <em>" . $childAddonDefault->getLabel() . "</em>";

                        // Verifie si on a un fils payant installé (pas en test, actif ou pas)
                        if (!$hasChildPaid) {
                            $childAddonDefaultForWebzine = new AddonDefaultForWebzine($childAddonDefault, $this->_webzine);
                            if ($childAddonDefaultForWebzine->getPrix() > 0 && $this->acl->isAddonEnable($child, false, false)) {
                                $hasChildPaid = 1;
                            }
                        }
                    }
                }
            }

            $hasExtras = false;
            // Tant quil ya des extras achetés on nautorise pas la suppression, on redirige vers page de paiement
            if (!empty($options) && in_array("extra" . $addon->code, array_keys($options))) {
                $hasExtras = true;
            }

            $action = "";

            // Addon payant
            if ($prix > 0 || $hasExtras) {
                // Site normal (pas reseller) ou proprietaire du site ou admins EChurch
                if (empty($WLAgency) || ($this->acl->isProprio() || $this->acl->isAdminEChurch())) {
                    if (!$addon->is_test) {
                        if (!empty($options[($hasExtras ? "extra" . $addon->code : "gb" . $addon->code)])) {
                            $action = "<a href=\"" . $urlModifySubscription . "\" $target class=\"btn btn-primary\">" . $this->translater->get('ABONNEMENT_16') . "</a>";
                        } else {
                            $action = "<span>" . $this->translater->get('FORBIDDEN_7') . "</span>";
                        }
                    } else {
                        /**
                         * Addon en test, on propose de payer si on est pas en période de test de l'app
                         */
                        $in_test = true;
                        if (!$this->_webzine->isTest()) {
                            $label = $this->translater->get('GBDEVELOPER_4');
                            if (!empty($WLAgency) && $addon->isTestCompleted(true, false) && $addonDefaultForWebzine->isUnlimited()) {
                                $label = $this->translater->get('GBADDONS_REACTIVATE_ADDON');
                            }

                            $action = "<a href=\"" . $this->url->getUrl("addons/detail/$addon->code/") . "\" class=\"btn btn-success\">" . $label . "</a>";
                        } else {
                            $action = "<span>" . $this->translater->get('GBADDONS_TEST_IN_PROGRESS') . "</span>";
                        }
                    }
                } else {
                    $action = "<span>" . $this->translater->get('GBADDONS_17') . "</span>";
                }

            } elseif ($canDisableAddon) {
                $ajax = $this->url->getUrl("addons/enable/?code=" . $addon->code . "&enable=" . intval(!$addon->enable));

                $tag = "data-loading=\"true\" data-ajax=\"" . $ajax . "\"";

                // Alerte pour les parents
                if (!empty($addon->enable) && !empty($children)) {
                    // Ibox Childof
                    if (!$hasChildPaid) {
                        $tag = "data-remote='" . $this->url->getUrl("addons/modalAddonChildOf/" . $addon->code . "/disable/") . "' data-toggle='modal' data-target='#modal-responsive'";
                    } else {
                        // Si on a au moins un addon payant comme fils (pas en test), on empeche la desactivation
                        $text = $this->translater->get("GBADDONS_29");

                        $array = array(
                            "content" => nl2br($text),
                            "onYesJs" => "document.location.href='" . $urlModifySubscription . "';",
                            "modalAlert" => "#modal-alert",
                            "btnlabel" => $this->translater->get("GBBILLING_100")
                        );

                        $tag = $this->ui->popoverAlert($array);
                    }
                }

                $action = "<button style=\"width: 100px\" data-container=\"#table-addons\" " . $tag . " class=\"btn btn-" . (empty($addon->enable) ? 'gray-inv addon-disabled' : 'primary addon-enabled') . "\">";
                $action .= "<span>" . $this->translater->get(empty($addon->enable) ? 'ACTIVER' : 'FREEMIUM_18') . "</span>";
                $action .= "</button>";

                // Childof d'un pere disabled ? On n'affiche pas le bouton
                $childof = $default->getChildofList();
                if (!empty($childof)) {
                    $hide_action = true;
                    foreach ($childof as $c) {
                        if (!empty($list[$c]->enable)) {
                            $hide_action = false;
                        }
                    }
                    if ($hide_action) {
                        $action = "";
                    }
                }
            }


            /**
             * Si addon en test et App pas en période de test, on affiche les infos de fin de test
             */
            $label_test = "";

            if (!$this->_webzine->isTest() && $addon->is_test) {
                $date = new \Common\Lib\Date($addon->date_end);
                $date_fin = $date->formatDate();

                /**
                 * Si addon non terminé ou avec des fils encore en test valide
                 */
                if (!$addon->isTestCompleted(true, false)) {
                    /**
                     * Si addon non terminé
                     */
                    if (!$addon->isTestReallyCompleted(false)) {
                        $label_test = " <span class=\"label label-warning\" style=\"margin-left:10px\">" . str_replace("[DATE]", $date_fin, $this->translater->get('GBADDONS_8')) . "</span>";
                    } /**
                     * Si addon terminé avec des fils en test valide
                     */
                    else {
                        $label_test = " <span class=\"label label-warning\" style=\"margin-left:10px\">" . $this->translater->get('GBADDONS_11') . "</span>";
                    }
                } /**
                 * Si addon terminé
                 */
                else {
                    $label_test = " <span class=\"label label-danger\" style=\"margin-left:10px\">" . $this->translater->get('GBADDONS_11') . "</span>";
                }
            }

            if (!empty($label_test)) {
                $label_test = "<br class='visible-mobile' />" . $label_test;
            }

            if (!empty($labelprice)) {
                $labelprice = "<br class='visible-mobile' />" . $labelprice;
            }

            /*
             * DELETE
             */
            // Arret de la periode de test
            if ($canDisableAddon) {
                if ($in_test) {
                    $array = array(
                        "onYesContainer" => "#table-addons",
                        "onYesUrl" => $this->url->getUrl("addons/finishTest/?code=" . $addon->code),
                    );

                    $suppression = "<a href=\"" . $this->url->getUrl("alert/?type=delete_addon_in_test&amp;addon=" . $addon->code . "&amp;args=") . urlencode(serialize($array)) . "\" class=\"del " . $this->ui->classIconDelete() . "\" role=\"button\" data-toggle=\"modal\" data-target=\"#modal-alert\">" . $this->ui->contentIconDelete() . "</a>";

                } elseif ((!$in_test && (empty($prix) && !$hasExtras)) || \control::GoodIP() || $this->acl->isAdminEChurch()) {
                    if (!$hasChildPaid) {
                        $text = $this->translater->get("GBADDONS_4");

                        // Test children : si il y a un addon enfant installé on affiche une alerte pour indiquer
                        // que l'enfant va aussi être supprimé
                        // Attention : pour le cas des parents multiples (ex: deliveryslots), il faut regarder si l'autre
                        // parent n'est pas présent. Dans ce cas on n'affiche pas l'alerte
                        if (!empty($children_do_delete)) {
                            $text = $this->translater->get(count($children_do_delete) == 1 ? "GBADDONS_20" : "GBADDONS_19");
                            $text = str_replace("[PARENT]", "<em>" . $default->getLabel() . "</em>", $text);
                            $text = preg_replace("#\[(CHILD|CHILDREN)\]#", $text_children_do_delete, $text);
                        }

                        $array = array(
                            "content" => (!empty($prix) && \control::GoodIP() ? "<b>GOODIP !</b><br/><br/>" : "") . nl2br($text),
                            "onYesContainer" => "#table-addons",
                            "onYesUrl" => $this->url->getUrl("addons/del/?code=" . $addon->code . ($in_test ? "&test=1" : "")),
                            "modalAlert" => "#modal-alert",
                            "btnlabel" => $this->translater->get("SUPPRIMER")
                        );
                    } else {
                        // Si on a au moins un addon payant comme fils, on empeche la suppression

                        $text = $this->translater->get("GBADDONS_30");

                        $array = array(
                            "content" => nl2br($text),
                            "onYesJs" => "document.location.href='" . $urlModifySubscription . "';",
                            "modalAlert" => "#modal-alert",
                            "btnlabel" => $this->translater->get("GBBILLING_100")
                        );

                        // Do not go to billing page if eChurch
                        if ($this->acl->isAdminEChurch()) {
                            unset($array["onYesJs"]);
                            $array["btnlabel"] = $this->translater->get("RETOUR");
                        }
                    }

                    $suppression = $this->ui->linkDelete($array, "del" . (!empty($prix) && \control::GoodIP() ? " text-danger-important" : ""));
                }
            } else {
                $suppression = "";
            }

            $level = $default->getLevel();

            $addonAvailabilityInfos = $addonDefaultForWebzine->getAvailabilityInfos();

            if (!empty($addonAvailabilityInfos)) {
                $action .= "<p class='availability-infos'>" . $addonAvailabilityInfos . "</p>";
            }

            $row = array(
                "<div><div class=\"logo level" . $level . "\" style=\"margin-left:" . ($level * 50) . "px;background-image:url(" . $this->url->getStaticImage("addons/" . $addon->code . ".jpg", "", true) . ")\"></div></div>" . "<label class='label-and-price'>" . $default->getLabel() . $labelprice . $label_test . "</label>",
                $action,
                $suppression
            );

            if (!empty($default->multiple) && isset($addon->value)) {
                $nb_addons = max(1, $addon->value);
            } else {
                $nb_addons = 1;
            }

            for ($i = 1; $i <= $nb_addons; $i++) {
                $tableArray["rows"][] = $row;
                $tr_rows = array();
                $tr_rows["class"] = (empty($addon->enable) ? "disabled" : "");

                $tableArray["tr_rows"][] = $tr_rows;
            }
        }

        return $tableArray;
    }
}

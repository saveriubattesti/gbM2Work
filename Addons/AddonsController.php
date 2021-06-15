<?php

namespace Engine\GBBackOffice\Controllers;

use Common\Lib\Goodbarber\Addons\AddonDefaultForWebzine;
use Common\Lib\Goodbarber\Addons\AddonUnlimitedForWebzine;
use Common\Lib\Goodbarber\Billing\Commande\CommandeCreator\AbstractDetailCreator;
use Common\Lib\Goodbarber\Billing\TarifsConfig;
use Common\Lib\Goodbarber\Sections\Section;
use Common\Models\AppsGbAddonsDefault, \Common\Models\AppsGbAddons;
use Common\Lib\Goodbarber\Addons\Tabler;
use Common\Models\Tarifs;
use Common\Lib\Goodbarber\Addons\AddonManager;

class AddonsController extends ControllerBase
{
    private $sqlConditions;
    private $hideAddon = [];

    /**
     * @var AddonManager
     */
    private $_addonManager;

    public function initialize()
    {
        parent::initialize();

        if (!$this->view->isV4) $this->view->bodyClass .= " white";

        $this->sqlConditions = "etat<>'disabled'";

        if (!empty($this->webzine->getWhiteLabelAgency()) || $this->webzine->isResellerPlan()) {
            $this->sqlConditions .= " AND code<>'whitelabel'";
            $this->hideAddon[] = "whitelabel";
        }

        if ($this->view->isV4) {
            $this->sqlConditions .= " AND code<>'mcmsadvanced'";
        } else {
            $this->sqlConditions .= " AND code NOT IN ('googleampclassic', 'inapppurchase')";
        }

        if ($this->webzine->isBackofficeLikeClassicPlan()) {
            $this->sqlConditions .= " AND code<>'statsexternal'";
        }

        if ($this->view->isShopPlan) {
            $this->sqlConditions .= " AND shop=true";
        } else {
            $this->sqlConditions .= " AND classic=true";
        }

        $this->_addonManager = new AddonManager($this->webzine);
    }

    public function indexAction()
    {
        return $this->response->redirect($this->url->getRedirectUrl($this->controllerName . "/list/"));
    }

    public function listAction()
    {
        $this->view->mobileCompliantPage = true;

        if ($this->view->isShopPlan) {
            $array = array(
                "order" => "FIELD(shop_id_category, 'LOCAL_SHOP', 'SALES', 'SEARCH_ENGINE', 'ADVANCED_OPTIONS'), shop_position"
            );
        } else {
            $array = array(
                "order" => "FIELD(id_category, 'users', 'monetize', 'notifications', 'live', 'advanced', 'localbusiness', 'content'), position"
            );
        }

        if (!empty($this->sqlConditions)) {
            $array["conditions"] = $this->sqlConditions;
        }

        $all = AppsGbAddonsDefault::find($array);

        $all_addons = $highlight = array();


        foreach ($all as $addon) {

            if (!\Control::goodIp() && $addon->etat == "stock") continue;

            if (!$addon->isAvailable($this->webzine)) continue;

            $category_label = $addon->getCategoryLabel($this->webzine);

            $all_addons[$category_label][$addon->code] = $addon;
            // Highlight
            if ($addon->highlight > 0) {
                $highlight[$addon->highlight][$addon->code] = $addon;
            }
        }

        $this->view->all_addons = $all_addons;

        // Remove highlight banner for issue FRONTEND-8100 at 09/03/2021
        /*ksort($highlight);
        $this->view->highlight = $highlight;*/
    }

    public function addpageAction()
    {
        $params = $this->dispatcher->getParams();
        if ($this->view->isV4) {
            $params["back"] = $this->url->getUrl("app/content/");
        }
        return $this->dispatcher->forward(array("action" => "detail", "params" => $params));
    }

    public function detailAction()
    {
        $this->view->mobileCompliantPage = true;

        $this->view->backUrl = $this->dispatcher->hasParam("back") ? $this->dispatcher->getParam("back") : $this->url->getUrl($this->controllerName . "/list/");

        $params = $this->dispatcher->getParams();
        $this->view->detail = $params[0];

        $array = array(
            "conditions" => "code=:code:",
            "bind" => array(
                "code" => $this->view->detail
            )
        );

        if (!empty($this->sqlConditions)) {
            $array["conditions"] .= " AND " . $this->sqlConditions;
        }

        /** @var AppsGbAddonsDefault $addon */
        $addon = AppsGbAddonsDefault::findFirst($array);

        if (!$addon || (in_array($addon->etat, ["stock", "soon_nodetail"]) && !\Control::GoodIP()) || !$addon->isAvailable($this->webzine)) {

            return $this->response->redirect($this->url->getRedirectUrl($this->controllerName . "/list/"));
        } else {
            // Help in title page
            $helpForDetail = [
                "userauth" => ["numhelp" => 149, "numrub" => 102],
                "usergroup" => ["numhelp" => 150, "numrub" => 102],
                "usersocial" => ["numhelp" => 175, "numrub" => 91],
                "userchat" => ["numhelp" => 194, "numrub" => 91],
                "punchcard" => ["numhelp" => 205, "numrub" => 92],
                "clubcard" => ["numhelp" => 210, "numrub" => 92],
                "couponing" => ["numhelp" => 216, "numrub" => 92],
            ];

            if (in_array($addon->code, array_keys($helpForDetail))) {
                $this->view->linkHelp = ["numhelp" => $helpForDetail[$addon->code]["numhelp"], "numrub" => $helpForDetail[$addon->code]["numhelp"]];
            }

            $addonDefaultForWebzine = new AddonDefaultForWebzine($addon, $this->webzine);
            $prix = $addonDefaultForWebzine->getPrix();

            if (preg_match("#^soon#", $addon->etat) && \Control::GoodIP()) {
                $addon->etat = "";
            }

            $this->view->addon = $addon;
            $this->view->addonDefaultForWebzine = $addonDefaultForWebzine;
            $this->view->addonAdded = ($this->acl->isAddonInstalled($addon->code) && (!$this->acl->isAddonOnTest($addon->code) || $addonDefaultForWebzine->addableWithoutConstraint()) && $addon->multiple == 0);
            // Can add addon (user right OK and tarif OK)
            // OR App in test and user right OK
            $this->view->canAddAddon = ($this->acl->canAddAddon($addon->code) || ($this->webzine->isTest() && $this->hasUserRight($addon->code)));
            // Si on ne peux pas ajouter l'addon et que le tarif du webzine n'est pas forbidden pour cet addon, c'est que l'utilisateur n'a pas le droit de l'ajouter
            $this->view->forbiddenAddAddon = (!$this->view->canAddAddon && $addonDefaultForWebzine->allowedTarif());
            // When webzine in test, if user can add addon, we propose to change offer if the addon is not available in the current one
            $this->view->changeOfferToAddAddon = ($this->hasUserRight($addon->code) && !$addonDefaultForWebzine->allowedTarif() && $this->webzine->isTest());

            $this->view->canTestAddon = ($addon->testable > 0 && $prix > 0 && !$this->acl->isAddonOnTest($addon->code) && !$this->acl->isAddonOnTestCompleted($addon->code) && !$this->acl->isAddonOnMultiplePaid($addon->code));

            $this->view->canEnableAddon = $this->acl->canEnableAddon($addon->code);

            /*
             * Check if has parent not free not installed
             */
            $hasParentNotFreeNotInstalled = $this->_hasParentNotFreeNotInstalled($addon);

            // Extra
            $this->view->extras = $addonDefaultForWebzine->getExtraPricingInfo();

            // Addon availability infos
            $this->view->addonAvailabilityInfos = $addonDefaultForWebzine->getAvailabilityInfos();

            // Libellé de limite sous le bouton
            if ($addon->code == "userchat") {
                $this->view->extras = $this->translater->get("GBADDONS_CHAT_9");

                // Apps for Kids
                if (!$this->view->canEnableAddon) {
                    $js = $this->getPopoverJsUserChat();
                    $this->view->popoverCanNotEnable = "href='#' onclick=\"$js\"";
                }
            }

            // App d'agence ? Si pas proprio, on ne peut pas acheter
            $this->view->restriProprio = false;
            // Price of unlimited addon only for Reseller
            $this->view->labelPriceUnlimited = 0;
            // Var for overriding childofModal
            $overrideChildOfModal = false;

            $WLAgency = $this->webzine->getWhiteLabelAgency(true);

            // Addon not free for Resellers
            if ($prix > 0 && !empty($WLAgency)) {
                // App d'agence ? Si pas proprio et pas admin eChurch, on ne peut pas acheter
                if (!$this->acl->isProprio() && !$this->acl->isAdminEChurch()) {
                    $this->view->restriProprio = true;
                    $this->view->canTestAddon = false;
                } elseif (!$this->acl->isAdminEChurch()) {
                    /*
                     * If not eChurch
                     */

                    /*
                     * Unlimited buy button management
                     */
                    if ($this->view->isV4 && $WLAgency) {
                        $resellerAlphaproject = $this->webzine->getResellerAlphaProject();

                        // If tarif V3 in V4 backend
                        if ($resellerAlphaproject->isResellerPlanV3()) {
                            $this->view->popover = "href='" . $this->url->getUrl("addons/modalResellerUpgrade/" . $addon->code . "/") . "' role='button' data-toggle='modal' data-target='#modal'";

                            if (!$hasParentNotFreeNotInstalled) {
                                $overrideChildOfModal = true;
                            }
                        } else {
                            // Ok to buy unlimited addon
                            $addonDefaultForReseller = new AddonDefaultForWebzine($addon, $resellerAlphaproject);

                            // Price for unlimited addon
                            $this->view->labelPriceUnlimited = $this->getLabelPriceUnlimited($addonDefaultForReseller, $addon->code);

                            // If addon not installed, display modal with the 2 choices : single add or unlimited
                            if (!$this->view->addonAdded) {
                                if (!$this->webzine->isResellerAlphaProject() || !$this->webzine->isTest() || !$this->view->canTestAddon) {
                                    $this->view->popover = "href='" . $this->url->getUrl("addons/modalUnitOrUnlimited/" . $addon->code . "/") . "' role='button' data-toggle='modal' data-target='#modal'";

                                    if (!$hasParentNotFreeNotInstalled) {
                                        $overrideChildOfModal = true;
                                    }
                                }
                            }
                        }


                    } elseif (!$this->webzine->isResellerAlphaProject()) {
                        // We go in alpha project to pay
                        $array = array(
                            "content" => "<p>" . nl2br($this->translater->get("GBADDONS_18")) . "</p>",
                            "onYesJs" => "$($('.form-add')[0]).attr('target', '_blank').submit(); $('#modal-alert-extended').hide(); $.loading(false);",
                            "modalAlert" => "#modal-alert-extended",
                            "btnlabel" => $this->translater->get("CONTINUER"),
                            "postMethod" => "get"
                        );

                        $this->view->popover = "href='#' " . $this->ui->popoverAlert($array);
                    }
                }
            }

            // Modal to tell the customer we will change the offer before installing addon
            if ($this->view->changeOfferToAddAddon) {
                $this->view->popover = "href='" . $this->url->getUrl("addons/modalAddonChangeOffer/" . $addon->code . "/") . ($this->view->canTestAddon ? "test/" : "") . "' data-toggle='modal' data-target='#modal-responsive'";
            }

            // Ibox Childof
            // If has to change the offer, tell the customer in the modal text
            if (!$overrideChildOfModal && !empty($addon->childof) && !$this->acl->hasParentAddonEnable($addon)) {
                $this->view->popover = "href='" . $this->url->getUrl("addons/modalAddonChildOf/" . $addon->code . "/") . ($this->webzine->isTest() && $this->view->canTestAddon ? "test/" : "") . "' data-toggle='modal' data-target='#modal-responsive'";
            }

            /* Label green button possibilities :
             * - Price to pay
             * - Add
             * - Test the add-on
             * - Reactivate the add-on
             */
            if (!empty($WLAgency) && $addonDefaultForWebzine->isUnlimited()) {
                // If Reseller with unlimited addon and test completed, label : Reactivate the add-on
                $addonInstalled = AppsGbAddons::get($this->webzine, $addon->code);
                if ($addonInstalled && $addonInstalled->isTestCompleted(true, false)) {
                    $this->view->labelPrice = $this->translater->get('GBADDONS_REACTIVATE_ADDON');
                }
            }
            if (empty($this->view->labelPrice)) {
                // Get label price depending on prix > 0, label: "Add" or "Price to pay"
                $labelPrice = $this->getLabelPrice($addonDefaultForWebzine, $addon->code);
                // If webzine in test and can test addon, label : Test the add-on
                $this->view->labelPrice = ($this->webzine->isTest() && $this->view->canTestAddon ? $this->translater->get("GBDEVELOPER_5") : $labelPrice);
            }

            // Demande d'ajout
            if ($this->request->getPost("action") == "add") {
                // Si Addon gratuit, ou GoodIP, ou eChurch
                $force_goodip = ($this->acl->isAdminEChurch() || (\Control::GoodIP() && $this->request->getPost("force") == 1));
                if (($this->view->canAddAddon && $prix == 0 && !$this->view->restriProprio) || $force_goodip) {

                    $options = [];
                    if ($force_goodip) {
                        $options["force_goodip"] = 1;
                    }

                    $this->_addonManager->setCode($addon->code);
                    $success = $this->_addonManager->add($options);

                    if ($success === true) {
                        return $this->response->redirect($this->url->getRedirectUrl($this->controllerName . "/detail/" . $addon->code . "/"));
                    }
                } else if ($this->view->canAddAddon && $prix > 0) {
                    $this->dispatcher->forward(array(
                        "controller" => "forbidden",
                        "action" => $addon->code
                    ));
                }
            }
            // Demande de test
            if ($this->request->getPost("action") == "test" && $this->view->canTestAddon) {
                $this->_addonManager->setCode($addon->code);
                $success = $this->_addonManager->add(["is_test" => 1]);

                if (!$success) {
                    return $this->response->redirect($this->url->getRedirectUrl($this->controllerName . "/detail/" . $addon->code . "/"));
                }
            }
        }
    }

    /**
     * Check if paid parent addon is not installed
     * @param AppsGbAddonsDefault $addon
     * @return bool
     */
    private function _hasParentNotFreeNotInstalled(AppsGbAddonsDefault $addon)
    {
        if (!empty($addon->childof)) {
            $hasParentNotFreeNotInstalled = true;

            $childofs = $addon->getChildofList();
            foreach($childofs as $childof) {
                $parentDefault = AppsGbAddonsDefault::get($childof);
                $parent = new AddonDefaultForWebzine($parentDefault, $this->webzine);

                if ($parent->getPrix() == 0 || $this->acl->isAddonEnable($childof)) {
                    $hasParentNotFreeNotInstalled = false;
                }
            }

            return $hasParentNotFreeNotInstalled;
        }

        return false;
    }

    private function getTable()
    {
        $tabler = new Tabler($this->webzine);
        $tabler->hideAddon = $this->hideAddon;
        return $tabler->getTable();
    }

    public function managementAction()
    {
        $this->view->mobileCompliantPage = true;

        $this->view->tableArray = $this->getTable();
    }

    public function delAction()
    {
        $this->view->disable();

        $code = $this->request->getPost("code", "striptags");

        if (!$this->acl->canDisableAddon($code)) {
            return;
        }


        // Securité pour addon en test : on ne peut pas les supprimer (sauf en Goodip)
        if (!\Control::GoodIP() && $addon = AppsGbAddons::get($this->webzine, $code)) {
            if ($addon->is_test) {
                return $this->dispatcher->forward(array("action" => "finishTest"));
            }
        }

        $this->_addonManager->setCode($code);
        $success = $this->_addonManager->delete();

        if ($success) {
            echo "<script type=\"text/javascript\">" . $this->registry->js . "</script>";
        }

        $tableArray = $this->getTable();

        if (empty($tableArray["rows"])) {
            echo "<div class=\"alert alert-info\">" . $this->translater->get('GBADDONS_10') . "</div>";
        } else {
            $this->view->partial("partials/utilities/table", array("array" => $tableArray));
        }

    }

    public function enableAction()
    {
        $this->view->disable();
        $code = $this->request->getPost("code", "striptags");

        if (!$this->acl->canDisableAddon($code)) {
            return $this->response->setStatusCode(412, "Precondition Failed")->setHeader("Content-Type", "application/javascript")->send();
        }

        $enable = intval($this->request->getPost("enable", "int"));

        if ($enable && !$this->acl->canEnableAddon($code)) {
            if ($code == "userchat") {
                $js = $this->getPopoverJsUserChat();
                $this->response->setContent($js);
            }

            return $this->response->setStatusCode(412, "Precondition Failed")->setHeader("Content-Type", "application/javascript")->send();
        }

        $this->_addonManager->setCode($code);
        $success = $this->_addonManager->save(array("enable" => $enable));

        if ($success) {
            echo "<script type=\"text/javascript\">" . $this->registry->js . "</script>";
        }

        $this->view->partial("partials/utilities/table", array("array" => $this->getTable()));
    }

    public function finishTestAction()
    {
        $this->view->disable();
        $code = $this->request->getPost("code", "striptags");

        if (!$this->acl->canDisableAddon($code)) {
            return;
        }

        $this->_addonManager->setCode($code);
        $success = $this->_addonManager->finishTest();

        if ($success) {
            echo "<script type=\"text/javascript\">" . $this->registry->js . "</script>";
        }

        $this->view->partial("partials/utilities/table", array("array" => $this->getTable()));
    }

    public function themesAction()
    {
        return $this->dispatcher->forward(array("controller" => "themes", "action" => "index"));
    }

    public function modalAddonChildOfAction($code, $action = "")
    {
        // Savoir si on check si un addon est en test ou pas
        $testAddon = false;

        $default = AppsGbAddonsDefault::get($code);
        $addonDefaultForWebzine = new AddonDefaultForWebzine($default, $this->webzine);

        $dependances = array();

        if ($action == "disable") {
            $addon = AppsGbAddons::get($this->webzine, $code);

            $defaultChildren = AppsGbAddonsDefault::getAllChildren($code);
            foreach ($defaultChildren as $defaultChild) {
                if ($child = AppsGbAddons::get($this->webzine, $defaultChild->code)) {
                    if (!$child->hasMultipleParentsEnabled()) {
                        $dependances[] = new AddonDefaultForWebzine($defaultChild, $this->webzine);
                    }
                }
            }

            if (count($dependances) > 1) {
                $text = $this->translater->get("GBADDONS_23");
                $tokenDependances = "[CHILDREN]";
            } else {
                $text = $this->translater->get("GBADDONS_22");
                $tokenDependances = "[CHILD]";
            }

            $tokenCurrent = "[PARENT]";

            $operator = "-";
            $idBtnAction = "btn-modal";
            $classBtnAction = "btn-primary";
            $libBtnAction = $this->translater->get("FREEMIUM_18");
            $ajax = $this->url->getUrl("addons/enable/?code=" . $addon->code . "&enable=" . intval(!$addon->enable));
            $jsBtnAction = "$.customPost('$ajax', {container:'#table-addons', loading:false, complete: function() {
                $('#modal-responsive').modal('hide');
            } }); ";
        } else {
            $defaultParents = $default->getParents();
            foreach ($defaultParents as $defaultParent) {
                $dependances[] = new AddonDefaultForWebzine($defaultParent, $this->webzine);
            }

            if (count($dependances) > 1) {
                $text = $this->translater->get("GBADDONS_28");
                $tokenDependances = "[PARENTS]";
            } else {
                $text = $this->translater->get("GBADDONS_21");
                $tokenDependances = "[PARENT]";
            }

            $tokenCurrent = "[CHILD]";

            $operator = "+";
            $classBtnAction = "btn-success";

            if ($action == "test") {
                $idBtnAction = "btn-modal-test";
                $libBtnAction = $this->translater->get("GBDEVELOPER_5");
                $jsBtnAction = "$($('.form-test')[0]).submit(); $('#modal-responsive').hide();";
                $testAddon = true;
            } else {
                $idBtnAction = "btn-modal";
                $libBtnAction = $addonDefaultForWebzine->getLabelPrice();
                $jsBtnAction = "$($('.form-add')[0]).submit(); $('#modal-responsive').hide();";
            }

            if ($this->webzine->isTest() && !$addonDefaultForWebzine->allowedTarif()) {
                $textChangeOffer = ($action == "test" ? $this->translater->get("GBADDONS_43") : $this->translater->get("GBADDONS_45"));

                $myOffer = TarifsConfig::getOffer($this->webzine->tarif);
                $myPlanLabel = $this->translater->get("GBNAMEPLAN_" . mb_strtoupper(str_replace("V4", "", $myOffer)));

                $newPlanLabel = $this->translater->get("GBNAMEPLAN_CLASSICFULL");

                $this->view->textChangeOffer = str_replace(
                    ["[MYOFFER]", "[ADDON]", "[NEWOFFER]"],
                    [$myPlanLabel, "<strong>" . $default->getLabel() . "</strong>", "<strong>" . $newPlanLabel . "</strong>"],
                    $textChangeOffer
                );
            }
        }

        if (!empty($dependances)) {
            $libDependances = "";
            $parentNotFree = "";
            /** @var AddonDefaultForWebzine $dependance */
            foreach ($dependances as $dependance) {
                /**
                 * Check if one parent is not free and uninstalled
                 */
                if ($action != "disable" && empty($parentNotFree) && $dependance->getPrix() > 0 && !$this->acl->isAddonEnable($dependance->getAddonDefault()->code, $testAddon)) {
                    $parentNotFree = $dependance;
                }

                $urlDetailDependance = "<a href='" . $this->url->getUrl("addons/detail/" . $dependance->getAddonDefault()->code . "/") . "' target='_blank'>" . $dependance->getAddonDefault()->getLabel() . "</a>";
                $libDependances .= (!empty($libDependances) ? " " . $this->translater->get("ET") . " " : "") . $urlDetailDependance;
            }

            /**
             * Si parent payant pas installé, on change les textes et on redirige vers cet add-on
             */
            if (!empty($parentNotFree)) {
                if ($action == "test") {
                    $text = $this->translater->get("GBADDONS_32");
                } else {
                    $text = $this->translater->get("GBADDONS_33");
                }

                $text = str_replace(
                    "[NOTFREEADDON]",
                    "<a href='" . $this->url->getUrl("addons/detail/" . $parentNotFree->getAddonDefault()->code . "/") . "' target='_blank'>" . $parentNotFree->getAddonDefault()->getLabel() . "</a>",
                    $text
                );

                $libBtnAction = $parentNotFree->getAddonDefault()->getLabel();
                $jsBtnAction = "document.location.href='" . $this->url->getUrl("addons/detail/" . $parentNotFree->getAddonDefault()->code . "/") . "';";
            }

            $text = str_replace($tokenDependances, $libDependances, $text);

            $urlDetailCurrent = "<a href='" . $this->url->getUrl("addons/detail/" . $code . "/") . "' target='_blank'>" . $default->getLabel() . "</a>";
            $text = str_replace($tokenCurrent, $urlDetailCurrent, $text);

            $this->view->dependances = $dependances;
            $this->view->code = $code;
            $this->view->content = $text;
            $this->view->action = $action;
            $this->view->operator = $operator;
            $this->view->idBtnAction = $idBtnAction;
            $this->view->classBtnAction = $classBtnAction;
            $this->view->libBtnAction = $libBtnAction;
            $this->view->jsBtnAction = $jsBtnAction;
            if ($addonDefaultForWebzine->getPrix() > 0) {
                $this->view->currentPrice = $addonDefaultForWebzine->getLabelPrice();
            }
        }
    }

    public function modalResellerUpgradeAction($addonCode)
    {
        // Url to upgrade
        $this->view->urlUpgrade = $this->webzine->getResellerAlphaProject()->getDomainRoot() . "/manage/settings/billing/paymentinfo/";
        $this->view->txtBtnUpgrade = $this->translater->get("GBBILLING_45");

        $addonDefault = AppsGbAddonsDefault::get($addonCode);
        $resellerAlphaProject = $this->webzine->getResellerAlphaProject();

        /*
         * Get price of unlimited addon
         */
        $addonUnlimitedForWebzine = new AddonUnlimitedForWebzine($addonDefault, $resellerAlphaProject);
        $libUnlimitedPrice = $addonUnlimitedForWebzine->getLabelPrice();

        /*
         * Get Prices of Reseller V4
         */
        $monthlyTarifV4 = Tarifs::getTarif(145, "GB_CLASSIC_RESELLER_M");
        $yearlyTarifV4 = Tarifs::getTarif(145, "GB_CLASSIC_RESELLER_Y");

        // Addon price for this app
        $addonDefaultForWebzine = new AddonDefaultForWebzine($addonDefault, $this->webzine);

        $this->view->content = str_replace(
            ["[ADD-ON]", "[UNLIMITED_PRICE]", "[OFFER]", "[MONTHLYPRICE]", "[YEARLYPRICE]"],
            [
                "<strong>" . $addonDefault->getLabel() . "</strong>",
                $libUnlimitedPrice,
                "<strong>" . $this->translater->get("TARIF_" . $resellerAlphaProject->tarif) . "</strong>",
                $this->ui->displayAmount($monthlyTarifV4->getAmountHT()),
                $this->ui->displayAmount($yearlyTarifV4->getAmountHT())
            ],
            $this->translater->get("GBRESELLER_106")
        );

        $this->view->linkAdd = str_replace("[ADDONPRICE]", $addonDefaultForWebzine->getLabelPrice(), $this->translater->get("GBRESELLER_107"));
        $this->view->addonCode = $addonCode;
    }

    public function modalUnlimitedAddonAction($addonCode)
    {
        $addonDefault = AppsGbAddonsDefault::get($addonCode);

        /*
         * Get price of unlimited addon
         */
        $addonUnlimitedForWebzine = new AddonUnlimitedForWebzine($addonDefault, $this->webzine->getResellerAlphaProject());
        $libUnlimitedPrice = $addonUnlimitedForWebzine->getLabelPrice();

        $this->view->content = str_replace(
            ["[ADD-ON]", "[UNLIMITEDPRICE]"],
            ["<strong>" . $addonDefault->getLabel() . "</strong>", $libUnlimitedPrice],
            $this->translater->get("GBRESELLER_109")
        );

        $this->view->btnBuy = $libUnlimitedPrice;
        $this->view->addonCode = $addonCode;
    }

    public function modalUnitOrUnlimitedAction($addonCode)
    {
        $addonDefault = AppsGbAddonsDefault::get($addonCode);

        //Get price for this app solo
        $addonDefaultForWebzine = new AddonDefaultForWebzine($addonDefault, $this->webzine);
        $libUnitPrice = $addonDefaultForWebzine->getLabelPrice();

        /*
         * Get price for unlimited addon
         */
        $addonUnlimitedForWebzine = new AddonUnlimitedForWebzine($addonDefault, $this->webzine->getResellerAlphaProject());
        $libUnlimitedPrice = $addonUnlimitedForWebzine->getLabelPrice();

        $this->view->titleModal = str_replace("[ADD-ON]", $addonDefault->getLabel(), $this->translater->get("GBRESELLER_111"));

        $this->view->content = str_replace(["[ADD-ON]", "[APPNAME]"], ["<strong>" . $addonDefault->getLabel() . "</strong>", "<strong>" . $this->webzine->getBeautifulWebzineName() . "</strong>"], $this->translater->get("GBRESELLER_112"));

        $this->view->infosUnit = str_replace("[APPNAME]", "<strong>" . $this->webzine->getBeautifulWebzineName() . "</strong>", $this->translater->get("GBRESELLER_114"));
        $this->view->infosUnlimited = str_replace("[APPNAME]", "<strong>" . $this->webzine->getBeautifulWebzineName() . "</strong>", $this->translater->get("GBRESELLER_116"));

        $this->view->btnBuyUnit = $libUnitPrice;
        $this->view->btnBuyUnlimited = $libUnlimitedPrice;
        $this->view->addonCode = $addonCode;
    }

    public function buyUnlimitedAddonAction($addonCode, $webzineCible = "")
    {
        // Si site reseller et user nest pas proprio, on remballe
        if (!empty($this->webzine->getWhiteLabelAgency(true)) && !$this->acl->isProprio()) {
            return $this->response->redirect($this->url->getRedirectUrl("addons/detail/$addonCode/"));
        }

        return $this->response->redirect($this->url->getPaymentUrl("addon", $addonCode . "_unlimited"));
    }

    /**
     * Get Label price for an addon
     * @param AddonDefaultForWebzine $addonDefaultForWebzine
     * @param $addonCode
     * @return string
     */
    private function getLabelPrice(AddonDefaultForWebzine $addonDefaultForWebzine, $addonCode)
    {
        $priceAddon = 0;

        /*
         * Exception clubcard : on ajoute le prix de Punchcard sil est pas deja installé
         */
        if ($addonCode == "clubcard" && !$this->acl->isAddonEnable("punchcard")) {
            $punchcardAddon = AppsGbAddonsDefault::get("punchcard");
            $punchcardAddonForWebzine = new AddonDefaultForWebzine($punchcardAddon, $this->webzine);
            $pricePunchCard = $punchcardAddonForWebzine->getPrix();

            $priceAddon = ($pricePunchCard + $addonDefaultForWebzine->getPrix());
        }

        return $addonDefaultForWebzine->getLabelPrice($priceAddon);
    }

    /**
     * Get Label price for an addon unlimited
     * @param AddonDefaultForWebzine $addonDefaultForWebzine
     * @param $addonCode
     * @return string
     */
    private function getLabelPriceUnlimited(AddonDefaultForWebzine $addonDefaultForWebzine, $addonCode)
    {
        $priceAddon = $addonDefaultForWebzine->getPrix() * 10;

        /*
         * Exception clubcard : on ajoute le prix de Punchcard sil est pas deja installé
         */
        if ($addonCode == "clubcard" && !$this->acl->isAddonEnable("punchcard")) {
            $punchcardAddon = AppsGbAddonsDefault::get("punchcard");
            $punchcardAddonForWebzine = new AddonDefaultForWebzine($punchcardAddon, $this->webzine->getResellerAlphaProject());
            $pricePunchCard = $punchcardAddonForWebzine->getPrix() * 10;

            $priceAddon = ($pricePunchCard + $priceAddon);
        }

        return $addonDefaultForWebzine->getLabelPrice($priceAddon);
    }

    public function modalAddonChangeOfferAction($code, $action = "")
    {
        // Do not change offer if not in test
        if (!$this->request->isAjax() && !$this->webzine->isTest()) return;

        $default = AppsGbAddonsDefault::get($code);
        $addonDefaultForWebzine = new AddonDefaultForWebzine($default, $this->webzine);
        if ($action == "test") {
            $text = $this->translater->get("GBADDONS_42");
            $idBtnAction = "btn-modal-test";
            $libBtnAction = $this->translater->get("GBDEVELOPER_5");
            $jsBtnAction = "$($('.form-test')[0]).submit(); $('#modal-responsive').hide();";
        } else {
            $text = $this->translater->get("GBADDONS_44");
            $idBtnAction = "btn-modal";
            $libBtnAction = $addonDefaultForWebzine->getLabelPrice();
            $jsBtnAction = "$($('.form-add')[0]).submit(); $('#modal-responsive').hide();";
        }

        $myOffer = TarifsConfig::getOffer($this->webzine->tarif);
        $myPlanLabel = $this->translater->get("GBNAMEPLAN_" . mb_strtoupper(str_replace("V4", "", $myOffer)));

        // Upgrade de plan proposee pour acceder aux addons avancés
        // Nouvelles régles de migration (en test)
        if ($this->view->isBackofficeLikeClassicPlan) {
            // Regles pour eventuels vieux plans en test
            $newPlanLabel = $this->translater->get("GBNAMEPLAN_CLASSICFULL");
        } elseif ($this->view->isShopPlan) {
            $newPlanLabel = $this->view->isPwaPlan ? $this->translater->get("GBNAMEPLAN_SHOPFULL") : $this->translater->get("GBNAMEPLAN_SHOPPREMIUM");
        } else {
            $newPlanLabel = $this->view->isPwaPlan ? $this->translater->get("GBNAMEPLAN_PWAFULL") : $this->translater->get("GBNAMEPLAN_ANDROIDFULL");
        }


        $text = str_replace(["[MYOFFER]", "[ADDON]", "[NEWOFFER]"], [$myPlanLabel, "<strong>" . $default->getLabel() . "</strong>", "<strong>" . $newPlanLabel . "</strong>"], $text);

        $this->view->code = $code;
        $this->view->content = $text;
        $this->view->action = $action;
        $this->view->idBtnAction = $idBtnAction;
        $this->view->libBtnAction = $libBtnAction;
        $this->view->jsBtnAction = $jsBtnAction;
    }

    /**
     * @param $code
     * @return bool
     */
    private function hasUserRight($code)
    {
        // Test sur les user rights
        $userAttribs = $this->auth->getUserAttribs();

        if (!empty($userAttribs["forbidden_page_sectionsmanagement"]) && $userAttribs["forbidden_page_sectionsmanagement"] == "oui") {
            foreach (Section::$GB_type_section as $typeSection => $infos) {
                if (!empty($infos["addOn"]) && $infos["addOn"] == $code) {
                    return false;
                }
            }
            foreach (Section::$GB_type_service as $typeService => $infos) {
                if (!empty($infos["addOn"]) && $infos["addOn"] == $code) {
                    return false;
                }
            }
        }

        return true;
    }

    private function getPopoverJsUserChat()
    {
        $default = AppsGbAddonsDefault::get("userchat");
        $addonName = $default->getLabel();
        $message = str_replace("[ADDON_NAME]", $addonName, $this->translater->get('GB_ALERT_APP_FOR_KIDS_ADDON_CONFLICT_2'));
        $modalArgs = ["content" => $message, "noButton" => true];
        $js = $this->ui->popoverAlertJS($modalArgs);

        return $js;
    }
}

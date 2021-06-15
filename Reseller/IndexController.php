<?php

namespace Engine\GBReseller\Controllers;

use Common\Lib\Domain\DomainManager;
use Common\Lib\Goodbarber\Billing\Subscription\Subscription;
use Common\Lib\Goodbarber\Billing\TarifsConfig;
use Common\Lib\Goodbarber\Submission;
use Common\Models\AppsGbParams;
use Common\Models\Webzine;
use \Common\Lib\Webzine\Creator\GbCreator;
use \Common\Models\WebzineCategorie;

class IndexController extends BaseController
{
    public function indexAction()
    {

        $type = $this->reseller->getTypeAgence($this->agence->id_agence_mere);

        //name of page
        $this->view->activeMenu = $type;

        //title
        if ($type == "apps") $var_title = "RESELLER_1";
        else if ($type == "webtv") $var_title = "RESELLER_21";
        else                        $var_title = "RESELLER_2";
        $this->view->title = $this->translater->get($var_title);

        /** @var Webzine $alphaProject */
        $alphaProject = $this->agence->getWebzinePrincipal();
        $this->view->alphaProject = $alphaProject;

        //Add webzine
        if (($add_webzine = $this->request->getPost("id_webzine"))) {

            /** @var Webzine $new_webzine */
            $new_webzine = \Common\Models\Webzine::findFirst(array(
                "conditions" => "id_webzine=:id_webzine:",
                "bind" => array(
                    "id_webzine" => $add_webzine,
                )));
            if ($new_webzine) {
                $new_webzine->id_sous_agence = $this->agence->id_agence;
                if ($this->agence->type == 5) {

                    // L'app devient gratuite
                    $new_webzine->valide = 8;

                    // On conserve ce tarif pour Echurch
                    $newTarif = "GB_T5";

                    // Pour les autres, plan par defaut
                    if ($new_webzine->isV4()) {
                        $newTarif = "GB_CLASSIC_PREMIUM_Y";
                        // Pour les reseller shop, possibilite de créer des fistons shop
                        if ($alphaProject->isResellerClassicAndShopPlan() && $new_webzine->isShopPlan()) {
                            $newTarif = "GB_SHOP_PREMIUM_Y";
                        }
                    }
                    $new_webzine->tarif = $newTarif;
                }
                $new_webzine->update();

                try {
                    $domainManager = new DomainManager();
                    $domainManager->createSymlinks($new_webzine);
                } catch (\Exception $e) {

                }

                /**
                 * Si un user pas proprio rattache un de ses sites a l'agence, on met le proprio de l'agence proprio de lapp ajoutée
                 */
                if ($this->user->id_user != $this->agence->id_user && !empty($new_webzine) && $new_webzine instanceof Webzine) {
                    $proprio = $this->agence->getAdmin();
                    $proprio->assignToWebzineTeam($new_webzine, $this->language);
                    $new_webzine->changeAdmin($this->agence->id_user);
                }

                $this->addAutomaticAccessUser($new_webzine);

                /*
                 * Call API Push to update the quota
                 */
                $this->pushApi->setWebzine($new_webzine);
                $this->pushApi->updateQuotaPush();

                return $this->response->redirect($this->url->getRedirectRefreshurl());
            }
        }

        //Content of select of modal ("existing app")
        if ($type == "apps")
            $good_type = " type = 'goodbarber' ";
        else if ($type == "webtv")
            $good_type = " type = 'webtv'";
        else
            $good_type = " type <> 'goodbarber' AND type <> 'webtv' ";


        // No Resseler in list
        $filterFillo = " AND tarif not like '%RESELLER%'";

        // Filter for old Reseller V3 & Classic Reseller & Echurch
        if ($alphaProject->isResellerPlanV3() || $alphaProject->isResellerClassicPlan() || $alphaProject->id_sous_agence == 559) {
            $filterFillo .= " AND tarif NOT IN ('" . implode("', '", TarifsConfig::$plansPerType["shop"]) . "')";
        }

        // Filter for ClassicShop Reseller
        if ($alphaProject->isResellerClassicAndShopPlan()) {
            $filterFillo .= " AND tarif not like '%PLATINUM%'";
        }

        $otherWebzines = \Common\Models\Webzine::find(array(
            "conditions" => "id_user=:id_user: AND " . $good_type . " AND site=0 AND id_sous_agence<>:id_sous_agence: $filterFillo",
            "bind" => array(
                "id_user" => $this->user->id_user,
                "id_sous_agence" => $this->agence->id_agence
            ),
            "order" => "date_creation DESC"
        ));

        // On controle qui a des addons payant avec facture
        $hasPaidAddonsWebzines = $otherWebzinesFiltered = array();
        foreach ($otherWebzines as $other) {
            // Only App with no agency, or with agency not Reseller
            if (!$other->getWhiteLabelAgency(true)) {
                $otherWebzinesFiltered[] = $other;
            }

            $subscription = new Subscription($other);
            foreach ($subscription->getOptions() as $option) {
                if ($option->isAddon() && $option->factureDetail->type_detail != "gbdevelopertools") {
                    $hasPaidAddonsWebzines[$other->id_webzine] = 1;
                    break;
                }
            }
        }

        $this->view->hasPaidAddonsWebzines = $hasPaidAddonsWebzines;
        $this->view->otherWebzines = $otherWebzinesFiltered;

        /** @var Webzine $alpha */
        $alpha = $this->agence->getWebzinePrincipal();

        $conditionsDesc = "";
        // If Reseller V4, display V4 descriptions for app creation
        if ($alpha->version == 4) {
            $conditionsDesc = "v4";
        }
        $categories = WebzineCategorie::getCategorieByAgence($this->agence->id_agence_mere, $conditionsDesc);

        $arrayCatClassic = $arrayCatShop = $to_unset = [];
        foreach ($categories as $category) {
            $label = $this->_getLabelCategory($category);

            if (!empty($category->childof)) {
                $label_parent = $this->_getLabelCategory(WebzineCategorie::findFirst($category->childof));
                $to_unset[] = $category->childof;
                if ($category->childof == 174) {
                    $arrayCatShop[$category->id_categorie] = $label;
                } else {
                    $arrayCatClassic[$label_parent][$category->id_categorie] = $label;
                }
            } else {
                $arrayCatClassic[$category->id_categorie] = $label;
            }
        }

        foreach ($to_unset as $id) {
            unset($arrayCatClassic[$id]);
        }

        $this->view->categoriesClassic = $arrayCatClassic;
        $this->view->categoriesShop = $arrayCatShop;

        // On affiche un message

        if ($this->reseller->isDisabled($this->agence)) {
            if (!empty($alpha) && $alpha->valide == 9) {
                $errorMsg = $this->translater->get("GBRESELLER_53");
            } elseif (!empty($alpha) && $alpha->hasNewCgs($alpha->getAdmin()->id_user)) {
                $errorMsg = $this->translater->get("CGS_6") . " <a href='" . $alpha->getDomainRoot() . "/manage/' target='_blank'>" . $this->translater->get("CGS_7") . "</a>";
            } else {
                $errorMsg = str_replace("[LINKALPHA]", $alpha->getDomainRoot() . "/manage/settings/billing/paymentinfo/", $this->translater->get("GBRESELLER_89"));
            }
            $this->flash->error("<b>" . $errorMsg . "</b>");
        }
        if (!\Control::goodIp()) {
            $this->view->inlineCss .= ".table-reseller .goodip { display:none; }";
        }

        $this->view->firstTimeVideo = $this->_hasToShowVideo();
        $this->view->freeTrial = $alphaProject->isTest();
    }

    private function _getLabelCategory(WebzineCategorie $category)
    {
        $label = (!empty($this->translater->get($category->titre, $this->language, "Langage")) ? $this->translater->get($category->titre, $this->language, "Langage") : $category->META_titre);

        return $label;
    }

    public function getwebzinesAction($type = null)
    {
        if (empty($type)) {
            $type = $this->request->get('type');
        }

        $this->microtime->trace("Before Query getwebzines");

        // Search
        $searchKey = "";
        $search = $this->request->getPost("search");
        if (!empty($search) && is_array($search)) {
            $searchKey = $search["value"];
        }

        $builder = $this->modelsManager->createBuilder();

        $joins = [];

        // Order
        $orderBy = "";
        $order = $this->request->getPost("order");
        if (!empty($order) && is_array($order)) {
            switch ($order[0]["column"]) {
                case 1:
                    $orderBy = "webzine.id_webzine";
                    break;
                case 2:
                    $orderBy = "webzine.titre";
                    break;
                /*case 3: $orderBy = "v_submission_status_iphone.status"; $joins[] = "v_submission_status_iphone"; break;
                case 4: $orderBy = "v_submission_status_android.status"; $joins[] = "v_submission_status_android"; break;*/
                case 5:
                    $orderBy = "webzine.valide";
                    break;
            }
            if (!empty($orderBy)) $orderBy .= " " . (strtolower($order[0]["dir"]) == "desc" ? "desc" : "asc");
        }

        // Moteur de recherche
        if (!empty($searchKey)) {
            $or = "";
            if (is_numeric($searchKey)) {
                $or = " OR cast(webzine.id_webzine as text) like '%$searchKey%'";
            }
            $builder->andWhere("webzine.titre ilike :key:" . $or, array("key" => "%" . $searchKey . "%"));
        }

        // Filters
        $tarif = $this->request->getPost("tarif");
        if (!empty($tarif)) {
            $builder->andWhere("webzine.tarif = :tarif:", array("tarif" => $tarif));
        }
        $status = $this->request->getPost("status");
        if (!empty($status)) {
            $tab_conv_status = ["actif" => ["0", "8"], "desactive" => ["9"]];
            $builder->inWhere("webzine.valide", $tab_conv_status[$status]);
        }
        /*$ios = $this->request->getPost("ios");
        if (!empty($ios)) {
            $builder->andWhere("v_submission_status_iphone.status=:ios:", array("ios" => $ios));
            $builder->groupBy('webzine.id_webzine');
            $joins[] = "v_submission_status_iphone";
        }
        $android = $this->request->getPost("android");
        if (!empty($android)) {
            $builder->andWhere("v_submission_status_android.status=:android:", array("android" => $android));
            $builder->groupBy('webzine.id_webzine');
            $joins[] = "v_submission_status_android";
        }*/

        // La requete
        $builder->from(array('webzine' => '\Common\Models\Webzine'));
        if (in_array("v_submission_status_iphone", $joins)) $builder->join("\Common\Models\Views\VSubmissionStatusIphone", "webzine.id_webzine = v_submission_status_iphone.id_webzine", 'v_submission_status_iphone');
        if (in_array("v_submission_status_android", $joins)) $builder->join("\Common\Models\Views\VSubmissionStatusAndroid", "webzine.id_webzine = v_submission_status_android.id_webzine", 'v_submission_status_android');
        $builder->andWhere('webzine.id_sous_agence=:id_sous_agence:', array("id_sous_agence" => $this->agence->id_agence));
        $builder->andWhere('webzine.id_webzine != :id_webzine: ', array("id_webzine" => $this->agence->id_webzine_principal));
        $builder->inWhere('webzine.valide', ["0", "8", "9"]);

        // Tri
        if (empty($orderBy)) {
            $builder->orderBy('webzine.valide, webzine.id_webzine desc');
        } else {
            $builder->orderBy($orderBy);
        }

        $builderTotal = clone $builder;
        $total = $builderTotal->getquery()->execute()->count();;

        // Le parsing
        $builder->limit($this->request->get('length'), $this->request->get('start'));
        //$builderInactif->andWhere('webzine.valide = "9"');

        $allWebzine = array();

        foreach ($builder->getquery()->execute() as $webzine) {
            $allWebzine[] = $webzine;
        }
        $principal = Webzine::findFirst($this->agence->id_webzine_principal);
        if ($principal) $allWebzine[] = $principal;

        $this->microtime->trace("After Query getwebzines");


        // On construit le tableau en fonction des données à afficher
        $body_table = array();

        if ($type == "gb") {
            $body_table = $this->formatTableApps($allWebzine);
        } elseif ($type == "webtv") {
            $body_table = $this->formatTableWebSites($allWebzine);
        }

        $this->microtime->trace("After formatTableApps");

        $this->view->disable();
        return $this->response->setContentType("application/json")->setContent(json_encode(array("draw" => intval($this->request->get("draw")), "recordsTotal" => count($allWebzine), "recordsFiltered" => $total, "data" => $body_table)))->send();
    }

    public function versionsAction()
    {
        $this->view->tableArray = $this->getTableVersions();
    }

    public function createAction()
    {
        $this->view->disable();
        if ($this->request->isPost() && $this->request->isAjax() && $this->request->hasPost("appname")) {
            $version = null;

            /** @var Webzine $alphaProject */
            $alphaProject = $this->agence->getWebzinePrincipal();

            // Exception Echurch
            if($alphaProject->id_sous_agence == 559){
                $version = 3;
                $tarifToCreate = "GB_T5";
            }


            // Nvo plan par tous les autres resellers
            elseif ($alphaProject->isResellerPlan() || ($alphaProject->isResellerPlanV3() && $alphaProject->isV4())) {
                $version = 4;
                $tarifToCreate = "GB_CLASSIC_PREMIUM_Y";

                // Plan Reseller Shop ou Platinum si pere resseler Shop + choix de faire un shop
                if(($alphaProject->isResellerClassicAndShopPlan() || $alphaProject->isResellerPlatinumPlan()) && $this->request->getPost("typePlan", "striptags") == "shop"){
                    $tarifToCreate = "GB_SHOP_PREMIUM_Y";
                }

            }

            $creator = new GbCreator($alphaProject, $version);

            $datas = $this->request->getPost();

            $datas["id_sous_agence"] = $this->agence->id_agence;
            $datas["id_user"] = $this->agence->id_user;
            $datas["identifiant"] = "duoapps-" . $this->request->getPost("appname", "striptags");
            $datas["tarif"] = $tarifToCreate;
            $datas["langue"] = $this->language;
            $datas["categorie"] = $this->request->getPost("categorie", "int");

            // On crée les fillo avec la devise de factu de l'app alpha
            $datas["devise"] =  $this->agence->devise;

            $newWebzine = $creator->create($datas);

            // Creation fail
            if(!$newWebzine){
                $this->response->setContent("fail")->send();
            }else{
                if ($this->user->id_user != $this->agence->id_user && !empty($newWebzine) && $newWebzine instanceof Webzine) {
                    $this->user->assignToWebzineTeam($newWebzine, $this->language);
                }
                $this->addAutomaticAccessUser($newWebzine);
                $this->response->setContent("ok")->send();
            }


        }
    }

    public function checkappnameAction()
    {
        $this->view->disable();
        if ($this->request->isPost() && $this->request->isAjax() && $this->request->hasPost("appname")) {
            $webzine = $this->agence->getWebzinePrincipal();
            if (!$webzine) {
                $message = $this->translater->get("GBRESELLER_57") . ". " . $this->translater->get("THREAD_9") . ".";
                if (\Control::goodIp()) $message .= "<br/><b>GOODIP : </b>id_webzine_principal from Agence " . $this->agence->id_agence . " is empty !";
            } else {
                $creator = new GbCreator($webzine);

                if (!$creator->validateIdentifiant("duoapps-" . $this->request->getPost("appname", "striptags"))) {
                    $messages = $creator->getMessages();
                    $message = $messages[0]->getMessage();
                } else {
                    $message = "";
                }
            }

            $this->response->setContent($message)->send();
        }
    }

    /**
     * Fonction qui retourne le tableau d'application GB
     * @param $listWebzinesEnfant
     * @return array
     */
    protected function formatTableApps($listWebzinesEnfant)
    {

        $body_table = array();

        $userAuth = $this->auth->getUser();
        $ident = $userAuth->getIdent();

        $cpt = 0;
        foreach ($listWebzinesEnfant as $key => $webzine) {
            $cachekey = $this->reseller->getInfosAppCacheKey($webzine);
            $content = $this->cache->get($cachekey);
            if (empty($content) || !is_array($content)) {
                $content = $this->reseller->processInfosApp($webzine);
            }

            $content[2] .= " <span class='goodip text-danger'>(" . str_replace('duoapps-', "", $webzine->identifiant) . ")</span>";

            if ($this->reseller->isDisabled($this->agence)) {
                $content['DT_RowClass'] .= " ";
                // Si l'utilisateur a acces au back, on met le lien vers le back
            } elseif ($userAuth->getAttrib("id_webzine", $webzine->id_webzine) == $webzine->id_webzine) {
                $suffix = "";
                if ($webzine->id_sous_agence == 559) {
                    $suffix = "settings/export/";
                }

                $content['DT_RowAttr']['data-href'] = $webzine->getComGoodbarberRoot() . ($webzine->version >= Webzine::GB_STARTVERSION ? "/manage/".$suffix : "/admin") . "?ident=" . $ident; // GoodbarberRoot -> ca va plus vite
            } else {
                $content['DT_RowClass'] .= "notAcces";
            }

            $content[6] = "<span class='hidden'>$webzine->valide</span>" . $this->getBtnAction($webzine); // le texte hidden c'est pour le trie

            $body_table[$cpt] = $content;
            $cpt++;
        }

        return $body_table;
    }

    /**
     * Fonction qui retourne le tableau d'application GB
     * @param $listWebzinesEnfant
     * @return array
     */
    protected function formatTableWebSites($listWebzinesEnfant)
    {

        $body_table = array();
        $ident = $this->auth->getUser()->getIdent();

        foreach ($listWebzinesEnfant as $key => $webzine) {

            $body_table[$key][0] = $webzine->id_webzine;
            $body_table[$key][1] = $webzine->identifiant;
            $body_table[$key][2] = $this->reseller->getStatut($webzine, $this->language);
            $body_table[$key][3] = $this->translater->get("TARIF_" . $webzine->tarif);
            $body_table[$key][4] = $webzine->date_creation;
//            $body_table[$key][5] = '';

            if ($webzine->valide == 9) {
                $body_table[$key]['DT_RowClass'] = " disabled ";
//                $body_table[$key][5] = $this->getBtnPopover($webzine->id_webzine,'delete');
            }

            // Pour les filtres
            $body_table[$key]['DT_RowId'] = "row-" . $webzine->id_webzine;
            $body_table[$key]['DT_RowAttr'] = array(
                "data-href" => $webzine->getDomainRoot() . ($webzine->version >= Webzine::GB_STARTVERSION ? "/manage/" : "/admin/") . "?ident=" . $ident,
                "data-id" => $webzine->id_webzine,
                "data-type" => $webzine->type,
                "data-status" => $this->reseller->getTypeStatus($webzine),
                "data-tarif" => $webzine->tarif
            );
        }

        return $body_table;
    }

    /**
     * Retourne les boutons d'action du webzine possible pour l'user
     * @param $webzine
     * @return string
     */
    private function getBtnAction($webzine)
    {

        // S'il n'a pas l'acces au back ou que c'est le projet alpha, on ne crée pas les boutons de modifications
        if (!($this->userCanChangeState($webzine))) {
            $retu = $this->popoverNotCanChange($webzine);
        } else {
            $retu = "";

            if ($webzine->valide == 9) {
                $retu .= $this->getBtnPopover($webzine, 'active');

                // On ne peux supprimer une apps que si on est le proprio
                $admin = $webzine->getAdmin();
                if ($admin && $admin->id_user == $this->auth->getUser()->id_user) {
                    $retu .= $this->getBtnPopover($webzine, 'delete');
                }
            } else {
                $retu .= $this->getBtnPopover($webzine, 'desactive');
            }

        }
        return $retu;
    }

    /**
     * Retourne le bouton avec le popover
     * @param $webzine
     * @param $state
     * @return bool|string
     */
    private function getBtnPopover($webzine, $state)
    {

        $arrayPopover = array("oncompleteJs" => "reloadTable();");
        switch ($state) {
            case "active":
                $arrayPopover['onYesUrl'] = $this->url->getUrl("index/?activeWebzine=" . $webzine->id_webzine);
                $arrayPopover['content'] = $this->translater->get("RESELLER_58");
                $class = "fa-certificate text-primary text-danger";
                break;
            case "desactive":
                if ($this->userCanDesactive($webzine)) {
                    $arrayPopover['onYesUrl'] = $this->url->getUrl("index/?desactiveWebzine=" . $webzine->id_webzine);
                    $arrayPopover['content'] = $this->translater->get("RESELLER_80");
                } else {
                    $arrayPopover['onYesUrl'] = "";//$this->urlSupportParent;
                    $arrayPopover['btnlabel'] = ucfirst($this->translater->get("OK"));
                    $arrayPopover['content'] = $this->translater->get("GBRESELLER_124");
                }
                $class = "fa-certificate text-primary text-success";
                break;
            case "delete":

                // on empêche la suppression d'une app d'un reseller, si elle a deja une facture ou une commande (elle à été rattaché a un reseller a posteriori )
                if ($this->userCanDeleted($webzine)) {
                    $arrayPopover['onYesUrl'] = $this->url->getUrl("index/?deleteWebzine=" . $webzine->id_webzine);
                    $arrayPopover['content'] = $this->translater->get("RESELLER_67");
                } else {
                    $arrayPopover['onYesUrl'] = "";//$this->urlSupportParent;
                    $arrayPopover['btnlabel'] = ucfirst($this->translater->get("OK"));
                    $arrayPopover['content'] = $this->translater->get("GBRESELLER_12");
                }
                $class = "fa-times tabledesactive tab_case";
                break;
            default :
                return false;
        }

        $popover = $this->ui->popoverAlert($arrayPopover);

        return '<a id="state-webzine-' . $webzine->id_webzine . '" class="fa pointer ' . $class . ' fa-2x pull-left" ' . $popover . '></a>';

    }

    public function exportAction()
    {
        $sql = "SELECT id_webzine, identifiant, valide FROM webzine WHERE id_sous_agence=" . $this->agence->id_agence.
            " AND valide IN (0, 8, 9) ORDER BY FIELD (id_webzine, ".$this->agence->id_webzine_principal."), id_webzine desc";

        $connection = $this->wrapper->getConnection("isegora_slave");
        if (!empty($connection)) {
            $webzines = $connection->fetchAll($sql, \Phalcon\Db::FETCH_ASSOC);

            $this->view->disable();
            $this->response->setHeader("Content-Type", "application/csv");
            $this->response->setHeader("Content-Disposition", 'attachment; filename="'.date("Y-m-d").'-agency-'.$this->agence->id_agence.'-app-export.csv"');
            $fp = fopen('php://output', 'w');

            fputs($fp, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
            fputcsv($fp, array("ID", "NAME", "PUBLISHED"), ";");
            foreach ($webzines as $row) {

                $published = "NO";
                $subidentifiant = preg_replace("#^duoapps-#", "", $row["identifiant"]);

                if ($row["valide"] != 9) {
                    $sql_published_once = "SELECT valeur FROM " . AppsGbParams::getPartitionedSource($row["id_webzine"]) . " WHERE id_webzine=" . $row["id_webzine"];
                    $sql_published_once .= " AND objet='submission/etat' AND valeur IN ('published', 'validating') LIMIT 1";
                    $result = $connection->query($sql_published_once, \Phalcon\Db::FETCH_ASSOC);
                    if ($result->numRows() > 0) {
                        $published = "YES";
                    } else {
                        // Web APP
                        $path = $this->config->gl_path["apps"] . "duoapps/" . substr($subidentifiant, 0, 1) . "/" . $subidentifiant . "/webapp/prod/front-assets/json/settings.json";
                        if (file_exists($path)) {
                            $published = "YES";
                        }
                    }
                }
                fputcsv($fp, array($row["id_webzine"], $subidentifiant, $published), ";");
            }
            $this->response->send();

        }
    }

    private function popoverNotCanChange($webzine)
    {

        // Si c'est le projet alpha on met pas la popover
        if ($webzine->id_webzine != $this->agence->id_webzine_principal) {

            if (empty($this->popoverNotCanChange)) {
                $arrayPopover = array(
                    'btnlabel' => ucfirst($this->translater->get("OK")),
                    'content' => $this->translater->get("GBRESELLER_16"),
                    'onYesUrl' => ""
                );
                $this->popoverNotCanChange = $this->ui->popoverAlert($arrayPopover);
            }

            $popover = $this->popoverNotCanChange;
        } else {
            $popover = "";
        }

        if ($webzine->valide == 9) $class = "text-danger";
        else $class = "text-success";

        return '<div class="popoverNotCanChange fa fa-certificate text-primary ' . $class . ' fa-2x pull-left" ' . $popover . ' ></div>';
    }

    /**
     * On rajoute tous ceux de l'equipe qui on l'attrib automaticAccess dans le webzine passé en param
     * @param Webzine $newWebzine
     */
    private function addAutomaticAccessUser(Webzine $newWebzine)
    {
        $team = $this->agence->getTeam();
        if (!empty($team)) {
            foreach ($team as $wmUser) {
                $agenceUser = $this->agence->getUser($wmUser->id_user);
                if (!empty($agenceUser->getAttrib("automaticAccess"))) {
                    $wmUser->assignToWebzineTeam($newWebzine, $this->getLanguage($wmUser));
                }
            }
        }
    }

    /**
     * On vérifie si on doit afficher l'onboarding vidéo
     * @return bool
     */
    private function _hasToShowVideo()
    {
        $user = $this->auth->getUser();

        if (empty($user->getAttrib("resellerOnboardingVideoWatched"))) {
            $user->setAttrib("resellerOnboardingVideoWatched", time());

            return true;
        }

        return false;
    }
}

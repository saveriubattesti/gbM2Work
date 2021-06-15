<?php

namespace Engine\GBBackOffice\V4\Controllers;

use Common\Lib\Assets;
use Common\Lib\Goodbarber\Commerce\Api\AcquisitionApi;
use Common\Lib\Goodbarber\Commerce\Api\BackuserApi;
use Common\Lib\Goodbarber\Commerce\Api\NotificationTemplateApi;
use Common\Lib\Goodbarber\Commerce\CommerceApiError;
use Common\Lib\Goodbarber\Commerce\PaymentServices\PaymentServicesManager;
use Common\Lib\Goodbarber\Commerce\Push\Form;
use Common\Lib\Goodbarber\Commerce\Push\OrderTracking\PushTemplate;
use Common\Lib\Goodbarber\Commerce\Push\Scheduler;
use Common\Lib\Goodbarber\Commerce\Push\TargetsTabler;
use Common\Lib\Goodbarber\Commerce\User\CommerceCustomer;
use Common\Lib\Goodbarber\Push\Grapher as PushGrapher;
use Engine\GBBackOffice\Controllers\PushController;
use Common\Lib\Goodbarber\Commerce\Push\OrderTracking\Tabler as NotificationTemplateTabler;


use \Common\Lib\Goodbarber\Push\CriteriasSummary;

use \Common\Lib\Date;
use \Common\Lib\Goodbarber\Amcharts;
use Engine\GBBackOffice\V4\Lib\Dashboard\Blocks\ConversionRateRenderer;
use Engine\GBBackOffice\V4\Lib\Push\PushConfiguration;

class CommercepushController extends PushController
{
    protected $scheduler;
    protected $date_debut;
    protected $date_fin;
    protected $typePush = 'commerce';

    /**
     * @var BackuserApi
     */
    private $customerApi;

    /**
     * @var NotificationTemplateApi
     */
    private $notificationTemplateApi;

    public function beforeExecuteRoute($dispatcher)
    {
        $this->commerceApiErrorHandler->dispatcherForward("commerce:onGetFailed");
        $commerce = $this->commerceApiWrapper->getCommerce();
        if (!$commerce) {
            return;
        }

    }

    public function initialize()
    {
        parent::initialize();

        $this->customerApi = new BackuserApi();
        $this->urlToHistory = "commerce/push/history/";
        $this->backTitle = $this->translater->get("PUSH_1");
        $this->view->setLayout("push");

        $this->view->mobileCompliantPage = true;

        $this->notificationTemplateApi = new NotificationTemplateApi();
    }

    public function sendAction($id_send = 0)
    {
        $this->controlPwaCert();
        $this->view->appIcon = $this->ui->getFirstIconUrl($this->webzine);
        $this->view->mobileCompliantPage = true;

        $notification = null;


        if (!empty($id_send)) {
            $notification = $this->pushApi->findById(intval($id_send), false);
            //\control::debug2r($notification);exit;

            /**
             * Si objet retourné vide OU false/vide/null
             */
            if (empty((array)$notification) || empty($notification)) {
                return $this->response->redirect($this->url->getRedirectUrl("commerce/users/push/send/"));
            }
        }

        $this->view->linkHelp = ["numhelp" => 99, "numrub" => 54];

        $this->view->quotaPushManager->redirectIfFatalErrorEvent("quota:onGetFailed", true);
        $this->view->isOverQuota = $this->view->quotaPushManager->isOverQuota();
        $this->view->subtitle = "";

        if ($this->view->isOverQuota) {
            return;
        }

        $form = $this->view->form = new Form($notification);
        $this->scheduler = new Scheduler($this->webzine, $form);

        $this->view->subtitle = $this->translater->get("PUSH_14");

        /**
         * Si on est en ajout de notif, on prend les critères de targeting
         * Sinon on utilise ceux définis pour le push en question lors de l'ajout
         */
        $params = [];
        if (!empty((array)$notification)) {

            $this->view->title = $this->backTitle;
            $this->view->backUrl = $this->url->getUrl($this->urlToHistory);
            $this->view->subtitle = "";
            $params = (array)$notification->sending_criterias;
            $allParams = array_merge((array)$notification, $params);

            $dateSend = $form->get("date")->getValue();
            $obj = new \DateTimeImmutable($dateSend);

            if ($obj->getTimestamp() > time()) {
                $this->view->btnLabel = $this->translater->get("GBPUSH_17");
            }

            $this->view->criteriasSummary = new CriteriasSummary($allParams);
        }

        $this->attachEventRedirectSomethingHappened("pushRecipients:onGetFailed");

        $this->view->recipients = $this->commercePushApi->getRecipientsCount($params);

        if (empty((array)$this->view->recipients)) {
            return;
        }

        $this->view->edit = !empty($notification->id);

        /**
         * Ajout / Edition du push
         */
        if ($this->request->isPost() && $this->request->getPost("action", "striptags") == "mod") {
            $params = $this->request->getPost();
            $isProductlLink = $params["linktype"] === "GBShopLinkTypeProduct";
            $isValidProductLink = $isProductlLink && !$this->linkManager->isProductLink($params["link"]);
            if ($isProductlLink) {
                $params["linktype"] = "extern";
            }

            if (!empty($notification->id)) $params["id"] = $notification->id;

            $params["scheduled_by"] = $this->auth->getIdUser();

            $this->eventsManager->attach("push:onSaveFailed", function ($event, $tmp, $params) {
                $error = (!empty($params[2]->err) ? $params[2]->err : $params[2]->error);
                if (!empty($error->err)) {
                    $error = $error->err;
                }
                $this->flash->error($this->translater->getStaticOnEmpty($error, null, "Langage"));
            });

            //\control::debug2r($params);exit;
            if (!$isValidProductLink || ($isValidProductLink && !empty($params["link"]))) {
                if ($this->scheduler->schedulePush($params)) {
                    $this->flashSession->success($this->ui->showMessage($this->translater->get("PUSH_13"), $this->filter->sanitize($params["message"], "striptags")));

                    if (!empty($params["pushDate"]) && $params["pushDate"] == "now") {
                        $redirectUrl = $this->url->getRedirectUrl($this->urlToHistory);
                    } elseif (!empty($notification)) {
                        $redirectUrl = $this->url->getRedirectRefreshUrl();
                    } else {
                        $redirectUrl = $this->url->getRedirectUrl($this->urlToHistory . "#scheduled");
                    }
                    return $this->response->redirect($redirectUrl);
                }
            } else {
                if (!$isProductlLink) {
                    $this->flash->error($this->translater->get('GBSHOPLINKTYPE_PDT_EMPTY'));
                } else {
                    $this->flash->error($this->translater->get('GBSHOPLINKTYPE_PDT_NOT_VALID'));
                }
            }
        }

        /**
         * Get Customers.
         * Si on a une liste d'ids en paramètres, on vient de la page de stats, on préselectionne les users (En mode créationd de push)
         * Sinon si on est en édition de push, on récupère les destinaires customers du push en question
         */
        $customer_ids = [];
        // Reception de targets depuis la page des customers
        if ($this->request->isPost() && !empty($this->request->getPost("checkitems"))) {
            foreach ($this->request->getPost("checkitems") as $customer) {
                list($userId, $name) = explode(";", $customer);
                $customer_ids[] = $userId;
            }
        }

        // Edition notif, get customers set in notif
        if (!empty((array)$notification)) {
            $this->attachEventRedirectSomethingHappened("user:onGetFailed");
            foreach ($notification->customer_ids as $id_cust) {
                $customer_ids[] = $id_cust;
            }
        }

        $customers = [];
        if (!empty($customer_ids)) {
            foreach ($customer_ids as $id_cust) {
                if ($user = $this->customerApi->getUser($id_cust)) {
                    $cust = new CommerceCustomer($user);
                    $customers[] = $id_cust . ";" . $cust->client_num . ";" . $cust->fullName();
                }
            }
        }
        $this->view->customers = $customers;

        // ********* LOCAL TIME ***********
        $dateFormat = $this->auth->getDateFormat();
        if (empty($dateFormat)) {
            $dateFormat = $this->auth->getLanguage();
        }
        $dateFormat = Date::getPickerFormatBylangue($dateFormat);
        $this->view->setVar('pickerFormat', $dateFormat);

        $this->addPreview();
    }


    public function statsAction()
    {
        $this->view->appIcon = $this->ui->getFirstIconUrl($this->webzine);
        $this->view->backUrl = $this->url->getUrl("commerce/push/history/");
        $this->view->mobileCompliantPage = true;


        $this->view->setLayout("push-stats");

        $this->assets->collection('cssHeaderBefore')->addCss(Assets::minifyPath("assets/css/gb_backoffice_v4/pages/stats.css"));

        $id_push = $this->dispatcher->getParam("id_push");

        // On retrouve les infos sur le push
        $notification = $this->pushApi->findById($id_push);
        //\control::debug2r($notification);

        /**
         * Panel
         */
        if (!empty((array)$notification)) {
            $params = (array)$notification->sending_criterias;
            $allParams = array_merge((array)$notification, $params);

            $this->view->criteriasSummary = new CriteriasSummary($allParams);
            $this->view->pushMessage = $notification->message;
            $this->view->backUrl = $this->url->getUrl($this->urlToHistory);
            $this->view->criteriasSummary = new CriteriasSummary($allParams);

            // On recupere la liste des users
            $pushCustomers = [];

            $this->attachEventRedirectSomethingHappened("user:onGetFailed");

            foreach ($notification->customer_ids as $id_cust) {
                $user = $this->customerApi->getUser($id_cust);

                if (empty($user)) {
                    return;
                }

                $cust = new CommerceCustomer($user);
                $pushCustomers[] = $id_cust . ";" . $cust->fullName() . ";" . $cust->email;
            }

            $this->view->commerceCustomers = $pushCustomers;
        }

        /**
         * Backto order detail
         */
        $referer = $this->request->getHTTPReferer();
        if (preg_match("#commerce/orders/#", $referer)) {
            $this->view->backUrl = $referer;
        }

        /**
         * Date locale
         */
        // Si on doit afficher un push envoyer avec l'option "heure locale" a false on affiche a l'heure de la personne connecté
        if (empty($notification->use_local_time)) {
            $date = new \Common\Lib\Date($notification->started_at);
            $date->localTimezone();
            // option "heure locale" a true on affiche a l'heure choisie dans le back sans timezone
        } else {
            $date = new \Common\Lib\Date(preg_replace('/(Z|[+-][0-9]{4})$/', '', $notification->started_at));
        }

        $dateTxt = $date->formatDateTxt(8);
        $this->view->titre = str_replace("[DATE]", $dateTxt, str_replace("[HOUR]", $date->formatDate(2, 0, ""), $this->translater->get("GBCOMMERCE_PUSH_SENT_AT_DATE_HOUR")));

        // GRAPHS **********
        $this->attachEventRedirectSomethingHappened("pushStats:onGetFailed");

        $grapher = new PushGrapher();

        $tabValBloc = $grapher->getPushBlocks();

        if ($tabValBloc === false) {
            return;
        }

        // Graphes column
        $columnPush = $grapher->getPushOpeningGraph();

        // Conversion
        $api = new AcquisitionApi();
        $stats = $api->getAll($id_push);

        if ($stats instanceof CommerceApiError) {
            return;
        }

        $this->view->conversionRate = new ConversionRateRenderer($this->view, [
            "block" => "conversion_rate",
            "stats_data" => $stats,
            "hidePercent" => true
        ]);

        $this->addPreview();

        $this->view->columnPush = $columnPush;
        $this->view->tabValBloc = $tabValBloc;
    }

    public function modalpushusersAction($id_notification = null)
    {
        if (!$this->request->isAjax()) {
            return $this->response->redirect($this->url->getRedirectUrl());
        }

        $tabler = new TargetsTabler();
        $this->view->targets = $tabler->getTargets();
        $this->view->refreshTableUrl = $this->url->getUrl("commerce/push/refreshTabtargets");

        // Display red error message if problem
        if ($this->view->targets === false) {
            $this->view->alertMessage = $this->translater->get("GBCOMMERCE_ERROR_TRY_AGAIN_LATER");
        }
    }

    public function refreshTargetsAction()
    {
        $this->view->disable();

        $customers = json_decode($this->request->getPost("customers"));

        return $this->view->partial("partials/commercepush/targets", array(
            "customers" => $customers
        ));
    }

    public function refreshTabtargetsAction()
    {
        $this->view->disable();

        $this->commerceApiErrorHandler->showRedMessageJS("userPush:onGetFailed", "#modal-pushusers");

        $tabler = new TargetsTabler();

        $keywords = "";
        if ($this->request->hasPost("keywords")) {
            $keywords = $this->request->getPost("keywords", "striptags");
        }
        $filter = null;
        if ($this->request->hasPost("filter")) {
            $filter = $this->request->getPost("filter", "striptags");
        }
        $ordering = $type = null;
        if ($this->request->hasPost("ordering")) {
            $ordArg = $this->request->getPost("ordering", "striptags");
            if (in_array($ordArg, ['customer', 'lead'])) {
                $type = $ordArg;
            } else {
                $ordering = $ordArg;
            }
        }

        $targets = $tabler->getTargets([], $filter, $keywords, $ordering, $type);

        // Display red error message if problem
        if ($targets === false) {
            return;
        }

        return $this->view->partial("partials/utilities/table", array(
            "array" => $targets
        ));
    }


    public function recipientsAction()
    {

        $form = $this->di->get("\Common\Lib\Goodbarber\Commerce\Push\Form");
        $this->scheduler = $this->di->get("\Common\Lib\Goodbarber\Commerce\Push\Scheduler", [$this->webzine, $form]);

        $this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);

        $params = $this->request->getPost();
        $params["scheduled_by"] = $this->auth->getIdUser();
        if ((!empty($params["pushDate"]) && $params["pushDate"] == "specificDate") || (!empty($params["type"]) && $params["type"] == "auto")) {
            $this->view->btnLabel = $this->translater->get("GBPUSH_17");
        }

        $this->scheduler->prepareParams($params);

        $this->view->recipients = $this->commercePushApi->getRecipientsCount($params);
        $this->view->pick("push/recipients");

        $allParams = $this->scheduler->getParams();
        $this->view->criteriasSummary = new CriteriasSummary($allParams);
    }

    public function orderTrackingAction()
    {
        $this->view->title = $this->translater->get("GBCOMMERCE_ORDERTRACKING_TITLE");
        $this->view->linkHelp = ["numhelp" => 445, "numrub" => 134];
        $this->view->subtitle = "";
        $this->eventsManager->fire("specificView:initializeBacktoIndexSettings", $this->view);

        $tabler = new NotificationTemplateTabler();

        $this->view->notificationTemplates = $tabler->getPushTemplates($this->webzine);

        $pushConfig = new PushConfiguration($this->webzine);
        $this->view->canSendPush = false;
        if ($pushConfig->canSendPush()) {
            $this->view->canSendPush = true;
        }
    }

    public function orderTrackingEditAction($pushType)
    {
        $this->view->subtitle = "";

        if (strtoupper($pushType) == PushTemplate::PUSH_ORDER_FULFILLED && ($this->acl->isAddonEnable("localpickup")
            || $this->acl->isAddonEnable("localdelivery"))) {

            $this->view->title = $this->translater->get("GBCOMMERCE_MAIL_ORDER_FULFILLED_PICKUP_LABEL");
        } else {
            $this->view->title = $this->translater->getStaticOnEmpty(NotificationTemplateTabler::$TABLE_ROW_CONFIG[strtoupper($pushType)]["label"]);
        }
        $this->view->backUrl = $this->url->getUrl("commerce/push/ordertracking/");

        /**
         * Ckeck acl
         */
        if (
            (strtoupper($pushType) == PushTemplate::PUSH_ORDER_FULFILLED_LOCAL && !$this->acl->isAddonEnable("localdelivery")) ||
            (strtoupper($pushType) == PushTemplate::PUSH_ORDER_RECOVERED && !$this->acl->isAllowed("commerceabandoned", "edit")) ||
            (strtoupper($pushType) == PushTemplate::PUSH_ORDER_FULFILLED_PICKUP && !$this->acl->isAddonEnable("localpickup")) ||
            (strtoupper($pushType) == PushTemplate::PUSH_ORDER_OFFLINE_PAYMENT && !PaymentServicesManager::create($this->webzine)->isMercadoConnected())
        ) {
            return $this->response->redirect($this->url->getUrl("commerce/push/ordertracking/"));
        }

        /**
         * Fetching the template
         */
        $pushTemplate = $this->notificationTemplateApi->getTemplate($pushType);

        /**
         * We check now if the template belongs to the customized templates list fetched from the API
         * This is the only way to determine if the push template is a custom one to choose if we have to create or update one
         */
        $templateIsCustom = false;
        $customizedTemplates = $this->notificationTemplateApi->getAll();

        if (!empty($customizedTemplates)) {
            foreach ($customizedTemplates as $template) {
                if ($template->push_type == $pushTemplate->push_type) {
                    $templateIsCustom = true;
                }
            }
        }

        $form = new \Common\Lib\Goodbarber\Commerce\Push\OrderTracking\Form($pushTemplate, ["push_type" => $pushType]);
        $tokens = $this->notificationTemplateApi->getTemplateTokens($pushType);

        if (!empty($tokens)) {
            $this->view->tokens = $tokens;
        }
        $this->view->form = $form;
        $this->view->pushType = $pushType;

        if ($this->request->isPost()) {
            if ($form->isValid($this->request->getPost())) {
                $data = [
                    "message" => $this->request->getPost("message", "striptags"),
                    "title" => $this->request->getPost("title", "striptags"),
                    "push_type" => strtoupper($pushType)
                ];

                $this->commerceApiErrorHandler->showRedMessageAfterRedirect("notificationTemplate:onSaveTemplateFailed");

                /**
                 * If template is a default one, we choose the creation method, otherwise update
                 */
                if (empty($pushTemplate) || !$templateIsCustom) {
                    $success = $this->notificationTemplateApi->createTemplate($pushType, $data);
                } else {
                    $success = $this->notificationTemplateApi->updateTemplate($pushType, $data);
                }

                // If error, let the errorHandler do
                if (!$success) {
                    return;
                }

                $this->flashSession->success($this->translater->get("EDIT_SUCCESSFUL"));

                return $this->response->redirect($this->url->getRedirectUrl("commerce/push/ordertracking/" . $pushType . "/edit/"));
            }
        }
    }

    public function orderTrackingResetAction($pushType)
    {
        $this->view->disable();

        $this->commerceApiErrorHandler->showRedMessageAfterRedirect("notificationTemplate:onDeleteTemplateFailed");

        $this->notificationTemplateApi->deleteTemplate($pushType);

        return $this->response->redirect($this->url->getRedirectUrl("commerce/push/ordertracking/" . $pushType . "/edit/"));
    }
}

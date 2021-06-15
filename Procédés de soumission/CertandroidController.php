<?php

namespace Engine\GBBackOffice\V4\Controllers;

use Common\Lib\FileDownload;
use \Common\Lib\Goodbarber\Compilation, \Common\Lib\Goodbarber\Submission;

use Engine\GBBackOffice\Controllers\CertController;
use Engine\GBBackOffice\V4\Lib\Compilation\AndroidForm;

class CertandroidController extends CertController
{
    public $keytool;

    public function beforeExecuteRoute($dispatcher)
    {
        $this->view->platform = $this->platform = "android";
        $this->keytool = "/usr/bin/keytool";//($this->config->dev ? "/usr/bin/keytool" : "/usr/lib/jvm/java-6-sun/jre/bin/keytool");
        if (parent::beforeExecuteRoute($dispatcher) === false) {
            return false;
        }
    }

    public function indexAction()
    {
        // On recupere l'id project dans le fichier services.json
        $serviceJsonPath = $this->webzine->getFile("apps/cert/android/google-services.json");
        $serviceJson = '';

        if ($this->file->file_exists($serviceJsonPath)) {
            $serviceJson = $this->file->file_get_contents($serviceJsonPath);
        }

        $jsonconf = json_decode($serviceJson);
        $projectId = (isset($jsonconf->project_info->project_id) ? $jsonconf->project_info->project_id : '');

        $tabVal["push"] = array(str_replace("[PLATFORM]", "Google", $this->translater->get("APPS_CERT_118")), $this->paramsManager->get($this->platform, "compilation/gcm/apiKey"));
        $tabVal["firebasetoken"] = array(str_replace("[PLATFORM]", "Google", $this->translater->get("APPS_CERT_FIREBASE_1")), $this->paramsManager->get($this->platform, "compilation/firebase/serverKey"));
        $tabVal["firebaseproject"] = array($this->translater->get("APPS_CERT_FIREBASE_2"), $projectId);
        $tabVal["serviceJson"] = array($this->translater->get("APPS_CERT_FIREBASE_3"), $this->translater->get("APPS_CERT_FIREBASE_4"), (!empty($serviceJson) ? 'file_exists' : ''));

        $tabVal["apikey"] = array(str_replace(array("[PLATFORM]", "[PLATFORM2]"), array("Google", "Android"), $this->translater->get("APPS_CERT_119")), $this->paramsManager->get($this->platform, "compilation/gmapApiKey"));
        $tabVal["sha1"] = array($this->translater->get("APPS_CERT_120"), $this->paramsManager->get($this->platform, "compilation/keystore/alias/sha1"));
        $tabVal["packagename"] = array($this->translater->get("APPS_CERT_121"), $this->paramsManager->get($this->platform, "compilation/packageName"));
        $tabVal["senderid"] = array(str_replace("[PLATFORM]", "Google", $this->translater->get("APPS_CERT_122")), $this->paramsManager->get($this->platform, "compilation/gcm/senderId"));

        // Pour les apps du reseller 431 - Kingdom, Inc.
        // On affiche le lien de téléchargement du keystore de l'app
        if ($this->acl->isKingdom()) {
            $tabVal["keystorelink"] = $this->url->getUrl("export/downloadKeystore/");
        }

        $keyhash = $this->paramsManager->get("android", "compilation/keystore/alias/fbHash");
        if (!isset($keyhash)) {
            $this->generateGBKeyHash();
        }
        $tabVal["keyhash"] = array($this->translater->get("APPS_CERT_FACEBOOK_HASH"), $keyhash);

        $this->view->title = $this->translater->get("APPS_CERT_117");
        $this->view->tabVal = $tabVal;
        $this->view->hasJson = $serviceJson;
    }

    private function generateGBKeyHash()
    {
        $path_keytool = "/usr/lib/jvm/java-6-sun/jre/bin/keytool";
        $path_keystore = $this->webzine->getPath("apps", "cert/android", true) . "gb.keystore";

        if (!$this->file->file_exists($path_keystore)) {
            exec("$path_keytool -genkeypair -dname \"CN=GoodBarber,OU=GoodBarber,O=GoodBarber,L=Ajaccio,S=Corsica,C=FR\" -v -keystore $path_keystore -keyalg RSA -keysize 2048 -validity 10000 -alias gbkey -storepass duoapps -keypass duoapps", $output, $retval);
            if ($retval == 0) {
                $this->paramsManager->set("android", "compilation/keystore/password", "duoapps", "compilation", 0, 0, 0);
                $this->paramsManager->set("android", "compilation/keystore/alias/name", "gbkey", "compilation", 0, 0, 0);
                $this->paramsManager->set("android", "compilation/keystore/alias/password", "duoapps", "compilation", 0, 0, 0);
            }
        }

        exec(sprintf("$path_keytool -exportcert -keystore %s -alias %s | openssl sha1 -binary | openssl base64", $path_keystore, $this->paramsManager->get("android", "compilation/keystore/alias/name")), $output, $retval);
        if ($retval == 0) {
            $sha1 = $output[0];
            $this->paramsManager->set("android", "compilation/keystore/alias/fbHash", $sha1, "compilation", 0, 0, 0);
        }
    }

    public function step0Action()
    {
        $HAS_KEYSTORE = $this->paramsManager->get($this->platform, "compilation/appUpdate");

        if ($this->request->isPost()) {
            $HAS_APP = $this->request->getPost("hasapp", "int");

            // On ne supprime plus le mot de passe pour eviter la regénération d'un keystore si le clien repasse en 1er soumission et simple update
            //if (isset($HAS_KEYSTORE) && $HAS_APP != $HAS_KEYSTORE)       $this->paramsManager->set($this->platform, "compilation/keystore/password", "", "compilation", 0, 0, 0);

            $this->paramsManager->set($this->platform, "compilation/appUpdate", $HAS_APP, "compilation", 0, 0, 0);
            $this->nextStep();

            /**
             * Si nouvelle APP => On avance en etape 2 (solo et GB)
             */
            if (empty($HAS_APP)) {
                $stop_at = 2;
                $success = true;
                while ($this->step < $stop_at && $success === true) {
                    $success = $this->{"step" . $this->step . "Process"}(true);
                }
            }

            return $this->response->redirect($this->url->getRedirectRefreshUrl());
        }

        $this->tag->setDefault('hasapp', intval($HAS_KEYSTORE));
    }

    public function step1Action()
    {
        if ($this->request->isAjax()) {
            $this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
        }

        $this->view->HAS_KEYSTORE = $HAS_KEYSTORE = $this->paramsManager->get($this->platform, "compilation/appUpdate");

        if ($this->request->isPost() && !$this->request->isAjax()) {
            if ($this->step1Process()) {
                return $this->response->redirect($this->url->getRedirectRefreshUrl());
            }
        }

        if (!empty($HAS_KEYSTORE)) {
            $storepass = $this->paramsManager->get($this->platform, "compilation/keystore/password");

            if (!$this->request->isPost() || $this->request->isAjax()) {
                $this->tag->setDefault('storepass', $storepass);
            }

            /**
             * Si le Keystore existe et qu'on a un mot de passe on tente de le lire
             */
            $keystoreFile = $this->webzine->getFile("apps/cert/android/gb.keystore");
            if ($this->file->file_exists($keystoreFile) && !empty($storepass)) {
                exec(sprintf($this->keytool . " -list -keystore " . $keystoreFile . " -storepass %s", escapeshellarg($storepass)), $output, $retval);
                $this->view->keystoreExists = true;

                /**
                 * Si je peux l'ouvrir, on liste les différents clés qu'il contient
                 */
                if ($retval == 0) {
                    $tmp = preg_grep("/PrivateKeyEntry,$/is", $output);
                    $aliases = array();
                    if (!empty($tmp)) {
                        foreach ($tmp as $alias) {
                            $aliasname = preg_replace("/^([^,]+),(.*)/is", "\\1", $alias);
                            $aliases[$aliasname] = $aliasname;
                        }

                        $this->view->aliases = $aliases;
                    }

                    if (!$this->request->isPost() || $this->request->isAjax()) {
                        $this->tag->setDefault('keypass', $this->paramsManager->get($this->platform, "compilation/keystore/alias/password"));
                    }
                }
            }
        }
    }

    private function step1Process($isSilent = false)
    {

        $path = $this->webzine->getPath("apps", "cert/android", true);
        $HAS_KEYSTORE = $this->paramsManager->get($this->platform, "compilation/appUpdate");
        $path = $path . "gb.keystore";

        foreach (array("storepass", "keyalias", "keypass") as $tmp)
            $$tmp = $this->request->getPost($tmp, "striptags");


        /**
         * Je suis une mise à jour d'application
         */
        if (!empty($HAS_KEYSTORE)) {
            /**
             * Si on envoie un keystore, on essaie de l'ouvrir pour vérifier le mot de passe
             */
            if (!empty($_FILES["keystore"]["tmp_name"])) {
                if (empty($storepass) || mb_strlen($storepass) < 6) {
                    $SESS_error = $this->translater->get("BAD_PASSWORD");
                } else {
                    $this->paramsManager->delete($this->platform, "compilation/keystore/alias/name");

                    exec(sprintf($this->keytool . " -list -keystore " . $_FILES["keystore"]["tmp_name"] . " -storepass %s", escapeshellarg($storepass)), $output, $retval);
                    if ($retval == 0) {
                        $aliases = preg_grep("/PrivateKeyEntry,$/is", $output);

                        /**
                         * Si on arrive a retrouver les clés, c'est bon
                         */
                        if (!empty($aliases)) {
                            // On conserve toujours une copie du précédent keystore
                            if ($this->file->file_exists($path)) {
                                $this->backupCertif($path);
                            }

                            $this->file->copy($_FILES["keystore"]["tmp_name"], $path);
                            $this->paramsManager->set($this->platform, "compilation/keystore/password", $storepass, "compilation", 0, 0, 0);
                            $RELOAD = true;
                        } else {
                            $SESS_error = $this->translater->get("APPS_CERT_98");
                        }

                    } else {
                        $SESS_error = $this->translater->get("BAD_PASSWORD");
                    }
                }
            } elseif ($this->file->is_file($path) && !empty($keyalias)) {
                /**
                 * Si on a deja le keystore, on essaie de changer le mot de passe pour vérifier que tout est toujours bon
                 */
                exec(sprintf($this->keytool . " -keystore %s -storepass %s -keypasswd -alias %s -keypass %s -new %s", $path, escapeshellarg($this->paramsManager->get($this->platform, "compilation/keystore/password")), escapeshellarg($keyalias), escapeshellarg($keypass), escapeshellarg($keypass)), $output, $retval);

                if (empty($keypass) || $retval != 0) {
                    $SESS_error = $this->translater->get("BAD_PASSWORD");
                } else {
                    $this->paramsManager->set($this->platform, "compilation/keystore/alias/name", $keyalias, "compilation", 0, 0, 0);
                    $this->paramsManager->set($this->platform, "compilation/keystore/alias/password", $keypass, "compilation", 0, 0, 0);
                }
            }

            /**
             * Je suis une nouvelle application
             */
        } else {
            /**
             * Si on manque d'infos, on supprime le keystore et on en créé un nouveau
             */
            $ksp = $this->paramsManager->get($this->platform, "compilation/keystore/password");
            $kssha = $this->paramsManager->get($this->platform, "compilation/keystore/alias/sha1");
            if ($this->file->is_file($path) && (empty($ksp) || empty($kssha))) {
                $this->file->unlink($path);
            }

            if (!$this->file->is_file($path)) {
                exec($this->keytool . " -genkeypair -dname \"CN=GoodBarber,OU=GoodBarber,O=GoodBarber,L=Ajaccio,S=Corsica,C=FR\" -v -keystore $path -keyalg RSA -keysize 2048 -validity 10000 -alias gbkey -storepass duoapps -keypass duoapps", $output, $retval);
                if ($retval == 0) {
                    $this->paramsManager->set($this->platform, "compilation/keystore/password", "duoapps", "compilation", 0, 0, 0);
                    $this->paramsManager->set($this->platform, "compilation/keystore/alias/name", "gbkey", "compilation", 0, 0, 0);
                    $this->paramsManager->set($this->platform, "compilation/keystore/alias/password", "duoapps", "compilation", 0, 0, 0);
                } else {
                    $SESS_error = $this->translater->get("APPS_CERT_98");
                }
            }
        }

        /**
         * A ce stade on a un keystore, on essaie de l'ouvrir pour stocker l'alias et le hash Facebook
         */
        $keyalias = $this->paramsManager->get($this->platform, "compilation/keystore/alias/name");
        if ($this->file->is_file($path) && empty($SESS_error) && !empty($keyalias)) {
            exec(sprintf($this->keytool . " -list -v -keystore %s -storepass %s -alias %s", $path, escapeshellarg($this->paramsManager->get($this->platform, "compilation/keystore/password")), escapeshellarg($keyalias)), $output, $retval);
            if ($retval == 0) {
                $tabsha = preg_grep("/SHA1[^:]*:/is", $output);
                sort($tabsha);
                $sha1 = trim(preg_replace("/SHA1[^:]*: ([A-F0-9:]+)/is", "\\1", $tabsha[0]));

                // En cas d'ecrasement du keystore, on stocke la précedente valeur
                $oldKeystore = $this->paramsManager->get($this->platform, "compilation/keystore/alias/sha1");
                if (!empty($oldKeystore) && $sha1 != $oldKeystore) {
                    $this->paramsManager->set($this->platform, "compilation/keystore/alias/sha1old", $oldKeystore, "compilation", 0, 0, 0);
                }

                $this->paramsManager->set($this->platform, "compilation/keystore/alias/sha1", $sha1, "compilation", 0, 0, 0);
                exec(sprintf($this->keytool . " -exportcert -keystore %s -alias %s | openssl sha1 -binary | openssl base64", $path, escapeshellarg($this->paramsManager->get($this->platform, "compilation/keystore/alias/name"))), $fboutput, $fbretval);
                if ($fbretval == 0) {
                    $sha1 = $fboutput[0];
                    $this->paramsManager->set("android", "compilation/keystore/alias/fbHash", $sha1, "compilation", 0, 0, 0);
                }
            } else {
                $SESS_error = $this->translater->get("APPS_CERT_98");
            }
        } elseif (!isset($RELOAD)) {
            $SESS_error = $this->translater->get("APPS_CERT_98");
        }

        if (empty($SESS_error)) {
            if (!isset($RELOAD)) $this->nextStep();
            return true;
        } else {
            if (!empty($SESS_error)) $this->flash->error($SESS_error);
            return false;
        }

    }

    public function step2Action()
    {
        // On vérifie si un package name existe deja
        $package = $this->paramsManager->get($this->platform, "compilation/packageName");
        if (!isset($package)) {
            $package = $this->getDefaultPackageName();
        }

        /**
         * Si je suis en soumission GB, on va sur le formulaire d'infos
         */
        if ($this->submissionMode == "gb") {
            // On mémorise le package name en passant
            if (!empty($this->request->getPost("packagename", "striptags")))
                $package = $this->request->getPost("packagename", "striptags");
            if (!empty($package))
                $this->paramsManager->set($this->platform, "compilation/packageName", $package, "compilation", 0, 0, 0);
            return $this->dispatcher->forward(array("action" => "infos", "params" => array("certProcess" => true)));
        }

        if ($this->request->isPost()) {
            if ($this->step2Process()) {
                return $this->response->redirect($this->url->getRedirectRefreshUrl());
            }


        }

        $this->view->HAS_KEYSTORE = $this->paramsManager->get($this->platform, "compilation/appUpdate");

        $this->tag->setDefault('package', $package);
        $this->view->packageName = $package;
    }

    /**
     * Retourne le package name par défaut : celui de l'agence s'il existe, sinon com.goodbarber...
     * @return string packagename
     */
    private function getDefaultPackageName()
    {
        if ($this->webzine->isWhiteLabel(true) && !empty($this->webzine->getWhiteLabelAgency()) && !empty($this->webzine->getWhiteLabelAgency()->getAttrib('packageName')))
            $partialName = $this->webzine->getWhiteLabelAgency()->getAttrib('packageName');
        else
            $partialName = "com.goodbarber";

        return $partialName . "." . Compilation::cleanGbIdentifiant($this->webzine->identifiant, true);

    }

    private function step2Process($isSilent = false)
    {
        $HAS_KEYSTORE = $this->paramsManager->get($this->platform, "compilation/appUpdate");

        /**
         * Package Name
         * si on update on verifie le format [0-9a-zA-Z\._]
         * en mode silencieux, on n'ecrase pas la valeur du packagename
         */
        if (empty($HAS_KEYSTORE) && $isSilent) {
            $package = $this->paramsManager->get($this->platform, "compilation/packageName");
            if (empty($package)) {
                $package = $this->getDefaultPackageName();
            }
        } else {
            $package = $this->request->getPost("package", "striptags");

            if (!empty($HAS_KEYSTORE) && Submission::getCurrentVersion($this->webzine, $this->platform) == 0) {
                $version = preg_replace("/([^0-9]+)/", "", $this->request->getPost("version", "striptags"));
                if (!empty($version) && is_numeric($version)) {
                    Submission::setInitialVersion($this->webzine, $this->platform, ($version + 1));
                } else {
                    return false;
                }
            }
        }

        if (!empty($package) && !preg_match("/[^0-9a-zA-Z\._]/", $package)) {
            $this->paramsManager->set($this->platform, "compilation/packageName", $package, "compilation", 0, 0, 0);
            $this->nextStep();

            return true;
        } else {
            return false;
        }
    }

    public function step3Action()
    {
        if ($this->request->isPost()) {
            if ($this->step3Process()) {
                return $this->response->redirect($this->url->getRedirectRefreshUrl());
            } else {
                $this->view->inlineJs .= "$.setHash('step-3-1');\n";
            }
        } else {
            $this->tag->setDefault('senderid', $this->paramsManager->get($this->platform, "compilation/gcm/senderId"));
        }
    }

    private function step3Process($isSilent = false)
    {
        $senderId = $this->request->getPost("senderid", "alphanum");

        if (!empty($senderId) && is_numeric($senderId)) {
            $this->paramsManager->set($this->platform, "compilation/gcm/senderId", $senderId, "compilation", 0, 0, 0);
            $this->nextStep();

            return true;
        } else {
            return false;
        }
    }

    public function step4Action()
    {
        if ($this->request->isPost()) {
            $this->nextStep();
            return $this->response->redirect($this->url->getRedirectRefreshUrl());
        }
    }


    public function step5Action()
    {
        $this->view->erreurApi = "";
        if ($this->request->isPost()) {
            if ($this->step5Process()) {
                return $this->response->redirect($this->url->getRedirectRefreshUrl());
            } else {
                $this->view->error = array("apikey" => 1);
                $this->view->inlineJs .= "$.setHash('step-5-2');";
                // Erreur JS (+ modal) si clés api identiques
                if ($this->isKeyDuplicate($this->request->getPost("apikey", "striptags")))
                    $this->view->erreurApi = $this->ui->popoverAlertJS(array(
                        "content" => nl2br($this->translater->get("APPS_CERT_135")),
                        "noButton" => true
                    ));
            }
        } else {
            $this->tag->setDefault('pname', $this->paramsManager->get($this->platform, "compilation/packageName"));
            $this->tag->setDefault('sha1', $this->paramsManager->get($this->platform, "compilation/keystore/alias/sha1"));
            $this->tag->setDefault('apikey', $this->paramsManager->get($this->platform, "compilation/gmapApiKey"));
        }
    }

    private function step5Process($isSilent = false)
    {
        $apikey = $this->request->getPost("apikey", "striptags");
        if (!empty($apikey) && !$this->isKeyDuplicate($apikey)) {
            $this->paramsManager->set($this->platform, "compilation/gmapApiKey", $apikey, "compilation", 0, 0, 0);
            $this->nextStep();
            return true;
        } else {
            return false;
        }
    }

    public function step6Action()
    {
        // On recupere l'id project dans le fichier services.json
        $serviceJsonPath = $this->webzine->getFile("apps/cert/android/google-services.json");
        $this->view->hasJson = $this->file->file_exists($serviceJsonPath);

        if ($this->request->isPost()) {
            if ($this->step6Process()) {
                return $this->response->redirect($this->url->getRedirectRefreshUrl());
            } else {
                if (empty($this->request->getPost("compilation/firebase/serverKey", "striptags"))) {
                    $this->view->inlineJs .= "$.setHash('step-6-2');\n";
                } elseif (!$this->request->hasFiles()) {
                    $this->view->inlineJs .= "$.setHash('step-6-3');\n";
                }
            }
        } else {
            $this->tag->setDefault('pname', $this->paramsManager->get($this->platform, "compilation/packageName"));
            // ApiServerKey préremplie uniquement si pas vide
            if (!empty($this->paramsManager->get($this->platform, "compilation/firebase/serverKey"))) {
                $this->tag->setDefault('serverapikey', $this->paramsManager->get($this->platform, "compilation/firebase/serverKey"));
            }
        }
    }

    private function step6Process($isSilent = false)
    {
        if ($this->request->hasFiles() == true) {
            $this->saveGoogleServiceFile();
        }

        $serverapikey = $this->request->getPost("serverapikey", "striptags");
        // Si un fichier google-services.json existe deja, pas obligatoire de le renvoyer
        if (!empty($serverapikey) && (!empty($jsonKey) && !empty($jsonFile) || $this->file->file_exists($this->webzine->getFile("apps/cert/android/google-services.json")))) {
            // Stockage api server key en bdd

            $this->paramsManager->set($this->platform, "compilation/firebase/serverKey", $serverapikey, "compilation", 0, 0, 0);

            $this->nextStep();
            return true;
        } else {
            return false;
        }
    }

    private function _getPackageName($client)
    {
        return $client['client_info']["android_client_info"]['package_name'];
    }

    private function saveGoogleServiceFile()
    {
        $files = $this->request->getUploadedFiles(true);
        if (!empty($files[0]) && !empty($files[0]->getTempName())) {
            $jsonFile = $files[0]->getTempName();
            $jsonconf = json_decode(file_get_contents($jsonFile), true);
            $jsonKey = $jsonconf['client'][0]['api_key'][0]['current_key'];
            $packageNames = array_map([$this, "_getPackageName"], $jsonconf['client']);
            $paramPackageName = $this->paramsManager->get($this->platform, "compilation/packageName");

            if (empty($packageNames) || !in_array($paramPackageName, $packageNames)) {
                $this->view->paramPackageName = $paramPackageName;
                $this->view->hasJsonError = true;
                $this->view->inlineJsFooter .= "scrollToSubsection('#package-name-submission-error-wrapper');\n";
            } else {
                $this->view->hasJsonError = false;
            }
        }

        // Stockage fichier google-services.json (le fichier peut etre vide si deja envoyé précédement
        if (!empty($jsonKey) && !empty($jsonFile) && in_array($paramPackageName, $packageNames)) {
            $this->file->copy($jsonFile, $this->webzine->getFile("apps/cert/android/google-services.json"));
        }
    }


    /**
     * Compare la clé key android et la clé key Server saisie précédemment
     * @param  [string]  $apiAndoid
     * @return boolean true si les clés sont identiques
     */
    private function isKeyDuplicate($apiAndroid)
    {
        $apiServer = $this->paramsManager->get($this->platform, "compilation/gcm/apiKey");
        if ($apiAndroid == $apiServer)
            return true;
        return false;
    }


    public function infosAction()
    {
        $this->form = new AndroidForm(null, array("submission" => $this->submission));

        $configArray = $this->ui->getImageFromObjet($this->webzine, array("objet" => "compilation/images/Icon", "platform" => "android"));
        $this->view->appIconUrl = $configArray["url"];

        $urlDesignIcon = $this->url->getUrl("app/icon/");
        $submission = Submission::getCurrent($this->webzine, "android");
        $this->view->textAppIcon = str_replace(
            ["[VERSION]", "[LINKDESIGNICON]"],
            [$submission->getFormattedVersion(), "<a href=\"$urlDesignIcon\" target=\"_blank\">" . $this->translater->get("APPS_CERT_GENERAL_DESIGN") . "</a>"],
            $this->translater->get("APPS_CERT_ICON_GOOGLE_PLAY")
        );

        $this->view->packageName = $this->_getCorrectPackageName();

        return parent::infosAction();
    }

    public function downloadJsonAction()
    {
        $fileDl = FileDownload::createFromFilePath($this->webzine->getFile("apps/cert/android/google-services.json"));
        $fileDl->sendDownload("google-services.json");
    }

    /**
     * Renvoie le bon package name
     * @return boolean true si les clés sont identiques
     */
    private function _getCorrectPackageName()
    {
        $package = $this->paramsManager->get($this->platform, "compilation/packageName");

        if (!isset($package)) {
            $package = $this->getDefaultPackageName();
        }

        if (!empty($this->request->getPost("packageName"))) {
            $package = $this->request->getPost("packageName");
        }

        if ($this->request->hasPost("packageName")) {
            if (!empty($package) && !preg_match("/[^0-9a-zA-Z\._]/", $package)) {
                $this->paramsManager->set($this->platform, "compilation/packageName", $package, "compilation", 0, 0, 0);
            }
        }

        return $package;
    }
}

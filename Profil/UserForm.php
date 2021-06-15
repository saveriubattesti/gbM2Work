<?php

namespace Common\Lib\Goodbarber\Users\User;

use Common\Lib\Date;
use \Phalcon\Forms\Form;
use \Phalcon\Forms\Element\Text;
use \Phalcon\Forms\Element\Password;
use \Phalcon\Forms\Element\Select;
use Phalcon\Validation\Validator\Callback;
use \Phalcon\Validation\Validator\Email as EmailValidator;
use \Phalcon\Validation\Validator\PresenceOf;
use \Phalcon\Validation\Validator\Confirmation;
use \Phalcon\Validation\Validator\StringLength;
use \Phalcon\Validation\Validator\Url as URLValidator;
use \Common\Validators\Password as PasswordValidator;
use \Common\Validators\Numeric as NumericValidator;
use \Common\Validators\Telephone as TelephoneValidator;
use \Common\Lib\Goodbarber\Users\UserProfileSettingsManager;
use \Common\Forms\Elements\DatePicker;

class UserForm extends Form
{
    public function initialize($entity = null, $options = null)
    {
        $this->setEntity($entity);

        $this->setAction($this->url->getUrl((empty($entity) ? "users/add" : "users/" . $entity->userId . "/edit/")));

        /**
         * Login
         * @var Text
         */
        $login = new Text("login", ["class" => "form-control input-md control-notempty", "maxlength" => 320]);
        $login->setLabel($this->translater->get("LOGIN") . "*")
            ->addFilter("striptags")
            ->addValidator(new PresenceOf([
                'cancelOnFail' => true,
                'message' => $this->translater->get("SEND_OBLIGATOIRE", null, "Langage")
            ]))
            ->addValidator(new StringLength([
                'min' => 3,
                'max' => 320,
                'messageMinimum' => str_replace("[X]", 3, $this->translater->get("PAGE2_FORMULAIRE_5", null, "Langage")),
                'messageMaximum' => str_replace("[X]", 320, $this->translater->get("PAGE2_FORMULAIRE_6", null, "Langage"))
            ]));

        $this->add($login);

        /**
         * Email
         * @var Text
         */
        // Si c'est un user inscrit via facebook/apple et qu'il na pas d'email, l'email n'est pas obligatoire
        $emailMandatory = true;
        if (!empty($entity->attribs) && (!empty($entity->attribs->facebook) || !empty($entity->attribs->apple)) && empty($entity->attribs->email)) {
            $emailMandatory = false;
        }

        $email = new Text("email", ['class' => 'form-control input-md control-email ' . ($emailMandatory ? "control-notempty" : "allow-empty")]);
        $email->setLabel($this->translater->get("FORM_lbl_email", null, "Langage") . ($emailMandatory ? "*" : ""))
            ->addFilter("email");

        if ($emailMandatory) {
            $email->addValidator(new EmailValidator([
                "message" => $this->translater->get("PAGE2_SOUMETTRE_24", null, "Langage")
            ]));
        }
        if (!empty($entity->attribs->email)) $email->setDefault($entity->attribs->email);

        $this->add($email);

        if (empty($entity) || (isset($entity->hasPassword) && !$entity->hasPassword)) {
            /**
             * Password
             * @var Password
             */
            $password = new Password('password', ['class' => 'form-control input-md control-notempty', "maxlength" => 30]);

            $password->setLabel($this->translater->get("PASSWORD") . "*")
                ->addFilter("striptags")
                ->addValidator(new PasswordValidator([
                    'cancelOnFail' => true,
                    'message' => $this->translater->get("ERROR_PASSWORD_3")
                ]))
                ->addValidator(new Confirmation([
                    "message" => $this->translater->get("ERROR_PASSWORD_2"),
                    "with" => "confirmpassword"
                ]));

            $this->add($password);

            /**
             * Confirmation Password
             * @var Password
             */
            $confirmpassword = new Password('confirmpassword', ['class' => 'form-control input-md control-notempty ', "maxlength" => 30]);

            $confirmpassword->setLabel($this->translater->get("REPASSWORD") . "*")
                ->addFilter("striptags")
                ->addValidator(new PasswordValidator([
                    'cancelOnFail' => true,
                    'message' => $this->translater->get("ERROR_PASSWORD_3")
                ]))
                ->addValidator(new Confirmation([
                    "message" => $this->translater->get("ERROR_PASSWORD_2"),
                    "with" => "password"
                ]));

            $this->add($confirmpassword);
        }

        /**
         * Groups
         */
        if ($this->acl->isAddonEnable("usergroup")) {
            $userGroups = $this->userGroups->getAllGroups();
            $this->setUserOption("groupAddonEnabled", true);

            if (!empty($userGroups)) {
                $tabGroups = [];
                foreach ($userGroups as $group) {
                    if (!$group->isDefaultGroup) {
                        $tabGroups[$group->id] = $group->label;
                    }
                }

                if (!empty($tabGroups)) {
                    $groups = new Select("groups[]", $tabGroups, ["id" => "groups", "class" => "form-control", "multiple" => true]);

                    $groups->setLabel($this->translater->get("NEWSLETTER_ARCHIVE_6"));

                    if (!empty($entity->groups)) {
                        $groups->setDefault($entity->groups);
                    }
                    $this->add($groups);
                }
            }
        }

        /**
         * Add custom user fields
         */
        $userCustomElements = [];

        $userSettingsManager = new UserProfileSettingsManager($this->webzine);
        $fields = $userSettingsManager->getUserSettingsFields();

        foreach ($fields as $field) {
            $options = ['class' => 'form-control input-md'];

            $typeElement = "\\Phalcon\\Forms\\Element\\Text";

            /**
             * Class options
             */
            if ($field["type"] == "GBUserFieldMail") {
                $options["class"] .= " control-email allow-empty";
            }

            if ($field["type"] == "GBUserFieldNumber") {
                $options["class"] .= " control-numeric allow-empty";
            }

            if ($field["type"] == "GBUserFieldLink") {
                $options["class"].= " control-url allow-empty";
                $options["placeholder"] = $this->translater->getStatic("https://");
            }

            // Si le field est obligatoire, on ajout le control js et l'etoile sur le label
            if (!empty($field["required"])) {
                // Si on est à l'ajout d'un user ou d'un customer on oblige à remplire les champs required
                if ($this->dispatcher->getActionName() == 'add') {
                    $options["class"] = str_replace("allow-empty", "", $options["class"]);
                    $options["class"] .= " control-notempty";
                }
                $field["name"] .= "*";
            }

            if ($field["type"] == "GBUserFieldParagraph" || $field["type"] == "GBUserFieldDescription") {
                $typeElement = "\\Phalcon\\Forms\\Element\\Textarea";
                $options["rows"] = 4;
            }

            if ($field["type"] == "GBUserFieldDropdown") {
                $typeElement = "\\Phalcon\\Forms\\Element\\Select";
                $options["class"] .= " allowed-zero";
            }

            /**
             * Constructeur spécifique pour un select ou un datePicker
             */
            if ($field["type"] == "GBUserFieldDate") {
                $element = new DatePicker($field["id"], array(
                    "class" => $options["class"],
                    "noDefaultValue" => true
                ));
            } elseif ($field["type"] == "GBUserFieldDropdown") {
                $element = new $typeElement($field["id"], $field["choices"], $options);
            } else {
                $element = new $typeElement($field["id"], $options);
            }


            /**
             * On set la valeur par défaut s'il y en a une
             */
            if (!empty($entity->attribs->intern->{$field["id"]}) && isset($entity->attribs->intern->{$field["id"]}->value) && $entity->attribs->intern->{$field["id"]}->value != "") {
                $element->setDefault($entity->attribs->intern->{$field["id"]}->value);
                // S'il n'y en a pas et que c'est un select on le met manuelement a vide
            } else if ($field["type"] == "GBUserFieldDropdown") {
                $this->view->inlineJsFooter .= '$(function() { $("select#' . $field["id"] . '").select2("val", "");});';
            }

            if ($field["type"] == "GBUserFieldName") {
                if (!empty($entity->name)) $element->setDefault($entity->name);

                $element->setUserOption("attribElement", "displayName");
            }

            if ($field["type"] == "GBUserFieldLocation") {
                if (!empty($entity->location)) $element->setDefault($entity->location);

                $element->setUserOption("attribElement", "location");
            }

            if ($field["type"] == "GBUserFieldDescription") {
                if (!empty($entity->attribs->description)) $element->setDefault($entity->attribs->description);
                $element->setUserOption("attribElement", "description");
            }

            if ($field["type"] == "GBUserFieldDate") {
                if (!empty($entity->attribs->intern->{$field["id"]}->value)) {
                    $localDateStartObj = new Date($entity->attribs->intern->{$field["id"]}->value);

                    $element->setDefault($localDateStartObj->getFullDate());
                    //$element->setDefault($userSettingsManager->formatProfileSettingsDate($localDateStart);
                }
            }


            /**
             * Name + basic filter
             */
            $element->setLabel($field["name"])
                ->addFilter("striptags");

            /**
             * On rajoute des validateurs supplémentaires
             */
            // Si on est à l'ajout d'un user ou d'un customer on oblige à remplire les champs required
            if (!empty($field["required"]) && $this->dispatcher->getActionName() == 'add') {
                $element->addValidator(new PresenceOf([
                    "message" => $this->translater->get("SEND_OBLIGATOIRE", null, "Langage")
                ]));
            }

            if ($field["type"] == "GBUserFieldNumber") {
                $element->addValidator(new NumericValidator([
                    "allowEmpty" => true
                ]));
            }

            if ($field["type"] == "GBUserFieldPhone") {
                $element->addValidator(new TelephoneValidator([
                    "allowEmpty" => true
                ]));
            }

            if ($field["type"] == "GBUserFieldMail") {
                $element->addValidator(new EmailValidator([
                    "message" => $this->translater->get("PAGE2_SOUMETTRE_24", null, "Langage"),
                    "allowEmpty" => true
                ]));
            }

            $element->setUserOption('gbtype', $field["type"]);

            $this->add($element);
            $userCustomElements[] = $field["id"];
        }

        $this->setUserOption('userCustomElements', $userCustomElements);

        /**
         * Social Links
         */
        $userSocialAccounts = [];

        $socials = $userSettingsManager->getUserSocialLink();
        if (!empty($socials)) {
            foreach ($socials as $type => $enable) {
                if ($enable) {
                    if ($type === "whatsapp") {
                        $element = new Text($type, ['class' => 'form-control allow-empty control-phonewithprefix', 'placeholder' => "+"]);
                        $element->addValidator(new Callback([
                            "message" => $this->translater->get("GBUSER_PROFILE_WHATSAPP_ERROR"),
                            "callback" => function($data) {
                                if (!empty($data["whatsapp"]) && !preg_match("#^\+[0-9 ]+$#", $data["whatsapp"])) {
                                    return false;
                                }
                                return true;
                            }
                        ]));
                    } elseif ($type === "snapchat") {
                        $element = new Text($type, ['class' => 'form-control allow-empty', 'placeholder' => $this->translater->get("GBUSER_PROFILE_SNAPCHAT")]);
                    } else {
                        $element = new Text($type, ['class' => 'form-control input-md control-url allow-empty', 'placeholder' => $this->translater->getStatic("http://")]);
                        $element->addValidator(new URLValidator(["message" => $this->translater->get("ERROR_URL", null, "GeLangage"), "allowEmpty" => true]));
                    }

                    $element->addFilter("striptags");

                    /**
                     * On set la valeur par défaut s'il y en a une
                     */
                    if (!empty($entity->attribs->socialAccountsUrl->{$type})) {
                        if ($type === "whatsapp") {
                            $entity->attribs->socialAccountsUrl->{$type} = $this->_removePrefixOfString("https://wa.me/", $entity->attribs->socialAccountsUrl->{$type});
                        }
                        if ($type === "snapchat") {
                            $entity->attribs->socialAccountsUrl->{$type} = $this->_removePrefixOfString("https://www.snapchat.com/add/", $entity->attribs->socialAccountsUrl->{$type});
                        }
                        $element->setDefault($entity->attribs->socialAccountsUrl->{$type});
                    }

                    $this->add($element);
                    $userSocialAccounts[] = $type;
                }
            }
        }
        $this->setUserOption('userSocialAccounts', $userSocialAccounts);
    }

    /**
     * Permet d'afficher le code HTML de l'élément en prenant en comptes la validation
     * @param string $name le name de l'élément
     * @return string le code HTML.
     */
    public function renderDecorated($name)
    {
        $element = $this->get($name);

        $messages = $this->getMessagesFor($element->getName());

        $retu = "";
        if (!empty($element->getLabel())) $retu .= "\t <label>" . $element->getLabel() . "</label>\n";
        $retu .= "<div class=\"form-group" . ($messages->count() > 0 ? " has-feedback has-error nocontrol-label" : "") . "\">\n";
        $retu .= $element->render();

        if ($messages->count() > 0) {
            $retu .= "\t <span class=\"fa fa-warning form-control-feedback\"></span>\n";
            $retu .= "\t <span class=\"help-block\"><small>" . $messages[0] . "</small></span>\n";
        }

        if ($name == "whatsapp") {
            $retu .= "\t <p class=\"error-whatsapp text-danger\"><small>" . $this->translater->get("GBUSER_PROFILE_WHATSAPP_ERROR") . "</small></p>";

        }
        $retu .= "</div>\n";

        return $retu;
    }

    /**
     * Construit le menu select qui sera utilisé pour la phase de mapping des champs
     * @return array
     */
    public function getSelectContentForCsvMapping()
    {
        $noMapping = ["confirmpassword", "groups[]", 'login', 'email', 'password'];

        $selects = [];
        foreach ($this->getElements() as $element) {
            if (!in_array($element->getName(), $noMapping)) {
                if (!in_array($element->getName(), $noMapping)) {
                    $label = $element->getLabel();
                    $name = $element->getName();

                    // TODO pour le moment on ne gere pas les imports social
//                    if (empty($label))
//                        $label = ucfirst($name);

                    if (!empty($label))
                        $selects[$name] = $label;
                }
            }
        }

        return $selects;
    }

    /**
     * Permet d'afficher le code HTML des éléments custom du formulaire
     * @return string le code HTML
     */
    public function renderUserCustomElements()
    {
        $retu = "";
        $elements = $this->getUserOption('userCustomElements');

        if (!empty($elements)) {
            foreach ($elements as $k => $id) {
                if ($k % 2 == 0) {
                    $retu .= "<div class=\"row\">\n";
                }

                $retu .= "<div class=\"col-md-6\">" . $this->renderDecorated($id) . "</div>\n";

                if ($k % 2 == 1) {
                    $retu .= "<div class=\"clearfix\"></div>\n</div>\n";
                }
            }

            if ($k % 2 == 0) {
                $retu .= "<div class=\"clearfix\"></div>\n</div>\n";
            }
        }

        return $retu;
    }

    /**
     * Retourne tous les éléments custom du formulaire
     * @return array Tableau d'objets elements
     */
    public function getUserCustomElements()
    {
        $retu = [];
        $elements = $this->getUserOption('userCustomElements');

        if (!empty($elements)) {
            foreach ($elements as $id) {
                $element = $this->get($id);

                $retu[] = $element;
            }
        }

        return $retu;
    }

    /**
     * Retourne tous les éléments social Accounts du formulair
     * @return array Tableau d'objets elements
     */
    public function getUserSocialAccountsElements()
    {
        $retu = [];
        $socials = $this->getUserOption('userSocialAccounts');

        if (!empty($socials)) {
            foreach ($socials as $id) {
                $element = $this->get($id);

                $retu[] = $element;
            }
        }

        return $retu;
    }

    /**
     * Permet d'enlever un préfixe à une chaîne de caractères
     * @param string $prefix la chaîne à enlever
     * @param string $str la chaîne à modifier
     * @return string la chaîne sans le préfixe
     */
    private function _removePrefixOfString($prefix, $str)
    {
        if (substr($str, 0, strlen($prefix)) === $prefix) {
            $str = substr($str, strlen($prefix));
        }

        return $str;
    }
}

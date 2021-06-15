<?php

namespace Engine\GBBackOffice\V4\Lib\Compilation;

use Common\Lib\Goodbarber\Compilation;
use Common\Lib\Goodbarber\Compilation\Infos\AbstractForm;
use \Common\Lib\Goodbarber\Submission;
use \Phalcon\Forms\Element\TextArea, \Phalcon\Forms\Element\Text, \Phalcon\Forms\Element\Radio, \Phalcon\Forms\Element\Select, \Phalcon\Forms\Element\Check;
use Phalcon\Validation\Validator;
use \Phalcon\Validation\Validator\PresenceOf, \Phalcon\Validation\Validator\StringLength;

class AndroidForm extends AbstractForm
{
    /**
     * Tableau contenant les éléments du formulaire relatifs au rating Android
     * @var array
     */
    protected $ratingInfos = [];
    public $blocNotes = [];
    private $_tabExcludePays = [1, 5, 7, 239, 19, 24, 25, 32, 35, 249, 40, 41, 48, 49, 50, 51, 113, 246, 59, 60, 67, 69, 70, 71, 75, 245, 135, 244, 175, 79, 80, 83, 86, 85, 88, 90, 66, 92, 103, 240, 112, 120, 121, 122, 128, 129, 131, 241, 134, 136, 140, 142, 143, 144, 149, 158, 159, 153, 164, 242, 173, 179, 181, 180, 185, 183, 194, 184, 4, 186, 189, 190, 195, 0, 201, 203, 206, 42, 62, 212, 213, 218, 219, 228, 248, 232, 231];
    // Structure en mode "Reference, news or educational"
    public $tabCatStandard = array(
        [
            "name" => "violence",
            "title" => "GBDROIDFORM_TITLE_1",
            "questions" => [0 => "GB_ANDROID_SUBMIT_RATING_1", 1 => "GB_ANDROID_SUBMIT_RATING_2", 2 => "GB_ANDROID_SUBMIT_RATING_3", 3 => "GB_ANDROID_SUBMIT_RATING_4"],
        ],
        [
            "name" => "sexuality",
            "title" => "GBDROIDFORM_TITLE_2",
            "questions" => [0 => "GB_ANDROID_SUBMIT_RATING_5", 1 => "GB_ANDROID_SUBMIT_RATING_6", 2 => "GB_ANDROID_SUBMIT_RATING_7", 3 => "GB_ANDROID_SUBMIT_RATING_8", 4 => "GB_ANDROID_SUBMIT_RATING_9"],
        ],
        [
            "name" => "language",
            "title" => "GBDROIDFORM_TITLE_4",
            "questions" => [0 => "GB_ANDROID_SUBMIT_RATING_10", 1 => "GB_ANDROID_SUBMIT_RATING_11", 2 => "GB_ANDROID_SUBMIT_RATING_12", 3 => "GB_ANDROID_SUBMIT_RATING_13", 4 => "GB_ANDROID_SUBMIT_RATING_14"],
            "answer" => [0 => "", 1 => "", 2 => "never", 3 => "never", 3 => "never", 4 => "never"]
        ],
        [
            "name" => "controlled_subtance",
            "title" => "GBDROIDFORM_TITLE_5",
            "questions" => [0 => "GB_ANDROID_SUBMIT_RATING_16", 1 => "GB_ANDROID_SUBMIT_RATING_17", 2 => "GB_ANDROID_SUBMIT_RATING_18", 3 => "GB_ANDROID_SUBMIT_RATING_19"],
        ],
        [
            "name" => "miscellaneous",
            "title" => "GBDROIDFORM_TITLE_7",
            "questions" => [0 => "GB_ANDROID_SUBMIT_RATING_20", 1 => "GB_ANDROID_SUBMIT_RATING_21", 2 => "GB_ANDROID_SUBMIT_RATING_22", 3 => "GB_ANDROID_SUBMIT_RATING_23"],
        ],

    );


    // Structure en mode "Entertainment APP"
    public $tabCatEntertainement = array(
        [
            "name" => "violence",
            "title" => "GBDROIDFORM_TITLE_1",
            "questions" => [0 => "GB_ANDROID_SUBMIT_RATING_1", 1 => "GB_ANDROID_SUBMIT_RATING_24", 2 => "GB_ANDROID_SUBMIT_RATING_25", 3 => "GB_ANDROID_SUBMIT_RATING_26",
                4 => "GB_ANDROID_SUBMIT_RATING_27", 5 => "GB_ANDROID_SUBMIT_RATING_28", 6 => "GB_ANDROID_SUBMIT_RATING_35", 7 => "GB_ANDROID_SUBMIT_RATING_36",
                8 => "GB_ANDROID_SUBMIT_RATING_40", 9 => "GB_ANDROID_SUBMIT_RATING_41", 10 => "GB_ANDROID_SUBMIT_RATING_42", 11 => "GB_ANDROID_SUBMIT_RATING_43",
                12 => "GB_ANDROID_SUBMIT_RATING_44", 13 => "GB_ANDROID_SUBMIT_RATING_45", 14 => "GB_ANDROID_SUBMIT_RATING_46", 15 => "GB_ANDROID_SUBMIT_RATING_47",
                16 => "GB_ANDROID_SUBMIT_RATING_48", 17 => "GB_ANDROID_SUBMIT_RATING_49", 18 => "GB_ANDROID_SUBMIT_RATING_50", 19 => "GB_ANDROID_SUBMIT_RATING_51",
                20 => "GB_ANDROID_SUBMIT_RATING_52"]
            ,
            "answer" => [5 => "violence_presented", 6 => "blood_level", 7 => "violence_motivation", 16 => "violence_presented", 17 => "blood_level"],
        ],
        [
            "name" => "fear",
            "title" => "GBDROIDFORM_TITLE_8",
            "questions" => [0 => "GB_ANDROID_SUBMIT_RATING_53", 1 => "GB_ANDROID_SUBMIT_RATING_54", 2 => "GB_ANDROID_SUBMIT_RATING_55"],
            "answer" => [1 => "never", 2 => "never"],
        ],
        [
            "name" => "sexuality",
            "title" => "GBDROIDFORM_TITLE_2",
            "questions" => [0 => "GB_ANDROID_SUBMIT_RATING_5", 1 => "GB_ANDROID_SUBMIT_RATING_56", 2 => "GB_ANDROID_SUBMIT_RATING_57", 3 => "GB_ANDROID_SUBMIT_RATING_58",
                4 => "GB_ANDROID_SUBMIT_RATING_59", 5 => "GB_ANDROID_SUBMIT_RATING_64", 6 => "GB_ANDROID_SUBMIT_RATING_65", 7 => "GB_ANDROID_SUBMIT_RATING_66",
                8 => "GB_ANDROID_SUBMIT_RATING_72", 9 => "GB_ANDROID_SUBMIT_RATING_73", 10 => "GB_ANDROID_SUBMIT_RATING_74", 11 => "GB_ANDROID_SUBMIT_RATING_75",
                12 => "GB_ANDROID_SUBMIT_RATING_76", 13 => "GB_ANDROID_SUBMIT_RATING_77", 14 => "GB_ANDROID_SUBMIT_RATING_78", 15 => "GB_ANDROID_SUBMIT_RATING_79",
                16 => "GB_ANDROID_SUBMIT_RATING_80", 17 => "GB_ANDROID_SUBMIT_RATING_81", 18 => "GB_ANDROID_SUBMIT_RATING_82", 19 => "GB_ANDROID_SUBMIT_RATING_83",
                20 => "GB_ANDROID_SUBMIT_RATING_84"]
            ,
            "answer" => [4 => "sexuality_nudity", 2 => "rarely", 7 => "sexuality_suggestivity", 9 => "rarely", 13 => "never", 14 => "never", 15 => "never",
                16 => "never", 17 => "never"],
            "multiple_answer" => [4 => "true", 7 => true]

        ],
        [
            "name" => "gambling",
            "title" => "GBDROIDFORM_TITLE_11",
            "questions" => [0 => "GB_ANDROID_SUBMIT_RATING_85", 1 => "GB_ANDROID_SUBMIT_RATING_86", 2 => "GB_ANDROID_SUBMIT_RATING_87", 3 => "GB_ANDROID_SUBMIT_RATING_88"]
        ],
        [
            "name" => "language",
            "title" => "GBDROIDFORM_TITLE_4",
            "questions" => [0 => "GB_ANDROID_SUBMIT_RATING_10", 1 => "GB_ANDROID_SUBMIT_RATING_11", 2 => "GB_ANDROID_SUBMIT_RATING_12", 3 => "GB_ANDROID_SUBMIT_RATING_13", 4 => "GB_ANDROID_SUBMIT_RATING_14"],
            "answer" => [0 => "", 1 => "", 2 => "never", 3 => "never", 3 => "never", 4 => "never"]
        ],
        [
            "name" => "controlled_subtance",
            "title" => "GBDROIDFORM_TITLE_5",
            "questions" => [0 => "GB_ANDROID_SUBMIT_RATING_16", 1 => "GB_ANDROID_SUBMIT_RATING_89", 2 => "GB_ANDROID_SUBMIT_RATING_92", 3 => "GB_ANDROID_SUBMIT_RATING_93",
                4 => "GB_ANDROID_SUBMIT_RATING_94", 5 => "GB_ANDROID_SUBMIT_RATING_95", 6 => "GB_ANDROID_SUBMIT_RATING_96", 7 => "GB_ANDROID_SUBMIT_RATING_94",
                8 => "GB_ANDROID_SUBMIT_RATING_97", 9 => "GB_ANDROID_SUBMIT_RATING_98", 10 => "GB_ANDROID_SUBMIT_RATING_94"],
            "answer" => [1 => "drugs_illegal", 3 => "drugs_illegal", 4 => "rarely", 6 => "drugs_illegal", 7 => "rarely", 9 => "drugs_illegal", 10 => "rarely"]
        ],
        [
            "name" => "crude_humor",
            "title" => "GBDROIDFORM_TITLE_12",
            "questions" => [0 => "GB_ANDROID_SUBMIT_RATING_99", 1 => "GB_ANDROID_SUBMIT_RATING_100"],
            "answer" => [1 => "crude_humor"],
            "multiple_answer" => [1 => "true"]
        ],
        [
            "name" => "miscellaneous",
            "title" => "GBDROIDFORM_TITLE_7",
            "questions" => [0 => "GB_ANDROID_SUBMIT_RATING_20", 1 => "GB_ANDROID_SUBMIT_RATING_21", 2 => "GB_ANDROID_SUBMIT_RATING_22", 3 => "GB_ANDROID_SUBMIT_RATING_23"],
        ],
    );

    public $tabOpenLevel3 = ['violence_2', 'violence_13', 'sexuality_2', 'sexuality_7', 'sexuality_9', 'sexuality_13',
        'sexuality_20', 'controlled_subtance_3', 'controlled_subtance_6', 'controlled_subtance_9'];

    public $tabCloseLevel3 = ['violence_12', 'sexuality_6', 'sexuality_8', 'sexuality_12', 'sexuality_19', 'sexuality_21',
        'controlled_subtance_5', 'controlled_subtance_8', 'controlled_subtance_11'];

    public $permissionsDeclaration = [];
    public $packageName;


    public function initialize($entity = null, $options = null)
    {
        $this->_platform = "android";
        parent::initialize($entity, $options);

        $HAS_APP = $this->paramsManager->get($this->_platform, "compilation/appUpdate");

        /**
         * Si la personne fait une MAJ d'un app publiée sur le store
         * et qu'elle réalise sa premiere soumission avec GoodBarber
         * on demande le BundleId et la version actuelle sur le store
         *
         * * S'il a deja publié avec GB : les champs sont en readonly
         *
         * Champ package name dispo tout le temps
         */

        /**
         * Account infos : revovery email
         */
        $recoveryEmail = new Text("recoveryemail", array(
            "class" => "form-control control-email allow-empty",
        ));

        $recoveryEmail->setLabel($this->translater->get("ANDROID_FORM_RECOVERYMAIL"));
        if ($this->request->hasPost("recoveryemail")) {
            $recoveryEmail->setDefault($this->request->getPost("recoveryemail"));
        }
        if (!$this->request->hasPost("recoveryemail")) {
            $default = $this->paramsManager->get($this->_platform, "compilation/store/recoveryemail");
            $recoveryEmail->setDefault($default);
        }

        $this->add($recoveryEmail);
        $this->accountsInfos[] = $recoveryEmail;


        /**
         * Account infos : google play developper name
         */
        $googlePlaydevelopperName = new Text("googleplaydeveloppername", array(
            "class" => "form-control allow-empty",
        ));

        $googlePlaydevelopperName->setLabel($this->translater->get("APPS_INFOS_GOOGLEPLAY_DEVELOPPERNAME") . " " . $this->eventsManager->fire("view:renderLinkOnlineHelp", ["numhelp" => 349, "target" => "_blank"]));
        if ($this->request->hasPost("googleplaydeveloppername")) {
            $googlePlaydevelopperName->setDefault($this->request->getPost("googleplaydeveloppername"));
        }
        if (!$this->request->hasPost("googleplaydeveloppername")) {
            $default = $this->paramsManager->get($this->_platform, "compilation/store/googleplaydeveloppername");
            $googlePlaydevelopperName->setDefault($default);
        }

        $this->add($googlePlaydevelopperName);
        $this->accountsInfos[] = $googlePlaydevelopperName;


        /*
         * Package Name
         */

        $default = $this->paramsManager->get($this->_platform, "compilation/packageName");
        // Bloc update visible si version > 10 ou si on est en mode Update d'app
        $submission = Submission::getCurrent($this->webzine, $this->_platform);
        if (isset($default) && $submission->shortVersion > 10 || !empty($HAS_APP)) {
            $attributes = array("class" => "form-control control-notempty control-packagename");
            // Package name editable uniquement en GoodIp
            if (!\Control::goodIp()) {
                $attributes["readonly"] = "readonly";
            }


            $packagename = new Text("packagename", $attributes);
            $packagename->setLabel($this->translater->get("APPS_CERT_121"))
                ->addValidator(new PresenceOf(array(
                    'message' => $this->translater->get("SEND_OBLIGATOIRE", null, "Langage")
                )));

            if ($this->request->hasPost("packagename")) {
                $packagename->setDefault($this->request->getPost("packagename"));
            }
            if (!$this->request->hasPost("packagename")) {
                $packagename->setDefault($default);
            }
            $this->add($packagename);


            /*
             * Store Version (masqué en mode 1ere soumission)
             */
            $default = $this->paramsManager->get($this->_platform, "compilation/store/currentVersion");
            $attributes = array("class" => "form-control control-notempty");

            // S'il a deja publié avec GB : les champs sont en readonly
            if (!\Control::goodIp() && isset($default) && Submission::getCurrentVersion($this->webzine, $this->_platform) > 0 && !empty($HAS_APP)) {
                $attributes["readonly"] = "readonly";
            }

            $storeVersion = new Text("currentVersion", $attributes);
            $storeVersion->setLabel($this->translater->get("VERSION"))
                ->addValidator(new PresenceOf(array(
                    'message' => $this->translater->get("SEND_OBLIGATOIRE", null, "Langage")
                )))
                ->addValidator(new Validator\Callback(
                    [
                        'callback' => function ($data) {
                            if (intval($data["currentVersion"]) < 10) {
                                return false;
                            }
                            return true;
                        },
                        'message' => nl2br($this->translater->get("GBSUBMISSION_MIN_VERSION"))
                    ]));

            if ($this->request->hasPost("currentVersion")) {
                $storeVersion->setDefault($this->request->getPost("currentVersion"));
            } else {
                $storeVersion->setDefault($default);
            }


            $this->add($storeVersion);
            array_unshift($this->blocUpdate, $packagename, $storeVersion);
        } else {
            $this->remove('new');
        }


        /**
         * Langue
         */

        $tabLang = [
            'af' => 'Afrikaans',
            "am" => 'Amharic',
            "ar" => 'Arabic',
            "hy-AM" => 'Armenian',
            "az-AZ" => 'Azerbaijani',
            "bn-BD" => 'Bangla',
            "eu-ES" => 'Basque',
            "be" => 'Belarusian',
            "bg" => 'Bulgarian',
            "my-MM" => 'Burmese',
            "ca" => 'Catalan',
            "zh-HK" => 'Chinese (Hong Kong)',
            "zh-CN" => 'Chinese (Simplified)',
            "zh-TW" => 'Chinese (Traditional)',
            "hr" => 'Croatian',
            "cs-CZ" => 'Czech',
            "da-DK" => 'Danish',
            "nl-NL" => 'Dutch',
            "en-IN" => 'English – en-IN',
            "en-SG" => 'English - en-SG',
            "en-ZA" => 'English - en-ZA',
            "en-AU" => 'English (Australia)',
            "en-CA" => 'English (Canada)',
            "en-GB" => 'English (United Kingdom)',
            "en-US" => 'English (United States)',
            "et" => 'Estonian',
            "fil" => 'Filipino',
            "fi-FI" => 'Finnish',
            "fr-FR" => 'French',
            "fr-CA" => 'French (Canada)',
            "gl-ES" => 'Galician',
            "ka-GE" => 'Georgian',
            "de-DE" => 'German',
            "el-GR" => 'Greek',
            "iw-IL" => 'Hebrew',
            "hi-IN" => 'Hindi',
            "hu-HU" => 'Hungarian',
            "is-IS" => 'Icelandic',
            "id" => 'Indonesian',
            "it-IT" => 'Italian',
            "ja-JP" => 'Japanese',
            "kn-IN" => 'Kannada',
            "kk" => 'Kazakh',
            "km-KH" => 'Khmer',
            "ko-KR" => 'Korean (South Korea)',
            "ky-KG" => 'Kyrgyz',
            "lo-LA" => 'Lao',
            "lv" => 'Latvian',
            "lt" => 'Lithuanian',
            "mk-MK" => 'Macedonian',
            "ms" => 'Malay',
            "ms-MY" => 'Malay (Malaysia)',
            "ml-IN" => 'Malayalam',
            "mr-IN" => 'Marathi',
            "mn-MN" => 'Mongolian',
            "ne-NP" => 'Nepali',
            "no-NO" => 'Norwegian',
            "fa" => 'Persian - fa',
            "fa-AE" => 'Persian - fa-AE',
            "fa-AF" => 'Persian - fa-AF',
            "fa-IR" => 'Persian - fa-IR',
            "pl-PL" => 'Polish',
            "pt-BR" => 'Portuguese (Brazil)',
            "pt-PT" => 'Portuguese (Portugal)',
            "pa" => 'Punjabi',
            "ro" => 'Romanian',
            "rm" => 'Romansh',
            "ru-RU" => 'Russian',
            "sr" => 'Serbian',
            "si-LK" => 'Sinhala',
            "sk" => 'Slovak',
            "sl" => 'Slovenian',
            "es-419" => 'Spanish (Latin America)',
            "es-ES" => 'Spanish (Spain)',
            "es-US" => 'Spanish (United States)',
            "sw" => 'Swahili',
            "sv-SE" => 'Swedish',
            "ta-IN" => 'Tamil',
            "te-IN" => 'Telugu',
            "th" => 'Thai',
            "tr-TR" => 'Turkish',
            "uk" => 'Ukrainian',
            "vi" => 'Vietnamese',
            "zu" => 'Zulu'
        ];

        $langOptions = array();
        foreach ($tabLang as $key => $lang) {
            $langOptions[$lang] = $this->translater->getStatic($lang);
        }

        $appLanguage = new Select("appLanguage", $langOptions, array(
            "class" => "form-control",
        ));
        $appLanguage->setLabel($this->translater->get("DESIGN_OPTIONS_10") . "|" . $this->translater->get("APPS_INFOS_37"));

        if ($this->request->hasPost("appLanguage")) {
            $appLanguage->setDefault($this->request->getPost("appLanguage"));
        } else {
            $default = $this->paramsManager->get($this->_platform, "compilation/store/appLanguage");
            if (empty($default)) $default = "English (United Kingdom)";
            $appLanguage->setDefault($default);
        }

        $this->add($appLanguage);
        array_unshift($this->blocAppInfo, $appLanguage);


        /**
         * Description courte (Nouveau nom pour : Promotionnal Text)
         */
        $promoText = new TextArea("promoText", array(
            "class" => "form-control control-notempty display-maxlength",
            "rows" => 2,
            "maxlength" => 80
        ));
        $promoText->setLabel($this->translater->get("APPS_INFOS_SHORT_DESC") . "|")
            ->addValidator(new PresenceOf(array(
                'message' => $this->translater->get("SEND_OBLIGATOIRE", null, "Langage")
            )))
            ->addValidator(new StringLength(array(
                'min' => 0,
                'max' => 80,
                'messageMinimum' => str_replace("[X]", 0, $this->translater->get("PAGE2_FORMULAIRE_5", null, "Langage")),
                'messageMaximum' => str_replace("[X]", 80, $this->translater->get("PAGE2_FORMULAIRE_6", null, "Langage"))
            )));

        if ($this->request->hasPost("promoText")) {
            $promoText->setDefault($this->request->getPost("promoText"));
        } else {
            $promoText->setDefault($this->paramsManager->get($this->_platform, "compilation/store/promoText"));
        }

        $this->add($promoText, "description", true);
        // On mets les champs dans el bon ordre (App name, short desc, long desc)
        $this->blocAppInfo[3] = $this->blocAppInfo[2];
        $this->blocAppInfo[2] = $promoText;


        /**
         * Contact Telephone
         */
        $telephone = new Text("contacttelephone", array(
            "class" => "form-control allow-empty control-phonewithprefix",
            "placeholder" => "+"
        ));

        $label = $this->translater->get("AJOUTER_EQUIPE_22") . "<em> (" . $this->translater->get("APPS_INFOS_36") . ")</em>";

        $telephone->setLabel($label);


        if ($this->request->hasPost("contacttelephone")) {
            $telephone->setDefault($this->request->getPost("contacttelephone"));
        } else {
            $default = $this->paramsManager->get($this->_platform, "compilation/store/contacttelephone");
            $telephone->setDefault($default);
        }


        $this->add($telephone);
        $this->blocContactDetail[] = $telephone;

        /**
         *  Privacy Policy
         */

        $appReference = new Text("appReference", array(
            "class" => "form-control control-url control-notempty",
            "placeholder" => "http://"
        ));
        $subLabel = $this->translater->get("GBREVIEW_10") . " " . $this->eventsManager->fire("view:renderLinkOnlineHelp", ["numhelp" => 238, "target" => "_blank"]);
        $appReference->setLabel($this->translater->get("GBREVIEW_9") . " *|$subLabel");

        if ($this->request->hasPost("appReference")) {
            $appReference->setDefault($this->request->getPost("appReference"));
        } else {
            $appReference->setDefault($this->paramsManager->get($this->_platform, "compilation/store/appReference"));
        }

        $this->add($appReference);
        $this->blocContactDetail[] = $appReference;


        /**
         * availability
         */
        $available = $this->paramsManager->get($this->_platform, "compilation/store/availability");

        $availability = new Radio("availability-free", array(
            "name" => "availability",
            "value" => "free"
        ));
        $availability->setLabel($this->translater->get("APPS_CERT_129"));

        if (!$this->request->hasPost("availability")) {
            if (empty($available) || $available == "free") $availability->setDefault("free");
        } else {
            $availability->setDefault($this->request->getPost("availability"));
        }

        $this->add($availability);
        $this->blocPricing[] = $availability;

        $availability = new Radio("availability-pay", array(
            "name" => "availability",
            "value" => "pay"
        ));

        $availability->setLabel($this->translater->get("APPS_CERT_130"));

        if (!$this->request->hasPost("availability")) {
            if (!empty($available) && $available != "free") $availability->setDefault("pay");
        } else {
            $availability->setDefault($this->request->getPost("availability"));
        }

        $this->add($availability);
        $this->blocPricing[] = $availability;

        $storeAvailability = $this->paramsManager->get($this->_platform, "compilation/store/availability");
        $price = new Text("availability-price", array(
            "class" => "form-control " . (!empty($storeAvailability) && $storeAvailability != "free" ? " control-notempty control-float" : ""),
            "placeholder" => $this->translater->get("ANNONCE_CATEGORIE_25")
        ));

        if ($this->request->hasPost("availability-price")) {
            $price->setDefault($this->request->getPost("availability-price"));
        } else {
            if ($available != "free") $price->setDefault($available);
        }

        $this->add($price);
        $this->blocPricing[] = $price;


        /**
         * TAXE
         */
        $tax = $this->paramsManager->get($this->_platform, "compilation/store/pricehastax");

        $tax = new Radio("pricehastax-yes", array(
            "name" => "pricehastax",
            "value" => "yes"
        ));
        $tax->setLabel($this->translater->get("ANDROID_FORM_PRICETAX"));

        if (!$this->request->hasPost("pricehastax")) {
            if (empty($pricehastax) || $pricehastax == "yes") $tax->setDefault("yes");
        } else {
            $tax->setDefault($this->request->getPost("pricehastax"));
        }

        $this->add($tax);
        $this->blocPricing[] = $tax;

        $tax = new Radio("pricehastax-no", array(
            "name" => "pricehastax",
            "value" => "no"
        ));

        $tax->setLabel($this->translater->get('ANDROID_FORM_PRICETOPTAX'));

        if (!$this->request->hasPost("pricehastax")) {
            if (!empty($pricehastax) && $pricehastax != "yes") $tax->setDefault("no");
        } else {
            $tax->setDefault($this->request->getPost("pricehastax"));
        }

        $this->add($tax);
        $this->blocPricing[] = $tax;


        /**
         * advertising
         */
        $advertise = $this->paramsManager->get($this->_platform, "compilation/store/advertising");

        $advertisability = new Radio("adverstise-yes", array(
            "name" => "advertising",
            "value" => "yes"
        ));
        $advertisability->setLabel($this->translater->get("OUI"));
        $advertisability->setDefault("yes");
        $this->add($advertisability);
        $this->accountsInfos[] = $advertisability;


        $advertisability = new Radio("adverstise-no", array(
            "name" => "advertising",
            "value" => "no"
        ));
        $advertisability->setLabel($this->translater->get("NON"));

        if ($this->request->hasPost("advertising")) {
            $advertisability->setDefault($this->request->getPost("advertising"));
        }

        if (($this->request->hasPost("advertising") && $this->request->getPost("advertising") == 'no')
            ||
            (!$this->request->hasPost("advertising") && $advertise == 'no')) {
            $advertisability->setDefault('no');
        }

        $this->add($advertisability);
        $this->accountsInfos[] = $advertisability;


        /**
         * Screenshots
         */
        $screenshotVal = $this->paramsManager->get($this->_platform, "compilation/store/screenshotauto");

        $screenshot = new Radio("screenshotauto-no", array(
            "name" => "screenshotauto",
            "value" => "no"
        ));
        $screenshot->setLabel($this->translater->get("ANDROID_FORM_SCREENSHOTUPLOAD"));
        $screenshot->setDefault("no");
        $this->add($screenshot);
        $this->blocAssets[] = $screenshot;


        $screenshot = new Radio("screenshotauto-yes", array(
            "name" => "screenshotauto",
            "value" => "yes"
        ));
        $screenshot->setLabel($this->translater->get("ANDROID_FORM_SCREENSHOTFROMTEAM"));

        if ($this->request->hasPost("screenshotauto")) {
            $screenshot->setDefault($this->request->getPost("screenshotauto"));
        }

        if (($this->request->hasPost("screenshotauto") && $this->request->getPost("screenshotauto") == 'yes')
            ||
            (!$this->request->hasPost("screenshotauto") && $screenshotVal == 'yes')) {
            $screenshot->setDefault('yes');
        }

        $this->add($screenshot);
        $this->blocAssets[] = $screenshot;

        /**
         * Countries
         */
        $countries = $this->paramsManager->get($this->_platform, "compilation/store/pays");

        $pays = new Radio("pays-all", array(
            "name" => "pays",
            "value" => "all"
        ));
        $pays->setLabel($this->translater->get("APPS_CERT_126"));

        if ($this->request->hasPost("pays")) {
            $pays->setDefault($this->request->getPost("pays"));
        } else {
            if (empty($countries) || $countries == "all") $pays->setDefault("all");
        }

        $this->add($pays);
        $this->accountsInfos[] = $pays;

        $pays = new Radio("pays-select", array(
            "name" => "pays",
            "value" => "select"
        ));

        $pays->setLabel($this->translater->get("APPS_CERT_127"));

        if ($this->request->hasPost("pays")) {
            $pays->setDefault($this->request->getPost("pays"));
        } else {
            if (!empty($countries) && $countries != "all") $pays->setDefault("select");
        }

        $this->add($pays);
        $this->accountsInfos[] = $pays;
        $this->setUserOption('accountsInfos', $this->accountsInfos);

        if (!empty($countries) && $countries != "all") {
            $countries = json_decode($countries);
        }

        $langue = $this->auth->getLanguage();

        switch ($langue) {
            case "fr":
                $field = "nom_pays";
                break;

            case "jp":
                $field = "nom_pays_jp";
                break;

            default:
                $field = "nom_pays_us";
                break;
        }

        $listePays = \Common\Models\ListePays::find(array(
            "columns" => "id_pays, nom_pays_us",
            'conditions' => "id_pays not in (" . implode(',', $this->_tabExcludePays) . ")",
            'order' => "position DESC, $field ASC",
        ));

        $paysArray = [];
        foreach ($listePays as $pays) {
            $paysArray[$pays->id_pays] = $pays->nom_pays_us;
        }

        // Rest of the world
        $paysArray += ["999" => $this->translater->get("GBCOMMERCE_SHIPPING_REST_OF_WORLD")];

        $this->view->selectPays = $this->ui->getListePays(array(
            "name" => "list-country[]",
            "field" => $field,
            "id" => "list-country",
            "langue" => $langue,
            "class" => "form-control input-md list-country",
            "class-pays" => "form-group",
            "model" => $paysArray,
            "multiple" => "multiple",
            "noetat" => true,
            "pays-default" => $countries,
        ));


        /**
         * New content app category
         */

        $default = $this->paramsManager->get($this->_platform, "compilation/store/content/type_category");
        if (empty($default)) {
            $default = "standard";
        }

        $attributes = array();

        $field = new Radio("type_category-oui", array(
            "name" => "type_category",
            "value" => "standard",
            "class" => "control-radio-notempty"
        ));
        $field->setLabel($this->translater->get("GBDROIDFORM_TYPE_CATEGORY_2"));

        if ($this->request->hasPost("type_category")) {
            $field->setDefault($this->request->getPost("type_category"));
        }
        if (!$this->request->hasPost("type_category") && $default == "standard") {
            $field->setDefault($default);
        }

        $this->add($field);
        $this->ratingContentCategory[] = $field;

        $field = new Radio("type_category-non", array(
            "name" => "type_category",
            "value" => "entertainement",
            "class" => "control-radio-notempty"
        ));
        $field->setLabel($this->translater->get("GBDROIDFORM_TYPE_CATEGORY_4"));

        if (!$this->request->hasPost("type_category") && $default == "entertainement") {
            $field->setDefault($default);
        }

        $this->add($field);
        $this->ratingContentCategory[] = $field;
        $this->setUserOption('ratingContentCategory', $this->ratingContentCategory);
        $tabTexteCat = array($this->translater->get("GBDROIDFORM_TYPE_CATEGORY_3"), $this->translater->get("GBDROIDFORM_TYPE_CATEGORY_5"));


        /**
         *  Google Play News policy only for classic (in category)
         */
        if (!$this->webzine->isShopPlan()) {
            // Radio button no / yes
            $isAndroidNewApp = $this->paramsManager->get($this->_platform, "compilation/store/isAndroidNewApp");

            $field = new Radio("androidnewapp-no", array(
                "name" => "androidnewapp",
                "value" => "non",
                "class" => "control-radio-notempty"
            ));
            $field->setLabel($this->translater->get("NON"));
            if ($this->request->hasPost("androidnewapp")) {
                $field->setDefault($this->request->getPost("androidnewapp"));
            }
            if (!$this->request->hasPost("androidnewapp") && (empty($isAndroidNewApp) || $isAndroidNewApp == "no")) {
                $field->setDefault("non");
            }
            $this->add($field);

            $field = new Radio("androidnewapp-yes", array(
                "name" => "androidnewapp",
                "value" => "oui",
                "class" => "control-radio-notempty"
            ));
            $field->setLabel($this->translater->get("OUI") . " " . $this->translater->get("CERT_ANDROID_NEWS_APP_CONFIRM"));
            if (!$this->request->hasPost("androidnewapp") && (!empty($isAndroidNewApp) && $isAndroidNewApp == "yes")) {
                $field->setDefault("oui");
            }
            $this->add($field);

            // Text field
            $field = new TextArea("policyInfo", array(
                "class" => "form-control control-notempty   ",
                "rows" => 4,
                "maxlength" => 500
            ));
            $field->setLabel($this->translater->get("CERT_ANDROID_NEWS_APP_EXPLAIN"));

            if (!$this->request->hasPost("policyInfoText")) {
                $field->setDefault($this->paramsManager->get($this->_platform, "compilation/store/policyInfo"));
            }

            $this->add($field);

        }

        /**
         * New content rating questions
         */

        $this->ratingReadonly = false;
//        if (isset($default) && Submission::getCurrentVersion($this->webzine, $this->platform) > 0){
//            $this->ratingReadonly = true;
//        }

        /**
         * Form rating app standard
         */

        $tabRatingContent = array();
        foreach ($this->tabCatStandard as $cat) {
            $tabTexte = [];
            $this->ratingContent = [];
            // IsYes permet de conserver la reponse de la question principale
            $this->isYes = true;
            foreach ($cat["questions"] as $key => $question) {
                $contentKey = "rating_" . $cat["name"] . "_" . $key;
                $this->makeRadioBloc($key, $contentKey, !empty($cat["answer"][$key]) ? $cat["answer"][$key] : "", "standard");


                $tabTexte[] = $this->translater->get($question);

            }

            $tabRatingContent[$cat["name"]] = [
                "ratingContent" => $this->ratingContent,
                "title" => $this->translater->get($cat["title"]),
                "ratingContentText" => $tabTexte,
                "ratingContentTextCat" => $tabTexteCat,
                "isYes" => $this->isYes,
            ];

        }
        $this->setUserOption('tabRatingContentStandard', $tabRatingContent);


        /**
         * Form rating app Entertainement
         */
        // Tableau qui permet d'encapsuler les levels 3 de question/reponse
        $tabOpenLevel3 = [];
        foreach ($this->tabOpenLevel3 as $key) {
            $tabOpenLevel3[] = "entertainement_rating_" . $key;
        }

        $tabCloseLevel3 = [];
        foreach ($this->tabCloseLevel3 as $key) {
            $tabCloseLevel3[] = "entertainement_rating_" . $key;
        }


        $this->setUserOption('tabOpenLevel3', $tabOpenLevel3);
        $this->setUserOption('tabCloseLevel3', $tabCloseLevel3);

        // Formulaire
        $tabRatingContent = array();
        foreach ($this->tabCatEntertainement as $cat) {
            $tabTexte = [];
            $this->ratingContent = [];
            // IsYes permet de conserver la reponse de la question principale
            $this->isYes = true;
            foreach ($cat["questions"] as $key => $question) {
                $contentKey = "rating_" . $cat["name"] . "_" . $key;

                if (isset($cat["multiple_answer"][$key])) {
                    // Question mode checkbox
                    $this->makeCheckBoxBloc($key, $contentKey, !empty($cat["answer"][$key]) ? $cat["answer"][$key] : "", "entertainement");
                } else {
                    // Question mode bouton radio
                    $this->makeRadioBloc($key, $contentKey, !empty($cat["answer"][$key]) ? $cat["answer"][$key] : "", "entertainement");
                }


                $tabTexte[] = $this->translater->get($question);

            }

            $tabRatingContent[$cat["name"]] = [
                "ratingContent" => $this->ratingContent,
                "title" => $this->translater->get($cat["title"]),
                "ratingContentText" => $tabTexte,
                "ratingContentTextCat" => $tabTexteCat,
                "isYes" => $this->isYes,
            ];

        }
        $this->setUserOption('tabRatingContentEntertainement', $tabRatingContent);


        /**
         * Permissions declaration
         */

        if ($this->acl->isAddonInstalled("pushgeofence")) {
            $appPurpose = new TextArea("appPurpose", [
                "class" => "form-control control-notempty display-maxlength",
                "style" => "margin-top: 11px",
                "rows" => 6,
                "maxlength" => 500
            ]);

            $appPurpose->setLabel($this->translater->get("APPS_INFOS_APP_PURPOSE"))
                ->addValidator(new StringLength(array(
                    'max' => 500,
                    'messageMaximum' => str_replace("[X]", 500, $this->translater->get("PAGE2_FORMULAIRE_6", null, "Langage"))
                )));

            if (!$this->request->hasPost("appPurpose")) {
                $appPurpose->setDefault($this->paramsManager->get($this->_platform, "compilation/store/appPurpose"));
            }

            $this->add($appPurpose);
            $this->permissionsDeclaration[] = $appPurpose;

            $locationAccess = new TextArea("locationAccess", [
                "class" => "form-control control-notempty display-maxlength",
                "rows" => 6,
                "maxlength" => 500
            ]);

            $locationAccess->setLabel($this->translater->get("APPS_INFOS_LOCATION_ACCESS") . "<p><em>" . $this->translater->get("APPS_INFOS_LOCATION_ACCESS_DESC") . "</em></p>")
                ->addValidator(new StringLength(array(
                    'max' => 500,
                    'messageMaximum' => str_replace("[X]", 500, $this->translater->get("PAGE2_FORMULAIRE_6", null, "Langage"))
                )));

            if (!$this->request->hasPost("locationAccess")) {
                $locationAccess->setDefault($this->paramsManager->get($this->_platform, "compilation/store/locationAccess"));
            }

            $this->add($locationAccess);
            $this->permissionsDeclaration[] = $locationAccess;

            $videoInstructions = new Text("videoInstructions", [
                "class" => "form-control control-url allow-empty",
                "placeholder" => "http://",
                "style" => "margin-top: 11px"
            ]);

            $videoInstructions->setLabel($this->translater->get("APPS_INFOS_VIDEO_INSTRUCTIONS"));

            if (!$this->request->hasPost("videoInstructions")) {
                $videoInstructions->setDefault($this->paramsManager->get($this->_platform, "compilation/store/videoInstructions"));
            }

            $this->add($videoInstructions);
            $this->permissionsDeclaration[] = $videoInstructions;
        }

        /**
         * Package Name
         */

        $packageName = new Text("packageName", array("class" => "form-control control-notempty control-packagename"));
        $packageName->addValidator(new PresenceOf(array(
            'message' => $this->translater->get("SEND_OBLIGATOIRE", null, "Langage")
        )));

        if (!$this->request->hasPost("packageName")) {
            $packageName->setDefault($this->paramsManager->get($this->_platform, "compilation/packageName"));
        }

        if ($packageName->getDefault() === null) {
            $packageName->setDefault($this->_getDefaultPackageName());
        }

        $this->add($packageName);
        $this->packageName = $packageName;
    }

    private function makeCheckBoxBloc($key, $contentKey, $type, $formType)
    {

        $contentKey = $formType . "_" . $contentKey;
        // Bouton radio oui - non
        switch ($type) {

            case "sexuality_nudity" :
                $default = $this->paramsManager->get($this->_platform, "compilation/store/rating/$contentKey-oui");
                $this->makeCheckBox($key, $contentKey, $default, "oui", "GB_ANDROID_SUBMIT_RATING_60");
                $default = $this->paramsManager->get($this->_platform, "compilation/store/rating/$contentKey-2");
                $this->makeCheckBox($key, $contentKey, $default, "2", "GB_ANDROID_SUBMIT_RATING_61");
                $default = $this->paramsManager->get($this->_platform, "compilation/store/rating/$contentKey-3");
                $this->makeCheckBox($key, $contentKey, $default, "3", "GB_ANDROID_SUBMIT_RATING_62");
                $default = $this->paramsManager->get($this->_platform, "compilation/store/rating/$contentKey-4");
                $this->makeCheckBox($key, $contentKey, $default, "4", "GB_ANDROID_SUBMIT_RATING_63");
                break;

            case "sexuality_suggestivity":
                $default = $this->paramsManager->get($this->_platform, "compilation/store/rating/$contentKey-oui");
                $this->makeCheckBox($key, $contentKey, $default, "oui", "GB_ANDROID_SUBMIT_RATING_67");
                $default = $this->paramsManager->get($this->_platform, "compilation/store/rating/$contentKey-2");
                $this->makeCheckBox($key, $contentKey, $default, "2", "GB_ANDROID_SUBMIT_RATING_68");
                $default = $this->paramsManager->get($this->_platform, "compilation/store/rating/$contentKey-3");
                $this->makeCheckBox($key, $contentKey, $default, "3", "GB_ANDROID_SUBMIT_RATING_69");
                $default = $this->paramsManager->get($this->_platform, "compilation/store/rating/$contentKey-4");
                $this->makeCheckBox($key, $contentKey, $default, "4", "GB_ANDROID_SUBMIT_RATING_70");
                $default = $this->paramsManager->get($this->_platform, "compilation/store/rating/$contentKey-5");
                $this->makeCheckBox($key, $contentKey, $default, "5", "GB_ANDROID_SUBMIT_RATING_71");
                break;

            case "crude_humor" :
                $default = $this->paramsManager->get($this->_platform, "compilation/store/rating/$contentKey-oui");
                $this->makeCheckBox($key, $contentKey, $default, "oui", "GB_ANDROID_SUBMIT_RATING_101");
                $default = $this->paramsManager->get($this->_platform, "compilation/store/rating/$contentKey-2");
                $this->makeCheckBox($key, $contentKey, $default, "2", "GB_ANDROID_SUBMIT_RATING_102");
                $default = $this->paramsManager->get($this->_platform, "compilation/store/rating/$contentKey-3");
                $this->makeCheckBox($key, $contentKey, $default, "3", "GB_ANDROID_SUBMIT_RATING_103");
                $default = $this->paramsManager->get($this->_platform, "compilation/store/rating/$contentKey-4");
                $this->makeCheckBox($key, $contentKey, $default, "4", "GB_ANDROID_SUBMIT_RATING_104");
                break;

        }


    }

    private function makeRadioBloc($key, $contentKey, $type, $formType)
    {
        $contentKey = $formType . "_" . $contentKey;
        $default = $this->paramsManager->get($this->_platform, "compilation/store/rating/$contentKey");

        // Bouton radio oui - non
        switch ($type) {

            case "never" :
                // Radio never
                $this->makeRadioButton($key, $contentKey, $default, "oui", "GB_ANDROID_SUBMIT_RATING_NEVER");
                // Radio rarely
                $this->makeRadioButton($key, $contentKey, $default, "rarely", "GB_ANDROID_SUBMIT_RATING_RARELY");
                // Radio often
                $this->makeRadioButton($key, $contentKey, $default, "often", "GB_ANDROID_SUBMIT_RATING_OFTEN");
                break;

            case "rarely" :
                // Radio rarely
                $this->makeRadioButton($key, $contentKey, $default, "oui", "GB_ANDROID_SUBMIT_RATING_RARELY");
                // Radio often
                $this->makeRadioButton($key, $contentKey, $default, "often", "GB_ANDROID_SUBMIT_RATING_OFTEN");
                break;


            case "violence_presented" :
                $this->makeRadioButton($key, $contentKey, $default, "oui", "GB_ANDROID_SUBMIT_RATING_29");
                $this->makeRadioButton($key, $contentKey, $default, "implied", "GB_ANDROID_SUBMIT_RATING_30");
                $this->makeRadioButton($key, $contentKey, $default, "rarelydistant", "GB_ANDROID_SUBMIT_RATING_31");
                $this->makeRadioButton($key, $contentKey, $default, "oftendistant", "GB_ANDROID_SUBMIT_RATING_32");
                $this->makeRadioButton($key, $contentKey, $default, "rarelyclose", "GB_ANDROID_SUBMIT_RATING_33");
                $this->makeRadioButton($key, $contentKey, $default, "oftenclose", "GB_ANDROID_SUBMIT_RATING_34");
                break;


            case "blood_level" :
                $this->makeRadioButton($key, $contentKey, $default, "oui", "GB_ANDROID_SUBMIT_RATING_NONE");
                $this->makeRadioButton($key, $contentKey, $default, "limited", "GB_ANDROID_SUBMIT_RATING_LIMITED");
                $this->makeRadioButton($key, $contentKey, $default, "moderate", "GB_ANDROID_SUBMIT_RATING_MODERATE");
                $this->makeRadioButton($key, $contentKey, $default, "high", "GB_ANDROID_SUBMIT_RATING_HIGH");
                break;

            case "violence_motivation" :
                $this->makeRadioButton($key, $contentKey, $default, "oui", "GB_ANDROID_SUBMIT_RATING_37");
                $this->makeRadioButton($key, $contentKey, $default, "neutral", "GB_ANDROID_SUBMIT_RATING_38");
                $this->makeRadioButton($key, $contentKey, $default, "negative", "GB_ANDROID_SUBMIT_RATING_39");
                break;

            case "drugs_illegal" :
                $this->makeRadioButton($key, $contentKey, $default, "oui", "GB_ANDROID_SUBMIT_RATING_REFERENCE");
                $this->makeRadioButton($key, $contentKey, $default, "2", "GB_ANDROID_SUBMIT_RATING_USE");
                $this->makeRadioButton($key, $contentKey, $default, "3", "GB_ANDROID_SUBMIT_RATING_90");
                break;

            default:
                // Radio oui
                $this->makeRadioButton($key, $contentKey, $default, "oui", "OUI");
                // Radio non
                $this->makeRadioButton($key, $contentKey, $default, "non", "NON");

        }


    }

    private function makeRadioButton($key, $name, $default, $value, $label)
    {
        if ($key == 0 && $default != "oui") {
            $this->isYes = false;
        }

        $class = "control-radio-notempty";
        if ($key == 0) {
            $class = "firstquestion control-radio-notempty";
        }

        // Cas particulier pour miscellaneous (toutes les questions en level 1, reponse obligatoire)
        if (preg_match("/rating_miscellaneous_/", $name)) {
            $this->isYes = true;
        }

        $field = new Radio("$name-$value", array(
            "name" => $name,
            "value" => $value,
            "class" => $class,
            //"readonly" => ($this->ratingReadonly ? "readonly" : "")
        ));
        $field->setLabel($this->translater->get($label));

        if ($this->request->hasPost($name)) {
            $field->setDefault($this->request->getPost($name));
        }

        if (!$this->request->hasPost($name) && !empty($default) && $default == $value) {
            $field->setDefault($value);
        }

        $this->add($field);
        $this->ratingContent[] = $field;

    }


    private function makeCheckBox($key, $name, $default, $value, $label)
    {
        $field = new Check("$name-$value", array(
            "name" => "$name-$value",
            "value" => $value,
            "class" => "control-checkbox-notempty",
        ));
        $field->setLabel($this->translater->get($label));

        if ($this->request->hasPost($name)) {
            $field->setDefault($this->request->getPost($name));
        }

        if (!$this->request->hasPost($name) && !empty($default) && $default == $value) {
            $field->setDefault($value);
        }

        $this->add($field);
        $this->ratingContent[] = $field;

    }

    /**
     * Retourne le package name par défaut : celui de l'agence s'il existe, sinon com.goodbarber...
     * @return string packagename
     */
    private function _getDefaultPackageName()
    {
        if ($this->webzine->isWhiteLabel(true) && !empty($this->webzine->getWhiteLabelAgency()) && !empty($this->webzine->getWhiteLabelAgency()->getAttrib('packageName')))
            $partialName = $this->webzine->getWhiteLabelAgency()->getAttrib('packageName');
        else
            $partialName = "com.goodbarber";

        return $partialName . "." . Compilation::cleanGbIdentifiant($this->webzine->identifiant, true);
    }

}

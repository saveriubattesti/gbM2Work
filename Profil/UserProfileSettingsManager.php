<?php

namespace Common\Lib\Goodbarber\Users;

use Common\Models\Webzine;
use \Common\Traits\DI;


/**
 * User Repository
 *
 * Responsable de la persistance des données User.
 */
class UserProfileSettingsManager implements \Phalcon\DI\InjectionAwareInterface
{
    use DI;

    private $aConfigSocial = array(
        'twitter' => array('onColor' => '#94CEED', 'icon' => '10'),
        'facebook' => array('onColor' => '#435B89', 'icon' => '8'),
        'pinterest' => array('onColor' => '#CB2027', 'icon' => '9'),
        'linkedin' => array('onColor' => '#0e76a8', 'icon' => '14'),
        'instagram' => array('onColor' => '#b9a28b', 'icon' => '21'),
        'whatsapp' => array('onColor' => '#6ccf72', 'icon' => 'whatsapp'),
        'snapchat' => array('onColor' => '#fffb01', 'icon' => 'snapchat')
    );

    private $aConfigField = array(
        "GBUserFieldName" => "FORMULAIRE_65",
        "GBUserFieldDescription" => "GBFORM_27",
        "GBUserFieldDate" => "FORMULAIRE_29",
        "GBUserFieldMail" => "FORMULAIRE_30",
        "GBUserFieldText" => "GBFORM_9",
        "GBUserFieldLink" => "GBUSERFIELDSLINK_LABEL",
        "GBUserFieldNumber" => "EDITER_RUBRIQUE_31",
        "GBUserFieldDropdown" => "GBFORM_5",
        "GBUserFieldLocation" => "GBFORM_38",
        "GBUserFieldPhone" => "RTE_tel",
        "GBUserFieldParagraph" => "GBFORM_8"
    );

    private $aFakeInfosFields = array(
        "GBUserFieldMail" => "address.email@user.com",
        "GBUserFieldText" => "Lorem ipsum dolor sit amet",
        "GBUserFieldNumber" => "42",
        "GBUserFieldLocation" => "Corsica, France",
        "GBUserFieldPhone" => "+44 7700 900085",
        "GBUserFieldParagraph" => "Lorem ipsum dolor sit amet, consectetur adipiscing elit.",
        "GBUserFieldLink" => "https://www.example.com"
    );


    private $aUniqueField = array("GBUserFieldName", "GBUserFieldDescription", "GBUserFieldLocation");
    private $aMandatoryField = array("GBUserFieldName", "GBUserFieldDescription");
    private $aChoicesField = array("GBUserFieldDropdown");

    private $aAdvancedSearchField = array("GBUserFieldDropdown");

    private $_paramsManager;
    private $_objet = 'iphone';
    private $_webzine;

    /**
     * Section Object
     * @var Object
     */
    private $sectionProfile;

    public function __construct(Webzine $webzine)
    {
        $di = $this->getDI();
        $this->_webzine = $webzine;
        // TODO: Mathieu
        // Check what addon we have
        $sectionsProfiles = $di->getSectionManager()->getAllByType($this->_webzine, "GBModuleTypeProfile", true);

        if (!empty($sectionsProfiles[0])) {
            $this->sectionProfile = $sectionsProfiles[0];
        }

        $this->_paramsManager = $di->getParamsManager();

        $this->aFakeInfosFields["GBUserFieldDate"] = $this->formatProfileSettingsDate();
    }


    /**
     * Use specific date format
     * @param string $date
     * @return mixed
     */
    public function formatProfileSettingsDate($date = "")
    {
        if (empty($date)) {
            $date = time();
        }
        $trame = $this->getDI()->getTranslater()->getGB($this->_webzine, "GB_DATETXT4");
        $formatedDate = str_replace("dd", date("d", $date), $trame);
        $formatedDate = str_replace("MM", date("m", $date), $formatedDate);
        $formatedDate = str_replace("yyyy", date("Y", $date), $formatedDate);
        return str_replace("'", "", $formatedDate);

    }

    public function getIdSectionProfile()
    {
        if (empty($this->sectionProfile)) return 0;

        return $this->sectionProfile->id_param;
    }

    /**
     * Fonction qui sert a instantier les paramtres par defaut apres la création de la section Profil
     */
    public function initProfileSectionAfterCreate()
    {
        if ($this->getDI()->getWebzine()->id_webzine != $this->_webzine->id_webzine) return;

        $this->setUserSocialLink(array('twitter', 'facebook'));
        $this->setUserSettingsFields(array(
            array('id' => 0, 'type' => 'GBUserFieldName', 'name' => '', 'required' => 1, 'state' => 'public'),
            array('id' => 0, 'type' => 'GBUserFieldLocation', 'name' => '', 'required' => 0, 'state' => 'public'),
            array('id' => 0, 'type' => 'GBUserFieldDescription', 'name' => '', 'required' => 0, 'state' => 'public')
        ));
    }

    /**
     * Fonction qui sert a instantier les paramtres par defaut de Login apres l'activation de l'addon User
     * @param string $loginAPItype ([ "classic", "commerce" ])
     */
    public function initLoginAfterCreate($loginAPItype = "")
    {
        if ($this->getDI()->getWebzine()->id_webzine != $this->_webzine->id_webzine) return;

        $nom_app = $this->_webzine->getCompilationName();
        if (!isset($nom_app)) {
            $nom_app = $this->_paramsManager->get($this->_objet, "navBar/title", "", 0, $this->_webzine);
        }
        $this->_paramsManager->set($this->_objet, "login/title", $nom_app, "login", 0, 1, 0);

        // API type (can be classic, commerce)
        $this->_paramsManager->set($this->_objet, "login/loginType", $loginAPItype, "login", 0, 1, 0);

        // Mise en place paramètres par défaut
        $tabParams = array('signUpEnabled' => 1, 'skipEnabled' => 0, 'facebookEnabled' => 0, 'twitterEnabled' => 0, 'terms' => $this->getDI()->getTranslater()->get("GBSTATS_SOCIAL_43"));
        foreach ($tabParams as $key => $val) {
            $this->_paramsManager->set("", "login/$key", $val, "login", 0, 1, 0);
        }
    }

    /**
     * Modifie la liste des champs perso de la page profil des utilisateurs
     */
    public function setAllowAvatarSettings($newValue)
    {
        if ($this->getDI()->getWebzine()->id_webzine != $this->_webzine->id_webzine) return;

        $newValue = (!empty($newValue) ? 1 : 0);

        $di = $this->getDI();
        $paramsLogger = $di->getParamsLogger();

        /**
         * Modification dans la section Profil ( profil privé )
         */
        $oldValue = $this->_paramsManager->get($this->_objet, "sections/allowAvatar", "sections", $this->getIdSectionProfile(), $this->_webzine);
        if ($oldValue != $newValue) {
            $this->_paramsManager->set($this->_objet, "sections/allowAvatar", $newValue, "sections", $this->getIdSectionProfile(), 1, 1);
            $paramsLogger->log($this->_objet, "gbpublish_section_modified", $this->sectionProfile->getNom(), "", $this->getIdSectionProfile());
        }

        /**
         * Pour la V4 suppression de ce code
         * Modification dans les section UserList ( profil public ) Seulement Pour la V3, la V4 n'a plus l'objet "sections/detail/allowAvatar"
         */
        $sectionsUserslist = $di->getSectionManager()->getAllByType($this->_webzine, "GBModuleTypeUserslist", true);
        foreach ($sectionsUserslist as $sectionUserslist) {
            if (!$this->_webzine->isV4()) {
                $oldValue = $this->_paramsManager->get($this->_objet, "sections/detail/allowAvatar", "sections", $sectionUserslist->id_param, $this->_webzine);
                if ($oldValue != $newValue && $newValue != null) {
                    $this->_paramsManager->set($this->_objet, "sections/detail/allowAvatar", $newValue, "sections", $sectionUserslist->id_param, 1, 1);
                    $paramsLogger->log($this->_objet, "gbpublish_section_modified", $sectionUserslist->getNom(), "", $sectionUserslist->id_param);
                }
            } else {
                $this->_paramsManager->delete($this->_objet, "sections/detail/allowAvatar", "sections", $sectionUserslist->id_param, "", $this->_webzine);
            }
        }

    }

    /**
     * Modifie la liste des champs perso de la page profil des utilisateurs
     */
    public function setUserSettingsFields($newListFields)
    {
        if ($this->getDI()->getWebzine()->id_webzine != $this->_webzine->id_webzine) return;

        // On recupère l'ancienne liste des champs customs
        $oldListFields = $this->_paramsManager->get($this->_objet, "sections/fields/%/id", "sections", $this->getIdSectionProfile(), $this->_webzine);

        $this->addUserSettingsFields($newListFields);

        // Si il y a moins de champs custom qu'avant on supprime le surplus
        $diffNbFields = count($newListFields) - count($oldListFields);
        if ($diffNbFields < 0) {
            for ($i = count($oldListFields) + $diffNbFields; $i < count($oldListFields); $i++) {
                $this->_paramsManager->delete($this->_objet, "sections/fields/$i/%", "sections", $this->getIdSectionProfile(), "", $this->_webzine);
            }
        }
    }

    /**
     * Ajoute un champ dans la page information du profil des utilisateurs
     * @param array(array) |array(json) $aFields = array( array('id'=> 0,'type'=> 'GBUserFieldText','name'=> 'le nom de votre chien ?','required'=> 0,'state'=>'public')) )
     */
    private function addUserSettingsFields($aFields)
    {
        $nbSave = 0;
        $aFieldsUniqueFree = $this->aUniqueField;
        foreach ($aFields as $nb => $field) {

            if (is_array($field)) $field = json_encode($field);
            $field = json_decode($field);

            // Si le name est de type unique et est celui d'origine, on stock rien, pour que les natif l'affiche dans la bonne langue
            if (in_array($field->type, $this->aUniqueField)) {
                // S'il à deja été créé, on ne le sauve pas
                if (!in_array($field->type, $aFieldsUniqueFree)) {
                    continue;
                } elseif ($field->name == $this->getDI()->getTranslater()->get($this->aConfigField[$field->type])) {
                    $field->name = "";
                }
                unset($aFieldsUniqueFree[array_search($field->type, $aFieldsUniqueFree)]);
            };

            $this->addUserSettingsField($field, $nb);

            $nbSave = $nb;
        }

        // en cas de hack et donc de supression de fields Obligatoire on les rajoutes en debut de list
        $aFieldsMandatoryNotFound = array_intersect($aFieldsUniqueFree, $this->aMandatoryField);
        if (count($aFieldsMandatoryNotFound)) {
            $aFieldsMandatoryNotCreate = [];
            if (in_array("GBUserFieldName", $aFieldsMandatoryNotFound))
                $aFieldsMandatoryNotCreate[] = array('id' => 0, 'type' => 'GBUserFieldName', 'name' => '', 'required' => 1, 'state' => 'public');
            if (in_array("GBUserFieldDescription", $aFieldsMandatoryNotFound))
                $aFieldsMandatoryNotCreate[] = array('id' => 0, 'type' => 'GBUserFieldDescription', 'name' => '', 'required' => 0, 'state' => 'public');

            foreach ($aFieldsMandatoryNotCreate as $field) {
                $nbSave++;
                $this->addUserSettingsField($field, $nbSave);
            }
        }

    }

    /**
     * Ajoute un champ dans la page information du profil des utilisateurs
     * @param array() $field = array('id'=> 0,'type'=> 'GBUserFieldText','name'=> 'le nom de votre chien ?','required'=> 0,'state'=>'public') )
     * @param $position
     */
    private function addUserSettingsField($field, $position)
    {
        if (!is_array($field)) $field = (array)$field;

        $oldField = $this->_paramsManager->get($this->_objet, "sections/fields/$position/%", "sections", $this->getIdSectionProfile(), $this->_webzine);

        // On change les valeurs si elles sont differentes
        if (empty($field['id']) || $field['id'] == 0) $field['id'] = '0' . time() . rand(0, 99999);
        foreach (array('id', 'type', 'name') as $objet) {
            if (empty($oldField) || $oldField["sections/fields/$position/$objet"] !== $field[$objet]) {
                $this->_paramsManager->set($this->_objet, "sections/fields/$position/$objet", $field[$objet], "sections", $this->getIdSectionProfile(), 1, 0);
            }
        }
        $this->_paramsManager->set($this->_objet, "sections/fields/$position/state", $field['state'], "sections", $this->getIdSectionProfile(), 1, 0);
        $this->_paramsManager->set($this->_objet, "sections/fields/$position/required", ($field['required'] ? 1 : 0), "sections", $this->getIdSectionProfile(), 1, 0);

        // On suppime des anciens choices
        if (!empty($oldField["sections/fields/$position/choices/0/"])) {
            $this->_paramsManager->delete($this->_objet, "sections/fields/$position/choices/%", "sections", $this->getIdSectionProfile(), "", $this->_webzine);
        }

        // Si dans le nouveau field il y a des choises, on les rajoutes
        if (in_array($field['type'], $this->aChoicesField)) {

            foreach ($field['choices'] as $i => $choice) {
                $this->_paramsManager->set($this->_objet, "sections/fields/$position/choices/$i/", $choice, "sections", $this->getIdSectionProfile(), 1, 0);
            }
        }
    }

    /**
     * Retourne tous les fields de la section
     * @return array(array) $aFieldSettings = array(array('id'=> $id,'type'=> $type,'name'=> $name,'required'=> $required,'state'=> $state));
     */
    public function getUserSettingsFields()
    {
        $aIdFields = $this->_paramsManager->get($this->_objet, "sections/fields/%/id", "sections", $this->getIdSectionProfile(), $this->_webzine);
        $aFieldSettings = array();
        foreach ($aIdFields as $objet => $id) {
            $type = $this->_paramsManager->get($this->_objet, str_replace('id', 'type', $objet), "sections", $this->getIdSectionProfile(), $this->_webzine);
            $name = $this->_paramsManager->get($this->_objet, str_replace('id', 'name', $objet), "sections", $this->getIdSectionProfile(), $this->_webzine);

            if (empty($name)) $name = $this->getDI()->getTranslater()->get($this->aConfigField[$type]);
            $required = $this->_paramsManager->get($this->_objet, str_replace('id', 'required', $objet), "sections", $this->getIdSectionProfile(), $this->_webzine);
            $state = $this->_paramsManager->get($this->_objet, str_replace('id', 'state', $objet), "sections", $this->getIdSectionProfile(), $this->_webzine);
            if (empty($state)) $state = 'public';
            $array = array('id' => $id, 'type' => $type, 'name' => $name, 'required' => $required, 'state' => $state);
            if (in_array($type, $this->aChoicesField)) {
                $choices = $this->_paramsManager->get($this->_objet, str_replace('id', 'choices/%', $objet), "sections", $this->getIdSectionProfile(), $this->_webzine);
                $array += array('choices' => array_values($choices));
            }
            $aFieldSettings[] = $array;
        }

        return $aFieldSettings;
    }

    /**
     * Retourne les fields de la section pour la recherche avancee
     * @return array(array) $aFieldSettings = array(array('id'=> $id,'type'=> $type,'name'=> $name,'required'=> $required,'state'=> $state));
     */
    public function getUserSettingsAdvancedSearchFields()
    {
        $aFieldSettings = $this->getUserSettingsFields();

        $retu = array();
        foreach ($aFieldSettings as $field) {
            if (in_array($field["type"], $this->aAdvancedSearchField)) {
                $retu[] = $field;
            }
        }

        return $retu;
    }

    /**
     * Retourne la liste des attribe et la liste des valeur des fields pour l'export de la list d'users
     * @return array;
     */
    public function getParamsForExportUsers()
    {
        $attribs = $values = array();

        foreach ($this->getUserSettingsFields() as $fieldConfig) {
            if (!in_array($fieldConfig['type'], $this->aUniqueField)) {
                $attribs[$fieldConfig['id']] = $fieldConfig['name'];

                if (!empty($fieldConfig['choices'])) {
                    $values[$fieldConfig['id']] = (object)$fieldConfig['choices'];
                }
            }
        }

        return array("attribs" => $attribs, "values" => $values);
    }

    /**
     * Modifie la liste des liens socials de la page profil des utilisateurs
     */
    public function setUserSocialLink($newListSocial)
    {
        // On recupere l'ancienne liste des champs social
        $oldListSocial = $this->_paramsManager->get("", "sections/social/%/type", "sections", $this->getIdSectionProfile(), $this->_webzine);

        if (!is_array($newListSocial)) {
            $newListSocial = [];
        }

        // S'il y a une difference entre les deux listes on la set
        if (array_values($oldListSocial) !== $newListSocial) {
            $this->addUserSocialLink($newListSocial);

            // Si il y a moins de champs social qu'avant on supprime le surplus
            $diffNbSocial = count($newListSocial) - count($oldListSocial);
            if ($diffNbSocial < 0) {
                for ($i = count($oldListSocial) + $diffNbSocial; $i < count($oldListSocial); $i++) {
                    $this->_paramsManager->delete("", "sections/social/$i/%", "sections", $this->getIdSectionProfile(), "", $this->_webzine);
                }
            }
        }

    }

    /**
     * Ajoute des liens socials de la page profil des utilisateurs
     * @param array $type = ('twitter','facebook','googleplus','pinterest','linkedin','instagram')
     */
    private function addUserSocialLink($newSocialType)
    {
        if (!empty($newSocialType)) {
            $aConfigSocial = $this->aConfigSocial;

            foreach ($newSocialType as $i => $type) {
                if ($social = $aConfigSocial[$type]) {
                    $oldSocialType = $this->_paramsManager->get("", "sections/social/" . $i . "/type", "sections", $this->getIdSectionProfile(), $this->_webzine);
                    if ($oldSocialType != $type) {
                        $path = ($type === "whatsapp" || $type === "snapchat") ? "generic/" : "v1/130/";
                        $this->_paramsManager->set("", "sections/social/" . $i . "/type", $type, "sections", $this->getIdSectionProfile(), 1, 0);
                        $this->_paramsManager->set("", "sections/social/" . $i . "/onColor", $social['onColor'], "sections", $this->getIdSectionProfile(), 1, 0);
                        $this->_paramsManager->set("", "sections/social/" . $i . "/icon/imageUrl", "/assets/gbicon/img/" . $path . $social['icon'] . ".png", "sections", $this->getIdSectionProfile(), 1, 0);
                        $this->_paramsManager->set("", "sections/social/" . $i . "/text", "", "sections", $this->getIdSectionProfile(), 1, 0);

                        if ($type == "snapchat") {
                            $this->_paramsManager->set("", "sections/social/" . $i . "/icon/isColored", 1, "sections", $this->getIdSectionProfile(), 1, 0);

                        }
                    }
                }
            }
        }

    }

    /**
     * Retourne la liste des liens socials (en clé et leur etat en valeur) disponible le profil des utilisateurs
     * @return array ('facebook' => 0, ....)
     */
    public function getUserSocialLink()
    {
        $aSocialLinkEnable = $this->_paramsManager->get("", "sections/social/%/type", "sections", $this->getIdSectionProfile(), $this->_webzine);
        $aConfigSocial = $this->aConfigSocial;

        // On fetch les boutons social actif et inactif
        $aSocialLink = array();
        foreach ($aConfigSocial as $type => $data) {
            $aSocialLink[$type] = (in_array($type, $aSocialLinkEnable) ? 1 : 0);
        }

        return $aSocialLink;
    }

    /**
     * @return array
     */
    public function getConfigField()
    {
        return $this->aConfigField;
    }

    /**
     * @return mixed
     */
    public function getUniqueField()
    {
        return $this->aUniqueField;
    }

    /**
     * @return array
     */
    public function getMandatoryField()
    {
        return $this->aMandatoryField;
    }

    /**
     * @return array
     */
    public function getChoicesField()
    {
        return $this->aChoicesField;
    }

    /**
     * @return array
     */
    public function getFakeInfosFields()
    {
        return $this->aFakeInfosFields;
    }

}

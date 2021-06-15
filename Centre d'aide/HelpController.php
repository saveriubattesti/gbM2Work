<?php

namespace Engine\GBBackOffice\Controllers;


use Common\Lib\Video\VideoPlayer;
use Common\Lib\Webzine\Article;
use Common\Models\Rubrique;
use Common\Models\Webzine;
use Common\Models\WmUser;
use Engine\GBBackOffice\V4\Lib\Help\OnlineHelpEvaluationManager;

/**
 * Class HelpController
 *
 * @package Engine\GBBackOffice\Controllers
 */
class HelpController extends ControllerBase
{
    public function initialize()
    {
        parent::initialize();

        if (!$this->view->isV4) $this->view->bodyClass .= " white";
        $this->type = $this->dispatcher->getParam("type", "string");
        $this->help = new \Common\Lib\Help($this->webzine, $this->type);
        $this->webzineHelp = $this->help->getWebzineHelp();

        $this->view->setVar("type", $this->type);

        $this->view->mobileCompliantPage = true;
        VideoPlayer::injectAssets($this->assets);
        //$this->view->hideBtnSupport = true;
    }

    public function helpcenterAction()
    {
        $accesToSupport = $this->acl->isAllowed("support", "index");
        $badge = "";
        if ($accesToSupport) {
            $nb_threads = $this->auth->getUser()->getNbThreadsAnswered($this->webzine);
            if ($nb_threads > 0) {
                $badge = "<span class='badge badge-danger'>$nb_threads</span>";
            }
        }
        $this->view->badge = $badge;
        $this->view->hideBtnSupport = true;

        $videosLink = $this->_getLink("videos");
        $this->view->videosLink = $videosLink;

        $academyLink = $this->_getAcademyLink();
        $this->view->academyLink = $academyLink;

        $showcasesLink = $this->_getShowcasesLink();
        $this->view->showcasesLink = $showcasesLink;

        $blogLink = $this->_getLink("blog");
        $this->view->blogLink = $blogLink;
    }

    public function helpdevAction($numrub = 0, $numart = 0)
    {
        // No more helpdev action in V4, integrated to Help
        if ($this->webzine->isV4()) {
            return $this->response->redirect($this->url->getRedirectUrl("settings/help/14/"));
        }

        return $this->dispatcher->forward(array("controller" => "help", "action" => "index", "params" => array($numrub, $numart, "type" => "helpdev")));
    }

    public function indexAction($numrub = 0, $numart = 0)
    {
        if ($this->request->has("key")) {
            $this->dispatcher->forward(array(
                    "action" => "search"
                )
            );

            return false;
        }

        $numrub = $this->filter->sanitize($numrub, "int");
        $numart = $this->filter->sanitize($numart, "int");

        if ($numart > 0) {
            $this->dispatcher->forward(array(
                    "action" => "content",
                    "params" => ["numart" => $numart, "type" => $this->type]
                )
            );

            return false;
        }

        // Get rubrique si on en passe une
        $childof = 0;
        if ($numrub > 0) {
            $rub = $this->webzineHelp->getRubrique(array(
                    "conditions" => "numrub=:numrub:" . $this->help->getSqlForbiddenRubriquesForGroup(),
                    "bind" => array("numrub" => $numrub),
                    "limit" => 1
                )
            )->getFirst();

            // Si on a une rub on affiche articles
            if (!empty($rub)) {
                /*
                 * On n'affiche que les rubriques publiées ou invisibles
                 */
                if ($rub->etat != "" && $rub->etat != "invis") {
                    return $this->response->redirect($this->url->getRedirectUrl("settings/help/"));
                }
                $childof = $rub->id_rubrique;

                $table = $this->getTableContent($rub, "article", "articles", $this->type);

                if (!empty($table)) {
                    $this->view->setVar("tableArticle_" . $rub->id_rubrique, $table);
                }

                $this->view->setVar("rub", $rub);

                // On recup la rub mere si yen a une pour breadcrump
                if ($rub->childof > 0) {
                    $rubParent = \Common\Models\Rubrique::get($this->webzineHelp, $rub->childof, null, false);

                    $tableOther = $this->getTableContent($this->webzineHelp, "rubrique", "other-rubriques", $this->type, array("childof" => $rub->childof, "exception" => $rub->id_rubrique));

                    if (!empty($tableOther)) {
                        $this->view->setVar("tableOtherRubrique", $tableOther);
                    }

                    $this->view->setVar("rubParent", $rubParent);
                }
            }
        }

        // On liste rubriques
        $wz = $this->webzineHelp;
        if ($wz) {
            $rubriques = $wz->getRubrique(array(
                    "conditions" => "childof=:childof: AND etat=''" . $this->help->getSqlForbiddenRubriquesForGroup(),
                    "bind" => array("childof" => $childof),
                    "order" => "position ASC"
                )
            );

            if ($rubriques->count() > 0) {
                $this->view->setVar("rubriques", $rubriques);

                // Pour chaque rubrique on affiche article et ss rubriques
                foreach ($rubriques as $rubrique) {
                    // Articles
                    $tableRubArticles = $this->getTableContent($rubrique, "article", "rub-articles", $this->type);

                    if (!empty($tableRubArticles)) {
                        $this->view->setVar("tableRubArticle_" . $rubrique->id_rubrique, $tableRubArticles);
                    }

                    // Sous rubriques
                    $tableSsRubriques = $this->getTableContent($this->webzineHelp, "rubrique", "ss-rubriques", $this->type, array("childof" => $rubrique->id_rubrique));

                    if (!empty($tableSsRubriques)) {
                        $this->view->setVar("tableSousRubrique_" . $rubrique->id_rubrique, $tableSsRubriques);
                    }
                }
            }
        }

        // Tabs Recent et Popular
        $tab = "help-recent";

        $tableRecent = $this->getTableContent($this->webzineHelp, "last-article", "date_redaction", $this->type);
        $tablePopular = $this->getTableContent($this->webzineHelp, "last-article", "compteur", $this->type);

        // Link PDF

        $libPdf = $this->translater->get("AIDE_7");

        $urlPdf = "pdf/" . $this->type . "/";
        if ($numrub > 0) {
            $urlPdf = "pdf/" . $this->type . "/$numrub/";
            $libPdf = $this->translater->get("AIDE_8");
        }

        $linkPdf = $this->url->getUrl($urlPdf);

        $this->view->setVar("tab", $tab);
        $this->view->setVar("tablePopular", $tablePopular);
        $this->view->setVar("tableRecent", $tableRecent);
        $this->view->setVar("linkPdf", $linkPdf);
        $this->view->setVar("libPdf", $libPdf);
    }

    public function contentAction()
    {
        $ok = false;

        if (!$this->view->isV4) $this->view->bodyClass .= " white";

        $numart = $this->dispatcher->getParam("numart", "int");

        if ($numart > 0) {
            $article = $this->help->getArticle($numart, $this->request->get("vide_cache"));

            if (!empty($article) && ($article->etat == "" || $article->etat == "invis")) {
                /*
                 * On n'affiche que les articles publiés ou invisibles
                 */

                // On incremente le compteur si on n'a pas deja fait dans la session
                $helpArticlesReaded = $this->session->get("helpArticlesReaded");
                if (empty($helpArticlesReaded)) {
                    $helpArticlesReaded = [];
                }
                if (!in_array($article->id_article, $helpArticlesReaded)) {
                    $article->incrementeCompteur();
                    $helpArticlesReaded[] = $article->id_article;
                    $this->session->set("helpArticlesReaded", $helpArticlesReaded);
                }

                $this->view->setVar("article", $article);

                $rubrique = Rubrique::get($article->getWebzine(), $article->id_rubrique, null, false);
                $this->view->setVar("rub", $rubrique);

                // On recup la rub mere si yen a une pour breadcrump
                if ($rubrique->childof > 0) {
                    $rubParent = \Common\Models\Rubrique::get($article->getWebzine(), $rubrique->childof, null, false);

                    $this->view->setVar("rubParent", $rubParent);
                }

                $this->view->setVar("details", $article->getFormattedDetails($this->webzine));

                // Dans la meme rubrique
                $tableSameRubrique = $this->getTableContent($rubrique, "article", "same-rubrique", $this->type, array("childof" => 0, "exception" => $article->id_article));

                if (!empty($tableSameRubrique)) {
                    $this->view->setVar("tableSameRubrique", $tableSameRubrique);
                }

                $ok = true;

            }
        }

        if (!$ok) {
            return $this->response->redirect($this->url->getRedirectUrl("settings/help/"));
        }
    }

    /**
     * Renvoie tableau d'aide (soit article soit rubrique)
     * @param object $parent Le modèle parent de la liste
     * @param string $type article|rubrique
     * @param string $fromWhere help|helpdev
     * @param integer $id ID du tableau
     * @param array $array Params : Childof pour la rubrique, exception ID a avoir si jveux enlever un élément du tableau
     * @return array              Tableau d'éléments
     */
    private function getTableContent($parent, $type, $id, $fromWhere, $array = array())
    {
        if (!isset($array["childof"])) $array["childof"] = 0;
        if (!isset($array["exception"])) $array["exception"] = 0;

        if ($type == "rubrique") {
            // Rubriques
            $rows = $this->help->getRubriquesForRubrique($parent, $array["childof"]);
        } elseif ($type == "article") {
            // Articles 
            $rows = $this->help->getArticlesForRubrique($parent, "ponderation ASC");
        } else {
            // Last articles
            $rows = $this->help->getArticlesForRubrique($parent, $id . " DESC", 5);
        }

        if ($rows && $rows->count() > 0) {
            $tableArray = array();
            $tableArray["rows"] = array();
            $tableArray["container"] = "table-$id";
            $tableArray["header"] = array(
                array("content" => "&nbsp;")
            );
            if ($type != "last-article") {
                $tableArray["header"][] = array("content" => "&nbsp;", "class" => "delete");
            }
            $tableArray["hideHeader"] = true;

            foreach ($rows as $row) {
                if ($type == "last-article") {
                    $rub = Rubrique::get($parent, $row->id_rubrique, null, false);
                    $parent->numrub = $rub->numrub;
                }

                $link = "<a href=\"" . $this->url->getUrl("settings/$fromWhere/" . ($type != "rubrique" ? $parent->numrub . "/" . $row->numero . "/" : $row->numrub . "/")) . "\">[LINK]</a>";
                $row_id = ($type != "rubrique" ? $row->id_article : $row->id_rubrique);

                if (empty($array["exception"]) || (!empty($array["exception"]) && $row_id != $array["exception"])) {
                    $tableArray["tr_rows"][$row_id]["class"] = "list-help";
                    $tableArray["rows"][$row_id] = array(str_replace("[LINK]", ($type != "rubrique" ? ($type == "last-article" ? "<i class='fa " . (!$this->webzine->isV4() ? 'fa-file-text' : 'fa-file-o') . "'>&nbsp;</i> " : "") . $row->titre : $row->libelle), $link));
                    if ($type != "last-article") {
                        $tableArray["rows"][$row_id][] = str_replace("[LINK]", "<i class='fa fa-angle-right'>&nbsp;</i>", $link);
                    }
                }
            }

            return $tableArray;
        } else {
            return false;
        }
    }

    public function autoCompleteHelpAction()
    {
        $this->view->disable();

        $term = $this->request->get("term", "striptags");

        $retu_art = $retu_rub = array();

        $rubriques = $this->webzineHelp->getRubrique(array(
                "conditions" => "etat='' AND libelle ILIKE :term:" . $this->help->getSqlForbiddenRubriquesForGroup(),
                "bind" => array("term" => "%" . trim($term) . "%"),
                "order" => "position ASC"
            )
        );

        // Articles 
        $articles = \Common\Models\Article::find(array(
                "conditions" => "id_webzine=:id_webzine: AND etat='' AND titre ILIKE :term:" . $this->help->getSqlForbiddenArticlesForGroup(),
                "bind" => array("id_webzine" => $this->webzineHelp->id_webzine, "term" => "%" . trim($term) . "%"),
                "order" => "ponderation ASC",
                "gardefous" => false
            )
        );

        if (!empty($rubriques)) {
            $i = 0;
            foreach ($rubriques as $res) {
                if ($i < 10) {
                    $tmp = array();
                    $tmp["label"] = trim($res->libelle, 80);
                    $tmp["term"] = $term;
                    $tmp["numrub"] = $res->numrub;
                    $retu_art[] = $tmp;
                }
                $i++;
            }
        }

        if (!empty($articles)) {
            $i = 0;
            foreach ($articles as $res) {
                if ($i < 10) {
                    $tmp = array();
                    $tmp["label"] = trim($res->titre, 80);
                    $tmp["term"] = $term;
                    $tmp["numart"] = $res->numero;
                    $retu_rub[] = $tmp;
                }
                $i++;
            }
        }

        $retu = array_merge($retu_art, $retu_rub);

        $this->response->setContent(json_encode($retu))->send();
    }

    public function searchAction()
    {
        // Initialisation page
        if (!$this->request->hasPost("page")) {
            $this->view->page = 1;
        }

        // Pour cesure
        $formatter = new \Common\Lib\Formatter();

        $key = $this->request->get("key", "striptags");

        $search = new \Common\Lib\Search();
        $x = array();
        $x["words"] = $key;
        $x["field"] = FALSE;
        $xwords = array($x);

        $search->search_age_ponderation = 1;

        $results = $search->getResults($xwords, $this->webzineHelp->id_webzine, 0, '', 'article');

        $rows = array();
        if (!empty($results)) {
            foreach ($results as $result) {
                $article = \Common\Models\Article::findFirstWithoutGardefous(array(
                        "conditions" => "id_article=:id_article:" . $this->help->getSqlForbiddenArticlesForGroup(),
                        "bind" => array("id_article" => $result["key"]),
                    )
                );
                if ($article) {
                    $content = "<div class='bloc_summary'>\n";
                    $content .= "<div class='title_rub'>\n";
                    $content .= "<a href='" . $this->url->getUrl("settings/" . $this->type . "/0/" . $article->numero . "/") . "''>" . $article->titre . "</a>\n";
                    $content .= "</div>\n";
                    $content .= "<p>" . $formatter->cesure($article->first_detail, 300) . "</p>\n";
                    $content .= "</div>\n";

                    $rows[] = $content;
                }
            }

            $paginator = new \Common\Lib\PaginatorNativeArray(array(
                "data" => $rows,
                "limit" => 10,
                "page" => $this->view->page
            ));

            $pagination = $paginator->getPaginate();

            $arrayPagination = array();
            $arrayPagination["pagination"] = $pagination;
            $arrayPagination["container"] = "help-content";
            $arrayPagination["refreshUrl"] = $this->url->getUrl("settings/help/") . "?key=" . urlencode($key);

            $this->view->results = $pagination->items;
            $this->view->total = $pagination->total_items;
            $this->view->arrayPagination = $arrayPagination;

            if ($this->request->hasPost("page")) {
                $this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
            }
        } else {
            $this->view->total = 0;
        }

        $this->view->key = $key;
    }

    /**
     * Save in base the evaluation of customer for help page
     * @return string
     */
    public function customersSatisfactionAction()
    {
        $this->view->disable();

        if (!$this->request->isAjax() || !$this->request->isPost()) return "";

        $help_number = $this->request->get("help-number", "striptags");
        $evaluation = $this->request->get("evaluation", "striptags");
        $message = $this->request->get("message", "striptags");

        /** @var WmUser $user */
        $user = $this->auth->getUser();

        $onlineHelpEvaluationManager = new OnlineHelpEvaluationManager($this->webzine);

        $onlineHelpEvaluationManager->addOrUpdateEvaluation($user, $help_number, $evaluation, $message);

        return "";
    }

    /**
     * Get the correct academy link
     * @return string the correct link
     */
    private function _getAcademyLink()
    {
        return ($this->auth->getLanguage() === "fr") ? "https://academy.fr.goodbarber.com" : "https://academy.goodbarber.com";
    }

    /**
     * Get the correct showcases link
     * @return string the correct link
     */
    private function _getShowcasesLink()
    {
        $array = [
            $this->config->glBlogs["fr"] => 10,
            $this->config->glBlogs["es"] => 5,
            $this->config->glBlogs["it"] => 5,
            $this->config->glBlogs["pt"] => 16,
            $this->config->glBlogs["en"] => 10
        ];

        $id_webzine_blog = (!empty($this->config->glBlogs[$this->auth->getLanguage()]) ? $this->config->glBlogs[$this->auth->getLanguage()] : $this->config->glBlogs["en"]);

        foreach ($array as $id_webzine => $num_rub) {
            if ($id_webzine === $id_webzine_blog) {
                $webzine_blog = Webzine::findFirst($id_webzine_blog);
                $rub = Rubrique::findByNumero($webzine_blog, $num_rub);

                return Article::convertGBBlogUrl($rub->getRewritedUrl());
            }
        }
    }

    /**
     * Get the correct link according to the webzine language and the category provided
     * @param string $category the category of the link needed
     * @return string the correct link
     */
    private function _getLink($category)
    {
        $linkLanguage = (in_array($this->auth->getLanguage(), array_keys($this->config->glPortals->toArray()))) ? $this->auth->getLanguage() : "www";
        $link = "https://" . $linkLanguage . "." . $this->config->gl_url_goodbarber;

        if (in_array($category, ["videos", "blog"])) {
            $link .= "/" . $category . "/";
            if ($category === "videos" && $this->webzine->isShopPlan()) $link .= "shop/";

            return $link;
        }
    }
}

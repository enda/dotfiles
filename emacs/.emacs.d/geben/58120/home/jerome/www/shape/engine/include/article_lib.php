<?php

/**
 * @date 18/11/05
 * @author Alexandre Malsch - alexandre@reagi.com
 */
class melty_lib_article extends melty_lib
{
    const ETAT_DELETED = -100;
    const ETAT_CREATED = -1;
    const ETAT_WRITED = 0;
    const ETAT_PUBLISHED = 1;

    const SHARE_NAME_FACEBOOK = 'facebook';
    const SHARE_NAME_TWITTER = 'twitter';
    const SHARE_NAME_PLUSONE = 'plusone';
    const SHARE_NAME_LIVE = 'live';
    const SHARE_STATUS_ENABLED = 'enabled';
    const SHARE_STATUS_DISABLED = 'disabled';
    const SHARE_STATUS_FB_LIKE = 'like';
    const SHARE_STATUS_FB_RECOMMEND = 'recommend';

    const URL_REGEXP = '#\-a(?:ctu)?[0-9]+(?:\.)?(?:html)?$#';
    const URL_REGEXP_ID = '#\-a(?:ctu)?([0-9]+)\.html$#';

    const MEDIA_PROTECTION_UNSET = 0;
    const MEDIA_PROTECTION_ENABLED = 1;
    const MEDIA_PROTECTION_DISABLED = 2;

    const GET_URL_RANDOM_MARGIN = 20; // In percents

    public function __construct()
    {
        parent::__construct();
        $this->hl_c_set_default_table('system2_article');
    }

    // @todo il faut delete ca quand shape_header sera OK
    public function switch_thema($id_article, $id_thema)
    {
        $q = 'UPDATE system2_article SET id_thema = ' . (int)$id_thema
            . ' WHERE id = ' . (int)$id_article . ' LIMIT 1';

        $sql_update = $this->sql->query($q);
        if ($sql_update === FALSE)
            return Errno::DB_ERROR;
        return Errno::OK;
    }

    public function switch_all_thema($id_thema_from, $id_thema_to)
    {
        $q = 'UPDATE system2_article SET id_thema = ' . (int)$id_thema_to
            . ' WHERE id_thema = ' . (int)$id_thema_from;

        $sql_update = $this->sql->query($q);
        if ($sql_update === FALSE)
            return Errno::DB_ERROR;
        return Errno::OK;
    }

    public function get_id_from_url($url)
    {
        preg_match(self::URL_REGEXP_ID, $url, $matches);
        if (!empty($matches) || !isset($matches[1]))
            return $matches[1];
        return FALSE;
    }

    // Format title
    public function format_title($id_article, $titre, $format_url = FALSE)
    {
        xdebug_break();
        // formatage du titre d'un article
        $titre = str_replace('<i>', '', $titre);
        $titre = str_replace('<I>', '', $titre);
        $titre = str_replace('</i>', '', $titre);
        $titre = str_replace('</I>', '', $titre);

        $titre = str_replace('<b>', '', $titre);
        $titre = str_replace('<B>', '', $titre);
        $titre = str_replace('</b>', '', $titre);
        $titre = str_replace('</B>', '', $titre);

        $url_t = NULL;
        if ($format_url === TRUE)
        {
            if ($_ENV['ARTICLE_NEW_URL'] === TRUE)
            {
                $url_t = str_replace('&', trans('et'), $titre);
                $url_t = rewrite2($url_t, FALSE, NULL);
                $url_t = !empty($url_t) ? $url_t : 'a-la-une';
                $url_t = $url_t . '-a' . $id_article . '.html';
            }
            else
            {
                $url_t = str_replace('&', trans('et'), $titre);
                $url_t = rewrite2($url_t);
                $url_t = !empty($url_t) ? $url_t : 'a-la-une';
                $url_t = $url_t . '-actu' . $id_article . '.html';
            }
        }

        return ["titre" => $titre,
                "url" => $url_t];
    }

    /**
     * Donne l'url d'un article. Utilisez Melty_RAII_Router_Instance pour
     * specifier l'instance a utiliser.
     *
     * @param int $id_article
     * @param string $url
     * @param string $c_origine
     *
     */
    public static function get_url($id_article, $url, $c_origine = NULL)
    {
        if (Melty_Helper_URL::isValid($url) === TRUE)
            return $url;

        if ($id_article && $url)
            $path = $url;
        elseif ($url)
            $path = $url . '/';
        else
            $path = 'actu';
        if ($c_origine !== NULL && $c_origine != $_ENV['INSTANCE'])
            $resSite = new Melty_RAII_Router_Instance($c_origine);
        Melty_Factory::getRouter()->changeSubDomain($_ENV['SUBDOMAIN_DEFAULT']);
        $re = Melty_Factory::getRouter()->base() . $path;
        Melty_Factory::getRouter()->restoreSubDomain();
        if (isset($resSite))
            unset($resSite);

        return $re;
    }

    public function get_url_thema($url)
    {
        return $this->get_url(NULL, $url);
    }

    // check si l'article est de ce flag
    public function checkFlag($from, $flag, $flag_type = NULL)
    {
        // from ID or tab_i_flag ?
        if (is_array($from) === FALSE)
        {
            $tab_article = $this->get_articles($from, self::ETAT_CREATED);
            $from = $tab_article['data'][0]['tab_i_flag'];
        }

        // flag_type is specified ?
        if ($flag_type !== NULL)
        {
            foreach ($from[$flag_type] as $row)
                if ($flag == $row)
                    return TRUE;
            return FALSE;
        }
        if (is_array($from))
        {
            foreach (array_keys($from) as $key)
            {
                foreach ($from[$key] as $row)
                    if ($flag == $row)
                        return TRUE;
            }
        }
        return FALSE;
    }

    public function create_extern($site, $id_article_extern)
    {
        whine_unused("2014/08/01");
        $id_article_intern = $this->create();

        $q = 'INSERT INTO system5_article_extern_link
                   (site, id_article_extern, id_article_intern)
              VALUES (' . $this->sql->quote($site) . ', ' .
            (int)$id_article_extern . ', ' . (int)$id_article_intern . ')';

        if ($this->sql->query($q) === FALSE)
            return Errno::DB_ERROR;

        return $id_article_intern;
    }

    public function create()
    {
        $id_membre = $_SESSION->get_id_membre();

        if (empty($id_membre))
            return Errno::FIELD_EMPTY;

        if (isset($_ENV['MUM_ARTICLE_DOMAIN'])
            && $_ENV['MUM_ARTICLE_DOMAIN'] != '')
        {
            $tmp = explode(',', $_ENV['MUM_ARTICLE_DOMAIN']);
            $q_domain = array_merge([$_ENV['INSTANCE']], $tmp);
        }
        else
        {
            $q_domain = [$_ENV['INSTANCE']];
        }

        $q = 'INSERT INTO system2_article
              (' . $this->c_f() . ', date, create_date,
                  id_membre, etat)
              VALUES (' . $this->c_v($q_domain) . ',
            NOW(),
            NOW(), ' .
            (int)$id_membre . ', ' .
            (int)self::ETAT_CREATED . ')';

        if ($this->sql->query($q) !== FALSE)
        {
            $id_article = $this->sql->lastInsertId();

            Melty_Factory::getMUM()->addToInstance(
                Melty_MUM::SITE_TYPE_ARTICLE, $id_article, $q_domain);

            $q = "INSERT IGNORE INTO system2_article_hits
                   (id_article, hits)
            VALUES (" . (int)$id_article . ", 1)";

            if ($this->sql->query($q) !== FALSE)
                return $id_article;
        }
        return -1;
    }

    public function alarache($tab_post)
    {
        $alarache = str_replace("\r\n", "\n", $tab_post['alarache']);

        unset($tab_post['alarache']);

        $alarache = str_replace('<BR>', '<br>', $alarache);
        $alarache = str_replace('<BR />', '<br />', $alarache);

        $alarache = str_replace('<br>', '<br />', $alarache);
        $alarache = str_replace("<br />\n", '<br />', $alarache);
        $alarache = str_replace("\n<br />", '<br />', $alarache);
        $alarache = str_replace("\n", '<br />', $alarache);

        $tab_tmp = explode("<br />", $alarache);

        // pour forcer meme sans thema !
        // on autorise donc de MAJ une premiere fois sans thema
        $tab_post['force_without_thema'] = 1;

        $i = 0;
        if (!isset($tab_post['texte']))
            $tab_post['texte'] = '';
        foreach ($tab_tmp as $row)
        {
            $r = trim($row);
            if (!empty($r))
            {
                if ($i == 0)
                    $tab_post['titre'] = $r;
                if ($i == 1)
                    $tab_post['resum'] = $r;
                if ($i >= 2)
                {
                    $tab_post['texte'] .= $r;
                    $tab_post['texte'] .= '<br /><br />';
                }
                $i++;
            }
        }
        return $tab_post;
    }

    public function update($id_article, $tab_post, $is_an_edit = FALSE)
    {
        if (empty($id_article) || empty($tab_post))
            return Errno::FIELD_EMPTY;

        if (!$_SESSION->is_log())
            return Errno::NEED_LOG;

        xdebug_break();
        $tab_article = $this->get_one($id_article);
        xdebug_break();

        if (empty($tab_article['id_article']))
            return Errno::FIELD_INVALID;

        if (empty($tab_post['id_thema']) && empty($tab_post['force_without_thema']))
            return Errno::FIELD_EMPTY;

        // Formatage du titre + URL
        $tab_titre = $this->format_title($id_article,
                                         isset($tab_post['titre']) ? $tab_post['titre'] : '',
                                         TRUE);

        // Si l'article a ete publié au moins une fois,
        // on conserve la derniere URL qui fut set
        if (!empty($tab_article['published_date']))
            $tab_titre['url'] = $tab_article['url'];

        xdebug_break();
        //mais si on a forcé le changement de l'url, alors ...
        if (!empty($tab_post['url_forced']) && $tab_titre['url'] != $tab_article['url'])
        {
            xdebug_break();
            //#\-a(?:ctu)?[0-9]+(?:\.)?(?:html)?$#
            $url_forced = preg_replace(self::URL_REGEXP, '', $tab_post['url_forced']);
            $tab_titre_forced = $this->format_title($id_article, $url_forced, TRUE);
            $tab_titre['url'] = $tab_titre_forced['url'];
        }

        if (isset($tab_post['tag_list']))
            $this->lib['tag']->update($tab_post['tag_list'], 'article', $id_article);

        // On met a jour le champs date a chaque fois que l'article est mis a jour,
        // et que son etat actuel est inferieur a publier (writing | writed)
        $sq_date = $tab_article['etat'] < self::ETAT_PUBLISHED ?
            'date = NOW(),' : '';

        /*
         * Si le redacteur a specifier une publication_date, dans ce
         * cas on rempli le champs pub_date
         */
        $sq_pub_date = '';
        if ($tab_article['etat'] < self::ETAT_PUBLISHED)
        {
            $sq_pub_date = 'NULL';
            if (isset($tab_post['diffusion']))
            {
                //Quoi qu'il arrive, on unlink l'article de tt les events
                //lorsqu'on choisi le type de diffusion
                $this->lib['calendar']->unlink_event('article', $id_article);
                if (isset($tab_post['pub_date'])
                    && $tab_post['diffusion'] === 'pub_date'
                    && $tab_post['pub_date'])
                {
                    $sq_pub_date = $this->sql->quote($tab_post['pub_date']);
                }
                elseif ($tab_post['diffusion'] === 'event'
                        && isset($tab_post['id_event'])
                        && (int)$tab_post['id_event'] > 0)
                {
                    $events = $this->lib['calendar']->get_event($tab_post['id_event']);
                    if (is_array($events))
                    {
                        $event = current($events);
                        $ret = $this->lib['calendar']->link_event($tab_post['id_event'],
                                                                  'article',
                                                                  $id_article);
                        if ($ret > 0)
                            $sq_pub_date = $this->sql->quote($event['start']);
                    }
                }
            }
            $sq_pub_date = 'pub_date = ' . $sq_pub_date . ',';
        }

        $sq_edit_date = $is_an_edit ? 'edit_date = NOW(),' : '';

        $sq_author = !empty($tab_post['author']) ?
            'author = ' . $this->sql->quote($tab_post['author']) . ', ' : '';

        // FORMATAGE DU TEXTE
        $texte = nl2br($tab_post['texte']);
        $texte_link = $this->link($texte);
        $tab_format = $this->format($texte_link, 0, 1);

        // Forcing area
        // Flag forcing
        $update_flags = TRUE;
        if (array_key_exists('i_flag_flag', $tab_post) === FALSE
            && array_key_exists('i_flag_display', $tab_post) === FALSE
            && array_key_exists('i_flag_type', $tab_post) === FALSE)
            $update_flags = FALSE;

        if ($update_flags === TRUE)
        {
            $tab_i_flag_flag = isset($tab_post['i_flag_flag']) ?
                explode(',', $tab_post['i_flag_flag'])
                : [];
            $partenaires = $this->lib['partenaires']->getLinks($texte);
            if ($partenaires !== NULL && !empty($partenaires))
                array_push($tab_i_flag_flag, 'selection_recette');
            $tab_post['i_flag_flag'] = implode(',', $tab_i_flag_flag);

            // Display forcing
            // Force video if article gallery contain one

            $has_video = $this->lib['media']->galery_contain_media_type($tab_article['id_galerie'],
                                                                        'article',
                                                                        'video',
                                                                        0);
            if (!$has_video && !empty($tab_format['data']))
            {
                foreach ($tab_format['data'] as $p)
                {
                    if (isset($p['type'])
                        && (($p['type'] == 'pexterne'
                             && $this->contain_external_video($p['data']))
                            || ($p['type'] == 'pexternmedia' &&
                                $p['type_media'] == 'video')))
                    {
                        $has_video = TRUE;
                        break;
                    }
                }
            }
            if ($has_video)
                $tab_post['i_flag_display'] = 'video';

            // Gestion des i_flags
            // i_flag_flag = type d'article (sexy, choc, ..)
            // i_flag_display = display de l'article dans un fil (image, video, classic, ..)
            // i_flag_type = type de l'article (poster, test, classic)
            $i_flag = !empty($tab_post['i_flag_flag']) ? $tab_post['i_flag_flag'] : '';
            $i_flag = !empty($tab_post['i_flag_display']) ? $i_flag . ',' . $tab_post['i_flag_display'] : $i_flag;
            $i_flag = !empty($tab_post['i_flag_type']) ? $i_flag . ',' . $tab_post['i_flag_type'] : $i_flag;
        }

        if (!isset($tab_post['alt_url']))
            $tab_post['alt_url'] = '';

        if (!isset($tab_post['id_thema']))
            $tab_post['id_thema'] = 0;

        if (!isset($tab_post['source']))
            $tab_post['source'] = '';

        if (!isset($tab_post['credit']))
            $tab_post['credit'] = '';

        if (!isset($tab_post['resum']))
            $tab_post['resum'] = '';

        if (!isset($tab_post['reaction']))
            $tab_post['reaction'] = '';

        $sq_censored_in_homepage = '';
        if (isset($tab_post['censored_in_homepage']))
            $sq_censored_in_homepage = "censored_in_homepage = " . $this->sql->quote((bool)$tab_post['censored_in_homepage'],
                                                                                     PDO::PARAM_BOOL) . ", ";

        $sq_censored_in_homepage_master = '';
        if (isset($tab_post['censored_in_homepage_master']))
            $sq_censored_in_homepage_master = "censored_in_homepage_master = " . $this->sql->quote((bool)$tab_post['censored_in_homepage_master'],
                                                                                                   PDO::PARAM_BOOL) . ", ";
        $sq_anti_protection = "";
        if (isset($tab_post['anti_protection']))
            $sq_anti_protection = "anti_protection = " . (int)$tab_post['anti_protection'] . ",";

        $sq_author_social_link = "";
        if (isset($tab_post['author_social_link']))
            $sq_author_social_link = "author_social_link = " . (int)$tab_post['author_social_link'] . ",";

        $sq_event_date = "";
        if (isset($tab_post['event_date']))
            $sq_event_date = "event_date = " . $this->sql->quote($tab_post['event_date']) . ",";

        $sq = "UPDATE system2_article
            SET titre = " . $this->sql->quote($tab_titre['titre']) . ",
                aresum = " . $this->sql->quote($tab_post['resum']) . ",
                url = " . $this->sql->quote($tab_titre['url']) . ",
                alt_url = " . $this->sql->quote($tab_post['alt_url']) . ",
                id_thema = " . (int)$tab_post['id_thema'] . ",
                source = " . $this->sql->quote($tab_post['source']) . ",
                " . (!empty($tab_post['social_title']) ? "social_title = " . $this->sql->quote($tab_post['social_title']) . "," : "") . "
                credit = " . $this->sql->quote($tab_post['credit']) . ",
                " . ($update_flags === TRUE ? "i_flag = " . $this->sql->quote($i_flag) . "," : '') . "
                last_date = NOW(),
                " . $sq_date . "
                " . $sq_edit_date . "
                " . $sq_pub_date . "
                " . $sq_author . "
                " . $sq_anti_protection . "
                " . $sq_censored_in_homepage . "
                " . $sq_censored_in_homepage_master . "
                " . $sq_author_social_link . "
                " . $sq_event_date . "
                texte = " . $this->sql->quote($texte_link) . ",
                texte_save = " . $this->sql->quote($texte) . ",
                reaction = " . $this->sql->quote($tab_post['reaction']) . "
        WHERE id = " . (int)$id_article . "
        LIMIT 1";

        $sql_update = $this->sql->query($sq);
        if ($sql_update === FALSE)
            return Errno::DB_ERROR;

        // Si on etait en mode reserve / pub_date
        // Et que : on veux afficher l'article en temps reel
        // On force tout le bazar comme il faut
        if ($tab_article['etat'] == self::ETAT_WRITED && $tab_post['diffusion'] == melty_lib_todo::PRIO_TEMPS_REEL)
            $this->update_status($id_article, self::ETAT_CREATED);

        // Gestion de la galerie
        if (!empty($tab_article['id_galerie']))
        {
            // Si on a changer le titre, on synchronise celui de la galerie
            if ($tab_article['titre'] != $tab_titre['titre'])
            {
                $tab_galerie['nom_type'] = $tab_titre['titre'];
                $tab_galerie['url_type'] = $tab_titre['url'];
                $tab_galerie['nom'] = $tab_titre['titre'];
                $this->lib['media']->galerie_update($tab_article['id_galerie'],
                                                    'article',
                                                    $tab_article['id_article'],
                                                    $tab_galerie);
            }
        }

        if (isset($tab_post['address']) && is_array($tab_post['address']))
        {
            $this->lib['geocoding']->setInfos('article', $id_article, $tab_post['address']);
        }

        if (isset($tab_post['share']))
            $this->set_share_options($id_article, $tab_post['share']);

        // Trigger articleChanged
        Melty_Factory::getAspect()->ArticleChanged(Array('id_article' => $id_article));

        // update du social title pour facebook
        if (empty($tab_post['social_title']) === FALSE)
        {
            $tab_article = $this->get_referencement($id_article);
            $post_params = 'id=' . urlencode($tab_article['published_url_article'])
                         . '&scrape=true';
            Melty_Helper_Network::post('http://graph.facebook.com/', $post_params);
        }

        Melty_Teddy::add($_ENV['CACHE_VARNISH_DELETE'], $id_article . '.html');
        return $id_article;
    }

    public function update_one($id_article, $key, $value)
    {
        if (empty($id_article) || empty($key))
            return Errno::FIELD_EMPTY;

        if (!$_SESSION->is_log())
            return Errno::NEED_LOG;

        $tab_article = $this->get_one($id_article);

        if (empty($tab_article['id_article']))
            return Errno::FIELD_INVALID;

        $s_value = '';

        if ($key == 'i_flag_display')
        {
            $key = 'i_flag';
            if (!empty($tab_article['tab_i_flag']['flag']))
                foreach ($tab_article['tab_i_flag']['flag'] as $row)
                    $s_value .= $row . ',';
            if (!empty($tab_article['tab_i_flag']['type']))
                foreach ($tab_article['tab_i_flag']['type'] as $row)
                    $s_value .= $row . ',';

            if (!empty($value))
                $value = $s_value . $value;
            else
                $value = $s_value;
        }
        elseif ($key == 'i_flag_flag')
        {
            $key = 'i_flag';
            if (!empty($tab_article['tab_i_flag']['display']))
                foreach ($tab_article['tab_i_flag']['display'] as $row)
                    $s_value .= $row . ',';
            if (!empty($tab_article['tab_i_flag']['type']))
                foreach ($tab_article['tab_i_flag']['type'] as $row)
                    $s_value .= $row . ',';

            if (!empty($value))
            {
                $tmpv = $s_value;
                foreach ($value as $r)
                    $tmpv .= $r . ',';
                $value = $tmpv;
            }
            else
                $value = $s_value;
        }
        elseif ($key == 'resum')
        {
            $key = 'aresum';
        }
        elseif ($key == 'alt_url')
        {
            ;
        }
        elseif ($key == 'titre')
        {
            if (empty($value))
                return Errno::FIELD_INVALID;

            $value = $this->format_title($id_article, $value)['titre'];
        }
        elseif ($key == 'id_thema')
        {
            $value = intval($value);
            if ($value <= 0)
                return Errno::FIELD_INVALID;
        }
        elseif ($key == 'pub_date')
        {
            ;
        }
        elseif ($key == 'censored_in_homepage'
                || $key == 'censored_in_homepage_master')
        {
            ;
        }
        elseif ($key == 'id_world_index')
        {
            $this->lib['world']->set_link_to_one('article', $id_article, $value, 1, FALSE);
            $value = (int)$value;
        }
        else
            return -5;

        $sq = "UPDATE system2_article
                  SET " . $key . " = " . $this->sql->quote($value) . ",
                      last_date = NOW()
                WHERE id = " . (int)$id_article . "
                LIMIT 1";

        $sql_update = $this->sql->query($sq);
        if ($sql_update === FALSE)
            return Errno::DB_ERROR;
        return $id_article;
    }

    public function set_flags($id_article, $flags)
    {
        $tab_article = $this->get_referencement($id_article);
        $current_flags = explode(',', $tab_article['i_flag']);
        $tab_flags = explode(',', $flags);

        foreach ($tab_flags as $flag)
        {
            $action = ((substr($flag, 0, 1) == "-") ? 'del' : 'add');
            $flag = $action == 'del' ? substr($flag, 1) : $flag;
            $position = array_search($flag, $current_flags);

            if ($action == 'del' && $position !== FALSE)
                array_splice($current_flags, $position, 1);
            else if ($action == 'add' && $position === FALSE)
                $current_flags[] = $flag;
        }

        $sq = 'UPDATE system2_article'
            . ' SET i_flag = ' . $this->sql->quote(implode(',', $current_flags))
            . ' WHERE id = ' . (int)$id_article
            . ' LIMIT 1';

        $sql_update = $this->sql->query($sq);
        if ($sql_update === FALSE)
            return Errno::DB_ERROR;
        return 1;
    }

    // Fonction qui  change le title
    // system2_article + system2_galerie
    public function set_title($id_article, $tab_titre)
    {
        $sq = 'UPDATE system2_article'
            . ' SET titre = ' . $this->sql->quote($tab_titre['titre']) . ', '
            . ($tab_titre['url'] ? ('url = ' . $this->sql->quote($tab_titre['url']) . ', ') : "")
            . ' last_date = NOW()'
            . ' WHERE id = ' . (int)$id_article
            . ' LIMIT 1';

        $sql_update = $this->sql->query($sq);
        if ($sql_update === FALSE)
            return Errno::DB_ERROR;
        return 1;
    }

    // Fonction qui change l'id_membre du proprietaire de l'article
    // system2_article + system2_galerie
    public function set_author($id_article, $id_membre)
    {
        $q_article = "UPDATE system2_article
                         SET id_membre = " . (int)$id_membre . "
                       WHERE id = " . (int)$id_article . "
                       LIMIT 1 ";

        $sql_article = $this->sql->query($q_article);
        if ($sql_article === FALSE)
            return Errno::DB_ERROR;

        $q_galerie = "UPDATE system2_galerie
                         SET id_membre = " . (int)$id_membre . "
                       WHERE id_type = " . (int)$id_article . " AND type = 'article'
                       LIMIT 1 ";
        $sql_galerie = $this->sql->query($q_galerie);
        if ($sql_galerie === FALSE)
            return Errno::DB_ERROR;

        return 1;
    }

    public function update_status($id_article, $etat = NULL)
    {
        $tab_article = $this->get_one($id_article);
        // Si pas de thema, on va pas plus loin !
        if (!$tab_article['id_thema'])
            return (-200);

        $tab_todo = $this->lib['todo']->get_one(0, 'article', $id_article);

        // On tente de determiner le etat aproprié
        if ($etat === NULL)
        {
            $etat = self::ETAT_CREATED;
            $id_prio = $tab_todo['id_prio'];

            if ($tab_article['etat'] == self::ETAT_CREATED)
            {
                $etat = self::ETAT_PUBLISHED;

                // Reserve ?
                if ($_ENV['ARTICLE_RESERVE'] === TRUE)
                    if ($id_prio == melty_lib_todo::PRIO_INTEMPOREL
                        || $id_prio == melty_lib_todo::PRIO_NORMAL
                        || $id_prio == melty_lib_todo::PRIO_URGENT)
                        $etat = self::ETAT_WRITED;

                // Pub_date ?
                if ($tab_article['pub_date'])
                {
                    if (strtotime($tab_article['pub_date']) < time())
                        return (-100);
                    $etat = self::ETAT_WRITED;
                }
            }
        }

        $errno = $this->val($id_article, $etat);
        if ($errno <= 0)
            return ($tab_article['etat']);

        // Todo part
        // On verifie que la todo est bien "synchro" avec le status d'un article
        if ($etat == self::ETAT_CREATED && $tab_todo['etat'] > melty_lib_todo::ETAT_LAYOUT)
            $this->lib['todo']->update_row($tab_todo['id_todo'], 'etat', melty_lib_todo::ETAT_LAYOUT);
        else
            $this->lib['todo']->update_row($tab_todo['id_todo'], 'etat', melty_lib_todo::ETAT_DONE);

        return ($etat);
    }

    public function val($id_article, $flag = self::ETAT_PUBLISHED,
                        $published_date = NULL)
    {
        if (empty($id_article))
            return Errno::FIELD_EMPTY;

        $tab_article = $this->get_one($id_article);

        if (!isset($tab_article['id_article'])
            || empty($tab_article['id_article']))
            return Errno::FIELD_INVALID;

        $sq_published_date = '';
        $sq_published_url = '';
        // On ne met a jour la published_date qu'une seule fois, si on repasse par cette fonction ca ne bouge plus :)
        if ($flag >= self::ETAT_PUBLISHED
            && $tab_article['published_date'] == NULL)
        {
            $sq_published_date = empty($published_date) ?
                'published_date = NOW(),' :
                'published_date = ' . $this->sql->quote($published_date) . ',';

            // On set published_url une seule fois, a la publication de l'article
            $sq_published_url = 'published_url = ' . $this->sql->quote($tab_article['url']) . ',';

            //check if we have some melty+wat medias in this article
            //to publish them also
            $this->checkWatMediaInArticle($id_article);
        }

        $sq = "UPDATE system2_article
            SET etat = " . (int)$flag . ",
                date = NOW(),
                " . $sq_published_date . "
                " . $sq_published_url . "
                val_date = NOW(),
                val_id_membre = " . (int)$_SESSION->get_id_membre() . "
            WHERE id = " . (int)$id_article . "
            LIMIT 1";

        $sql_update = $this->sql->query($sq);
        if ($sql_update === FALSE)
            return Errno::DB_ERROR;

        if ($flag >= self::ETAT_PUBLISHED
            && $tab_article['published_date'] == NULL)
        {
            try // Don't block the publication when something goes wrong (think Mongo)
            {
                // Notify all user for a new article
                Melty_Factory::getAspect()->ArticlePublished($this->get_for_teddy($id_article));
                Melty_Factory::getAspect()->ArticleFirstPublication(['id_article' => $id_article]);
            }
            catch (Melty_Exception_DatabaseError $e)
            {
            }
        }

        return 1;
    }

    public function checkWatMediaInArticle($id_article)
    {
        if ($id_article <= 0)
            return FALSE;

        $value = 'wat-cron-refill-argv-id_article-' . (int)$id_article;
        return (new Melty_Media)->rpcRequest('cron', $value, ['async' => TRUE]);
    }

    // enleve un media du tab_format, et le place dans un tab_media
    public function move_first_media(&$tab_article, $type = NULL, $ptype = NULL)
    {
        if ($ptype === NULL)
            $ptype = ['pmedia', 'pexterne', 'pexternemedia'];

        foreach ($tab_article['format']['data'] as $key => $row)
        {
            if (!isset($row['type']))
                continue;

            // on gere a l'arrache les pexterne (youtube)
            if (in_array($row['type'], $ptype))
            {
                if (isset($type)
                    && (!isset($row['media'])
                        || isset($row['media'])
                        && $tab_article['media']['order'][$row['media']]['format'] != $type))
                    continue;

                $tab_article['tab_first_media']['data'][] = $row;
                array_splice($tab_article['format']['data'], $key, 1);
                break;
            }
        }
    }

    public function move_first_paragraph(&$tab_article, $ptype = NULL)
    {
        if ($ptype === NULL)
            $ptype = ['pmedia', 'pexterne', 'pexternemedia'];

        if (isset($tab_article['format']['data'][0]))
        {
            $first_paragraph = $tab_article['format']['data'][0];
            if (isset($first_paragraph['type']))
            {
                if (in_array($first_paragraph['type'], $ptype))
                {
                    foreach($tab_article['format']['data'] AS $key => $paragraph)
                    {
                        if (!isset($paragraph['type']) || in_array($paragraph['type'], $ptype) === FALSE)
                            break;
                    }

                    array_splice($tab_article['format']['data'], $key, 1);
                    array_unshift($tab_article['format']['data'], $paragraph);
                }
            }
        }
    }

    // Fonction qui desactive un article
    public function delete($id_article)
    {
        if (empty($id_article))
            Return Errno::FIELD_EMPTY;

        $tab_article = $this->get_one($id_article);
        if (empty($tab_article['id_article']))
            return Errno::FIELD_INVALID;

        $tab_article['worlds'] = $this->lib['world']->get_my_index(
            melty_lib_world::TYPE_ARTICLE, $id_article);

        $this->sql->beginTransaction();

        $crud = new Melty_CRUD_MySQL('system2_article');
        $result = $crud->update(['etat' => self::ETAT_DELETED], ['id' => (int)$id_article]);

        try // Don't block when something goes wrong (think Mongo)
        {
            Melty_Factory::getAspect()->ArticleDeleted($tab_article);
        }
        catch (Melty_Exception_DatabaseError $e)
        {
            $this->sql->rollback();
            return FALSE;
        }

        $res = $this->sql->commit();
        if ($res === FALSE)
            return Ernno::DB_ERROR;

        Melty_Teddy::add($_ENV['CACHE_VARNISH_DELETE'], $id_article . '.html');
        return $result;
    }

    public function link($texte)
    {
        $allowed_tags = ['world', 'article', 'articlenum', 'worldnum'];

        preg_match_all("/\{([^\}]+)\}/", $texte, $tab);
        foreach ($tab[1] as $key => $row)
        {
            if (empty($row))
                break;

            $rowr = trim($row);
            $tab_exp = explode(' ', $rowr);

            if (!in_array($tab_exp[0], $allowed_tags, TRUE))
                continue;

            $search = str_replace($tab_exp[0], '', $rowr);
            $search = trim($search);

            $link = $search;
            if ($tab_exp[0] == 'world' || $tab_exp[0] == 'worldnum')
            {
                $tab_world = $this->lib['world']->get_index_one_lite($tab_exp[1]);

                if (!empty($tab_world['id_world_index']))
                {
                    $url = $tab_world['url_world_index'];
                    $link = '<a href=\'' . $url . '\' title=\'' . stripslashes($tab_world['nom']) . '\'>';
                    $link2 = stripslashes($tab_world['nom']);
                    foreach ($tab_exp as $k => $row)
                    {
                        if ($k == 2)
                            $link2 = stripslashes($row);
                        elseif ($k > 2)
                            $link2 .= ' ' . stripslashes($row);
                    }
                    $link .= $link2 . '</a>';
                }
            }
            elseif ($tab_exp[0] == 'article' || $tab_exp[0] == 'articlenum')// || $tab_exp[0] == 'an')
            {
                $tab_article = $this->get_lite($tab_exp[1]);
                $tab_article = $tab_article[0];
                if (!empty($tab_article['id_article']))
                {
                    $link = '';
                    $link = '<a href=\'' . $tab_article['url_article'] . '\' class=\'_skined\'>';

                    $link2 = stripslashes($tab_article['titre']);
                    foreach ($tab_exp as $k => $row)
                    {
                        if ($k == 2)
                            $link2 = stripslashes($row);
                        elseif ($k > 2)
                            $link2 .= ' ' . stripslashes($row);
                    }
                    $link .= $link2 . '</a>';
                }
            }
            $texte = str_replace($tab[0][$key], $link, $texte);
        }
        return $texte;
    }

    public function format($texte, $tab_media = 0, $space = 0)
    {
        if (empty($texte))
            return 0;
        $texte = str_replace("\r\n", "\n", $texte);
        $texte = str_replace("Clique pour modifier", "", $texte);

        $tab_tmp1 = explode("\n", $texte);

        $texteb = '';
        foreach ($tab_tmp1 as $row)
            $texteb .= trim($row);
        $texte = $texteb;

        $texte = str_replace('<BR>', '<br>', $texte);
        $texte = str_replace('<BR />', '<br />', $texte);

        $texte = str_replace('<br>', '<br />', $texte);
        $texte = str_replace("<br />\n", '<br />', $texte);
        $texte = str_replace("\n<br />", '<br />', $texte);
        $texte = str_replace('<br />', "\n", $texte);
        $texte = str_replace("\n[", "[", $texte);
        $texte = str_replace("]\n", "]", $texte);

        if ($space == 0)
        {
            $texte = str_replace("\n", "[p]", $texte);
        }
        else
        {
            $texte = str_replace("\n\n", "[p]", $texte);
        }
        $texte = str_replace("[/p]", '', $texte);

        $tab_tmp = explode("\n", $texte);

        $nbr_para = 0;
        $pmediatmp = 0;

        foreach ($tab_tmp as $line)
        {
            while (!empty($line))
            {
                if (stristr($line, '[p') !== FALSE)
                {
                    $start = mb_strpos($line, '[p') + 1;
                    $end = mb_strpos($line, ']') + 1;

                    if ($end < $start)
                    {
                        if (!isset($tab_format['data'][$nbr_para]['data']))
                            $tab_format['data'][$nbr_para]['data'] = '';
                        $tab_format['data'][$nbr_para]['data'] .= mb_substr($line, 0, $start - 1);

                        $line = mb_substr($line, $start - 1);

                        continue;
                    }

                    if ($end == 1)
                        $end = strlen($line);
                    if ($start > 1)
                    {
                        if (!isset($tab_format['data'][$nbr_para]['data']))
                            $tab_format['data'][$nbr_para]['data'] = '';
                        $tab_format['data'][$nbr_para]['data'] .= mb_substr($line, 0, $start - 1);
                    }
                    $tab_word = explode(' ', mb_substr($line, $start, ($end - 1) - $start));

                    // Le para ou on est deja set, donc on change pour pas écraser
                    if (!empty($tab_format['data'][$nbr_para]['data']) || !empty($tab_format['data'][$nbr_para]['end']))
                    {
                        $nbr_para++;
                        $tab_format['data'][$nbr_para]['data'] = '';
                    }

                    if ($tab_word[0] == 'pimg' && !empty($tab_word[1]))
                    {
                        if ($tab_media == 0 || ($tab_media != 0 && !empty($tab_media['order'][$tab_word[1]])))
                        {
                            $tab_format['data'][$nbr_para]['media'] = $tab_word[1];
                            $tab_format['data'][$nbr_para]['type'] = 'pimg';
                            $tab_format['data'][$nbr_para]['end'] = 1;
                        }
                    }
                    elseif ($tab_word[0] == 'pmedia' && !empty($tab_word[1]))
                    {
                        if ($tab_media == 0 || ($tab_media != 0 && !empty($tab_media['order'][$tab_word[1]])))
                        {
                            $tab_format['data'][$nbr_para]['media'] = $tab_word[1];
                            $tab_format['data'][$nbr_para]['type'] = 'pmedia';
                            $tab_format['data'][$nbr_para]['end'] = 1;
                        }
                    }
                    elseif ($tab_word[0] == 'pexternmedia')
                    {
                        $tab_format['data'][$nbr_para]['type_media'] = $tab_word[1];
                        $tab_format['data'][$nbr_para]['media'] = $tab_word[2];
                        $tab_format['data'][$nbr_para]['type'] = 'pexternmedia';
                        $tab_format['data'][$nbr_para]['end'] = 1;
                    }
                    elseif ($tab_word[0] == 'ptitre')
                    {
                        $tab_format['data'][$nbr_para]['type'] = 'ptitre';
                    }
                    elseif ($tab_word[0] == 'pliste')
                    {
                        $tab_format['data'][$nbr_para]['type'] = 'pliste';
                    }
                    elseif ($tab_word[0] == 'ptop')
                    {
                        $tab_format['data'][$nbr_para]['type'] = 'ptop';
                    }
                    elseif ($tab_word[0] == 'particlerelative')
                    {
                        $tab_format['data'][$nbr_para]['type'] = 'particlerelative';
                    }
                    elseif ($tab_word[0] == 'pflop')
                    {
                        $tab_format['data'][$nbr_para]['type'] = 'pflop';
                    }
                    elseif ($tab_word[0] == 'pnote')
                    {
                        $tab_format['data'][$nbr_para]['type'] = 'pnote';
                    }
                    elseif ($tab_word[0] == 'pcritique')
                    {
                        $tab_format['data'][$nbr_para]['type'] = 'pcritique';
                    }
                    elseif ($tab_word[0] == 'pexterne')
                    {
                        $tab_format['data'][$nbr_para]['type'] = 'pexterne';
                    }
                    elseif ($tab_word[0] == 'pcitation')
                    {
                        $tab_format['data'][$nbr_para]['type'] = 'pcitation';
                    }
                    elseif ($tab_word[0] == 'ppub')
                    {
                        $tab_format['data'][$nbr_para]['type'] = 'ppub';
                    }
                    elseif ($tab_word[0] == 'pmediatmp')
                    {
                        if ($tab_media != 0)
                        {
                            if ($tab_media['data'][$pmediatmp]['avatar'] == 1)
                                $pmediatmp++;

                            if (!empty($tab_media['data'][$pmediatmp]))
                            {
                                $tab_format['data'][$nbr_para]['media'] = $tab_media['data'][$pmediatmp]['id_galerie_media'];
                                $tab_format['data'][$nbr_para]['type'] = 'pmedia';
                            }
                            else
                                $tab_format['data'][$nbr_para]['type'] = 'pmediatmp';
                            $pmediatmp++;
                        }
                        else
                            $tab_format['data'][$nbr_para]['type'] = 'pmediatmp';
                    }
                    if (isset($tab_word[2]))
                    {
                        $tab_format['data'][$nbr_para]['align'] = ($tab_word[2] == 'l' || $tab_word[2] == 'left') ? 'left' : '';
                        $tab_format['data'][$nbr_para]['align'] = ($tab_word[2] == 'r' || $tab_word[2] == 'right') ? 'right' : $tab_format['data'][$nbr_para]['align'];
                    }

                    $line = mb_substr($line, $start);
                    $line = mb_substr($line, mb_strpos($line, ']') + 1);
                }
                else if (!empty($line))
                {
                    if (!isset($tab_format['data'][$nbr_para]['data']))
                        $tab_format['data'][$nbr_para]['data'] = '';
                    $tab_format['data'][$nbr_para]['data'] .= $line;
                    if (empty($tab_format['data'][$nbr_para]['type']))
                    {
                        $tab_format['data'][$nbr_para]['type'] = 'p';
                    }
                    $line = 0;
                }
            }
        }

        if (!isset($tab_format))
            $tab_format = ['data' => []];
        // Si le dernier paragraphe est un paragraphe vide (et que c'est pas un media) on le gicle
        if (isset($tab_format['data'][count($tab_format['data']) - 1]['data'])
            && $tab_format['data'][count($tab_format['data']) - 1]['data'] == ""
            && count($tab_format['data'][count($tab_format['data']) - 1]) == 1)
            array_pop($tab_format['data']);
        $tab_format['nbr'] = count($tab_format['data']);

        foreach ($tab_format['data'] as $key => $row)
        {
            if (!empty($row['data']))
                $row['data'] = Melty_Helper_String::mb_trim($row['data']);
            if (!empty($row['data']))
            {
                $tab_format['data'][$key]['data_spe'] = $row['data'];

                $row['data'] = str_replace("\n", "<br />", $row['data']);
                $row['data'] = str_replace("\n\r", "<br />", $row['data']);
                $tab_format['data'][$key]['data'] = $row['data'];

                if (isset($row['type']))
                {
                    if ($row['type'] == 'pliste'
                        || $row['type'] == 'ptop'
                        || $row['type'] == 'pflop')
                    {
                        // Patch jeremy truc degeux :D
                        $tab_format['data'][$key]['tab_data'] = explode('__EOL__', $row['data']);
                    }
                    else if ($row['type'] == 'particlerelative')
                    {
                        $ids = explode(';', $row['data']);
                        foreach ($ids as &$id)
                        { $id = (int)$id; } unset($id);
                        $tab_format['data'][$key]['tab_data'] = $this->get_lite($ids,
                                                                                NULL,
                                                                                NULL,
                                                                                NULL,
                                                                                NULL,
                                                                                FALSE,
                                                                                FALSE);
                    }
                    elseif ($row['type'] == 'ptitre')
                    {
                        $tab_format['data'][$key]['link_id'] = rewrite2($row['data']);
                        $tab_tmp_sommaire['data'][] = $tab_format['data'][$key];
                        if (!isset($tab_tmp_sommaire['nbr']))
                            $tab_tmp_sommaire['nbr'] = 0;
                        $tab_tmp_sommaire['nbr']++;
                    }
                    elseif ($row['type'] == 'pnote')
                    {
                        // DEFAULT
                        $default_max_note = 20;

                        // on tente de split
                        $lpos = strrpos($row['data'], '/');

                        if ($lpos)
                        {
                            $tab_format['data'][$key]['tab_data']['note'] = trim(substr($row['data'], 0, $lpos));
                            $tab_format['data'][$key]['tab_data']['max'] = trim(substr($row['data'], $lpos + 1));
                        }
                        else
                        {
                            $tab_format['data'][$key]['tab_data']['note'] = $row['data'];
                            $tab_format['data'][$key]['tab_data']['max'] = $default_max_note;
                        }
                    }
                    elseif ($row['type'] == 'pcritique')
                    {
                        // lorsque c'est une critique, il faut splitter la data en deux parties
                        $lpos = strrpos($row['data'], ':');

                        if ($lpos !== FALSE)
                        {
                            $tab_format['data'][$key]['tab_data']['str'] = trim(substr($row['data'], 0, $lpos));
                            $tab_format['data'][$key]['tab_data']['value'] = trim(substr($row['data'], $lpos + 1));
                        }
                        else
                        {
                            $tab_format['data'][$key]['tab_data']['str'] = 'Pour conclure';
                            $tab_format['data'][$key]['tab_data']['value'] = $row['data'];
                        }


                        // si la value possede un /[1-9], on l'encadre par un <span class="max"></span> puisqu'on decide que c'est une note
                        $tab_format['data'][$key]['tab_data']['value'] = preg_replace('/(\/[0-9]+)$/i', '<span class="max">$1</span>', $tab_format['data'][$key]['tab_data']['value']);
                    }
                }
            }
            elseif (isset($row['type']) && $row['type'] != 'pmedia' && $row['type'] != 'pexternmedia')
                unset($tab_format['data'][$key]);
        }

        // SOMMAIRE QUE JEREMY FERA
        foreach ($tab_format['data'] as $key => $row)
            $tab_format_real['data'][] = $row;
        if (!isset($tab_format_real))
            return ['nbr' => 0, 'data' => []];
        $tab_format_real['nbr'] = sizeof($tab_format_real['data']);
        return $tab_format_real;
    }

    public function get_all($id_thema = 0, $type_thema = '', $i_flag = 0,
                            $de = 0, $a = 0, $etat = self::ETAT_PUBLISHED)
    {
        $sq_thema = !empty($id_thema) ? " AND system2_article.id_thema = " . (int)$id_thema : '';
        $sq_type_thema = !empty($type_thema) ? " AND system2_thema.type = " . $this->sql->quote($type_thema) : '';

        if ($i_flag == 1)
            $sq_i_flag = " AND system2_article.i_flag != 0";
        else
            $sq_i_flag = !empty($i_flag) ? " AND FIND_IN_SET(" . $this->sql->quote($i_flag) . ", system2_article.i_flag)" : '';
        $sq_etat = $etat >= self::ETAT_PUBLISHED ? " AND system2_article.etat = " . (int)$etat : '';

        $sq_now = '';
        if (empty($id_thema) && empty($de))
            $sq_now = " AND system2_article.date > '" . date("Y-m-d", strtotime("-7 day")) . "'";

        // recherche des champs de l'article demande
        $q = "SELECT /*STRAIGHT_JOIN*/
               system2_article.titre,
               system2_article.id_membre,
               system2_article.aresum AS resum,
               system2_article.texte,
               system2_article.id AS id_article,
               system2_article.url,
               system2_article.published_url,
               system2_article.alt_url,
               system2_article.c_site,
               system2_article.c_origine,
               system2_article.id_thema,
               system2_article.i_flag,
               system2_article.etat,
               system2_article.date,
               system2_article.create_date,
               system2_article.anti_protection,
               system2_article.social_title,

               galerie.id_galerie,
               galerie_media.id_galerie_media,
               galerie_media.fingerprint AS fingerprint_media,
               galerie_media.description,
               media.titre AS tags_media,
               media.type AS type_media,
               media.format AS format_media,
               media.duration AS duration_media,
               media.height AS height_so,
               media.width AS width_so,
               COALESCE(galerie_media.crop_h, media.height) AS height_media,
               COALESCE(galerie_media.crop_w, media.width) AS width_media,

               system2_thema.nom AS nom_thema,
               system2_thema.type AS type_thema,
               system2_thema.url AS url_thema,
               system2_info.login,

               system2_com_index.id_com_index,
               system2_com_index.nbr AS nbr_com,

               system2_world_index.id_world_index,
               system2_world_index.nom AS nom_world,
               system2_world_index.url AS url_world,

               system2_votespe.id_votespe,
               system2_votespe.nbr_vote,
               system2_votespe.nbr_oui,
               system2_votespe.p_vote

          FROM system2_article
          " . $this->c_j(Melty_MUM::SITE_TYPE_ARTICLE, 'id') . "
          JOIN system2_thema ON system2_thema.id_thema = system2_article.id_thema
     LEFT JOIN system2_world ON system2_world.type = 'article' AND system2_world.id_type = system2_article.id AND system2_world.etat = 1
     LEFT JOIN system2_world_index ON system2_world_index.id_world_index = system2_world.id_world_index
           AND system2_world_index.etat > " . melty_lib_world::ETAT_DELETED . "
     LEFT JOIN system2_com_index ON system2_com_index.id_type = system2_article.id AND system2_com_index.type = 'article'
     LEFT JOIN system2_votespe ON system2_votespe.type = 'article' AND system2_votespe.id_type = system2_article.id
     LEFT JOIN system2_galerie AS galerie ON galerie.type = 'article'
               AND galerie.id_type = system2_article.id
               AND galerie.etat != -100
     LEFT JOIN system5_galerie_media AS galerie_media ON galerie_media.id_galerie = galerie.id_galerie
               AND galerie_media.id_galerie_media = galerie.id_avatar
               AND galerie_media.etat != -100
     LEFT JOIN system2_media AS media ON media.id_media = galerie_media.id_media
               AND media.etat != -100
     LEFT JOIN system2_info ON system2_info.id_membre = system2_article.id_membre
         WHERE 1 " . $sq_thema . " " . $sq_type_thema . " " . $sq_i_flag . " " . $sq_etat . " " . $sq_now . "
      ORDER BY system2_article.date DESC
         LIMIT " . (int)$de . ", " . (int)$a;

        $sql = $this->sql->query($q);
        if (is_object($sql))
        {
            $tab_article = ['nbr' => 0];
            while ($tabarticle = $sql->fetch())
            {
                $tabarticle['tags_media'] = Melty_Helper_String::explode(',', $tabarticle['tags_media']);
                $tabarticle['tab_i_flag'] = $this->order_i_flag($tabarticle['i_flag'], $tabarticle['c_origine']);

                if ($tabarticle['c_origine'] != $_ENV['INSTANCE'])
                    $resSite = new Melty_RAII_Router_Instance($tabarticle['c_origine']);

                $tabarticle['url_world_index'] = $this->lib['world']->get_url_index($tabarticle['id_world_index'], $tabarticle['url_world']);
                $tabarticle['url_thema_light'] = $tabarticle['url_thema'];
                $tabarticle['url_thema'] = $this->get_url_thema($tabarticle['url_thema']);
                $tabarticle['url_article'] = $this->get_url($tabarticle['id_article'], $tabarticle['url']);

                $tabarticle['published_url_article'] = !empty($tabarticle['published_url'])
                    ? $this->get_url($tabarticle['id_article'], $tabarticle['published_url'])
                    : $tabarticle['url_article'];

                if (isset($resSite))
                    unset($resSite);

                $tabarticle['url_membre'] = $this->lib['membre']->get_url($tabarticle['id_membre'], $tabarticle['login']);

                // media protection by date
                $tabarticle["media_protection"] = $this->media_protection($tabarticle['anti_protection'], $tabarticle['create_date'], $tabarticle['date']);

                if ($tabarticle['nbr_com'] > 0)
                {
                    unset($tabarticle['tab_com']);
                    $len = 0;
                    $tab_com_tmp = $this->lib['com']->get_lite($tabarticle['id_com_index'], 0, 5);
                    $len_article = strlen($tabarticle['resum']) * 2 + 1500;
                    if (!empty($tab_com_tmp['data']))
                    {
                        foreach ($tab_com_tmp['data'] as $row)
                        {
                            $len += strlen($row['texte']) + 40;
                            if (!isset($tabarticle['tab_com']))
                                $tabarticle['tab_com'] = ['nbr' => 0, 'data' => []];
                            if ($tabarticle['tab_com']['nbr'] == 0 || ($len < $len_article / 2))
                            {
                                $tabarticle['tab_com']['data'][] = $row;
                                if (!isset($tabarticle['tab_com']['nbr']))
                                    $tabarticle['tab_com']['nbr'] = 0;
                                $tabarticle['tab_com']['nbr']++;
                            }
                        }
                    }
                }
                $tab_article['data'][] = $tabarticle;
                if (!isset($tab_article['nbr']))
                    $tab_article['nbr'] = 0;
                $tab_article['nbr']++;
            }
            return $tab_article;
        }
        else
        {
            return Errno::DB_ERROR;
        }
    }

    public function get_wall($id_thema = 0, $i_flag = 0, $de = 0, $a = 100)
    {
        whine_unused("2014/08/01");
        $sq_thema = !empty($id_thema) ? "AND system2_article.id_thema = " . (int)$id_thema : '';

        if ($i_flag == 1)
            $sq_i_flag = " AND system2_article.i_flag != 0";
        else
            $sq_i_flag = !empty($i_flag) ? " AND FIND_IN_SET(" . $this->sql->quote($i_flag) . ", system2_article.i_flag)" : '';

        $sq_now = " AND system2_article.date > '" . date("Y-m-d", strtotime("-7 day")) . "'";

        $q = "SELECT /*STRAIGHT_JOIN*/
            " . $this->c_f('system2_article') . ",
           system2_article.titre,
           system2_article.id_membre,
           system2_article.aresum AS resum,
           system2_article.i_flag,
           system2_article.id AS id_article,
           system2_article.url,
           system2_article.published_url,
           system2_article.alt_url,
           system2_article.id_thema,
           system2_article.etat,
           system2_article.date,
           system2_article.c_origine,
           system2_article_hits.hits,
           system2_article.social_title,

           galerie.id_galerie,

           galerie_media.id_galerie_media,
           galerie_media.fingerprint AS fingerprint_media,
           galerie_media.description,
           media.titre AS tags_media,
           media.type AS type_media,
           media.format AS format_media,
           media.duration AS duration_media,
           COALESCE(galerie_media.crop_h, media.height) AS height_media,
           COALESCE(galerie_media.crop_w, media.width) AS width_media,
           media.height AS height_so,
           media.width AS width_so,

           system2_thema.nom AS nom_thema,
           system2_thema.url AS url_thema,
           system2_info.login,

           system2_com_index.id_com_index,
           system2_com_index.nbr AS nbr_com,

           system2_world_index.id_world_index,
           system2_world_index.nom AS nom_world,
           system2_world_index.url AS url_world,

           system2_votespe.id_votespe,
           system2_votespe.nbr_vote,
           system2_votespe.nbr_oui,
           system2_votespe.p_vote

          FROM system2_article /*USE INDEX (date)*/
          " . $this->c_j(Melty_MUM::SITE_TYPE_ARTICLE, 'id') . "
          JOIN system2_thema ON system2_thema.id_thema = system2_article.id_thema

     LEFT JOIN system2_article_hits ON system2_article_hits.id_article = system2_article.id
     LEFT JOIN system2_com_index ON system2_com_index.type = 'article' AND system2_com_index.id_type = system2_article.id
     LEFT JOIN system2_votespe ON system2_votespe.type = 'article' AND system2_votespe.id_type = system2_article.id

     LEFT JOIN system2_galerie AS galerie ON galerie.type = 'article'
               AND galerie.id_type = system2_article.id
               AND galerie.etat != -100
     LEFT JOIN system5_galerie_media AS galerie_media ON galerie_media.id_galerie = galerie.id_galerie
               AND galerie_media.id_galerie_media = galerie.id_avatar
               AND galerie_media.etat != -100
     LEFT JOIN system2_media AS media ON media.id_media = galerie_media.id_media
               AND media.etat != -100

     LEFT JOIN system2_world ON system2_world.type = 'article'
           AND system2_world.id_type = system2_article.id
           AND system2_world.etat = 1

     LEFT JOIN system2_world_index ON system2_world_index.id_world_index = system2_world.id_world_index
           AND system2_world_index.etat > " . melty_lib_world::ETAT_DELETED . "

     LEFT JOIN system2_info ON system2_info.id_membre = system2_article.id_membre

         WHERE 1 " . $sq_thema . " " . $sq_i_flag . " " . $sq_now . "
           AND system2_article.etat >= '" . self::ETAT_PUBLISHED . "'

      ORDER BY system2_article.date DESC
         LIMIT " . (int)$de . ", " . (int)$a;

        $sql = $this->sql->query($q);
        $total_hits = 0;
        $nbr = 0;
        if (is_object($sql))
        {
            while ($tabarticle = $sql->fetch())
            {
                $tabarticle['tags_media'] = Melty_Helper_String::explode(',', $tabarticle['tags_media']);

                if ($tabarticle['c_origine'] != $_ENV['INSTANCE'])
                    $resSite = new Melty_RAII_Router_Instance($tabarticle['c_origine']);
                $tabarticle['tab_i_flag'] = $this->order_i_flag($tabarticle['i_flag'], $tabarticle['c_origine']);
                $tabarticle['url_thema_light'] = $tabarticle['url_thema'];

                $tabarticle['url_thema'] = $this->get_url_thema($tabarticle['url_thema']);
                $tabarticle['url_article'] = $this->get_url($tabarticle['id_article'], $tabarticle['url']);

                $tabarticle['published_url_article'] = !empty($tabarticle['published_url'])
                    ? $this->get_url($tabarticle['id_article'], $tabarticle['published_url'])
                    : $tabarticle['url_article'];

                $tabarticle['url_world'] = $this->lib['world']->get_url_index($tabarticle['id_world_index'], $tabarticle['url_world']);

                //if (isset($tabarticle['list_tag']))
                //    $tabarticle['tag'] = $this->lib['tag']->list_clean(
                //        $tabarticle['list_tag']);
                //else
                //    $tabarticle['tag'] = [];

                if (isset($resSite))
                    unset($resSite);

                if (strtotime("-1 hours") < strtotime($tabarticle['date']))
                    $tabarticle['point'] = ceil($tabarticle['hits'] * 10);
                elseif (strtotime("-2 hours") < strtotime($tabarticle['date']))
                    $tabarticle['point'] = ceil($tabarticle['hits'] * 5);
                elseif (strtotime("-3 hours") < strtotime($tabarticle['date']))
                    $tabarticle['point'] = ceil($tabarticle['hits'] * 4);
                elseif (strtotime("-4 hours") < strtotime($tabarticle['date']))
                    $tabarticle['point'] = ceil($tabarticle['hits'] * 3);
                elseif (strtotime("-5 hours") < strtotime($tabarticle['date']))
                    $tabarticle['point'] = ceil($tabarticle['hits'] * 2);
                elseif (strtotime("-1 day") < strtotime($tabarticle['date']))
                    $tabarticle['point'] = ceil($tabarticle['hits'] * 1.5);
                else
                    $tabarticle['point'] = $tabarticle['hits'];

                $tab_article_tmp_hits[$tabarticle['point'] . $tabarticle['id_article']] = $tabarticle;

                $nbr++;
                $total_hits += $tabarticle['hits'];
            }
            krsort($tab_article_tmp_hits);
        }
        else
        {
            $tab_article_tmp_hits = [];
        }
        $nbr_article = 0;
        foreach ($tab_article_tmp_hits as $tabarticle)
        {
            if ($nbr_article <= ceil(5 * $a / 100))
            {
                $tabarticle['s_media'] = 'ww0f';
                $tabarticle['x_media'] = 648;
                $tabarticle['y_media'] = 300;
                $tabarticle['value'] = '4';
            }
            elseif ($nbr_article <= ceil(15 * $a / 100))
            {
                $tabarticle['s_media'] = 'ww1f';
                $tabarticle['x_media'] = 486;
                $tabarticle['y_media'] = 200;
                $tabarticle['value'] = '3';
            }
            elseif ($nbr_article <= ceil(30 * $a / 100))
            {
                $tabarticle['s_media'] = 'ww2f';
                $tabarticle['x_media'] = 324;
                $tabarticle['y_media'] = 200;
                $tabarticle['value'] = '2';
            }
            else
            {
                $tabarticle['s_media'] = 'ww3f';
                $tabarticle['x_media'] = 162;
                $tabarticle['y_media'] = 200;
                $tabarticle['value'] = '1';
            }

            $tab_article_tmp_date[$tabarticle['date'] . '-' . $tabarticle['id_article']] = $tabarticle;
            $nbr_article++;
        }
        krsort($tab_article_tmp_date);
        foreach ($tab_article_tmp_date as $row)
            $tab_article_tmp[] = $row;

        unset($tab_save);
        unset($tab_ligne);
        $ligne = 0;
        $nbr_save = 0;
        if (!empty($tab_article_tmp))
        {
            while ($nbr_save < $nbr_article)
            {
                $no_modif = 1;
                $i = 0;
                while ($i < $nbr_article)
                {
                    $row = $tab_article_tmp[$i];
                    if (!empty($row))
                    {
                        if (empty($tab_save[$row['id_article']])
                            && ($tab_ligne[$ligne]['value'] + $row['value']) <= 4)
                        {
                            $no_modif = 0;
                            $tab_ligne[$ligne]['data'][] = $row;
                            if (!isset($tab_ligne[$ligne]['value']))
                                $tab_ligne[$ligne]['value'] = 0;
                            $tab_ligne[$ligne]['value'] += $row['value'];

                            $tab_save[$row['id_article']] = 1;
                            $nbr_save++;
                        }

                        if (isset($tab_ligne[$ligne]['value']) && $tab_ligne[$ligne]['value'] >= 4)
                        {
                            $no_modif = 0;
                            $ligne++;
                        }
                        unset($row);
                        if ($no_modif == 0)
                            break;
                    }
                    $i++;
                }

                if ($no_modif == 1)
                {
                    break;
                }
            }
        }

        foreach ($tab_ligne as $ligne)
        {
            if (!empty($ligne) && $ligne['value'] == '4')
                foreach ($ligne['data'] as $row)
                {
                    $tab_final['data'][] = $row;
                    $tab_final['order_by_size'][$row['s_media']][] = $row;
                    $tab_final['nbr']++;
                    $tab_final['nbr_' . $row['s_media']]++;
                }
            unset($ligne);
        }

        return $tab_final;
    }

    public function get_top_buzz($id_thema = 0, $now = 60)
    {
        $sq_thema = !empty($id_thema) ? "AND system2_article.id_thema = " . (int)$id_thema : '';

        $now = 120;

        if (!empty($now))
            $sq_now = " AND system2_article.date > '" . date("Y-m-d", strtotime("-" . $now . " day")) . "'";
        elseif (empty($id_thema))
            $sq_now = " AND system2_article.date > '" . date("Y-m-d", strtotime("-7 day")) . "'";
        elseif (!empty($id_thema))
            $sq_now = " AND system2_article.date > '" . date("Y-m-d", strtotime("-30 day")) . "'";

        $q = "SELECT
               count(*) AS nbr,
            sib.id_membre,
            sib.login AS login,

            galerie_media.id_galerie_media AS id_galerie_media,
            galerie_media.fingerprint AS fingerprint_media,
            media.titre AS tags_media,
            media.type AS type_media,
            media.format AS format_media
          FROM system2_article
          " . $this->c_j(Melty_MUM::SITE_TYPE_ARTICLE, 'id') . "
          JOIN system2_info AS sib ON sib.id_membre = system2_article.id_membre_buzz

     LEFT JOIN system2_galerie AS galerie ON galerie.type = 'membre'
               AND galerie.id_type = system2_article.id_membre_buzz
               AND galerie.etat != -100
     LEFT JOIN system5_galerie_media AS galerie_media ON galerie_media.id_galerie = galerie.id_galerie
               AND galerie_media.id_galerie_media = galerie.id_avatar
               AND galerie_media.etat != -100
     LEFT JOIN system2_media AS media ON media.id_media = galerie_media.id_media
               AND media.etat != -100

         WHERE system2_article.id_membre_buzz != 0 " . $sq_thema . " " . $sq_now . "

      GROUP BY system2_article.id_membre_buzz
      ORDER BY  nbr DESC";

        if (is_object($sql = $this->sql->query($q)) === FALSE)
            return Errno::DB_ERROR;

        while ($tabarticle = $sql->fetch())
        {
            $tabarticle['tags_media'] = Melty_Helper_String::explode(',', $tabarticle['tags_media']);

            if (!empty($tabarticle['id_membre']))
                $tabarticle['url_membre'] = $this->lib['membre']->get_url($tabarticle['id_membre'], $tabarticle['login']);

            $tab_top_buzz['data'][] = $tabarticle;
            $tab_top_buzz['nbr'];
        }
        return $tab_top_buzz;
    }

    public function get_articles_from_those_worlds($id_world_index, $de = 0, $a = 4, $subscribe = FALSE)
    {
        $cache = Melty_Factory::getCache();
        return $cache->cache(
            'get_articles_from_those_worlds(' . $id_world_index . ', ' . $de . ', ' . $a . ')',
            function() use ($id_world_index, $de, $a, $subscribe)
            {
                return $this->get_fil(0, '', 0, $de, $a, NULL, NULL, NULL, NULL,
                                      melty_lib_article::ETAT_PUBLISHED, NULL, NULL,
                                      NULL, $id_world_index, 1, NULL, FALSE, NULL, FALSE, NULL, $subscribe);
            },
            60,
            Melty_Cache::LOCAL_ONLY);
    }

    /**
     * Recupere une liste d'article.
     * $unread TRUE / FALSE / NULL
     * Si TRUE : On ne recupere que les articles non lus
     * Si FALSE : On ne recupere que les articles lus
     * SI NULL : On recupere tous !
     */
    public function get_fil($id_thema = 0, $type_thema = '', $i_flag = 0,
                            $de = 0, $a = 10, $from_id = NULL, $to_id = NULL,
                            $order = NULL, $order_by = NULL,
                            $etat = self::ETAT_PUBLISHED, $unread = NULL,
                            $liked = NULL, $ids = NULL, $id_world_index = NULL,
                            $etat_world = 1, $blacklist = NULL,
                            $aggregate_by_world = FALSE,
                            $max_aggregations = NULL, $force_all_text = FALSE,
                            $geo_params = NULL, $subscribe = FALSE,
                            $macdo_order = NULL)
    {
        if ($max_aggregations ===  NULL)
            $max_aggregations = $_ENV['ARTICLE_MAX_AGGREGATIONS'];

        $index_hint = 'USE INDEX (published_date)';
        $join_hint = 'STRAIGHT_JOIN';

        $where = [];

        if ($order === NULL)
            $order = 'DESC';
        $sq_order = $order == 'ASC' ? 'ASC' : 'DESC';

        /*
         * Manage $order_by parameter,
         * $order_by can be like :
         *  - NULL (default)
         *  - field_name (field of system2_article)
         *  - [table_name.]field_name[ ORDER], [table_name.]field_name[ ORDER]...
         */
        if ($order_by === NULL || strpos($order_by, ',') === FALSE)
        {
            if ($order_by === NULL)
                $order_by = 'published_date';
            $sq_order_by = $this->sql->field('system2_article.' . $order_by);
        }
        else if (strpos($order_by, ',') !== FALSE)
        {
            $fields = Melty_Helper_String::explode(',', $order_by);
            foreach ($fields as &$f)
            {
                $f = Melty_Helper_String::explode(' ', $f);
                if (isset($f[1]))
                    $f[1] = $f[1] == 'ASC' ? 'ASC' : 'DESC';
                else
                    $f[1] = $sq_order;
                $f = $this->sql->field($f[0]) . ' ' . $f[1];
            } unset($f);
            $sq_order_by = implode(',', $fields);
            $sq_order = '';
        }

        if (!empty($i_flag) && gettype($i_flag) == "string")
            $i_flag = $this->helper['params_parse']->parse($i_flag);

        if ($to_id !== NULL)
        {
            $sq = 'SELECT published_date FROM system2_article'
                . ' WHERE id = ' . (int)$to_id;
            if (($row = $this->sql->memoized_fetch($sq)) === FALSE)
                return Errno::DB_ERROR;
            $where[] = 'system2_article.published_date > ' . $this->sql->quote($row['published_date']);
        }
        if ($from_id !== NULL)
        {
            $sq = 'SELECT published_date FROM system2_article'
                . ' WHERE id = ' . (int)$from_id;
            if (($row = $this->sql->memoized_fetch($sq)) === FALSE)
                return Errno::DB_ERROR;
            $where[] = 'system2_article.published_date < ' . $this->sql->quote($row['published_date']);
            $sq_limit = '';
        }
        else
        {
            $sq_limit = (int)$de . ', ';
        }

        if ($aggregate_by_world)
            $sq_limit .= (int)($a * (1 + $max_aggregations));
        else
            $sq_limit .= (int)$a;

        if ($ids)
        {
            $where[] = 'system2_article.id IN ( ' . $this->sql->quote($ids, Melty_Database_SQL::ARRAY_OF_INT) . ')';
            $index_hint = '';
        }
        $sq_unread_join = $sq_like_join = $sq_unread_select = '';

        // etat_world: on affiche meme si c'est pas le dossier principal ???
        $sq_iwi = 'LEFT JOIN system2_world ON system2_world.type = "article"'
            . '          AND system2_world.id_type = system2_article.id'
            . '          AND system2_world.etat = ' . (int)$etat_world;
        if ($id_world_index)
        {
            if (!is_array($id_world_index))
            {
                if (strpos($id_world_index, ',') !== FALSE)
                    $id_world_index = explode(',', $id_world_index);
                else
                    $id_world_index = [$id_world_index];
            }
            // Generate in/notin array
            $tab_in = [];
            $tab_notin = [];
            foreach ($id_world_index as $key => $value)
            {
                // strpos will be 50/100% faster than substr
                if (strpos($value, '!') > -1)
                    $tab_notin[] = (int)substr($value, 1);
                else
                    $tab_in[] = (int)$value;
            }

            $sw_in = [];
            if (count($tab_in) > 0)
            {
                $sw_in[] = 'IN (' . $this->sql->quote($tab_in, Melty_Database_SQL::ARRAY_OF_INT) . ')';
            }
            if (count($tab_notin) > 0)
            {
                $sw_in[] = 'NOT IN (' . $this->sql->quote($tab_notin, Melty_Database_SQL::ARRAY_OF_INT) . ')';
            }

            $sq_iwi = 'INNER JOIN system2_world ON system2_world.id_world_index ' . implode(' AND ', $sw_in)
                . '           AND system2_world.type = "article"'
                . '           AND system2_world.id_type = system2_article.id';

            $index_hint = $join_hint = '';
        }

        if ($id_thema)
        {
            $tab_o = [];
            $tab_i = [];
            $tab_t = explode(',', $id_thema);
            for ($i = 0; isset($tab_t[$i]); $i++)
            {
                if (substr($tab_t[$i], 0, 1) == "!")
                    $tab_o[] = substr($tab_t[$i], 1);
                else
                    $tab_i[] = $tab_t[$i];
            }

            if (count($tab_i) > 0)
                $where[] = "system2_article.id_thema IN (" .
                    $this->sql->quote($tab_i, Melty_Database_SQL::ARRAY_OF_INT)
                    . ')';
            if (count($tab_o) > 0)
                $where[] = "system2_article.id_thema NOT IN (" .
                    $this->sql->quote($tab_o, Melty_Database_SQL::ARRAY_OF_INT)
                    . ')';
            $index_hint = $join_hint = '';
        }
        if (!empty($type_thema))
            $where[] = "system2_thema.type = " . $this->sql->quote($type_thema);

        $id_membre = (int)$_SESSION->get_id_membre();
        if ($id_membre > 0)
        {
            $sq_unread_select = ' IF (srl.id_membre IS NOT NULL, 1, 0) AS unread,';
            $sq_unread_join = ' LEFT JOIN system4_read_log AS srl ON srl.type = "article" AND srl.id_type = system2_article.id AND srl.id_membre = ' . $id_membre;

            if ($unread !== NULL)
            {
                if ($unread === TRUE)
                    $where[] = 'srl.id_membre IS NULL';
                else if ($unread === FALSE)
                    $where[] = 'srl.id_membre IS NOT NULL';
            }
        }

        if ($liked != NULL)
        {
            $sq_like_join = ' INNER JOIN system4_like AS sl ON sl.id_type = system2_world.id_world_index AND sl.type = "world" AND sl.id_membre = ' . $id_membre;
            $index_hint = $join_hint = '';
        }

        if (!empty($i_flag))
        {
            if ($i_flag == 1)
            {
                $where[] = 'system2_article.i_flag != 0';
            }
            else
            {
                // Gestion des homes.
                // En gros, si on a set le flag 'home', c'est filtrer par c_origine, donc un article 'home' de style, n'apparait plus
                // sur melty.
                // Par contre, si on est le gros papa, on plus le flag 'home_master', pour afficher les articles que gregoire
                // a souhaiter explicitement remonter sur melty.fr, mais pas ceux qu'il s'est mis en tant
                // qu'important pour style (home)
                foreach ($i_flag as $key => $f)
                {
                    if ($f['value'] == 'home')
                    {
                        $sq_i_flag_home = '(';

                        $negation = $f['negation'] ? 'NOT ' : '';

                        $sq_i_flag_home .= '(system2_article.c_origine = ' . (int)$_ENV['C_ORIGINE']
                            . ' AND ' . $negation . 'FIND_IN_SET("home", system2_article.i_flag))';

                        if (Melty_Factory::getMUM()->getMasterInstanceName() == $_ENV['INSTANCE'])
                            $sq_i_flag_home .= ' OR ' . $negation . 'FIND_IN_SET("home_master", system2_article.i_flag)';

                        $sq_i_flag_home .= ')';
                        $where[] = $sq_i_flag_home;

                        array_splice($i_flag, $key, 1);
                        break;
                    }
                }

                // Gestion classique
                if (!empty($i_flag))
                {
                    if (count($i_flag) == 1)
                        $i_flag[0]['operator'] = '';

                    $sq_i_flag = '(';
                    foreach ($i_flag as $f)
                    {
                        if ($f['operator'] == '|')
                            $sq_i_flag .= ' OR ';
                        elseif ($f['operator'] == '&')
                            $sq_i_flag .= ' AND ';
                        $sq_i_flag .= $f['negation'] ? 'NOT ' : '';
                        $sq_i_flag .= 'FIND_IN_SET(' . $this->sql->quote($f['value']) . ', system2_article.i_flag)';
                    }
                    $sq_i_flag .= ')';
                    $where[] = $sq_i_flag;
                }
            }
        }

        if (!empty($blacklist))
        {
            $where[] = 'system2_article.id NOT IN ('
                . $this->sql->quote($blacklist, Melty_Database_SQL::ARRAY_OF_INT)
                . ')';
        }

        if ($etat >= self::ETAT_PUBLISHED)
            $where[] = 'system2_article.etat = ' . (int)$etat;

        $s_texte_condition = 'FIND_IN_SET("interview", system2_article.i_flag) OR
                              FIND_IN_SET("titre", system2_article.i_flag) OR
                              FIND_IN_SET("test", system2_article.i_flag) OR
                              FIND_IN_SET("selection_recette", system2_article.i_flag) OR
                              FIND_IN_SET("qcm", system2_article.i_flag)';
        if ($force_all_text === TRUE)
            $s_texte_condition = '1';

        $sq_join_geo = '';
        if (is_array($geo_params) && !empty($geo_params))
        {
            $sq_join_geo = $this->lib['geocoding']->formatJoinForGeoParameter(
                $geo_params, "article", "system2_article.id");
        }

        if ($macdo_order !== NULL && (bool)$macdo_order)
        {
            $sq_order_by = 'system2_article.event_date';
            $sq_order = 'ASC';
            $where[] = 'system2_article.event_date > NOW() - INTERVAL 12 HOUR';
            $where[] = 'system2_article.event_date IS NOT NULL';
            $where[] = 'system2_article.event_date != ""';
        }

        $q = "SELECT $join_hint "
            . $this->c_f('system2_article') . ',
          ' . $sq_unread_select . '
          system2_article.titre,
          system2_article.id_membre,
          system2_article.aresum AS resum,
          system2_article.i_flag,
          system2_article.id AS id_article,
          system2_article.url,
          system2_article.published_url,
          system2_article.alt_url,
          IF(' . $s_texte_condition . ', system2_article.texte, NULL) AS texte,
          system2_article.id_thema,
          system2_article.etat,
          system2_article.anti_protection,
          system2_article.date,
          system2_article.create_date,
          system2_article.published_date,
          system2_article.last_date,
          system2_article.adresse,
          system2_article.social_title,
          DATE(system2_article.event_date) AS event_date,
          system2_article.id_galerie,

          galerie_media_article.id_galerie_media,
          galerie_media_article.fingerprint AS fingerprint_media,
          media_article.titre AS tags_media,
          media_article.type AS type_media,
          media_article.format AS format_media,
          media_article.tab_spe_tips,
          media_article.tab_spe_tips,
          media_article.duration,

          system2_thema.nom AS nom_thema,
          system2_thema.url AS url_thema,
          system2_thema.type AS type_thema,

          system2_info.login,

          system2_com_index.id_com_index,
          system2_com_index.nbr AS nbr_com,

          system2_world_index.id_world_index,
          system2_world_index.url AS url_world,
          system2_world_index.nom AS nom_world,

          system2_article_hits.likes,
          system2_article_hits.tweets,
          system2_article_hits.plusones,

          (SELECT GROUP_CONCAT(worlds.id_world_index)
             FROM system2_world AS worlds
            WHERE worlds.type = "article"
                  AND worlds.id_type = id_article) AS id_worlds

     FROM system2_article ' . $index_hint . '
          ' . $this->c_j(Melty_MUM::SITE_TYPE_ARTICLE, 'id') . '
     JOIN system2_thema ON system2_thema.id_thema = system2_article.id_thema
          ' . $sq_iwi . '
LEFT JOIN system2_world_index ON system2_world_index.id_world_index = system2_world.id_world_index
          AND system2_world_index.etat > ' . melty_lib_world::ETAT_DELETED . '
LEFT JOIN system2_com_index ON system2_com_index.type = "article" AND system2_com_index.id_type = system2_article.id
LEFT JOIN system2_article_hits ON system2_article_hits.id_article = system2_article.id

LEFT JOIN system2_info ON system2_info.id_membre = system2_article.id_membre
LEFT JOIN system2_galerie AS galerie_article ON galerie_article.type = "article"
          AND galerie_article.id_type = system2_article.id
          AND galerie_article.etat != -100
LEFT JOIN system5_galerie_media AS galerie_media_article
          ON galerie_media_article.id_galerie = galerie_article.id_galerie
          AND galerie_media_article.id_galerie_media = galerie_article.id_avatar
          AND galerie_media_article.etat != -100
LEFT JOIN system2_media AS media_article ON media_article.id_media = galerie_media_article.id_media
          AND media_article.etat != -100
          ' . $sq_unread_join . '
          ' . $sq_like_join . '
          ' . $sq_join_geo . '
    WHERE ' . implode(' AND ', $where) . '
 ORDER BY ' . $sq_order_by . ' ' . $sq_order . '
    LIMIT ' . $sq_limit;

        $articles = Melty_Factory::getLoadBalancedSlaveDatabase()->memoized_fetchAll($q);
        if ($articles === FALSE)
            return Errno::DB_ERROR;

        $ret = ['data' => [], 'nbr' => 0];
        if (empty($articles))
            return $ret;

        if ($aggregate_by_world)
        {
            $groups = [];
            foreach ($articles as $row)
            {
                $id_world = $row['id_world_index'];
                $new_group = TRUE;
                if (!empty($id_world))
                {
                    /* Boucle pour tenter d'insérer l'article dans son groupe
                       correspondant si il existe */
                    foreach ($groups as $key => $group)
                    {
                        /* Si l'id_world_index de l'article est le même que
                           celui du groupe et que le nombre d'article dedans
                           ne dépasse pas la limite.. */
                        if ($group[0]['id_world_index'] === $id_world
                            && count($group) < $max_aggregations + 1)
                        {
                            $row['group'] = $key;
                            // Ajout de l'article au groupe correspondant
                            $groups[$key][] = $row;
                            $new_group = FALSE;
                            break;
                        }
                    }
                }
                if ($new_group)
                {
                    $row['group'] = count($groups);
                    // Création d'un nouveau groupe
                    $groups[] = [$row];
                    if (count($groups) >= $a)
                        break;
                }
            }
            $articles = Melty_Helper_Array::flattenArray($groups);
        }

        foreach ($articles as $row)
        {
            $row['tags_media'] = Melty_Helper_String::explode(',', $row['tags_media']);
            $row['tab_i_flag'] = $this->order_i_flag($row['i_flag'], $row['c_origine']);
            $row['url_thema_light'] = $row['url_thema'];

            if ($row['c_origine'] != $_ENV['INSTANCE'])
                $resSite = new Melty_RAII_Router_Instance($row['c_origine']);
            $row['url_thema'] = $this->get_url_thema($row['url_thema']);
            $row['url_article'] = $this->get_url($row['id_article'], $row['url']);

            $row['published_url_article'] = !empty($row['published_url'])
                ? $this->get_url($row['id_article'], $row['published_url'])
                : $row['url_article'];

            if (isset($row['id_worlds']))
            {
                $row['tab_worlds'] = explode(',', $row['id_worlds']);
                unset($row['id_worlds']);
            }

            $row['url_world_lite'] = $row['url_world'];
            $row['url_world'] = $this->lib['world']->get_url_index($row['id_world_index'], $row['url_world']);
            $row['url_world_index'] = $row['url_world'];
            if (isset($resSite))
                unset($resSite);

            // media protection by date
            $row["media_protection"] = $this->media_protection($row['anti_protection'], $row['create_date'], $row['date']);

            if ((int)$row['duration'] > 0)
                $row['format_duration'] = date('i:s', (int)($row['duration'] / 1000));

            $row['timestamp'] = strtotime($row['published_date']);

            if ($this->test_i_flag($row['tab_i_flag'], "image,test,poster") === TRUE)
            {
                $row['tab_media'] = $this->lib['media']->galerie_get_media($row['id_galerie'], -1);
            }

            $row['url_membre'] = $this->lib['membre']->get_url($row['id_membre'], $row['login']);

            if (!empty($row['tab_spe_tips']))
                $row['real_tab_spe_tips'] = unserialize(base64_decode($row['tab_spe_tips']));

            if ($subscribe === TRUE)
                $row['is_liked'] = $this->lib['like']->exist('world', $row['id_world_index'], $_SESSION->get_id_membre());

            if (isset($row['texte']))
            {
                //on recupère le texte formated
                //si on en a besoin,
                //et on l'envoi car il est aussi calculé dans la fct
                // melty_article_lib::get_i_flag_tab_spe
                $worker = NULL;
                if ($force_all_text === TRUE)
                    $worker = $this->format($row['texte'], 0, 1);

                $row['tab_spe_format'] = $this->get_i_flag_tab_spe(
                    $row['tab_i_flag'],
                    $row['texte'],
                    $worker);

                if ($force_all_text === TRUE)
                    $row['texte'] = $worker;
                else
                    unset($row['texte']);
            }
            $row['nbr_reactions'] = [
                'nbr_com' => (int)$row['nbr_com'],
                'nbr_likes' => (int)$row['likes'],
                'nbr_tweets' => (int)$row['tweets'],
                'nbr_plusones' => (int)$row['plusones']
                ];
            $row['nbr_reactions']['total'] = array_sum($row['nbr_reactions']);

            $ret['data'][] = $row;
            $ret['nbr']++;
        }
        $this->add_share_option($ret);
        return $ret;
    }

    public function get_fil_light($id_thema = 0, $type_thema = '', $i_flag = 0,
                                  $de = 0, $a = 10, $etat = self::ETAT_PUBLISHED)
    {
        $sq_thema = '';
        if (!empty($id_thema))
        {
            if (is_array($id_thema))
                $sq_thema = "AND system2_article.id_thema IN (" . $this->sql->quote($id_thema, Melty_Database_SQL::ARRAY_OF_INT) . ") ";
            else
                $sq_thema = "AND system2_article.id_thema = " . (int)$id_thema;
        }
        $sq_type_thema = !empty($type_thema) ? "AND system2_thema.type = " . $this->sql->quote($type_thema) : '';

        if ($i_flag == 1)
            $sq_i_flag = " AND system2_article.i_flag != 0";
        else
            $sq_i_flag = !empty($i_flag) ? " AND FIND_IN_SET(" . $this->sql->quote($i_flag) . ", system2_article.i_flag)" : '';
        $sq_etat = $etat >= self::ETAT_PUBLISHED ? " AND system2_article.etat = " . (int)$etat : '';

        $sq_now = '';
        if (empty($id_thema) && empty($de))
            $sq_now = " AND system2_article.date > '" . date("Y-m-d", strtotime("-7 day")) . "'";

        $id_membre = $_SESSION->get_id_membre();
        $sq_unread_select = $sq_unread_join = '';
        if ($id_membre)
        {
            $sq_unread_select = ' IF (srl.id_membre IS NOT NULL, 1, 0) AS unread,';
            $sq_unread_join = ' LEFT JOIN system4_read_log AS srl ON srl.type = "article" AND srl.id_type = system2_article.id AND srl.id_membre = ' . (int)$id_membre;
        }

        $q = "SELECT
              " . $this->c_f('system2_article') . ",
              " . $sq_unread_select . "
              system2_article.titre,
              system2_article.id_membre,
              system2_article.aresum AS resum,
              system2_article.texte,
              system2_article.i_flag,
              system2_article.id AS id_article,
              system2_article.url,
              system2_article.published_url,
              system2_article.alt_url,
              system2_article.id_thema,
              system2_article.etat,
              system2_article.anti_protection,
              system2_article.date,
              system2_article.create_date,
              system2_article.social_title,
              galerie.id_galerie,

              galerie_media.id_galerie_media,
              galerie_media.fingerprint AS fingerprint_media,
              media.titre AS tags_media,
              media.type AS type_media,
              media.format AS format_media,

              system2_thema.nom AS nom_thema,
              system2_thema.url AS url_thema,
              system2_thema.type AS type_thema,
              system2_info.login,

              system2_com_index.id_com_index,
              system2_com_index.nbr AS nbr_com

         FROM system2_article
              " . $this->c_j(Melty_MUM::SITE_TYPE_ARTICLE, 'id') . "
         JOIN system2_thema ON system2_thema.id_thema = system2_article.id_thema
    LEFT JOIN system2_com_index ON system2_com_index.type = 'article' AND system2_com_index.id_type = system2_article.id
    LEFT JOIN system2_galerie AS galerie ON galerie.type = 'article'
              AND galerie.id_type = system2_article.id
              AND galerie.etat != -100
         JOIN system5_galerie_media AS galerie_media ON galerie_media.id_galerie = galerie.id_galerie
              AND galerie_media.id_galerie_media = galerie.id_avatar
              AND galerie_media.etat != -100
    LEFT JOIN system2_media AS media ON media.id_media = galerie_media.id_media
              AND media.etat != -100
    LEFT JOIN system2_info ON system2_info.id_membre = system2_article.id_membre
              " . $sq_unread_join . "
        WHERE 1 " . $sq_thema . " " . $sq_type_thema . " " . $sq_i_flag . " " . $sq_etat . " " . $sq_now . "
     ORDER BY system2_article.date DESC
        LIMIT " . (int)$de . ", " . (int)$a;

        $sql = $this->sql->query($q);

        if (is_object($sql))
        {
            $tab_article = ['nbr' => 0];
            while ($tabarticle = $sql->fetch())
            {
                $tabarticle['tags_media'] = Melty_Helper_String::explode(',', $tabarticle['tags_media']);

                $tabarticle['tab_i_flag'] = $this->order_i_flag($tabarticle['i_flag'], $tabarticle['c_origine']);
                if ($tabarticle['c_origine'] != $_ENV['INSTANCE'])
                    $resSite = new Melty_RAII_Router_Instance($tabarticle['c_origine']);
                $tabarticle['url_thema_light'] = $tabarticle['url_thema'];
                $tabarticle['url_thema'] = $this->get_url_thema($tabarticle['url_thema']);
                $tabarticle['url_article'] = $this->get_url($tabarticle['id_article'], $tabarticle['url']);

                $tabarticle['published_url_article'] = !empty($tabarticle['published_url'])
                    ? $this->get_url($tabarticle['id_article'], $tabarticle['published_url'])
                    : $tabarticle['url_article'];

                if (isset($resSite))
                    unset($resSite);
                $tabarticle['url_membre'] = $this->lib['membre']->get_url($tabarticle['id_membre'], $tabarticle['login']);

                // media protection by date
                $tabarticle["media_protection"] = $this->media_protection($tabarticle['anti_protection'], $tabarticle['create_date'], $tabarticle['date']);

                if (isset($tabarticle['tab_i_flag']['flag']['image']))
                    $tabarticle['tab_media'] = $this->lib['media']->galerie_get($tabarticle['id_galerie']);

                $tab_article['data'][] = $tabarticle;
                if (!isset($tab_article['nbr']))
                    $tab_article['nbr'] = 0;
                $tab_article['nbr']++;
            }
            return $tab_article;
        }
        else
        {
            return Errno::DB_ERROR;
        }
    }

    public function get_fil_iphone($id_thema = 0, $type_thema = '', $i_flag = 0,
                                   $de = 0, $a = 0, $etat = self::ETAT_PUBLISHED)
    {
        $sq_thema = !empty($id_thema) ? "AND system2_article.id_thema = " . (int)$id_thema : '';
        $sq_type_thema = !empty($type_thema) ? "AND system2_thema.type = " . $this->sql->quote($type_thema) : '';

        if ($i_flag == 1)
            $sq_i_flag = " AND system2_article.i_flag != 0";
        else
            $sq_i_flag = !empty($i_flag) ? " AND FIND_IN_SET(" . $this->sql->quote($i_flag) . ", system2_article.i_flag)" : '';
        $sq_etat = $etat >= self::ETAT_PUBLISHED ? " AND system2_article.etat = " . (int)$etat : '';


        if (empty($id_thema) && empty($de))
            $sq_now = " AND system2_article.date > '" . date("Y-m-d", strtotime("-7 day")) . "'";


        $q = "SELECT
               system2_article.titre,
               system2_article.aresum AS resum,
               system2_article.id AS id_article,
               system2_article.date,
               system2_article.social_title,

               galerie_media.id_galerie_media,
               galerie_media.fingerprint AS fingerprint_media,
               media.titre AS tags_media,
               media.format AS format_media,
               media.type AS type_media,

               system2_info.login,
               system2_com_index.nbr AS nbr_com
          FROM system2_article
          " . $this->c_j(Melty_MUM::SITE_TYPE_ARTICLE, 'id') . "
          JOIN system2_thema
               ON system2_thema.id_thema = system2_article.id_thema
     LEFT JOIN system2_com_index ON system2_com_index.type = 'article'
               AND system2_com_index.id_type = system2_article.id

     LEFT JOIN system2_galerie AS galerie ON galerie.type = 'article'
               AND galerie.id_type = system2_article.id
               AND galerie.etat != -100
     LEFT JOIN system5_galerie_media AS galerie_media ON galerie_media.id_galerie = galerie.id_galerie
               AND galerie_media.id_galerie_media = galerie.id_avatar
               AND galerie_media.etat != -100
     LEFT JOIN system2_media AS media ON media.id_media = galerie_media.id_media
               AND media.etat != -100
     LEFT JOIN system2_info ON system2_info.id_membre = system2_article.id_membre
         WHERE 1 " . $sq_thema . " " . $sq_type_thema . " " . $sq_i_flag
            . " " . $sq_etat . " " . $sq_now . "
      ORDER BY system2_article.date DESC
         LIMIT " . (int)$de . ", " . (int)$a;

        $sql = $this->sql->query($q);
        if (is_object($sql))
        {
            while ($tabarticle = $sql->fetch())
            {
                $tabarticle['tags_media'] = Melty_Helper_String::explode(',', $tabarticle['tags_media']);
                $tab_article['data'][] = $tabarticle;
                $tab_article['nbr']++;
            }
            return $tab_article;
        }
        else
        {
            return Errno::DB_ERROR;
        }
    }

    public function get_fil_date($id_thema = 0, $type_thema = '', $i_flag = 0,
                                 $de = 0, $a = 0, $etat = self::ETAT_PUBLISHED,
                                 $date = '', $order = 'DESC')
    {
        return $this->get_fil_($id_thema, $type_thema, $i_flag, $de, $a, $etat,
                               $date, $order, 'system2_article.date');

    }

    public function get_fil_ads_view($id_thema = 0, $type_thema = '', $i_flag = 0,
                                     $de = 0, $a = 0, $etat = self::ETAT_PUBLISHED,
                                     $date = '', $order = 'ASC')
    {
        whine_unused("2014/08/01");
        return $this->get_fil_($id_thema, $type_thema, $i_flag, $de, $a, $etat,
                               $date, $order, 'ads.nb_view');

    }

    private function get_fil_($id_thema = 0, $type_thema = '', $i_flag = 0,
                              $de = 0, $a = 0, $etat = self::ETAT_PUBLISHED,
                              $date = '', $order = 'ASC', $order_by)
    {
        $sq_order_by = $this->sql->field($order_by);
        $sq_order = $order === 'ASC' ? 'ASC' : 'DESC';

        $sq_thema = !empty($id_thema) ? "AND system2_article.id_thema = " . (int)$id_thema : '';
        $sq_type_thema = !empty($type_thema) ? "AND system2_thema.type = " . $this->sql->quote($type_thema) : '';

        $sq_i_flag = '';
        if (!empty($i_flag))
        {
            if ($i_flag == 1)
            {
                $sq_i_flag = " AND system2_article.i_flag != 0";
            }
            else
            {
                $tab_i_flag = explode('|', $i_flag);
                foreach ($tab_i_flag as $row)
                {
                    if ($row[0] == '!')
                    {
                        $row = substr($row, 1);
                        $sq_i_flag .=!empty($row) ? " AND NOT FIND_IN_SET(" . $this->sql->quote($row) . ", system2_article.i_flag)" : '';
                    }
                    else
                    {
                        $sq_i_flag .=!empty($row) ? " AND FIND_IN_SET(" . $this->sql->quote($row) . ", system2_article.i_flag)" : '';
                    }
                }
            }
        }

        $sq_etat = $etat >= self::ETAT_PUBLISHED ? " AND system2_article.etat = " . (int)$etat : '';
        if (empty($date))
            $sq_now = " AND system2_article.date > '" . date("Y-m-d", strtotime("-7 day")) . "'";
        else
            $sq_now = " AND system2_article.date > " . $this->sql->quote($date);

        $id_membre = $_SESSION->get_id_membre();
        $sq_unread_select = $sq_unread_join = '';
        if ($id_membre)
        {
            $sq_unread_select = ' IF (srl.id_membre IS NOT NULL, 1, 0) AS unread,';
            $sq_unread_join = ' LEFT JOIN system4_read_log AS srl ON srl.type = "article" AND srl.id_type = system2_article.id AND srl.id_membre = ' . (int)$id_membre;
        }

        $q = "SELECT /*STRAIGHT_JOIN*/
               " . $this->c_f('system2_article') . ",
               " . $sq_unread_select . "
               system2_article.titre,
               system2_article.id_membre,
               system2_article.aresum AS resum,
               system2_article.i_flag,
               system2_article.id AS id_article,
               system2_article.url,
               system2_article.published_url,
               system2_article.alt_url,
               system2_article.id_thema,
               system2_article.etat,
               system2_article.anti_protection,
               system2_article.date,
               system2_article.social_title,

               galerie_article.id_galerie,

               galerie_media_article.id_galerie_media,
               galerie_media_article.fingerprint AS fingerprint_media,
               media_article.titre AS tags_media,
               media_article.type AS type_media,
               media_article.format AS format_media,

               system2_thema.nom AS nom_thema,
               system2_thema.url AS url_thema,
               system2_thema.type AS type_thema,

               system2_info.login,

               system2_com_index.id_com_index,
               system2_com_index.nbr AS nbr_com,

               system2_world_index.id_world_index,
               system2_world_index.url AS url_world,
               system2_world_index.nom AS nom_world,

               tag.list_tag_clean AS keywords,

               ads.nb_view

          FROM system2_article /*USE INDEX (date)*/
          " . $this->c_j(Melty_MUM::SITE_TYPE_ARTICLE, 'id') . "
          JOIN system2_thema ON system2_thema.id_thema = system2_article.id_thema
     LEFT JOIN system2_com_index ON system2_com_index.type = 'article'
               AND system2_com_index.id_type = system2_article.id

     LEFT JOIN system2_galerie AS galerie_article ON galerie_article.type = 'article'
               AND galerie_article.id_type = system2_article.id
               AND galerie_article.etat != -100
     LEFT JOIN system5_galerie_media AS galerie_media_article
               ON galerie_media_article.id_galerie = galerie_article.id_galerie
               AND galerie_media_article.id_galerie_media = galerie_article.id_avatar
               AND galerie_media_article.etat != -100
     LEFT JOIN system2_media AS media_article ON media_article.id_media = galerie_media_article.id_media
               AND media_article.etat != -100

     LEFT JOIN system2_world ON system2_world.type = 'article'
               AND system2_world.id_type = system2_article.id
               AND system2_world.etat = 1
     LEFT JOIN system2_world_index
               ON system2_world_index.id_world_index = system2_world.id_world_index
               AND system2_world_index.etat > " . melty_lib_world::ETAT_DELETED . "
     LEFT JOIN system2_info
               ON system2_info.id_membre = system2_article.id_membre

     LEFT JOIN system2_tag AS tag ON tag.type = 'article'
               AND tag.id_type = system2_article.id
     LEFT JOIN system4_mtm_ads_view AS ads"
            . " ON ads.type = 'article'"
            . " AND ads.id_type = system2_article.id"
            . " AND ads.date = " . $this->sql->quote(date('Y-m-d'))
            . $sq_unread_join . "
         WHERE 1 " .$sq_type_thema . " " . $sq_thema
            . " " . $sq_i_flag . " " . $sq_etat . " " . $sq_now . "
      ORDER BY " . $sq_order_by . ' ' . $sq_order
            . " LIMIT " . (int)$de . ", " . (int)$a;

        $sql = $this->sql->query($q);

        if (is_object($sql))
        {
            $tab_article = ['nbr' => 0];
            while ($tabarticle = $sql->fetch())
            {
                $tabarticle['tags_media'] = Melty_Helper_String::explode(',', $tabarticle['tags_media']);
                $tabarticle['tab_i_flag'] = $this->order_i_flag($tabarticle['i_flag'], $tabarticle['c_origine']);
                $tabarticle['url_thema_light'] = $tabarticle['url_thema'];
                $tabarticle['url_thema'] = $this->get_url_thema($tabarticle['url_thema']);

                if ($tabarticle['c_origine'] != $_ENV['INSTANCE'])
                    $resSite = new Melty_RAII_Router_Instance($tabarticle['c_origine']);
                $tabarticle['url_article'] = $this->get_url($tabarticle['id_article'], $tabarticle['url']);

                $tabarticle['published_url_article'] = !empty($tabarticle['published_url'])
                    ? $this->get_url($tabarticle['id_article'], $tabarticle['published_url'])
                    : $tabarticle['url_article'];

                if (isset($resSite))
                    unset($resSite);

                $tabarticle['url_world'] = $this->lib['world']->get_url_index($tabarticle['id_world_index'], $tabarticle['url_world']);
                $tabarticle['url_membre'] = $this->lib['membre']->get_url($tabarticle['id_membre'], $tabarticle['login']);

                if ($tabarticle['tab_i_flag']['display']['0'] == 'image')
                    $tabarticle['tab_media'] = $this->lib['media']->galerie_get_media($tabarticle['id_galerie'], -1);

                $tab_article['data'][] = $tabarticle;
                $tab_article['nbr']++;
            }
            return $tab_article;
        }
        else
        {
            return Errno::DB_ERROR;
        }
    }

    public function get_thema_media($id_thema = 0, $type_thema = '', $de = 0,
                                    $a = 0)
    {
        $sq_thema = !empty($id_thema) ? "AND system2_article.id_thema = " . (int)$id_thema : '';
        $sq_type_thema = !empty($type_thema) ? "AND system2_thema.type_thema = " . $this->sql->quote($type_thema) : '';
        $sq_now = " AND system2_article.date > '" . date("Y-m-d", strtotime("-3 day")) . "'";

        $q = "SELECT
                system2_article.titre,
                system2_article.aresum AS resum,
                system2_article.id AS id_article,
                system2_article.url,
                system2_article.published_url,
                system2_article.alt_url,
                system2_article.id_thema,
                system2_article.etat,
                system2_article.anti_protection,
                system2_article.date,
                system2_article.social_title,

                galerie_media.id_galerie_media,
                galerie_media.fingerprint AS fingerprint_media,
                media.titre AS tags_media,
                media.type AS type_media,
                media.format AS format_media,

                system2_thema.nom AS nom_thema,
                system2_thema.url AS url_thema
           FROM system2_article
                " . $this->c_j(Melty_MUM::SITE_TYPE_ARTICLE, 'id') . "

      LEFT JOIN system2_galerie AS galerie ON galerie.type = 'article'
                AND galerie.id_type = system2_article.id
                AND galerie.etat != -100
      LEFT JOIN system5_galerie_media AS galerie_media ON galerie_media.id_galerie = galerie.id_galerie
                AND galerie_media.id_galerie_media = galerie.id_avatar
                AND galerie_media.etat != -100
      LEFT JOIN system2_media AS media ON media.id_media = galerie_media.id_media
                AND media.etat != -100

           JOIN system2_thema
                ON system2_thema.id_thema = system2_article.id_thema
                AND system2_thema.etat != 0
          WHERE system2_article.etat >= '" . self::ETAT_PUBLISHED . "' "
            . $sq_thema . " " . $sq_type_thema . " " . $sq_now . "
       ORDER BY system2_article.date DESC
          LIMIT " . (int)$de . ", " . (int)$a;

        $sql = $this->sql->query($q);
        if (is_object($sql))
        {
            $tab_article = ['data' => [], 'nbr' => 0];
            while ($tabarticle = $sql->fetch())
            {
                $tabarticle['tags_media'] = Melty_Helper_String::explode(',', $tabarticle['tags_media']);
                $tabarticle['tab_i_flag'] = $this->order_i_flag($tabarticle['i_flag'], $tabarticle['c_origine']);
                if ($tabarticle['c_origine'] != $_ENV['INSTANCE'])
                    $resSite = new Melty_RAII_Router_Instance($tabarticle['c_origine']);
                $tabarticle['url_thema'] = $this->get_url_thema($tabarticle['url_thema']);
                $tabarticle['url_article'] = $this->get_url($tabarticle['id_article'], $tabarticle['url']);

                $tabarticle['published_url_article'] = !empty($tabarticle['published_url'])
                    ? $this->get_url($tabarticle['id_article'], $tabarticle['published_url'])
                    : $tabarticle['url_article'];

                if (isset($resSite))
                    unset($resSite);

                // media protection by date
                $tabarticle["media_protection"] = $this->media_protection($tabarticle['anti_protection'], $tabarticle['create_date'], $tabarticle['date']);

                $tab_article['data'][] = $tabarticle;
                $tab_article['nbr']++;
            }
            return $tab_article;
        }
        else
        {
            return Errno::DB_ERROR;
        }
    }

    /**
     *
     * @param int $id_thema
     * @param int $de
     * @param int $a
     * @param int $from_id
     * @param mixed $ids
     * @return mixed
     */
    public function get_last_modified($id_thema = NULL, $de = 0, $a = 10,
                                      $from_id = NULL, $ids = NULL)
    {
        $w = ['etat >= ' . self::ETAT_PUBLISHED];
        if ($id_thema !== NULL)
            $w[] = 'id_thema = ' . (int)$id_thema;
        if ($from_id !== NULL)
            $w[] = 'id > ' . (int)$from_id;
        if ($ids !== NULL && (is_string($ids) || is_array($ids)))
        {
            if (is_string($ids))
                $ids = explode(',', $ids);
            foreach ($ids as &$i)
                $i = (int)$i;
            unset($i);
            $w[] = 'id IN (' . implode(',', $ids) . ')';
        }

        $q = 'SELECT id as id_article, last_date, published_date FROM system2_article';
        if (!empty($w))
            $q .= ' WHERE ' . implode(' AND ', $w);
        $q .= ' ORDER BY last_date DESC LIMIT ' . (int)$de . ', ' . (int)$a;

        $ret = array('data' => $this->sql->memoized_fetchAll($q),
                     'nbr' => 0);
        if ($ret['data'] === FALSE)
            return Errno::DB_ERROR;
        $ret['nbr'] = count($ret['data']);
        return $ret;
    }

    public function get_list($id_thema = 0, $type_thema = '', $now = 1, $order,
                             $etat = self::ETAT_PUBLISHED, $de = 0, $a = 10,
                             $tab_spe = 0)
    {
        $week = !empty($id_thema) ? 4 : 1;

        $sq_etat = $etat != -2 ? "article.etat = " . (int)$etat : self::ETAT_PUBLISHED;
        $sq_thema = !empty($id_thema) ? "article.id_thema = " . (int)$id_thema : 1;

        $sq_type_thema = !empty($type_thema) ? "system2_thema.type = " . $this->sql->quote($type_thema) : 1;

        $sq_now = $now == 1 ? "article.date >= '" . date("Y-m-d", strtotime("-" . $week . " weeks")) . "'" : 1;
        $sq_order = !empty($order) ? $order : 'date';

        if ($order == 'hits')
        {
            $sq_join = '     LEFT JOIN system2_article_hits ON system2_article_hits.id_article = article.id';
            $sq_row = '        system2_article_hits.hits,';
            $sq_order = 'system2_article_hits.hits';
        }
        else
        {
            $sq_join = '';
            $sq_row = '';
            $sq_order = 'article.' . $sq_order;
        }

        $sq_spe = '';
        if (!empty($tab_spe))
        {
            if (is_array($tab_spe))
                foreach ($tab_spe as $row)
                    $sq_spe .= ' AND article.id != ' . $this->sql->quote($row);
            else
                $sq_spe = ' AND article.id != ' . $this->sql->quote($tab_spe);
        }

        $q = 'SELECT article.c_origine,
           article.titre, article.id_membre,
           article.aresum AS resum, article.id AS id_article,
           article.alt_url, article.url, article.published_url, article.id_thema, article.i_flag,
           article.etat, article.date, article.id_galerie, article.social_title,
           ' . $sq_row . "

           galerie_media.id_galerie_media,
           galerie_media.fingerprint AS fingerprint_media,
           media.titre AS tags_media,
           media.type AS type_media,
           media.format AS format_media,

           system2_com_index.nbr AS nbr_com,
           system2_thema.nom AS nom_thema,
           system2_thema.url AS url_thema,
           system2_thema.type AS type_thema,
           system2_info.login

      FROM system2_article AS article
           " . $this->c_j(Melty_MUM::SITE_TYPE_ARTICLE, 'id', 'article') . "
           " . $sq_join . "
 LEFT JOIN system2_galerie AS galerie ON galerie.type = 'article'
           AND galerie.id_type = article.id
           AND galerie.etat != -100
 LEFT JOIN system5_galerie_media AS galerie_media ON galerie_media.id_galerie = galerie.id_galerie
           AND galerie_media.id_galerie_media = galerie.id_avatar
           AND galerie_media.etat != -100
 LEFT JOIN system2_media AS media ON media.id_media = galerie_media.id_media
           AND media.etat != -100

 LEFT JOIN system2_info ON system2_info.id_membre = article.id_membre
 LEFT JOIN system2_com_index ON system2_com_index.id_type = article.id
           AND system2_com_index.type = 'article'
      JOIN system2_thema ON system2_thema.id_thema = article.id_thema
     WHERE " . $sq_thema . " AND " . $sq_type_thema . " AND " . $sq_now
            . " AND " . $sq_etat . " " . $sq_spe . "
  ORDER BY " . $sq_order . " DESC
     LIMIT " . ((int)$de * $a) . ", " . ((int)$a + 1);

        $sql = $this->sql->query($q);
        if (is_object($sql))
        {
            $tab_article = ['nbr' => 0, 'data' => []];
            while ($tabarticle = $sql->fetch())
            {
                $tabarticle['tags_media'] = Melty_Helper_String::explode(',', $tabarticle['tags_media']);
                $tabarticle['tab_i_flag'] = $this->order_i_flag($tabarticle['i_flag'], $tabarticle['c_origine']);
                if ($tabarticle['c_origine'] != $_ENV['INSTANCE'])
                    $resSite = new Melty_RAII_Router_Instance($tabarticle['c_origine']);
                $tabarticle['url_article'] = $this->get_url($tabarticle['id_article'], $tabarticle['url']);

                $tabarticle['published_url_article'] = !empty($tabarticle['published_url'])
                    ? $this->get_url($tabarticle['id_article'], $tabarticle['published_url'])
                    : $tabarticle['url_article'];

                $tabarticle['url_thema'] = $this->get_url_thema($tabarticle['url_thema']);
                if (isset($resSite))
                    unset($resSite);
                $tabarticle['url_membre'] = $this->lib['membre']->get_url($tabarticle['id_membre'], $tabarticle['login']);
                if ($tab_article['nbr'] < $a)
                    $tab_article['data'][] = $tabarticle;
                $tab_article['nbr']++;
            }

            $tab_article['current'] = ($de) ? $de : 0;
            return $tab_article;
        }
        else
            return Errno::DB_ERROR;
    }

    public function get_flag_edito()
    {
        $tab_flags = [];

        $tab_flags["exclu"]["nom"] = trans("Exclu");
        $tab_flags["exclu"]["url"] = trans_url("actu-exclu");
        $tab_flags["exclu"]["rss"] = $tab_flags["exclu"]["url"] . ".rss";
        $tab_flags['exclu']['id_flag'] = 4;

        $tab_flags["discover"]["nom"] = trans("Découvertes");
        $tab_flags["discover"]["url"] = trans_url("actu-decouvertes");
        $tab_flags["discover"]["rss"] = $tab_flags["discover"]["url"] . ".rss";
        $tab_flags['discover']['id_flag'] = 3;

        $tab_flags["reportage"]["nom"] = trans("Reportages");
        $tab_flags["reportage"]["url"] = trans_url("actu-reportages");
        $tab_flags["reportage"]["rss"] = $tab_flags["reportage"]["url"] . ".rss";
        $tab_flags['reportage']['id_flag'] = 2;

        $tab_flags["interview"]["nom"] = trans("Interviews");
        $tab_flags["interview"]["url"] = trans_url("actu-interviews");
        $tab_flags["interview"]["rss"] = $tab_flags["interview"]["url"] . ".rss";
        $tab_flags['interview']['id_flag'] = 1;

        return $tab_flags;
    }

    public function get_conf_i_flag($c_origine = NULL)
    {
        if (!isset($c_origine))
            $c_origine = $_ENV['INSTANCE'];
        /* Le flag est un set */
        $tab_i_flag['home']['type'] = 'flag';

        // Si on n'est pas une super instance, on propose l'i_flag home_master
        if (Melty_Factory::getMUM()->getMasterInstanceName() != $c_origine)
            $tab_i_flag['home_master']['type'] = 'flag';

        // edito
        $tab_i_flag['exclu']['type'] = 'flag';
        $tab_i_flag['reportage']['type'] = 'flag';
        $tab_i_flag['interview']['type'] = 'flag';
        $tab_i_flag['discover']['type'] = 'flag';

        //old_flags
        $tab_i_flag['redac']['type'] = 'flag';
        $tab_i_flag['sexy']['type'] = 'flag';
        $tab_i_flag['choc']['type'] = 'flag';
        $tab_i_flag['osef']['type'] = 'flag';
        $tab_i_flag['breve']['type'] = 'flag';

        //meltyfood
        $tab_i_flag['selection_recette']['type'] = 'flag';

        // web series
        $tab_i_flag['bonus']['type'] = 'flag';
        $tab_i_flag['episode']['type'] = 'flag';

        // air of melty
        $tab_i_flag['etudes']['type'] = 'flag';

        // mcm
        $tab_i_flag['emission']['type'] = 'flag';

        // regie
        $tab_i_flag['sponso']['type'] = 'flag';

        // gaming zone
        $tab_i_flag['jeuxvideo_pc']['type'] = 'flag';
        $tab_i_flag['jeuxvideo_consoles']['type'] = 'flag';
        $tab_i_flag['jeuxvideo_mobile']['type'] = 'flag';
        $tab_i_flag['jeuxvideo_web']['type'] = 'flag';

        //edito
        $tab_i_flag['edito']['type'] = 'flag';

        /* Le display est un enum */
        $tab_i_flag['lite']['type'] = 'display';
        $tab_i_flag['full']['type'] = 'display';
        $tab_i_flag['classic']['type'] = 'display';
        // On deplace le flag interview en type flag
        //$tab_i_flag['interview']['type'] = 'display';

        //old display
        $tab_i_flag['video']['type'] = 'display';
        $tab_i_flag['titre']['type'] = 'display';
        $tab_i_flag['image']['type'] = 'display';


        /* Le type est un enum */
        $tab_i_flag['test']['type'] = 'type';
        $tab_i_flag['qcm']['type'] = 'type';
        $tab_i_flag['poster']['type'] = 'type';
        //ALORS ICI, mecton qui vient essayer de comprendre ce qui ce passe
        //tu DOIS faire gaffe : on écrase le flag classic.type
        //ne me demande pas pourquoi, mais fais attention a l'edition d'article
        $tab_i_flag['classic']['type'] = 'type'; // Valeur par défaut
        return $tab_i_flag;
    }

    /**
     * Récupère l'i_flag spécifique (type ou display) d'un article.
     *
     * get_i_flag($id_article, 'type') donne le type de l'article.
     * get_i_flag($id_article, 'display') donne le display de l'article.
     */
    public function get_i_flag($id_article, $i_flag_category)
    {
        $q = 'SELECT i_flag FROM system2_article WHERE id = ' . (int)$id_article;
        $res = $this->sql->query($q);
        if ($res === FALSE)
            return Errno::DB_ERROR;
        $iflags = $this->order_i_flag($res->fetchColumn(0));
        return isset($iflags[$i_flag_category][0]) ? $iflags[$i_flag_category][0] : NULL;
    }

    /**
     * Test si l'article est flager tel qu'on le souhaite.
     * $in : mixed. int ou array. si c'est un int, on fait une requete, si c'est un array on test direct dessus
     * $flags : string. Separer par des virgules si on souhaites en tester plusieurs
     * $strict: bool. Si TRUE, il faut obligatoirement que tous les flags soient OK.
     */
    public function test_i_flag($in, $flags, $strict = FALSE, &$matches = FALSE)
    {
        $tab_i_flags = NULL;
        $id_article = NULL;

        if (is_array($in))
            $tab_i_flags = $in;
        else
            $id_article = (int)$in;

        if ($tab_i_flags === NULL && ($id_article === NULL || $id_article <= 0))
            return (Errno::FIELD_INVALID);

        // On tente de mettre le tableau a plat
        if ($tab_i_flags !== NULL)
        {
            if (isset($tab_i_flags['type'])
                || isset($tab_i_flags['display'])
                || isset($tab_i_flags['flag']))
            {
                $real_tab_i_flags = NULL;
                foreach ($tab_i_flags as $type_list)
                {
                    for ($i = 0; isset($type_list[$i]); $i++)
                        if ($type_list[$i])
                            $real_tab_i_flags[] = $type_list[$i];
                }
                $tab_i_flags = $real_tab_i_flags;
            }
        }

        // On recupere les i_flags de ID_ARTICLE
        if ($id_article !== NULL)
        {
            whine_unused("2012/03/15");
            $q = 'SELECT i_flag FROM system2_article WHERE id = ' . (int)$id_article;
            $res = $this->sql->query($q);
            if ($res === FALSE)
                return Errno::DB_ERROR;
            $tab_i_flags = explode(',', $res->fetchColumn(0));
        }

        if ($tab_i_flags === NULL)
            return FALSE;

        // Et on parcours notre $tab_i_flags
        $tab_check_i_flags = explode(',', $flags);
        $succed = 0;

        for ($i = 0; isset($tab_check_i_flags[$i]); $i++)
        {
            $test = $tab_check_i_flags[$i];
            $need_to_be_set = TRUE;


            if ($tab_check_i_flags[$i][0] == '!')
            {
                $test = substr($tab_check_i_flags[$i], 1);
                $need_to_be_set = FALSE;
            }

            if (in_array($test, $tab_i_flags) === $need_to_be_set)
            {
                $succed++;
                if ($matches !== FALSE)
                    $matches[$test] = TRUE;
            }
        }

        if ($strict === TRUE && $succed == count($tab_check_i_flags))
            return (TRUE);

        if ($strict === FALSE && $succed > 0)
            return (TRUE);

        return (FALSE);
    }

    public function order_i_flag($i_flag, $c_origine = NULL)
    {
        if (!isset($c_origine))
            $c_origine = $_ENV['INSTANCE'];
        if (!empty($i_flag))
            $tab_s_flag = explode(',', $i_flag);

        $tab_conf_i_flag = $this->get_conf_i_flag($c_origine);

        if (!empty($tab_s_flag))
            foreach ($tab_s_flag as $row)
                if (isset($tab_conf_i_flag[$row]) && $tab_conf_i_flag[$row]['type'])
                    $tab_i_flag[$tab_conf_i_flag[$row]['type']][] = $row;

        $tab_i_flag['flag'][0] = !empty($tab_i_flag['flag'][0]) ? $tab_i_flag['flag'][0] : '';
        $tab_i_flag['display'][0] = !empty($tab_i_flag['display'][0]) ? $tab_i_flag['display'][0] : '';

        return $tab_i_flag;
    }

    /**
     * Permet de renvoyer en fonction des i_flags de l'article,
     * le tableau spe correspondant.
     */
    public function get_i_flag_tab_spe($tab_i_flags, $texte, $worker = NULL)
    {
        if (empty($tab_i_flags) || empty($texte))
            return -1;

        $this->test_i_flag($tab_i_flags, "interview,titre,test,selection_recette", FALSE, $matches);

        if (count($matches) <= 0)
            return (-1);
        $tab_ret = NULL;
        if ($worker === NULL)
            $worker = $this->format($texte, 0, 1);
        // Les interviews
        if (isset($matches['interview']) && $matches["interview"] === TRUE)
        {
            // on recherche le premier pcitation ainsi que sa photo associe si elle existe
            $i = 0;
            $found = FALSE;
            foreach ($worker["data"] as $key => $work)
            {
                if (isset($work['type']) && $work['type'] == 'pcitation')
                {
                    $i = $key;
                    $found = TRUE;
                    break;
                }
            }
            if ($found)
            {
                $tab_ret["interview"]["citation"] = $worker["data"][$i]["data"];
                if (isset($worker["data"][$i + 1]["type"])
                    && $worker["data"][$i + 1]["type"] == "pmedia")
                    $tab_ret["interview"]["media"] = $this->lib['media']->media_auth($worker["data"][$i + 1]["media"]);
            }
        }

        // Les titres
        if (isset($matches['titre']) && $matches["titre"] === TRUE)
        {
            // on recherche tous les ptitre de l'article

            foreach ($worker["data"] as $key => $work)
            {
                if (isset($work["type"]) && $work["type"] == "ptitre")
                {
                    $tab_ret["titre"]["data"][] = $work["data"];
                }
            }
        }

        // Les partenaires
        if (isset($matches["selection_recette"])
            && $matches["selection_recette"] === TRUE)
        {
            $tab_ret["partenaires"] = $this->lib['partenaires']->getLinks($texte);
            //$tab_ret["partenaires"] = $this->lib['partenaires']->getData($tab_ret['partenaires']);
        }

        // Les tests
        if (isset($matches['test']) && $matches['test'] === TRUE)
        {
            // on recherche le pcritique
            // si il est present, on choppe la note, ou au pire des cas au moins le texte

            $critique = -1;
            $note = -1;
            $resum = -1;

            foreach ($worker["data"] as $key => $work)
            {
                if (isset($work["type"]) && $work["type"] == "pcritique")
                {
                    $critique = $key;
                }
                else if ($critique >= 0)
                {
                    if ($work['type'] && $work['type'] == "pnote")
                    {
                        $note = $key;
                    }
                    if ($work['type'] && $work['type'] == 'p')
                    {
                        $resum = $key;
                    }

                    if ($note >= 0 && $resum >= 0)
                        break;
                }
            }

            if (isset($worker["data"][$critique]["data"]))
                $tab_ret["test"]["critique"] = $worker["data"][$critique]["data"];
            else
                $tab_ret["test"]["critique"] = NULL;
            if ($note >= 0)
            {
                $tab_ret["test"]["note"]["data"] = $worker["data"][$note]["data"];

                // DEFAULT
                $default_max_note = 20;

                // on tente de split
                $lpos = strrpos($tab_ret["test"]["note"]["data"], '/');

                if ($lpos)
                {
                    $tab_ret["test"]["note"]['tab_data']['note'] = trim(substr($tab_ret["test"]["note"]["data"], 0, $lpos));
                    $tab_ret["test"]["note"]['tab_data']['max'] = trim(substr($tab_ret["test"]["note"]["data"], $lpos + 1));
                }
                else
                {
                    $tab_ret["test"]["note"]['tab_data']['note'] = $tab_ret["test"]["note"]["data"];
                    $tab_ret["test"]["note"]['tab_data']['max'] = $default_max_note;
                }
            }
            if ($resum >= 0)
                $tab_ret["test"]["resum"] = $worker["data"][$resum]["data"];
        }

        return $tab_ret;
    }

    /**
     * Remove pmedia who have media_protection == 1 in tab_format
     */
    public function remove_old_medias(&$tab_article)
    {
        if ($tab_article['article']['media_protection'] != "1")
            return;

        $real_data = [];
        foreach ($tab_article['format']['data'] as $row)
            if (!isset($row['type']) || ($row['type'] != "pmedia" && $row['type'] != "pimg"))
                array_push($real_data, $row);

        $tab_article['format']['data'] = $real_data;
    }

    protected function media_protection($anti_protection, $create_date,
                                        $date_first_publish)
    {
        // media protection by date
        $media_date_limite = strtotime("-1 year");
        $article_time = strtotime($create_date);
        // mode sans echec
        if (isset($article_time) || empty($article_time) || !$article_time || $article_time < 0)
            $article_time = strtotime($date_first_publish);

        if ($_ENV['MEDIA_PROTECTION'] === FALSE)
            return (0);

        return $anti_protection == self::MEDIA_PROTECTION_DISABLED ? 0 : ($article_time - $media_date_limite < 0 ? 1 : 0);
    }

    public function get_one($id)
    {
        if (empty($id))
            return Errno::NO_DATA;

        $q = "SELECT
               " . $this->c_f('system2_article') . ",
           system2_article.id,
           system2_article.titre,
           system2_article.id_membre,
           system2_article.aresum,
           system2_article.texte,
           system2_article.texte_save,
           system2_article.reaction,
           system2_article.author,
           system2_article.id,
           system2_article.id AS id_article,
           system2_article.i_flag,
           system2_article.url,
           system2_article.published_url,
           system2_article.alt_url,
           system2_article.credit,
           system2_article.source,
           system2_article.social_title,
           system2_article.id_thema,
           system2_article.etat,
           system2_article.anti_protection,
           system2_article.date,
           system2_article.create_date,
           system2_article.published_date,
           system2_article.last_date,
           system2_article.pub_date,
           system2_article.edit_date,
           system2_article.val_id_membre,
           system2_article.censored_in_homepage,
           system2_article.censored_in_homepage_master,
           system2_article.author_social_link,
           DATE(system2_article.event_date) AS event_date,
           system2_article_hits.hits,
           system4_article_hits_unique.hits AS hits_unique,
           system2_article_hits.likes,
           system2_article_hits.tweets,
           system2_article_hits.plusones,
           system2_article_hits.paragraphes as corps_nbr_paragraphes,
           system2_article_hits.images as corps_nbr_images,
           system2_article_hits.videos as corps_nbr_videos,
           system2_article_hits.malus,
           system4_article_visitors_unique_firstday.count AS visitors_unique_firstday,

           galerie.id_galerie,

           galerie_media.id_galerie_media,
           galerie_media.fingerprint AS fingerprint_media,
           galerie_media.description,
           galerie_media.tab_spe_tips,
           media.titre AS tags_media,
           media.format AS format_media,
           media.duration AS duration_media,
           COALESCE(galerie_media.crop_h, media.height) AS height_media,
           COALESCE(galerie_media.crop_w, media.width) AS width_media,
           media.height AS height_so,
           media.width AS width_so,
           media.type AS type_media,

           system2_thema.nom AS nom_thema,
           system2_thema.url AS url_thema,
           system2_thema.type AS type_thema,

           system2_info.login,
           system2_info.prenom,
           system2_info.nom,

           system2_com_index.nbr AS nbr_com,
           system2_com_index.status AS status_com,

           galerie_media_author.id_galerie_media AS id_galerie_media_membre_author,
           galerie_media_author.fingerprint AS fingerprint_media_membre_author,
           media_author.type AS type_media_membre_author,
           media_author.format AS format_media_membre_author,

           tag.id_tag,
           tag.list_tag_clean AS tag_list,

           IF (geocoding.id_type, 1, 0) AS has_address,
           geocoding.address AS geo_address,
           city.name AS city_name,
           city.cp AS city_cp

          FROM system2_article
     LEFT JOIN system2_article_hits ON system2_article_hits.id_article = system2_article.id
     LEFT JOIN system4_article_hits_unique ON system4_article_hits_unique.id_article = system2_article.id
     LEFT JOIN system4_article_visitors_unique_firstday ON system4_article_visitors_unique_firstday.id_article = system2_article.id

     LEFT JOIN system2_galerie AS galerie ON galerie.type = 'article'
               AND galerie.id_type = system2_article.id
               AND galerie.etat != -100
     LEFT JOIN system5_galerie_media AS galerie_media ON galerie_media.id_galerie = galerie.id_galerie
               AND galerie_media.id_galerie_media = galerie.id_avatar
               AND galerie_media.etat != -100
     LEFT JOIN system2_media AS media ON media.id_media = galerie_media.id_media
               AND media.etat != -100

     LEFT JOIN system2_thema ON system2_thema.id_thema = system2_article.id_thema

     LEFT JOIN system2_com_index ON system2_com_index.id_type = system2_article.id
               AND system2_com_index.type = 'article'

     LEFT JOIN system2_info ON system2_info.id_membre = system2_article.id_membre
     LEFT JOIN system2_galerie AS galerie_author ON galerie_author.type = 'membre'
               AND galerie_author.id_type = system2_article.id_membre
               AND galerie_author.etat != -100
     LEFT JOIN system5_galerie_media AS galerie_media_author
               ON galerie_media_author.id_galerie = galerie_author.id_galerie
               AND galerie_media_author.id_galerie_media = galerie_author.id_avatar
               AND galerie_media_author.etat != -100
     LEFT JOIN system2_media AS media_author ON media_author.id_media = galerie_media_author.id_media
               AND media_author.etat != -100

     LEFT JOIN system2_tag AS tag ON tag.id_type = system2_article.id
           AND tag.type = 'article'

     LEFT JOIN system5_geocoding AS geocoding
               ON geocoding.id_type = system2_article.id
               AND geocoding.type = 'article'
     LEFT JOIN system5_city AS city
               ON geocoding.id_city = city.id_city

         WHERE system2_article.id = " . (int)$id . "
         LIMIT 1";

        /* Attention si vous voulez memoizer, un article peut se faire update
           par des fonctions de media_lib en rapport avec les galeries */
        $sql = $this->sql->query($q);
        if ($sql === FALSE)
            return Errno::DB_ERROR;

        $tabarticle = $sql->fetch();
        if ($tabarticle === FALSE)
            return Errno::NO_DATA;

        if ($tabarticle)
        {
            $tabarticle['tags_media'] = Melty_Helper_String::explode(',', $tabarticle['tags_media']);
            if ($tabarticle['c_origine'] != $_ENV['INSTANCE'])
                $resSite = new Melty_RAII_Router_Instance($tabarticle['c_origine']);
            $tabarticle['url_thema_clean'] = $tabarticle['url_thema'];
            $tabarticle['url_thema'] = $this->get_url_thema($tabarticle['url_thema']);
            $tabarticle['url_article'] = $this->get_url($tabarticle['id_article'], $tabarticle['url']);

            $tabarticle['published_url_article'] = !empty($tabarticle['published_url'])
                ? $this->get_url($tabarticle['id_article'], $tabarticle['published_url'])
                : $tabarticle['url_article'];

            if (isset($resSite))
                unset($resSite);

            $tabarticle['tab_i_flag'] = $this->order_i_flag($tabarticle['i_flag'], $tabarticle['c_origine']);
            $tabarticle['url_membre'] = $this->lib['membre']->get_url($tabarticle['id_membre'], $tabarticle['login']);
            // publication date
            if ($tabarticle['pub_date'])
            {
                $pub_time = strtotime($tabarticle['pub_date']);
                $tabarticle['tab_pub_date']['date'] = date("Y-m-d", $pub_time);
                $tabarticle['tab_pub_date']['hour'] = date("H", $pub_time);
                $tabarticle['tab_pub_date']['minute'] = date("i", $pub_time);
                $tabarticle['tab_pub_date']['second'] = date("s", $pub_time);
            }

            // media protection by date
            $tabarticle["media_protection"] = $this->media_protection($tabarticle['anti_protection'], $tabarticle['create_date'], $tabarticle['date']);

            // On balance les liens partenaires :)

            if ($this->test_i_flag($tabarticle['tab_i_flag'], "selection_recette") === TRUE)
            {
                $tabarticle['partenaires'] = $this->lib['partenaires']->getLinks($tabarticle['texte']);
                if ($tabarticle['partenaires'])
                    $tabarticle['partenaires'] = $this->lib['partenaires']->getData($tabarticle['partenaires']);
            }

            // tips decoding :)
            if (!empty($tabarticle['tab_spe_tips']))
                $tabarticle['real_tab_spe_tips'] = unserialize(base64_decode($tabarticle['tab_spe_tips']));

            $tabarticle['status_com'] = $tabarticle['status_com'] === NULL ?
                melty_lib_com::INDEX_STATUS_ENABLE :
                (int)$tabarticle['status_com'];

            $tabarticle['nbr_reactions'] = [
                'nbr_com' => (int)$tabarticle['nbr_com'],
                'nbr_likes' => (int)$tabarticle['likes'],
                'nbr_tweets' => (int)$tabarticle['tweets'],
                'nbr_plusones' => (int)$tabarticle['plusones']
                ];
            $tabarticle['nbr_reactions']['total'] = array_sum($tabarticle['nbr_reactions']);

            $tabarticle['have_adresse'] = (bool)$tabarticle['has_address'];
            $tabarticle['tab_address'] = FALSE;

            if ($tabarticle['have_adresse'] === TRUE)
                $tabarticle['tab_address'] = $this->lib['geocoding']->getInfos('article', $id);

            return $tabarticle;
        }
        return Errno::NO_DATA;
    }

    public function get_one_admin($id_article)
    {
        $tabarticle = $this->get_one($id_article);
        if (!is_array($tabarticle))
            return $tabarticle;

        //$tabarticle['reserve'] = $this->lib['reserve']->get_priority($id_article);

        $share = $this->get_share_options($id_article);
        // On crée les options si l'article ne les AS pas encore.
        if (!$share)
        {
            $this->set_share_options($id_article, []);
            $share = $this->get_share_options($id_article);
        }
        $tabarticle['tab_share'] = $share;
        $tabarticle['c_site_real'] = Melty_Factory::getMUM()->getInstancesLinked(
            Melty_MUM::SITE_TYPE_ARTICLE, $id_article);

        return $tabarticle;
    }

    public function count_increase($id_article)
    {
        $q = 'UPDATE system2_article_hits
                 SET system2_article_hits.hits = system2_article_hits.hits + 1
               WHERE system2_article_hits.id_article = ' . (int)$id_article;

        return $this->sql->query($q);
    }

    public function get_arround($published_date, $id_thema = NULL, $nb_left = 1, $nb_right = 1)
    {
        $sq_thema = '1';
        if (isset($id_thema))
            $sq_thema = 'system2_article.id_thema = ' . (int)$id_thema;
        $q = '
          (SELECT id, titre, url, published_date FROM system2_article USE INDEX (`co_e_pd`) WHERE system2_article.c_origine = ' . (int)$_ENV['C_ORIGINE'] . ' AND system2_article.etat = ' . self::ETAT_PUBLISHED . ' AND system2_article.published_date > ' . $this->sql->quote($published_date) . ' AND ' . $sq_thema . ' ORDER BY system2_article.published_date ASC LIMIT ' . ((int)$nb_left) . ')
          UNION
          (SELECT id, titre, url, published_date FROM system2_article USE INDEX (`co_e_pd`) WHERE system2_article.c_origine = ' . (int)$_ENV['C_ORIGINE'] . ' AND system2_article.etat = ' . self::ETAT_PUBLISHED . ' AND system2_article.published_date <= ' . $this->sql->quote($published_date) . ' AND ' . $sq_thema . ' ORDER BY system2_article.published_date DESC LIMIT ' . ((int)$nb_right + 1) . ')
          ORDER BY published_date
        ';

        $sql = $this->sql->query($q);
        if (is_object($sql))
        {
            $tab_articles = $sql->fetchAll();
            if (count($tab_articles) == 0)
                return NULL;

            $idx = 0;
            while (isset($tab_articles[$idx]))
            {
                if ($tab_articles[$idx]['published_date'] == $published_date)
                    break;
                $idx++;
            }

            $tab_article_arround = ['index' => NULL, 'data' => []];
            for ($i = $idx - $nb_left; $i < $idx + $nb_right + 1; $i++)
                if (isset($tab_articles[$i]))
                {
                    if ($tab_articles[$i]['published_date'] == $published_date)
                        $tab_article_arround['index'] = count($tab_article_arround['data']);

                    $tab_articles[$i]['url_article'] = $this->get_url($tab_articles[$i]['id'], $tab_articles[$i]['url']);
                    $tab_article_arround['data'][] = $tab_articles[$i];
                }
            return ($tab_article_arround);
        }
        return NULL;
    }

    public function get_for_teddy($id_article)
    {
        if (empty($id_article))
            return Errno::FIELD_EMPTY;
        $q = "SELECT " . $this->c_f('sa') . ",
                sa.id AS id_article,
                sa.titre,
                sa.url,
                sa.published_url,
                sa.id_thema,
                sa.i_flag,
                sa.alt_url,
                si.id_membre,
                si.login,
                system2_world_index.nom as nom_world,
                system2_world_index.url as url_world,
                system2_world_index.id_world_index as id_world_index,
                galerie_media_article.id_galerie_media,
                GROUP_CONCAT(sw.id_world_index) AS id_world_index
            FROM system2_article AS sa
            INNER JOIN system2_info AS si ON si.id_membre = sa.id_membre
            LEFT JOIN system2_world AS sw ON sw.type = 'article' AND sw.id_type = sa.id
            LEFT JOIN system2_world_index ON system2_world_index.id_world_index = sa.id_world_index
                  AND system2_world_index.etat > " . melty_lib_world::ETAT_DELETED . "
            LEFT JOIN system2_galerie AS galerie_article ON galerie_article.type = 'article'
                      AND galerie_article.id_type = sa.id
                      AND galerie_article.etat != -100
            LEFT JOIN system5_galerie_media AS galerie_media_article
                      ON galerie_media_article.id_galerie = galerie_article.id_galerie
                      AND galerie_media_article.id_galerie_media = galerie_article.id_avatar
                      AND galerie_media_article.etat != -100
            WHERE sa.id = '$id_article'
            GROUP BY id_article
            LIMIT 1";

        $sql = $this->sql->query($q);
        if (is_object($sql))
        {
            $tabarticle = $sql->fetch();
            if ($tabarticle['c_origine'] != $_ENV['INSTANCE'])
                $resSite = new Melty_RAII_Router_Instance($tabarticle['c_origine']);
            $tabarticle['url_article'] = $this->get_url($tabarticle['id_article'], $tabarticle['url']);

            $tabarticle['published_url_article'] = !empty($tabarticle['published_url'])
                ? $this->get_url($tabarticle['id_article'], $tabarticle['published_url'])
                : $tabarticle['url_article'];

            $tabarticle['url_world_index'] = $this->lib['world']->get_url_index($tabarticle['id_world_index'], $tabarticle['url_world']);

            unset($tabarticle['url']);
            if (isset($resSite))
                unset($resSite);

            // URL Redacteur
            $tabarticle['url_membre'] = $this->lib['membre']->get_url($tabarticle['id_membre'], $tabarticle['login']);
            // Tab i_flags
            $tabarticle['tab_i_flag'] = $this->order_i_flag($tabarticle['i_flag'], $tabarticle['c_origine']);
            unset($tabarticle['i_flag']);
            // Worlds
            $tabarticle['tab_world_index'] = explode(',', $tabarticle['id_world_index']);
            unset($tabarticle['id_world_index']);

            // Notice
            $tabarticle['notice'] = Melty_Helper_Smarty::template(array('skin' => 'default',
                                                                        'module' => 'toasts',
                                                                        'view' => 'notice/article'), ['tab_article' => $tabarticle]);

            return $tabarticle;
        }
        else
        {
            return Errno::DB_ERROR;
        }
    }

    public function get_lite($id_article, $etat = NULL, $de = NULL, $a = NULL,
                             $search = NULL, $filteredByInstance = FALSE,
                             $order = NULL)
    {
        $w = [];

        if ($etat === NULL && empty($id_article))
            return Errno::FIELD_EMPTY;
        else if ($etat !== NULL && Melty_Helper_Reflection::constantsValueExist($this, (int)$etat, 'ETAT_') === FALSE)
            return Errno::FIELD_INVALID;

        if ($etat !== NULL)
            $w[] = 'sa.etat = ' . (int)$etat;
        if (!empty($id_article))
            $w[] = 'sa.id IN ( ' . $this->sql->quote($id_article, Melty_Database_SQL::ARRAY_OF_INT) . ')';
        if (!empty($search))
            $w[] = 'sa.titre LIKE ' . $this->sql->quote('%' . $search . '%');

        $q = 'SELECT ' . $this->c_f() . ',
                     sa.titre,
                     sa.id_membre,
                     sa.aresum AS resum,
                     sa.texte,
                     sa.texte_save,
                     sa.id,
                     sa.id AS id_article,
                     sa.i_flag,
                     sa.url,
                     sa.published_url,
                     sa.alt_url,
                     sa.id_thema,
                     sa.id_galerie,
                     sa.etat,
                     sa.anti_protection,
                     sa.date,
                     sa.id_galerie,
                     sa.create_date,
                     sa.pub_date,
                     sa.social_title,
                     sa.id_world_index
                FROM system2_article AS sa';
        if ($filteredByInstance === TRUE)
            $q .= $this->c_j(Melty_MUM::SITE_TYPE_ARTICLE, 'id', 'sa');
        if (!empty($w))
            $q .= ' WHERE ' . implode(' AND ', $w);

        if ($order !== FALSE)
        {
            if ($order === NULL)
                $q .= ' ORDER BY sa.date DESC';
            else if ($order instanceof Melty_Database_Interface_Order)
                $q .= ' ORDER BY ' . $order;
        }

        if ($a !== NULL)
            $q .= ' LIMIT ' . (int)$a;
        if ($de !== NULL)
            $q .= ' OFFSET ' . (int)$de;

        $sql = $this->sql->query($q);
        if ($sql === FALSE)
            return Errno::DB_ERROR;

        $tabarticle = [];
        while ($row = $sql->fetch())
        {
            if ($row['c_origine'] != $_ENV['INSTANCE'])
                $resSite = new Melty_RAII_Router_Instance($row['c_origine']);
            $row['url_article'] = $this->get_url($row['id_article'], $row['url']);

            $row['published_url_article'] = !empty($row['published_url'])
                ? $this->get_url($row['id_article'], $row['published_url'])
                : $row['url_article'];

            if (isset($resSite))
                unset($resSite);
            $row['aresum'] = $row['resum'];

            // media protection by date
            $row["media_protection"] = $this->media_protection($row['anti_protection'], $row['create_date'], $row['date']);

            $tabarticle[] = $row;
        }
        return $tabarticle;
    }

    public function get_extern_lite($site, $id_article_extern)
    {
        whine_unused("2014/08/01");
        if (empty($id_article_extern))
            return Errno::FIELD_EMPTY;

        $q = 'SELECT
            ' . $this->c_f() . ',
           article.id,
           article.titre,
           article.id_membre,
           article.aresum AS resum,
           article.texte,
           article.texte_save,
           article.id AS id_article,
           article.i_flag,
           article.url,
           article.alt_url,
           article.id_thema,
           article.id_galerie,
           article.etat,
           article.anti_protection,
           article.date,
           article.last_date,
           article.social_title,
           article.id_galerie

          FROM system5_article_extern_link AS link
          LEFT JOIN system2_article AS article
               ON article.id = link.id_article_intern
          WHERE link.site = ' . $this->sql->quote($site) . '
            AND link.id_article_extern = ' . (int)$id_article_extern;

        $sql = $this->sql->query($q);
        if ($sql === FALSE)
            return Errno::DB_ERROR;

        $row = $sql->fetch();

        // Si la liaison existe mais pas l'article, on l'a supprime
        if (!empty($row) && empty($row['id']))
        {
            $q = 'DELETE
                  FROM system5_article_extern_link
                  WHERE site=' . $this->sql->quote($site) . '
                    AND id_article_extern=' . (int)$id_article_extern;

            $this->sql->query($q);
        }

        return $row;
    }

    /**
     * Add `get_referencement` for each `id_article` in &$array with a
     * single SQL query.
     *
     * Your 'id_article' may be at any depth inside &$array, they will
     * be found.
     *
     * For example :
     * $articles = [['id_article' => 42], ['id_article' => 43]];
     * add_metadata_for_articles($articles);
     * var_dump($articles); (expected)
     * results in a huge :
     * [['id_article' => 42,
     *   ['article' => ['titre' => ..., ...]]
     *  ],
     *  ['id_article' => 43,
     *   ['article' => ['titre' => ..., ...]]
     *  ]]
     *
     */
    public function add_metadata_for_articles(&$array, $get_program_infos = FALSE)
    {
        $this->inject($array,
                      function($ids_article) use ($get_program_infos)
                      {
                          return $this->get_referencements($ids_article,
                                                           $get_program_infos);
                      }, 'id_article', 'article', 'article');
    }

    public function get_referencements($ids_article,
                                       $get_program_infos = FALSE)
    {
        if (empty($ids_article))
            return Errno::FIELD_EMPTY;

        $sql_get_last_video = '';
        $sql_get_episode = '';

        if ($get_program_infos)
        {
            $sql_get_last_video = ", (SELECT duration FROM system2_media AS video WHERE video.id_galerie = galerie.id_galerie AND video.format = 'video' LIMIT 1) AS media_duration";
            $sql_get_episode = ", (SELECT episode FROM system4_calendar_event AS calendar_event WHERE calendar_event.type  = 'article' AND calendar_event.id_type = system2_article.id LIMIT 1) AS episode";
        }

        $q = "SELECT
               " . $this->c_f('system2_article') . ",
               system2_article.titre,
               system2_article.aresum AS resum,
               system2_article.id AS id_article,
               system2_article.url,
               system2_article.published_url,
               system2_article.published_date,
               system2_article.edit_date,
               system2_article.id_membre,
               system2_article.alt_url,
               system2_article.id_thema,
               system2_article.etat,
               system2_article.anti_protection,
               system2_article.create_date,
               system2_article.date,
               system2_article.i_flag,
               system2_article.id_world_index,
               system2_article.author_social_link,
               system2_article.social_title,

               system2_article_hits.likes,
               system2_article_hits.tweets,
               system2_article_hits.plusones,

               system2_thema.id_thema,
               system2_thema.url AS url_thema,
               system2_thema.nom AS nom_thema,
               system2_thema.type AS type_thema,
               system2_world_index.nom AS nom_world,
               system2_world_index.url AS url_world,
               system2_world_index.c_origine AS c_origine_world,
               GROUP_CONCAT(system2_world.id_world_index) AS ids_world,

               galerie.id_galerie,
               galerie_media.id_galerie_media,
               galerie_media.fingerprint AS fingerprint_media,
               media.type AS type_media,
               media.format AS format_media,

               system2_com_index.id_com_index,
               system2_com_index.nbr AS nbr_com,
               system2_info.login,
               system2_info.login,
               system2_info.prenom,
               system2_info.nom,
               system4_profil.website AS author_website,
               tag.list_tag_clean AS tag_list

               $sql_get_last_video
               $sql_get_episode

          FROM system2_article
     LEFT JOIN system2_thema ON system2_thema.id_thema = system2_article.id_thema
     LEFT JOIN system2_galerie AS galerie ON galerie.type = 'article'
               AND galerie.id_type = system2_article.id
               AND galerie.etat != -100

     LEFT JOIN system5_galerie_media AS galerie_media ON galerie_media.id_galerie = galerie.id_galerie
               AND galerie_media.id_galerie_media = galerie.id_avatar
               AND galerie_media.etat != -100
     LEFT JOIN system2_media AS media ON media.id_media = galerie_media.id_media
               AND media.etat != -100
     LEFT JOIN system2_com_index ON system2_com_index.id_type = system2_article.id AND system2_com_index.type = 'article'
     LEFT JOIN system2_article_hits ON system2_article_hits.id_article = system2_article.id
     LEFT JOIN system2_world ON system2_world.id_type = system2_article.id
               AND system2_world.type = 'article'
     LEFT JOIN system2_world_index ON system2_world_index.id_world_index = system2_article.id_world_index
           AND system2_world_index.etat > " . melty_lib_world::ETAT_DELETED . "
     LEFT JOIN system2_info ON system2_info.id_membre = system2_article.id_membre
     LEFT JOIN system4_profil ON system4_profil.id_membre = system2_article.id_membre
     LEFT JOIN system2_tag AS tag ON tag.id_type = system2_article.id
           AND tag.type = 'article'

         WHERE system2_article.id IN (" . $this->sql->quote($ids_article, Melty_Database_SQL::ARRAY_OF_INT)
            . ') GROUP BY system2_article.id';

        $sql = $this->sql->memoized_fetchAll($q);
        if ($sql === FALSE)
            return Errno::DB_ERROR;

        $articles = [];
        foreach ($sql as $tabarticle)
        {
            if ($tabarticle['c_origine'] != $_ENV['INSTANCE'])
                $resSite = new Melty_RAII_Router_Instance($tabarticle['c_origine']);
            $tabarticle['url_article_plink'] = $tabarticle['url_article'] = $this->get_url($tabarticle['id_article'], $tabarticle['url']);

            $tabarticle['tinyurl'] = $this->lib['tinyurl']->tinyurl_from_article_id($tabarticle['id_article']);

            $tabarticle['published_url_article'] = !empty($tabarticle['published_url'])
                ? $this->get_url($tabarticle['id_article'], $tabarticle['published_url'])
                : $tabarticle['url_article'];

            $tabarticle['nbr_reactions'] = [
                'nbr_com' => (int)$tabarticle['nbr_com'],
                'nbr_likes' => (int)$tabarticle['likes'],
                'nbr_tweets' => (int)$tabarticle['tweets'],
                'nbr_plusones' => (int)$tabarticle['plusones']
                ];
            $tabarticle['nbr_reactions']['total'] = array_sum($tabarticle['nbr_reactions']);

            $tabarticle['url_thema_clean'] = $tabarticle['url_thema'];
            $tabarticle['url_thema_rss'] = $this->lib['thema']->get_url_rss($tabarticle['url_thema'], $tabarticle['id_thema']);
            //ne pas reafecter url_thema avant d'avoir fini de l'utiliser, sinon ca fait des chocapic !
            $tabarticle['url_thema'] = $this->get_url_thema($tabarticle['url_thema']);
            $tabarticle['tab_i_flag'] = $this->order_i_flag($tabarticle['i_flag'], $tabarticle['c_origine']);

            // media protection by date
            $tabarticle["media_protection"] = $this->media_protection($tabarticle['anti_protection'], $tabarticle['create_date'], $tabarticle['date']);
            if (isset($tabarticle['url_world']))
                $tabarticle['url_world_index'] = $this->lib['world']->get_url_index($tabarticle['id_world_index'], $tabarticle['url_world']);
            else
                $tabarticle['url_world_index'] = '';
            $tabarticle['world'] = [];
            foreach (Melty_Helper_String::explode(',', $tabarticle['ids_world']) AS $id_world)
                $tabarticle['world'][$id_world] = ['id_world_index' => $id_world];

            if (isset($resSite))
                unset($resSite);

            if ($_SESSION->is_log())
            {
                $tabarticle['is_liked'] = $this->lib['like']->exist('world', $tabarticle['id_world_index'], $_SESSION->get_id_membre());
                foreach (array_keys($tabarticle['world']) as $id_world)
                    $tabarticle['world'][$id_world]['is_liked'] = $tabarticle['is_liked'];
            }

            //hide id_world_index, for add_world_index who follows
            $tabarticle['id_world_index_hidden'] = $tabarticle['id_world_index'];
            unset($tabarticle['id_world_index']);

            $articles[$tabarticle['id_article']] = $tabarticle;
        }

        $this->lib['world']->add_world_index($articles, NULL);

        //unhide id_world_index
        foreach ($articles as &$article)
        {
            $article['id_world_index'] = $article['id_world_index_hidden'];
        } unset($article);
        if (isset($articles))
        {
            $this->add_share_option($articles);
            return $articles;
        }
        return NULL;
    }

    public function get_referencement($id_article)
    {
        $res = $this->get_referencements([$id_article]);
        if (!is_array($res))
            return $res;
        if (!isset($res[$id_article]))
            return Errno::NO_DATA;
        return $res[$id_article];
    }

    public function get_articles($id_article, $etat = self::ETAT_PUBLISHED, $force_all_text = FALSE)
    {
        if (empty($id_article))
            return Errno::FIELD_EMPTY;

        $sq_id_article = 'system2_article.id IN ( ' . $this->sql->quote($id_article, Melty_Database_SQL::ARRAY_OF_INT) . ')';

        if (empty($etat))
            $etat = self::ETAT_PUBLISHED;
        elseif (Melty_Helper_Reflection::constantsValueExist($this, $etat, 'ETAT_') === FALSE)
            return Errno::FIELD_INVALID;

        $s_texte_condition = 'FIND_IN_SET("interview", system2_article.i_flag) OR
                              FIND_IN_SET("titre", system2_article.i_flag) OR
                              FIND_IN_SET("test", system2_article.i_flag)';
        if ($force_all_text === TRUE)
            $s_texte_condition = '1';

        $q = "SELECT
              " . $this->c_f('system2_article') . ",
              system2_article.titre,
              system2_article.id AS id_article,
              system2_article.aresum AS resum,
              system2_article.i_flag,
              IF(" . $s_texte_condition . ", system2_article.texte, NULL) AS texte,
              system2_article.etat,
              system2_article.anti_protection,
              system2_article.url,
              system2_article.published_url,
              system2_article.alt_url,
              system2_article.date,
              system2_article.create_date,
              system2_article.last_date,
              system2_article.published_date,
              system2_article.social_title,

              galerie.id_galerie,
              galerie_media.id_galerie_media,
              galerie_media.fingerprint AS fingerprint_media,
              galerie_media.tab_spe_tips,
              media.titre AS tags_media,
              media.type AS type_media,
              media.format AS format_media,
              media.duration AS duration_media,
              COALESCE(galerie_media.crop_h, media.height) AS height_media,
              COALESCE(galerie_media.crop_w, media.width) AS width_media,
              media.height AS height_so,
              media.width AS width_so,

              system2_world_index.id_world_index,
              system2_world_index.url AS url_world,
              system2_world_index.nom AS nom_world,

              system2_article.id_membre,
              system2_thema.id_thema,
              system2_thema.url AS url_thema,
              system2_thema.nom AS nom_thema,
              system2_thema.type AS type_thema,
              system2_article_hits.likes,
              system2_article_hits.tweets,
              system2_article_hits.plusones,
              galerie.url AS url_galerie,
              ci.nbr AS nbr_com,
              system2_info.login
         FROM system2_article
              " . $this->c_j(Melty_MUM::SITE_TYPE_ARTICLE, 'id') . "
    LEFT JOIN system2_galerie AS galerie ON galerie.type = 'article'
              AND galerie.id_type = system2_article.id
              AND galerie.etat != -100
         JOIN system5_galerie_media AS galerie_media ON galerie_media.id_galerie = galerie.id_galerie
              AND galerie_media.id_galerie_media = galerie.id_avatar
              AND galerie_media.etat != -100
    LEFT JOIN system2_media AS media ON media.id_media = galerie_media.id_media
              AND media.etat != -100
    LEFT JOIN system2_world ON system2_world.type = 'article'
              AND system2_world.id_type = system2_article.id
              AND system2_world.etat = 1
    LEFT JOIN system2_world_index ON system2_world_index.id_world_index = system2_world.id_world_index
          AND system2_world_index.etat > " . melty_lib_world::ETAT_DELETED . "
         JOIN system2_article_hits ON system2_article_hits.id_article = system2_article.id
         JOIN system2_thema ON system2_thema.id_thema = system2_article.id_thema
    LEFT JOIN system2_com_index AS ci ON ci.id_type = system2_article.id AND ci.type = 'article'
         JOIN system2_info ON system2_info.id_membre = system2_article.id_membre
        WHERE system2_article.etat >= " . (int)$etat . " AND " . $sq_id_article;
        $sql = $this->sql->query($q);
        if ($sql === FALSE)
            return Errno::DB_ERROR;
        $tab_article = ['data' => [], 'nbr' => 0];
        while ($tabarticle = $sql->fetch())
        {
            $tabarticle['tags_media'] = Melty_Helper_String::explode(',', $tabarticle['tags_media']);
            $tabarticle['tab_i_flag'] = $this->order_i_flag($tabarticle['i_flag'], $tabarticle['c_origine']);
            if ($tabarticle['c_origine'] != $_ENV['INSTANCE'])
                $resSite = new Melty_RAII_Router_Instance($tabarticle['c_origine']);
            $tabarticle['url_article'] = $this->get_url($tabarticle['id_article'], $tabarticle['url']);

            $tabarticle['published_url_article'] = !empty($tabarticle['published_url'])
                ? $this->get_url($tabarticle['id_article'], $tabarticle['published_url'])
                : $tabarticle['url_article'];

            $tabarticle['nbr_reactions'] = [
                'nbr_com' => (int)$tabarticle['nbr_com'],
                'nbr_likes' => (int)$tabarticle['likes'],
                'nbr_tweets' => (int)$tabarticle['tweets'],
                'nbr_plusones' => (int)$tabarticle['plusones']
                ];
            $tabarticle['nbr_reactions']['total'] = array_sum($tabarticle['nbr_reactions']);

            $tabarticle['url_galerie_old'] = $tabarticle['url_galerie'];
            $tabarticle['url_galerie'] = $this->lib["media"]->galerie_get_url($tabarticle['id_galerie'],
                                                                              $tabarticle['url_galerie']);

            if (isset($resSite))
                unset($resSite);

            // media protection by date
            $tabarticle["media_protection"] = $this->media_protection($tabarticle['anti_protection'], $tabarticle['create_date'], $tabarticle['date']);

            if ($this->test_i_flag($tabarticle['tab_i_flag'], "image,test,poster") === TRUE)
            {
                $tabarticle['tab_media'] = $this->lib['media']->galerie_get_media($tabarticle['id_galerie'], -1);
            }

            // tips decoding :)
            if (!empty($tabarticle['tab_spe_tips']))
                $tabarticle['real_tab_spe_tips'] = unserialize(base64_decode($tabarticle['tab_spe_tips']));

            if (isset($tabarticle['texte']))
            {
                //on recupère le texte formated
                //si on en a besoin,
                //et on l'envoi car il est aussi calculé dans la fct
                // melty_article_lib::get_i_flag_tab_spe
                $worker = NULL;
                if ($force_all_text === TRUE)
                    $worker = $this->format($tabarticle['texte'], 0, 1);

                $tabarticle['tab_spe_format'] = $this->get_i_flag_tab_spe(
                    $tabarticle['tab_i_flag'],
                    $tabarticle['texte'],
                    $worker);

                if ($force_all_text === TRUE)
                    $tabarticle['texte'] = $worker;
                else
                    unset($tabarticle['texte']);
            }

            $tabarticle['url_thema'] = $this->get_url_thema($tabarticle['url_thema']);
            $tabarticle['url_membre'] = $this->lib['membre']->get_url($tabarticle['id_membre'], $tabarticle['login']);
            // on cree le table optimise
            $tab_article['data'][] = $tabarticle;
            $tab_article['nbr']++;
        }
        if (is_array($id_article))
        {
            $articles = $tab_article['data'];
            $tab_article['data'] = [];
            $by_id = [];
            if ($articles !== NULL)
                foreach ($articles as $article)
                    $by_id[$article['id_article']] = $article;
            foreach ($id_article as $id)
                if (isset($by_id[$id]))
                    $tab_article['data'][] = $by_id[$id];
        }
        return $tab_article;
    }

    /*
     * Pour Sphinx, qui commprend les | (ou) et les & (et) :
     * On rewrite la recherche du user, si il met des espaces, on met des ET
     * Si il met des | on ne met pas de & dans ses espaces, typiquement :
     * "foo bar" -> "foo & bar"
     * "foo | bar baz" -> "foo | bar baz"
     */
    public function prepare_query($search)
    {
        $search = Melty_Factory::getSphinx()->Escape($search);
        if (!empty($search) && strpos('|', $search) === FALSE)
            return implode(' & ', explode(' ', $search));
        else
            return str_replace('\|', '|', $search);
    }

    public function search_count($search, $instance = NULL)
    {
        if (preg_match('/^\s*$/', $search))
            return FALSE;

        $search = html_entity_decode($search, ENT_COMPAT, 'UTF-8');

        Melty_Factory::getSphinx()->ResetFilters();
        Melty_Factory::getSphinx()->SetLimits(0, 1);
        $search = $this->prepare_query($search);
        $resultSphinx = Melty_Factory::getSphinx()->Query($search, $instance);

        return $resultSphinx['total_found'];
    }

    public function search($search, $de = 0, $a = 30, $instance = NULL,
                           $id_thema = 0, $raw = FALSE)
    {
        if (preg_match('/^\s*$/', $search))
            return [];
        $search = html_entity_decode($search, ENT_COMPAT, 'UTF-8');

        Melty_Factory::getSphinx()->ResetFilters();
        Melty_Factory::getSphinx()->SetLimits((int)$de, (int)$a);
        if ($id_thema > 0)
            Melty_Factory::getSphinx()->SetFilter('id_thema', [$id_thema]);

        $search = $this->prepare_query($search);

        $fromSphinx = Melty_Factory::getSphinx()->Query($search, $instance);
        if ($fromSphinx && isset($fromSphinx['matches']))
        {
            if ($raw === TRUE)
            {
                return $fromSphinx;
            }
            else
            {
                $matches = $fromSphinx['matches'];
                $fromSQL = $this->get_articles(array_keys($matches));
                return $fromSQL;
            }
        }
        return [];
    }

    public function autocomplete_search($q, $de = 0, $a = 10)
    {
        $res = $this->search($q, $de, $a, NULL, 0, TRUE);
        if ($res !== FALSE && !empty($res) && !empty($res['matches']))
        {
            $ids_article = array_keys($res['matches']);
            $ids_article = $this->sql->quote((array)$ids_article, Melty_Database_SQL::ARRAY_OF_INT);
            $query = 'SELECT article.id, article.titre AS name
                        FROM system2_article AS article
                        WHERE article.id IN (' . $ids_article . ')';

            $stmt = $this->sql->query($query);
            if ($stmt === FALSE)
                return Errno::DB_ERROR;

            return $stmt->fetchAll();
        }
        return [];
    }

    // get top VU < 1h ecrit < 24h group by id_world_index
    public function top_hourly($de = 0, $a = 10, $id_thema = NULL)
    {
        $date_24h = date("Y-m-d H:i:00", Melty_Helper_Date::strtotime("-24 hours"));

        if ($id_thema)
        {
            $tab_t = explode(',', $id_thema);
            $tab_o = [];
            $tab_i = [];
            for ($i = 0; isset($tab_t[$i]); $i++)
            {
                if (substr($tab_t[$i], 0, 1) == "!")
                    $tab_o[] = substr($tab_t[$i], 1);
                else
                    $tab_i[] = $tab_t[$i];
            }

            if (count($tab_i) > 0)
                $where[] = "article.id_thema IN (" . $this->sql->quote($tab_i, Melty_Database_SQL::ARRAY_OF_INT) . ')';
            if (count($tab_o) > 0)
                $where[] = "article.id_thema NOT IN (" . $this->sql->quote($tab_o, Melty_Database_SQL::ARRAY_OF_INT) . ')';
        }

        $world_already_see = $tab_actu = [];
        $pool_duplicate = [];
        $nb_elm = 0;
        $where[] = 'article.etat >= ' . self::ETAT_PUBLISHED;
        $where[] = 'article.published_date > ' . $this->sql->quote($date_24h);
        $where[] = 'counter.type = "article"';
        $where[] = 'counter.ttl = "3600"';
        $where[] = 'counter.name = "views"';
        for ($limit = floor($a * 1.25), $offset = $de; $nb_elm < $a && $offset < $a * 3; $offset += $limit)
        {
            $q = 'SELECT
                    article.id AS id_article,
                    article.c_origine,
                    article.titre,
                    article.alt_url,
                    article.date,
                    article.url AS url_article,
                    article.published_url AS published_url_article,
                    article_hits.likes,
                    article_hits.tweets,
                    article_hits.plusones,
                    system2_com_index.nbr AS nbr_com,
                    world_index.id_world_index,
                    world_index.nom AS nom_world,
                    world_index.url AS url_world,

                    galerie_media.id_galerie_media,
                    galerie_media.fingerprint AS fingerprint_media,
                    media.titre AS tags_media,
                    media.type AS type_media,
                    media.format AS format_media,

                    SUM(counter.value) AS nb_view
                FROM system5_counter AS counter
                INNER JOIN system2_article AS article ON article.id = counter.id_type
                INNER JOIN system2_article_hits AS article_hits ON article_hits.id_article = counter.id_type
                LEFT JOIN system2_com_index ON system2_com_index.type = "article" AND system2_com_index.id_type = article.id
                ' . $this->c_j(Melty_MUM::SITE_TYPE_ARTICLE, 'id', 'article', Melty_Factory::getMUM()->instance->getValue()) . '

                LEFT JOIN system2_galerie AS galerie ON galerie.type = "article"
                          AND galerie.id_type = article.id
                          AND galerie.etat != -100
                LEFT JOIN system5_galerie_media AS galerie_media ON galerie_media.id_galerie = galerie.id_galerie
                          AND galerie_media.id_galerie_media = galerie.id_avatar
                          AND galerie_media.etat != -100
                LEFT JOIN system2_media AS media ON media.id_media = galerie_media.id_media
                          AND media.etat != -100

                INNER JOIN system2_world AS world
                           ON world.id_type = article.id AND world.type = "article"
                INNER JOIN system2_world_index AS world_index ON world_index.id_world_index = world.id_world_index
                       AND world_index.etat > ' . melty_lib_world::ETAT_DELETED . '
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY counter.id_type
                ORDER BY nb_view DESC
                LIMIT ' . (int)$offset . ',' . (int)$limit;
            $sql = $this->sql->query($q);
            if ($sql === FALSE)
                return Errno::DB_ERROR;

            while (($tabactu = $sql->fetch()) && $nb_elm < $a)
            {
                $tabactu['tags_media'] = Melty_Helper_String::explode(',', $tabactu['tags_media']);
                if ($tabactu['c_origine'] != $_ENV['INSTANCE'])
                    $resSite = new Melty_RAII_Router_Instance($tabactu['c_origine']);
                $tabactu['url_article'] = $this->get_url($tabactu['id_article'], $tabactu['url_article']);

                $tabactu['published_url_article'] = !empty($tabactu['published_url_article'])
                    ? $this->get_url($tabactu['id_article'], $tabactu['published_url_article'])
                    : $tabactu['url_article'];

                $tabactu['nbr_reactions'] = [
                    'nbr_com' => (int)$tabactu['nbr_com'],
                    'nbr_likes' => (int)$tabactu['likes'],
                    'nbr_tweets' => (int)$tabactu['tweets'],
                    'nbr_plusones' => (int)$tabactu['plusones']
                    ];
                $tabactu['nbr_reactions']['total'] = array_sum($tabactu['nbr_reactions']);

                $tabactu['url_world_index'] = $this->lib['world']->get_url_index($tabactu['id_world_index'], $tabactu['url_world']);
                if (isset($resSite))
                    unset($resSite);
                if (isset($world_already_see[$tabactu['id_world_index']]))
                {
                    $pool_duplicate[] = $tabactu;
                    continue;
                }
                $tab_actu['data'][] = $tabactu;
                $nb_elm++;
                $world_already_see[$tabactu['id_world_index']] = TRUE;
            }
        }

        if ($nb_elm < $a)
        {
            for (; $nb_elm < $a && !empty($pool_duplicate); $nb_elm++)
                $tab_actu['data'][] = array_shift($pool_duplicate);
        }
        $this->add_share_option($tab_actu);
        return $tab_actu;
    }

    public function top_hourly_for_wat($de = 0, $a = 10, $without_world = NULL)
    {
        $date_24h = date("Y-m-d H:i:00", Melty_Helper_Date::strtotime("-24 hours"));
        if ($without_world)
            $where[] = 'article.id_world_index NOT IN (' . $this->sql->quote((array)$without_world, Melty_Database_SQL::ARRAY_OF_INT) . ')';
        $where[] = 'article.etat >= ' . self::ETAT_PUBLISHED;
        $where[] = 'article.published_date > ' . $this->sql->quote($date_24h);
        $where[] = 'counter.type = "article"';
        $where[] = 'counter.ttl = "3600"';
        $where[] = 'counter.name = "views"';
        $q = 'SELECT article.id AS id_article,
                     article.c_origine,
                     article.titre,
                     article.url AS url_article,
                     article.published_url AS published_url_article
                FROM system5_counter AS counter
          INNER JOIN system2_article AS article ON article.id = counter.id_type
          ' . $this->c_j(Melty_MUM::SITE_TYPE_ARTICLE, 'id', 'article', Melty_Factory::getMUM()->instance->getValue()) . '
               WHERE ' . implode(' AND ', $where) . '
            ORDER BY counter.value DESC
               LIMIT ' . (int)$de . ',' . (int)$a;
        $sql = $this->sql->query($q);
        if ($sql === FALSE)
            return Errno::DB_ERROR;
        while ($tabactu = $sql->fetch())
        {
            if ($tabactu['c_origine'] != $_ENV['INSTANCE'])
                $resSite = new Melty_RAII_Router_Instance($tabactu['c_origine']);
            $tabactu['url_article'] = $this->get_url($tabactu['id_article'], $tabactu['url_article']);

            $tabactu['published_url_article'] = !empty($tabactu['published_url_article'])
                ? $this->get_url($tabactu['id_article'], $tabactu['published_url_article'])
                : $tabactu['url_article'];

            if (isset($resSite))
                unset($resSite);
            $tab_actu['data'][] = $tabactu;
        }
        return $tab_actu;
    }

    /** get top VU < 1h ecrit < 24h group by id_world_index
     *
     * There is no WHERE to don't select world_index when it's deleted
     *   because if you check for one world_index, maybe you really need it
     *   or you know it is deleted
     */
    public function top_hourly_for_world($id_world_index, $de = 0, $a = 10)
    {
        $date_24h = date("Y-m-d H:i:00", Melty_Helper_Date::strtotime("-24 hours"));
        $tab_actu = [];
        $nb_elm = 0;

        for ($limit = floor($a * 1.25), $offset = $de; $nb_elm < $a && $offset < $a * 3; $offset += $limit)
        {
            $q = 'SELECT
                    article.id AS id_article,
                    article.c_origine,
                    article.titre,
                    article.date,
                    article.url AS url_article,
                    article.alt_url,
                    article.published_url AS published_url_article,
                    system2_com_index.nbr as nbr_com,
                    article_hits.likes,
                    article_hits.tweets,
                    article_hits.plusones,
                    world_index.id_world_index,
                    world_index.nom AS nom_world,
                    world_index.url AS url_world,

                    galerie_media.id_galerie_media,
                    galerie_media.fingerprint AS fingerprint_media,
                    media.titre AS tags_media,
                    media.type AS type_media,
                    media.format AS format_media,

                    SUM(counter.value) AS nb_view
                FROM system5_counter AS counter
                INNER JOIN system2_article AS article ON article.id = counter.id_type AND article.c_origine = ' . (int)$_ENV['C_ORIGINE'] . '
                INNER JOIN system2_article_hits AS article_hits ON article_hits.id_article = article.id
                LEFT JOIN system2_com_index ON system2_com_index.type = "article" AND system2_com_index.id_type = article.id

                LEFT JOIN system2_galerie AS galerie ON galerie.type = "article"
                          AND galerie.id_type = article.id
                          AND galerie.etat != -100
                LEFT JOIN system5_galerie_media AS galerie_media ON galerie_media.id_galerie = galerie.id_galerie
                          AND galerie_media.id_galerie_media = galerie.id_avatar
                          AND galerie_media.etat != -100
                LEFT JOIN system2_media AS media ON media.id_media = galerie_media.id_media
                          AND media.etat != -100

                INNER JOIN system2_world AS world ON world.id_type = article.id AND world.type = "article"
                INNER JOIN system2_world_index AS world_index ON world_index.id_world_index = world.id_world_index
                       AND world_index.id_world_index = ' . (int)$id_world_index . '
                WHERE counter.type = "article" AND counter.ttl = 3600 AND counter.name = "views" AND article.etat >= ' . self::ETAT_PUBLISHED . ' AND article.published_date > ' . $this->sql->quote($date_24h) . '
                GROUP BY counter.id_type
                ORDER BY nb_view DESC
                LIMIT ' . (int)$offset . ',' . (int)$limit;
            $sql = $this->sql->query($q);
            if ($sql === FALSE)
                return Errno::DB_ERROR;

            while (($tabactu = $sql->fetch()) && $nb_elm < $a)
            {
                $tabactu['tags_media'] = Melty_Helper_String::explode(',', $tabactu['tags_media']);
                if ($tabactu['c_origine'] != $_ENV['INSTANCE'])
                    $resSite = new Melty_RAII_Router_Instance($tabactu['c_origine']);
                $tabactu['url_article'] = $this->get_url($tabactu['id_article'], $tabactu['url_article']);

                $tabactu['published_url_article'] = !empty($tabactu['published_url_article'])
                    ? $this->get_url($tabactu['id_article'], $tabactu['published_url_article'])
                    : $tabactu['url_article'];

                $tabactu['nbr_reactions'] = [
                    'nbr_com' => (int)$tabactu['nbr_com'],
                    'nbr_likes' => (int)$tabactu['likes'],
                    'nbr_tweets' => (int)$tabactu['tweets'],
                    'nbr_plusones' => (int)$tabactu['plusones']
                    ];
                $tabactu['nbr_reactions']['total'] = array_sum($tabactu['nbr_reactions']);

                $tabactu['url_world_index'] = $this->lib['world']->get_url_index($tabactu['id_world_index'], $tabactu['url_world']);
                if (isset($resSite))
                    unset($resSite);
                $tab_actu['data'][] = $tabactu;
                $nb_elm++;
            }
        }
        return $tab_actu;
    }

    public function by_hits($display = "month", $nb = 100, $ids_thema = NULL,
                            $id_world_index = NULL, $i_flags = NULL,
                            $order = "DESC", $etat = self::ETAT_PUBLISHED,
                            $order_by = 'hits', $instance = NULL,
                            $blacklist = NULL, $de = 0, $geo_params = NULL)
    {
        $order = $order == 'DESC' ? 'DESC' : '';
        $sq_iflags = "";

        if (!empty($i_flags) && gettype($i_flags) == "string")
            $i_flag = $this->helper['params_parse']->parse($i_flags);

        if (!empty($i_flag))
        {
            if ($i_flag == 1)
                $sq_iflags = 'system2_article.i_flag != 0';
            else
            {
                foreach ($i_flag as $key => $f)
                {
                    if ($f['value'] == 'home')
                    {
                        $sq_i_flag_home = 'AND (';

                        $negation = $f['negation'] ? 'NOT ' : '';

                        $sq_i_flag_home .= '(system2_article.c_origine = ' . (int)$_ENV['C_ORIGINE']
                            . ' AND ' . $negation . 'FIND_IN_SET("home", system2_article.i_flag))';

                        if (Melty_Factory::getMUM()->getMasterInstanceName() == $_ENV['INSTANCE'])
                            $sq_i_flag_home .= ' OR ' . $negation . 'FIND_IN_SET("home_master", system2_article.i_flag)';

                        $sq_i_flag_home .= ')';
                        $sq_iflags = $sq_i_flag_home;

                        array_splice($i_flag, $key, 1);
                        break;
                    }
                }

                // Gestion classique
                if (!empty($i_flag))
                {
                    if (count($i_flag) == 1)
                        $i_flag[0]['operator'] = '';

                    $sq_iflags = 'AND (';
                    foreach ($i_flag as $f)
                    {
                        if ($f['operator'] == '|')
                            $sq_iflags .= ' OR ';
                        elseif ($f['operator'] == '&')
                            $sq_iflags .= ' AND ';
                        $sq_iflags .= $f['negation'] ? 'NOT ' : '';
                        $sq_iflags .= 'FIND_IN_SET(' . $this->sql->quote($f['value']) . ', system2_article.i_flag)';
                    }
                    $sq_iflags .= ')';
                }
            }
        }

        if ($display == "month")
        {
            $date = date("Y-m-d 00:00:00", strtotime('-1 month'));
            $date_end = NULL;
        }
        else if ($display == "week")
        {
            $d = date("w");
            if ($d > 1)
                $date = strtotime("-1 week");
            else
                $date = time();

            $date = strtotime("last monday", $date);

            $date_end = strtotime("+5 days", $date);

            $date = date("Y-m-d 00:00:00", $date);
            $date_end = date("Y-m-d 00:00:00", $date_end);
        }
        else if ($display == "today")
        {
            $date = date("Y-m-d 00:00:00");
            $date_end = date("Y-m-d 23:59:59");
        }
        else if ($display == "yesterday")
        {
            $d = strtotime("-1 day");
            $date = date("Y-m-d 00:00:00", $d);
            $date_end = date("Y-m-d 23:59:59", $d);
        }
        else if ($display == "seven")
        {
            $d = strtotime("-7 days");
            $date = date("Y-m-d 00:00:00", $d);

            $d = strtotime("+6 days", $d);
            $date_end = date("Y-m-d 23:59:59", $d);
        }
        else
        {
            $date = strtotime(($display ? $display : "-1") . " days");
            $date = date("Y-m-d 00:00:00", $date);
            $date_end = NULL;
        }

        $sql_thema = ($ids_thema !== NULL && $ids_thema != 0 ? "system2_article.id_thema IN (" . $this->sql->quote($ids_thema, Melty_Database_SQL::ARRAY_OF_INT) . ')' : '1');

        $sql_world = "";
        if ($id_world_index)
        {
            if (!is_array($id_world_index))
            {
                if (strpos($id_world_index, ',') !== FALSE)
                    $id_world_index = explode(',', $id_world_index);
                else
                    $id_world_index = [$id_world_index];
            }
            // Generate in/notin array
            $tab_in = [];
            $tab_notin = [];
            foreach ($id_world_index as $key => $value)
            {
                // strpos will be 50/100% faster than substr
                if (strpos($value, '!') > -1)
                    $tab_notin[] = (int)substr($value, 1);
                else
                    $tab_in[] = (int)$value;
            }

            $sw_in = [];
            if (count($tab_in) > 0)
            {
                $sw_in[] = 'IN (' . $this->sql->quote($tab_in, Melty_Database_SQL::ARRAY_OF_INT) . ')';
            }
            if (count($tab_notin) > 0)
            {
                $sw_in[] = 'NOT IN (' . $this->sql->quote($tab_notin, Melty_Database_SQL::ARRAY_OF_INT) . ')';
            }

            $sql_world = 'INNER JOIN system2_world as sw ON sw.id_world_index ' . implode(' AND ', $sw_in)
                . '       AND sw.type = "article"'
                . '       AND sw.id_type = system2_article.id';
        }

        $sql_date = "system2_article.date > " . $this->sql->quote($date);
        $sql_date_end = "1";
        if ($date_end)
            $sql_date_end = "system2_article.date < " . $this->sql->quote($date_end);

        $id_membre = $_SESSION->get_id_membre();
        $sq_unread_select = $sq_unread_join = '';
        if ($id_membre)
        {
            $sq_unread_select = ' IF (srl.id_membre IS NOT NULL, 1, 0) AS unread,';
            $sq_unread_join = ' LEFT JOIN system4_read_log AS srl ON srl.type = "article" AND srl.id_type = system2_article.id AND srl.id_membre = ' . (int)$id_membre;
        }

        $sq_etat = '1';
        if ($etat >= self::ETAT_PUBLISHED)
            $sq_etat = 'system2_article.etat = ' . (int)$etat;

        $sq_instance = '1';
        if ($instance !== NULL)
            $sq_instance = ' system2_article.c_origine = ' . $this->sql->quote($instance);

        $sq_blacklist = '1';
        if ($blacklist !== NULL && !empty($blacklist))
            $sq_blacklist = 'system2_article.id NOT IN (' . $this->sql->quote($blacklist, Melty_Database_SQL::ARRAY_OF_INT) . ')';

        $sq_join_geo = '';
        if (is_array($geo_params) && !empty($geo_params))
        {
            $sq_join_geo = $this->lib['geocoding']->formatJoinForGeoParameter(
                $geo_params, "article", "system2_article.id");
        }

        $q = "SELECT
                   " . $this->c_f('system2_article') . ",
                   " . $sq_unread_select . "
                   system2_article.id,
                   system2_article.id AS id_article,
                   system2_article.id_membre,
                   system2_article.titre,
                   system2_article.aresum AS resum,
                   system2_article.i_flag,
                   system2_article.published_date,
                   system2_article.date,
                   system2_article.url,
                   system2_article.published_url,
                   system2_article.etat,
                   system2_article.social_title,
                   system2_article_hits.hits,
                   system2_article_hits.likes,
                   system2_article_hits.tweets,
                   system2_article_hits.plusones,
                   COALESCE(ci.nbr, 0) AS nbr_com,
                   (COALESCE(system2_article_hits.likes, 0) + COALESCE(system2_article_hits.tweets, 0) + COALESCE(system2_article_hits.plusones, 0) + COALESCE(ci.nbr, 0)) AS total_hits,
                   system2_article.alt_url,
                   system2_thema.nom AS nom_thema,
                   system2_thema.url AS url_thema,

                   system2_world_index.id_world_index,
                   system2_world_index.url AS url_world,
                   system2_world_index.nom AS nom_world,

                   galerie_media.id_galerie_media,
                   galerie_media.fingerprint AS fingerprint_media,
                   media.type AS type_media,
                   media.format AS format_media,
                   media.titre AS tags_media,
                   media.duration,

                   system2_info.login
              FROM system2_article
              " . $this->c_j(Melty_MUM::SITE_TYPE_ARTICLE, 'id') . "
         LEFT JOIN system2_article_hits ON system2_article_hits.id_article = system2_article.id
         LEFT JOIN system2_info ON system2_info.id_membre = system2_article.id_membre
         LEFT JOIN system2_thema ON system2_thema.id_thema = system2_article.id_thema
         LEFT JOIN system2_world ON system2_world.type = 'article'
                   AND system2_world.id_type = system2_article.id
                   AND system2_world.etat = 1
         LEFT JOIN system2_world_index ON system2_world_index.id_world_index = system2_world.id_world_index
               AND system2_world_index.etat > " . melty_lib_world::ETAT_DELETED . "
         LEFT JOIN system2_com_index AS ci ON ci.id_type = system2_article.id AND ci.type = 'article'
                   " . $sql_world . "

         LEFT JOIN system2_galerie AS galerie ON galerie.type = 'article'
                   AND galerie.id_type = system2_article.id
                   AND galerie.etat != -100
         LEFT JOIN system5_galerie_media AS galerie_media ON galerie_media.id_galerie = galerie.id_galerie
                   AND galerie_media.id_galerie_media = galerie.id_avatar
                   AND galerie_media.etat != -100
         LEFT JOIN system2_media AS media ON media.id_media = galerie_media.id_media
                   AND media.etat != -100

               " . $sq_join_geo . "
               " . $sq_unread_join . "
             WHERE " . $sq_etat . "  AND " . $sql_date . " AND " . $sql_date_end . " AND " . $sql_thema . " " . $sq_iflags . " AND " . $sq_instance . " AND " . $sq_blacklist . "
          GROUP BY system2_article.id
          ORDER BY " . $order_by . " " . $order . "
          LIMIT " . (int)$de . ", " . (int)$nb;

        $sql = $this->sql->query($q);
        if ($sql === FALSE)
            return Errno::DB_ERROR;
        $tab_actu = [];
        while ($tabactu = $sql->fetch())
        {
            $tabactu['tags_media'] = Melty_Helper_String::explode(',', $tabactu['tags_media']);
            if ($tabactu['c_origine'] != $_ENV['INSTANCE'])
                $resSite = new Melty_RAII_Router_Instance($tabactu['c_origine']);
            $tabactu['url_article'] = $this->get_url($tabactu['id'], $tabactu['url']);

            $tabactu['published_url_article'] = !empty($tabactu['published_url'])
                ? $this->get_url($tabactu['id'], $tabactu['published_url'])
                : $tabactu['url_article'];

            $tabactu['url_thema'] = $this->get_url_thema($tabactu['url_thema']);

            $tabactu['url_world'] = $this->lib['world']->get_url_index($tabactu['id_world_index'], $tabactu['url_world']);

            $tabactu['nbr_reactions'] = [
                'nbr_com' => (int)$tabactu['nbr_com'],
                'nbr_likes' => (int)$tabactu['likes'],
                'nbr_tweets' => (int)$tabactu['tweets'],
                'nbr_plusones' => (int)$tabactu['plusones']
                ];
            $tabactu['nbr_reactions']['total'] = array_sum($tabactu['nbr_reactions']);

            $tabactu['format_duration'] = date('H:i', $tabactu['duration']);

            if (isset($resSite))
                unset($resSite);
            $tabactu['url_membre'] = $this->lib['membre']->get_url($tabactu['id_membre'], $tabactu['login']);
            $tab_actu['data'][] = $tabactu;
        }
        $this->add_share_option($tab_actu);
        return $tab_actu;
    }

    public function change_site($id_article, $sites = NULL, $origine = NULL)
    {
        whine_unused("2014/08/01");
        if (!$id_article)
            return -2;
        $set_site = $set_origine = $sep = '';
        if (is_string($sites))
            $sq_sites = $sites;
        elseif (is_array($sites))
            $sq_sites = implode(',', $sites);
        if ($sq_sites)
            $set_site = 'c_site = ' . $this->sql->quote($sq_sites);
        if ($origine)
            $set_origine = 'c_origine = ' . $this->sql->quote($origine);
        if ($set_site && $set_origine)
            $sep = ', ';
        $q = 'UPDATE system2_article SET ' . $set_site . $sep . $set_origine . ' WHERE id = ' . (int)$id_article;
        $sql = $this->sql->query($q);
        $q = 'UPDATE system2_galerie SET ' . $set_site . $sep . $set_origine . ' WHERE type = "article" AND id_type = ' . (int)$id_article;
        $sql = $this->sql->query($q);
        return 1;
    }

    public function mark_world_article_read($id_membre, $world,
                                            $read_by = 'user')
    {
        if (count($world) === 0)
            return Errno::FIELD_EMPTY;
        $world = 'id_world_index IN (' . $this->sql->quote($world, Melty_Database_SQL::ARRAY_OF_INT) . ')';
        $q = 'SELECT DISTINCT sw.id_type
              FROM system2_world AS sw
              WHERE sw.type = "article" '
            . 'AND ' . $world . ' AND sw.id_type > 0';
        $res = $this->sql->query($q);

        while ($tmp = $res->fetch(PDO::FETCH_NUM))
            $article[] = $tmp[0];

        if (!$article)
            return 0;
        return $this->lib['read']->mark_read($id_membre, 'article', $article, $read_by);
    }

    public function count_unread($id_membre = NULL, $liked = NULL,
                                 $id_world_index = NULL)
    {
        $sq_world_join = $sq_world_where = $sq_liked_join = $sq_liked_where = '';
        if ($id_membre === NULL)
        {
            if ($_SESSION->is_log())
                $id_membre = (int)$_SESSION->get_id_membre();
            else
                return Errno::NO_DATA;
        }

        if ($liked !== NULL)
        {
            $sq_liked_where = ' AND sl.id_membre IS NOT NULL';
            $sq_liked_join = '
                LEFT JOIN system2_world AS sw ON sw.id_type = sa.id AND sw.type = "article"
                INNER JOIN system4_like AS sl ON sl.id_type = sw.id_world_index AND sl.type = "world" AND sl.id_membre = ' . (int)$id_membre;
        }
        if ($id_world_index !== NULL)
        {
            if (empty($sq_liked_join))
                $sq_world_join = ' LEFT JOIN system2_world AS sw ON sw.id_type = sa.id AND sw.type = "article"';
            $sq_world_where = ' AND sw.id_world_index = ' . (int)$id_world_index;
        }

        $q = '
            SELECT COUNT(id)
            FROM system2_article AS sa
            LEFT JOIN system4_read_log AS srl ON srl.type = "article" AND srl.id_type = sa.id AND srl.id_membre = ' . (int)$id_membre . '
            ' . $sq_liked_join . '
            ' . $sq_world_join . '
            WHERE sa.etat >= "' . self::ETAT_PUBLISHED . '" AND srl.id_type IS NULL' . $sq_liked_where . $sq_world_where . ' AND sa.c_origine = ' . Melty_Factory::getMUM()->instance->getIntForValue($_ENV['INSTANCE']);
        $sql = $this->sql->memoized_fetch($q, PDO::FETCH_NUM);
        if ($sql === FALSE)
            return Errno::DB_ERROR;
        return $sql[0];
    }

    /**
     * @param int $id_article
     * @return boolean
     */
    public function is_planned($id_article)
    {
        $crud = new Melty_CRUD_MySQL('system2_article');
        return (boolean) $crud->read('1', array(
                                         'id' => $id_article,
                                         'pub_date' => ['fieldType' => 'verbatim', 'value' => 'IS NOT NULL', 'operator' => '']
                                         )
            )->fetchColumn();
    }

    public function publish_planned_article()
    {
        $crud = new Melty_CRUD_MySQL('system2_article');

        // On recupere les articles a valider avec une pub_date
        $plannedArticle = $crud->read('id',
                                      ['c_origine' => $_ENV['INSTANCE'],
                                       'etat' => self::ETAT_WRITED,
                                       'pub_date' => ['fieldType' => 'verbatim',
                                                      'value' => 'NOW()',
                                                      'operator' => '<=']]
            )->fetchAll();

        foreach ($plannedArticle as $article)
        {
            trigger_error("[DEBUG] Publishing a planned article, id: " . $article['id']);
            $this->val($article['id'], self::ETAT_PUBLISHED);
        }

        // On recupere les articles qui ont timeout
        $q = ' SELECT id
                 FROM system2_article AS article
           INNER JOIN system4_todo AS todo ON todo.type = "article" AND todo.id_type = article.id
                WHERE article.c_origine = ' . $this->sql->quote($_ENV['INSTANCE']) . '
                      AND article.etat = ' . self::ETAT_WRITED . ' AND article.pub_date IS NULL
                      AND ((todo.id_prio = ' . melty_lib_todo::PRIO_NORMAL . '
                            AND article.val_date + INTERVAL ' . melty_lib_todo::TIMEOUT_NORMAL . ' MINUTE < NOW())
                           OR (todo.id_prio = ' . melty_lib_todo::PRIO_URGENT . '
                             AND article.val_date + INTERVAL ' . melty_lib_todo::TIMEOUT_URGENT . ' MINUTE < NOW())
                           OR (todo.id_prio = ' . melty_lib_todo::PRIO_INTEMPOREL . '
                             AND article.val_date + INTERVAL ' . melty_lib_todo::TIMEOUT_INTEMPOREL . ' MINUTE < NOW()))';

        $sql = $this->sql->query($q);

        if ($sql === FALSE)
            return Errno::DB_ERROR;

        foreach ($sql as $row)
            $this->val($row['id'], self::ETAT_PUBLISHED);
    }

    public function get_last_article_published_for_world($id_world_index)
    {
        $query = '
            SELECT date
              FROM system2_article AS sa
        INNER JOIN system2_world AS sw ON sw.type = ' . $this->sql->quote(melty_lib_world::TYPE_ARTICLE) . '
               AND sw.id_type = sa.id AND sw.id_world_index = ' . (int)$id_world_index . '
             WHERE sa.etat >= ' . self::ETAT_PUBLISHED . '
          ORDER BY date DESC
             LIMIT 1';
        $res = $this->sql->query($query);
        if ($res === FALSE)
            return FALSE;

        $row = $res->fetch(PDO::FETCH_NUM);
        return $row[0];
    }

    /**
     * Synchronise the unique view from Google Analytics for all articles.
     *
     * Really expensive process, need a set_time_limit(0) for get all the datas.
     */
    public function get_unique_view_from_analytics()
    {
        if ($_ENV['ANALYTICS_IDS'] === FALSE)
            return;
        $client = Zend_Gdata_ClientLogin::getHttpClient($_ENV['GOOGLE_ACCOUNT_EMAIL'], $_ENV['GOOGLE_ACCOUNT_PASSWORD'], Zend_Gdata_Analytics::AUTH_SERVICE_NAME);
        $service = new Zend_Gdata_Analytics($client);

        $query = new Zend_Gdata_Analytics_DataQuery();
        $query->setProfileId($_ENV['ANALYTICS_IDS'])
            ->addDimension(Zend_Gdata_Analytics_DataQuery::DIMENSION_PAGE_PATH)
            ->addMetric(Zend_Gdata_Analytics_DataQuery::METRIC_UNIQUE_PAGEVIEWS)
            ->setFilter(Zend_Gdata_Analytics_DataQuery::DIMENSION_PAGE_PATH . '=~' . '^/[a-z0-9][a-z0-9-]+-actu[0-9]+.html$') // Get only article
            ->setSort(Zend_Gdata_Analytics_DataQuery::METRIC_UNIQUE_PAGEVIEWS, TRUE)
            ->setStartDate(date('2005-01-01')) // Date before that aren't allowed by GA
            ->setEndDate(date('Y-m-d', strtotime('-1 day')))
            ->setMaxResults(1000); // More requests but less memory used

        $totalResults = 0;
        $startIndex = 0;
        $itemsPerPage = 0;
        // Iteration on page
        while ($totalResults >= ($startIndex + $itemsPerPage))
        {
            $query->setStartIndex($startIndex + $itemsPerPage);
            $results = $service->getDataFeed($query);
            // Info for iteration
            $totalResults = (string) $results->getTotalResults();
            $startIndex = (string) $results->getStartIndex();
            $itemsPerPage = (string) $results->getItemsPerPage();

            $values = [];
            // Handle results
            foreach ($results as $row) /* @var $row Zend_Gdata_Analytics_DataEntry */
            {
                $uniquePageViews = (string) $row->getMetric(Zend_Gdata_Analytics_DataQuery::METRIC_UNIQUE_PAGEVIEWS);
                // Extract the id from path
                $pagePath = (string) $row->getDimension(Zend_Gdata_Analytics_DataQuery::DIMENSION_PAGE_PATH);
                $id_article = str_replace(['-actu', '.html'], '', stristr($pagePath, '-actu'));

                $values[] = '(' . (int)$id_article . ', ' . (int)$uniquePageViews . ')';
            }
            $querySQL = '
                    INSERT INTO system4_article_hits_unique (id_article, hits)
                    VALUES ' . implode(',', $values) . '
                    ON DUPLICATE KEY UPDATE hits = VALUES(hits)';
            $this->sql->query($querySQL);
        }
    }

    public function get_article_informations($id_article, $with_texte = FALSE, $with_social_title = FALSE)
    {
        $q = "SELECT article.c_origine,
                     article.url,
                     article.titre,
                     article.id,
                     article.id_membre,
                     " . ($with_texte === TRUE ? 'article.texte,' : '') . "
                     " . ($with_social_title === TRUE ? 'article.social_title,' : '') . "
                     system2_thema.nom AS nom_thema,
                     system2_thema.url AS url_thema,
                     system2_world_index.nom AS nom_world,
                     system2_world_index.url AS url_world,
                     system2_world_index.id_world_index,
                     galerie.id_galerie
                FROM system2_article AS article
           LEFT JOIN system2_galerie AS galerie ON article.id = galerie.id_type
                 AND galerie.type = 'article'
           LEFT JOIN system2_thema ON system2_thema.id_thema = article.id_thema
           LEFT JOIN system2_world ON system2_world.type = 'article' AND system2_world.id_type = " . (int)$id_article . "
           LEFT JOIN system2_world_index ON system2_world_index.id_world_index = system2_world.id_world_index
                 AND system2_world_index.etat > " . melty_lib_world::ETAT_DELETED . "
               WHERE article.id = " . (int)$id_article . "
               LIMIT 1;";

        $req = $this->sql->query($q);
        if ($req === FALSE)
            return Errno::DB_ERROR;
        $tab_article = $req->fetch();
        if ($tab_article === FALSE)
            return Errno::NO_DATA;
        if ($tab_article['c_origine'] != $_ENV['INSTANCE'])
            $resSite = new Melty_RAII_Router_Instance($tab_article['c_origine']);
        $tab_article['url'] = $this->get_url($tab_article['id'], $tab_article['url']);
        if (isset($resSite))
            unset($resSite);
        $tab_article['url_thema'] = $this->lib['article']->get_url_thema($tab_article['url_thema']);
        $tab_article['url_world_index'] = $this->lib['world']->get_url_index($tab_article['id_world_index'], $tab_article['url_world']);

        if ($with_texte === TRUE
            && isset($tab_article['texte'])
            && !empty($tab_article['texte']))
            $tab_article['texte'] = $this->format($tab_article['texte'], 0, 1);

        return $tab_article;
    }

    public function get_fil_for_sitemap($offset = NULL, $limit = NULL)
    {
        $query = 'SELECT article.id,
                         article.url AS url_article,
                         article.published_url AS published_url_article,
                         article.id_galerie,
                         article.date,
                         article.c_origine,
                         article.texte
                    FROM system2_article AS article
                         ' . $this->c_j(Melty_MUM::SITE_TYPE_ARTICLE, 'id', 'article') . '
                   WHERE article.etat = ' . self::ETAT_PUBLISHED . '
                ORDER BY article.published_date DESC
                   LIMIT ' . (int)$offset . ',' . (int)$limit;

        $res = $this->sql->query($query); /* @var $res PDOStatement */
        if ($res === FALSE)
            return Errno::DB_ERROR;

        $tab = ['data' => [], 'nbr' => 0];
        while ($tmp = $res->fetch())
        {
            if ($tmp['c_origine'] != $_ENV['INSTANCE'])
                $resSite = new Melty_RAII_Router_Instance($tmp['c_origine']);
            $tmp['url_article'] = $this->get_url($tmp['id'], $tmp['url_article']);

            $tmp['published_url_article'] = !empty($tmp['published_url_article'])
                ? $this->get_url($tmp['id'], $tmp['published_url_article'])
                : $tmp['url_article'];

            if (isset($resSite))
                unset($resSite);
            $tab['data'][] = $tmp;
            $tab['nbr']++;
        }
        return $tab;
    }

    public function get_daily_views($id_article, $day)
    {
        $result = $this->sql->query(
            "SELECT nb_view " .
            "FROM system4_mtm_ads_view " .
            "WHERE type = 'article' " .
            "AND id_type = " . (int)$id_article . " " .
            "AND date = " . $this->sql->quote($day));
        if ($result === FALSE)
            return Errno::DB_ERROR;
        $result = $result->fetch();
        return $result['nb_view'] ? : 0;
    }

    public function get_views($id_article)
    {
        $result = $this->sql->query(
            "SELECT hits " .
            "FROM system2_article_hits " .
            "WHERE id_article = " . (int)$id_article);
        if ($result === FALSE)
            return Errno::DB_ERROR;
        $result = $result->fetch();
        return $result['hits'] ? : 0;
    }

    public function video_stat($id_article)
    {
        $q = "SELECT galerie_media.id_galerie_media,
                     galerie_media.description,
                     IFNULL(firstday.count, 0) AS firstday_views,
                     IFNULL(total.count, 0) AS total_views
                FROM system2_article AS article
                JOIN system2_galerie AS galerie ON galerie.type = 'article'
                     AND galerie.id_type = article.id
                     AND galerie.etat != -100
                JOIN system5_galerie_media AS galerie_media ON galerie_media.id_galerie = galerie.id_galerie
                     AND galerie_media.etat != -100
                JOIN system2_media AS media ON media.id_media = galerie_media.id_media
                     AND media.format = 'video'
                     AND media.etat != -100
           LEFT JOIN system4_media_page_view_firstday AS firstday
                     ON firstday.id_media = galerie_media.id_galerie_media
           LEFT JOIN system4_media_page_view AS total
                     ON total.id_media = galerie_media.id_galerie_media
               WHERE article.id = " . (int)$id_article;
        $res = $this->sql->query($q);
        if ($res === FALSE)
            return Errno::DB_ERROR;
        return $res->fetchAll();
    }

    public function stat($id_article)
    {
        $tab_article = $this->get_one($id_article);
        if (!is_array($tab_article))
            return Errno::NOT_EXIST;
        $tab_media = $this->lib['media']->galerie_get_media_light(0, 'article', $id_article);
        $formatted = $this->format($tab_article['texte']);
        if (!is_array($formatted))
            return Errno::NOT_EXIST;
        $stats = [];
        foreach ($formatted['data'] as $row)
        {
            if (isset($row['type']) && $row['type'] == 'pmedia')
            {
                if (!isset($stats[$tab_media['order'][$row['media']]['format']]))
                    $stats[$tab_media['order'][$row['media']]['format']] = 0;
                $stats[$tab_media['order'][$row['media']]['format']] += 1;
            }
            else
            {
                if (!isset($stats[isset($row['type']) ? $row['type'] : 'texte']))
                    $stats[isset($row['type']) ? $row['type'] : 'texte'] = 0;
                $stats[isset($row['type']) ? $row['type'] : 'texte'] += 1;
            }
        }
        $stats['image'] = isset($stats['image']) ? $stats['image'] : 0;
        $stats['video'] = isset($stats['video']) ? $stats['video'] : 0;
        $stats['texte'] = isset($stats['texte']) ? $stats['texte'] : 0;
        $stats['today_views'] = $this->get_daily_views($id_article, date('Y-m-d'));
        $stats['yesterday_views'] = $this->get_daily_views($id_article, date('Y-m-d', strtotime('-1 day')));
        $tab_com = $this->lib['com']->index_get_one(0, 'article', $id_article);
        $stats['commentaires'] = isset($tab_com['nbr']) ? $tab_com['nbr'] : 0;
        $stats['all_views'] = $this->get_views($id_article);
        return $stats;
    }

    public function update_stats($id_article)
    {
        $stats = $this->stat($id_article);
        if ($stats == Errno::NOT_EXIST)
            return $stats;
        $this->sql->query("UPDATE system2_article_hits
                              SET commentaires = $stats[commentaires],
                                  images = $stats[image],
                                  videos = $stats[video],
                                  paragraphes = $stats[texte]
                            WHERE id_article = $id_article
                            LIMIT 1");
        return $id_article;
    }

    /**
     * Check if $data contain an external video.
     * @param string $data
     * @return boolean
     */
    public function contain_external_video($data)
    {
        // Must contain a <object> or <embed> or <video>
        if (strpos($data, '<object') === FALSE && strpos($data, '<embed') === FALSE && strpos($data, '<video') === FALSE)
            return FALSE;

        // Check some major video platform
        if (strpos($data, 'http://www.wat.tv/') !== FALSE)
            return TRUE;
        if (strpos($data, 'm6.fr/u/players/') !== FALSE)
            return TRUE;
        if (strpos($data, 'http://www.dailymotion.com/') !== FALSE)
            return TRUE;
        if (strpos($data, 'http://www.youtube.com/') !== FALSE)
            return TRUE;
        if (strpos($data, 'gorillanation.com/') !== FALSE)
            return TRUE;
        if (strpos($data, 'brightcove.com/') !== FALSE)
            return TRUE;
        if (strpos($data, 'http://www.twitvid.com/') !== FALSE)
            return TRUE;

        // Nothing found
        return FALSE;
    }

    public function aspect_ArticleChanged($argv)
    {
        return $this->update_stats((int)$argv['id_article']);
    }

    public function aspect_NewComment($argv)
    {
        if ($argv['type'] != 'article')
            return;
        $query = 'UPDATE system2_article_hits
                  SET commentaires = %d WHERE id_article = %d';
        $id_article = $argv['id_type'];
        $tab_com = $this->lib['com']->index_get_one(0, 'article', $id_article);
        $this->sql->query(sprintf($query, (int)$tab_com['nbr'], (int)$id_article));
    }

    /**
     *
     * @param numeric|string $id If a numeric is given check by id otherwise check by url.
     * @return int The id of the article.
     */
    public function exists($id)
    {
        $crud = new Melty_CRUD_MySQL('system2_article');
        $where = [];
        if (is_numeric($id))
            $where['id'] = $id;
        else
            $where['url'] = (string) $id;
        return $crud->read('id', $where)->fetchColumn();
    }

    /**
     * @param type $id_article
     * @param array $options
     * @return int
     */
    public function set_share_options($id_article, array $options)
    {
        // Get existing constant
        $optAllowedNames = Melty_Helper_Reflection::getConstants($this, 'SHARE_NAME_');
        $optAllowedValues = Melty_Helper_Reflection::getConstants($this, 'SHARE_STATUS_');

        // Init array with the PRIMARY field
        $fields = ['id_article'];
        $values = [(int)$id_article];
        $duplicate = [];
        $ignore = 'IGNORE'; /* Ne pas whine si aucune option n'est
                             * passée et que le article_share existe
                             * déjà pour cet article. */
        // Setup array with user data
        foreach ($options as $optName => $optVal)
        {
            $ignore = '';
            if (!in_array($optName, $optAllowedNames, TRUE)
                || !in_array($optVal, $optAllowedValues, TRUE))
                return Errno::FIELD_INVALID;

            $fields[] = $this->sql->field($optName);
            $values[] = $this->sql->quote($optVal);
            $duplicate[] = $optName . ' = VALUES(' . $optName . ')';
        }

        // Update data
        $query = 'INSERT ' . $ignore . ' INTO'
            . ' system4_article_share (' . implode(', ', $fields) . ')'
            . ' VALUES (' . implode(', ', $values) . ')';
        if ($duplicate)
            $query .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $duplicate);
        $stmt = $this->sql->query($query);
        if ($stmt === FALSE)
            return Errno::DB_ERROR;
        Melty_Factory::getCache()->delete('get_share_options:' . $id_article);
        return $stmt->rowCount();
    }

    public function get_shares_options($ids_article)
    {
        if (empty($ids_article))
            return [];
        $ids_article = $this->sql->quote($ids_article,
                                         Melty_Database_SQL::ARRAY_OF_INT);
        $sql = "SELECT id_article, facebook, twitter, plusone, live
                  FROM system4_article_share
                 WHERE id_article IN ($ids_article)";
        $res = $this->sql->query($sql);
        if ($res === FALSE)
            return FALSE;
        $share_options = [];
        foreach ($res->fetchAll() as $share_option)
        {
            $id_article = $share_option['id_article'];
            unset($share_option['id_article']);
            $share_options[$id_article] = $share_option;
        }
        return $share_options;
    }

    /**
     * Find id_article or (type = 'article', id_type) in $array and
     * add a tab_share for each.
     */
    public function add_share_option(&$array)
    {
        $this->inject($array, [$this, 'get_shares_options'],
                      'id_article', 'article', 'tab_share');
    }

    /**
     * @param int $id_article
     * @return array
     */
    public function get_share_options($id_article)
    {
        $options = [melty_lib_article::SHARE_NAME_FACEBOOK,
                    melty_lib_article::SHARE_NAME_TWITTER,
                    melty_lib_article::SHARE_NAME_PLUSONE,
                    melty_lib_article::SHARE_NAME_LIVE];
        $cache = Melty_Factory::getCache();
        return $cache->cache(
            'get_share_options:' . $id_article,
            function() use ($id_article, $options)
            {
                $crud = new Melty_CRUD_MySQL('system4_article_share');
                return $crud->read(
                    $options, ['id_article' => $id_article]
                    )->fetch();
            },
            60,
            Melty_Cache::LOCAL_ONLY);
    }

    /**
     * COUNTER
     */
    public function count_articles_by_id_thema($id_thema,
                                               $etat = self::ETAT_PUBLISHED)
    {
        whine_unused("2014/08/01");
        if (Melty_Helper_Reflection::constantsValueExist($this, $etat, 'ETAT_') === FALSE)
            return Errno::FIELD_INVALID;

        $w = ['etat = ' . (int)$etat];
        if ($id_thema)
            $w[] = 'id_thema IN (' . $this->sql->quote($id_thema, Melty_Database_SQL::ARRAY_OF_INT) . ')';
        else
            return Errno::FIELD_EMPTY;
        $q = 'SELECT COUNT(*) AS nbr FROM system2_article WHERE ' . implode(' AND ', $w);
        $sql = $this->sql->query($q);
        if ($sql === FALSE)
            return Errno::DB_ERROR;
        $row = $sql->fetch();
        return $row['nbr'];
    }

    public function get_advises_for_title($title, array $keywords = NULL)
    {
        $wizard = new Melty_Wizard_ArticleTitle($title, $keywords);
        return $wizard->advise();
    }

    public function get_average_title_length_for_world($id_world_index)
    {
        $query = '
        SELECT AVG(LENGTH(a.titre))
        FROM system2_article AS a
        INNER JOIN system2_world AS w ON w.type = "article" AND w.id_type = a.id
        WHERE w.id_world_index = ' . (int)$id_world_index;
        $stmt = $this->sql->query($query);
        if ($stmt === FALSE)
            return Errno::DB_ERROR;
        return $stmt->fetchColumn();
    }

    public function get_fil_url($date = NULL, $de = NULL, $a = NULL)
    {
        $sq_date = !empty($date)
            ? (' AND article.date > ' . $this->sql->quote($date))
            : '';
        $sq_limit = ($de !== NULL || $a !== NULL) ?
            'LIMIT ' . (int)$de . ',' . (int)$a : '';

        $query = '
        SELECT
            c_origine,
            id AS id_article,
            url,
            published_url
        FROM system2_article AS article
        WHERE etat = ' . self::ETAT_PUBLISHED . $sq_date . ' AND c_origine = ' . (int)$_ENV['C_ORIGINE'] . '
        ORDER BY date DESC ' . $sq_limit;

        $sql = $this->sql->query($query);
        if ($sql === FALSE)
            return Errno::DB_ERROR;
        if (empty($sql))
            return [];

        $tab = [];
        foreach ($sql as $row)
        {
            if ($row['c_origine'] != $_ENV['INSTANCE'])
                $resSite = new Melty_RAII_Router_Instance($row['c_origine']);
            $url = $this->get_url($row['id_article'], $row['url']);
            $published_url = !empty($row['published_url'])
                ? $this->get_url($row['id_article'], $row['published_url'])
                : $url;

            if (isset($resSite))
                unset($resSite);

            $tab[] = [
                'id_article' => $row['id_article'],
                'published_url' => $published_url
                ];
        }

        return $tab;
    }

    public function get_last_id()
    {
        $q = 'SELECT MAX(id) FROM system2_article';
        $sql = $this->sql->query($q);
        if ($sql === FALSE)
            return Errno::DB_ERROR;
        return $sql->fetchColumn();
    }

    public function get_url_random($offset, $nb)
    {
        $last_id = $this->get_last_id();
        $ids = [];
        $nb_ids = ceil($nb * (1 + self::GET_URL_RANDOM_MARGIN / 100));
        $i = 0;
        while ($i < $nb_ids)
        {
            $id = Melty_Helper_Math::saw_weighted_rand($offset, $last_id);
            if (!in_array($id, $ids))
            {
                $try = 0;
                $ids[] = $id;
                $i++;
            }
            else
            {
                if ($try++ > 3)
                {
                    trigger_error('Infinite loop', E_USER_ERROR);
                    return ;
                }
            }
        }

        $q = 'SELECT'
            . '  c_origine,'
            . '  id AS id_article,'
            . '  url,'
            . '  published_url'
            . ' FROM system2_article AS article'
            . ' WHERE etat = ' . self::ETAT_PUBLISHED
            . '  AND id IN (' . $this->sql->quote(
                $ids, Melty_Database_SQL::ARRAY_OF_INT) . ')'
            . ' LIMIT ' . (int)$nb;
        $sql = $this->sql->query($q);
        if ($sql === FALSE)
            return Errno::DB_ERROR;
        $ret = [];
        foreach ($sql as $row)
        {
            if ($row['c_origine'] != $_ENV['INSTANCE'])
                $resSite = new Melty_RAII_Router_Instance($row['c_origine']);
            $published_url = !empty($row['published_url'])
                ? $this->get_url($row['id_article'], $row['published_url'])
                : $this->get_url($row['id_article'], $row['url']);

            if (isset($resSite))
                unset($resSite);

            $ret[] = [
                'id_article' => $row['id_article'],
                'published_url' => $published_url
                ];
        }

        return $ret;
    }

    public function remove($id_article)
    {
        $tab_article = $this->get_one($id_article);
        if (empty($tab_article['id_article']))
            return Errno::FIELD_INVALID;

        $this->sql->beginTransaction();

        $this->lib['world']->delete_link(melty_lib_world::TYPE_ARTICLE, $id_article);

        $q = "DELETE FROM system2_article WHERE id = " . (int)$id_article;
        $this->sql->query($q);

        $q = "DELETE FROM system2_article_hits WHERE id_article="
            . (int)$id_article;
        $this->sql->query($q);

        $q = "DELETE FROM system2_galerie WHERE type = 'article'"
            . " AND id_type = " . (int)$id_article;
        $this->sql->query($q);

        $this->lib['media']->galery_remove_medias(
            $article_intern['id_galerie']);

        $q = "DELETE FROM system2_com WHERE id_com_index IN (SELECT id_com_index FROM system2_com_index WHERE type='article' AND id_type=" . (int)$id_article . ")";
        $this->sql->query($q);

        $q = "DELETE FROM system2_com_index WHERE type='article' AND id_type=" . (int)$id_article;
        $this->sql->query($q);

        $this->lib['media']->galery_remove_medias(
            $article_intern['id_galerie']);

        $this->sql->commit();
    }

    /**
     * Met à jour le nombre de likes, tweets et plusones
     * sur des articles random.
     * @param $offset
     * @param $nb Nombre d'éléments à recupérer
     */
    public function shares_update($offset = 0, $nb)
    {
        $articles = $this->get_url_random($offset, $nb);
        $urls = [];
        foreach ($articles as &$article)
        {
            $article['published_url'] = Melty_Router::forceProductionDomain(
                $article['published_url']);
            $urls[] = $article['published_url'];
        }

        $likes = Shares::get_likes($urls);
        $tweets = Shares::get_tweets($urls);
        $plusones = Shares::get_plusones($urls);
        $values = [];
        foreach ($articles as $article)
        {
            $url = $article['published_url'];
            if (!isset($likes[$url])
                || !isset($tweets[$url])
                || !isset($plusones[$url]))
            {
                continue;
            }

            $values[] = '(' . (int)$article['id_article'] . ', '
                . (int)$likes[$url] . ', '
                . (int)$tweets[$url] . ', '
                . (int)$plusones[$url] . ')';
        }
        if (empty($values))
            return Errno::ERRNO_UNKNOW;

        $q = 'INSERT INTO system2_article_hits'
            . ' (id_article, likes, tweets, plusones)'
            . ' VALUES ' . implode(',', $values)
            . ' ON DUPLICATE KEY UPDATE'
            . '  likes = VALUES(likes),'
            . '  tweets = VALUES(tweets),'
            . '  plusones = VALUES(plusones)';
        $sql = $this->sql->query($q);
        if ($sql === FALSE)
            return Errno::DB_ERROR;
        return Errno::OK;
    }

    /**
     * Update le nombre de likes des articles de moins de 24h.
     */
    public function likes_update_firstday()
    {
        $date = Melty_Helper_Date::strtodate('Y-m-d H:i:00', '-1 day 5 minutes');
        $articles = $this->get_fil_url($date);
        $urls = [];
        foreach ($articles as &$article)
        {
            $article['published_url'] = Melty_Router::forceProductionDomain(
                $article['published_url']);
            $urls[] = $article['published_url'];
        }
        unset($article);
        $likes = Shares::get_likes($urls);
        if (!is_array($likes))
            return Errno::ERRNO_UNKNOW;

        $pairs = [];
        $total_likes = 0;
        foreach ($articles as $article)
        {
            $url = $article['published_url'];
            if (!isset($likes[$url]))
                continue;
            $total_likes += $likes[$url];
            $pairs[] = '('
                . (int)$article['id_article'] . ', '
                . (int)$likes[$url]
                . ')';
        }
        if (empty($pairs))
            return Errno::ERRNO_UNKNOW;

        $q = 'INSERT INTO system4_article_share_stat_firstday'
            . '  (id_article, fb_like)'
            . ' VALUES '
            . implode(',', $pairs)
            . ' ON DUPLICATE KEY UPDATE'
            . ' fb_like = VALUES(fb_like)';
        $sql = $this->sql->query($q);
        if ($sql === FALSE)
            return Errno::DB_ERROR;
        return Errno::OK;
    }

    /**
     * Update le nombre de plusone des articles de moins de 24h.
     */
    public function plusone_update_firstday()
    {
        $date = Melty_Helper_Date::strtodate('Y-m-d H:i:00', '-1 day 5 minutes');
        $articles = $this->get_fil_url($date);
        $urls = [];
        foreach ($articles as &$article)
        {
            $article['published_url'] = Melty_Router::forceProductionDomain(
                $article['published_url']);
            $urls[] = $article['published_url'];
        }
        unset($article);

        $plusones = Shares::get_plusones($urls);
        if (!is_array($plusones))
            return Errno::ERRNO_UNKNOW;


        foreach ($articles as $article)
        {
            $url = $article['published_url'];
            if (!isset($plusones[$url]) || $plusones[$url] <= 0)
                continue;

            $this->lib['share']->update_reactions('article', $article['id_article'],
                                                  'google', $plusones[$url]);
        }

        return Errno::OK;
    }

    protected function update_extern_article($id_article_intern, $article,
                                             $id_galerie, $published_date)
    {
        whine_unused("2014/08/01");
        $this->lib['world']->delete_link(melty_lib_world::TYPE_ARTICLE, $id_article_intern);

        $this->lib['world']->add_tab($article['keywords'], melty_lib_world::TYPE_ARTICLE, $id_article_intern);

        if (!empty($article['avatar_id']))
            $this->lib['media']->galerie_set_avatar($id_galerie, $article['avatar_id']);

        $this->update($id_article_intern, $article, $published_date);
    }

    public function get_one_all_details_for_admin($ids)
    {
        if (is_numeric($ids))
            $ids = [(int)$ids];

        $q = 'SELECT article.c_site,
                     article.c_origine,
                     article.titre,
                     article.url,
                     article.published_url,
                     article.id As id_article,
                     article.etat,
                     article.date,
                     article.create_date,
                     article.last_date,
                     article.edit_date,
                     article.social_title,

                     galerie.id_galerie,
                     galerie.etat AS galerie_etat,
                     galerie.date AS galerie_date,
                     galerie.last_date AS galerie_last_date,

                     galerie_media.id_galerie_media,
                     galerie_media.fingerprint AS media_fingerprint,
                     galerie_media.etat AS media_etat,
                     galerie_media.create_at AS media_create_at,
                     media.format AS media_format,
                     COALESCE(galerie_media.crop_h, media.height) AS height_media,
                     COALESCE(galerie_media.crop_w, media.width) AS width_media,
                     media.height AS height_so,
                     media.width AS width_so,
                     media.duration AS media_duration,
                     media.md5 AS media_md5,

                     com_index.id_com_index,
                     com_index.nbr AS com_index_nbr,
                     com_index.last_date AS com_index_last_date,
                     com_index.status AS com_index_status,

                     com.id_com,
                     com.date AS com_date

                FROM system2_article AS article
           LEFT JOIN system2_galerie AS galerie ON galerie.type = "article"
                     AND galerie.id_type = article.id
                     AND galerie.etat != -100
           LEFT JOIN system5_galerie_media AS galerie_media ON galerie_media.id_galerie = galerie.id_galerie
                     AND galerie_media.etat != -100
           LEFT JOIN system2_media AS media ON media.id_media = galerie_media.id_media
                     AND media.etat != -100
           LEFT JOIN system2_com_index AS com_index ON com_index.type = "article" AND com_index.id_type = article.id
           LEFT JOIN system2_com AS com ON com.id_com_index = com_index.id_com_index
               WHERE article.id IN (' . $this->sql->quote($ids, Melty_Database_SQL::ARRAY_OF_INT) . ')
            ORDER BY article.date DESC, galerie_media.create_at DESC';

        $sql = $this->sql->query($q);
        if ($sql === FALSE)
            return Errno::DB_ERROR;

        $ret = [];
        while ($row = $sql->fetch())
        {
            $id = (int)$row['id_article'];
            if (!isset($ret[$id]))
            {
                $ret[$id] = [];
                $ret[$id]['c_site'] = explode(',', $row['c_site']);
                $ret[$id]['c_origine'] = $row['c_origine'];
                $ret[$id]['titre'] = $row['titre'];
                $ret[$id]['url'] = $row['url'];
                $ret[$id]['url_article'] = $this->get_url($id, $row['url']);

                $ret[$id]['published_url_article'] = !empty($row['published_url'])
                    ? $this->get_url($id, $row['published_url'])
                    : $ret[$id]['url_article'];

                $ret[$id]['id_article'] = $id;
                $ret[$id]['etat'] = (int)$row['etat'];
                $ret[$id]['date'] = $row['date'];
                $ret[$id]['create_date'] = $row['create_date'];
                $ret[$id]['last_date'] = $row['last_date'];
                $ret[$id]['galeries'] = [];
                $ret[$id]['com_indexes'] = [];
            }

            if ($row['id_galerie'] !== NULL)
            {
                $idg = (int)$row['id_galerie'];
                if (!isset($ret[$id]['galeries'][$idg]))
                {
                    $ret[$id]['galeries'][$idg] = [];
                    $ret[$id]['galeries'][$idg]['id_galerie'] = $idg;
                    $ret[$id]['galeries'][$idg]['etat'] = (int)$row['galerie_etat'];
                    $ret[$id]['galeries'][$idg]['date'] = $row['galerie_date'];
                    $ret[$id]['galeries'][$idg]['last_date'] = $row['galerie_last_date'];
                    $ret[$id]['galeries'][$idg]['medias'] = [];
                }

                if ($row['id_galerie_media'] !== NULL)
                {
                    $idm = (int)$row['id_galerie_media'];
                    if (!isset($ret[$id]['galeries'][$idg]['medias'][$idm]))
                    {
                        $ret[$id]['galeries'][$idg]['medias'][$idm]['id_galerie_media'] = $idm;
                        $ret[$id]['galeries'][$idg]['medias'][$idm]['format'] = $row['media_format'];
                        $ret[$id]['galeries'][$idg]['medias'][$idm]['width'] = (int)$row['media_width'];
                        $ret[$id]['galeries'][$idg]['medias'][$idm]['height'] = (int)$row['media_height'];
                        $ret[$id]['galeries'][$idg]['medias'][$idm]['duration'] = (int)$row['media_duration'];
                        $ret[$id]['galeries'][$idg]['medias'][$idm]['md5'] = $row['media_md5'];
                        $ret[$id]['galeries'][$idg]['medias'][$idm]['etat'] = (int)$row['media_etat'];
                        $ret[$id]['galeries'][$idg]['medias'][$idm]['date'] = $row['media_create_at'];
                        $ret[$id]['galeries'][$idg]['medias'][$idm]['create_at'] = $row['media_create_at'];
                    }
                }
            }

            if ($row['id_com_index'] !== NULL)
            {
                $idci = (int)$row['id_com_index'];
                if (!isset($ret[$id]['com_indexes'][$idci]))
                {
                    $ret[$id]['com_indexes'][$idci]['id_com_index'] = $idci;
                    $ret[$id]['com_indexes'][$idci]['last_date'] = $row['com_index_last_date'];
                    $ret[$id]['com_indexes'][$idci]['nbr'] = (int)$row['com_index_nbr'];
                    $ret[$id]['com_indexes'][$idci]['status'] = (int)$row['com_index_status'];
                    $ret[$id]['com_indexes'][$idci]['coms'] = [];
                }

                if ($row['id_com'] !== NULL)
                {
                    $idc = (int)$row['id_com'];
                    if (!isset($ret[$id]['com_indexes'][$idci]['coms'][$idc]))
                    {
                        $ret[$id]['com_indexes'][$idci]['coms'][$idc]['id_com'] = $idc;
                        $ret[$id]['com_indexes'][$idci]['coms'][$idc]['date'] = $row['com_date'];
                    }
                }
            }
        }
        return $ret;
    }

    /** *******************************************************
     *
     * Code Spécifique à meltyPub
     * Effectivement, meltyPub utilise les article d'une
     * manière TRES spécifique, à savoir : comme des sites.
     *
     * ******************************************************* */
    /**
     *
     * @param string $site
     * @param uint $id_article
     */
    public function get_for_meltypub($site, $id_article = NULL)
    {
        whine_unused("2014/08/01");
        if ($id_article)
            return $this->get_one($id_article);

        $w = array(
            'FIND_IN_SET(' . $this->sql->quote($site) . ', c_site) > 0',
            'system2_article.etat >= ' . self::ETAT_PUBLISHED
            );
        $q = 'SELECT system2_article.id AS id_article FROM system2_article '
            . $this->c_j(Melty_MUM::SITE_TYPE_ARTICLE, 'id')
            . ' WHERE ' . implode(' AND ', $w)
            . ' LIMIT 1';
        $row = $this->sql->memoized_fetch($q);
        if ($row === FALSE)
            return Errno::DB_ERROR;
        return $this->get_one((int)$row['id_article']);
    }

    /**
     *
     * @param array $sites
     * @return array
     */
    public function forge_info_for_meltypub(array $sites)
    {
        $ret = ['data' => [], 'nbr' => 0];
        foreach ($sites as $site)
        {
            $ret['data'][$site] = array(
                'name' => $site,
                'url_site' => $site == 'melty' ?
                'melty-fr/' :
                preg_replace_callback(
                    '/melty(.)/', function ($x)
                    {
                        return 'melty' . strtoupper($x[1]);
                    }, $site
                    ) . '/',
                'url' => $site . '.fr'
                );
        }
        ksort($ret['data']);
        $ret['nbr'] = count($ret['data']);
        return $ret;
    }

    /*     * *******************************************************
     *
     *              FIN Code Spécifique meltyPub
     *
     * ******************************************************* */

    /**
     * Change le c_origine d'un article, et donc de sa galerie.
     *
     * Attention, l'ancien c_site de l'article n'est pas supprimé, alors
     * que le nouveau c_site est bien ajouté.
     * Donc si un article à (c_origine, c_site) = (melty, melty)
     * et que quelqu'un fait :
     *   switch_instance($id_article, 'meltystyle')
     *   switch_instance($id_article, 'melty')
     * Alors l'article finira avec :
     * c_origine = melty
     * c_site = melty, meltystyle
     *
     * Il ne semble pas bon de toucher plus en profondeur aux c_site dans
     * cette méthode car si les c_site ont été modifiés par un humain
     * nous risquons d'écraser ces modifications.
     *
     * @param uint $id_article
     * @param string $instance
     *
     */
    public function switch_instance($id_article, $instance)
    {
        $sql = 'SELECT c_origine,
                       c_site,
                       id AS id_article,
                       id_galerie
                  FROM system2_article
                 WHERE id = ' . (int)$id_article;
        $res = $this->sql->query($sql);
        if ($res === FALSE)
            return Errno::DB_ERROR;
        $article = $res->fetch();
        if ($article === FALSE)
            return Errno::NO_DATA;
        if ($article['c_origine'] === $instance)
            return Errno::NOTHING_TO_DO;
        $sql = 'UPDATE system2_article
                   SET c_origine = %s,
                       c_site = CONCAT_WS(",", IF(c_site = "", NULL, c_site), %s)
                 WHERE id = %s';
        $res = $this->sql->query(sprintf($sql,
                                         $this->sql->quote($instance),
                                         $this->sql->quote($instance),
                                         (int)$id_article));
        if ($res === FALSE)
            return Errno::DB_ERROR;
        Melty_Factory::getMUM()->addToInstance(Melty_MUM::SITE_TYPE_ARTICLE,
                                               $id_article,
                                               $instance);

        $this->lib['media']->switch_instance($article['id_galerie'], $instance);

        $tab_com_index = $this->lib['com']->index_get_one(0, 'article', $id_article);
        if (isset($tab_com_index['id_com_index']))
            $this->lib['com']->switch_instance($tab_com_index['id_com_index'],
                                                            $instance);
        return Errno::OK;
    }


    public function infer_c_origine_for_world($id_world_index, $exclude_origine)
    {
        $sq = 'SELECT c_origine
     FROM system2_article AS article
     JOIN system2_world AS world
          ON world.id_type = article.id AND world.type = "article"
    WHERE world.id_world_index = ' . (int)$id_world_index . '
                  AND article.published_date > NOW() - INTERVAL 1 MONTH
      AND article.c_origine != ' . $this->sql->quote($exclude_origine) . '
 GROUP BY c_origine
 ORDER BY COUNT(1) DESC
    LIMIT 1';
        return $this->sql->query($sq)->fetchColumn();
    }

    public function get_stats($id_article)
    {
        date_default_timezone_set('UTC');
        $db = Melty_Factory::getLogDatabase();
        $coll = $db->mined;
        $query = Array('_id.name' => 'uniqueVisitors',
                       '_id.scale' => 'Day',
                       '_id.id_article' => (string)$id_article,
                       '_id.host' => str_replace($_ENV['TLD'], $_ENV['TLD_PROD'], $_ENV['DOMAIN_FOR_HOMEPAGE']),
                       '_id.date' => ['$gte' => new MongoDate(strtotime("-2 weeks"))]);
        $curUnique = $coll->find($query)->sort(Array('_id.date' => 1));
        $query['_id.name'] = 'lectureArticle';
        $curVisits = $coll->find($query)->sort(Array('_id.date' => 1));
        $stats_article = [];
        foreach ($curUnique as $val)
        {
            $date = date("Y-m-d", $val['_id']['date']->sec);
            $stats_article[$date] = ['visitors' => $val['value']['unique_visitors'],
                                     'date' => $date];
        }
        foreach ($curVisits as $val)
        {
            $date = date("Y-m-d", $val['_id']['date']->sec);
            $stats_article[$date]['visits'] = $val['value']['visits'];
            $stats_article[$date]['search_result_position'] = (string)$val['value']['google_ranking'];
        }
        return $stats_article;
    }

    public function get_articles_by_calendar_foreign_type(array $ids_world_index,
                                                          $nb_article_by_ids_world_index = 3, $all_instance = TRUE)
    {
        if (empty($ids_world_index))
            return [];

        $q = "SELECT sub.id_world_index,
                     sub.id_article,
                     sub.published_date
             FROM (SELECT article.id AS id_article,
                          article.id_world_index,
                          article.published_date,
                  @counter := if(@previous_world = article.id_world_index, @counter + 1, 0),
                  @counter AS counter,
                  @previous_world := article.id_world_index
             FROM system2_article AS article
            WHERE article.id_world_index IN (" . $this->sql->quote($ids_world_index, Melty_Database_SQL::ARRAY_OF_INT) . ")
                  AND article.etat = 1 ";

        if (!$all_instance)
            $q .= " AND article.c_origine = " . $this->sql->quote( $_ENV['INSTANCE']);

        $q .= "ORDER BY article.id_world_index, article.published_date DESC)
               AS sub
            WHERE counter < " . (int)$nb_article_by_ids_world_index . ";";

        $this->sql->query('SET @counter = 0;');

        $stmt = $this->sql->query($q);

        if ($stmt === FALSE)
            return Errno::DB_ERROR;

        $results = $stmt->fetchAll();

        $this->add_metadata_for_articles($results, TRUE);

        return $results;
    }

    /**
     * Get sources used to create an article.
     *
     * Typically there's only one source used to build the article,
     * but as it's possible to use many, an array is returned per
     * article.
     *
     * @param mixed $id_article An array of ids may be given.
     * @param string $instance Optional filter on instance.
     */
    public function get_article_feeds($id_article, $instance = NULL)
    {
        whine_unused("2014/08/01");
        if (!$instance)
            $instance = $_ENV['INSTANCE'];
        $query = 'SELECT article.id AS id_article,
                         feed.id_feed,
                         feed.url_feed,
                         feed.title
                    FROM system2_article AS article
                    JOIN system4_demeter_feed_item AS feed_item
                         ON feed_item.id_article = article.id
                    JOIN system4_demeter_feed AS feed USING (id_feed)
                   WHERE article.id IN (' . $this->sql->quote($id_article, Melty_Database_SQL::ARRAY_OF_INT) . ')
                         AND article.c_origine = ' . $this->sql->quote($instance);
        $res = $this->sql->query($query);
        if ($res === FALSE)
            return Errno::DB_ERROR;
        return $this->sql->group_by($res->fetchAll(), 'id_article');
    }

    public function store_social_action($id_article, $action, $gender = NULL, $age = 0)
    {
        $count_boy = ($gender == "male") ? 1 : 0;
        $count_girl = ($gender == "female") ? 1 : 0;
        $count_age = ($age) ? 1 : 0;
        $q = 'INSERT INTO system4_article_social (type,
                          id_type,
                          actions,
                          date,
                          total_age,
                          count_age,
                          count_boy,
                          count_girl,
                          count_total)
                   VALUES ("article",' .
            (int)$id_article . ',' .
            $this->sql->quote($action) . ',' .
            $this->sql->quote(date("Y-m-d")) . ',' .
            (int)$age . ',' .
            $count_age . ',' .
            $count_boy . ',' .
            $count_girl . ',' .
            '1)
  ON DUPLICATE KEY UPDATE total_age=total_age + ' . (int)$age . ',' . '
                           count_age=count_age + ' . $count_age . ',' . '
                           count_boy=count_boy + ' . $count_boy . ',' . '
                           count_girl=count_girl + ' . $count_girl . ',' . '
                           count_total=count_total + 1';
        $res = $this->sql->query($q);
        if ($res === FALSE)
            return Errno::DB_ERROR;
        return Errno::OK;
    }

    /**
     * @param uint $id_thema
     * @param uint $id_world_index
     * @param uint $id_article
     * @param uint $de
     * @param uint $a
     */
    public function get_article_with_video($id_thema = NULL, $id_world_index = NULL, $id_article = NULL, $de = 0, $a = 10)
    {
        $sq_where = ['article.etat >= 1'];
        if ($id_article !== NULL)
            $sq_where[] = 'article.id != ' . (int)$id_article;
        if ($id_thema !== NULL)
            $sq_where[] = 'article.id_thema = ' . (int)$id_thema;
        if ($id_world_index !== NULL)
            $sq_where[] = 'article.id_world_index = ' . (int)$id_world_index;

        $q = 'SELECT article.id AS id_article
                FROM system2_article AS article
               WHERE ' . implode(' AND ', $sq_where) . '
                 AND EXISTS (
                       SELECT 1
                         FROM system2_galerie AS galerie
                         JOIN system5_galerie_media AS galerie_media
                              ON galerie_media.id_galerie = galerie.id_galerie
                              AND galerie_media.etat > -100
                         JOIN system2_media AS media
                              ON media.id_media = galerie_media.id_media
                              AND media.etat > -100 AND media.format = "video"
                   LEFT JOIN system5_service_media AS service_media
                             ON service_media.id_media = media.id_media
                             AND (service_media.id_service IS NULL
                                  OR service_media.id_service = 6
                                  OR service_media.embed_id_service = 3)
                       WHERE galerie.id_type = article.id
                             AND galerie.type = "article"
                             AND galerie.etat > -100
                     )
            ORDER BY article.published_date DESC
               LIMIT ' . (int)$de . ', ' . (int)$a;

        $res = $this->sql->query($q);
        if ($res === FALSE)
            return Errno::DB_ERROR;
        $result = $res->fetchAll();
        $this->lib['media']->add_all_media_for_articles($result);
        return $result;
    }

    /**
     * @param uint $id_thema
     * @param mixed $ids_world_index
     * @param uint $de
     * @param uint $a
     * @param mixed $ids_article_exclude
     *
     */
    public function get_random($id_thema = NULL, $ids_world_index = NULL, $de = 0, $a = 10, $ids_article_exclude = NULL)
    {
        if (!is_array($ids_article_exclude))
            $ids_article_exclude = explode(',', $ids_article_exclude);

        $w = [];
        if ($id_thema !== NULL)
            $w[] = 'article.id_thema = ' . (int)$id_thema;

        if ($ids_world_index !== NULL)
            $w[] = 'article.id_world_index IN( ' . $this->sql->quote((array)$ids_world_index, Melty_Database_SQL::ARRAY_OF_INT) . ')';

        if ($ids_article_exclude !== NULL)
            $w[] = 'article.id NOT IN ('. $this->sql->quote((array)$ids_article_exclude, Melty_Database_SQL::ARRAY_OF_INT) . ')';

        if (empty($w))
            return Errno::NO_DATA;

        $w[] = 'article.etat >= ' . self::ETAT_PUBLISHED;
        $q = 'SELECT article.id
              FROM system2_article AS article
              WHERE ' . implode(' AND ', $w) . '
              ORDER BY RAND()
              LIMIT ' . (int)$de . ', ' . (int)$a;

        $res = $this->sql->query($q);
        if ($res === FALSE)
            return Errno::DB_ERROR;

        return $this->get_referencements($res->fetchAll(PDO::FETCH_COLUMN));
    }

    public function get_most_commented($id_thema, $limit = 2)
    {
        $min_com = 10;

        $w = [];
        $w[] = 'article.etat = ' . self::ETAT_PUBLISHED;
        $w[] = 'com_index.last_date BETWEEN "' . date('Y-m-d H:i:00', Melty_Helper_Date::strtotime('-2 days')) . '" AND "' . date('Y-m-d H:i:00'). '"';
        $w[] = 'article.i_flag != "qcm"';
        $w[] = 'article_hits.commentaires > ' . (int)$min_com;
        $w[] = 'article.c_origine = "' . $_ENV['INSTANCE'] . '"';
        $w[] = 'article.id_thema = ' . (int)$id_thema;

        $q = 'SELECT
                article.id,
                article.titre,
                article.url,
                article.published_url,
                article.published_date,
                article.social_title,

                article_hits.commentaires,

                galerie.id_galerie,
                galerie_media.id_galerie_media,
                galerie_media.fingerprint AS fingerprint_media,
                media.type AS type_media,
                media.format AS format_media

              FROM system2_article AS article
              JOIN system2_article_hits AS article_hits
                ON article_hits.id_article = article.id
              LEFT JOIN system2_com_index AS com_index
                     ON com_index.id_type = article.id AND com_index.type = "article"
              LEFT JOIN system2_galerie AS galerie
                     ON galerie.type = "article"
                    AND galerie.id_type = article.id
                    AND galerie.etat != -100
              LEFT JOIN system5_galerie_media AS galerie_media
                     ON galerie_media.id_galerie = galerie.id_galerie
                    AND galerie_media.id_galerie_media = galerie.id_avatar
                    AND galerie_media.etat != ' . melty_lib_media::MEDIA_ETAT_DELETE . '
              LEFT JOIN system2_media AS media
                     ON media.id_media = galerie_media.id_media
                    AND media.etat != ' . melty_lib_media::MEDIA_ETAT_DELETE . '
              WHERE ' . implode(' AND ', $w) . '
              GROUP BY article.id
              ORDER BY article_hits.commentaires DESC
              LIMIT ' . (int)$limit;

        $res = $this->sql->query($q);
        if ($res === FALSE)
            return Errno::DB_ERROR;

        $tab_article = [];

        $res = $res->fetchAll();
        foreach($res AS $article)
        {
            $tab_article[$article['id']] = $article;
            $tab_article[$article['id']]['published_url_article'] = $this->get_url($article['id'], $article['url']);
            $tab_article[$article['id']]['coms'] = $this->lib['com']->index_get_one(0, 'article', $article['id']);
        }

        return $tab_article;
    }

    public static function article_from_bamar($url)
    {
        $split_url = explode('/', $url);
        // Bamar URLS are of the form:
        // /2008/03/jesse-pinkman-said-yeah-mr-white-yeah-science.html
        //
        // So it's ~100% discriminant to tell them apart using the two first /
        // Also, as our article never have slashes in their URL's
        // split_url[2] will never be numeric.
        if (!isset($split_url[2]) ||
            !is_numeric($split_url[1]) ||
            !is_numeric($split_url[2]))
            return FALSE;
        $crud = new Melty_CRUD_MySQL('system2_article');
        $res = $crud->read('id',
                           ['published_url' => 'http://www.bamarenlive.com/' . ltrim($url, '/'),
                            'etat' => 1],
                           ['limit' => 1]);
        if ($res === FALSE)
            return Errno::DB_ERROR;
        $id_article = $res->fetchColumn();
        if ($id_article)
            return '/article/main/argv/id_article/' . $id_article . '/';
        else
            return FALSE;
    }

    public static function article_from_neon($url)
    {
        // Neon URLS are of the form:
        // http://www.neonmag.fr/la-vie-au-bureau-cetait-pas-mieux-avant-112162/
        //
        // So it's ~100% discriminant to tell them apart using the "-[0-9]/$" pattern.
        // Also, as our article never have slashes in their URL's
        // split_url[2] will never be numeric.
        if (!preg_match('~-[0-9]+/$~', $url))
            return FALSE;
        $crud = new Melty_CRUD_MySQL('system2_article');
        $res = $crud->read('id',
                           ['published_url' => 'http://www.neonmag.fr/' . ltrim($url, '/'),
                            'etat' => 1],
                           ['limit' => 1]);
        if ($res === FALSE)
            return Errno::DB_ERROR;
        $id_article = $res->fetchColumn();
        if ($id_article)
            return '/article/main/argv/id_article/' . $id_article . '/';
        else
            return FALSE;
    }

    /**
     * There is no WHERE to don't select world_index when it's deleted
     *   because if you check for one world_index, maybe you really need it
     *   or you know it is deleted
     * if (world_index.etat == ETAT_DELETED)
     *   main_world != deleted world_index
     */
    public function redirect_to_main_world($id_article)
    {
        $sq = "SELECT system2_world_index.id_world_index,
                      system2_world_index.url
                 FROM system2_article
                 JOIN system2_world_index USING (id_world_index)
                WHERE id = " . (int)$id_article;
        $res = $this->sql->query($sq);
        $world = $res->fetch();
        if ($world !== FALSE && $world['id_world_index'] > 0)
        {
            $world_url = $this->lib['world']->get_url($world['id_world_index'],
                                                      NULL,
                                                      $world['url']);
            throw new Melty_Exception_HTTPCode_301($world_url);
        }
    }

    public static function try_1_with_id($url)
    {
        $sql = Melty_Factory::getDatabase();
        $matches = [];
        if (preg_match('/-a(?:ctu)?([0-9]+)\.htm/', $url, $matches) !== 1)
            return ;
        $q = 'SELECT id AS id_article, url
                    FROM system2_article
                   WHERE id = ' . (int)$matches[1];
        $res = $sql->query($q);
        if ($res === FALSE)
            return FALSE;
        $article = $res->fetch();
        if ($article === FALSE)
            return FALSE;
        $url = melty_lib_article::get_url($article['id_article'],
                                          $article['url']);
        throw new Melty_Exception_HTTPCode_301($url);
    }

    public static function try_2_with_url($url, $field = 'url')
    {
        $sql = Melty_Factory::getDatabase();
        $field = ['published_url' => 'published_url',
                  'url' => 'url'][$field];
        $url = ltrim($url, '/');
        $search = substr($url, 0, strspn($url, 'qwertyuiopasdfghjklzxcvbnm-1234567890'));
        if (strlen($search) < 8)
            return ;
        $q = 'SELECT id AS id_article, url
                FROM system2_article
               WHERE etat = 1
                     AND c_origine = ' . (int)$_ENV['C_ORIGINE'] . '
                     AND ' . $field . ' LIKE ' . $sql->quote($search . '%');
        $res = $sql->query($q);
        if ($res === FALSE)
            return ;
        $article = $res->fetch();
        if ($article === FALSE)
            return ;
        $url = melty_lib_article::get_url($article['id_article'],
                                          $article['url']);
        throw new Melty_Exception_HTTPCode_301($url);
    }

    public static function typo_in_url($url)
    {
        melty_lib_article::try_1_with_id($url);
        melty_lib_article::try_2_with_url($url, 'url');
        melty_lib_article::try_2_with_url($url, 'published_url');
        return FALSE;
    }
}

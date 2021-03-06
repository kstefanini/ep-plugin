<?php

namespace Migration\App\NewsEvents;

use Migration\Api\BaseMigration;
use Migration\Api\MigrationException;
use Migration\App\AnnuaireTelaBpProfileDataMap;
use Migration\App\Utilz\WpmlIclTranslationDAO;
use \Exception;
use \PDO;
use \DateTime;
use \DateTimeZone;

/**
 * Migrates news from SPIP DB to WP DB.
 */
class NewsMigration extends BaseMigration {

  /**
   * Migrates news from SPIP DB to WP DB..
   */
  /*
  * Explication des champs de la table wp_posts : https://deliciousbrains.com/tour-wordpress-database/#wp_posts
  *
  * @todo : revoir la méthode d'ajout, là c'est un peu violent, ça écrase les menus, le footer, toussa
  * (wai les menus c'est stocké dans la table posts, deal with it)
  * Vérifier l'auto-incrément de la table Posts, 20000 ids sont censés être réservés aux articles importés
  * Voir : https://wordpress.stackexchange.com/a/78317
  */
  public function migrate() {

    $wpmlIclTranslationDao = new WpmlIclTranslationDAO();
    $trGrId = $wpmlIclTranslationDao->getMaxTranslationGroupId();
    $requete_doc = "SELECT d.`id_document`, `fichier`, titre, `id_article` FROM `spip_documents` d LEFT JOIN spip_documents_articles da ON da.`id_document` = d.`id_document` WHERE distant = 'non'";
    $documents = $this->spipDbConnection->query($requete_doc)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($documents as $doc) {
      $doc_loc[$doc['id_document']] = $doc;
    }

    /*INSERT INTO `wp4_posts`
    SELECT spip_articles.`id_article` as ID, `id_auteur` as post_author, `date` as post_date, `date` as post_date_gmt,
    replace(replace(replace(replace(replace(replace(replace(replace(replace(convert( convert( texte USING latin1 ) USING utf8 ),'{{{{',''), '}}}}', '<!--more-->'), '{{{','<h2>'), '}}}', '</h2>'), '{{', '<strong>'), '}}', '</strong>'), '{', '<em>'), '}', '</em>'), '_ ', '') as post_content,
    `titre` as post_title,  "" as post_excerpt, replace(replace(replace(replace(replace(`statut`,'poubelle', 'trash'),'publie', 'publish'), 'prepa', 'private'), 'prop', 'pending'), 'refuse', 'trash') as post_status,  "open" as comment_status, "open" as ping_status, "" as post_password, spip_articles.`id_article` as post_name, "" as to_ping, "" as pinged, `date_modif` as post_modified,`date_modif` as post_modified_gmt, "" as post_content_filtered, "" as post_parent,
    concat("http://tela-botanica.net/wpsite/actu",spip_articles.`id_article`) as guid, "0" as menu_order, "post" as post_type, "" as post_mime_type, "" as comment_count FROM tela_prod_spip_actu.`spip_articles` left join tela_prod_spip_actu.spip_auteurs_articles on spip_auteurs_articles.`id_article` =  spip_articles.`id_article` WHERE id_rubrique in (22,54,70,30,19,51)
    */
    $requete = "SELECT spip_articles.`id_article` AS ID, `id_auteur` AS post_author, `date` AS post_date, `date` AS post_date_gmt,
    replace(replace(replace(replace(replace(replace(replace(replace(replace(convert( convert( texte USING latin1 ) USING utf8 ),'{{{{',''), '}}}}', '<!--more-->'), '{{{','<h2>'), '}}}', '</h2>'), '{{', '<strong>'), '}}', '</strong>'), '{', '<em>'), '}', '</em>'), '_ ', '') AS post_content,
    `titre` AS post_title,  \"\" AS post_excerpt, replace(replace(replace(replace(replace(`statut`,'poubelle', 'trash'),'publie', 'publish'), 'prepa', 'private'), 'prop', 'pending'), 'refuse', 'trash') AS post_status,  \"open\" AS comment_status, \"open\" AS ping_status, \"\" AS post_password, concat(\"article\",spip_articles.`id_article`) AS post_name, \"\" AS to_ping, \"\" AS pinged, `date_modif` AS post_modified,`date_modif` AS post_modified_gmt, \"\" AS post_content_filtered, \"\" AS post_parent,
    concat(\"http://tela-botanica.org/?p=\",spip_articles.`id_article`) AS guid, \"0\" AS menu_order, \"post\" AS post_type, \"\" AS post_mime_type, \"\" AS comment_count FROM `spip_articles` LEFT JOIN spip_auteurs_articles ON spip_auteurs_articles.`id_article` =  spip_articles.`id_article` WHERE id_rubrique in ( " . AnnuaireTelaBpProfileDataMap::getSpipRubricsToBeMigrated() . " ) GROUP BY ID";
    $articles = $this->spipDbConnection->query($requete)->fetchAll(PDO::FETCH_ASSOC);

    $compteurSucces = 0;
    foreach ($articles as $article) {

      // remove chapo (we add it later in postmeta)
      $article['post_content'] = preg_replace("@{{{{.*}}}}@", '', $article['post_content']);
      // transform links
      $article['post_content'] = preg_replace("@\[([^\[]*)\-\>([^\[]*)\]@", '<a href="$2">$1</a>', $article['post_content']);

      $article['post_content'] = preg_replace_callback(
        "@\<(?:img|doc|emb)([0-9]*)\|[a-z]*\>@",
        function($matches) use ($doc_loc) {
          if (isset($matches[1]) && isset($doc_loc[$matches[1]])) {
            $url = $doc_loc[$matches[1]]['fichier'];
            $titre = $doc_loc[$matches[1]]['titre'];
            switch (substr($url, -3, 3)) {
              case 'jpg':
              case 'png':
              case 'gif':
                if ('' !== $titre) {
                  return
                    '<figure>
                      <img src="http://www.tela-botanica.org/actu/'.$url.'" alt="'.$titre.'"/>
                      <figcaption>'.$titre.'</figcaption>
                    </figure>'
                  ;
                } else {
                  return '<img src="http://www.tela-botanica.org/actu/'.$url.'" alt="pas de alt pour cette image, soz"/>';
                }
              default:
                return '<a href="http://www.tela-botanica.org/actu/'.$url.'">'.($titre ?? $url).'</a>';
            }
          } else {
            return '';
          }
        },
        $article['post_content']
      );

      // gestion des dates normales et dates en GMT
      $date = new DateTime($article['post_date'], new DateTimeZone('Europe/Paris'));
      $article['post_date_gmt'] = $date->setTimezone(new DateTimeZone('GMT'))->format('Y-m-d H:i:s');
      $date = new DateTime($article['post_modified'], new DateTimeZone('Europe/Paris'));
      $article['post_modified_gmt'] = $date->setTimezone(new DateTimeZone('GMT'))->format('Y-m-d H:i:s');

      $insert[] = '(' . implode(', ', array_map(array($this->wpDbConnection, 'quote'), $article)) . ')';

      // collecte les infos pour l'enregistrement des redirections 301 des articles
      $ancienne_url = 'http://www.tela-botanica.org/actu/article' . $article['ID'] . '.html';
      $insert_redirection[] = '(' . $article['ID'] . ', ' . $this->wpDbConnection->quote($ancienne_url) . ')';

      $requeteInsert = 'INSERT INTO ' . $this->wpTablePrefix . 'posts (`ID`, `post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_title`, `post_excerpt`, `post_status`, `comment_status`, `ping_status`, `post_password`, `post_name`, `to_ping`, `pinged`, `post_modified`, `post_modified_gmt`, `post_content_filtered`, `post_parent`, `guid`, `menu_order`, `post_type`, `post_mime_type`, `comment_count`) VALUES ' . implode(', ', $insert)
      ;

      try {
        $this->wpDbConnection->exec($requeteInsert);
        // // Verbose
        // echo $compteurSucces . PHP_EOL;
        $compteurSucces += count($insert);
        $lastInsertId = $this->wpDbConnection->lastInsertId();
        $wpmlIclTranslationDao->create("'post_post'", $lastInsertId, ++$trGrId, "'fr'", 'NULL');
      } catch(Exception $e) {
        echo "-- ECHEC " . basename(__FILE__) . ':' . __FUNCTION__ . " REQUÊTE: [$requeteInsert]" . PHP_EOL;

        throw new MigrationException($e, $requeteInsert, basename(__FILE__) . ':' . __FUNCTION__);

      }
      $insert = array();

      $query = 'INSERT INTO ' . $this->wpTablePrefix . 'slug_history (`post_id`, `url`)
      VALUES ' . implode(', ', $insert_redirection) . '
      ON DUPLICATE KEY UPDATE `post_id`=VALUES(`post_id`), `url`=VALUES(`url`);';
      try {
        $this->wpDbConnection->exec($query);
      } catch(Exception $e) {
        echo "-- ECHEC " . basename(__FILE__) . ':' . __FUNCTION__ . " REQUÊTE: [$query]" . PHP_EOL;
        throw new MigrationException($e, $query, basename(__FILE__) . ':' . __FUNCTION__);
      }
      $insert_redirection = array();

    }

    echo '-- ' . $compteurSucces . '/' . count($articles) . ' actualités migrées. ' . PHP_EOL;
  }


  private function transformerNumEnUrl($matches) {
    global $doc_loc;

    $remplace = '';
    if (isset($matches[1])) {
      $remplace = '<img src="http://www.tela-botanica.org/actu/'.$doc_loc[$matches[1]].'" \/\>';
    }

    return $remplace;
  }

}

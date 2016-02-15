<?php
/*
Plugin Name: Tela Botanica
Description: Plugin permettant d'ajouter les outils de Tela Botanica à l'espace projets
Version: 1.0 BETA
Author: Tela Botanica
*/

// chargement de la configuration depuis config.json
function chargerConfig() {
	$fichierConfig = dirname( __FILE__ ) . "/config.json";
	if (! file_exists($fichierConfig)) {
		throw new Exception("Veuillez placer un fichier de configuration valide dans 'config.json'");
	}
	$config = file_get_contents($fichierConfig);
	$config = json_decode($config, true);

	return $config;
}

/* Chargement du code nécessitant BuddyPress */
function initialisation_bp() {

	require( dirname( __FILE__ ) . '/outils/TB_Outil.php' );
	require( dirname( __FILE__ ) . '/formulaires/categorie/categorie.php' );
	require( dirname( __FILE__ ) . '/formulaires/description/description-complete.php' );

	$config = chargerConfig();
	// chargement des outils depuis la configuration
	if (array_key_exists('outils', $config)) {
		foreach ($config['outils'] as $outil) {
			// include plutôt que require pour éviter une erreur fatale en cas
			// de mauvaise config
			include_once( dirname( __FILE__ ) . '/outils/' . $outil . '.php' );
		}
	}

	require( dirname( __FILE__ ) . '/formulaires/etiquettes/etiquettes.php' );	

}
add_action( 'bp_include', 'initialisation_bp' );
add_action( 'bp_include', 'description_complete' );
add_action( 'bp_include', 'categorie' );


class TelaBotanica
{

	/* Constructeur de la classe TelaBotanica */
	public function __construct()
	{	
		/* On déclenche la fonction ajout_menu_admin lors du chargement des menus de WordPress */
		add_action('admin_menu',array('TelaBotanica','ajout_menu_admin'));

		// @TODO remplacer "activation" par "installation" dans la version prod
		/* On lance la création de la table Outils Réglages lorsque le plugin est activé */
		register_activation_hook(__FILE__,array('TelaBotanica','installation_outils'));
		/* On lance la création de la table Catégories Projets lorsque le plugin est activé */
		register_activation_hook(__FILE__,array('TelaBotanica','installation_categories'));

		// @TODO remplacer "deactivation" par "deinstallation" dans la version prod
		/* On lance la supression de la table Outils Réglages lorsque le plugin est désinstallé */
		register_deactivation_hook(__FILE__,array('TelaBotanica','desinstallation_outils'));
		/* On lance la supression de la table Outils Réglages lorsque le plugin est désinstallé */
		register_deactivation_hook(__FILE__,array('TelaBotanica','desinstallation_categories'));
	}

	/**
	 * Méthode qui crée les tables "{$wpdb->prefix}tb_outils" et
	 * "{$wpdb->prefix}tb_outils_reglages" dans la base de données lors de
	 * l'installation du plugin, et appelle la méthode installation() de chaque
	 * outil recensé dans la config
	 * 
	 * - "{$wpdb->prefix}tb_outils" concerne la configuration d'un outil quel
	 * que soit le projet (liée au panneau de configuration du tableau de bord
	 * WP)
	 * 
	 * - "{$wpdb->prefix}tb_outils_reglages" concerne la configuration d'un outil
	 * pour un projet donné (liée au sous-onglet de réglages de l'outil, dans
	 * l'onglet d'administration d'un projet)
	 * 
	 * Dans chaque table, la colonne "config" contient la configuration propre à
	 * chaque outil, en JSON (ou autre, à la discrétion de l'outil)
	 * 
	 */
	static function installation_outils()
	{
		global $wpdb;
		$config = chargerConfig();

		// 1) réglages d'une outil pour l'ensemble de l'espace projets
		$create_outils = "
			CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}tb_outils` (
				`id_outil` varchar(50) NOT NULL,
				`active` tinyint(1) NOT NULL,
				`config` text NOT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";
		$pk_outils = "
			ALTER TABLE `{$wpdb->prefix}tb_outils`
			ADD PRIMARY KEY (`id_outil`);
		";
		$wpdb->query($create_outils);
		$wpdb->query($pk_outils);

		// 2) réglages d'un outil pour un projet
		$create_outils_reglages = "
			CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}tb_outils_reglages` (
				`id_projet` bigint(11) NOT NULL,
				`id_outil` varchar(50) NOT NULL,
				`name` varchar(50) NOT NULL,
				`prive` tinyint(1) NOT NULL,
				`create_step_position` tinyint(3) NOT NULL,
				`nav_item_position` tinyint(3) NOT NULL,
				`enable_nav_item` tinyint(1) NOT NULL,
				`config` text NOT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";
		$pk_outils_reglages = "
			ALTER TABLE `{$wpdb->prefix}tb_outils_reglages`
			ADD PRIMARY KEY (`id_projet`,`id_outil`),
			ADD KEY `id_projet` (`id_projet`);
		";
		$fk_outils_reglages = "
			ALTER TABLE `{$wpdb->prefix}tb_outils_reglages`
			ADD CONSTRAINT `fk_id-projet_id-group` FOREIGN KEY (`id_projet`) REFERENCES `{$wpdb->prefix}bp_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
		";
		$wpdb->query($create_outils_reglages);
		$wpdb->query($pk_outils_reglages);
		$wpdb->query($fk_outils_reglages);

		// déclenchement des routines d'installation des outils depuis la config
		// @WARNING Astuce pourrite : les fichiers des classes outils ne sont
		// normalement pas inclus si l'extension n'est pas activée, donc lors de
		// l'activation de l'extention, eh ben ils n'y sont pas encore... donc on
		// les inclut à la main ici, afin d'accéder à leur méthode "install"
		// (un peu nul - revoir cette stratégie)
		if (array_key_exists('outils', $config)) {
			require( dirname( __FILE__ ) . '/outils/TB_Outil.php' );
			foreach ($config['outils'] as $outil) {
				// include plutôt que require pour éviter une erreur fatale en cas
				// de mauvaise config
				include_once( dirname( __FILE__ ) . '/outils/' . $outil . '.php' );
				$classeOutil = TelaBotanica::nomFichierVersClasseOutil($outil);
				//echo "CO: [$classeOutil] ";
				call_user_func(array($classeOutil, 'installation'));
			}
		}
	}

	/**
	 * Méthode qui supprime les tables "{$wpdb->prefix}tb_outils" et
	 * "{$wpdb->prefix}tb_outils_reglages" dans la base de données lors de la
	 * désinstallation du plugin; appelle la méthode desinstallation() de chaque
	 * outil recensé dans la config
	 */
	static function desinstallation_outils()
	{
		global $wpdb;
		$config = chargerConfig();

		// déclenchement des routines d'installation des outils depuis la config
		if (array_key_exists('outils', $config)) {
			foreach ($config['outils'] as $outil) {
				$classeOutil = TelaBotanica::nomFichierVersClasseOutil($outil);
				//echo "CO: [$classeOutil] ";
				call_user_func(array($classeOutil, 'desinstallation'));
			}
		}

		/* On vérifie que les tables existent puis on les supprime */	
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tb_outils_reglages;");
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tb_outils;");
	}

	/* Méthode qui crée la table "{$wpdb->prefix}tb_categories_projets" dans la base de données lors de l'installation du plugin */
	static function installation_categories()
	{
		global $wpdb;
		$create_categories = "
			CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}tb_categories_projets` (
				`id_categorie` int(11) NOT NULL,
				`nom_categorie` varchar(30) NOT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=UTF8;
		";
		$insert_categories = "
			INSERT INTO `{$wpdb->prefix}tb_categories_projets` (`id_categorie`, `nom_categorie`) VALUES
				(0, 'Aucune catégorie'),
				(1, 'Botanique locale'),
				(2, 'Echanges'),
				(3, 'Outils informatiques'),
				(4, 'Organisation'),
				(5, 'Contribution'),
				(6, 'Construction')
			;
		";
		/*$create_col_categories = "
			ALTER TABLE {$wpdb->prefix}bp_groups
			ADD `id_categorie` int(11) NOT NULL;
		";*/
		$pk_categories = "
			ALTER TABLE `{$wpdb->prefix}tb_categories_projets`
 			ADD PRIMARY KEY (`id_categorie`);
		";
		/*$fk_categories = "
			ALTER TABLE `{$wpdb->prefix}tb_outils_reglages`
			ADD CONSTRAINT `fk_id-categorie_id-group` FOREIGN KEY (`id_categorie`) REFERENCES `{$wpdb->prefix}bp_groups` (`id_categorie`) ON DELETE CASCADE ON UPDATE CASCADE;
		";*/
		$wpdb->query($create_categories);
		$wpdb->query($insert_categories);
		//$wpdb->query($create_col_categories);
		$wpdb->query($pk_categories);
		//$wpdb->query($fk_categories);	
	}

	
	
	/* Méthode qui supprime la table "{$wpdb->prefix}tb_categories_projets" dans la base de données lors de la désinstallation du plugin */
	static function desinstallation_categories()
	{
		/* Classe d'accès à la base de données dans WordPress */
		global $wpdb;
	
		/* On vérifie que la table existe puis on la supprime */		
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tb_categories_projets;");
		//$wpdb->query("ALTER TABLE {$wpdb->prefix}bp_groups DROP id_categorie;");
	}
	
	
	
	/* Méthode qui crée des menus ayant pour paramètres :
	 * - Titre de la page
	 * - Libellé du menu
	 * - Intitulé des droits
	 * - Clé d'identification du menu
	 * - La fonction de rendu à appeler
	 */
	static function ajout_menu_admin()
	{
		/* Menu */
		add_menu_page('Tela Botanica','Tela Botanica','manage_options','tela-botanica',array('TelaBotanica','vue_presentation'));									
		/* Sous-menus */
		add_submenu_page('tela-botanica','Outils','Outils','manage_options','outils',array('TelaBotanica','vue_outils'));								
	}
	
		
	
	/* Méthode qui affiche la vue Apercu */
	static function vue_presentation()
	{
		$titre = get_admin_page_title();
		/* On définit l'URL de la vue HTML */
		$url_html = plugin_dir_path(__FILE__).'admin/vue_presentation.html';
		/* On récupère la vue HTML et on l'affiche */
		$html = TelaBotanica::lecture_vue($url_html,array($titre,'Tela Botanica'));
		echo $html;
	}
	
	
	
	/* Méthode qui affiche la vue A propos */
	static function vue_outils()
	{
		//$titre = get_admin_page_title();
		/* On définit l'URL de la vue HTML */
		$url_html = plugin_dir_path(__FILE__).'admin/vue_outils.html';
		/* On récupère la vue HTML et on l'affiche */
		$html = TelaBotanica::lecture_vue($url_html,array('Tela Botanica'));
		echo $html;
	}
	
	
	/**
	 * Extrait du code HTML depuis une vue, avec en paramètres l'URL du fichier
	 * HTML et les variables PHP à faire passer à la méthode
	 */
	static function lecture_vue($html,$donnees = array())
	{
		$sortie = false;
		/* On vérifie que le fichier existe */
		if (file_exists($html))
		{
			/* On ouvre le buffer et on lit le fichier */
			ob_start();
			include $html;
			/* On stocke le contenu du buffer dans une variable de sortie */
			$sortie = ob_get_contents();
			/* On vide le buffer */	
			ob_end_clean();		
		}
		return $sortie;
	}

	/**
	 * Convertit un nom d'outil correspondant au nom de fichier dans extension
	 * (ex: porte-documents) en nom de classe (ex: Porte_Documents); les - sont
	 * convertis en _, et chaque mot a la première lettre en majuscule
	 */
	static function nomFichierVersClasseOutil($nomFichier) {
		$classeOutil = $nomFichier;
		$morceaux = explode('-', $nomFichier);
		foreach ($morceaux as $m) {
			$m = ucfirst(strtolower($m));
		}
		$classeOutil = implode('_', $morceaux);
		return $classeOutil;
	}
	
		
}

new TelaBotanica();
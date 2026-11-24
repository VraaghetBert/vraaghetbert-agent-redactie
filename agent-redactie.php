<?php
/**
 * Plugin Name:       Agent-redactie
 * Description:       Laat een AI-contentagent berichten en media klaarzetten als "Ter beoordeling". Publiceren blijft altijd aan jou.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Bert Schaap
 * License:           GPL-2.0-or-later
 * Update URI:        https://github.com/VraaghetBert
 */

defined( 'ABSPATH' ) || exit;

define( 'PAR_VERSION', '1.0.0' );
define( 'PAR_FILE', __FILE__ );
define( 'PAR_DIR', plugin_dir_path( __FILE__ ) );
// De mapnaam is ook de naam van de GitHub-repository, bijvoorbeeld vraaghetbert-agent-redactie.
define( 'PAR_SLUG', basename( __DIR__ ) );
define( 'PAR_ROL', 'content_agent' );

require_once PAR_DIR . 'inc/rol.php';
require_once PAR_DIR . 'inc/vangnet.php';
require_once PAR_DIR . 'inc/meldingen.php';
require_once PAR_DIR . 'inc/instellingen.php';
require_once PAR_DIR . 'inc/updates.php';

register_activation_hook( __FILE__, 'par_activeer' );
register_deactivation_hook( __FILE__, 'par_deactiveer' );

/**
 * Activeren: rol aanmaken en dagelijkse bundelmail inplannen.
 */
function par_activeer() {
	par_rol_aanmaken();
	if ( ! wp_next_scheduled( 'par_dagelijkse_bundel' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 08:00' ), 'daily', 'par_dagelijkse_bundel' );
	}
}

/**
 * Deactiveren: rol verwijderen. Gebruikers met deze rol houden dan geen rechten over
 * (veilig dicht). Bij opnieuw activeren krijgen ze hun rechten vanzelf terug.
 */
function par_deactiveer() {
	remove_role( PAR_ROL );
	wp_clear_scheduled_hook( 'par_dagelijkse_bundel' );
}

/**
 * Is de huidige (of opgegeven) gebruiker een contentagent?
 */
function par_is_agent( $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	if ( is_numeric( $user ) ) {
		$user = get_userdata( (int) $user );
	}
	return $user instanceof WP_User && in_array( PAR_ROL, (array) $user->roles, true );
}

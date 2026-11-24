<?php
// Bij verwijderen: rol en instellingen opruimen. Berichten en media blijven staan.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
remove_role( 'content_agent' );
foreach ( array( 'par_ontvanger', 'par_mailmodus', 'par_github_repo', 'par_rol_versie' ) as $optie ) {
	delete_option( $optie );
}
delete_site_transient( 'par_release' );
wp_clear_scheduled_hook( 'par_dagelijkse_bundel' );

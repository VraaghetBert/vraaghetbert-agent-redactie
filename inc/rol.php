<?php
/**
 * De rol "Content-agent": schrijven en media uploaden, niet publiceren.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rechten van de rol. Bewust zonder publish_posts, edit_published_posts,
 * delete_posts, edit_pages, edit_others_posts en manage_categories.
 */
function par_rol_rechten() {
	return array(
		'read'         => true,
		'edit_posts'   => true, // eigen concepten en berichten ter beoordeling
		'upload_files' => true, // afbeeldingen uploaden
	);
}

function par_rol_aanmaken() {
	remove_role( PAR_ROL ); // altijd de actuele set rechten
	add_role( PAR_ROL, 'Content-agent', par_rol_rechten() );
}

/**
 * Houd de rol gelijk aan de code na een plugin-update.
 */
add_action( 'init', function () {
	if ( get_option( 'par_rol_versie' ) !== PAR_VERSION ) {
		par_rol_aanmaken();
		update_option( 'par_rol_versie', PAR_VERSION, false );
	}
} );

/**
 * Een contentagent mag de eigen geüploade afbeeldingen bijwerken (alt-tekst, titel,
 * bijschrift), ook als ze nog niet aan een bericht hangen. WordPress behandelt losse
 * media als "gepubliceerd" en vraagt dan edit_published_posts; dat recht geven we
 * bewust niet, omdat de agent daarmee ook live berichten zou kunnen wijzigen.
 * Verwijderen van media blijft niet toegestaan.
 */
add_filter( 'map_meta_cap', function ( $caps, $cap, $user_id, $args ) {
	if ( 'edit_post' !== $cap || empty( $args[0] ) || ! par_is_agent( $user_id ) ) {
		return $caps;
	}
	$post = get_post( (int) $args[0] );
	if ( $post && 'attachment' === $post->post_type && (int) $post->post_author === (int) $user_id ) {
		return array( 'upload_files' );
	}
	return $caps;
}, 10, 4 );

/**
 * Houd de agent uit het beheerscherm, behalve de schermen die bij schrijven horen.
 * De agent werkt via de koppeling, niet via wp-admin; dit is een extra slot.
 */
add_action( 'admin_init', function () {
	if ( wp_doing_ajax() || ! par_is_agent() ) {
		return;
	}
	global $pagenow;
	$toegestaan = array( 'index.php', 'profile.php', 'edit.php', 'post.php', 'post-new.php', 'upload.php', 'media-new.php', 'async-upload.php' );
	if ( ! in_array( $pagenow, $toegestaan, true ) ) {
		wp_safe_redirect( admin_url( 'edit.php' ) );
		exit;
	}
} );

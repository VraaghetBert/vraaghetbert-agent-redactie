<?php
/**
 * Mail naar Bert zodra een agent iets ter beoordeling heeft klaargezet:
 * per bericht, of één bundel per dag.
 */

defined( 'ABSPATH' ) || exit;

function par_ontvanger() {
	$mail = get_option( 'par_ontvanger' );
	return is_email( $mail ) ? $mail : get_option( 'admin_email' );
}

function par_mailmodus() {
	return 'bundel' === get_option( 'par_mailmodus' ) ? 'bundel' : 'direct';
}

/**
 * Regels voor één bericht in de mail.
 */
function par_berichtregels( WP_Post $post ) {
	$titel = get_the_title( $post ) ?: '(zonder titel)';
	return sprintf(
		"%s\nVoorbeeld: %s\nBewerken en publiceren: %s\n",
		wp_strip_all_tags( $titel ),
		get_preview_post_link( $post ),
		admin_url( 'post.php?post=' . $post->ID . '&action=edit' )
	);
}

add_action( 'transition_post_status', function ( $nieuw, $oud, $post ) {
	if ( 'pending' !== $nieuw || 'pending' === $oud || 'post' !== $post->post_type ) {
		return;
	}
	if ( ! par_is_agent( (int) $post->post_author ) ) {
		return;
	}
	if ( 'bundel' === par_mailmodus() ) {
		return; // de dagelijkse bundel pakt hem op
	}
	$onderwerp = sprintf( '[%s] Klaar voor je beoordeling: %s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), wp_strip_all_tags( get_the_title( $post ) ) );
	$tekst     = "De contentagent heeft een bericht voor je klaargezet.\n\n" . par_berichtregels( $post ) .
		"\nJij beslist: publiceren, inplannen of aanpassen.\n";
	wp_mail( par_ontvanger(), $onderwerp, $tekst );
}, 20, 3 );

/**
 * Dagelijkse bundel om 08:00 (alleen als er iets klaarstaat).
 */
add_action( 'par_dagelijkse_bundel', 'par_stuur_bundel' );

function par_stuur_bundel() {
	if ( 'bundel' !== par_mailmodus() ) {
		return 0;
	}
	$agents = get_users( array( 'role' => PAR_ROL, 'fields' => 'ID' ) );
	if ( ! $agents ) {
		return 0;
	}
	$berichten = get_posts( array(
		'post_type'      => 'post',
		'post_status'    => 'pending',
		'author__in'     => $agents,
		'posts_per_page' => 50,
		'orderby'        => 'date',
		'order'          => 'ASC',
	) );
	if ( ! $berichten ) {
		return 0;
	}
	$tekst = sprintf( "Er %s %d bericht%s klaar voor je beoordeling.\n\n", 1 === count( $berichten ) ? 'staat' : 'staan', count( $berichten ), 1 === count( $berichten ) ? '' : 'en' );
	foreach ( $berichten as $post ) {
		$tekst .= par_berichtregels( $post ) . "\n";
	}
	$tekst .= "Jij beslist: publiceren, inplannen of aanpassen.\n";
	wp_mail( par_ontvanger(), sprintf( '[%s] %d bericht(en) klaar voor je beoordeling', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), count( $berichten ) ), $tekst );
	return count( $berichten );
}

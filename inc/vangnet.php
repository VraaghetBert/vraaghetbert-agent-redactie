<?php
/**
 * Vangnet: wat een contentagent ook probeert, een bericht komt nooit live.
 * Werkt op het laagste niveau (wp_insert_post), dus ook als een koppeling
 * de REST API overslaat.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Statussen die een agent mag gebruiken. Alles daarbuiten wordt "Ter beoordeling",
 * ook "Concept": zo komt elk stuk in jouw beoordelingslijst en krijg je altijd een
 * melding, welke koppeling de agent ook gebruikt.
 */
function par_toegestane_statussen() {
	return array( 'pending', 'auto-draft', 'inherit' );
}

add_filter( 'wp_insert_post_data', function ( $data, $postarr ) {
	if ( ! par_is_agent() || current_user_can( 'publish_posts' ) ) {
		return $data;
	}
	if ( 'attachment' === $data['post_type'] || 'revision' === $data['post_type'] ) {
		return $data;
	}
	if ( 'trash' === $data['post_status'] ) {
		// Weggooien mag niet: terug naar ter beoordeling.
		$data['post_status'] = 'pending';
	}
	if ( ! in_array( $data['post_status'], par_toegestane_statussen(), true ) ) {
		$data['post_status'] = 'pending';
		// Een datum in de toekomst is een poging tot inplannen: datum leegmaken,
		// zodat Bert zelf het moment kiest.
		$data['post_date']     = current_time( 'mysql' );
		$data['post_date_gmt'] = '0000-00-00 00:00:00';
	}
	$data['post_password'] = ''; // geen afgeschermde berichten
	return $data;
}, 99, 2 );

/**
 * Plakkerig maken (bovenaan vastzetten) is een publicatiekeuze: niet voor de agent.
 */
add_filter( 'pre_update_option_sticky_posts', function ( $nieuw, $oud ) {
	return par_is_agent() ? $oud : $nieuw;
}, 10, 2 );

/**
 * Dubbele bodem voor de laatste stap: mocht een bericht van een agent toch
 * naar "publish" of "future" gaan terwijl een agent de huidige gebruiker is,
 * dan zetten we het direct terug.
 */
add_action( 'transition_post_status', function ( $nieuw, $oud, $post ) {
	if ( ! in_array( $nieuw, array( 'publish', 'future', 'private' ), true ) ) {
		return;
	}
	if ( ! par_is_agent() || current_user_can( 'publish_posts' ) || 'attachment' === $post->post_type ) {
		return;
	}
	global $wpdb;
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $post->ID ) );
	clean_post_cache( $post->ID );
}, 1, 3 );

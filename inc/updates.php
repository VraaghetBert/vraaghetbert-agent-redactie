<?php
/**
 * Updates via GitHub-releases. Elke release moet een zip met de naam van de
 * repository bevatten, bijvoorbeeld vraaghetbert-agent-redactie.zip (de workflow maakt dat).
 */

defined( 'ABSPATH' ) || exit;

define( 'PAR_STANDAARD_REPO', 'VraaghetBert/' . PAR_SLUG );
define( 'PAR_ZIPNAAM', PAR_SLUG . '.zip' );

function par_repo_opschonen( $waarde ) {
	$waarde = trim( (string) $waarde );
	$waarde = preg_replace( '#^https?://github\.com/#i', '', $waarde );
	$waarde = trim( preg_replace( '#\.git$#', '', $waarde ), '/' );
	return preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $waarde ) ? $waarde : '';
}

function par_github_repo() {
	$repo = par_repo_opschonen( get_option( 'par_github_repo', '' ) );
	return $repo ? $repo : PAR_STANDAARD_REPO;
}

function par_github_token() {
	return defined( 'PLANCIJFER_GITHUB_TOKEN' ) ? (string) PLANCIJFER_GITHUB_TOKEN : '';
}

function par_github_headers( $accept = 'application/vnd.github+json' ) {
	$headers = array( 'Accept' => $accept, 'User-Agent' => 'agent-redactie' );
	if ( par_github_token() ) {
		$headers['Authorization'] = 'Bearer ' . par_github_token();
	}
	return $headers;
}

/**
 * Nieuwste release ophalen (6 uur in cache).
 *
 * @return array|WP_Error array( version, package, url )
 */
function par_haal_release() {
	$cache = get_site_transient( 'par_release' );
	if ( false !== $cache ) {
		return $cache;
	}
	$antwoord = wp_remote_get( 'https://api.github.com/repos/' . par_github_repo() . '/releases/latest', array(
		'timeout' => 10,
		'headers' => par_github_headers(),
	) );
	$code = wp_remote_retrieve_response_code( $antwoord );
	if ( is_wp_error( $antwoord ) || 200 !== $code ) {
		$fout = new WP_Error( 'par_geen_release', 404 === $code ? 'Geen release gevonden. Klopt de repository, en is het token ingevuld bij een privé-repository?' : 'GitHub is nu niet bereikbaar.' );
		set_site_transient( 'par_release', $fout, HOUR_IN_SECONDS );
		return $fout;
	}
	$release = json_decode( wp_remote_retrieve_body( $antwoord ), true );
	$asset   = null;
	foreach ( (array) ( $release['assets'] ?? array() ) as $a ) {
		if ( PAR_ZIPNAAM === ( $a['name'] ?? '' ) ) {
			$asset = $a;
		}
	}
	if ( ! $asset ) {
		$fout = new WP_Error( 'par_geen_zip', 'De nieuwste release heeft geen bestand ' . PAR_ZIPNAAM . '.' );
		set_site_transient( 'par_release', $fout, HOUR_IN_SECONDS );
		return $fout;
	}
	$info = array(
		'version' => ltrim( (string) ( $release['tag_name'] ?? '' ), 'vV' ),
		// Bij een privé-repository downloaden we via de API (met token), anders de gewone link.
		'package' => par_github_token() ? $asset['url'] : $asset['browser_download_url'],
		'url'     => (string) ( $release['html_url'] ?? '' ),
	);
	set_site_transient( 'par_release', $info, 6 * HOUR_IN_SECONDS );
	return $info;
}

/**
 * WordPress vraagt via de Update URI-header (github.com) om updatedata.
 */
add_filter( 'update_plugins_github.com', function ( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( PAR_FILE ) !== $plugin_file ) {
		return $update;
	}
	$release = par_haal_release();
	if ( is_wp_error( $release ) || ! $release['version'] ) {
		return $update;
	}
	return array(
		'slug'         => dirname( $plugin_file ),
		'version'      => $release['version'],
		'url'          => $release['url'],
		'package'      => $release['package'],
		'requires_php' => '8.0',
	);
}, 10, 3 );

/**
 * Privé-repository: download zelf afhandelen, zodat het token alleen naar de
 * GitHub-API gaat en niet mee naar de downloadserver waar GitHub naar doorverwijst.
 */
add_filter( 'upgrader_pre_download', function ( $antwoord, $package ) {
	if ( false !== $antwoord || ! par_github_token() || 0 !== strpos( (string) $package, 'https://api.github.com/repos/' . par_github_repo() . '/releases/assets/' ) ) {
		return $antwoord;
	}
	$stap1 = wp_remote_get( $package, array(
		'timeout'     => 15,
		'redirection' => 0,
		'headers'     => par_github_headers( 'application/octet-stream' ),
	) );
	$doel = wp_remote_retrieve_header( $stap1, 'location' );
	if ( is_wp_error( $stap1 ) || ! $doel ) {
		return new WP_Error( 'par_download', 'Downloaden van de update via GitHub is mislukt. Is het token nog geldig?' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	return download_url( $doel, 300 );
}, 10, 2 );

/**
 * Na de update: cache leegmaken.
 */
add_action( 'upgrader_process_complete', function () {
	delete_site_transient( 'par_release' );
} );

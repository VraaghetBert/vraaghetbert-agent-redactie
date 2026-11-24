<?php
/**
 * Instellingen → Agent-redactie: ontvanger, mailmodus en GitHub-updates.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', function () {
	register_setting( 'par_instellingen', 'par_ontvanger', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ) );
	register_setting( 'par_instellingen', 'par_mailmodus', array(
		'type'              => 'string',
		'sanitize_callback' => function ( $v ) { return 'bundel' === $v ? 'bundel' : 'direct'; },
	) );
	register_setting( 'par_instellingen', 'par_github_repo', array( 'type' => 'string', 'sanitize_callback' => 'par_repo_opschonen' ) );
} );

add_action( 'admin_menu', function () {
	add_options_page( 'Agent-redactie', 'Agent-redactie', 'manage_options', 'par-instellingen', 'par_instellingen_pagina' );
} );

function par_instellingen_pagina() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$agents    = get_users( array( 'role' => PAR_ROL ) );
	$wachtend  = $agents ? count( get_posts( array( 'post_status' => 'pending', 'author__in' => wp_list_pluck( $agents, 'ID' ), 'posts_per_page' => -1, 'fields' => 'ids' ) ) ) : 0;
	$update    = isset( $_GET['par_gecontroleerd'] ) ? get_site_transient( 'par_release' ) : null;
	?>
	<div class="wrap">
		<h1>Agent-redactie</h1>
		<p>De contentagent zet berichten klaar als <strong>Ter beoordeling</strong>. Publiceren en inplannen doe jij.</p>

		<h2>Stand van zaken</h2>
		<ul>
			<li>Gebruikers met de rol Content-agent: <strong><?php echo esc_html( $agents ? implode( ', ', wp_list_pluck( $agents, 'user_login' ) ) : 'nog geen' ); ?></strong></li>
			<li>Berichten ter beoordeling van een agent: <a href="<?php echo esc_url( admin_url( 'edit.php?post_status=pending' ) ); ?>"><strong><?php echo (int) $wachtend; ?></strong></a></li>
		</ul>
		<?php if ( ! $agents ) : ?>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>">Gebruiker contentagent aanmaken</a> Kies bij <em>Rol</em> voor <strong>Content-agent</strong>.</p>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'par_instellingen' ); ?>
			<h2>Meldingen</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="par_ontvanger">Mail naar</label></th>
					<td><input type="email" class="regular-text" id="par_ontvanger" name="par_ontvanger" value="<?php echo esc_attr( get_option( 'par_ontvanger', '' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
					<p class="description">Leeg laten = het beheerdersadres.</p></td>
				</tr>
				<tr>
					<th scope="row">Wanneer</th>
					<td>
						<label><input type="radio" name="par_mailmodus" value="direct" <?php checked( 'direct', par_mailmodus() ); ?>> Direct bij elk klaargezet bericht</label><br>
						<label><input type="radio" name="par_mailmodus" value="bundel" <?php checked( 'bundel', par_mailmodus() ); ?>> Eén bundelmail per dag (rond 08:00)</label>
					</td>
				</tr>
			</table>
			<h2>Updates via GitHub</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="par_github_repo">GitHub-repository</label></th>
					<td><input type="text" class="regular-text" id="par_github_repo" name="par_github_repo" value="<?php echo esc_attr( par_github_repo() ); ?>">
					<p class="description">Bijvoorbeeld <code><?php echo esc_html( PAR_STANDAARD_REPO ); ?></code>. Privé-repository? Zet het token in <code>wp-config.php</code>: <code>define( 'PLANCIJFER_GITHUB_TOKEN', 'github_pat_...' );</code></p></td>
				</tr>
			</table>
			<?php submit_button( 'Opslaan' ); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="par_controleer_update">
			<?php wp_nonce_field( 'par_controleer_update' ); ?>
			<p><button class="button">Nu controleren op updates</button>
			<?php
			if ( $update ) {
				if ( is_wp_error( $update ) ) {
					echo ' <span style="color:#b32d2e">' . esc_html( $update->get_error_message() ) . '</span>';
				} else {
					$bij = version_compare( $update['version'], PAR_VERSION, '<=' );
					echo ' <span>Nieuwste release ' . esc_html( $update['version'] ) . ( $bij ? ' – je bent bij.' : ' – <a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">update beschikbaar</a>.' ) . '</span>';
				}
			}
			?>
			</p>
		</form>
		<p class="description">Versie <?php echo esc_html( PAR_VERSION ); ?></p>
	</div>
	<?php
}

add_action( 'admin_post_par_controleer_update', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Geen toegang.' );
	}
	check_admin_referer( 'par_controleer_update' );
	delete_site_transient( 'par_release' );
	par_haal_release();
	delete_site_transient( 'update_plugins' );
	wp_safe_redirect( admin_url( 'options-general.php?page=par-instellingen&par_gecontroleerd=1' ) );
	exit;
} );

add_filter( 'plugin_action_links_' . plugin_basename( PAR_FILE ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=par-instellingen' ) ) . '">Instellingen</a>' );
	return $links;
} );

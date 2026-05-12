<?php
/**
 * Settings view.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

$owner_const = defined( 'DIGITONE_EVENTS_GH_OWNER' );
$repo_const  = defined( 'DIGITONE_EVENTS_GH_REPO' );
$token_const = defined( 'DIGITONE_EVENTS_GH_TOKEN' ) && DIGITONE_EVENTS_GH_TOKEN;

$owner = get_option( 'digitone_events_gh_owner', DIGITONE_EVENTS_GH_OWNER );
$repo  = get_option( 'digitone_events_gh_repo',  DIGITONE_EVENTS_GH_REPO );
$token = get_option( 'digitone_events_gh_token', '' );
?>
<div class="wrap digitone-events-settings">
	<h1><?php esc_html_e( 'DigitOne Events — Settings', 'digitone-events' ); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields( DigitOne_Events_Settings::GROUP ); ?>

		<h2><?php esc_html_e( 'GitHub auto-update', 'digitone-events' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Configure the GitHub repository that hosts this plugin\'s releases. WordPress will check this repository for new versions and offer one-click updates.', 'digitone-events' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="digitone_events_gh_owner"><?php esc_html_e( 'GitHub owner / org', 'digitone-events' ); ?></label></th>
				<td>
					<input name="digitone_events_gh_owner" id="digitone_events_gh_owner" type="text" class="regular-text"
						value="<?php echo esc_attr( $owner ); ?>" <?php disabled( $owner_const ); ?>>
					<?php if ( $owner_const ) : ?>
						<p class="description"><?php esc_html_e( 'Set via DIGITONE_EVENTS_GH_OWNER constant in wp-config.php.', 'digitone-events' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="digitone_events_gh_repo"><?php esc_html_e( 'Repository name', 'digitone-events' ); ?></label></th>
				<td>
					<input name="digitone_events_gh_repo" id="digitone_events_gh_repo" type="text" class="regular-text"
						value="<?php echo esc_attr( $repo ); ?>" <?php disabled( $repo_const ); ?>>
					<?php if ( $repo_const ) : ?>
						<p class="description"><?php esc_html_e( 'Set via DIGITONE_EVENTS_GH_REPO constant in wp-config.php.', 'digitone-events' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="digitone_events_gh_token"><?php esc_html_e( 'Personal access token', 'digitone-events' ); ?></label></th>
				<td>
					<input name="digitone_events_gh_token" id="digitone_events_gh_token" type="password" class="regular-text"
						value="<?php echo esc_attr( $token ); ?>" <?php disabled( $token_const ); ?> autocomplete="off">
					<p class="description">
						<?php esc_html_e( 'Only needed for private repositories. For better security, set DIGITONE_EVENTS_GH_TOKEN constant in wp-config.php instead.', 'digitone-events' ); ?>
						<?php if ( $token_const ) : ?>
							<br><strong><?php esc_html_e( 'Currently provided by constant.', 'digitone-events' ); ?></strong>
						<?php endif; ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Check for updates now', 'digitone-events' ); ?></h2>
	<p>
		<?php
		printf(
			/* translators: %s: current installed version */
			esc_html__( 'Installed version: %s', 'digitone-events' ),
			'<code>' . esc_html( DIGITONE_EVENTS_VERSION ) . '</code>'
		);
		?>
	</p>
	<p>
		<button type="button" class="button button-primary" id="de-check-update-btn">
			<?php esc_html_e( 'Force check GitHub', 'digitone-events' ); ?>
		</button>
		<span id="de-check-update-result" class="de-check-result"></span>
	</p>
	<p class="description">
		<?php esc_html_e( 'This bypasses the 6-hour update cache and reads the latest release directly. If a newer version is found, it will appear in Plugins → Installed Plugins on the next admin page load.', 'digitone-events' ); ?>
	</p>
</div>

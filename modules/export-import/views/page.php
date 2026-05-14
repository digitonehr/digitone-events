<?php
/**
 * Export / Import page.
 *
 * @var string|null $active_event_id
 * @var string      $imported
 * @var string      $new_event_id
 * @var string      $error
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

$events_repo = DigitOne_Events_Plugin::instance()->module( 'events' )->repo();
$active      = $active_event_id ? $events_repo->find( $active_event_id ) : null;

$export_json_url = $active ? wp_nonce_url(
	admin_url( 'admin-post.php?action=digitone_events_export&format=json&event_id=' . urlencode( $active_event_id ) ),
	DigitOne_Events_Export_Import_Ajax::EXPORT_NONCE
) : '';
$export_csv_url = $active ? wp_nonce_url(
	admin_url( 'admin-post.php?action=digitone_events_export&format=csv&event_id=' . urlencode( $active_event_id ) ),
	DigitOne_Events_Export_Import_Ajax::EXPORT_NONCE
) : '';
?>
<div class="wrap digitone-events-page" data-page="export-import">
	<h1><?php esc_html_e( 'Export / Import', 'digitone-events' ); ?></h1>

	<?php DigitOne_Events_Helpers_View::render( 'admin/views/partials/active-event-bar', [ 'active_event_id' => $active_event_id ] ); ?>

	<?php if ( $imported ) :
		$new_event = $new_event_id ? $events_repo->find( $new_event_id ) : null;
	?>
		<div class="notice notice-success">
			<p>
				<?php esc_html_e( 'Import successful!', 'digitone-events' ); ?>
				<?php if ( $new_event ) : ?>
					<?php printf(
						/* translators: 1: new event name, 2: edit URL */
						' ' . esc_html__( 'Created event "%1$s". %2$s', 'digitone-events' ),
						esc_html( $new_event['name'] ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=digitone-events-events' ) ) . '">' . esc_html__( 'View it on Events page', 'digitone-events' ) . '</a>'
					); ?>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $error ) : ?>
		<div class="notice notice-error">
			<p>
				<?php
				$error_messages = [
					'no_file'      => __( 'No file was uploaded.', 'digitone-events' ),
					'bad_type'     => __( 'Only .json files are accepted.', 'digitone-events' ),
					'too_big'      => __( 'File exceeds 10 MB limit.', 'digitone-events' ),
					'unreadable'   => __( 'Could not read the uploaded file.', 'digitone-events' ),
					'invalid_json' => __( 'The uploaded file is not valid JSON.', 'digitone-events' ),
				];
				$msg = $error_messages[ $error ] ?? rawurldecode( $error );
				echo '<strong>' . esc_html__( 'Import failed:', 'digitone-events' ) . '</strong> ' . esc_html( $msg );
				?>
			</p>
		</div>
	<?php endif; ?>

	<div class="de-ei-grid">
		<!-- Export panel -->
		<section class="de-ei-card">
			<h2><?php esc_html_e( 'Export', 'digitone-events' ); ?></h2>
			<?php if ( $active ) : ?>
				<p>
					<?php printf(
						/* translators: %s: event name */
						esc_html__( 'Download all data for %s in your preferred format.', 'digitone-events' ),
						'<strong>' . esc_html( $active['name'] ) . '</strong>'
					); ?>
				</p>
				<p class="de-ei-buttons">
					<a class="button button-primary button-hero" href="<?php echo esc_url( $export_json_url ); ?>">
						📦 <?php esc_html_e( 'Download JSON', 'digitone-events' ); ?>
					</a>
					<a class="button button-hero" href="<?php echo esc_url( $export_csv_url ); ?>">
						📊 <?php esc_html_e( 'Download CSV (zip)', 'digitone-events' ); ?>
					</a>
				</p>
				<p class="description">
					<?php esc_html_e( 'JSON is a complete, round-trip snapshot — use it for backup or moving an event between sites. CSV is for opening in a spreadsheet (one file per data type, packaged as a zip).', 'digitone-events' ); ?>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'Select an active event to export.', 'digitone-events' ); ?></p>
			<?php endif; ?>
		</section>

		<!-- Import panel -->
		<section class="de-ei-card">
			<h2><?php esc_html_e( 'Import as new event', 'digitone-events' ); ?></h2>
			<p><?php esc_html_e( 'Upload a JSON file exported from DigitOne Events. A brand new event will be created from its contents — your existing events are not modified.', 'digitone-events' ); ?></p>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="digitone_events_import">
				<?php wp_nonce_field( DigitOne_Events_Export_Import_Ajax::IMPORT_NONCE ); ?>

				<p>
					<label for="de-import-file"><strong><?php esc_html_e( 'JSON file:', 'digitone-events' ); ?></strong></label><br>
					<input type="file" name="import_file" id="de-import-file" accept=".json,application/json" required>
				</p>
				<p>
					<label for="de-import-name"><?php esc_html_e( 'Override event name (optional):', 'digitone-events' ); ?></label><br>
					<input type="text" name="new_name" id="de-import-name" class="regular-text" placeholder="<?php esc_attr_e( 'Leave blank to use original name + (imported)', 'digitone-events' ); ?>">
				</p>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Import as new event', 'digitone-events' ); ?></button>
				</p>
				<p class="description">
					<?php esc_html_e( 'Max upload size: 10 MB. The imported event starts as a draft and you can publish it from the Events screen.', 'digitone-events' ); ?>
				</p>
			</form>
		</section>
	</div>

	<?php DigitOne_Events_Helpers_View::render(
		'modules/export-import/views/excel-panel',
		[
			'active_event_id' => $active_event_id,
			'active_event'    => $active,
		]
	); ?>

	<details class="de-ei-format-notes">
		<summary><?php esc_html_e( 'JSON format reference', 'digitone-events' ); ?></summary>
		<p><?php esc_html_e( 'The export includes:', 'digitone-events' ); ?></p>
		<ul>
			<li><code>event</code> — name, slug, description, dates, status</li>
			<li><code>days[]</code> — date, times, label, order</li>
			<li><code>venues[]</code> — name, address, type (primary/sub-venue), parent reference</li>
			<li><code>titles[]</code>, <code>roles[]</code>, <code>session_types[]</code> — taxonomies</li>
			<li><code>speakers[]</code> — with <code>role_ids</code></li>
			<li><code>sessions[]</code> — with <code>speaker_assignments</code></li>
		</ul>
		<p><?php esc_html_e( 'All IDs are UUIDs. On import, fresh UUIDs are generated and all references re-mapped automatically.', 'digitone-events' ); ?></p>
	</details>
</div>

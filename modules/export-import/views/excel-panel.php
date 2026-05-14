<?php
/**
 * Excel import / export panel — rendered inside Export / Import admin page.
 *
 * Uses SheetJS (lazy-loaded from cdnjs by assets/js/modules/excel-import.js)
 * for both reading uploaded .xlsx and writing the export / template files.
 *
 * @var string|null  $active_event_id
 * @var array        $active_event   from events repo, or null
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="de-ei-card de-ei-excel-card" data-de-excel-panel data-active-event="<?php echo esc_attr( $active_event_id ?: '' ); ?>">
	<h2><?php esc_html_e( 'Excel bulk import / export', 'digitone-events' ); ?></h2>

	<?php if ( ! $active_event_id ) : ?>
		<p><?php esc_html_e( 'Select an active event first.', 'digitone-events' ); ?></p>
	<?php else : ?>

		<p class="de-excel-blurb">
			<?php esc_html_e( 'Edit the schedule in Excel. One workbook with one sheet per entity (Days, Venues, Sub-Venues, Session-Types, Roles, Titles, Speakers, Sessions). Sheet names and column headers are matched automatically.', 'digitone-events' ); ?>
		</p>

		<div class="de-excel-row">
			<div class="de-excel-col">
				<h3><?php esc_html_e( '1. Get a workbook', 'digitone-events' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Either start from an empty template, or download the current event so you can edit existing rows.', 'digitone-events' ); ?></p>
				<p class="de-excel-buttons">
					<button type="button" class="button" data-de-action="excel-download-template">
						📄 <?php esc_html_e( 'Download empty template', 'digitone-events' ); ?>
					</button>
					<button type="button" class="button button-primary" data-de-action="excel-download-current">
						📦 <?php esc_html_e( 'Download current data', 'digitone-events' ); ?>
					</button>
				</p>
			</div>

			<div class="de-excel-col">
				<h3><?php esc_html_e( '2. Upload your workbook', 'digitone-events' ); ?></h3>
				<fieldset class="de-excel-mode" data-de-excel-mode>
					<legend class="screen-reader-text"><?php esc_html_e( 'Import mode', 'digitone-events' ); ?></legend>
					<label class="de-excel-mode-option is-active">
						<input type="radio" name="de-excel-mode" value="incremental" checked>
						<span class="de-excel-mode-title"><?php esc_html_e( 'Incremental', 'digitone-events' ); ?></span>
						<span class="de-excel-mode-desc"><?php esc_html_e( 'Insert rows that don\'t exist yet; skip the rest. Safe to re-run.', 'digitone-events' ); ?></span>
					</label>
					<label class="de-excel-mode-option de-excel-mode-danger">
						<input type="radio" name="de-excel-mode" value="full">
						<span class="de-excel-mode-title"><?php esc_html_e( 'Full overwrite', 'digitone-events' ); ?></span>
						<span class="de-excel-mode-desc"><?php esc_html_e( 'Delete everything for this event, then insert from the workbook. Destructive — backup recommended.', 'digitone-events' ); ?></span>
					</label>
				</fieldset>
				<div class="de-excel-upload">
					<input type="file" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" data-de-excel-file>
					<button type="button" class="button button-primary" data-de-action="excel-preview" disabled>
						<?php esc_html_e( 'Preview import', 'digitone-events' ); ?>
					</button>
				</div>
				<p class="description"><?php esc_html_e( 'Max 10 MB. Only the active event will receive these rows.', 'digitone-events' ); ?></p>
			</div>
		</div>

		<div class="de-excel-result" data-de-excel-result hidden>
			<h3><?php esc_html_e( 'Preview', 'digitone-events' ); ?></h3>
			<div class="de-excel-summary" data-de-excel-summary></div>
			<table class="wp-list-table widefat striped de-excel-table" data-de-excel-table>
				<thead>
					<tr>
						<th><?php esc_html_e( 'Entity', 'digitone-events' ); ?></th>
						<th class="de-col-num de-excel-col-delete" hidden><?php esc_html_e( 'Will delete', 'digitone-events' ); ?></th>
						<th class="de-col-num"><?php esc_html_e( 'Will insert', 'digitone-events' ); ?></th>
						<th class="de-col-num"><?php esc_html_e( 'Will skip', 'digitone-events' ); ?></th>
						<th class="de-col-num"><?php esc_html_e( 'Errors', 'digitone-events' ); ?></th>
					</tr>
				</thead>
				<tbody data-de-excel-rows></tbody>
			</table>

			<div class="de-excel-errors" data-de-excel-errors hidden>
				<h4><?php esc_html_e( 'Rows that will be skipped due to errors', 'digitone-events' ); ?></h4>
				<table class="wp-list-table widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Sheet', 'digitone-events' ); ?></th>
							<th class="de-col-num"><?php esc_html_e( 'Row', 'digitone-events' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'digitone-events' ); ?></th>
						</tr>
					</thead>
					<tbody data-de-excel-errors-body></tbody>
				</table>
			</div>

			<p class="de-excel-buttons" data-de-excel-confirm-wrap>
				<button type="button" class="button" data-de-action="excel-cancel"><?php esc_html_e( 'Cancel', 'digitone-events' ); ?></button>
				<button type="button" class="button button-primary" data-de-action="excel-commit">
					<?php esc_html_e( 'Confirm import', 'digitone-events' ); ?>
				</button>
			</p>
		</div>

	<?php /* Full-mode confirmation dialog. Hidden until user clicks Confirm
	         in full mode; forces them to download a JSON backup (or tick
	         "I already have a backup") before the Replace button enables. */ ?>
	<dialog class="de-excel-danger-dialog" data-de-excel-danger>
		<form method="dialog">
			<header class="de-excel-danger-header">
				<h3>⚠️ <?php esc_html_e( 'Confirm destructive import', 'digitone-events' ); ?></h3>
			</header>
			<div class="de-excel-danger-body">
				<p>
					<?php
					/* translators: %s: event name */
					printf(
						esc_html__( 'You are about to REPLACE all existing data for %s with the contents of the uploaded workbook. This includes:', 'digitone-events' ),
						'<strong>' . esc_html( $active_event['name'] ?? '' ) . '</strong>'
					);
					?>
				</p>
				<ul class="de-excel-danger-list" data-de-danger-list>
					<!-- populated by JS from preview totals -->
				</ul>
				<p class="de-excel-danger-irreversible"><strong><?php esc_html_e( 'This cannot be undone.', 'digitone-events' ); ?></strong> <?php esc_html_e( 'Strongly recommended: download a JSON backup first.', 'digitone-events' ); ?></p>
				<p>
					<?php
					$backup_url = wp_nonce_url(
						admin_url( 'admin-post.php?action=digitone_events_export&format=json&event_id=' . urlencode( $active_event_id ) ),
						DigitOne_Events_Export_Import_Ajax::EXPORT_NONCE
					);
					?>
					<a class="button button-primary button-hero"
						href="<?php echo esc_url( $backup_url ); ?>"
						target="_blank"
						rel="noopener"
						data-de-backup-link>
						📦 <?php esc_html_e( 'Download JSON backup', 'digitone-events' ); ?>
					</a>
				</p>
				<label class="de-excel-danger-confirm">
					<input type="checkbox" data-de-danger-checkbox>
					<?php esc_html_e( 'I have a backup and understand this cannot be undone.', 'digitone-events' ); ?>
				</label>
			</div>
			<footer class="de-excel-danger-footer">
				<button type="button" class="button" data-de-action="excel-danger-cancel"><?php esc_html_e( 'Cancel', 'digitone-events' ); ?></button>
				<button type="button" class="button button-danger" data-de-action="excel-danger-proceed" disabled>
					<?php esc_html_e( 'Replace everything', 'digitone-events' ); ?>
				</button>
			</footer>
		</form>
	</dialog>

	<?php endif; ?>
</section>

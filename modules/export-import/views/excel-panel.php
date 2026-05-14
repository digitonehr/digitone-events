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
				<p class="description"><?php esc_html_e( 'Incremental mode only in this release: rows that already exist (matched by name / date / time) are skipped, the rest are inserted.', 'digitone-events' ); ?></p>
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

	<?php endif; ?>
</section>

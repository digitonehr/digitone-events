<?php
/**
 * Days list view.
 *
 * @var array<int,array<string,mixed>> $days
 * @var string|null $active_event_id
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap digitone-events-page" data-page="days" data-active-event="<?php echo esc_attr( $active_event_id ?: '' ); ?>">

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Days', 'digitone-events' ); ?></h1>
	<?php if ( $active_event_id ) : ?>
		<button type="button" class="page-title-action" data-de-action="open-create">
			<?php esc_html_e( 'Add new', 'digitone-events' ); ?>
		</button>
	<?php endif; ?>
	<hr class="wp-header-end">

	<?php DigitOne_Events_Helpers_View::render( 'admin/views/partials/active-event-bar', [ 'active_event_id' => $active_event_id ] ); ?>

	<?php if ( ! $active_event_id ) : ?>
		<p><?php esc_html_e( 'Select an active event to manage its days.', 'digitone-events' ); ?></p>
	<?php else : ?>
		<div class="de-toolbar">
			<button type="button" class="button" data-de-action="bulk-delete" disabled>
				<?php esc_html_e( 'Delete selected', 'digitone-events' ); ?>
			</button>
			<span class="de-toolbar-hint"><?php esc_html_e( 'Drag rows to reorder.', 'digitone-events' ); ?></span>
		</div>

		<table class="wp-list-table widefat fixed striped de-days-table">
			<thead>
				<tr>
					<td class="check-column"><input type="checkbox" class="de-check-all"></td>
					<th class="de-col-drag" aria-label="<?php esc_attr_e( 'Drag handle', 'digitone-events' ); ?>"></th>
					<th><?php esc_html_e( 'Date', 'digitone-events' ); ?></th>
					<th><?php esc_html_e( 'Start', 'digitone-events' ); ?></th>
					<th><?php esc_html_e( 'End', 'digitone-events' ); ?></th>
					<th><?php esc_html_e( 'Label', 'digitone-events' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'digitone-events' ); ?></th>
				</tr>
			</thead>
			<tbody class="de-days-tbody">
				<?php if ( empty( $days ) ) : ?>
					<tr class="de-empty">
						<td colspan="7"><?php esc_html_e( 'No days yet. Add the first day of your event.', 'digitone-events' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $days as $day ) : ?>
						<tr data-id="<?php echo esc_attr( $day['id'] ); ?>">
							<th class="check-column"><input type="checkbox" class="de-row-check" value="<?php echo esc_attr( $day['id'] ); ?>"></th>
							<td class="de-col-drag"><span class="de-drag-handle" aria-hidden="true">⋮⋮</span></td>
							<td><strong><a href="#" data-de-action="edit"><?php echo esc_html( DigitOne_Events_Helpers_Format::date_display( $day['day_date'] ) ); ?></a></strong></td>
							<td><?php echo esc_html( DigitOne_Events_Helpers_Format::time_display( $day['start_time'] ) ); ?></td>
							<td><?php echo esc_html( DigitOne_Events_Helpers_Format::time_display( $day['end_time'] ) ); ?></td>
							<td><?php echo esc_html( $day['label'] ?: '—' ); ?></td>
							<td class="de-row-actions">
								<button type="button" class="button button-small" data-de-action="edit"><?php esc_html_e( 'Edit', 'digitone-events' ); ?></button>
								<button type="button" class="button button-small button-link-delete" data-de-action="delete"><?php esc_html_e( 'Delete', 'digitone-events' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<dialog id="de-day-modal" class="de-modal">
	<form method="dialog" class="de-modal-form" data-de-form="day">
		<header class="de-modal-header">
			<h2 class="de-modal-title"><?php esc_html_e( 'New day', 'digitone-events' ); ?></h2>
			<button type="button" class="de-modal-close" aria-label="<?php esc_attr_e( 'Close', 'digitone-events' ); ?>">&times;</button>
		</header>
		<div class="de-modal-body">
			<input type="hidden" name="id" value="">

			<label class="de-field">
				<span><?php esc_html_e( 'Date', 'digitone-events' ); ?> <em>*</em></span>
				<input type="date" name="day_date" required>
			</label>

			<div class="de-field-row">
				<label class="de-field">
					<span><?php esc_html_e( 'Start time', 'digitone-events' ); ?></span>
					<input type="time" name="start_time">
				</label>
				<label class="de-field">
					<span><?php esc_html_e( 'End time', 'digitone-events' ); ?></span>
					<input type="time" name="end_time">
				</label>
			</div>

			<label class="de-field">
				<span><?php esc_html_e( 'Label', 'digitone-events' ); ?></span>
				<input type="text" name="label" maxlength="255" placeholder="<?php esc_attr_e( 'e.g. Workshop Day, Main Conference', 'digitone-events' ); ?>">
			</label>
		</div>
		<footer class="de-modal-footer">
			<button type="button" class="button" data-de-action="cancel"><?php esc_html_e( 'Cancel', 'digitone-events' ); ?></button>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save day', 'digitone-events' ); ?></button>
		</footer>
	</form>
</dialog>

<div class="de-feedback" role="status" aria-live="polite"></div>

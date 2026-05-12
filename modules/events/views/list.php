<?php
/**
 * Events list view.
 *
 * @var array<int,array<string,mixed>> $events
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

$active_id = DigitOne_Events_Helpers_Event_Context::active_event_id();
?>
<div class="wrap digitone-events-page" data-page="events">

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Events', 'digitone-events' ); ?></h1>
	<button type="button" class="page-title-action" data-de-action="open-create">
		<?php esc_html_e( 'Add new', 'digitone-events' ); ?>
	</button>
	<hr class="wp-header-end">

	<div class="de-toolbar">
		<input type="search" class="de-search" placeholder="<?php esc_attr_e( 'Search events…', 'digitone-events' ); ?>">
		<select class="de-filter-status">
			<option value=""><?php esc_html_e( 'All statuses', 'digitone-events' ); ?></option>
			<option value="draft"><?php esc_html_e( 'Draft', 'digitone-events' ); ?></option>
			<option value="published"><?php esc_html_e( 'Published', 'digitone-events' ); ?></option>
			<option value="archived"><?php esc_html_e( 'Archived', 'digitone-events' ); ?></option>
		</select>
		<button type="button" class="button" data-de-action="bulk-delete" disabled>
			<?php esc_html_e( 'Delete selected', 'digitone-events' ); ?>
		</button>
	</div>

	<table class="wp-list-table widefat fixed striped de-events-table">
		<thead>
			<tr>
				<td class="check-column"><input type="checkbox" class="de-check-all"></td>
				<th><?php esc_html_e( 'Name', 'digitone-events' ); ?></th>
				<th><?php esc_html_e( 'Slug', 'digitone-events' ); ?></th>
				<th><?php esc_html_e( 'Dates', 'digitone-events' ); ?></th>
				<th><?php esc_html_e( 'Status', 'digitone-events' ); ?></th>
				<th><?php esc_html_e( 'Active', 'digitone-events' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'digitone-events' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $events ) ) : ?>
				<tr class="de-empty">
					<td colspan="7">
						<?php esc_html_e( 'No events yet. Create your first event to get started.', 'digitone-events' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $events as $event ) :
					$is_active = ( $event['id'] === $active_id );
					$dates     = '';
					if ( ! empty( $event['start_date'] ) || ! empty( $event['end_date'] ) ) {
						$dates  = DigitOne_Events_Helpers_Format::date_display( $event['start_date'] ?? null );
						if ( ! empty( $event['end_date'] ) ) {
							$dates .= ' – ' . DigitOne_Events_Helpers_Format::date_display( $event['end_date'] );
						}
					}
				?>
					<tr data-id="<?php echo esc_attr( $event['id'] ); ?>" data-status="<?php echo esc_attr( $event['status'] ); ?>">
						<th class="check-column"><input type="checkbox" class="de-row-check" value="<?php echo esc_attr( $event['id'] ); ?>"></th>
						<td>
							<strong><a href="#" data-de-action="edit"><?php echo esc_html( $event['name'] ); ?></a></strong>
						</td>
						<td><code><?php echo esc_html( $event['slug'] ); ?></code></td>
						<td><?php echo esc_html( $dates ); ?></td>
						<td>
							<span class="de-badge de-badge-<?php echo esc_attr( $event['status'] ); ?>">
								<?php echo esc_html( ucfirst( $event['status'] ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( $is_active ) : ?>
								<span class="de-badge de-badge-active">★ <?php esc_html_e( 'Active', 'digitone-events' ); ?></span>
							<?php else : ?>
								<button type="button" class="button-link" data-de-action="set-active"><?php esc_html_e( 'Make active', 'digitone-events' ); ?></button>
							<?php endif; ?>
						</td>
						<td class="de-row-actions">
							<button type="button" class="button button-small" data-de-action="edit"><?php esc_html_e( 'Edit', 'digitone-events' ); ?></button>
							<button type="button" class="button button-small button-link-delete" data-de-action="delete"><?php esc_html_e( 'Delete', 'digitone-events' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<!-- Create/Edit modal -->
<dialog id="de-event-modal" class="de-modal">
	<form method="dialog" class="de-modal-form" data-de-form="event">
		<header class="de-modal-header">
			<h2 class="de-modal-title"><?php esc_html_e( 'New event', 'digitone-events' ); ?></h2>
			<button type="button" class="de-modal-close" aria-label="<?php esc_attr_e( 'Close', 'digitone-events' ); ?>">&times;</button>
		</header>
		<div class="de-modal-body">
			<input type="hidden" name="id" value="">

			<label class="de-field">
				<span><?php esc_html_e( 'Name', 'digitone-events' ); ?> <em>*</em></span>
				<input type="text" name="name" required maxlength="255">
			</label>

			<label class="de-field">
				<span><?php esc_html_e( 'Slug', 'digitone-events' ); ?></span>
				<input type="text" name="slug" maxlength="255" placeholder="<?php esc_attr_e( 'Auto-generated from name', 'digitone-events' ); ?>">
			</label>

			<div class="de-field-row">
				<label class="de-field">
					<span><?php esc_html_e( 'Start date', 'digitone-events' ); ?></span>
					<input type="date" name="start_date">
				</label>
				<label class="de-field">
					<span><?php esc_html_e( 'End date', 'digitone-events' ); ?></span>
					<input type="date" name="end_date">
				</label>
			</div>

			<label class="de-field">
				<span><?php esc_html_e( 'Status', 'digitone-events' ); ?></span>
				<select name="status">
					<option value="draft"><?php esc_html_e( 'Draft', 'digitone-events' ); ?></option>
					<option value="published"><?php esc_html_e( 'Published', 'digitone-events' ); ?></option>
					<option value="archived"><?php esc_html_e( 'Archived', 'digitone-events' ); ?></option>
				</select>
			</label>

			<label class="de-field">
				<span><?php esc_html_e( 'Description', 'digitone-events' ); ?></span>
				<textarea name="description" rows="4"></textarea>
			</label>
		</div>
		<footer class="de-modal-footer">
			<button type="button" class="button" data-de-action="cancel"><?php esc_html_e( 'Cancel', 'digitone-events' ); ?></button>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save event', 'digitone-events' ); ?></button>
		</footer>
	</form>
</dialog>

<div class="de-feedback" role="status" aria-live="polite"></div>

<?php
/**
 * Venues list view.
 *
 * @var array<int,array<string,mixed>> $tree       Primary venues with nested 'sub_venues' key.
 * @var array<int,array<string,mixed>> $primaries  Flat list of primaries (for sub-venue parent selector).
 * @var string|null                    $active_event_id
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap digitone-events-page" data-page="venues" data-active-event="<?php echo esc_attr( $active_event_id ?: '' ); ?>">

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Venues', 'digitone-events' ); ?></h1>
	<?php if ( $active_event_id ) : ?>
		<button type="button" class="page-title-action" data-de-action="open-create"><?php esc_html_e( 'Add new', 'digitone-events' ); ?></button>
	<?php endif; ?>
	<hr class="wp-header-end">

	<?php DigitOne_Events_Helpers_View::render( 'admin/views/partials/active-event-bar', [ 'active_event_id' => $active_event_id ] ); ?>

	<?php if ( ! $active_event_id ) : ?>
		<p><?php esc_html_e( 'Select an active event to manage its venues.', 'digitone-events' ); ?></p>
	<?php else : ?>

		<div class="de-toolbar">
			<button type="button" class="button" data-de-action="bulk-delete" disabled>
				<?php esc_html_e( 'Delete selected', 'digitone-events' ); ?>
			</button>
			<span class="de-toolbar-hint"><?php esc_html_e( 'Deleting a primary venue removes its sub-venues.', 'digitone-events' ); ?></span>
		</div>

		<table class="wp-list-table widefat striped de-venues-table">
			<thead>
				<tr>
					<td class="check-column"><input type="checkbox" class="de-check-all"></td>
					<th><?php esc_html_e( 'Name', 'digitone-events' ); ?></th>
					<th><?php esc_html_e( 'Type', 'digitone-events' ); ?></th>
					<th><?php esc_html_e( 'Address', 'digitone-events' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'digitone-events' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $tree ) ) : ?>
					<tr class="de-empty">
						<td colspan="5"><?php esc_html_e( 'No venues yet. Create your first venue.', 'digitone-events' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $tree as $venue ) : ?>
						<tr data-id="<?php echo esc_attr( $venue['id'] ); ?>" data-type="primary" class="de-venue-primary">
							<th class="check-column"><input type="checkbox" class="de-row-check" value="<?php echo esc_attr( $venue['id'] ); ?>"></th>
							<td><strong><a href="#" data-de-action="edit"><?php echo esc_html( $venue['name'] ); ?></a></strong></td>
							<td><span class="de-badge de-badge-primary"><?php esc_html_e( 'Primary', 'digitone-events' ); ?></span></td>
							<td><?php echo esc_html( $venue['address'] ?: '—' ); ?></td>
							<td class="de-row-actions">
								<button type="button" class="button button-small" data-de-action="add-sub" data-parent="<?php echo esc_attr( $venue['id'] ); ?>">
									<?php esc_html_e( 'Add sub-venue', 'digitone-events' ); ?>
								</button>
								<button type="button" class="button button-small" data-de-action="edit"><?php esc_html_e( 'Edit', 'digitone-events' ); ?></button>
								<button type="button" class="button button-small button-link-delete" data-de-action="delete"><?php esc_html_e( 'Delete', 'digitone-events' ); ?></button>
							</td>
						</tr>
						<?php foreach ( $venue['sub_venues'] as $sub ) : ?>
							<tr data-id="<?php echo esc_attr( $sub['id'] ); ?>" data-type="sub-venue" data-parent="<?php echo esc_attr( $venue['id'] ); ?>" class="de-venue-sub">
								<th class="check-column"><input type="checkbox" class="de-row-check" value="<?php echo esc_attr( $sub['id'] ); ?>"></th>
								<td>
									<span class="de-sub-marker" aria-hidden="true">└──</span>
									<a href="#" data-de-action="edit"><?php echo esc_html( $sub['name'] ); ?></a>
								</td>
								<td><span class="de-badge de-badge-sub"><?php esc_html_e( 'Sub-venue', 'digitone-events' ); ?></span></td>
								<td><?php echo esc_html( $sub['address'] ?: '—' ); ?></td>
								<td class="de-row-actions">
									<button type="button" class="button button-small" data-de-action="edit"><?php esc_html_e( 'Edit', 'digitone-events' ); ?></button>
									<button type="button" class="button button-small button-link-delete" data-de-action="delete"><?php esc_html_e( 'Delete', 'digitone-events' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<dialog id="de-venue-modal" class="de-modal">
	<form method="dialog" class="de-modal-form" data-de-form="venue">
		<header class="de-modal-header">
			<h2 class="de-modal-title"><?php esc_html_e( 'New venue', 'digitone-events' ); ?></h2>
			<button type="button" class="de-modal-close" aria-label="<?php esc_attr_e( 'Close', 'digitone-events' ); ?>">&times;</button>
		</header>
		<div class="de-modal-body">
			<input type="hidden" name="id" value="">

			<label class="de-field">
				<span><?php esc_html_e( 'Type', 'digitone-events' ); ?> <em>*</em></span>
				<select name="venue_type" class="de-venue-type-select">
					<option value="primary"><?php esc_html_e( 'Primary venue', 'digitone-events' ); ?></option>
					<option value="sub-venue"><?php esc_html_e( 'Sub-venue (room/hall)', 'digitone-events' ); ?></option>
				</select>
			</label>

			<label class="de-field de-parent-field" style="display:none">
				<span><?php esc_html_e( 'Parent venue', 'digitone-events' ); ?> <em>*</em></span>
				<select name="parent_id">
					<option value=""><?php esc_html_e( '— select parent —', 'digitone-events' ); ?></option>
					<?php foreach ( $primaries as $p ) : ?>
						<option value="<?php echo esc_attr( $p['id'] ); ?>"><?php echo esc_html( $p['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>

			<label class="de-field">
				<span><?php esc_html_e( 'Name', 'digitone-events' ); ?> <em>*</em></span>
				<input type="text" name="name" required maxlength="255">
			</label>

			<label class="de-field">
				<span><?php esc_html_e( 'Address', 'digitone-events' ); ?></span>
				<textarea name="address" rows="3"></textarea>
			</label>
		</div>
		<footer class="de-modal-footer">
			<button type="button" class="button" data-de-action="cancel"><?php esc_html_e( 'Cancel', 'digitone-events' ); ?></button>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save venue', 'digitone-events' ); ?></button>
		</footer>
	</form>
</dialog>

<div class="de-feedback" role="status" aria-live="polite"></div>

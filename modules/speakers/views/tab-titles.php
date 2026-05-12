<?php
/**
 * Titles tab.
 *
 * @var array<int,array<string,mixed>> $titles
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="de-tab-section de-tab-section-narrow">
	<p class="description"><?php esc_html_e( 'Titles prefixed before a speaker\'s name (e.g. Dr., Prof., Mr.). Optional.', 'digitone-events' ); ?></p>

	<form class="de-inline-add" data-de-form="title-quick-add">
		<input type="text" name="name" placeholder="<?php esc_attr_e( 'New title (e.g. Dr.)', 'digitone-events' ); ?>" maxlength="100" required>
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Add', 'digitone-events' ); ?></button>
	</form>

	<table class="wp-list-table widefat striped de-titles-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'digitone-events' ); ?></th>
				<th class="de-col-actions"><?php esc_html_e( 'Actions', 'digitone-events' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $titles ) ) : ?>
				<tr class="de-empty">
					<td colspan="2"><?php esc_html_e( 'No titles yet.', 'digitone-events' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $titles as $t ) : ?>
					<tr data-id="<?php echo esc_attr( $t['id'] ); ?>" data-name="<?php echo esc_attr( $t['name'] ); ?>">
						<td class="de-edit-cell">
							<span class="de-display-name"><?php echo esc_html( $t['name'] ); ?></span>
							<input type="text" class="de-edit-input" value="<?php echo esc_attr( $t['name'] ); ?>" hidden>
						</td>
						<td class="de-row-actions">
							<button type="button" class="button button-small" data-de-action="edit-inline"><?php esc_html_e( 'Edit', 'digitone-events' ); ?></button>
							<button type="button" class="button button-small de-save-btn" data-de-action="save-inline" hidden><?php esc_html_e( 'Save', 'digitone-events' ); ?></button>
							<button type="button" class="button button-small de-cancel-btn" data-de-action="cancel-inline" hidden><?php esc_html_e( 'Cancel', 'digitone-events' ); ?></button>
							<button type="button" class="button button-small button-link-delete" data-de-action="delete-title"><?php esc_html_e( 'Delete', 'digitone-events' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

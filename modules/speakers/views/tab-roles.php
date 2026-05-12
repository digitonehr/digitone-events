<?php
/**
 * Roles tab.
 *
 * @var array<int,array<string,mixed>> $roles
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="de-tab-section de-tab-section-narrow">
	<p class="description"><?php esc_html_e( 'Roles assigned to speakers (e.g. Keynote Speaker, Moderator, Panelist). Color is used in lists for quick scanning.', 'digitone-events' ); ?></p>

	<form class="de-inline-add" data-de-form="role-quick-add">
		<input type="text" name="name" placeholder="<?php esc_attr_e( 'New role (e.g. Moderator)', 'digitone-events' ); ?>" maxlength="100" required>
		<input type="color" name="color" value="#2271b1" title="<?php esc_attr_e( 'Color', 'digitone-events' ); ?>">
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Add', 'digitone-events' ); ?></button>
	</form>

	<table class="wp-list-table widefat striped de-roles-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'digitone-events' ); ?></th>
				<th class="de-col-color"><?php esc_html_e( 'Color', 'digitone-events' ); ?></th>
				<th class="de-col-actions"><?php esc_html_e( 'Actions', 'digitone-events' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $roles ) ) : ?>
				<tr class="de-empty">
					<td colspan="3"><?php esc_html_e( 'No roles yet.', 'digitone-events' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $roles as $r ) :
					$color = $r['color'] ?: '#cccccc';
				?>
					<tr data-id="<?php echo esc_attr( $r['id'] ); ?>" data-name="<?php echo esc_attr( $r['name'] ); ?>" data-color="<?php echo esc_attr( $color ); ?>">
						<td class="de-edit-cell">
							<span class="de-role-badge" style="background:<?php echo esc_attr( $color ); ?>;color:#fff" data-display-badge><?php echo esc_html( $r['name'] ); ?></span>
							<input type="text" class="de-edit-input" value="<?php echo esc_attr( $r['name'] ); ?>" hidden>
						</td>
						<td>
							<span class="de-color-swatch" style="background:<?php echo esc_attr( $color ); ?>" data-display-swatch></span>
							<input type="color" class="de-edit-color" value="<?php echo esc_attr( $color ); ?>" hidden>
						</td>
						<td class="de-row-actions">
							<button type="button" class="button button-small" data-de-action="edit-inline"><?php esc_html_e( 'Edit', 'digitone-events' ); ?></button>
							<button type="button" class="button button-small de-save-btn" data-de-action="save-inline" hidden><?php esc_html_e( 'Save', 'digitone-events' ); ?></button>
							<button type="button" class="button button-small de-cancel-btn" data-de-action="cancel-inline" hidden><?php esc_html_e( 'Cancel', 'digitone-events' ); ?></button>
							<button type="button" class="button button-small button-link-delete" data-de-action="delete-role"><?php esc_html_e( 'Delete', 'digitone-events' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

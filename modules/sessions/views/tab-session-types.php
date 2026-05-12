<?php
/**
 * Session Types tab.
 *
 * @var array<int,array<string,mixed>> $session_types
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="de-tab-section de-tab-section-narrow">
	<p class="description"><?php esc_html_e( 'Session types categorize sessions in the agenda (e.g. Workshop, Keynote, Panel, Break). Each type can have an icon (emoji works) and a color.', 'digitone-events' ); ?></p>

	<form class="de-inline-add" data-de-form="session-type-quick-add">
		<input type="text" name="name" placeholder="<?php esc_attr_e( 'Type name (e.g. Workshop)', 'digitone-events' ); ?>" maxlength="100" required>
		<input type="text" name="icon" placeholder="<?php esc_attr_e( 'Icon (emoji or short text)', 'digitone-events' ); ?>" maxlength="10" class="de-icon-input">
		<input type="color" name="color" value="#2271b1" title="<?php esc_attr_e( 'Color', 'digitone-events' ); ?>">
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Add', 'digitone-events' ); ?></button>
	</form>

	<table class="wp-list-table widefat striped de-session-types-table">
		<thead>
			<tr>
				<th class="de-col-icon"><?php esc_html_e( 'Icon', 'digitone-events' ); ?></th>
				<th><?php esc_html_e( 'Name', 'digitone-events' ); ?></th>
				<th class="de-col-color"><?php esc_html_e( 'Color', 'digitone-events' ); ?></th>
				<th class="de-col-actions"><?php esc_html_e( 'Actions', 'digitone-events' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $session_types ) ) : ?>
				<tr class="de-empty">
					<td colspan="4"><?php esc_html_e( 'No session types yet.', 'digitone-events' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $session_types as $st ) :
					$color = $st['color'] ?: '#cccccc';
				?>
					<tr data-id="<?php echo esc_attr( $st['id'] ); ?>"
					    data-name="<?php echo esc_attr( $st['name'] ); ?>"
					    data-icon="<?php echo esc_attr( $st['icon'] ?? '' ); ?>"
					    data-color="<?php echo esc_attr( $color ); ?>">
						<td>
							<span class="de-icon-display" data-display-icon><?php echo esc_html( $st['icon'] ?: '—' ); ?></span>
							<input type="text" class="de-edit-icon" value="<?php echo esc_attr( $st['icon'] ?? '' ); ?>" maxlength="10" hidden>
						</td>
						<td>
							<span class="de-type-badge" style="background:<?php echo esc_attr( $color ); ?>;color:#fff" data-display-badge>
								<?php if ( ! empty( $st['icon'] ) ) echo esc_html( $st['icon'] ) . ' '; ?>
								<?php echo esc_html( $st['name'] ); ?>
							</span>
							<input type="text" class="de-edit-input" value="<?php echo esc_attr( $st['name'] ); ?>" hidden>
						</td>
						<td>
							<span class="de-color-swatch" style="background:<?php echo esc_attr( $color ); ?>" data-display-swatch></span>
							<input type="color" class="de-edit-color" value="<?php echo esc_attr( $color ); ?>" hidden>
						</td>
						<td class="de-row-actions">
							<button type="button" class="button button-small" data-de-action="edit-inline"><?php esc_html_e( 'Edit', 'digitone-events' ); ?></button>
							<button type="button" class="button button-small de-save-btn" data-de-action="save-inline" hidden><?php esc_html_e( 'Save', 'digitone-events' ); ?></button>
							<button type="button" class="button button-small de-cancel-btn" data-de-action="cancel-inline" hidden><?php esc_html_e( 'Cancel', 'digitone-events' ); ?></button>
							<button type="button" class="button button-small button-link-delete" data-de-action="delete-session-type"><?php esc_html_e( 'Delete', 'digitone-events' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

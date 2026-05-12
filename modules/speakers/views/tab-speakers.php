<?php
/**
 * Speakers list tab.
 *
 * @var array<int,array<string,mixed>> $speakers
 * @var array<int,array<string,mixed>> $titles
 * @var array<int,array<string,mixed>> $roles
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="de-tab-section">
	<div class="de-section-header">
		<button type="button" class="button button-primary" data-de-action="open-create-speaker">
			<?php esc_html_e( '+ Add speaker', 'digitone-events' ); ?>
		</button>
		<input type="search" class="de-search" placeholder="<?php esc_attr_e( 'Search speakers…', 'digitone-events' ); ?>">
		<button type="button" class="button" data-de-action="bulk-delete-speakers" disabled>
			<?php esc_html_e( 'Delete selected', 'digitone-events' ); ?>
		</button>
	</div>

	<table class="wp-list-table widefat fixed striped de-speakers-table">
		<thead>
			<tr>
				<td class="check-column"><input type="checkbox" class="de-check-all"></td>
				<th class="de-col-photo"><?php esc_html_e( 'Photo', 'digitone-events' ); ?></th>
				<th><?php esc_html_e( 'Name', 'digitone-events' ); ?></th>
				<th><?php esc_html_e( 'Roles', 'digitone-events' ); ?></th>
				<th><?php esc_html_e( 'Email', 'digitone-events' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'digitone-events' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $speakers ) ) : ?>
				<tr class="de-empty">
					<td colspan="6"><?php esc_html_e( 'No speakers yet. Add the first one.', 'digitone-events' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $speakers as $sp ) :
					$full_name = trim( ( $sp['title_name'] ?? '' ) . ' ' . $sp['first_name'] . ' ' . $sp['last_name'] );
				?>
					<tr data-id="<?php echo esc_attr( $sp['id'] ); ?>">
						<th class="check-column"><input type="checkbox" class="de-row-check" value="<?php echo esc_attr( $sp['id'] ); ?>"></th>
						<td class="de-col-photo">
							<?php if ( ! empty( $sp['photo_url'] ) ) : ?>
								<img src="<?php echo esc_url( $sp['photo_url'] ); ?>" alt="" class="de-speaker-thumb">
							<?php else : ?>
								<span class="de-speaker-thumb de-speaker-thumb-empty" aria-hidden="true">👤</span>
							<?php endif; ?>
						</td>
						<td><strong><a href="#" data-de-action="edit-speaker"><?php echo esc_html( $full_name ); ?></a></strong></td>
						<td>
							<?php if ( ! empty( $sp['roles'] ) ) : ?>
								<?php foreach ( $sp['roles'] as $r ) :
									$style = ! empty( $r['color'] ) ? 'background:' . esc_attr( $r['color'] ) . ';color:#fff;' : '';
								?>
									<span class="de-role-badge" style="<?php echo $style; ?>"><?php echo esc_html( $r['name'] ); ?></span>
								<?php endforeach; ?>
							<?php else : ?>
								<span class="de-muted">—</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! empty( $sp['email'] ) ) : ?>
								<a href="mailto:<?php echo esc_attr( $sp['email'] ); ?>"><?php echo esc_html( $sp['email'] ); ?></a>
							<?php else : ?>
								<span class="de-muted">—</span>
							<?php endif; ?>
						</td>
						<td class="de-row-actions">
							<button type="button" class="button button-small" data-de-action="edit-speaker"><?php esc_html_e( 'Edit', 'digitone-events' ); ?></button>
							<button type="button" class="button button-small button-link-delete" data-de-action="delete-speaker"><?php esc_html_e( 'Delete', 'digitone-events' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<!-- Speaker modal -->
<dialog id="de-speaker-modal" class="de-modal de-modal-wide">
	<form method="dialog" class="de-modal-form" data-de-form="speaker">
		<header class="de-modal-header">
			<h2 class="de-modal-title"><?php esc_html_e( 'New speaker', 'digitone-events' ); ?></h2>
			<button type="button" class="de-modal-close" aria-label="<?php esc_attr_e( 'Close', 'digitone-events' ); ?>">&times;</button>
		</header>
		<div class="de-modal-body">
			<input type="hidden" name="id" value="">

			<div class="de-field-row">
				<label class="de-field">
					<span><?php esc_html_e( 'Title', 'digitone-events' ); ?></span>
					<select name="title_id">
						<option value=""><?php esc_html_e( '— none —', 'digitone-events' ); ?></option>
						<?php foreach ( $titles as $t ) : ?>
							<option value="<?php echo esc_attr( $t['id'] ); ?>"><?php echo esc_html( $t['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="de-field">
					<span><?php esc_html_e( 'First name', 'digitone-events' ); ?></span>
					<input type="text" name="first_name" maxlength="100">
				</label>
				<label class="de-field">
					<span><?php esc_html_e( 'Last name', 'digitone-events' ); ?></span>
					<input type="text" name="last_name" maxlength="100">
				</label>
			</div>

			<label class="de-field">
				<span><?php esc_html_e( 'Email', 'digitone-events' ); ?></span>
				<input type="email" name="email" maxlength="190">
			</label>

			<div class="de-field">
				<span><?php esc_html_e( 'Photo', 'digitone-events' ); ?></span>
				<div class="de-photo-picker">
					<input type="url" name="photo_url" class="de-photo-url" placeholder="https://…">
					<button type="button" class="button" data-de-action="pick-photo"><?php esc_html_e( 'Choose from library', 'digitone-events' ); ?></button>
					<button type="button" class="button-link" data-de-action="clear-photo"><?php esc_html_e( 'Remove', 'digitone-events' ); ?></button>
				</div>
				<div class="de-photo-preview" aria-hidden="true"></div>
			</div>

			<div class="de-field">
				<span><?php esc_html_e( 'Roles', 'digitone-events' ); ?></span>
				<div class="de-roles-checkboxes">
					<?php if ( empty( $roles ) ) : ?>
						<p class="de-muted"><?php esc_html_e( 'No roles defined yet. Add some on the Roles tab.', 'digitone-events' ); ?></p>
					<?php else : ?>
						<?php foreach ( $roles as $r ) : ?>
							<label class="de-role-checkbox">
								<input type="checkbox" name="role_ids[]" value="<?php echo esc_attr( $r['id'] ); ?>">
								<span><?php echo esc_html( $r['name'] ); ?></span>
							</label>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<label class="de-field">
				<span><?php esc_html_e( 'Bio', 'digitone-events' ); ?></span>
				<textarea name="bio" rows="5"></textarea>
			</label>
		</div>
		<footer class="de-modal-footer">
			<button type="button" class="button" data-de-action="cancel-speaker"><?php esc_html_e( 'Cancel', 'digitone-events' ); ?></button>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save speaker', 'digitone-events' ); ?></button>
		</footer>
	</form>
</dialog>

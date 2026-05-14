<?php
/**
 * Sessions tab — main list of sessions for selected day.
 *
 * @var array<int,array<string,mixed>> $days
 * @var string                         $day_id
 * @var array<int,array<string,mixed>> $sessions
 * @var array<int,array<string,mixed>> $venues_tree   Primary venues each with 'sub_venues'.
 * @var array<int,array<string,mixed>> $speakers
 * @var array<int,array<string,mixed>> $roles
 * @var array<int,array<string,mixed>> $session_types
 * @var string                         $base_url
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

// Flatten venues for the venue dropdown.
$primary_venues = [];
$sub_venues_by_parent = [];
foreach ( $venues_tree as $v ) {
	$primary_venues[] = [ 'id' => $v['id'], 'name' => $v['name'] ];
	if ( ! empty( $v['sub_venues'] ) ) {
		foreach ( $v['sub_venues'] as $sub ) {
			$sub_venues_by_parent[ $v['id'] ][] = [ 'id' => $sub['id'], 'name' => $sub['name'] ];
		}
	}
}
?>
<div class="de-tab-section">

	<?php if ( empty( $days ) ) : ?>
		<p class="notice notice-warning inline" style="padding:10px"><?php
			printf(
				/* translators: %s: link to Days admin page */
				esc_html__( 'No days defined for this event yet. %s before adding sessions.', 'digitone-events' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=digitone-events-days' ) ) . '">' . esc_html__( 'Create some days first', 'digitone-events' ) . '</a>'
			);
		?></p>
	<?php else : ?>

		<div class="de-section-header">
			<label class="de-day-picker-label">
				<span><?php esc_html_e( 'Day:', 'digitone-events' ); ?></span>
				<select class="de-day-picker" id="de-day-picker">
					<?php foreach ( $days as $d ) :
						$lbl = DigitOne_Events_Helpers_Format::date_display( $d['day_date'] );
						if ( ! empty( $d['label'] ) ) $lbl .= ' — ' . $d['label'];
					?>
						<option value="<?php echo esc_attr( $d['id'] ); ?>" <?php selected( $d['id'], $day_id ); ?>><?php echo esc_html( $lbl ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>

			<button type="button" class="button button-primary" data-de-action="open-create-session">
				<?php esc_html_e( '+ Add session', 'digitone-events' ); ?>
			</button>
			<button type="button" class="button" data-de-action="bulk-delete-sessions" disabled>
				<?php esc_html_e( 'Delete selected', 'digitone-events' ); ?>
			</button>
		</div>

		<table class="wp-list-table widefat striped de-sessions-table">
			<thead>
				<tr>
					<td class="check-column"><input type="checkbox" class="de-check-all"></td>
					<th class="de-col-time"><?php esc_html_e( 'Time', 'digitone-events' ); ?></th>
					<th><?php esc_html_e( 'Title', 'digitone-events' ); ?></th>
					<th><?php esc_html_e( 'Type', 'digitone-events' ); ?></th>
					<th><?php esc_html_e( 'Venue', 'digitone-events' ); ?></th>
					<th><?php esc_html_e( 'Speakers', 'digitone-events' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'digitone-events' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $sessions ) ) : ?>
					<tr class="de-empty">
						<td colspan="7"><?php esc_html_e( 'No sessions for this day yet.', 'digitone-events' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $sessions as $s ) :
						$time = '';
						if ( ! empty( $s['start_time'] ) ) {
							$time = DigitOne_Events_Helpers_Format::time_display( $s['start_time'] );
							if ( ! empty( $s['end_time'] ) ) {
								$time .= ' – ' . DigitOne_Events_Helpers_Format::time_display( $s['end_time'] );
							}
						}
						$venue = $s['venue_name'] ?? '';
						if ( ! empty( $s['sub_venue_name'] ) ) {
							$venue = $venue ? $venue . ' / ' . $s['sub_venue_name'] : $s['sub_venue_name'];
						}
						$type_style = ! empty( $s['type_color'] ) ? 'background:' . esc_attr( $s['type_color'] ) . ';color:#fff' : '';
					?>
						<tr data-id="<?php echo esc_attr( $s['id'] ); ?>" data-level="<?php echo esc_attr( $s['session_level'] ); ?>">
							<th class="check-column"><input type="checkbox" class="de-row-check" value="<?php echo esc_attr( $s['id'] ); ?>"></th>
							<td class="de-col-time"><?php echo esc_html( $time ?: '—' ); ?></td>
							<td>
								<?php if ( $s['session_level'] === 'child' ) : ?>
									<span class="de-sub-marker" aria-hidden="true">└─</span>
								<?php endif; ?>
								<strong><a href="#" data-de-action="edit-session"><?php echo esc_html( $s['title'] ); ?></a></strong>
							</td>
							<td>
								<?php if ( ! empty( $s['type_name'] ) ) : ?>
									<span class="de-type-badge" style="<?php echo $type_style; ?>">
										<?php if ( ! empty( $s['type_icon'] ) ) echo esc_html( $s['type_icon'] ) . ' '; ?>
										<?php echo esc_html( $s['type_name'] ); ?>
									</span>
								<?php else : ?>
									<span class="de-muted">—</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $venue ?: '—' ); ?></td>
							<td>
								<?php if ( ! empty( $s['speakers'] ) ) : ?>
									<div class="de-session-speakers">
										<?php foreach ( $s['speakers'] as $sp ) :
											$name = trim( $sp['first_name'] . ' ' . $sp['last_name'] );
											$role_style = ! empty( $sp['role_color'] ) ? 'background:' . esc_attr( $sp['role_color'] ) . ';color:#fff' : '';
										?>
											<span class="de-session-speaker"><?php echo esc_html( $name ); ?>
												<?php if ( ! empty( $sp['role_name'] ) ) : ?>
													<span class="de-role-badge de-role-badge-mini" style="<?php echo $role_style; ?>"><?php echo esc_html( $sp['role_name'] ); ?></span>
												<?php endif; ?>
											</span>
										<?php endforeach; ?>
									</div>
								<?php else : ?>
									<span class="de-muted">—</span>
								<?php endif; ?>
							</td>
							<td class="de-row-actions">
								<button type="button" class="button button-small" data-de-action="edit-session"><?php esc_html_e( 'Edit', 'digitone-events' ); ?></button>
								<button type="button" class="button button-small button-link-delete" data-de-action="delete-session"><?php esc_html_e( 'Delete', 'digitone-events' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<!-- Session modal -->
<dialog id="de-session-modal" class="de-modal de-modal-wide">
	<form method="dialog" class="de-modal-form" data-de-form="session"
		data-sub-venues='<?php echo esc_attr( wp_json_encode( $sub_venues_by_parent ) ); ?>'>
		<header class="de-modal-header">
			<h2 class="de-modal-title"><?php esc_html_e( 'New session', 'digitone-events' ); ?></h2>
			<button type="button" class="de-modal-close" aria-label="<?php esc_attr_e( 'Close', 'digitone-events' ); ?>">&times;</button>
		</header>
		<div class="de-modal-body">
			<input type="hidden" name="id" value="">

			<div class="de-field-row">
				<label class="de-field">
					<span><?php esc_html_e( 'Day', 'digitone-events' ); ?> <em>*</em></span>
					<select name="day_id" required>
						<?php foreach ( $days as $d ) :
							$lbl = DigitOne_Events_Helpers_Format::date_display( $d['day_date'] );
							if ( ! empty( $d['label'] ) ) $lbl .= ' — ' . $d['label'];
						?>
							<option value="<?php echo esc_attr( $d['id'] ); ?>"><?php echo esc_html( $lbl ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
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
				<span><?php esc_html_e( 'Title', 'digitone-events' ); ?> <em>*</em></span>
				<input type="text" name="title" required maxlength="255">
			</label>

			<div class="de-field-row">
				<label class="de-field">
					<span><?php esc_html_e( 'Type', 'digitone-events' ); ?></span>
					<select name="session_type_id">
						<option value=""><?php esc_html_e( '— none —', 'digitone-events' ); ?></option>
						<?php foreach ( $session_types as $st ) : ?>
							<option value="<?php echo esc_attr( $st['id'] ); ?>"><?php echo esc_html( $st['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="de-field">
					<span><?php esc_html_e( 'Venue', 'digitone-events' ); ?></span>
					<select name="venue_id" class="de-venue-select">
						<option value=""><?php esc_html_e( '— none —', 'digitone-events' ); ?></option>
						<?php foreach ( $primary_venues as $v ) : ?>
							<option value="<?php echo esc_attr( $v['id'] ); ?>"><?php echo esc_html( $v['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="de-field">
					<span><?php esc_html_e( 'Sub-venue', 'digitone-events' ); ?></span>
					<select name="sub_venue_id" class="de-sub-venue-select" disabled>
						<option value=""><?php esc_html_e( '— pick a venue first —', 'digitone-events' ); ?></option>
					</select>
				</label>
			</div>

			<div class="de-field">
				<span><?php esc_html_e( 'Speakers', 'digitone-events' ); ?></span>
				<?php if ( empty( $speakers ) ) : ?>
					<p class="de-muted"><?php esc_html_e( 'No speakers yet. Add some on the Speakers page.', 'digitone-events' ); ?></p>
				<?php else : ?>
					<div class="de-speakers-autocomplete" data-de-speakers-ac>
						<div class="de-speakers-chips" data-de-chips></div>
						<div class="de-speakers-input-wrap">
							<input
								type="text"
								class="de-speakers-input"
								data-de-speakers-input
								placeholder="<?php esc_attr_e( 'Type a name to find speakers…', 'digitone-events' ); ?>"
								autocomplete="off">
							<ul class="de-speakers-dropdown" data-de-dropdown role="listbox" hidden></ul>
						</div>
						<?php
						// Embed speakers as JSON for client-side filtering — lowercased
						// once here so the autocomplete loop is a substring check only.
						$speaker_index = [];
						foreach ( $speakers as $sp ) {
							$first      = (string) ( $sp['first_name'] ?? '' );
							$last       = (string) ( $sp['last_name']  ?? '' );
							$title_name = (string) ( $sp['title_name'] ?? '' );
							$name       = trim( $title_name . ' ' . $first . ' ' . $last );
							$speaker_index[] = [
								'id'         => (string) $sp['id'],
								'name'       => $name,
								'search'     => mb_strtolower(
									$first . ' ' . $last . ' ' . $last . ' ' . $first . ' ' . $title_name,
									'UTF-8'
								),
							];
						}
						?>
						<script type="application/json" data-de-speakers-data><?php echo wp_json_encode( $speaker_index ); ?></script>
					</div>
				<?php endif; ?>
			</div>

			<label class="de-field">
				<span><?php esc_html_e( 'Role for selected speakers', 'digitone-events' ); ?></span>
				<select name="default_role_id">
					<option value=""><?php esc_html_e( '— no role —', 'digitone-events' ); ?></option>
					<?php foreach ( $roles as $r ) : ?>
						<option value="<?php echo esc_attr( $r['id'] ); ?>"><?php echo esc_html( $r['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<small class="description"><?php esc_html_e( 'Applied to all selected speakers (each can be changed later by editing the session).', 'digitone-events' ); ?></small>
			</label>

			<div class="de-field-row">
				<label class="de-field">
					<span><?php esc_html_e( 'Session level', 'digitone-events' ); ?></span>
					<select name="session_level" class="de-level-select">
						<option value="master"><?php esc_html_e( 'Master (standalone or parent)', 'digitone-events' ); ?></option>
						<option value="child"><?php esc_html_e( 'Child of another session', 'digitone-events' ); ?></option>
					</select>
				</label>
				<label class="de-field de-parent-field" style="display:none">
					<span><?php esc_html_e( 'Parent session', 'digitone-events' ); ?></span>
					<select name="parent_id" class="de-parent-select">
						<option value=""><?php esc_html_e( '— select parent —', 'digitone-events' ); ?></option>
					</select>
					<small class="description"><?php esc_html_e( 'Loaded from sessions on the selected day.', 'digitone-events' ); ?></small>
				</label>
			</div>

			<label class="de-field">
				<span><?php esc_html_e( 'Description', 'digitone-events' ); ?></span>
				<textarea name="description" rows="4"></textarea>
			</label>
		</div>
		<footer class="de-modal-footer">
			<button type="button" class="button" data-de-action="cancel-session"><?php esc_html_e( 'Cancel', 'digitone-events' ); ?></button>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save session', 'digitone-events' ); ?></button>
		</footer>
	</form>
</dialog>

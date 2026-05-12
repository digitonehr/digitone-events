<?php
/**
 * Active event bar — appears at top of module pages.
 *
 * @var string|null $active_event_id
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

$events_repo  = DigitOne_Events_Plugin::instance()->module( 'events' )->repo();
$all_events   = $events_repo->all();
$active_event = $active_event_id ? $events_repo->find( $active_event_id ) : null;
?>
<div class="de-active-event-bar">
	<?php if ( $active_event ) : ?>
		<div class="de-active-event-info">
			<span class="de-active-event-label"><?php esc_html_e( 'Active event:', 'digitone-events' ); ?></span>
			<strong class="de-active-event-name"><?php echo esc_html( $active_event['name'] ); ?></strong>
			<span class="de-badge de-badge-<?php echo esc_attr( $active_event['status'] ); ?>">
				<?php echo esc_html( ucfirst( $active_event['status'] ) ); ?>
			</span>
		</div>
		<?php if ( count( $all_events ) > 1 ) : ?>
			<div class="de-active-event-switch">
				<label for="de-active-event-select" class="screen-reader-text"><?php esc_html_e( 'Switch active event', 'digitone-events' ); ?></label>
				<select id="de-active-event-select" class="de-active-event-select">
					<?php foreach ( $all_events as $ev ) : ?>
						<option value="<?php echo esc_attr( $ev['id'] ); ?>" <?php selected( $ev['id'], $active_event_id ); ?>>
							<?php echo esc_html( $ev['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="de-active-event-info de-active-event-empty">
			<span class="dashicons dashicons-warning"></span>
			<strong><?php esc_html_e( 'No active event selected.', 'digitone-events' ); ?></strong>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=digitone-events-events' ) ); ?>">
				<?php esc_html_e( 'Go to Events', 'digitone-events' ); ?>
			</a>
		</div>
	<?php endif; ?>
</div>

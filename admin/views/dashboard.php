<?php
/**
 * Dashboard view.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

$repo        = DigitOne_Events_Plugin::instance()->module( 'events' )->repo();
$event_count = $repo->count_all();
$active_id   = DigitOne_Events_Helpers_Event_Context::active_event_id();
$active      = $active_id ? $repo->find( $active_id ) : null;
?>
<div class="wrap digitone-events-dashboard">
	<h1><?php esc_html_e( 'DigitOne Events', 'digitone-events' ); ?>
		<small class="de-version-badge">v<?php echo esc_html( DIGITONE_EVENTS_VERSION ); ?></small>
	</h1>

	<div class="de-dashboard-grid">
		<div class="de-stat-card">
			<div class="de-stat-num"><?php echo esc_html( $event_count ); ?></div>
			<div class="de-stat-label"><?php esc_html_e( 'Total events', 'digitone-events' ); ?></div>
		</div>

		<div class="de-stat-card de-stat-card-wide">
			<div class="de-stat-label"><?php esc_html_e( 'Active event', 'digitone-events' ); ?></div>
			<?php if ( $active ) : ?>
				<div class="de-stat-num-small"><?php echo esc_html( $active['name'] ); ?></div>
				<div class="de-stat-sub">
					<span class="de-badge de-badge-<?php echo esc_attr( $active['status'] ); ?>">
						<?php echo esc_html( ucfirst( $active['status'] ) ); ?>
					</span>
				</div>
			<?php else : ?>
				<div class="de-stat-sub"><?php esc_html_e( 'No event selected.', 'digitone-events' ); ?></div>
			<?php endif; ?>
		</div>
	</div>

	<div class="de-quick-links">
		<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=digitone-events-events' ) ); ?>">
			<?php esc_html_e( 'Manage events', 'digitone-events' ); ?>
		</a>
		<a class="button button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=digitone-events-settings' ) ); ?>">
			<?php esc_html_e( 'Plugin settings', 'digitone-events' ); ?>
		</a>
	</div>

	<div class="notice notice-info inline">
		<p>
			<strong><?php esc_html_e( 'Phase 1', 'digitone-events' ); ?>:</strong>
			<?php esc_html_e( 'Events module is fully migrated. Days, Venues, Speakers, Sessions, Session Types and Export/Import are coming in upcoming phases.', 'digitone-events' ); ?>
		</p>
	</div>
</div>

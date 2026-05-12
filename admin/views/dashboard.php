<?php
/**
 * Dashboard view.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

$plugin       = DigitOne_Events_Plugin::instance();
$events_repo  = $plugin->module( 'events' )->repo();
$days_module     = $plugin->module( 'days' );
$venues_module   = $plugin->module( 'venues' );
$speakers_module = $plugin->module( 'speakers' );
$sessions_module = $plugin->module( 'sessions' );

$event_count   = $events_repo->count_all();
$day_count     = $days_module     ? $days_module->repo()->count_all()     : 0;
$venue_count   = $venues_module   ? $venues_module->repo()->count_all()   : 0;
$speaker_count = $speakers_module ? $speakers_module->repo()->count_all() : 0;
$session_count = $sessions_module ? $sessions_module->repo()->count_all() : 0;

$active_id = DigitOne_Events_Helpers_Event_Context::active_event_id();
$active    = $active_id ? $events_repo->find( $active_id ) : null;

$active_day_count   = ( $active_id && $days_module )   ? $days_module->repo()->count_for_event( $active_id )   : 0;
$active_venue_count = ( $active_id && $venues_module ) ? $venues_module->repo()->count_for_event( $active_id ) : 0;
?>
<div class="wrap digitone-events-dashboard">
	<h1><?php esc_html_e( 'DigitOne Events', 'digitone-events' ); ?>
		<small class="de-version-badge">v<?php echo esc_html( DIGITONE_EVENTS_VERSION ); ?></small>
	</h1>

	<div class="de-dashboard-grid">
		<div class="de-stat-card">
			<div class="de-stat-num"><?php echo esc_html( $event_count ); ?></div>
			<div class="de-stat-label"><?php esc_html_e( 'Events', 'digitone-events' ); ?></div>
			<a class="de-stat-link" href="<?php echo esc_url( admin_url( 'admin.php?page=digitone-events-events' ) ); ?>"><?php esc_html_e( 'Manage →', 'digitone-events' ); ?></a>
		</div>

		<div class="de-stat-card">
			<div class="de-stat-num"><?php echo esc_html( $day_count ); ?></div>
			<div class="de-stat-label"><?php esc_html_e( 'Days', 'digitone-events' ); ?></div>
			<a class="de-stat-link" href="<?php echo esc_url( admin_url( 'admin.php?page=digitone-events-days' ) ); ?>"><?php esc_html_e( 'Manage →', 'digitone-events' ); ?></a>
		</div>

		<div class="de-stat-card">
			<div class="de-stat-num"><?php echo esc_html( $venue_count ); ?></div>
			<div class="de-stat-label"><?php esc_html_e( 'Venues', 'digitone-events' ); ?></div>
			<a class="de-stat-link" href="<?php echo esc_url( admin_url( 'admin.php?page=digitone-events-venues' ) ); ?>"><?php esc_html_e( 'Manage →', 'digitone-events' ); ?></a>
		</div>

		<div class="de-stat-card">
			<div class="de-stat-num"><?php echo esc_html( $speaker_count ); ?></div>
			<div class="de-stat-label"><?php esc_html_e( 'Speakers', 'digitone-events' ); ?></div>
			<a class="de-stat-link" href="<?php echo esc_url( admin_url( 'admin.php?page=digitone-events-speakers' ) ); ?>"><?php esc_html_e( 'Manage →', 'digitone-events' ); ?></a>
		</div>

		<div class="de-stat-card">
			<div class="de-stat-num"><?php echo esc_html( $session_count ); ?></div>
			<div class="de-stat-label"><?php esc_html_e( 'Sessions', 'digitone-events' ); ?></div>
			<a class="de-stat-link" href="<?php echo esc_url( admin_url( 'admin.php?page=digitone-events-sessions' ) ); ?>"><?php esc_html_e( 'Manage →', 'digitone-events' ); ?></a>
		</div>

		<div class="de-stat-card de-stat-card-wide">
			<div class="de-stat-label"><?php esc_html_e( 'Active event', 'digitone-events' ); ?></div>
			<?php if ( $active ) : ?>
				<div class="de-stat-num-small"><?php echo esc_html( $active['name'] ); ?></div>
				<div class="de-stat-sub">
					<span class="de-badge de-badge-<?php echo esc_attr( $active['status'] ); ?>">
						<?php echo esc_html( ucfirst( $active['status'] ) ); ?>
					</span>
					<span class="de-active-counts">
						<?php
						printf(
							/* translators: 1: number of days, 2: number of venues */
							esc_html__( '%1$s days · %2$s venues', 'digitone-events' ),
							'<strong>' . esc_html( $active_day_count ) . '</strong>',
							'<strong>' . esc_html( $active_venue_count ) . '</strong>'
						);
						?>
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
		<a class="button button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=digitone-events-days' ) ); ?>">
			<?php esc_html_e( 'Manage days', 'digitone-events' ); ?>
		</a>
		<a class="button button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=digitone-events-venues' ) ); ?>">
			<?php esc_html_e( 'Manage venues', 'digitone-events' ); ?>
		</a>
		<a class="button button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=digitone-events-speakers' ) ); ?>">
			<?php esc_html_e( 'Manage speakers', 'digitone-events' ); ?>
		</a>
		<a class="button button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=digitone-events-sessions' ) ); ?>">
			<?php esc_html_e( 'Manage sessions', 'digitone-events' ); ?>
		</a>
		<a class="button button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=digitone-events-export-import' ) ); ?>">
			<?php esc_html_e( 'Export / Import', 'digitone-events' ); ?>
		</a>
		<a class="button button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=digitone-events-settings' ) ); ?>">
			<?php esc_html_e( 'Settings', 'digitone-events' ); ?>
		</a>
	</div>

	<div class="notice notice-info inline">
		<p>
			<strong><?php esc_html_e( 'Phase 5 — feature complete', 'digitone-events' ); ?>:</strong>
			<?php esc_html_e( 'Export/Import and frontend shortcodes are now live. Use [digitone_events_agenda], [digitone_events_speakers] and [digitone_events_venues] in any page or post.', 'digitone-events' ); ?>
		</p>
	</div>
</div>

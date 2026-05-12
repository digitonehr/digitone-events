<?php
/**
 * Sessions admin page (tabs container).
 *
 * @var string                         $tab
 * @var string|null                    $active_event_id
 * @var array<int,array<string,mixed>> $days
 * @var string                         $day_id
 * @var array<int,array<string,mixed>> $sessions
 * @var array<int,array<string,mixed>> $venues_tree
 * @var array<int,array<string,mixed>> $speakers
 * @var array<int,array<string,mixed>> $roles
 * @var array<int,array<string,mixed>> $session_types
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

$base_url = admin_url( 'admin.php?page=digitone-events-sessions' );
$tabs = [
	'sessions' => __( 'Sessions',      'digitone-events' ),
	'types'    => __( 'Session Types', 'digitone-events' ),
];
?>
<div class="wrap digitone-events-page" data-page="sessions" data-active-event="<?php echo esc_attr( $active_event_id ?: '' ); ?>" data-tab="<?php echo esc_attr( $tab ); ?>" data-day-id="<?php echo esc_attr( $day_id ); ?>">

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Sessions', 'digitone-events' ); ?></h1>
	<hr class="wp-header-end">

	<?php DigitOne_Events_Helpers_View::render( 'admin/views/partials/active-event-bar', [ 'active_event_id' => $active_event_id ] ); ?>

	<?php if ( ! $active_event_id ) : ?>
		<p><?php esc_html_e( 'Select an active event to manage its sessions.', 'digitone-events' ); ?></p>
	<?php else : ?>

		<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $slug => $label ) :
				$url    = add_query_arg( 'tab', $slug, $base_url );
				$active = ( $slug === $tab ) ? ' nav-tab-active' : '';
			?>
				<a href="<?php echo esc_url( $url ); ?>" class="nav-tab<?php echo esc_attr( $active ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<div class="de-tab-panel">
			<?php
			if ( $tab === 'sessions' ) {
				DigitOne_Events_Helpers_View::render( 'modules/sessions/views/tab-sessions', [
					'days'          => $days,
					'day_id'        => $day_id,
					'sessions'      => $sessions,
					'venues_tree'   => $venues_tree,
					'speakers'      => $speakers,
					'roles'         => $roles,
					'session_types' => $session_types,
					'base_url'      => $base_url,
				] );
			} elseif ( $tab === 'types' ) {
				DigitOne_Events_Helpers_View::render( 'modules/sessions/views/tab-session-types', [
					'session_types' => $session_types,
				] );
			}
			?>
		</div>
	<?php endif; ?>
</div>

<div class="de-feedback" role="status" aria-live="polite"></div>

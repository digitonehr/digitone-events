<?php
/**
 * Speakers admin page (tabs container).
 *
 * @var string                          $tab
 * @var array<int,array<string,mixed>>  $speakers
 * @var array<int,array<string,mixed>>  $titles
 * @var array<int,array<string,mixed>>  $roles
 * @var string|null                     $active_event_id
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

$base_url = admin_url( 'admin.php?page=digitone-events-speakers' );
$tabs = [
	'speakers' => __( 'Speakers', 'digitone-events' ),
	'titles'   => __( 'Titles',   'digitone-events' ),
	'roles'    => __( 'Roles',    'digitone-events' ),
];
?>
<div class="wrap digitone-events-page" data-page="speakers" data-active-event="<?php echo esc_attr( $active_event_id ?: '' ); ?>" data-tab="<?php echo esc_attr( $tab ); ?>">

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Speakers, Titles & Roles', 'digitone-events' ); ?></h1>
	<hr class="wp-header-end">

	<?php DigitOne_Events_Helpers_View::render( 'admin/views/partials/active-event-bar', [ 'active_event_id' => $active_event_id ] ); ?>

	<?php if ( ! $active_event_id ) : ?>
		<p><?php esc_html_e( 'Select an active event to manage its speakers, titles, and roles.', 'digitone-events' ); ?></p>
	<?php else : ?>

		<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $slug => $label ) :
				$url    = add_query_arg( 'tab', $slug, $base_url );
				$active = ( $slug === $tab ) ? ' nav-tab-active' : '';
			?>
				<a href="<?php echo esc_url( $url ); ?>" class="nav-tab<?php echo esc_attr( $active ); ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="de-tab-panel">
			<?php
			if ( $tab === 'speakers' ) {
				DigitOne_Events_Helpers_View::render( 'modules/speakers/views/tab-speakers', [
					'speakers' => $speakers,
					'titles'   => $titles,
					'roles'    => $roles,
				] );
			} elseif ( $tab === 'titles' ) {
				DigitOne_Events_Helpers_View::render( 'modules/speakers/views/tab-titles', [
					'titles' => $titles,
				] );
			} elseif ( $tab === 'roles' ) {
				DigitOne_Events_Helpers_View::render( 'modules/speakers/views/tab-roles', [
					'roles' => $roles,
				] );
			}
			?>
		</div>

	<?php endif; ?>
</div>

<div class="de-feedback" role="status" aria-live="polite"></div>

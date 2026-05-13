<?php
/**
 * Frontend speakers template.
 *
 * Renders the [digitone_events_speakers] shortcode output: search box,
 * role filter pills, and a grid of speaker cards. Filtering happens
 * client-side via assets/js/frontend.js (setupSpeakersFilter), which
 * reads the data-search-text and data-role-ids attributes baked into
 * each card. Server already sorts by last_name → first_name in the
 * repository, so the rendered grid is alphabetical out of the box.
 *
 * @var array<string,mixed>            $event
 * @var array<int,array<string,mixed>> $speakers
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

// Collect the unique roles that actually appear on at least one speaker.
// Filter pills only show roles that would return non-empty results.
$role_options = []; // role_id => [ 'name' => ..., 'color' => ..., 'count' => N ]
foreach ( $speakers as $sp ) {
	foreach ( (array) ( $sp['roles'] ?? [] ) as $r ) {
		$rid = $r['id'] ?? '';
		if ( $rid === '' ) continue;
		if ( ! isset( $role_options[ $rid ] ) ) {
			$role_options[ $rid ] = [
				'name'  => $r['name']  ?? '',
				'color' => $r['color'] ?? '#6b7280',
				'count' => 0,
			];
		}
		$role_options[ $rid ]['count']++;
	}
}
// Stable role order: by name, alphabetically.
uasort( $role_options, function ( $a, $b ) {
	return strcasecmp( $a['name'], $b['name'] );
} );

$total = count( $speakers );
?>
<div class="digitone-events-frontend digitone-events-speakers" data-event-slug="<?php echo esc_attr( $event['slug'] ); ?>">

	<header class="de-fe-speakers-header">
		<h2 class="de-fe-title"><?php
			printf(
				/* translators: %s: event name */
				esc_html__( 'Speakers — %s', 'digitone-events' ),
				esc_html( $event['name'] )
			);
		?></h2>
		<?php if ( $total > 0 ) : ?>
			<p class="de-fe-speakers-count">
				<span data-de-speakers-shown><?php echo (int) $total; ?></span>
				<span class="de-fe-speakers-count-sep">/</span>
				<span><?php echo (int) $total; ?></span>
				<?php esc_html_e( 'speakers', 'digitone-events' ); ?>
			</p>
		<?php endif; ?>
	</header>

	<?php if ( empty( $speakers ) ) : ?>
		<p class="de-fe-empty"><?php esc_html_e( 'Speakers will be announced soon.', 'digitone-events' ); ?></p>
	<?php else : ?>

		<div class="de-fe-speakers-filters">
			<div class="de-fe-speakers-search">
				<svg class="de-fe-speakers-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<circle cx="11" cy="11" r="8"></circle>
					<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
				</svg>
				<input
					type="search"
					class="de-fe-speakers-search-input"
					data-de-speakers-search
					placeholder="<?php esc_attr_e( 'Search speakers…', 'digitone-events' ); ?>"
					aria-label="<?php esc_attr_e( 'Search speakers', 'digitone-events' ); ?>"
					autocomplete="off">
				<button type="button"
					class="de-fe-speakers-search-clear"
					data-de-speakers-search-clear
					aria-label="<?php esc_attr_e( 'Clear search', 'digitone-events' ); ?>"
					hidden>×</button>
			</div>

			<?php if ( ! empty( $role_options ) ) : ?>
				<nav class="de-fe-speakers-roles-nav" role="tablist" aria-label="<?php esc_attr_e( 'Filter by role', 'digitone-events' ); ?>">
					<button type="button"
						class="de-fe-speakers-role-pill is-active"
						data-de-speakers-role=""
						role="tab"
						aria-selected="true">
						<?php esc_html_e( 'All roles', 'digitone-events' ); ?>
					</button>
					<?php foreach ( $role_options as $rid => $role ) : ?>
						<button type="button"
							class="de-fe-speakers-role-pill"
							data-de-speakers-role="<?php echo esc_attr( $rid ); ?>"
							style="--role-color: <?php echo esc_attr( $role['color'] ); ?>"
							role="tab"
							aria-selected="false">
							<?php echo esc_html( $role['name'] ); ?>
							<span class="de-fe-speakers-role-count"><?php echo (int) $role['count']; ?></span>
						</button>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
		</div>

		<p class="de-fe-speakers-empty-results" data-de-speakers-empty hidden>
			<?php esc_html_e( 'No speakers match your search.', 'digitone-events' ); ?>
		</p>

		<ul class="de-fe-speakers-grid">
			<?php foreach ( $speakers as $sp ) :
				$first      = (string) ( $sp['first_name'] ?? '' );
				$last       = (string) ( $sp['last_name']  ?? '' );
				$title_name = (string) ( $sp['title_name'] ?? '' );
				$name       = trim( $title_name . ' ' . $first . ' ' . $last );

				// Build per-speaker filter metadata (UTF-8 lowercased on the
				// server so JS doesn't have to normalize per keystroke).
				$role_ids   = [];
				$role_names = [];
				foreach ( (array) ( $sp['roles'] ?? [] ) as $r ) {
					if ( ! empty( $r['id'] ) )   $role_ids[]   = $r['id'];
					if ( ! empty( $r['name'] ) ) $role_names[] = mb_strtolower( $r['name'], 'UTF-8' );
				}
				$search_bits = array_filter( [
					mb_strtolower( $first . ' ' . $last, 'UTF-8' ),
					mb_strtolower( $last  . ' ' . $first, 'UTF-8' ),
					mb_strtolower( $title_name, 'UTF-8' ),
					implode( ' ', $role_names ),
				] );
			?>
				<li class="de-fe-speaker-card"
					data-search-text="<?php echo esc_attr( implode( ' ', $search_bits ) ); ?>"
					data-role-ids="<?php echo esc_attr( implode( ',', $role_ids ) ); ?>">
					<?php if ( ! empty( $sp['photo_url'] ) ) : ?>
						<img class="de-fe-speaker-photo"
							src="<?php echo esc_url( $sp['photo_url'] ); ?>"
							alt="<?php echo esc_attr( $name ); ?>"
							loading="lazy">
					<?php else : ?>
						<div class="de-fe-speaker-photo de-fe-speaker-photo-empty" aria-hidden="true">👤</div>
					<?php endif; ?>
					<div class="de-fe-speaker-name"><?php echo esc_html( $name ); ?></div>
					<?php if ( ! empty( $sp['roles'] ) ) : ?>
						<div class="de-fe-speaker-roles">
							<?php foreach ( $sp['roles'] as $r ) :
								$style = ! empty( $r['color'] ) ? 'background:' . esc_attr( $r['color'] ) : '';
							?>
								<span class="de-fe-role-badge" style="<?php echo $style; ?>"><?php echo esc_html( $r['name'] ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $sp['bio'] ) ) : ?>
						<div class="de-fe-speaker-bio"><?php echo wp_kses_post( $sp['bio'] ); ?></div>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>

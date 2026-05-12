<?php
/**
 * Frontend agenda — time-grid schedule with day switcher (v0.5.4).
 *
 * Only one day is visible at a time. Initial day is chosen by PHP:
 *   - If today's date matches one of the event days → that day
 *   - Otherwise → first day
 * JS handles clicking between days (frontend.js).
 *
 * @var array<string,mixed>                              $event
 * @var array<int,array<string,mixed>>                   $days
 * @var array<string,array<int,array<string,mixed>>>     $sessions_by_day
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

$to_min = function ( ?string $hms ) : ?int {
	if ( ! $hms ) return null;
	$parts = explode( ':', $hms );
	if ( count( $parts ) < 2 ) return null;
	return ( (int) $parts[0] ) * 60 + (int) $parts[1];
};
$fmt = function ( int $m ) : string {
	return sprintf( '%02d:%02d', intdiv( $m, 60 ), $m % 60 );
};

// Determine the global hall set used across the event.
$all_halls       = [];
$all_halls_order = [];
foreach ( $sessions_by_day as $sessions ) {
	foreach ( $sessions as $s ) {
		$hid   = $s['sub_venue_id'] ?? null;
		$hname = $s['sub_venue_name'] ?? null;
		if ( ! $hid || ! $hname ) continue;
		if ( ! isset( $all_halls[ $hid ] ) ) {
			$all_halls[ $hid ] = $hname;
			$all_halls_order[] = $hid;
		}
	}
}
usort( $all_halls_order, function ( $a, $b ) use ( $all_halls ) {
	return strcasecmp( $all_halls[ $a ], $all_halls[ $b ] );
} );
$hall_count = count( $all_halls_order );
$hall_col   = [];
foreach ( $all_halls_order as $i => $hid ) {
	$hall_col[ $hid ] = $i + 2;
}

// Choose default day: today's date if it matches one of the event days, else 0.
$today        = current_time( 'Y-m-d' );
$current_day  = 0;
foreach ( $days as $i => $d ) {
	if ( $d['day_date'] === $today ) {
		$current_day = $i;
		break;
	}
}

$slot_minutes = 30;
?>
<div class="digitone-events-frontend digitone-events-schedule"
	data-event-slug="<?php echo esc_attr( $event['slug'] ); ?>"
	data-initial-day="<?php echo (int) $current_day; ?>">

	<header class="de-fe-header">
		<h2 class="de-fe-title"><?php echo esc_html( $event['name'] ); ?></h2>
		<?php if ( ! empty( $event['start_date'] ) ) :
			$date_line = DigitOne_Events_Helpers_Format::date_display( $event['start_date'] );
			if ( ! empty( $event['end_date'] ) && $event['end_date'] !== $event['start_date'] ) {
				$date_line .= ' → ' . DigitOne_Events_Helpers_Format::date_display( $event['end_date'] );
			}
		?>
			<p class="de-fe-dates"><?php echo esc_html( $date_line ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $event['description'] ) ) : ?>
			<div class="de-fe-description"><?php echo wp_kses_post( $event['description'] ); ?></div>
		<?php endif; ?>
	</header>

	<?php if ( ! empty( $days ) ) : ?>
		<nav class="de-fe-day-nav" role="tablist">
			<?php foreach ( $days as $i => $d ) :
				$is_active = ( $i === $current_day );
				$wd        = DigitOne_Events_Helpers_Format::date_display( $d['day_date'] );
			?>
				<button type="button"
					class="de-fe-day-nav-item<?php echo $is_active ? ' is-active' : ''; ?>"
					data-day-index="<?php echo (int) $i; ?>"
					role="tab"
					aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
					aria-controls="de-day-panel-<?php echo (int) $i; ?>">
					<span class="de-fe-day-num">Day <?php echo esc_html( $i + 1 ); ?></span>
					<span class="de-fe-day-date"><?php echo esc_html( $wd ); ?></span>
				</button>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php if ( empty( $days ) ) : ?>
		<p class="de-fe-empty"><?php esc_html_e( 'Agenda coming soon.', 'digitone-events' ); ?></p>
	<?php else : ?>
		<?php foreach ( $days as $day_idx => $day ) :
			$sessions = $sessions_by_day[ $day['id'] ] ?? [];

			$starts = $ends = [];
			foreach ( $sessions as $s ) {
				$st = $to_min( $s['start_time'] );
				$en = $to_min( $s['end_time'] );
				if ( $st !== null ) $starts[] = $st;
				if ( $en !== null ) $ends[]   = $en;
			}
			if ( empty( $starts ) ) {
				$day_start = 8 * 60;
				$day_end   = 19 * 60;
			} else {
				$day_start = (int) floor( min( $starts ) / $slot_minutes ) * $slot_minutes;
				$day_end   = (int) ceil(  max( $ends )   / $slot_minutes ) * $slot_minutes;
			}
			$total_slots = max( 1, intdiv( $day_end - $day_start, $slot_minutes ) );

			$day_label       = DigitOne_Events_Helpers_Format::date_display( $day['day_date'] );
			$day_label_extra = $day['label'] ?? '';
			$is_active       = ( $day_idx === $current_day );
		?>
			<section class="de-fe-schedule-day<?php echo $is_active ? ' is-active' : ''; ?>"
				id="de-day-panel-<?php echo (int) $day_idx; ?>"
				data-day-index="<?php echo (int) $day_idx; ?>"
				role="tabpanel"
				aria-hidden="<?php echo $is_active ? 'false' : 'true'; ?>">

				<div class="de-fe-day-header">
					<div class="de-fe-day-header-num">Day <?php echo esc_html( $day_idx + 1 ); ?></div>
					<div class="de-fe-day-header-date"><?php echo esc_html( $day_label ); ?></div>
					<?php if ( $day_label_extra ) : ?>
						<div class="de-fe-day-header-extra"><?php echo esc_html( $day_label_extra ); ?></div>
					<?php endif; ?>
				</div>

				<?php if ( empty( $sessions ) ) : ?>
					<p class="de-fe-day-empty"><?php esc_html_e( 'No sessions scheduled.', 'digitone-events' ); ?></p>
				<?php else : ?>

					<div class="de-fe-schedule-grid"
						style="--hall-count: <?php echo (int) $hall_count; ?>; --slot-count: <?php echo (int) $total_slots; ?>;"
						role="grid"
						aria-label="<?php echo esc_attr( $day_label ); ?>">

						<div class="de-fe-grid-corner" style="grid-row: 1; grid-column: 1"></div>

						<?php foreach ( $all_halls_order as $hid ) : ?>
							<div class="de-fe-grid-hall" style="grid-row: 1; grid-column: <?php echo (int) $hall_col[ $hid ]; ?>">
								<?php echo esc_html( $all_halls[ $hid ] ); ?>
							</div>
						<?php endforeach; ?>

						<?php for ( $i = 0; $i < $total_slots; $i++ ) :
							$row_idx = $i + 2;
							$min     = $day_start + $i * $slot_minutes;
							$show    = ( $min % 60 === 0 );
						?>
							<div class="de-fe-grid-time<?php echo $show ? ' is-hour' : ''; ?>" style="grid-row: <?php echo (int) $row_idx; ?>; grid-column: 1">
								<?php echo $show ? esc_html( $fmt( $min ) ) : ''; ?>
							</div>
						<?php endfor; ?>

						<?php foreach ( $sessions as $s ) :
							$st = $to_min( $s['start_time'] );
							$en = $to_min( $s['end_time'] );
							if ( $st === null || $en === null || $en <= $st ) continue;
							$row_start = intdiv( $st - $day_start, $slot_minutes ) + 2;
							$row_span  = max( 1, intdiv( $en - $st,        $slot_minutes ) );

							$is_break = ! empty( $s['type_name'] ) && strtolower( $s['type_name'] ) === 'break';
							if ( $is_break ) {
								$col_style = 'grid-column: 2 / -1';
							} else {
								$col = $hall_col[ $s['sub_venue_id'] ?? '' ] ?? 2;
								$col_style = 'grid-column: ' . (int) $col;
							}

							$color = $s['type_color'] ?? '#6b7280';
							$style = sprintf(
								'grid-row: %d / span %d; %s; --type-color: %s;',
								$row_start, $row_span, $col_style, esc_attr( $color )
							);

							$type_label = trim( ( $s['type_icon'] ?? '' ) . ' ' . ( $s['type_name'] ?? '' ) );
							$time_label = $fmt( $st ) . ' – ' . $fmt( $en );
						?>
							<article class="de-fe-block<?php echo $is_break ? ' is-break' : ''; ?>" style="<?php echo $style; ?>">
								<?php if ( $type_label ) : ?>
									<div class="de-fe-block-type"><?php echo esc_html( $type_label ); ?></div>
								<?php endif; ?>
								<div class="de-fe-block-title"><?php echo esc_html( $s['title'] ); ?></div>
								<div class="de-fe-block-time"><?php echo esc_html( $time_label ); ?></div>
								<?php if ( ! empty( $s['speakers'] ) ) : ?>
									<div class="de-fe-block-speakers">
										<?php
										$names = [];
										foreach ( array_slice( $s['speakers'], 0, 4 ) as $sp ) {
											$names[] = trim( $sp['first_name'] . ' ' . $sp['last_name'] );
										}
										echo esc_html( implode( ' · ', $names ) );
										if ( count( $s['speakers'] ) > 4 ) {
											printf( ' <span class="de-fe-block-more">+%d</span>', count( $s['speakers'] ) - 4 );
										}
										?>
									</div>
								<?php endif; ?>
							</article>
						<?php endforeach; ?>

					</div>

					<ul class="de-fe-mobile-list">
						<?php foreach ( $sessions as $s ) :
							$type_label = trim( ( $s['type_icon'] ?? '' ) . ' ' . ( $s['type_name'] ?? '' ) );
							$time = '';
							if ( ! empty( $s['start_time'] ) ) {
								$time = DigitOne_Events_Helpers_Format::time_display( $s['start_time'] );
								if ( ! empty( $s['end_time'] ) ) $time .= ' – ' . DigitOne_Events_Helpers_Format::time_display( $s['end_time'] );
							}
							$venue = $s['sub_venue_name'] ?? '';
							$color = $s['type_color'] ?? '#6b7280';
						?>
							<li class="de-fe-mobile-item" style="--type-color: <?php echo esc_attr( $color ); ?>">
								<div class="de-fe-mobile-time"><?php echo esc_html( $time ); ?></div>
								<div class="de-fe-mobile-body">
									<?php if ( $type_label ) : ?>
										<span class="de-fe-mobile-type"><?php echo esc_html( $type_label ); ?></span>
									<?php endif; ?>
									<div class="de-fe-mobile-title"><?php echo esc_html( $s['title'] ); ?></div>
									<?php if ( $venue ) : ?>
										<div class="de-fe-mobile-venue"><?php echo esc_html( $venue ); ?></div>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>

				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

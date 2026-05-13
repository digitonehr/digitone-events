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
$hall_primary    = []; // sub_venue_id => primary venue_id (needed for primary-level filtering)
foreach ( $sessions_by_day as $sessions ) {
	foreach ( $sessions as $s ) {
		$hid   = $s['sub_venue_id'] ?? null;
		$hname = $s['sub_venue_name'] ?? null;
		if ( ! $hid || ! $hname ) continue;
		if ( ! isset( $all_halls[ $hid ] ) ) {
			$all_halls[ $hid ]    = $hname;
			$all_halls_order[]    = $hid;
			$hall_primary[ $hid ] = $s['venue_id'] ?? '';
		}
	}
}
usort( $all_halls_order, function ( $a, $b ) use ( $all_halls ) {
	return strcasecmp( $all_halls[ $a ], $all_halls[ $b ] );
} );
$hall_count = count( $all_halls_order );
$hall_col   = [];
$hall_color = [];
// Auto-assigned colour palette so each hall has a distinct visual identity on
// mobile (where we lose the per-hall columns of the desktop grid).
$hall_palette = [
	'#2563eb', // blue
	'#db2777', // pink
	'#0d9488', // teal
	'#ea580c', // orange
	'#7c3aed', // purple
	'#dc2626', // red
	'#16a34a', // green
	'#0284c7', // cyan
];
foreach ( $all_halls_order as $i => $hid ) {
	$hall_col[ $hid ]   = $i + 2;
	$hall_color[ $hid ] = $hall_palette[ $i % count( $hall_palette ) ];
}

// Collect unique primary venues (venue_id => venue_name).
$primary_venues = [];
foreach ( $sessions_by_day as $day_sessions ) {
	foreach ( $day_sessions as $s ) {
		$vid = $s['venue_id'] ?? '';
		if ( ! $vid || isset( $primary_venues[ $vid ] ) ) continue;
		$primary_venues[ $vid ] = $s['venue_name'] ?? '';
	}
}
asort( $primary_venues );
$multi_primary = count( $primary_venues ) > 1;

// Build venue-picker options. Single primary: label = sub-venue name only.
// Multi primary: label = "Primary Venue — Sub Venue Name" + group metadata.
$venue_options       = []; // sub_venue_id => [ 'label' => ..., 'primary_id' => ..., 'color' => ..., 'name' => ... ]
$venue_options_order = []; // ordered list for stable iteration
foreach ( $sessions_by_day as $day_sessions ) {
	foreach ( $day_sessions as $s ) {
		$sub_id = $s['sub_venue_id'] ?? '';
		if ( ! $sub_id || isset( $venue_options[ $sub_id ] ) ) continue;
		$sub_name = $s['sub_venue_name'] ?? '';
		if ( $multi_primary ) {
			$label = trim( ( $s['venue_name'] ?? '' ) . ( $sub_name ? ' — ' . $sub_name : '' ) );
		} else {
			$label = $sub_name ?: ( $s['venue_name'] ?? '' );
		}
		$venue_options[ $sub_id ] = [
			'label'      => $label,
			'primary_id' => $s['venue_id'] ?? '',
			'color'      => $hall_color[ $sub_id ] ?? '#6b7280',
			'name'       => $sub_name,
		];
		$venue_options_order[] = [
			'id'          => $sub_id,
			'primary_id'  => $s['venue_id'] ?? '',
			'primary_name'=> strtolower( $s['venue_name'] ?? '' ),
			'sub_name'    => strtolower( $sub_name ),
		];
	}
}
usort( $venue_options_order, function ( $a, $b ) {
	if ( $a['primary_name'] !== $b['primary_name'] ) return strcmp( $a['primary_name'], $b['primary_name'] );
	return strcmp( $a['sub_name'], $b['sub_name'] );
} );

// Collect unique session types used in this event.
$type_options = []; // type_id => [ 'name' => ..., 'color' => ..., 'icon' => ... ]
foreach ( $sessions_by_day as $day_sessions ) {
	foreach ( $day_sessions as $s ) {
		$tid = $s['session_type_id'] ?? '';
		if ( ! $tid || isset( $type_options[ $tid ] ) ) continue;
		$type_options[ $tid ] = [
			'name'  => $s['type_name']  ?? '',
			'color' => $s['type_color'] ?? '#6b7280',
			'icon'  => $s['type_icon']  ?? '',
		];
	}
}
uasort( $type_options, function ( $a, $b ) { return strcasecmp( $a['name'], $b['name'] ); } );

// Collect unique speakers used in this event (id => "First Last", sorted by last name).
$speaker_options = []; // speaker_id => 'First Last'
$speaker_sort    = []; // for usort: speaker_id => 'last first' lowercased
foreach ( $sessions_by_day as $day_sessions ) {
	foreach ( $day_sessions as $s ) {
		foreach ( (array) ( $s['speakers'] ?? [] ) as $sp ) {
			$sid = $sp['speaker_id'] ?? '';
			if ( ! $sid || isset( $speaker_options[ $sid ] ) ) continue;
			$first = $sp['first_name'] ?? '';
			$last  = $sp['last_name']  ?? '';
			$full  = trim( $first . ' ' . $last );
			if ( $full === '' ) continue;
			$speaker_options[ $sid ] = $full;
			$speaker_sort[ $sid ]    = strtolower( $last . ' ' . $first );
		}
	}
}
uksort( $speaker_options, function ( $a, $b ) use ( $speaker_sort ) {
	return strcmp( $speaker_sort[ $a ] ?? '', $speaker_sort[ $b ] ?? '' );
} );

// Initial active day defaults to first day. Browser-side JS will switch to
// "today" if today's local date matches one of the event days. Doing this in JS
// (not PHP) avoids server timezone and clock drift issues.
$current_day = 0;

$slot_minutes = 30;
?>
<div class="digitone-events-frontend digitone-events-schedule"
	data-event-slug="<?php echo esc_attr( $event['slug'] ); ?>"
	data-event-name="<?php echo esc_attr( $event['name'] ); ?>"
	data-title-suffix="<?php echo esc_attr( $title_suffix ?? 'Programme' ); ?>">

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
		<div class="de-fe-header-actions">
			<a class="de-fe-add-calendar"
				href="<?php echo esc_url( rest_url( 'digitone-events/v1/ical/event/' . $event['slug'] ) ); ?>"
				download>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
					<line x1="16" y1="2" x2="16" y2="6"></line>
					<line x1="8" y1="2" x2="8" y2="6"></line>
					<line x1="3" y1="10" x2="21" y2="10"></line>
				</svg>
				<?php esc_html_e( 'Add to calendar', 'digitone-events' ); ?>
			</a>
		</div>
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

		<?php if ( ! empty( $venue_options ) ) : ?>
			<details class="de-fe-filters">
				<summary class="de-fe-filters-summary">
					<span class="de-fe-filters-chevron" aria-hidden="true"></span>
					<span class="de-fe-filters-label"><?php esc_html_e( 'Filters', 'digitone-events' ); ?></span>
					<span class="de-fe-filters-active" data-de-active-label></span>
				</summary>
				<div class="de-fe-filters-body">
					<div class="de-fe-filter-row de-fe-filter-search">
						<svg class="de-fe-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<circle cx="11" cy="11" r="8"></circle>
							<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
						</svg>
						<input type="search"
							class="de-fe-search-input"
							data-de-filter="search"
							placeholder="<?php esc_attr_e( 'Search sessions and speakers…', 'digitone-events' ); ?>"
							aria-label="<?php esc_attr_e( 'Search sessions', 'digitone-events' ); ?>">
						<button type="button" class="de-fe-search-clear" data-de-search-clear hidden aria-label="<?php esc_attr_e( 'Clear search', 'digitone-events' ); ?>">×</button>
					</div>

					<?php if ( ! empty( $type_options ) ) : ?>
						<nav class="de-fe-type-nav" aria-label="<?php esc_attr_e( 'Filter by type', 'digitone-events' ); ?>">
							<button type="button" class="de-fe-type-nav-item is-active" data-type="">
								<?php esc_html_e( 'All types', 'digitone-events' ); ?>
							</button>
							<?php foreach ( $type_options as $tid => $topt ) :
								$label = trim( ( $topt['icon'] ?? '' ) . ' ' . $topt['name'] );
							?>
								<button type="button" class="de-fe-type-nav-item"
									data-type="<?php echo esc_attr( $tid ); ?>"
									style="--type-color: <?php echo esc_attr( $topt['color'] ); ?>">
									<?php echo esc_html( $label ); ?>
								</button>
							<?php endforeach; ?>
						</nav>
					<?php endif; ?>

					<?php if ( ! empty( $speaker_options ) ) : ?>
						<div class="de-fe-filter-row de-fe-filter-speaker">
							<label class="de-fe-filter-label" for="de-fe-speaker-select"><?php esc_html_e( 'Speaker:', 'digitone-events' ); ?></label>
							<select id="de-fe-speaker-select" class="de-fe-speaker-select" data-de-filter="speaker">
								<option value=""><?php esc_html_e( 'All speakers', 'digitone-events' ); ?></option>
								<?php foreach ( $speaker_options as $sid => $sname ) : ?>
									<option value="<?php echo esc_attr( $sid ); ?>"><?php echo esc_html( $sname ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<?php if ( $multi_primary ) : ?>
						<nav class="de-fe-primary-nav" aria-label="<?php esc_attr_e( 'Filter by venue', 'digitone-events' ); ?>">
							<button type="button" class="de-fe-primary-nav-item is-active" data-primary="">
								<?php esc_html_e( 'All venues', 'digitone-events' ); ?>
							</button>
							<?php foreach ( $primary_venues as $pid => $pname ) : ?>
								<button type="button" class="de-fe-primary-nav-item" data-primary="<?php echo esc_attr( $pid ); ?>">
									<?php echo esc_html( $pname ); ?>
								</button>
							<?php endforeach; ?>
						</nav>
					<?php endif; ?>
					<nav class="de-fe-venue-nav" aria-label="<?php echo $multi_primary ? esc_attr__( 'Filter by hall', 'digitone-events' ) : esc_attr__( 'Filter by venue', 'digitone-events' ); ?>">
						<?php if ( ! $multi_primary ) : ?>
							<button type="button" class="de-fe-venue-nav-item is-active" data-venue="">
								<?php esc_html_e( 'All venues', 'digitone-events' ); ?>
							</button>
						<?php endif; ?>
						<?php foreach ( $venue_options_order as $vo ) :
							$sub_id    = $vo['id'];
							$opt       = $venue_options[ $sub_id ];
							// Short label = sub-venue name alone (used when its parent primary is the active filter).
							// Long label  = full "Primary — Sub" (used when "All venues" is active in multi-primary mode).
							$short_lbl = $opt['name'] ?: $opt['label'];
							$long_lbl  = $opt['label'];
						?>
							<button type="button" class="de-fe-venue-nav-item"
								data-venue="<?php echo esc_attr( $sub_id ); ?>"
								data-primary="<?php echo esc_attr( $opt['primary_id'] ); ?>"
								data-label-short="<?php echo esc_attr( $short_lbl ); ?>"
								data-label-long="<?php echo esc_attr( $long_lbl ); ?>"
								style="--hall-color: <?php echo esc_attr( $opt['color'] ); ?>">
								<?php echo esc_html( $long_lbl ); ?>
							</button>
						<?php endforeach; ?>
					</nav>
				</div>
			</details>
		<?php endif; ?>
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
				data-day-date="<?php echo esc_attr( $day['day_date'] ); ?>"
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
							<div class="de-fe-grid-hall" data-sub-venue-id="<?php echo esc_attr( $hid ); ?>" data-venue-id="<?php echo esc_attr( $hall_primary[ $hid ] ?? '' ); ?>" data-original-grid-column="<?php echo (int) $hall_col[ $hid ]; ?>" style="grid-row: 1; grid-column: <?php echo (int) $hall_col[ $hid ]; ?>; --hall-color: <?php echo esc_attr( $hall_color[ $hid ] ); ?>">
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
							<?php
							// Compute the original grid-column string for filter restoration.
							if ( $is_break ) {
								$orig_grid_col = '2 / -1';
							} else {
								$orig_grid_col = (string) ( $hall_col[ $s['sub_venue_id'] ?? '' ] ?? 2 );
							}
							?>
							<?php
							// Build search index + speaker id list for the multi-filter logic.
							$de_speaker_ids = [];
							$de_search_bits = [ mb_strtolower( (string) ( $s['title'] ?? '' ), 'UTF-8' ) ];
							foreach ( (array) ( $s['speakers'] ?? [] ) as $de_sp ) {
								if ( ! empty( $de_sp['speaker_id'] ) ) $de_speaker_ids[] = $de_sp['speaker_id'];
								$de_search_bits[] = mb_strtolower( trim( ( $de_sp['first_name'] ?? '' ) . ' ' . ( $de_sp['last_name'] ?? '' ) ), 'UTF-8' );
							}
							if ( ! empty( $s['type_name'] ) )      $de_search_bits[] = mb_strtolower( $s['type_name'], 'UTF-8' );
							if ( ! empty( $s['sub_venue_name'] ) ) $de_search_bits[] = mb_strtolower( $s['sub_venue_name'], 'UTF-8' );
							?>
							<article class="de-fe-block<?php echo $is_break ? ' is-break' : ''; ?>"
								style="<?php echo $style; ?>"
								data-session-id="<?php echo esc_attr( $s['id'] ); ?>"
								data-sub-venue-id="<?php echo esc_attr( $s['sub_venue_id'] ?? '' ); ?>"
								data-venue-id="<?php echo esc_attr( $s['venue_id'] ?? '' ); ?>"
								data-type-id="<?php echo esc_attr( $s['session_type_id'] ?? '' ); ?>"
								data-speaker-ids="<?php echo esc_attr( implode( ',', $de_speaker_ids ) ); ?>"
								data-search-text="<?php echo esc_attr( implode( ' ', $de_search_bits ) ); ?>"
								data-original-grid-column="<?php echo esc_attr( $orig_grid_col ); ?>"
								data-start-minutes="<?php echo (int) $st; ?>"
								data-end-minutes="<?php echo (int) $en; ?>"
								<?php if ( ! $is_break ) : ?>tabindex="0" role="button"<?php endif; ?>>
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
						<?php
						// Mobile list sort: first by sub_venue_name (Hall A → Hall B → ...),
						// then by start_time within each venue. This is what users intuitively
						// expect on phones where there's no per-hall column.
						$mobile_sessions = $sessions;
						usort( $mobile_sessions, function ( $a, $b ) {
							$va = strtolower( (string) ( $a['sub_venue_name'] ?? '' ) );
							$vb = strtolower( (string) ( $b['sub_venue_name'] ?? '' ) );
							if ( $va !== $vb ) return strcmp( $va, $vb );
							return strcmp( (string) ( $a['start_time'] ?? '' ), (string) ( $b['start_time'] ?? '' ) );
						} );
						?>
						<?php foreach ( $mobile_sessions as $s ) :
							$type_label = trim( ( $s['type_icon'] ?? '' ) . ' ' . ( $s['type_name'] ?? '' ) );
							$time = '';
							if ( ! empty( $s['start_time'] ) ) {
								$time = DigitOne_Events_Helpers_Format::time_display( $s['start_time'] );
								if ( ! empty( $s['end_time'] ) ) $time .= ' – ' . DigitOne_Events_Helpers_Format::time_display( $s['end_time'] );
							}
							$venue       = $s['sub_venue_name'] ?? '';
							$type_clr    = $s['type_color'] ?? '#6b7280';
							$hall_clr    = $hall_color[ $s['sub_venue_id'] ?? '' ] ?? '#6b7280';
							$is_break_mi = ! empty( $s['type_name'] ) && strtolower( $s['type_name'] ) === 'break';
							$speakers_mi = $s['speakers'] ?? [];
						?>
							<?php
							$st_mi = $to_min( $s['start_time'] ?? null );
							$en_mi = $to_min( $s['end_time']   ?? null );
							?>
							<?php
							$mi_speaker_ids = [];
							$mi_search_bits = [ mb_strtolower( (string) ( $s['title'] ?? '' ), 'UTF-8' ) ];
							foreach ( (array) ( $s['speakers'] ?? [] ) as $mi_sp ) {
								if ( ! empty( $mi_sp['speaker_id'] ) ) $mi_speaker_ids[] = $mi_sp['speaker_id'];
								$mi_search_bits[] = mb_strtolower( trim( ( $mi_sp['first_name'] ?? '' ) . ' ' . ( $mi_sp['last_name'] ?? '' ) ), 'UTF-8' );
							}
							if ( ! empty( $s['type_name'] ) )      $mi_search_bits[] = mb_strtolower( $s['type_name'], 'UTF-8' );
							if ( ! empty( $s['sub_venue_name'] ) ) $mi_search_bits[] = mb_strtolower( $s['sub_venue_name'], 'UTF-8' );
							?>
							<li class="de-fe-mobile-item<?php echo $is_break_mi ? ' is-break' : ''; ?>"
								style="--type-color: <?php echo esc_attr( $type_clr ); ?>; --hall-color: <?php echo esc_attr( $hall_clr ); ?>"
								data-sub-venue-id="<?php echo esc_attr( $s['sub_venue_id'] ?? '' ); ?>"
								data-venue-id="<?php echo esc_attr( $s['venue_id'] ?? '' ); ?>"
								data-type-id="<?php echo esc_attr( $s['session_type_id'] ?? '' ); ?>"
								data-speaker-ids="<?php echo esc_attr( implode( ',', $mi_speaker_ids ) ); ?>"
								data-search-text="<?php echo esc_attr( implode( ' ', $mi_search_bits ) ); ?>"
								data-start-minutes="<?php echo (int) $st_mi; ?>"
								data-end-minutes="<?php echo (int) $en_mi; ?>"
								<?php if ( ! $is_break_mi ) : ?>
								data-session-id="<?php echo esc_attr( $s['id'] ); ?>"
								tabindex="0"
								role="button"
								<?php endif; ?>>
								<?php if ( $is_break_mi ) : ?>
									<div class="de-fe-mobile-break-time"><?php echo esc_html( $time ); ?></div>
									<div class="de-fe-mobile-break-title"><?php echo esc_html( ( $s['type_icon'] ?? '' ) . ' ' . ( $s['title'] ?? '' ) ); ?></div>
								<?php else : ?>
									<div class="de-fe-mobile-top">
										<?php if ( $venue ) : ?>
											<span class="de-fe-mobile-hall-pill"><?php echo esc_html( $venue ); ?></span>
										<?php endif; ?>
										<span class="de-fe-mobile-time"><?php echo esc_html( $time ); ?></span>
									</div>
									<?php if ( $type_label ) : ?>
										<div class="de-fe-mobile-type"><?php echo esc_html( $type_label ); ?></div>
									<?php endif; ?>
									<div class="de-fe-mobile-title"><?php echo esc_html( $s['title'] ); ?></div>
									<?php if ( ! empty( $speakers_mi ) ) : ?>
										<div class="de-fe-mobile-speakers">
											<?php
											$names_mi = [];
											foreach ( array_slice( $speakers_mi, 0, 3 ) as $sp ) {
												$names_mi[] = trim( ( $sp['first_name'] ?? '' ) . ' ' . ( $sp['last_name'] ?? '' ) );
											}
											echo esc_html( implode( ' · ', $names_mi ) );
											if ( count( $speakers_mi ) > 3 ) {
												printf( ' <span class="de-fe-mobile-more">+%d</span>', count( $speakers_mi ) - 3 );
											}
											?>
										</div>
									<?php endif; ?>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>

				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>

	<?php
	// Build a JSON map of session details for the detail modal.
	$detail_map = [];
	foreach ( $sessions_by_day as $day_id => $day_sessions ) {
		foreach ( $day_sessions as $s ) {
			if ( ! empty( $s['type_name'] ) && strtolower( $s['type_name'] ) === 'break' ) continue;
			$speakers_out = [];
			foreach ( (array) ( $s['speakers'] ?? [] ) as $sp ) {
				$speakers_out[] = [
					'name'        => trim( ( $sp['first_name'] ?? '' ) . ' ' . ( $sp['last_name'] ?? '' ) ),
					'photo_url'   => $sp['photo_url'] ?? '',
					'bio'         => $sp['bio']       ?? '',
					'role_name'   => $sp['role_name'] ?? '',
					'role_color'  => $sp['role_color']?? '',
				];
			}
			$detail_map[ $s['id'] ] = [
				'title'          => $s['title'] ?? '',
				'description'    => $s['description'] ?? '',
				'start_time'     => substr( (string) ( $s['start_time'] ?? '' ), 0, 5 ),
				'end_time'       => substr( (string) ( $s['end_time']   ?? '' ), 0, 5 ),
				'venue_name'     => $s['venue_name']     ?? '',
				'sub_venue_name' => $s['sub_venue_name'] ?? '',
				'type_name'      => $s['type_name']      ?? '',
				'type_color'     => $s['type_color']     ?? '',
				'type_icon'      => $s['type_icon']      ?? '',
				'speakers'       => $speakers_out,
				'ics_url'        => rest_url( 'digitone-events/v1/ical/session/' . $s['id'] ),
			];
		}
	}
	?>
	<script type="application/json" class="de-fe-session-data"><?php echo wp_json_encode( $detail_map ); ?></script>

	<!-- Session detail modal (hidden by default, opened by JS on block click) -->
	<div class="de-fe-session-modal" role="dialog" aria-modal="true" aria-hidden="true" hidden>
		<div class="de-fe-modal-backdrop" data-de-modal-close></div>
		<div class="de-fe-modal-panel" role="document">
			<button type="button" class="de-fe-modal-close" data-de-modal-close aria-label="<?php esc_attr_e( 'Close', 'digitone-events' ); ?>">×</button>
			<div class="de-fe-modal-body"></div>
		</div>
	</div>
</div>

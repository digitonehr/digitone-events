<?php
/**
 * Frontend agenda template.
 *
 * @var array<string,mixed>                              $event
 * @var array<int,array<string,mixed>>                   $days
 * @var array<string,array<int,array<string,mixed>>>     $sessions_by_day
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="digitone-events-frontend digitone-events-agenda" data-event-slug="<?php echo esc_attr( $event['slug'] ); ?>">
	<header class="de-fe-header">
		<h2 class="de-fe-title"><?php echo esc_html( $event['name'] ); ?></h2>
		<?php if ( ! empty( $event['start_date'] ) ) :
			$date_line = DigitOne_Events_Helpers_Format::date_display( $event['start_date'] );
			if ( ! empty( $event['end_date'] ) && $event['end_date'] !== $event['start_date'] ) {
				$date_line .= ' – ' . DigitOne_Events_Helpers_Format::date_display( $event['end_date'] );
			}
		?>
			<p class="de-fe-dates"><?php echo esc_html( $date_line ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $event['description'] ) ) : ?>
			<div class="de-fe-description"><?php echo wp_kses_post( $event['description'] ); ?></div>
		<?php endif; ?>
	</header>

	<?php if ( empty( $days ) ) : ?>
		<p class="de-fe-empty"><?php esc_html_e( 'Agenda coming soon.', 'digitone-events' ); ?></p>
	<?php else : ?>
		<?php foreach ( $days as $day ) :
			$day_sessions = $sessions_by_day[ $day['id'] ] ?? [];
			$day_label    = DigitOne_Events_Helpers_Format::date_display( $day['day_date'] );
			if ( ! empty( $day['label'] ) ) {
				$day_label .= ' — ' . $day['label'];
			}
		?>
			<section class="de-fe-day">
				<h3 class="de-fe-day-title"><?php echo esc_html( $day_label ); ?></h3>
				<?php if ( empty( $day_sessions ) ) : ?>
					<p class="de-fe-day-empty"><?php esc_html_e( 'No sessions scheduled.', 'digitone-events' ); ?></p>
				<?php else : ?>
					<ol class="de-fe-sessions">
						<?php foreach ( $day_sessions as $s ) :
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
							$type_style = ! empty( $s['type_color'] ) ? 'background:' . esc_attr( $s['type_color'] ) : '';
							$is_child   = ( $s['session_level'] ?? '' ) === 'child';
						?>
							<li class="de-fe-session<?php echo $is_child ? ' is-child' : ''; ?>">
								<div class="de-fe-session-time"><?php echo esc_html( $time ); ?></div>
								<div class="de-fe-session-body">
									<div class="de-fe-session-headline">
										<?php if ( ! empty( $s['type_name'] ) ) : ?>
											<span class="de-fe-type-badge" style="<?php echo $type_style; ?>">
												<?php if ( ! empty( $s['type_icon'] ) ) echo esc_html( $s['type_icon'] ) . ' '; ?>
												<?php echo esc_html( $s['type_name'] ); ?>
											</span>
										<?php endif; ?>
										<h4 class="de-fe-session-title"><?php echo esc_html( $s['title'] ); ?></h4>
									</div>
									<?php if ( $venue ) : ?>
										<div class="de-fe-session-venue">📍 <?php echo esc_html( $venue ); ?></div>
									<?php endif; ?>
									<?php if ( ! empty( $s['description'] ) ) : ?>
										<div class="de-fe-session-description"><?php echo wp_kses_post( $s['description'] ); ?></div>
									<?php endif; ?>
									<?php if ( ! empty( $s['speakers'] ) ) : ?>
										<ul class="de-fe-session-speakers">
											<?php foreach ( $s['speakers'] as $sp ) :
												$name = trim( $sp['first_name'] . ' ' . $sp['last_name'] );
												$role_style = ! empty( $sp['role_color'] ) ? 'background:' . esc_attr( $sp['role_color'] ) : '';
											?>
												<li class="de-fe-speaker-item">
													<?php if ( ! empty( $sp['photo_url'] ) ) : ?>
														<img class="de-fe-speaker-avatar" src="<?php echo esc_url( $sp['photo_url'] ); ?>" alt="">
													<?php endif; ?>
													<span class="de-fe-speaker-name"><?php echo esc_html( $name ); ?></span>
													<?php if ( ! empty( $sp['role_name'] ) ) : ?>
														<span class="de-fe-role-badge" style="<?php echo $role_style; ?>"><?php echo esc_html( $sp['role_name'] ); ?></span>
													<?php endif; ?>
												</li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

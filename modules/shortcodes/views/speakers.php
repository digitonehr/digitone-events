<?php
/**
 * Frontend speakers template.
 *
 * @var array<string,mixed>            $event
 * @var array<int,array<string,mixed>> $speakers
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="digitone-events-frontend digitone-events-speakers" data-event-slug="<?php echo esc_attr( $event['slug'] ); ?>">
	<h2 class="de-fe-title"><?php
		printf(
			/* translators: %s: event name */
			esc_html__( 'Speakers — %s', 'digitone-events' ),
			esc_html( $event['name'] )
		);
	?></h2>

	<?php if ( empty( $speakers ) ) : ?>
		<p class="de-fe-empty"><?php esc_html_e( 'Speakers will be announced soon.', 'digitone-events' ); ?></p>
	<?php else : ?>
		<ul class="de-fe-speakers-grid">
			<?php foreach ( $speakers as $sp ) :
				$name = trim( ( $sp['title_name'] ?? '' ) . ' ' . $sp['first_name'] . ' ' . $sp['last_name'] );
			?>
				<li class="de-fe-speaker-card">
					<?php if ( ! empty( $sp['photo_url'] ) ) : ?>
						<img class="de-fe-speaker-photo" src="<?php echo esc_url( $sp['photo_url'] ); ?>" alt="<?php echo esc_attr( $name ); ?>">
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

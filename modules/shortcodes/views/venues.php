<?php
/**
 * Frontend venues template.
 *
 * @var array<string,mixed>            $event
 * @var array<int,array<string,mixed>> $tree   Primary venues each with 'sub_venues'.
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="digitone-events-frontend digitone-events-venues" data-event-slug="<?php echo esc_attr( $event['slug'] ); ?>">
	<h2 class="de-fe-title"><?php
		printf(
			/* translators: %s: event name */
			esc_html__( 'Venues — %s', 'digitone-events' ),
			esc_html( $event['name'] )
		);
	?></h2>

	<?php if ( empty( $tree ) ) : ?>
		<p class="de-fe-empty"><?php esc_html_e( 'Venues will be announced soon.', 'digitone-events' ); ?></p>
	<?php else : ?>
		<ul class="de-fe-venues-list">
			<?php foreach ( $tree as $v ) : ?>
				<li class="de-fe-venue">
					<h3 class="de-fe-venue-name"><?php echo esc_html( $v['name'] ); ?></h3>
					<?php if ( ! empty( $v['address'] ) ) : ?>
						<address class="de-fe-venue-address"><?php echo nl2br( esc_html( $v['address'] ) ); ?></address>
					<?php endif; ?>
					<?php if ( ! empty( $v['sub_venues'] ) ) : ?>
						<ul class="de-fe-subvenues">
							<?php foreach ( $v['sub_venues'] as $sub ) : ?>
								<li class="de-fe-subvenue">
									<strong><?php echo esc_html( $sub['name'] ); ?></strong>
									<?php if ( ! empty( $sub['address'] ) ) : ?>
										<span class="de-fe-subvenue-address"><?php echo esc_html( $sub['address'] ); ?></span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>

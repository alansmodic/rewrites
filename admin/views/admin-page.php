<?php
/**
 * Admin page template for reviewing staged revisions.
 *
 * @package Rewrites
 * @var array $staged_items Array of staged revision data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Rewrites - Staged Content', 'rewrites' ); ?></h1>

	<p><?php esc_html_e( 'Review and manage pending content changes awaiting publication.', 'rewrites' ); ?></p>

	<?php if ( empty( $staged_items ) ) : ?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'No staged revisions found. When authors save changes to published posts without publishing, they will appear here for review.', 'rewrites' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" class="column-title"><?php esc_html_e( 'Post', 'rewrites' ); ?></th>
					<th scope="col" class="column-type"><?php esc_html_e( 'Type', 'rewrites' ); ?></th>
					<th scope="col" class="column-author"><?php esc_html_e( 'Author', 'rewrites' ); ?></th>
					<th scope="col" class="column-modified"><?php esc_html_e( 'Modified', 'rewrites' ); ?></th>
					<th scope="col" class="column-status"><?php esc_html_e( 'Status', 'rewrites' ); ?></th>
					<th scope="col" class="column-scheduled"><?php esc_html_e( 'Scheduled', 'rewrites' ); ?></th>
					<th scope="col" class="column-notes"><?php esc_html_e( 'Notes', 'rewrites' ); ?></th>
					<th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'rewrites' ); ?></th>
				</tr>
			</thead>
			<tbody id="rewrites-staged-list">
				<?php foreach ( $staged_items as $item ) : ?>
					<?php
					$author      = get_userdata( $item->staged_author_id );
					$author_name = $author ? $author->display_name : __( 'Unknown', 'rewrites' );
					$item_status = $item->staged_status ? $item->staged_status : 'pending';
					?>
					<tr data-revision-id="<?php echo esc_attr( $item->revision_id ); ?>">
						<td class="column-title">
							<strong>
								<a href="<?php echo esc_url( get_edit_post_link( $item->post_parent ) ); ?>">
									<?php echo esc_html( $item->post_title ); ?>
								</a>
							</strong>
							<div class="row-actions">
								<span class="view">
									<a href="<?php echo esc_url( get_permalink( $item->post_parent ) ); ?>" target="_blank">
										<?php esc_html_e( 'View Live', 'rewrites' ); ?>
									</a> |
								</span>
								<span class="edit">
									<a href="<?php echo esc_url( get_edit_post_link( $item->post_parent ) ); ?>">
										<?php esc_html_e( 'Edit', 'rewrites' ); ?>
									</a>
								</span>
							</div>
						</td>
						<td class="column-type">
							<?php
							$post_type_obj = get_post_type_object( $item->post_type );
							echo esc_html( $post_type_obj ? $post_type_obj->labels->singular_name : $item->post_type );
							?>
						</td>
						<td class="column-author"><?php echo esc_html( $author_name ); ?></td>
						<td class="column-modified">
							<?php
							/* translators: %s: human-readable time difference */
							printf( esc_html__( '%s ago', 'rewrites' ), esc_html( human_time_diff( strtotime( $item->post_modified ) ) ) );
							?>
							<br>
							<small><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->post_modified ) ) ); ?></small>
						</td>
						<td class="column-status">
							<span class="rewrites-status rewrites-status--<?php echo esc_attr( $item_status ); ?>">
								<?php
								$status_labels = array(
									'pending'  => __( 'Pending', 'rewrites' ),
									'approved' => __( 'Approved', 'rewrites' ),
									'rejected' => __( 'Rejected', 'rewrites' ),
								);
								echo esc_html( $status_labels[ $item_status ] ?? ucfirst( $item_status ) );
								?>
							</span>
						</td>
						<td class="column-scheduled">
							<?php if ( $item->scheduled_date ) : ?>
								<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->scheduled_date ) ) ); ?>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
						<td class="column-notes">
							<?php echo esc_html( $item->notes ? $item->notes : '—' ); ?>
						</td>
						<td class="column-actions">
							<div class="rewrites-actions">
								<a href="<?php echo esc_url( admin_url( 'revision.php?revision=' . $item->revision_id ) ); ?>" class="button button-small">
									<?php esc_html_e( 'Compare', 'rewrites' ); ?>
								</a>

								<?php if ( 'approved' !== $item_status && 'rejected' !== $item_status ) : ?>
									<button type="button" class="button button-small button-primary rewrites-approve" data-revision="<?php echo esc_attr( $item->revision_id ); ?>">
										<?php esc_html_e( 'Approve', 'rewrites' ); ?>
									</button>
								<?php endif; ?>

								<?php if ( 'rejected' !== $item_status ) : ?>
									<button type="button" class="button button-small rewrites-publish" data-revision="<?php echo esc_attr( $item->revision_id ); ?>">
										<?php esc_html_e( 'Publish Now', 'rewrites' ); ?>
									</button>
								<?php endif; ?>

								<?php if ( 'rejected' !== $item_status ) : ?>
									<button type="button" class="button button-small rewrites-reject" data-revision="<?php echo esc_attr( $item->revision_id ); ?>">
										<?php esc_html_e( 'Reject', 'rewrites' ); ?>
									</button>
								<?php endif; ?>

								<button type="button" class="button button-small button-link-delete rewrites-discard" data-revision="<?php echo esc_attr( $item->revision_id ); ?>">
									<?php esc_html_e( 'Discard', 'rewrites' ); ?>
								</button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

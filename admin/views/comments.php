<?php
/**
 * Admin comments list view.
 *
 * @package WP_QA_Comments
 */

defined( 'ABSPATH' ) || exit;

$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$post_filter   = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
$paged         = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$per_page      = 20;

$result = WPQA_Database::query(
	array(
		'post_id'  => $post_filter,
		'status'   => $status_filter,
		'search'   => $search,
		'per_page' => $per_page,
		'page'     => $paged,
		'orderby'  => 'created_at',
		'order'    => 'DESC',
	)
);

$total_pages = max( 1, (int) ceil( $result['total'] / $per_page ) );

$counts = array(
	'all'      => WPQA_Database::count_by_status( '' ),
	'pending'  => WPQA_Database::count_by_status( 'pending' ),
	'approved' => WPQA_Database::count_by_status( 'approved' ),
	'rejected' => WPQA_Database::count_by_status( 'rejected' ),
);

WPQA_Admin::print_notice();
?>
<div class="wrap wpqa-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'پرسش و پاسخ', 'wp-qa-comments' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpqa-import' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'درون‌ریزی JSON', 'wp-qa-comments' ); ?>
	</a>
	<hr class="wp-header-end" />

	<?php if ( $post_filter ) : ?>
		<div class="notice notice-info inline">
			<p>
				<?php
				printf(
					/* translators: 1: post ID, 2: post title */
					esc_html__( 'فیلتر شده بر اساس نوشته #%1$d (%2$s).', 'wp-qa-comments' ),
					(int) $post_filter,
					esc_html( get_the_title( $post_filter ) ? get_the_title( $post_filter ) : __( 'بدون عنوان', 'wp-qa-comments' ) )
				);
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpqa-comments' ) ); ?>"><?php esc_html_e( 'حذف فیلتر', 'wp-qa-comments' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<ul class="subsubsub">
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpqa-comments' ) ); ?>" class="<?php echo '' === $status_filter ? 'current' : ''; ?>">
				<?php esc_html_e( 'همه', 'wp-qa-comments' ); ?>
				<span class="count">(<?php echo esc_html( (string) $counts['all'] ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpqa-comments&status=pending' ) ); ?>" class="<?php echo 'pending' === $status_filter ? 'current' : ''; ?>">
				<?php esc_html_e( 'در انتظار', 'wp-qa-comments' ); ?>
				<span class="count">(<?php echo esc_html( (string) $counts['pending'] ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpqa-comments&status=approved' ) ); ?>" class="<?php echo 'approved' === $status_filter ? 'current' : ''; ?>">
				<?php esc_html_e( 'تأیید شده', 'wp-qa-comments' ); ?>
				<span class="count">(<?php echo esc_html( (string) $counts['approved'] ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpqa-comments&status=rejected' ) ); ?>" class="<?php echo 'rejected' === $status_filter ? 'current' : ''; ?>">
				<?php esc_html_e( 'رد شده', 'wp-qa-comments' ); ?>
				<span class="count">(<?php echo esc_html( (string) $counts['rejected'] ); ?>)</span>
			</a>
		</li>
	</ul>

	<form method="get" class="wpqa-search-form">
		<input type="hidden" name="page" value="wpqa-comments" />
		<?php if ( $status_filter ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>" />
		<?php endif; ?>
		<?php if ( $post_filter ) : ?>
			<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_filter ); ?>" />
		<?php endif; ?>
		<p class="search-box">
			<label class="screen-reader-text" for="wpqa-search"><?php esc_html_e( 'جستجوی کامنت‌ها', 'wp-qa-comments' ); ?></label>
			<input type="search" id="wpqa-search" name="s" value="<?php echo esc_attr( $search ); ?>" />
			<button type="submit" class="button"><?php esc_html_e( 'جستجو', 'wp-qa-comments' ); ?></button>
		</p>
	</form>

	<form method="post">
		<?php wp_nonce_field( 'wpqa_bulk_action' ); ?>
		<div class="tablenav top">
			<div class="alignleft actions bulkactions">
				<label for="wpqa-bulk" class="screen-reader-text"><?php esc_html_e( 'عملیات گروهی', 'wp-qa-comments' ); ?></label>
				<select name="wpqa_bulk_action" id="wpqa-bulk">
					<option value=""><?php esc_html_e( 'عملیات گروهی', 'wp-qa-comments' ); ?></option>
					<option value="approve"><?php esc_html_e( 'تأیید', 'wp-qa-comments' ); ?></option>
					<option value="reject"><?php esc_html_e( 'رد', 'wp-qa-comments' ); ?></option>
					<option value="pending"><?php esc_html_e( 'انتقال به در انتظار', 'wp-qa-comments' ); ?></option>
					<option value="delete"><?php esc_html_e( 'حذف', 'wp-qa-comments' ); ?></option>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'اجرا', 'wp-qa-comments' ); ?></button>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
				<tr>
					<td class="manage-column column-cb check-column"><input type="checkbox" id="wpqa-cb-all" /></td>
					<th scope="col"><?php esc_html_e( 'نام', 'wp-qa-comments' ); ?></th>
					<th scope="col"><?php esc_html_e( 'سؤال', 'wp-qa-comments' ); ?></th>
					<th scope="col"><?php esc_html_e( 'نوشته / برگه', 'wp-qa-comments' ); ?></th>
					<th scope="col"><?php esc_html_e( 'تاریخ', 'wp-qa-comments' ); ?></th>
					<th scope="col"><?php esc_html_e( 'وضعیت', 'wp-qa-comments' ); ?></th>
					<th scope="col"><?php esc_html_e( 'پاسخ مدیر', 'wp-qa-comments' ); ?></th>
					<th scope="col"><?php esc_html_e( 'عملیات', 'wp-qa-comments' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $result['items'] ) ) : ?>
					<tr>
						<td colspan="8"><?php esc_html_e( 'کامنتی یافت نشد.', 'wp-qa-comments' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $result['items'] as $item ) : ?>
						<?php
						$post_title = get_the_title( $item->post_id );
						$edit_link  = admin_url( 'admin.php?page=wpqa-comments&action=edit&comment_id=' . (int) $item->id );
						$permalink  = get_permalink( $item->post_id );
						?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" name="comment_ids[]" value="<?php echo esc_attr( $item->id ); ?>" />
							</th>
							<td><strong><?php echo esc_html( $item->name ); ?></strong></td>
							<td>
								<?php echo esc_html( wp_trim_words( wp_strip_all_tags( $item->question ), 18 ) ); ?>
							</td>
							<td>
								<?php if ( $post_title ) : ?>
									<a href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( $post_title ); ?>
									</a>
									<br /><small>#<?php echo esc_html( (string) $item->post_id ); ?></small>
								<?php else : ?>
									#<?php echo esc_html( (string) $item->post_id ); ?>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( WPQA_Jalali::format( $item->created_at, true ) ); ?>
							</td>
							<td>
								<span class="wpqa-status wpqa-status--<?php echo esc_attr( $item->status ); ?>">
									<?php echo esc_html( WPQA_Admin::status_label( $item->status ) ); ?>
								</span>
							</td>
							<td>
								<?php
								if ( ! empty( $item->answer ) ) {
									echo esc_html( wp_trim_words( wp_strip_all_tags( $item->answer ), 12 ) );
								} else {
									echo '<em>' . esc_html__( '—', 'wp-qa-comments' ) . '</em>';
								}
								?>
							</td>
							<td class="wpqa-row-actions">
								<a href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'ویرایش / پاسخ', 'wp-qa-comments' ); ?></a>
								|
								<?php if ( 'approved' !== $item->status ) : ?>
									<a href="<?php echo esc_url( WPQA_Admin::action_url( 'approve', $item->id ) ); ?>"><?php esc_html_e( 'تأیید', 'wp-qa-comments' ); ?></a> |
								<?php endif; ?>
								<?php if ( 'rejected' !== $item->status ) : ?>
									<a href="<?php echo esc_url( WPQA_Admin::action_url( 'reject', $item->id ) ); ?>"><?php esc_html_e( 'رد', 'wp-qa-comments' ); ?></a> |
								<?php endif; ?>
								<a href="<?php echo esc_url( WPQA_Admin::action_url( 'delete', $item->id ) ); ?>" class="wpqa-delete" onclick="return confirm('<?php echo esc_js( __( 'این کامنت حذف شود؟', 'wp-qa-comments' ) ); ?>');">
									<?php esc_html_e( 'حذف', 'wp-qa-comments' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $paged,
							)
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>
	</form>
</div>
<script>
document.getElementById('wpqa-cb-all')?.addEventListener('change', function () {
	document.querySelectorAll('input[name="comment_ids[]"]').forEach(function (el) {
		el.checked = this.checked;
	}, this);
});
</script>

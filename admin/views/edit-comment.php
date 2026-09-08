<?php
/**
 * Admin edit / reply comment view.
 *
 * @package WP_QA_Comments
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $comment ) ) {
	echo '<div class="wrap"><p>' . esc_html__( 'کامنت یافت نشد.', 'wp-qa-comments' ) . '</p></div>';
	return;
}

WPQA_Admin::print_notice();

$post_title = get_the_title( $comment->post_id );
?>
<div class="wrap wpqa-admin-wrap">
	<h1><?php esc_html_e( 'ویرایش / پاسخ', 'wp-qa-comments' ); ?></h1>
	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpqa-comments' ) ); ?>">&rarr; <?php esc_html_e( 'بازگشت به لیست', 'wp-qa-comments' ); ?></a>
	</p>

	<form method="post" class="wpqa-edit-form">
		<?php wp_nonce_field( 'wpqa_save_comment' ); ?>
		<input type="hidden" name="comment_id" value="<?php echo esc_attr( $comment->id ); ?>" />
		<input type="hidden" name="wpqa_save_comment" value="1" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wpqa-post-id"><?php esc_html_e( 'شناسه نوشته', 'wp-qa-comments' ); ?></label></th>
				<td>
					<input type="number" name="post_id" id="wpqa-post-id" value="<?php echo esc_attr( $comment->post_id ); ?>" class="small-text" min="1" />
					<?php if ( $post_title ) : ?>
						<p class="description">
							<?php echo esc_html( $post_title ); ?> —
							<a href="<?php echo esc_url( get_permalink( $comment->post_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'مشاهده', 'wp-qa-comments' ); ?></a>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpqa-name"><?php esc_html_e( 'نام', 'wp-qa-comments' ); ?></label></th>
				<td>
					<input type="text" name="name" id="wpqa-name" value="<?php echo esc_attr( $comment->name ); ?>" class="regular-text" required />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpqa-question"><?php esc_html_e( 'سؤال', 'wp-qa-comments' ); ?></label></th>
				<td>
					<textarea name="question" id="wpqa-question" rows="5" class="large-text" required><?php echo esc_textarea( $comment->question ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpqa-answer"><?php esc_html_e( 'پاسخ مدیر', 'wp-qa-comments' ); ?></label></th>
				<td>
					<textarea name="answer" id="wpqa-answer" rows="5" class="large-text wpqa-admin-reply-field"><?php echo esc_textarea( (string) $comment->answer ); ?></textarea>
					<p class="description"><?php esc_html_e( 'برای حذف پاسخ مدیر، این فیلد را خالی بگذارید.', 'wp-qa-comments' ); ?></p>
					<?php if ( ! empty( $comment->replied_at ) ) : ?>
						<p class="description">
							<?php
							printf(
								/* translators: %s: datetime */
								esc_html__( 'آخرین پاسخ در: %s', 'wp-qa-comments' ),
								esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $comment->replied_at ) )
							);
							?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpqa-status"><?php esc_html_e( 'وضعیت', 'wp-qa-comments' ); ?></label></th>
				<td>
					<select name="status" id="wpqa-status">
						<option value="pending" <?php selected( $comment->status, 'pending' ); ?>><?php esc_html_e( 'در انتظار', 'wp-qa-comments' ); ?></option>
						<option value="approved" <?php selected( $comment->status, 'approved' ); ?>><?php esc_html_e( 'تأیید شده', 'wp-qa-comments' ); ?></option>
						<option value="rejected" <?php selected( $comment->status, 'rejected' ); ?>><?php esc_html_e( 'رد شده', 'wp-qa-comments' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'تاریخ ایجاد', 'wp-qa-comments' ); ?></th>
				<td>
					<code><?php echo esc_html( $comment->created_at ); ?></code>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'ذخیره کامنت', 'wp-qa-comments' ) ); ?>
	</form>
</div>

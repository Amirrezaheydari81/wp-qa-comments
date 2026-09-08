<?php
/**
 * Admin Import JSON view.
 *
 * @package WP_QA_Comments
 */

defined( 'ABSPATH' ) || exit;

WPQA_Admin::print_notice();

$report = get_transient( 'wpqa_import_report_' . get_current_user_id() );
if ( $report ) {
	delete_transient( 'wpqa_import_report_' . get_current_user_id() );
}

$sample = wp_json_encode(
	array(
		array(
			'post_id'  => get_option( 'page_on_front' ) ? (int) get_option( 'page_on_front' ) : 1,
			'name'     => 'علی',
			'question' => 'این محصول برای استفاده روزمره مناسبه؟',
			'answer'   => 'بله، برای استفاده روزمره کاملاً مناسبه.',
		),
		array(
			'post_id'  => get_option( 'page_on_front' ) ? (int) get_option( 'page_on_front' ) : 1,
			'name'     => 'محمد',
			'question' => 'ارسال این محصول چقدر زمان می‌بره؟',
			'answer'   => 'معمولاً سفارش‌ها بین ۲ تا ۴ روز کاری ارسال می‌شوند.',
		),
	),
	JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);
?>
<div class="wrap wpqa-admin-wrap">
	<h1><?php esc_html_e( 'درون‌ریزی JSON', 'wp-qa-comments' ); ?></h1>

	<div class="notice notice-warning inline">
		<p>
			<strong><?php esc_html_e( 'فقط برای داده دمو / تست', 'wp-qa-comments' ); ?>:</strong>
			<?php
			esc_html_e(
				'از این بخش برای بارگذاری محتوای آزمایشی یا دمو استفاده کنید. آیتم‌های درون‌ریزی‌شده را به‌عنوان نظر واقعی کاربران معرفی نکنید.',
				'wp-qa-comments'
			);
			?>
		</p>
	</div>

	<?php if ( is_array( $report ) ) : ?>
		<div class="wpqa-import-report notice notice-info">
			<h2><?php esc_html_e( 'درون‌ریزی انجام شد', 'wp-qa-comments' ); ?></h2>
			<ul>
				<li><?php printf( esc_html__( 'کل: %d', 'wp-qa-comments' ), (int) $report['total'] ); ?></li>
				<li><?php printf( esc_html__( 'موفق: %d', 'wp-qa-comments' ), (int) $report['imported'] ); ?></li>
				<li><?php printf( esc_html__( 'ناموفق: %d', 'wp-qa-comments' ), (int) $report['failed'] ); ?></li>
			</ul>
			<?php if ( ! empty( $report['errors'] ) ) : ?>
				<h3><?php esc_html_e( 'خطاها:', 'wp-qa-comments' ); ?></h3>
				<ul class="wpqa-import-errors">
					<?php foreach ( $report['errors'] as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<form method="post" enctype="multipart/form-data" class="wpqa-import-form">
		<?php wp_nonce_field( 'wpqa_import_json' ); ?>
		<input type="hidden" name="wpqa_import_json" value="1" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wpqa-json"><?php esc_html_e( 'داده JSON', 'wp-qa-comments' ); ?></label></th>
				<td>
					<textarea name="wpqa_json" id="wpqa-json" rows="16" class="large-text code" placeholder='[ { "post_id": 123, "name": "...", "question": "...", "answer": "..." } ]'></textarea>
					<p class="description">
						<?php esc_html_e( 'یک آرایه JSON بچسبانید. هر آیتم باید post_id، name و question داشته باشد. فیلد answer اختیاری است.', 'wp-qa-comments' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpqa-json-file"><?php esc_html_e( 'یا آپلود فایل JSON', 'wp-qa-comments' ); ?></label></th>
				<td>
					<input type="file" name="wpqa_json_file" id="wpqa-json-file" accept=".json,application/json" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpqa-default-status"><?php esc_html_e( 'وضعیت پیش‌فرض', 'wp-qa-comments' ); ?></label></th>
				<td>
					<select name="default_status" id="wpqa-default-status">
						<option value="approved"><?php esc_html_e( 'تأیید شده', 'wp-qa-comments' ); ?></option>
						<option value="pending"><?php esc_html_e( 'در انتظار', 'wp-qa-comments' ); ?></option>
						<option value="rejected"><?php esc_html_e( 'رد شده', 'wp-qa-comments' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'اگر آیتم وضعیت جداگانه نداشته باشد از این مقدار استفاده می‌شود.', 'wp-qa-comments' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'تاریخ‌های دمو', 'wp-qa-comments' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="generate_random_dates" value="1" />
						<?php esc_html_e( 'تولید تاریخ‌های تصادفی', 'wp-qa-comments' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'فقط برای داده تست/دمو — تاریخ ایجاد (و پاسخ) متنوع ساخته می‌شود و از تاریخ انتشار نوشته عقب‌تر نمی‌رود.', 'wp-qa-comments' ); ?>
					</p>
					<label for="wpqa-date-range">
						<?php esc_html_e( 'بازه زمانی (روز):', 'wp-qa-comments' ); ?>
						<input type="number" name="date_range_days" id="wpqa-date-range" value="90" min="1" max="3650" class="small-text" />
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'شروع درون‌ریزی', 'wp-qa-comments' ) ); ?>
	</form>

	<details class="wpqa-sample-json">
		<summary><?php esc_html_e( 'نمونه JSON', 'wp-qa-comments' ); ?></summary>
		<pre class="wpqa-code-block"><?php echo esc_html( $sample ); ?></pre>
	</details>
</div>

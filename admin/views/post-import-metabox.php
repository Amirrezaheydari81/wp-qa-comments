<?php
/**
 * Post/Page edit screen — Import JSON meta box.
 *
 * @package WP_QA_Comments
 *
 * @var WP_Post $post Current post object (from meta box callback).
 */

defined( 'ABSPATH' ) || exit;

$count_result = WPQA_Database::query(
	array(
		'post_id'  => $post->ID,
		'per_page' => 1,
		'page'     => 1,
	)
);
$total_count = (int) $count_result['total'];

$sample = wp_json_encode(
	array(
		array(
			'name'     => 'علی',
			'question' => 'این محصول برای استفاده روزمره مناسبه؟',
			'answer'   => 'بله، برای استفاده روزمره کاملاً مناسبه.',
		),
		array(
			'name'     => 'محمد',
			'question' => 'ارسال این محصول چقدر زمان می‌بره؟',
			'answer'   => 'معمولاً سفارش‌ها بین ۲ تا ۴ روز کاری ارسال می‌شوند.',
		),
	),
	JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);
?>
<div class="wpqa-post-import" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
	<p class="description wpqa-post-import-note">
		<strong><?php esc_html_e( 'فقط دمو / تست:', 'wp-qa-comments' ); ?></strong>
		<?php esc_html_e( 'آیتم‌های درون‌ریزی‌شده برای محتوای آزمایشی یا دمو هستند و نباید به‌عنوان نظر واقعی کاربران معرفی شوند. همه آیتم‌ها به‌صورت خودکار به همین نوشته متصل می‌شوند (وارد کردن post_id در JSON اختیاری است).', 'wp-qa-comments' ); ?>
	</p>

	<p class="wpqa-post-import-count">
		<?php
		printf(
			/* translators: %d: number of Q&A comments */
			esc_html__( 'تعداد پرسش و پاسخ‌های این محتوا: %d', 'wp-qa-comments' ),
			$total_count
		);
		?>
	</p>

	<p>
		<label for="wpqa-post-json"><strong><?php esc_html_e( 'داده JSON', 'wp-qa-comments' ); ?></strong></label>
		<textarea id="wpqa-post-json" class="large-text code wpqa-post-json" rows="10" placeholder='[ { "name": "...", "question": "...", "answer": "..." } ]'></textarea>
	</p>

	<p>
		<label for="wpqa-post-json-file"><?php esc_html_e( 'یا انتخاب فایل JSON', 'wp-qa-comments' ); ?></label><br />
		<input type="file" id="wpqa-post-json-file" class="wpqa-post-json-file" accept=".json,application/json" />
	</p>

	<p class="wpqa-post-import-options">
		<label for="wpqa-post-default-status">
			<?php esc_html_e( 'وضعیت پیش‌فرض', 'wp-qa-comments' ); ?>
			<select id="wpqa-post-default-status" class="wpqa-post-default-status">
				<option value="approved"><?php esc_html_e( 'تأیید شده', 'wp-qa-comments' ); ?></option>
				<option value="pending"><?php esc_html_e( 'در انتظار', 'wp-qa-comments' ); ?></option>
				<option value="rejected"><?php esc_html_e( 'رد شده', 'wp-qa-comments' ); ?></option>
			</select>
		</label>
		&nbsp;&nbsp;
		<label>
			<input type="checkbox" class="wpqa-post-random-dates" value="1" />
			<?php esc_html_e( 'تولید تاریخ‌های تصادفی', 'wp-qa-comments' ); ?>
		</label>
		&nbsp;&nbsp;
		<label>
			<?php esc_html_e( 'تعداد روز:', 'wp-qa-comments' ); ?>
			<input type="number" class="wpqa-post-date-range small-text" value="90" min="1" max="3650" />
		</label>
	</p>

	<p>
		<button type="button" class="button button-primary wpqa-post-import-btn">
			<?php esc_html_e( 'درون‌ریزی برای این نوشته', 'wp-qa-comments' ); ?>
		</button>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wpqa-comments&post_id=' . (int) $post->ID ) ); ?>">
			<?php esc_html_e( 'مشاهده لیست پرسش و پاسخ', 'wp-qa-comments' ); ?>
		</a>
	</p>

	<div class="wpqa-post-import-report" hidden></div>

	<details class="wpqa-sample-json">
		<summary><?php esc_html_e( 'نمونه JSON (بدون نیاز به post_id)', 'wp-qa-comments' ); ?></summary>
		<pre class="wpqa-code-block"><?php echo esc_html( $sample ); ?></pre>
	</details>
</div>

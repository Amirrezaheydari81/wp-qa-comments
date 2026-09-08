<?php
/**
 * Admin settings view.
 *
 * @package WP_QA_Comments
 */

defined( 'ABSPATH' ) || exit;

$settings   = WPQA_Settings::get_all();
$post_types = WPQA_Settings::get_public_post_types();

WPQA_Admin::print_notice();
?>
<div class="wrap wpqa-admin-wrap">
	<h1><?php esc_html_e( 'تنظیمات پرسش و پاسخ', 'wp-qa-comments' ); ?></h1>

	<form method="post">
		<?php wp_nonce_field( 'wpqa_save_settings' ); ?>
		<input type="hidden" name="wpqa_save_settings" value="1" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'فعال‌سازی برای انواع محتوا', 'wp-qa-comments' ); ?></th>
				<td>
					<fieldset>
						<?php foreach ( $post_types as $pt ) : ?>
							<label style="display:block;margin-bottom:6px;">
								<input
									type="checkbox"
									name="enabled_post_types[]"
									value="<?php echo esc_attr( $pt->name ); ?>"
									<?php checked( in_array( $pt->name, (array) $settings['enabled_post_types'], true ) ); ?>
								/>
								<?php echo esc_html( $pt->labels->singular_name . ' (' . $pt->name . ')' ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description">
							<?php esc_html_e( 'اگر «نمایش خودکار» فعال باشد، بخش پرسش و پاسخ در انتهای این انواع محتوا نشان داده می‌شود.', 'wp-qa-comments' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpqa-per-page"><?php esc_html_e( 'تعداد سؤال در هر صفحه', 'wp-qa-comments' ); ?></label></th>
				<td>
					<input type="number" name="per_page" id="wpqa-per-page" value="<?php echo esc_attr( $settings['per_page'] ); ?>" min="1" max="100" class="small-text" />
					<p class="description"><?php esc_html_e( 'تعداد سؤالات تأییدشده قبل از دکمه «مشاهده سوالات بیشتر».', 'wp-qa-comments' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'نیاز به تأیید مدیر', 'wp-qa-comments' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="require_approval" value="1" <?php checked( ! empty( $settings['require_approval'] ) ); ?> />
						<?php esc_html_e( 'سؤالات جدید تا تأیید مدیر در انتظار بمانند', 'wp-qa-comments' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'نمایش فرم ارسال سؤال', 'wp-qa-comments' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="show_form" value="1" <?php checked( ! empty( $settings['show_form'] ) ); ?> />
						<?php esc_html_e( 'فرم ارسال سؤال در فرانت‌اند نمایش داده شود', 'wp-qa-comments' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'نمایش خودکار در محتوا', 'wp-qa-comments' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="auto_append" value="1" <?php checked( ! empty( $settings['auto_append'] ) ); ?> />
						<?php esc_html_e( 'بخش پرسش و پاسخ به‌صورت خودکار در انتهای نوشته‌ها/برگه‌های فعال نمایش داده شود', 'wp-qa-comments' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'همچنین می‌توانید از شورت‌کد [wpqa_comments] در هر جایی استفاده کنید.', 'wp-qa-comments' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'پاک‌سازی هنگام حذف افزونه', 'wp-qa-comments' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?> />
						<?php esc_html_e( 'جدول و تنظیمات افزونه هنگام حذف کامل پاک شوند', 'wp-qa-comments' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'فقط هنگام حذف افزونه از وردپرس اعمال می‌شود (نه هنگام غیرفعال‌سازی).', 'wp-qa-comments' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'ذخیره تنظیمات', 'wp-qa-comments' ) ); ?>
	</form>
</div>

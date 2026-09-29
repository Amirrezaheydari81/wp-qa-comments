<?php
/**
 * Admin settings view.
 *
 * @package WP_QA_Comments
 */

defined( 'ABSPATH' ) || exit;

$settings   = WPQA_Settings::get_all();
$post_types = WPQA_Settings::get_public_post_types();
$enabled    = (array) $settings['enabled_post_types'];
$has_post   = in_array( 'post', $enabled, true );
$has_page   = in_array( 'page', $enabled, true );
if ( $has_post && $has_page ) {
	$scope = 'both';
} elseif ( $has_page ) {
	$scope = 'page';
} elseif ( $has_post ) {
	$scope = 'post';
} else {
	$scope = 'both';
}

WPQA_Admin::print_notice();
?>
<div class="wrap wpqa-admin-wrap">
	<h1><?php esc_html_e( 'تنظیمات پرسش و پاسخ', 'wp-qa-comments' ); ?></h1>

	<form method="post">
		<?php wp_nonce_field( 'wpqa_save_settings' ); ?>
		<input type="hidden" name="wpqa_save_settings" value="1" />

		<h2><?php esc_html_e( 'پاسخ‌دهنده', 'wp-qa-comments' ); ?></h2>
		<p class="description"><?php esc_html_e( 'این نام و عکس بالای هر پاسخ در سایت دیده می‌شود. عکس را از کتابخانه رسانه انتخاب کنید.', 'wp-qa-comments' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wpqa-reply-author"><?php esc_html_e( 'نام پاسخ‌دهنده', 'wp-qa-comments' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" name="reply_author_name" id="wpqa-reply-author" value="<?php echo esc_attr( $settings['reply_author_name'] ); ?>" />
					<p class="description"><?php esc_html_e( 'اگر خالی باشد، برچسب «پاسخ مدیر سایت» نشان داده می‌شود.', 'wp-qa-comments' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'عکس پروفایل', 'wp-qa-comments' ); ?></th>
				<td>
					<?php
					$avatar_id = absint( $settings['reply_author_avatar'] );
					$avatar    = ! empty( $settings['reply_author_avatar_url'] ) ? $settings['reply_author_avatar_url'] : ( $avatar_id ? wp_get_attachment_image_url( $avatar_id, 'thumbnail' ) : '' );
					?>
					<div data-answer-profile data-title="<?php esc_attr_e( 'عکس پروفایل پاسخ‌دهنده', 'wp-qa-comments' ); ?>" data-button="<?php esc_attr_e( 'استفاده از این تصویر', 'wp-qa-comments' ); ?>">
						<input data-answer-id type="hidden" name="reply_author_avatar" value="<?php echo esc_attr( $avatar_id ? (string) $avatar_id : '' ); ?>" />
						<img data-answer-preview alt="" src="<?php echo esc_url( $avatar ); ?>" width="64" height="64" style="width:64px;height:64px;border-radius:50%;object-fit:cover;display:block;margin:0 0 8px;" <?php echo $avatar ? '' : 'hidden'; ?> />
						<button type="button" class="button" data-answer-select><?php esc_html_e( 'انتخاب از رسانه', 'wp-qa-comments' ); ?></button>
						<button type="button" class="button-link-delete" data-answer-remove <?php echo $avatar ? '' : 'hidden'; ?>><?php esc_html_e( 'حذف تصویر', 'wp-qa-comments' ); ?></button>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpqa-reply-role"><?php esc_html_e( 'سمت', 'wp-qa-comments' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" name="reply_author_role" id="wpqa-reply-role" value="<?php echo esc_attr( $settings['reply_author_role'] ); ?>" placeholder="<?php esc_attr_e( 'مثلاً وکیل پایه یک دادگستری', 'wp-qa-comments' ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpqa-reply-url"><?php esc_html_e( 'لینک پروفایل', 'wp-qa-comments' ); ?></label></th>
				<td>
					<input type="url" class="regular-text" name="reply_author_url" id="wpqa-reply-url" value="<?php echo esc_attr( $settings['reply_author_url'] ); ?>" placeholder="https://" />
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'نمایش و فرم', 'wp-qa-comments' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'نمایش پرسش و پاسخ', 'wp-qa-comments' ); ?></th>
				<td>
					<fieldset>
						<label style="display:block;margin-bottom:6px;">
							<input type="radio" name="display_scope" value="post" <?php checked( $scope, 'post' ); ?> />
							<?php esc_html_e( 'فقط نوشته‌ها', 'wp-qa-comments' ); ?>
						</label>
						<label style="display:block;margin-bottom:6px;">
							<input type="radio" name="display_scope" value="page" <?php checked( $scope, 'page' ); ?> />
							<?php esc_html_e( 'فقط برگه‌ها', 'wp-qa-comments' ); ?>
						</label>
						<label style="display:block;margin-bottom:6px;">
							<input type="radio" name="display_scope" value="both" <?php checked( $scope, 'both' ); ?> />
							<?php esc_html_e( 'نوشته و برگه', 'wp-qa-comments' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
			<?php
			$extra_types = $post_types;
			unset( $extra_types['post'], $extra_types['page'] );
			if ( $extra_types ) :
				?>
			<tr>
				<th scope="row"><?php esc_html_e( 'انواع محتوای دیگر', 'wp-qa-comments' ); ?></th>
				<td>
					<fieldset>
						<?php foreach ( $extra_types as $pt ) : ?>
							<label style="display:block;margin-bottom:6px;">
								<input
									type="checkbox"
									name="enabled_post_types[]"
									value="<?php echo esc_attr( $pt->name ); ?>"
									<?php checked( in_array( $pt->name, $enabled, true ) ); ?>
								/>
								<?php echo esc_html( $pt->labels->singular_name . ' (' . $pt->name . ')' ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
			<?php endif; ?>
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
						<?php esc_html_e( 'اگر هیچ سؤال تأییدشده‌ای وجود نداشته باشد، کادر اصلاً نمایش داده نمی‌شود. همچنین می‌توانید از شورت‌کد [wpqa_comments] استفاده کنید.', 'wp-qa-comments' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'کپچا ضد اسپم', 'wp-qa-comments' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="captcha_enabled" value="1" <?php checked( ! empty( $settings['captcha_enabled'] ) ); ?> />
						<?php esc_html_e( 'فعال بودن کپچا در فرم ارسال سؤال', 'wp-qa-comments' ); ?>
					</label>
					<p class="description" style="margin-top:10px;">
						<label for="wpqa-captcha-type"><?php esc_html_e( 'نوع کپچا', 'wp-qa-comments' ); ?></label><br />
						<select name="captcha_type" id="wpqa-captcha-type">
							<option value="image" <?php selected( $settings['captcha_type'], 'image' ); ?>><?php esc_html_e( 'کپچای تصویری نویزدار (پیشنهادی)', 'wp-qa-comments' ); ?></option>
							<option value="math" <?php selected( $settings['captcha_type'], 'math' ); ?>><?php esc_html_e( 'کپچای ریاضی ساده', 'wp-qa-comments' ); ?></option>
							<option value="turnstile" <?php selected( $settings['captcha_type'], 'turnstile' ); ?>><?php esc_html_e( 'Cloudflare Turnstile', 'wp-qa-comments' ); ?></option>
						</select>
					</p>
					<p class="description">
						<?php esc_html_e( 'همیشه یک فیلد honeypot مخفی هم برای جلوگیری از ربات‌های ساده فعال است.', 'wp-qa-comments' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'کلیدهای Turnstile', 'wp-qa-comments' ); ?></th>
				<td>
					<p>
						<label for="wpqa-turnstile-site"><?php esc_html_e( 'Site Key', 'wp-qa-comments' ); ?></label><br />
						<input type="text" class="regular-text" name="turnstile_site_key" id="wpqa-turnstile-site" value="<?php echo esc_attr( $settings['turnstile_site_key'] ); ?>" autocomplete="off" />
					</p>
					<p>
						<label for="wpqa-turnstile-secret"><?php esc_html_e( 'Secret Key', 'wp-qa-comments' ); ?></label><br />
						<input type="password" class="regular-text" name="turnstile_secret_key" id="wpqa-turnstile-secret" value="<?php echo esc_attr( $settings['turnstile_secret_key'] ); ?>" autocomplete="new-password" />
					</p>
					<p class="description">
						<?php esc_html_e( 'فقط وقتی نوع کپچا Turnstile باشد استفاده می‌شود. اگر کلیدها خالی باشند، به‌صورت خودکار به کپچای ریاضی برمی‌گردد.', 'wp-qa-comments' ); ?>
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

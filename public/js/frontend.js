/**
 * WP Q&A Comments — Vanilla JS frontend
 */
(function () {
	'use strict';

	if (typeof wpqaData === 'undefined') {
		return;
	}

	function qs(el, sel) {
		return el.querySelector(sel);
	}

	function showMessage(box, text, type) {
		if (!box) {
			return;
		}
		box.hidden = false;
		box.className = 'wpqa-message wpqa-message--' + type;
		box.textContent = text;
	}

	function postForm(action, data) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', wpqaData.nonce);

		Object.keys(data).forEach(function (key) {
			body.append(key, data[key]);
		});

		return fetch(wpqaData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		}).then(function (res) {
			return res.json();
		});
	}

	function applyCaptcha(form, captcha) {
		if (!form || !captcha) {
			return;
		}

		var token = qs(form, '[data-role="token"]');
		var answer = qs(form, '[name="wpqa_captcha_answer"]');
		if (token && captcha.token) {
			token.value = captcha.token;
		}
		if (answer) {
			answer.value = '';
		}

		if (captcha.type === 'image' || captcha.image_url) {
			var image = qs(form, '[data-role="image"]');
			if (image && captcha.image_url) {
				image.src = captcha.image_url;
			}
		}

		if (captcha.type === 'math' || captcha.question) {
			var question = qs(form, '[data-role="question"]');
			if (question && captcha.question) {
				question.textContent = captcha.question;
			}
		}
	}

	function resetTurnstile(form) {
		if (typeof turnstile === 'undefined' || !form) {
			return;
		}
		var widget = qs(form, '.cf-turnstile');
		if (!widget) {
			return;
		}
		try {
			turnstile.reset(widget);
		} catch (e) {
			// Ignore reset errors.
		}
	}

	function collectCaptchaPayload(form) {
		var payload = {
			wpqa_website: ''
		};
		var honey = qs(form, '[name="wpqa_website"]');
		if (honey) {
			payload.wpqa_website = honey.value;
		}

		if (wpqaData.captchaType === 'math' || wpqaData.captchaType === 'image') {
			var token = qs(form, '[name="wpqa_captcha_token"]');
			var answer = qs(form, '[name="wpqa_captcha_answer"]');
			payload.wpqa_captcha_token = token ? token.value : '';
			payload.wpqa_captcha_answer = answer ? answer.value.trim() : '';
		}

		if (wpqaData.captchaType === 'turnstile') {
			var ts = qs(form, '[name="cf-turnstile-response"]');
			payload['cf-turnstile-response'] = ts ? ts.value : '';
			payload.wpqa_turnstile_token = payload['cf-turnstile-response'];
		}

		return payload;
	}

	function bindCaptchaRefresh(form) {
		var btn = qs(form, '.wpqa-captcha-refresh');
		if (!btn || (wpqaData.captchaType !== 'math' && wpqaData.captchaType !== 'image')) {
			return;
		}

		btn.addEventListener('click', function (e) {
			e.preventDefault();
			btn.disabled = true;
			postForm('wpqa_refresh_captcha', {})
				.then(function (json) {
					if (json && json.success && json.data && json.data.captcha) {
						applyCaptcha(form, json.data.captcha);
					}
				})
				.finally(function () {
					btn.disabled = false;
				});
		});
	}

	function bindForm(wrap) {
		var form = qs(wrap, '.wpqa-form');
		if (!form) {
			return;
		}

		bindCaptchaRefresh(form);

		form.addEventListener('submit', function (e) {
			e.preventDefault();

			var nameInput = qs(form, '[name="name"]');
			var questionInput = qs(form, '[name="question"]');
			var postIdInput = qs(form, '[name="post_id"]');
			var submitBtn = qs(form, '.wpqa-submit');
			var messageBox = qs(form, '.wpqa-message');
			var list = qs(wrap, '.wpqa-list');

			var name = nameInput ? nameInput.value.trim() : '';
			var question = questionInput ? questionInput.value.trim() : '';
			var postId = postIdInput ? postIdInput.value : wrap.getAttribute('data-post-id');
			var captchaPayload = collectCaptchaPayload(form);

			if (!name) {
				showMessage(messageBox, wpqaData.i18n.nameRequired, 'error');
				return;
			}
			if (!question) {
				showMessage(messageBox, wpqaData.i18n.questionRequired, 'error');
				return;
			}
			if ((wpqaData.captchaType === 'math' || wpqaData.captchaType === 'image') && !captchaPayload.wpqa_captcha_answer) {
				showMessage(messageBox, wpqaData.i18n.captchaRequired, 'error');
				return;
			}

			submitBtn.disabled = true;
			var original = submitBtn.textContent;
			submitBtn.textContent = wpqaData.i18n.sending;

			var data = Object.assign(
				{
					post_id: postId,
					name: name,
					question: question
				},
				captchaPayload
			);

			postForm('wpqa_submit_question', data)
				.then(function (json) {
					if (!json || !json.success) {
						var err =
							json && json.data && json.data.message
								? json.data.message
								: wpqaData.i18n.error;
						showMessage(messageBox, err, 'error');
						if (json && json.data && json.data.captcha) {
							applyCaptcha(form, json.data.captcha);
						}
						resetTurnstile(form);
						return;
					}

					showMessage(messageBox, json.data.message, 'success');
					form.reset();

					if (json.data.captcha) {
						applyCaptcha(form, json.data.captcha);
					}
					resetTurnstile(form);

					if (json.data.approved && json.data.html && list) {
						var empty = qs(list, '.wpqa-empty');
						if (empty) {
							empty.remove();
						}
						list.insertAdjacentHTML('afterbegin', json.data.html);
					}
				})
				.catch(function () {
					showMessage(messageBox, wpqaData.i18n.error, 'error');
				})
				.finally(function () {
					submitBtn.disabled = false;
					submitBtn.textContent = original || wpqaData.i18n.submit;
				});
		});
	}

	function bindLoadMore(wrap) {
		var btn = qs(wrap, '.wpqa-load-more');
		if (!btn) {
			return;
		}

		btn.addEventListener('click', function () {
			var postId = wrap.getAttribute('data-post-id');
			var page = parseInt(wrap.getAttribute('data-page') || '1', 10) + 1;
			var list = qs(wrap, '.wpqa-list');
			var original = btn.textContent;

			btn.disabled = true;
			btn.textContent = wpqaData.i18n.loading;

			postForm('wpqa_load_more', {
				post_id: postId,
				page: page
			})
				.then(function (json) {
					if (!json || !json.success) {
						btn.disabled = false;
						btn.textContent = original || wpqaData.i18n.loadMore;
						return;
					}

					if (json.data.html && list) {
						list.insertAdjacentHTML('beforeend', json.data.html);
					}

					wrap.setAttribute('data-page', String(json.data.page));

					if (!json.data.has_more) {
						var wrapBtn = btn.parentElement;
						if (wrapBtn) {
							wrapBtn.remove();
						}
					} else {
						btn.disabled = false;
						btn.textContent = original || wpqaData.i18n.loadMore;
					}
				})
				.catch(function () {
					btn.disabled = false;
					btn.textContent = original || wpqaData.i18n.loadMore;
				});
		});
	}

	function init() {
		document.querySelectorAll('.wpqa-wrap').forEach(function (wrap) {
			bindForm(wrap);
			bindLoadMore(wrap);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

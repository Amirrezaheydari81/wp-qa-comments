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

	function bindForm(wrap) {
		var form = qs(wrap, '.wpqa-form');
		if (!form) {
			return;
		}

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

			if (!name) {
				showMessage(messageBox, wpqaData.i18n.nameRequired, 'error');
				return;
			}
			if (!question) {
				showMessage(messageBox, wpqaData.i18n.questionRequired, 'error');
				return;
			}

			submitBtn.disabled = true;
			var original = submitBtn.textContent;
			submitBtn.textContent = wpqaData.i18n.sending;

			postForm('wpqa_submit_question', {
				post_id: postId,
				name: name,
				question: question
			})
				.then(function (json) {
					if (!json || !json.success) {
						var err =
							json && json.data && json.data.message
								? json.data.message
								: wpqaData.i18n.error;
						showMessage(messageBox, err, 'error');
						return;
					}

					showMessage(messageBox, json.data.message, 'success');
					form.reset();

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

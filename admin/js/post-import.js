/**
 * صفحه ویرایش نوشته — درون‌ریزی JSON پرسش و پاسخ
 */
(function () {
	'use strict';

	if (typeof wpqaPostImport === 'undefined') {
		return;
	}

	function readFileAsText(file) {
		return new Promise(function (resolve, reject) {
			var reader = new FileReader();
			reader.onload = function () {
				resolve(reader.result || '');
			};
			reader.onerror = reject;
			reader.readAsText(file);
		});
	}

	function sprintfCount(template, count) {
		return String(template).replace('%d', String(count));
	}

	function renderReport(container, report) {
		var i18n = wpqaPostImport.i18n;
		var html = '<div class="notice notice-info inline wpqa-import-report">';
		html += '<p><strong>' + escapeHtml(reportTitle(report)) + '</strong></p>';
		html += '<ul>';
		html += '<li>' + escapeHtml(i18n.total) + ': ' + Number(report.total || 0) + '</li>';
		html += '<li>' + escapeHtml(i18n.imported) + ': ' + Number(report.imported || 0) + '</li>';
		html += '<li>' + escapeHtml(i18n.failed) + ': ' + Number(report.failed || 0) + '</li>';
		html += '</ul>';

		if (report.errors && report.errors.length) {
			html += '<p><strong>' + escapeHtml(i18n.errors) + '</strong></p><ul class="wpqa-import-errors">';
			report.errors.forEach(function (err) {
				html += '<li>' + escapeHtml(String(err)) + '</li>';
			});
			html += '</ul>';
		}

		html += '</div>';
		container.hidden = false;
		container.innerHTML = html;
	}

	function reportTitle(report) {
		var i18n = wpqaPostImport.i18n;
		if (report.imported > 0 && report.failed === 0) {
			return i18n.done;
		}
		if (report.imported > 0) {
			return i18n.donePartial;
		}
		return i18n.doneEmpty;
	}

	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function bindBox(box) {
		var btn = box.querySelector('.wpqa-post-import-btn');
		var textarea = box.querySelector('.wpqa-post-json');
		var fileInput = box.querySelector('.wpqa-post-json-file');
		var reportEl = box.querySelector('.wpqa-post-import-report');
		var countEl = box.querySelector('.wpqa-post-import-count');
		var statusEl = box.querySelector('.wpqa-post-default-status');
		var randomEl = box.querySelector('.wpqa-post-random-dates');
		var rangeEl = box.querySelector('.wpqa-post-date-range');
		var postId = box.getAttribute('data-post-id');

		if (!btn || !textarea) {
			return;
		}

		if (fileInput) {
			fileInput.addEventListener('change', function () {
				var file = fileInput.files && fileInput.files[0];
				if (!file) {
					return;
				}
				readFileAsText(file).then(function (text) {
					textarea.value = text;
				});
			});
		}

		btn.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();

			var json = (textarea.value || '').trim();
			if (!json) {
				reportEl.hidden = false;
				reportEl.innerHTML =
					'<div class="notice notice-error inline"><p>' +
					escapeHtml(wpqaPostImport.i18n.empty) +
					'</p></div>';
				return;
			}

			var original = btn.textContent;
			btn.disabled = true;
			btn.textContent = wpqaPostImport.i18n.importing;

			var body = new FormData();
			body.append('action', 'wpqa_import_for_post');
			body.append('nonce', wpqaPostImport.nonce);
			body.append('post_id', postId);
			body.append('wpqa_json', json);
			body.append('default_status', statusEl ? statusEl.value : 'approved');
			body.append('generate_random_dates', randomEl && randomEl.checked ? '1' : '');
			body.append('date_range_days', rangeEl ? rangeEl.value : '90');

			fetch(wpqaPostImport.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			})
				.then(function (res) {
					return res.json();
				})
				.then(function (jsonRes) {
					if (!jsonRes || !jsonRes.success) {
						var msg =
							jsonRes && jsonRes.data && jsonRes.data.message
								? jsonRes.data.message
								: wpqaPostImport.i18n.error;
						reportEl.hidden = false;
						reportEl.innerHTML =
							'<div class="notice notice-error inline"><p>' +
							escapeHtml(msg) +
							'</p></div>';
						return;
					}

					renderReport(reportEl, jsonRes.data.report || {});

					if (countEl && typeof jsonRes.data.total_count !== 'undefined') {
						countEl.textContent = sprintfCount(
							wpqaPostImport.i18n.countLabel,
							Number(jsonRes.data.total_count)
						);
					}
				})
				.catch(function () {
					reportEl.hidden = false;
					reportEl.innerHTML =
						'<div class="notice notice-error inline"><p>' +
						escapeHtml(wpqaPostImport.i18n.error) +
						'</p></div>';
				})
				.finally(function () {
					btn.disabled = false;
					btn.textContent = original || wpqaPostImport.i18n.import;
				});
		});
	}

	function init() {
		document.querySelectorAll('.wpqa-post-import').forEach(bindBox);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

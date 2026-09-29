(function () {
	var frame = null;
	var root = null;

	document.addEventListener('click', function (event) {
		var select = event.target.closest('[data-answer-select]');
		var remove = event.target.closest('[data-answer-remove]');

		if (!select && !remove) {
			return;
		}

		event.preventDefault();

		var container = (select || remove).closest('[data-answer-profile]');

		if (!container) {
			return;
		}

		var input = container.querySelector('[data-answer-id]');
		var image = container.querySelector('[data-answer-preview]');
		var removeButton = container.querySelector('[data-answer-remove]');

		if (remove) {
			if (input) {
				input.value = '';
			}
			if (image) {
				image.removeAttribute('src');
				image.hidden = true;
			}
			if (removeButton) {
				removeButton.hidden = true;
			}
			return;
		}

		if (typeof wp === 'undefined' || !wp.media) {
			return;
		}

		root = container;

		if (!frame) {
			frame = wp.media({
				title: container.getAttribute('data-title') || '',
				button: { text: container.getAttribute('data-button') || '' },
				library: { type: 'image' },
				multiple: false
			});

			frame.on('select', function () {
				if (!root) {
					return;
				}

				var attachment = frame.state().get('selection').first().toJSON();
				var idInput = root.querySelector('[data-answer-id]');
				var preview = root.querySelector('[data-answer-preview]');
				var removeBtn = root.querySelector('[data-answer-remove]');
				var url = attachment.url || '';

				if (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) {
					url = attachment.sizes.thumbnail.url;
				}

				if (idInput) {
					idInput.value = String(attachment.id || '');
				}

				if (preview && url) {
					preview.src = url;
					preview.hidden = false;
				}

				if (removeBtn) {
					removeBtn.hidden = false;
				}
			});
		}

		frame.open();
	});
})();

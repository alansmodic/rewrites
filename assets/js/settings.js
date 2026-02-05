/**
 * Settings page JavaScript for Rewrites plugin.
 */
(function ($) {
	'use strict';

	const { nonce, ajaxUrl, strings } = window.rewritesSettings || {};

	/**
	 * Initialize sortable for checklist items.
	 */
	function initSortable() {
		$('#rewrites-checklist-items').sortable({
			handle: '.rewrites-checklist-handle',
			placeholder: 'rewrites-checklist-placeholder',
			update: function () {
				updateIndices();
			},
		});
	}

	/**
	 * Update data-index attributes after sorting.
	 */
	function updateIndices() {
		$('.rewrites-checklist-item').each(function (index) {
			$(this).attr('data-index', index);
		});
	}

	/**
	 * Add a new checklist item.
	 */
	function addItem() {
		const index = $('.rewrites-checklist-item').length;
		const html = `
			<div class="rewrites-checklist-item" data-index="${index}">
				<span class="rewrites-checklist-handle dashicons dashicons-menu"></span>
				<input type="text" class="rewrites-checklist-label regular-text" value="" placeholder="Checklist item text...">
				<label class="rewrites-checklist-required">
					<input type="checkbox">
					Required
				</label>
				<button type="button" class="button rewrites-checklist-delete" title="Delete">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</div>
		`;
		$('#rewrites-checklist-items').append(html);
		$('#rewrites-checklist-items').sortable('refresh');
	}

	/**
	 * Delete a checklist item.
	 *
	 * @param {jQuery} $item The item to delete.
	 */
	function deleteItem($item) {
		if (confirm(strings.confirmDelete)) {
			$item.fadeOut(200, function () {
				$(this).remove();
				updateIndices();
			});
		}
	}

	/**
	 * Gather form data.
	 *
	 * @return {Object} Form data.
	 */
	function getFormData() {
		const enabled = $('#rewrites-checklist-enabled').is(':checked');
		const items = [];

		$('.rewrites-checklist-item').each(function () {
			const label = $(this).find('.rewrites-checklist-label').val().trim();
			const required = $(this).find('.rewrites-checklist-required input').is(':checked');

			if (label) {
				items.push({ label, required });
			}
		});

		return { enabled, items };
	}

	/**
	 * Save settings via AJAX.
	 */
	function saveSettings() {
		const $button = $('#rewrites-save-settings');
		const $status = $('#rewrites-settings-status');
		const data = getFormData();

		$button.prop('disabled', true).text('Saving...');
		$status.text('').removeClass('error');

		$.ajax({
			url: ajaxUrl,
			method: 'POST',
			data: {
				action: 'rewrites_save_checklist',
				nonce: nonce,
				enabled: data.enabled ? 1 : 0,
				items: data.items,
			},
			success: function (response) {
				if (response.success) {
					$status.text(strings.saved);
				} else {
					$status.text(response.data?.message || strings.error).addClass('error');
				}
			},
			error: function () {
				$status.text(strings.error).addClass('error');
			},
			complete: function () {
				$button.prop('disabled', false).text('Save Settings');
			},
		});
	}

	// Initialize on document ready.
	$(function () {
		initSortable();

		// Add item button.
		$('#rewrites-add-checklist-item').on('click', addItem);

		// Delete item button.
		$(document).on('click', '.rewrites-checklist-delete', function () {
			deleteItem($(this).closest('.rewrites-checklist-item'));
		});

		// Save settings form.
		$('#rewrites-settings-form').on('submit', function (e) {
			e.preventDefault();
			saveSettings();
		});
	});
})(jQuery);

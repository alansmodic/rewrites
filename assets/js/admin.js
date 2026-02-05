/**
 * Admin page JavaScript for Rewrites plugin.
 */
(function ($) {
	'use strict';

	const { apiBase, strings } = window.rewritesAdmin || {};

	/**
	 * Make API request.
	 *
	 * @param {string} endpoint API endpoint.
	 * @param {string} method   HTTP method.
	 * @return {Promise} API response.
	 */
	function apiRequest(endpoint, method = 'POST') {
		return wp.apiFetch({
			path: `rewrites/v1/${endpoint}`,
			method: method,
		});
	}

	/**
	 * Handle action button click.
	 *
	 * @param {jQuery}   $button     The button element.
	 * @param {string}   action      The action to perform.
	 * @param {string}   confirmMsg  Confirmation message.
	 * @param {string}   loadingText Loading button text.
	 * @param {Function} onSuccess   Success callback.
	 */
	function handleAction($button, action, confirmMsg, loadingText, onSuccess) {
		if (confirmMsg && !confirm(confirmMsg)) {
			return;
		}

		const revisionId = $button.data('revision');
		const $row = $button.closest('tr');
		const originalText = $button.text();

		$button.text(loadingText).prop('disabled', true);
		$row.find('button').prop('disabled', true);

		apiRequest(`staged/${revisionId}/${action}`)
			.then((response) => {
				if (onSuccess) {
					onSuccess(response, $row);
				}
			})
			.catch((error) => {
				alert(error.message || strings.error);
				$button.text(originalText).prop('disabled', false);
				$row.find('button').prop('disabled', false);
			});
	}

	/**
	 * Update status badge.
	 *
	 * @param {jQuery} $row   The table row.
	 * @param {string} status The new status.
	 */
	function updateStatus($row, status) {
		const statusLabels = {
			pending: 'Pending',
			approved: 'Approved',
			rejected: 'Rejected',
		};

		const $statusCell = $row.find('.column-status');
		$statusCell.html(
			`<span class="rewrites-status rewrites-status--${status}">${statusLabels[status] || status}</span>`
		);
	}

	// Approve button handler.
	$(document).on('click', '.rewrites-approve', function () {
		const $button = $(this);

		handleAction($button, 'approve', null, strings.approving, (response, $row) => {
			updateStatus($row, 'approved');
			$button.remove();
			$row.find('button').prop('disabled', false);
		});
	});

	// Publish button handler.
	$(document).on('click', '.rewrites-publish', function () {
		const $button = $(this);

		handleAction($button, 'publish', strings.confirmPublish, strings.publishing, (response, $row) => {
			$row.fadeOut(300, function () {
				$(this).remove();

				// Check if table is now empty.
				if ($('#rewrites-staged-list tr').length === 0) {
					location.reload();
				}
			});
		});
	});

	// Reject button handler.
	$(document).on('click', '.rewrites-reject', function () {
		const $button = $(this);

		handleAction($button, 'reject', strings.confirmReject, strings.rejecting, (response, $row) => {
			updateStatus($row, 'rejected');
			$row.find('.rewrites-approve, .rewrites-publish, .rewrites-reject').remove();
			$row.find('button').prop('disabled', false);
		});
	});

	// Discard button handler.
	$(document).on('click', '.rewrites-discard', function () {
		if (!confirm(strings.confirmDiscard)) {
			return;
		}

		const $button = $(this);
		const revisionId = $button.data('revision');
		const $row = $button.closest('tr');

		$button.text('Discarding...').prop('disabled', true);
		$row.find('button').prop('disabled', true);

		wp.apiFetch({
			path: `rewrites/v1/staged/revision/${revisionId}`,
			method: 'DELETE',
		})
			.then(() => {
				$row.fadeOut(300, function () {
					$(this).remove();

					// Check if table is now empty.
					if ($('#rewrites-staged-list tr').length === 0) {
						location.reload();
					}
				});
			})
			.catch((error) => {
				alert(error.message || strings.error);
				$button.text('Discard').prop('disabled', false);
				$row.find('button').prop('disabled', false);
			});
	});
})(jQuery);

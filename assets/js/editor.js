/**
 * Block editor integration for Rewrites plugin.
 */
(function () {
	'use strict';

	const { registerPlugin } = wp.plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
	const { useSelect, useDispatch, subscribe } = wp.data;
	const { useState, useEffect, useCallback, useRef } = wp.element;
	const {
		Button,
		PanelBody,
		DateTimePicker,
		TextareaControl,
		Notice,
		Spinner,
		Flex,
		FlexItem,
		Modal,
		CheckboxControl,
	} = wp.components;
	const { __ } = wp.i18n;
	const apiFetch = wp.apiFetch;

	// Get configuration from localized data.
	const config = window.rewritesData || {};
	const { checklistEnabled, checklistItems, strings } = config;

	/**
	 * Checklist Modal Component.
	 */
	function ChecklistModal({ isOpen, onClose, onSaveRewrite, onPublish, isSaving }) {
		const [checkedItems, setCheckedItems] = useState({});
		const [error, setError] = useState(null);

		// Reset checked items when modal opens.
		useEffect(() => {
			if (isOpen) {
				setCheckedItems({});
				setError(null);
			}
		}, [isOpen]);

		if (!isOpen) return null;

		const handleCheckChange = (index, checked) => {
			setCheckedItems((prev) => ({ ...prev, [index]: checked }));
		};

		const allRequiredChecked = () => {
			return checklistItems.every((item, index) => {
				if (item.required) {
					return checkedItems[index] === true;
				}
				return true;
			});
		};

		const handlePublish = () => {
			if (!allRequiredChecked()) {
				setError(strings.requiredItems);
				return;
			}
			setError(null);
			onPublish();
		};

		return wp.element.createElement(
			Modal,
			{
				title: strings.checklistTitle,
				onRequestClose: onClose,
				className: 'rewrites-checklist-modal',
				isDismissible: !isSaving,
			},
			wp.element.createElement(
				'div',
				{ className: 'rewrites-checklist-content' },
				wp.element.createElement('p', { className: 'rewrites-checklist-subtitle' }, strings.checklistSubtitle),

				error &&
					wp.element.createElement(
						Notice,
						{ status: 'error', isDismissible: false, className: 'rewrites-checklist-error' },
						error
					),

				wp.element.createElement(
					'div',
					{ className: 'rewrites-checklist-items' },
					checklistItems.map((item, index) =>
						wp.element.createElement(
							'div',
							{ key: index, className: 'rewrites-checklist-item' },
							wp.element.createElement(CheckboxControl, {
								label: wp.element.createElement(
									'span',
									null,
									item.label,
									item.required &&
										wp.element.createElement(
											'span',
											{ className: 'rewrites-required-indicator' },
											' *'
										)
								),
								checked: checkedItems[index] || false,
								onChange: (checked) => handleCheckChange(index, checked),
								disabled: isSaving,
							})
						)
					)
				),

				wp.element.createElement(
					'div',
					{ className: 'rewrites-checklist-actions' },
					wp.element.createElement(
						Flex,
						{ justify: 'flex-end', gap: 3 },
						wp.element.createElement(
							Button,
							{
								variant: 'secondary',
								onClick: onSaveRewrite,
								isBusy: isSaving === 'rewrite',
								disabled: isSaving,
							},
							strings.saveAsRewrite
						),
						wp.element.createElement(
							Button,
							{
								variant: 'primary',
								onClick: handlePublish,
								isBusy: isSaving === 'publish',
								disabled: isSaving,
							},
							strings.confirmAndPublish
						)
					)
				)
			),

			// Modal styles.
			wp.element.createElement(
				'style',
				null,
				`
				.rewrites-checklist-modal {
					max-width: 500px;
				}
				.rewrites-checklist-subtitle {
					color: #646970;
					margin-bottom: 16px;
				}
				.rewrites-checklist-items {
					margin-bottom: 24px;
				}
				.rewrites-checklist-item {
					padding: 8px 0;
					border-bottom: 1px solid #f0f0f1;
				}
				.rewrites-checklist-item:last-child {
					border-bottom: none;
				}
				.rewrites-required-indicator {
					color: #d63638;
					font-weight: 600;
				}
				.rewrites-checklist-error {
					margin-bottom: 16px;
				}
				.rewrites-checklist-actions {
					padding-top: 16px;
					border-top: 1px solid #ddd;
				}
			`
			)
		);
	}

	/**
	 * Main plugin component with checklist integration.
	 */
	function RewritesPlugin() {
		const [stagedRevision, setStagedRevision] = useState(null);
		const [isLoading, setIsLoading] = useState(false);
		const [isSaving, setIsSaving] = useState(false);
		const [isPublishing, setIsPublishing] = useState(false);
		const [isScheduling, setIsScheduling] = useState(false);
		const [error, setError] = useState(null);
		const [success, setSuccess] = useState(null);
		const [notes, setNotes] = useState('');
		const [scheduleDate, setScheduleDate] = useState(null);
		const [showScheduler, setShowScheduler] = useState(false);

		// Checklist modal state.
		const [showChecklist, setShowChecklist] = useState(false);
		const [checklistSaving, setChecklistSaving] = useState(null);
		const pendingSaveRef = useRef(null);

		// Track if post has become published (for showing UI after first publish).
		const [isPostPublished, setIsPostPublished] = useState(false);
		const previousStatusRef = useRef(null);

		const { createSuccessNotice, createErrorNotice } = useDispatch('core/notices');
		const { lockPostSaving, unlockPostSaving } = useDispatch('core/editor');

		// Get post data from editor.
		const { postId, postStatus, editedTitle, editedContent, editedExcerpt, isSavingPost, isAutosavingPost } =
			useSelect((select) => {
				const editor = select('core/editor');
				return {
					postId: editor.getCurrentPostId(),
					postStatus: editor.getEditedPostAttribute('status'),
					editedTitle: editor.getEditedPostAttribute('title'),
					editedContent: editor.getEditedPostAttribute('content'),
					editedExcerpt: editor.getEditedPostAttribute('excerpt'),
					isSavingPost: editor.isSavingPost(),
					isAutosavingPost: editor.isAutosavingPost(),
				};
			});

		// Track when post becomes published (including after first publish without page reload).
		useEffect(() => {
			if (postStatus === 'publish' && previousStatusRef.current !== 'publish') {
				setIsPostPublished(true);
			}
			previousStatusRef.current = postStatus;
		}, [postStatus]);

		// Also set published state if post was already published on load.
		useEffect(() => {
			if (postStatus === 'publish') {
				setIsPostPublished(true);
			}
		}, []);

		// Intercept save for published posts when checklist is enabled.
		useEffect(() => {
			if (!checklistEnabled || !isPostPublished) {
				return;
			}

			// Lock post saving initially so we can intercept.
			let isLocked = false;
			let wasTriggeredByUs = false;

			const interceptSave = () => {
				// Find the Update button and intercept clicks.
				const updateButton = document.querySelector('.editor-post-publish-button');
				if (!updateButton) return;

				const handleClick = (e) => {
					// If we triggered the save, let it through.
					if (wasTriggeredByUs) {
						wasTriggeredByUs = false;
						return;
					}

					// If checklist modal is already showing, ignore.
					if (showChecklist) {
						e.preventDefault();
						e.stopPropagation();
						return;
					}

					// Prevent default save and show checklist.
					e.preventDefault();
					e.stopPropagation();
					setShowChecklist(true);
				};

				// Add our click handler with capture to intercept before Gutenberg.
				updateButton.addEventListener('click', handleClick, true);

				return () => {
					updateButton.removeEventListener('click', handleClick, true);
				};
			};

			// Wait for DOM to be ready.
			const timeoutId = setTimeout(interceptSave, 500);

			// Store reference to trigger real save later.
			pendingSaveRef.current = () => {
				wasTriggeredByUs = true;
				const updateButton = document.querySelector('.editor-post-publish-button');
				if (updateButton) {
					updateButton.click();
				}
			};

			return () => {
				clearTimeout(timeoutId);
			};
		}, [checklistEnabled, isPostPublished, showChecklist]);

		// Fetch existing staged revision on mount or when post becomes published.
		useEffect(() => {
			if (!postId || !isPostPublished) return;

			setIsLoading(true);
			apiFetch({ path: `/rewrites/v1/staged/${postId}` })
				.then((response) => {
					setStagedRevision(response);
					setNotes(response.notes || '');
					if (response.scheduled_date) {
						setScheduleDate(new Date(response.scheduled_date));
					}
				})
				.catch(() => {
					setStagedRevision(null);
				})
				.finally(() => {
					setIsLoading(false);
				});
		}, [postId, isPostPublished]);

		// Only show for published posts (including freshly published).
		if (!isPostPublished) {
			return null;
		}

		/**
		 * Save changes as staged revision (from checklist modal).
		 */
		const handleChecklistSaveRewrite = async () => {
			setChecklistSaving('rewrite');

			try {
				const response = await apiFetch({
					path: `/rewrites/v1/staged/${postId}`,
					method: 'POST',
					data: {
						title: editedTitle,
						content: editedContent,
						excerpt: editedExcerpt,
						notes: '',
					},
				});

				setStagedRevision(response);
				createSuccessNotice(__('Changes saved as rewrite for review.', 'rewrites'), {
					type: 'snackbar',
				});
				setShowChecklist(false);
			} catch (err) {
				createErrorNotice(err.message || __('Failed to save rewrite.', 'rewrites'), {
					type: 'snackbar',
				});
			} finally {
				setChecklistSaving(null);
			}
		};

		/**
		 * Publish immediately after checklist confirmation.
		 */
		const handleChecklistPublish = async () => {
			setChecklistSaving('publish');
			setShowChecklist(false);

			// Trigger the actual WordPress save.
			if (pendingSaveRef.current) {
				pendingSaveRef.current();
			}

			setChecklistSaving(null);
		};

		/**
		 * Save changes as staged revision (from sidebar).
		 */
		const handleSaveStaged = async () => {
			setIsSaving(true);
			setError(null);
			setSuccess(null);

			try {
				const response = await apiFetch({
					path: `/rewrites/v1/staged/${postId}`,
					method: 'POST',
					data: {
						title: editedTitle,
						content: editedContent,
						excerpt: editedExcerpt,
						notes: notes,
					},
				});

				setStagedRevision(response);
				setSuccess(__('Changes saved without publishing.', 'rewrites'));
				createSuccessNotice(__('Changes saved as staged revision.', 'rewrites'), {
					type: 'snackbar',
				});
			} catch (err) {
				setError(err.message || __('Failed to save staged revision.', 'rewrites'));
				createErrorNotice(err.message || __('Failed to save staged revision.', 'rewrites'), {
					type: 'snackbar',
				});
			} finally {
				setIsSaving(false);
			}
		};

		/**
		 * Publish staged revision immediately.
		 */
		const handlePublish = async () => {
			if (!stagedRevision) return;

			if (!confirm(__('Are you sure you want to publish these changes now?', 'rewrites'))) {
				return;
			}

			setIsPublishing(true);
			setError(null);

			try {
				await apiFetch({
					path: `/rewrites/v1/staged/${stagedRevision.id}/publish`,
					method: 'POST',
				});

				setStagedRevision(null);
				setNotes('');
				setScheduleDate(null);
				createSuccessNotice(__('Staged changes published successfully.', 'rewrites'), {
					type: 'snackbar',
				});

				window.location.reload();
			} catch (err) {
				setError(err.message || __('Failed to publish staged revision.', 'rewrites'));
			} finally {
				setIsPublishing(false);
			}
		};

		/**
		 * Schedule staged revision for future publishing.
		 */
		const handleSchedule = async () => {
			if (!stagedRevision || !scheduleDate) return;

			setIsScheduling(true);
			setError(null);

			try {
				const response = await apiFetch({
					path: `/rewrites/v1/staged/${stagedRevision.id}/schedule`,
					method: 'POST',
					data: {
						publish_date: scheduleDate.toISOString(),
					},
				});

				setStagedRevision(response);
				setShowScheduler(false);
				createSuccessNotice(__('Staged revision scheduled for publishing.', 'rewrites'), {
					type: 'snackbar',
				});
			} catch (err) {
				setError(err.message || __('Failed to schedule staged revision.', 'rewrites'));
			} finally {
				setIsScheduling(false);
			}
		};

		/**
		 * Discard staged revision.
		 */
		const handleDiscard = async () => {
			if (!stagedRevision) return;

			if (!confirm(__('Are you sure you want to discard these changes? This cannot be undone.', 'rewrites'))) {
				return;
			}

			try {
				await apiFetch({
					path: `/rewrites/v1/staged/revision/${stagedRevision.id}`,
					method: 'DELETE',
				});

				setStagedRevision(null);
				setNotes('');
				setScheduleDate(null);
				createSuccessNotice(__('Staged revision discarded.', 'rewrites'), {
					type: 'snackbar',
				});
			} catch (err) {
				setError(err.message || __('Failed to discard staged revision.', 'rewrites'));
			}
		};

		/**
		 * Get status badge element.
		 */
		const getStatusBadge = () => {
			if (!stagedRevision) return null;

			const status = stagedRevision.status || 'pending';
			const statusColors = {
				pending: '#f0b849',
				approved: '#4ab866',
				rejected: '#d63638',
			};
			const statusLabels = {
				pending: __('Pending Review', 'rewrites'),
				approved: __('Approved', 'rewrites'),
				rejected: __('Rejected', 'rewrites'),
			};

			return wp.element.createElement(
				'span',
				{
					style: {
						backgroundColor: statusColors[status] || '#888',
						color: '#fff',
						padding: '2px 8px',
						borderRadius: '3px',
						fontSize: '11px',
						fontWeight: '600',
						textTransform: 'uppercase',
					},
				},
				statusLabels[status] || status
			);
		};

		// Render sidebar content.
		const sidebarContent = wp.element.createElement(
			wp.element.Fragment,
			null,
			error &&
				wp.element.createElement(
					Notice,
					{ status: 'error', isDismissible: true, onDismiss: () => setError(null) },
					error
				),

			success &&
				wp.element.createElement(
					Notice,
					{ status: 'success', isDismissible: true, onDismiss: () => setSuccess(null) },
					success
				),

			isLoading &&
				wp.element.createElement(
					'div',
					{ style: { padding: '20px', textAlign: 'center' } },
					wp.element.createElement(Spinner, null)
				),

			!isLoading &&
				wp.element.createElement(
					PanelBody,
					{ title: __('Save Without Publishing', 'rewrites'), initialOpen: true },
					wp.element.createElement(
						'p',
						null,
						__(
							'Save your changes without making them live immediately. An editor can review and approve before publishing.',
							'rewrites'
						)
					),
					wp.element.createElement(TextareaControl, {
						label: __('Notes for reviewers', 'rewrites'),
						value: notes,
						onChange: setNotes,
						placeholder: __('Describe your changes...', 'rewrites'),
					}),
					wp.element.createElement(Button, {
						variant: 'secondary',
						onClick: handleSaveStaged,
						isBusy: isSaving,
						disabled: isSaving,
						style: { width: '100%' },
						children: stagedRevision
							? __('Update Staged Changes', 'rewrites')
							: __('Save Without Publishing', 'rewrites'),
					})
				),

			!isLoading &&
				stagedRevision &&
				wp.element.createElement(
					PanelBody,
					{ title: __('Pending Changes', 'rewrites'), initialOpen: true },
					wp.element.createElement(
						Flex,
						{ justify: 'space-between', style: { marginBottom: '12px' } },
						wp.element.createElement(FlexItem, null, __('Status:', 'rewrites')),
						wp.element.createElement(FlexItem, null, getStatusBadge())
					),
					wp.element.createElement(
						'p',
						null,
						__('Last saved:', 'rewrites'),
						' ',
						wp.element.createElement('strong', null, new Date(stagedRevision.modified).toLocaleString())
					),

					stagedRevision.scheduled_date &&
						wp.element.createElement(
							Notice,
							{ status: 'warning', isDismissible: false },
							__('Scheduled for:', 'rewrites'),
							' ',
							new Date(stagedRevision.scheduled_date).toLocaleString()
						),

					!showScheduler &&
						wp.element.createElement(
							Button,
							{
								variant: 'secondary',
								onClick: () => setShowScheduler(true),
								style: { width: '100%', marginTop: '12px' },
							},
							stagedRevision.scheduled_date
								? __('Change Schedule', 'rewrites')
								: __('Schedule Publication', 'rewrites')
						),

					showScheduler &&
						wp.element.createElement(
							'div',
							{ style: { marginTop: '12px' } },
							wp.element.createElement(DateTimePicker, {
								currentDate: scheduleDate,
								onChange: setScheduleDate,
								is12Hour: true,
							}),
							wp.element.createElement(
								Flex,
								{ style: { marginTop: '12px' } },
								wp.element.createElement(
									FlexItem,
									null,
									wp.element.createElement(Button, {
										variant: 'primary',
										onClick: handleSchedule,
										isBusy: isScheduling,
										disabled: isScheduling || !scheduleDate,
										children: __('Schedule', 'rewrites'),
									})
								),
								wp.element.createElement(
									FlexItem,
									null,
									wp.element.createElement(Button, {
										variant: 'tertiary',
										onClick: () => setShowScheduler(false),
										children: __('Cancel', 'rewrites'),
									})
								)
							)
						),

					wp.element.createElement(
						'div',
						{ style: { marginTop: '16px', borderTop: '1px solid #ddd', paddingTop: '16px' } },
						wp.element.createElement(
							Flex,
							{ direction: 'column', gap: 2 },
							wp.element.createElement(Button, {
								variant: 'primary',
								onClick: handlePublish,
								isBusy: isPublishing,
								disabled: isPublishing || stagedRevision.status === 'rejected',
								style: { width: '100%' },
								children: __('Publish Now', 'rewrites'),
							}),
							wp.element.createElement(Button, {
								variant: 'tertiary',
								isDestructive: true,
								onClick: handleDiscard,
								style: { width: '100%' },
								children: __('Discard Changes', 'rewrites'),
							})
						)
					)
				)
		);

		return wp.element.createElement(
			wp.element.Fragment,
			null,
			// Checklist modal.
			checklistEnabled &&
				wp.element.createElement(ChecklistModal, {
					isOpen: showChecklist,
					onClose: () => setShowChecklist(false),
					onSaveRewrite: handleChecklistSaveRewrite,
					onPublish: handleChecklistPublish,
					isSaving: checklistSaving,
				}),

			// Menu item to open sidebar.
			wp.element.createElement(
				PluginSidebarMoreMenuItem,
				{ target: 'rewrites-sidebar' },
				__('Rewrites', 'rewrites')
			),

			// Sidebar panel.
			wp.element.createElement(
				PluginSidebar,
				{
					name: 'rewrites-sidebar',
					title: __('Rewrites', 'rewrites'),
					icon: 'backup',
				},
				sidebarContent
			)
		);
	}

	// Register the plugin.
	registerPlugin('rewrites', {
		render: RewritesPlugin,
		icon: 'backup',
	});
})();

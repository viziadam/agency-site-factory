(function (wp, config) {
	'use strict';

	if (!wp || !config || !wp.editPost || !wp.plugins || !wp.apiFetch) {
		return;
	}

	var el = wp.element.createElement;
	var registry = config.components || {};
	var strings = config.strings || {};
	var previewTimer = null;
	var previewRequestId = 0;
	var previewAbortController = null;
	var previewMountTimer = null;

	function normalizeSection(section) {
		var component = section && section.component ? String(section.component) : '';
		var variant = section && section.variant ? String(section.variant) : '';

		if (!variant && component.indexOf('/') !== -1) {
			var parts = component.split('/');
			component = parts[0];
			var template = parts[1] || '';
			var prefix = component + '-';
			variant = template.indexOf(prefix) === 0 ? template.slice(prefix.length) : template;
		}

		if (!registry[component] || !registry[component].variants || !registry[component].variants[variant]) {
			return null;
		}

		return {
			component: component,
			variant: variant,
			version: registry[component].version || 1,
			data: section.data && typeof section.data === 'object' ? section.data : {}
		};
	}

	function parseSections(raw) {
		if (!raw) {
			return { items: [], invalid: false };
		}
		try {
			var parsed = JSON.parse(raw);
			if (!Array.isArray(parsed)) {
				return { items: [], invalid: true };
			}
			var normalized = parsed.map(normalizeSection).filter(Boolean);
			return { items: normalized, invalid: normalized.length !== parsed.length };
		} catch (error) {
			return { items: [], invalid: true };
		}
	}

	function fieldsFor(component, variant) {
		var definition = registry[component] || {};
		var variantDefinition = definition.variants && definition.variants[variant] ? definition.variants[variant] : {};
		return Object.assign({}, definition.fields || {}, variantDefinition.fields || {});
	}

	function defaultsFor(component, variant, existingData) {
		var definition = registry[component] || { variants: {}, version: 1 };
		var selectedVariant = variant || definition.default_variant || Object.keys(definition.variants || {})[0] || '';
		var variantDefinition = definition.variants && definition.variants[selectedVariant] ? definition.variants[selectedVariant] : {};
		var data = Object.assign({}, definition.default_data || {}, variantDefinition.default_data || {}, existingData || {});
		var fields = fieldsFor(component, selectedVariant);

		Object.keys(fields).forEach(function (key) {
			if (!Object.prototype.hasOwnProperty.call(data, key) && Object.prototype.hasOwnProperty.call(fields[key], 'default')) {
				data[key] = fields[key].default;
			}
		});

		return {
			component: component,
			variant: selectedVariant,
			version: definition.version || 1,
			data: data
		};
	}

	function editorDocument() {
		var iframe = document.querySelector('iframe[name="editor-canvas"]');
		if (iframe && iframe.contentDocument) {
			return iframe.contentDocument;
		}
		return document;
	}

	function previewMount() {
		var doc = editorDocument();
		var canvas = doc.querySelector('.editor-styles-wrapper, .block-editor-writing-flow, .block-editor-block-list__layout');
		if (!canvas) {
			return null;
		}
		var wrapper = doc.querySelector('[data-agency-live-preview]');
		if (!wrapper) {
			wrapper = doc.createElement('div');
			wrapper.className = 'agency-core-live-preview';
			wrapper.setAttribute('data-agency-live-preview', 'true');
			var frame = doc.createElement('iframe');
			frame.className = 'agency-core-live-preview__frame';
			frame.setAttribute('title', strings.previewTitle);
			frame.setAttribute('sandbox', 'allow-scripts allow-same-origin');
			wrapper.appendChild(frame);
			canvas.insertBefore(wrapper, canvas.firstChild);
		}
		var blockList = doc.querySelector('.block-editor-block-list__layout');
		if (blockList && !blockList.contains(wrapper)) {
			blockList.classList.add('agency-core-editor-blocks-hidden');
		}
		return wrapper.querySelector('iframe');
	}

	function removePreview() {
		if (previewTimer) {
			clearTimeout(previewTimer);
			previewTimer = null;
		}
		if (previewMountTimer) {
			clearTimeout(previewMountTimer);
			previewMountTimer = null;
		}
		if (previewAbortController) {
			previewAbortController.abort();
			previewAbortController = null;
		}
		var docs = [document];
		var iframe = document.querySelector('iframe[name="editor-canvas"]');
		if (iframe && iframe.contentDocument) {
			docs.push(iframe.contentDocument);
		}
		docs.forEach(function (doc) {
			var wrapper = doc.querySelector('[data-agency-live-preview]');
			if (wrapper) {
				wrapper.remove();
			}
			var blockList = doc.querySelector('.agency-core-editor-blocks-hidden');
			if (blockList) {
				blockList.classList.remove('agency-core-editor-blocks-hidden');
			}
		});
	}

	function messageDocument(message, status) {
		var safe = String(message || '').replace(/[&<>"']/g, function (character) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#039;' }[character];
		});
		return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;padding:32px;background:#fffaf4;color:#17110d;font:15px/1.6 system-ui,sans-serif}.box{max-width:720px;margin:40px auto;padding:24px;border:1px solid #d4ad32;border-radius:14px;background:#fff}.error{border-color:#b32d2e}</style></head><body><div class="box ' + (status === 'error' ? 'error' : '') + '">' + safe + '</div></body></html>';
	}

	function setPreviewDocument(html) {
		var frame = previewMount();
		if (!frame) {
			if (previewMountTimer) {
				clearTimeout(previewMountTimer);
			}
			previewMountTimer = setTimeout(function () {
				setPreviewDocument(html);
			}, 100);
			return;
		}
		frame.srcdoc = html;
	}

	function schedulePreview(postId, sections) {
		if (previewTimer) {
			clearTimeout(previewTimer);
		}
		setPreviewDocument(messageDocument(strings.previewLoading, 'loading'));
		previewTimer = setTimeout(function () {
			var requestId = ++previewRequestId;
			if (previewAbortController) {
				previewAbortController.abort();
			}
			previewAbortController = typeof AbortController !== 'undefined' ? new AbortController() : null;
			var request = {
				path: config.previewPath,
				method: 'POST',
				data: { post_id: postId, sections: sections },
				headers: { 'X-WP-Nonce': config.nonce }
			};
			if (previewAbortController) {
				request.signal = previewAbortController.signal;
			}

			wp.apiFetch(request).then(function (response) {
				if (requestId === previewRequestId && response && response.html) {
					setPreviewDocument(response.html);
				}
			}).catch(function (error) {
				if (error && error.name === 'AbortError') {
					return;
				}
				if (requestId === previewRequestId) {
					var detail = error && error.message ? ' ' + error.message : '';
					setPreviewDocument(messageDocument(strings.previewError + detail, 'error'));
					window.console.error('Agency live preview:', error);
				}
			});
		}, Number(config.debounceMs) || 400);
	}

	function optionList(options) {
		if (Array.isArray(options)) {
			return options;
		}
		return Object.keys(options || {}).map(function (value) {
			return { value: value, label: options[value] };
		});
	}

	function RepeaterControl(props) {
		var serialized = JSON.stringify(Array.isArray(props.value) ? props.value : [], null, 2);
		var state = wp.element.useState(serialized);
		var raw = state[0];
		var setRaw = state[1];
		wp.element.useEffect(function () {
			setRaw(serialized);
		}, [serialized]);
		return el(wp.components.TextareaControl, {
			label: props.field.label,
			help: strings.repeaterHelp,
			value: raw,
			onChange: function (value) {
				setRaw(value);
				try {
					var parsed = JSON.parse(value);
					if (Array.isArray(parsed)) {
						props.onChange(parsed);
					}
				} catch (error) {
					// Partial JSON remains local until it becomes valid.
				}
			}
		});
	}

	function FieldControl(props) {
		var field = props.field;
		if (field.type === 'select') {
			return el(wp.components.SelectControl, {
				label: field.label,
				value: props.value === undefined ? '' : props.value,
				options: optionList(field.options),
				onChange: props.onChange
			});
		}
		if (field.type === 'checkbox') {
			return el(wp.components.ToggleControl, {
				label: field.label,
				checked: !!props.value,
				onChange: props.onChange
			});
		}
		if (field.type === 'repeater') {
			return el(RepeaterControl, props);
		}

		var common = {
			label: field.label,
			value: props.value === undefined ? '' : props.value,
			onChange: props.onChange
		};
		if (field.type === 'textarea') {
			return el(wp.components.TextareaControl, common);
		}
		if (field.type === 'number' || field.type === 'image_id') {
			common.type = 'number';
			common.min = 0;
		}
		if (field.type === 'url' || field.type === 'image_url') {
			common.type = 'url';
		}
		if (field.type === 'email') {
			common.type = 'email';
		}
		return el(wp.components.TextControl, common);
	}

	function SectionEditor() {
		var editorState = wp.data.useSelect(function (select) {
			return {
				meta: select('core/editor').getEditedPostAttribute('meta') || {},
				postId: select('core/editor').getCurrentPostId() || config.postId
			};
		}, []);
		var meta = editorState.meta;
		var postId = editorState.postId;
		var editPost = wp.data.useDispatch('core/editor').editPost;
		var parsed = parseSections(meta._agency_sections);
		var sections = parsed.items;
		var componentOptions = Object.keys(registry).filter(function (key) {
			return registry[key].section_allowed !== false;
		}).map(function (key) {
			return { label: registry[key].editor_label || registry[key].label || key, value: key };
		});

		wp.element.useEffect(function () {
			if (sections.length > 0 && postId) {
				schedulePreview(postId, sections);
			} else {
				removePreview();
			}
		}, [meta._agency_sections, postId]);

		wp.element.useEffect(function () {
			return removePreview;
		}, []);

		function save(next) {
			var nextMeta = Object.assign({}, meta, { _agency_sections: JSON.stringify(next) });
			editPost({ meta: nextMeta });
		}

		function update(index, nextSection) {
			var next = sections.slice();
			next[index] = nextSection;
			save(next);
		}

		function move(index, direction) {
			var target = index + direction;
			if (target < 0 || target >= sections.length) {
				return;
			}
			var next = sections.slice();
			var current = next[index];
			next[index] = next[target];
			next[target] = current;
			save(next);
		}

		return el(
			wp.editPost.PluginDocumentSettingPanel,
			{ name: 'agency-core-sections', title: strings.panelTitle, className: 'agency-core-section-editor' },
			parsed.invalid ? el(wp.components.Notice, { status: 'error', isDismissible: false }, strings.invalidJson) : null,
			!sections.length ? el('p', { className: 'agency-core-section-editor__empty' }, strings.empty) : null,
			sections.map(function (section, index) {
				var definition = registry[section.component] || { label: section.component, fields: {}, variants: {} };
				var variantDefinition = definition.variants[section.variant] || { label: section.variant };
				var fields = fieldsFor(section.component, section.variant);
				var data = section.data || {};
				var sectionName = data.title || data.eyebrow || definition.editor_label || definition.label || section.component;
				var variantOptions = Object.keys(definition.variants || {}).map(function (key) {
					return { label: definition.variants[key].label || key, value: key };
				});

				return el(
					'div',
					{ className: 'agency-core-section', key: index + '-' + section.component + '-' + section.variant },
					el('p', { className: 'agency-core-section__title' }, (index + 1) + '. ' + sectionName),
					el('p', { className: 'agency-core-section__meta' }, section.component + ' / ' + section.variant + ' · ' + (variantDefinition.label || section.variant)),
					el(wp.components.SelectControl, {
						label: strings.component,
						value: section.component,
						options: componentOptions,
						onChange: function (component) { update(index, defaultsFor(component)); }
					}),
					el(wp.components.SelectControl, {
						label: strings.variant,
						value: section.variant,
						options: variantOptions,
						onChange: function (variant) { update(index, defaultsFor(section.component, variant, data)); }
					}),
					Object.keys(fields).map(function (key) {
						return el(FieldControl, {
							key: key,
							field: fields[key],
							value: data[key],
							onChange: function (value) {
								var nextData = Object.assign({}, data);
								nextData[key] = fields[key].type === 'number' || fields[key].type === 'image_id' ? parseInt(value || 0, 10) : value;
								update(index, Object.assign({}, section, { data: nextData }));
							}
						});
					}),
					el(
						'div',
						{ className: 'agency-core-section__actions' },
						el(wp.components.Button, { variant: 'secondary', disabled: index === 0, onClick: function () { move(index, -1); } }, strings.moveUp),
						el(wp.components.Button, { variant: 'secondary', disabled: index === sections.length - 1, onClick: function () { move(index, 1); } }, strings.moveDown),
						el(wp.components.Button, { isDestructive: true, onClick: function () { save(sections.filter(function (_, itemIndex) { return itemIndex !== index; })); } }, strings.remove)
					)
				);
			}),
			el(wp.components.Button, {
				variant: 'primary',
				onClick: function () {
					var first = componentOptions.length ? componentOptions[0].value : '';
					if (first) {
						save(sections.concat([defaultsFor(first)]));
					}
				}
			}, strings.addSection)
		);
	}

	wp.plugins.registerPlugin('agency-core-section-editor', { render: SectionEditor, icon: 'layout' });
}(window.wp, window.AgencyCoreEditor));

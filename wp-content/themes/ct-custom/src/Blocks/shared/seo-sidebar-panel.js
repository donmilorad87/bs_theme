/**
 * SEO Sidebar Panel — Block Editor Plugin (vanilla JS).
 *
 * Full PluginSidebar with four tabs: SEO, Social, Schema, Advanced.
 * Saves all meta via bs_seo_* post meta keys (registered in SeoMeta.php).
 * Uses bsSeoAnalysis.analyze() from seo-analysis-engine.js for scoring.
 *
 * No JSX. No imports. IIFE pattern matching sidebar-settings-panel.js.
 * All loops bounded (NASA Power-of-10 compliant).
 *
 * @package BS_Custom
 */
(function (wp) {
	'use strict';

	/* ── WordPress Dependencies ───────────────────────────────────── */
	var el               = wp.element.createElement;
	var Fragment         = wp.element.Fragment;
	var useState         = wp.element.useState;
	var useEffect        = wp.element.useEffect;
	var useCallback      = wp.element.useCallback;
	var useMemo          = wp.element.useMemo;
	var registerPlugin   = wp.plugins.registerPlugin;
	/* WP 6.6+: PluginSidebar moved from wp.editPost to wp.editor */
	var PluginSidebar = (wp.editor && wp.editor.PluginSidebar) || (wp.editPost && wp.editPost.PluginSidebar);
	var PluginSidebarMoreMenuItem = (wp.editor && wp.editor.PluginSidebarMoreMenuItem) || (wp.editPost && wp.editPost.PluginSidebarMoreMenuItem);
	var TextControl      = wp.components.TextControl;
	var TextareaControl  = wp.components.TextareaControl;
	var SelectControl    = wp.components.SelectControl;
	var ToggleControl    = wp.components.ToggleControl;
	var PanelBody        = wp.components.PanelBody;
	var Button           = wp.components.Button;
	var useSelect        = wp.data.useSelect;
	var useDispatch      = wp.data.useDispatch;
	var __               = wp.i18n.__;
	var MediaUpload      = wp.blockEditor.MediaUpload;

	/* ── Constants ────────────────────────────────────────────────── */
	var MAX_CHECKS       = 20;
	var MAX_IMAGES       = 5000;
	var MAX_BLOCKS       = 5000;
	var TITLE_MIN        = 30;
	var TITLE_MAX        = 60;
	var DESC_MIN         = 120;
	var DESC_MAX         = 160;
	var SIDEBAR_NAME     = 'ct-seo-sidebar';

	/* ── Meta Keys (must match SeoMeta.php) ───────────────────────── */
	var META = {
		title:           'bs_seo_title',
		description:     'bs_seo_description',
		keywords:        'bs_seo_keywords',
		canonical:       'bs_seo_canonical',
		focusKeyword:    'bs_seo_focus_keyword',
		robotsIndex:     'bs_seo_robots_index',
		robotsFollow:    'bs_seo_robots_follow',
		robotsAdvanced:  'bs_seo_robots_advanced',
		ogTitle:         'bs_seo_og_title',
		ogDescription:   'bs_seo_og_description',
		ogImage:         'bs_seo_og_image',
		ogImageId:       'bs_seo_og_image_id',
		ogType:          'bs_seo_og_type',
		twitterCard:     'bs_seo_twitter_card',
		twitterTitle:    'bs_seo_twitter_title',
		twitterDescription: 'bs_seo_twitter_description',
		twitterImage:    'bs_seo_twitter_image',
		twitterImageId:  'bs_seo_twitter_image_id',
		schemaType:      'bs_seo_schema_type',
		schemaData:      'bs_seo_schema_data',
		breadcrumbHide:    'bs_seo_breadcrumb_hide',
		sitemapExcluded:   'bs_seo_sitemap_excluded',
		score:             'bs_seo_score',
	};

	/* ── Helpers ──────────────────────────────────────────────────── */

	/**
	 * Get a meta value with a string fallback.
	 *
	 * @param {Object} meta     Post meta object.
	 * @param {string} key      Meta key.
	 * @param {string} fallback Default value.
	 * @return {string}
	 */
	function getMeta(meta, key, fallback) {
		if (meta && typeof meta[key] !== 'undefined' && meta[key] !== null) {
			return String(meta[key]);
		}
		return fallback || '';
	}

	/**
	 * Get an integer meta value.
	 *
	 * @param {Object} meta     Post meta object.
	 * @param {string} key      Meta key.
	 * @param {number} fallback Default value.
	 * @return {number}
	 */
	function getMetaInt(meta, key, fallback) {
		if (meta && typeof meta[key] !== 'undefined') {
			var val = parseInt(meta[key], 10);
			return isNaN(val) ? (fallback || 0) : val;
		}
		return fallback || 0;
	}

	/**
	 * Extract image data from editor blocks (bounded loop).
	 *
	 * @param {Array} blocks Top-level blocks from the editor.
	 * @return {Array<{alt: string}>}
	 */
	function extractImages(blocks) {
		var images = [];
		var stack  = blocks.slice(0);
		var iter   = 0;

		while (stack.length > 0 && iter < MAX_BLOCKS) {
			iter++;
			var block = stack.shift();

			if (!block) {
				continue;
			}

			if (block.name === 'core/image') {
				var alt = (block.attributes && block.attributes.alt) ? block.attributes.alt : '';
				images.push({ alt: alt });

				if (images.length >= MAX_IMAGES) {
					break;
				}
			}

			/* Push inner blocks onto stack */
			if (block.innerBlocks && block.innerBlocks.length > 0) {
				var innerMax = block.innerBlocks.length < 500 ? block.innerBlocks.length : 500;

				for (var j = 0; j < innerMax; j++) {
					stack.push(block.innerBlocks[j]);
				}
			}
		}

		return images;
	}

	/**
	 * Determine character counter state class.
	 *
	 * @param {number} len  Current length.
	 * @param {number} min  Minimum good length.
	 * @param {number} max  Maximum good length.
	 * @return {string} CSS modifier class.
	 */
	function charCounterClass(len, min, max) {
		if (len === 0) {
			return 'ct-seo-char-counter--bad';
		}
		if (len >= min && len <= max) {
			return 'ct-seo-char-counter--good';
		}
		return 'ct-seo-char-counter--warning';
	}

	/* ── SVG Score Circle Component ──────────────────────────────── */

	/**
	 * Render an SVG donut chart with the SEO score.
	 *
	 * @param {Object} props
	 * @param {number} props.score SEO score 0-100.
	 * @return {Object} React element.
	 */
	function ScoreCircle(props) {
		var score  = props.score || 0;
		var size   = 80;
		var stroke = 6;
		var radius = (size - stroke) / 2;
		var circumference = 2 * Math.PI * radius;
		var progress      = circumference - (score / 100) * circumference;

		/* Color based on score */
		var color = '#DC2626'; /* red < 40 */
		if (score >= 70) {
			color = '#059669'; /* green */
		} else if (score >= 40) {
			color = '#D97706'; /* orange */
		}

		/* Label text */
		var label = 'Poor';
		if (score >= 70) {
			label = 'Good';
		} else if (score >= 40) {
			label = 'OK';
		}

		return el('div', { className: 'ct-seo-score-circle' },
			el('svg', {
				width:  size,
				height: size,
				viewBox: '0 0 ' + size + ' ' + size,
			},
				/* Background circle */
				el('circle', {
					cx:          size / 2,
					cy:          size / 2,
					r:           radius,
					fill:        'none',
					stroke:      '#e0e0e0',
					strokeWidth: stroke,
				}),
				/* Progress circle */
				el('circle', {
					cx:               size / 2,
					cy:               size / 2,
					r:                radius,
					fill:             'none',
					stroke:           color,
					strokeWidth:      stroke,
					strokeDasharray:  circumference,
					strokeDashoffset: progress,
					strokeLinecap:    'round',
					transform:        'rotate(-90 ' + (size / 2) + ' ' + (size / 2) + ')',
					style:            { transition: 'stroke-dashoffset 0.4s ease' },
				}),
				/* Score text */
				el('text', {
					x:          size / 2,
					y:          size / 2 + 1,
					textAnchor: 'middle',
					dominantBaseline: 'middle',
					fontSize:   '18px',
					fontWeight: '700',
					fill:       color,
				}, String(score))
			),
			el('span', { className: 'ct-seo-score-circle__label', style: { color: color } }, label)
		);
	}

	/* ── Character Counter Component ─────────────────────────────── */

	function CharCounter(props) {
		var len      = props.length || 0;
		var min      = props.min || 0;
		var max      = props.max || 999;
		var cssClass = 'ct-seo-char-counter ' + charCounterClass(len, min, max);

		return el('div', { className: cssClass },
			len + ' / ' + min + '-' + max + ' ' + __('characters', 'ct-custom')
		);
	}

	/* ── Analysis Checklist Component ────────────────────────────── */

	function AnalysisChecklist(props) {
		var checks = props.checks || [];
		var max    = checks.length < MAX_CHECKS ? checks.length : MAX_CHECKS;

		var statusIcons = {
			good:    '\u2713',  /* checkmark */
			warning: '!',
			bad:     '\u2717',  /* cross */
		};

		var items = [];

		for (var i = 0; i < max; i++) {
			var check = checks[i];

			items.push(
				el('li', {
					key:       check.id,
					className: 'ct-seo-analysis-list__item',
				},
					el('span', {
						className: 'ct-seo-analysis-list__icon ct-seo-analysis-list__icon--' + check.status,
					}, statusIcons[check.status] || '?'),
					el('span', { className: 'ct-seo-analysis-list__content' },
						el('span', { className: 'ct-seo-analysis-list__label' }, check.label),
						el('span', { className: 'ct-seo-analysis-list__detail' }, check.detail)
					)
				)
			);
		}

		return el('ul', { className: 'ct-seo-analysis-list' }, items);
	}

	/* ── SERP Preview Component ──────────────────────────────────── */

	function SerpPreview(props) {
		var title       = props.title || __('Page Title', 'ct-custom');
		var url         = props.url || 'https://example.com/page';
		var description = props.description || __('Add a meta description to see a preview.', 'ct-custom');

		return el('div', { className: 'ct-seo-serp-preview' },
			el('p', { className: 'ct-seo-serp-preview__title' }, title),
			el('p', { className: 'ct-seo-serp-preview__url' }, url),
			el('p', { className: 'ct-seo-serp-preview__description' }, description)
		);
	}

	/* ── Social Preview Component ────────────────────────────────── */

	function SocialPreview(props) {
		var imageUrl    = props.imageUrl || '';
		var title       = props.title || __('Social Share Title', 'ct-custom');
		var description = props.description || '';
		var domain      = props.domain || 'example.com';
		var variant     = props.variant || '';

		var wrapperClass = 'ct-seo-social-preview';
		if (variant) {
			wrapperClass += ' ct-seo-social-preview--' + variant;
		}

		var imageStyle = {};
		if (imageUrl) {
			imageStyle.backgroundImage = 'url(' + imageUrl + ')';
		}

		return el('div', { className: wrapperClass },
			el('div', {
				className: 'ct-seo-social-preview__image',
				style:     imageStyle,
			}, imageUrl ? null : __('No image selected', 'ct-custom')),
			el('div', { className: 'ct-seo-social-preview__body' },
				el('p', { className: 'ct-seo-social-preview__domain' }, domain),
				el('p', { className: 'ct-seo-social-preview__title' }, title),
				description ? el('p', { className: 'ct-seo-social-preview__description' }, description) : null
			)
		);
	}

	/* ── Image Upload Component ──────────────────────────────────── */

	function ImageUploadField(props) {
		var imageUrl  = props.imageUrl || '';
		var imageId   = props.imageId || 0;
		var onSelect  = props.onSelect;
		var onRemove  = props.onRemove;
		var label     = props.label || __('Image', 'ct-custom');

		return el('div', { className: 'components-base-control' },
			el('label', { className: 'components-base-control__label' }, label),
			el('div', { className: 'ct-seo-image-upload' },
				imageUrl
					? el('div', {
						className: 'ct-seo-image-upload__preview',
						style: { backgroundImage: 'url(' + imageUrl + ')' },
					})
					: null,
				el('div', { className: 'ct-seo-image-upload__actions' },
					el(MediaUpload, {
						onSelect: function (media) {
							if (media && media.url) {
								onSelect(media);
							}
						},
						allowedTypes: ['image'],
						value:  imageId,
						render: function (renderProps) {
							return el(Button, {
								variant:  'secondary',
								isSmall:  true,
								onClick:  renderProps.open,
							}, imageUrl ? __('Replace', 'ct-custom') : __('Select Image', 'ct-custom'));
						},
					}),
					imageUrl
						? el(Button, {
							variant:     'link',
							isSmall:     true,
							isDestructive: true,
							onClick:     onRemove,
						}, __('Remove', 'ct-custom'))
						: null
				)
			)
		);
	}

	/* ── TAB 1: SEO Tab ──────────────────────────────────────────── */

	function SeoTab(props) {
		var meta      = props.meta;
		var editPost  = props.editPost;
		var postTitle = props.postTitle;
		var postUrl   = props.postUrl;
		var content   = props.content;
		var blocks    = props.blocks;

		var focusKeyword = getMeta(meta, META.focusKeyword, '');
		var seoTitle     = getMeta(meta, META.title, '');
		var description  = getMeta(meta, META.description, '');
		var keywords     = getMeta(meta, META.keywords, '');
		var canonical    = getMeta(meta, META.canonical, '');

		/* Effective title for SERP preview */
		var displayTitle = seoTitle || postTitle || '';

		/* Run analysis */
		var images   = extractImages(blocks);
		var analysis = null;

		if (typeof window.bsSeoAnalysis !== 'undefined' && typeof window.bsSeoAnalysis.analyze === 'function') {
			analysis = window.bsSeoAnalysis.analyze({
				title:        displayTitle,
				description:  description,
				focusKeyword: focusKeyword,
				content:      content,
				url:          postUrl,
				headings:     [],
				images:       images,
				links:        [],
			});
		}

		var score  = analysis ? analysis.score : 0;
		var checks = analysis ? analysis.checks : [];

		/* Save score to meta */
		useEffect(function () {
			var currentScore = getMetaInt(meta, META.score, 0);

			if (currentScore !== score) {
				var update = {};
				update[META.score] = score;
				editPost.editPost({ meta: update });
			}
		}, [score]);

		function updateMeta(key, value) {
			var update = {};
			update[key] = value;
			editPost.editPost({ meta: update });
		}

		return el('div', { className: 'ct-seo-tab-content' },
			/* Score circle */
			el(ScoreCircle, { score: score }),

			/* Focus keyword */
			el(TextControl, {
				label:    __('Focus Keyword', 'ct-custom'),
				value:    focusKeyword,
				onChange: function (val) { updateMeta(META.focusKeyword, val); },
				help:     __('The primary keyword you want this page to rank for.', 'ct-custom'),
			}),

			el('hr', { className: 'ct-seo-divider' }),

			/* Meta title */
			el(TextControl, {
				label:    __('Meta Title', 'ct-custom'),
				value:    seoTitle,
				onChange: function (val) { updateMeta(META.title, val); },
				placeholder: postTitle || __('Enter SEO title...', 'ct-custom'),
			}),
			el(CharCounter, { length: (seoTitle || postTitle || '').length, min: TITLE_MIN, max: TITLE_MAX }),

			/* Meta description */
			el(TextareaControl, {
				label:    __('Meta Description', 'ct-custom'),
				value:    description,
				onChange: function (val) { updateMeta(META.description, val); },
				rows:     3,
				placeholder: __('Enter meta description...', 'ct-custom'),
			}),
			el(CharCounter, { length: description.length, min: DESC_MIN, max: DESC_MAX }),

			/* Keywords */
			el(TextControl, {
				label:    __('Keywords', 'ct-custom'),
				value:    keywords,
				onChange: function (val) { updateMeta(META.keywords, val); },
				help:     __('Comma-separated list of keywords.', 'ct-custom'),
			}),

			/* Canonical URL */
			el(TextControl, {
				label:    __('Canonical URL', 'ct-custom'),
				value:    canonical,
				onChange: function (val) { updateMeta(META.canonical, val); },
				help:     __('Leave empty to use the default URL.', 'ct-custom'),
				type:     'url',
			}),

			el('hr', { className: 'ct-seo-divider' }),

			/* SERP Preview */
			el('h3', { className: 'ct-seo-section-heading' }, __('Search Preview', 'ct-custom')),
			el(SerpPreview, {
				title:       displayTitle,
				url:         postUrl,
				description: description,
			}),

			el('hr', { className: 'ct-seo-divider' }),

			/* Analysis Checklist */
			el('h3', { className: 'ct-seo-section-heading' }, __('SEO Analysis', 'ct-custom')),
			el(AnalysisChecklist, { checks: checks })
		);
	}

	/* ── TAB 2: Social Tab ───────────────────────────────────────── */

	function SocialTab(props) {
		var meta      = props.meta;
		var editPost  = props.editPost;
		var postTitle = props.postTitle;
		var siteUrl   = props.siteUrl || '';

		/* Facebook OG */
		var ogTitle       = getMeta(meta, META.ogTitle, '');
		var ogDescription = getMeta(meta, META.ogDescription, '');
		var ogImage       = getMeta(meta, META.ogImage, '');
		var ogImageId     = getMetaInt(meta, META.ogImageId, 0);
		var ogType        = getMeta(meta, META.ogType, 'website');

		/* Twitter */
		var twitterCard   = getMeta(meta, META.twitterCard, 'summary');
		var twitterTitle  = getMeta(meta, META.twitterTitle, '');
		var twitterDesc   = getMeta(meta, META.twitterDescription, '');
		var twitterImage  = getMeta(meta, META.twitterImage, '');
		var twitterImageId = getMetaInt(meta, META.twitterImageId, 0);

		/* Domain from site URL */
		var domain = '';
		if (siteUrl) {
			var match = siteUrl.match(/\/\/([^/]+)/);
			if (match) {
				domain = match[1];
			}
		}

		function updateMeta(key, value) {
			var update = {};
			update[key] = value;
			editPost.editPost({ meta: update });
		}

		function updateMetaPair(key1, val1, key2, val2) {
			var update = {};
			update[key1] = val1;
			update[key2] = val2;
			editPost.editPost({ meta: update });
		}

		return el('div', { className: 'ct-seo-tab-content' },

			/* ── Facebook OG ─────────────────────────────────────── */
			el(PanelBody, {
				title:       __('Facebook / Open Graph', 'ct-custom'),
				initialOpen: true,
			},
				el(TextControl, {
					label:    __('OG Title', 'ct-custom'),
					value:    ogTitle,
					onChange: function (val) { updateMeta(META.ogTitle, val); },
					placeholder: postTitle,
				}),

				el(TextareaControl, {
					label:    __('OG Description', 'ct-custom'),
					value:    ogDescription,
					onChange: function (val) { updateMeta(META.ogDescription, val); },
					rows:     2,
				}),

				el(ImageUploadField, {
					label:    __('OG Image', 'ct-custom'),
					imageUrl: ogImage,
					imageId:  ogImageId,
					onSelect: function (media) {
						updateMetaPair(META.ogImage, media.url, META.ogImageId, media.id);
					},
					onRemove: function () {
						updateMetaPair(META.ogImage, '', META.ogImageId, 0);
					},
				}),

				el(SelectControl, {
					label:    __('OG Type', 'ct-custom'),
					value:    ogType,
					options: [
						{ label: 'Website',     value: 'website' },
						{ label: 'Article',     value: 'article' },
						{ label: 'Profile',     value: 'profile' },
						{ label: 'Product',     value: 'product' },
						{ label: 'Video',       value: 'video.other' },
						{ label: 'Music',       value: 'music.song' },
					],
					onChange: function (val) { updateMeta(META.ogType, val); },
				}),

				/* Facebook Preview Card */
				el('h3', { className: 'ct-seo-section-heading' }, __('Preview', 'ct-custom')),
				el(SocialPreview, {
					imageUrl:    ogImage,
					title:       ogTitle || postTitle,
					description: ogDescription,
					domain:      domain,
				})
			),

			/* ── Twitter ─────────────────────────────────────────── */
			el(PanelBody, {
				title:       __('Twitter', 'ct-custom'),
				initialOpen: false,
			},
				el(SelectControl, {
					label:    __('Card Type', 'ct-custom'),
					value:    twitterCard,
					options: [
						{ label: __('Summary', 'ct-custom'),             value: 'summary' },
						{ label: __('Summary Large Image', 'ct-custom'), value: 'summary_large_image' },
					],
					onChange: function (val) { updateMeta(META.twitterCard, val); },
				}),

				el(TextControl, {
					label:    __('Twitter Title', 'ct-custom'),
					value:    twitterTitle,
					onChange: function (val) { updateMeta(META.twitterTitle, val); },
					placeholder: postTitle,
				}),

				el(TextareaControl, {
					label:    __('Twitter Description', 'ct-custom'),
					value:    twitterDesc,
					onChange: function (val) { updateMeta(META.twitterDescription, val); },
					rows:     2,
				}),

				el(ImageUploadField, {
					label:    __('Twitter Image', 'ct-custom'),
					imageUrl: twitterImage,
					imageId:  twitterImageId,
					onSelect: function (media) {
						updateMetaPair(META.twitterImage, media.url, META.twitterImageId, media.id);
					},
					onRemove: function () {
						updateMetaPair(META.twitterImage, '', META.twitterImageId, 0);
					},
				}),

				/* Twitter Preview Card */
				el('h3', { className: 'ct-seo-section-heading' }, __('Preview', 'ct-custom')),
				el(SocialPreview, {
					imageUrl:    twitterImage || ogImage,
					title:       twitterTitle || ogTitle || postTitle,
					description: twitterDesc || ogDescription,
					domain:      domain,
					variant:     twitterCard === 'summary_large_image' ? 'twitter-large' : '',
				})
			)
		);
	}

	/* ── TAB 3: Schema Tab ───────────────────────────────────────── */

	function SchemaTab(props) {
		var meta     = props.meta;
		var editPost = props.editPost;

		var schemaType = getMeta(meta, META.schemaType, '');
		var schemaData = getMeta(meta, META.schemaData, '');

		function updateMeta(key, value) {
			var update = {};
			update[key] = value;
			editPost.editPost({ meta: update });
		}

		/* Auto-generate default JSON-LD when type changes */
		function onSchemaTypeChange(val) {
			updateMeta(META.schemaType, val);

			/* Only auto-generate if schemaData is empty or was auto-generated */
			if (schemaData.trim() === '' || schemaData.indexOf('"@type"') !== -1) {
				var template = '';

				if (val && val !== 'none' && val !== '') {
					template = JSON.stringify({
						'@context': 'https://schema.org',
						'@type':    val,
					}, null, 2);
				}

				var dataUpdate = {};
				dataUpdate[META.schemaData] = template;
				editPost.editPost({ meta: dataUpdate });
			}
		}

		return el('div', { className: 'ct-seo-tab-content' },

			el(SelectControl, {
				label:    __('Schema Type', 'ct-custom'),
				value:    schemaType,
				options: [
					{ label: __('None', 'ct-custom'),          value: '' },
					{ label: 'WebPage',        value: 'WebPage' },
					{ label: 'Article',        value: 'Article' },
					{ label: 'BlogPosting',    value: 'BlogPosting' },
					{ label: 'FAQPage',        value: 'FAQPage' },
					{ label: 'HowTo',          value: 'HowTo' },
					{ label: 'Product',        value: 'Product' },
					{ label: 'Review',         value: 'Review' },
					{ label: 'Event',          value: 'Event' },
					{ label: 'LocalBusiness',  value: 'LocalBusiness' },
					{ label: 'Person',         value: 'Person' },
				],
				onChange: onSchemaTypeChange,
				help:     __('Select a Schema.org type for this page.', 'ct-custom'),
			}),

			el('hr', { className: 'ct-seo-divider' }),

			el('h3', { className: 'ct-seo-section-heading' }, __('Custom Schema JSON-LD', 'ct-custom')),

			el('div', { className: 'ct-seo-schema-preview' },
				el(TextareaControl, {
					label:    __('Schema Data', 'ct-custom'),
					value:    schemaData,
					onChange: function (val) { updateMeta(META.schemaData, val); },
					rows:     10,
					help:     __('Enter valid JSON-LD schema data. Leave empty to use defaults.', 'ct-custom'),
				})
			)
		);
	}

	/* ── TAB 4: Advanced Tab ─────────────────────────────────────── */

	function AdvancedTab(props) {
		var meta     = props.meta;
		var editPost = props.editPost;

		var robotsIndex     = getMeta(meta, META.robotsIndex, '');
		var robotsFollow    = getMeta(meta, META.robotsFollow, '');
		var robotsAdvanced  = getMeta(meta, META.robotsAdvanced, '');
		var breadcrumbHide  = getMeta(meta, META.breadcrumbHide, '');
		var sitemapExcluded = getMeta(meta, META.sitemapExcluded, '');

		function updateMeta(key, value) {
			var update = {};
			update[key] = value;
			editPost.editPost({ meta: update });
		}

		return el('div', { className: 'ct-seo-tab-content' },

			el('h3', { className: 'ct-seo-section-heading' }, __('Robots', 'ct-custom')),

			el(SelectControl, {
				label:    __('Index', 'ct-custom'),
				value:    robotsIndex,
				options: [
					{ label: __('Default (index)', 'ct-custom'),   value: '' },
					{ label: __('Index', 'ct-custom'),    value: 'index' },
					{ label: __('No Index', 'ct-custom'), value: 'noindex' },
				],
				onChange: function (val) { updateMeta(META.robotsIndex, val); },
				help:     __('Controls whether search engines index this page.', 'ct-custom'),
			}),

			el(SelectControl, {
				label:    __('Follow', 'ct-custom'),
				value:    robotsFollow,
				options: [
					{ label: __('Default (follow)', 'ct-custom'),   value: '' },
					{ label: __('Follow', 'ct-custom'),    value: 'follow' },
					{ label: __('No Follow', 'ct-custom'), value: 'nofollow' },
				],
				onChange: function (val) { updateMeta(META.robotsFollow, val); },
				help:     __('Controls whether search engines follow links on this page.', 'ct-custom'),
			}),

			el(TextControl, {
				label:    __('Advanced Robots', 'ct-custom'),
				value:    robotsAdvanced,
				onChange: function (val) { updateMeta(META.robotsAdvanced, val); },
				help:     __('Additional robots directives (e.g., noarchive, nosnippet, max-snippet:150).', 'ct-custom'),
				placeholder: 'noarchive, nosnippet',
			}),

			el('hr', { className: 'ct-seo-divider' }),

			el('h3', { className: 'ct-seo-section-heading' }, __('Breadcrumbs', 'ct-custom')),

			el(ToggleControl, {
				label:    __('Hide from Breadcrumbs', 'ct-custom'),
				checked:  breadcrumbHide === 'on',
				onChange: function (checked) { updateMeta(META.breadcrumbHide, checked ? 'on' : ''); },
				help:     __('When enabled, this page will be hidden from the breadcrumb trail.', 'ct-custom'),
			}),

			el('hr', { className: 'ct-seo-divider' }),

			el('h3', { className: 'ct-seo-section-heading' }, __('Sitemap', 'ct-custom')),

			el(ToggleControl, {
				label:    __('Exclude from Sitemap', 'ct-custom'),
				checked:  sitemapExcluded === 'on',
				onChange: function (checked) { updateMeta(META.sitemapExcluded, checked ? 'on' : ''); },
				help:     __('When enabled, this page will not appear in the XML sitemap.', 'ct-custom'),
			})
		);
	}

	/* ── Main Sidebar Component ──────────────────────────────────── */

	function SeoSidebarPanel() {
		var meta = useSelect(function (select) {
			var postMeta = select('core/editor').getEditedPostAttribute('meta');
			return postMeta || {};
		}, []);

		var postTitle = useSelect(function (select) {
			return select('core/editor').getEditedPostAttribute('title') || '';
		}, []);

		var postSlug = useSelect(function (select) {
			return select('core/editor').getEditedPostAttribute('slug') || '';
		}, []);

		var postLink = useSelect(function (select) {
			return select('core/editor').getEditedPostAttribute('link') || '';
		}, []);

		var content = useSelect(function (select) {
			return select('core/editor').getEditedPostAttribute('content') || '';
		}, []);

		var blocks = useSelect(function (select) {
			return select('core/block-editor').getBlocks() || [];
		}, []);

		var editPost = useDispatch('core/editor');

		/* Site URL for social previews */
		var siteUrl = useSelect(function (select) {
			var site = select('core').getSite();
			return site ? site.url : '';
		}, []);

		/* Post URL for SERP preview */
		var postUrl = postLink || (siteUrl + '/' + postSlug + '/');

		/* Current score for the sidebar icon badge */
		var currentScore = getMetaInt(meta, META.score, 0);

		/* Active tab state */
		var tabState     = useState('seo');
		var activeTab    = tabState[0];
		var setActiveTab = tabState[1];

		/* Tab definitions (bounded) */
		var TAB_DEFS = [
			{ name: 'seo',      label: __('SEO',      'ct-custom') },
			{ name: 'social',   label: __('Social',   'ct-custom') },
			{ name: 'schema',   label: __('Schema',   'ct-custom') },
			{ name: 'advanced', label: __('Advanced', 'ct-custom') },
		];

		var MAX_TABS = 10;
		var tabBtns  = [];
		var tabCount = 0;

		for (var t = 0; t < TAB_DEFS.length && tabCount < MAX_TABS; t++) {
			tabCount++;
			(function (def) {
				tabBtns.push(
					el('button', {
						key:       def.name,
						type:      'button',
						className: 'ct-seo-tab-btn' + (activeTab === def.name ? ' ct-seo-tab-btn--active' : ''),
						onClick:   function () { setActiveTab(def.name); },
					}, def.label)
				);
			})(TAB_DEFS[t]);
		}

		/* Active tab content */
		var tabContent = null;

		if (activeTab === 'seo') {
			tabContent = el(SeoTab, {
				meta:      meta,
				editPost:  editPost,
				postTitle: postTitle,
				postUrl:   postUrl,
				content:   content,
				blocks:    blocks,
			});
		} else if (activeTab === 'social') {
			tabContent = el(SocialTab, {
				meta:      meta,
				editPost:  editPost,
				postTitle: postTitle,
				siteUrl:   siteUrl,
			});
		} else if (activeTab === 'schema') {
			tabContent = el(SchemaTab, {
				meta:     meta,
				editPost: editPost,
			});
		} else if (activeTab === 'advanced') {
			tabContent = el(AdvancedTab, {
				meta:     meta,
				editPost: editPost,
			});
		}

		return el(
			Fragment,
			null,
			el(PluginSidebarMoreMenuItem, {
				target: SIDEBAR_NAME,
				icon:   'chart-line',
			}, __('SEO', 'ct-custom')),
			el(PluginSidebar, {
				name:  SIDEBAR_NAME,
				title: __('SEO', 'ct-custom'),
				icon:  'chart-line',
			},
				el('div', { className: 'ct-seo-sidebar' },
					el('div', { className: 'ct-seo-tabs' }, tabBtns),
					el('div', { className: 'ct-seo-tab-content' }, tabContent)
				)
			)
		);
	}

	/* ── Register Plugin ─────────────────────────────────────────── */
	registerPlugin('ct-seo-sidebar', {
		render: SeoSidebarPanel,
		icon:   'chart-line',
	});

})(window.wp);

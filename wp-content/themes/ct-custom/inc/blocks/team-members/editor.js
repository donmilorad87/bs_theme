/**
 * CT Team Members — Gutenberg Editor (vanilla JS, no JSX).
 *
 * Uses wp.element.createElement exclusively.
 * Dynamic block: save() returns null, PHP handles frontend.
 */
(function (wp) {
    'use strict';

    var el                = wp.element.createElement;
    var Fragment          = wp.element.Fragment;
    var registerBlockType = wp.blocks.registerBlockType;
    var useBlockProps     = wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var MediaUpload       = wp.blockEditor.MediaUpload;
    var MediaUploadCheck  = wp.blockEditor.MediaUploadCheck;
    var PanelBody         = wp.components.PanelBody;
    var TextControl       = wp.components.TextControl;
    var RangeControl      = wp.components.RangeControl;
    var Button            = wp.components.Button;
    var ColorPalette      = wp.components.ColorPalette;
    var ToggleControl     = wp.components.ToggleControl;
    var SelectControl     = wp.components.SelectControl;
    var __                = wp.i18n.__;

    var MAX_MEMBERS = 100;

    var EMPTY_MEMBER = {
        imageId:   0,
        imageUrl:  '',
        firstName: '',
        lastName:  '',
        position:  ''
    };

    var TRANSFORM_OPTIONS = [
        { label: __('None', 'ct-custom'),       value: 'none' },
        { label: __('Uppercase', 'ct-custom'),   value: 'uppercase' },
        { label: __('Lowercase', 'ct-custom'),   value: 'lowercase' },
        { label: __('Capitalize', 'ct-custom'),  value: 'capitalize' }
    ];

    /* ── Helper: build a single member card ── */

    function memberCard(member, index, total, updateMember, removeMember, moveMember, visibleCount) {
        var hasImage = member.imageUrl && member.imageId > 0;
        var isBeyond = index >= visibleCount;

        /* Image section */
        var imageSection;

        if (hasImage) {
            imageSection = el('div', { className: 'ct-team-member-editor__image-wrap' },
                el('img', { src: member.imageUrl, alt: '' }),
                el('div', { className: 'ct-team-member-editor__image-actions' },
                    el(MediaUploadCheck, null,
                        el(MediaUpload, {
                            onSelect: function (media) {
                                var url = media.sizes && media.sizes.medium
                                    ? media.sizes.medium.url
                                    : media.url;
                                updateMember(index, { imageId: media.id, imageUrl: url });
                            },
                            allowedTypes: ['image'],
                            value: member.imageId,
                            render: function (obj) {
                                return el(Button, {
                                    onClick: obj.open,
                                    variant: 'secondary',
                                    size: 'compact'
                                }, __('Replace', 'ct-custom'));
                            }
                        })
                    ),
                    el(Button, {
                        onClick: function () {
                            updateMember(index, { imageId: 0, imageUrl: '' });
                        },
                        isDestructive: true,
                        size: 'compact'
                    }, __('Remove Image', 'ct-custom'))
                )
            );
        } else {
            imageSection = el(MediaUploadCheck, null,
                el(MediaUpload, {
                    onSelect: function (media) {
                        var url = media.sizes && media.sizes.medium
                            ? media.sizes.medium.url
                            : media.url;
                        updateMember(index, { imageId: media.id, imageUrl: url });
                    },
                    allowedTypes: ['image'],
                    value: member.imageId,
                    render: function (obj) {
                        return el('div', {
                            className: 'ct-team-member-editor__upload',
                            onClick: obj.open,
                            role: 'button',
                            tabIndex: 0
                        }, __('+ Upload Photo', 'ct-custom'));
                    }
                })
            );
        }

        /* Card */
        return el('div', {
            className: 'ct-team-member-editor__card' + (isBeyond ? ' ct-team-member-editor__card--beyond' : ''),
            key: 'member-' + index
        },
            /* Header row: number, arrows, trash */
            el('div', { className: 'ct-team-member-editor__header' },
                el('span', { className: 'ct-team-member-editor__number' },
                    '#' + (index + 1) + (isBeyond ? '  (' + __('hidden', 'ct-custom') + ')' : '')
                ),
                el('div', { className: 'ct-team-member-editor__actions' },
                    el(Button, {
                        icon: 'arrow-up-alt2',
                        onClick: function () { moveMember(index, index - 1); },
                        disabled: index === 0,
                        size: 'compact',
                        label: __('Move up', 'ct-custom')
                    }),
                    el(Button, {
                        icon: 'arrow-down-alt2',
                        onClick: function () { moveMember(index, index + 1); },
                        disabled: index === total - 1,
                        size: 'compact',
                        label: __('Move down', 'ct-custom')
                    }),
                    el(Button, {
                        icon: 'trash',
                        onClick: function () { removeMember(index); },
                        isDestructive: true,
                        size: 'compact',
                        label: __('Remove member', 'ct-custom')
                    })
                )
            ),
            imageSection,
            el(TextControl, {
                label: __('First Name', 'ct-custom'),
                value: member.firstName || '',
                onChange: function (val) { updateMember(index, { firstName: val }); }
            }),
            el(TextControl, {
                label: __('Last Name', 'ct-custom'),
                value: member.lastName || '',
                onChange: function (val) { updateMember(index, { lastName: val }); }
            }),
            window.ctTranslationPicker
                ? window.ctTranslationPicker.createControl({
                      label:    __('Position', 'ct-custom'),
                      value:    member.position || '',
                      onChange: function (val) { updateMember(index, { position: val }); }
                  })
                : el(TextControl, {
                      label: __('Position', 'ct-custom'),
                      value: member.position || '',
                      onChange: function (val) { updateMember(index, { position: val }); }
                  })
        );
    }

    /* ── Register block ── */

    registerBlockType('ct-custom/team-members', {

        edit: function (props) {
            var attrs        = props.attributes;
            var members      = attrs.members || [];
            var visibleCount = attrs.visibleCount || 8;
            var buttonText   = attrs.buttonText || __('Meet Our Team', 'ct-custom');
            var blockProps   = useBlockProps({ className: 'ct-team-members-editor' });

            /* ── Mutators ── */

            function setMembers(newMembers) {
                props.setAttributes({ members: newMembers });
            }

            function updateMember(index, updates) {
                var newMembers = members.map(function (m, i) {
                    return i === index ? Object.assign({}, m, updates) : m;
                });
                setMembers(newMembers);
            }

            function addMember() {
                if (members.length >= MAX_MEMBERS) { return; }
                setMembers(members.concat([Object.assign({}, EMPTY_MEMBER)]));
            }

            function removeMember(index) {
                setMembers(members.filter(function (_, i) { return i !== index; }));
            }

            function moveMember(from, to) {
                if (to < 0 || to >= members.length) { return; }
                var arr  = members.slice();
                var item = arr.splice(from, 1)[0];
                arr.splice(to, 0, item);
                setMembers(arr);
            }

            /* ── Build member cards ── */

            var cards = [];
            var limit = Math.min(members.length, MAX_MEMBERS);

            for (var i = 0; i < limit; i++) {
                cards.push(
                    memberCard(members[i], i, members.length, updateMember, removeMember, moveMember, visibleCount)
                );
            }

            /* ── Sidebar ── */

            var sidebar = el(InspectorControls, null,

                /* Panel 1: Display Settings */
                el(PanelBody, { title: __('Display Settings', 'ct-custom'), initialOpen: true },
                    el(RangeControl, {
                        label: __('Initially Visible Members', 'ct-custom'),
                        help: __('Members beyond this count are hidden until the button is clicked.', 'ct-custom'),
                        value: visibleCount,
                        onChange: function (val) { props.setAttributes({ visibleCount: val }); },
                        min: 1,
                        max: MAX_MEMBERS,
                        step: 1
                    }),
                    window.ctTranslationPicker
                        ? window.ctTranslationPicker.createControl({
                              label:    __('Show More Button Text', 'ct-custom'),
                              value:    buttonText,
                              onChange: function (val) { props.setAttributes({ buttonText: val }); }
                          })
                        : el(TextControl, {
                              label: __('Show More Button Text', 'ct-custom'),
                              value: buttonText,
                              onChange: function (val) { props.setAttributes({ buttonText: val }); }
                          }),
                    window.ctTranslationPicker
                        ? window.ctTranslationPicker.createControl({
                              label:    __('Hide Button Text', 'ct-custom'),
                              help:     __('Text shown on the button when the grid is expanded.', 'ct-custom'),
                              value:    attrs.hideText !== undefined ? attrs.hideText : 'Hide',
                              onChange: function (val) { props.setAttributes({ hideText: val }); }
                          })
                        : el(TextControl, {
                              label: __('Hide Button Text', 'ct-custom'),
                              help: __('Text shown on the button when the grid is expanded.', 'ct-custom'),
                              value: attrs.hideText !== undefined ? attrs.hideText : 'Hide',
                              onChange: function (val) { props.setAttributes({ hideText: val }); }
                          })
                ),

                /* Panel 2: Colors — Light Theme */
                el(PanelBody, { title: __('Colors \u2014 Light Theme', 'ct-custom'), initialOpen: false },
                    el('div', { className: 'ct-team-sidebar__field' },
                        el('p', { className: 'ct-team-sidebar__label' }, __('Grid Background', 'ct-custom')),
                        el(ColorPalette, {
                            value: attrs.gridBgColor !== undefined ? attrs.gridBgColor : '#4a6eb0',
                            onChange: function (val) { props.setAttributes({ gridBgColor: val || '#4a6eb0' }); }
                        })
                    ),
                    el('div', { className: 'ct-team-sidebar__field' },
                        el('p', { className: 'ct-team-sidebar__label' }, __('Image Overlay', 'ct-custom')),
                        el(ColorPalette, {
                            value: attrs.imageOverlayColor !== undefined ? attrs.imageOverlayColor : '#355fad',
                            onChange: function (val) { props.setAttributes({ imageOverlayColor: val || '#355fad' }); }
                        })
                    ),
                    el('div', { className: 'ct-team-sidebar__field' },
                        el('p', { className: 'ct-team-sidebar__label' }, __('Show More Overlay', 'ct-custom')),
                        el(ColorPalette, {
                            value: attrs.showmoreBgColor !== undefined ? attrs.showmoreBgColor : '#021327',
                            onChange: function (val) { props.setAttributes({ showmoreBgColor: val || '#021327' }); }
                        })
                    ),
                    el(RangeControl, {
                        label: __('Overlay Opacity', 'ct-custom'),
                        value: attrs.showmoreBgOpacity !== undefined ? attrs.showmoreBgOpacity : 0.65,
                        onChange: function (val) { props.setAttributes({ showmoreBgOpacity: val }); },
                        min: 0,
                        max: 1,
                        step: 0.05
                    }),
                    el('div', { className: 'ct-team-sidebar__field' },
                        el('p', { className: 'ct-team-sidebar__label' }, __('Button Background', 'ct-custom')),
                        el(ColorPalette, {
                            value: attrs.buttonBgColor !== undefined ? attrs.buttonBgColor : '#a8c930',
                            onChange: function (val) { props.setAttributes({ buttonBgColor: val || '#a8c930' }); }
                        })
                    ),
                    el('div', { className: 'ct-team-sidebar__field' },
                        el('p', { className: 'ct-team-sidebar__label' }, __('Button Hover', 'ct-custom')),
                        el(ColorPalette, {
                            value: attrs.buttonHoverColor !== undefined ? attrs.buttonHoverColor : '#7e9724',
                            onChange: function (val) { props.setAttributes({ buttonHoverColor: val || '#7e9724' }); }
                        })
                    ),
                    el('div', { className: 'ct-team-sidebar__field' },
                        el('p', { className: 'ct-team-sidebar__label' }, __('Name Color', 'ct-custom')),
                        el(ColorPalette, {
                            value: attrs.nameColor !== undefined ? attrs.nameColor : '#4eafdc',
                            onChange: function (val) { props.setAttributes({ nameColor: val || '#4eafdc' }); }
                        })
                    ),
                    el('div', { className: 'ct-team-sidebar__field' },
                        el('p', { className: 'ct-team-sidebar__label' }, __('Position Color', 'ct-custom')),
                        el(ColorPalette, {
                            value: attrs.positionColor !== undefined ? attrs.positionColor : '#4eafdc',
                            onChange: function (val) { props.setAttributes({ positionColor: val || '#4eafdc' }); }
                        })
                    )
                ),

                /* Panel 3: Colors — Dark Theme */
                el(PanelBody, { title: __('Colors \u2014 Dark Theme', 'ct-custom'), initialOpen: false },
                    el('div', { className: 'ct-team-sidebar__field' },
                        el('p', { className: 'ct-team-sidebar__label' }, __('Grid Background', 'ct-custom')),
                        el(ColorPalette, {
                            value: attrs.gridBgColorDark !== undefined ? attrs.gridBgColorDark : '#4a6eb0',
                            onChange: function (val) { props.setAttributes({ gridBgColorDark: val || '#4a6eb0' }); }
                        })
                    ),
                    el('div', { className: 'ct-team-sidebar__field' },
                        el('p', { className: 'ct-team-sidebar__label' }, __('Image Overlay', 'ct-custom')),
                        el(ColorPalette, {
                            value: attrs.imageOverlayColorDark !== undefined ? attrs.imageOverlayColorDark : '#355fad',
                            onChange: function (val) { props.setAttributes({ imageOverlayColorDark: val || '#355fad' }); }
                        })
                    ),
                    el('div', { className: 'ct-team-sidebar__field' },
                        el('p', { className: 'ct-team-sidebar__label' }, __('Show More Overlay', 'ct-custom')),
                        el(ColorPalette, {
                            value: attrs.showmoreBgColorDark !== undefined ? attrs.showmoreBgColorDark : '#021327',
                            onChange: function (val) { props.setAttributes({ showmoreBgColorDark: val || '#021327' }); }
                        })
                    ),
                    el('div', { className: 'ct-team-sidebar__field' },
                        el('p', { className: 'ct-team-sidebar__label' }, __('Button Background', 'ct-custom')),
                        el(ColorPalette, {
                            value: attrs.buttonBgColorDark !== undefined ? attrs.buttonBgColorDark : '#a8c930',
                            onChange: function (val) { props.setAttributes({ buttonBgColorDark: val || '#a8c930' }); }
                        })
                    ),
                    el('div', { className: 'ct-team-sidebar__field' },
                        el('p', { className: 'ct-team-sidebar__label' }, __('Button Hover', 'ct-custom')),
                        el(ColorPalette, {
                            value: attrs.buttonHoverColorDark !== undefined ? attrs.buttonHoverColorDark : '#7e9724',
                            onChange: function (val) { props.setAttributes({ buttonHoverColorDark: val || '#7e9724' }); }
                        })
                    ),
                    el('div', { className: 'ct-team-sidebar__field' },
                        el('p', { className: 'ct-team-sidebar__label' }, __('Name Color', 'ct-custom')),
                        el(ColorPalette, {
                            value: attrs.nameColorDark !== undefined ? attrs.nameColorDark : '#4eafdc',
                            onChange: function (val) { props.setAttributes({ nameColorDark: val || '#4eafdc' }); }
                        })
                    ),
                    el('div', { className: 'ct-team-sidebar__field' },
                        el('p', { className: 'ct-team-sidebar__label' }, __('Position Color', 'ct-custom')),
                        el(ColorPalette, {
                            value: attrs.positionColorDark !== undefined ? attrs.positionColorDark : '#4eafdc',
                            onChange: function (val) { props.setAttributes({ positionColorDark: val || '#4eafdc' }); }
                        })
                    )
                ),

                /* Panel 4: Name Typography */
                el(PanelBody, { title: __('Name Typography', 'ct-custom'), initialOpen: false },
                    el(RangeControl, {
                        label: __('Font Size (px)', 'ct-custom'),
                        value: attrs.nameFontSize !== undefined ? attrs.nameFontSize : 14,
                        onChange: function (val) { props.setAttributes({ nameFontSize: val }); },
                        min: 8,
                        max: 36,
                        step: 1
                    }),
                    el(ToggleControl, {
                        label: __('Bold', 'ct-custom'),
                        checked: attrs.nameBold !== undefined ? attrs.nameBold : true,
                        onChange: function (val) { props.setAttributes({ nameBold: val }); }
                    }),
                    el(ToggleControl, {
                        label: __('Italic', 'ct-custom'),
                        checked: attrs.nameItalic !== undefined ? attrs.nameItalic : false,
                        onChange: function (val) { props.setAttributes({ nameItalic: val }); }
                    }),
                    el(SelectControl, {
                        label: __('Text Transform', 'ct-custom'),
                        value: attrs.nameTransform !== undefined ? attrs.nameTransform : 'none',
                        options: TRANSFORM_OPTIONS,
                        onChange: function (val) { props.setAttributes({ nameTransform: val }); }
                    })
                ),

                /* Panel 5: Position Typography */
                el(PanelBody, { title: __('Position Typography', 'ct-custom'), initialOpen: false },
                    el(RangeControl, {
                        label: __('Font Size (px)', 'ct-custom'),
                        value: attrs.positionFontSize !== undefined ? attrs.positionFontSize : 11,
                        onChange: function (val) { props.setAttributes({ positionFontSize: val }); },
                        min: 8,
                        max: 36,
                        step: 1
                    }),
                    el(ToggleControl, {
                        label: __('Bold', 'ct-custom'),
                        checked: attrs.positionBold !== undefined ? attrs.positionBold : false,
                        onChange: function (val) { props.setAttributes({ positionBold: val }); }
                    }),
                    el(ToggleControl, {
                        label: __('Italic', 'ct-custom'),
                        checked: attrs.positionItalic !== undefined ? attrs.positionItalic : false,
                        onChange: function (val) { props.setAttributes({ positionItalic: val }); }
                    }),
                    el(SelectControl, {
                        label: __('Text Transform', 'ct-custom'),
                        value: attrs.positionTransform !== undefined ? attrs.positionTransform : 'none',
                        options: TRANSFORM_OPTIONS,
                        onChange: function (val) { props.setAttributes({ positionTransform: val }); }
                    })
                ),

                /* Panel 6: Button Typography */
                el(PanelBody, { title: __('Button Typography', 'ct-custom'), initialOpen: false },
                    el(RangeControl, {
                        label: __('Font Size (px)', 'ct-custom'),
                        value: attrs.buttonFontSize !== undefined ? attrs.buttonFontSize : 15,
                        onChange: function (val) { props.setAttributes({ buttonFontSize: val }); },
                        min: 8,
                        max: 36,
                        step: 1
                    }),
                    el(RangeControl, {
                        label: __('Line Height', 'ct-custom'),
                        value: attrs.buttonLineHeight !== undefined ? attrs.buttonLineHeight : 1.2,
                        onChange: function (val) { props.setAttributes({ buttonLineHeight: val }); },
                        min: 0.8,
                        max: 3.0,
                        step: 0.1
                    })
                )
            );

            /* ── Main UI ── */

            return el(Fragment, null,
                sidebar,
                el('div', blockProps,
                    el('div', { className: 'ct-team-members-editor__top' },
                        el('h3', { className: 'ct-team-members-editor__title' },
                            __('Team Members', 'ct-custom')
                        ),
                        el('span', { className: 'ct-team-members-editor__count' },
                            members.length + ' ' + (members.length === 1 ? __('member', 'ct-custom') : __('members', 'ct-custom'))
                            + ' \u00B7 ' + visibleCount + ' ' + __('visible', 'ct-custom')
                        )
                    ),
                    cards.length
                        ? el('div', { className: 'ct-team-members-editor__list' }, cards)
                        : el('p', { className: 'ct-team-members-editor__empty' },
                            __('No team members yet. Click the button below to add one.', 'ct-custom')
                        ),
                    el(Button, {
                        onClick: addMember,
                        variant: 'primary',
                        className: 'ct-team-members-editor__add',
                        disabled: members.length >= MAX_MEMBERS
                    }, __('+ Add Team Member', 'ct-custom'))
                )
            );
        },

        save: function () {
            return null;
        }
    });

})(window.wp);

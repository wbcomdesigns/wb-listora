/**
 * Standard attribute schemas for WB Listora blocks.
 * Import and spread into block attributes for consistency.
 */

export const uniqueIdAttribute = {
	uniqueId: { type: 'string', default: '' },
};

export const spacingAttributes = {
	padding: {
		type: 'object',
		default: { top: 24, right: 24, bottom: 24, left: 24 },
	},
	paddingTablet: { type: 'object', default: undefined },
	paddingMobile: { type: 'object', default: undefined },
	paddingUnit: { type: 'string', default: 'px' },
	margin: {
		type: 'object',
		default: { top: 0, right: 0, bottom: 0, left: 0 },
	},
	marginTablet: { type: 'object', default: undefined },
	marginMobile: { type: 'object', default: undefined },
	marginUnit: { type: 'string', default: 'px' },
};

/**
 * INTENTIONALLY NOT part of getStandardAttributes() (BC #9977214822).
 *
 * Per-block typography customization is deliberately deferred: no block.json
 * declares these attributes and no edit.js imports TypographyControl
 * (src/shared/components/TypographyControl.js). Block typography is owned by
 * the design tokens in src/variables/ so all 11 blocks stay visually
 * consistent and theme-overridable from one place.
 *
 * If a block ever needs per-instance typography: spread typographyAttributes
 * into THAT block's block.json, wire TypographyControl into its edit.js
 * Style panel, and emit the values via Block_CSS — do not add them to
 * getStandardAttributes(), which would bloat every block's saved markup.
 */
export const typographyAttributes = {
	fontFamily: { type: 'string', default: '' },
	fontSize: { type: 'number', default: undefined },
	fontSizeTablet: { type: 'number', default: undefined },
	fontSizeMobile: { type: 'number', default: undefined },
	fontSizeUnit: { type: 'string', default: 'px' },
	fontWeight: { type: 'string', default: '' },
	lineHeight: { type: 'number', default: undefined },
	lineHeightUnit: { type: 'string', default: '' },
	letterSpacing: { type: 'number', default: undefined },
	textTransform: { type: 'string', default: '' },
};

export const shadowAttributes = {
	boxShadow: { type: 'boolean', default: false },
	shadowHorizontal: { type: 'number', default: 0 },
	shadowVertical: { type: 'number', default: 4 },
	shadowBlur: { type: 'number', default: 8 },
	shadowSpread: { type: 'number', default: 0 },
	shadowColor: { type: 'string', default: 'rgba(0, 0, 0, 0.12)' },
};

export const borderAttributes = {
	borderRadius: {
		type: 'object',
		default: { top: 0, right: 0, bottom: 0, left: 0 },
	},
	borderRadiusUnit: { type: 'string', default: 'px' },
};

export const visibilityAttributes = {
	hideOnDesktop: { type: 'boolean', default: false },
	hideOnTablet: { type: 'boolean', default: false },
	hideOnMobile: { type: 'boolean', default: false },
};

/**
 * Get all standard attributes combined.
 * Usage: { ...getStandardAttributes() } in block.json attributes.
 */
export function getStandardAttributes() {
	return {
		...uniqueIdAttribute,
		...spacingAttributes,
		...shadowAttributes,
		...borderAttributes,
		...visibilityAttributes,
	};
}

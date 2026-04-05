/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "react/jsx-runtime"
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
(module) {

module.exports = window["ReactJSXRuntime"];

/***/ },

/***/ "@wordpress/block-editor"
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
(module) {

module.exports = window["wp"]["blockEditor"];

/***/ },

/***/ "@wordpress/blocks"
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
(module) {

module.exports = window["wp"]["blocks"];

/***/ },

/***/ "@wordpress/components"
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
(module) {

module.exports = window["wp"]["components"];

/***/ },

/***/ "@wordpress/i18n"
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["i18n"];

/***/ },

/***/ "./blocks/listing-search/block.json"
/*!******************************************!*\
  !*** ./blocks/listing-search/block.json ***!
  \******************************************/
(module) {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"listora/listing-search","version":"1.0.0","title":"Listing Search","category":"listora","icon":"search","description":"Search form with filters, keyword, location, and type selector for directory listings.","keywords":["search","filter","directory","listing"],"supports":{"html":false,"align":["wide","full"],"spacing":{"margin":true,"padding":true},"color":{"background":true,"text":false}},"attributes":{"layout":{"type":"string","default":"horizontal","enum":["horizontal","stacked"]},"listingType":{"type":"string","default":""},"showKeyword":{"type":"boolean","default":true},"showLocation":{"type":"boolean","default":true},"showTypeFilter":{"type":"boolean","default":true},"showMoreFilters":{"type":"boolean","default":true},"showNearMe":{"type":"boolean","default":true},"placeholder":{"type":"string","default":""},"defaultSort":{"type":"string","default":"featured"}},"textdomain":"wb-listora","editorScript":"file:../../build/blocks/listing-search/index.js","viewScript":"file:../../build/blocks/listing-search/view.js","style":"file:./style.css","render":"file:./render.php"}');

/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!********************************************!*\
  !*** ./src/blocks/listing-search/index.js ***!
  \********************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _blocks_listing_search_block_json__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../../blocks/listing-search/block.json */ "./blocks/listing-search/block.json");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);
/**
 * Listing Search Block — Editor registration.
 *
 * @package WBListora
 */







(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_blocks_listing_search_block_json__WEBPACK_IMPORTED_MODULE_4__.name, {
  edit({
    attributes,
    setAttributes
  }) {
    const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps)({
      className: 'listora-search listora-search--' + attributes.layout
    });
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InspectorControls, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
          title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Search Settings', 'wb-listora'),
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Layout', 'wb-listora'),
            value: attributes.layout,
            options: [{
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Horizontal Bar', 'wb-listora'),
              value: 'horizontal'
            }, {
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Stacked', 'wb-listora'),
              value: 'stacked'
            }],
            onChange: layout => setAttributes({
              layout
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Pre-filter by Listing Type', 'wb-listora'),
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Leave empty to show all types. Enter a type slug (e.g., "restaurant") to pre-filter.', 'wb-listora'),
            value: attributes.listingType,
            onChange: listingType => setAttributes({
              listingType
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Placeholder Text', 'wb-listora'),
            value: attributes.placeholder,
            onChange: placeholder => setAttributes({
              placeholder
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Default Sort', 'wb-listora'),
            value: attributes.defaultSort,
            options: [{
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Featured', 'wb-listora'),
              value: 'featured'
            }, {
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Newest', 'wb-listora'),
              value: 'newest'
            }, {
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Rating', 'wb-listora'),
              value: 'rating'
            }, {
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Distance', 'wb-listora'),
              value: 'distance'
            }, {
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Relevance', 'wb-listora'),
              value: 'relevance'
            }],
            onChange: defaultSort => setAttributes({
              defaultSort
            })
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
          title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Visibility', 'wb-listora'),
          initialOpen: false,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Show Keyword Search', 'wb-listora'),
            checked: attributes.showKeyword,
            onChange: showKeyword => setAttributes({
              showKeyword
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Show Location Search', 'wb-listora'),
            checked: attributes.showLocation,
            onChange: showLocation => setAttributes({
              showLocation
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Show Type Filter', 'wb-listora'),
            checked: attributes.showTypeFilter,
            onChange: showTypeFilter => setAttributes({
              showTypeFilter
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Show More Filters', 'wb-listora'),
            checked: attributes.showMoreFilters,
            onChange: showMoreFilters => setAttributes({
              showMoreFilters
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Show Near Me Button', 'wb-listora'),
            checked: attributes.showNearMe,
            onChange: showNearMe => setAttributes({
              showNearMe
            })
          })]
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
        ...blockProps,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
          className: "listora-search__bar",
          children: [attributes.showKeyword && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
            className: "listora-search__field listora-search__field--keyword",
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("input", {
              type: "search",
              className: "listora-input listora-search__input",
              placeholder: attributes.placeholder || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Search listings...', 'wb-listora'),
              disabled: true,
              style: {
                paddingInlineStart: '2.2rem'
              }
            })
          }), attributes.showLocation && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
            className: "listora-search__field listora-search__field--location",
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("input", {
              type: "text",
              className: "listora-input listora-search__input",
              placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Location...', 'wb-listora'),
              disabled: true,
              style: {
                paddingInlineStart: '2.2rem'
              }
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("button", {
            className: "listora-btn listora-btn--primary listora-search__submit",
            disabled: true,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Search', 'wb-listora')
          })]
        }), attributes.showTypeFilter && !attributes.listingType && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
          className: "listora-search__type-tabs",
          style: {
            marginBlockStart: '0.75rem'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("span", {
            className: "listora-search__type-tab is-active",
            children: [" ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('All', 'wb-listora'), " "]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("span", {
            className: "listora-search__type-tab",
            children: [" ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Business', 'wb-listora'), " "]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("span", {
            className: "listora-search__type-tab",
            children: [" ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Restaurant', 'wb-listora'), " "]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("span", {
            className: "listora-search__type-tab",
            children: [" ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Real Estate', 'wb-listora'), " "]
          })]
        }), attributes.showMoreFilters && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
          style: {
            marginBlockStart: '0.5rem',
            fontSize: '0.85rem',
            color: '#666'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('▼ More Filters (expand on frontend)', 'wb-listora')
        })]
      })]
    });
  },
  save() {
    // Dynamic block — rendered via render.php.
    return null;
  }
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map
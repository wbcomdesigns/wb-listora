/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "@wordpress/interactivity"
/*!***************************************!*\
  !*** external ["wp","interactivity"] ***!
  \***************************************/
(module) {

module.exports = window["wp"]["interactivity"];

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
/*!*******************************************!*\
  !*** ./src/blocks/listing-search/view.js ***!
  \*******************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__);
/**
 * Listing Search Block — Interactivity API view module.
 *
 * Extends the shared store with search-block-specific actions.
 *
 * @package WBListora
 */


const {
  state,
  actions
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('listora/directory', {
  actions: {
    /**
     * Toggle the filters panel visibility.
     */
    toggleFiltersPanel() {
      const panel = document.getElementById('listora-filters-panel');
      const btn = document.querySelector('.listora-search__toggle-btn');
      if (!panel) return;
      const isVisible = !panel.classList.contains('is-hidden');
      if (isVisible) {
        panel.classList.add('is-hidden');
        if (btn) btn.setAttribute('aria-expanded', 'false');
      } else {
        panel.classList.remove('is-hidden');
        if (btn) btn.setAttribute('aria-expanded', 'true');

        // Focus first input in panel.
        const firstInput = panel.querySelector('input, select');
        if (firstInput) {
          setTimeout(() => firstInput.focus(), 100);
        }
      }
    },
    /**
     * Handle type selection from the dropdown (not tabs).
     * The tabs use selectType via context, this handles the <select> change.
     */
    selectTypeFromDropdown(event) {
      const slug = event.target.value;
      // Set context for the shared selectType action.
      const ctx = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      ctx.typeSlug = slug;
      actions.selectType();
    }
  }
});

/**
 * Initialize: hide filters panel by default.
 */
document.addEventListener('DOMContentLoaded', () => {
  const panel = document.getElementById('listora-filters-panel');
  if (panel) {
    panel.classList.add('is-hidden');
  }
});
})();

/******/ })()
;
//# sourceMappingURL=view.js.map
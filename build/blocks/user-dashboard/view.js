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
  !*** ./src/blocks/user-dashboard/view.js ***!
  \*******************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__);
/**
 * User Dashboard — Interactivity API view module.
 *
 * Handles tab switching, URL hash sync, keyboard nav, and listing menus.
 *
 * @package WBListora
 */


(0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('listora/directory', {
  actions: {
    /**
     * Switch dashboard tab.
     */
    switchDashTab() {
      const ctx = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const tabId = ctx.tabId;
      const el = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      const dashboard = el.ref.closest('.listora-dashboard');
      if (!dashboard) return;

      // Deactivate all tabs and panels.
      dashboard.querySelectorAll('.listora-dashboard__nav-item, .listora-dashboard__tab').forEach(tab => {
        tab.classList.remove('is-active');
        tab.setAttribute('aria-selected', 'false');
      });
      dashboard.querySelectorAll('.listora-dashboard__panel').forEach(panel => {
        panel.hidden = true;
      });

      // Activate clicked tab and panel.
      const tab = dashboard.querySelector(`#dash-tab-${tabId}`);
      const panel = dashboard.querySelector(`#dash-panel-${tabId}`);
      if (tab) {
        tab.classList.add('is-active');
        tab.setAttribute('aria-selected', 'true');
      }
      if (panel) {
        panel.hidden = false;
      }

      // Update URL hash.
      if (typeof window !== 'undefined') {
        window.history.replaceState(null, '', `#${tabId}`);
      }
    },
    /**
     * Toggle listing three-dot menu.
     */
    toggleListingMenu() {
      const el = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      const dropdown = el.ref.closest('.listora-dashboard__menu-wrap')?.querySelector('.listora-dashboard__menu-dropdown');
      if (!dropdown) return;
      const isOpen = !dropdown.hidden;

      // Close all other open menus first.
      document.querySelectorAll('.listora-dashboard__menu-dropdown').forEach(d => {
        d.hidden = true;
      });
      dropdown.hidden = isOpen;

      // Close on outside click.
      if (!isOpen) {
        const closeHandler = e => {
          if (!dropdown.contains(e.target) && !el.ref.contains(e.target)) {
            dropdown.hidden = true;
            document.removeEventListener('click', closeHandler);
          }
        };
        setTimeout(() => document.addEventListener('click', closeHandler), 0);
      }
    }
  },
  callbacks: {
    /**
     * On dashboard init — restore tab from URL hash, setup keyboard nav.
     */
    onDashboardInit() {
      if (typeof window === 'undefined') return;
      const dashboard = document.querySelector('.listora-dashboard');
      if (!dashboard) return;

      // Restore tab from URL hash.
      const hash = window.location.hash.replace('#', '');
      if (hash) {
        const tab = dashboard.querySelector(`#dash-tab-${hash}`);
        if (tab) {
          tab.click();
        }
      }

      // Keyboard navigation for sidebar items.
      const sidebar = dashboard.querySelector('.listora-dashboard__sidebar');
      if (sidebar) {
        sidebar.addEventListener('keydown', e => {
          if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
          e.preventDefault();
          const items = Array.from(sidebar.querySelectorAll('.listora-dashboard__nav-item'));
          const current = document.activeElement;
          const idx = items.indexOf(current);
          let next;
          if (e.key === 'ArrowDown') {
            next = items[Math.min(idx + 1, items.length - 1)];
          } else {
            next = items[Math.max(idx - 1, 0)];
          }
          if (next) {
            next.focus();
          }
        });
      }
    }
  }
});
})();

/******/ })()
;
//# sourceMappingURL=view.js.map
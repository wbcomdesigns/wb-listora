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
  !*** ./src/blocks/listing-detail/view.js ***!
  \*******************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__);
/**
 * Listing Detail — Interactivity API view module.
 *
 * Handles tab switching, gallery image switching, and detail map.
 *
 * @package WBListora
 */


(0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('listora/directory', {
  actions: {
    /**
     * Switch active tab.
     */
    switchTab() {
      const ctx = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const tabId = ctx.tabId;
      const el = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();

      // Find parent detail block.
      const detail = el.ref.closest('.listora-detail');
      if (!detail) return;

      // Deactivate all tabs and panels.
      detail.querySelectorAll('.listora-detail__tab').forEach(tab => {
        tab.classList.remove('is-active');
        tab.setAttribute('aria-selected', 'false');
      });
      detail.querySelectorAll('.listora-detail__panel').forEach(panel => {
        panel.hidden = true;
      });

      // Activate clicked tab and panel.
      const tab = detail.querySelector(`#tab-${tabId}`);
      const panel = detail.querySelector(`#panel-${tabId}`);
      if (tab) {
        tab.classList.add('is-active');
        tab.setAttribute('aria-selected', 'true');
      }
      if (panel) {
        panel.hidden = false;
      }

      // Initialize map if map tab clicked.
      if (tabId === 'map') {
        initDetailMap(detail);
      }

      // Update URL hash for direct linking.
      if (typeof window !== 'undefined') {
        window.history.replaceState(null, '', `#${tabId}`);
      }
    },
    /**
     * Submit lead/contact form to listing owner (Pro feature).
     */
    async submitLeadForm(event) {
      event.preventDefault();
      const ctx = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const el = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      const form = el.ref.closest('.listora-lead-form__form') || el.ref;
      const msgDiv = form.querySelector('.listora-lead-form__message');
      const submitBtn = form.querySelector('button[type="submit"]');
      const name = form.querySelector('input[name="name"]')?.value?.trim();
      const email = form.querySelector('input[name="email"]')?.value?.trim();
      const phone = form.querySelector('input[name="phone"]')?.value?.trim() || '';
      const message = form.querySelector('textarea[name="message"]')?.value?.trim();
      const hp = form.querySelector('input[name="hp"]')?.value || '';

      // Validate required fields.
      if (!name || !email || !message) {
        if (msgDiv) {
          msgDiv.hidden = false;
          msgDiv.textContent = 'Please fill in all required fields.';
          msgDiv.style.color = 'var(--listora-error, #d63638)';
        }
        return;
      }
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';
      }
      try {
        const response = await window.wp.apiFetch({
          path: `/listora/v1/listings/${ctx.listingId}/contact`,
          method: 'POST',
          data: {
            name,
            email,
            phone,
            message,
            hp
          }
        });
        if (msgDiv) {
          msgDiv.hidden = false;
          msgDiv.textContent = response.message || 'Message sent successfully!';
          msgDiv.style.color = 'var(--listora-success, #00a32a)';
        }

        // Reset form on success.
        form.reset();
      } catch (error) {
        if (msgDiv) {
          msgDiv.hidden = false;
          msgDiv.textContent = error.message || 'Failed to send message. Please try again.';
          msgDiv.style.color = 'var(--listora-error, #d63638)';
        }
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Send Message';
        }
      }
    },
    /**
     * Submit review from the detail block's inline review form.
     */
    async submitDetailReviewForm(event) {
      event.preventDefault();
      const ctx = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const el = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      const form = el.ref.closest('.listora-reviews__form') || el.ref;
      const rating = form.querySelector('input[name="overall_rating"]:checked')?.value;
      const title = form.querySelector('input[name="title"]')?.value;
      const content = form.querySelector('textarea[name="content"]')?.value;
      if (!rating || !title || !content) return;

      // Collect criteria ratings (Pro multi-criteria fields).
      const criteriaRatings = {};
      form.querySelectorAll('input[name^="criteria_ratings["]:checked').forEach(input => {
        const match = input.name.match(/^criteria_ratings\[([^\]]+)\]$/);
        if (match) {
          criteriaRatings[match[1]] = parseInt(input.value, 10);
        }
      });
      const submitBtn = form.querySelector('button[type="submit"]');
      const msgDiv = form.querySelector('.listora-reviews__form-message');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
      }
      const requestData = {
        listing_id: ctx.listingId,
        overall_rating: parseInt(rating, 10),
        title,
        content
      };
      if (Object.keys(criteriaRatings).length > 0) {
        requestData.criteria_ratings = criteriaRatings;
      }
      try {
        const response = await window.wp.apiFetch({
          path: `/listora/v1/listings/${ctx.listingId}/reviews`,
          method: 'POST',
          data: requestData
        });
        if (msgDiv) {
          msgDiv.hidden = false;
          msgDiv.textContent = response.message || 'Review submitted!';
          msgDiv.style.color = 'var(--listora-success)';
        }
        setTimeout(() => {
          const wrapper = form.closest('.listora-reviews__form-wrapper');
          if (wrapper) wrapper.hidden = true;
          window.location.reload();
        }, 2000);
      } catch (error) {
        if (msgDiv) {
          msgDiv.hidden = false;
          msgDiv.textContent = error.message || 'Failed to submit review.';
          msgDiv.style.color = 'var(--listora-error)';
        }
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Submit Review';
        }
      }
    },
    /**
     * Toggle review form visibility in the detail block.
     */
    toggleDetailReviewForm() {
      const el = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      const detail = el.ref.closest('.listora-detail');
      const form = detail?.querySelector('#listora-detail-review-form');
      if (form) {
        form.hidden = !form.hidden;
        if (!form.hidden) {
          const firstInput = form.querySelector('input[type="radio"], input[type="text"]');
          if (firstInput) firstInput.focus();
        }
      }
    },
    /**
     * Switch gallery main image.
     */
    switchGalleryImage() {
      const ctx = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const el = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      const detail = el.ref.closest('.listora-detail');
      if (!detail) return;

      // Update main image.
      const mainImg = detail.querySelector('.listora-detail__gallery-image');
      if (mainImg && ctx.imageSrc) {
        mainImg.src = ctx.imageSrc;
      }

      // Update active thumb.
      detail.querySelectorAll('.listora-detail__gallery-thumb').forEach(thumb => {
        thumb.classList.remove('is-active');
      });
      el.ref.classList.add('is-active');
    }
  },
  callbacks: {
    /**
     * On detail block init — check URL hash for active tab.
     */
    onDetailInit() {
      if (typeof window === 'undefined') return;
      const hash = window.location.hash.replace('#', '');
      if (hash) {
        const el = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
        const detail = el.ref.closest('.listora-detail');
        const tab = detail?.querySelector(`#tab-${hash}`);
        if (tab) {
          tab.click();
        }
      }
    }
  }
});

/**
 * Initialize Leaflet map in detail view.
 *
 * @param {HTMLElement} detail The detail block element.
 */
function initDetailMap(detail) {
  const mapEl = detail.querySelector('#listora-detail-map');
  if (!mapEl || mapEl._leafletMap || typeof L === 'undefined') return;
  const lat = parseFloat(mapEl.dataset.lat);
  const lng = parseFloat(mapEl.dataset.lng);
  if (!lat || !lng) return;
  const map = L.map(mapEl).setView([lat, lng], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19
  }).addTo(map);
  L.marker([lat, lng]).addTo(map);
  mapEl._leafletMap = map;

  // Fix map container size after tab becomes visible.
  setTimeout(() => map.invalidateSize(), 100);
}
})();

/******/ })()
;
//# sourceMappingURL=view.js.map
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
/*!***********************************************!*\
  !*** ./src/blocks/listing-submission/view.js ***!
  \***********************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__);
/**
 * Listing Submission — Interactivity API view module.
 *
 * Handles multi-step navigation, form validation, draft saving, and submission.
 *
 * @package WBListora
 */


(0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('listora/directory', {
  actions: {
    /**
     * Move to next step.
     */
    nextSubmissionStep() {
      const el = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      const form = el.ref.closest('.listora-submission');
      if (!form) return;
      const steps = form.querySelectorAll('.listora-submission__step');
      const indicators = form.querySelectorAll('.listora-submission__step-indicator');
      const lines = form.querySelectorAll('.listora-submission__step-line');
      let currentIdx = -1;
      steps.forEach((step, i) => {
        if (!step.hidden) currentIdx = i;
      });
      if (!validateStep(steps[currentIdx])) return;
      if (currentIdx < steps.length - 1) {
        steps[currentIdx].hidden = true;
        steps[currentIdx + 1].hidden = false;
        if (indicators[currentIdx]) {
          indicators[currentIdx].classList.remove('is-current');
          indicators[currentIdx].classList.add('is-completed');
        }
        if (indicators[currentIdx + 1]) {
          indicators[currentIdx + 1].classList.add('is-current');
        }
        // Mark connecting line as completed.
        if (lines[currentIdx]) {
          lines[currentIdx].classList.add('is-completed');
        }
        updateNavButtons(form, currentIdx + 1, steps.length);
        if (currentIdx + 1 === steps.length - 1) {
          buildPreview(form);
        }
        if (steps[currentIdx + 1].dataset.step === 'details') {
          initMapPickers(steps[currentIdx + 1]);
        }
        form.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    },
    /**
     * Move to previous step.
     */
    prevSubmissionStep() {
      const el = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      const form = el.ref.closest('.listora-submission');
      if (!form) return;
      const steps = form.querySelectorAll('.listora-submission__step');
      const indicators = form.querySelectorAll('.listora-submission__step-indicator');
      const lines = form.querySelectorAll('.listora-submission__step-line');
      let currentIdx = -1;
      steps.forEach((step, i) => {
        if (!step.hidden) currentIdx = i;
      });
      if (currentIdx > 0) {
        steps[currentIdx].hidden = true;
        steps[currentIdx - 1].hidden = false;
        if (indicators[currentIdx]) {
          indicators[currentIdx].classList.remove('is-current');
        }
        if (indicators[currentIdx - 1]) {
          indicators[currentIdx - 1].classList.remove('is-completed');
          indicators[currentIdx - 1].classList.add('is-current');
        }
        // Revert connecting line.
        if (lines[currentIdx - 1]) {
          lines[currentIdx - 1].classList.remove('is-completed');
        }
        updateNavButtons(form, currentIdx - 1, steps.length);
        form.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    },
    /**
     * Select listing type — auto-advance.
     */
    selectSubmissionType() {
      setTimeout(() => {
        const el = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
        const nextBtn = el.ref.closest('.listora-submission')?.querySelector('.listora-submission__next');
        if (nextBtn) nextBtn.click();
      }, 300);
    },
    /**
     * Handle form submission via REST API.
     * When a listing_id hidden field is present (edit mode), uses POST /submit with the
     * listing_id in the body — the server routes to update instead of create.
     */
    async handleSubmission(event) {
      event.preventDefault();
      const el = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      const form = el.ref.closest('.listora-submission');
      const formEl = form?.querySelector('.listora-submission__form');
      if (!formEl) return;
      const hp = formEl.querySelector('[name="listora_hp_field"]');
      if (hp && hp.value) return;
      const submitBtn = form.querySelector('.listora-submission__submit-btn');
      const errorDiv = form.querySelector('.listora-submission__error');
      const successDiv = form.querySelector('.listora-submission__success');

      // Detect edit mode via hidden listing_id field.
      const listingIdInput = formEl.querySelector('[name="listing_id"]');
      const listingId = listingIdInput ? parseInt(listingIdInput.value, 10) : 0;
      const isEditMode = listingId > 0;
      const originalBtnText = submitBtn ? submitBtn.textContent.trim() : '';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = isEditMode ? 'Updating...' : 'Submitting...';
      }
      if (errorDiv) errorDiv.hidden = true;
      try {
        const formData = new FormData(formEl);

        // Always use POST — the server detects listing_id in the body to route to update.
        await window.wp.apiFetch({
          path: '/listora/v1/submit',
          method: 'POST',
          body: formData
        });
        formEl.hidden = true;
        const progress = form.querySelector('.listora-submission__progress');
        if (progress) progress.remove();
        const nav = form.querySelector('.listora-submission__nav');
        if (nav) nav.remove();
        if (successDiv) successDiv.hidden = false;
      } catch (error) {
        if (errorDiv) {
          errorDiv.hidden = false;
          const p = errorDiv.querySelector('p');
          if (p) p.textContent = error.message || 'Submission failed. Please try again.';
        }
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = originalBtnText || (isEditMode ? 'Update Listing' : 'Submit Listing');
        }
      }
    },
    /**
     * Save draft via REST API.
     * In edit mode, updates the existing listing. Otherwise creates a new draft.
     */
    async saveDraft() {
      const el = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      const form = el.ref.closest('.listora-submission');
      const formEl = form?.querySelector('.listora-submission__form');
      if (!formEl) return;
      const btn = form.querySelector('.listora-submission__save-draft');
      if (btn) btn.textContent = 'Saving...';
      try {
        const formData = new FormData(formEl);
        // listing_id already in FormData when editing; server detects it.
        // For new listings only, explicitly set draft status.
        const listingIdInput = formEl.querySelector('[name="listing_id"]');
        const isEditMode = listingIdInput && parseInt(listingIdInput.value, 10) > 0;
        if (!isEditMode) {
          formData.set('status', 'draft');
        }
        await window.wp.apiFetch({
          path: '/listora/v1/submit',
          method: 'POST',
          body: formData
        });
        if (btn) btn.textContent = '✓ Saved';
        setTimeout(() => {
          if (btn) btn.textContent = 'Save Draft';
        }, 2000);
      } catch {
        if (btn) btn.textContent = 'Save Draft';
      }
    },
    /**
     * Auto-save draft (debounced, called on field changes).
     */
    autoSaveDraft() {
      const el = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      const form = el.ref.closest('.listora-submission');
      const formEl = form?.querySelector('.listora-submission__form');
      const indicator = form?.querySelector('.listora-submission__autosave');
      if (!formEl) return;

      // Debounce 30 seconds.
      if (form._autoSaveTimeout) clearTimeout(form._autoSaveTimeout);
      form._autoSaveTimeout = setTimeout(async () => {
        if (indicator) {
          indicator.textContent = 'Saving...';
          indicator.className = 'listora-submission__autosave listora-submission__autosave--saving';
        }
        try {
          const formData = new FormData(formEl);
          formData.set('status', 'draft');
          await window.wp.apiFetch({
            path: '/listora/v1/submit',
            method: 'POST',
            body: formData
          });
          if (indicator) {
            indicator.textContent = 'Draft saved';
            indicator.className = 'listora-submission__autosave listora-submission__autosave--saved';
          }
        } catch {
          if (indicator) {
            indicator.textContent = '';
            indicator.className = 'listora-submission__autosave';
          }
        }
      }, 30000);
    },
    /**
     * Validate a field on blur.
     */
    validateField() {
      const el = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      const field = el.ref.closest('.listora-submission__field');
      const input = el.ref;
      if (!field || !input) return;
      if (input.required && !input.value.trim()) {
        field.classList.remove('listora-submission__field--valid');
        field.classList.add('listora-submission__field--error');
      } else if (input.value.trim()) {
        field.classList.remove('listora-submission__field--error');
        field.classList.add('listora-submission__field--valid');
      } else {
        field.classList.remove('listora-submission__field--valid', 'listora-submission__field--error');
      }
    },
    /**
     * Open WP media library for image uploads.
     */
    openMediaUpload() {
      const ctx = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const target = ctx.uploadTarget;
      if (typeof wp === 'undefined' || !wp.media) {
        return;
      }
      const isGallery = target === 'gallery';
      const frame = wp.media({
        title: isGallery ? 'Select Gallery Images' : 'Select Image',
        multiple: isGallery,
        library: {
          type: 'image'
        }
      });
      frame.on('select', function () {
        const selection = frame.state().get('selection');
        if (isGallery) {
          const ids = [];
          selection.each(attachment => {
            ids.push(attachment.id);
            addGalleryThumb(attachment.toJSON());
          });
          const input = document.querySelector('input[name="gallery"]');
          if (input) {
            const existing = input.value ? input.value.split(',') : [];
            input.value = [...existing, ...ids].join(',');
          }
        } else {
          const attachment = selection.first().toJSON();
          const input = document.querySelector(`input[name="${target}"]`);
          if (input) input.value = attachment.id;

          // Show preview using safe DOM methods.
          const zone = document.querySelector(`[data-wp-context*="${target}"]`);
          if (zone) {
            zone.textContent = '';
            const img = document.createElement('img');
            img.src = attachment.sizes?.medium?.url || attachment.url;
            img.alt = '';
            img.style.cssText = 'max-width:100%;border-radius:var(--listora-card-radius);';
            zone.appendChild(img);
          }
        }
      });
      frame.open();
    }
  }
});

/**
 * Validate required fields in the current step.
 */
function validateStep(step) {
  if (!step) return true;
  const required = step.querySelectorAll('[required]');
  let valid = true;
  required.forEach(field => {
    if (!field.value.trim()) {
      field.classList.add('is-invalid');
      field.style.borderColor = 'var(--listora-error)';
      valid = false;
      field.addEventListener('input', () => {
        field.classList.remove('is-invalid');
        field.style.borderColor = '';
      }, {
        once: true
      });
    }
  });
  if (!valid) {
    const firstInvalid = step.querySelector('.is-invalid');
    if (firstInvalid) firstInvalid.focus();
  }
  return valid;
}

/**
 * Show/hide navigation buttons based on current step.
 */
function updateNavButtons(form, idx, total) {
  const backBtn = form.querySelector('.listora-submission__back');
  const nextBtn = form.querySelector('.listora-submission__next');
  const submitBtn = form.querySelector('.listora-submission__submit-btn');
  const draftBtn = form.querySelector('.listora-submission__save-draft');
  if (backBtn) backBtn.hidden = idx === 0;
  if (nextBtn) nextBtn.hidden = idx === total - 1;
  if (submitBtn) submitBtn.hidden = idx !== total - 1;
  if (draftBtn) draftBtn.hidden = idx === total - 1;
}

/**
 * Build a preview from form data using safe DOM methods.
 */
function buildPreview(form) {
  const preview = form.querySelector('#listora-preview-content');
  if (!preview) return;
  const title = form.querySelector('[name="title"]')?.value || '';
  const desc = form.querySelector('[name="description"]')?.value || '';
  const category = form.querySelector('[name="category"] option:checked')?.textContent || '';

  // Clear previous preview safely.
  preview.textContent = '';
  const h3 = document.createElement('h3');
  h3.style.cssText = 'margin:0 0 0.5rem';
  h3.textContent = title || 'Untitled';
  preview.appendChild(h3);
  if (category) {
    const badge = document.createElement('span');
    badge.className = 'listora-badge listora-badge--type';
    badge.textContent = category;
    preview.appendChild(badge);
  }
  const p = document.createElement('p');
  p.style.cssText = 'margin:0.5rem 0;color:var(--listora-text-secondary)';
  p.textContent = desc.length > 200 ? desc.substring(0, 200) + '...' : desc;
  preview.appendChild(p);
}

/**
 * Add a gallery thumbnail using safe DOM methods.
 */
function addGalleryThumb(attachment) {
  const thumbs = document.querySelector('#listora-gallery-thumbs');
  if (!thumbs) return;
  const url = attachment.sizes?.thumbnail?.url || attachment.url;
  const div = document.createElement('div');
  div.style.cssText = 'width:80px;height:80px;border-radius:var(--listora-radius-md);overflow:hidden;position:relative;';
  const img = document.createElement('img');
  img.src = url;
  img.alt = '';
  img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
  div.appendChild(img);
  thumbs.appendChild(div);
}

/**
 * Init Leaflet map pickers in the details step.
 */
function initMapPickers(step) {
  if (typeof L === 'undefined') return;
  step.querySelectorAll('.listora-submission__map-picker').forEach(el => {
    if (el._leafletMap) return;
    const defaultLat = 40.7128;
    const defaultLng = -74.006;
    const map = L.map(el).setView([defaultLat, defaultLng], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap',
      maxZoom: 19
    }).addTo(map);
    const marker = L.marker([defaultLat, defaultLng], {
      draggable: true
    }).addTo(map);
    marker.on('dragend', () => {
      const pos = marker.getLatLng();
      const parent = el.closest('.listora-submission__map-field');
      if (parent) {
        parent.querySelector('[name$="[lat]"]').value = pos.lat.toFixed(7);
        parent.querySelector('[name$="[lng]"]').value = pos.lng.toFixed(7);
      }
    });
    map.on('click', e => {
      marker.setLatLng(e.latlng);
      const parent = el.closest('.listora-submission__map-field');
      if (parent) {
        parent.querySelector('[name$="[lat]"]').value = e.latlng.lat.toFixed(7);
        parent.querySelector('[name$="[lng]"]').value = e.latlng.lng.toFixed(7);
      }
    });
    el._leafletMap = map;
    setTimeout(() => map.invalidateSize(), 200);
  });
}
})();

/******/ })()
;
//# sourceMappingURL=view.js.map
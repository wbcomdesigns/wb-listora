/**
 * Listing Grid — Interactivity API view module.
 *
 * Handles dynamic result updates after search.
 * Server-renders initial results, JS updates on search/filter/page changes.
 *
 * @package WBListora
 */

import '../../interactivity/store.js';

// The grid reads results from the shared store (state.results).
// When search updates results, the server-rendered cards remain but
// the Interactivity API reactivity handles hiding/showing the loading state.
//
// For full dynamic rendering (replacing cards via JS), we would need
// wp_interactivity_process_directives_of_interactive_blocks or
// a wp-router approach. For v1, we use a hybrid:
// - Initial page load = server-rendered cards (SEO)
// - Search = full page navigation with URL params (progressive enhancement)
// - Interactivity handles: loading state, pagination, sort, view mode toggle
//
// View mode needs no block-level code: the isGridView/isListView getters in
// the shared store read the visitor's remembered choice, then the block's
// Default View seeded by render.php (card 10294600329).

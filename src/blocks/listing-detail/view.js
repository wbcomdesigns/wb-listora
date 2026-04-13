/**
 * Listing Detail — Interactivity API view module.
 *
 * All actions are in the shared store (src/interactivity/store.js).
 * This module only needs to import the store to ensure it loads.
 *
 * @package WBListora
 */

// Import the shared store — ensures all actions (favorites, share, claim,
// tabs, gallery, reviews) are registered before directives are processed.
import '../../interactivity/store.js';

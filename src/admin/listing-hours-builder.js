/**
 * Business-hours builder for the wp-admin listing editor.
 *
 * The Listing Fields metabox reuses the frontend submission field renderer, so
 * the hours field prints the same builder markup as the Add Listing wizard. On
 * the frontend the wizard's view module drives it; in wp-admin nothing did, so
 * "+ Add another time" and the Open 24 hours / Closed toggles did nothing. This
 * starts the same builder (src/utils/business-hours-builder.js) here.
 *
 * @package WBListora
 */

import { initBusinessHoursBuilder } from '../utils/business-hours-builder.js';

initBusinessHoursBuilder();

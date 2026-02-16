/**
 * Customizer Controls - Entry point
 *
 * Imports and initializes all Customizer control modules.
 * Loaded in the controls frame (admin sidebar) via
 * customize_controls_enqueue_scripts.
 *
 * @package BS_Custom
 */

import { init as initRange } from './controls/customizer-range.js';
import { init as initContactPoint } from './controls/customizer-contact-point.js';
import { init as initSocialNetworks } from './controls/customizer-social-networks.js';
import { init as initExportImport } from './controls/customizer-export-import.js';
import { init as initTranslation } from './controls/customizer-translation.js';
import { init as initFontFamily } from './controls/customizer-font-family.js';
import { init as initFontWeights } from './controls/customizer-font-weights.js';

initRange();
initContactPoint();
initSocialNetworks();
initExportImport();
initTranslation();
initFontFamily();
initFontWeights();

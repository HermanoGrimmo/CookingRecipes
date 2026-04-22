/*
 * Haupt-Einstiegspunkt des Frontend-Bundles.
 * Wird via webpack.config.js als Entry "app" kompiliert und im Layout
 * über {{ encore_entry_script_tags('app') }} eingebunden.
 */

// Stimulus-App starten (inkl. ux-live-component)
import './bootstrap.js';

// Importiertes CSS landet im selben Entry (app.css)
import './styles/app.css';

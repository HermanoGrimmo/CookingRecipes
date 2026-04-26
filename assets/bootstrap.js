import { startStimulusApp } from '@symfony/stimulus-bridge';

// Registriert Stimulus-Controller aus assets/controllers/ und assets/controllers.json
// (insb. live_controller von @symfony/ux-live-component).
export const app = startStimulusApp(require.context(
    '@symfony/stimulus-bridge/lazy-controller-loader!./controllers',
    true,
    /\.[jt]sx?$/,
));

// Hier können eigene Drittanbieter-Controller manuell registriert werden:
// app.register('some_controller_name', SomeImportedController);

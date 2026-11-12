import './app';
import { init as initRepeaters } from './modules/repeater';
import { init as initLeadBoard } from './modules/lead-board';

document.addEventListener('DOMContentLoaded', () => {
    initRepeaters();
    initLeadBoard();
});

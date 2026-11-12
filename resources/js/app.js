import { applyStoredTheme, init as initTheme } from './modules/theme';
import { init as initDropdowns } from './modules/dropdown';
import { init as initToasts } from './modules/toast';
import { init as initConfirm } from './modules/confirm';
import { init as initAutoSubmit } from './modules/auto-submit';

applyStoredTheme();

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initDropdowns();
    initToasts();
    initConfirm();
    initAutoSubmit();
});

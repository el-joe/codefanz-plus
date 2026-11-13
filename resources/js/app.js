import { applyStoredTheme, init as initTheme } from './modules/theme';
import { init as initDropdowns } from './modules/dropdown';
import { init as initToasts } from './modules/toast';
import { init as initConfirm } from './modules/confirm';
import { init as initAutoSubmit } from './modules/auto-submit';
import { init as initNav } from './modules/nav';
import { init as initFaqSearch } from './modules/faq-search';
import { init as initStepper } from './modules/stepper';
import { init as initAssistant } from './modules/assistant';
import { init as initConversations } from './modules/conversation';
import { init as initDialogs } from './modules/dialog';
import { init as initCopy } from './modules/copy';
import { init as initPrint } from './modules/print';
import { init as initConsent } from './modules/consent';
import { init as initTabs } from './modules/tabs';

applyStoredTheme();

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initDropdowns();
    initToasts();
    initConfirm();
    initAutoSubmit();
    initNav();
    initFaqSearch();
    initStepper();
    initAssistant();
    initConversations();
    initDialogs();
    initCopy();
    initPrint();
    initConsent();
    initTabs();
});

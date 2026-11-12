import './app';
import { init as initRepeaters } from './modules/repeater';
import { init as initLeadBoard } from './modules/lead-board';
import { init as initTabs } from './modules/tabs';
import { init as initRichEditor } from './modules/rich-editor';
import { init as initCharCount } from './modules/char-count';
import { init as initAnalyticsCharts } from './modules/analytics-charts';
import { init as initSocialComposer } from './modules/social-composer';
import { init as initSocialCalendar } from './modules/social-calendar';

document.addEventListener('DOMContentLoaded', () => {
    initRepeaters();
    initLeadBoard();
    initTabs();
    initRichEditor();
    initCharCount();
    initAnalyticsCharts();
    initSocialComposer();
    initSocialCalendar();
});

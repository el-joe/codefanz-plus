/**
 * Loads the TipTap article editor only on pages that have <textarea data-rich-editor> (see rich-editor-core.js).
 */
export function init() {
    if (!document.querySelector('textarea[data-rich-editor]')) {
        return;
    }

    import('./rich-editor-core').then(({ enhanceAll }) => enhanceAll());
}

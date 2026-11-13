/**
 * Article editor (TipTap). Progressive enhancement of <textarea data-rich-editor>: without JavaScript the
 * textarea stays and accepts HTML. The textarea keeps the form value in sync, so the server always receives
 * HTML — which it sanitizes again (App\Services\Blog\HtmlSanitizer). Images come from the media library
 * dialog [data-media-picker]; links accept https:// and site-relative paths only.
 *
 * The form [data-post-form] also refreshes the editorial checklist ([data-checklist]) while typing.
 */
import { Editor, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { TableKit } from '@tiptap/extension-table';

const Callout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'block+',
    defining: true,

    addAttributes() {
        return {
            type: {
                default: 'info',
                parseHTML: (element) => element.getAttribute('data-callout') || 'info',
                renderHTML: (attributes) => ({ 'data-callout': attributes.type }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'aside' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['aside', mergeAttributes(HTMLAttributes), 0];
    },

    addCommands() {
        return {
            toggleCallout: () => ({ commands }) => commands.toggleWrap(this.name),
        };
    },
});

const LibraryImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            mediaId: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-media-id'),
                renderHTML: (attributes) => (attributes.mediaId ? { 'data-media-id': attributes.mediaId } : {}),
            },
        };
    },
});

const SAFE_URL = /^(https:\/\/[^\s/?#@]+[^\s]*|\/(?!\/)[^\s]*|#[\p{L}\p{N}_-]+)$/u;

export function isSafeUrl(url) {
    return SAFE_URL.test(url.trim());
}

const BUTTONS = [
    { key: 'bold', text: 'B', run: (e) => e.chain().focus().toggleBold().run(), active: (e) => e.isActive('bold') },
    { key: 'italic', text: 'I', run: (e) => e.chain().focus().toggleItalic().run(), active: (e) => e.isActive('italic') },
    { key: 'h2', text: 'H2', run: (e) => e.chain().focus().toggleHeading({ level: 2 }).run(), active: (e) => e.isActive('heading', { level: 2 }) },
    { key: 'h3', text: 'H3', run: (e) => e.chain().focus().toggleHeading({ level: 3 }).run(), active: (e) => e.isActive('heading', { level: 3 }) },
    { key: 'h4', text: 'H4', run: (e) => e.chain().focus().toggleHeading({ level: 4 }).run(), active: (e) => e.isActive('heading', { level: 4 }) },
    { key: 'bullet', text: '•', run: (e) => e.chain().focus().toggleBulletList().run(), active: (e) => e.isActive('bulletList') },
    { key: 'ordered', text: '1.', run: (e) => e.chain().focus().toggleOrderedList().run(), active: (e) => e.isActive('orderedList') },
    { key: 'quote', text: '❝', run: (e) => e.chain().focus().toggleBlockquote().run(), active: (e) => e.isActive('blockquote') },
    { key: 'code', text: '</>', run: (e) => e.chain().focus().toggleCodeBlock().run(), active: (e) => e.isActive('codeBlock') },
    { key: 'callout', text: 'ⓘ', run: (e) => e.chain().focus().toggleCallout().run(), active: (e) => e.isActive('callout') },
    { key: 'table', text: '⊞', run: (e) => e.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run() },
    { key: 'link', text: '🔗', action: 'link', active: (e) => e.isActive('link') },
    { key: 'image', text: '🖼', action: 'image' },
    { key: 'undo', text: '↶', run: (e) => e.chain().focus().undo().run() },
    { key: 'redo', text: '↷', run: (e) => e.chain().focus().redo().run() },
];

function countWords(text) {
    return (text.match(/[\p{L}\p{N}\p{M}'’-]+/gu) || []).length;
}

function createPicker() {
    const dialog = document.querySelector('[data-media-picker]');
    const dataElement = document.querySelector('[data-media-library]');

    if (!dialog || !dataElement) {
        return null;
    }

    const items = JSON.parse(dataElement.textContent || '[]');
    const list = dialog.querySelector('[data-media-picker-list]');
    const altInput = dialog.querySelector('[data-media-picker-alt]');
    let selected = null;
    let onInsert = null;
    let locale = 'en';

    items.forEach((item) => {
        const li = document.createElement('li');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'block w-full overflow-hidden rounded-lg border-2 border-transparent bg-muted aria-pressed:border-brand';
        button.setAttribute('aria-pressed', 'false');
        button.setAttribute('aria-label', item.name);
        const img = document.createElement('img');
        img.src = item.thumb || item.url;
        img.alt = '';
        img.className = 'aspect-square w-full object-cover';
        button.append(img);
        button.addEventListener('click', () => {
            selected = item;
            list.querySelectorAll('button').forEach((other) => other.setAttribute('aria-pressed', other === button ? 'true' : 'false'));
            altInput.value = (item.alt && (item.alt[locale] || item.alt.en)) || '';
        });
        li.append(button);
        list?.append(li);
    });

    dialog.addEventListener('close', () => {
        if (dialog.returnValue === 'insert' && selected && onInsert) {
            onInsert({ src: selected.url, alt: altInput.value.trim(), mediaId: String(selected.id) });
        }

        selected = null;
        list?.querySelectorAll('button').forEach((button) => button.setAttribute('aria-pressed', 'false'));
    });

    return {
        open(forLocale, callback) {
            locale = forLocale;
            onInsert = callback;
            dialog.returnValue = '';
            if (altInput) {
                altInput.value = '';
            }
            dialog.showModal();
        },
    };
}

function enhance(textarea, picker, onChange) {
    const labels = JSON.parse(textarea.dataset.labels || '{}');
    const field = textarea.closest('[data-rich-editor-field]');
    const panel = textarea.closest('[data-tab-panel]');
    const locale = panel?.dataset.locale || document.documentElement.lang;
    const label = field?.querySelector(`label[for="${textarea.id}"]`);
    const stats = field?.querySelector('[data-editor-stats]');

    const wrapper = document.createElement('div');
    wrapper.className = 'overflow-hidden rounded-lg border border-line focus-within:border-line-focus focus-within:ring-4 focus-within:ring-brand/20';
    const toolbar = document.createElement('div');
    toolbar.setAttribute('role', 'toolbar');
    toolbar.setAttribute('aria-label', labels.toolbar || 'Formatting');
    toolbar.setAttribute('aria-controls', `${textarea.id}-editor`);
    toolbar.className = 'flex flex-wrap gap-1 border-b border-line bg-subtle p-1.5';
    const content = document.createElement('div');
    wrapper.append(toolbar, content);
    textarea.after(wrapper);
    textarea.hidden = true;
    field?.querySelector('[data-editor-fallback-note]')?.remove();

    if (label) {
        label.id = label.id || `${textarea.id}-label`;
        label.removeAttribute('for');
        label.addEventListener('click', () => editor.commands.focus());
    }

    const editor = new Editor({
        element: content,
        content: textarea.value,
        // The editor's base styles are injected as a <style> tag, allowed by the page's CSP nonce.
        injectNonce: document.querySelector('meta[name="csp-nonce"]')?.content || undefined,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3, 4] },
                link: {
                    openOnClick: false,
                    autolink: true,
                    defaultProtocol: 'https',
                    HTMLAttributes: { target: null, rel: null },
                    isAllowedUri: (url) => isSafeUrl(url),
                },
            }),
            LibraryImage.configure({ inline: false, allowBase64: false }),
            TableKit.configure({ table: { resizable: false } }),
            Callout,
        ],
        editorProps: {
            attributes: {
                id: `${textarea.id}-editor`,
                role: 'textbox',
                'aria-multiline': 'true',
                'aria-labelledby': label?.id || '',
                dir: panel?.getAttribute('dir') || 'auto',
                class: 'cf-editor prose-article min-h-80 max-w-none bg-surface px-4 py-3 text-fg focus:outline-none',
            },
        },
    });

    const buttons = BUTTONS.map((definition) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = definition.text;
        button.title = labels[definition.key] || definition.key;
        button.setAttribute('aria-label', labels[definition.key] || definition.key);
        button.className = 'min-w-8 rounded-md px-2 py-1 text-sm font-semibold text-fg-secondary hover:bg-surface hover:text-fg aria-pressed:bg-brand-subtle aria-pressed:text-fg-brand';
        if (definition.active) {
            button.setAttribute('aria-pressed', 'false');
        }
        button.addEventListener('click', () => {
            if (definition.action === 'link') {
                const current = editor.getAttributes('link').href || '';
                const url = window.prompt(labels.linkPrompt || 'URL', current);

                if (url === null) {
                    return;
                }

                if (url.trim() === '') {
                    editor.chain().focus().extendMarkRange('link').unsetLink().run();
                } else if (isSafeUrl(url)) {
                    editor.chain().focus().extendMarkRange('link').setLink({ href: url.trim() }).run();
                } else {
                    window.alert(labels.linkInvalid || 'Invalid URL');
                }
            } else if (definition.action === 'image') {
                picker?.open(locale, (image) => editor.chain().focus().setImage(image).run());
            } else {
                definition.run(editor);
            }
        });
        toolbar.append(button);

        return { button, definition };
    });

    const refresh = () => {
        buttons.forEach(({ button, definition }) => {
            if (definition.active) {
                button.setAttribute('aria-pressed', definition.active(editor) ? 'true' : 'false');
            }
        });
    };

    const sync = () => {
        textarea.value = editor.isEmpty ? '' : editor.getHTML();

        if (stats) {
            const words = countWords(editor.getText({ blockSeparator: ' ' }));
            stats.textContent = (labels.words || ':count words · :minutes min read')
                .replace(':count', words)
                .replace(':minutes', Math.max(1, Math.ceil(words / 200)));
        }

        onChange();
    };

    editor.on('update', sync);
    editor.on('selectionUpdate', refresh);
    editor.on('transaction', refresh);
    textarea.value = editor.isEmpty ? '' : editor.getHTML();
}

function liveChecklist(form) {
    const target = document.querySelector('[data-checklist]');
    const url = form.dataset.checklistUrl;
    let timer = null;
    let controller = null;

    if (!target || !url) {
        return () => {};
    }

    const run = async () => {
        const data = new FormData(form);
        data.delete('_method');
        controller?.abort();
        controller = new AbortController();

        try {
            const response = await fetch(url, {
                method: 'POST',
                body: data,
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });

            if (response.ok) {
                target.innerHTML = await response.text();
            }
        } catch {
            // Offline or aborted: keep the last evaluation.
        }
    };

    return () => {
        clearTimeout(timer);
        timer = setTimeout(run, 700);
    };
}

export function enhanceAll() {
    const textareas = document.querySelectorAll('textarea[data-rich-editor]');

    if (textareas.length === 0) {
        return;
    }

    const form = document.querySelector('form[data-post-form]');
    const schedule = form ? liveChecklist(form) : () => {};
    const picker = createPicker();

    textareas.forEach((textarea) => enhance(textarea, picker, schedule));

    if (form) {
        form.addEventListener('input', schedule);
        document.querySelectorAll('[data-checklist-input]').forEach((input) => input.addEventListener('change', schedule));
    }
}

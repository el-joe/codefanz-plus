/**
 * Project conversation thread (<section data-conversation>): polls for new messages every
 * data-poll-seconds (default 20) with fetch, appends them by cloning <template data-conversation-template>
 * and filling it with textContent only (messages are plain text), and posts the composer without a reload.
 * No websockets. Without JavaScript the composer is an ordinary form post.
 */
export function init() {
    document.querySelectorAll('[data-conversation]').forEach(setUp);
}

function setUp(root) {
    const list = root.querySelector('[data-conversation-messages]');
    const template = root.querySelector('[data-conversation-template]');
    const form = root.querySelector('[data-conversation-form]');
    const pollUrl = root.dataset.pollUrl;
    const seconds = Math.max(5, Number(root.dataset.pollSeconds) || 20);
    let after = Number(root.dataset.after) || 0;
    let busy = false;

    const scrollToEnd = () => {
        list.scrollTop = list.scrollHeight;
    };

    const render = (message) => {
        if (message.id <= after || list.querySelector(`[data-message-id="${message.id}"]`)) {
            return;
        }

        list.querySelector('[data-conversation-empty]')?.remove();

        const item = template.content.querySelector('[data-template-root]').cloneNode(true);
        item.dataset.messageId = String(message.id);
        item.removeAttribute('data-template-root');
        const field = (name) => item.querySelector(`[data-field="${name}"]`);

        field('initials').textContent = message.initials;
        field('author').textContent = message.author;
        field('body').textContent = message.body;
        field('time').textContent = message.time;

        if (message.mine) {
            item.classList.add('flex-row-reverse');
            field('initials').classList.add('hidden');
            field('author').classList.add('hidden');
            field('bubble').className = 'max-w-[80%] rounded-xl px-4 py-3 text-sm bg-brand text-fg-on-brand';
        } else {
            field('author').classList.add('text-fg-brand');
        }

        message.attachments.forEach((attachment) => {
            const li = document.createElement('li');
            const link = document.createElement('a');
            link.href = attachment.url;
            link.className = 'inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold ' + (message.mine ? 'bg-white/15' : 'bg-subtle text-fg');
            link.textContent = `${attachment.name} · ${attachment.size}`;
            li.append(link);
            field('attachments').append(li);
        });

        list.append(item);
        after = Math.max(after, message.id);
    };

    const poll = async () => {
        if (busy || document.hidden) {
            return;
        }

        busy = true;

        try {
            const response = await fetch(`${pollUrl}?after=${after}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

            if (response.ok) {
                const { messages } = await response.json();
                const atBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 80;
                messages.forEach(render);

                if (messages.length && atBottom) {
                    scrollToEnd();
                }
            }
        } catch {
            // Offline or signed out: try again on the next tick.
        } finally {
            busy = false;
        }
    };

    scrollToEnd();
    window.setInterval(poll, seconds * 1000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            poll();
        }
    });

    if (!form) {
        return;
    }

    const fileInput = form.querySelector('[data-conversation-file]');
    const fileName = form.querySelector('[data-conversation-file-name]');
    const error = form.querySelector('[data-conversation-error]');
    const textarea = form.querySelector('textarea[name="body"]');

    fileInput?.addEventListener('change', () => {
        fileName.textContent = fileInput.files[0]?.name ?? '';
    });

    textarea.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        error.classList.add('hidden');
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (response.status === 201) {
                const { message } = await response.json();
                render(message);
                scrollToEnd();
                form.reset();
                fileName.textContent = '';
            } else {
                const payload = await response.json().catch(() => ({}));
                const first = payload.errors ? Object.values(payload.errors)[0]?.[0] : null;
                error.textContent = first || root.dataset.errorText;
                error.classList.remove('hidden');
            }
        } catch {
            error.textContent = root.dataset.errorText;
            error.classList.remove('hidden');
        } finally {
            button.disabled = false;
        }
    });
}

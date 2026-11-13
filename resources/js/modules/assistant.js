/**
 * AI Project Assistant chat ([data-assistant]). Talks to the JSON endpoints with fetch (CSRF + the
 * conversation's secret token), renders every message with textContent (never innerHTML), and switches to
 * the guided questionnaire whenever the server reports mode "guided" (AI not configured, paused, over
 * budget or failing). The conversation starts lazily on the first message or answer.
 */
const SVG_NS = 'http://www.w3.org/2000/svg';

function icon(done) {
    const svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('width', '18');
    svg.setAttribute('height', '18');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '2');
    svg.setAttribute('aria-hidden', 'true');
    svg.classList.add('shrink-0', done ? 'text-fg-success' : 'text-fg-tertiary');
    const circle = document.createElementNS(SVG_NS, 'circle');
    circle.setAttribute('cx', '12');
    circle.setAttribute('cy', '12');
    circle.setAttribute('r', '9');
    const path = document.createElementNS(SVG_NS, 'path');
    path.setAttribute('d', done ? 'm8 12 3 3 5-6' : 'M12 8v.01M12 11v5');
    svg.append(circle, path);
    return svg;
}

function format(template, values) {
    return Object.entries(values).reduce((text, [key, value]) => text.replace(`:${key}`, String(value)), template);
}

function formToJson(form) {
    const data = {};
    new FormData(form).forEach((value, key) => {
        if (key.endsWith('[]')) {
            const name = key.slice(0, -2);
            (data[name] ??= []).push(value);
        } else {
            data[key] = value;
        }
    });
    return data;
}

export function init() {
    const root = document.querySelector('[data-assistant]');
    if (!root) {
        return;
    }

    const $ = (selector) => root.querySelector(selector);
    const log = $('[data-assistant-log]');
    const scroller = $('[data-assistant-scroll]');
    const typing = $('[data-assistant-typing]');
    const quick = $('[data-assistant-quick]');
    const composer = $('[data-assistant-composer]');
    const input = composer.querySelector('textarea');
    const sendButton = $('[data-composer-send]');
    const composerError = $('[data-composer-error]');
    const guided = $('[data-assistant-guided]');
    const guidedError = $('[data-guided-error]');
    const notice = $('[data-assistant-notice]');
    const status = $('[data-assistant-status]');
    const summary = $('[data-assistant-summary]');
    const confirmForm = $('[data-assistant-confirm]');
    const confirmError = $('[data-confirm-error]');
    const done = $('[data-assistant-done]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const state = { session: null, token: null, mode: root.dataset.mode, busy: false, finished: false };

    const url = (template) => root.dataset[template].replace('__SESSION__', encodeURIComponent(state.session ?? ''));

    const scrollToEnd = () => {
        scroller.scrollTop = scroller.scrollHeight;
    };

    const showError = (element, message) => {
        element.textContent = message ?? '';
        element.classList.toggle('hidden', !message);
    };

    const addMessage = ({ role, content, time }) => {
        const item = document.createElement('li');
        const bubble = document.createElement('div');
        const text = document.createElement('p');
        text.className = 'whitespace-pre-line';
        text.setAttribute('dir', 'auto');
        text.textContent = content;
        bubble.append(text);

        if (time) {
            const stamp = document.createElement('span');
            stamp.className = 'mt-1 block type-body-xs';
            stamp.setAttribute('dir', 'ltr');
            stamp.textContent = time;
            bubble.append(stamp);
        }

        if (role === 'user') {
            item.className = 'flex justify-end';
            bubble.className = 'max-w-[85%] rounded-xl rounded-ee-sm bg-brand px-3 py-2.5 type-body-sm text-fg-on-brand sm:max-w-[60%] sm:type-body-md';
            item.append(bubble);
        } else {
            item.className = 'flex items-end gap-2';
            const avatar = root.querySelector('[data-assistant-intro] > span').cloneNode(true);
            bubble.className = 'max-w-[85%] rounded-xl rounded-es-sm bg-subtle px-3 py-2.5 type-body-sm text-fg sm:max-w-[60%] sm:type-body-md';
            item.append(avatar, bubble);
        }

        log.append(item);
    };

    const renderQuickReplies = (options) => {
        quick.replaceChildren();
        if (state.mode !== 'ai' || state.finished) {
            return;
        }
        (options ?? []).forEach((option) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'rounded-full border border-line-brand bg-surface px-3 py-1.5 type-label-md text-fg-brand hover:bg-brand-subtle';
            button.textContent = option;
            button.addEventListener('click', () => send(option));
            quick.append(button);
        });
    };

    const renderProgress = (progress) => {
        if (!progress) {
            return;
        }
        const { collected, total, items } = progress;
        root.querySelectorAll('[data-progress-list]').forEach((list) => {
            list.replaceChildren(...items.map((item) => {
                const li = document.createElement('li');
                li.className = `flex items-center gap-2.5 ${item.done ? 'text-fg' : 'text-fg-tertiary'}`;
                const label = document.createElement('span');
                label.textContent = item.label;
                li.append(icon(item.done), label);
                return li;
            }));
        });
        const text = $('[data-progress-text]');
        if (text) {
            text.textContent = format(root.dataset.progressText, { collected, total });
        }
        $('[data-progress-strip]').textContent = format(root.dataset.stripText, { collected, total });
        const bar = $('[data-progress-bar]');
        bar?.setAttribute('aria-valuenow', String(collected));
        const fill = $('[data-progress-fill]');
        if (fill) {
            fill.style.width = `${Math.round((collected / total) * 100)}%`;
        }
    };

    const showGuidedStep = (step) => {
        guided.querySelectorAll('[data-guided-step]').forEach((form) => {
            const active = form.dataset.guidedStep === step;
            form.classList.toggle('hidden', !active);
            form.classList.toggle('flex', active);
        });
        guided.classList.toggle('hidden', !step || state.finished);
        showError(guidedError, '');
        guided.querySelector(`[data-guided-step="${step}"] input:not([type=hidden]), [data-guided-step="${step}"] textarea`)?.focus({ preventScroll: true });
    };

    const setMode = (mode, noticeText) => {
        state.mode = mode;
        const isGuided = mode === 'guided';
        composer.classList.toggle('hidden', isGuided || state.finished);
        status.textContent = isGuided ? status.dataset.guidedText : status.dataset.aiText;
        summary.querySelector('[data-summary-edit]').classList.toggle('hidden', isGuided);
        if (noticeText) {
            notice.textContent = noticeText;
        }
        notice.classList.toggle('hidden', !isGuided || !notice.textContent);
        if (isGuided) {
            renderQuickReplies([]);
        }
    };

    const renderSummary = (data) => {
        summary.classList.toggle('hidden', !data || state.finished);
        if (!data) {
            return;
        }
        const rows = summary.querySelector('[data-summary-rows]');
        rows.replaceChildren(...data.rows.map((row) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'flex flex-wrap gap-x-2';
            const term = document.createElement('dt');
            term.className = 'text-fg-tertiary';
            term.textContent = `${row.label}:`;
            const value = document.createElement('dd');
            value.className = 'whitespace-pre-line text-fg';
            value.setAttribute('dir', 'auto');
            value.textContent = row.value;
            wrapper.append(term, value);
            return wrapper;
        }));
        Object.entries(data.contact).forEach(([name, value]) => {
            const field = confirmForm.elements.namedItem(name);
            if (field && !field.dataset.touched && value) {
                field.value = value;
            }
        });
    };

    const apply = (data) => {
        if (data.mode && data.mode !== state.mode) {
            setMode(data.mode, data.notice);
        } else if (data.mode === 'guided' && data.notice && notice.classList.contains('hidden')) {
            setMode('guided', data.notice);
        }
        (data.messages ?? []).forEach(addMessage);
        renderQuickReplies(data.quick_replies);
        renderProgress(data.progress);
        if (state.mode === 'guided') {
            showGuidedStep(data.step);
        }
        renderSummary(data.summary);
        requestAnimationFrame(scrollToEnd);
    };

    const request = async (target, body) => {
        const response = await fetch(target, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                ...(state.token ? { 'X-Assistant-Token': state.token } : {}),
            },
            body: JSON.stringify(body),
        });
        let data = {};
        try {
            data = await response.json();
        } catch {
            data = {};
        }
        return { ok: response.ok, status: response.status, data };
    };

    const errorText = (result) => {
        if (result.status === 429 && !result.data.limit_reached) {
            return root.dataset.rateText;
        }
        const errors = result.data.errors ? Object.values(result.data.errors).flat() : [];
        return errors[0] ?? result.data.message ?? root.dataset.errorText;
    };

    const ensureSession = async () => {
        if (state.session) {
            return true;
        }
        const result = await request(root.dataset.startUrl, {
            website: composer.querySelector('[name="website"]').value,
            form_token: root.dataset.formToken,
        });
        if (!result.ok) {
            showError(state.mode === 'guided' ? guidedError : composerError, errorText(result));
            return false;
        }
        state.session = result.data.session;
        state.token = result.data.token;
        // The greeting is already on the page; take the rest of the state only.
        apply({ ...result.data, messages: [] });
        return true;
    };

    const busy = (value) => {
        state.busy = value;
        sendButton.disabled = value;
        typing.classList.toggle('hidden', !value || state.mode !== 'ai');
        root.setAttribute('aria-busy', value ? 'true' : 'false');
    };

    const send = async (text) => {
        const message = text.trim();
        if (!message || state.busy || state.finished) {
            return;
        }
        showError(composerError, '');
        busy(true);
        try {
            if (!(await ensureSession())) {
                return;
            }
            if (state.mode !== 'ai') {
                return;
            }
            addMessage({ role: 'user', content: message });
            renderQuickReplies([]);
            input.value = '';
            requestAnimationFrame(scrollToEnd);

            const result = await request(url('messageUrl'), { message });
            if (result.data.messages) {
                // The server echoes the visitor's message; it is already shown.
                result.data.messages = result.data.messages.filter((entry) => entry.role !== 'user');
            }
            if (result.ok || result.status === 409 || result.data.limit_reached) {
                apply(result.data);
            }
            if (!result.ok && result.status !== 409) {
                showError(composerError, errorText(result));
            }
        } catch {
            showError(composerError, root.dataset.errorText);
        } finally {
            busy(false);
        }
    };

    composer.addEventListener('submit', (event) => {
        event.preventDefault();
        send(input.value);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            send(input.value);
        }
    });

    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 160)}px`;
    });

    const submitAnswer = async (form, skip = false) => {
        if (state.busy) {
            return;
        }
        busy(true);
        showError(guidedError, '');
        try {
            if (!(await ensureSession())) {
                return;
            }
            const body = skip ? {} : formToJson(form);
            body.step = form.dataset.guidedStep;
            const result = await request(url('answerUrl'), body);
            if (result.ok) {
                apply(result.data);
            } else {
                showError(guidedError, errorText(result));
            }
        } catch {
            showError(guidedError, root.dataset.errorText);
        } finally {
            busy(false);
        }
    };

    guided.querySelectorAll('[data-guided-step]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submitAnswer(form);
        });
        form.querySelector('[data-guided-skip]')?.addEventListener('click', () => submitAnswer(form, true));
    });

    confirmForm.querySelectorAll('input, select').forEach((field) => {
        field.addEventListener('input', () => {
            field.dataset.touched = '1';
        });
    });

    summary.querySelector('[data-summary-edit]').addEventListener('click', () => {
        input.focus();
    });

    confirmForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (state.busy) {
            return;
        }
        busy(true);
        showError(confirmError, '');
        try {
            const body = formToJson(confirmForm);
            body.consent = confirmForm.elements.namedItem('consent').checked ? '1' : '';
            const result = await request(url('confirmUrl'), body);
            if (!result.ok) {
                showError(confirmError, errorText(result));
                return;
            }
            state.finished = true;
            summary.classList.add('hidden');
            composer.classList.add('hidden');
            guided.classList.add('hidden');
            quick.replaceChildren();
            done.classList.remove('hidden');
            done.querySelector('[data-done-text]').textContent = result.data.message;
            requestAnimationFrame(scrollToEnd);
            done.setAttribute('tabindex', '-1');
            done.focus({ preventScroll: true });
        } catch {
            showError(confirmError, root.dataset.errorText);
        } finally {
            busy(false);
        }
    });

    // Initial render.
    setMode(state.mode, notice.dataset.initialNotice);
    try {
        renderQuickReplies(JSON.parse(root.dataset.initialQuick || '[]'));
    } catch {
        renderQuickReplies([]);
    }
    if (state.mode === 'guided') {
        showGuidedStep('project');
    }
    renderProgress({
        collected: 0,
        total: root.querySelectorAll('aside [data-progress-list] li').length || 6,
        items: [...root.querySelectorAll('aside [data-progress-list] li')].map((li) => ({ label: li.textContent.trim(), done: false })),
    });
}

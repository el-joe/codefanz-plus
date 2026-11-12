/**
 * Leads Kanban (A02). Cards ([data-lead-card]) can be dragged between columns ([data-lead-column]) or moved
 * with each card's own "Move" form ([data-lead-move]) — the keyboard alternative, which also works without
 * JavaScript as a normal form post. Moves are applied optimistically, sent as JSON with the CSRF token and
 * the card's lock_version, and rolled back when the server refuses (403, 409 conflict, 422 invalid move).
 * Outside the board, [data-lead-move] forms only get the "reason if lost" field toggled.
 */
import { request } from '../services/http';
import { toast } from './toast';

const toggleReason = (form) => {
    const select = form.querySelector('[data-lead-move-status]');
    const field = form.querySelector('[data-lost-reason-field]');

    if (!select || !field) {
        return;
    }

    const update = () => {
        field.hidden = select.value !== 'lost';
    };
    select.addEventListener('change', update);
    update();
};

const rebuildOptions = (card, allowed, labels) => {
    const select = card.querySelector('[data-lead-move-status]');
    const form = card.querySelector('[data-lead-move]');

    if (!select || !form) {
        return;
    }

    if (allowed.length === 0) {
        form.hidden = true;
        card.removeAttribute('draggable');
        return;
    }

    select.replaceChildren(
        ...allowed.map((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = labels[value] ?? value;
            return option;
        }),
    );
    select.dispatchEvent(new Event('change'));
};

export function init() {
    document.querySelectorAll('[data-lead-move]').forEach(toggleReason);

    const board = document.querySelector('[data-lead-board]');

    if (!board) {
        return;
    }

    const labels = {};
    board.querySelectorAll('[data-lead-column]').forEach((column) => {
        labels[column.dataset.leadColumn] = column.dataset.leadColumnLabel ?? column.dataset.leadColumn;
    });

    const adjustCount = (column, delta) => {
        const badge = column.querySelector('[data-lead-count]');
        if (badge) {
            const value = Number.parseInt(badge.textContent.trim(), 10) || 0;
            badge.lastChild.textContent = ` ${Math.max(0, value + delta)}`;
        }
    };

    const move = async (card, to, lostReason = '') => {
        const fromColumn = card.closest('[data-lead-column]');
        const toColumn = board.querySelector(`[data-lead-column="${to}"]`);

        if (!toColumn || fromColumn === toColumn) {
            return;
        }

        let reason = lostReason;
        if (to === 'lost' && !reason) {
            reason = window.prompt(board.dataset.lostPrompt ?? '') ?? '';
            if (!reason.trim()) {
                return;
            }
        }

        const nextSibling = card.nextElementSibling;
        toColumn.querySelector('[data-lead-list]').prepend(card);
        adjustCount(fromColumn, -1);
        adjustCount(toColumn, 1);
        card.setAttribute('aria-busy', 'true');

        try {
            const payload = await request(card.dataset.moveUrl, {
                method: 'POST',
                body: { status: to, lost_reason: reason || null, lock_version: Number(card.dataset.lockVersion) },
            });
            card.dataset.status = payload.lead.status;
            card.dataset.lockVersion = String(payload.lead.lock_version);
            card.querySelectorAll('[data-lock-version]').forEach((input) => {
                input.value = String(payload.lead.lock_version);
            });
            rebuildOptions(card, payload.lead.allowed, labels);
            toast(payload.message, 'success');
            card.querySelector('[data-lead-move-status]')?.focus();
        } catch (error) {
            fromColumn.querySelector('[data-lead-list]').insertBefore(card, nextSibling);
            adjustCount(toColumn, -1);
            adjustCount(fromColumn, 1);
            const firstError = Object.values(error.errors ?? {})[0]?.[0];
            toast(firstError ?? error.message ?? board.dataset.errorMessage, 'danger');
        } finally {
            card.removeAttribute('aria-busy');
        }
    };

    board.querySelectorAll('[data-lead-card]').forEach((card) => {
        card.addEventListener('dragstart', (event) => {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', card.dataset.moveUrl);
            card.classList.add('opacity-50');
            board.dataset.dragging = card.dataset.moveUrl;
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('opacity-50');
            delete board.dataset.dragging;
        });

        card.querySelector('[data-lead-move]')?.addEventListener('submit', (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            move(card, form.querySelector('[data-lead-move-status]').value, form.querySelector('[data-lead-move-reason]')?.value.trim() ?? '');
        });
    });

    board.querySelectorAll('[data-lead-column]').forEach((column) => {
        column.addEventListener('dragover', (event) => {
            if (board.dataset.dragging) {
                event.preventDefault();
                column.classList.add('ring-2', 'ring-brand');
            }
        });
        column.addEventListener('dragleave', () => column.classList.remove('ring-2', 'ring-brand'));
        column.addEventListener('drop', (event) => {
            event.preventDefault();
            column.classList.remove('ring-2', 'ring-brand');
            const card = [...board.querySelectorAll('[data-lead-card]')].find((item) => item.dataset.moveUrl === board.dataset.dragging);
            if (card) {
                move(card, column.dataset.leadColumn);
            }
        });
    });
}

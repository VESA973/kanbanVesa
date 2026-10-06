import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';

/*
 * Drag & drop of board columns or task cards.
 *
 * Each draggable child carries data-sortable-url (PATCH endpoint). Lists of tasks
 * carry data-column-id so a card dropped in another column sends its new column.
 * The server answers with the confirmed position; on failure the element goes back
 * to where it was and the error is announced to screen readers.
 */
export default class extends Controller {
    static values = {
        group: String,
        handle: String,
        csrfToken: String,
        errorMessage: String,
    };

    connect() {
        this.sortable = Sortable.create(this.element, {
            group: this.groupValue,
            handle: this.handleValue || null,
            draggable: '[data-sortable-url]',
            animation: 150,
            ghostClass: 'opacity-40',
            onEnd: (event) => this.save(event),
        });
    }

    disconnect() {
        this.sortable.destroy();
    }

    async save({ item, from, to, oldDraggableIndex, newDraggableIndex }) {
        if (from === to && oldDraggableIndex === newDraggableIndex) {
            return;
        }

        const payload = { position: newDraggableIndex };
        if (to.dataset.columnId) {
            payload.columnId = Number(to.dataset.columnId);
        }

        try {
            const response = await fetch(item.dataset.sortableUrl, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-Token': this.csrfTokenValue,
                },
                body: JSON.stringify(payload),
            });
            if (!response.ok || response.redirected) {
                throw new Error(`HTTP ${response.status}`);
            }
            await response.json();
        } catch {
            this.revert(item, from, oldDraggableIndex);
        }
    }

    revert(item, from, oldIndex) {
        const siblings = [...from.querySelectorAll(':scope > [data-sortable-url]')].filter((el) => el !== item);
        from.insertBefore(item, siblings[oldIndex] ?? from.querySelector(':scope > :not([data-sortable-url])'));
        this.announce(this.errorMessageValue);
    }

    announce(message) {
        const status = document.getElementById('board-status');
        if (status) {
            status.textContent = message;
        }
    }
}

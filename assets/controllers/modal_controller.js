import { Controller } from '@hotwired/stimulus';
import { visit } from '@hotwired/turbo';

// Forms inside the modal (comments, checklist) re-render the frame, which replaces
// the <dialog> and its controller: state that must survive lives on the frame.
const frameState = new WeakMap();

/*
 * Opens the <dialog> loaded into the "modal" Turbo Frame and empties the frame on close.
 * If something changed in the modal, the board is refreshed (morphed) on close so the
 * cards show the new comment count, checklist progress…
 */
export default class extends Controller {
    connect() {
        this.frame = this.element.closest('turbo-frame');
        if (this.frame && !frameState.has(this.frame)) {
            frameState.set(this.frame, { opener: document.activeElement, dirty: false });
        }

        this.element.showModal();
        this.element.addEventListener('close', this.clear);
        this.element.addEventListener('turbo:submit-end', this.markDirty);
    }

    disconnect() {
        this.element.removeEventListener('close', this.clear);
        this.element.removeEventListener('turbo:submit-end', this.markDirty);
    }

    close() {
        this.element.close();
    }

    closeOnBackdrop(event) {
        if (event.target === this.element) {
            this.close();
        }
    }

    markDirty = () => {
        const state = this.frame && frameState.get(this.frame);
        if (state) {
            state.dirty = true;
        }
    };

    clear = () => {
        const state = (this.frame && frameState.get(this.frame)) ?? {};
        if (this.frame) {
            frameState.delete(this.frame);
            this.frame.removeAttribute('src');
            this.frame.innerHTML = '';
        }

        if (state.dirty) {
            visit(window.location.href, { action: 'replace' });

            return;
        }
        state.opener?.focus?.();
    };
}

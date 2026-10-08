import { Controller } from '@hotwired/stimulus';
import { visit } from '@hotwired/turbo';

// Forms inside the modal (comments, checklist) re-render the frame, which replaces
// the <dialog> and its controller: state that must survive lives on the frame.
const frameState = new WeakMap();

// Width chosen by resizing the dialog, remembered per browser (a convenience: never required).
const WIDTH_KEY = 'task-modal-width';
const MIN_WIDTH = 384;

function readWidth() {
    try {
        return Number(window.localStorage.getItem(WIDTH_KEY)) || null;
    } catch {
        return null;
    }
}

function writeWidth(width) {
    try {
        window.localStorage.setItem(WIDTH_KEY, String(width));
    } catch {
        // Private browsing or blocked storage: the width is simply not remembered.
    }
}

/*
 * Opens the <dialog> loaded into the "modal" Turbo Frame and empties the frame on close.
 * The dialog can be resized (CSS resize) or widened to the whole screen; the width is remembered.
 * If something changed in the modal, the board is refreshed (morphed) on close so the
 * cards show the new comment count, checklist progress…
 */
export default class extends Controller {
    static targets = ['wideButton'];

    connect() {
        this.frame = this.element.closest('turbo-frame');
        if (this.frame && !frameState.has(this.frame)) {
            frameState.set(this.frame, { opener: document.activeElement, dirty: false });
        }

        this.applyWidth(readWidth());
        this.element.showModal();
        this.element.addEventListener('close', this.clear);
        this.element.addEventListener('turbo:submit-end', this.markDirty);
        this.resizeObserver = new ResizeObserver(this.rememberWidth);
        this.resizeObserver.observe(this.element);
    }

    disconnect() {
        this.resizeObserver?.disconnect();
        this.element.removeEventListener('close', this.clear);
        this.element.removeEventListener('turbo:submit-end', this.markDirty);
    }

    toggleWide() {
        const wide = window.innerWidth * 0.95;
        const isWide = this.element.offsetWidth >= wide - 1;
        this.applyWidth(isWide ? null : wide);
        writeWidth(isWide ? '' : Math.round(wide));
    }

    applyWidth(width) {
        // Below the sm breakpoint the dialog always takes the whole width.
        if (!width || window.innerWidth < 640) {
            this.element.style.width = '';
        } else {
            this.element.style.width = `${Math.max(MIN_WIDTH, Math.min(width, window.innerWidth * 0.95))}px`;
        }
        this.updateWideButton();
    }

    rememberWidth = () => {
        if (window.innerWidth >= 640 && this.element.open && this.element.style.width) {
            writeWidth(Math.round(this.element.offsetWidth));
        }
        this.updateWideButton();
    };

    updateWideButton() {
        if (this.hasWideButtonTarget) {
            this.wideButtonTarget.setAttribute('aria-pressed', String(this.element.offsetWidth >= window.innerWidth * 0.95 - 1));
        }
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

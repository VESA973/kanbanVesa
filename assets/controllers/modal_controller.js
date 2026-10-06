import { Controller } from '@hotwired/stimulus';

/*
 * Opens the <dialog> loaded into the "modal" Turbo Frame and empties the frame
 * on close, giving the focus back to the element that opened it.
 */
export default class extends Controller {
    connect() {
        this.opener = document.activeElement;
        this.element.showModal();
        this.element.addEventListener('close', this.clear);
    }

    disconnect() {
        this.element.removeEventListener('close', this.clear);
    }

    close() {
        this.element.close();
    }

    closeOnBackdrop(event) {
        if (event.target === this.element) {
            this.close();
        }
    }

    clear = () => {
        const frame = this.element.closest('turbo-frame');
        if (frame) {
            frame.removeAttribute('src');
            frame.innerHTML = '';
        }
        this.opener?.focus?.();
    };
}

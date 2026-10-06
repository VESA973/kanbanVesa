import { Controller } from '@hotwired/stimulus';

/*
 * Asks for confirmation before submitting a destructive form.
 * Usage: <form data-controller="confirm" data-confirm-message-value="…" data-action="confirm#ask">
 */
export default class extends Controller {
    static values = { message: String };

    ask(event) {
        if (!window.confirm(this.messageValue)) {
            event.preventDefault();
        }
    }
}

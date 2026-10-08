import { Controller } from '@hotwired/stimulus';

/*
 * Saves one cell of a task table as soon as it changes (PATCH, JSON).
 * The server answers the value as stored (e.g. "1 234,5" for a number) and the new
 * total of a number column; on refusal the previous value comes back and the
 * reason is announced to screen readers.
 */
export default class extends Controller {
    static values = {
        url: String,
        csrfToken: String,
        totalId: String,
        savedMessage: String,
    };

    connect() {
        this.previous = this.read();
    }

    async save() {
        this.element.removeAttribute('aria-invalid');
        try {
            const response = await fetch(this.urlValue, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-Token': this.csrfTokenValue,
                },
                body: JSON.stringify({ value: this.read() }),
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error ?? `HTTP ${response.status}`);
            }
            if (this.element.type !== 'checkbox') {
                this.write(data.input);
            }
            this.previous = this.read();
            this.updateTotal(data.total);
            this.announce(this.savedMessageValue);
        } catch (error) {
            this.write(this.previous);
            this.element.setAttribute('aria-invalid', 'true');
            this.announce(error.message);
        }
    }

    read() {
        if (this.element.type === 'checkbox') {
            return this.element.checked ? '1' : '0';
        }

        return this.element.value;
    }

    write(value) {
        if (this.element.type === 'checkbox') {
            this.element.checked = value === '1';
            return;
        }
        this.element.value = value;
    }

    updateTotal(total) {
        const element = this.totalIdValue ? document.getElementById(this.totalIdValue) : null;
        if (element && total !== null && total !== undefined) {
            element.textContent = total;
        }
    }

    announce(message) {
        const status = document.getElementById('tables-status');
        if (status) {
            status.textContent = message;
        }
    }
}

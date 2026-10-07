import { Controller } from '@hotwired/stimulus';

/*
 * Copies the value of the "source" target and briefly confirms it.
 * Without JavaScript the field stays selectable for a manual copy.
 */
export default class extends Controller {
    static targets = ['source', 'button'];
    static values = { copiedLabel: String };

    async copy() {
        this.sourceTarget.select();
        try {
            await navigator.clipboard.writeText(this.sourceTarget.value);
        } catch {
            document.execCommand('copy');
        }

        const label = this.buttonTarget.textContent;
        this.buttonTarget.textContent = this.copiedLabelValue;
        setTimeout(() => { this.buttonTarget.textContent = label; }, 2000);
    }
}

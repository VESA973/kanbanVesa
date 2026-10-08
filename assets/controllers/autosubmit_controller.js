import { Controller } from '@hotwired/stimulus';

/*
 * Submits its form as soon as a field changes (e.g. the pole of a program).
 * Usage: <form data-controller="autosubmit"> … <select data-action="autosubmit#submit">
 */
export default class extends Controller {
    submit() {
        this.element.requestSubmit();
    }
}

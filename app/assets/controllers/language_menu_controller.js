import { Controller } from '@hotwired/stimulus';

const searchable = (text) =>
    text
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();

/**
 * A panel of languages under a button, with a search field. Each "item" target lists in
 * data-search the words that find it: its own name, its English name and its code.
 */
export default class extends Controller {
    static targets = ['button', 'panel', 'search', 'item', 'empty'];

    connect() {
        this.closeOnOutsidePress = (event) => {
            if (!this.element.contains(event.target)) {
                this.close();
            }
        };
        this.closeOnEscape = (event) => {
            if ('Escape' === event.key) {
                this.close();
                this.buttonTarget.focus();
            }
        };
    }

    disconnect() {
        this.close();
    }

    toggle() {
        if (this.panelTarget.hidden) {
            this.open();
        } else {
            this.close();
        }
    }

    open() {
        this.panelTarget.hidden = false;
        this.buttonTarget.setAttribute('aria-expanded', 'true');
        this.searchTarget.value = '';
        this.filter();
        this.searchTarget.focus();
        document.addEventListener('pointerdown', this.closeOnOutsidePress);
        document.addEventListener('keydown', this.closeOnEscape);
    }

    close() {
        this.panelTarget.hidden = true;
        this.buttonTarget.setAttribute('aria-expanded', 'false');
        document.removeEventListener('pointerdown', this.closeOnOutsidePress);
        document.removeEventListener('keydown', this.closeOnEscape);
    }

    filter() {
        const query = searchable(this.searchTarget.value.trim());
        for (const item of this.itemTargets) {
            item.hidden = !searchable(item.dataset.search).includes(query);
        }
        this.emptyTarget.hidden = this.itemTargets.some((item) => !item.hidden);
    }

    /** Enter in the search field opens the first language that is left. */
    choose(event) {
        event.preventDefault();
        this.itemTargets
            .find((item) => !item.hidden)
            ?.querySelector('a')
            .click();
    }
}

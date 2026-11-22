import { Controller } from '@hotwired/stimulus';

/**
 * Filters the providers by region in the page: it holds all of them, so the server is not asked. The address
 * keeps the region, so that a link, a reload or the Back button shows the same list. Without JavaScript, the
 * links of the filter load the page of the region.
 */
export default class extends Controller {
    static targets = ['filter', 'provider'];

    connect() {
        this.showRegionOfAddress = () => this.show(new URL(window.location.href).searchParams.get('region'));
        window.addEventListener('popstate', this.showRegionOfAddress);
    }

    disconnect() {
        window.removeEventListener('popstate', this.showRegionOfAddress);
    }

    choose(event) {
        // A click that opens a tab or a window keeps the behavior of a link.
        if (0 !== event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();
        const link = event.currentTarget;

        if (link.href !== window.location.href) {
            window.history.pushState(null, '', link.href);
        }

        this.show(link.dataset.region ?? null);
    }

    /**
     * @param {?string} region a region of a filter; any other value shows every provider
     */
    show(region) {
        const known = this.filterTargets.some((filter) => (filter.dataset.region ?? null) === region);
        const shown = known ? region : null;

        for (const provider of this.providerTargets) {
            provider.hidden = null !== shown && provider.dataset.region !== shown;
        }

        for (const filter of this.filterTargets) {
            if ((filter.dataset.region ?? null) === shown) {
                filter.setAttribute('aria-current', 'page');
            } else {
                filter.removeAttribute('aria-current');
            }
        }
    }
}

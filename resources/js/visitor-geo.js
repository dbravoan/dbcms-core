/** Manual region selection remains available after the first-visit prompt. */
export function createVisitorGeoSelector(configuration, browser = globalThis) {
    const markets = Array.isArray(configuration.markets) ? configuration.markets : [];
    const currentMarket = configuration.currentMarket ?? '';
    const currentLocale = configuration.currentLocale ?? '';

    return {
        markets,
        showModal: Boolean(configuration.autoShow),
        selectedMarket: configuration.autoShow ? configuration.recommendedMarket : currentMarket,
        selectedLocale: configuration.autoShow ? configuration.recommendedLocale : currentLocale,
        openListener: null,

        get languages() {
            return this.markets.find(market => market.code === this.selectedMarket)?.languages ?? [];
        },

        get destination() {
            return this.languages.find(language => language.code === this.selectedLocale)?.url ?? '';
        },

        init() {
            this.normalizeSelection();
            if (!this.openListener) {
                this.openListener = () => this.open();
                browser.addEventListener('open-geo-modal', this.openListener);
            }
        },

        destroy() {
            if (this.openListener) {
                browser.removeEventListener('open-geo-modal', this.openListener);
                this.openListener = null;
            }
        },

        open() {
            this.selectedMarket = currentMarket;
            this.selectedLocale = currentLocale;
            this.normalizeSelection();
            this.showModal = this.markets.length > 0;
        },

        selectMarket(code) {
            this.selectedMarket = code;
            this.normalizeLocale();
        },

        normalizeSelection() {
            if (!this.markets.some(market => market.code === this.selectedMarket)) {
                this.selectedMarket = this.markets.some(market => market.code === currentMarket)
                    ? currentMarket : (this.markets[0]?.code ?? '');
            }
            this.normalizeLocale();
        },

        normalizeLocale() {
            if (this.languages.some(language => language.code === this.selectedLocale)) return;
            const market = this.markets.find(candidate => candidate.code === this.selectedMarket);
            this.selectedLocale = this.languages.find(language => language.code === market?.defaultLocale)?.code
                ?? this.languages[0]?.code ?? '';
        },

        remember(market, locale) {
            const value = `${market}_${locale}`;
            const secure = browser.location.protocol === 'https:' ? '; Secure' : '';
            browser.document.cookie = `cms_geo_preference=${encodeURIComponent(value)}; path=/; max-age=31536000; SameSite=Lax${secure}`;
            try { browser.localStorage.setItem('cms_geo_preference', value); } catch (_) { /* Cookie still preserves the choice. */ }
        },

        confirmSelection() {
            const destination = this.destination;
            if (!destination) return false;
            this.remember(this.selectedMarket, this.selectedLocale);
            browser.location.assign(destination);
            return true;
        },

        stayCurrent() {
            const available = this.markets.find(market => market.code === currentMarket)?.languages
                .some(language => language.code === currentLocale) ?? false;
            if (available) this.remember(currentMarket, currentLocale);
            this.showModal = false;
            return available;
        },
    };
}

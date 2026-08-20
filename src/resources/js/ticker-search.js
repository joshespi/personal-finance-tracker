window.tickerSearch = function tickerSearch({ query = '', defaultType = 'stock', syncSelectId = null } = {}) {
    return {
        query,
        assetType: defaultType,
        results: [],
        open: false,
        activeIndex: -1,
        async search() {
            if (this.query.length < 1) { this.results = []; this.open = false; return; }
            try {
                const res = await fetch(`/tickers/search?q=${encodeURIComponent(this.query)}&type=${encodeURIComponent(this.assetType)}`);
                this.results = await res.json();
                this.open = this.results.length > 0;
                this.activeIndex = -1;
            } catch { this.results = []; }
        },
        select(r) {
            this.query     = r.symbol;
            this.assetType = r.type;
            this.open      = false;
            const sync = syncSelectId ? document.getElementById(syncSelectId) : null;
            if (sync) sync.value = r.type;
        },
        selectCurrent() {
            if (this.activeIndex >= 0 && this.results[this.activeIndex]) this.select(this.results[this.activeIndex]);
        },
        moveDown()   { this.activeIndex = Math.min(this.activeIndex + 1, this.results.length - 1); },
        moveUp()     { this.activeIndex = Math.max(this.activeIndex - 1, -1); },
        close()      { this.open = false; this.activeIndex = -1; },
        delayClose() { setTimeout(() => this.close(), 150); },
        submitForm(form) {
            const field = form.querySelector('[name="asset_type"]');
            if (field) field.value = this.assetType;
            form.submit();
        },
    };
};

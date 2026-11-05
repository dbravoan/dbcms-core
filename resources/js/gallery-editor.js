export const createGalleryEditor = (translations = {}, items = [], languages = [], defaultLocale = 'es') => ({
    activeLocale: defaultLocale,
    defaultLocale,
    translations,
    items,
    allLanguages: languages,
    slugTouched: {},
    fileSelectionListener: null,

    init() {
        for (const code of this.allLanguages) {
            this.translations[code] ??= {
                title: '', slug: '', description: '', meta_title: '', meta_description: '', schema_type: 'ImageGallery',
            };
        }
    },

    switchLocale(code) {
        if (this.allLanguages.includes(code)) this.activeLocale = code;
    },

    generateSlug(code) {
        this.translations[code].slug = (this.translations[code]?.title || '').toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)+/g, '');
    },

    openFileManagerForNewItem() {
        this.destroy();
        const target = 'new_gallery_item_' + Date.now() + '_' + Math.random().toString(36).slice(2);
        const selected = (event) => {
            if (event.detail?.target !== target) return;
            this.destroy();
            const file = event.detail.file;
            this.items.push({
                id: target,
                image_url: file.url,
                title: file.title || file.name || '',
                alt_text: file.alt_text || '',
                caption: file.caption || '',
            });
        };
        this.fileSelectionListener = selected;
        window.addEventListener('file-selected', selected);
        window.dispatchEvent(new CustomEvent('open-filemanager', { detail: { target } }));
    },

    destroy() {
        if (this.fileSelectionListener) window.removeEventListener('file-selected', this.fileSelectionListener);
        this.fileSelectionListener = null;
    },

    removeItem(index) {
        this.items.splice(index, 1);
    },

    moveUp(index) {
        if (index > 0) [this.items[index - 1], this.items[index]] = [this.items[index], this.items[index - 1]];
    },

    moveDown(index) {
        if (index < this.items.length - 1) [this.items[index], this.items[index + 1]] = [this.items[index + 1], this.items[index]];
    },
});

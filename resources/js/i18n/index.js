import { ref, watchEffect } from 'vue';
import en from './en.js';
import ur from './ur.js';

const dictionaries = { en, ur };

const STORAGE_KEY = 'll_locale';

function readStoredLocale() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);

        return stored === 'ur' ? 'ur' : 'en';
    } catch {
        return 'en';
    }
}

// Module-level (not per-component) so every page and the sidebar share
// one language switch, and it persists across navigations.
const locale = ref(readStoredLocale());

watchEffect(() => {
    if (typeof document === 'undefined') return;

    document.documentElement.lang = locale.value;
    // Layout direction stays left-to-right even in Urdu — this app's
    // grids/flex layouts were built for LTR, and fully mirroring every
    // screen is future work. Unicode still shapes and right-aligns the
    // Urdu glyphs correctly within each text run; only the Nastaliq
    // font and its taller line-height are switched here.
    document.documentElement.classList.toggle('font-urdu', locale.value === 'ur');
});

function setLocale(next) {
    locale.value = next === 'ur' ? 'ur' : 'en';

    try {
        localStorage.setItem(STORAGE_KEY, locale.value);
    } catch {
        // Private browsing / storage disabled — language just won't persist.
    }
}

function toggleLocale() {
    setLocale(locale.value === 'ur' ? 'en' : 'ur');
}

function t(key, params = {}) {
    const dict = dictionaries[locale.value] ?? dictionaries.en;
    let text = dict[key] ?? dictionaries.en[key] ?? key;

    for (const [param, value] of Object.entries(params)) {
        text = text.replace(`:${param}`, value);
    }

    return text;
}

export function useI18n() {
    return { locale, setLocale, toggleLocale, t };
}

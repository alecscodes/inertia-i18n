type Params = Record<string, string | number>;
type TranslationTree = Record<string, string>;

type PageProps = Record<string, unknown>;

/**
 * Replace :key placeholders in a string with their values.
 * Uses a single-pass reduce; skips entirely when no params are given.
 */
function replaceParams(raw: string, params?: Params): string {
    if (!params) {
        return raw;
    }

    return Object.entries(params).reduce((str, [key, val]) => {
        // Escape special regex chars in the placeholder key
        const escaped = key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

        return str.replace(new RegExp(`:${escaped}`, 'g'), String(val));
    }, raw);
}

/**
 * Pick the correct plural form from a pipe-separated string.
 *
 * Supported formats (same as Laravel):
 *   {n}          exact count      "{0} None|{1} One item"
 *   [min,max]    range            "[2,*] :count items"
 *   word|words   simple fallback  "minute|minutes"
 */
function choosePluralForm(line: string, count: number): string {
    const parts = line.split('|');

    for (const part of parts) {
        const t = part.trim();

        const exact = t.match(/^\{(\d+)}\s*(.*)$/s);

        if (exact && Number(exact[1]) === count) {
            return exact[2];
        }

        const range = t.match(/^\[(\d+),(\*|\d+)]\s*(.*)$/s);

        if (range) {
            const min = Number(range[1]);
            const max = range[2] === '*' ? Infinity : Number(range[2]);

            if (count >= min && count <= max) {
                return range[3];
            }
        }
    }

    // Simple singular|plural fallback
    return parts.length > 1
        ? count === 1
            ? parts[0].trim()
            : parts[1].trim()
        : line;
}

/**
 * Composable for Inertia translations — mirrors Laravel's t() / tc().
 *
 * @example
 * const { t, tc, locale } = useTranslation();
 *
 * t('nav.dashboard')                        // "Dashboard"
 * t('greetings.welcome', { name: 'Jo' })   // "Welcome, Jo"
 * tc('items.count', 0)                      // "No items"
 * tc('items.count', 5, { count: 5 })        // "5 items"
 */
export function useTranslation(props: PageProps): {
    locale: string;
    translations: TranslationTree;
    t: (key: string, params?: Params) => string;
    tc: (key: string, count: number, params?: Params) => string;
} {
    const locale = typeof props.locale === 'string' ? props.locale : 'en';

    const translations: TranslationTree = (() => {
        const v = props.translations;

        return v && typeof v === 'object' && !Array.isArray(v)
            ? (v as TranslationTree)
            : {};
    })();

    /** Translate a key, optionally replacing :param placeholders. */
    const t = (key: string, params?: Params): string => {
        const raw = translations[key.trim()];

        return raw !== undefined ? replaceParams(raw, params) : key;
    };

    /**
     * Translate with pluralization, auto-injecting `count` into params.
     *
     * @param key    Translation key
     * @param count  Number used to select the plural form
     * @param params Extra replacements (`:count` is always available)
     */
    const tc = (key: string, count: number, params?: Params): string => {
        const raw = translations[key.trim()];

        if (raw === undefined) {
            return key;
        }

        const form = choosePluralForm(raw, count);

        return replaceParams(form, { count, ...params });
    };

    return { locale, translations, t, tc };
}

export default useTranslation;

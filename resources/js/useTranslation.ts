export type TranslationParams = Record<string, string | number>;
export type PageProps = Record<string, unknown>;

export interface TranslationOptions {
    /** Prop names; must match `props` in config/inertia-i18n.php if you changed them. */
    props?: { locale?: string; translations?: string };
}

export interface Translator {
    readonly locale: string;
    readonly translations: Record<string, string>;
    t(key: string, params?: TranslationParams): string;
    tc(key: string, count: number, params?: TranslationParams): string;
}

const isRecord = (value: unknown): value is Record<string, unknown> =>
    value !== null && typeof value === 'object' && !Array.isArray(value);

const escapeRegExp = (value: string): string => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const ucfirst = (value: string): string => value.charAt(0).toUpperCase() + value.slice(1);

/**
 * Replace :key, :Key and :KEY placeholders like Laravel's translator: a single
 * pass, longest placeholder first, with values inserted literally.
 */
function replaceParams(line: string, params?: TranslationParams): string {
    const entries = Object.entries(params ?? {}).filter(([key]) => key !== '');

    if (entries.length === 0) {
        return line;
    }

    const replacements = new Map<string, string>();

    for (const [key, raw] of entries) {
        const value = String(raw ?? '');
        replacements.set(`:${ucfirst(key)}`, ucfirst(value));
        replacements.set(`:${key.toUpperCase()}`, value.toUpperCase());
    }

    // Exact placeholders win over generated case variants.
    for (const [key, raw] of entries) {
        replacements.set(`:${key}`, String(raw ?? ''));
    }

    const pattern = [...replacements.keys()]
        .sort((a, b) => b.length - a.length)
        .map(escapeRegExp)
        .join('|');

    return line.replace(new RegExp(pattern, 'g'), (token) => replacements.get(token) ?? token);
}

/*
 * Plural rules follow Laravel's MessageSelector::getPluralIndex() (MIT), whose
 * rules derive from Zend Framework (BSD-3-Clause). Locales are matched by their
 * language subtag, so 'pt_BR', 'pt-BR' and 'pt' share the 'pt' rule.
 */
type PluralRule = (n: number, i: number) => number;

const pluralRules = new Map<string, PluralRule>();

const rule = (languages: string, fn: PluralRule): void => {
    for (const language of languages.split(' ')) {
        pluralRules.set(language, fn);
    }
};

rule('az bo dz id ja jv ka km kn ko ms th tr vi zh', () => 0);
rule(
    'af bn bg ca da de el en eo es et eu fa fi fo fur fy gl gu ha he hu is it ku lb ml mn mr nah nb ne nl nn no om or pa pap ps pt so sq sv sw ta te tk ur zu',
    (n) => (n === 1 ? 0 : 1),
);
rule('am bh fil fr gun hi hy ln mg nso ti wa xbr', (n) => (n === 0 || n === 1 ? 0 : 1));
rule('be bs hr ru sr uk', (_, i) =>
    i % 10 === 1 && i % 100 !== 11 ? 0 : i % 10 >= 2 && i % 10 <= 4 && (i % 100 < 10 || i % 100 >= 20) ? 1 : 2,
);
rule('cs sk', (n) => (n === 1 ? 0 : n >= 2 && n <= 4 ? 1 : 2));
rule('ga', (n) => (n === 1 ? 0 : n === 2 ? 1 : 2));
rule('lt', (_, i) => (i % 10 === 1 && i % 100 !== 11 ? 0 : i % 10 >= 2 && (i % 100 < 10 || i % 100 >= 20) ? 1 : 2));
rule('sl', (_, i) => (i % 100 === 1 ? 0 : i % 100 === 2 ? 1 : i % 100 === 3 || i % 100 === 4 ? 2 : 3));
rule('mk', (_, i) => (i % 10 === 1 ? 0 : 1));
rule('mt', (n, i) => (n === 1 ? 0 : n === 0 || (i % 100 > 1 && i % 100 < 11) ? 1 : i % 100 > 10 && i % 100 < 20 ? 2 : 3));
rule('lv', (n, i) => (n === 0 ? 0 : i % 10 === 1 && i % 100 !== 11 ? 1 : 2));
rule('pl', (n, i) => (n === 1 ? 0 : i % 10 >= 2 && i % 10 <= 4 && (i % 100 < 12 || i % 100 > 14) ? 1 : 2));
rule('cy', (n) => (n === 1 ? 0 : n === 2 ? 1 : n === 8 || n === 11 ? 2 : 3));
rule('ro', (n, i) => (n === 1 ? 0 : n === 0 || (i % 100 > 0 && i % 100 < 20) ? 1 : 2));
rule('ar', (n, i) =>
    n === 0 ? 0 : n === 1 ? 1 : n === 2 ? 2 : i % 100 >= 3 && i % 100 <= 10 ? 3 : i % 100 >= 11 && i % 100 <= 99 ? 4 : 5,
);

function pluralIndex(locale: string, count: number): number {
    const language = locale.toLowerCase().split(/[-_]/)[0];
    const n = Math.abs(count);

    // Unknown languages use the first form, as in Laravel.
    return pluralRules.get(language)?.(n, Math.trunc(n)) ?? 0;
}

// Same syntax Laravel accepts: {0}, {1.5}, [2,5], [6,*], [*,0]
const CONDITION = /^\s*[{[]\s*([-?\d|*,.]*)\s*[}\]](.*)/s;

function matchCondition(segment: string, count: number): string | null {
    const match = CONDITION.exec(segment);

    if (!match) {
        return null;
    }

    const [, condition, text] = match;
    const comma = condition.indexOf(',');

    if (comma !== -1) {
        const from = condition.slice(0, comma);
        const to = condition.slice(comma + 1);

        if (to === '*' && count >= Number(from)) return text;
        if (from === '*' && count <= Number(to)) return text;
        if (count >= Number(from) && count <= Number(to)) return text;
    }

    return condition !== '' && Number(condition) === count ? text : null;
}

/**
 * Pick the plural form like Laravel's trans_choice(): the first matching
 * explicit condition wins; otherwise conditions are stripped and the form is
 * chosen by the locale's plural rule (falling back to the first form).
 */
function choosePluralForm(line: string, count: number, locale: string): string {
    const segments = line.split('|');

    for (const segment of segments) {
        const text = matchCondition(segment, count);

        if (text !== null) {
            return text.trim();
        }
    }

    const forms = segments.map((segment) => segment.replace(CONDITION, '$2').trim());

    return forms[pluralIndex(locale, count)] ?? forms[0];
}

/**
 * Translation helper for Inertia page props — mirrors Laravel's __() and trans_choice().
 *
 * Pass the page props, or a getter returning them. With a getter every call
 * reads the current props, so the helper keeps working in components that
 * outlive a navigation (persistent layouts, Vue/Svelte setup code).
 *
 * @example
 * const { t, tc, locale } = useTranslation(() => usePage().props);
 *
 * t('nav.dashboard')                       // "Dashboard"
 * t('greetings.welcome', { name: 'Jo' })   // "Welcome, Jo"
 * tc('items.count', 0)                     // "No items"
 * tc('items.count', 5)                     // "5 items" (:count is filled in)
 */
export function useTranslation(source: PageProps | (() => PageProps), options: TranslationOptions = {}): Translator {
    const localeProp = options.props?.locale ?? 'locale';
    const translationsProp = options.props?.translations ?? 'translations';

    const readProps = (): PageProps => {
        const props = typeof source === 'function' ? source() : source;

        return isRecord(props) ? props : {};
    };

    const localeOf = (props: PageProps): string => {
        const locale = props[localeProp];

        return typeof locale === 'string' ? locale : 'en';
    };

    const catalogOf = (props: PageProps): Record<string, unknown> => {
        const catalog = props[translationsProp];

        return isRecord(catalog) ? catalog : {};
    };

    // Own string values only, so keys like 'constructor' never hit Object.prototype.
    const lookup = (props: PageProps, key: string): string | undefined => {
        const catalog = catalogOf(props);
        const value = Object.prototype.hasOwnProperty.call(catalog, key) ? catalog[key] : undefined;

        return typeof value === 'string' ? value : undefined;
    };

    return {
        get locale() {
            return localeOf(readProps());
        },

        get translations() {
            return catalogOf(readProps()) as Record<string, string>;
        },

        /** Translate a key, replacing :param placeholders. Returns the key when missing. */
        t(key: string, params?: TranslationParams): string {
            const line = lookup(readProps(), key.trim());

            return line === undefined ? key : replaceParams(line, params);
        },

        /** Translate with pluralization; `:count` is available unless overridden in params. */
        tc(key: string, count: number, params?: TranslationParams): string {
            const props = readProps();
            const line = lookup(props, key.trim());

            if (line === undefined) {
                return key;
            }

            return replaceParams(choosePluralForm(line, count, localeOf(props)), { count, ...params });
        },
    };
}

export default useTranslation;

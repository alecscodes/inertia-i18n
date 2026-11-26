// THIRD-PARTY LICENSE ATTRIBUTION (this notice travels with the copied standalone asset)
//
// Laravel Framework 12.x/13.x Illuminate/Translation/MessageSelector behavior is adapted under the
// MIT License (https://github.com/laravel/framework/blob/13.x/LICENSE.md).
// Copyright (c) Taylor Otwell
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
// SOFTWARE.
//
// The underlying Zend Technologies plural-rule table is derived from code under
// the New BSD (3-Clause) License. Copyright (c) 2005-2010, Zend Technologies USA Inc.
// Source: https://github.com/laravel/framework/blob/13.x/src/Illuminate/Translation/MessageSelector.php
// Zend license: https://framework.zend.com/license
// Redistribution and use in source and binary forms, with or without modification,
// are permitted provided that the following conditions are met:
// 1. Redistributions of source code must retain the above copyright notice, this
//    list of conditions and the following disclaimer.
// 2. Redistributions in binary form must reproduce the above copyright notice,
//    this list of conditions and the following disclaimer in the documentation
//    and/or other materials provided with the distribution.
// 3. Neither the name of Zend Technologies USA Inc. nor the names of its
//    contributors may be used to endorse or promote products derived from this
//    software without specific prior written permission.
// THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
// AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
// IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
// DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
// FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
// DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
// SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
// CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
// OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
// OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

/** Adapter-neutral translations for the Inertia page props catalog. */
export type TranslationParams = Record<string, string | number>;
export type TranslationTree = Record<string, unknown>;
export type TranslationPageProps = Record<string, unknown>;
export interface TranslationPropNames {
    locale?: string;
    translations?: string;
    translation_locales?: string;
}
export interface TranslationOptions { props?: TranslationPropNames }
export type TranslationSource = TranslationPageProps | (() => TranslationPageProps);
export interface TranslationResult {
    readonly locale: string;
    readonly translations: Readonly<Record<string, string>>;
    t(key: string, params?: TranslationParams): string;
    tc(key: string, count: number, params?: TranslationParams): string;
}

const own = (value: object, key: string): boolean => Object.prototype.hasOwnProperty.call(value, key);
const plainRecord = (value: unknown): value is Record<string, unknown> =>
    value !== null && typeof value === 'object' && !Array.isArray(value);

function readProps(source: TranslationSource): TranslationPageProps {
    try { const value = typeof source === 'function' ? source() : source; return plainRecord(value) ? value : {}; }
    catch { return {}; }
}

function readMap(value: unknown): Record<string, string> {
    const result: Record<string, string> = Object.create(null) as Record<string, string>;
    if (!plainRecord(value)) return result;
    for (const key of Object.keys(value)) if (typeof value[key] === 'string') result[key] = value[key] as string;
    return result;
}

function ownString(value: unknown, key: string): string | undefined {
    if (!plainRecord(value) || !own(value, key)) return undefined;
    const found = value[key];
    return typeof found === 'string' ? found : undefined;
}

function replaceParams(raw: string, params?: TranslationParams): string {
    if (!params || typeof params !== 'object') return raw;
    const generated = new Map<string, string>();
    // Sort source keys so generated aliases resolve deterministically regardless of object insertion order.
    for (const key of Object.keys(params).sort()) {
        const value = params[key];
        if (typeof value !== 'string' && typeof value !== 'number') continue;
        const token = `:${key}`;
        if (token.length > 1) {
            const chars = Array.from(String(value));
            const ucfirst = chars.length ? chars[0].toUpperCase() + chars.slice(1).join('') : '';
            generated.set(`:${key[0]?.toUpperCase() ?? ''}${key.slice(1)}`, ucfirst);
            generated.set(`:${key.toUpperCase()}`, String(value).toUpperCase());
            generated.set(token, String(value));
        }
    }
    const tokens = [...generated.entries()].map(([token, value]) => ({ token, value }));
    tokens.sort((a, b) => b.token.length - a.token.length || (a.token < b.token ? -1 : a.token > b.token ? 1 : 0));
    if (!tokens.length) return raw;
    const alternation = tokens.map(({ token }) => token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|');
    const lookup = new Map(tokens.map(({ token, value }) => [token, value]));
    return raw.replace(new RegExp(alternation, 'gu'), (match) => lookup.get(match) as string);
}

// Laravel-style family rules; supported regional identifiers are deliberately enumerated.
// See the full notices below and in THIRD_PARTY_NOTICES.md.
function pluralIndex(locale: string, number: number): number {
    const normalized = locale.replace(/-/g, '_').toLowerCase();
    const [lang, region, ...rest] = normalized.split('_');
    if (rest.length) return 0;
    const regions: Record<string, string> = {
        az: 'az', bo: 'bo', dz: 'dz', eo: 'eo', id: 'id', ja: 'ja', ka: 'ka', km: 'km', kn: 'kn', ko: 'ko', ms: 'ms', th: 'th', tr: 'tr', vi: 'vi', zh: 'zh',
        af: 'af', bn: 'bn', bg: 'bg', ca: 'ca', da: 'da', de: 'de', el: 'el', en: 'en', es: 'es', et: 'et', eu: 'eu', fa: 'fa', fi: 'fi', fo: 'fo', fur: 'fur', fy: 'fy', gl: 'gl', gu: 'gu', ha: 'ha', he: 'he', hu: 'hu', is: 'is', it: 'it', ku: 'ku', lb: 'lb', ml: 'ml', mn: 'mn', mr: 'mr', nb: 'nb', ne: 'ne', nl: 'nl', nn: 'nn', no: 'no', om: 'om', or: 'or', pa: 'pa', pap: 'pap', ps: 'ps', pt: 'pt', so: 'so', sq: 'sq', sv: 'sv', sw: 'sw', ta: 'ta', te: 'te', tk: 'tk', ur: 'ur', zu: 'zu',
        am: 'am', fil: 'fil', fr: 'fr', hi: 'hi', hy: 'hy', ln: 'ln', mg: 'mg', nso: 'nso', ti: 'ti', wa: 'wa', be: 'be', bs: 'bs', hr: 'hr', ru: 'ru', sr: 'sr', uk: 'uk', cs: 'cs', sk: 'sk', ga: 'ga', lt: 'lt', sl: 'sl', mk: 'mk', mt: 'mt', lv: 'lv', pl: 'pl', cy: 'cy', ro: 'ro', ar: 'ar',
    };
    const supportedRegions: Record<string, string> = {
        az:'AZ',bo:'CN IN',dz:'BT',af:'ZA',bn:'BD IN',bg:'BG',ca:'AD ES FR IT',da:'DK',de:'AT BE CH DE LI LU',el:'CY GR',en:'AG AU BW CA DK GB HK IE IN NG NZ PH SG US ZA ZM ZW',es:'AR BO CL CO CR CU DO EC ES GT HN MX NI PA PE PR PY SV US UY VE',et:'EE',eu:'ES FR',fa:'IR',fi:'FI',fo:'FO',fur:'IT',fy:'DE NL',gl:'ES',gu:'IN',ha:'NG',he:'IL',hu:'HU',is:'IS',it:'CH IT',ku:'TR',lb:'LU',ml:'IN',mn:'MN',mr:'IN',nb:'NO',ne:'NP',nl:'AW BE NL',nn:'NO',om:'ET KE',or:'IN',pa:'IN PK',pap:'AN AW CW',ps:'AF',pt:'BR PT',so:'DJ ET KE SO',sq:'AL MK',sv:'FI SE',sw:'KE TZ',ta:'IN LK',te:'IN',tk:'TM',ur:'IN PK',zu:'ZA',
        eo:'US',id:'ID',ja:'JP',ka:'GE',km:'KH',kn:'IN',ko:'KR',ms:'MY',th:'TH',tr:'CY TR',vi:'VN',zh:'CN HK SG TW',
        am:'ET',fil:'PH',fr:'BE CA CH FR LU',hi:'IN',hy:'AM',ln:'CD',mg:'MG',nso:'ZA',ti:'ER ET',wa:'BE',be:'BY',bs:'BA',hr:'HR',ru:'RU UA',sr:'ME RS',uk:'UA',cs:'CZ',sk:'SK',ga:'IE',lt:'LT',sl:'SI',mk:'MK',mt:'MT',lv:'LV',pl:'PL',cy:'GB',ro:'RO',
        ar:'AE BH DZ EG IN IQ JO KW LB LY MA OM QA SA SD SS SY TN YE',
    };
    const known = new Set('az bo dz id ja jv ka km kn ko ms th tr vi zh af bn bg ca da de el en eo es et eu fa fi fo fur fy gl gu ha he hu is it ku lb ml mn mr nah nb ne nl nn no om or pa pap ps pt so sq sv sw ta te tk ur zu am bh fil fr gun hi hy ln mg nso ti wa xbr be bs hr ru sr uk cs sk ga lt sl mk mt lv pl cy ro ar'.split(' '));
    if (!known.has(lang)) return 0;
    if (region && (!regions[lang] || !supportedRegions[lang]?.split(' ').includes(region.toUpperCase()))) return 0;
    const n = Math.abs(number), i = Math.trunc(n), mod10 = i % 10, mod100 = i % 100;
    if ('az bo dz id ja jv ka km kn ko ms th tr vi zh'.split(' ').includes(lang)) return 0;
    if ('am bh fil fr gun hi hy ln mg nso ti wa xbr'.split(' ').includes(lang)) return n === 0 || n === 1 ? 0 : 1;
    if ('be bs hr ru sr uk'.split(' ').includes(lang)) return mod10 === 1 && mod100 !== 11 ? 0 : mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20) ? 1 : 2;
    if ('cs sk'.split(' ').includes(lang)) return n === 1 ? 0 : n >= 2 && n <= 4 ? 1 : 2;
    if (lang === 'ga') return n === 1 ? 0 : n === 2 ? 1 : 2;
    if (lang === 'lt') return mod10 === 1 && mod100 !== 11 ? 0 : mod10 >= 2 && (mod100 < 10 || mod100 >= 20) ? 1 : 2;
    if (lang === 'sl') return i % 100 === 1 ? 0 : i % 100 === 2 ? 1 : i % 100 === 3 || i % 100 === 4 ? 2 : 3;
    if (lang === 'mk') return mod10 === 1 ? 0 : 1;
    if (lang === 'mt') return n === 1 ? 0 : n === 0 || mod100 > 1 && mod100 < 11 ? 1 : mod100 > 10 && mod100 < 20 ? 2 : 3;
    if (lang === 'lv') return n === 0 ? 0 : mod10 === 1 && mod100 !== 11 ? 1 : 2;
    if (lang === 'pl') return n === 1 ? 0 : mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14) ? 1 : 2;
    if (lang === 'cy') return n === 1 ? 0 : n === 2 ? 1 : n === 8 || n === 11 ? 2 : 3;
    if (lang === 'ro') return n === 1 ? 0 : n === 0 || mod100 > 0 && mod100 < 20 ? 1 : 2;
    if (lang === 'ar') return n === 0 ? 0 : n === 1 ? 1 : n === 2 ? 2 : mod100 >= 3 && mod100 <= 10 ? 3 : mod100 >= 11 && mod100 <= 99 ? 4 : 5;
    // Laravel defaults unknown languages to the first form.
    return 'af bn bg ca da de el en eo es et eu fa fi fo fur fy gl gu ha he hu is it ku lb ml mn mr nah nb ne nl nn no om or pa pap ps pt so sq sv sw ta te tk ur zu'.split(' ').includes(lang)
        ? (n === 1 ? 0 : 1)
        : 0;
}

function choosePluralForm(line: string, count: number, locale: string): string {
    const parts = line.split('|');
    for (const part of parts) {
        const match = part.match(/^\s*[\[{]\s*(-?(?:\d+(?:\.\d*)?|\.\d+)|\*)\s*(?:,\s*(-?(?:\d+(?:\.\d*)?|\.\d+)|\*)\s*)?[\]}](.*)$/s);
        if (!match) continue;
        const [, lower, upper, text] = match;
        if (upper === undefined) { if (Number(lower) === count) return text.trim(); continue; }
        const min = lower === '*' ? -Infinity : Number(lower);
        const max = upper === '*' ? Infinity : Number(upper);
        if (count >= min && count <= max) return text.trim();
    }
    const cleaned = parts.map(part => part.replace(/^\s*[\[{]\s*-?(?:\d+(?:\.\d*)?|\.\d+|\*)(?:\s*,\s*-?(?:\d+(?:\.\d*)?|\.\d+|\*))?\s*[\]}]/, ''));
    const index = pluralIndex(locale, count);
    return cleaned[index] ?? cleaned[0] ?? '';
}

/**
 * Returns call-time reads of Inertia props. Framework integrations should derive/read these
 * getters in their own reactive system (Vue computed, Svelte derived); React calls during render.
 */
export function useTranslation(source: TranslationSource, options: TranslationOptions = {}): TranslationResult {
    const names = {
        locale: options.props?.locale ?? 'locale',
        translations: options.props?.translations ?? 'translations',
        translation_locales: options.props?.translation_locales ?? 'translationLocales',
    };
    const props = () => readProps(source);
    const localeFrom = (p: TranslationPageProps) => ownString(p, names.locale) ?? 'en';
    const translationsFrom = (p: TranslationPageProps) => own(p, names.translations) ? p[names.translations] : undefined;
    const localesFrom = (p: TranslationPageProps) => own(p, names.translation_locales) ? p[names.translation_locales] : undefined;
    const translate = (key: string, params?: TranslationParams): string => {
        const p = props();
        const value = ownString(translationsFrom(p), key);
        return value === undefined ? key : replaceParams(value, params);
    };
    const plural = (key: string, count: number, params?: TranslationParams): string => {
        if (!Number.isFinite(count)) throw new RangeError('Plural count must be a finite number');
        const p = props();
        const value = ownString(translationsFrom(p), key);
        if (value === undefined) return key;
        const sourceLocale = ownString(localesFrom(p), key);
        const form = choosePluralForm(value, count, sourceLocale ?? localeFrom(p));
        const combined: TranslationParams = Object.assign(Object.create(null), { count }, params ?? {});
        return replaceParams(form, combined);
    };
    return { get locale() { return localeFrom(props()); }, get translations() { const p = props(); return readMap(translationsFrom(p)); }, t: translate, tc: plural };
}

export default useTranslation;

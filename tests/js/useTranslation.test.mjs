import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import useTranslation from '../../resources/js/useTranslation.ts';

const translate = (translations, locale = 'en') => useTranslation({ locale, translations });

test('t() returns the translation, or the key when missing', () => {
    const { t } = translate({ 'nav.home': 'Home' });

    assert.equal(t('nav.home'), 'Home');
    assert.equal(t(' nav.home '), 'Home');
    assert.equal(t('nav.missing'), 'nav.missing');
});

test('t() ignores inherited properties and non-string values', () => {
    const { t } = translate({ count: 3 });

    assert.equal(t('constructor'), 'constructor');
    assert.equal(t('toString'), 'toString');
    assert.equal(t('count'), 'count');
});

test('t() tolerates missing or malformed props', () => {
    assert.equal(useTranslation({}).t('x'), 'x');
    assert.equal(useTranslation({ translations: null }).t('x'), 'x');
    assert.equal(useTranslation({ translations: [] }).t('x'), 'x');
    assert.equal(useTranslation({}).locale, 'en');
});

test('placeholders: :name, :Name and :NAME', () => {
    const { t } = translate({ greet: 'Hi :name / :Name / :NAME' });

    assert.equal(t('greet', { name: 'éva' }), 'Hi éva / Éva / ÉVA');
});

test('placeholders: longest match first, single pass, literal values', () => {
    const { t } = translate({ x: ':names :name', y: 'Hi :name', z: ':a :b' });

    assert.equal(t('x', { name: 'A', names: 'B' }), 'B A');
    assert.equal(t('y', { name: '$& $1 \\' }), 'Hi $& $1 \\');
    assert.equal(t('z', { a: ':b', b: 'B' }), ':b B');
});

test('tc() fills :count and lets params override it', () => {
    const { tc } = translate({ items: '{0} No items|{1} One item|[2,*] :count items' });

    assert.equal(tc('items', 0), 'No items');
    assert.equal(tc('items', 1), 'One item');
    assert.equal(tc('items', 7), '7 items');
    assert.equal(tc('items', 7, { count: 'seven' }), 'seven items');
    assert.equal(tc('missing', 2), 'missing');
});

test('tc() uses the locale plural rule', () => {
    const line = 'un minut|:count minute|:count de minute';

    assert.equal(translate({ m: line }, 'ro').tc('m', 1), 'un minut');
    assert.equal(translate({ m: line }, 'ro').tc('m', 19), '19 minute');
    assert.equal(translate({ m: line }, 'ro').tc('m', 20), '20 de minute');
    assert.equal(translate({ m: 'item|items' }, 'en-US').tc('m', 2), 'items');
    assert.equal(translate({ m: 'item|items' }, 'pt-BR').tc('m', 0), 'items');
});

test('tc() never leaks unmatched condition markers', () => {
    assert.equal(translate({ x: '{0} none|{1} one|[2,4] few' }).tc('x', 5), 'one');
});

test('a props getter is read on every call (persistent layouts, locale switches)', () => {
    let props = { locale: 'en', translations: { hi: 'Hello' } };
    const translator = useTranslation(() => props);
    const { t } = translator;

    assert.equal(t('hi'), 'Hello');

    props = { locale: 'fr', translations: { hi: 'Bonjour' } };

    assert.equal(t('hi'), 'Bonjour');
    assert.equal(translator.locale, 'fr');
    assert.deepEqual(translator.translations, { hi: 'Bonjour' });
});

test('custom prop names', () => {
    const { t, tc, locale } = useTranslation(
        { lang: 'ru', i18n: { x: ':count файл|:count файла|:count файлов' } },
        { props: { locale: 'lang', translations: 'i18n' } },
    );

    assert.equal(locale, 'ru');
    assert.equal(t('x', { count: 1 }), '1 файл|1 файла|1 файлов');
    assert.equal(tc('x', 3), '3 файла');
    assert.equal(tc('x', 5), '5 файлов');
});

test('plural selection matches Laravel MessageSelector (shared fixture)', () => {
    const vectors = JSON.parse(readFileSync(new URL('../fixtures/plural-vectors.json', import.meta.url), 'utf8'));

    for (const { line, locale, count, expected } of vectors) {
        // ':count' is never interpolated in the fixture: pass it through literally.
        assert.equal(translate({ x: line }, locale).tc('x', count, { count: ':count' }), expected, `${locale} / ${count} / ${line}`);
    }
});

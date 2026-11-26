import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import useTranslation from '../../.test-dist/resources/js/useTranslation.js';

test('props are read lazily and getter results are live', () => {
  let props = { locale: 'en', translations: { hi: 'Hello' } };
  const tr = useTranslation(() => props);
  assert.equal(tr.locale, 'en'); assert.equal(tr.t('hi'), 'Hello');
  props = { locale: 'fr', translations: { hi: 'Salut' } };
  assert.equal(tr.locale, 'fr'); assert.equal(tr.translations.hi, 'Salut'); assert.equal(tr.t('hi'), 'Salut');
  props.locale = 'de'; props.translations.hi = 'Hallo';
  assert.equal(tr.locale, 'de'); assert.equal(tr.t('hi'), 'Hallo');
});

test('custom prop names, malformed props, literal keys and own dangerous keys', () => {
  const input = Object.create({ inherited: 'bad', constructor: 'bad', toString: 'bad' });
  const dict = Object.create(null);
  Object.assign(dict, { ' key. ': 'space', constructor: 'mine', toString: 'mine too' });
  Object.defineProperty(dict, '__proto__', { value: 'own proto', enumerable: true });
  input.language = 'de'; input.dict = dict;
  const tr = useTranslation(input, { props: { locale: 'language', translations: 'dict' } });
  assert.equal(tr.locale, 'de'); assert.equal(tr.t(' key. '), 'space');
  assert.equal(tr.t('__proto__'), 'own proto'); assert.equal(tr.t('constructor'), 'mine'); assert.equal(tr.t('toString'), 'mine too');
  assert.equal(tr.t('missing'), 'missing'); assert.equal(tr.t(' key. '), 'space');
  assert.equal(useTranslation(null).t('x'), 'x'); assert.equal(useTranslation([]).t('x'), 'x');
  assert.equal(useTranslation({ translations: { x: 1 } }).t('x'), 'x');
  const inheritedCatalog = Object.create({ inherited: 'bad' }); inheritedCatalog.empty = '';
  assert.equal(useTranslation({ translations: inheritedCatalog }).t('inherited'), 'inherited');
  assert.equal(useTranslation({ translations: inheritedCatalog }).t('empty'), '');
  assert.equal(useTranslation({ translations: [] }).t('x'), 'x');
  assert.equal(useTranslation({ translationLocales: [] , translations: { x: 'one|other' } }).tc('x', 5), 'other');
  const meta = Object.create(null); Object.defineProperty(meta, '__proto__', { value: 'en', enumerable: true });
  const dangerousTranslations = Object.create(null); Object.defineProperty(dangerousTranslations, '__proto__', { value: 'one|other', enumerable: true });
  assert.equal(useTranslation({ locale: 'ru', translations: dangerousTranslations, translationLocales: meta }).tc('__proto__', 5), 'other');
  const customMeta = useTranslation({ lang: 'ru', strings: { x: 'one|other' }, origins: { x: 'en' } }, { props: { locale: 'lang', translations: 'strings', translation_locales: 'origins' } });
  assert.equal(customMeta.tc('x', 5), 'other');
  const defaults = useTranslation({ locale: 'fr', translations: { x: 'bonjour' }, translationLocales: { x: 'en' } }, { props: { locale: undefined, translations: undefined, translation_locales: undefined } });
  assert.equal(defaults.locale, 'fr'); assert.equal(defaults.t('x'), 'bonjour');
});

test('placeholders are single-pass, deterministic, literal, and case compatible', () => {
  const tr = useTranslation({ translations: { x: ':name :Name :NAME :id :id2 :dollar :back' } });
  assert.equal(tr.t('x', { name: 'alexx', id: 'I', id2: 'J', dollar: '$& $1', back: '\\q' }), 'alexx Alexx ALEXX I J $& $1 \\q');
  assert.equal(useTranslation({ translations: { overlap: ':id2' } }).t('overlap', { id: 'short', id2: 'long' }), 'long');
  const overlap = useTranslation({ translations: { x: ':a :ab' } });
  assert.equal(overlap.t('x', { a: 'A', ab: 'AB' }), 'A AB');
  assert.equal(overlap.t('x', { ab: 'AB', a: 'A' }), 'A AB');
  assert.equal(useTranslation({ translations: { x: ':a :b' } }).t('x', { a: ':b', b: 'B' }), ':b B');
  assert.equal(useTranslation({ translations: { x: ':a.* :a.*' } }).t('x', { 'a.*': '$\\' }), '$\\ $\\');
  assert.equal(useTranslation({ translations: { x: ':name :Name :NAME' } }).t('x', { name: 'éclair' }), 'éclair Éclair ÉCLAIR');
  assert.equal(useTranslation({ translations: { x: ':name :Name :NAME' } }).t('x', { name: 'low', Name: 'User' }), 'low Low LOW');
  assert.equal(useTranslation({ translations: { x: ':Name' } }).t('x', { Name: 'User', name: 'low' }), 'Low');
  assert.equal(useTranslation({ translations: { x: ':Name' } }).t('x', { name: 'low', Name: 'User' }), 'Low');
  assert.equal(tr.t(' key '), ' key ');
});

test('tc explicit conditions, source locale, count override and validation', () => {
  const tr = useTranslation({ locale: 'ru', translations: { en: '{0} zero|{1} one|[-3,-2] less|[2,4] few|[5,*] many', fallback: ':count|other' }, translationLocales: { en: 'en' } });
  assert.equal(tr.tc('en', 0), 'zero'); assert.equal(tr.tc('en', -2), 'less'); assert.equal(tr.tc('en', 3), 'few'); assert.equal(tr.tc('en', 6), 'many');
  assert.equal(tr.tc('fallback', 1, { count: 9 }), '9');
  for (const invalid of [NaN, Infinity, -Infinity]) assert.throws(() => tr.tc('fallback', invalid), RangeError);
  assert.equal(useTranslation({ locale: 'ja', translations: { x: 'a|b' } }).tc('x', 5), 'a');
});

test('plural provenance and all inputs are resolved from one current props snapshot', () => {
  let props = { locale: 'ru', translations: { x: 'one|few|many' }, translationLocales: { x: 'ru' } };
  const live = useTranslation(() => props);
  assert.equal(live.tc('x', 5), 'many');
  props.translationLocales.x = 'en';
  props.translations.x = 'one|other';
  assert.equal(live.tc('x', 5), 'other');
  props = { locale: 'ru', translations: { x: 'one|other' }, translationLocales: { x: 'en' } };
  assert.equal(live.tc('x', 5), 'other');
  props.locale = 'fr'; props.translationLocales = {};
  assert.equal(live.tc('x', 5), 'other');

  let reads = 0;
  const snapshots = [
    { locale: 'ru', translations: { x: 'one|few|many' }, translationLocales: { x: 'ru' } },
    { locale: 'en', translations: { x: 'one|other' }, translationLocales: { x: 'en' } },
  ];
  assert.equal(useTranslation(() => snapshots[Math.min(reads++, 1)]).tc('x', 5), 'many');
  assert.equal(reads, 1);
});

test('known regional locales, unknown regions, condition boundaries and whitespace', () => {
  for (const locale of ['en_US', 'en-US', 'en_GB', 'en-GB'])
    assert.equal(useTranslation({ locale, translations: { x: 'one|other' } }).tc('x', 2), 'other');
  for (const locale of ['en_UNKNOWN', 'en-UNKNOWN'])
    assert.equal(useTranslation({ locale, translations: { x: 'first|second' } }).tc('x', 2), 'first');

  const tr = useTranslation({ locale: 'en', translations: {
    decimalExact: '{1.5}  half  |other', decimalRange: '[1.5,2.5] between|other',
    openLower: '[*,0] none|other', unmatched: '[-5,-3] no|one|other',
    insufficient: 'first|second', spaced: ' first | second ', selected: '{1}  exact  |other',
  } });
  assert.equal(tr.tc('decimalExact', 1.5), 'half');
  assert.equal(tr.tc('decimalRange', 2), 'between');
  assert.equal(tr.tc('openLower', -1), 'none');
  assert.equal(tr.tc('unmatched', 2), 'one');
  assert.equal(useTranslation({ locale: 'ar', translations: { x: 'first|second' } }).tc('x', 10), 'first');
  assert.equal(tr.tc('spaced', 1), ' first ');
  assert.equal(tr.tc('selected', 1), 'exact');
  assert.equal(useTranslation({ locale: 'en', translations: { x: 'one|other' } }).tc('x', -1), 'one');
  assert.equal(useTranslation({ locale: 'ru', translations: { x: 'one|few|many' } }).tc('x', -2), 'few');
});

test('Laravel plural parity vectors shared with the PHP MessageSelector suite', async () => {
  const vectors = JSON.parse(await readFile(new URL('../fixtures/plural-vectors.json', import.meta.url), 'utf8'));
  for (const { line, locale, count, expected } of vectors) {
    assert.equal(useTranslation({ locale, translations: { x: line } }).tc('x', count), expected, `${locale}/${count}`);
  }
  assert.equal(useTranslation({ locale: 'ru-RU', translations: { x: 'one|few|many' } }).tc('x', 4), 'few');
  assert.equal(useTranslation({ locale: 'ja-JP', translations: { x: 'one|other' } }).tc('x', 10), 'one');
  assert.equal(useTranslation({ locale: 'ar-SA', translations: { x: 'zero|one|two|few|many|other' } }).tc('x', 11), 'many');
});

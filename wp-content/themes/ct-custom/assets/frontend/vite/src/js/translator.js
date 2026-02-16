/**
 * Frontend Translation helper.
 *
 * Reads from ctTranslationData.translations injected via wp_localize_script.
 * Provides window.ct_translate(key, args, count) for frontend JS usage.
 *
 * @package BS_Custom
 */

const MAX_ARGS = 50;

/**
 * Translate a key using the frontend translation data.
 *
 * @param {string}      key   Translation key.
 * @param {Object}      args  Placeholder replacements {name: 'value'}.
 * @param {number|null} count Plural count.
 * @return {string}
 */
function ct_translate(key, args = {}, count = null) {
    const data = window.ctTranslationData?.translations || {};

    if (!data[key]) {
        return key;
    }

    let value = data[key];

    /* Plural handling — combined format: { singular, zero, one, two, few, many, other } */
    const validForms = ['singular', 'zero', 'one', 'two', 'few', 'many', 'other'];

    if (typeof value === 'object' && value !== null) {
        if (typeof count === 'string' && validForms.includes(count)) {
            value = value[count] || value['singular'] || key;
        } else if (count !== null) {
            const category = resolvePluralCategory(
                window.ctTranslationData?.iso2 || 'en',
                count
            );

            value = value[category] || value['other'] || value['singular'] || key;
        } else {
            value = value.singular || key;
        }
    }

    if (typeof value !== 'string') {
        return key;
    }

    /* Placeholder replacement */
    let argCount = 0;
    for (const placeholder in args) {
        if (argCount >= MAX_ARGS) { break; }
        argCount++;

        value = value.replaceAll('##' + placeholder + '##', String(args[placeholder]));
    }

    /* Strip unresolved placeholders */
    value = value.replace(/##[a-zA-Z0-9_]+##/g, '');

    return value;
}

/**
 * Simple plural category resolver (mirrors PHP CT_CLDR_Plural_Rules).
 *
 * @param {string} iso2  Language code.
 * @param {number} count Integer count.
 * @return {string} Plural category.
 */
function resolvePluralCategory(iso2, count) {
    const n = Math.abs(count);
    const mod10  = n % 10;
    const mod100 = n % 100;

    const noPluralLangs = ['ja', 'zh', 'ko', 'tr', 'vi', 'th', 'id', 'ms'];
    if (noPluralLangs.includes(iso2)) {
        return 'other';
    }

    const frenchLangs = ['fr', 'hi', 'fa'];
    if (frenchLangs.includes(iso2)) {
        return n <= 1 ? 'one' : 'other';
    }

    const eastSlavicLangs = ['sr', 'ru', 'uk', 'be', 'hr', 'bs'];
    if (eastSlavicLangs.includes(iso2)) {
        if (mod10 === 1 && mod100 !== 11) { return 'one'; }
        if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) { return 'few'; }
        return 'other';
    }

    const westSlavicLangs = ['cs', 'sk'];
    if (westSlavicLangs.includes(iso2)) {
        if (n === 1) { return 'one'; }
        if (n >= 2 && n <= 4) { return 'few'; }
        return 'other';
    }

    if (iso2 === 'pl') {
        if (n === 1) { return 'one'; }
        if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) { return 'few'; }
        return 'many';
    }

    if (iso2 === 'ar') {
        if (n === 0) { return 'zero'; }
        if (n === 1) { return 'one'; }
        if (n === 2) { return 'two'; }
        if (mod100 >= 3 && mod100 <= 10) { return 'few'; }
        if (mod100 >= 11 && mod100 <= 99) { return 'many'; }
        return 'other';
    }

    /* Germanic / default */
    return n === 1 ? 'one' : 'other';
}

/* Expose globally */
window.ct_translate = ct_translate;

export default ct_translate;

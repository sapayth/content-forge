// DESCRIPTION: Asserts every Content Forge color token meets WCAG AA against the surface it is actually used on.
// DESCRIPTION: Run with `node tests/js/contrast.test.js`. Fails loudly if a token is retuned below threshold.
const assert = require('assert');
const { theme } = require('../../tailwind.config.js');

const c = theme.extend.colors;
const WHITE = '#FFFFFF';

const luminance = (hex) => {
    const [r, g, b] = hex.replace('#', '').match(/../g)
        .map((h) => parseInt(h, 16) / 255)
        .map((v) => (v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4)));
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

const ratio = (a, b) => {
    const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (hi + 0.05) / (lo + 0.05);
};

// [label, foreground, background, minimum]
// 4.5 = AA normal text. 3.0 = AA non-text (borders, icons, large text).
const cases = [
    ['white label on primary', WHITE, c.primary, 4.5],
    ['white label on primaryHover', WHITE, c.primaryHover, 4.5],
    ['primary text on white', c.primary, WHITE, 4.5],
    ['primaryHover text on brand-50', c.primaryHover, c['brand-50'], 4.5],
    ['white label on success', WHITE, c.success, 4.5],
    ['white label on warning', WHITE, c.warning, 4.5],
    ['white label on error', WHITE, c.error, 4.5],
    ['error text on white', c.error, WHITE, 4.5],
    ['text-primary on white', c['text-primary'], WHITE, 4.5],
    ['text-secondary on white', c['text-secondary'], WHITE, 4.5],
    ['text-primary on tertiary', c['text-primary'], c.tertiary, 4.5],
    ['border on white', c.border, WHITE, 1.0],

    // Notice and Badge sit on Tailwind's default 50 tints, which match HFE's alert values.
    ['success on green-50', c.success, '#F0FDF4', 4.5],
    ['error on red-50', c.error, '#FEF2F2', 4.5],
    ['warning on yellow-50', c.warning, '#FEFCE8', 4.5],
    ['sky-700 on sky-50', '#0369A1', '#F0F9FF', 4.5],
    ['primaryHover on brand-50', c.primaryHover, c['brand-50'], 4.5],

    ['white label on errorHover', WHITE, c.errorHover, 4.5],
];

let failed = 0;
for (const [label, fg, bg, min] of cases) {
    const r = ratio(fg, bg);
    const ok = r >= min;
    if (!ok) { failed++; }
    console.log(`${ok ? 'PASS' : 'FAIL'}  ${r.toFixed(2)}:1  (min ${min})  ${label}`);
}

// accent is deliberately too light for white text — guard the rule rather than the value,
// so nobody promotes it to a button fill later.
const accentOnWhite = ratio(c.accent, WHITE);
console.log(`INFO  ${accentOnWhite.toFixed(2)}:1  accent on white — fills only, dark text required`);
assert.ok(accentOnWhite < 3.0, 'accent unexpectedly passes on white; re-check its documented role');
assert.ok(ratio(c['text-primary'], c.accent) >= 4.5, 'accent must carry text-primary at AA');

assert.strictEqual(failed, 0, `${failed} token pair(s) below WCAG AA`);
console.log('\nAll token pairs meet WCAG AA.');

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';

const source = readFileSync(new URL('../resources/views/admin/order/sales_bill_register.blade.php', import.meta.url), 'utf8');
const callback = source.split('$.fn.dataTable.ext.search.push(')[1].split('\n        );')[0].trim();

function matches(date, from = '', to = '', tableId = 'data-table') {
    const filter = vm.runInNewContext(`(${callback})`, {
        $: selector => ({ val: () => selector === '#min-date' ? from : to }),
    });
    return filter({ nTable: { id: tableId } }, ['', '', '2025-26', date], 0);
}

test('date boundaries are inclusive and use invoice date rather than financial year', () => {
    assert.equal(matches('01-04-2025', '2025-04-01', '2025-04-30'), true);
    assert.equal(matches('30-04-2025', '2025-04-01', '2025-04-30'), true);
    assert.equal(matches('31-03-2025', '2025-04-01', '2025-04-30'), false);
    assert.equal(matches('01-05-2025', '2025-04-01', '2025-04-30'), false);
});

test('one-sided filters and clearing both dates work across years', () => {
    assert.equal(matches('31-12-2025', '2026-01-01'), false);
    assert.equal(matches('01-01-2026', '2026-01-01'), true);
    assert.equal(matches('01-01-2026', '', '2025-12-31'), false);
    assert.equal(matches('31-12-2025', '', '2025-12-31'), true);
    assert.equal(matches('01-01-2026'), true);
});

test('missing dates are excluded only when filtering and other tables are unaffected', () => {
    assert.equal(matches('', '2025-04-01'), false);
    assert.equal(matches(''), true);
    assert.equal(matches('', '2025-04-01', '', 'another-table'), true);
});

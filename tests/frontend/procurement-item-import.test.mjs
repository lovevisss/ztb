import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import ts from 'typescript';
import ExcelJS from 'exceljs';

const source = await readFile(new URL('../../resources/js/lib/procurement-item-import.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } });
const { itemHeaders, parseItemCsv, validateItemRows, populateItemTemplate } = await import(`data:text/javascript;base64,${Buffer.from(compiled.outputText).toString('base64')}`);
const valid = ['办公椅', '可调节', '10', '350.00', '把', '送货'];

test('template survives xlsx export with blank input sheet and examples isolated', async () => {
    const workbook = new ExcelJS.Workbook();
    populateItemTemplate(workbook);
    const loaded = new ExcelJS.Workbook();
    await loaded.xlsx.load(await workbook.xlsx.writeBuffer());
    assert.deepEqual(loaded.worksheets.map((sheet) => sheet.name), ['采购清单', '填写说明与示例']);
    const input = loaded.worksheets[0];
    assert.deepEqual(input.getRow(1).values.slice(1), itemHeaders);
    assert.equal(input.getCell('A1').font.color.argb, 'FFB42318');
    assert.equal(input.getCell('B1').font.color.argb, 'FF23253A');
    assert.equal(input.getCell('A2').text, '');
    assert.equal(loaded.worksheets[1].getCell('A11').text, '办公椅（仅必填示例）');
    input.getRow(2).values = valid;
    const rows = [];
    for (let row = 1; row <= input.rowCount; row++) rows.push(Array.from({ length: 6 }, (_, col) => input.getCell(row, col + 1).text));
    assert.equal(validateItemRows(rows, 0).length, 1);
});

test('csv preserves quoted commas, multiline remarks, BOM and blank row positions', () => {
    const csv = `\uFEFF${itemHeaders.join(',')}\r\n\r\n办公椅,"配置,含扶手",1,0,把,"第一行\n第二行"`;
    const result = validateItemRows(parseItemCsv(csv), 0);
    assert.equal(result[0].specification, '配置,含扶手');
    assert.equal(result[0].remarks, '第一行\n第二行');
    assert.equal(result[0].unit_price, 0);
    assert.throws(() => parseItemCsv('名称,"未闭合'), /引号未闭合/);
});

test('required fields, precision and lengths report actual row without partial append', () => {
    for (const [column, value, message] of [
        [0, '', '货物名称为必填'], [2, '', '数量为必填'], [3, '', '单价为必填'],
        [2, '0', '数量必须大于零'], [2, '1.1234', '三位小数'], [3, '-1', '非负金额'],
        [3, '1.234', '两位小数'], [0, '椅'.repeat(256), '255'], [4, '把'.repeat(51), '50'],
    ]) {
        const bad = [...valid]; bad[column] = value;
        const existing = [{ name: '原有项目' }];
        assert.throws(() => existing.push(...validateItemRows([itemHeaders, valid, [], bad], 1)), new RegExp(`第4行.*${message}`));
        assert.equal(existing.length, 1);
    }
    assert.throws(() => validateItemRows([['数量', ...itemHeaders.slice(1)], valid], 0), /第1行/);
    assert.throws(() => validateItemRows([itemHeaders, []], 0), /未找到采购项目/);
});

test('append cap includes existing items, zero price and fractional quantity remain valid', () => {
    assert.equal(validateItemRows([itemHeaders, ['椅', '', '0.125', '0', '', '']], 499).length, 1);
    assert.throws(() => validateItemRows([itemHeaders, valid, valid], 499), /第3行.*500项/);
    assert.throws(() => validateItemRows([itemHeaders, valid], 500), /第2行.*500项/);
});

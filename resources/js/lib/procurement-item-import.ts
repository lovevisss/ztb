import type { Workbook } from 'exceljs';

export const itemHeaders = ['货物名称（必填）', '技术参数', '数量（必填）', '单价（元，必填）', '单位', '备注'];
const headerAliases = [
    ['货物名称', itemHeaders[0]], ['技术参数', '技术参数、配置/主要配置'],
    ['数量', itemHeaders[2]], ['单价', '单价(元)', '单价（元）', itemHeaders[3]], ['单位'], ['备注'],
];
export type ImportedItem = { name: string; specification: string; quantity: number; unit_price: number; unit: string; remarks: string };

export function parseItemCsv(text: string): string[][] {
    const rows: string[][] = [];
    let row: string[] = [], value = '', quoted = false;
    const source = text.replace(/^\uFEFF/, '');
    for (let i = 0; i < source.length; i++) {
        const char = source[i];
        if (char === '"') {
            if (quoted && source[i + 1] === '"') { value += '"'; i++; }
            else quoted = !quoted;
        } else if (char === ',' && !quoted) { row.push(value); value = ''; }
        else if ((char === '\r' || char === '\n') && !quoted) {
            row.push(value); rows.push(row); row = []; value = '';
            if (char === '\r' && source[i + 1] === '\n') i++;
        } else value += char;
    }
    if (quoted) throw new Error('CSV 引号未闭合，请检查文件。');
    if (value || row.length) { row.push(value); rows.push(row); }
    return rows;
}

export function validateItemRows(rows: string[][], existingCount: number): ImportedItem[] {
    const header = rows[0]?.map((cell) => cell.trim()) ?? [];
    if (headerAliases.some((aliases, i) => !aliases.includes(header[i])) || header.slice(6).some(Boolean)) {
        throw new Error('第1行表头或列顺序不正确，请使用导入模板的六列：货物名称、技术参数、数量、单价、单位、备注。');
    }
    const items: ImportedItem[] = [];
    for (let i = 1; i < rows.length; i++) {
        const cells = rows[i].map((cell) => cell.trim());
        if (!cells.some(Boolean)) continue;
        const [name = '', specification = '', quantity = '', price = '', unit = '', remarks = ''] = cells;
        const fail = (message: string): never => { throw new Error(`第${i + 1}行：${message}`); };
        if (!name) fail('货物名称为必填项。');
        if ([...name].length > 255) fail('货物名称最多255个字符。');
        if (!quantity) fail('数量为必填项。');
        if (!/^\d+(?:\.\d{1,3})?$/.test(quantity) || !Number.isFinite(Number(quantity)) || Number(quantity) <= 0) fail('数量必须大于零，最多保留三位小数。');
        if (!price) fail('单价为必填项；免费项目请填写0。');
        if (!/^\d+(?:\.\d{1,2})?$/.test(price) || !Number.isFinite(Number(price))) fail('单价必须为非负金额，最多保留两位小数。');
        if ([...unit].length > 50) fail('单位最多50个字符。');
        if (cells.slice(6).some(Boolean)) fail('只支持模板中的六列。');
        if (existingCount + items.length >= 500) fail('追加后清单超过500项，请减少导入项目。');
        items.push({ name, specification, quantity: Number(quantity), unit_price: Number(price), unit, remarks });
    }
    if (!items.length) throw new Error('未找到采购项目，请在第一个工作表填写数据后导入。');
    return items;
}

export function populateItemTemplate(workbook: Workbook): void {
    const sheet = workbook.addWorksheet('采购清单', { views: [{ state: 'frozen', ySplit: 1 }] });
    sheet.columns = [30, 48, 20, 24, 14, 40].map((width) => ({ width }));
    sheet.addRow(itemHeaders);
    sheet.getRow(1).height = 36;
    sheet.getRow(1).eachCell((cell, index) => {
        cell.font = { name: '微软雅黑', size: 11, bold: true, color: { argb: [1, 3, 4].includes(index) ? 'FFB42318' : 'FF23253A' } };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFEAF1FF' } };
        cell.alignment = { vertical: 'middle', wrapText: true };
    });
    sheet.autoFilter = 'A1:F1';
    for (let row = 2; row <= 21; row++) {
        sheet.getRow(row).height = 28;
        for (let col = 1; col <= 6; col++) {
            const cell = sheet.getCell(row, col);
            cell.font = { name: '微软雅黑', size: 11, color: { argb: 'FF165DFF' } };
            cell.numFmt = col === 3 ? '0.###' : col === 4 ? '0.00' : '@';
        }
    }
    const guide = workbook.addWorksheet('填写说明与示例');
    guide.columns = [30, 48, 20, 24, 14, 40].map((width) => ({ width }));
    const notes = [
        '采购清单填写说明（本表不会导入）',
        '请在第一个工作表“采购清单”填写数据；不要更改六列表头或列顺序。',
        '必填：货物名称、数量、单价。技术参数、单位、备注可留空。',
        '货物名称最多255个字符；单位最多50个字符。',
        '数量必须大于零，最多三位小数；单价不小于零，最多两位小数，金额单位为元。',
        '导入将追加到已有清单，合计最多500项；完全空白行忽略。',
        '导入完成后可修改、删除；点击“保存采购清单”才提交。',
        '以下为示例，请勿将示例复制为实际采购项目。',
    ];
    notes.forEach((note, index) => {
        guide.mergeCells(index + 1, 1, index + 1, 6);
        guide.getCell(index + 1, 1).value = note;
        guide.getRow(index + 1).height = index === 0 ? 36 : 30;
        guide.getCell(index + 1, 1).alignment = { wrapText: true, vertical: 'middle' };
    });
    guide.addRow(itemHeaders);
    guide.addRow(['办公椅（完整示例）', '可调节高度，含扶手', 10, 350, '把', '送货安装']);
    guide.addRow(['办公椅（仅必填示例）', '', 10, 350, '', '']);
    guide.getColumn(4).numFmt = '0.00';
    guide.getRow(1).font = { name: '微软雅黑', size: 14, bold: true, color: { argb: 'FF165DFF' } };
    guide.getRow(9).font = { bold: true, color: { argb: 'FFB42318' } };
    guide.getRow(9).height = 36;
    for (const row of [10, 11]) guide.getRow(row).height = 30;
}

'use strict';

const metatests = require('metatests');
const { Sheet } = require('..');

metatests.test('Simple expressions', async (test) => {
  const sheet = new Sheet();
  sheet.cells['A1'] = 100;
  sheet.cells['B1'] = 2;
  sheet.cells['C1'] = '=A1*B1';
  sheet.cells['D1'] = '=(A1 / B1) - 5';
  sheet.cells['E1'] = '=-A1';
  test.strictSame(sheet.values['C1'], 200);
  test.strictSame(sheet.values['D1'], 45);
  test.strictSame(sheet.values['E1'], -100);
  test.end();
});

metatests.test('Expression chain', async (test) => {
  const sheet = new Sheet();
  sheet.cells['A1'] = 100;
  sheet.cells['B1'] = 2;
  sheet.cells['C1'] = '=A1*B1';
  sheet.cells['D1'] = '=C1+8';
  sheet.cells['E1'] = '=D1/2';
  test.strictSame(sheet.values['D1'], 208);
  test.strictSame(sheet.values['E1'], 104);
  test.end();
});

metatests.test('JavaScript Math', async (test) => {
  const sheet = new Sheet();
  sheet.cells['A1'] = 100;
  sheet.cells['B1'] = -2;
  sheet.cells['C1'] = '=Math.abs(B1)';
  sheet.cells['D1'] = '=Math.exp(A1)';
  sheet.cells['E1'] = '=Math.max(A1, B1)';
  sheet.cells['F1'] = '=Math.pow(A1, 2)';
  sheet.cells['G1'] = '=Math.sin(A1)';
  sheet.cells['H1'] = '=Math.sqrt(A1)';
  sheet.cells['I1'] = '=Math.sin(Math.sqrt(Math.pow(A1, B1)))';
  test.strictSame(sheet.values['C1'], 2);
  test.strictSame(sheet.values['D1'], Math.exp(100));
  test.strictSame(sheet.values['E1'], 100);
  test.strictSame(sheet.values['F1'], 10000);
  test.strictSame(sheet.values['G1'], Math.sin(100));
  test.strictSame(sheet.values['H1'], Math.sqrt(100));
  test.strictSame(sheet.values['I1'], Math.sin(Math.sqrt(Math.pow(100, -2))));
  test.end();
});

metatests.test('Prevent arbitrary js code execution', async (test) => {
  const sheet = new Sheet();
  sheet.cells['A1'] =
    '=Math.constructor.constructor("console.log(\\"Hello, World!\\")")();';
  try {
    const res = sheet.values['A1'];
    test.strictSame(res, undefined);
  } catch (error) {
    test.strictSame(
      error.message,
      `Cannot read property '${'constructor'}' of null`
    );
  }
  test.end();
});

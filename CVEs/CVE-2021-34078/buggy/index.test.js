'use strict';

const Chance = require('chance');
const { promisify: getMockExecAsync } = require('util');
const { join: mockJoin } = require('path');
const verifyDeps = require('.');

jest.mock('path', () => ({ join: jest.fn() }));
jest.mock('child_process', () => ({}));
jest.mock('util', () => {
  const mockExecAsync = jest.fn();
  return { promisify: () => mockExecAsync };
});
jest.mock('chalk', () => ({
  blue: (str) => str,
  bold: (str) => str,
  green: (str) => str,
  red: (str) => str
}));

const chance = new Chance();
const moduleNameRegexVersions = new RegExp('npm view (.*) versions --json');
const moduleNameRegexTags = new RegExp('npm view (.*) dist-tags --json');
const pathString = './index.test.js';
const mockExecAsync = getMockExecAsync();
const mockExports = {};

describe('lib/index', () => {
  const dir = chance.word();
  const outdatedDep = chance.word();
  const updatedDep = chance.word();
  const outdatedDevDep = chance.word();
  const updatedDevDep = chance.word();
  const logger = { info: jest.fn() };
  let olderVersion;
  let newerVersion;
  const mock = (command) => {
    const result = command.match(moduleNameRegexVersions) || command.match(moduleNameRegexTags);
    const moduleName = result[1];
    const versions = [olderVersion];
    if (moduleName === outdatedDep || moduleName === outdatedDevDep) versions.push(newerVersion);
    return Promise.resolve({ stdout: JSON.stringify(versions) });
  };
  beforeEach(() => {
    olderVersion = '1.0.0';
    newerVersion = '1.0.1';
    mockExports.version = olderVersion;
    mockExports.dependencies = {
      [outdatedDep]: `^${olderVersion}`,
      [updatedDep]: `^${olderVersion}`
    };
    mockExports.devDependencies = {
      [outdatedDevDep]: `^${olderVersion}`,
      [updatedDevDep]: `^${olderVersion}`
    };
    mockExecAsync.mockImplementation(mock);
    mockJoin.mockImplementation(() => pathString);
  });

  afterEach(() => {
    logger.info.mockClear();
    mockExecAsync.mockClear();
    mockJoin.mockClear();
  });

  it('should compare installed dependencies to latest NPM versions', async () => {
    delete mockExports.devDependencies;
    await expect(verifyDeps({ dir, logger })).rejects.toThrow(
      'Please update your installed modules.'
    );
    expect(mockExecAsync).toHaveBeenCalledWith(`npm view ${outdatedDep} versions --json`);
    expect(mockExecAsync).toHaveBeenCalledWith(`npm view ${updatedDep} versions --json`);
    expect(mockJoin).toHaveBeenCalledWith(dir, 'package.json');
    expect(mockJoin).toHaveBeenCalledWith(dir, 'node_modules', outdatedDep, 'package.json');
    expect(mockJoin).toHaveBeenCalledWith(dir, 'node_modules', updatedDep, 'package.json');
  });

  it('should compare installed devDpendencies to latest NPM versions', async () => {
    delete mockExports.dependencies;
    await expect(verifyDeps({ dir, logger })).rejects.toThrow(
      'Please update your installed modules.'
    );
    expect(mockExecAsync).toHaveBeenCalledWith(`npm view ${outdatedDevDep} versions --json`);
    expect(mockExecAsync).toHaveBeenCalledWith(`npm view ${updatedDevDep} versions --json`);
    expect(mockJoin).toHaveBeenCalledWith(dir, 'package.json');
    expect(mockJoin).toHaveBeenCalledWith(dir, 'node_modules', outdatedDevDep, 'package.json');
    expect(mockJoin).toHaveBeenCalledWith(dir, 'node_modules', updatedDevDep, 'package.json');
  });

  it('should show dependency update required when using semver and later version in range is available', async () => {
    await expect(verifyDeps({ dir, logger })).rejects.toThrow(
      'Please update your installed modules.'
    );
    expect(logger.info).toHaveBeenCalledTimes(5);
    expect(logger.info).toHaveBeenNthCalledWith(1, 'Verifying dependencies…\n');
    expect(logger.info).toHaveBeenNthCalledWith(
      2,
      `${outdatedDep} is outdated: ${olderVersion} → ${newerVersion}`
    );
    expect(logger.info).toHaveBeenNthCalledWith(
      3,
      `${outdatedDevDep} is outdated: ${olderVersion} → ${newerVersion}`
    );
    expect(logger.info).toHaveBeenNthCalledWith(4, `\n${'To resolve this, run:'}`);
    expect(logger.info).toHaveBeenNthCalledWith(
      5,
      `npm i ${outdatedDep}@${newerVersion} \nnpm i -D ${outdatedDevDep}@${newerVersion} `
    );
  });

  it('should not show dependency update required when using semver and later version is out of range', async () => {
    newerVersion = '2.0.0';
    await verifyDeps({ dir, logger });
    expect(logger.info).toHaveBeenCalledTimes(2);
    expect(logger.info).toHaveBeenNthCalledWith(1, 'Verifying dependencies…\n');
    expect(logger.info).toHaveBeenNthCalledWith(2, 'All NPM modules are up to date.');
  });

  it('should show dependency update required when version is locked if non-major-version update available', async () => {
    newerVersion = '1.1.0';
    mockExports.dependencies[outdatedDep] = olderVersion;
    mockExports.devDependencies[outdatedDevDep] = olderVersion;
    await expect(verifyDeps({ dir, logger })).rejects.toThrow(
      'Please update your installed modules.'
    );
    expect(logger.info).toHaveBeenCalledTimes(5);
    expect(logger.info).toHaveBeenNthCalledWith(1, 'Verifying dependencies…\n');
    expect(logger.info).toHaveBeenNthCalledWith(
      2,
      `${outdatedDep} is outdated: ${olderVersion} → ${newerVersion}`
    );
    expect(logger.info).toHaveBeenNthCalledWith(
      3,
      `${outdatedDevDep} is outdated: ${olderVersion} → ${newerVersion}`
    );
    expect(logger.info).toHaveBeenNthCalledWith(4, `\n${'To resolve this, run:'}`);
    expect(logger.info).toHaveBeenNthCalledWith(
      5,
      `npm i ${outdatedDep}@${newerVersion} \nnpm i -D ${outdatedDevDep}@${newerVersion} `
    );
  });

  it('should not show dependency update required when version is locked if only major-version update available', async () => {
    mockExports.dependencies[outdatedDep] = olderVersion;
    mockExports.devDependencies[outdatedDevDep] = olderVersion;
    newerVersion = '2.0.0';
    await verifyDeps({ dir, logger });
    expect(logger.info).toHaveBeenCalledTimes(2);
    expect(logger.info).toHaveBeenNthCalledWith(1, 'Verifying dependencies…\n');
    expect(logger.info).toHaveBeenCalledWith('All NPM modules are up to date.');
  });

  it('should show dependency install required if module cannot be found', async () => {
    mockJoin.mockImplementation((...args) => {
      if (args[2] === outdatedDep) throw new Error('module not found');
      return pathString;
    });

    await expect(verifyDeps({ dir, logger })).rejects.toThrow(
      'Please update your installed modules.'
    );
    expect(logger.info).toHaveBeenCalledTimes(5);
    expect(logger.info).toHaveBeenNthCalledWith(1, 'Verifying dependencies…\n');
    expect(logger.info).toHaveBeenNthCalledWith(2, `${outdatedDep} is ${'not installed'}`);
    expect(logger.info).toHaveBeenNthCalledWith(
      3,
      `${outdatedDevDep} is outdated: ${olderVersion} → ${newerVersion}`
    );
    expect(logger.info).toHaveBeenNthCalledWith(4, `\n${'To resolve this, run:'}`);
    expect(logger.info).toHaveBeenNthCalledWith(
      5,
      `npm i ${outdatedDep}@${newerVersion} \nnpm i -D ${outdatedDevDep}@${newerVersion} `
    );
  });

  it('should show dependency install required if fetching versions does not return valid JSON output', async () => {
    const invalidOutput = chance.word();
    mockExecAsync.mockImplementation(() => ({ stdout: invalidOutput }));
    let syntaxError;
    try {
      JSON.parse(invalidOutput);
    } catch (err) {
      syntaxError = err;
    }
    await expect(verifyDeps({ dir, logger })).rejects.toThrow(
      `Failed to parse output from NPM view - ${syntaxError.toString()}`
    );
  });

  it('should show dependency install required if fetching tags does not return valid JSON output', async () => {
    const invalidOutput = chance.word();
    mockExecAsync
      .mockImplementationOnce(() => ({ stdout: JSON.stringify(['1.1.1']) }))
      .mockImplementationOnce(() => ({ stdout: JSON.stringify(['1.1.1']) }))
      .mockImplementationOnce(() => ({ stdout: JSON.stringify(['1.1.1']) }))
      .mockImplementationOnce(() => ({ stdout: JSON.stringify(['1.1.1']) }));
    mockExecAsync.mockImplementation(() => ({ stdout: invalidOutput }));
    let syntaxError;
    try {
      JSON.parse(invalidOutput);
    } catch (err) {
      syntaxError = err;
    }
    await expect(verifyDeps({ dir, logger })).rejects.toThrow(
      `Failed to parse output from NPM view - ${syntaxError.toString()}`
    );
  });

  it('should show dependency install required if latest module is installed but not reflected in package.json', async () => {
    mockExports.dependencies[outdatedDep] = `^${newerVersion}`;
    mockExports.devDependencies[outdatedDevDep] = `^${newerVersion}`;

    await expect(verifyDeps({ dir, logger })).rejects.toThrow(
      'Please update your installed modules.'
    );

    expect(logger.info).toHaveBeenCalledTimes(5);
    expect(logger.info).toHaveBeenNthCalledWith(1, 'Verifying dependencies…\n');
    expect(logger.info).toHaveBeenNthCalledWith(
      2,
      `${outdatedDep} is outdated: ${newerVersion} → ${newerVersion}`
    );
    expect(logger.info).toHaveBeenNthCalledWith(
      3,
      `${outdatedDevDep} is outdated: ${newerVersion} → ${newerVersion}`
    );
    expect(logger.info).toHaveBeenNthCalledWith(4, `\n${'To resolve this, run:'}`);
    expect(logger.info).toHaveBeenNthCalledWith(
      5,
      `npm i ${outdatedDep}@${newerVersion} \nnpm i -D ${outdatedDevDep}@${newerVersion} `
    );
  });

  it('should not throw an error if no dependencies are in package.json', async () => {
    delete mockExports.dependencies;
    delete mockExports.devDependencies;
    await verifyDeps({ dir, logger });
    expect(logger.info).toHaveBeenCalledTimes(2);
    expect(logger.info).toHaveBeenNthCalledWith(1, 'Verifying dependencies…\n');
    expect(logger.info).toHaveBeenNthCalledWith(2, 'All NPM modules are up to date.');
  });

  it('should not throw an error if dependencies are empty in package.json', async () => {
    mockExports.dependencies = {};
    mockExports.devDependencies = {};
    await verifyDeps({ dir, logger });
    expect(logger.info).toHaveBeenCalledTimes(2);
    expect(logger.info).toHaveBeenNthCalledWith(1, 'Verifying dependencies…\n');
    expect(logger.info).toHaveBeenNthCalledWith(2, 'All NPM modules are up to date.');
  });

  it('should default to native console when no logger is passed', async () => {
    const consoleInfo = console.info;
    console.info = jest.fn();
    await expect(verifyDeps({ dir })).rejects.toThrow('Please update your installed modules.');
    expect(console.info).toHaveBeenCalledTimes(5);
    expect(console.info).toHaveBeenNthCalledWith(1, 'Verifying dependencies…\n');
    expect(console.info).toHaveBeenNthCalledWith(
      2,
      `${outdatedDep} is outdated: ${olderVersion} → ${newerVersion}`
    );
    expect(console.info).toHaveBeenNthCalledWith(
      3,
      `${outdatedDevDep} is outdated: ${olderVersion} → ${newerVersion}`
    );
    expect(console.info).toHaveBeenNthCalledWith(4, `\n${'To resolve this, run:'}`);
    expect(console.info).toHaveBeenNthCalledWith(
      5,
      `npm i ${outdatedDep}@${newerVersion} \nnpm i -D ${outdatedDevDep}@${newerVersion} `
    );
    console.info = consoleInfo;
  });

  it('should not throw type error if options are not passed', async () => {
    const consoleInfo = console.info;
    console.info = jest.fn();
    await expect(verifyDeps()).rejects.toThrow('Please update your installed modules.');
    console.info = consoleInfo;
  });

  it('should update to version aliased as latest when aliased latest is less that most recent published version', async () => {
    mockExports.dependencies = { foo1: '1.2.3' };
    mockExports.devDependencies = { fooDev1: '1.2.3' };

    mockExecAsync
      .mockImplementationOnce(() => Promise.resolve({ stdout: JSON.stringify(['1.2.4', '1.2.5']) }))
      .mockImplementationOnce(() => Promise.resolve({ stdout: JSON.stringify(['1.2.4', '1.2.5']) }))
      .mockImplementationOnce(() =>
        Promise.resolve({ stdout: JSON.stringify({ latest: '1.2.4' }) })
      )
      .mockImplementationOnce(() =>
        Promise.resolve({ stdout: JSON.stringify({ latest: '1.2.4' }) })
      )
      .mockImplementationOnce(() => Promise.resolve({ stdout: JSON.stringify(['1.2.4']) }))
      .mockImplementationOnce(() => Promise.resolve({ stdout: JSON.stringify(['1.2.4']) }));

    await verifyDeps({ autoUpgrade: true, dir, logger });

    expect(logger.info).toHaveBeenCalledTimes(7);
    expect(logger.info).toHaveBeenNthCalledWith(1, 'Verifying dependencies…\n');
    expect(logger.info).toHaveBeenNthCalledWith(2, `foo1 is outdated: 1.2.3 → 1.2.4`);
    expect(logger.info).toHaveBeenNthCalledWith(3, `fooDev1 is outdated: 1.2.3 → 1.2.4`);
    expect(logger.info).toHaveBeenNthCalledWith(4, 'UPGRADING…');
    expect(logger.info).toHaveBeenNthCalledWith(5, `npm i foo1@1.2.4 \nnpm i -D fooDev1@1.2.4 `);
    expect(logger.info).toHaveBeenNthCalledWith(6, `Upgraded dependencies:\n["1.2.4"]`);
    expect(logger.info).toHaveBeenNthCalledWith(7, `Upgraded development dependencies:\n["1.2.4"]`);
  });

  test('autoUpgrade modules', async () => {
    const mock2 = (command) => {
      const moduleName = command.match('npm i (.*)')[1];
      const versions = [olderVersion];
      if (moduleName === outdatedDep || moduleName === outdatedDevDep) versions.push(newerVersion);
      return Promise.resolve({ stdout: JSON.stringify(versions) });
    };

    mockExecAsync
      .mockImplementationOnce(mock)
      .mockImplementationOnce(mock)
      .mockImplementationOnce(mock)
      .mockImplementationOnce(mock)
      .mockImplementationOnce(mock)
      .mockImplementationOnce(mock)
      .mockImplementationOnce(mock)
      .mockImplementationOnce(mock)
      .mockImplementationOnce(mock2)
      .mockImplementationOnce(mock2);

    await verifyDeps({ autoUpgrade: true, dir, logger });
    expect(logger.info).toHaveBeenCalledTimes(7);
    expect(logger.info).toHaveBeenNthCalledWith(1, 'Verifying dependencies…\n');
    expect(logger.info).toHaveBeenNthCalledWith(
      2,
      `${outdatedDep} is outdated: ${olderVersion} → ${newerVersion}`
    );
    expect(logger.info).toHaveBeenNthCalledWith(
      3,
      `${outdatedDevDep} is outdated: ${olderVersion} → ${newerVersion}`
    );
    expect(logger.info).toHaveBeenNthCalledWith(4, 'UPGRADING…');
    expect(logger.info).toHaveBeenNthCalledWith(
      5,
      `npm i ${outdatedDep}@1.0.1 \nnpm i -D ${outdatedDevDep}@1.0.1 `
    );
    expect(logger.info).toHaveBeenNthCalledWith(6, `Upgraded dependencies:\n["1.0.0"]`);
    expect(logger.info).toHaveBeenNthCalledWith(7, `Upgraded development dependencies:\n["1.0.0"]`);
  });
});

module.exports = mockExports;

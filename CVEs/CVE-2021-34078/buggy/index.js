/**
 * @module lifion-verify-deps
 */

'use strict';

const { blue, bold, green, red } = require('chalk');
const path = require('path');
const semver = require('semver');
const { exec } = require('child_process');
const { promisify } = require('util');

const execAsync = promisify(exec);

async function getLatestVersions(name) {
  const { stdout } = await execAsync(`npm view ${name} versions --json`);
  try {
    return JSON.parse(stdout);
  } catch (err) {
    throw new Error(`Failed to parse output from NPM view - ${err.toString()}`);
  }
}

async function getLatestTag(name) {
  try {
    const { stdout } = await execAsync(`npm view ${name} dist-tags --json`);
    const { latest } = JSON.parse(stdout);
    return latest;
  } catch (err) {
    throw new Error(`Failed to parse output from NPM view - ${err.toString()}`);
  }
}

async function getLatestVersion(name, wanted) {
  const versions = await getLatestVersions(name);
  const latest = await getLatestTag(name);
  const applicableVersions = versions.filter((i) => semver.satisfies(i, wanted));
  applicableVersions.sort((a, b) => semver.rcompare(a, b));

  if (latest && semver.lt(latest, applicableVersions[0])) {
    return latest;
  }
  return applicableVersions[0];
}

function getInstalledVersion(currentDir, name) {
  try {
    return require(path.join(currentDir, 'node_modules', name, 'package.json')).version;
  } catch (err) {
    return null;
  }
}

function pushPkgs({ dir, logger, deps = {}, type, pkgs }) {
  return Object.keys(deps).map(async (name) => {
    let wanted = deps[name];
    if (!wanted.startsWith('^')) wanted = `^${wanted}`;
    const installed = getInstalledVersion(dir, name);
    const latest = await getLatestVersion(name, wanted);
    const wantedFixed = wanted.slice(1);
    const shouldBeInstalled =
      installed === null || wantedFixed !== installed || installed !== latest;
    if (shouldBeInstalled) {
      const warning =
        installed !== null
          ? `outdated: ${red(wantedFixed !== installed ? wantedFixed : installed)} → ${green(
              latest
            )}`
          : red('not installed');
      logger.info(`${red(name)} is ${warning}`);
    }
    pkgs.push({ installed, latest, name, shouldBeInstalled, type, wanted });
  });
}

function getPkgIds(filteredPkgs) {
  return filteredPkgs.map(({ latest, name }) => `${name}@${latest}`).join(' ');
}

/**
 * Verifies the dependencies listed in the package.json of the given directory.
 *
 * @alias module:lifion-verify-deps
 * @param {object} [options] - Optional parameters.
 * @param {boolean} [options.autoUpgrade=false] - Automatically upgrade all suggested dependencies.
 * @param {string} [options.dir] - The path where to look for the package.json file.
 * @param {object} [options.logger] - A logger instance, with a similar API as the console object.
 */
async function verifyDeps({ autoUpgrade = false, dir, logger = console } = {}) {
  const { dependencies, devDependencies } = require(path.join(dir, 'package.json'));
  logger.info(blue('Verifying dependencies…\n'));
  const pkgs = [];
  await Promise.all([
    ...pushPkgs({ deps: dependencies, dir, logger, pkgs, type: 'prod' }),
    ...pushPkgs({ deps: devDependencies, dir, logger, pkgs, type: 'dev' })
  ]);
  const toInstall = pkgs.filter(({ shouldBeInstalled }) => shouldBeInstalled);
  if (toInstall.length > 0) {
    const prodPkgs = toInstall.filter(({ type }) => type === 'prod');
    let upgradePackages = '';
    if (prodPkgs.length > 0) {
      upgradePackages = upgradePackages.concat(`npm i ${getPkgIds(prodPkgs)} `);
    }
    const devPkgs = toInstall.filter(({ type }) => type === 'dev');
    if (devPkgs.length > 0) {
      upgradePackages = upgradePackages.concat(`\nnpm i -D ${getPkgIds(devPkgs)} `);
    }

    if (autoUpgrade) {
      logger.info('UPGRADING…');
      logger.info(upgradePackages);
      const prodResult = await execAsync(`npm i ${getPkgIds(prodPkgs)}`);
      const devResult = await execAsync(`npm i -D ${getPkgIds(devPkgs)}`);
      logger.info(`${green(`${bold('Upgraded dependencies:\n')}${prodResult.stdout}`)}`);
      logger.info(`${green(`${bold('Upgraded development dependencies:\n')}${devResult.stdout}`)}`);
    } else {
      logger.info(`\n${bold('To resolve this, run:')}`);
      logger.info(upgradePackages);
      throw new Error(red('Please update your installed modules.'));
    }
  } else {
    logger.info(green('All NPM modules are up to date.'));
  }
}

module.exports = verifyDeps;

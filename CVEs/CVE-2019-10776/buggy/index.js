'use strict';

const path = require('path');
const { promisify } = require('util');
const tmpDir = promisify(require('tmp').dir);
const fs = require('fs-extra');
const uuidv1 = require('uuid/v1');
const debug = require('debug')('git-diff-apply');
const utils = require('./utils');
const getRootDir = require('./get-root-dir');
const getSubDir = require('./get-sub-dir');
const gitInit = require('./git-init');
const gitStatus = require('./git-status');
const commit = require('./commit');
const checkOutTag = require('./check-out-tag');
const convertToObj = require('./convert-to-obj');
const resolveConflicts = require('./resolve-conflicts');
const commitAndTag = require('./commit-and-tag');
const gitRemoveAll = require('./git-remove-all');
const createCustomRemote = require('./create-custom-remote');

const { isGitClean } = gitStatus;
const { gitConfigInit } = gitInit;

const tempBranchName = uuidv1();

async function ensureDir(dir) {
  debug('ensureDir', dir);
  await fs.ensureDir(dir);
}

module.exports = async function gitDiffApply({
  cwd = process.cwd(),
  remoteUrl,
  startTag,
  endTag,
  resolveConflicts: _resolveConflicts,
  ignoredFiles = [],
  reset,
  init,
  createCustomDiff,
  startCommand,
  endCommand
}) {
  let _tmpDir;
  let tmpWorkingDir;

  let hasConflicts;
  let returnObject;

  let isCodeUntracked;
  let isCodeModified;

  let root;

  let err;

  async function buildReturnObject() {
    let from;

    if (reset || init) {
      from = {};
    } else {
      await checkOutTag(startTag, { cwd: _tmpDir });

      from = convertToObj(_tmpDir, ignoredFiles);
    }

    await checkOutTag(endTag, { cwd: _tmpDir });

    let to = convertToObj(_tmpDir, ignoredFiles);

    return {
      from,
      to
    };
  }

  async function namespaceRepoWithSubDir(subDir) {
    let newTmpDir = await tmpDir();

    await gitInit({ cwd: newTmpDir });

    let newTmpSubDir = path.join(newTmpDir, subDir);

    async function copyToSubDir(tag) {
      await ensureDir(newTmpSubDir);

      await checkOutTag(tag, { cwd: _tmpDir });

      await utils.copy(_tmpDir, newTmpSubDir);

      await commitAndTag(tag, { cwd: newTmpDir });
    }

    if (!(reset || init)) {
      await copyToSubDir(startTag);

      await gitRemoveAll({ cwd: newTmpDir });
    }

    await copyToSubDir(endTag);

    _tmpDir = newTmpDir;
    tmpWorkingDir = newTmpSubDir;
  }

  async function copy() {
    await utils.copy(tmpWorkingDir, cwd);
  }

  async function resetIgnoredFiles(cwd) {
    for (let ignoredFile of ignoredFiles) {
      // An exist check is not good enough.
      // `git checkout` will fail unless it is also tracked.
      let isTracked = await utils.run(`git ls-files ${ignoredFile}`, { cwd });
      if (isTracked) {
        await utils.run(`git checkout -- ${ignoredFile}`, { cwd });
      } else {
        await fs.remove(path.join(cwd, ignoredFile));
      }
    }
  }

  async function createPatchFile() {
    let patchFile = path.join(await tmpDir(), 'file.patch');
    await utils.run(`git diff ${startTag} ${endTag} --binary > ${patchFile}`, { cwd: _tmpDir });
    if (await fs.readFile(patchFile, 'utf8') !== '') {
      return patchFile;
    }
  }

  async function applyPatch(patchFile) {
    // --whitespace=fix seems to prevent any unnecessary conflicts with line endings
    // https://stackoverflow.com/questions/6308625/how-to-avoid-git-apply-changing-line-endings#comment54419617_11189296
    await utils.run(`git apply --whitespace=fix ${patchFile}`, { cwd: _tmpDir });
  }

  async function go() {
    if (reset || init) {
      await checkOutTag(endTag, { cwd: _tmpDir });

      isCodeUntracked = true;
      isCodeModified = true;
      if (reset) {
        await utils.gitRemoveAll({ cwd: root });
      }

      await copy();

      await utils.run('git reset', { cwd });

      await resetIgnoredFiles(cwd);

      return;
    }

    await checkOutTag(startTag, { cwd: _tmpDir });

    await utils.run(`git branch ${tempBranchName}`, { cwd: _tmpDir });
    await utils.run(`git checkout ${tempBranchName}`, { cwd: _tmpDir });

    let patchFile = await createPatchFile();
    if (!patchFile) {
      return;
    }

    await applyPatch(patchFile);

    await resetIgnoredFiles(tmpWorkingDir);

    let wereAnyChanged = !await isGitClean({ cwd: _tmpDir });

    if (wereAnyChanged) {
      await commit({ cwd: _tmpDir });

      let sha = await utils.run('git rev-parse HEAD', { cwd: _tmpDir });

      await utils.run(`git remote add ${tempBranchName} ${_tmpDir}`, { cwd });
      await utils.run(`git fetch --no-tags ${tempBranchName}`, { cwd });

      try {
        await utils.run(`git cherry-pick --no-commit ${sha.trim()}`, { cwd });
      } catch (err) {
        hasConflicts = true;
      }

      await utils.run(`git remote remove ${tempBranchName}`, { cwd });
    }
  }

  try {
    if (startTag === endTag && !(reset || init)) {
      throw 'Tags match, nothing to apply';
    }

    let isClean;

    try {
      isClean = await isGitClean({ cwd });
    } catch (err) {
      throw 'Not a git repository';
    }

    if (!isClean) {
      throw 'You must start with a clean working directory';
    }

    if (createCustomDiff) {
      let tmpPath = await createCustomRemote({
        startCommand,
        endCommand,
        startTag,
        endTag,
        reset,
        init
      });

      remoteUrl = tmpPath;
    }

    _tmpDir = await tmpDir();
    tmpWorkingDir = _tmpDir;

    await utils.run(`git clone ${remoteUrl} ${_tmpDir}`);

    // needed because we are going to be committing in here
    await gitConfigInit({ cwd: _tmpDir });

    returnObject = await buildReturnObject();

    root = await getRootDir({ cwd });
    let subDir = await getSubDir({ cwd });
    if (subDir) {
      debug('subDir', subDir);

      await namespaceRepoWithSubDir(subDir);
    }

    await go();

    debug('success');
  } catch (_err) {
    err = _err;

    try {
      if (isCodeUntracked) {
        await utils.run('git clean -f', { cwd });
      }
      if (isCodeModified) {
        await utils.run('git reset --hard', { cwd });
      }
    } catch (err2) {
      throw {
        err,
        err2
      };
    }
  }

  if (err) {
    debug('failure');

    throw err;
  }

  if (hasConflicts && _resolveConflicts) {
    returnObject.resolveConflictsProcess = resolveConflicts({ cwd });
  }

  return returnObject;
};

module.exports.run = utils.run;
module.exports.gitInit = gitInit;
module.exports.gitStatus = gitStatus;
module.exports.isGitClean = isGitClean;
module.exports.gitRemoveAll = gitRemoveAll;

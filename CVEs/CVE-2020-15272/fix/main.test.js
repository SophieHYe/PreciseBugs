const core = require('@actions/core');
const child_process = require('child_process');

const main = require('../src/main.js');

jest.mock('@actions/core');
jest.mock('child_process');

beforeEach(() => {
  core.getInput.mockClear();
  core.setFailed.mockClear();
  core.setOutput.mockClear();

  child_process.exec.mockClear();
});

it.each([
  "v1.2.3",
  "v0.3.14",
])('uses the tag from the environment (%s)', (tag) => {
  process.env.GITHUB_REF = `refs/tags/${tag}`;

  main();

  expect(child_process.exec).toHaveBeenCalledWith(
    `git for-each-ref --format='%(contents)' 'refs/tags/${tag}'`,
    expect.any(Function),
  );
});

it('tries to get a tag from the input', () => {
  core.getInput.mockReturnValue(undefined);

  main();

  expect(core.getInput).toHaveBeenCalledTimes(1);
});

it.each([
  "v3.2.1",
  "v0.2.718",
])('uses the tag from the input (%s)', (tag) => {
  core.getInput.mockReturnValue(tag);

  main();

  expect(core.getInput).toHaveBeenCalledTimes(2);
  expect(child_process.exec).toHaveBeenCalledWith(
    `git for-each-ref --format='%(contents)' 'refs/tags/${tag}'`,
    expect.any(Function),
  );
});

it('outputs the annotation', (done) => {
  const annotation = "Hello world!";
  child_process.exec.mockImplementation((_, fn) => {
    fn(null, annotation);

    expect(core.setOutput).toHaveBeenCalledTimes(1);
    expect(core.setOutput).toHaveBeenCalledWith(
      'git-tag-annotation',
      annotation,
    );
    done();
  });

  main();
});

it('sets an error if the annotation could not be found', (done) => {
  child_process.exec.mockImplementation((_, fn) => {
    fn("Something went wrong!", null);

    expect(core.setOutput).not.toHaveBeenCalled();
    expect(core.setFailed).toHaveBeenCalledTimes(1);
    done();
  });

  main();
});

it('sets an error if exec fails', () => {
  child_process.exec.mockImplementation(() => {
    throw new Error({ message: "Something went wrong" })
  });

  main();

  expect(core.setOutput).not.toHaveBeenCalled();
  expect(core.setFailed).toHaveBeenCalledTimes(1);
});

it('escapes malicious values from the input', () => {
  core.getInput.mockReturnValue(`'; $(cat /etc/shadow)`);

  main();

  expect(child_process.exec).toHaveBeenCalledWith(
    "git for-each-ref --format='%(contents)' 'refs/tags/'\\''; $(cat /etc/shadow)'",
    expect.any(Function),
  );
});

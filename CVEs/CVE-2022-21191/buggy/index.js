"use strict";

// Linter rules, so mocha's methods will not be detected as undeclared.
/* global describe, it, afterEach, beforeEach */

const assert = require("chai").assert,
	path = require("path");

const index = require("../lib/index");

let processWrapper = require("../lib/process-wrapper");
let fs = require("fs"),
	childProcess = require("child_process");

const originalExecSync = childProcess.execSync;
const originalConsoleError = console.error;
const originalExistsSync = fs.existsSync;
const originalReadFileSync = fs.readFileSync;

describe("getPath", () => {

	afterEach(() => {
		processWrapper.getProcessPlatform = () => process.platform;
		childProcess.execSync = originalExecSync;
		console.error = originalConsoleError;
		fs.existsSync = originalExistsSync;
		fs.readFileSync = originalReadFileSync;
	});

	// platform independant tests. Execute them by simulating all supported platforms, but results should be the same.
	let supportedPlatforms = ["win32", "darwin", "linux"];

	supportedPlatforms.forEach(platform => {
		describe(`OS independant tests, on ${platform}`, () => {
			beforeEach(() => {
				processWrapper.getProcessPlatform = () => platform;
			});

			describe("works correctly when npm prefix is used", () => {
				it("does not throw error when getting npm prefix throws", () => {
					const expectedErrorMessage = "npm throws";
					let errors = [];
					console.error = (message) => errors.push(message);
					childProcess.execSync = (command) => {
						if (command.indexOf("get prefix") !== -1) {
							throw new Error(expectedErrorMessage);
						}
					};

					// Do not pass executable name here as we want to test only getting npm prefix.
					index.getPath("test1");
					assert.isTrue(errors.length === 1, "Only single error should be thrown.");
					assert.deepEqual(errors[0], expectedErrorMessage);
				});

				it("returns null when npm prefix returns value, but searched module is NOT installed there (and executable name is not passed)", () => {
					const npmConfigPrefix = "/npm/dir",
						packageName = "test1";

					fs.existsSync = () => false;

					childProcess.execSync = (command) => {
						if (command.indexOf("get prefix") !== -1) {
							return npmConfigPrefix;
						}
					};

					const resultPath = index.getPath(packageName);
					assert.deepEqual(resultPath, null);
				});
			});

			describe("works correctly when executableName is used", () => {
				it("returns null when where result fails", () => {
					const packageName = "test1",
						executableName = "test1.js";

					childProcess.execSync = () => null;

					const result = index.getPath(packageName, executableName);
					assert.deepEqual(result, null);
				});
			});
		});
	});

	const getCorrectResultFromNpmPrefix = (npmConfigPrefix, packageName) => {
		fs.existsSync = () => true;
		fs.readFileSync = () => JSON.stringify({ name: packageName });
		childProcess.execSync = (command) => {
			if (command.indexOf("get prefix") !== -1) {
				return npmConfigPrefix;
			}
		};

		return index.getPath(packageName);
	};

	// Platform specific tests
	describe("on windows", () => {
		beforeEach(() => {
			processWrapper.getProcessPlatform = () => "win32";
		});

		describe("works correctly when npm prefix is used", () => {
			it("uses npm.cmd for npm executable", () => {
				let commands = [];
				childProcess.execSync = (command) => {
					commands.push(command);
				};

				index.getPath("test1");
				assert.isTrue(commands.indexOf("npm.cmd config get prefix") !== -1);
			});

			it("returns path containing npm prefix when it returns correct result and the searched module is installed there", () => {
				const packageName = "test1",
					npmConfigPrefix = "/bin/test";

				const resultPath = getCorrectResultFromNpmPrefix(npmConfigPrefix, packageName);
				assert.deepEqual(resultPath, path.join(npmConfigPrefix, "node_modules", packageName));
			});

			it("returns path containing npm prefix when it returns correct result and the searched module is installed there (and executable name is not passed), when npm prefix is not trimmed ", () => {
				const packageName = "test1",
					npmConfigPrefix = "     /bin/test      ";

				const resultPath = getCorrectResultFromNpmPrefix(npmConfigPrefix, packageName);
				assert.deepEqual(resultPath, path.join(npmConfigPrefix.trim(), "node_modules", packageName));
			});
		});

		describe("works correctly when executableName is used", () => {
			it("returns null when where command throws", () => {
				const packageName = "test1",
					executableName = "test1.js",
					expectedErrorMessage = "Where error";

				let errors = [];
				console.error = (message) => errors.push(message);

				fs.existsSync = () => true;

				childProcess.execSync = (command) => {
					if (command.indexOf("where") !== -1) {
						throw new Error(expectedErrorMessage);
					}

					return null;
				};

				const result = index.getPath(packageName, executableName);
				assert.deepEqual(result, null);
				assert.deepEqual(errors.length, 1, "Only single error should be thrown.");
				assert.deepEqual(errors[0], expectedErrorMessage);
			});

			it("returns correct result when where result is correct", () => {
				const packageName = "test1",
					executableName = "test1.js",
					executableDirName = path.join("C:", "Users", "username", "AppData", "Roaming", "npm"),
					whereResult = path.join(executableDirName, executableName);

				fs.existsSync = () => true;

				childProcess.execSync = (command) => {
					if (command.indexOf("where") !== -1) {
						return whereResult;
					}

					return null;
				};

				fs.readFileSync = (filePath) => {
					if (filePath.indexOf("package.json") !== -1) {
						return JSON.stringify({
							"name": packageName
						});
					}

					return "";
				};

				const result = index.getPath(packageName, executableName);
				assert.deepEqual(result, path.join(executableDirName, "node_modules", packageName));
			});

			it("returns correct result when yarn is used on Windows", () => {
				const packageName = "test1",
					executableName = "test1.cmd",
					yarnDirPath = path.join("C:", "Users", "username", "AppData", "Roaming", "Local", "Yarn"),
					executableDirName = path.join(yarnDirPath, "bin"),
					whereResult = path.join(executableDirName, executableName);

				fs.existsSync = (pathToCheck) => pathToCheck !== path.join(executableDirName, "node_modules", packageName);

				childProcess.execSync = (command) => {
					if (command.indexOf("where") !== -1) {
						return whereResult;
					}

					return null;
				};

				fs.readFileSync = (filePath) => {
					if (filePath.indexOf("package.json") !== -1) {
						return JSON.stringify({
							"name": packageName
						});
					}

					if (path.basename(filePath) === executableName) {
						return "@\"%~dp0\\..\\Data\\global\\node_modules\\.bin\\test1.cmd\"   %*";
					}

					return "";
				};

				const result = index.getPath(packageName, executableName);
				const expectedData = process.platform === "win32" ?
					path.join(yarnDirPath, "Data", "global", "node_modules", packageName) :
					path.join(yarnDirPath, "bin\\..\\Data\\global\\node_modules", packageName);

				assert.deepEqual(path.normalize(result), expectedData);
			});

			it("returns correct result when where result is correct, and package is added to PATH via a .bin dir", () => {
				const packageName = "test1",
					executableName = "test1.cmd",
					nodeModulesDirName = path.join("C:", "Users", "username", "someDir", "node_modules"),
					executableDirName = path.join(nodeModulesDirName, ".bin"),
					whereResult = path.join(executableDirName, executableName);

				fs.existsSync = (pathToCheck) => pathToCheck !== path.join(executableDirName, "node_modules", packageName);

				childProcess.execSync = (command) => {
					if (command.indexOf("where") !== -1) {
						return whereResult;
					}

					return null;
				};

				fs.readFileSync = (filePath) => {
					if (filePath.indexOf("package.json") !== -1) {
						return JSON.stringify({
							"name": packageName
						});
					}

					if (path.basename(filePath) === executableName) {
						return "@\"%~dp0\\..\\test1\\bin\\test1\" %*";
					}

					return "";
				};

				const result = index.getPath(packageName, executableName);

				const expectedData = process.platform === "win32" ?
					path.join(path.dirname(executableDirName), packageName) :
					path.join(nodeModulesDirName, ".bin\\..\\test1");

				assert.deepEqual(result, expectedData);
			});

			it("returns correct result when where result is correct, and package is added to PATH via its bin dir", () => {
				const packageName = "test1",
					executableName = "test1.js",
					executableDirName = path.join("C:", "Users", "username", "nativescript-cli", "bin"),
					whereResult = path.join(executableDirName, executableName);

				fs.existsSync = (filePath) => {
					return filePath.indexOf("package.json") !== -1;
				};

				fs.readFileSync = (filePath) => {
					if (filePath.indexOf("package.json") !== -1) {
						return JSON.stringify({
							"name": packageName
						});
					}

					return "";
				};

				childProcess.execSync = (command) => {
					if (command.indexOf("where") !== -1) {
						return whereResult;
					}

					return null;
				};

				const result = index.getPath(packageName, executableName);
				assert.deepEqual(result, path.join(path.dirname(executableDirName)));
			});

			it("returns null when package is added to PATH via its bin dir, but the name in package.json is incorrect", () => {
				const packageName = "test1",
					executableName = "test1.js",
					executableDirName = path.join("C:", "Users", "username", "nativescript-cli", "bin"),
					whereResult = path.join(executableDirName, executableName);

				fs.existsSync = (filePath) => {
					return filePath.indexOf("package.json") !== -1;
				};

				fs.readFileSync = (filePath) => {
					if (filePath.indexOf("package.json") !== -1) {
						return JSON.stringify({
							"name": "invalidName"
						});
					}

					return "";
				};

				childProcess.execSync = (command) => {
					if (command.indexOf("where") !== -1) {
						return whereResult;
					}

					return null;
				};

				const result = index.getPath(packageName, executableName);
				assert.deepEqual(result, null);
			});

			it("returns correct result when where result returns multiple lines correct", () => {
				const packageName = "test1",
					executableName = "test1.js",
					executableDirName = path.join("C:", "Users", "username", "AppData", "Roaming", "npm"),
					invalidName = "invalidName",
					invalidLineOfWhereResult = path.join(executableDirName, invalidName, invalidName, executableName),
					whereResult = invalidLineOfWhereResult + "\n" + invalidLineOfWhereResult + "\r\n" + path.join(executableDirName, executableName);

				fs.existsSync = (filePath) => {
					if (filePath && filePath.indexOf(invalidName) !== -1) {
						return false;
					}

					return true;
				};

				fs.readFileSync = (filePath) => {
					if (filePath.indexOf("package.json") !== -1) {
						return JSON.stringify({
							"name": packageName
						});
					}

					return "";
				};

				childProcess.execSync = (command) => {
					if (command.indexOf("where") !== -1) {
						return whereResult;
					}

					return null;
				};

				const result = index.getPath(packageName, executableName);
				assert.deepEqual(result, path.join(executableDirName, "node_modules", packageName));
			});
		});
	});

	// Remove win32 from supported platforms. Tests below are not for Windows.
	supportedPlatforms.shift();

	supportedPlatforms.forEach(platform => {

		describe(`on ${platform}`, () => {
			beforeEach(() => {
				processWrapper.getProcessPlatform = () => platform;
			});

			it("uses npm for npm executable", () => {
				let commands = [];
				childProcess.execSync = (command) => {
					commands.push(command);
				};

				index.getPath("test1");

				assert.isTrue(commands.indexOf("npm config get prefix") !== -1);
			});

			it("returns path containing npm prefix when it returns correct result and the searched module is installed there (and executable name is not passed)", () => {
				const packageName = "test1",
					npmConfigPrefix = "/bin/test";

				const resultPath = getCorrectResultFromNpmPrefix(npmConfigPrefix, packageName);
				assert.deepEqual(resultPath, path.join(npmConfigPrefix, "lib", "node_modules", packageName));
			});

			it("returns path containing npm prefix when it returns correct result and the searched module is installed there (and executable name is not passed), when npm prefix is not trimmed ", () => {
				const packageName = "test1",
					npmConfigPrefix = "    /bin/test       ";

				const resultPath = getCorrectResultFromNpmPrefix(npmConfigPrefix, packageName);
				assert.deepEqual(resultPath, path.join(npmConfigPrefix.trim(), "lib", "node_modules", packageName));
			});

			describe("works correctly when executableName is used", () => {
				it("returns null when which command throws", () => {
					const packageName = "test1",
						executableName = "test1.js",
						expectedErrorMessage = "Which error";

					let errors = [];
					console.error = (message) => errors.push(message);

					fs.existsSync = () => true;

					childProcess.execSync = (command) => {
						if (command.indexOf("which") !== -1) {
							throw new Error(expectedErrorMessage);
						}

						return null;
					};

					const result = index.getPath(packageName, executableName);
					assert.deepEqual(result, null);
					assert.deepEqual(errors.length, 1, "Only single error should be thrown.");
					assert.deepEqual(errors[0], expectedErrorMessage);
				});

				it("returns null when ls -l command throws", () => {
					const packageName = "test1",
						executableName = "test1.js",
						expectedErrorMessage = "ls -l error";

					let errors = [];
					console.error = (message) => errors.push(message);

					fs.existsSync = () => true;

					childProcess.execSync = (command) => {
						if (command.indexOf("ls -l") !== -1) {
							throw new Error(expectedErrorMessage);
						}

						return null;
					};

					const result = index.getPath(packageName, executableName);
					assert.deepEqual(result, null);
					assert.deepEqual(errors.length, 1, "Only single error should be thrown.");
					assert.deepEqual(errors[0], expectedErrorMessage);
				});

				if (process.platform === "win32") {
					console.log("Some tests cannot be executed on Windows. PR will execute them on Linux, so don't worry.");
				} else {

					const constructData = (packageName, executableName, lsLResult, whichResult, readFileResult) => {
						fs.existsSync = () => true;

						fs.readFileSync = (filePath) => {
							if (readFileResult !== undefined) {
								return readFileResult;
							}

							if (filePath.indexOf("package.json") !== -1) {
								return JSON.stringify({
									"name": packageName
								});
							}

							return "";
						};

						fs.realpathSync = (filePath) => {
							return filePath;
						};

						childProcess.execSync = (command) => {

							if (command.indexOf("ls -l") !== -1) {
								return lsLResult;
							}

							if (command.indexOf("which") !== -1) {
								return whichResult;
							}

							return null;
						};
					};

					it("returns correct result when which and ls -l results are correct", () => {
						const packageName = "test1",
							executableName = "test1.js",
							executableDirName = path.join("/usr", "local", "node", "bin"),
							whichResult = path.join(executableDirName, executableName),
							lsLResult = `lrwxrwxrwx 1 rvladimirov rvladimirov 52 Oct 20 14:51 ${whichResult} -> ${path.join("..", "lib", "node_modules", packageName, "bin", executableName)}`;

						constructData(packageName, executableName, lsLResult, whichResult);

						const result = index.getPath(packageName, executableName);
						assert.deepEqual(result, path.join(executableDirName, "..", "lib", "node_modules", packageName));
					});

					it("returns null when ls -l result is not the searched one", () => {
						const packageName = "test1",
							executableName = "test1.js",
							executableDirName = path.join("/usr", "local", "node", "bin"),
							whichResult = path.join(executableDirName, executableName),
							lsLResult = `lrwxrwxrwx 1 rvladimirov rvladimirov 52 Oct 20 14:51 incorrect ls -l -> ${path.join("..", "lib", "node_modules", packageName, "bin", executableName)}`;

						constructData(packageName, executableName, lsLResult);

						const result = index.getPath(packageName, executableName, whichResult);
						assert.deepEqual(result, null);
					});

					it("returns null when ls -l result is not the searched one (no node_modules in it)", () => {
						const packageName = "test1",
							executableName = "test1.js",
							executableDirName = path.join("/usr", "local", "node", "bin"),
							whichResult = path.join(executableDirName, executableName),
							lsLResult = `lrwxrwxrwx 1 rvladimirov rvladimirov 52 Oct 20 14:51 ${whichResult} -> incorrect`,
							readFileResult = "{}";

						constructData(packageName, executableName, lsLResult, whichResult, readFileResult);

						const result = index.getPath(packageName, executableName);
						assert.deepEqual(result, null);
					});

					it("returns correct result when which result is correct, and package is added to PATH via its bin dir", () => {
						const packageName = "test1",
							executableName = "test1.js",
							executableDirName = path.join("/usr", "username", "repository_name", "bin"),
							whichResult = path.join(executableDirName, executableName),
							lsLResult = `lrwxrwxrwx 1 rvladimirov rvladimirov 52 Oct 20 14:51 ${whichResult} -> incorrect`;

						fs.existsSync = (filePath) => {
							return filePath.indexOf("package.json") !== -1;
						};

						childProcess.execSync = (command) => {

							if (command.indexOf("ls -l") !== -1) {
								return lsLResult;
							}

							if (command.indexOf("which") !== -1) {
								return whichResult;
							}

							return null;
						};

						fs.readFileSync = (filePath) => {
							if (filePath.indexOf("package.json") !== -1) {
								return JSON.stringify({
									"name": packageName
								});
							}

							return "";
						};

						const result = index.getPath(packageName, executableName);
						assert.deepEqual(result, path.dirname(executableDirName));
					});

					it("returns null when package is added to PATH via its bin dir, but the name in package.json is incorrect", () => {
						const packageName = "test1",
							executableName = "test1.js",
							executableDirName = path.join("/usr", "username", "repository_name", "bin"),
							whichResult = path.join(executableDirName, executableName),
							lsLResult = `lrwxrwxrwx 1 rvladimirov rvladimirov 52 Oct 20 14:51 ${whichResult} -> incorrect`;

						fs.existsSync = (filePath) => {
							return filePath.indexOf("package.json") !== -1;
						};

						childProcess.execSync = (command) => {

							if (command.indexOf("ls -l") !== -1) {
								return lsLResult;
							}

							if (command.indexOf("which") !== -1) {
								return whichResult;
							}

							return null;
						};

						fs.readFileSync = (filePath) => {
							if (filePath.indexOf("package.json") !== -1) {
								return JSON.stringify({
									"name": "invalid name"
								});
							}

							return "";
						};

						const result = index.getPath(packageName, executableName);
						assert.deepEqual(result, null);
					});
				}
			});
		});
	});

	it("throws error when process.platform is not valid", () => {
		require("../lib/process-wrapper").getProcessPlatform = () => "1";
		assert.throws(() => index.getPath("test1", "test1"), "OS '1' is not supported");
	});

});

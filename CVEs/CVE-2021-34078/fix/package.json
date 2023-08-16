{
  "name": "lifion-verify-deps",
  "version": "1.1.0",
  "description": "Verifies that installed NPM modules are the latest currently available version.",
  "keywords": [
    "check",
    "dependencies",
    "installed",
    "lifion",
    "update",
    "upgrade",
    "verify",
    "version"
  ],
  "author": "Mackenzie Turner <turner.mackenzie.m@gmail.com>",
  "maintainers": [
    "Chen Doron <Chen.Doron@ADP.com>",
    "Edgardo Avilés <Edgardo.Aviles@ADP.com>",
    "Jenny Eckstein <Jenny.Eckstein@ADP.com>",
    "Zaid Masud <Zaid.Masud@ADP.com>"
  ],
  "contributors": [],
  "license": "MIT",
  "repository": {
    "type": "git",
    "url": "https://github.com/lifion/lifion-verify-deps.git"
  },
  "bugs": {
    "url": "https://github.com/lifion/lifion-verify-deps/issues"
  },
  "homepage": "https://github.com/lifion/lifion-verify-deps#readme",
  "main": "./lib/index.js",
  "bin": {
    "lifion-verify-deps": "./bin/lifion-verify-deps.js"
  },
  "engines": {
    "node": ">=8.6.0"
  },
  "scripts": {
    "build-docs": "jsdoc2md -t ./templates/README.hbs ./lib/*.js > ./README.md && git add ./README.md",
    "build-docs-watch": "npm-watch build-docs",
    "eslint": "eslint . --ext .js,.json --ignore-pattern='!.*.*'",
    "format": "prettier --write '**/*.{md,js,json}' '!coverage/**/*.{js,json}'",
    "prepare": "check-engines",
    "test": "jest -c ./.jest.json",
    "version": "auto-changelog -p && git add CHANGELOG.md"
  },
  "dependencies": {
    "chalk": "^3.0.0",
    "minimist": "^1.2.5",
    "semver": "^7.3.4",
    "validate-npm-package-name": "^3.0.0"
  },
  "devDependencies": {
    "auto-changelog": "^1.16.4",
    "chance": "^1.1.7",
    "check-engines": "^1.5.0",
    "codecov": "^3.8.1",
    "eslint": "^6.8.0",
    "eslint-config-lifion": "^1.4.0",
    "husky": "^4.3.8",
    "jest": "^25.5.4",
    "jsdoc-to-markdown": "^5.0.3",
    "lint-staged": "^10.5.4",
    "npm-watch": "^0.6.0",
    "prettier": "^2.2.1"
  },
  "husky": {
    "hooks": {
      "pre-commit": "npm run build-docs && lint-staged",
      "pre-push": "npm run eslint && npm test"
    }
  },
  "watch": {
    "build-docs": {
      "patterns": [
        "lib"
      ]
    }
  },
  "@lifion/core-commons": {
    "template": "public",
    "updated": "2019-11-11T20:44:29.068Z",
    "version": "2.3.4"
  }
}

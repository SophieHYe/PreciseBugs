{
  "name": "bmoor",
  "version": "0.10.0",
  "author": "Brian Heilman <das.ist.junk@gmail.com>",
  "description": "A basic foundation for other libraries, establishing useful patterbs, and letting them be more.",
  "license": "MIT",
  "repository": {
    "type": "git",
    "url": "git://github.com/b-heilman/bmoor.git"
  },
  "main": "src/index.js",
  "dependencies": {
    "uuid": "^3.4.0"
  },
  "devDependencies": {
    "@typescript-eslint/eslint-plugin": "^5.0.0",
    "@typescript-eslint/parser": "^5.0.0",
    "chai": "^4.2.0",
    "eslint": "^8.0.0",
    "eslint-config-prettier": "^8.3.0",
    "eslint-plugin-prettier": "^4.0.0",
    "jshint": "^2.13.1",
    "jshint-stylish": "2.1.0",
    "mocha": "^9.1.2",
    "prettier": "2.4.1",
    "sinon": "^7.5.0",
    "typescript": "^4.4.4",
    "yargs": "^15.3.1"
  },
  "scripts": {
    "lint": "node ./node_modules/eslint/bin/eslint ./src",
    "test": "npm run prettier && mocha --recursive \"./src/**/*.spec.js\"",
    "prettier": "npx prettier --write ./src && npm run lint"
  }
}

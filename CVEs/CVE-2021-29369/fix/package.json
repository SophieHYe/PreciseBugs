{
  "name": "@rkesters/gnuplot",
  "private": false,
  "version": "0.0.2",
  "description": "Node gnuplot",
  "main": "dist/index.js",
  "types": "dist/index.d.ts",
  "files": [
    "dist/",
    "register/",
    "LICENSE"
  ],
  "scripts": {
    "lint": "tslint \"src/**/*.ts\" --project tsconfig.json",
    "build": "rm -rf dist && tsc",
    "dev": "ts-node src/dev",
    "clean-test": "rm -rf src/test && mkdir src/test",
    "test-spec": "npm run clean-test && mocha src/test.ts",
    "test": "npm run test-spec",
    "prepare": "npm run build",
    "prepublishOnly": "npm run build && npm test"
  },
  "engines": {
    "node": ">=12.19.0"
  },
  "repository": {
    "type": "git",
    "url": "git://github.com/rkesters/gnuplot.git"
  },
  "keywords": [
    "gnuplot",
    "stoqey"
  ],
  "author": {
    "name": "Robert Kesterson",
    "email": "robert.d.kesterson@gmail.c"
  },
  "license": "MIT",
  "bugs": {
    "url": "https://github.com/rkesters/gnuplot/issues"
  },
  "homepage": "https://github.com/rkesters/gnuplot",
  "devDependencies": {
    "@types/chai": "^4.2.11",
    "@types/lodash": "^4.14.144",
    "@types/mocha": "^7.0.2",
    "@types/node": "^10.0.3",
    "@types/source-map-support": "^0.4.0",
    "chai": "^4.2.0",
    "mocha": "^7.1.1",
    "ts-node": "^8.4.1",
    "tslint": "^5.11.0",
    "tslint-config-standard": "^8.0.1",
    "typescript": "^4.0.5"
  },
  "dependencies": {
    "filenamify": "^4.2.0",
    "lodash": "^4.17.15"
  }
}

{
  "name": "putil-merge",
  "description": "Lightweight solution for merging multiple objects into one. Also it supports deep merge and deep clone",
  "version": "3.7.0",
  "author": "Panates Ltd.",
  "contributors": [
    "Eray Hanoglu <e.hanoglu@panates.com>"
  ],
  "license": "MIT",
  "repository": {
    "type": "git",
    "url": "https://github.com/panates/putil-merge.git"
  },
  "main": "lib/merge.js",
  "types": "lib/merge",
  "keywords": [
    "javascript",
    "merge",
    "object"
  ],
  "devDependencies": {
    "eslint": "^7.19.0",
    "eslint-config-google": "^0.14.0",
    "mocha": "^9.0.2",
    "nyc": "^15.1.0"
  },
  "engines": {
    "node": ">= 10.0"
  },
  "directories": {
    "lib": "./lib"
  },
  "files": [
    "LICENSE",
    "README.md",
    "lib/"
  ],
  "nyc": {
    "temp-dir": "./coverage/.nyc_output"
  },
  "scripts": {
    "test": "mocha --require ./test/support/env --reporter spec --bail --check-leaks test/",
    "cover": "nyc --reporter html --reporter text npm run test",
    "travis-cover": "nyc --reporter lcovonly npm run test"
  }
}

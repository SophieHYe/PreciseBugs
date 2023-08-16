{
	"name": "git-interface",
	"version": "2.1.2",
	"description": "some interfaces for work with git repository",
	"main": "dist/index",
	"typings": "dist/index",
	"scripts": {
		"clean": "rm -rf ./dist",
		"build": "npm run clean && npm run ts",
		"dev": "npm run clean &&  ./node_modules/.bin/tsc -w",
		"ts": "./node_modules/.bin/tsc",
		"prepublishOnly": "npm run build && bump"
	},
	"repository": {
		"type": "git",
		"url": "git://github.com/yarkeev/git-interface.git"
	},
	"keywords": [
		"git",
		"hash",
		"pull",
		"push",
		"commit",
		"last changes",
		"checkout",
		"merge",
		"conflicts",
		"cvs"
	],
	"author": "Yarkeev Denis <denis.yarkeev@gmail.com>",
	"license": "MIT",
	"bugs": {
		"url": "https://github.com/yarkeev/git-interface/issues"
	},
	"homepage": "https://github.com/yarkeev/git-interface",
	"devDependencies": {
		"@types/node": "^10.10.3",
		"typescript": "^3.0.3",
		"version-bump-prompt": "^6.1.0"
	}
}

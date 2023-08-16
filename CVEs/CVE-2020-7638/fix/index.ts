// See README.md for details

import * as confinit from "../index";
import * as path from "path";

export class Section1Config implements confinit.IConfigSection {
	url: string = "";

	validate(): void {
		if (!this.url) {
			throw new Error("Section 1 url not set.");
		}
	}
}

export class WebServerConfig implements confinit.IConfigSection {
	port = 3000;

	constructor() {
		const envPort = process.env.PORT;
		if (envPort) {
			this.port = parseInt(envPort, 10);
		}
	}

	validate(): void {
		if (!this.port) {
			throw new Error("Invalid port");
		}
		this.port = parseInt(this.port.toString(), 10);
	}
}

export class Configuration {
	readonly section1 = new Section1Config();
	readonly webServer = new WebServerConfig();

	constructor(env?: NodeJS.ProcessEnv) {
		if (!env) {
			env = process.env;
		}

		// Enable config file
		if (env.config) {
			const configFile = path.resolve(process.cwd(), env.config);
			confinit.applyConfigFile(this, configFile);
		}
		// Enable environment variables
		confinit.applyEnvVariables(this, process.env, "cfg_");
		// Enable command arguments
		confinit.applyCommandArgs(this, process.argv);

		confinit.validate(this);
	}
}

console.log("Sample configuration");
const config = new Configuration();

console.log("---------------");
console.log("section1:");
console.log("---------------");
console.log(config.section1);
console.log("---------------");
console.log("webServer:");
console.log("---------------");
console.log(config.webServer);

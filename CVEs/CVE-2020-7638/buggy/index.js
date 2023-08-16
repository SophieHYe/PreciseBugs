"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
const confinit = require("../index");
const path = require("path");
class Section1Config {
    constructor() {
        this.url = "";
    }
    validate() {
        if (!this.url) {
            throw new Error("Section 1 url not set.");
        }
    }
}
exports.Section1Config = Section1Config;
class WebServerConfig {
    constructor() {
        this.port = 3000;
        const envPort = process.env.PORT;
        if (envPort) {
            this.port = parseInt(envPort, 10);
        }
    }
    validate() {
        if (!this.port) {
            throw new Error("Invalid port");
        }
        this.port = parseInt(this.port.toString(), 10);
    }
}
exports.WebServerConfig = WebServerConfig;
class Configuration {
    constructor(env) {
        this.section1 = new Section1Config();
        this.webServer = new WebServerConfig();
        if (!env) {
            env = process.env;
        }
        if (env.config) {
            const configFile = path.resolve(process.cwd(), env.config);
            confinit.applyConfigFile(this, configFile);
        }
        confinit.applyEnvVariables(this, process.env, "cfg_");
        confinit.applyCommandArgs(this, process.argv);
        confinit.validate(this);
    }
}
exports.Configuration = Configuration;
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
//# sourceMappingURL=index.js.map
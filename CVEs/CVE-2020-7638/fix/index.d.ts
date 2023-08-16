/// <reference types="node" />
export interface IConfigSection {
    validate(): void;
}
export declare function validate(configuration: any): void;
export declare function applyEnvVariables(configuration: any, envVariables: NodeJS.ProcessEnv, envPrefix?: string): void;
export declare function applyConfigFile(configuration: any, configFile: string): void;
export declare function applyCommandArgs(configuration: any, argv: string[]): void;
export declare function setDeepProperty(obj: {
    [key: string]: any;
}, propertyPath: string, value: any): void;
export declare function getDeepProperty(obj: any, propertyPath: string): any;
export declare function objectsAreEqual(obj1: any, obj2: any, leftOnly?: boolean): boolean;

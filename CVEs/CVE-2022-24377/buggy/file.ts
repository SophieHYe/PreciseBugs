import { Extension, ScanResult, ReportVO, PackageJson, FileImportDescription } from "./type";
import { sync } from "glob";
import { join as pathJoin, dirname, join, normalize, relative } from "path";
import { join as arrayJoin, map, isArray, isString, concat, filter, keys } from "@newdash/newdash";
import { includes } from "@newdash/newdash/includes"
import { readFileSync, writeFileSync, existsSync } from "fs";
import { cwd } from "process";
import { tmpdir, platform } from "os";
import { exec } from "child_process";

require.extensions[".ts"] = require.extensions[".js"]
require.extensions[".jsx"] = require.extensions[".js"]
require.extensions[".tsx"] = require.extensions[".js"]
require.extensions[".mjs"] = require.extensions[".js"]

const extensions = [
  "js", "jsx", "ts", "tsx", "mjs"
]

const { resolve } = require

export const allDependencies = (absPath: string) => {
  return concatAllDependencies(findProjectPackageJson(absPath))
}

export const concatAllDependencies = (json: PackageJson): string[] => {
  try {
    const { dependencies, devDependencies, peerDependencies } = json;
    var rt = [];
    if (dependencies) {
      rt = concat(rt, keys(dependencies))
    }
    if (devDependencies) {
      rt = concat(rt, keys(devDependencies))
    }
    if (peerDependencies) {
      rt = concat(rt, keys(peerDependencies))
    }
    return rt;
  } catch (error) {
    throw new Error("please run cycle-import-check in npm project (with project.json)")
  }

}

export const findProjectPackageJson = (absPath: string): PackageJson => {
  const finder = require("find-package-json")(absPath)
  return finder.next().value;
}

export const filterNodeDependenciesImport = (descriptions: FileImportDescription[], dependencies: string[]) => {
  // @ts-ignore
  return filter(descriptions, i => !includes(dependencies, i.importFile))
}

/**
 * list all acceptable files in a specific directory
 *
 * @param dir
 * @param ext
 */
export const listAllFile = (dir: string, ext: Extension[] = []) => {
  return sync(pathJoin(dir, `./**/*.{${arrayJoin(ext, ",")}}`), {
    realpath: true,
    ignore: [
      "**/node_modules/**"
    ]
  })
}

/**
 * read file content
 *
 * @param absolutePath
 */
export const readFile = (absolutePath: string) => {
  return readFileSync(absolutePath, { encoding: "utf8" })
}

/**
 * will throw error if file not exist
 *
 * @param fromFileAbsolutePath
 * @param importFileRelativePath
 */
export const resolveFilePath = (fromFileAbsolutePath: string, importFileRelativePath: string) => {
  const dir = dirname(fromFileAbsolutePath);
  const targetPath = join(dir, importFileRelativePath);
  // to do replace nodejs resolve function
  try {
    return normalize(resolve(targetPath));
  } catch (error) {
    // can not resolve import file
    return ""
  }
}

/**
 * map absolute path to relative path
 *
 * @param paths
 */
export const mapAbsPathsToRelPaths = (paths: string | string[]): string | string[] => {
  if (isString(paths)) {
    return relative(cwd(), paths)
  }
  if (isArray(paths)) {
    return map(paths, p => relative(cwd(), p))
  }
}

export const mapScanResultToReportVO = (result: ScanResult): ReportVO => {
  var rt: ReportVO = { nodes: [], links: [] }
  rt.nodes = map(result.nodes, n => ({ name: (mapAbsPathsToRelPaths(n) as string) }))
  rt.links = map(result.imports, i => ({
    source: (mapAbsPathsToRelPaths(i.fromFile) as string),
    target: (mapAbsPathsToRelPaths(i.importFile) as string),
    value: i.code,
  }))
  return rt;
}

export const writeFileToTmpDirAndOpenIt = (filename: string, content: string) => {
  const path = join(tmpdir(), filename);
  writeFileSync(path, content);
  if (platform() === "win32") {
    exec(`start ${path}`)
  } else {
    exec(`open ${path}`)
  }
}

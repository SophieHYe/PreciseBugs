import { concat, filter, isArray, isString, join as arrayJoin, keys, map } from "@newdash/newdash";
import { includes } from "@newdash/newdash/includes";
import { readFileSync } from "fs";
import { sync } from "glob";
import { dirname, join, join as pathJoin, normalize, relative } from "path";
import { cwd } from "process";
import { Extension, FileImportDescription, PackageJson, ReportVO, ScanResult } from "./type";

require.extensions[".ts"] = require.extensions[".js"]
require.extensions[".jsx"] = require.extensions[".js"]
require.extensions[".tsx"] = require.extensions[".js"]
require.extensions[".mjs"] = require.extensions[".js"]

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


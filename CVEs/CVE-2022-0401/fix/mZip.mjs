import path from 'path'
import fs from 'fs'
import archiver from 'archiver'
import archiverEnc from 'archiver-zip-encrypted'
import unzipper from 'unzipper'
import get from 'lodash/get'
import genPm from 'wsemi/src/genPm.mjs'
import pmMap from 'wsemi/src/pmMap.mjs'
import getFileName from 'wsemi/src/getFileName.mjs'
import checkTarget from './checkTarget.mjs'


//registerFormat
archiver.registerFormat('zip-encrypted', archiverEnc)


/**
 * Zip處理
 *
 * @class
 * @returns {Object} 回傳壓縮物件，可使用函數zipFile、zipFolder、unzip
 * @example
 * import wz from 'w-zip'
 *
 * let fpUnzip = './testData/outputZip'
 * let fpUnzipExtract = fpUnzip + '/extract'
 *
 * let fpSrc1 = './testData/input/file1(中文).txt'
 * let fpZip1 = fpUnzip + '/test1.zip'
 *
 * let fpSrc2 = './testData/input/folder1'
 * let fpZip2 = fpUnzip + '/test2.zip'
 * let fpZip2PW = fpUnzip + '/test2PW.zip'
 * let pw = 'abc'
 *
 * async function test() {
 *
 *     //zipFile
 *     console.log('zipFile before')
 *     console.log('zipFile', await wz.mZip.zipFile(fpSrc1, fpZip1))
 *     console.log('zipFile after')
 *
 *     //zipFolder
 *     console.log('zipFolder before')
 *     console.log('zipFolder', await wz.mZip.zipFolder(fpSrc2, fpZip2))
 *     console.log('zipFolder after')
 *
 *     //zipFolder with password
 *     console.log('zipFolder with password before')
 *     console.log('zipFolder with password', await wz.mZip.zipFolder(fpSrc2, fpZip2PW, { pw }))
 *     console.log('zipFolder with password after')
 *
 *     //unzip
 *     console.log('unzip1 before')
 *     console.log('unzip1', await wz.mZip.unzip(fpZip1, fpUnzipExtract + '/test1'))
 *     console.log('unzip1 after')
 *
 *     //unzip
 *     console.log('unzip2 before')
 *     console.log('unzip2', await wz.mZip.unzip(fpZip2, fpUnzipExtract + '/test2'))
 *     console.log('unzip2 after')
 *
 *     //unzip with password
 *     console.log('unzip2 with password before')
 *     console.log('unzip2 with password', await wz.mZip.unzip(fpZip2PW, fpUnzipExtract + '/test2PW', { pw }))
 *     console.log('unzip2 with password after')
 *
 * }
 * test()
 *     .catch((err) => {
 *         console.log(err)
 *     })
 *
 * // zipFile before
 * // zipFile done: ./testData/outputZip/test1.zip
 * // zipFile after
 * // zipFolder before
 * // zipFolder done: ./testData/outputZip/test2.zip
 * // zipFolder after
 * // zipFolder with password before
 * // zipFolder with password done: ./testData/outputZip/test2PW.zip
 * // zipFolder with password after
 * // unzip1 before
 * // unzip1 done: ./testData/outputZip/extract/test1
 * // unzip1 after
 * // unzip2 before
 * // unzip2 done: ./testData/outputZip/extract/test2
 * // unzip2 after
 * // unzip2 with password before
 * // unzip2 with password done: ./testData/outputZip/extract/test2PW
 * // unzip2 with password after
 */
function mZip() {


    /**
     * 壓縮檔案
     *
     * @memberof mZip
     * @param {String} fpSrc 輸入壓縮來源檔案位置字串
     * @param {String} fpTar 輸入壓縮目標檔案位置字串
     * @param {Object} [opt={}] 輸入設定物件，預設{}
     * @param {Integer} [opt.level] 輸入壓縮程度整數，範圍為0至9，0為不壓縮而9為最高壓縮，預設9
     * @param {String} [opt.pw] 輸入壓縮密碼字串，預設''
     * @returns {Promise} 回傳Promise，resolve為成功資訊，reject為失敗資訊
     */
    async function zipFile(fpSrc, fpTar, opt = {}) {

        //check
        if (!fs.existsSync(fpSrc)) {
            return Promise.reject('invalid path of source file')
        }
        if (!fs.lstatSync(fpSrc).isFile()) {
            return Promise.reject('path of source is not file')
        }

        //check fpTar
        if (!checkTarget(fpTar)) {
            return Promise.reject('invalid fpSrc')
        }

        //default
        let level = get(opt, 'level', 9)
        let pw = get(opt, 'pw', '')

        try {

            //pm
            let pm = genPm()

            //check
            if (!fs.existsSync(path.dirname(fpTar))) {
                fs.mkdirSync(path.dirname(fpTar), { recursive: true })
            }

            //archiver
            let output = fs.createWriteStream(fpTar)
            let archive
            if (pw === '') {
                archive = archiver('zip', {
                    zlib: { level },
                })
            }
            else {
                archive = archiver('zip-encrypted', {
                    zlib: { level },
                    encryptionMethod: 'zip20', //只能用zip20, 因unzipper目前不支援aes256
                    password: pw,
                })
            }

            output.on('close', function() {
                //console.log(archive.pointer() + ' total bytes')

                //resolve
                pm.resolve('done: ' + fpTar)

            })
            archive.on('warning', function(warn) {
                //console.log('warning', warn)
            })
            archive.on('error', function(err) {
                Promise.reject(err)
            })

            //pipe
            archive.pipe(output)

            //file
            archive.file(fpSrc, { name: path.basename(fpSrc) })

            //finalize
            archive.finalize()

            return pm
        }
        catch (err) {
            return Promise.reject(err)
        }
    }


    /**
     * 壓縮資料夾
     *
     * @memberof mZip
     * @param {String} fpSrc 輸入壓縮來源資料夾位置字串
     * @param {String} fpTar 輸入壓縮目標資料夾位置字串
     * @param {Integer} level 輸入壓縮程度整數，範圍為0至9，0為不壓縮而9為最高壓縮，預設9
     * @returns {Promise} 回傳Promise，resolve為成功資訊，reject為失敗資訊
     */
    async function zipFolder(fpSrc, fpTar, opt = {}) {

        //check
        if (!fs.existsSync(fpSrc)) {
            return Promise.reject('invalid path of source file')
        }
        if (!fs.lstatSync(fpSrc).isDirectory()) {
            return Promise.reject('path of source is not folder')
        }

        //check fpTar
        if (!checkTarget(fpTar)) {
            return Promise.reject('invalid fpSrc')
        }

        //default
        let level = get(opt, 'level', 9)
        let pw = get(opt, 'pw', '')

        try {

            //pm
            let pm = genPm()

            //check
            if (!fs.existsSync(path.dirname(fpTar))) {
                fs.mkdirSync(path.dirname(fpTar), { recursive: true })
            }

            //archiver
            let output = fs.createWriteStream(fpTar)
            let archive
            if (pw === '') {
                archive = archiver('zip', {
                    zlib: { level },
                })
            }
            else {
                archive = archiver('zip-encrypted', {
                    zlib: { level },
                    encryptionMethod: 'zip20', //只能用zip20, 因unzipper目前不支援aes256
                    password: pw,
                })
            }

            output.on('close', function() {
                //console.log(archive.pointer() + ' total bytes')

                //resolve
                pm.resolve('done: ' + fpTar)

            })
            archive.on('warning', function(warn) {
                //console.log('warning', warn)
            })
            archive.on('error', function(err) {
                Promise.reject(err)
            })

            //pipe
            archive.pipe(output)

            //directory
            archive.directory(fpSrc, path.basename(fpSrc))

            //finalize
            archive.finalize()

            return pm
        }
        catch (err) {
            return Promise.reject(err)
        }
    }


    /**
     * 解壓縮檔案至資料夾
     *
     * @memberof mZip
     * @param {String} fpSrc 輸入解壓縮來源檔案位置字串
     * @param {String} fpTar 輸入解壓縮目標資料夾位置字串
     * @param {Object} [opt={}] 輸入設定物件，預設{}
     * @param {String} [opt.pw=''] 輸入解壓縮密碼字串，預設''
     * @returns {Promise} 回傳Promise，resolve為成功資訊，reject為失敗資訊
     */
    async function unzip(fpSrc, fpTar, opt = {}) {

        //check
        if (!fs.existsSync(fpSrc)) {
            return Promise.reject('invalid path of source file')
        }
        if (!fs.lstatSync(fpSrc).isFile()) {
            return Promise.reject('path of source is not file')
        }

        //check fpTar
        if (!checkTarget(fpTar)) {
            return Promise.reject('invalid fpSrc')
        }

        //default
        let pw = get(opt, 'pw', '')

        //extract
        async function extract(fpSrc, fpTar, password) {

            //mkdir
            async function mkdir(fd) {
                try {

                    //check
                    if (!fs.existsSync(fd)) {
                        fs.mkdirSync(fd, { recursive: true })
                    }

                }
                catch (err) {
                    return Promise.reject(err)
                }
                return 'done'
            }

            //extractFile
            async function extractFile(file, fn, password = '') {
                try {

                    //check
                    if (!fs.existsSync(path.dirname(fn))) {
                        fs.mkdirSync(path.dirname(fn), { recursive: true })
                    }

                    //buffer
                    let b = await file.buffer(password)

                    //writeFileSync
                    fs.writeFileSync(fn, b)

                }
                catch (err) {
                    return Promise.reject(err)
                }
                return 'done'
            }

            //directory
            let directory = await unzipper.Open.file(fpSrc)

            //pmMap
            return pmMap(directory.files, async (file) => {
                let p = path.join(fpTar, file.path)
                if (file.type === 'File') {
                    return extractFile(file, p, password)
                }
                else {
                    return mkdir(p)
                }
            })

        }

        try {

            //extract
            await extract(fpSrc, fpTar, pw)

            return Promise.resolve('done: ' + getFileName(fpTar))
        }
        catch (err) {
            return Promise.reject(err)
        }
    }


    return {
        zipFile,
        zipFolder,
        unzip,
    }
}


export default mZip()

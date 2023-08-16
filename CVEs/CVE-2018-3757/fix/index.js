// node-pdf

var Promise = require("es6-promise").Promise;

var path = require("path");
var fs   = require("fs");
var util = require("util");
var spawn = require("child-process-promise").spawn;

function PDFImage(pdfFilePath, options) {
  if (!options) options = {};

  this.pdfFilePath = pdfFilePath;

  this.setPdfFileBaseName(options.pdfFileBaseName);
  this.setConvertOptions(options.convertOptions);
  this.setConvertExtension(options.convertExtension);
  this.useGM = options.graphicsMagick || false;
  this.combinedImage = options.combinedImage || false;

  this.outputDirectory = options.outputDirectory || path.dirname(pdfFilePath);
}

PDFImage.prototype = {
  constructGetInfoCommand: function () {
    return {
      cmd: "pdfinfo",
      args: [this.pdfFilePath]
    };
  },
  parseGetInfoCommandOutput: function (output) {
    var info = {};
    output.split("\n").forEach(function (line) {
      if (line.match(/^(.*?):[ \t]*(.*)$/)) {
        info[RegExp.$1] = RegExp.$2;
      }
    });
    return info;
  },
  getInfo: function () {
    var self = this;
    var getInfoCommand = this.constructGetInfoCommand();
    return new Promise(function (resolve, reject) {
      spawn(getInfoCommand.cmd, getInfoCommand.args, { capture: [ 'stdout', 'stderr' ]})
        .then(function (cmdResult) {
          resolve(self.parseGetInfoCommandOutput(cmdResult.stdout.toString()));
        }).catch(reject);
    });
  },
  numberOfPages: function () {
    return this.getInfo().then(function (info) {
      return info["Pages"];
    });
  },
  getOutputImagePathForPage: function (pageNumber) {
    return path.join(
      this.outputDirectory,
      this.pdfFileBaseName + "-" + pageNumber + "." + this.convertExtension
    );
  },
  getOutputImagePathForFile: function () {
    return path.join(
      this.outputDirectory,
      this.pdfFileBaseName + "." + this.convertExtension
    );
  },
  setConvertOptions: function (convertOptions) {
    this.convertOptions = convertOptions || {};
  },
  setPdfFileBaseName: function(pdfFileBaseName) {
    this.pdfFileBaseName = pdfFileBaseName || path.basename(this.pdfFilePath, ".pdf");
  },
  setConvertExtension: function (convertExtension) {
    this.convertExtension = convertExtension || "png";
  },
  constructConvertCommandForPage: function (pageNumber) {
    var pdfFilePath = this.pdfFilePath;
    var outputImagePath = this.getOutputImagePathForPage(pageNumber);
    var convertOptions = this.constructConvertOptions();
    var args = [];
    if (convertOptions) args = convertOptions.slice();
    args.push(pdfFilePath+"["+pageNumber+"]");
    args.push(outputImagePath);

    return {
      cmd: this.useGM ? "gm convert" : "convert",
      args: args
    };
  },
  constructCombineCommandForFile: function (imagePaths) {
    var args = imagePaths.slice();
    args.push(this.getOutputImagePathForFile());
    args.unshift("-append");
    return {
      cmd: this.useGM ? "gm convert" : "convert",
      args: args
    };
  },
  constructConvertOptions: function () {
    var convertOptions = [];
    Object.keys(this.convertOptions).sort().map(function (optionName) {
      if (this.convertOptions[optionName] !== null) {
        convertOptions.push(optionName);
        convertOptions.push(this.convertOptions[optionName]);
      } else {
        convertOptions.push(optionName);
      }
    }, this);
    return convertOptions;
  },
  combineImages: function(imagePaths) {
    var pdfImage = this;
    var combineCommand = pdfImage.constructCombineCommandForFile(imagePaths);
    return new Promise(function (resolve, reject) {
      spawn(combineCommand.cmd, combineCommand.args, { capture: [ 'stdout', 'stderr' ]})
        .then(function () {
          spawn("rm", imagePaths); //cleanUp
          resolve(pdfImage.getOutputImagePathForFile());
        }).catch(function(error){
          reject({
            message: "Failed to combine images",
            error: error.message,
            stdout: error.stdout,
            stderr: error.stderr
          });
      });
    });
  },
  convertFile: function () {
    var pdfImage = this;
    return new Promise(function (resolve, reject) {
      pdfImage.numberOfPages().then(function (totalPages) {
        var convertPromise = new Promise(function (resolve, reject){
          var imagePaths = [];
          for (var i = 0; i < totalPages; i++) {
            pdfImage.convertPage(i).then(function(imagePath){
              imagePaths.push(imagePath);
              if (imagePaths.length === parseInt(totalPages)){
                imagePaths.sort(); //because of asyc pages we have to reSort pages
                resolve(imagePaths);
              }
            }).catch(function(error){
              reject(error);
            });
          }
        });

        convertPromise.then(function(imagePaths){
          if (pdfImage.combinedImage){
            pdfImage.combineImages(imagePaths).then(function(imagePath){
              resolve(imagePath);
            });
          } else {
            resolve(imagePaths);
          }
        }).catch(function(error){
          reject(error);
        });
      });
    });
  },
  convertPage: function (pageNumber) {
    var pdfFilePath     = this.pdfFilePath;
    var outputImagePath = this.getOutputImagePathForPage(pageNumber);
    var convertCommand  = this.constructConvertCommandForPage(pageNumber);

    var promise = new Promise(function (resolve, reject) {
      function convertPageToImage() {
        return new Promise(function (resolve, reject) {
          spawn(convertCommand.cmd, convertCommand.args, { capture: [ 'stdout', 'stderr' ]})
            .then(function () {
              resolve(outputImagePath);
            }).catch(function(error){
            reject({
              message: "Failed to convert page to image",
              error: error.message,
              stdout: error.stdout,
              stderr: error.stderr
            });
          });
        });
      }

      fs.stat(outputImagePath, function (err, imageFileStat) {
        var imageNotExists = err && err.code === "ENOENT";
        if (!imageNotExists && err) {
          return reject({
            message: "Failed to stat image file",
            error: err
          });
        }

        // convert when (1) image doesn't exits or (2) image exists
        // but its timestamp is older than pdf's one

        if (imageNotExists) {
          // (1)
          convertPageToImage().then(function(result){
            resolve(result);
          }).catch(reject);
          return;
        }

        // image exist. check timestamp.
        fs.stat(pdfFilePath, function (err, pdfFileStat) {
          if (err) {
            return reject({
              message: "Failed to stat PDF file",
              error: err
            });
          }

          if (imageFileStat.mtime < pdfFileStat.mtime) {
            // (2)
            convertPageToImage().then(function(result){
              resolve(result);
            }).catch(reject);
          }
        });
      });
    });
    return promise;
  }
};

exports.PDFImage = PDFImage;

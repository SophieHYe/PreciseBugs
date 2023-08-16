let expect = require("chai").expect;
let fs     = require("fs");

let PDFImage = require("../").PDFImage;

describe("PDFImage", function () {
  let pdfPath = "/tmp/test.pdf";
  let pdfImage;
  let generatedFiles = [];
  this.timeout(7000);

  before(function(done){
    fs.createReadStream('tests/test.pdf').pipe(fs.createWriteStream(pdfPath));
    if (fs.existsSync(pdfPath)){
      done();
    } else {
      throw new Error({
        message: 'File missing at: '+ pdfPath + '. Copy task was not a success'
      });
    }
  });

  beforeEach(function() {
     pdfImage = new PDFImage(pdfPath)
  });

  it("should have correct basename", function () {
    expect(pdfImage.pdfFileBaseName).equal("test");
  });
  
  it("should set custom basename", function() {
    pdfImage.setPdfFileBaseName('custom-basename');
    expect(pdfImage.pdfFileBaseName).equal("custom-basename");
  });

  it("should return correct page path", function () {
    expect(pdfImage.getOutputImagePathForPage(1))
      .equal("/tmp/test-1.png");
    expect(pdfImage.getOutputImagePathForPage(2))
      .equal("/tmp/test-2.png");
    expect(pdfImage.getOutputImagePathForPage(1000))
      .equal("/tmp/test-1000.png");
    expect(pdfImage.getOutputImagePathForFile())
      .equal("/tmp/test.png");
  });

  it("should return correct convert command", function () {
    var convertCommand = pdfImage.constructConvertCommandForPage(1);
    expect(convertCommand.cmd).equal("convert");
    expect(convertCommand.args.length).equal(2);
  });

  it("should return correct convert command to combine images", function () {
    var cmdConfig = pdfImage.constructCombineCommandForFile(['/tmp/test-0.png', '/tmp/test-1.png']);
    expect(cmdConfig.cmd).equal('convert');
    expect(cmdConfig.args.length).equal(4);
  });

  it("should use gm when you ask it to", function () {
    pdfImage = new PDFImage(pdfPath, {graphicsMagick: true});
    var cmdConfig = pdfImage.constructConvertCommandForPage(1);
    expect(cmdConfig.cmd).equal('gm convert');
    expect(cmdConfig.args.length).equal(2);
  });

  // TODO: Do page updating test
  it("should convert PDF's page to a file with the default extension", function () {
    return new Promise(function(resolve, reject) {
      pdfImage.convertPage(1).then(function (imagePath) {
        expect(imagePath).equal("/tmp/test-1.png");
        expect(fs.existsSync(imagePath)).to.be.true;
        generatedFiles.push(imagePath);
        resolve();
      }).catch(function(err){
        reject(err);
      });
    });
  });

  it("should convert PDF's page 10 to a file with the default extension", function () {
    return new Promise(function(resolve, reject){
      pdfImage.convertPage(9).then(function (imagePath) {
        expect(imagePath).equal("/tmp/test-9.png");
        expect(fs.existsSync(imagePath)).to.be.true;
        generatedFiles.push(imagePath);
        resolve();
      }).catch(function(err){
        reject(err);
      });
    })
  });

  it("should convert PDF's page to file with a specified extension", function () {
    return new Promise(function(resolve, reject) {
      pdfImage.setConvertExtension("jpeg");
      pdfImage.convertPage(1).then(function (imagePath) {
        expect(imagePath).equal("/tmp/test-1.jpeg");
        expect(fs.existsSync(imagePath)).to.be.true;
        generatedFiles.push(imagePath);
        resolve();
      }).catch(function(err){
        reject(err);
      });
    });
  });

  it("should convert all PDF's pages to files", function () {
    return new Promise(function(resolve, reject) {
      pdfImage.convertFile().then(function (imagePaths) {
        imagePaths.forEach(function(imagePath){
          expect(fs.existsSync(imagePath)).to.be.true;
          generatedFiles.push(imagePath);
        });
        resolve();
      }).catch(function(err){
        reject(err);
      });
    });
  });

  it("should convert all PDF's pages to single image", function () {
    return new Promise(function(resolve, reject){
      let pdfImageCombined = new PDFImage(pdfPath, {
        combinedImage: true,
      });

      pdfImageCombined.convertFile().then(function (imagePath) {
        expect(imagePath).to.equal("/tmp/test.png");
        expect(fs.existsSync(imagePath)).to.be.true;
        generatedFiles.push(imagePath);
        resolve();
      }).catch(function (error) {
        reject(error);
      });
    })
  });

  it("should return # of pages", function () {
    return new Promise(function(resolve, reject) {
      pdfImage.numberOfPages().then(function (numberOfPages) {
        expect(parseInt(numberOfPages)).to.be.equal(10);
        resolve();
      }).catch(function(err){
        reject(err);
      });
    });
  });

  it("should construct convert options correctly", function () {
    pdfImage.setConvertOptions({
      "-density": 300,
      "-trim": null
    });
    expect(pdfImage.constructConvertOptions()[0]).equal("-density 300");
    expect(pdfImage.constructConvertOptions()[1]).equal("-trim");
  });

  it("should convert all PDF's pages with convertOptions", function () {
    return new Promise(function(resolve, reject){
      pdfImage.setConvertOptions({
        "-quality": 100,
        "-trim": null
      });

      pdfImage.convertFile().then(function (images) {
        images.forEach(function(image){
          expect(fs.existsSync(image)).to.be.true;
        });
        generatedFiles = images;
        resolve();
      }).catch(function (error) {
        reject(error.message + " " + error.stderr);
      });
    })
  });

  afterEach(function(done){
    //cleanUp files generated during test
    let i = generatedFiles.length;
    if (i > 0 ){
      generatedFiles.forEach(function(filepath, index){
        fs.unlink(filepath, function(err) {
          i--;
          if (err) {
            done(err);
          } else if (i <= 0) {
            done();
          }
        });
      });
      generatedFiles = []; //clear after delete
    } else {
      done();
    }
  });

  after(function(done){
    //finaly - remove test.pdf from /tmp/
    fs.unlink(pdfPath, function(err) {
      if (err) {
        done(err);
      }
      done();
    });
  });
});

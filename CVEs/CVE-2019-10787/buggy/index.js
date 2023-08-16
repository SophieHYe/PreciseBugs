var exec = require('child_process').exec;
var aspect = require('aspectratio');
var dirname = require('path').dirname;
var basename = require('path').basename;
var extname = require('path').extname;
var join = require('path').join;
var sprintf = require('util').format;

module.exports = function(image, output, cb) {
  var cmd = module.exports.cmd(image, output);
  exec(cmd, {timeout: 30000}, function(e, stdout, stderr) {
    if (e) { return cb(e); }
    if (stderr) { return cb(new Error(stderr)); }

    return cb(null, output.versions);
  });
};

/**
 * Get cropped geometry for given aspectratio
 *
 * @param object image - original image metadata
 * @param string ratio - new aspect ratio
 *
 * @return object geometry
 *  - string geometry - crop geometry; or null
 *  - number width    - image version height
 *  - number height   - image version width
 */
module.exports.crop = function(image, ratio) {
  if (!ratio) {
    return { geometry: null, width: image.width, height: image.height };
  }

  var g = aspect.crop(image.width, image.height, ratio);

  // Check if the image already has the decired aspectratio.
  if (g[0] === 0 && g[1] === 0) {
    return { geometry: null, width: image.width, height: image.height };
  } else {
    return {
      geometry: sprintf('%dx%d+%d+%d', g[2], g[3], g[0], g[1]),
      width: g[2],
      height: g[3]
    };
  }
};

/**
 * Get resize geometry for max width and/or height
 *
 * @param object crop - image crop object
 * @param object versin - image version object
 *
 * @return string geometry; null if no resize applies
 */
module.exports.resize = function(crop, version) {
  var maxW = version.maxWidth;
  var maxH = version.maxHeight;

  var resize = aspect.resize(crop.width, crop.height, maxW, maxH);

  // Update version object
  version.width  = resize[0];
  version.height = resize[1];

  if (maxW && maxH) {
    return maxW + 'x' + maxH;
  } else if (maxW) {
    return '' + maxW;
  } else if (maxH) {
    return 'x' + maxH;
  } else {
    return null;
  }
};

/**
 * Get new path with suffix
 *
 * @param string src - source image path
 * @param string opts - output path transformations
 *  * format
 *  * suffix
 *
 * @return string path
 */
module.exports.path = function(src, opts) {
  var dir = opts.path || dirname(src);
  var ext = extname(src);
  var base = basename(src, ext);

  if (opts.format) {
    ext = '.' + opts.format;
  }

  return join(dir, opts.prefix + base + opts.suffix + ext);
};

/**
 * Get convert command
 *
 * @param object image - original image object
 * @param Array versions - derivated versions
 *
 * @return string convert command
 */
module.exports.cmd = function(image, output) {
  var cmd = [
    sprintf(
      'convert %s -auto-orient -strip -write mpr:%s +delete', image.path, image.path
    )
  ];

  for (var i = 0; i < output.versions.length; i++) {
    var version = output.versions[i];
    var last = (i === output.versions.length-1);

    version.quality = version.quality || output.quality || 80;

    version.path = module.exports.path(image.path, {
      format: version.format,
      path: output.path,
      prefix: version.prefix || output.prefix || '',
      suffix: version.suffix || ''
    });

    cmd.push(module.exports.cmdVersion(image, version, last));
  }

  return cmd.join(' ');
};

/**
 * Get convert command for single version
 *
 * @param object image - original image object
 * @param object version - derivated version
 * @patam boolean last - true if this is last version
 *
 * @return string version convert command
 */
module.exports.cmdVersion = function(image, version, last) {
  var cmd = [];

  // http://www.imagemagick.org/Usage/files/#mpr
  cmd.push(sprintf('mpr:%s', image.path));

  // -quality
  if (version.quality) {
    cmd.push(sprintf('-quality %d', version.quality));
  }

  // -background
  if (version.background) {
    cmd.push(sprintf('-background "%s"', version.background));
  }

  // -flatten
  if (version.flatten) {
    cmd.push('-flatten');
  }

  // -crop
  var crop = module.exports.crop(image, version.aspect);
  if (crop.geometry) {
    cmd.push(sprintf('-crop "%s"', crop.geometry));
  }

  // -resize
  // http://www.imagemagick.org/script/command-line-processing.php#geometry
  var resize = module.exports.resize(crop, version);
  if (resize) {
    cmd.push(sprintf('-resize "%s"', resize));
  }

  // -write
  if (last) {
    cmd.push(version.path);
  } else {
    cmd.push(sprintf('-write %s +delete', version.path));
  }

  return cmd.join(' ');
};

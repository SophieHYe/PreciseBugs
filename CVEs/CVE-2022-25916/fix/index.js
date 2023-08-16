var exec = require('child_process').exec;

var defaultInterface = 'ra0',
  freqs = [
    '2.412',
    '2.417',
    '2.422',
    '2.427',
    '2.432',
    '2.437',
    '2.442',
    '2.447',
    '2.452',
    '2.457',
    '2.462',
  ];

var wiscan = {};

wiscan.scan = function (intf, callback) {
  var child;

  if (typeof intf === 'function') {
    callback = intf;
    intf = defaultInterface;
  }

  intf = intf || defaultInterface;
  callback = callback || function () {};

  if (typeof intf !== 'string')
    return callback(new Error('intf should be a string.'));

  if (!/[0-9a-zA-Z]/.test(intf.charAt(0)))
    return callback(new Error('Bad intf.'));

  child = exec('iwinfo ' + intf + ' scan', function (error, stdout, stderr) {
    if (error) {
      stderr = stderr.trim();
      return callback(new Error(stderr));
    }

    var info = stdout,
      parsed = [];

    info = info.replace(/\n/g, ' ');
    info = info.replace(/"/g, '');
    info = info.split(' ');
    info.forEach(function (char, i) {
      if (char === 'ESSID' && info[i + 1] === '') info[i + 1] = 'unknown';
      else if (char !== '') parsed.push(char);
    });

    parsed = parse(parsed);
    callback(null, parsed);
  });
};

wiscan.scanByEssid = function (intf, essid, callback) {
  var target = null;

  if (arguments.length === 2) {
    callback = essid;
    essid = intf;
    intf = defaultInterface;
  }

  intf = intf || defaultInterface;

  if (typeof intf !== 'string')
    return callback(new Error('intf should be a string.'));
  else if (typeof essid !== 'string')
    return callback(new Error('essid should be a string.'));

  callback = callback || function () {};

  wiscan.scan(intf, function (err, infos) {
    if (err) return callback(err);

    infos.forEach(function (info) {
      if (info.essid === essid) target = info;
    });

    callback(null, target);
  });
};

wiscan.lqi = function (intf, essid, callback) {
  if (arguments.length === 2) {
    callback = essid;
    essid = intf;
    intf = defaultInterface;
  }

  intf = intf || defaultInterface;

  wiscan.scanByEssid(intf, essid, function (err, info) {
    if (err) return callback(err);

    if (info) callback(null, info.quality);
    else callback(null, null);
  });
};

function parse(items) {
  var parsed = [],
    len = items.length,
    idx = 0;

  if (items.length === 0) return parsed;

  items.forEach(function (c, i) {
    var val;
    if (c === 'Cell') {
      //- [deleted: need not cell field] val = items[i+1];
      //- [deleted: need not cell field] val = isNaN(parseInt(val)) ? val : parseInt(val);
      parsed.push({});
    } else if (c === 'Address:') {
      parsed[idx].address = items[i + 1];
    } else if (c === 'ESSID:') {
      parsed[idx].essid = items[i + 1];
    } else if (c === 'Mode:') {
      parsed[idx].mode = items[i + 1];
    } else if (c === 'Channel:') {
      val = items[i + 1];
      val = isNaN(parseInt(val)) ? val : parseInt(val);
      parsed[idx].channel = val;
      parsed[idx].frequency = getFrequency(val);
    } else if (c === 'Signal:') {
      val = items[i + 1];
      val = isNaN(parseInt(val)) ? val : parseInt(val);
      parsed[idx].signal = val;
    } else if (c === 'Quality:') {
      val = items[i + 1].split('/')[0];
      val = isNaN(parseInt(val)) ? val : parseInt(val);
      parsed[idx].quality = val;
    } else if (c === 'Encryption:') {
      var x = i + 1,
        enc = '';

      while (items[x] !== 'Cell') {
        if (x !== len) {
          enc = enc + items[x] + ' ';
          x += 1;
        } else {
          break;
        }
      }
      enc = enc.trim();
      parsed[idx].encryption = enc;
      idx += 1;
    }
  });
  return parsed;
}

function getFrequency(ch) {
  var f = freqs[ch - 1];

  if (f) f = f + ' GHz';
  else f = '';

  return f;
}

module.exports = wiscan;

# mt7688-wiscan

## Table of Contents

1. [Overiew](#Overiew)
2. [Installation](#Installation)
3. [Usage](#Usage)

<a name="Overiew"></a>

## 1. Overview

**mt7688-wiscan** is a wifi access points scanning tool which is running with node.js on MediaTek Linkit Smart 7688. This tool provides three APIs [lqi()](#API_lqi), [scan()](#API_scan), and [scanByEssid()](#API_scanByEssid) that help you with getting link quality indicator (LQI) to an specific AP, scanning neighbor wifi APs, and scanning a specific AP with its **essid**.

I am using this tool on machine nodes in my LWM2M IoT project. On each machine, there is a panel to show some information about the machine and to show LQI between machine and its router as well.

<a name="Installation"></a>

## 2. Installation

> $ npm install mt7688-wiscan --save

<a name="Usage"></a>

## 3. Usage

### Require the module

```js
var wiscan = require('mt7688-wiscan');
```

---

<a name="API_lqi"></a>

### .lqi([intf,] essid, callback)

Query the LQI (link quality indicator) between your Linkit Smart 7688(in station mode) and its router(AP).

**Arguments:**

1. `intf` (_String_, optional): A default value of `'ra0'` will be used if not given.
2. `essid` (_String_): The ESSID of the AP to scan for.
3. `callback` (_Function_): `function(err, result) { ... }`. The `result` is a number between 0 and 100 to indicate the relative link quality between the station and access point. The value is bigger to show better link quality. If given 'essid' is not found after scan, `result` will be `null`.

**[Note]**

- It takes around 5 seconds to accomplish a single scan.
- If you've changed the name of radio interface with OpenWrt configuration tool, you should give this method with the correct interface name, for example, `'myradio'`.
- You can also try this tool on other platforms, but be aware of that the radio interface name is subject to platforms. Use `iwconfig` command at console to get some hints.

**Returns:**

- _none_

**Examples:**

```js
// scan with deafult radio interface, just give it an essid to scan for
wiscan.lqi('my_office_ap', function (err, result) {
  if (err) console.log(err); // null
  else console.log(result); // 78
});

// if an AP with given essid is not around (result is nothing after scan)
wiscan.lqi('ap_not_found', function (err, result) {
  if (err) console.log(err); // null
  else console.log(result); // null
});

// scan with the given radio interface and essid
wiscan.lqi('ra0', 'my_office_ap', function (err, result) {
  if (err) console.log(err); // null
  else console.log(result); // 82
});

// scan with the given radio interface, e.g. 'bad_ra', that doesn't exist
wiscan.lqi('bad_ra', 'my_office_ap', function (err, result) {
  if (err) console.log(err); // [Error: No such wireless device: bad_ra]
});
```

---

<a name="API_scan"></a>

### .scan([intf,] callback)

Scan neighbor wifi access points.

**Arguments:**

1. `intf` (_String_, optional): A default value of `'ra0'` will be used if not given.
2. `callback` (_Function_): `function(err, result) { ... }`. The `result` is an array of scanned report objects. Each report object has the following format:

```js
{
    address: 'D8:FE:E3:E5:9F:3B',    // String. MAC address of the found AP
    essid: 'sivann',                 // String
    mode: 'Master',                  // String
    channel: 1,                      // Number
    frequency: '2.412 GHz',          // Number
    signal: -256,                    // Number. It seems MTK's driver does not report this value. Don't use it.
    quality: 78,                     // Number. Valued from 0 ~ 100, bigger is better.
    encryption: 'WPA2 PSK (AES-OCB)' // String
}
```

**Returns:**

- _none_

**Examples:**

```js
// scan with deafult radio interface
wiscan.scan(function (err, result) {
  console.log(result);

  // [
  //     { address: 'D8:FE:E3:E5:9F:3B',  essid: 'sivann', ...   },
  //     { address: '20:0C:C8:01:1D:98',  essid: 'delta_01', ... },
  //     { address: '9C:D6:43:01:7E:C7',  essid: 'AVIS', ...     },
  //     ...
  // ]
});

// scan with given radio interface
wiscan.scan('ra0', function (err, result) {
  console.log(result);
});

// given radio interface is not valid
wiscan.scan('foo', function (err, result) {
  console.log(err); // [Error: No such wireless device: foo]
});
```

---

<a name="API_scanByEssid"></a>

### .scanByEssid([intf,] essid, callback)

Scan for a specific AP with its essid.

**Arguments:**

1. `intf` (_String_, optional): A default value of `'ra0'` will be used if not given.
2. `essid` (_String_): The ESSID of the AP to scan for.
3. `callback` (_Function_): `function(err, result) { ... }`. The result is a report object, otherwise `null` if not found.

</br>
  
**Returns:**  
  
* _none_

**Examples:**

```js
wiscan.scanByEssid('sivann', function (err, result) {
  console.log(result);

  // {
  //     address: 'D8:FE:E3:E5:9F:3B',
  //     essid: 'sivann',
  //     mode: 'Master',
  //     channel: 1,
  //     frequency: '2.412 GHz',
  //     signal: -256,
  //     quality: 68,
  //     encryption: 'WPA2 PSK (AES-OCB)' // String
  // }
});

// AP not found
wiscan.scanByEssid('no_such_ap', function (err, result) {
  console.log(result); // null
});
```

## License

MIT

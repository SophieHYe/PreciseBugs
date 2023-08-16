## 3.1

- Added [Travis CI](https://travis-ci.org/) configuration

## 3.0

- Changed from expat XML library to libxml and XMLReader
- Changed API.  Added `->load()` and changed `->parse()`

## 2.2

- Added [Composer](https://getcomposer.org/) package information

## 2.1

- Improved compliance against [RFC 7033](http://tools.ietf.org/html/rfc7033):
    - Changed default language identifier from `default` to `und`
    - Ensure property values are strings

## 2.0

- Removed support for &lt;Expires&gt; element as per definition
  of JRD in [RFC 7033](http://tools.ietf.org/html/rfc7033).

## 1.0

- Initial release

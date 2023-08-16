import { getIPRange } from './index';

const successResponsev4 = [
  '192.168.1.128',
  '192.168.1.129',
  '192.168.1.130',
  '192.168.1.131',
  '192.168.1.132',
  '192.168.1.133',
  '192.168.1.134',
  '192.168.1.135',
];

const successResponsev6 = [
  '::ffff:102:304',
  '::ffff:102:305',
  '::ffff:102:306',
  '::ffff:102:307',
]

describe('convert', () => {
  describe('for cidr notation', () => {
    it('should return an error if the IP address supplied is invalid', () => {
      const fn = () => getIPRange('abc');
      expect(fn).toThrow();
    });

    it('should return an error if the IP address is not in CIDR notation', () => {
      const fn = () => getIPRange('10.1.128.0');
      expect(fn).toThrow();
    });

    it('should return an error if the IP address uses numbers which are too high', () => {
      const fn = () => getIPRange('192.168.1.134/256');
      expect(fn).toThrow();
    });

    it('should return an array of IP addresses within the specified range', () => {
      expect(getIPRange('192.168.1.134/29')).toEqual(successResponsev4);
    });

    it('should support IPv6', () => {
      expect(getIPRange('0:0:0:0:0:ffff:102:304/126')).toEqual(successResponsev6);
    });
  });
});

describe('for two IP addresses', () => {
  it('should return an error if one of the IP addresses supplied is invalid', () => {
    const fn = () => getIPRange('abc', '192.168.0.1');
    expect(fn).toThrow();
  });

  it('should return an error if one of the IP addresses supplied is invalid', () => {
    const fn = () => getIPRange('192.168.0.1', 'abc');
    expect(fn).toThrow();
  });

  it('should return an error if one of the IP addresses is in CIDR notation', () => {
    const fn = () => getIPRange('10.1.128.0/29', '10.1.128.0');
    expect(fn).toThrow();
  });

  it('should return an error if one of the IP addresses is in CIDR notation', () => {
    const fn = () => getIPRange('10.1.128.0', '10.1.128.0/29');
    expect(fn).toThrow();
  });

  it('should return an error if one IP address has numbers which are too high', () => {
    const fn = () => getIPRange('192.168.1.134/256', '192.168.1.134');
    expect(fn).toThrow();
  });

  it('should return an error if one IP address has numbers which are too high', () => {
    const fn = () => getIPRange('192.168.1.134', '192.168.1.134/256');
    expect(fn).toThrow();
  });

  it('should return an array of IP addresses within the specified range', () => {
    expect(getIPRange('192.168.1.128', '192.168.1.135')).toEqual(successResponsev4);
  });

  it('should support IPv6', () => {
    expect(getIPRange('::ffff:102:304', '::ffff:102:307')).toEqual(successResponsev6);
  });

  it('should support hyphenated range in IPv4', () => {
    expect(getIPRange('192.168.1.128-192.168.1.135')).toEqual(successResponsev4);
  });

  it('should support hyphenated range in IPv5', () => {
    expect(getIPRange('::ffff:102:304-::ffff:102:307')).toEqual(successResponsev6);
  });
});

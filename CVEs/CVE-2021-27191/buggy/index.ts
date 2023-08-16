import { toLong, fromLong } from 'ip';
// @ts-ignore
import { Address4, Address6 } from 'ip-address';

const getIPv4 = (ip: string): Address4 | null => {
  try {
    return new Address4(ip);
  } catch (err) {
    return null;
  }
};

const getIPv6 = (ip: string): Address6 | null => {
  try {
    return new Address6(ip);
  } catch (err) {
    return null;
  }
};

const getRangev4 = (ip1: string, ip2: string) => {
  const ips = [];

  let firstAddressLong = toLong(ip1);
  const lastAddressLong = toLong(ip2);

  for (firstAddressLong; firstAddressLong <= lastAddressLong; firstAddressLong++)
    ips.push(fromLong(firstAddressLong));

  return ips;
};

const getRangev6 = (ip1: string, ip2: string) => {
  const ips = [];

  const firstAddress = new Address6(ip1);
  const lastAddress = new Address6(ip2);

  for (let i = firstAddress.bigInteger(); i <= lastAddress.bigInteger(); i++) {
    ips.push(Address6.fromBigInteger(i).correctForm());
  }

  return ips;
};

const isCIDR = (ipCIDR: Address4 | Address6): boolean => Boolean(ipCIDR.parsedSubnet);

const isRange = (ipRange: string): boolean => ipRange.indexOf('-') !== -1;

const getIPRange = (ip1: string, ip2?: string): Array<string> => {
  const ip1v4 = getIPv4(ip1);
  const ip1v6 = getIPv6(ip1);

  //
  // Two IPs
  //
  if (ip2) {
    // IPv4
    const ip2v4 = getIPv4(ip2);
    if (ip1v4.valid && ip2v4.valid && !ip1v4.parsedSubnet && !ip2v4.parsedSubnet) {
      return getRangev4(ip1v4.correctForm(), ip2v4.correctForm());
    }

    // IPv6
    const ip2v6 = getIPv6(ip2);
    if (ip1v6.valid && ip2v6.valid && !ip1v6.parsedSubnet && !ip2v6.parsedSubnet) {
      return getRangev6(ip1v6.correctForm(), ip2v6.correctForm());
    }

    // IPs do not match version, or are invalid
    throw new Error('Cannot get range of two IPs if they are not both valid and the same version');
  }

  //
  // CIDR
  //
  if (isCIDR(ip1v4)) {
    return getRangev4(ip1v4.startAddress().correctForm(), ip1v4.endAddress().correctForm());
  }

  if (isCIDR(ip1v6)) {
    return getRangev6(ip1v6.startAddress().correctForm(), ip1v6.endAddress().correctForm());
  }

  //
  // Hyphenated Range
  //
  if (isRange(ip1)) {
    const [firstAddress, lastAddress] = ip1.split('-');
    return getIPRange(firstAddress, lastAddress);
  }

  // Did not match any of the above
  throw new Error('IP supplied is not valid');
};

export { getIPRange };

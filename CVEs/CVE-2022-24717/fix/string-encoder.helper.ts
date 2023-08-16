export function stringEncode(string: string) {
    let encodedString = '';
    for (let i = 0; i < string.length; i++) {
        let charCodePointHex = string.charCodeAt(i).toString(16);
        encodedString += `\\u{${charCodePointHex}}`;
    }
    return encodedString;
}
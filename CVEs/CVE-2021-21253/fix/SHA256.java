package com.bijay.onlinevotingsystem.controller;

import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.security.SecureRandom;
import java.util.Base64;

public class SHA256 {
	private static final String SSHA_PREFIX = "{SSHA}";
	private static final int SSHA_256_LENGTH = 32; // SHA-256 is 32 bytes long
	private static final int SALT_LENGTH = 16; // Use a 16 byte salt

	public String getSHA(String password) {
		try {
			byte[] salt = getSalt();
			String cipher = getCipher(password, salt);
			
			return cipher;

			// For specifying wrong message digest algorithms
		} catch (NoSuchAlgorithmException e) {
			e.printStackTrace();
			return null;
		}
	}

	public static boolean validatePassword(String password, String cipherText) {
		boolean isValid = false;
		try {
			String cipher = cipherText.substring(SSHA_PREFIX.length());
		
			byte[] cipherBytes = Base64.getDecoder().decode(cipher.getBytes());
			byte[] salt = new byte[SALT_LENGTH];
			System.arraycopy(cipherBytes, SSHA_256_LENGTH, salt, 0, SALT_LENGTH);

			String result = getCipher(password, salt);
			//Compare the newly hashed password taking the same salt with the input hash
			isValid = result.equals(cipherText);
		} catch (NoSuchAlgorithmException e) {
			e.printStackTrace();
		}
		return isValid;
	}
	
	private static byte[] getSalt() throws NoSuchAlgorithmException {
		SecureRandom random = new SecureRandom();
		byte[] salt = new byte[SALT_LENGTH];
		random.nextBytes(salt);
		return salt;
	}

	private static String getCipher(String password, byte[] salt) throws NoSuchAlgorithmException {
		// Static getInstance method is called with hashing SHA
		MessageDigest md = MessageDigest.getInstance("SHA-256");
		md.update(salt);
	
		byte[] passBytes = password.getBytes();
		byte[] allBytes = new byte[passBytes.length + SALT_LENGTH];
		System.arraycopy(passBytes, 0, allBytes, 0, passBytes.length);
		System.arraycopy(salt, 0, allBytes, passBytes.length, SALT_LENGTH);
	
			
		byte[] cipherBytes = new byte[SSHA_256_LENGTH + SALT_LENGTH];
			
		// digest() method called
		// to calculate message digest of an input
		// and return array of byte
		byte[] messageDigest = md.digest(allBytes);
	
		System.arraycopy(messageDigest, 0, cipherBytes, 0, SSHA_256_LENGTH);
		System.arraycopy(salt, 0, cipherBytes, SSHA_256_LENGTH, SALT_LENGTH);
		
		String result = SSHA_PREFIX + Base64.getEncoder().encodeToString(cipherBytes);
		return result;
	}
}

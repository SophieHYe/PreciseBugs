/*
 * Digital Signature Service Protocol Project.
 * Copyright (C) 2013-2016 e-Contract.be BVBA.
 *
 * This is free software; you can redistribute it and/or modify it
 * under the terms of the GNU Lesser General Public License version
 * 3.0 as published by the Free Software Foundation.
 *
 * This software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this software; if not, see 
 * http://www.gnu.org/licenses/.
 */

package be.e_contract.dssp.client;

import java.io.ByteArrayInputStream;
import java.io.IOException;
import java.io.InputStream;
import java.util.List;

import javax.xml.bind.JAXBContext;
import javax.xml.bind.JAXBElement;
import javax.xml.bind.JAXBException;
import javax.xml.bind.UnmarshalException;
import javax.xml.bind.Unmarshaller;
import javax.xml.crypto.MarshalException;
import javax.xml.crypto.dsig.XMLSignature;
import javax.xml.crypto.dsig.XMLSignatureException;
import javax.xml.crypto.dsig.XMLSignatureFactory;
import javax.xml.crypto.dsig.dom.DOMValidateContext;
import javax.xml.namespace.QName;
import javax.xml.parsers.DocumentBuilder;
import javax.xml.parsers.DocumentBuilderFactory;
import javax.xml.parsers.ParserConfigurationException;

import org.apache.xml.security.exceptions.Base64DecodingException;
import org.apache.xml.security.utils.Base64;
import org.joda.time.DateTime;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.w3c.dom.Document;
import org.w3c.dom.Element;
import org.w3c.dom.NodeList;
import org.xml.sax.SAXException;
import org.xml.sax.SAXParseException;

import be.e_contract.dssp.client.exception.ClientRuntimeException;
import be.e_contract.dssp.client.exception.SubjectNotAuthorizedException;
import be.e_contract.dssp.client.exception.UserCancelException;
import be.e_contract.dssp.client.impl.SecurityTokenKeySelector;
import be.e_contract.dssp.ws.DigitalSignatureServiceConstants;
import be.e_contract.dssp.ws.jaxb.dss.AnyType;
import be.e_contract.dssp.ws.jaxb.dss.ObjectFactory;
import be.e_contract.dssp.ws.jaxb.dss.Result;
import be.e_contract.dssp.ws.jaxb.dss.SignResponse;
import be.e_contract.dssp.ws.jaxb.saml.protocol.NameIdentifierType;
import be.e_contract.dssp.ws.jaxb.wsa.AttributedURIType;
import be.e_contract.dssp.ws.jaxb.wsa.RelatesToType;
import be.e_contract.dssp.ws.jaxb.wsu.AttributedDateTime;
import be.e_contract.dssp.ws.jaxb.wsu.TimestampType;

/**
 * Verifier for browser post dss:SignResponse messages.
 * 
 * @author Frank Cornelis
 * 
 */
public class SignResponseVerifier {

	private static final Logger LOGGER = LoggerFactory.getLogger(SignResponseVerifier.class);

	private static final QName RESPONSE_ID_QNAME = new QName(
			"urn:oasis:names:tc:dss:1.0:profiles:asynchronousprocessing:1.0", "ResponseID");

	private final static QName TO_QNAME = new QName("http://www.w3.org/2005/08/addressing", "To");

	private SignResponseVerifier() {
		super();
	}

	/**
	 * Checks the signature on the SignResponse browser POST message.
	 * 
	 * @param signResponseMessage
	 *            the SignResponse message.
	 * @param session
	 *            the session object.
	 * @return the verification result object.
	 * @throws JAXBException
	 * @throws ParserConfigurationException
	 * @throws SAXException
	 * @throws IOException
	 * @throws MarshalException
	 * @throws XMLSignatureException
	 * @throws Base64DecodingException
	 * @throws UserCancelException
	 * @throws ClientRuntimeException
	 * @throws SubjectNotAuthorizedException
	 */
	public static SignResponseVerificationResult checkSignResponse(String signResponseMessage,
			DigitalSignatureServiceSession session) throws JAXBException, ParserConfigurationException, SAXException,
			IOException, MarshalException, XMLSignatureException, Base64DecodingException, UserCancelException,
			ClientRuntimeException, SubjectNotAuthorizedException {
		if (null == session) {
			throw new IllegalArgumentException("missing session");
		}

		byte[] decodedSignResponseMessage;
		try {
			decodedSignResponseMessage = Base64.decode(signResponseMessage);
		} catch (Base64DecodingException e) {
			throw new SecurityException("no Base64");
		}

		// DOM parsing
		DocumentBuilderFactory documentBuilderFactory = DocumentBuilderFactory.newInstance();
		documentBuilderFactory.setNamespaceAware(true);
		documentBuilderFactory.setFeature("http://apache.org/xml/features/disallow-doctype-decl", true);
		DocumentBuilder documentBuilder = documentBuilderFactory.newDocumentBuilder();
		InputStream signResponseInputStream = new ByteArrayInputStream(decodedSignResponseMessage);
		Document signResponseDocument;
		try {
			signResponseDocument = documentBuilder.parse(signResponseInputStream);
		} catch (SAXParseException e) {
			throw new SecurityException("no valid SignResponse XML");
		}

		// JAXB parsing
		JAXBContext jaxbContext = JAXBContext.newInstance(ObjectFactory.class,
				be.e_contract.dssp.ws.jaxb.dss.async.ObjectFactory.class,
				be.e_contract.dssp.ws.jaxb.wsa.ObjectFactory.class, be.e_contract.dssp.ws.jaxb.wsu.ObjectFactory.class);
		Unmarshaller unmarshaller = jaxbContext.createUnmarshaller();
		SignResponse signResponse;
		try {
			signResponse = (SignResponse) unmarshaller.unmarshal(signResponseDocument);
		} catch (UnmarshalException e) {
			throw new SecurityException("no valid SignResponse XML");
		}

		// signature verification
		NodeList signatureNodeList = signResponseDocument.getElementsByTagNameNS("http://www.w3.org/2000/09/xmldsig#",
				"Signature");
		if (signatureNodeList.getLength() != 1) {
			throw new SecurityException("requires 1 ds:Signature element");
		}
		Element signatureElement = (Element) signatureNodeList.item(0);
		SecurityTokenKeySelector keySelector = new SecurityTokenKeySelector(session.getKey());
		DOMValidateContext domValidateContext = new DOMValidateContext(keySelector, signatureElement);
		XMLSignatureFactory xmlSignatureFactory = XMLSignatureFactory.getInstance("DOM");
		XMLSignature xmlSignature = xmlSignatureFactory.unmarshalXMLSignature(domValidateContext);
		boolean validSignature = xmlSignature.validate(domValidateContext);
		if (false == validSignature) {
			throw new SecurityException("invalid ds:Signature");
		}

		// verify content
		String responseId = null;
		RelatesToType relatesTo = null;
		AttributedURIType to = null;
		TimestampType timestamp = null;
		String signerIdentity = null;
		AnyType optionalOutputs = signResponse.getOptionalOutputs();
		List<Object> optionalOutputsList = optionalOutputs.getAny();
		for (Object optionalOutputObject : optionalOutputsList) {
			LOGGER.debug("optional output object type: {}", optionalOutputObject.getClass().getName());
			if (optionalOutputObject instanceof JAXBElement) {
				JAXBElement optionalOutputElement = (JAXBElement) optionalOutputObject;
				LOGGER.debug("optional output name: {}", optionalOutputElement.getName());
				LOGGER.debug("optional output value type: {}", optionalOutputElement.getValue().getClass().getName());
				if (RESPONSE_ID_QNAME.equals(optionalOutputElement.getName())) {
					responseId = (String) optionalOutputElement.getValue();
				} else if (optionalOutputElement.getValue() instanceof RelatesToType) {
					relatesTo = (RelatesToType) optionalOutputElement.getValue();
				} else if (TO_QNAME.equals(optionalOutputElement.getName())) {
					to = (AttributedURIType) optionalOutputElement.getValue();
				} else if (optionalOutputElement.getValue() instanceof TimestampType) {
					timestamp = (TimestampType) optionalOutputElement.getValue();
				} else if (optionalOutputElement.getValue() instanceof NameIdentifierType) {
					NameIdentifierType nameIdentifier = (NameIdentifierType) optionalOutputElement.getValue();
					signerIdentity = nameIdentifier.getValue();
				}
			}
		}

		Result result = signResponse.getResult();
		LOGGER.debug("result major: {}", result.getResultMajor());
		LOGGER.debug("result minor: {}", result.getResultMinor());
		if (DigitalSignatureServiceConstants.REQUESTER_ERROR_RESULT_MAJOR.equals(result.getResultMajor())) {
			if (DigitalSignatureServiceConstants.USER_CANCEL_RESULT_MINOR.equals(result.getResultMinor())) {
				throw new UserCancelException();
			}
			if (DigitalSignatureServiceConstants.CLIENT_RUNTIME_RESULT_MINOR.equals(result.getResultMinor())) {
				throw new ClientRuntimeException();
			}
			if (DigitalSignatureServiceConstants.SUBJECT_NOT_AUTHORIZED_RESULT_MINOR.equals(result.getResultMinor())) {
				throw new SubjectNotAuthorizedException(signerIdentity);
			}
		}
		if (false == DigitalSignatureServiceConstants.PENDING_RESULT_MAJOR.equals(result.getResultMajor())) {
			throw new SecurityException("invalid dss:ResultMajor");
		}

		if (null == responseId) {
			throw new SecurityException("missing async:ResponseID");
		}
		if (false == responseId.equals(session.getResponseId())) {
			throw new SecurityException("invalid async:ResponseID");
		}

		if (null == relatesTo) {
			throw new SecurityException("missing wsa:RelatesTo");
		}
		if (false == session.getInResponseTo().equals(relatesTo.getValue())) {
			throw new SecurityException("invalid wsa:RelatesTo");
		}

		if (null == to) {
			throw new SecurityException("missing wsa:To");
		}
		if (false == session.getDestination().equals(to.getValue())) {
			throw new SecurityException("invalid wsa:To");
		}

		if (null == timestamp) {
			throw new SecurityException("missing wsu:Timestamp");
		}
		AttributedDateTime expires = timestamp.getExpires();
		if (null == expires) {
			throw new SecurityException("missing wsu:Timestamp/wsu:Expires");
		}
		DateTime expiresDateTime = new DateTime(expires.getValue());
		DateTime now = new DateTime();
		if (now.isAfter(expiresDateTime)) {
			throw new SecurityException("wsu:Timestamp expired");
		}

		session.setSignResponseVerified(true);

		SignResponseVerificationResult signResponseVerificationResult = new SignResponseVerificationResult(
				signerIdentity);
		return signResponseVerificationResult;
	}
}

/*
 * Digital Signature Service Protocol Project.
 * Copyright (C) 2016 e-Contract.be BVBA.
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
package be.e_contract.dssp.client.metadata;

import java.io.ByteArrayInputStream;
import java.io.Serializable;
import java.net.URL;
import java.security.cert.CertificateFactory;
import java.security.cert.X509Certificate;
import java.util.List;

import javax.xml.bind.JAXBContext;
import javax.xml.bind.JAXBElement;
import javax.xml.bind.Unmarshaller;
import javax.xml.namespace.QName;

import be.e_contract.dssp.ws.jaxb.dssp.DigitalSignatureServiceDescriptorType;
import be.e_contract.dssp.ws.jaxb.metadata.EntityDescriptorType;
import be.e_contract.dssp.ws.jaxb.metadata.KeyDescriptorType;
import be.e_contract.dssp.ws.jaxb.metadata.KeyTypes;
import be.e_contract.dssp.ws.jaxb.metadata.ObjectFactory;
import be.e_contract.dssp.ws.jaxb.metadata.RoleDescriptorType;
import be.e_contract.dssp.ws.jaxb.xmldsig.KeyInfoType;
import be.e_contract.dssp.ws.jaxb.xmldsig.X509DataType;

/**
 * Digital Signature Service Metadata Consumer. This class is serializable so
 * you can store it within a servlet container's HTTP session.
 *
 * @author Frank Cornelis
 */
public class DigitalSignatureServiceMetadata implements Serializable {

	private final static QName _X509DataTypeX509Certificate_QNAME = new QName("http://www.w3.org/2000/09/xmldsig#",
			"X509Certificate");

	private final String webServiceAddress;

	private final String browserPostAddress;

	private final X509Certificate certificate;

	/**
	 * Main constructor.
	 * 
	 * @param metadataLocation
	 *            the URL of the DSS metadata document.
	 * @throws Exception
	 */
	public DigitalSignatureServiceMetadata(String metadataLocation) throws Exception {
		JAXBContext jaxbContext = JAXBContext.newInstance(ObjectFactory.class);
		Unmarshaller unmarshaller = jaxbContext.createUnmarshaller();
		JAXBElement<EntityDescriptorType> entityDescriptorElement = (JAXBElement<EntityDescriptorType>) unmarshaller
				.unmarshal(new URL(metadataLocation));
		EntityDescriptorType entityDescriptor = entityDescriptorElement.getValue();
		List<RoleDescriptorType> roleDescriptors = entityDescriptor
				.getRoleDescriptorOrIDPSSODescriptorOrSPSSODescriptor();
		String webServiceAddress = null;
		String browserPostAddress = null;
		byte[] certificateData = null;
		for (RoleDescriptorType roleDescriptor : roleDescriptors) {
			if (roleDescriptor instanceof DigitalSignatureServiceDescriptorType) {
				DigitalSignatureServiceDescriptorType dssDescriptor = (DigitalSignatureServiceDescriptorType) roleDescriptor;
				if (!dssDescriptor.getProtocolSupportEnumeration().contains("urn:be:e-contract:dssp")) {
					continue;
				}
				webServiceAddress = dssDescriptor.getWebServiceEndpoint().getEndpointReference().getAddress()
						.getValue();
				browserPostAddress = dssDescriptor.getBrowserPostEndpoint().getEndpointReference().getAddress()
						.getValue();
				List<KeyDescriptorType> keyDescriptors = dssDescriptor.getKeyDescriptor();
				for (KeyDescriptorType keyDescriptor : keyDescriptors) {
					if (!keyDescriptor.getUse().equals(KeyTypes.SIGNING)) {
						continue;
					}
					KeyInfoType keyInfo = keyDescriptor.getKeyInfo();
					List<Object> keyInfoContent = keyInfo.getContent();
					for (Object keyInfoObject : keyInfoContent) {
						if (keyInfoObject instanceof JAXBElement) {
							JAXBElement<?> keyInfoElement = (JAXBElement<?>) keyInfoObject;
							if (keyInfoElement.getValue() instanceof X509DataType) {
								X509DataType x509Data = (X509DataType) keyInfoElement.getValue();
								List<Object> x509DataContent = x509Data.getX509IssuerSerialOrX509SKIOrX509SubjectName();
								for (Object x509DataObject : x509DataContent) {
									if (x509DataObject instanceof JAXBElement) {
										JAXBElement<?> x509DataElement = (JAXBElement<?>) x509DataObject;
										if (x509DataElement.getName().equals(_X509DataTypeX509Certificate_QNAME)) {
											certificateData = (byte[]) x509DataElement.getValue();
										}
									}
								}
							}
						}
					}
				}
			}
		}
		this.webServiceAddress = webServiceAddress;
		this.browserPostAddress = browserPostAddress;
		if (null != certificateData) {
			CertificateFactory certificateFactory = CertificateFactory.getInstance("X.509");
			this.certificate = (X509Certificate) certificateFactory
					.generateCertificate(new ByteArrayInputStream(certificateData));
		} else {
			this.certificate = null;
		}
	}

	/**
	 * Gives back the URL of the DSS SOAP web service.
	 * 
	 * @return
	 */
	public String getWebServiceAddress() {
		return this.webServiceAddress;
	}

	/**
	 * Gives back the URL of the DSS Browser POST entry point.
	 * 
	 * @return
	 */
	public String getBrowserPostAddress() {
		return this.browserPostAddress;
	}

	/**
	 * Gives back the (optional) DSS signing certificate. This certificate is
	 * used for signing of attestation SAML assertions by the DSS instance.
	 * 
	 * @return
	 */
	public X509Certificate getCertificate() {
		return this.certificate;
	}
}

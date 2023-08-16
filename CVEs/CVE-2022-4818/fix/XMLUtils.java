/*
 * Copyright (C) 2006-2019 Talend Inc. - www.talend.com
 *
 * This source code is available under agreement available at
 * %InstallDIR%\features\org.talend.rcp.branding.%PRODUCTNAME%\%PRODUCTNAME%license.txt
 *
 * You should have received a copy of the agreement along with this program; if not, write to Talend SA 9 rue Pages
 * 92150 Suresnes, France
 */
package com.amalto.commons.core.utils;

import java.io.StringReader;
import java.io.StringWriter;

import javax.xml.XMLConstants;
import javax.xml.parsers.DocumentBuilder;
import javax.xml.parsers.DocumentBuilderFactory;
import javax.xml.transform.Transformer;
import javax.xml.transform.TransformerConfigurationException;
import javax.xml.transform.TransformerException;
import javax.xml.transform.TransformerFactory;
import javax.xml.transform.dom.DOMSource;
import javax.xml.transform.stream.StreamResult;
import javax.xml.transform.stream.StreamSource;

import org.apache.logging.log4j.LogManager;
import org.apache.logging.log4j.Logger;
import org.dom4j.io.DocumentResult;
import org.dom4j.io.DocumentSource;
import org.w3c.dom.DOMImplementation;
import org.w3c.dom.Document;
import org.w3c.dom.Element;
import org.w3c.dom.Node;
import org.w3c.dom.NodeList;

import com.sun.org.apache.xpath.internal.XPathAPI;
import com.sun.org.apache.xpath.internal.objects.XObject;

/**
 * XML Manipulation routines
 * @author Bruno Grieder
 *
 */
public final class XMLUtils {

    private static final Logger logger = LogManager.getLogger(XMLUtils.class);

    private static final TransformerFactory transformerFactory;

    private static final TransformerFactory saxonTransformerFactory = new net.sf.saxon.TransformerFactoryImpl();

    static {
        // the open jdk implementation allows the disabling of the feature used for XXE
        System.setProperty("javax.xml.transform.TransformerFactory",
                "com.sun.org.apache.xalan.internal.xsltc.trax.TransformerFactoryImpl");
        transformerFactory = TransformerFactory.newInstance();
        try {
            transformerFactory.setFeature(XMLConstants.FEATURE_SECURE_PROCESSING, true);
            transformerFactory.setAttribute(XMLConstants.ACCESS_EXTERNAL_DTD, "");
            transformerFactory.setAttribute(XMLConstants.ACCESS_EXTERNAL_STYLESHEET, "");

            saxonTransformerFactory.setFeature(XMLConstants.FEATURE_SECURE_PROCESSING, true);
            saxonTransformerFactory.setAttribute("http://saxon.sf.net/feature/version-warning", Boolean.FALSE);
            saxonTransformerFactory.setAttribute("http://saxon.sf.net/feature/allow-external-functions", Boolean.FALSE);
            saxonTransformerFactory.setAttribute("http://saxon.sf.net/feature/trace-external-functions", Boolean.FALSE);
        } catch (TransformerConfigurationException e) {
            // Just catch this, as Xalan doesn't support the above
        }
    }

    /**
	 * Returns a namespaced root element of a document
	 * Useful to create a namespace holder element
	 * @param namespace
	 * @return the root Element
	 */
	public static Element getRootElement(String elementName, String namespace, String prefix) throws TransformerException{
	 	Element rootNS=null;
    	try {
	    	DocumentBuilderFactory factory = DocumentBuilderFactory.newInstance();
	        factory.setNamespaceAware(true);
	        factory.setExpandEntityReferences(false);
	        factory.setFeature("http://apache.org/xml/features/disallow-doctype-decl", true);
	        DocumentBuilder builder = factory.newDocumentBuilder();
	    	DOMImplementation impl = builder.getDOMImplementation();
	    	Document namespaceHolder = impl.createDocument(namespace,(prefix==null?"":prefix+":")+elementName, null);
	    	rootNS = namespaceHolder.getDocumentElement();
	    	rootNS.setAttributeNS("http://www.w3.org/2000/xmlns/", "xmlns:"+prefix, namespace);
    	} catch (Exception e) {
    	    String err="Error creating a namespace holder document: "+e.getLocalizedMessage();
    	    throw new TransformerException(err);
    	}
    	return rootNS;
	}

	/**
	 * Get a nodelist from an xPath
	 * @throws TransformerException
	 */
	public static NodeList getNodeList(Document d, String xPath) throws TransformerException{
		return getNodeList(d.getDocumentElement(),xPath,null,null);
	}
	/**
	 * Get a nodelist from an xPath
	 * @throws TransformerException
	 */
	public static NodeList getNodeList(Node contextNode, String xPath) throws TransformerException{
		return getNodeList(contextNode,xPath,null,null);
	}

	/**
	 * Get a nodelist from an xPath
	 * @throws TransformerException
	 */
	public static NodeList getNodeList(Node contextNode, String xPath, String namespace, String prefix) throws TransformerException{
		try {
		    XObject xo = XPathAPI.eval(
	    		contextNode,
				xPath,
				(namespace == null) ? contextNode : getRootElement("nsholder",namespace,prefix)
		    );
		    if (xo.getType() != XObject.CLASS_NODESET) return null;
		    return xo.nodelist();
    	} catch (TransformerException e) {
    	    String err = "Unable to get the Nodes List for xpath '"+xPath+"'"
    	    	+((contextNode==null) ? "" : " for Node "+contextNode.getLocalName())
    			+": "+e.getLocalizedMessage();
    		throw new TransformerException(err);
    	}
	}

    /**
     * Returns the first Element of the Node List at xPath
     * @param n
     * @param xPath
     * @return
     * @throws XtentisWebappException
     */
	public static Element getFirstElement(Node n, String xPath) throws TransformerException {
			NodeList nl = getNodeList(n, xPath);
			if ((nl==null) || (nl.getLength()==0)) return null;
			return (Element)nl.item(0);
	}

    public static String[] getAttributeNodeValue(Node contextNode, String xPath, Node namespaceNode) throws TransformerException{
        String[] results;

        //test for hard-coded values
        if (xPath.startsWith("\"") && xPath.endsWith("\""))
            return new String[] { xPath.substring(1, xPath.length()-1)};

        //test for incomplete path
        //if (! xPath.endsWith(")")) xPath+="/text()";

        try {
	        XObject xo = XPathAPI.eval(contextNode, xPath,namespaceNode);
	        if (xo.getType() == XObject.CLASS_NODESET) {
	            NodeList l = xo.nodelist();
	            int len = l.getLength();
	            results = new String[len];
	            for (int i = 0; i < len; i++) {
	                Node n = l.item(i);
	                results[i] = n.getNodeValue();
	            }
	        } else {
	            results = new String[]{xo.toString()};
	        }
		} catch (TransformerException e) {
			String err = "Unable to get the text node(s) of "+xPath
					+": " + e.getClass().getName() + ": "
					+ e.getLocalizedMessage();
			throw new TransformerException(err);
		}
		return results;
    }

    public static Transformer generateTransformer() throws TransformerConfigurationException {
        return transformerFactory.newTransformer();
    }

    public static Transformer generateTransformer(boolean isOmitXmlDeclaration) throws TransformerConfigurationException {
        Transformer transformer = generateTransformer();
        if (isOmitXmlDeclaration) {
            transformer.setOutputProperty("omit-xml-declaration", "yes");
        } else {
            transformer.setOutputProperty("omit-xml-declaration", "no");
        }
        return transformer;
    }

    public static Transformer generateTransformer(boolean isOmitXmlDeclaration, boolean isIndent)
            throws TransformerConfigurationException {
        Transformer transformer = generateTransformer(isOmitXmlDeclaration);
        if (isIndent) {
            transformer.setOutputProperty("indent", "yes");
        } else {
            transformer.setOutputProperty("indent", "no");
        }
        return transformer;
    }

    public static String nodeToString(Node n, boolean isOmitXmlDeclaration, boolean isIndent) throws TransformerException {
        StringWriter sw = new StringWriter();
        Transformer transformer = generateTransformer(isOmitXmlDeclaration, isIndent);
        transformer.transform(new DOMSource(n), new StreamResult(sw));
        return sw.toString();
    }

    public static String nodeToString(Node n) throws TransformerException {
        return nodeToString(n, true, true);
    }

    public static org.dom4j.Document styleDocument(org.dom4j.Document document, String stylesheet) throws Exception {
        // load the transformer using JAXP
        Transformer transformer = saxonTransformerFactory.newTransformer(new StreamSource(new StringReader(stylesheet)));

        // now lets style the given document
        DocumentSource source = new DocumentSource(document);
        DocumentResult result = new DocumentResult();
        transformer.transform(source, result);

        // return the transformed document
        org.dom4j.Document transformedDoc = result.getDocument();

        if (logger.isDebugEnabled()) {
            logger.debug("The xml file style transformed successfully ");
        }
        return transformedDoc;
    }
 }

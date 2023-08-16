/**
 * Copyright 2006 OCLC Online Computer Library Center Licensed under the Apache
 * License, Version 2.0 (the "License"); you may not use this file except in
 * compliance with the License. You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0 Unless required by applicable law or
 * agreed to in writing, software distributed under the License is distributed on
 * an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
 * or implied. See the License for the specific language governing permissions and
 * limitations under the License.
 */
package ORG.oclc.oai.server.verb;

import java.util.ArrayList;
import java.util.Date;
import java.util.HashMap;
import java.util.Iterator;
import java.util.Map;
import java.util.Properties;
import java.util.StringTokenizer;
import java.util.Vector;

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.xml.transform.Transformer;
import javax.xml.transform.TransformerException;

import ORG.oclc.oai.server.catalog.AbstractCatalog;
import ORG.oclc.oai.server.crosswalk.Crosswalk;
import ORG.oclc.oai.server.crosswalk.CrosswalkItem;
import ORG.oclc.oai.server.crosswalk.Crosswalks;

/**
 * This class represents a ListMetadataFormats verb on either
 * the client or on the server.
 *
 * @author Jeffrey A. Young, OCLC Online Computer Library Center
 */
public class ListMetadataFormats extends ServerVerb {
    private static ArrayList validParamNames = new ArrayList();
    static {
        validParamNames.add("verb");
        validParamNames.add("identifier");
    }
    private static ArrayList requiredParamNames = new ArrayList();
    static {
        requiredParamNames.add("verb");
    }

    /**
     * Server-side construction of the xml response
     *
     * @param context the servlet context
     * @param request the servlet request
     * @exception OAIBadRequestException an http 400 status code problem
     * @exception OAINotFoundException an http 404 status code problem
     * @exception OAIInternalServerError an http 500 status code problem
     */
    public static String construct(HashMap context,
            HttpServletRequest request,
            HttpServletResponse response,
            Transformer serverTransformer)
    throws OAIInternalServerError, TransformerException {
        Properties properties = (Properties)context.get("OAIHandler.properties");
        AbstractCatalog abstractCatalog =
            (AbstractCatalog)context.get("OAIHandler.catalog");
        String baseURL = properties.getProperty("OAIHandler.baseURL");
        if (baseURL == null) {
            try {
                baseURL = request.getRequestURL().toString();
            } catch (java.lang.NoSuchMethodError f) {
                baseURL = request.getRequestURL().toString();
            }
        }
        StringBuffer sb = new StringBuffer();
        String identifier = request.getParameter("identifier");
        sb.append("<?xml version=\"1.0\" encoding=\"UTF-8\" ?>");
        String styleSheet = properties.getProperty("OAIHandler.styleSheet");
        if (styleSheet != null) {
            sb.append("<?xml-stylesheet type=\"text/xsl\" href=\"");
            sb.append(styleSheet);
            sb.append("\"?>");
        }
        sb.append("<OAI-PMH xmlns=\"http://www.openarchives.org/OAI/2.0/\"");
        sb.append(" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"");
        sb.append(" xsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/");
        sb.append(" http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd\">");
        sb.append("<responseDate>");
        sb.append(createResponseDate(new Date()));
        sb.append("</responseDate>");
//      sb.append("<requestURL>");
//      sb.append(getRequestURL(request));
//      sb.append("</requestURL>");
        sb.append(getRequestElement(request, validParamNames, baseURL));
        if (hasBadArguments(request, requiredParamNames.iterator(),
                validParamNames)) {
            sb.append(new BadArgumentException().getMessage());
        } else {
            Crosswalks crosswalks = abstractCatalog.getCrosswalks();
            if (identifier == null || identifier.length() == 0) {
                Iterator iterator = crosswalks.iterator();
                sb.append("<ListMetadataFormats>");
                while (iterator.hasNext()) {
                    Map.Entry entry = (Map.Entry)iterator.next();
                    String oaiSchemaLabel = (String)entry.getKey();
                    CrosswalkItem crosswalkItem = (CrosswalkItem)entry.getValue();
                    Crosswalk crosswalk = crosswalkItem.getCrosswalk();
//                  StringTokenizer tokenizer = new StringTokenizer(crosswalk.getSchemaLocation());
//                  String namespaceURI = tokenizer.nextToken();
//                  String schemaURL = tokenizer.nextToken();
                    String[] tokenizer = split(crosswalk.getSchemaLocation());
                    String namespaceURI = null;
                    String schemaURL = null;
                    if (tokenizer.length == 1) {
                        schemaURL = tokenizer[0];
                    } else if (tokenizer.length > 1) {
                        namespaceURI = tokenizer[0];
                        schemaURL = tokenizer[1];
                    }
                    sb.append("<metadataFormat>");
                    sb.append("<metadataPrefix>");
                    sb.append(oaiSchemaLabel);
                    sb.append("</metadataPrefix>");
                    sb.append("<schema>");
                    sb.append(schemaURL);
                    sb.append("</schema>");
                    sb.append("<metadataNamespace>");
                    if (namespaceURI != null) {
                    	sb.append(namespaceURI);
                    }
                    sb.append("</metadataNamespace>");
                    sb.append("</metadataFormat>");
                }
                sb.append("</ListMetadataFormats>");
            } else {
                try {
                    Vector schemaLocations = abstractCatalog.getSchemaLocations(identifier);
                    sb.append("<ListMetadataFormats>");
                    for (int i=0; i<schemaLocations.size(); ++i) {
                        String schemaLocation = (String)schemaLocations.elementAt(i);
//                      StringTokenizer tokenizer = new StringTokenizer(schemaLocation);
//                      String namespaceURI = tokenizer.nextToken();
//                      String schemaURL = tokenizer.nextToken();
                        String[] tokenizer = split(schemaLocation);
                        String namespaceURI = null;
                        String schemaURL = null;
                        if (tokenizer.length == 1) {
                            schemaURL = tokenizer[0];
                        } else if (tokenizer.length > 1) {
                            namespaceURI = tokenizer[0];
                            schemaURL = tokenizer[1];
                        }
                        sb.append("<metadataFormat>");
                        sb.append("<metadataPrefix>");
                        // make sure it's a space that separates them
                        sb.append(crosswalks.getMetadataPrefix(namespaceURI, schemaURL));
                        sb.append("</metadataPrefix>");
                        sb.append("<schema>");
                        sb.append(schemaURL);
                        sb.append("</schema>");
                        sb.append("<metadataNamespace>");
                        if (namespaceURI != null) {
                        	sb.append(namespaceURI);
                        }
                        sb.append("</metadataNamespace>");
                        sb.append("</metadataFormat>");
                    }
                    sb.append("</ListMetadataFormats>");
                } catch (IdDoesNotExistException e) {
                    sb.append(e.getMessage());
                } catch (NoMetadataFormatsException e) {
                    sb.append(e.getMessage());
                }
            }
        }
        sb.append("</OAI-PMH>");
        return render(response, "text/xml; charset=UTF-8", sb.toString(), serverTransformer);
    }

    private static String[] split(String s) {
        StringTokenizer tokenizer = new StringTokenizer(s);
        String[] tokens = new String[tokenizer.countTokens()];
        for (int i=0; i<tokens.length; ++i) {
            tokens[i] = tokenizer.nextToken();
        }
        return tokens;
    }
}

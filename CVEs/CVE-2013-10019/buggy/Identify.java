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
import java.util.Enumeration;
import java.util.HashMap;
import java.util.Properties;

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.xml.transform.Transformer;
import javax.xml.transform.TransformerException;

import ORG.oclc.oai.server.catalog.AbstractCatalog;
//import org.xml.sax.SAXException;

/**
 * This class represents an Identify response on either the server or
 * on the client
 *
 * @author Jeffrey A. Young, OCLC Online Computer Library Center
 */
public class Identify extends ServerVerb {
    private static ArrayList validParamNames = new ArrayList();
    static {
        validParamNames.add("verb");
    }
    
    /**
     * Construct the xml response on the server side.
     *
     * @param context the servlet context
     * @param request the servlet request
     * @return a String containing the xml response
     */
    public static String construct(HashMap context,
            HttpServletRequest request,
            HttpServletResponse response,
            Transformer serverTransformer)
    throws TransformerException {
        String version = (String)context.get("OAIHandler.version");
        AbstractCatalog abstractCatalog =
            (AbstractCatalog)context.get("OAIHandler.catalog");
        Properties properties =
            (Properties)context.get("OAIHandler.properties");
        String baseURL = properties.getProperty("OAIHandler.baseURL");
        if (baseURL == null) {
            try {
                baseURL = request.getRequestURL().toString();
            } catch (java.lang.NoSuchMethodError f) {
                baseURL = request.getRequestURL().toString();
            }
        }
        StringBuffer sb = new StringBuffer();
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
        if (hasBadArguments(request, validParamNames.iterator(), validParamNames)) {
            sb.append(new BadArgumentException().getMessage());
        } else {
            sb.append("<Identify>");
            sb.append("<repositoryName>");
            sb.append(properties.getProperty("Identify.repositoryName",
            "undefined"));
            sb.append("</repositoryName>");
            sb.append("<baseURL>");
            sb.append(baseURL);
            sb.append("</baseURL>");
            sb.append("<protocolVersion>2.0</protocolVersion>");
            sb.append("<adminEmail>");
            sb.append(properties.getProperty("Identify.adminEmail", "undefined"));
            sb.append("</adminEmail>");
            sb.append("<earliestDatestamp>");
            sb.append(properties.getProperty("Identify.earliestDatestamp", "undefined"));
            sb.append("</earliestDatestamp>");
            sb.append("<deletedRecord>");
            sb.append(properties.getProperty("Identify.deletedRecord", "undefined"));
            sb.append("</deletedRecord>");
            String granularity = properties.getProperty("AbstractCatalog.granularity");
            if (granularity != null) {
                sb.append("<granularity>");
                sb.append(granularity);
                sb.append("</granularity>");
            }
            // 	String compression = properties.getProperty("Identify.compression");
            // 	if (compression != null) {
            sb.append("<compression>gzip</compression>");
//          sb.append("<compression>compress</compression>");
            sb.append("<compression>deflate</compression>");
            // 	}
            String repositoryIdentifier = properties.getProperty("Identify.repositoryIdentifier");
            String sampleIdentifier = properties.getProperty("Identify.sampleIdentifier");
            if (repositoryIdentifier != null && sampleIdentifier != null) {
                sb.append("<description>");
                sb.append("<oai-identifier xmlns=\"http://www.openarchives.org/OAI/2.0/oai-identifier\"");
                sb.append(" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"");
                sb.append(" xsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/oai-identifier http://www.openarchives.org/OAI/2.0/oai-identifier.xsd\">");
                sb.append("<scheme>oai</scheme>");
                sb.append("<repositoryIdentifier>");
                sb.append(repositoryIdentifier);
                sb.append("</repositoryIdentifier>");
                sb.append("<delimiter>:</delimiter>");
                sb.append("<sampleIdentifier>");
                sb.append(sampleIdentifier);
                sb.append("</sampleIdentifier>");
                sb.append("</oai-identifier>");
                sb.append("</description>");
            }
            String propertyPrefix = "Identify.description";
            Enumeration propNames = properties.propertyNames();
            while (propNames.hasMoreElements()) {
                String propertyName = (String)propNames.nextElement();
                if (propertyName.startsWith(propertyPrefix)) {
                    sb.append((String)properties.get(propertyName));
                    sb.append("\n");
                }
            }
            sb.append("<description><toolkit xsi:schemaLocation=\"http://oai.dlib.vt.edu/OAI/metadata/toolkit http://alcme.oclc.org/oaicat/toolkit.xsd\" xmlns=\"http://oai.dlib.vt.edu/OAI/metadata/toolkit\"><title>OCLC's OAICat Repository Framework</title><author><name>Jeffrey A. Young</name><email>jyoung@oclc.org</email><institution>OCLC</institution></author><version>");
            sb.append(version);
            sb.append("</version><toolkitIcon>http://alcme.oclc.org/oaicat/oaicat_icon.gif</toolkitIcon><URL>http://www.oclc.org/research/software/oai/cat.shtm</URL></toolkit></description>");
            String descriptions = abstractCatalog.getDescriptions();
            if (descriptions != null) {
                sb.append(descriptions);
            }
            sb.append("</Identify>");
        }
        sb.append("</OAI-PMH>");
        return render(response, "text/xml; charset=UTF-8", sb.toString(), serverTransformer);
    }
}

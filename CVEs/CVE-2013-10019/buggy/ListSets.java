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

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.xml.transform.Transformer;
import javax.xml.transform.TransformerException;

import ORG.oclc.oai.server.catalog.AbstractCatalog;

/**
 * A ListSets OAI verb representation.
 *
 * @author Jeffrey A. Young, OCLC Online Computer Library Center
 */
public class ListSets extends ServerVerb {
    private static ArrayList validParamNames = new ArrayList();
    static {
	validParamNames.add("verb");
	validParamNames.add("resumptionToken");
    }
    private static ArrayList requiredParamNames = new ArrayList();
    static {
	validParamNames.add("verb");
    }

    /**
     * construct ListSets response
     *
     * @param context the context object from the local OAI server
     * @param request the request object from the local OAI server
     * @exception OAIInternalServerError
     */
    public static String construct(HashMap context, HttpServletRequest request,
                                   HttpServletResponse response, Transformer serverTransformer) 
	throws OAIInternalServerError, TransformerException {
        Properties properties =
	    (Properties)context.get("OAIHandler.properties");
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
	String oldResumptionToken = request.getParameter("resumptionToken");
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
//         sb.append("<requestURL>");
//         sb.append(getRequestURL(request));
//         sb.append("</requestURL>");
	sb.append(getRequestElement(request, validParamNames, baseURL));
	Map listSetsMap = null;
	if (hasBadArguments(request, requiredParamNames.iterator(),
			    validParamNames)) {
	    sb.append(new BadArgumentException().getMessage());
	} else {
	    try {
		if (oldResumptionToken == null) {
		    listSetsMap = abstractCatalog.listSets();
		} else {
		    listSetsMap = abstractCatalog.listSets(oldResumptionToken);
		}
		sb.append("<ListSets>");
		Iterator sets = (Iterator)listSetsMap.get("sets");
		while (sets.hasNext()) {
		    sb.append((String)sets.next());
		}
		Map newResumptionMap = (Map)listSetsMap.get("resumptionMap");
		if (newResumptionMap != null) {
		    String newResumptionToken = (String)newResumptionMap.get("resumptionToken");
		    String expirationDate = (String)newResumptionMap.get("expirationDate");
		    String completeListSize = (String)newResumptionMap.get("completeListSize");
		    String cursor = (String)newResumptionMap.get("cursor");
		    sb.append("<resumptionToken");
		    if (expirationDate != null) {
			sb.append(" expirationDate=\"");
			sb.append(expirationDate);
			sb.append("\"");
		    }
		    if (completeListSize != null) {
			sb.append(" completeListSize=\"");
			sb.append(completeListSize);
			sb.append("\"");
		    }
		    if (cursor != null) {
			sb.append(" cursor=\"");
			sb.append(cursor);
			sb.append("\"");
		    }
		    sb.append(">");
		    sb.append(newResumptionToken);
		    sb.append("</resumptionToken>");
		} else if (oldResumptionToken != null) {
		    sb.append("<resumptionToken />");
		}
		sb.append("</ListSets>");
	    } catch (NoSetHierarchyException e) {
		sb.append(e.getMessage());
	    } catch (BadResumptionTokenException e) {
		sb.append(e.getMessage());
	    }
	}
        sb.append("</OAI-PMH>");
	return render(response, "text/xml; charset=UTF-8", sb.toString(), serverTransformer);
    }
}

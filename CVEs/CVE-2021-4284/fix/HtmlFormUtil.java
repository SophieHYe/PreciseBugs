/*
 * The contents of this file are subject to the OpenMRS Public License
 * Version 1.0 (the "License"); you may not use this file except in
 * compliance with the License. You may obtain a copy of the License at
 * http://license.openmrs.org
 *
 * Software distributed under the License is distributed on an "AS IS"
 * basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See the
 * License for the specific language governing rights and limitations
 * under the License.
 *
 * Copyright (C) OpenMRS, LLC.  All Rights Reserved.
 */

package org.openmrs.module.htmlformentryui;

import org.apache.commons.lang3.StringUtils;
import org.openmrs.Encounter;
import org.openmrs.EncounterType;
import org.openmrs.Form;
import org.openmrs.Patient;
import org.openmrs.Visit;
import org.openmrs.api.FormService;
import org.openmrs.module.htmlformentry.HtmlForm;
import org.openmrs.module.htmlformentry.HtmlFormEntryService;
import org.openmrs.module.htmlformentry.HtmlFormEntryUtil;
import org.openmrs.ui.framework.SimpleObject;
import org.openmrs.ui.framework.UiUtils;
import org.openmrs.ui.framework.resource.ResourceFactory;
import org.openmrs.util.OpenmrsUtil;
import org.w3c.dom.Document;
import org.w3c.dom.Node;

import java.io.IOException;

/**
 *
 */
public class HtmlFormUtil {
	
	public static HtmlForm getHtmlFormFromUiResource(ResourceFactory resourceFactory, FormService formService,
	        HtmlFormEntryService htmlFormEntryService, String providerAndPath, Encounter encounter) throws IOException {
		int ind = providerAndPath.indexOf(':');
		String provider = providerAndPath.substring(0, ind);
		String path = providerAndPath.substring(ind + 1);
		return getHtmlFormFromUiResource(resourceFactory, formService, htmlFormEntryService, provider, path, encounter);
	}
	
	public static HtmlForm getHtmlFormFromUiResource(ResourceFactory resourceFactory, FormService formService,
	        HtmlFormEntryService htmlFormEntryService, String providerName, String resourcePath, Encounter encounter)
	        throws IOException {
		
		String xml = null;
		
		// first, see if there is a specific version of the form referenced by version number
		if (encounter != null && encounter.getForm() != null && encounter.getForm().getVersion() != null) {
			String resourcePathWithVersion = resourcePath.replaceAll("\\.xml$", "") + "_v" + encounter.getForm().getVersion()
			        + ".xml";
			xml = resourceFactory.getResourceAsString(providerName, resourcePathWithVersion);
			// should be of the format <htmlform formUuid="..." formVersion="..." formEncounterType="...">...</htmlform>
		}
		
		// if not, use the bare resource path (without version number appended) to fetch the form
		if (xml == null) {
			xml = resourceFactory.getResourceAsString(providerName, resourcePath);
			
		}
		
		if (xml == null) {
			throw new IllegalArgumentException("No resource found at " + providerName + ":" + resourcePath);
		}
		
		return getHtmlFormFromResourceXml(formService, htmlFormEntryService, xml);
	}
	
	// the new method above with "encounter" is preferred if an encounter is available, see: https://issues.openmrs.org/browse/HTML-768
	public static HtmlForm getHtmlFormFromUiResource(ResourceFactory resourceFactory, FormService formService,
	        HtmlFormEntryService htmlFormEntryService, String providerAndPath) throws IOException {
		return getHtmlFormFromUiResource(resourceFactory, formService, htmlFormEntryService, providerAndPath,
		    (Encounter) null);
	}
	
	// the new method above with "encounter" is preferred if an encounter is available, see: https://issues.openmrs.org/browse/HTML-768
	public static HtmlForm getHtmlFormFromUiResource(ResourceFactory resourceFactory, FormService formService,
	        HtmlFormEntryService htmlFormEntryService, String providerName, String resourcePath) throws IOException {
		return getHtmlFormFromUiResource(resourceFactory, formService, htmlFormEntryService, providerName, resourcePath,
		    null);
	}
	
	public static HtmlForm getHtmlFormFromResourceXml(FormService formService, HtmlFormEntryService htmlFormEntryService,
	        String xml) {
		try {
			Document doc = HtmlFormEntryUtil.stringToDocument(xml);
			Node htmlFormNode = HtmlFormEntryUtil.findChild(doc, "htmlform");
			String formUuid = getAttributeValue(htmlFormNode, "formUuid");
			if (formUuid == null) {
				throw new IllegalArgumentException("formUuid is required");
			}
			Form form = formService.getFormByUuid(formUuid);
			boolean needToSaveForm = false;
			if (form == null) {
				form = new Form();
				form.setUuid(formUuid);
				needToSaveForm = true;
			}
			
			String formName = getAttributeValue(htmlFormNode, "formName");
			if (!OpenmrsUtil.nullSafeEquals(form.getName(), formName)) {
				form.setName(formName);
				needToSaveForm = true;
			}
			
			String formDescription = getAttributeValue(htmlFormNode, "formDescription");
			if (!OpenmrsUtil.nullSafeEquals(form.getDescription(), formDescription)) {
				form.setDescription(formDescription);
				needToSaveForm = true;
			}
			
			String formVersion = getAttributeValue(htmlFormNode, "formVersion");
			if (!OpenmrsUtil.nullSafeEquals(form.getVersion(), formVersion)) {
				form.setVersion(formVersion);
				needToSaveForm = true;
			}
			
			String formEncounterType = getAttributeValue(htmlFormNode, "formEncounterType");
			EncounterType encounterType = formEncounterType == null ? null
			        : HtmlFormEntryUtil.getEncounterType(formEncounterType);
			if (encounterType != null && !OpenmrsUtil.nullSafeEquals(form.getEncounterType(), encounterType)) {
				form.setEncounterType(encounterType);
				needToSaveForm = true;
			}
			
			if (needToSaveForm) {
				formService.saveForm(form);
			}
			
			HtmlForm htmlForm = htmlFormEntryService.getHtmlFormByForm(form);
			boolean needToSaveHtmlForm = false;
			if (htmlForm == null) {
				htmlForm = new HtmlForm();
				htmlForm.setForm(form);
				needToSaveHtmlForm = true;
				
			}
			
			// if there is a html form uuid specified, make sure the htmlform uuid is set to that value
			String htmlformUuid = getAttributeValue(htmlFormNode, "htmlformUuid");
			if (StringUtils.isNotBlank(htmlformUuid) && !OpenmrsUtil.nullSafeEquals(htmlformUuid, htmlForm.getUuid())) {
				htmlForm.setUuid(htmlformUuid);
				needToSaveHtmlForm = true;
			}
			
			if (!OpenmrsUtil.nullSafeEquals(trim(htmlForm.getXmlData()), trim(xml))) { // trim because if the file ends with a newline the db will have trimmed it
				htmlForm.setXmlData(xml);
				needToSaveHtmlForm = true;
			}
			if (needToSaveHtmlForm) {
				htmlFormEntryService.saveHtmlForm(htmlForm);
			}
			return htmlForm;
			
		}
		catch (Exception e) {
			throw new IllegalArgumentException("Failed to parse XML and build Form and HtmlForm", e);
		}
	}
	
	public static String determineReturnUrl(String returnUrl, String returnProviderName, String returnPageName,
	        Patient patient, Visit visit, UiUtils ui) {
		
		SimpleObject returnParams = null;
		
		if (patient != null) {
			if (visit == null) {
				returnParams = SimpleObject.create("patientId", patient.getId());
			} else {
				returnParams = SimpleObject.create("patientId", patient.getId(), "visitId", visit.getId());
			}
		}
		
		// first see if a return provider and page have been specified
		if (org.apache.commons.lang.StringUtils.isNotBlank(returnProviderName)
		        && org.apache.commons.lang.StringUtils.isNotBlank(returnPageName)) {
			return ui.pageLink(returnProviderName, returnPageName, returnParams);
		}
		
		// if not, see if a returnUrl has been specified
		if (org.apache.commons.lang.StringUtils.isNotBlank(returnUrl)) {
			return returnUrl;
		}
		
		// otherwise return to patient dashboard if we have a patient, but index if not
		if (returnParams != null && returnParams.containsKey("patientId")) {
			return ui.pageLink("coreapps", "patientdashboard/patientDashboard", returnParams);
		} else {
			return "/" + ui.contextPath() + "index.html";
		}
		
	}
	
	public static String determineReturnLabel(String returnLabel, Patient patient, UiUtils ui) {
		
		if (org.apache.commons.lang.StringUtils.isNotBlank(returnLabel)) {
			return ui.message(returnLabel);
		} else {
			return ui.encodeJavaScript(ui.format(patient));
		}
		
	}
	
	private static String trim(String s) {
		return s == null ? null : s.trim();
	}
	
	private static String getAttributeValue(Node htmlForm, String attributeName) {
		Node item = htmlForm.getAttributes().getNamedItem(attributeName);
		return item == null ? null : item.getNodeValue();
	}
	
}

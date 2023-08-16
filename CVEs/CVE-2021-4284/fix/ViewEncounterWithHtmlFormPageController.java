package org.openmrs.module.htmlformentryui.page.controller.htmlform;

import org.apache.commons.lang.StringUtils;
import org.openmrs.Encounter;
import org.openmrs.api.AdministrationService;
import org.openmrs.module.emrapi.patient.PatientDomainWrapper;
import org.openmrs.module.htmlformentry.HtmlForm;
import org.openmrs.module.htmlformentry.HtmlFormEntryService;
import org.openmrs.ui.framework.SimpleObject;
import org.openmrs.ui.framework.UiUtils;
import org.openmrs.ui.framework.annotation.InjectBeans;
import org.openmrs.ui.framework.annotation.SpringBean;
import org.openmrs.ui.framework.page.PageModel;
import org.springframework.web.bind.annotation.RequestParam;

public class ViewEncounterWithHtmlFormPageController {
	
	public void get(@RequestParam("encounter") Encounter encounter,
	        @RequestParam(value = "showPatientHeader", defaultValue = "true") boolean showPatientHeader,
	        @RequestParam(value = "returnUrl", required = false) String returnUrl,
	        @RequestParam(value = "returnLabel", required = false) String returnLabel,
	        @RequestParam(value = "editStyle", defaultValue = "standard") String editStyle,
	        @InjectBeans PatientDomainWrapper patient,
	        @SpringBean("htmlFormEntryService") HtmlFormEntryService htmlFormEntryService,
	        @SpringBean("adminService") AdministrationService administrationService, UiUtils ui, PageModel model) {
		
		patient.setPatient(encounter.getPatient());
		
		String customPrintProvider = administrationService.getGlobalProperty("htmlformentryui.customPrintProvider");
		String customPrintPageName = administrationService.getGlobalProperty("htmlformentryui.customPrintPageName");
		String customPrintTarget = administrationService.getGlobalProperty("htmlformentryui.customPrintTarget");
		
		model.addAttribute("customPrintProvider", customPrintProvider);
		model.addAttribute("customPrintPageName", customPrintPageName);
		model.addAttribute("customPrintTarget", customPrintTarget);
		
		if (StringUtils.isEmpty(returnUrl)) {
			returnUrl = ui.pageLink("coreapps", "patientdashboard/patientDashboard",
			    SimpleObject.create("patientId", patient.getId()));
		}
		if (StringUtils.isEmpty(returnLabel)) {
			returnLabel = ui.encodeJavaScript(ui.format(patient.getPatient()));
		}
		
		model.addAttribute("patient", patient);
		model.addAttribute("visit", encounter.getVisit());
		model.addAttribute("encounter", encounter);
		model.addAttribute("returnUrl", returnUrl);
		model.addAttribute("returnLabel", returnLabel);
		model.addAttribute("editStyle", fixCase(editStyle));
		model.addAttribute("showPatientHeader", showPatientHeader);
		
		HtmlForm htmlForm = htmlFormEntryService.getHtmlFormByForm(encounter.getForm());
		if (htmlForm == null) {
			throw new IllegalArgumentException("encounter.form is not an HTML Form: " + encounter.getForm());
		}
		model.addAttribute("htmlForm", htmlForm);
	}
	
	/**
	 * @param word
	 * @return word with the first letter uppercase, and the rest lowercase
	 */
	private String fixCase(String word) {
		return Character.toUpperCase(word.charAt(0)) + word.substring(1).toLowerCase();
	}
	
}

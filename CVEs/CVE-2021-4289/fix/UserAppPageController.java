/**
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
package org.openmrs.module.referenceapplication.page.controller;

import javax.servlet.http.HttpSession;

import org.apache.commons.lang.StringEscapeUtils;
import org.apache.commons.logging.Log;
import org.apache.commons.logging.LogFactory;
import org.codehaus.jackson.map.ObjectMapper;
import org.openmrs.module.appframework.domain.AppDescriptor;
import org.openmrs.module.appframework.domain.UserApp;
import org.openmrs.module.appframework.service.AppFrameworkService;
import org.openmrs.module.uicommons.UiCommonsConstants;
import org.openmrs.module.uicommons.util.InfoErrorMessageUtil;
import org.openmrs.module.webservices.rest.SimpleObject;
import org.openmrs.ui.framework.UiUtils;
import org.openmrs.ui.framework.annotation.BindParams;
import org.openmrs.ui.framework.annotation.SpringBean;
import org.openmrs.ui.framework.page.PageModel;
import org.springframework.stereotype.Controller;
import org.springframework.web.bind.annotation.ModelAttribute;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseBody;

@Controller
public class UserAppPageController {
	
	protected final Log log = LogFactory.getLog(getClass());
	
	private ObjectMapper mapper = new ObjectMapper();
	
	public void get(PageModel model, @RequestParam(value = "appId", required = false) UserApp userApp) {
		
		if (userApp == null) {
			userApp = new UserApp();
		}
		model.addAttribute("userApp", userApp);
	}
	
	public String post(PageModel model, @ModelAttribute(value = "appId") @BindParams UserApp userApp,
	                   @RequestParam("action") String action,
	                   @SpringBean("appFrameworkService") AppFrameworkService service, HttpSession session, UiUtils ui) {

		try {
			AppDescriptor descriptor = mapper.readValue(userApp.getJson(), AppDescriptor.class);
			if (!userApp.getAppId().equals(descriptor.getId())) {
				session.setAttribute(UiCommonsConstants.SESSION_ATTRIBUTE_ERROR_MESSAGE,
				    ui.message("referenceapplication.app.errors.IdsShouldMatch"));
			} else if ("add".equals(action) && service.getUserApp(userApp.getAppId()) != null) {
				session.setAttribute(UiCommonsConstants.SESSION_ATTRIBUTE_ERROR_MESSAGE,
				    ui.message("referenceapplication.app.errors.duplicateAppId"));
			} else {
				service.saveUserApp(userApp);
				
				InfoErrorMessageUtil.flashInfoMessage(session,
				    ui.message("referenceapplication.app.userApp.save.success", StringEscapeUtils.escapeHtml(userApp.getAppId())));
				
				return "redirect:/referenceapplication/manageApps.page";
			}
		}
		catch (Exception e) {
			session.setAttribute(UiCommonsConstants.SESSION_ATTRIBUTE_ERROR_MESSAGE,
			    ui.message("referenceapplication.app.userApp.save.fail", StringEscapeUtils.escapeHtml(userApp.getAppId())));
		}
		
		model.addAttribute("userApp", userApp);
		
		return null;
	}
	
	@ResponseBody
	@RequestMapping("/referenceapplication/verifyJson")
	public SimpleObject verifyJson(@RequestParam("json") String json) {
		SimpleObject so = new SimpleObject();
		try {
			mapper.readValue(json, AppDescriptor.class);
			so.add("isValid", true);
		}
		catch (Exception e) {
			log.warn("Invalid json:", e);
			so.add("isValid", false);
		}
		
		return so;
	}
}

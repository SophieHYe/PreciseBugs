/**
 * This Source Code Form is subject to the terms of the Mozilla Public License,
 * v. 2.0. If a copy of the MPL was not distributed with this file, You can
 * obtain one at http://mozilla.org/MPL/2.0/. OpenMRS is also distributed under
 * the terms of the Healthcare Disclaimer located at http://openmrs.org/license.
 *
 * Copyright (C) OpenMRS Inc. OpenMRS is a registered trademark and the OpenMRS
 * graphic logo is a trademark of OpenMRS Inc.
 */
package org.openmrs.module.adminui.page.controller.systemadmin.accounts;

import java.io.IOException;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.Set;
import java.util.Collections;

import javax.servlet.http.HttpServletRequest;
import org.apache.commons.lang.StringUtils;
import org.apache.commons.logging.Log;
import org.apache.commons.logging.LogFactory;
import org.codehaus.jackson.map.ObjectMapper;
import org.openmrs.OpenmrsObject;
import org.openmrs.Person;
import org.openmrs.PersonName;
import org.openmrs.Role;
import org.openmrs.User;
import org.openmrs.api.APIException;
import org.openmrs.api.AdministrationService;
import org.openmrs.api.UserService;
import org.openmrs.api.context.Context;
import org.openmrs.messagesource.MessageSourceService;
import org.openmrs.module.adminui.AdminUiConstants;
import org.openmrs.module.adminui.account.Account;
import org.openmrs.module.adminui.account.AccountService;
import org.openmrs.module.adminui.account.AdminUiAccountValidator;
import org.openmrs.module.appframework.domain.Extension;
import org.openmrs.module.appframework.service.AppFrameworkService;
import org.openmrs.module.providermanagement.Provider;
import org.openmrs.module.providermanagement.ProviderRole;
import org.openmrs.module.providermanagement.api.ProviderManagementService;
import org.openmrs.module.uicommons.UiCommonsConstants;
import org.openmrs.module.uicommons.util.InfoErrorMessageUtil;
import org.openmrs.ui.framework.SimpleObject;
import org.openmrs.ui.framework.UiUtils;
import org.openmrs.ui.framework.annotation.BindParams;
import org.openmrs.ui.framework.annotation.MethodParam;
import org.openmrs.ui.framework.annotation.SpringBean;
import org.openmrs.ui.framework.page.PageModel;
import org.openmrs.util.OpenmrsConstants;
import org.springframework.validation.BeanPropertyBindingResult;
import org.springframework.validation.Errors;
import org.springframework.validation.ObjectError;
import org.springframework.web.bind.annotation.RequestParam;
import org.openmrs.PersonAttributeType;
import org.openmrs.PersonAttribute;

/**
 * This controller only handles requests to create a new account and doesn't support editing
 */
public class AccountPageController {
	
	protected final Log log = LogFactory.getLog(getClass());
	
	public Account getAccount(@RequestParam(value = "personId", required = false) Person person) {
		
		Account account;
		
		if (person == null) {
			account = new Account(new Person());
		} else {
			account = new Account(person);
			if (account == null) {
				throw new APIException("Failed to find user account matching person with id:" + person.getPersonId());
			}
		}
		
		return account;
	}
	
	/**
	 * @param model
	 * @param account
	 * @param accountService
	 * @param providerManagementService
	 */
	public void get(PageModel model, @MethodParam("getAccount") Account account,
	                @SpringBean("adminAccountService") AccountService accountService,
	                @SpringBean("adminService") AdministrationService administrationService,
	                @SpringBean("providerManagementService") ProviderManagementService providerManagementService,
					UiUtils uu,
					@SpringBean("appFrameworkService") AppFrameworkService appFrameworkService)
	    throws IOException {
		
		setModelAttributes(model, account, null, accountService, administrationService, providerManagementService, uu, appFrameworkService);
		if (account.getPerson().getPersonId() == null) {
			setJsonFormData(model, account, null, uu);
		}
	}
	
	/**
	 * @param account
	 * @param messageSourceService
	 * @param accountService
	 * @param administrationService
	 * @param providerManagementService
	 * @param accountValidator
	 * @param model
	 * @param request
	 * @return
	 */
	public String post(PageModel model, @MethodParam("getAccount") @BindParams Account account, @BindParams User user,
	                   @BindParams OtherAccountData otherAccountData, @SpringBean("userService") UserService userService,
	                   @SpringBean("messageSourceService") MessageSourceService messageSourceService,
	                   @SpringBean("adminAccountService") AccountService accountService,
	                   @SpringBean("adminService") AdministrationService administrationService,
	                   @SpringBean("adminUiAccountValidator") AdminUiAccountValidator accountValidator,
	                   @SpringBean("providerManagementService") ProviderManagementService providerManagementService,
					   @SpringBean("appFrameworkService") AppFrameworkService appFrameworkService,
	                   HttpServletRequest request, UiUtils uu) throws IOException {
		
		Errors errors = new BeanPropertyBindingResult(account, "account");

        List<Extension> customUserPropertyEditFragments =
				appFrameworkService.getExtensionsForCurrentUser("userAccount.userPropertyEditFragment");
        Map<String, String[]> parameterMap = request.getParameterMap();
        for(Extension ext : customUserPropertyEditFragments) {
            if (StringUtils.equals(ext.getExtensionParams().get("type").toString(), "userProperty")) {
                String userPropertyName = ext.getExtensionParams().get("userPropertyName").toString();
                String[] parameterValues = parameterMap.get(userPropertyName);
                if (parameterValues != null && parameterValues.length > 0) {
                    String parameterValue;
                    if (userPropertyName == "locationUuid") {
                    	parameterValue = String.join(",", parameterValues);
                    } else {
                        if (parameterValues.length > 1) {
                            log.warn("Multiple userProperty for a single user type not supported, ignoring extra values");
                        }
                        parameterValue = parameterValues[0];
                    }
                    if (userPropertyName != null && parameterValue != null) {
                        user.setUserProperty(userPropertyName, parameterValue);
                    }
                }
            }
        }

        List<Extension> customPersonAttributeEditFragments =
				appFrameworkService.getExtensionsForCurrentUser("userAccount.personAttributeEditFragment");
        for(Extension ext : customPersonAttributeEditFragments) {
            if (StringUtils.equals(ext.getExtensionParams().get("type").toString(), "personAttribute")) {
                String formFiledName = ext.getExtensionParams().get("formFieldName").toString();
                String personAttributeTypeUuid = ext.getExtensionParams().get("uuid").toString();
                String[] parameterValues = parameterMap.get(formFiledName);
                if (parameterValues != null && parameterValues.length > 0) {
                    if (parameterValues.length > 1) {
                        log.warn("Multiple values for a single person attribute type not supported, ignoring extra values");
                    }
                    String parameterValue = parameterValues[0];
                    if (parameterValue != null) {
                        PersonAttributeType personAttributeByUuid = Context.getPersonService()
                                .getPersonAttributeTypeByUuid(personAttributeTypeUuid);
                        if (personAttributeByUuid != null) {
                            PersonAttribute attribute = new PersonAttribute(personAttributeByUuid, parameterValue);
                            account.getPerson().addAttribute(attribute);
                        }
                    }
                }
            }
        }

		if (otherAccountData.getAddUserAccount()) {
			//The StringToRoleConverter emrapi for some reason is taking precedence over the
			//one in uiframework module and it doesn't get role by uuid, so we have to do this
			user.addRole(userService.getRoleByUuid(request.getParameter("privilegeLevel")));
			String[] uuids = request.getParameterValues("capabilities");
			if (uuids != null) {
				for (String uuid : uuids) {
					user.addRole(userService.getRoleByUuid(uuid));
				}
			}
			
			String forcePassword = otherAccountData.getForceChangePassword() ? "true" : "false";
			user.setUserProperty(OpenmrsConstants.USER_PROPERTY_CHANGE_PASSWORD, forcePassword);
			account.addUserAccount(user);
		}
		if (otherAccountData.getAddProviderAccount()) {
			Provider provider = new Provider();
			provider.setIdentifier(request.getParameter("identifier"));
			provider.setProviderRole(providerManagementService.getProviderRoleByUuid(request.getParameter("providerRole")));
			account.addProviderAccount(provider);
		}
		
		accountValidator.validate(account, errors);
		
		if (!errors.hasErrors()) {
			try {
				account.setPassword(user, otherAccountData.getPassword());
				accountService.saveAccount(account);
				InfoErrorMessageUtil.flashInfoMessage(request.getSession(), "adminui.account.saved");
				return "redirect:/adminui/systemadmin/accounts/manageAccounts.page";
			}
			catch (Exception e) {
				errors.reject("adminui.account.error.save.fail");
				//If the person, provider or user account had been flushed we need to unset the ids because
				//they actually don't exist in the DB otherwise the logic in the GSP will see the ids and
				//think we are editing and things will break
				account.getPerson().setId(null);
				if (otherAccountData.getAddProviderAccount()) {
					account.getProviderAccounts().get(0).setProviderId(null);
				}
				if (otherAccountData.getAddUserAccount()) {
					account.getUserAccounts().get(0).setUserId(null);
				}
			}
		}
		
		setModelAttributes(model, account, otherAccountData, accountService, administrationService,
		    providerManagementService, uu, appFrameworkService);
		
		sendErrorMessage(errors, model, messageSourceService, request);
		
		if (account.getPerson().getPersonId() == null) {
			setJsonFormData(model, account, otherAccountData, uu);
		}
		
		return "systemadmin/accounts/account";
		
	}
	
	public void setModelAttributes(PageModel model, Account account, OtherAccountData otherAccountData,
	                               AccountService accountService, AdministrationService administrationService,
	                               ProviderManagementService providerManagementService, UiUtils uu,
								   AppFrameworkService appFrameworkService) throws IOException {

		model.addAttribute("account", account);
		Boolean forcePasswordChange = null;
		if (otherAccountData == null) {
			otherAccountData = new OtherAccountData();
			if (account.getPerson().getPersonId() == null) {
				//Default value when creating a new account otherwise we need to read the DB for each user
				forcePasswordChange = true;
			}
		} else {
			//other account data is not null only when we are sending
			//the user back to the form, so we need to get what they
			//had previously selected
			forcePasswordChange = otherAccountData.getForceChangePassword();
		}
		
		model.addAttribute("otherAccountData", otherAccountData);
		List<Role> capabilities = accountService.getAllCapabilities();
		model.addAttribute("capabilities", accountService.getAllCapabilities());
		List<Role> privilegeLevels = accountService.getAllPrivilegeLevels();
		model.addAttribute("privilegeLevels", privilegeLevels);
		String privilegeLevelPrefix = AdminUiConstants.ROLE_PREFIX_PRIVILEGE_LEVEL;
		String rolePrefix = AdminUiConstants.ROLE_PREFIX_CAPABILITY;
		model.addAttribute("privilegeLevelPrefix", privilegeLevelPrefix);
		model.addAttribute("rolePrefix", rolePrefix);
		model.addAttribute("allowedLocales", administrationService.getAllowedLocales());
		List<ProviderRole> providerRoles = providerManagementService.getAllProviderRoles(false);
		model.addAttribute("providerRoles", providerRoles);

		List<Extension> customPersonAttributeEditFragments =
				appFrameworkService.getExtensionsForCurrentUser("userAccount.personAttributeEditFragment");
		Collections.sort(customPersonAttributeEditFragments);
		model.addAttribute("customPersonAttributeEditFragments", customPersonAttributeEditFragments);
		List<Extension> customPersonAttributeViewFragments =
				appFrameworkService.getExtensionsForCurrentUser("userAccount.personAttributeViewFragment");
		Collections.sort(customPersonAttributeViewFragments);
		model.addAttribute("customPersonAttributeViewFragments", customPersonAttributeViewFragments);

		List<Extension> customUserPropertyEditFragments =
				appFrameworkService.getExtensionsForCurrentUser("userAccount.userPropertyEditFragment");
		Collections.sort(customUserPropertyEditFragments);
		model.addAttribute("customUserPropertyEditFragments", customUserPropertyEditFragments);
		List<Extension> customUserPropertyViewFragments =
				appFrameworkService.getExtensionsForCurrentUser("userAccount.userPropertyViewFragment");
		Collections.sort(customUserPropertyViewFragments);
		model.addAttribute("customUserPropertyViewFragments", customUserPropertyViewFragments);

		Map<String, Integer> propertyMaxLengthMap = new HashMap<String, Integer>();
		propertyMaxLengthMap.put("familyName",
		    administrationService.getMaximumPropertyLength(PersonName.class, "family_name"));
		propertyMaxLengthMap
		        .put("givenName", administrationService.getMaximumPropertyLength(PersonName.class, "given_name"));
		propertyMaxLengthMap.put("identifier", administrationService.getMaximumPropertyLength(Provider.class, "identifier"));
		propertyMaxLengthMap.put("username", administrationService.getMaximumPropertyLength(User.class, "username"));
		propertyMaxLengthMap.put("password", administrationService.getMaximumPropertyLength(User.class, "password"));
		model.addAttribute("propertyMaxLengthMap", propertyMaxLengthMap);
		model.addAttribute("passwordMinLength",
		    administrationService.getGlobalProperty(OpenmrsConstants.GP_PASSWORD_MINIMUM_LENGTH, "8"));
		
		ObjectMapper mapper = new ObjectMapper();
		SimpleObject so = new SimpleObject();
		for (Role cap : capabilities) {
			String str = uu.format(cap);
			so.put(cap.getUuid(), str.substring(str.indexOf(rolePrefix) + rolePrefix.length()));
		}
		model.addAttribute("capabilitiesJson", mapper.writeValueAsString(so));
		so = new SimpleObject();
		for (Role role : privilegeLevels) {
			String str = uu.format(role);
			so.put(role.getUuid(), str.substring(str.indexOf(privilegeLevelPrefix) + privilegeLevelPrefix.length()));
		}
		model.addAttribute("privilegeLevelsJson", mapper.writeValueAsString(so));
		
		if (account.getPerson().getPersonId() != null) {
			account.getProviderAccounts().add(new Provider());
			account.getUserAccounts().add(new User());
		}
		so = new SimpleObject();
		for (User user : account.getUserAccounts()) {
			SimpleObject simpleUser = new SimpleObject();
			simpleUser.put("username", user.getUsername());
			simpleUser.put("systemId", user.getSystemId());
			simpleUser.put("privilegeLevel", account.getPrivilegeLevel(user) != null ? account.getPrivilegeLevel(user)
			        .getUuid() : "");
			SimpleObject userProperties = new SimpleObject();
			boolean force;
			if (forcePasswordChange != null) {
				force = forcePasswordChange;
			} else {
				//Default to force password change for a new account to be added otherwise get DB value
				force = (user.getUserId() == null) ? true : account.isSupposedToChangePassword(user);
			}
			userProperties.put(OpenmrsConstants.USER_PROPERTY_CHANGE_PASSWORD, force);
			for(Extension ext : customUserPropertyViewFragments) {
				if (ext.getExtensionParams().get("type").equals("userProperty")) {
					String userPropertyName = ext.getExtensionParams().get("userPropertyName").toString();
					userProperties.put(userPropertyName, user.getUserProperty(userPropertyName));
				}
			}
			simpleUser.put("userProperties", userProperties);
			simpleUser.put("retired", user.getRetired());
			
			SimpleObject simpleUserCapabilities = new SimpleObject();
			Set<Role> userCapabilities = account.getCapabilities(user);
			for (Role cap : capabilities) {
				simpleUserCapabilities.put(cap.getUuid(), userCapabilities.contains(cap));
			}
			simpleUser.put("capabilities", simpleUserCapabilities);
			
			so.put(user.getUuid(), simpleUser);
		}
		model.addAttribute("uuidAndUserMapJson", mapper.writeValueAsString(so));
		
		so = new SimpleObject();
		for (ProviderRole pr : providerRoles) {
			so.put(pr.getUuid(), uu.format(pr));
		}
		model.addAttribute("providerRolesJson", mapper.writeValueAsString(so));
		
		so = new SimpleObject();
		for (OpenmrsObject o : account.getProviderAccounts()) {
			Provider p = (Provider) o;
			SimpleObject simpleProvider = new SimpleObject();
			simpleProvider.put("uuid", p.getUuid());
			simpleProvider.put("identifier", p.getIdentifier());
			simpleProvider.put("providerRole", (p.getProviderRole() != null) ? p.getProviderRole().getUuid() : "");
			simpleProvider.put("retired", p.isRetired());
			
			so.put(p.getUuid(), simpleProvider);
		}
		model.addAttribute("uuidAndProviderJson", mapper.writeValueAsString(so));
		
		so = new SimpleObject();
		so.put("savedChanges", uu.message("adminui.savedChanges"));
		so.put("saved", uu.message("adminui.saved"));
		so.put("retired", uu.message("adminui.retired"));
		so.put("restored", uu.message("adminui.restored"));
		so.put("changedByOn", uu.message("adminui.changedByOn"));
		so.put("auditInfoFail", uu.message("adminui.getAuditInfo.fail"));

		so = new SimpleObject();
		for(Extension ext : customPersonAttributeEditFragments) {
			Object type = ext.getExtensionParams().get("type");
			Object personAttributeTypeUuid = ext.getExtensionParams().get("uuid");
			Person person = account.getPerson();
			if (person != null && type != null && personAttributeTypeUuid != null &&
					type.toString().equals("personAttribute")) {
				String formFieldName = ext.getExtensionParams().get("formFieldName").toString();
				PersonAttribute personAttribute = person.getAttribute(Context.getPersonService()
						.getPersonAttributeTypeByUuid(personAttributeTypeUuid.toString()));
				if(personAttribute != null) {
					String personAttributeUuid = personAttribute.getUuid();
					SimpleObject personAttributeInfo = new SimpleObject();
					personAttributeInfo.put("formFieldName", formFieldName);
					personAttributeInfo.put("personAttributeUuid", personAttributeUuid);
					so.put("personAttributeInfo", personAttributeInfo);
				}
			}
		}
		model.addAttribute("customPersonAttributeJson", mapper.writeValueAsString(so));

		model.addAttribute("messages", mapper.writeValueAsString(so));
	}
	
	private void sendErrorMessage(Errors errors, PageModel model, MessageSourceService mss, HttpServletRequest request) {
		model.addAttribute("errors", errors);
		StringBuffer errorMessage = new StringBuffer(mss.getMessage("error.failed.validation"));
		errorMessage.append("<ul>");
		for (ObjectError error : errors.getAllErrors()) {
			errorMessage.append("<li>");
			errorMessage.append(mss.getMessage(error.getCode(), error.getArguments(), error.getDefaultMessage(), null));
			errorMessage.append("</li>");
		}
		errorMessage.append("</ul>");
		request.getSession().setAttribute(UiCommonsConstants.SESSION_ATTRIBUTE_ERROR_MESSAGE, errorMessage.toString());
	}
	
	private void setJsonFormData(PageModel model, Account account, OtherAccountData otherAccountData, UiUtils uu) throws IOException {
		
		ObjectMapper mapper = new ObjectMapper();
		SimpleObject simplePerson = new SimpleObject();
		simplePerson.put("familyName", uu.encodeHtml(account.getFamilyName()));
		simplePerson.put("givenName", uu.encodeHtml(account.getGivenName()));
		simplePerson.put("gender", uu.encodeHtml(account.getGender()) != null ? uu.encodeHtml(account.getGender()) : "");
		model.addAttribute("personJson", mapper.writeValueAsString(simplePerson));
		
		SimpleObject simpleUser = new SimpleObject();
		SimpleObject simpleProvider = new SimpleObject();
		if (otherAccountData != null) {
			if (otherAccountData.getAddUserAccount()) {
				User u = account.getUserAccounts().get(0);
				simpleUser.put("username", uu.encodeHtml(u.getUsername()));
				simpleUser.put("privilegeLevel", account.getPrivilegeLevel(u).getUuid());
				SimpleObject userProperties = new SimpleObject();
				userProperties
				        .put(OpenmrsConstants.USER_PROPERTY_CHANGE_PASSWORD, otherAccountData.getForceChangePassword());
				simpleUser.put("userProperties", userProperties);
				SimpleObject simpleUserCapabilities = new SimpleObject();
				for (Role cap : account.getCapabilities(u)) {
					simpleUserCapabilities.put(cap.getUuid(), true);
				}
				simpleUser.put("capabilities", simpleUserCapabilities);
			}
			
			if (otherAccountData.getAddProviderAccount()) {
				Provider prov = (Provider) account.getProviderAccounts().get(0);
				simpleProvider.put("identifier", prov.getIdentifier());
				simpleProvider.put("providerRole", prov.getProviderRole().getUuid());
			}
		}
		
		model.addAttribute("userJson", mapper.writeValueAsString(simpleUser));
		model.addAttribute("providerJson", mapper.writeValueAsString(simpleProvider));
	}
	
}

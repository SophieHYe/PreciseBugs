<%
    ui.decorateWith("appui", "standardEmrPage", [ title: ui.message("referenceapplication.app.userApp."+param.action[0]) ])

    ui.includeJavascript("referenceapplication", "userApp.js");
%>

<script type="text/javascript">
    var breadcrumbs = [
        { icon: "icon-home", link: '/' + OPENMRS_CONTEXT_PATH + '/index.htm' },
        { label: "${ ui.message("coreapps.app.systemAdministration.label")}",
            link: "${ui.pageLink("coreapps", "systemadministration/systemAdministration")}"
        },
        { label: "${ ui.message("referenceapplication.app.manageApps.title")}",
            link: "${ui.pageLink("referenceapplication", "manageApps")}"
        },
        { label: "${ ui.message("referenceapplication.app.userApp."+param.action[0])}"}
    ];

    jq(function(){
        setAction('${param.action[0]}');
    });
</script>

<h2>${ ui.message("referenceapplication.app.userApp."+param.action[0])}</h2>

<form class="simple-form-ui" method="POST" action="userApp.page">
    <span id="errorMsg" class="field-error" style="display: none">
        ${ui.message("referenceapplication.app.errors.invalidJson")}
    </span>
    <span id="server-error-msg" class="field-error" style="display: none">
        ${ui.message("referenceapplication.app.errors.serverError")}
    </span>
    <input type="hidden" name="action" value="${param.action[0]}" />
    <p>
        <%if(param.action[0] == 'edit'){%>
        <span class="title">
        ${ui.message("referenceapplication.app.appId.label")}:
        </span>&nbsp;${ui.escapeHtml(userApp.appId)}
        <input class="form-control form-control-sm form-control-lg form-control-md" id="appId-field-hidden" type="hidden" name="appId" value="${userApp.appId ? userApp.appId : ""}" />
        <%} else{%>
        <label for="appId-field">
            <span class="title">
                ${ui.message("referenceapplication.app.appId.label")} (${ ui.message("coreapps.formValidation.messages.requiredField.label") })
            </span>
        </label>
        <input class="form-control form-control-sm form-control-lg form-control-md required" id="appId-field" type="text" name="appId" value="${userApp.appId ? ui.encodeJavaScript(ui.escapeHtml(userApp.appId)) : ""}" size="80" placeholder="${ ui.message("referenceapplication.app.definition.placeholder") }" />
        <%}%>
    </p>
    <p>
        <label for="json-field">
            <span class="title">
            ${ui.message("referenceapplication.app.definition.label")} (${ ui.message("coreapps.formValidation.messages.requiredField.label") })
            </span>
        </label>
        <textarea class="form-control form-control-sm form-control-lg form-control-md required" id="json-field" name="json" rows="15" cols="80">${userApp.json ? userApp.json : ""}</textarea>
    </p>

    <input type="button" class="cancel" value="${ ui.message("general.cancel") }" onclick="javascript:window.location='/${ contextPath }/referenceapplication/manageApps.page'" />
    <input type="submit" class="confirm right" id="save-button" value="${ ui.message("general.save") }" disabled="disabled" />
</form>
<%
    ui.decorateWith("appui", "standardEmrPage")

    ui.includeJavascript("adminui", "jquery.validate.js")

    ui.includeCss("adminui", "adminui.css")

    def createPrivilege = ("add" == param.action[0]);
%>

<script type="text/javascript">
    var breadcrumbs = [
        { icon: "icon-home", link: '/' + OPENMRS_CONTEXT_PATH + '/index.htm' },
        { label: "${ ui.message('adminui.app.configureMetadata.label')}" , link: '${ui.pageLink("adminui", "metadata/configureMetadata")}'},
        { label: "${ ui.message("adminui.managePrivileges.title")}", link: '${ ui.pageLink("adminui", "metadata/privileges/managePrivileges") }' },
        { label: "${ ui.message((createPrivilege) ? "adminui.addNewPrivilege.label" : "adminui.editPrivilege.label")}" }
    ];
</script>

<script type="text/javascript">

    jq().ready(function () {

        jq("#privilegeForm").validate({
            rules: {
                "privilege": {
                    required: true,
                    minlength: 3,
                    maxlength: 255
                },
                "description": {
                    required: false,
                    maxlength: 1024
                }
            },
            errorClass: "error",
            validClass: "",
            onfocusout: function (element) {
                jq(element).valid();
            },
            errorPlacement: function (error, element) {
                element.next().text(error.text());
            },
            highlight: function (element, errorClass, validClass) {
                jq(element).addClass(errorClass);
                jq(element).next().addClass(errorClass);
                jq(element).next().show();
            },
            unhighlight: function (element, errorClass, validClass) {
                jq(element).removeClass(errorClass);
                jq(element).next().removeClass(errorClass);
                jq(element).next().hide();
            }
        });
    });
</script>

<h1>
    <h3>${ ui.message((createPrivilege) ? "adminui.addNewPrivilege.label" : "adminui.editPrivilege.label")}</h3>
</h1>

<form class="simple-form-ui" method="post" id="privilegeForm" autocomplete="off">

<fieldset>
    <% if(createPrivilege){ %>
        ${ui.includeFragment("uicommons", "field/text", [
            label        : ui.message("general.name")+"<span class='adminui-text-red'>*</span>",
            formFieldName: "privilege",
            id           : "privilege",
            maxLength    : 101,
            initialValue : ui.encodeHtmlContent(privilege.privilege)
        ])}
    <% } else{ %>
        <b>${ui.message("general.name")}:</b> ${ui.encodeHtmlContent(privilege.privilege)}
        <input type="hidden" name="privilegeName" value="${ui.encodeHtmlAttribute(privilege.privilege)}" />
    <% } %>
    ${ui.includeFragment("uicommons", "field/textarea", [
            label        : ui.message("general.description"),
            formFieldName: "description",
            id           : "description",
            initialValue : ui.encodeHtmlContent((privilege.description) ? privilege.description.trim() : "")
    ])}

    <div>
        <input type="button" class="cancel" value="${ui.message("general.cancel")}" onclick="window.location='/${ contextPath }/adminui/metadata/privileges/managePrivileges.page'"/>
        <input type="submit" class="confirm" id="save-button" value="${ui.message("general.save")}"/>
    </div>
    </fieldset>
</form>

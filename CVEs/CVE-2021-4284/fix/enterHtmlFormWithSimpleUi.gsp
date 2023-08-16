<%
    ui.decorateWith("appui", "standardEmrPage")
    ui.includeJavascript("uicommons", "navigator/validators.js", Integer.MAX_VALUE - 19)
    ui.includeJavascript("uicommons", "navigator/navigator.js", Integer.MAX_VALUE - 20)
    ui.includeJavascript("uicommons", "navigator/navigatorHandlers.js", Integer.MAX_VALUE - 21)
    ui.includeJavascript("uicommons", "navigator/navigatorModels.js", Integer.MAX_VALUE - 21)
    ui.includeJavascript("uicommons", "navigator/navigatorTemplates.js", Integer.MAX_VALUE - 21)
    ui.includeJavascript("uicommons", "navigator/exitHandlers.js", Integer.MAX_VALUE - 22)
    ui.includeJavascript("uicommons", "angular.min.js");
    ui.includeJavascript("uicommons", "angular-resource.min.js");
    ui.includeJavascript("htmlformentryui", "htmlFormSimple.js", Integer.MIN_VALUE)
    ui.includeCss("htmlformentryui", "htmlform/htmlFormSimple.css")
    def createNewVisit = createVisit ?: false

    def breadcrumbMiddle = breadcrumbOverride ?: """
        [ { label: '${ returnLabel }' , link: '${ returnUrl }'} ]
    """
%>

${ ui.includeFragment("uicommons", "validationMessages")}

${ ui.includeFragment("coreapps", "patientHeader", [ patient: patient ]) }

<script type="text/javascript">

    // we expose this as a global variable so that HTML forms can call the API methods associated with the Keyboard Controller
    // TODO expose this some other way than a global variable so we can support multiple navigators (if that will ever be needed)
    var NavigatorController;

    var breadcrumbs = _.flatten([
        { icon: "icon-home", link: '/' + OPENMRS_CONTEXT_PATH + '/index.htm' },
        ${ ui.encodeHtmlContent(breadcrumbMiddle) } ,
        { label: "${ ui.encodeJavaScript(ui.format(htmlForm.form)) }" }
    ]);

    jQuery(function() {
        jq('input.submitButton').hide();
        jq('form#htmlform').append(jq('#confirmation-template').html());
        NavigatorController =  KeyboardController(jq('#htmlform').first());

        jq('input.confirm').click(function(){

            if (!jq(this).attr("disabled")) {
                jq(this).closest("form").submit();
            }

            jq(this).attr('disabled', 'disabled');
            jq(this).addClass("disabled");

        });

        // clicking the save form link should have the same functionality as clicking on the confirmation section title (ie, jumps to confirmation)
        jq('#save-form').click(function() {
            NavigatorController.getSectionById("confirmation").title.click();
        })

    });
</script>

<div id="form-actions-container">
    <a href="#" id="save-form">
        <i class="icon-save small"></i>
        ${ ui.message("htmlformentryui.saveForm") }
    </a>
    <% if (returnUrl) { %>
    <a href="${ ui.escapeAttribute(returnUrl) }">
        <i class="icon-signout small"></i>
        ${ ui.message("htmlformentryui.exitForm") }
    </a>
    <% } %>
</div>

${ ui.includeFragment("htmlformentryui", "htmlform/enterHtmlForm", [
        patient: patient,
        htmlForm: htmlForm,
        visit: visit,
        createVisit: createNewVisit,
        returnUrl: returnUrl,
        automaticValidation: false,
        cssClass: "simple-form-ui"
]) }

<script type="text/template" id="confirmation-template">
    <div id="confirmation" class="container">
        <span class="title">${ ui.message("coreapps.simpleFormUi.confirm.title") }</span>

        <div id="confirmationQuestion">
            <h3>${ ui.message("coreapps.simpleFormUi.confirm.question") }</h3>

            <div id="confirmation-messages"></div>

            <div class="before-dataCanvas"></div>
            <div id="dataCanvas"></div>
            <div class="after-data-canvas"></div>

            <p style="display: inline">
                <button type="submit" onclick="submitHtmlForm()" class="submitButton confirm right">
                    ${ ui.message("coreapps.save") }
                    <i class="icon-spinner icon-spin icon-2x" style="display: none; margin-left: 10px;"></i>
                </button>
            </p>
            <p style="display: inline">
                <input type="button" value="${ ui.message("coreapps.no") }" class="cancel" />
            </p>
            <p>
                <span class="error field-error">${ ui.message("coreapps.simpleFormUi.error.emptyForm") }</span>
            </p>
        </div>
    </div>
</script>

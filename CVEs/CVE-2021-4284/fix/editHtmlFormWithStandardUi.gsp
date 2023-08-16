<%
    ui.decorateWith("appui", "standardEmrPage")

    def breadcrumbMiddle = breadcrumbOverride ?: """
        [ { label: '${ returnLabel }' , link: '${ returnUrl }'} ]
    """
%>

<script type="text/javascript">
    var breadcrumbs = _.flatten([
        { icon: "icon-home", link: '/' + OPENMRS_CONTEXT_PATH + '/index.htm' },
        ${ breadcrumbMiddle },
        { label: "${ ui.encodeJavaScript(ui.message("coreapps.editHtmlForm.breadcrumb", ui.message(ui.format(htmlForm.form)))) }" }
    ]);

    jq(function() {
        jq('.cancel').click(function(event) {
            event.preventDefault();
            htmlForm.cancel();
        });
    });
</script>

${ ui.includeFragment("coreapps", "patientHeader", [ patient: patient ]) }

${ ui.includeFragment("htmlformentryui", "htmlform/enterHtmlForm", [
        visit: encounter.visit,
        encounter: encounter,
        patient: patient,
        returnUrl: returnUrl,
        definitionUiResource: definitionUiResource ?: ""

]) }

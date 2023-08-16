<%
    ui.decorateWith("appui", "standardEmrPage")

    ui.includeCss("adminui", "adminui.css")

    ui.includeJavascript("adminui", "jquery.validate.js")

    def createLocation = (location.locationId == null ? true : false);

    def parentLocationOptions = []
    existingLocations.each {
        parentLocationOptions.push([ label: ui.escapeJs(ui.format(it)), value: it.id ])
    }
%>

<script type="text/javascript">
    var breadcrumbs = [
        { icon: "icon-home", link: '/' + OPENMRS_CONTEXT_PATH + '/index.htm' },
        { label: "${ ui.message('adminui.app.configureMetadata.label')}" , link: '${ui.pageLink("adminui", "metadata/configureMetadata")}'},
        { label: "${ ui.message("adminui.manageLocations.label")}", link: '${ui.pageLink("adminui","metadata/locations/manageLocations")}' },
        { label: "${ (createLocation) ? ui.message("adminui.createLocation.locationManagement.label") : ui.message("adminui.editLocation.locationManagement.label")}" }
    ];

</script>

<script type="text/javascript">

    jq().ready(function () {

        jq("#locationForm").validate({
            rules: {
                "name": {
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

<h2>${ (createLocation) ? ui.message("adminui.addNewLocation.label") : ui.message("adminui.editLocation.label") }</h2>


<form class="simple-form-ui" method="post" id="locationForm" autocomplete="off">
<fieldset>
    ${ui.includeFragment("uicommons", "field/text", [
            label        : ui.message("general.name")+"*",
            formFieldName: "name",
            id           : "name",
            maxLength    : 101,
            initialValue : ui.encodeHtml((location.name ?: ''))
    ])}

    ${ui.includeFragment("uicommons", "field/textarea", [
            label        : ui.message("general.description"),
            formFieldName: "description",
            id           : "description",
            initialValue : ui.encodeHtml((location.description ?: ''))
    ])}

    <p>
	${ui.includeFragment("uicommons", "field/text", [
            label        : ui.message("adminui.location.address1"),
            formFieldName: "address1",
            id           : "address1",
            initialValue : ui.encodeHtml((location.address1 ?: ''))
    ])}

	${ui.includeFragment("uicommons", "field/text", [
            label        : ui.message("adminui.location.address2"),
            formFieldName: "address2",
            id           : "address2",
            initialValue : ui.encodeHtml((location.address2 ?: ''))
    ])}

    ${ui.includeFragment("uicommons", "field/text", [
            label        : ui.message("adminui.location.city_village"),
            formFieldName: "cityVillage",
            id           : "cityVillage",
            initialValue : ui.encodeHtml((location.cityVillage ?: ''))
    ])}

    ${ui.includeFragment("uicommons", "field/text", [
            label        : ui.message("adminui.location.state_province"),
            formFieldName: "stateProvince",
            id           : "stateProvince",
            initialValue : ui.encodeHtml((location.stateProvince ?: ''))
    ])}


    ${ui.includeFragment("uicommons", "field/text", [
            label        : ui.message("adminui.location.country"),
            formFieldName: "country",
            id           : "country",
            initialValue : ui.encodeHtml((location.country ?: ''))
    ])}

    ${ui.includeFragment("uicommons", "field/text", [
            label        : ui.message("adminui.location.postalCode"),
            formFieldName: "postalCode",
            id           : "postalCode",
            initialValue : ui.encodeHtml((location.postalCode ?: ''))
    ])}
    </p>

    ${ ui.includeFragment("uicommons", "field/dropDown", [
            label: ui.message("adminui.location.parentLocation"),
            emptyOptionLabel: ui.message("adminui.chooseOne"),
            formFieldName: "parentLocation",
            options: parentLocationOptions,
            initialValue : (location.parentLocation?.id  ?: '')
    ])}

    <% attributeTypes.each{ %>
	${ ui.includeFragment("uicommons", "field/text", [
	       	label: ui.encodeHtml(ui.format(it)),
	        formFieldName: "attribute."+it.id+"",
		    value: it.id,
		    checked: false
    ])}
    <% } %>

    <label>${ui.message("adminui.location.tags")}</label>
    <br>
	<table class="adminui-display-table" cellspacing="0" cellpadding="0">
		<tr>
    	<% locationTags.eachWithIndex { tag, index -> %>
			<td valign="top">
		        <input type="checkbox" name="locTags" value="${tag.id}"
                    <% if (location.tags && location.tags.contains(tag)) { %> checked='checked'<% } %>> ${ui.encodeHtml(ui.format(tag))}
	        </td>
	        <% if ((index + 1) % 2 == 0) { %> </tr> <tr> <% } %>
    	<% } %>
    	</tr>
	</table>

    </fieldset>

    <div>
        <input type="button" class="cancel left" value='${ui.message("general.cancel")}' onclick='window.location="/${ contextPath }/adminui/metadata/locations/manageLocations.page"' />
        <input type="submit" class="confirm right" name="save" id="save-button" value="${ui.message("general.save")}"/>
    </div>

</form>

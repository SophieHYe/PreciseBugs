<%@page
    pageEncoding="UTF-8"
    buffer="none"
    session="false"
    trimDirectiveWhitespaces="true"%>

<%@ taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core" %>
<%@ taglib prefix="cms" uri="http://www.opencms.org/taglib/cms"%>
<%@ taglib prefix="fn" uri="http://java.sun.com/jsp/jstl/functions"%>
<%@ taglib prefix="fmt" uri="http://java.sun.com/jsp/jstl/fmt"%>
<%@ taglib prefix="mercury" tagdir="/WEB-INF/tags/mercury" %>

<mercury:init-messages>

<cms:formatter var="content" val="value">

<c:set var="setting"                    value="${cms.element.setting}" />
<c:set var="cssWrapper"                 value="${setting.cssWrapper}" />
<c:set var="cssVisibility"              value="${setting.cssVisibility.toString != 'always' ? setting.cssVisibility.toString : ''}" />
<c:set var="breadcrumbsIncludeHidden"   value="${setting.breadcrumbsIncludeHidden.toBoolean}" />
<c:set var="breadcrumbsFullPath"        value="${setting.breadcrumbsFullPath.toBoolean}" />
<c:set var="breadcrumbsFromRoot"        value="${setting.breadcrumbsFromRoot.toBoolean}" />

<mercury:nl />
<div class="element type-nav-breadcrumbs ${cssWrapper}${' '}${cssVisibility}"><%----%>
<mercury:nl />

    <mercury:nav-vars params="${param}">
        <mercury:nav-items
            type="${breadcrumbsFromRoot ? 'rootBreadCrumb' : 'breadCrumb'}"
            content="${content}"
            currentPageFolder="${currentPageFolder}"
            currentPageUri="${currentPageUri}" >

            <ul class="nav-breadcrumbs"><%----%>
                <mercury:nl />
                <c:set var="currNavPos" value="1" />

                <cms:jsonarray var="breadCrumbJson">

                    <c:forEach var="navElem" items="${navItems}" varStatus="status">
                        <c:if test="${
                            ((breadcrumbsIncludeHidden or (status.last and not cms.detailRequest)) and (navElem.navPosition > 0))
                            or (navElem.info ne 'ignoreInDefaultNav')}">
                            <c:set var="navText" value="${(empty navElem.navText or fn:startsWith(navElem.navText, '???'))
                                ? navElem.title : navElem.navText}" />
                            <c:if test="${!empty navText}">
                                <c:set var="navLink"><cms:link>${navElem.resourceName}</cms:link></c:set>
                                <c:if test="${breadcrumbsFullPath or (navLink ne lastNavLink)}">
                                    <c:set var="lastNavLink" value="${navLink}" />
                                    <c:out value='<li><a href="${navLink}">' escapeXml="false" />
                                    <c:out value='${navText}' escapeXml="true" />
                                    <c:out value='</a></li>' escapeXml="false" />
                                    <mercury:nl />

                                    <cms:jsonobject>
                                        <cms:jsonvalue key="@type" value="ListItem" />
                                        <cms:jsonvalue key="position" value="${currNavPos}" />
                                        <cms:jsonvalue key="name" value="${navText}" />
                                        <cms:jsonvalue key="item" value="${cms.site.url}${navLink}" />
                                    </cms:jsonobject>

                                    <c:set var="currNavPos" value="${currNavPos + 1}" />
                                </c:if>
                            </c:if>
                        </c:if>
                    </c:forEach>

                    <c:if test="${cms.detailRequest}">
                        <c:set var="navLink"><cms:link>${cms.detailContent.sitePath}?${pageContext.request.queryString}</cms:link></c:set>
                        <c:set var="navText"><mercury:meta-title addIntro="${true}" /></c:set>

                        <c:out value='<li><a href="${navLink}">' escapeXml="false" />
                        <c:out value='${navText}' escapeXml="true" />
                        <c:out value='</a></li>' escapeXml="false" />

                        <cms:jsonobject>
                            <cms:jsonvalue key="@type" value="ListItem" />
                            <cms:jsonvalue key="position" value="${currNavPos}" />
                            <cms:jsonobject key="item">
                                <cms:jsonvalue key="@id" value="${cms.site.url}${navLink}" />
                                <cms:jsonvalue key="name" value="${navText}" />
                            </cms:jsonobject>
                        </cms:jsonobject>

                    </c:if>

                </cms:jsonarray>

                <c:if test="${(empty breadCrumbJson) or cms.modelGroupPage}">
                    <li><mercury:meta-title addIntro="${true}" /></li><%----%>
                </c:if>
            </ul><%----%>
            <mercury:nl />

            <c:if test="${not empty breadCrumbJson}">
                <cms:jsonobject var="jsonLd">
                    <cms:jsonvalue key="@context" value="http://schema.org" />
                    <cms:jsonvalue key="@type" value="BreadcrumbList" />
                    <cms:jsonvalue key="itemListElement" value="${breadCrumbJson.json}" />
                </cms:jsonobject>
                <script type="application/ld+json">${jsonLd.compact}</script><%----%>
                <mercury:nl />
            </c:if>

        </mercury:nav-items>
    </mercury:nav-vars>

</div><%----%>
<mercury:nl />

</cms:formatter>
</mercury:init-messages>
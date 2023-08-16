<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<%@ page contentType="text/html; charset=utf-8" pageEncoding="utf-8" %>
<%--
 -
 -  cilla - Blog Management System
 -
 -  Copyright (C) 2013 Richard "Shred" Körber
 -    http://cilla.shredzone.org
 -
 -  This program is free software: you can redistribute it and/or modify
 -  it under the terms of the GNU Affero General Public License as published
 -  by the Free Software Foundation, either version 3 of the License, or
 -  (at your option) any later version.
 -
 -  This program is distributed in the hope that it will be useful,
 -  but WITHOUT ANY WARRANTY; without even the implied warranty of
 -  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 -  GNU General Public License for more details.
 -
 -  You should have received a copy of the GNU Affero General Public License
 -  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 -
 --%>
<%@ taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core" %>
<%@ taglib prefix="fmt" uri="http://java.sun.com/jsp/jstl/fmt" %>
<%@ taglib prefix="cilla" uri="http://cilla.shredzone.org/taglib/cilla" %>
<%@ taglib prefix="sz" tagdir="/WEB-INF/tags/sz" %>
<fmt:setBundle basename="messages"/>
<c:set var="headTitle" value="${search.title}" scope="request"/>
<c:import url="/WEB-INF/jsp/design/header.jspf"/>
<c:import url="/WEB-INF/jsp/design/sidebar-left.jsp"/>

<div id="content" class="content">
  <cilla:link view="search" var="searchUrl"/>

  <div class="page-toolbar">
    <form action="<c:url value="${searchUrl}"/>" method="get"><fmt:message key="search.term"/> <input style="width:50%" type="text" name="q" value="<c:out value="${query}"/>" /><button class="button" type="submit"><fmt:message key="search.execute"/></button></form>
  </div>

  <c:if test="${not empty message}">
    <p>
      <fmt:message key="${message}">
        <fmt:param value="${details}"/>
      </fmt:message>
    </p>
  </c:if>

  <c:if test="${empty result.pages and empty message}">
    <p>
      <fmt:message key="search.msg.empty"/>
    </p>
  </c:if>

  <cilla:render fragment="paginator" item="${paginator}" rendered="${paginator.pageCount gt 1}">
    <cilla:param name="filter" value="${result.filter}"/>
  </cilla:render>

  <c:forEach var="page" items="${result.pages}" varStatus="pageStatus">
    <h1><cilla:link page="${page}"><c:out value="${page.title}"/></cilla:link></h1>
    <c:out value="${result.highlighted[pageStatus.index]}" escapeXml="false"/>
  </c:forEach>

  <cilla:render fragment="paginator" item="${paginator}" rendered="${paginator.pageCount gt 1}">
    <cilla:param name="filter" value="${result.filter}"/>
  </cilla:render>

</div>
<c:import url="/WEB-INF/jsp/design/sidebar-right.jsp"/>
<c:import url="/WEB-INF/jsp/design/footer.jspf"/>

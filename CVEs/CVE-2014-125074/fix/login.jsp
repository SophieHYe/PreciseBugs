
<%@ taglib uri="http://java.sun.com/jsp/jstl/core" prefix="c"%>
<%@ page language="java" contentType="text/html; charset=ISO-8859-1"
	pageEncoding="ISO-8859-1"%>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
<title>Voyager &#124; Login</title>
<link rel="stylesheet"
	href="${pageContext.request.contextPath}/resources/main.css">
<link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css'>
</head>
<body>

	<header>
		<nav>
			<ul>
				<li>Home</li> &bull;
				<li>Profile</li> &bull;
				<li>Submit</li> &bull;
				<li>Search</li>
			</ul>
		</nav>
	</header>

	<article class="bodyContainer">
		<h1>Register</h1>
		<form method="get" action="<%=request.getContextPath()%>/register">
			<input type="submit" value="Register" id="submit" />
		</form>
		<br /> <br />
		<hr />
		<h1>Login</h1>
		<p><%= (request.getAttribute("errorMessage") == null ? "" : request.getAttribute("errorMessage")) %></p>
		<form method="post">
			<label>Username:</label> <input name="username" /> <label>Password:</label>
			<input name="password" type="password" /> <input type="submit"
				value="Login" id="submit" />
		</form>
	</article>

</body>
</html>
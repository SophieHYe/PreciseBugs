<%@page import="models.RegisterUserModel"%>
<%@page import="models.Account"%>
<%@ page language="java" contentType="text/html; charset=ISO-8859-1"
	pageEncoding="ISO-8859-1"%>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
<title>Voyager &#124; Register</title>
<link rel="stylesheet" href="${pageContext.request.contextPath}/resources/main.css">
<link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css'>
</head>
<body>
<% Account current = (request.getAttribute("attemptedAccount") == null) ? new Account() : (Account)request.getAttribute("attemptedAccount"); %>

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
		<h1>Register New User</h1>
		<p><%=(((RegisterUserModel)request.getAttribute("errorMessage")).getErrorMessage() == null ? "" : ((RegisterUserModel)request.getAttribute("errorMessage")).getErrorMessage()) %></p>
		<form method="post" enctype="multipart/form-data">
			<label>Username:</label> <input name="username" type="text" value="<%= current.getUsername() %>"/>
			<label>Password:</label><input	name="password" type="password" /> 
			<label>Confirm Password:</label><input name="confirmPassword" type="password" />
			<label>Email Address:</label><input name="email" type="email" value="<%=current.getEmail() %>"/>
			<label>Confirm Email:</label><input name="confirmEmail" type="email" />
			<label>Profile Image:</label><input name="image" type="file"/>
			<input type="submit" value="Register" id="submit" />
		</form>

	</article>
</body>
</html>
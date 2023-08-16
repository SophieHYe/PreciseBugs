<%@ page contentType="text/html;charset=UTF-8" language="java" %>
<!DOCTYPE html>
<html>
<head>
    <title>Search for/Add contact</title>
    <link rel="stylesheet" type="text/css" href="styles.css"/>
    <script type="text/javascript" src="http://code.jquery.com/jquery-latest.js"></script>
    <script type="text/javascript">
    $( ".reset" ).click(function() {
    document.getElementByClassName("result-table").remove();
    </script>
</head>
<body>
<a id="back" href="email.html">Back</a>
<div class="logout-container">
    <form action="logout" method="post">
        <input id="logout" type="submit" value="Log Out"/>
    </form>
</div>
<section class="main">
    <h1>Search for a contact</h1>

    <p>Enter a forename and/or surname and click search!</p>

    <form action="searchcontact" method="post">
        <table class="form-table">
            <tr>
                <td>
                    <b>Forename: </b>
                </td>
                <td>
                    <input id="name" name="forename" type="text" placeholder="...">
                </td>
            </tr>
            <tr>
                <td>
                    <b>Surname: </b>
                </td>
                <td>
                    <input id="surname" name="surname" type="text" placeholder="...">
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <input type="submit" value="Submit">
                    <button class="reset" type="reset" value="Reset">Reset</button>
                </td>
            </tr>
        </table>
    </form>
    <%=request.getSession().getAttribute("results")%>
</section>
</body>
</html>

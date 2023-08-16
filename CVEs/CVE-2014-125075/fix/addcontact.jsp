<%@ page contentType="text/html;charset=UTF-8" language="java" %>
<!DOCTYPE html>
<html>
<head>
    <title>Search for/Add contact</title>
    <link rel="stylesheet" type="text/css" href="styles.css"/>
    <script type="text/javascript" src="http://code.jquery.com/jquery-latest.js"></script>
    <script type="text/javascript">
      $(document).ready( function() {
        $('#success').delay(1000).fadeOut();
      });
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
    <h1>Add a contact</h1>
    <p>Enter a forename, surname and email and click add!</p>
    <form action="addcontact" method="post">
        <table class="form-table">
            <tr>
                <td>
                    <b>Forename: </b>
                </td>
                <td>
                    <input id="name" name="firstname" type="text" placeholder="..." required>
                </td>
            </tr>
            <tr>
                <td>
                    <b>Surname: </b>
                </td>
                <td>
                    <input id="surname" name="secondname" type="text" placeholder="...">
                </td>
            </tr>
            <tr>
                <td>
                    <b>Email Address: </b>
                </td>
                <td>
                    <input id="email" name="email" type="email" placeholder="..." required>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <input type="submit" value="Submit">
                    <button type="reset" value="Reset">Reset</button>
                </td>
            </tr>
        </table>
    </form>
    <%=request.getSession().getAttribute("success")%>
</section>
</body>
</html>

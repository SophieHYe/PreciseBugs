import java.sql.*;

/**
 * Created by chris on 08/12/14.
 */
public class Model {

    private String DBNAME = "jdbc:postgresql://dbteach2.cs.bham.ac.uk/cxm373";
    private String USERNAME = "cxm373";
    private String PASSWORD = "computer2014";
    private String emailUser;
    private Connection conn;

    public Model(String username) {
        emailUser = username;
        conn = connect();
    }

    public Connection connect() {
        try {
            Class.forName("org.postgresql.Driver");
        } catch (ClassNotFoundException e) {
            System.out.println("Driver not found");
            System.exit(0);
        }

        System.out.println("PostgreSQL driver registered.");

        Connection dbConn = null;

        try {
            dbConn = DriverManager.getConnection(DBNAME, USERNAME, PASSWORD);
        } catch (SQLException e) {
            e.printStackTrace();
        }

        if (dbConn != null) {
            System.out.println("Connected to database successfully.");
        } else {
            System.out.println("Database connection failed.");
        }
        return dbConn;

    }

    public String search(String forename, String surname, String contactemail) throws SQLException {

        String query;
        if (forename.isEmpty() && surname.isEmpty()) {
            query = "";
        } else if (forename.isEmpty()) {
            query = "familyname LIKE '%" + surname + "' and";
        } else if (surname.isEmpty()) {
            query = "forename LIKE '%" + forename + "' and ";
        } else {
            query = "forename LIKE '%" + forename + "' and familyname LIKE '%" + surname + "' and";
        }

        PreparedStatement ps = conn.prepareStatement("SELECT * FROM contactinfo WHERE ? contactemailaddress = ?");
        ps.setString(1, query);
        ps.setString(2, contactemail);
        ResultSet rs = ps.executeQuery();
        StringBuilder result = new StringBuilder("<h3>Search results...</h3><table class=\"result-table\">" +
                "<tr>" +
                "<th>Forename</th> <th>Surname</th> <th>Email</th>" +
                "</tr>");
        while (rs.next())
        {
            result.append("<tr><td>");
            result.append(rs.getString(2));
            result.append("</td><td>" + rs.getString(3));
            result.append("</td><td>" + rs.getString(4) + "</td></tr>");
        }

        result.append("</table");
        conn.close();
        return result.toString();
    }

    public void addContact(String firstname, String surname, String email, String user) throws SQLException {

        PreparedStatement checkDuplicate = conn.prepareStatement("SELECT * FROM contactinfo WHERE emailaddress = ?");
        checkDuplicate.setString(1, email);
        ResultSet rs = checkDuplicate.executeQuery();
        if (rs.next()) {
            throw new SQLException("Contact already exists");
        }
        PreparedStatement newStudent = conn.prepareStatement("INSERT INTO " +
                "contactinfo (forename, familyname, emailaddress, contactemailaddress) VALUES (?, ?, ?, ?)");
        newStudent.setString(1, firstname);
        newStudent.setString(2, surname);
        newStudent.setString(3, email);
        newStudent.setString(4, user);
        newStudent.execute();

        conn.close();
    }
}


//Todo sort out errors, when logging in unsuccessfully etc
//Todo format message sent successfully page
//Todo add some JS to allow user to click search results and send an email to that address

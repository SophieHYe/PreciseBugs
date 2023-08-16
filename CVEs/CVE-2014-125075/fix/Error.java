import javax.servlet.ServletException;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.io.PrintWriter;

public class Error extends HttpServlet {

    /**
     * Calls doPost
     */
    protected void doGet(HttpServletRequest request, HttpServletResponse response) throws ServletException, IOException {
        doPost(request, response);
    }

    /**
     * Gets the PrintWriter from the response and prints the HTML to show the error page
     */
    protected void doPost(HttpServletRequest request, HttpServletResponse response) throws ServletException, IOException {

        PrintWriter out = response.getWriter();    //Gets the PrintWriter
        String back = (String) request.getAttribute("previous");
        out.println(
                "<!DOCTYPE html>" +
                        "<html>" +
                        "<head lang=\"en\">" +
                        "<meta charset=\"UTF-8\">" +
                        "<title>Error Occured</title>" +
                        "</head>" +
                        "<body>" +
                        "<center>" +
                        "<h1>Error Occurred!</h1>" +
                        "<div>" +
                        "<br>" +
                        "Error: " + request.getAttribute("error") + "<br>" + "<br>" + "<br>" +//Gets the error message
                        "</div>" +
                        "<div class='error-actions'>" +
                        "<a href='" + back + "'>Retry</a>" +
                        "</div>" +
                        "</center>" +
                        "</body>" +
                        "</html>"
        );
    }
}

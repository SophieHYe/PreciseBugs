package com.bezman.servlet;

import com.bezman.background.DailySubmission;
import org.json.simple.JSONArray;
import org.json.simple.JSONObject;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestMethod;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseBody;

import javax.servlet.http.Cookie;
import javax.servlet.http.HttpServletRequest;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.Calendar;
import java.util.Date;
import java.util.stream.Collectors;

/**
 * Created by Terence on 11/10/2014.
 */
@Controller
@RequestMapping
public class MonthlyRecapServlet {

    @RequestMapping(value = "/monthlyrecap", method = {RequestMethod.GET})
    public String getMonthlyPage(Model model, HttpServletRequest request){
        Cookie cookie = IndexServlet.getCookie(request.getCookies(), "sessionID");
        if (cookie != null){
            try {
                ResultSet resultSet = IndexServlet.execQuery("select * from sessions where sessionID='" + cookie.getValue() + "'");
                String username = null;

                while(resultSet.next()){
                    model.addAttribute("username", resultSet.getString("username"));
                    username = resultSet.getString("username");
                }

                System.out.println("Username : " + username);
                ResultSet accountSet = IndexServlet.execQuery("select * from accounts where username='" + username + "'");

                while(accountSet.next()){
                    model.addAttribute("role", accountSet.getString("role"));
                }
            } catch (SQLException e) {
                e.printStackTrace();
            }
        }

        return "monthlyrecap";
    }

    @RequestMapping(value = "/monthlyrecapjson", method = {RequestMethod.GET, RequestMethod.POST})
    @ResponseBody
    public String getMonthly(@RequestParam(value = "month", required = false) String month, @RequestParam(value = "day", required = false) String day, @RequestParam (value = "year", required = false) String year, @RequestParam(value = "order", required = false) String order){
        JSONArray jsonArray = new JSONArray();

        Calendar calendar = Calendar.getInstance();
        calendar.setTime(new Date());

        String query = "select * from daily where ";

        ArrayList params = new ArrayList();

        if(month != null)
            params.add("MONTH(date)='" + month + "' ");

        if(day != null)
            params.add("DAY(date)='" + day + "' ");

        if(year != null)
           params.add("YEAR(date)='" + year +"' ");

        if (month == null && day == null && year == null)
            query = "select * from daily";
        else query += params.stream().collect(Collectors.joining(" and "));

        if(order == null)
            order = "";

        query += (" order by date " + order);

        System.out.println(query);

        ResultSet resultSet = null;

        try {
            resultSet = IndexServlet.execQuery(query);
        } catch (SQLException e) {
            e.printStackTrace();
        }

        try {
            while(resultSet.next()){
                DailySubmission submission = DailySubmission.submissionFromRow(resultSet);

                jsonArray.add(submission.toJson());
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }

        return jsonArray.toJSONString();
    }

    @RequestMapping(value = "/deleteperiod", method = {RequestMethod.GET, RequestMethod.POST})
    @ResponseBody
    public String deletePeriodFromDate(@RequestParam("date") String date){
        JSONObject returnJSON = new JSONObject();
        try {
            IndexServlet.execUpdate("delete from daily where date='" + date + "'");
            IndexServlet.execUpdate("delete from sales where date='" + date + "'");
            returnJSON.put("success", "true");
        } catch (SQLException e) {
            e.printStackTrace();
            returnJSON.put("success", "false");
        }

        return returnJSON.toJSONString();
    }

}

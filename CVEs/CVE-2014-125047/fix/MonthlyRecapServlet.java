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
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.Calendar;
import java.util.Date;
import java.util.HashMap;
import java.util.stream.Collectors;

/**
 * Created by Terence on 11/10/2014.
 */
@Controller
@RequestMapping
public class MonthlyRecapServlet {

    @RequestMapping(value = "/monthlyrecap", method = {RequestMethod.GET})
    public String getMonthlyPage(Model model, HttpServletRequest request){
        IndexServlet.servletLoginCheck(model, request);

        return "monthlyrecap";
    }

    @RequestMapping(value = "/monthlyrecapjson", method = {RequestMethod.GET, RequestMethod.POST})
    @ResponseBody
    public String getMonthly(@RequestParam(value = "month", required = false) String month, @RequestParam(value = "day", required = false) String day, @RequestParam (value = "year", required = false) String year, @RequestParam(value = "order", required = false) String order){
        JSONArray jsonArray = new JSONArray();

        Calendar calendar = Calendar.getInstance();
        calendar.setTime(new Date());

        HashMap<Integer, String> valueMap = new HashMap<>();

        String query = "select * from daily where ";

        ArrayList params = new ArrayList();

        int predCount = 1;

        if (month != null) {
            query += "MONTH(date)=?";

            valueMap.put(predCount, month);

            predCount++;
        }

        if (day != null) {

            if (predCount > 1)
                query += " and ";

            query += "DAY(date)=?";

            valueMap.put(predCount, day);

            predCount++;
        }

        if (year != null) {

            if (predCount > 1)
                query += " and ";

            query += "YEAR(date)=?";

            valueMap.put(predCount, year);

            predCount++;
        }

        if (month == null && day == null && year == null)
            query = "select * from daily";
        else query += params.stream().collect(Collectors.joining(" and "));

        if(order == null)
            order = "";

        query += (" order by date " + order);

        try {

            PreparedStatement statement = IndexServlet.connection.prepareStatement(query);

            for(Integer integer : valueMap.keySet()){
                statement.setString(integer, valueMap.get(integer));
            }

            System.out.println(statement);

            ResultSet resultSet = statement.executeQuery();

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
            PreparedStatement deleteDaily = IndexServlet.connection.prepareStatement("DELETE from daily where date=?");
            deleteDaily.setString(1, date);

            deleteDaily.executeUpdate();

            PreparedStatement deleteSales = IndexServlet.connection.prepareStatement("DELETE from sales where date=?");
            deleteSales.setString(1, date);

            deleteSales.executeUpdate();
            returnJSON.put("success", "true");
        } catch (SQLException e) {
            e.printStackTrace();
            returnJSON.put("success", "false");
        }

        return returnJSON.toJSONString();
    }

}

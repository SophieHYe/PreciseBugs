package com.bezman.servlet;

import org.json.simple.JSONArray;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestMethod;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseBody;

import javax.servlet.http.HttpServletRequest;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.Calendar;
import java.util.List;

/**
 * Created by Terence on 11/11/2014.
 */
@Controller
@RequestMapping
public class Attendance {

    @RequestMapping(value = "/attendancejson", method = {RequestMethod.GET})
    @ResponseBody
    public String getAttendanceJSON(Model model, HttpServletRequest request, @RequestParam(value = "month", required = false) String month, @RequestParam(value = "day", required = false) String day, @RequestParam(value = "year", required = false) String year){

        List<String> list = new ArrayList<>();

        Calendar calendar = Calendar.getInstance();

        if(month == null)
            month = String.valueOf(calendar.get(Calendar.MONTH) + 1);

        if(day == null)
            day = String.valueOf(calendar.get(Calendar.DAY_OF_MONTH));

        if (year == null)
            year = String.valueOf(calendar.get(Calendar.YEAR));

        JSONArray jsonArray = new JSONArray();

        System.out.println(month + "/ " + day + "/ " + year);

        try {
            PreparedStatement statement = IndexServlet.connection.prepareStatement("select * from daily where MONTH(date)=? and DAY(date)=? and YEAR(date)=? order by period");
            statement.setString(1, month);
            statement.setString(2, day);
            statement.setString(3, year);

            ResultSet resultSet = statement.executeQuery();

            while(resultSet.next()){
                jsonArray.add(resultSet.getString("names") + ";" + resultSet.getString("period"));
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }

        return jsonArray.toJSONString();
    }


    @RequestMapping(value = "/attendance", method = {RequestMethod.GET})
    public String getAttendance(Model model, HttpServletRequest request, @RequestParam(value = "month", required = false) String month, @RequestParam(value = "day", required = false) String day, @RequestParam(value = "year", required = false) String year) {

        IndexServlet.servletLoginCheck(model, request);

        model.addAttribute("namesJSON", getAttendanceJSON(model, request, month, day, year));

        return "attendance";
    }


}

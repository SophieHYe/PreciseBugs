package com.bezman.servlet;

import org.apache.commons.lang.StringEscapeUtils;
import org.json.simple.JSONObject;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestMethod;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseBody;

import javax.servlet.http.HttpServletRequest;
import java.sql.PreparedStatement;
import java.sql.SQLException;

/**
 * Created by Terence on 11/13/2014.
 */
@Controller
@RequestMapping
public class StudentSettingsServlet {

    @RequestMapping(value = "/studentsettings", method = {RequestMethod.GET, RequestMethod.POST})
    public String studentSettings(Model model, HttpServletRequest request){

        IndexServlet.servletLoginCheck(model, request);

        return "studentsettings";
    }

    @RequestMapping(value = "/removestudent", method = {RequestMethod.GET, RequestMethod.POST})
    @ResponseBody
    public String removeStudent(@RequestParam("name") String name){

        JSONObject jsonObject = new JSONObject();

        try {
            PreparedStatement statement = IndexServlet.connection.prepareStatement("DELETE  from students where name=?");
            statement.setString(1, name);

            statement.executeUpdate();
            jsonObject.put("success", "true");
        } catch (SQLException e) {
            e.printStackTrace();
            jsonObject.put("success", "false");
        }

        return jsonObject.toJSONString();
    }

    @RequestMapping(value = "/addstudent", method = {RequestMethod.GET, RequestMethod.POST})
    @ResponseBody
    public String addStudent(@RequestParam("name") String name, @RequestParam("period") String period){

        JSONObject jsonObject = new JSONObject();

        try {
            PreparedStatement statement = IndexServlet.connection.prepareStatement("insert into students VALUES(?, ?)");
            statement.setString(1, StringEscapeUtils.escapeHtml(name));
            statement.setString(2, StringEscapeUtils.escapeHtml(period));

            statement.executeUpdate();
            jsonObject.put("success", "true");
        } catch (SQLException e) {
            e.printStackTrace();
            jsonObject.put("success", "false");
        }

        return jsonObject.toJSONString();
    }

    @RequestMapping(value = "/editstudent", method = {RequestMethod.GET, RequestMethod.POST})
    @ResponseBody
    public String editStudent(@RequestParam("oldName") String oldName, @RequestParam("newName") String newName, @RequestParam("oldPeriod") String oldPeriod, @RequestParam("newPeriod") String newPeriod){

        JSONObject jsonObject = new JSONObject();

        try {
            PreparedStatement statement = IndexServlet.connection.prepareStatement("update students set name=?, period=? where name=? and period=?");
            statement.setString(1, newName);
            statement.setString(2, newPeriod);
            statement.setString(3, oldName);
            statement.setString(4, oldPeriod);

            statement.executeUpdate();
            jsonObject.put("success", "true");
        } catch (SQLException e) {
            e.printStackTrace();
            jsonObject.put("success", "false");
        }

        return jsonObject.toJSONString();
    }

}

package com.bezman.servlet;

import org.apache.commons.lang.StringEscapeUtils;
import org.json.simple.JSONArray;
import org.json.simple.JSONObject;
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
import java.util.Comparator;

/**
 * Created by Terence on 11/9/2014.
 */
@Controller
@RequestMapping
public class DailyServlet {

    @RequestMapping(value = "/daily", method = RequestMethod.GET)
    public String getDaily(Model model, HttpServletRequest request){

        IndexServlet.servletLoginCheck(model, request);

        try {
            ResultSet itemSet = IndexServlet.execQuery("select * from items");
            JSONArray jsonArray = new JSONArray();

            while (itemSet.next()) {
                JSONObject jsonObject = new JSONObject();

                jsonObject.put("itemName", itemSet.getString("name"));
                jsonObject.put("priceOfItem", itemSet.getDouble("price"));

                jsonArray.add(jsonObject);
            }

            model.addAttribute("itemNames", StringEscapeUtils.escapeJavaScript(jsonArray.toJSONString()));
        }catch (SQLException e){
            e.printStackTrace();
        }

        return "daily";
    }

    @RequestMapping(value = "/getstudents", method = {RequestMethod.GET, RequestMethod.POST})
    @ResponseBody
    public String getStudents(Model model, @RequestParam(value = "period", required = false) String period){

        JSONArray jsonArray = new JSONArray();

        try {
            PreparedStatement statement;

            if (period == null){
                statement = IndexServlet.connection.prepareStatement("SELECT * FROM students");
            }else{
                statement = IndexServlet.connection.prepareStatement("SELECT * FROM students WHERE period=? ORDER BY period ASC");
                statement.setString(1, period);
            }

            ResultSet resultSet = statement.executeQuery();

            while(resultSet.next()){
                JSONObject jsonObject = new JSONObject();

                jsonObject.put("name", resultSet.getString("name"));
                jsonObject.put("period", resultSet.getString("period"));

                jsonArray.add(jsonObject);
            }

            jsonArray.sort(new Comparator() {
                @Override
                public int compare(Object o1, Object o2) {
                    JSONObject jsonObject1 = (JSONObject) o1;
                    JSONObject jsonObject2 = (JSONObject) o2;

                    return String.valueOf(jsonObject1.get("period")).compareTo(String.valueOf(jsonObject2.get("period")));
                }
            });
        } catch (SQLException e) {
            e.printStackTrace();
        }

        return  jsonArray.toJSONString();
    }

}

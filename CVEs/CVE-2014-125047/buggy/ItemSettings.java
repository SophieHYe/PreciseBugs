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

import javax.servlet.http.Cookie;
import javax.servlet.http.HttpServletRequest;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;

/**
 * Created by Terence on 11/11/2014.
 */
@Controller
@RequestMapping
public class ItemSettings {

    @RequestMapping(value = "/itemsettings", method = {RequestMethod.GET})
    public String ItemSettings(Model model, HttpServletRequest request){

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

        JSONArray jsonArray = new JSONArray();

        try {
            ResultSet itemSet = IndexServlet.execQuery("select * from items");

            while(itemSet.next()){
                JSONObject jsonObject = new JSONObject();

                jsonObject.put("itemName", itemSet.getString("name"));
                jsonObject.put("priceOfItem", itemSet.getString("price"));

                jsonArray.add(jsonObject);
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }

        model.addAttribute("itemNames", StringEscapeUtils.escapeJavaScript(jsonArray.toJSONString()));

        return "itemsettings";
    }

    @RequestMapping(value = "/changeitemname", method = {RequestMethod.GET, RequestMethod.POST})
    @ResponseBody
    public String changeItemName(Model model, HttpServletRequest request, @RequestParam("name") String name, @RequestParam("oldName") String oldName, @RequestParam("price") String price){
        JSONObject jsonObject = new JSONObject();

        if(IndexServlet.isSessionAdmin(IndexServlet.getCookie(request.getCookies(), "sessionID").getValue())){
            try {
                PreparedStatement statement = IndexServlet.connection.prepareStatement("update items set name=?,price=? where name=?");
                statement.setString(1, name);
                statement.setDouble(2, Double.valueOf(price));
                statement.setString(3, oldName);

                statement.executeUpdate();
                jsonObject.put("success", "true");
            } catch (SQLException e) {
                jsonObject.put("success", "false");
            }
        }else{
            jsonObject.put("success", "false");
        }

        return jsonObject.toJSONString();
    }

    @RequestMapping(value = "/deleteitem", method = {RequestMethod.GET, RequestMethod.POST})
    @ResponseBody
    public String deleteItem(Model model, HttpServletRequest request, @RequestParam("name") String name){
        JSONObject jsonObject = new JSONObject();

        if(IndexServlet.isSessionAdmin(IndexServlet.getCookie(request.getCookies(), "sessionID").getValue())){
            try {
                PreparedStatement statement = IndexServlet.connection.prepareStatement("delete from items where name=?");
                statement.setString(1, name);

                statement.executeUpdate();
                jsonObject.put("success", "true");
            } catch (SQLException e) {
                jsonObject.put("success", "false");
            }
        }else{
            jsonObject.put("success", "false");
        }

        return jsonObject.toJSONString();
    }

    @RequestMapping(value = "/additem", method = {RequestMethod.GET, RequestMethod.POST})
    @ResponseBody
    public String addItem(Model model, HttpServletRequest request, @RequestParam("name") String name, @RequestParam("price") String price){
        JSONObject jsonObject = new JSONObject();

        if(IndexServlet.isSessionAdmin(IndexServlet.getCookie(request.getCookies(), "sessionID").getValue())){
            try {
                PreparedStatement statement = IndexServlet.connection.prepareStatement("insert into items values(?, ?)");
                statement.setString(1, StringEscapeUtils.escapeHtml(name));
                statement.setDouble(2, Double.valueOf(price));

                statement.executeUpdate();

                jsonObject.put("success", "true");
            } catch (SQLException e) {
                jsonObject.put("success", "false");
            }
        }else{
            jsonObject.put("success", "false");
        }

        return jsonObject.toJSONString();
    }

}

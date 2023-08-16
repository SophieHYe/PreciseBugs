package com.bezman.servlet;

import org.json.simple.JSONObject;
import org.springframework.stereotype.Controller;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestMethod;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseBody;

import javax.servlet.http.Cookie;
import javax.servlet.http.HttpServletResponse;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.Random;

/**
 * Created by Terence on 11/11/2014.
 */
@Controller
@RequestMapping
public class Login {

    @RequestMapping(value = "/login", method = {RequestMethod.POST, RequestMethod.GET})
    @ResponseBody
    public String login(@RequestParam("username") String username, @RequestParam("password") String password, HttpServletResponse response){

        JSONObject jsonObject = new JSONObject();

        try {
            ResultSet resultSet = IndexServlet.execQuery("select * from accounts where username='" + username + "' and password='" + password + "'");

            boolean foundAccount = false;

            while(resultSet.next()){
                foundAccount = true;
            }

            if (foundAccount){
                String sessionID = getRandomSessionID();

                IndexServlet.execUpdate("delete from sessions where username='" + username + "'");
                IndexServlet.execUpdate("insert into sessions values('" + username + "', '" + sessionID + "')");

                jsonObject.put("success", sessionID);
                response.addCookie(new Cookie("sessionID", sessionID));
            }else jsonObject.put("success", "false");
        } catch (SQLException e) {
            e.printStackTrace();
           jsonObject.put("success", "false");
        }

        return jsonObject.toJSONString();
    }

    public String getRandomSessionID(){
        String chars = "abcdefghijklmnopqrstuvwxyz1234567890";
        String returnString = "";

        for(int i = 0; i < 16; i++){
            returnString += chars.charAt(new Random().nextInt(chars.length()));
        }

        return returnString;
    }

}

package com.bezman.servlet;

import org.json.simple.JSONObject;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestMethod;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseBody;

import java.sql.PreparedStatement;
import java.sql.SQLException;
import java.sql.Timestamp;
import java.util.*;
import java.util.stream.Collectors;
import java.util.stream.IntStream;

/**
 * Created by Terence on 11/9/2014.
 */
@Controller
@RequestMapping
public class SubmitDaily {

    @RequestMapping(value = "/dailysubmit", method = {RequestMethod.GET, RequestMethod.POST})
    @ResponseBody
    public String submit(@RequestParam Map allParams, Model model){
        JSONObject jsonObject = new JSONObject();

        String sales = (String) allParams.get("sale");
        allParams.remove("sale");

        HashMap<Integer, String> keyMap = new HashMap<>();
        HashMap<Integer, String> valueMap = new HashMap<>();

        int i = 1;
        for(Object obj : allParams.keySet()){
            keyMap.put(i, (String) obj);
            valueMap.put(i, (String) allParams.get(obj));
            i++;
        }

        Calendar calendar = Calendar.getInstance(TimeZone.getTimeZone("America/New_York"));
        Timestamp timestamp = new Timestamp(calendar.getTime().getTime());

        String columnNames = (String) keyMap.values().stream().collect(Collectors.joining(", "));

        ArrayList questionMarks = new ArrayList();
        IntStream.range(0, allParams.size()).forEach(a -> questionMarks.add("?"));

        String query = "insert into daily (date, " + columnNames + ") VALUES('" + timestamp + "', " + questionMarks.stream().collect(Collectors.joining(", ")) + ")";

        System.out.println("query : " + query);

        try {
            PreparedStatement statement = IndexServlet.connection.prepareStatement(query);

            for(Integer integer: valueMap.keySet()){
                statement.setString(integer, valueMap.get(integer));
            }

            System.out.println(statement);

            statement.executeUpdate();

            System.out.println(sales);
            for(String currentSale : sales.split(",")) {
                query = "insert into sales values(?, ?)";

                PreparedStatement salesStatement = IndexServlet.connection.prepareStatement(query);
                salesStatement.setTimestamp(1, timestamp);
                salesStatement.setString(2, currentSale);

                salesStatement.executeUpdate();
            }

            jsonObject.put("success", "true");
        } catch (SQLException e) {
            jsonObject.put("success", "false");
            e.printStackTrace();
        }

        return jsonObject.toJSONString();
    }

}

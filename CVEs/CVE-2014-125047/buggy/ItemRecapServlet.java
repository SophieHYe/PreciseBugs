package com.bezman.servlet;

import com.bezman.background.ItemSale;
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
import java.sql.Timestamp;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.stream.Collectors;

/**
 * Created by Terence on 11/16/2014.
 */
@Controller
@RequestMapping
public class ItemRecapServlet {

    @RequestMapping(value = "/itemrecap", method = {RequestMethod.GET, RequestMethod.POST})
    public String getItemRecap(Model model, HttpServletRequest request){

        Cookie cookie = IndexServlet.getCookie(request.getCookies(), "sessionID");
        if (cookie != null){
            try {
                ResultSet resultSet = IndexServlet.execQuery("select * from sessions where sessionID='" + cookie.getValue() + "'");
                String username = null;

                while(resultSet.next()){
                    model.addAttribute("username", resultSet.getString("username"));
                    username = resultSet.getString("username");
                }

                ResultSet accountSet = IndexServlet.execQuery("select * from accounts where username='" + username + "'");

                while(accountSet.next()){
                    model.addAttribute("role", accountSet.getString("role"));
                }
            } catch (SQLException e) {
                e.printStackTrace();
            }
        }

        return "itemrecap";
    }

    @RequestMapping(value = "/itemrecapjson", method = {RequestMethod.GET, RequestMethod.POST})
    @ResponseBody
    public String itemRecapJSON(@RequestParam(value = "month", required = false) String month, @RequestParam(value = "day", required = false) String day, @RequestParam (value = "year", required = false) String year, @RequestParam(value = "order", required = false) String order) {
        JSONArray jsonArray = new JSONArray();

        String query = "select * from sales where ";

        ArrayList params = new ArrayList();
        ArrayList<ItemSale> items = new ArrayList<>();

        if (month != null)
            params.add("MONTH(date)='" + month + "' ");

        if (day != null)
            params.add("DAY(date)='" + day + "' ");

        if (year != null)
            params.add("YEAR(date)='" + year + "' ");

        if (month == null && day == null && year == null)
            query = "select * from sales";
        else query += params.stream().collect(Collectors.joining(" and "));

        if (order == null)
            order = "";

        System.out.println(query);

        try {
            ResultSet resultSet = IndexServlet.execQuery(query);

            while(resultSet.next()){
                String sale = resultSet.getString("sale");
                String[] saleSplit = sale.split(";");

                Timestamp timestamp = resultSet.getTimestamp("date");

                if (saleSplit.length == 3){
                    String itemName = saleSplit[0];
                    Integer numOfItems = Integer.valueOf(saleSplit[1]);
                    Double priceOfItem = Double.valueOf(saleSplit[2]);

                    System.out.println("Item name : " + itemName + ", numOfItems : " + numOfItems + ", Price : " + priceOfItem);

                    if (itemName != null && numOfItems != null && priceOfItem != null) {
                        ItemSale itemSale = new ItemSale(itemName, numOfItems);
                        itemSale.setDate(timestamp);

                        boolean foundOne = false;

                        for(ItemSale item : items){
                            if (item.itemName.equals(itemSale.itemName)){
                                item.numOfItems += itemSale.numOfItems;
                                item.totalCash += (numOfItems.doubleValue() * priceOfItem.doubleValue());

                                System.out.println("Num of Items : " + numOfItems + ", price: " + priceOfItem);
                                System.out.println(numOfItems * priceOfItem);

                                foundOne = true;
                            }
                        }

                        if(!foundOne) {
                            itemSale.totalCash = numOfItems * priceOfItem;
                            items.add(itemSale);
                        }

                    }
                }
            }

            for (ItemSale item : items){
                JSONObject jsonObject = new JSONObject();

                jsonObject.put("itemName", item.itemName);
                jsonObject.put("numOfItems", item.numOfItems);
                jsonObject.put("totalCash", item.totalCash);
                jsonObject.put("date", item.timestamp.getTime());

                jsonArray.add(jsonObject);;
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }

        int ascdesc = order.equals("desc") ? -1 : 1;

        jsonArray.sort(new Comparator() {
            @Override
            public int compare(Object o1, Object o2) {
                JSONObject jsonObject1 = (JSONObject) o1;
                JSONObject jsonObject2 = (JSONObject) o2;

                return String.valueOf(jsonObject1.get("itemName")).compareTo(String.valueOf(jsonObject2.get("itemName"))) * ascdesc;
            }
        });


        return jsonArray.toJSONString();
    }

}

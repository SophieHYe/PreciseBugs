package com.bezman.background;

import com.bezman.servlet.IndexServlet;
import org.json.simple.JSONArray;
import org.json.simple.JSONObject;

import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Timestamp;
import java.util.ArrayList;

/**
 * Created by Terence on 11/10/2014.
 */
public class DailySubmission {

    public double startOnes = 0;
    public double startFives = 0;
    public double startTens = 0;
    public double startTwenties = 0;
    public double startPennies = 0;
    public double startNickels = 0;
    public double startDimes = 0;
    public double startQuarters = 0;

    public double endOnes = 0;
    public double endFives = 0;
    public double endTens = 0;
    public double endTwenties = 0;
    public double endPennies = 0;
    public double endNickels = 0;
    public double endDimes = 0;
    public double endQuarters = 0;

    public double startSum = 0;
    public double endSum = 0;

    public double startChecks = 0;
    public double endChecks = 0;

    public String[] names;
    public int period;

    public Timestamp date;

    public ArrayList<Sale> sales = new ArrayList<>();

    public DailySubmission(double startOnes, double startFives, double startTens, double startTwenties, double startPennies, double startNickels, double startDimes, double startQuarters, double endOnes, double endFives, double endTens, double endTwenties, double endPennies, double endNickels, double endDimes, double endQuarters, double startSum, double endSum, String names, int period, String sales, Timestamp date, double startChecks, double endChecks) {
        this.startOnes = startOnes;
        this.startFives = startFives;
        this.startTens = startTens;
        this.startTwenties = startTwenties;
        this.startPennies = startPennies;
        this.startNickels = startNickels;
        this.startDimes = startDimes;
        this.startQuarters = startQuarters;
        this.endOnes = endOnes;
        this.endFives = endFives;
        this.endTens = endTens;
        this.endTwenties = endTwenties;
        this.endPennies = endPennies;
        this.endNickels = endNickels;
        this.endDimes = endDimes;
        this.endQuarters = endQuarters;
        this.startSum = startSum;
        this.endSum = endSum;
        this.names = names.split(",");
        this.period = period;
        this.date = date;

        this.startChecks = startChecks;
        this.endChecks = endChecks;

        String[] salesArray = sales.split(",");

        for(String sale : salesArray){
            if(sale.split(";").length == 3) {
                String nameOfItem = sale.split(";")[0];
                int numOfItems = Integer.parseInt(sale.split(";")[1]);
                double price = Double.parseDouble(sale.split(";")[2]);

                this.sales.add(new Sale(nameOfItem, numOfItems, price));
            }
        }
    }

    public static DailySubmission submissionFromRow(ResultSet resultSet) throws SQLException {
        double startOnes = resultSet.getDouble("startOnes");
        double startFives = resultSet.getDouble("startFives");
        double startTens = resultSet.getDouble("startTens");
        double startTwenties = resultSet.getDouble("startTwenties");
        double startPennies = resultSet.getDouble("startPennies");
        double startNickels = resultSet.getDouble("startNickels");
        double startDimes = resultSet.getDouble("startDimes");
        double startQuarters = resultSet.getDouble("startQuarters");

        double endOnes = resultSet.getDouble("endOnes");
        double endFives = resultSet.getDouble("endFives");
        double endTens = resultSet.getDouble("endTens");
        double endTwenties = resultSet.getDouble("endTwenties");
        double endPennies = resultSet.getDouble("endPennies");
        double endNickels = resultSet.getDouble("endNickels");
        double endDimes = resultSet.getDouble("endDimes");
        double endQuarters = resultSet.getDouble("endQuarters");

        double startSum = resultSet.getDouble("startSum");
        double endSum = resultSet.getDouble("endSum");

        double startChecks = resultSet.getDouble("startChecks");
        double endChecks = resultSet.getDouble("endChecks");

        int period = resultSet.getInt("period");
        String names = resultSet.getString("names");

        Timestamp date = resultSet.getTimestamp("date");

        String sales = "";

        ResultSet salesSet = IndexServlet.execQuery("select * from sales where date='" + date + "'");
        while(salesSet.next()){
            sales += salesSet.getString("sale") + ",";
        }

        return new DailySubmission(startOnes, startFives, startTens, startTwenties, startPennies, startNickels, startDimes, startQuarters, endOnes, endFives, endTens, endTwenties, endPennies, endNickels, endDimes, endQuarters, startSum, endSum, names, period, sales, date, startChecks, endChecks);
    }

    public JSONObject toJson(){
        JSONObject jsonObject = new JSONObject();

        jsonObject.put("startOnes", startOnes);
        jsonObject.put("startFives", startFives);
        jsonObject.put("startTens", startTens);
        jsonObject.put("startTwenties", startTwenties);
        jsonObject.put("startPennies", startPennies);
        jsonObject.put("startNickels", startNickels);
        jsonObject.put("startDimes", startDimes);
        jsonObject.put("startQuarters", startQuarters);

        jsonObject.put("endOnes", endOnes);
        jsonObject.put("endFives", endFives);
        jsonObject.put("endTens", endTens);
        jsonObject.put("endTwenties", endTwenties);
        jsonObject.put("endPennies", endPennies);
        jsonObject.put("endNickels", endNickels);
        jsonObject.put("endDimes", endDimes);
        jsonObject.put("endQuarters", endQuarters);

        jsonObject.put("startSum", startSum);
        jsonObject.put("endSum", endSum);

        jsonObject.put("startChecks", startChecks);
        jsonObject.put("endChecks", endChecks);

        jsonObject.put("period", period);

        jsonObject.put("date", date.toString());

        JSONArray sales = new JSONArray();
        for(Sale sale : this.sales){
            JSONObject saleJSON = new JSONObject();

            saleJSON.put("itemName", sale.item);
            saleJSON.put("numOfItems", sale.numOfItems);
            saleJSON.put("priceOfItem", sale.price);

            sales.add(saleJSON);
        }

        jsonObject.put("sales", sales);

        JSONArray names = new JSONArray();
        for(String name : this.names){
            JSONObject nameObject = new JSONObject();

            nameObject.put("name", name);

            names.add(nameObject);
        }

        jsonObject.put("names", names);

        return  jsonObject;
    }
}

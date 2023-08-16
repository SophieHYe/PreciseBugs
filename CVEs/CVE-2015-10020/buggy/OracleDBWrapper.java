package com.datformers.database;

import java.sql.*;
import java.util.Enumeration;


public class OracleDBWrapper {
	String url;
	String pwd;
	String user;
	Connection conn;
	
	
	public OracleDBWrapper(String url,String user,String pwd) {
		this.url=url;
		this.user=user;
		this.pwd=pwd;
		createConnection();
		
	}
	public void createConnection() {
		
			try {
				Class.forName("oracle.jdbc.driver.OracleDriver");
				conn = DriverManager.getConnection(url,
						user, pwd);
			} catch (SQLException | ClassNotFoundException e) {
				// TODO Auto-generated catch block
				e.printStackTrace();
			}
		
	}
	
	public ResultSet executeQuery(String query) {
		 Statement stmt;
		 ResultSet rs = null;
		 
		try {
			stmt = conn.createStatement();
			rs = stmt.executeQuery(query);
			
		} catch (Exception e) {
			// TODO Auto-generated catch block
			e.printStackTrace();
		}
		
			return rs;
	}
	public void closeConnection() {
		try {
			conn.close();
		} catch (SQLException e) {
			// TODO Auto-generated catch block
			e.printStackTrace();
		}
	}
	public void deregister() {
		Enumeration<Driver> drivers = DriverManager.getDrivers();
        while (drivers.hasMoreElements()) {
            Driver driver = drivers.nextElement();
            try {
                DriverManager.deregisterDriver(driver);
               
            } catch (SQLException e) {
               
            }

        }
	}
	public static void main(String[] args) {
		try {
			String url = "jdbc:oracle:thin:@//cis550hw1.cfoish237b6z.us-west-2.rds.amazonaws.com:1521/IMDB";
			Connection conn = DriverManager.getConnection(url,
					"cis550students", "cis550hw1");
			Statement stmt = conn.createStatement();
			ResultSet rs;
			String query="With result as (select genre as g,count(movie_id) as c,year"+
                         " from MOVIES_GENRES M inner join MOVIES K on K.ID=M.MOVIE_ID"+
                         " group by M.genre,year)"+
                         " select  g as GENRE,year"+
                         " from result"+ 
                         " where (c,g) in"+ 
                         " (select max(c),g from result"+
                         " group by g)";
			
			rs = stmt.executeQuery(query);
			ResultSetMetaData d=rs.getMetaData();
			System.out.println(d.getColumnName(1)+"      "+d.getColumnName(2));
			while (rs.next()) {
				String genre = rs.getString(d.getColumnName(1));
				String year = rs.getString(d.getColumnName(2));
				System.out.println(genre+"     "+year);
			}
			conn.close();
		} catch (Exception e) {
			System.err.println("Got an exception! ");
			System.err.println(e.getMessage());
		}
	}
}

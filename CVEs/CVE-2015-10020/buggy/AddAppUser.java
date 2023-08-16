package com.datformers.servlet;



import java.sql.DriverManager;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;

import com.datformers.database.OracleDBWrapper;
import com.datformers.utils.DatabaseUtil;


public class AddAppUser {
	String query = "";
	OracleDBWrapper wrapper;

	public AddAppUser(String str){
		query = str;
		wrapper = new OracleDBWrapper(DatabaseUtil.getURL(DatabaseUtil.IP), DatabaseUtil.UERNAME, DatabaseUtil.PASSWORD);
	}
	public ResultSet addUser(){
		
	
		//String query1 = "Insert into APPUSER(USER_ID,EMAIL,PASSWORD,FIRST_NAME,LAST_NAME,IS_FACEBOOK_LOGIN)"
		//		+ " values (usr_id.NEXTVAL,'aryaa@seas.upenn.edu','test','ARyaa','Gautam','Y')";
		
		ResultSet rs = wrapper.executeQuery(query);
		return rs;
		
		
	}
	public void closeDb(){
		wrapper.closeConnection();
	}
	
}

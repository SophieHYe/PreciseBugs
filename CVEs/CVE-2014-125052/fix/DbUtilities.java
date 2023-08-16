package org.identifiers.db;

import java.net.Socket;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;


/**
 * Database access utilities.
 * 
 * @author Camille Laibe
 * @version 20140519
 */
public class DbUtilities
{
	private static final String DRIVER = "com.mysql.jdbc.Driver";
    private static final String SERVER = "172.17.1.107";
    private static final String PORT = "3306";
    private static final String DATABASE = "miriam-demo";
    private static final String LOGIN = "miriam_demo";  
    private static final String PASSWORD = "demo";
    
	
	/**
	 * Initialises the database connection.
	 * @return connection
	 */
	public static Connection initDbConnection()
	{
		Connection connection = null;
		
		// loads the JDBC driver
//        System.out.println("+ Loads the JDBC driver...");
        try
        {
            Class.forName(DRIVER);
        }
        catch (ClassNotFoundException e)
        {
            System.out.println("Cannot load the database driver!");
            System.out.println("ClassNotFound Exception raised: " + e.getMessage());
        }
        
        // creates a connection to the database
//        System.out.println("+ Creates the connection to the database...");
        String url = "jdbc:mysql://" + SERVER +  ":" + PORT + "/" + DATABASE;   // a JDBC url
        try
        {
        	connection = DriverManager.getConnection(url, LOGIN, PASSWORD);
        }
        catch (SQLException e)
        {
            System.out.println("Cannot open the database connection!");
            System.out.println("SQL Exception raised: " + e.getMessage());
        }
        
        return connection;
	}
	
	
	/**
	 * Closes the database connection, included the prepared statement.
	 * @param connection
	 * @param stmt
	 */
	public static void closeDbConnection(Connection connection)
	{
        // closes the connection
        try
        {
            if (null != connection)
            {
            	//System.out.println("- Closes the connection");
            	connection.close();
            }
        }
        catch (SQLException e)
        {
            System.err.println("Cannot close the database connection!");
            System.err.println("SQL Exception raised: " + e.getMessage());
        }
	}
	
	
	/**
	 * Closes a Statement silently (no exception raised).
	 */
	public static void closeSilentlyStatement(Statement stmt)
	{
		try
		{
			stmt.close();
		}
		catch (SQLException e)
		{
			// nothing
		}
	}
	
	
	/**
	 * Closes a PreparedStatement silently (no exception raised).
	 */
	public static void closeSilentlyPreparedStatement(PreparedStatement stmt)
	{
		try
		{
			stmt.close();
		}
		catch (SQLException e)
		{
			// nothing
		}
	}
	
	
	/**
	 * Closes a ResultSet silently (no exception raised).
	 */
	public static void closeSilentlyResultSet(ResultSet rs)
	{
		try
		{
			rs.close();
		}
		catch (SQLException e)
		{
			// nothing
		}
	}
}

package org.identifiers.db;


import org.identifiers.data.URIextended;
import org.identifiers.db.DbUtilities;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.List;


/**
 * Simple dao for SPARQL testing.
 * 
 * @author Camille
 * @version 20140519
 */
public class RegistryDao
{
	private Connection connection = null;
	
	
	
	
	/**
	 * Returns all URIs sameAs the provided one.
	 * @param uri
	 * @return
	 */
	public List<URIextended> getSameAsURIs(String uri)
	{
        Boolean error = false;   // if an error happens
        PreparedStatement stmt = null;
        ResultSet rs;
        List<URIextended> urls = null;
        
        // initialisation of the database connection
	    connection = DbUtilities.initDbConnection();
        
        try
        {

            final String uriTobe = uri.substring(0,uri.indexOf("/", 10));
			String query = "SELECT convertPrefix, ptr_datatype FROM mir_resource WHERE `convertPrefix` LIKE '"+uriTobe+"%'";
            
            try
            {
                stmt = connection.prepareStatement(query);
            }
            catch (SQLException e)
            {
                System.err.println("Error while creating the prepared statement!");
                System.err.println("SQL Exception raised: " + e.getMessage());
            }
            
            //logger.debug("SQL prepared query: " + stmt.toString());
            rs = stmt.executeQuery();

            String dataTypeId = null;
            String identifier = null;

            while (rs.next()) {
                String convertPrefix = rs.getString("convertPrefix");
                if(uri.contains(convertPrefix)){
                    dataTypeId = rs.getString("ptr_datatype");
                    identifier = uri.substring(convertPrefix.length());
                }

            }

            query = "SELECT convertPrefix, obsolete FROM mir_resource WHERE ptr_datatype=\""+dataTypeId+"\" and urischeme=1";

            try
            {
                stmt = connection.prepareStatement(query);
            }
            catch (SQLException e)
            {
                System.err.println("Error while creating the prepared statement!");
                System.err.println("SQL Exception raised: " + e.getMessage());
            }
            //logger.debug("SQL prepared query: " + stmt.toString());
            rs = stmt.executeQuery();

            urls = new ArrayList<URIextended>();
            while (rs.next())
            {
                urls.add(new URIextended(rs.getString("convertPrefix") + identifier, rs.getInt("obsolete")));
            }
            rs.close();
        }
        catch (SQLException e)
        {
            //logger.error("Error during the processing of the result of a query.");
            //logger.error("SQL Exception raised: " + e.getMessage());
            error = true;
        }
        finally
        {
        	// closes the database connection and statement
            DbUtilities.closeDbConnection(connection, stmt);
        }


        // exception handling
        if (error)
        {
            throw new RuntimeException("Sorry, an error occurred while dealing with your request.");
        }
        System.out.println("u"+urls.size());
        return urls;
	}
}

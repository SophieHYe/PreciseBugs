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
public class RegistryDao {

	/**
	 * Returns all URIs sameAs the provided one.
	 * 
	 * @param uri
	 * @return
	 */
	public List<URIextended> getSameAsURIs(String uri) {

		List<URIextended> urls = null;

		// initialisation of the database connection
		try (Connection connection = DbUtilities.initDbConnection()) {

			final String uriTobe = uri.substring(0, uri.indexOf("/", 10))+'%';
			String query = "SELECT convertPrefix, ptr_datatype FROM mir_resource WHERE `convertPrefix` LIKE ?";

			String dataTypeId = null;
			String identifier = null;

			try (PreparedStatement stmt = connection.prepareStatement(query)) {
				stmt.setString(1, uriTobe);
				try (ResultSet rs = stmt.executeQuery()) {
					while (rs.next()) {
						String convertPrefix = rs.getString("convertPrefix");
						if (uri.contains(convertPrefix)) {
							dataTypeId = rs.getString("ptr_datatype");
							identifier = uri.substring(convertPrefix.length());
						}
					}
				}
			} catch (SQLException e) {
				System.err
						.println("Error while creating the prepared statement!");
				System.err.println("SQL Exception raised: " + e.getMessage());
				throw new RuntimeException(
						"Sorry, an error occurred while dealing with your request.",
						e);
			}

			// logger.debug("SQL prepared query: " + stmt.toString());

			query = "SELECT convertPrefix, obsolete FROM mir_resource WHERE ptr_datatype=? and urischeme=1";

			try (PreparedStatement stmt = connection.prepareStatement(query)) {
				stmt.setString(1, dataTypeId);
				try (ResultSet rs = stmt.executeQuery()) {

					urls = new ArrayList<URIextended>();
					while (rs.next()) {
						urls.add(new URIextended(rs.getString("convertPrefix")
								+ identifier, rs.getInt("obsolete")));
					}
				}
			} catch (SQLException e) {
				System.err
						.println("Error while creating the prepared statement!");
				System.err.println("SQL Exception raised: " + e.getMessage());
				throw new RuntimeException(
						"Sorry, an error occurred while dealing with your request.",
						e);
			}
			// logger.debug("SQL prepared query: " + stmt.toString());

		} catch (SQLException e1) {
			throw new RuntimeException(
					"Sorry, an error occurred while dealing with your request.",
					e1);
		}
		System.out.println("u" + urls.size());
		return urls;
	}
}

package database;

import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.List;

import models.Address;
import models.Coordinates;
import models.Gym;
import play.db.DB;

public class GymsDao {
	
	public static GymsDao get() {
		return INSTANCE;
	}
	
	public List<Gym> getAll() {
		List<Gym> gyms = new ArrayList<Gym>();
		
		Connection connection = null;
		try {
			connection = DB.getConnection();
			Statement statement = connection.createStatement();
			ResultSet resultSet = statement.executeQuery(
					"SELECT gyms.*, AVG(rating) AS rating, COUNT(rating) as ratings_count "
					+ "FROM gyms "
					+ "LEFT JOIN gym_ratings USING (gym_id) "
					+ "GROUP BY gyms.gym_id");
			
			while (resultSet.next()) {
				int id = resultSet.getInt("gym_id");
				String name = resultSet.getString("gym_name");
				String city = resultSet.getString("city");
				String street = resultSet.getString("street");
				double longitude = resultSet.getDouble("longitude");
				double latitude = resultSet.getDouble("latitude");
				String url = resultSet.getString("url");
				Gym g = new Gym(id, name, new Address(city, street, new Coordinates(longitude, latitude))); //TODO if not defined
				g.setUrl(url);
				g.setRating(resultSet.getDouble("rating"));
				g.setRatingsCount(resultSet.getInt("ratings_count"));
				gyms.add(g);
			}
			
			resultSet.close();
			statement.close();
		} catch (SQLException e) {
			e.printStackTrace();
		} finally {
			if (connection != null) {
				try {
					connection.close();
				} catch (SQLException e) {
					e.printStackTrace();
				}
			}
		}

		return gyms;
	}
	
	public void rateGym(int userId, int gymId, int rating) {
		Connection connection = null;
		try {
			connection = DB.getConnection();
			String sql = connection.nativeSQL("INSERT INTO gym_ratings(user_id, gym_id, rating) VALUES" + 
					"  (" + userId + ", " + gymId + ", " + rating + ");");
			play.Logger.info("Insert gym_rating: " + sql);
			connection.createStatement().execute(sql);
		} catch (SQLException e) {
			e.printStackTrace();
		} finally {
			if (connection != null) {
				try {
					connection.close();
				} catch (SQLException e) {
					e.printStackTrace();
				}
			}
		}
	}

	private static final GymsDao INSTANCE = new GymsDao();
	
	private GymsDao() {}

	public Gym getById(int gymId) {
		Gym g = null;
		Connection connection = null;
		try {
			connection = DB.getConnection();
			Statement statement = connection.createStatement();
			ResultSet resultSet = statement.executeQuery(
					"SELECT gyms.*, AVG(rating) AS rating, COUNT(rating) as ratings_count "
					+ "FROM gyms "
					+ "LEFT JOIN gym_ratings USING (gym_id) "
					+ "WHERE gym_id = " + gymId
					+ " GROUP BY gyms.gym_id");
			
			if (resultSet.next()) {
				int id = resultSet.getInt("gym_id");
				String name = resultSet.getString("gym_name");
				String city = resultSet.getString("city");
				String street = resultSet.getString("street");
				double longitude = resultSet.getDouble("longitude");
				double latitude = resultSet.getDouble("latitude");
				String url = resultSet.getString("url");
				g = new Gym(id, name, new Address(city, street, new Coordinates(longitude, latitude))); //TODO if not defined
				g.setUrl(url);
				g.setRating(resultSet.getDouble("rating"));
				g.setRatingsCount(resultSet.getInt("ratings_count"));
				
				
			}
			
			resultSet.close();
			statement.close();
		} catch (SQLException e) {
			e.printStackTrace();
		} finally {
			if (connection != null) {
				try {
					connection.close();
				} catch (SQLException e) {
					e.printStackTrace();
				}
			}
		}

		return g;
	}

}

package database;

import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.sql.Connection;
import java.sql.Date;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.List;

import models.Stranger;
import models.User;
import play.Logger;
import play.db.DB;

public class UsersDao {

	private static final UsersDao INSTANCE = new UsersDao();

	private UsersDao() {
	}
	
	public static UsersDao get() {
		return INSTANCE;
	}
	
	public boolean insert(User user) {
		Connection connection = null;
		try {
			connection = DB.getConnection();
			String passwordDigest = byteArrayToHexString(MessageDigest
					.getInstance("SHA-1").digest(user.getPassword().getBytes()));
			PreparedStatement p = connection.prepareStatement("INSERT INTO users(login, email, password_digest, first_name, last_name) VALUES (?, ?, ?, ?, ?)");
			p.setString(1, user.getLogin());
			p.setString(2, user.getEmail());
			p.setString(3, passwordDigest);
			p.setString(4, user.getFirstName());
			p.setString(5, user.getLastName());
			p.executeQuery();
			p.close();
			
			p = connection.prepareStatement("SELECT * FROM users WHERE login = ?");
			p.setString(1, user.getLogin());
			ResultSet result = p.executeQuery();
			if (result.next()){
				user.setId(result.getInt("user_id"));				
			}
			else {
				throw new SQLException("Insert error");
			}
			result.close();
			p.close();
			return true;
		} catch (SQLException e) {
			e.printStackTrace();
			return false;
		} catch (NoSuchAlgorithmException e) {
			e.printStackTrace();
			return false;
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

	public User login(String login, String password) {
		Connection connection = null;
		try {
			connection = DB.getConnection();
			String passwordDigest = byteArrayToHexString(MessageDigest
					.getInstance("SHA-1").digest(password.getBytes()));
			PreparedStatement p = connection.prepareStatement("SELECT * FROM users WHERE (login = ? OR email = ?) AND password_digest = ?");
			p.setString(1, login);
			p.setString(2, login);
			p.setString(3, passwordDigest);
			ResultSet resultUser = p.executeQuery();
			
			User user = null;
			if (resultUser.next()) {
				Logger.info("User " + resultUser.getString("login") + " logged in!");
				user = new User();
				user.setId(resultUser.getInt("user_id"));
				user.setLogin(resultUser.getString("login"));
				user.setEmail(resultUser.getString("email"));
			}
			
			resultUser.close();
			p.close();
			
			return user;
		} catch (SQLException e) {
			e.printStackTrace();
			return null;
		} catch (NoSuchAlgorithmException e) {
			e.printStackTrace();
			return null;
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
	
	private static String byteArrayToHexString(byte[] b) {
		String result = "";
		for (int i = 0; i < b.length; i++) {
			result += Integer.toString((b[i] & 0xff) + 0x100, 16).substring(1);
		}
		return result;
	}

	public User getById(int userId) {
		Connection connection = null;
		try {
			connection = DB.getConnection();
			PreparedStatement p = connection.prepareStatement("SELECT * FROM users WHERE user_id = ?");
			p.setInt(1, userId);
			
			ResultSet resultUser = p.executeQuery();
			
			User user = null;
			if (resultUser.next()) {
				user = new User();
				user.setId(resultUser.getInt("user_id"));
				user.setLogin(resultUser.getString("login"));
				user.setEmail(resultUser.getString("email"));
				user.setFirstName(resultUser.getString("first_name"));
				user.setLastName(resultUser.getString("last_name"));
				user.setDateOfBirth(resultUser.getDate("date_of_birth"));
				user.setHeight(resultUser.getInt("height"));
				user.setWeight(resultUser.getDouble("weight"));
			}
			
			resultUser.close();
			p.close();
			
			return user;
		} catch (SQLException e) {
			e.printStackTrace();
			return null;
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

	public boolean checkPasswordForUser(int userId, String password) {
		Connection connection = null;
		try {
			String passwordDigest = byteArrayToHexString(MessageDigest
					.getInstance("SHA-1").digest(password.getBytes()));
			
			connection = DB.getConnection();
			PreparedStatement p = connection.prepareStatement("SELECT * FROM users WHERE user_id = ? AND password_digest = ?");
			p.setInt(1, userId);
			p.setString(2, passwordDigest);
			ResultSet resultUser = p.executeQuery();
			
			boolean result;
			if (resultUser.next()) {
				result = true;
			}
			else {
				result = false;
			}
			
			resultUser.close();
			p.close();
			return result;
		} catch (SQLException e) {
			e.printStackTrace();
			return false;
		} catch (NoSuchAlgorithmException e) {
			e.printStackTrace();
			return false;
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

	public void changePassword(int userId, String password) {
		Connection connection = null;
		try {
			String passwordDigest = byteArrayToHexString(MessageDigest
					.getInstance("SHA-1").digest(password.getBytes()));
			
			connection = DB.getConnection();
			PreparedStatement p = connection.prepareStatement("UPDATE users SET password_digest = ? where user_id = ?");
			p.setString(1, passwordDigest);;
			p.setInt(2, userId);
			p.executeQuery();
			p.close();
			play.Logger.info("Password changed!");
			
		} catch (SQLException e) {
			e.printStackTrace();
		} catch (NoSuchAlgorithmException e) {
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

	public boolean update(int userId, Double weight, Double height, Date dateOfBirth) {
		Connection connection = null;
		try {	
			connection = DB.getConnection();
			String sql = "UPDATE users SET   ";
			if (weight != null)
				sql += "weight = ?, ";
			if (height != null)
				sql += "height = ?, ";
			if (dateOfBirth != null)
				sql += "date_of_birth = ?, ";
			sql = sql.substring(0, sql.length() - 2);
			sql += " WHERE user_id = ?";
			
			PreparedStatement p = connection.prepareStatement(sql);

			int i = 1;
			if (weight != null)
				p.setDouble(i++, weight);
			if (height != null)
				p.setDouble(i++, height);
			if (dateOfBirth != null)
				p.setDate(i++, dateOfBirth);
			
			p.setInt(i, userId);

			play.Logger.info(sql);

			if (i > 1)
				p.executeUpdate();
			
			p.close();
			return true;
		} catch (SQLException e) {
			e.printStackTrace();
			return false;
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
	
	public List<Stranger> getStrangersForUser(int userId) {
		List<Stranger> strangers = new ArrayList<>();
		
		Connection connection = null;
		try {	
			connection = DB.getConnection();
			PreparedStatement p = connection.prepareStatement("SELECT * FROM random_strangers_of_user(?)");
			p.setInt(1, userId);
			ResultSet resultSet = p.executeQuery();
			
			Stranger stranger = null;
			while (resultSet.next()) {
				stranger = new Stranger();
				stranger.setId(resultSet.getInt("id"));
				stranger.setFirstName(resultSet.getString("first_name"));
				stranger.setLastName(resultSet.getString("last_name"));
				strangers.add(stranger);
			}
			
			resultSet.close();
			p.close();
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
		
		return strangers;
	}
	
	public void inviteUser(int userId, int requestedUserId) {
		Connection connection = null;
		try {
			connection = DB.getConnection();
			PreparedStatement p = connection.prepareStatement("INSERT INTO "
						+ "friendship_requests (first_user_id, second_user_id) "
						+ "VALUES (?, ?)");

			p.setInt(1, userId);
			p.setInt(2, requestedUserId);
			p.execute();
			p.close();
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
	
	public void removeRequest(int userId, int requestingUserId) {
		Connection connection = null;
		try {
			connection = DB.getConnection();
			PreparedStatement p = connection.prepareStatement(""
					+ "DELETE FROM friendship_requests "
					+ "WHERE first_user_id = ? AND second_user_id = ?");

			p.setInt(1, requestingUserId);
			p.setInt(2, userId);
			p.execute();
			p.close();
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
	
	public List<User> getFriendshipRequests(int userId) {
		List<User> users = new ArrayList<User>();
		Connection connection = null;
		try {
			connection = DB.getConnection();
			PreparedStatement statement = connection.prepareStatement(
					"SELECT user_id, first_name, last_name "
					+ "FROM friendship_requests "
					+ "JOIN users ON first_user_id = user_id "
					+ "WHERE second_user_id = ?");
			
			statement.setInt(1, userId);
			ResultSet resultSet = statement.executeQuery();
			play.Logger.debug("aaa");
			
			while (resultSet.next()) {
				User u = new User();
				u.setId(resultSet.getInt("user_id"));
				u.setFirstName(resultSet.getString("first_name"));
				u.setLastName(resultSet.getString("last_name"));
				users.add(u);
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
		return users;
	}

	public boolean areFriends(int userId, int foreignerId) {
		int firstUser = Math.min(userId, foreignerId);
		int secondUser = Math.max(foreignerId, userId);
		
		Connection connection = null;
		try {
			connection = DB.getConnection();
			PreparedStatement statement = connection.prepareStatement(
					"SELECT * FROM friendships WHERE first_user_id = " + firstUser + " AND second_user_id = " + secondUser);			
			ResultSet resultSet = statement.executeQuery();
			
			boolean areFriends = resultSet.next();
			
			resultSet.close();
			statement.close();
			
			return areFriends;
		} catch (SQLException e) {
			e.printStackTrace();
			return false;
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

}

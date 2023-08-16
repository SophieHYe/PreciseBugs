package models;

import java.sql.Connection;
import java.sql.Driver;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.Properties;

import com.microsoft.sqlserver.jdbc.SQLServerDriver;

import exceptions.BadLoginException;
import exceptions.UsernameAlreadyExistsException;


public class DatabaseAccess implements DataService {

	private final String connectionUrl = "jdbc:sqlserver://n8bu1j6855.database.windows.net:1433;database=VoyagerDB;user=VoyageLogin@n8bu1j6855;password={GroupP@ssword};encrypt=true;hostNameInCertificate=*.database.windows.net;loginTimeout=30;";
	/* (non-Javadoc)
	 * @see models.DataService#login(java.lang.String, java.lang.String)
	 */
	@Override
	public Account login(String username, String password){
		Account account = null;
		Driver driver = new SQLServerDriver();
		try {
			Connection con = driver.connect(connectionUrl, new Properties());
			PreparedStatement statement = con.prepareStatement("Select userName, userPassword, userEmail, userRole from UserTable where userName = ?");
			statement.setString(1, username);
			ResultSet rs = statement.executeQuery();
			rs.next();
			String storedPass = rs.getString("userPassword");
			if(storedPass.equals(password)){
				System.out.println("Successfully logged in");
				account = new Account(rs.getString("userName"), rs.getString("userEmail"), "", Roles.valueOf(rs.getString("userRole")), rs.getString("userPassword"));
			}
			else{
				throw new BadLoginException("The username/password combination is incorrect");
			}
		} catch (SQLException e) {
			e.printStackTrace();
			if(e.getMessage().contains("result set has no current row")){
				throw new BadLoginException("The username/password combination is incorrect");
			}
		}	
		
		return account;
	}
	
	//Bind variables
	
	
	/* (non-Javadoc)
	 * @see models.DataService#registerUser(models.Account)
	 */
	@Override
	public void registerUser(Account user){
		Driver driver = new SQLServerDriver();
		try {
			Connection con = driver.connect(connectionUrl, new Properties());
			PreparedStatement statement = con.prepareStatement("Insert INTO UserTable (userName, userPassword, userEmail, userRole) "
					+ "VALUES (?, ?, ?, ?);");
			statement.setString(1, user.getUsername());
			statement.setString(2, user.getPassword());
			statement.setString(3, user.getEmail());
			statement.setString(4, user.getRole().toString());
			statement.execute();
			System.out.println("Registration Successful");
		} catch (SQLException e) {
			if(e.getMessage().contains("UNIQUE KEY")){
				System.err.println("User has already been registered.");
				throw new UsernameAlreadyExistsException();
			}
			else{
				e.printStackTrace();
			}
		}
	}
	
	/* (non-Javadoc)
	 * @see models.DataService#removeUser(models.Account)
	 */
	@Override
	public void removeUser(Account user){
		Driver driver = new SQLServerDriver();
		try {
			Connection con = driver.connect(connectionUrl, new Properties());
			PreparedStatement statement = con.prepareStatement("DELETE FROM UserTable WHERE userName=?");
			statement.setString(1, user.getUsername());
			statement.execute();
			System.out.println("Removal sucessful");
		} catch (SQLException e) {
			e.printStackTrace();
		}	
	}
	
	/* (non-Javadoc)
	 * @see models.DataService#updateUser(models.Account)
	 */
	@Override
	public void updateUser(Account user){
		Driver driver = new SQLServerDriver();
		try {
			Connection con = driver.connect(connectionUrl, new Properties());
			PreparedStatement statement = con.prepareStatement("UPDATE UserTable "
					+ "SET userPassword=?, userEmail=?, userRole=?"
					+ "WHERE userName=?");
			statement.setString(1, user.getPassword());
			statement.setString(2, user.getEmail());
			statement.setString(3, user.getRole().toString());
			statement.setString(4, user.getUsername());
			statement.execute();
			System.out.println("Update successful");
		} catch (SQLException e) {
			e.printStackTrace();
		}
	}
	
	/* (non-Javadoc)
	 * @see models.DataService#getUserId(java.lang.String)
	 */
	@Override
	public int getUserId(String user){
		int id = -1;
		Driver driver = new SQLServerDriver();
		try {
			Connection con = driver.connect(connectionUrl, new Properties());
			PreparedStatement statement = con.prepareStatement("Select userId from UserTable where userName = ?");
			statement.setString(1, user);
			ResultSet rs = statement.executeQuery();
			rs.next();
			String storedId = rs.getString("userId");
			id = Integer.parseInt(storedId);
		} catch (SQLException e) {
			e.printStackTrace();
		}	
		return id;
	}
	
	/* (non-Javadoc)
	 * @see models.DataService#getUserName(int)
	 */
	@Override
	public String getUserName(int userId){
		String userName = null;
		Driver driver = new SQLServerDriver();
		try {
			Connection con = driver.connect(connectionUrl, new Properties());
			PreparedStatement statement = con.prepareStatement("Select userName from UserTable where userId = ?");
			statement.setInt(1, userId);
			ResultSet rs = statement.executeQuery();
			rs.next();
			userName = rs.getString("userName");
			
		} catch (SQLException e) {
			e.printStackTrace();
		}	
		
		return userName;
	}
	
	/* (non-Javadoc)
	 * @see models.DataService#enterPost(models.Post)
	 */
	@Override
	public void enterPost(Post post){
		Driver driver = new SQLServerDriver();
		try {
			Connection con = driver.connect(connectionUrl, new Properties());
			PreparedStatement statement = con.prepareStatement("Insert INTO PostTable (postTitle, postAuthorId, postTime, postContent) "
					+ "VALUES ('" + post.getTitle() + "', '" + this.getUserId(post.getAuthor()) + "', CURRENT_TIMESTAMP, '" + post.getMessage() + "');");
			statement.setString(1, post.getTitle());
			statement.setInt(2, this.getUserId(post.getAuthor()));
			statement.setString(3, post.getMessage());
			statement.execute();
			System.out.println("Successful post");
		} catch (SQLException e) {
			e.printStackTrace();
		}	
	}

	/* (non-Javadoc)
	 * @see models.DataService#retrievePost(java.lang.String)
	 */
	@Override
	public Post retrievePost(String postTitle){
		Post post = null;
		Driver driver = new SQLServerDriver();
		try {
			Connection con = driver.connect(connectionUrl, new Properties());
			PreparedStatement statement = con.prepareStatement("Select postTitle, postAuthorId, postTime, postContent from PostTable where postTitle = '" + postTitle + "'");
			ResultSet rs = statement.executeQuery();
			rs.next();
			post = new Post(rs.getString("postTitle"), rs.getString("postContent"), this.getUserName(rs.getInt("postAuthorId")));

		} catch (SQLException e) {
			e.printStackTrace();
		}	
		
		return post;
	}
}

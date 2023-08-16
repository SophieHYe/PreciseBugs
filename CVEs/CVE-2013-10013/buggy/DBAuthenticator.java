package talentum.escenic.plugins.authenticator.authenticators;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.ResultSetMetaData;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

import neo.dbaccess.Transaction;
import neo.dbaccess.TransactionOperation;
import neo.xredsys.content.ContentManager;

import org.apache.commons.logging.Log;
import org.apache.commons.logging.LogFactory;

import talentum.escenic.plugins.authenticator.AuthenticationException;
import talentum.escenic.plugins.authenticator.RegistrationException;
import talentum.escenic.plugins.authenticator.ReminderException;

/**
 * Database authenticator.
 * 
 * @author stefan.norman
 */
public class DBAuthenticator extends Authenticator {

	private static Log log = LogFactory.getLog(DBAuthenticator.class);

	private String table;
	private String userClass;
	private HashMap columns = new HashMap();

	public String getTable() {
		return table;
	}

	public void setTable(String table) {
		this.table = table;
	}

	public String getUserClass() {
		return userClass;
	}

	public void setUserClass(String userClass) {
		this.userClass = userClass;
	}

	public void addColumn(String column,
			String columnName) {
		columns.put(column, columnName);
	}

	public AuthenticatedUser authenticate(String username, String password,
			String ipaddress) throws AuthenticationException {
		AuthenticatedUser user = null;
		if (username == null || username.trim().length() == 0
				|| password == null || password.trim().length() == 0) {
			throw new AuthenticationException(
					"Authentication failed: Invalid arguments");
		}
		try {

			ContentManager contentManager = ContentManager.getContentManager();
			List result = new ArrayList();
			String sql = "SELECT * FROM " + table + " WHERE "
					+ columns.get("username") + "= ? AND "
					+ columns.get("password") + "= '?'";
			
			String[] preparedVariables = new String[] {username, password};
			
			
			
			if(log.isDebugEnabled()) {
				log.debug(sql);
			}
			contentManager.doQuery(new Query(sql, preparedVariables, result));
			
			if(log.isDebugEnabled()) {
				log.debug("found " + result.size() + " records");
			}
			if(result.size() > 0) {
				// get the first found row and create user object
				Map row = (Map) result.get(0);

				// intantiate the user class an add the map
				Class clazz = Class.forName(userClass);
				if(log.isDebugEnabled()) {
					log.debug("creating user class " + clazz.getName());
				}
				DBUser dbUser = (DBUser)clazz.newInstance();
				dbUser.init(row);
				user = dbUser;
			}

		} catch (Exception e) {
			log.error("Authentication failed: Finding user failed");
			if (log.isDebugEnabled()) {
				log.debug(e.getMessage(), e);
			}
		}
		if (user == null) {
			throw new AuthenticationException(
					"Authentication failed: User not found");
		}
		return user;
	}

	public void logout(String token) {
		// do nothing
	}

	public void passwordReminder(String emailAddress, String publication)
			throws ReminderException {
		// do nothing
	}

	public void register(String username, String password)
			throws RegistrationException {
		// do nothing
	}

	private Comparator getComparator() {
		return new Comparator() {
			public int compare(Object o1, Object o2) {
				String[] arr1 = (String[]) o1;
				String[] arr2 = (String[]) o2;
				Integer i1 = new Integer(arr1[2]);
				Integer i2 = new Integer(arr2[2]);
				return i1.compareTo(i2);
			}
		};
	}
	
	private static class Query implements TransactionOperation {
		private String query;
		private List list;
		private String[] variables;

		public Query(String query, String[] variables, List list) {
			this.query = query;
			this.variables = variables;
			this.list = list;
		}

		public void execute(Transaction t) throws SQLException {
			//Statement st = t.getConnection().createStatement();
			Statement st = t.getConnection().prepareStatement(query, variables);
			try {
				ResultSet rs = st.executeQuery(query);
				ResultSetMetaData metaData = rs.getMetaData();
				while (rs.next()) {
					Map map = new HashMap();
					for (int i = 0; i < metaData.getColumnCount(); i++) {
						map.put(metaData.getColumnLabel(i + 1), rs.getString(i + 1));
					}
					list.add(map);
				}
	        } finally {
	            st.close();
	        }
		}
	}
}
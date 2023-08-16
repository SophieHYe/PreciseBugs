/**
 * Copyright 2006 OCLC Online Computer Library Center Licensed under the Apache
 * License, Version 2.0 (the "License"); you may not use this file except in
 * compliance with the License. You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0 Unless required by applicable law or
 * agreed to in writing, software distributed under the License is distributed on
 * an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
 * or implied. See the License for the specific language governing permissions and
 * limitations under the License.
 */

package ORG.oclc.oai.server.catalog;

import java.io.IOException;
import java.io.UnsupportedEncodingException;
import java.net.URLEncoder;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.ResultSet;
import java.sql.ResultSetMetaData;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.Date;
import java.util.Enumeration;
import java.util.HashMap;
import java.util.Iterator;
import java.util.Map;
import java.util.NoSuchElementException;
import java.util.Properties;
import java.util.StringTokenizer;
import java.util.Vector;

import ORG.oclc.oai.server.verb.BadResumptionTokenException;
import ORG.oclc.oai.server.verb.CannotDisseminateFormatException;
import ORG.oclc.oai.server.verb.IdDoesNotExistException;
import ORG.oclc.oai.server.verb.NoItemsMatchException;
import ORG.oclc.oai.server.verb.NoMetadataFormatsException;
import ORG.oclc.oai.server.verb.NoSetHierarchyException;
import ORG.oclc.oai.server.verb.OAIInternalServerError;
import ORG.oclc.oai.util.OAIUtil;

/**
 * JDBCOAICatalog is an example of how to implement the AbstractCatalog interface
 * for JDBC. Pattern an implementation of the AbstractCatalog interface after this class
 * to have OAICat work with your JDBC database.
 *
 * @author Jeffrey A. Young, OCLC Online Computer Library Center
 * @deprecated Use ExtendedJDBCOAICatalog instead
 */
public class JDBCOAICatalog extends AbstractCatalog {
    private static final boolean debug = false;
    
    /**
     * SQL identifier query (loaded from properties)
     * \\i -> localIdentifier, \\o -> oaiIdentifier
     */
    private String identifierQuery = null;
    
    /**
     * SQL range query (loaded from properties)
     * \\f -> from, \\u -> until
     */
    private String rangeQuery = null;
    
    /**
     * SQL range query (loaded from properties)
     * \\f -> from, \\u -> until, \\s -> set
     */
    private String rangeSetQuery = null;
    
    /**
     * SQL query to get a list of available sets
     */
    private String setQuery = null;
    
    /**
     * SQL query to get a list of available sets that apply to a particular identifier
     */
    private String setSpecQuery = null;
    
    /**
     * SQL query to get a list of available abouts that apply to a particular identifier
     */
    private String aboutQuery = null;
    
    /**
     * SQL column labels containing the values of particular interest
     */
    private String aboutValueLabel = null;
    private String setSpecItemLabel = null;
    private String setSpecListLabel = null;
    private String setNameLabel = null;
    private String setDescriptionLabel = null;
    
    /**
     * maximum number of entries to return for ListRecords and ListIdentifiers
     * (loaded from properties)
     */
    private int maxListSize;
    
    /**
     * The format required for dates in SQL queries
     * "UTC" = YYYY-MM-DD or YYYY-MM-DDTHH:MM:SSZ
     * other = YYYY/MM/DD
     */
    private String dateFormat = null;
    
    /**
     * Set Strings to be loaded from the properties file
     * (if they are to be loaded from properties rather than queried from the database)
     */
    ArrayList sets = new ArrayList();
    
    /**
     * Assume the connection will be persistent unless configured otherwise
     */
    private boolean isPersistentConnection = true;
    
    /**
     * The JDBC Connection
     */
    private Connection persistentConnection = null;
    
    /**
     * pending resumption tokens
     */
    private HashMap resumptionResults = new HashMap();
    private String jdbcURL = null;
    private String jdbcLogin = null;
    private String jdbcPasswd = null;
    
    /**
     * Construct a JDBCOAICatalog object
     *
     * @param properties a properties object containing initialization parameters
     * @exception IOException an I/O error occurred during database initialization.
     */
    public JDBCOAICatalog(Properties properties) throws IOException {
        dateFormat = properties.getProperty("JDBCOAICatalog.dateFormat");
        String maxListSize = properties.getProperty("JDBCOAICatalog.maxListSize");
        if (maxListSize == null) {
            throw new IllegalArgumentException("JDBCOAICatalog.maxListSize is missing from the properties file");
        } else {
            this.maxListSize = Integer.parseInt(maxListSize);
        }
        
        String jdbcDriverName = properties.getProperty("JDBCOAICatalog.jdbcDriverName");
        if (jdbcDriverName == null) {
            throw new IllegalArgumentException("JDBCOAICatalog.jdbcDriverName is missing from the properties file");
        }
        jdbcURL = properties.getProperty("JDBCOAICatalog.jdbcURL");
        if (jdbcURL == null) {
            throw new IllegalArgumentException("JDBCOAICatalog.jdbcURL is missing from the properties file");
        }
        
        jdbcLogin = properties.getProperty("JDBCOAICatalog.jdbcLogin");
        if (jdbcLogin == null) {
            throw new IllegalArgumentException("JDBCOAICatalog.jdbcLogin is missing from the properties file");
        }
        
        jdbcPasswd = properties.getProperty("JDBCOAICatalog.jdbcPasswd");
        if (jdbcPasswd == null) {
            throw new IllegalArgumentException("JDBCOAICatalog.jdbcPasswd is missing from the properties file");
        }
        
        rangeQuery = properties.getProperty("JDBCOAICatalog.rangeQuery");
        if (rangeQuery == null) {
            throw new IllegalArgumentException("JDBCOAICatalog.rangeQuery is missing from the properties file");
        }
        
        rangeSetQuery = properties.getProperty("JDBCOAICatalog.rangeSetQuery");
        if (rangeSetQuery == null) {
            throw new IllegalArgumentException("JDBCOAICatalog.rangeSetQuery is missing from the properties file");
        }
        
        identifierQuery = properties.getProperty("JDBCOAICatalog.identifierQuery");
        if (identifierQuery == null) {
            throw new IllegalArgumentException("JDBCOAICatalog.identifierQuery is missing from the properties file");
        }
        
        aboutQuery = properties.getProperty("JDBCOAICatalog.aboutQuery");
        if (aboutQuery != null) {
            aboutValueLabel = properties.getProperty("JDBCOAICatalog.aboutValueLabel");
            if (aboutValueLabel == null) {
                throw new IllegalArgumentException("JDBCOAICatalog.aboutValueLabel is missing from the properties file");
            }
        }
        
        setSpecQuery = properties.getProperty("JDBCOAICatalog.setSpecQuery");
        setSpecItemLabel = properties.getProperty("JDBCOAICatalog.setSpecItemLabel");
        if (setSpecItemLabel == null) {
            throw new IllegalArgumentException("JDBCOAICatalog.setSpecItemLabel is missing from the properties file");
        }
        setSpecListLabel = properties.getProperty("JDBCOAICatalog.setSpecListLabel");
        if (setSpecListLabel == null) {
            throw new IllegalArgumentException("JDBCOAICatalog.setSpecListLabel is missing from the properties file");
        }
        setNameLabel = properties.getProperty("JDBCOAICatalog.setNameLabel");
        if (setNameLabel == null) {
            throw new IllegalArgumentException("JDBCOAICatalog.setNameLabel is missing from the properties file");
        }
        
        String temp = properties.getProperty("JDBCOAICatalog.isPersistentConnection");
        if ("false".equalsIgnoreCase(temp)) {
            isPersistentConnection = false;
        }
        
        setDescriptionLabel = properties.getProperty("JDBCOAICatalog.setDescriptionLabel");
//      if (setDescriptionLabel == null) {
//      throw new IllegalArgumentException("JDBCOAICatalog.setDescriptionLabel is missing from the properties file");
//      }
        
        // See if a setQuery exists
        setQuery = properties.getProperty("JDBCOAICatalog.setQuery");
        if (setQuery == null) {
            // if not, load the set Strings from the properties file (if present)
            String propertyPrefix = "Sets.";
            Enumeration propNames = properties.propertyNames();
            while (propNames.hasMoreElements()) {
                String propertyName = (String)propNames.nextElement();
                if (propertyName.startsWith(propertyPrefix)) {
                    sets.add(properties.get(propertyName));
                }
            }
        }
        
        try {
            Class.forName(jdbcDriverName);
        } catch (ClassNotFoundException e) {
            throw new IllegalArgumentException("JDBCOAICatalog.jdbcDriverName is invalid: "
                    + jdbcDriverName);
        }
        
        if (isPersistentConnection) {
            try {
                persistentConnection = getNewConnection();
            } catch (SQLException e) {
                e.printStackTrace();
                throw new IOException(e.getMessage());
            }
        }
    }
    
    private Connection getNewConnection() throws SQLException {
        // open the connection
        return DriverManager.getConnection(jdbcURL, jdbcLogin, jdbcPasswd);
    }
    
    private Connection startConnection() throws SQLException {
        if (persistentConnection != null) {
            if (persistentConnection.isClosed()) {
                System.out.println("Persistent connection has expired.");
                persistentConnection = getNewConnection();
            }
            return persistentConnection;
        } else {
            return getNewConnection();
        }
    }
    
    private void endConnection(Connection con) throws OAIInternalServerError {
        if (persistentConnection == null) {
            try {
                if (debug) System.out.println("ending connection");
                con.close();
            } catch (SQLException e) {
                throw new OAIInternalServerError(e.getMessage());
            }
        }
    }
    
    /**
     * Retrieve a list of schemaLocation values associated with the specified
     * oaiIdentifier.
     *
     * @param oaiIdentifier the OAI identifier
     * @return a Vector containing schemaLocation Strings
     * @exception OAIInternalServerError signals an http status code 500 problem
     * @exception IdDoesNotExistException the specified oaiIdentifier can't be found
     * @exception NoMetadataFormatsException the specified oaiIdentifier was found
     * but the item is flagged as deleted and thus no schemaLocations (i.e.
     * metadataFormats) can be produced.
     */
    public Vector getSchemaLocations(String oaiIdentifier)
    throws OAIInternalServerError, IdDoesNotExistException, NoMetadataFormatsException {
        Connection con = null;
        try {
            con = startConnection();
            Statement stmt = con.createStatement();
            ResultSet rs =
                stmt.executeQuery(populateIdentifierQuery(oaiIdentifier));
            /*
             * Let your recordFactory decide which schemaLocations
             * (i.e. metadataFormats) it can produce from the record.
             * Doing so will preserve the separation of database access
             * (which happens here) from the record content interpretation
             * (which is the responsibility of the RecordFactory implementation).
             */
            if (!rs.next()) {
                endConnection(con);
                throw new IdDoesNotExistException(oaiIdentifier);
            } else {
                /* Make sure the identifierQuery returns the columns you need
                 * (if any) to determine the supported schemaLocations for this item */
                HashMap nativeItem = getColumnValues(rs);
                endConnection(con);
                return getRecordFactory().getSchemaLocations(nativeItem);
            }
        } catch (SQLException e) {
            if (con != null)
                endConnection(con);
            e.printStackTrace();
            throw new OAIInternalServerError(e.getMessage());
        }
    }
    
    /**
     * Since the columns should only be read once, copy them into a
     * HashMap and consider that to be the "record"
     * @param rs The ResultSet row
     * @return a HashMap mapping column names with values
     */
    private HashMap getColumnValues(ResultSet rs)
    throws SQLException {
        ResultSetMetaData mdata = rs.getMetaData();
        int count = mdata.getColumnCount();
        HashMap nativeItem = new HashMap(count);
        for (int i=1; i<=count; ++i) {
            String fieldName = new StringBuffer().append(mdata.getTableName(i)).append(".").append(mdata.getColumnName(i)).toString();
            nativeItem.put(fieldName, rs.getObject(i));
            if (debug) System.out.println(fieldName + "=" + nativeItem.get(fieldName));
//          if (debug) {
//          System.out.println("JDBCOAICatalog.getColumnValues");
//          System.out.println("catalogName=" + mdata.getCatalogName(i));
//          System.out.println("columnLabel=" + mdata.getColumnLabel(i));
//          System.out.println("columnName=" + mdata.getColumnName(i));
//          System.out.println("schemaName=" + mdata.getSchemaName(i));
//          System.out.println("tableName=" + mdata.getTableName(i));
//          }
        }
        return nativeItem;
    }
    
    /**
     * insert actual from, until, and set parameters into the rangeQuery String
     *
     * @param from the OAI from parameter
     * @param until the OAI until paramter
     * @param set the OAI set parameter
     * @return a String containing an SQL query
     */
    private String populateRangeQuery(String from, String until, String set)
    throws OAIInternalServerError {
        StringBuffer sb = new StringBuffer();
        StringTokenizer tokenizer;
        if (set == null || set.length() == 0)
            tokenizer = new StringTokenizer(rangeQuery, "\\");
        else
            tokenizer = new StringTokenizer(rangeSetQuery, "\\");
        
        if (tokenizer.hasMoreTokens())
            sb.append(tokenizer.nextToken());
        else
            throw new OAIInternalServerError("Invalid query");
        
        while (tokenizer.hasMoreTokens()) {
            String token = tokenizer.nextToken();
            switch (token.charAt(0)) {
            case 'f':  // what are the chances someone would use this to indicate a form feed?
                sb.append(formatFromDate(from));
                break;
            case 'u':
                sb.append(formatUntilDate(until));
                break;
            case 's':
                sb.append(set);
                break;
            default: // ignore it
                sb.append("\\");
            sb.append(token.charAt(0));
            }
            sb.append(token.substring(1));
        }
        if (debug) System.out.println(sb.toString());
        return sb.toString();
    }
    
    /**
     * Extend this class and override this method if necessary.
     */
    protected String formatFromDate(String date) {
        return formatDate(date);
    }
    
    /**
     * Extend this class and override this method if necessary.
     */
    protected String formatUntilDate(String date) {
        return formatDate(date);
    }
    
    /**
     * Change the String from UTC to SQL format
     * If this method doesn't suit your needs, extend this class and override
     * the method rather than change this code directly. 
     */
    protected String formatDate(String date) {
        if ("UTC".equals(dateFormat)) {
            return date;
        } else {
            StringBuffer sb = new StringBuffer();
            sb.append(date.substring(5, 7));
            sb.append("/");
            sb.append(date.substring(8));
            sb.append("/");
            sb.append(date.substring(0, 4));
            if (debug) System.out.println("JDBCOAICatalog.formatDate: from " + date + " to " + sb.toString());
            return sb.toString();
        }
    }
    
    /**
     * insert actual from, until, and set parameters into the identifierQuery String
     *
     * @param from the OAI from parameter
     * @param until the OAI until paramter
     * @param set the OAI set parameter
     * @return a String containing an SQL query
     */
    private String populateIdentifierQuery(String oaiIdentifier)
    throws OAIInternalServerError {
        StringTokenizer tokenizer = new StringTokenizer(identifierQuery, "\\");
        StringBuffer sb = new StringBuffer();
        if (tokenizer.hasMoreTokens())
            sb.append(tokenizer.nextToken());
        else
            throw new OAIInternalServerError("Invalid identifierQuery");
        
        while (tokenizer.hasMoreTokens()) {
            String token = tokenizer.nextToken();
            switch (token.charAt(0)) {
            case 'i':
                sb.append(getRecordFactory().fromOAIIdentifier(oaiIdentifier));
                break;
            case 'o':
                sb.append(oaiIdentifier);
                break;
            default: // ignore it
                sb.append("\\");
            sb.append(token.charAt(0));
            }
            sb.append(token.substring(1));
        }
        if (debug) System.out.println(sb.toString());
        return sb.toString();
    }
    
    /**
     * insert actual from, until, and set parameters into the identifierQuery String
     *
     * @param from the OAI from parameter
     * @param until the OAI until paramter
     * @param set the OAI set parameter
     * @return a String containing an SQL query
     */
    private String populateSetSpecQuery(String oaiIdentifier)
    throws OAIInternalServerError {
        StringTokenizer tokenizer = new StringTokenizer(setSpecQuery, "\\");
        StringBuffer sb = new StringBuffer();
        if (tokenizer.hasMoreTokens())
            sb.append(tokenizer.nextToken());
        else
            throw new OAIInternalServerError("Invalid identifierQuery");
        
        while (tokenizer.hasMoreTokens()) {
            String token = tokenizer.nextToken();
            switch (token.charAt(0)) {
            case 'i':
                sb.append(getRecordFactory().fromOAIIdentifier(oaiIdentifier));
                break;
            case 'o':
                sb.append(oaiIdentifier);
                break;
            default: // ignore it
                sb.append("\\");
            sb.append(token.charAt(0));
            }
            sb.append(token.substring(1));
        }
        if (debug) System.out.println(sb.toString());
        return sb.toString();
    }
    
    /**
     * insert actual from, until, and set parameters into the identifierQuery String
     *
     * @param from the OAI from parameter
     * @param until the OAI until paramter
     * @param set the OAI set parameter
     * @return a String containing an SQL query
     */
    private String populateAboutQuery(String oaiIdentifier)
    throws OAIInternalServerError {
        StringTokenizer tokenizer = new StringTokenizer(aboutQuery, "\\");
        StringBuffer sb = new StringBuffer();
        if (tokenizer.hasMoreTokens())
            sb.append(tokenizer.nextToken());
        else
            throw new OAIInternalServerError("Invalid identifierQuery");
        
        while (tokenizer.hasMoreTokens()) {
            String token = tokenizer.nextToken();
            switch (token.charAt(0)) {
            case 'i':
                sb.append(getRecordFactory().fromOAIIdentifier(oaiIdentifier));
                break;
            case 'o':
                sb.append(oaiIdentifier);
                break;
            default: // ignore it
                sb.append("\\");
            sb.append(token.charAt(0));
            }
            sb.append(token.substring(1));
        }
        if (debug) System.out.println(sb.toString());
        return sb.toString();
    }
    
    /**
     * Retrieve a list of identifiers that satisfy the specified criteria
     *
     * @param from beginning date using the proper granularity
     * @param until ending date using the proper granularity
     * @param set the set name or null if no such limit is requested
     * @param metadataPrefix the OAI metadataPrefix or null if no such limit is requested
     * @return a Map object containing entries for "headers" and "identifiers" Iterators
     * (both containing Strings) as well as an optional "resumptionMap" Map.
     * It may seem strange for the map to include both "headers" and "identifiers"
     * since the identifiers can be obtained from the headers. This may be true, but
     * AbstractCatalog.listRecords() can operate quicker if it doesn't
     * need to parse identifiers from the XML headers itself. Better
     * still, do like I do below and override AbstractCatalog.listRecords().
     * AbstractCatalog.listRecords() is relatively inefficient because given the list
     * of identifiers, it must call getRecord() individually for each as it constructs
     * its response. It's much more efficient to construct the entire response in one fell
     * swoop by overriding listRecords() as I've done here.
     * @exception OAIInternalServerError signals an http status code 500 problem
     */
    public Map listIdentifiers(String from, String until, String set, String metadataPrefix)
    throws NoItemsMatchException, OAIInternalServerError {
        purge(); // clean out old resumptionTokens
        Map listIdentifiersMap = new HashMap();
        ArrayList headers = new ArrayList();
        ArrayList identifiers = new ArrayList();
        Connection con = null;
        
        try {
            con = startConnection();
            /* Get some records from your database */
            Statement stmt = con.createStatement(ResultSet.TYPE_SCROLL_INSENSITIVE, ResultSet.CONCUR_READ_ONLY);
            ResultSet rs = stmt.executeQuery(populateRangeQuery(from, until, set));
            rs.last();
            int numRows = rs.getRow();
            if (numRows == 0) {
                endConnection(con);
                throw new NoItemsMatchException();
            }
            rs.beforeFirst();
            int count;
            
            /* load the headers and identifiers ArrayLists. */
            for (count=0; count < maxListSize && rs.next(); ++count) {
                HashMap nativeItem = getColumnValues(rs);
                /* Use the RecordFactory to extract header/identifier pairs for each item */
//                RecordFactory rf = getRecordFactory();
                Iterator setSpecs = getSetSpecs(nativeItem);
                String[] header = getRecordFactory().createHeader(nativeItem, setSpecs);
                headers.add(header[0]);
                identifiers.add(header[1]);
            }
            
            /* decide if you're done */
            if (count < numRows) {
                String resumptionId = getResumptionId();
                resumptionResults.put(resumptionId, rs);
                
                /*****************************************************************
                 * Construct the resumptionToken String however you see fit.
                 *****************************************************************/
                StringBuffer resumptionTokenSb = new StringBuffer();
                resumptionTokenSb.append(resumptionId);
                resumptionTokenSb.append("!");
                resumptionTokenSb.append(Integer.toString(count));
                resumptionTokenSb.append("!");
                resumptionTokenSb.append(Integer.toString(numRows));
                resumptionTokenSb.append("!");
                resumptionTokenSb.append(metadataPrefix);
                
                /*****************************************************************
                 * Use the following line if you wish to include the optional
                 * resumptionToken attributes in the response. Otherwise, use the
                 * line after it that I've commented out.
                 *****************************************************************/
                listIdentifiersMap.put("resumptionMap", getResumptionMap(resumptionTokenSb.toString(),
                        numRows,
                        0));
                //          listIdentifiersMap.put("resumptionMap",
                //                                 getResumptionMap(resumptionTokenSb.toString()));
            }
            endConnection(con);
        } catch (SQLException e) {
            if (con != null)
                endConnection(con);
            e.printStackTrace();
            throw new OAIInternalServerError(e.getMessage());
        }
        
        listIdentifiersMap.put("headers", headers.iterator());
        listIdentifiersMap.put("identifiers", identifiers.iterator());
        return listIdentifiersMap;
    }
    
    /**
     * Retrieve the next set of identifiers associated with the resumptionToken
     *
     * @param resumptionToken implementation-dependent format taken from the
     * previous listIdentifiers() Map result.
     * @return a Map object containing entries for "headers" and "identifiers" Iterators
     * (both containing Strings) as well as an optional "resumptionMap" Map.
     * @exception BadResumptionTokenException the value of the resumptionToken
     * is invalid or expired.
     * @exception OAIInternalServerError signals an http status code 500 problem
     */
    public Map listIdentifiers(String resumptionToken)
    throws BadResumptionTokenException, OAIInternalServerError {
        purge(); // clean out old resumptionTokens
        Map listIdentifiersMap = new HashMap();
        ArrayList headers = new ArrayList();
        ArrayList identifiers = new ArrayList();
        
        /**********************************************************************
         * parse your resumptionToken and look it up in the resumptionResults,
         * if necessary
         **********************************************************************/
        StringTokenizer tokenizer = new StringTokenizer(resumptionToken, "!");
        String resumptionId;
        int oldCount;
        String metadataPrefix;
        int numRows;
        try {
            resumptionId = tokenizer.nextToken();
            oldCount = Integer.parseInt(tokenizer.nextToken());
            numRows = Integer.parseInt(tokenizer.nextToken());
            metadataPrefix = tokenizer.nextToken();
        } catch (NoSuchElementException e) {
            throw new BadResumptionTokenException();
        }
        
        try {
            /* Get some more records from your database */
            ResultSet rs = (ResultSet)resumptionResults.get(resumptionId);
            if (rs == null) {
                throw new BadResumptionTokenException();
            }
            int count;
            
            if (rs.getRow() != oldCount) {
//              System.out.println("JDBCOAICatalog.listIdentifiers: reuse of old resumptionToken?");
                rs.absolute(oldCount);
            }
            
            /* load the headers and identifiers ArrayLists. */
            for (count = 0; count < maxListSize && rs.next(); ++count) {
                HashMap nativeItem = getColumnValues(rs);
                /* Use the RecordFactory to extract header/identifier pairs for each item */
                Iterator setSpecs = getSetSpecs(nativeItem);
                String[] header = getRecordFactory().createHeader(nativeItem, setSpecs);
                headers.add(header[0]);
                identifiers.add(header[1]);
            }
            
            /* decide if you're done. */
            if (oldCount+count < numRows) {
                /*****************************************************************
                 * Construct the resumptionToken String however you see fit.
                 *****************************************************************/
                StringBuffer resumptionTokenSb = new StringBuffer();
                resumptionTokenSb.append(resumptionId);
                resumptionTokenSb.append("!");
                resumptionTokenSb.append(Integer.toString(oldCount + count));
                resumptionTokenSb.append("!");
                resumptionTokenSb.append(Integer.toString(numRows));
                resumptionTokenSb.append("!");
                resumptionTokenSb.append(metadataPrefix);
                
                /*****************************************************************
                 * Use the following line if you wish to include the optional
                 * resumptionToken attributes in the response. Otherwise, use the
                 * line after it that I've commented out.
                 *****************************************************************/
                listIdentifiersMap.put("resumptionMap", getResumptionMap(resumptionTokenSb.toString(),
                        numRows,
                        oldCount));
                //          listIdentifiersMap.put("resumptionMap",
                //                                 getResumptionMap(resumptionTokenSb.toString()));
            }
        } catch (SQLException e) {
            e.printStackTrace();
            throw new OAIInternalServerError(e.getMessage());
        }
        
        listIdentifiersMap.put("headers", headers.iterator());
        listIdentifiersMap.put("identifiers", identifiers.iterator());
        return listIdentifiersMap;
    }
    
    /**
     * Retrieve the specified metadata for the specified oaiIdentifier
     *
     * @param oaiIdentifier the OAI identifier
     * @param metadataPrefix the OAI metadataPrefix
     * @return the <record/> portion of the XML response.
     * @exception OAIInternalServerError signals an http status code 500 problem
     * @exception CannotDisseminateFormatException the metadataPrefix is not
     * supported by the item.
     * @exception IdDoesNotExistException the oaiIdentifier wasn't found
     */
    public String getRecord(String oaiIdentifier, String metadataPrefix)
    throws OAIInternalServerError, CannotDisseminateFormatException,
    IdDoesNotExistException {
        Connection con = null;
        try {
            con = startConnection();
            Statement stmt = con.createStatement();
            ResultSet rs =
                stmt.executeQuery(populateIdentifierQuery(oaiIdentifier));
            if (!rs.next()) {
                endConnection(con);
                throw new IdDoesNotExistException(oaiIdentifier);
            }
            HashMap nativeItem = getColumnValues(rs);
            endConnection(con);
            return constructRecord(nativeItem, metadataPrefix);
        } catch (SQLException e) {
            if (con != null)
                endConnection(con);
            e.printStackTrace();
            throw new OAIInternalServerError(e.getMessage());
        }
    }
    
    /**
     * Retrieve a list of records that satisfy the specified criteria. Note, though,
     * that unlike the other OAI verb type methods implemented here, both of the
     * listRecords methods are already implemented in AbstractCatalog rather than
     * abstracted. This is because it is possible to implement ListRecords as a
     * combination of ListIdentifiers and GetRecord combinations. Nevertheless,
     * I suggest that you override both the AbstractCatalog.listRecords methods
     * here since it will probably improve the performance if you create the
     * response in one fell swoop rather than construct it one GetRecord at a time.
     *
     * @param from beginning date using the proper granularity
     * @param until ending date using the proper granularity
     * @param set the set name or null if no such limit is requested
     * @param metadataPrefix the OAI metadataPrefix or null if no such limit is requested
     * @return a Map object containing entries for a "records" Iterator object
     * (containing XML <record/> Strings) and an optional "resumptionMap" Map.
     * @exception OAIInternalServerError signals an http status code 500 problem
     * @exception CannotDisseminateFormatException the metadataPrefix isn't
     * supported by the item.
     */
    public Map listRecords(String from, String until, String set, String metadataPrefix)
    throws CannotDisseminateFormatException, NoItemsMatchException,
    OAIInternalServerError {
        purge(); // clean out old resumptionTokens
        Map listRecordsMap = new HashMap();
        ArrayList records = new ArrayList();
        Connection con = null;
        
        try {
            con = startConnection();
            /* Get some records from your database */
            Statement stmt = con.createStatement(ResultSet.TYPE_SCROLL_INSENSITIVE,
                    ResultSet.CONCUR_READ_ONLY);
            ResultSet rs = stmt.executeQuery(populateRangeQuery(from, until, set));
            rs.last();
            int numRows = rs.getRow();
            if (numRows == 0) {
                endConnection(con);
                throw new NoItemsMatchException();
            }
            rs.beforeFirst();
            int count;
            
//          if (debug) System.out.println("JDBCOAICatalog.listRecords: numRows=" + numRows);
            
            /* load the records ArrayList */
            for (count=0; count < maxListSize && rs.next(); ++count) {
                HashMap nativeItem = getColumnValues(rs);
                String record = constructRecord(nativeItem, metadataPrefix);
                records.add(record);
            }
            
            /* decide if you're done */
            if (count < numRows) {
                String resumptionId = getResumptionId();
                resumptionResults.put(resumptionId, rs);
                
                /*****************************************************************
                 * Construct the resumptionToken String however you see fit.
                 *****************************************************************/
                StringBuffer resumptionTokenSb = new StringBuffer();
                resumptionTokenSb.append(resumptionId);
                resumptionTokenSb.append("!");
                resumptionTokenSb.append(Integer.toString(count));
                resumptionTokenSb.append("!");
                resumptionTokenSb.append(Integer.toString(numRows));
                resumptionTokenSb.append("!");
                resumptionTokenSb.append(metadataPrefix);
                
                /*****************************************************************
                 * Use the following line if you wish to include the optional
                 * resumptionToken attributes in the response. Otherwise, use the
                 * line after it that I've commented out.
                 *****************************************************************/
                listRecordsMap.put("resumptionMap", getResumptionMap(resumptionTokenSb.toString(),
                        numRows,
                        0));
                //          listRecordsMap.put("resumptionMap",
                //                                 getResumptionMap(resumptionTokenSbSb.toString()));
                endConnection(con);
            }
        } catch (SQLException e) {
            if (con != null)
                endConnection(con);
            e.printStackTrace();
            throw new OAIInternalServerError(e.getMessage());
        }
        
        listRecordsMap.put("records", records.iterator());
        return listRecordsMap;
    }
    
    /**
     * Retrieve the next set of records associated with the resumptionToken
     *
     * @param resumptionToken implementation-dependent format taken from the
     * previous listRecords() Map result.
     * @return a Map object containing entries for "headers" and "identifiers" Iterators
     * (both containing Strings) as well as an optional "resumptionMap" Map.
     * @exception OAIInternalServerError signals an http status code 500 problem
     * @exception BadResumptionTokenException the value of the resumptionToken argument
     * is invalid or expired.
     */
    public Map listRecords(String resumptionToken)
    throws BadResumptionTokenException, OAIInternalServerError {
        Map listRecordsMap = new HashMap();
        ArrayList records = new ArrayList();
        purge(); // clean out old resumptionTokens
        
        /**********************************************************************
         * parse your resumptionToken and look it up in the resumptionResults,
         * if necessary
         **********************************************************************/
        StringTokenizer tokenizer = new StringTokenizer(resumptionToken, "!");
        String resumptionId;
        int oldCount;
        int numRows;
        String metadataPrefix;
        try {
            resumptionId = tokenizer.nextToken();
            oldCount = Integer.parseInt(tokenizer.nextToken());
            numRows = Integer.parseInt(tokenizer.nextToken());
            metadataPrefix = tokenizer.nextToken();
        } catch (NoSuchElementException e) {
            throw new BadResumptionTokenException();
        }
        
        try {
            /* Get some more records from your database */
            ResultSet rs = (ResultSet)resumptionResults.get(resumptionId);
            if (rs == null) {
                throw new BadResumptionTokenException();
            }
            
            if (rs.getRow() != oldCount) {
//              System.out.println("JDBCOAICatalog.listIdentifiers: reuse of old resumptionToken?");
                rs.absolute(oldCount);
            }
            
            int count;
            
            /* load the headers and identifiers ArrayLists. */
            for (count = 0; count < maxListSize && rs.next(); ++count) {
                try {
                    HashMap nativeItem = getColumnValues(rs);
                    String record = constructRecord(nativeItem, metadataPrefix);
                    records.add(record);
                } catch (CannotDisseminateFormatException e) {
                    /* the client hacked the resumptionToken beyond repair */
                    throw new BadResumptionTokenException();
                }
            }
            
            /* decide if you're done */
            if (oldCount+count < numRows) {
                /*****************************************************************
                 * Construct the resumptionToken String however you see fit.
                 *****************************************************************/
                StringBuffer resumptionTokenSb = new StringBuffer();
                resumptionTokenSb.append(resumptionId);
                resumptionTokenSb.append("!");
                resumptionTokenSb.append(Integer.toString(oldCount + count));
                resumptionTokenSb.append("!");
                resumptionTokenSb.append(Integer.toString(numRows));
                resumptionTokenSb.append("!");
                resumptionTokenSb.append(metadataPrefix);
                
                /*****************************************************************
                 * Use the following line if you wish to include the optional
                 * resumptionToken attributes in the response. Otherwise, use the
                 * line after it that I've commented out.
                 *****************************************************************/
                listRecordsMap.put("resumptionMap", getResumptionMap(resumptionTokenSb.toString(),
                        numRows,
                        oldCount));
                //          listRecordsMap.put("resumptionMap",
                //                                 getResumptionMap(resumptionTokenSb.toString()));
            }
        } catch (SQLException e) {
            e.printStackTrace();
            throw new OAIInternalServerError(e.getMessage());
        }
        
        listRecordsMap.put("records", records.iterator());
        return listRecordsMap;
    }
    
    /**
     * Utility method to construct a Record object for a specified
     * metadataFormat from a native record
     *
     * @param nativeItem native item from the dataase
     * @param metadataPrefix the desired metadataPrefix for performing the crosswalk
     * @return the <record/> String
     * @exception CannotDisseminateFormatException the record is not available
     * for the specified metadataPrefix.
     */
    private String constructRecord(HashMap nativeItem, String metadataPrefix)
    throws CannotDisseminateFormatException, OAIInternalServerError {
        String schemaURL = null;
        Iterator setSpecs = getSetSpecs(nativeItem);
        Iterator abouts = getAbouts(nativeItem);
        
        if (metadataPrefix != null) {
            if ((schemaURL = getCrosswalks().getSchemaURL(metadataPrefix)) == null)
                throw new CannotDisseminateFormatException(metadataPrefix);
        }
        return getRecordFactory().create(nativeItem, schemaURL, metadataPrefix, setSpecs, abouts);
    }
    
    /**
     * Retrieve a list of sets that satisfy the specified criteria
     *
     * @return a Map object containing "sets" Iterator object (contains
     * <setSpec/> XML Strings) as well as an optional resumptionMap Map.
     * @exception OAIBadRequestException signals an http status code 400 problem
     * @exception OAIInternalServerError signals an http status code 500 problem
     */
    public Map listSets() throws NoSetHierarchyException, OAIInternalServerError {
        Connection con = null;
        if (setQuery == null) {
            if (sets.size() == 0)
                throw new NoSetHierarchyException();
            Map listSetsMap = new HashMap();
            listSetsMap.put("sets", sets.iterator());
            return listSetsMap;
        } else {
            purge(); // clean out old resumptionTokens
            Map listSetsMap = new HashMap();
            ArrayList sets = new ArrayList();
            
            try {
                con = startConnection();
                if (debug) System.out.println(setQuery);
                
                /* Get some records from your database */
                Statement stmt = con.createStatement(ResultSet.TYPE_SCROLL_INSENSITIVE, ResultSet.CONCUR_READ_ONLY);
                ResultSet rs = stmt.executeQuery(setQuery);
                rs.last();
                int numRows = rs.getRow();
                rs.beforeFirst();
                int count;
                
//              if (debug) System.out.println("JDBCOAICatalog.listSets: numRows=" + numRows);
                
                /* load the sets ArrayLists. */
                for (count=0; count < maxListSize && rs.next(); ++count) {
                    /* Use the RecordFactory to extract header/set pairs for each item */
                    HashMap nativeItem = getColumnValues(rs);
                    sets.add(getSetXML(nativeItem));
                    if (debug) System.out.println("JDBCOAICatalog.listSets: adding an entry");
                }
                
                /* decide if you're done */
                if (count < numRows) {
                    String resumptionId = getResumptionId();
                    /*****************************************************************
                     * Note that storing the ResultSet in the resumptionResult
                     * means the token can't be reused.
                     *****************************************************************/
                    resumptionResults.put(resumptionId, rs);
                    
                    /*****************************************************************
                     * Construct the resumptionToken String however you see fit.
                     *****************************************************************/
                    StringBuffer resumptionTokenSb = new StringBuffer();
                    resumptionTokenSb.append(resumptionId);
                    resumptionTokenSb.append("!");
                    resumptionTokenSb.append(Integer.toString(count));
                    resumptionTokenSb.append("!");
                    resumptionTokenSb.append(Integer.toString(numRows));
                    
                    /*****************************************************************
                     * Use the following line if you wish to include the optional
                     * resumptionToken attributes in the response. Otherwise, use the
                     * line after it that I've commented out.
                     *****************************************************************/
                    listSetsMap.put("resumptionMap", getResumptionMap(resumptionTokenSb.toString(),
                            numRows,
                            0));
                    //          listSetsMap.put("resumptionMap",
                    //                                 getResumptionMap(resumptionTokenSb.toString()));
                    endConnection(con);
                }
            } catch (SQLException e) {
                if (con != null)
                    endConnection(con);
                e.printStackTrace();
                throw new OAIInternalServerError(e.getMessage());
            }
            
            listSetsMap.put("sets", sets.iterator());
            return listSetsMap;
        }
    }
    
    /**
     * Retrieve the next set of sets associated with the resumptionToken
     *
     * @param resumptionToken implementation-dependent format taken from the
     * previous listSets() Map result.
     * @return a Map object containing "sets" Iterator object (contains
     * <setSpec/> XML Strings) as well as an optional resumptionMap Map.
     * @exception BadResumptionTokenException the value of the resumptionToken
     * is invalid or expired.
     * @exception OAIInternalServerError signals an http status code 500 problem
     */
    public Map listSets(String resumptionToken)
    throws OAIInternalServerError, BadResumptionTokenException {
        if (setQuery == null) {
            throw new BadResumptionTokenException();
        } else {
            purge(); // clean out old resumptionTokens
            Map listSetsMap = new HashMap();
            ArrayList sets = new ArrayList();
            
            /**********************************************************************
             * parse your resumptionToken and look it up in the resumptionResults,
             * if necessary
             **********************************************************************/
            StringTokenizer tokenizer = new StringTokenizer(resumptionToken, "!");
            String resumptionId;
            int oldCount;
            int numRows;
            try {
                resumptionId = tokenizer.nextToken();
                oldCount = Integer.parseInt(tokenizer.nextToken());
                numRows = Integer.parseInt(tokenizer.nextToken());
            } catch (NoSuchElementException e) {
                throw new BadResumptionTokenException();
            }
            
            try {
                /* Get some more records from your database */
                ResultSet rs = (ResultSet)resumptionResults.get(resumptionId);
                if (rs == null) {
                    throw new BadResumptionTokenException();
                }
                
                if (rs.getRow() != oldCount) {
//                  System.out.println("JDBCOAICatalog.listIdentifiers: reuse of old resumptionToken?");
                    rs.absolute(oldCount);
                }
                
                int count;
                
                /* load the sets ArrayLists. */
                for (count = 0; count < maxListSize && rs.next(); ++count) {
                    HashMap nativeItem = getColumnValues(rs);
                    /* Use the RecordFactory to extract set for each item */
                    sets.add(getSetXML(nativeItem));
                }
                
                /* decide if you're done. */
                if (oldCount+count < numRows) {
                    /*****************************************************************
                     * Construct the resumptionToken String however you see fit.
                     *****************************************************************/
                    StringBuffer resumptionTokenSb = new StringBuffer();
                    resumptionTokenSb.append(resumptionId);
                    resumptionTokenSb.append("!");
                    resumptionTokenSb.append(Integer.toString(oldCount + count));
                    resumptionTokenSb.append("!");
                    resumptionTokenSb.append(Integer.toString(numRows));
                    
                    /*****************************************************************
                     * Use the following line if you wish to include the optional
                     * resumptionToken attributes in the response. Otherwise, use the
                     * line after it that I've commented out.
                     *****************************************************************/
                    listSetsMap.put("resumptionMap", getResumptionMap(resumptionTokenSb.toString(),
                            numRows,
                            oldCount));
                    //          listSetsMap.put("resumptionMap",
                    //                                 getResumptionMap(resumptionTokenSb.toString()));
                }
            } catch (SQLException e) {
                e.printStackTrace();
                throw new OAIInternalServerError(e.getMessage());
            }
            
            listSetsMap.put("sets", sets.iterator());
            return listSetsMap;
        }
    }
    
    /**
     * get an Iterator containing the setSpecs for the nativeItem
     *
     * @param nativeItem
     * @return an Iterator containing the list of setSpec values for this nativeItem
     */
    private Iterator getSetSpecs(HashMap nativeItem)
    throws OAIInternalServerError {
        Connection con = null;
        try {
            ArrayList setSpecs = new ArrayList();
            if (setSpecQuery != null) {
                con = startConnection();
                RecordFactory rf = getRecordFactory();
                String oaiIdentifier = rf.getOAIIdentifier(nativeItem);
                Statement stmt = con.createStatement();
                ResultSet rs = stmt.executeQuery(populateSetSpecQuery(oaiIdentifier));
                while (rs.next()) {
                    HashMap setMap = getColumnValues(rs);
                    setSpecs.add(setMap.get(setSpecItemLabel).toString());
                }
                endConnection(con);
            }
            return setSpecs.iterator();
        } catch (SQLException e) {
            if (con != null)
                endConnection(con);
            e.printStackTrace();
            throw new OAIInternalServerError(e.getMessage());
        }
    }
    
    /**
     * get an Iterator containing the abouts for the nativeItem
     *
     * @param nativeItem
     * @return an Iterator containing the list of about values for this nativeItem
     */
    private Iterator getAbouts(HashMap nativeItem)
    throws OAIInternalServerError {
        Connection con = null;
        try {
            ArrayList abouts = new ArrayList();
            if (aboutQuery != null) {
                con = startConnection();
                RecordFactory rf = getRecordFactory();
                String oaiIdentifier = rf.getOAIIdentifier(nativeItem);
                Statement stmt = con.createStatement();
                ResultSet rs = stmt.executeQuery(populateAboutQuery(oaiIdentifier));
                while (rs.next()) {
                    HashMap aboutMap = getColumnValues(rs);
                    abouts.add(aboutMap.get(aboutValueLabel));
                }
                endConnection(con);
            }
            return abouts.iterator();
        } catch (SQLException e) {
            if (con != null)
                endConnection(con);
            e.printStackTrace();
            throw new OAIInternalServerError(e.getMessage());
        }
    }
    
    /**
     * Extract &lt;set&gt; XML string from setItem object
     *
     * @param setItem individual set instance in native format
     * @return an XML String containing the XML &lt;set&gt; content
     */
    public String getSetXML(HashMap setItem)
    throws IllegalArgumentException {
//      try {
        String setSpec = getSetSpec(setItem);
        String setName = getSetName(setItem);
        String setDescription = getSetDescription(setItem);
        
        StringBuffer sb = new StringBuffer();
        sb.append("<set>");
        sb.append("<setSpec>");
        sb.append(OAIUtil.xmlEncode(setSpec));
        sb.append("</setSpec>");
        sb.append("<setName>");
        sb.append(OAIUtil.xmlEncode(setName));
        sb.append("</setName>");
        if (setDescription != null) {
            sb.append("<setDescription>");
            sb.append(OAIUtil.xmlEncode(setDescription));
            sb.append("</setDescription>");
        }
        sb.append("</set>");
        return sb.toString();
//      } catch (SQLException e) {
//      e.printStackTrace();
//      throw new IllegalArgumentException(e.getMessage());
//      }
    }
    
    /**
     * get the setSpec XML string. Extend this class and override this method
     * if the setSpec can't be directly taken from the result set as a String
     *
     * @param setItem
     * @return an XML String containing the &lt;setSpec&gt; content
     */
    protected String getSetSpec(HashMap setItem) {
        try {
            return URLEncoder.encode((String)setItem.get(setSpecListLabel), "UTF-8");
        } catch (UnsupportedEncodingException e) {
            return "UnsupportedEncodingException";
        }
    }
    
    /**
     * get the setName XML string. Extend this class and override this method
     * if the setName can't be directly taken from the result set as a String
     *
     * @param setItem
     * @return an XML String containing the &lt;setName&gt; content
     */
    protected String getSetName(HashMap setItem) {
        return (String)setItem.get(setNameLabel);
    }
    
    /**
     * get the setDescription XML string. Extend this class and override this method
     * if the setDescription can't be directly taken from the result set as a String
     *
     * @param setItem
     * @return an XML String containing the &lt;setDescription&gt; content
     */
    protected String getSetDescription(HashMap setItem) {
        if (setDescriptionLabel == null)
            return null;
        return (String)setItem.get(setDescriptionLabel);
    }
    
    /**
     * close the repository
     */
    public void close() {
        try {
            if (persistentConnection != null)
                persistentConnection.close();
        } catch (SQLException e) {
            e.printStackTrace();
        }
        persistentConnection = null;
    }
    
    /**
     * Purge tokens that are older than the configured time-to-live.
     */
    private void purge() {
        ArrayList old = new ArrayList();
        Date now = new Date();
        Iterator keySet = resumptionResults.keySet().iterator();
        while (keySet.hasNext()) {
            String key = (String)keySet.next();
            Date then = new Date(Long.parseLong(key) + getMillisecondsToLive());
            if (now.after(then)) {
                old.add(key);
            }
        }
        Iterator iterator = old.iterator();
        while (iterator.hasNext()) {
            String key = (String)iterator.next();
            resumptionResults.remove(key);
        }
    }
    
    /**
     * Use the current date as the basis for the resumptiontoken
     *
     * @return a String version of the current time
     */
    private synchronized static String getResumptionId() {
        Date now = new Date();
        return Long.toString(now.getTime());
    }
}

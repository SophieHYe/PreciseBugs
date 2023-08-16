/*
 * The contents of this file are subject to the Mozilla Public
 * License Version 1.1 (the "License"); you may not use this file
 * except in compliance with the License. You may obtain a copy of
 * the License at http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS
 * IS" basis, WITHOUT WARRANTY OF ANY KIND, either express or
 * implied. See the License for the specific language governing
 * rights and limitations under the License.
 *
 * The Original Code is Content Registry 2.0.
 *
 * The Initial Owner of the Original Code is European Environment
 * Agency.  Portions created by Tieto Eesti are Copyright
 * (C) European Environment Agency.  All Rights Reserved.
 *
 * Contributor(s):
 * Jaanus Heinlaid, Tieto Eesti
 */
package eionet.cr.web.action.factsheet;

import java.util.ArrayList;
import java.util.Collection;
import java.util.Collections;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpSession;

import net.sourceforge.stripes.action.DefaultHandler;
import net.sourceforge.stripes.action.ForwardResolution;
import net.sourceforge.stripes.action.HandlesEvent;
import net.sourceforge.stripes.action.RedirectResolution;
import net.sourceforge.stripes.action.Resolution;
import net.sourceforge.stripes.action.StreamingResolution;
import net.sourceforge.stripes.action.UrlBinding;
import net.sourceforge.stripes.validation.SimpleError;
import net.sourceforge.stripes.validation.ValidationMethod;

import org.apache.commons.lang.StringEscapeUtils;
import org.apache.commons.lang.StringUtils;
import org.apache.commons.lang.math.NumberUtils;

import eionet.cr.common.Predicates;
import eionet.cr.common.Subjects;
import eionet.cr.config.GeneralConfig;
import eionet.cr.dao.CompiledDatasetDAO;
import eionet.cr.dao.DAOException;
import eionet.cr.dao.DAOFactory;
import eionet.cr.dao.HarvestSourceDAO;
import eionet.cr.dao.HelperDAO;
import eionet.cr.dao.SpoBinaryDAO;
import eionet.cr.dao.util.UriLabelPair;
import eionet.cr.dao.virtuoso.PredicateObjectsReader;
import eionet.cr.dataset.CurrentLoadedDatasets;
import eionet.cr.dto.DatasetDTO;
import eionet.cr.dto.FactsheetDTO;
import eionet.cr.dto.HarvestSourceDTO;
import eionet.cr.dto.ObjectDTO;
import eionet.cr.dto.SubjectDTO;
import eionet.cr.dto.TripleDTO;
import eionet.cr.harvest.CurrentHarvests;
import eionet.cr.harvest.HarvestException;
import eionet.cr.harvest.OnDemandHarvester;
import eionet.cr.harvest.scheduled.UrgentHarvestQueue;
import eionet.cr.harvest.util.CsvImportUtil;
import eionet.cr.util.Pair;
import eionet.cr.util.URLUtil;
import eionet.cr.util.Util;
import eionet.cr.web.action.AbstractActionBean;
import eionet.cr.web.action.source.ViewSourceActionBean;
import eionet.cr.web.util.ApplicationCache;
import eionet.cr.web.util.tabs.FactsheetTabMenuHelper;
import eionet.cr.web.util.tabs.TabElement;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

/**
 * Factsheet.
 *
 * @author <a href="mailto:jaanus.heinlaid@tietoenator.com">Jaanus Heinlaid</a>
 *
 */
@UrlBinding("/factsheet.action")
public class FactsheetActionBean extends AbstractActionBean {

    private static final Logger LOGGER = LoggerFactory.getLogger(FactsheetActionBean.class);

    /** Prefix for the name of the "which page of predicate values to display" request parameter. */
    public static final String PAGE_PARAM_PREFIX = "page";

    /** Name for session attributes for addible properties. */
    private static final String ADDIBLE_PROPERTIES_SESSION_ATTR = FactsheetActionBean.class.getName() + ".addibleProperties";

    /** URI by which the factsheet has been requested. */
    private String uri;

    /** URI hash by which the factsheet has been requested. Ignored when factsheet requested by URI. */
    private long uriHash;

    /** The subject data object found by the requestd URI or URI hash. */
    private FactsheetDTO subject;

    /** Used in factsheet edit mode only, where it indicates if the subject is anonymous. */
    private boolean anonymous;

    /** */
    private String propertyUri;
    /** */
    private String propertyValue;

    /** List of identifiers of property-value rows submitted from factsheet edit form. */
    private List<String> rowId;

    /** True if the session bears a user and it happens to be an administrator. Otherwise false. */
    private boolean adminLoggedIn;

    /** True if the found subject is a bookmark of the logged-in user. In all other cases false. */
    private Boolean subjectIsUserBookmark;

    /** True if the found subject has downloadable content in filestore. */
    private Boolean subjectDownloadable;

    /** True, if URI is harvest source. */
    private boolean uriIsHarvestSource;

    /** True, if URI is local folder. */
    private boolean uriIsFolder;

    /** */
    private String bookmarkLabel;

    /** */
    private Map<String, Integer> predicatePageNumbers;
    private Map<String, Integer> predicatePageCounts;

    /** */
    private List<TabElement> tabs;

    /** */
    private Boolean subjectIsType = null;

    /** */
    private String predicateUri;
    private String objectMD5;
    private String graphUri;

    /** */
    private List<DatasetDTO> userCompiledDatasets;

    /** */
    private HarvestSourceDTO harvestSourceDTO;

    /**
     *
     * @return Resolution
     * @throws DAOException
     *             if query fails
     */
    @DefaultHandler
    public Resolution view() throws DAOException {

        if (isNoCriteria()) {
            addCautionMessage("No request criteria specified!");
        } else {
            HelperDAO helperDAO = DAOFactory.get().getDao(HelperDAO.class);

            adminLoggedIn = getUser() != null && getUser().isAdministrator();

            subject = helperDAO.getFactsheet(uri, null, getPredicatePageNumbers());

            FactsheetTabMenuHelper tabsHelper = new FactsheetTabMenuHelper(uri, subject, factory.getDao(HarvestSourceDAO.class));

            tabs = tabsHelper.getTabs(FactsheetTabMenuHelper.TabTitle.RESOURCE_PROPERTIES);
            uriIsHarvestSource = tabsHelper.isUriIsHarvestSource();
            uriIsFolder = tabsHelper.isUriFolder();
            harvestSourceDTO = tabsHelper.getHarvestSourceDTO();
        }

        return new ForwardResolution("/pages/factsheet/factsheet.jsp");
    }

    /**
     * Schedules a harvest for resource.
     *
     * @return view resolution
     * @throws HarvestException
     *             if harvesting fails
     * @throws DAOException
     *             if query fails
     */
    public Resolution harvest() throws HarvestException, DAOException {

        HelperDAO helperDAO = DAOFactory.get().getDao(HelperDAO.class);
        SubjectDTO subjectDTO = helperDAO.getSubject(uri);

        if (subjectDTO != null && CsvImportUtil.isSourceTableFile(subjectDTO)) {

            // Special block for harvesting table files.
            try {
                List<String> warnings = CsvImportUtil.harvestTableFile(subjectDTO, getUserName());
                for (String msg : warnings) {
                    addWarningMessage(msg);
                }
                addSystemMessage("Source successfully harvested!");
            } catch (Exception e) {
                LOGGER.error("Failed to harvest table file", e);
                addWarningMessage("Failed to harvest table file: " + e.getMessage());
            }
        } else {

            // Block for harvesting other, i.e. non-table-file sources.
            Pair<Boolean, String> message = harvestNow();
            if (message.getLeft()) {
                addWarningMessage(message.getRight());
            } else {
                addSystemMessage(message.getRight());
            }
        }

        return new RedirectResolution(this.getClass(), "view").addParameter("uri", uri);
    }

    /**
     * helper method to eliminate code duplication.
     *
     * @return Pair<Boolean, String> feedback messages
     * @throws HarvestException
     *             if harvesting fails
     * @throws DAOException
     *             if query fails
     */
    private Pair<Boolean, String> harvestNow() throws HarvestException, DAOException {

        String message = null;
        if (isUserLoggedIn()) {
            if (!StringUtils.isBlank(uri) && URLUtil.isURL(uri)) {

                // Add this URL into HARVEST_SOURCE table.

                HarvestSourceDAO dao = factory.getDao(HarvestSourceDAO.class);
                HarvestSourceDTO dto = new HarvestSourceDTO();
                dto.setUrl(StringUtils.substringBefore(uri, "#"));
                dto.setEmails("");
                dto.setIntervalMinutes(GeneralConfig.getDefaultHarvestIntervalMinutes());
                dto.setPrioritySource(false);
                dto.setOwner(null);
                dao.addSourceIgnoreDuplicate(dto);

                // Issue an instant harvest of this URL.

                OnDemandHarvester.Resolution resolution = OnDemandHarvester.harvest(dto.getUrl(), getUserName());

                // Give feedback to the user.

                if (resolution.equals(OnDemandHarvester.Resolution.ALREADY_HARVESTING)) {
                    message = "The resource is currently being harvested by another user or background harvester!";
                } else if (resolution.equals(OnDemandHarvester.Resolution.UNCOMPLETE)) {
                    message = "The harvest hasn't finished yet, but continues in the background!";
                } else if (resolution.equals(OnDemandHarvester.Resolution.COMPLETE)) {
                    message = "The harvest has been completed!";
                } else if (resolution.equals(OnDemandHarvester.Resolution.SOURCE_UNAVAILABLE)) {
                    message = "The resource was not available!";
                } else if (resolution.equals(OnDemandHarvester.Resolution.NO_STRUCTURED_DATA)) {
                    message = "The resource contained no RDF data!";
                } else {
                    message = "No feedback given from harvest!";
                }
            }
            return new Pair<Boolean, String>(false, message);
        } else {
            return new Pair<Boolean, String>(true, getBundle().getString("not.logged.in"));
        }
    }

    /**
     *
     * @return Resolution
     * @throws DAOException
     *             if query fails if query fails
     */
    public Resolution edit() throws DAOException {

        return view();
    }

    /**
     *
     * @return Resolution
     * @throws DAOException
     *             if query fails if query fails
     */
    public Resolution addbookmark() throws DAOException {
        if (isUserLoggedIn()) {
            DAOFactory.get().getDao(HelperDAO.class).addUserBookmark(getUser(), getUrl(), bookmarkLabel);
            addSystemMessage("Succesfully bookmarked this source.");
        } else {
            addSystemMessage("Only logged in users can bookmark sources.");
        }
        return view();
    }

    /**
     *
     * @return Resolution
     * @throws DAOException
     *             if query fails
     */
    public Resolution removebookmark() throws DAOException {
        if (isUserLoggedIn()) {
            DAOFactory.get().getDao(HelperDAO.class).deleteUserBookmark(getUser(), getUrl());
            addSystemMessage("Succesfully removed this source from bookmarks.");
        } else {
            addSystemMessage("Only logged in users can remove bookmarks.");
        }
        return view();
    }

    /**
     *
     * @return Resolution
     * @throws DAOException
     *             if query fails if query fails
     */
    public Resolution save() throws DAOException {

        SubjectDTO subjectDTO = new SubjectDTO(uri, anonymous);

        if (propertyUri.equals(Predicates.CR_TAG)) {
            List<String> tags = Util.splitStringBySpacesExpectBetweenQuotes(propertyValue);

            for (String tag : tags) {
                ObjectDTO objectDTO = new ObjectDTO(tag, true);
                objectDTO.setSourceUri(getUser().getRegistrationsUri());
                subjectDTO.addObject(propertyUri, objectDTO);
            }
        } else {
            // other properties
            ObjectDTO objectDTO = new ObjectDTO(propertyValue, true);
            objectDTO.setSourceUri(getUser().getRegistrationsUri());
            subjectDTO.addObject(propertyUri, objectDTO);
        }

        HelperDAO helperDao = factory.getDao(HelperDAO.class);
        helperDao.addTriples(subjectDTO);
        helperDao.updateUserHistory(getUser(), uri);

        // since user registrations URI was used as triple source, add it to HARVEST_SOURCE too
        // (but set interval minutes to 0, to avoid it being background-harvested)
        DAOFactory
                .get()
                .getDao(HarvestSourceDAO.class)
                .addSourceIgnoreDuplicate(
                        HarvestSourceDTO.create(getUser().getRegistrationsUri(), true, 0, getUser().getUserName()));

        return new RedirectResolution(this.getClass(), "edit").addParameter("uri", uri);
    }

    /**
     *
     * @return Resolution
     * @throws DAOException
     *             if query fails
     */
    public Resolution delete() throws DAOException {

        if (rowId != null && !rowId.isEmpty()) {

            ArrayList<TripleDTO> triples = new ArrayList<TripleDTO>();

            for (String row : rowId) {
                int i = row.indexOf("_");
                if (i <= 0 || i == (row.length() - 1)) {
                    throw new IllegalArgumentException("Illegal rowId: " + row);
                }

                String predicateHash = row.substring(0, i);
                String predicate = getContext().getRequestParameter("pred_".concat(predicateHash));

                String objectHash = row.substring(i + 1);
                String objectValue = getContext().getRequest().getParameter("obj_".concat(objectHash));
                String sourceUri = getContext().getRequest().getParameter("source_".concat(objectHash));

                TripleDTO triple = new TripleDTO(uri, predicate, objectValue);
                // FIXME - find a better way to determine if the object is literal or not, URIs may be literals also
                triple.setLiteralObject(!URLUtil.isURL(objectValue));
                triple.setSourceUri(sourceUri);

                triples.add(triple);
            }

            HelperDAO helperDao = factory.getDao(HelperDAO.class);
            helperDao.deleteTriples(triples);
            helperDao.updateUserHistory(getUser(), uri);
        }

        return new RedirectResolution(this.getClass(), "edit").addParameter("uri", uri);
    }

    /**
     * Validates if user is logged on and if event property is not empty.
     */
    @ValidationMethod(on = {"save", "delete", "edit", "harvest"})
    public void validateUserKnown() {

        if (getUser() == null) {
            addWarningMessage("Operation not allowed for anonymous users");
        } else if (getContext().getEventName().equals("save") && StringUtils.isBlank(propertyValue)) {
            addGlobalValidationError(new SimpleError("Property value must not be blank"));
        }
    }

    /**
     * @return the resourceUri
     */
    public String getUri() {
        return uri;
    }

    /**
     * @param resourceUri
     *            the resourceUri to set
     */
    public void setUri(final String resourceUri) {
        this.uri = resourceUri;
    }

    /**
     * @return the resource
     */
    public FactsheetDTO getSubject() {
        return subject;
    }

    /**
     * @return the addibleProperties
     * @throws DAOException
     *             if query fails
     */
    @SuppressWarnings("unchecked")
    public Collection<UriLabelPair> getAddibleProperties() throws DAOException {

        // get the addible properties from session

        HttpSession session = getContext().getRequest().getSession();
        ArrayList<UriLabelPair> result = (ArrayList<UriLabelPair>) session.getAttribute(ADDIBLE_PROPERTIES_SESSION_ATTR);

        // if not in session, create them and add to session
        if (result == null || result.isEmpty()) {

            // get addible properties from database

            HelperDAO helperDAO = factory.getDao(HelperDAO.class);
            HashMap<String, String> props = helperDAO.getAddibleProperties(uri);

            // add some hard-coded properties, HashMap assures there won't be duplicates
            props.put(Predicates.RDFS_LABEL, "Title");
            props.put(Predicates.CR_TAG, "Tag");
            props.put(Predicates.RDFS_COMMENT, "Other comments"); // Don't use
            props.put(Predicates.DC_DESCRIPTION, "Description");
            props.put(Predicates.CR_HAS_SOURCE, "hasSource");
            props.put(Predicates.ROD_PRODUCT_OF, "productOf");

            // create the result object from the found and hard-coded properties, sort it

            result = new ArrayList<UriLabelPair>();
            if (props != null && !props.isEmpty()) {

                for (String propUri : props.keySet()) {
                    result.add(UriLabelPair.create(propUri, props.get(propUri)));
                }
                Collections.sort(result);
            }

            // put into session
            session.setAttribute(ADDIBLE_PROPERTIES_SESSION_ATTR, result);
        }

        return result;
    }

    /**
     * @param anonymous
     *            the anonymous to set
     */
    public void setAnonymous(final boolean anonymous) {
        this.anonymous = anonymous;
    }

    /**
     * @param propertyUri
     *            the propertyUri to set
     */
    public void setPropertyUri(final String propertyUri) {
        this.propertyUri = propertyUri;
    }

    /**
     * @param propertyValue
     *            the propertyValue to set
     */
    public void setPropertyValue(final String propertyValue) {
        this.propertyValue = propertyValue;
    }

    /**
     * @param rowId
     *            the rowId to set
     */
    public void setRowId(final List<String> rowId) {
        this.rowId = rowId;
    }

    /**
     * @return the noCriteria
     */
    public boolean isNoCriteria() {
        return StringUtils.isBlank(uri);
    }

    /**
     * @return the uriHash
     */
    public long getUriHash() {
        return uriHash;
    }

    /**
     * @param uriHash
     *            the uriHash to set
     */
    public void setUriHash(final long uriHash) {
        this.uriHash = uriHash;
    }

    /**
     *
     * @return String
     */
    public String getUrl() {
        return uri != null && URLUtil.isURL(uri) ? uri : null;
    }

    /**
     * True if admin is logged in.
     *
     * @return boolean
     */
    public boolean isAdminLoggedIn() {
        return adminLoggedIn;
    }

    /**
     *
     * @return boolean
     * @throws DAOException
     *             if query fails if query fails
     */
    public boolean getSubjectIsUserBookmark() throws DAOException {

        if (!isUserLoggedIn()) {
            return false;
        }

        if (subjectIsUserBookmark == null) {
            subjectIsUserBookmark = Boolean.valueOf(factory.getDao(HelperDAO.class).isSubjectUserBookmark(getUser(), uri));
        }

        return subjectIsUserBookmark.booleanValue();
    }

    /**
     * @return the subjectDownloadable
     * @throws DAOException
     */
    public boolean isSubjectDownloadable() throws DAOException {

        if (subjectDownloadable == null) {
            subjectDownloadable = Boolean.valueOf(DAOFactory.get().getDao(SpoBinaryDAO.class).exists(uri));
        }
        return subjectDownloadable.booleanValue();
    }

    /**
     *
     * @return boolean
     * @throws DAOException
     */
    public boolean isCurrentlyHarvested() throws DAOException {

        return uri == null ? false : (CurrentHarvests.contains(uri) || UrgentHarvestQueue.isInQueue(uri) || CurrentLoadedDatasets
                .contains(uri));
    }

    /**
     *
     * @return boolean
     */
    public boolean isCompiledDataset() {

        boolean ret = false;

        if (subject.getObject(Predicates.RDF_TYPE) != null) {
            ret = Subjects.CR_COMPILED_DATASET.equals(subject.getObject(Predicates.RDF_TYPE).getValue());
        }

        return ret;
    }

    /**
     *
     * @return Resolution
     * @throws DAOException
     */
    public Resolution showOnMap() throws DAOException {
        HelperDAO helperDAO = DAOFactory.get().getDao(HelperDAO.class);
        subject = helperDAO.getFactsheet(uri, null, null);

        FactsheetTabMenuHelper helper = new FactsheetTabMenuHelper(uri, subject, factory.getDao(HarvestSourceDAO.class));
        tabs = helper.getTabs(FactsheetTabMenuHelper.TabTitle.SHOW_ON_MAP);
        return new ForwardResolution("/pages/factsheet/map.jsp");
    }

    public boolean isUriIsHarvestSource() {
        return uriIsHarvestSource;
    }

    /**
     *
     * @return
     */
    public String getBookmarkLabel() {
        return bookmarkLabel;
    }

    /**
     *
     * @param bookmarkLabel
     */
    public void setBookmarkLabel(String bookmarkLabel) {
        this.bookmarkLabel = bookmarkLabel;
    }

    /**
     * @return the predicatePages
     */
    @SuppressWarnings("unchecked")
    public Map<String, Integer> getPredicatePageNumbers() {

        if (predicatePageNumbers == null) {

            predicatePageNumbers = new HashMap<String, Integer>();
            HttpServletRequest request = getContext().getRequest();
            Map<String, String[]> paramsMap = request.getParameterMap();

            if (paramsMap != null && !paramsMap.isEmpty()) {

                for (Map.Entry<String, String[]> entry : paramsMap.entrySet()) {

                    String paramName = entry.getKey();
                    if (isPredicatePageParam(paramName)) {

                        int pageNumber = NumberUtils.toInt(paramName.substring(PAGE_PARAM_PREFIX.length()));
                        if (pageNumber > 0) {

                            String[] predicateUris = entry.getValue();
                            if (predicateUris != null) {
                                for (String predUri : predicateUris) {
                                    predicatePageNumbers.put(predUri, pageNumber);
                                }
                            }
                        }
                    }
                }
            }
        }

        return predicatePageNumbers;
    }

    /**
     *
     * @param paramName
     * @return
     */
    public boolean isPredicatePageParam(String paramName) {

        if (paramName.startsWith(PAGE_PARAM_PREFIX) && paramName.length() > PAGE_PARAM_PREFIX.length()) {
            return StringUtils.isNumeric(paramName.substring(PAGE_PARAM_PREFIX.length()));
        } else {
            return false;
        }
    }

    /**
     *
     * @return
     */
    public int getPredicatePageSize() {

        return PredicateObjectsReader.PREDICATE_PAGE_SIZE;
    }

    /**
     *
     * @return
     */
    public List<TabElement> getTabs() {
        return tabs;
    }

    /**
     *
     * @return
     */
    public boolean getSubjectIsType() {

        if (subjectIsType == null) {

            List<String> typeUris = ApplicationCache.getTypeUris();
            subjectIsType = Boolean.valueOf(typeUris.contains(this.uri));
        }

        return subjectIsType;
    }

    /**
     *
     * @return
     */
    @HandlesEvent("openPredObjValue")
    public Resolution openPredObjValue() {

        LOGGER.trace("Retrieving object value for MD5 " + objectMD5 + " of predicate " + predicateUri);
        String value = DAOFactory.get().getDao(HelperDAO.class).getLiteralObjectValue(uri, predicateUri, objectMD5, graphUri);
        if (StringUtils.isBlank(value)) {
            value = "Found no value!";
        } else {
            value = StringEscapeUtils.escapeXml(value);
        }
        return new StreamingResolution("text/html", value);
    }

    /**
     * @param predicateUri
     *            the predicateUri to set
     */
    public void setPredicateUri(String predicateUri) {
        this.predicateUri = predicateUri;
    }

    /**
     * @param objectMD5
     *            the objectMD5 to set
     */
    public void setObjectMD5(String objectMD5) {
        this.objectMD5 = objectMD5;
    }

    /**
     * @param graphUri
     *            the graphUri to set
     */
    public void setGraphUri(String graphUri) {
        this.graphUri = graphUri;
    }

    /**
     *
     * @return
     */
    public List<DatasetDTO> getUserCompiledDatasets() {
        if (userCompiledDatasets == null && !StringUtils.isBlank(uri)) {
            try {
                CompiledDatasetDAO dao = DAOFactory.get().getDao(CompiledDatasetDAO.class);
                userCompiledDatasets = dao.getCompiledDatasets(getUser().getHomeUri(), uri);
            } catch (DAOException e) {
                e.printStackTrace();
            }
        }
        return userCompiledDatasets;
    }

    /**
     *
     * @param userCompiledDatasets
     */
    public void setUserCompiledDatasets(List<DatasetDTO> userCompiledDatasets) {
        this.userCompiledDatasets = userCompiledDatasets;
    }

    /**
     *
     * @return
     */
    public Class<ViewSourceActionBean> getViewSourceActionBeanClass() {
        return ViewSourceActionBean.class;
    }

    /**
     *
     * @return
     */
    public boolean isUriIsFolder() {
        return uriIsFolder;
    }

    /**
     *
     * @param uriIsFolder
     */
    public void setUriIsFolder(boolean uriIsFolder) {
        this.uriIsFolder = uriIsFolder;

    }

    /**
     * @return the harvestSourceDTO
     */
    public HarvestSourceDTO getHarvestSourceDTO() {
        return harvestSourceDTO;
    }

}

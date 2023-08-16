/*
 * This file is part of the Goobi viewer - a content presentation and management
 * application for digitized objects.
 *
 * Visit these websites for more information.
 *          - http://www.intranda.com
 *          - http://digiverso.com
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program. If not, see <http://www.gnu.org/licenses/>.
 */
package io.goobi.viewer.managedbeans;

import java.io.IOException;
import java.io.OutputStream;
import java.io.Serializable;
import java.nio.file.Paths;
import java.util.ArrayList;
import java.util.Collection;
import java.util.Collections;
import java.util.HashMap;
import java.util.HashSet;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.Optional;
import java.util.stream.Collectors;

import javax.enterprise.context.SessionScoped;
import javax.faces.context.ExternalContext;
import javax.faces.context.FacesContext;
import javax.inject.Inject;
import javax.inject.Named;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpSession;

import org.apache.commons.lang3.StringUtils;
import org.apache.commons.text.StringEscapeUtils;
import org.apache.http.client.ClientProtocolException;
import org.apache.logging.log4j.LogManager;
import org.apache.logging.log4j.Logger;
import org.apache.solr.common.SolrDocument;
import org.apache.solr.common.SolrDocumentList;

import com.ocpsoft.pretty.PrettyContext;
import com.ocpsoft.pretty.faces.url.URL;

import de.intranda.api.annotation.wa.TypedResource;
import de.intranda.metadata.multilanguage.IMetadataValue;
import de.intranda.metadata.multilanguage.MultiLanguageMetadataValue;
import io.goobi.viewer.controller.DataManager;
import io.goobi.viewer.controller.IndexerTools;
import io.goobi.viewer.controller.NetTools;
import io.goobi.viewer.controller.PrettyUrlTools;
import io.goobi.viewer.controller.StringConstants;
import io.goobi.viewer.controller.StringTools;
import io.goobi.viewer.exceptions.DAOException;
import io.goobi.viewer.exceptions.IDDOCNotFoundException;
import io.goobi.viewer.exceptions.IndexUnreachableException;
import io.goobi.viewer.exceptions.PresentationException;
import io.goobi.viewer.exceptions.RecordDeletedException;
import io.goobi.viewer.exceptions.RecordLimitExceededException;
import io.goobi.viewer.exceptions.RecordNotFoundException;
import io.goobi.viewer.exceptions.ViewerConfigurationException;
import io.goobi.viewer.faces.validators.PIValidator;
import io.goobi.viewer.faces.validators.SolrQueryValidator;
import io.goobi.viewer.managedbeans.utils.BeanUtils;
import io.goobi.viewer.messages.Messages;
import io.goobi.viewer.messages.ViewerResourceBundle;
import io.goobi.viewer.model.annotation.PublicationStatus;
import io.goobi.viewer.model.annotation.comments.CommentGroup;
import io.goobi.viewer.model.cms.pages.CMSPage;
import io.goobi.viewer.model.crowdsourcing.DisplayUserGeneratedContent;
import io.goobi.viewer.model.crowdsourcing.DisplayUserGeneratedContent.ContentType;
import io.goobi.viewer.model.job.download.DownloadJob;
import io.goobi.viewer.model.job.download.DownloadOption;
import io.goobi.viewer.model.job.download.EPUBDownloadJob;
import io.goobi.viewer.model.job.download.PDFDownloadJob;
import io.goobi.viewer.model.maps.GeoMap;
import io.goobi.viewer.model.maps.GeoMap.GeoMapType;
import io.goobi.viewer.model.maps.GeoMapFeature;
import io.goobi.viewer.model.search.BrowseElement;
import io.goobi.viewer.model.search.SearchHelper;
import io.goobi.viewer.model.search.SearchHit;
import io.goobi.viewer.model.security.AccessConditionUtils;
import io.goobi.viewer.model.security.AccessPermission;
import io.goobi.viewer.model.security.IPrivilegeHolder;
import io.goobi.viewer.model.statistics.usage.RequestType;
import io.goobi.viewer.model.toc.TOC;
import io.goobi.viewer.model.toc.TOCElement;
import io.goobi.viewer.model.toc.export.pdf.TocWriter;
import io.goobi.viewer.model.toc.export.pdf.WriteTocException;
import io.goobi.viewer.model.translations.language.Language;
import io.goobi.viewer.model.viewer.PageOrientation;
import io.goobi.viewer.model.viewer.PageType;
import io.goobi.viewer.model.viewer.PhysicalElement;
import io.goobi.viewer.model.viewer.StructElement;
import io.goobi.viewer.model.viewer.ViewManager;
import io.goobi.viewer.model.viewer.pageloader.AbstractPageLoader;
import io.goobi.viewer.modules.IModule;
import io.goobi.viewer.solr.SolrConstants;
import io.goobi.viewer.solr.SolrConstants.DocType;
import io.goobi.viewer.solr.SolrSearchIndex;
import io.goobi.viewer.solr.SolrTools;

/**
 * This bean opens the requested record and provides all data relevant to this record.
 */
@Named
@SessionScoped
public class ActiveDocumentBean implements Serializable {

    private static final long serialVersionUID = -8686943862186336894L;

    private static final Logger logger = LogManager.getLogger(ActiveDocumentBean.class);

    /**
     * Regex pattern 'imageToShow' matches if doublePageMode should be active
     */
    private static final String DOUBLE_PAGE_PATTERN = "\\d+-\\d+";

    private static int imageContainerWidth = 600;

    private final transient Object lock = new Object();

    @Inject
    private NavigationHelper navigationHelper;
    @Inject
    private CmsBean cmsBean;
    @Inject
    private SearchBean searchBean;
    @Inject
    private BookmarkBean bookmarkBean;
    @Inject
    private ImageDeliveryBean imageDelivery;
    @Inject
    private BreadcrumbBean breadcrumbBean;

    /** URL parameter 'action'. */
    private String action = "";
    /** URL parameter 'imageToShow'. */
    private String imageToShow = "1";
    /** URL parameter 'logid'. */
    private String logid = "";
    /** URL parameter 'tocCurrentPage'. */
    private int tocCurrentPage = 1;

    private ViewManager viewManager;
    private boolean anchor = false;
    private boolean volume = false;
    private boolean group = false;
    protected long topDocumentIddoc = 0;

    // TODO move to SearchBean
    private BrowseElement prevHit;
    private BrowseElement nextHit;

    /** This persists the last value given to setPersistentIdentifier() and is used for handling a RecordNotFoundException. */
    String lastReceivedIdentifier;
    /** Available languages for this record. */
    private List<String> recordLanguages;
    /** Currently selected language for multilingual records. */
    private String selectedRecordLanguage;

    private Boolean deleteRecordKeepTrace;

    private String clearCacheMode;

    private Map<String, GeoMap> geoMaps = new HashMap<>();

    private int reloads = 0;

    private boolean downloadImageModalVisible = false;

    private String selectedDownloadOptionLabel;
    /* Previous docstruct URL cache. TODO Implement differently once other views beside full-screen are used. */
    private Map<String, String> prevDocstructUrlCache = new HashMap<>();
    /* Next docstruct URL cache. TODO Implement differently once other views beside full-screen are used. */
    private Map<String, String> nextDocstructUrlCache = new HashMap<>();

    /**
     * Empty constructor.
     */
    public ActiveDocumentBean() {
        // the emptiness inside
    }

    /**
     * Required setter for ManagedProperty injection
     *
     * @param navigationHelper the navigationHelper to set
     */
    public void setNavigationHelper(NavigationHelper navigationHelper) {
        this.navigationHelper = navigationHelper;
    }

    /**
     * Required setter for ManagedProperty injection
     *
     * @param cmsBean the cmsBean to set
     */
    public void setCmsBean(CmsBean cmsBean) {
        this.cmsBean = cmsBean;
    }

    /**
     * Required setter for ManagedProperty injection
     *
     * @param searchBean the searchBean to set
     */
    public void setSearchBean(SearchBean searchBean) {
        this.searchBean = searchBean;
    }

    /**
     * Required setter for ManagedProperty injection
     *
     * @param bookshelfBean the bookshelfBean to set
     */
    public void setBookshelfBean(BookmarkBean bookshelfBean) {
        this.bookmarkBean = bookshelfBean;
    }

    /**
     * Required setter for ManagedProperty injection
     *
     * @param breadcrumbBean the breadcrumbBean to set
     */
    public void setBreadcrumbBean(BreadcrumbBean breadcrumbBean) {
        this.breadcrumbBean = breadcrumbBean;
    }

    /**
     * TODO This can cause NPEs if called while update() is running.
     *
     * @throws IndexUnreachableException
     * @should reset lastReceivedIdentifier
     */
    public void reset() throws IndexUnreachableException {
        synchronized (this) {
            logger.trace("reset (thread {})", Thread.currentThread().getId());
            String pi = viewManager != null ? viewManager.getPi() : null;
            viewManager = null;
            topDocumentIddoc = 0;
            logid = "";
            action = "";
            prevHit = null;
            nextHit = null;
            group = false;
            clearCacheMode = null;
            prevDocstructUrlCache.clear();
            nextDocstructUrlCache.clear();
            lastReceivedIdentifier = null;

            // Any cleanup modules need to do when a record is unloaded
            for (IModule module : DataManager.getInstance().getModules()) {
                module.augmentResetRecord();
            }

            // Remove record lock for this record and session
            if (BeanUtils.getSession() != null) {
                DataManager.getInstance()
                        .getRecordLockManager()
                        .removeLockForPiAndSessionId(pi, BeanUtils.getSession().getId());
            }
        }
    }

    /**
     * Do not call from ActiveDocumentBean.update()!
     *
     * @return a {@link io.goobi.viewer.model.viewer.ViewManager} object.
     */
    public ViewManager getViewManager() {
        if (viewManager == null) {
            try {
                try {
                    update();
                } catch (IDDOCNotFoundException e) {
                    reload(lastReceivedIdentifier);
                }
            } catch (PresentationException e) {
                logger.debug(StringConstants.LOG_PRESENTATION_EXCEPTION_THROWN_HERE, e.getMessage());
            } catch (RecordNotFoundException | RecordDeletedException | RecordLimitExceededException e) {
                if (e.getMessage() != null && !"null".equals(e.getMessage()) && !"???".equals(e.getMessage())) {
                    logger.warn("{}: {}", e.getClass().getName(), e.getMessage());
                }
            } catch (IndexUnreachableException | DAOException | ViewerConfigurationException e) {
                logger.error(e.getMessage(), e);
            }
        }

        return viewManager;
    }

    /**
     *
     * @param pi @throws PresentationException @throws RecordNotFoundException @throws RecordDeletedException @throws
     *            IndexUnreachableException @throws DAOException @throws ViewerConfigurationException @throws RecordLimitExceededException @throws
     */
    public String reload(String pi) throws PresentationException, RecordNotFoundException, RecordDeletedException, IndexUnreachableException,
            DAOException, ViewerConfigurationException, RecordLimitExceededException {
        logger.trace("reload({})", pi);
        reloads++;
        reset();
        if (reloads > 3) {
            throw new RecordNotFoundException(pi);
        }
        setPersistentIdentifier(pi);
        //        setImageToShow(1);
        return open();
    }

    /**
     * Loads the record with the IDDOC set in <code>currentElementIddoc</code>.
     *
     * @throws io.goobi.viewer.exceptions.PresentationException if any.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws io.goobi.viewer.exceptions.RecordNotFoundException if any.
     * @throws io.goobi.viewer.exceptions.RecordDeletedException if any.
     * @throws io.goobi.viewer.exceptions.DAOException if any.
     * @throws io.goobi.viewer.exceptions.ViewerConfigurationException if any.
     * @throws IDDOCNotFoundException
     * @throws RecordLimitExceededException
     * @throws NumberFormatException
     * @should create ViewManager correctly
     * @should update ViewManager correctly if LOGID has changed
     * @should not override topDocumentIddoc if LOGID has changed
     * @should throw RecordNotFoundException if listing not allowed by default
     * @should load records that have been released via moving wall
     */

    public void update() throws PresentationException, IndexUnreachableException, RecordNotFoundException, RecordDeletedException, DAOException,
            ViewerConfigurationException, IDDOCNotFoundException, NumberFormatException, RecordLimitExceededException {
        synchronized (this) {
            if (topDocumentIddoc == 0) {
                try {
                    if (StringUtils.isNotEmpty(lastReceivedIdentifier)) {
                        throw new RecordNotFoundException(lastReceivedIdentifier);
                    }
                    throw new RecordNotFoundException("???");
                } finally {
                    lastReceivedIdentifier = null;
                }
            }
            logger.debug("update(): (IDDOC {} ; page {} ; thread {})", topDocumentIddoc, imageToShow, Thread.currentThread().getId());
            prevHit = null;
            nextHit = null;
            boolean doublePageMode = isDoublePageUrl();
            // Do these steps only if a new document has been loaded
            boolean mayChangeHitIndex = false;
            if (viewManager == null || viewManager.getTopStructElement() == null || viewManager.getTopStructElementIddoc() != topDocumentIddoc) {
                anchor = false;
                volume = false;
                group = false;

                // Change current hit index only if loading a new record
                if (searchBean != null && searchBean.getCurrentSearch() != null) {
                    searchBean.increaseCurrentHitIndex();
                    mayChangeHitIndex = true;
                }

                StructElement topStructElement = new StructElement(topDocumentIddoc);

                // Exit here if record is not found or has been deleted
                if (!topStructElement.isExists()) {
                    logger.info("IDDOC for the current record '{}' ({}) no longer seems to exist, attempting to retrieve an updated IDDOC...",
                            topStructElement.getPi(), topDocumentIddoc);
                    topDocumentIddoc = DataManager.getInstance().getSearchIndex().getIddocFromIdentifier(topStructElement.getPi());
                    if (topDocumentIddoc == 0) {
                        logger.warn("New IDDOC for the current record '{}' could not be found. Perhaps this record has been deleted?",
                                topStructElement.getPi());
                        reset();
                        try {
                            throw new RecordNotFoundException(lastReceivedIdentifier);
                        } finally {
                            lastReceivedIdentifier = null;
                        }
                    }
                } else if (topStructElement.isDeleted()) {
                    logger.debug("Record '{}' is deleted and only available as a trace document.", topStructElement.getPi());
                    reset();
                    throw new RecordDeletedException(topStructElement.getPi());
                }

                // Do not open records who may not be listed for the current user
                List<String> requiredAccessConditions = topStructElement.getMetadataValues(SolrConstants.ACCESSCONDITION);
                if (requiredAccessConditions != null && !requiredAccessConditions.isEmpty()) {
                    AccessPermission access =
                            AccessConditionUtils.checkAccessPermission(new HashSet<>(requiredAccessConditions), IPrivilegeHolder.PRIV_LIST,
                                    new StringBuilder().append('+').append(SolrConstants.PI).append(':').append(topStructElement.getPi()).toString(),
                                    (HttpServletRequest) FacesContext.getCurrentInstance().getExternalContext().getRequest());
                    if (!access.isGranted()) {
                        logger.debug("User may not open {}", topStructElement.getPi());
                        try {
                            throw new RecordNotFoundException(lastReceivedIdentifier);
                        } finally {
                            lastReceivedIdentifier = null;
                        }
                    }
                    // If license type is configured to redirect to a URL, redirect here
                    if (access.isRedirect() && StringUtils.isNotEmpty(access.getRedirectUrl())) {
                        logger.debug("Redirecting to {}", access.getRedirectUrl());
                        try {
                            FacesContext.getCurrentInstance().getExternalContext().redirect(access.getRedirectUrl());
                            return;
                        } catch (IOException e) {
                            logger.error(e.getMessage());
                            return;
                        }
                    }

                }

                viewManager = new ViewManager(topStructElement, AbstractPageLoader.create(topStructElement), topDocumentIddoc,
                        logid, topStructElement.getMetadataValue(SolrConstants.MIMETYPE), imageDelivery);
                viewManager.setToc(createTOC());

                HttpSession session = BeanUtils.getSession();
                // Release all locks for this session except the current record
                if (session != null) {
                    DataManager.getInstance()
                            .getRecordLockManager()
                            .removeLocksForSessionId(session.getId(), Collections.singletonList(viewManager.getPi()));
                }
                String limit = viewManager.getTopStructElement().getMetadataValue(SolrConstants.ACCESSCONDITION_CONCURRENTUSE);
                // Lock limited view records, if limit exists and record has a license type that has this feature enabled
                if (limit != null && AccessConditionUtils.isConcurrentViewsLimitEnabledForAnyAccessCondition(
                        viewManager.getTopStructElement().getMetadataValues(SolrConstants.ACCESSCONDITION))) {
                    if (session != null) {
                        DataManager.getInstance()
                                .getRecordLockManager()
                                .lockRecord(viewManager.getPi(), session.getId(), Integer.valueOf(limit));
                    } else {
                        logger.debug("No session found, unable to lock limited view record {}", topStructElement.getPi());
                        try {
                            throw new RecordLimitExceededException(lastReceivedIdentifier + ":" + limit);
                        } finally {
                            lastReceivedIdentifier = null;
                        }
                    }
                }
            }

            //update usage statistics
            DataManager.getInstance()
                    .getUsageStatisticsRecorder()
                    .recordRequest(RequestType.RECORD_VIEW, viewManager.getPi(), BeanUtils.getRequest());

            // If LOGID is set, update the current element
            if (StringUtils.isNotEmpty(logid) && viewManager != null && !logid.equals(viewManager.getLogId())) {
                // TODO set new values instead of re-creating ViewManager, perhaps
                logger.debug("Find doc by LOGID: {}", logid);
                new StructElement(topDocumentIddoc);
                String query = new StringBuilder("+")
                        .append(SolrConstants.LOGID)
                        .append(":\"")
                        .append(logid)
                        .append("\" +")
                        .append(SolrConstants.PI_TOPSTRUCT)
                        .append(":")
                        .append(viewManager.getPi())
                        .append(" +")
                        .append(SolrConstants.DOCTYPE)
                        .append(':')
                        .append(DocType.DOCSTRCT.name())
                        .toString();
                SolrDocumentList docList = DataManager.getInstance()
                        .getSearchIndex()
                        .search(query, 1, null, Collections.singletonList(SolrConstants.IDDOC));
                long subElementIddoc = 0;
                // TODO check whether creating a new ViewManager can be avoided here
                if (!docList.isEmpty()) {
                    subElementIddoc = Long.valueOf((String) docList.get(0).getFieldValue(SolrConstants.IDDOC));
                    // Re-initialize ViewManager with the new current element
                    PageOrientation firstPageOrientation = viewManager.getFirstPageOrientation();
                    viewManager = new ViewManager(viewManager.getTopStructElement(), viewManager.getPageLoader(), subElementIddoc, logid,
                            viewManager.getMimeType(), imageDelivery);
                    viewManager.setFirstPageOrientation(firstPageOrientation);
                    viewManager.setToc(createTOC());
                } else {
                    logger.warn("{} not found for LOGID '{}'.", SolrConstants.IDDOC, logid);
                }
            }

            if (viewManager != null && viewManager.getCurrentStructElement() != null) {
                viewManager.setDoublePageMode(doublePageMode);
                StructElement structElement = viewManager.getCurrentStructElement();
                if (!structElement.isExists()) {
                    logger.trace("StructElement {} is not marked as existing. Record will be reloaded", structElement.getLuceneId());
                    try {
                        throw new IDDOCNotFoundException(lastReceivedIdentifier + " - " + structElement.getLuceneId());
                    } finally {
                        lastReceivedIdentifier = null;
                    }
                }
                if (structElement.isAnchor()) {
                    anchor = true;
                }
                if (structElement.isVolume()) {
                    volume = true;
                }
                if (structElement.isGroup()) {
                    group = true;
                }

                viewManager.setCurrentImageOrderString(imageToShow);
                viewManager.updateDropdownSelected();

                // Search hit navigation
                if (searchBean != null && searchBean.getCurrentSearch() != null) {
                    if (searchBean.getCurrentHitIndex() < 0) {
                        // Determine the index of this element in the search result list. Must be done after re-initializing ViewManager so that the PI is correct!
                        searchBean.findCurrentHitIndex(getPersistentIdentifier(), viewManager.getCurrentImageOrder(), true);
                    } else if (mayChangeHitIndex) {
                        // Modify the current hit index
                        searchBean.increaseCurrentHitIndex();
                    } else if (searchBean.getHitIndexOperand() != 0) {
                        // Reset hit index operand (should only be necessary if the URL was called twice, but the current hit has not changed
                        // logger.trace("Hit index modifier operand is {}, resetting...", searchBean.getHitIndexOperand());
                        searchBean.setHitIndexOperand(0);
                    }
                }
            } else {
                logger.debug("ViewManager is null or ViewManager.currentDocument is null.");
                try {
                    throw new RecordNotFoundException(lastReceivedIdentifier);
                } finally {
                    lastReceivedIdentifier = null;
                }
            }

            // Metadata language versions
            recordLanguages = viewManager.getTopStructElement().getMetadataValues(SolrConstants.LANGUAGE);
            // If the record has metadata language versions, pre-select the current locale as the record language
            //            if (StringUtils.isBlank(selectedRecordLanguage) && !recordLanguages.isEmpty()) {
            if (StringUtils.isBlank(selectedRecordLanguage) && navigationHelper != null) {
                selectedRecordLanguage = navigationHelper.getLocaleString();
            }

            // Prepare a new bookshelf item
            if (bookmarkBean != null) {
                bookmarkBean.prepareItemForBookmarkList();
                if (bookmarkBean.getCurrentBookmark() == null || !viewManager.getPi().equals(bookmarkBean.getCurrentBookmark().getPi())) {
                    bookmarkBean.prepareItemForBookmarkList();
                }
            }
        }

    }

    /**
     *
     * @return true if the 'imageToShow' part of the url matches {@link #DOUBLE_PAGE_PATTERN}, i.e. if the url suggests that double page mode is
     *         expected
     */
    private boolean isDoublePageUrl() {
        return StringUtils.isNotBlank(imageToShow) && imageToShow.matches(DOUBLE_PAGE_PATTERN);
    }

    /**
     * @throws PresentationException
     * @throws IndexUnreachableException
     * @throws DAOException
     * @throws ViewerConfigurationException
     */
    private TOC createTOC() throws PresentationException, IndexUnreachableException, DAOException, ViewerConfigurationException {
        TOC toc = new TOC();
        synchronized (toc) {
            if (viewManager != null) {
                toc.generate(viewManager.getTopStructElement(), viewManager.isListAllVolumesInTOC(), viewManager.getMimeType(), tocCurrentPage);
                // The TOC object will correct values that are too high, so update the local value, if necessary
                if (toc.getCurrentPage() != this.tocCurrentPage) {
                    this.tocCurrentPage = toc.getCurrentPage();
                }
            }
        }
        return toc;
    }

    /**
     * Pretty-URL entry point.
     *
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.RecordNotFoundException if any.
     * @throws io.goobi.viewer.exceptions.RecordDeletedException if any.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws io.goobi.viewer.exceptions.DAOException if any.
     * @throws io.goobi.viewer.exceptions.ViewerConfigurationException if any.
     * @throws RecordLimitExceededException
     * @throws PresentationException
     */
    public String open()
            throws RecordNotFoundException, RecordDeletedException, IndexUnreachableException, DAOException, ViewerConfigurationException,
            RecordLimitExceededException {
        synchronized (this) {
            logger.trace("open()");
            try {
                update();
                if (navigationHelper == null || viewManager == null) {
                    return "";
                }

                IMetadataValue name = viewManager.getTopStructElement().getMultiLanguageDisplayLabel();
                HttpServletRequest request = (HttpServletRequest) FacesContext.getCurrentInstance().getExternalContext().getRequest();
                URL url = PrettyContext.getCurrentInstance(request).getRequestURL();

                for (String language : name.getLanguages()) {
                    String translation = name.getValue(language).orElse(getPersistentIdentifier());
                    if (translation != null && translation.length() > DataManager.getInstance().getConfiguration().getBreadcrumbsClipping()) {
                        translation =
                                new StringBuilder(translation.substring(0, DataManager.getInstance().getConfiguration().getBreadcrumbsClipping()))
                                        .append("...")
                                        .toString();
                        name.setValue(translation, language);
                    }
                }
                // Fallback using the identifier as the label
                if (name.isEmpty()) {
                    name.setValue(getPersistentIdentifier());
                }
                logger.trace("topdocument label: {} ", name.getValue());
                if (!PrettyContext.getCurrentInstance(request).getRequestURL().toURL().contains("/crowd")) {
                    breadcrumbBean.addRecordBreadcrumbs(viewManager, name, url);
                }
            } catch (PresentationException e) {
                logger.debug(StringConstants.LOG_PRESENTATION_EXCEPTION_THROWN_HERE, e.getMessage(), e);
                Messages.error(e.getMessage());
            } catch (IDDOCNotFoundException e) {
                try {
                    return reload(lastReceivedIdentifier);
                } catch (PresentationException e1) {
                    logger.debug(StringConstants.LOG_PRESENTATION_EXCEPTION_THROWN_HERE, e.getMessage(), e);
                }
            }

            reloads = 0;
            return "";
        }
    }

    /**
     * <p>
     * openFulltext.
     * </p>
     *
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.RecordNotFoundException if any.
     * @throws io.goobi.viewer.exceptions.RecordDeletedException if any.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws io.goobi.viewer.exceptions.DAOException if any.
     * @throws io.goobi.viewer.exceptions.ViewerConfigurationException if any.
     * @throws PresentationException
     * @throws RecordLimitExceededException
     * @throws NumberFormatException
     */
    public String openFulltext()
            throws RecordNotFoundException, RecordDeletedException, IndexUnreachableException, DAOException, ViewerConfigurationException,
            PresentationException, NumberFormatException, RecordLimitExceededException {
        open();
        return "viewFulltext";
    }

    /**
     * <p>
     * Getter for the field <code>prevHit</code>.
     * </p>
     *
     * @return a {@link io.goobi.viewer.model.search.BrowseElement} object.
     * @throws io.goobi.viewer.exceptions.PresentationException if any.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws io.goobi.viewer.exceptions.DAOException if any.
     * @throws io.goobi.viewer.exceptions.ViewerConfigurationException if any.
     */
    public BrowseElement getPrevHit() throws PresentationException, IndexUnreachableException, DAOException, ViewerConfigurationException {
        if (prevHit == null && searchBean != null) {
            prevHit = searchBean.getPreviousElement();
        }

        return prevHit;
    }

    /**
     * <p>
     * Getter for the field <code>nextHit</code>.
     * </p>
     *
     * @return a {@link io.goobi.viewer.model.search.BrowseElement} object.
     * @throws io.goobi.viewer.exceptions.PresentationException if any.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws io.goobi.viewer.exceptions.DAOException if any.
     * @throws io.goobi.viewer.exceptions.ViewerConfigurationException if any.
     */
    public BrowseElement getNextHit() throws PresentationException, IndexUnreachableException, DAOException, ViewerConfigurationException {
        if (nextHit == null && searchBean != null) {
            nextHit = searchBean.getNextElement();
        }

        return nextHit;
    }

    /**
     ********************************* Getter and Setter **************************************
     *
     * @return a long.
     */
    public long getActiveDocumentIddoc() {
        if (viewManager != null) {
            return viewManager.getTopStructElementIddoc();
        }

        return 0;
    }

    /**
     * <p>
     * getCurrentElement.
     * </p>
     *
     * @return the currentElement
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     */
    public StructElement getCurrentElement() throws IndexUnreachableException {
        if (viewManager != null) {
            return viewManager.getCurrentStructElement();
        }

        return null;
    }

    /**
     * <p>
     * Setter for the field <code>imageToShow</code>.
     * </p>
     *
     * @param imageToShow the imageToShow to set
     */
    public void setImageToShow(String imageToShow) {
        synchronized (lock) {
            this.imageToShow = imageToShow;
            if (viewManager != null) {
                viewManager.setDropdownSelected(String.valueOf(imageToShow));
            }
            // Reset LOGID (the LOGID setter is called later by PrettyFaces, so if a value is passed, it will still be set)
            try {
                setLogid("");
            } catch (PresentationException e) {
                //cannot be thrown here
            }
            logger.trace("imageToShow: {}", this.imageToShow);
        }
    }

    /**
     * <p>
     * Getter for the field <code>imageToShow</code>.
     * </p>
     *
     * @return the imageToShow
     */
    public String getImageToShow() {
        synchronized (lock) {
            return imageToShow;
        }
    }

    /**
     * <p>
     * Setter for the field <code>logid</code>.
     * </p>
     *
     * @param logid the logid to set
     * @throws PresentationException 
     */
    public void setLogid(String logid) throws PresentationException {
        synchronized (this) {
            if ("-".equals(logid)) {
                this.logid = "";
            } else if(StringUtils.isNotBlank(logid) && logid.matches("[\\w-]+")) {
                this.logid = SolrTools.escapeSpecialCharacters(logid);
            } else {
                throw new PresentationException("The passed logId " + SolrTools.escapeSpecialCharacters(logid) + " contains illegal characters");
            }
        }
    }

    /**
     * <p>
     * Getter for the field <code>logid</code>.
     * </p>
     *
     * @return the logid
     */
    public String getLogid() {
        synchronized (this) {
            if (StringUtils.isEmpty(logid)) {
                return "-";
            }

            return logid;
        }
    }

    /**
     * <p>
     * isAnchor.
     * </p>
     *
     * @return the anchor
     */
    public boolean isAnchor() {
        return anchor;
    }

    /**
     * <p>
     * Setter for the field <code>anchor</code>.
     * </p>
     *
     * @param anchor the anchor to set
     */
    public void setAnchor(boolean anchor) {
        this.anchor = anchor;
    }

    /**
     * <p>
     * isVolume.
     * </p>
     *
     * @return a boolean.
     */
    public boolean isVolume() {
        return volume;
    }

    /**
     * <p>
     * isGroup.
     * </p>
     *
     * @return a boolean.
     */
    public boolean isGroup() {
        return group;
    }

    /**
     * <p>
     * Getter for the field <code>action</code>.
     * </p>
     *
     * @return the action
     */
    public String getAction() {
        synchronized (this) {
            return action;
        }
    }

    /**
     * <p>
     * Setter for the field <code>action</code>.
     * </p>
     *
     * @param action the action to set
     */
    public void setAction(String action) {
        synchronized (this) {
            logger.trace("setAction: " + action);
            this.action = action;
            if (searchBean != null && action != null) {
                switch (action) {
                    case "nextHit":
                        searchBean.setHitIndexOperand(1);
                        break;
                    case "prevHit":
                        searchBean.setHitIndexOperand(-1);
                        break;
                    default:
                        // do nothing
                        break;

                }
            }
        }
    }

    /**
     * <p>
     * setPersistentIdentifier.
     * </p>
     *
     * @param persistentIdentifier a {@link java.lang.String} object.
     * @should determine currentElementIddoc correctly
     * @throws io.goobi.viewer.exceptions.PresentationException if any.
     * @throws io.goobi.viewer.exceptions.RecordNotFoundException if any.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     */
    public void setPersistentIdentifier(String persistentIdentifier)
            throws PresentationException, RecordNotFoundException, IndexUnreachableException {
        synchronized (this) {
            logger.trace("setPersistentIdentifier: {}", persistentIdentifier);
            lastReceivedIdentifier = persistentIdentifier;
            if (!PIValidator.validatePi(persistentIdentifier)) {
                logger.warn("Invalid identifier '{}'.", persistentIdentifier);
                reset();
                return;
                // throw new RecordNotFoundException("Illegal identifier: " + persistentIdentifier);
            }
            if (!"-".equals(persistentIdentifier) && (viewManager == null || !persistentIdentifier.equals(viewManager.getPi()))) {
                long id = DataManager.getInstance().getSearchIndex().getIddocFromIdentifier(persistentIdentifier);
                if (id > 0) {
                    if (topDocumentIddoc != id) {
                        topDocumentIddoc = id;
                        logger.trace("IDDOC found for {}: {}", persistentIdentifier, id);
                    }
                } else {
                    logger.warn("No IDDOC for identifier '{}' found.", persistentIdentifier);
                    reset();
                    return;
                    // throw new RecordNotFoundException(new StringBuilder(persistentIdentifier).toString());
                }
            }
        }
    }

    /**
     * Returns the PI of the currently loaded record. Only call this method after the update() method has re-initialized ViewManager, otherwise the
     * previous PI may be returned!
     *
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     */
    public String getPersistentIdentifier() throws IndexUnreachableException {
        synchronized (this) {
            if (viewManager != null) {
                return viewManager.getPi();
            }
            return "-";
        }
    }

    /**
     * <p>
     * getThumbPart.
     * </p>
     *
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     */
    public String getThumbPart() throws IndexUnreachableException {
        if (viewManager != null) {
            return new StringBuilder("/").append(getPersistentIdentifier())
                    .append('/')
                    .append(viewManager.getCurrentThumbnailPage())
                    .append('/')
                    .toString();
        }

        return "";
    }

    /**
     * <p>
     * getLogPart.
     * </p>
     *
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     */
    public String getLogPart() throws IndexUnreachableException {
        return new StringBuilder("/").append(getPersistentIdentifier())
                .append('/')
                .append(imageToShow)
                .append('/')
                .append(getLogid())
                .append('/')
                .toString();
    }

    // navigation in work

    /**
     * Returns the navigation URL for the given page type and number.
     *
     * @param pageType a {@link java.lang.String} object.
     * @param pageOrderRange Single page number or range
     * @should construct url correctly
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     */
    public String getPageUrl(String pageType, String pageOrderRange) throws IndexUnreachableException {
        StringBuilder sbUrl = new StringBuilder();
        if (StringUtils.isBlank(pageType)) {
            if (navigationHelper != null) {
                pageType = navigationHelper.getCurrentView();
                if (pageType == null) {
                    pageType = PageType.viewObject.name();
                }
            }
            if (StringUtils.isBlank(pageType)) {
                pageType = PageType.viewObject.name();
            }
            // logger.trace("current view: {}", pageType);
        }

        int[] pages = StringTools.getIntegerRange(pageOrderRange);
        int page = pages[0];
        int page2 = pages[1];

        if (viewManager != null) {
            page = Math.max(page, viewManager.getPageLoader().getFirstPageOrder());
            page = Math.min(page, viewManager.getPageLoader().getLastPageOrder());
            if (page2 != Integer.MAX_VALUE) {
                page2 = Math.max(page2, viewManager.getPageLoader().getFirstPageOrder());
                page2 = Math.min(page2, viewManager.getPageLoader().getLastPageOrder());
            }
        }
        //        if (page == page2) {
        //            page2 = Integer.MAX_VALUE;
        //        }
        String range = page + (page2 != Integer.MAX_VALUE ? "-" + page2 : "");
        // logger.trace("final range: {}", range);
        sbUrl.append(BeanUtils.getServletPathWithHostAsUrlFromJsfContext())
                .append('/')
                .append(PageType.getByName(pageType).getName())
                .append('/')
                .append(getPersistentIdentifier())
                .append('/')
                .append(range)
                .append('/');

        return sbUrl.toString();
    }

    /**
     * <p>
     * getPageUrl.
     * </p>
     *
     * @param pageOrderRange Single page number or range
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     */
    public String getPageUrl(String pageOrderRange) throws IndexUnreachableException {
        return getPageUrl(null, pageOrderRange);
    }

    /**
     * <p>
     * getPageUrl.
     * </p>
     *
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     */
    public String getPageUrl() throws IndexUnreachableException {
        String pageType = null;
        if (StringUtils.isBlank(pageType)) {
            pageType = navigationHelper.getPreferredView();
        }
        if (StringUtils.isBlank(pageType)) {
            pageType = navigationHelper.getCurrentView();
        }
        return getPageUrlByType(pageType);
    }

    /**
     * <p>
     * getPageUrl.
     * </p>
     *
     * @param pageType a {@link java.lang.String} object.
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     */
    public String getPageUrlByType(String pageType) throws IndexUnreachableException {
        StringBuilder sbUrl = new StringBuilder();
        sbUrl.append(BeanUtils.getServletPathWithHostAsUrlFromJsfContext())
                .append('/')
                .append(PageType.getByName(pageType).getName())
                .append('/')
                .append(getPersistentIdentifier())
                .append('/');

        return sbUrl.toString();
    }

    /**
     * <p>
     * getFirstPageUrl.
     * </p>
     *
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     */
    public String getFirstPageUrl() throws IndexUnreachableException {
        if (viewManager != null) {
            int image = viewManager.getPageLoader().getFirstPageOrder();
            if (viewManager.isDoublePageMode()) {
                return getPageUrl(image + "-" + image);
            }

            return getPageUrl(Integer.toString(image));
        }

        return null;
    }

    /**
     * <p>
     * getLastPageUrl.
     * </p>
     *
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     */
    public String getLastPageUrl() throws IndexUnreachableException {
        if (viewManager != null) {
            int image = viewManager.getPageLoader().getLastPageOrder();
            if (viewManager.isDoublePageMode()) {
                return getPageUrl(image + "-" + image);
            }

            return getPageUrl(Integer.toString(image));
        }

        return null;
    }

    /**
     * <p>
     * getNextPageUrl.
     * </p>
     *
     * @param step a int.
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws DAOException
     * @should return correct page in single page mode
     * @should return correct range in double page mode if current page double image
     * @should return correct range in double page mode if currently showing two pages
     * @should return correct range in double page mode if currently showing one page
     */
    public String getPageUrlRelativeToCurrentPage(int step) throws IndexUnreachableException, DAOException {
        // logger.trace("getPageUrl: {}", step);
        if (viewManager == null) {
            return getPageUrl(imageToShow);
        }

        if (!viewManager.isDoublePageMode()) {
            int number = viewManager.getCurrentImageOrder() + step;
            return getPageUrl(String.valueOf(number));
        }

        int number;

        // Current image contains two pages
        if (viewManager.getCurrentPage().isDoubleImage()) {
            // logger.trace("{} is double page", viewManager.getCurrentPage().getOrder());
            if (step < 0) {
                number = viewManager.getCurrentImageOrder() + 2 * step;
            } else {
                number = viewManager.getCurrentImageOrder() + step;
            }
            return getPageUrl(number + "-" + (number + 1));
        }

        // Use current left/right page as a point of reference, if available (opposite when in right-to-left navigation)
        Optional<PhysicalElement> currentLeftPage =
                viewManager.getTopStructElement().isRtl() ? viewManager.getCurrentRightPage() : viewManager.getCurrentLeftPage();
        Optional<PhysicalElement> currentRightPage =
                viewManager.getTopStructElement().isRtl() ? viewManager.getCurrentLeftPage() : viewManager.getCurrentRightPage();

        // Only go back one step unit at first
        if (currentLeftPage.isPresent()) {
            // logger.trace("{} is left page", currentLeftPage.get().getOrder());
            number = currentLeftPage.get().getOrder() + step;
        } else if (currentRightPage.isPresent()) {
            // If only the right page is present, it's probably the first page - do not add step at this point
            // logger.trace("{} is right page", currentRightPage.get().getOrder());
            number = currentRightPage.get().getOrder();
        } else {
            number = viewManager.getCurrentImageOrder() + step;
        }

        // Target image candidate contains two pages
        Optional<PhysicalElement> nextPage = viewManager.getPage(number);
        if (nextPage.isPresent() && nextPage.get().isDoubleImage()) {
            return getPageUrl(String.valueOf(number) + "-" + String.valueOf(number));
        }
        // If the immediate neighbor is not a double image, add another step
        number += step;

        nextPage = viewManager.getPage(number);
        if (nextPage.isPresent() && nextPage.get().isDoubleImage()) {
            return getPageUrl(String.valueOf(number) + "-" + String.valueOf(number));
        }

        // logger.trace("step: {}", step);
        // logger.trace("Number: {}", number);

        return getPageUrl(number + "-" + (number + 1));
    }

    public String getPageUrl(int order) throws IndexUnreachableException {
        return getPageUrl(Integer.toString(order));
    }

    /**
     * <p>
     * getPreviousPageUrl.
     * </p>
     *
     * @param step
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws DAOException
     */
    public String getPreviousPageUrl(int step) throws IndexUnreachableException, DAOException {
        return getPageUrlRelativeToCurrentPage(step * -1);
    }

    /**
     * <p>
     * getNextPageUrl.
     * </p>
     *
     * @param step
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws DAOException
     */
    public String getNextPageUrl(int step) throws IndexUnreachableException, DAOException {
        return getPageUrlRelativeToCurrentPage(step);
    }

    /**
     * <p>
     * getPreviousPageUrl.
     * </p>
     *
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws DAOException
     */
    public String getPreviousPageUrl() throws IndexUnreachableException, DAOException {
        return getPreviousPageUrl(1);
    }

    /**
     * <p>
     * getNextPageUrl.
     * </p>
     *
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws DAOException
     */
    public String getNextPageUrl() throws IndexUnreachableException, DAOException {
        return getNextPageUrl(1);
    }

    /**
     *
     * @return
     * @throws IndexUnreachableException
     * @throws ViewerConfigurationException
     * @throws DAOException
     * @throws PresentationException
     */
    public String getPreviousDocstructUrl() throws IndexUnreachableException, PresentationException, DAOException, ViewerConfigurationException {
        // logger.trace("getPreviousDocstructUrl");
        if (viewManager == null) {
            return null;
        }
        List<String> docstructTypes =
                DataManager.getInstance().getConfiguration().getDocstructNavigationTypes(viewManager.getTopStructElement().getDocStructType(), true);
        if (docstructTypes.isEmpty()) {
            return null;
        }

        String currentDocstructIddoc = String.valueOf(viewManager.getCurrentStructElementIddoc());
        // Determine docstruct URL and cache it
        if (prevDocstructUrlCache.get(currentDocstructIddoc) == null) {
            int currentElementIndex = getToc().findTocElementIndexByIddoc(currentDocstructIddoc);
            if (currentElementIndex == -1) {
                logger.warn("Current IDDOC not found in TOC: {}", viewManager.getCurrentStructElement().getLuceneId());
                return null;
            }

            boolean found = false;
            for (int i = currentElementIndex - 1; i >= 0; --i) {
                TOCElement tocElement = viewManager.getToc().getTocElements().get(i);
                String docstructType = tocElement.getMetadataValue(SolrConstants.DOCSTRCT);
                if (docstructType != null && docstructTypes.contains(docstructType) && StringUtils.isNotBlank(tocElement.getPageNo())) {
                    logger.trace("Found previous {}: {}", docstructType, tocElement.getLogId());
                    // Add LOGID to the URL because ViewManager.currentStructElementIddoc (IDDOC_OWNER) can be incorrect in the index sometimes,
                    // resulting in the URL pointing at the current element
                    prevDocstructUrlCache.put(currentDocstructIddoc,
                            "/" + viewManager.getPi() + "/" + Integer.valueOf(tocElement.getPageNo()) + "/" + tocElement.getLogId() + "/");
                    found = true;
                    break;
                }
            }
            if (!found) {
                prevDocstructUrlCache.put(currentDocstructIddoc, "");
            }
        }

        if (StringUtils.isNotEmpty(prevDocstructUrlCache.get(currentDocstructIddoc))) {
            return BeanUtils.getServletPathWithHostAsUrlFromJsfContext() + "/" + navigationHelper.getCurrentPageType().getName()
                    + prevDocstructUrlCache.get(currentDocstructIddoc);
        }

        return "";
    }

    /**
     *
     * @return
     * @throws IndexUnreachableException
     * @throws ViewerConfigurationException
     * @throws DAOException
     * @throws PresentationException
     */
    public String getNextDocstructUrl() throws IndexUnreachableException, PresentationException, DAOException, ViewerConfigurationException {
        // logger.trace("getNextDocstructUrl");
        if (viewManager == null) {
            return "";
        }
        List<String> docstructTypes =
                DataManager.getInstance().getConfiguration().getDocstructNavigationTypes(viewManager.getTopStructElement().getDocStructType(), true);
        if (docstructTypes.isEmpty()) {
            return null;
        }

        String currentDocstructIddoc = String.valueOf(viewManager.getCurrentStructElementIddoc());
        // Determine docstruct URL and cache it
        if (nextDocstructUrlCache.get(currentDocstructIddoc) == null) {
            int currentElementIndex = getToc().findTocElementIndexByIddoc(currentDocstructIddoc);
            logger.trace("currentIndexElement: {}", currentElementIndex);
            if (currentElementIndex == -1) {
                return null;
            }

            boolean found = false;
            for (int i = currentElementIndex + 1; i < viewManager.getToc().getTocElements().size(); ++i) {
                TOCElement tocElement = viewManager.getToc().getTocElements().get(i);
                String docstructType = tocElement.getMetadataValue(SolrConstants.DOCSTRCT);
                if (docstructType != null && docstructTypes.contains(docstructType)) {
                    logger.trace("Found next {}: {}", docstructType, tocElement.getLogId());
                    // Add LOGID to the URL because ViewManager.currentStructElementIddoc (IDDOC_OWNER) can be incorrect in the index sometimes,
                    // resulting in the URL pointing at the current element
                    nextDocstructUrlCache.put(currentDocstructIddoc,
                            "/" + viewManager.getPi() + "/" + Integer.valueOf(tocElement.getPageNo()) + "/" + tocElement.getLogId() + "/");
                    found = true;
                    break;
                }
            }
            if (!found) {
                nextDocstructUrlCache.put(currentDocstructIddoc, "");
            }
        }

        if (StringUtils.isNotEmpty(nextDocstructUrlCache.get(currentDocstructIddoc))) {
            return BeanUtils.getServletPathWithHostAsUrlFromJsfContext() + "/" + navigationHelper.getCurrentPageType().getName()
                    + nextDocstructUrlCache.get(currentDocstructIddoc);
        }

        return "";
    }

    /**
     * <p>
     * getImageUrl.
     * </p>
     *
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     */
    public String getImageUrl() throws IndexUnreachableException {
        return getPageUrl(PageType.viewImage.getName(), imageToShow);
    }

    /**
     * <p>
     * getFullscreenImageUrl.
     * </p>
     *
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws DAOException
     */
    public String getFullscreenImageUrl() throws IndexUnreachableException, DAOException {
        if (viewManager != null && viewManager.isDoublePageMode() && !viewManager.getCurrentPage().isDoubleImage()) {
            Optional<PhysicalElement> currentLeftPage = viewManager.getCurrentLeftPage();
            Optional<PhysicalElement> currentRightPage = viewManager.getCurrentRightPage();
            if (currentLeftPage.isPresent() && currentRightPage.isPresent()) {
                return getPageUrl(PageType.viewFullscreen.getName(), currentLeftPage.get().getOrder() + "-" + currentRightPage.get().getOrder());
            }
        }

        return getPageUrl(PageType.viewFullscreen.getName(), imageToShow);
    }

    /**
     * <p>
     * getReadingModeUrl.
     * </p>
     *
     * @deprecated renamed to fullscreen
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws DAOException
     */
    public String getReadingModeUrl() throws IndexUnreachableException, DAOException {
        return getFullscreenImageUrl();
    }

    /**
     * <p>
     * getFulltextUrl.
     * </p>
     *
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     */
    public String getFulltextUrl() throws IndexUnreachableException {
        return getPageUrl(PageType.viewFulltext.getName(), imageToShow);
    }

    /**
     * <p>
     * getMetadataUrl.
     * </p>
     *
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     */
    public String getMetadataUrl() throws IndexUnreachableException {
        return getPageUrl(PageType.viewMetadata.getName(), imageToShow);
    }

    /**
     * <p>
     * getTopDocument.
     * </p>
     *
     * @return a {@link io.goobi.viewer.model.viewer.StructElement} object.
     */
    public StructElement getTopDocument() {
        if (viewManager != null) {
            return viewManager.getTopStructElement();
        }

        return null;
    }

    /**
     * <p>
     * setChildrenVisible.
     * </p>
     *
     * @param element a {@link io.goobi.viewer.model.toc.TOCElement} object.
     * @throws io.goobi.viewer.exceptions.PresentationException if any.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws io.goobi.viewer.exceptions.DAOException if any.
     * @throws io.goobi.viewer.exceptions.ViewerConfigurationException if any.
     */
    public void setChildrenVisible(TOCElement element)
            throws PresentationException, IndexUnreachableException, DAOException, ViewerConfigurationException {
        if (getToc() != null) {
            synchronized (getToc()) {
                getToc().setChildVisible(element.getID());
                getToc().getActiveElement();
            }
        }
    }

    /**
     * <p>
     * setChildrenInvisible.
     * </p>
     *
     * @param element a {@link io.goobi.viewer.model.toc.TOCElement} object.
     * @throws io.goobi.viewer.exceptions.PresentationException if any.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws io.goobi.viewer.exceptions.DAOException if any.
     * @throws io.goobi.viewer.exceptions.ViewerConfigurationException if any.
     */
    public void setChildrenInvisible(TOCElement element)
            throws PresentationException, IndexUnreachableException, DAOException, ViewerConfigurationException {
        if (getToc() != null) {
            synchronized (getToc()) {
                getToc().setChildInvisible(element.getID());
                getToc().getActiveElement();
            }
        }
    }

    /**
     * Recalculates the visibility of TOC elements and jumps to the active element after a +/- button has been pressed.
     *
     * @return a {@link java.lang.String} object.
     * @throws java.io.IOException if any.
     * @throws io.goobi.viewer.exceptions.PresentationException if any.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws io.goobi.viewer.exceptions.DAOException if any.
     * @throws io.goobi.viewer.exceptions.ViewerConfigurationException if any.
     */
    public String calculateSidebarToc()
            throws IOException, PresentationException, IndexUnreachableException, DAOException, ViewerConfigurationException {
        if (getToc() != null) {
            TOCElement activeTocElement = getToc().getActiveElement();
            if (activeTocElement != null) {
                String result = new StringBuilder("#").append(activeTocElement.getLogId()).toString();
                FacesContext.getCurrentInstance().getExternalContext().redirect(result);
                return result;
            }
        }

        return null;
    }

    /**
     * <p>
     * Getter for the field <code>toc</code>.
     * </p>
     *
     * @return the toc
     * @throws io.goobi.viewer.exceptions.PresentationException if any.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws io.goobi.viewer.exceptions.DAOException if any.
     * @throws io.goobi.viewer.exceptions.ViewerConfigurationException if any.
     */
    public TOC getToc() throws PresentationException, IndexUnreachableException, DAOException, ViewerConfigurationException {
        if (viewManager == null) {
            return null;
        }

        if (viewManager.getToc() == null) {
            viewManager.setToc(createTOC());
        }
        return viewManager.getToc();
    }

    /**
     * <p>
     * Getter for the field <code>tocCurrentPage</code>.
     * </p>
     *
     * @return a int.
     */
    public String getTocCurrentPage() {
        synchronized (this) {
            return Integer.toString(tocCurrentPage);
        }
    }

    /**
     * <p>
     * Setter for the field <code>tocCurrentPage</code>.
     * </p>
     *
     * @param tocCurrentPage a int.
     * @should set toc page to last page if value too high
     * @throws io.goobi.viewer.exceptions.PresentationException if any.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws io.goobi.viewer.exceptions.DAOException if any.
     * @throws io.goobi.viewer.exceptions.ViewerConfigurationException if any.
     */
    public void setTocCurrentPage(String tocCurrentPage)
            throws PresentationException, IndexUnreachableException, DAOException, ViewerConfigurationException {
        synchronized (this) {
            int[] pages = StringTools.getIntegerRange(tocCurrentPage);
            this.tocCurrentPage = pages[0];
            if (this.tocCurrentPage < 1) {
                this.tocCurrentPage = 1;
            }
            // Do not call getToc() here - the setter is usually called before update(), so the required information for proper TOC creation is not yet available
            if (viewManager != null && viewManager.getToc() != null) {
                int currentCurrentPage = viewManager.getToc().getCurrentPage();
                viewManager.getToc().setCurrentPage(this.tocCurrentPage);
                // The TOC object will correct values that are too high, so update the local value, if necessary
                if (viewManager.getToc().getCurrentPage() != this.tocCurrentPage) {
                    this.tocCurrentPage = viewManager.getToc().getCurrentPage();
                }
                // Create a new TOC if pagination is enabled and the paginator page has changed
                if (currentCurrentPage != this.tocCurrentPage && DataManager.getInstance().getConfiguration().getTocAnchorGroupElementsPerPage() > 0
                        && viewManager != null) {
                    viewManager.getToc()
                            .generate(viewManager.getTopStructElement(), viewManager.isListAllVolumesInTOC(), viewManager.getMimeType(),
                                    this.tocCurrentPage);
                }
            }
        }
    }

    /**
     * <p>
     * getTitleBarLabel.
     * </p>
     *
     * @param locale a {@link java.util.Locale} object.
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws io.goobi.viewer.exceptions.PresentationException if any.
     * @throws io.goobi.viewer.exceptions.DAOException if any.
     * @throws io.goobi.viewer.exceptions.ViewerConfigurationException if any.
     */
    public String getTitleBarLabel(Locale locale)
            throws IndexUnreachableException, PresentationException, DAOException, ViewerConfigurationException {
        return getTitleBarLabel(locale.getLanguage());
    }

    /**
     * <p>
     * getTitleBarLabel.
     * </p>
     *
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws io.goobi.viewer.exceptions.PresentationException if any.
     * @throws io.goobi.viewer.exceptions.DAOException if any.
     * @throws io.goobi.viewer.exceptions.ViewerConfigurationException if any.
     */
    public String getTitleBarLabel() throws IndexUnreachableException, PresentationException, DAOException, ViewerConfigurationException {
        Locale locale = BeanUtils.getLocale();
        if (locale != null) {
            return getTitleBarLabel(locale.getLanguage());
        }

        return getTitleBarLabel(MultiLanguageMetadataValue.DEFAULT_LANGUAGE);
    }

    /**
     * <p>
     * getTitleBarLabel.
     * </p>
     *
     * @param language a {@link java.lang.String} object.
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws io.goobi.viewer.exceptions.PresentationException if any.
     * @throws io.goobi.viewer.exceptions.DAOException if any.
     * @throws io.goobi.viewer.exceptions.ViewerConfigurationException if any.
     */
    public String getTitleBarLabel(String language)
            throws IndexUnreachableException, PresentationException, DAOException, ViewerConfigurationException {
        if (navigationHelper == null) {
            return null;
        }

        if (PageType.getByName(navigationHelper.getCurrentPage()) != null
                && PageType.getByName(navigationHelper.getCurrentPage()).isDocumentPage() && viewManager != null) {
            // Prefer the label of the current TOC element
            TOC toc = getToc();
            if (toc != null && toc.getTocElements() != null && !toc.getTocElements().isEmpty()) {
                String label = null;
                String labelTemplate = "_DEFAULT";
                if (getViewManager() != null) {
                    labelTemplate = getViewManager().getTopStructElement().getDocStructType();
                }
                if (DataManager.getInstance().getConfiguration().isDisplayAnchorLabelInTitleBar(labelTemplate)
                        && StringUtils.isNotBlank(viewManager.getAnchorPi())) {
                    String prefix = DataManager.getInstance().getConfiguration().getAnchorLabelInTitleBarPrefix(labelTemplate);
                    String suffix = DataManager.getInstance().getConfiguration().getAnchorLabelInTitleBarSuffix(labelTemplate);
                    prefix = ViewerResourceBundle.getTranslation(prefix, Locale.forLanguageTag(language)).replace("_SPACE_", " ");
                    suffix = ViewerResourceBundle.getTranslation(suffix, Locale.forLanguageTag(language)).replace("_SPACE_", " ");
                    label = prefix = toc.getLabel(viewManager.getAnchorPi(), language) + suffix + toc.getLabel(viewManager.getPi(), language);
                } else {
                    label = toc.getLabel(viewManager.getPi(), language);
                }
                if (label != null) {
                    return label;
                }
            }
            String label = viewManager.getTopStructElement().getLabel(selectedRecordLanguage);
            if (StringUtils.isNotEmpty(label)) {
                return label;
            }
        } else if (cmsBean != null && navigationHelper.isCmsPage()) {
            CMSPage cmsPage = cmsBean.getCurrentPage();
            if (cmsPage != null) {
                String cmsPageName = StringUtils.isNotBlank(cmsPage.getMenuTitle()) ? cmsPage.getMenuTitle() : cmsPage.getTitle();
                if (StringUtils.isNotBlank(cmsPageName)) {
                    return cmsPageName;
                }
            }
        }

        if (navigationHelper.getCurrentPageType() != null) {
            PageType pageType = navigationHelper.getCurrentPageType();
            if (PageType.other.equals(pageType)) {
                String pageLabel = navigationHelper.getCurrentPage();
                if (StringUtils.isNotBlank(pageLabel)) {
                    return Messages.translate(pageLabel, Locale.forLanguageTag(language));
                }
            }
            return Messages.translate(pageType.getLabel(), Locale.forLanguageTag(language));
        }

        return null;
    }

    /**
     * Title bar label value escaped for JavaScript.
     *
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws io.goobi.viewer.exceptions.PresentationException if any.
     * @throws io.goobi.viewer.exceptions.DAOException if any.
     * @throws io.goobi.viewer.exceptions.ViewerConfigurationException if any.
     */
    public String getLabelForJS() throws IndexUnreachableException, PresentationException, DAOException, ViewerConfigurationException {
        String label = getTitleBarLabel();
        if (label != null) {
            return StringEscapeUtils.escapeEcmaScript(label);
        }

        return null;
    }

    /**
     * <p>
     * Getter for the field <code>imageContainerWidth</code>.
     * </p>
     *
     * @return a int.
     */
    public int getImageContainerWidth() {
        return imageContainerWidth;
    }

    /**
     * <p>
     * getNumberOfImages.
     * </p>
     *
     * @return a int.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     */
    public int getNumberOfImages() throws IndexUnreachableException {
        if (viewManager != null) {
            return viewManager.getImagesCount();
        }

        return 0;
    }

    /**
     * <p>
     * Getter for the field <code>topDocumentIddoc</code>.
     * </p>
     *
     * @return Not this.topDocumentIddoc but ViewManager.topDocumentIddoc
     */
    public long getTopDocumentIddoc() {
        if (viewManager != null) {
            return viewManager.getTopStructElementIddoc();
        }
        return 0;
    }

    /**
     * Indicates whether a record is currently properly loaded in this bean. Use to determine whether to display components.
     *
     * @return a boolean.
     */
    public boolean isRecordLoaded() {
        return viewManager != null;
    }

    /**
     * Checks if there is an anchor in this docStruct's hierarchy
     *
     * @return a boolean.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     */
    public boolean hasAnchor() throws IndexUnreachableException {
        return getTopDocument().isAnchorChild();
    }

    /**
     * Exports the currently loaded for re-indexing.
     *
     * @return a {@link java.lang.String} object.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws io.goobi.viewer.exceptions.DAOException if any.
     * @throws io.goobi.viewer.exceptions.RecordNotFoundException if any.
     */
    public String reIndexRecordAction() throws IndexUnreachableException, DAOException, RecordNotFoundException {
        if (viewManager != null) {
            if (IndexerTools.reIndexRecord(viewManager.getPi())) {
                Messages.info("reIndexRecordSuccess");
            } else {
                Messages.error("reIndexRecordFailure");
            }
        }

        return "";
    }

    /**
     * <p>
     * deleteRecordAction.
     * </p>
     *
     * @param keepTraceDocument If true, a .delete file will be created; otherwise a .purge file
     * @return outcome
     * @throws java.io.IOException if any.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     */
    public String deleteRecordAction(boolean keepTraceDocument) throws IOException, IndexUnreachableException {
        try {
            if (viewManager == null) {
                return "";
            }

            if (IndexerTools.deleteRecord(viewManager.getPi(), keepTraceDocument,
                    Paths.get(DataManager.getInstance().getConfiguration().getHotfolder()))) {
                Messages.info("deleteRecord_success");
                return "pretty:index";
            }
            Messages.error("deleteRecord_failure");
        } finally {
            deleteRecordKeepTrace = null;
        }

        return "";
    }

    /**
     *
     * @return
     * @throws ClientProtocolException
     * @throws IOException
     * @throws IndexUnreachableException
     */
    public String clearCacheAction() throws ClientProtocolException, IOException, IndexUnreachableException {
        logger.trace("clearCacheAction: {}", clearCacheMode);
        if (clearCacheMode == null || viewManager == null) {
            return "";
        }

        String url = NetTools.buildClearCacheUrl(clearCacheMode, viewManager.getPi(), navigationHelper.getApplicationUrl(),
                DataManager.getInstance().getConfiguration().getWebApiToken());
        try {
            try {
                NetTools.getWebContentDELETE(url, null, null, null, null);
                Messages.info("cache_clear__success");
            } catch (ClientProtocolException e) {
                logger.error(e.getMessage());
                Messages.error("cache_clear__failure");
            } catch (IOException e) {
                logger.error(e.getMessage());
                Messages.error("cache_clear__failure");
            }
        } finally {
            clearCacheMode = null;
        }

        return "";
    }

    /**
     * <p>
     * getCurrentThumbnailPage.
     * </p>
     *
     * @return a int.
     */
    public int getCurrentThumbnailPage() {
        synchronized (this) {
            return viewManager != null ? viewManager.getCurrentThumbnailPage() : 1;
        }
    }

    /**
     * <p>
     * setCurrentThumbnailPage.
     * </p>
     *
     * @param currentThumbnailPage a int.
     */
    public void setCurrentThumbnailPage(int currentThumbnailPage) {
        synchronized (this) {
            if (viewManager != null) {
                viewManager.setCurrentThumbnailPage(currentThumbnailPage);
            }
        }
    }

    /**
     * <p>
     * isHasLanguages.
     * </p>
     *
     * @return a boolean.
     */
    public boolean isHasLanguages() {
        return recordLanguages != null && !recordLanguages.isEmpty();
    }

    /**
     * <p>
     * Getter for the field <code>recordLanguages</code>.
     * </p>
     *
     * @return the recordLanguages
     */
    public List<String> getRecordLanguages() {
        return recordLanguages;
    }

    /**
     * <p>
     * Setter for the field <code>recordLanguages</code>.
     * </p>
     *
     * @param recordLanguages the recordLanguages to set
     */
    public void setRecordLanguages(List<String> recordLanguages) {
        this.recordLanguages = recordLanguages;
    }

    /**
     * <p>
     * Getter for the field <code>selectedRecordLanguage</code>.
     * </p>
     *
     * @return the selectedRecordLanguage
     */
    public String getSelectedRecordLanguage() {
        return selectedRecordLanguage;
    }

    /**
     * <p>
     * Setter for the field <code>selectedRecordLanguage</code>.
     * </p>
     *
     * @param selectedRecordLanguage the selectedRecordLanguage to set
     */
    public void setSelectedRecordLanguage(String selectedRecordLanguage) {
        logger.trace("setSelectedRecordLanguage: {}", selectedRecordLanguage);
        if (selectedRecordLanguage != null && selectedRecordLanguage.length() == 3) {
            // Map ISO-3 codes to their ISO-2 variant
            Language language = DataManager.getInstance().getLanguageHelper().getLanguage(selectedRecordLanguage);
            if (language != null) {
                logger.trace("Mapped language found: {}", language.getIsoCodeOld());
                this.selectedRecordLanguage = language.getIsoCodeOld();
            } else {
                logger.warn("Language not found for code: {}", selectedRecordLanguage);
                this.selectedRecordLanguage = selectedRecordLanguage;
            }
        } else {
            this.selectedRecordLanguage = selectedRecordLanguage;
        }
        MetadataBean mdb = BeanUtils.getMetadataBean();
        if (mdb != null) {
            mdb.setSelectedRecordLanguage(this.selectedRecordLanguage);
        }
    }

    /**
     * <p>
     * isAccessPermissionEpub.
     * </p>
     *
     * @return a boolean.
     */
    public boolean isAccessPermissionEpub() {
        synchronized (this) {
            try {
                if ((navigationHelper != null && !isEnabled(EPUBDownloadJob.LOCAL_TYPE, navigationHelper.getCurrentPage())) || viewManager == null
                        || !DownloadJob.ocrFolderExists(viewManager.getPi())) {
                    return false;
                }
            } catch (PresentationException | IndexUnreachableException e) {
                logger.error("Error checking EPUB resources: {}", e.getMessage());
                return false;
            }

            // TODO EPUB privilege type
            return viewManager.isAccessPermissionPdf();
        }
    }

    /**
     * <p>
     * isAccessPermissionPdf.
     * </p>
     *
     * @return a boolean.
     */
    public boolean isAccessPermissionPdf() {
        synchronized (this) {
            if ((navigationHelper != null && !isEnabled(PDFDownloadJob.LOCAL_TYPE, navigationHelper.getCurrentPage())) || viewManager == null) {
                return false;
            }

            return viewManager.isAccessPermissionPdf();
        }
    }

    /**
     * @param currentPage
     * @return
     */
    private static boolean isEnabled(String downloadType, String pageTypeName) {
        if (downloadType.equals(EPUBDownloadJob.LOCAL_TYPE) && !DataManager.getInstance().getConfiguration().isGeneratePdfInTaskManager()) {
            return false;
        }
        PageType pageType = PageType.getByName(pageTypeName);
        boolean pdf = PDFDownloadJob.LOCAL_TYPE.equals(downloadType);
        if (pageType != null) {
            switch (pageType) {
                case viewToc:
                    return pdf ? DataManager.getInstance().getConfiguration().isTocPdfEnabled()
                            : DataManager.getInstance().getConfiguration().isTocEpubEnabled();
                case viewMetadata:
                    return pdf ? DataManager.getInstance().getConfiguration().isMetadataPdfEnabled()
                            : DataManager.getInstance().getConfiguration().isMetadataEpubEnabled();
                default:
                    return pdf ? DataManager.getInstance().getConfiguration().isTitlePdfEnabled()
                            : DataManager.getInstance().getConfiguration().isTitleEpubEnabled();
            }
        }

        logger.warn("Unknown page type: {}", pageTypeName);
        return false;
    }

    /**
     * <p>
     * downloadTOCAction.
     * </p>
     *
     * @throws java.io.IOException if any.
     * @throws io.goobi.viewer.exceptions.PresentationException if any.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws io.goobi.viewer.exceptions.DAOException if any.
     * @throws io.goobi.viewer.exceptions.ViewerConfigurationException if any.
     */
    public void downloadTOCAction() throws IOException, PresentationException, IndexUnreachableException, DAOException, ViewerConfigurationException {
        try {

            String fileNameRaw = getToc().getTocElements().get(0).getLabel();
            String fileName = fileNameRaw + ".pdf";

            FacesContext fc = FacesContext.getCurrentInstance();
            ExternalContext ec = fc.getExternalContext();
            ec.responseReset(); // Some JSF component library or some Filter might have set some headers in the buffer beforehand. We want to get rid of them, else it may collide.
            ec.setResponseContentType("application/pdf");
            ec.setResponseHeader("Content-Disposition", "attachment; filename=\"" + fileName + "\"");
            OutputStream os = ec.getResponseOutputStream();
            TocWriter writer = new TocWriter("", fileNameRaw);
            writer.createPdfDocument(os, getToc().getTocElements());
            fc.responseComplete(); // Important! Otherwise JSF will attempt to render the response which obviously will fail since it's already written with a file and closed.
        } catch (IndexOutOfBoundsException e) {
            logger.error("No toc to generate");
        } catch (WriteTocException e) {
            logger.error("Error writing toc: " + e.getMessage(), e);
        }
    }

    /**
     * <p>
     * getRelatedItems.
     * </p>
     *
     * @param identifierField Index field containing related item identifiers
     * @return List of related items as SearchHit objects.
     * @throws io.goobi.viewer.exceptions.PresentationException if any.
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws io.goobi.viewer.exceptions.DAOException if any.
     * @throws io.goobi.viewer.exceptions.ViewerConfigurationException if any.
     */
    public List<SearchHit> getRelatedItems(String identifierField)
            throws PresentationException, IndexUnreachableException, DAOException, ViewerConfigurationException {
        logger.trace("getRelatedItems: {}", identifierField);
        if (identifierField == null) {
            return null;
        }
        if (viewManager == null) {
            return null;
        }
        String query = getRelatedItemsQueryString(identifierField);
        if (query == null) {
            return null;
        }

        List<SearchHit> ret = SearchHelper.searchWithAggregation(query, 0, SolrSearchIndex.MAX_HITS, null, null, null, null, null, null,
                navigationHelper.getLocale(), 0);

        logger.trace("{} related items found", ret.size());
        return ret;
    }

    /**
     * Returns a query string containing all values of the given identifier field.
     *
     * @param identifierField Index field containing related item identifiers
     * @return Query string of the pattern "PI:(a OR b OR c)"
     * @should construct query correctly
     */
    public String getRelatedItemsQueryString(String identifierField) {
        logger.trace("getRelatedItemsQueryString: {}", identifierField);
        List<String> relatedItemIdentifiers = viewManager.getTopStructElement().getMetadataValues(identifierField);
        if (relatedItemIdentifiers.isEmpty()) {
            return null;
        }

        StringBuilder sbQuery = new StringBuilder(SolrConstants.PI).append(":(");
        int initLength = sbQuery.length();
        for (String identifier : relatedItemIdentifiers) {
            if (sbQuery.length() > initLength) {
                sbQuery.append(" OR ");
            }
            sbQuery.append(identifier);
        }
        sbQuery.append(')');

        return sbQuery.toString();
    }

    /**
     * Returns a string that contains previous and/or next url <link> elements
     *
     * @return string containing previous and/or next url <link> elements
     * @throws io.goobi.viewer.exceptions.IndexUnreachableException if any.
     * @throws io.goobi.viewer.exceptions.DAOException if any.
     * @throws io.goobi.viewer.exceptions.PresentationException if any.
     */
    public String getRelativeUrlTags() throws IndexUnreachableException, DAOException, PresentationException {
        if (!isRecordLoaded() || navigationHelper == null) {
            return "";
        }
        if (logger.isTraceEnabled()) {
            logger.trace("current view: {}", navigationHelper.getCurrentView());
        }

        StringBuilder sb = new StringBuilder();

        // Add canonical links
        if (viewManager.getCurrentPage() != null) {
            if (StringUtils.isNotEmpty(viewManager.getCurrentPage().getUrn())) {
                String urnResolverUrl = DataManager.getInstance().getConfiguration().getUrnResolverUrl() + viewManager.getCurrentPage().getUrn();
                sb.append("\n<link rel=\"canonical\" href=\"").append(urnResolverUrl).append("\" />");
            }
            if (viewManager.getCurrentPage().equals(viewManager.getRepresentativePage())) {
                String piResolverUrl = navigationHelper.getApplicationUrl() + "piresolver?id=" + viewManager.getPi();
                sb.append("\n<link rel=\"canonical\" href=\"").append(piResolverUrl).append("\" />");
            }
        }
        PageType currentPageType = PageType.getByName(navigationHelper.getCurrentView());
        if (currentPageType != null && StringUtils.isNotEmpty(currentPageType.name())) {
            // logger.trace("page type: {}", currentPageType.getName());
            // logger.trace("current url: {}", navigationHelper.getCurrentUrl());
            String currentUrl = navigationHelper.getCurrentUrl();
            
            if(currentUrl.contains(SolrTools.unescapeSpecialCharacters(getLogid()))) {
                currentUrl = currentUrl.replace(SolrTools.unescapeSpecialCharacters(getLogid()), getLogid());
            }
            
            if (currentUrl.contains("!" + currentPageType.getName())) {
                // Preferred view - add regular view URL
                sb.append("\n<link rel=\"canonical\" href=\"")
                        .append(currentUrl.replace("!" + currentPageType.getName(), currentPageType.getName()))
                        .append("\" />");
            } else if (currentUrl.contains(currentPageType.getName())) {
                // Regular view - add preferred view URL
                sb.append("\n<link rel=\"canonical\" href=\"")
                        .append(currentUrl.replace(currentPageType.getName(), "!" + currentPageType.getName()))
                        .append("\" />");
            }
        }

        // Skip prev/next links for non-paginated views
        if (PageType.viewMetadata.equals(currentPageType) || PageType.viewToc.equals(currentPageType)) {
            return "";
        }

        // Add next/prev links
        String currentUrl = getPageUrl(imageToShow);
        String prevUrl = getPreviousPageUrl();
        String nextUrl = getNextPageUrl();
        if (StringUtils.isNotEmpty(nextUrl) && !nextUrl.equals(currentUrl)) {
            sb.append("\n<link rel=\"next\" href=\"").append(nextUrl).append("\" />");
        }
        if (StringUtils.isNotEmpty(prevUrl) && !prevUrl.equals(currentUrl)) {
            sb.append("\n<link rel=\"prev\" href=\"").append(prevUrl).append("\" />");
        }

        return sb.toString();
    }

    /**
     * resets the access rights for user comments and pdf download stored in {@link io.goobi.viewer.model.viewer.ViewManager}. After reset, the access
     * rights will be evaluated again on being called
     */
    public void resetAccess() {
        if (getViewManager() != null) {
            getViewManager().resetAccessPermissionPdf();
            getViewManager().resetAllowUserComments();
        }
    }

    /**
     * <p>
     * Getter for the field <code>deleteRecordKeepTrace</code>.
     * </p>
     *
     * @return the deleteRecordKeepTrace
     */
    public Boolean getDeleteRecordKeepTrace() {
        return deleteRecordKeepTrace;
    }

    /**
     * <p>
     * Setter for the field <code>deleteRecordKeepTrace</code>.
     * </p>
     *
     * @param deleteRecordKeepTrace the deleteRecordKeepTrace to set
     */
    public void setDeleteRecordKeepTrace(Boolean deleteRecordKeepTrace) {
        this.deleteRecordKeepTrace = deleteRecordKeepTrace;
    }

    /**
     * @return the clearCacheMode
     */
    public String getClearCacheMode() {
        return clearCacheMode;
    }

    /**
     * @param clearCacheMode the clearCacheMode to set
     */
    public void setClearCacheMode(String clearCacheMode) {
        logger.trace("setClearCacheMode: {}", clearCacheMode);
        this.clearCacheMode = clearCacheMode;
    }

    /**
     * Get a CMSSidebarElement with a map containing all GeoMarkers for the current PI. The widget is stored in the bean, but refreshed each time the
     * PI changes
     *
     * @return
     * @throws PresentationException
     * @throws DAOException
     * @throws IndexUnreachableException
     */
    public synchronized GeoMap getGeoMap() throws PresentationException, DAOException, IndexUnreachableException {
        GeoMap widget = this.geoMaps.get(getPersistentIdentifier());
        if (widget == null) {
            widget = generateGeoMap(getPersistentIdentifier());
            this.geoMaps = Collections.singletonMap(getPersistentIdentifier(), widget);
        }
        return widget;
    }

    /**
     * 
     * @param pi
     * @return
     * @throws PresentationException
     * @throws DAOException
     */
    public GeoMap generateGeoMap(String pi) throws PresentationException, DAOException {
        try {
            if ("-".equals(pi)) {
                return null;
            }

            GeoMap map = new GeoMap();
            map.setId(Long.MAX_VALUE);
            map.setType(GeoMapType.MANUAL);
            map.setShowPopover(true);
            map.setMarkerTitleField(null);
            map.setMarker("default");

            String mainDocQuery = String.format("PI:%s", pi);
            List<String> mainDocFields = PrettyUrlTools.getSolrFieldsToDeterminePageType();
            SolrDocument mainDoc = DataManager.getInstance().getSearchIndex().getFirstDoc(mainDocQuery, mainDocFields);
            PageType pageType = PrettyUrlTools.getPreferredPageType(mainDoc);

            boolean addMetadataFeatures = DataManager.getInstance().getConfiguration().includeCoordinateFieldsFromMetadataDocs();
            String docTypeFilter = "+DOCTYPE:DOCSTRCT";
            if (addMetadataFeatures) {
                docTypeFilter = "+(DOCTYPE:DOCSTRCT DOCTYPE:METADATA)";
            }

            String subDocQuery = String.format("+PI_TOPSTRUCT:%s " + docTypeFilter, pi);
            List<String> coordinateFields = DataManager.getInstance().getConfiguration().getGeoMapMarkerFields();
            List<String> subDocFields = new ArrayList<>();
            subDocFields.add(SolrConstants.LABEL);
            subDocFields.add(SolrConstants.PI_TOPSTRUCT);
            subDocFields.add(SolrConstants.THUMBPAGENO);
            subDocFields.add(SolrConstants.LOGID);
            subDocFields.add(SolrConstants.ISWORK);
            subDocFields.add(SolrConstants.DOCTYPE);
            subDocFields.add("MD_VALUE");
            subDocFields.addAll(coordinateFields);

            Collection<GeoMapFeature> features = new ArrayList<>();

            List<DisplayUserGeneratedContent> annos = DataManager.getInstance()
                    .getDao()
                    .getAnnotationsForWork(pi)
                    .stream()
                    .filter(a -> PublicationStatus.PUBLISHED.equals(a.getPublicationStatus()))
                    .filter(a -> StringUtils.isNotBlank(a.getBody()))
                    .map(a -> new DisplayUserGeneratedContent(a))
                    .filter(a -> ContentType.GEOLOCATION.equals(a.getType()))
                    .filter(a -> ContentBean.isAccessible(a, BeanUtils.getRequest()))
                    .collect(Collectors.toList());
            for (DisplayUserGeneratedContent anno : annos) {
                if (anno.getAnnotationBody() instanceof TypedResource) {
                    GeoMapFeature feature = new GeoMapFeature(((TypedResource) anno.getAnnotationBody()).asJson());
                    features.add(feature);
                }
            }

            SolrDocumentList subDocs = DataManager.getInstance().getSearchIndex().getDocs(subDocQuery, subDocFields);
            if (subDocs != null) {
                for (SolrDocument solrDocument : subDocs) {
                    List<GeoMapFeature> docFeatures = new ArrayList<>();
                    for (String coordinateField : coordinateFields) {
                        String docType = solrDocument.getFieldValue(SolrConstants.DOCTYPE).toString();
                        String labelField = "METADATA".equals(docType) ? "MD_VALUE" : SolrConstants.LABEL;
                        docFeatures.addAll(GeoMap.getGeojsonPoints(solrDocument, coordinateField, labelField, null));
                    }
                    if (!solrDocument.containsKey(SolrConstants.ISWORK) && solrDocument.getFieldValue(SolrConstants.DOCTYPE).equals("DOCSTRCT")) {
                        docFeatures.forEach(f -> f.setLink(PrettyUrlTools.getRecordUrl(solrDocument, pageType)));
                    } else {
                        docFeatures.forEach(f -> f.setLink(null));
                    }
                    docFeatures.forEach(f -> f.setDocumentId((String) solrDocument.getFieldValue(SolrConstants.LOGID)));
                    features.addAll(docFeatures);
                }
            }
            //remove dubplicates
            features = features.stream().distinct().collect(Collectors.toList());
            if (!features.isEmpty()) {
                map.setFeatures(features.stream().map(f -> f.getJsonObject().toString()).collect(Collectors.toList()));
            }
            return map;
        } catch (IndexUnreachableException e) {
            logger.error("Unable to load geomap", e);
            return null;
        }
    }

    /**
     *
     */
    public void toggleDownloadImageModal() {
        downloadImageModalVisible = !downloadImageModalVisible;
    }

    /**
     * @return the downloadImageModalVisible
     */
    public boolean isDownloadImageModalVisible() {
        return downloadImageModalVisible;
    }

    /**
     *
     */
    public DownloadOption getSelectedDownloadOption() {
        if (selectedDownloadOptionLabel == null) {
            return null;
        }

        return DownloadOption.getByLabel(selectedDownloadOptionLabel);
    }

    /**
     * @return the selectedDownloadOptionLabel
     */
    public String getSelectedDownloadOptionLabel() {
        return selectedDownloadOptionLabel;
    }

    /**
     * @param selectedDownloadOptionLabel the selectedDownloadOptionLabel to set
     */
    public void setSelectedDownloadOptionLabel(String selectedDownloadOptionLabel) {
        logger.trace("setSelectedDownloadOption: {}", selectedDownloadOptionLabel != null ? selectedDownloadOptionLabel : null);
        this.selectedDownloadOptionLabel = selectedDownloadOptionLabel;
    }

    public void setDownloadOptionLabelFromRequestParameter() {
        Map<String, String> params = FacesContext.getCurrentInstance().getExternalContext().getRequestParameterMap();

        String value = params.get("optionvalue");
        if (StringUtils.isNotBlank(value)) {
            setSelectedDownloadOptionLabel(value);
        }

    }

    /**
     * This method augments the setter <code>ViewManager.setDoublePageMode(boolean)</code> with URL modifications to reflect the mode.
     *
     * @param doublePageMode The doublePageMode to set
     * @throws IndexUnreachableException
     * @throws DAOException
     * @should set imageToShow if value changes
     */
    public String setDoublePageModeAction(boolean doublePageMode) throws IndexUnreachableException, DAOException {
        if (viewManager == null) {
            return "";
        }
        try {
            // Adapt URL page range when switching between single and double page modes
            if (viewManager.isDoublePageMode() != doublePageMode) {
                if (doublePageMode && !viewManager.getCurrentPage().isDoubleImage()) {
                    Optional<PhysicalElement> currentLeftPage = viewManager.getCurrentLeftPage();
                    Optional<PhysicalElement> currentRightPage = viewManager.getCurrentRightPage();
                    if (currentLeftPage.isPresent() && currentRightPage.isPresent()) {
                        imageToShow = currentLeftPage.get().getOrder() + "-" + currentRightPage.get().getOrder();
                    } else if (currentLeftPage.isPresent()) {
                        imageToShow = currentLeftPage.get().getOrder() + "-" + currentLeftPage.get().getOrder();
                    } else if (currentRightPage.isPresent()) {
                        imageToShow = currentRightPage.get().getOrder() + "-" + currentRightPage.get().getOrder();
                    }
                } else if (doublePageMode) {
                    imageToShow = String.valueOf(viewManager.getCurrentPage().getOrder() + "-" + viewManager.getCurrentPage().getOrder());
                } else {
                    imageToShow = String.valueOf(viewManager.getCurrentPage().getOrder());
                }
            }
        } finally {
            viewManager.setDoublePageMode(doublePageMode);
        }

        // When not using PrettyContext, the updated URL will always be a click behind
        if (PrettyContext.getCurrentInstance() != null && PrettyContext.getCurrentInstance().getCurrentMapping() != null) {
            return "pretty:" + PrettyContext.getCurrentInstance().getCurrentMapping().getId();
        }

        return "";
    }

    /**
     * Indicates whether user comments are allowed for the current record based on several criteria.
     *
     * @return a boolean.
     * @throws DAOException
     */
    public synchronized boolean isAllowUserComments() throws DAOException {
        if (viewManager == null) {
            return false;
        }

        CommentGroup commentGroupAll = DataManager.getInstance().getDao().getCommentGroupUnfiltered();
        if (commentGroupAll == null) {
            logger.warn("Comment view for all comments not found in the DB, please insert.");
            return false;
        }
        if (!commentGroupAll.isEnabled()) {
            logger.trace("User comments disabled globally.");
            viewManager.setAllowUserComments(false);
            return false;
        }

        if (viewManager.isAllowUserComments() == null) {
            try {
                if (StringUtils.isNotEmpty(commentGroupAll.getSolrQuery()) && DataManager.getInstance()
                        .getSearchIndex()
                        .getHitCount(new StringBuilder("+").append(SolrConstants.PI)
                                .append(':')
                                .append(viewManager.getPi())
                                .append(" +(")
                                .append(commentGroupAll.getSolrQuery())
                                .append(')')
                                .toString()) == 0) {
                    viewManager.setAllowUserComments(false);
                    logger.trace("User comments are not allowed for this record.");
                } else {
                    viewManager.setAllowUserComments(true);
                }
            } catch (IndexUnreachableException e) {
                logger.debug("IndexUnreachableException thrown here: {}", e.getMessage());
                return false;
            } catch (PresentationException e) {
                logger.debug(StringConstants.LOG_PRESENTATION_EXCEPTION_THROWN_HERE, e.getMessage());
                return false;
            }
        }

        return viewManager.isAllowUserComments();
    }

    /**
     * Check if the current page should initialize a WebSocket
     * 
     * @return true if a document is loaded and it contains the field {@link SolrConstants.ACCESSCONDITION_CONCURRENTUSE}
     */
    public boolean isRequiresWebSocket() {
        if (viewManager != null && viewManager.getTopStructElement() != null && viewManager.getTopStructElement().getMetadataFields() != null) {
            return viewManager.getTopStructElement().getMetadataFields().containsKey(SolrConstants.ACCESSCONDITION_CONCURRENTUSE);
        }

        return false;
    }

}

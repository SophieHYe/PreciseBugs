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
 * Agency.  Portions created by Tieto Estonia are Copyright
 * (C) European Environment Agency.  All Rights Reserved.
 *
 * Contributor(s):
 * Enriko Käsper, Tieto Estonia
 */
package eionet.cr.web.action;

import java.util.ArrayList;
import java.util.LinkedList;
import java.util.List;

import net.sourceforge.stripes.action.DefaultHandler;
import net.sourceforge.stripes.action.ForwardResolution;
import net.sourceforge.stripes.action.Resolution;
import net.sourceforge.stripes.action.UrlBinding;
import eionet.cr.common.Predicates;
import eionet.cr.config.GeneralConfig;
import eionet.cr.dao.DAOException;
import eionet.cr.dao.DAOFactory;
import eionet.cr.dao.SearchDAO;
import eionet.cr.dto.SearchResultDTO;
import eionet.cr.dto.SubjectDTO;
import eionet.cr.dto.TagDTO;
import eionet.cr.util.SortOrder;
import eionet.cr.util.SortingRequest;
import eionet.cr.util.pagination.PagingRequest;
import eionet.cr.web.util.ApplicationCache;
import eionet.cr.web.util.columns.SearchResultColumn;
import eionet.cr.web.util.columns.SubjectPredicateColumn;
import org.apache.commons.lang.StringEscapeUtils;

/**
 *
 * @author <a href="mailto:enriko.kasper@tieto.com">Enriko Käsper</a>
 *
 */

@UrlBinding("/tagSearch.action")
public class TagSearchActionBean extends AbstractSearchActionBean<SubjectDTO> {

    /** */
    private static final String TAG_SEARCH_PATH = "/pages/tagSearch.jsp";
    private static final String SELECTED_TAGS_CACHE = TypeSearchActionBean.class.getName() + ".selectedTagsCache";

    /** */
    private List<TagDTO> tagCloud;
    private String cloudSorted = "name";
    private String searchTag;
    private String queryString;

    // selected tags
    private List<String> selectedTags;
    // columns
    private static final ArrayList<SearchResultColumn> columns;

    private int tagCloudSize = Integer.parseInt(GeneralConfig.getProperty(GeneralConfig.TAGCOLUD_TAGSEARCH_SIZE));

    static {
        columns = new ArrayList<SearchResultColumn>();

        SubjectPredicateColumn col = new SubjectPredicateColumn();
        col.setPredicateUri(Predicates.RDF_TYPE);
        col.setTitle("Type");
        col.setSortable(true);
        columns.add(col);

        col = new SubjectPredicateColumn();
        col.setPredicateUri(Predicates.RDFS_LABEL);
        col.setTitle("Label");
        col.setSortable(true);
        columns.add(col);

        col = new SubjectPredicateColumn();
        col.setPredicateUri(Predicates.CR_TAG);
        col.setTitle("Tags");
        col.setSortable(false);
        columns.add(col);
    }

    /**
     * @return
     * @throws Exception
     */
    @DefaultHandler
    public Resolution preparePage() throws Exception {
        tagCloud = ApplicationCache.getTagCloudSortedByName(tagCloudSize);
        return new ForwardResolution(TAG_SEARCH_PATH);
    }

    public Resolution sortByName() throws Exception {
        tagCloud = ApplicationCache.getTagCloudSortedByName(tagCloudSize);
        cloudSorted = "name";
        return new ForwardResolution(TAG_SEARCH_PATH);
    }

    public Resolution sortByCount() throws Exception {
        tagCloud = ApplicationCache.getTagCloudSortedByCount(tagCloudSize);
        cloudSorted = "count";
        return new ForwardResolution(TAG_SEARCH_PATH);
    }

    @Override
    public Resolution search() throws DAOException {

        if ((searchTag == null || searchTag.isEmpty()) && (selectedTags == null || selectedTags.isEmpty())) {
            return new ForwardResolution(TAG_SEARCH_PATH);
        }
        if (selectedTags == null) {
            selectedTags = new LinkedList<String>();
        }

        if (!selectedTags.contains(searchTag) && searchTag != null && !searchTag.isEmpty()) {
            selectedTags.add(getSearchTag().trim());
        }

        SearchResultDTO<SubjectDTO> searchResult =
                DAOFactory
                        .get()
                        .getDao(SearchDAO.class)
                        .searchByTags(selectedTags, PagingRequest.create(getPageN()),
                                new SortingRequest(getSortP(), SortOrder.parse(getSortO())));
        resultList = searchResult.getItems();
        matchCount = searchResult.getMatchCount();
        queryString = searchResult.getQuery();
        this.getContext().getRequest().setAttribute("searchTag", "");

        getSession().setAttribute(SELECTED_TAGS_CACHE, selectedTags);

        return new ForwardResolution(TAG_SEARCH_PATH).addParameter("searchTag", "");
    }

    /*
     * reads the selected tag list from session
     */
    @SuppressWarnings("unchecked")
    public Resolution addTag() throws DAOException {
        selectedTags = (List<String>) getSession().getAttribute(SELECTED_TAGS_CACHE);

        return search();
    }

    @SuppressWarnings("unchecked")
    public Resolution removeTag() throws Exception {
        selectedTags = (List<String>) getSession().getAttribute(SELECTED_TAGS_CACHE);
        if (selectedTags != null && !selectedTags.isEmpty()) {
            selectedTags.remove(searchTag);
        }
        searchTag = "";

        Resolution result = null;
        if (selectedTags.size() == 0) {
            result = preparePage();
        } else {
            result = search();
        }
        if (result instanceof ForwardResolution) {
            ((ForwardResolution) result).addParameter("searchTag", "");
        }
        return result;
    }

    public List<TagDTO> getTagCloud() {
        return tagCloud;
    }

    public void setTagCloud(List<TagDTO> tagCloud) {
        this.tagCloud = tagCloud;
    }

    public String getCloudSorted() {
        return cloudSorted;
    }

    public String getSearchTag() {
        return searchTag;
    }

    public void setSearchTag(String searchTag) {

        this.searchTag = StringEscapeUtils.escapeHtml(searchTag);
    }

    public List<String> getSelectedTags() {
        return selectedTags;
    }

    public void setSelectedTags(List<String> selectedTags) {
        this.selectedTags = selectedTags;
    }

    @Override
    public List<SearchResultColumn> getColumns() throws DAOException {
        return columns;
    }

    /**
     * @return the queryString
     */
    public String getQueryString() {
        return queryString;
    }

}

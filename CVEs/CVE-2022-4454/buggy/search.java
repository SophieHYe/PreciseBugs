/*******************************************************************************
 * Copyright  (c) 2013 Mover Zhou
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *******************************************************************************/
package custom.application;

import custom.objects.User;
import custom.objects.bible;
import custom.objects.book;
import custom.objects.keyword;
import org.apache.http.HttpResponse;
import org.apache.http.ParseException;
import org.apache.http.client.ClientProtocolException;
import org.apache.http.client.HttpClient;
import org.apache.http.client.methods.HttpGet;
import org.apache.http.impl.client.DefaultHttpClient;
import org.apache.http.params.HttpProtocolParams;
import org.tinystruct.AbstractApplication;
import org.tinystruct.ApplicationException;
import org.tinystruct.data.component.Field;
import org.tinystruct.data.component.Pager;
import org.tinystruct.data.component.Row;
import org.tinystruct.data.component.Table;
import org.tinystruct.dom.Document;
import org.tinystruct.dom.Element;
import org.tinystruct.handler.Reforward;
import org.tinystruct.http.Request;
import org.tinystruct.http.Response;
import org.tinystruct.http.Session;
import org.tinystruct.system.util.StringUtilities;

import java.io.IOException;
import java.io.InputStream;
import java.io.UnsupportedEncodingException;
import java.net.URLEncoder;
import java.util.Enumeration;
import java.util.Iterator;
import java.util.List;
import java.util.Locale;

import static org.tinystruct.http.Constants.HTTP_REQUEST;
import static org.tinystruct.http.Constants.HTTP_RESPONSE;

public class search extends AbstractApplication {
    private static int i = 0;
    private static String API_KEY = "AIzaSyCgMMCOs8drxcnBclraPiR0eU29qSF1vHM";
    private static String CUSTOM_SEARCH_ENGINE_ID = "016436735745445346824:fgyqgo18wfm";
    private final String[] ids = new String[]{"016436735745445346824:fgyqgo18wfm", "014099384324434647311:udrkfx4-ipk"};
    private final String[] keys = new String[]{"AIzaSyCgMMCOs8drxcnBclraPiR0eU29qSF1vHM", "AIzaSyC-k_Cm_xClsqzeOGk8Dh5ECaZ449Vf6Ic"};
    private Request request;
    private Response response;
    private User usr;

    public static void main(String[] args) throws UnsupportedEncodingException {
        System.out.println(new search().createRequestString("hello", 1));
    }

    @Override
    public void init() {

        this.setAction("bible/search", "query");
        this.setAction("bible/advsearch", "advanced");

        this.setVariable("TEMPLATES_DIR", "/themes", false);
        this.setVariable("keyword", "");
        this.setVariable("start", "0");
        this.setVariable("end", "0");
        this.setVariable("size", "0");
        this.setVariable("value", "");
    }

    @Override
    public void setLocale(Locale locale) {
        super.setLocale(locale);

        this.setText("application.title");
        this.setText("application.language.name");

        this.setText("page.search.title");
        this.setText("page.welcome.caption");
        this.setText("page.language-setting.title");
        this.setText("page.logout.caption");
        this.setText("page.reading.title");

        this.setText("navigator.bible.caption");
        this.setText("navigator.video.caption");
        this.setText("navigator.document.caption");
        this.setText("navigator.reader.caption");
        this.setText("navigator.controller.caption");
        this.setText("navigator.help.caption");

        this.setText("holy.book.forward");
        this.setText("holy.book.previous");
        this.setText("holy.book.next");
        this.setText("holy.book.find-and-reading");
        this.setText("holy.book.tools");
        this.setText("holy.book.select");

        this.setText("holy.bible");
        this.setText("holy.bible.old-testament");
        this.setText("holy.bible.new-testament");

        this.setText("footer.report-a-site-bug");
        this.setText("footer.privacy");
        this.setText("footer.register");
        this.setText("footer.api");
        this.setText("footer.updates-rss");

        this.setText("search.confirm.caption");
        this.setText("search.submit.caption");
        this.setText("search.strict.mode");
        this.setText("search.advanced.mode");

        this.setText("invite.confirm.caption");
        this.setText("invite.submit.caption");

        this.setText("subscribe.plan");
        this.setText("subscribe.bible.plan");
        this.setText("subscribe.article.plan");
        this.setText("subscribe.submit.caption");
        this.setText("subscribe.email.caption");
        this.setText("user.lastlogin.caption");
        this.setText("holy.bible.download");
        this.setText("holy.bible.chinese.download");
        this.setText("search.info", 0, 0, "", 0);

        String username = "";
        if (this.getVariable("username") != null) {
            username = String.valueOf(this.getVariable("username").getValue());
        }

        this.setText("page.welcome.hello", (username == null || username.trim()
                .length() == 0) ? "" : username + "，");
    }

    @Override
    public String version() {
        // TODO Auto-generated method stub
        return null;
    }

    public Object query() throws ApplicationException {
        this.request = (Request) this.context
                .getAttribute(HTTP_REQUEST);
        if (this.request.getParameter("keyword") != null)
            return this.query(this.request.getParameter("keyword"));

        return this;
    }

    public Object query(String query) throws ApplicationException {
        StringBuffer html = new StringBuffer();
        String[] keywords;

        int page = 1, pageSize = 20;

        this.request = (Request) this.context
                .getAttribute(HTTP_REQUEST);
        if (this.request.getParameter("page") == null
                || this.request.getParameter("page").toString().trim().length() <= 0) {
            page = 1;
        } else {
            page = Integer.parseInt(this.request.getParameter("page").toString());
        }

        int startIndex = (page - 1) * pageSize;
        this.setVariable("search.title", "无相关结果 - ");

        if (query.trim().length() > 0) {
            query = StringUtilities.htmlSpecialChars(query);
            if (query.indexOf('|') != -1) {
                String[] q = query.split("|");
                query = q[0];
            }

            query = query.trim();
            keywords = query.split(" ");

            this.setVariable("keyword", query);
            this.setVariable("search.title", query + " - ");
        } else {
            this.setVariable("keyword", "");
            return this;
        }

        StringBuffer condition = new StringBuffer();
        int i = 0, j, k = 0;
        String _keyword;
        while (i < keywords.length) {
            _keyword = keywords[i];
            if (_keyword.trim().length() > 0) {
                if (condition.length() == 0) {
                    condition.append(" bible.content like '%" + _keyword + "%' ");
                } else {
                    condition.append(" AND bible.content like '%" + _keyword + "%' ");
                    /*
                     * if(true)
                     * condition.append(" AND bible.content like '%"+keywords[i]+"%' ");
                     * else
                     * condition.append(" OR bible.content like '%"+keywords[i]+"%' ");
                     */
                }
            }
            i++;
        }

        Locale locale = this.getLocale();
        if (condition.length() == 0)
            condition.append(" book.language='" + locale + "' ");
        else
            condition.append(" AND book.language='" + locale + "' ");

        book book = new book();
        bible bible = new bible();
        if (locale.toString().equalsIgnoreCase(Locale.US.toString())) {
            bible.setTableName("NIV");
        } else if (locale.toString().equalsIgnoreCase(Locale.UK.toString())) {
            bible.setTableName("ESV");
        } else {
            bible.setTableName(locale.toString());
        }

        String SQL = "SELECT bible.*,book.book_name FROM " + bible.getTableName()
                + " as bible left join " + book.getTableName()
                + " as book on bible.book_id=book.book_id where " + condition
                + " order by bible.book_id,bible.chapter_id limit " + startIndex + ","
                + pageSize;
        String look = "SELECT count(bible.id) AS size FROM " + bible.getTableName()
                + " as bible left join " + book.getTableName()
                + " as book on bible.book_id=book.book_id where " + condition;

        Table vtable = bible.find(SQL, new Object[]{});
        boolean noResult = vtable.size() > 0;

        if (!noResult && query.length() > 0) {
            try {
                Table list = book.findWith("WHERE language=? and book_name=?",
                        new Object[]{this.getLocale().toString(), query});
                if (list.size() > 0) {
                    this.response = (Response) this.context
                            .getAttribute(HTTP_RESPONSE);

                    Reforward reforward = new Reforward(request, response);
                    query = URLEncoder.encode(query, "utf-8");
                    reforward.setDefault(this.context.getAttribute("HTTP_HOST") + query);
                    reforward.forward();
                    return reforward;
                }
            } catch (ApplicationException e) {
                // TODO Auto-generated catch block
                e.printStackTrace();
            } catch (UnsupportedEncodingException e) {
                // TODO Auto-generated catch block
                e.printStackTrace();
            }
        }

        Row found = bible.findOne(look, new Object[]{});

        long startTime = System.currentTimeMillis();
        Pager pager = new Pager();
        pager.setPageSize(pageSize);
        pager.setCurrentPage(page);
        pager.setListSize(found.getFieldInfo("size").intValue());

        Field field;
        int next = pager.getStartIndex();// 此位置即为当前页的第一条记录的ID

        html.append("<ol class=\"searchresults\" start=\"" + next + "\">\r\n");

        String finded, word;
        Row row;
        Enumeration<Row> table = vtable.elements();
        int n = 0;
        while (table.hasMoreElements()) {
            row = table.nextElement();
            Iterator<Field> iterator = row.iterator();

            n++;
            while (iterator.hasNext()) {
                field = iterator.next();
                finded = field.get("content").value().toString();

                j = 0;
                while (j < keywords.length) {
                    finded = StringUtilities.sign(finded, keywords[j++]);
                }

                html.append("<li"
                        + (n % 2 == 0 ? " class=\"even\"" : " class=\"odd\"")
                        + "><a href=\""
                        + this.context.getAttribute("HTTP_HOST")
                        + "bible/"
                        + field.get("book_id").value().toString()
                        + "/"
                        + field.get("chapter_id").value().toString()
                        + "/"
                        + field.get("part_id").value().toString()
                        + "\" target=\"_blank\">"
                        + this.setText("search.bible.info", field.get("book_name").value()
                        .toString(), field.get("chapter_id").value().toString(), field
                        .get("part_id").value().toString()) + "</a><p>" + finded
                        + "</p></li> \r\n");
                next++;
            }
        }

        Table ktable;
        Row krow;
        while (k < keywords.length && noResult) {
            word = keywords[k++];
            keyword keyword = new keyword();
            keyword.setKeyword(word);
            ktable = keyword.setRequestFields("id,visit").findWith("WHERE keyword=?",
                    new Object[]{word});

            if (ktable.size() == 0) {
                keyword.setVisit(0);
                keyword.append();
            } else {
                krow = ktable.get(0);
                keyword.setId(krow.getFieldInfo("id").value());
                keyword.setVisit(krow.getFieldInfo("visit").intValue() + 1);
                keyword.update();
            }
        }
        html.append("</ol>\r\n");

        String actionURL = this.context.getAttribute("HTTP_HOST") + "bible/search/"
                + query + "&page";
        pager.setFirstPageText(this.getProperty("page.first.text"));
        pager.setLastPageText(this.getProperty("page.last.text"));
        pager.setCurrentPageText(this.getProperty("page.current.text"));
        pager.setNextPageText(this.getProperty("page.next.text"));
        pager.setEndPageText(this.getProperty("page.end.text"));
        pager.setControlBarText(this.getProperty("page.controlbar.text"));

        html.append("<div class=\"pagination\" style=\"cursor:default\">"
                + pager.getPageControlBar(actionURL) + "</div>\r\n");
        html.append("<!-- "
                + String.valueOf(System.currentTimeMillis() - startTime) + " -->");

        int start = page - 1 == 0 ? 1 : (page - 1) * pageSize + 1, end = page
                * pageSize;

        this.setVariable("start", String.valueOf(start));
        this.setVariable("end", String.valueOf(end));
        this.setVariable("size", String.valueOf(pager.getSize()));
        this.setVariable("value", html.toString());
        this.setVariable("action", this.config.get("default.base_url")
                + this.context.getAttribute("REQUEST_ACTION").toString());

        this.setText("search.info", start, end, query, pager.getSize());

        Session session = request.getSession();
        if (session.getAttribute("usr") != null) {
            this.usr = (User) session.getAttribute("usr");

            this.setVariable("user.status", "");
            this.setVariable("user.profile",
                    "<a href=\"javascript:void(0)\" onmousedown=\"profileMenu.show(event,'1')\">"
                            + this.usr.getEmail() + "</a>");
        } else {
            this.setVariable("user.status", "<a href=\"" + this.getLink("user/login")
                    + "\">" + this.getProperty("page.login.caption") + "</a>");
            this.setVariable("user.profile", "");
        }

        return this;
    }

    public String feed(String query) throws ApplicationException {
        StringBuffer xml = new StringBuffer();
        String finded = "";
        String[] keywords;
        boolean noResult = true;

        int page = 1, pageSize = 20, startIndex = (page - 1) * pageSize;
        if (this.request.getParameter("page") == null
                || this.request.getParameter("page").toString().trim().length() <= 0) {
            page = 1;
        } else {
            page = Integer.parseInt(this.request.getParameter("page").toString());
        }

        if (query.trim().length() > 0) {
            keywords = query.split(" ");
        } else {
            return "<result>Error</result>";
        }

        String condition = "";
        for (int i = 0; i < keywords.length; i++) {
            if (condition.trim().length() == 0) {
                condition = " content like '%" + keywords[i] + "%' ";
            } else {
                condition += " or content like '%" + keywords[i] + "%' ";
            }
        }

        String SQL = "SELECT a.*,b.book_name FROM bible as a left join book as b on a.book_id=b.book_id where "
                + condition
                + " order by a.book_id,a.chapter_id limit "
                + startIndex
                + "," + pageSize;
        // String look = "SELECT FOUND_ROWS() AS size";

        bible bible = new bible();
        Table vtable = bible.find(SQL, new Object[]{});
        noResult = vtable.size() > 0;

        /*
         * Row found=bible.findOne(look, new Object[]{});
         *
         * Pager pager = new Pager(); pager.pageSize = pageSize; pager.currentPage =
         * page; pager.size=found.getFieldInfo("size").intValue();
         * pager.setListSize(vtable.size());
         */

        Field field;
        int next = startIndex + 1;// 此位置即为当前页的第一条记录的ID

        for (Enumeration<Row> table = vtable.elements(); table.hasMoreElements(); ) {
            Row row = table.nextElement();
            Iterator<Field> iterator = row.iterator();

            while (iterator.hasNext()) {
                field = iterator.next();
                finded = field.get("content").value().toString();

                for (int j = 0; j < keywords.length; j++) {
                    finded = StringUtilities.sign(finded, keywords[j]);
                }

                xml.append("<item id=\"" + next + "\" chapterid=\""
                        + field.get("chapter_id").value().toString() + "\" bookid=\""
                        + field.get("book_id").value().toString() + "\" "
                        + field.get("book_name").value().toString() + " partid=\""
                        + field.get("part_id").value().toString() + "\">" + finded
                        + "</item>\r\n");
                next++;
            }
        }

        for (int k = 0; k < keywords.length && noResult; k++) {
            keyword keyword = new keyword();
            keyword.setKeyword(keywords[k]);
            Row findRow = keyword.findOne(
                    "SELECT id,visit FROM keyword WHERE keyword='" + keywords[k] + "'",
                    new Object[]{});

            if (findRow.size() == 0) {
                keyword.setVisit(0);
                keyword.append();
            } else {
                keyword.setId(findRow.getFieldInfo("id"));
                keyword.setVisit(findRow.getFieldInfo("visit").intValue() + 1);
                keyword.update();
            }
        }

        return xml.toString();
    }

    public Object advanced(String query) throws ApplicationException {
        if (query == null || query.trim().length() == 0) {
            return this;
        }
        query = StringUtilities.htmlSpecialChars(query);

        int page = 1, pageSize = 10, total = 0;
        this.request = (Request) this.context.getAttribute(HTTP_REQUEST);

        if (this.request.getParameter("page") == null
                || this.request.getParameter("page").toString().trim().length() == 0) {
            page = 1;
        } else {
            page = Integer.parseInt(this.request.getParameter("page").toString());
        }

        if (this.request.getParameter("amount") == null || this.request.getParameter("amount").toString().trim().length() == 0) {
            total = 1;
        } else {
            total = Integer.parseInt(this.request.getParameter("amount").toString());
        }

        long startTime = System.currentTimeMillis();
        Pager pager = new Pager();
        pager.setPageSize(pageSize);
        pager.setCurrentPage(page);
        pager.setListSize(total);

        if (query == null || query.length() > 0) {
            this.setVariable("keyword", "");
        } else {
            this.setVariable("keyword", query);
            this.setVariable("search.title", query + " - ");
        }

        Document document = this.execute(query, pager.getStartIndex());
        Element root = document.getRoot();
        List<Element> vtable = root.getElementsByTagName("entry");
        if (vtable.size() == 0) {
            this.setVariable("value", "Sorry, we could not get any related results with this keyword! " + StringUtilities.htmlSpecialChars(root.toString()));
            return this;
        }

        int n = 0, next, amount = Integer.parseInt(root.getElementsByTagName("opensearch:totalResults").get(0).getData());
        pager.setListSize(amount);

        next = pager.getStartIndex();// 此位置即为当前页的第一条记录的ID
        // opensearch:totalResults
        StringBuffer html = new StringBuffer();
        html.append("<ol class=\"searchresults\" start=\"" + next + "\">\r\n");

        Element element, title, link;
        List<Element> t;
        String summary;

        Iterator<Element> item = vtable.iterator();
        while (item.hasNext()) {
            element = item.next();
            n++;
            link = element.getElementsByTagName("id").get(0);
            title = element.getElementsByTagName("title").get(0);

            t = element.getElementsByTagName("cse:PageMap").get(0)
                    .getElementsByTagName("cse:DataObject");
            if (t.size() >= 3) {
                t = t.get(1).getElementsByTagName("cse:Attribute");
                summary = t.get(1).getAttribute("value");
            } else
                summary = element.getElementsByTagName("summary").get(0).getData();

            html.append("<li" + (n % 2 == 0 ? " class=\"even\"" : " class=\"odd\"")
                    + "><a href=\"" + link.getData() + "\" target=\"_blank\">"
                    + title.getData() + " </a><p>" + summary + "</p></li> \r\n");
            next++;
        }

        html.append("</ol>\r\n");

        String actionURL = this.context.getAttribute("HTTP_HOST") + "bible/advsearch/" + query + "&amount=" + amount + "&page";
        pager.setFirstPageText(this.getProperty("page.first.text"));
        pager.setLastPageText(this.getProperty("page.last.text"));
        pager.setCurrentPageText(this.getProperty("page.current.text"));
        pager.setNextPageText(this.getProperty("page.next.text"));
        pager.setEndPageText(this.getProperty("page.end.text"));
        pager.setControlBarText(this.getProperty("page.controlbar.text"));

        html.append("<div class=\"pagination\" style=\"cursor:default\">"
                + pager.getPageControlBar(actionURL) + "</div>\r\n");
        html.append("<!-- "
                + String.valueOf(System.currentTimeMillis() - startTime) + " -->");

        int start = page - 1 == 0 ? 1 : (page - 1) * pageSize + 1, end = page
                * pageSize;

        this.setVariable("start", String.valueOf(start));
        this.setVariable("end", String.valueOf(end));
        this.setVariable("size", String.valueOf(pager.getSize()));
        this.setVariable("value", html.toString());

        this.setText("search.info", start, end, query, pager.getSize());

        this.setVariable("action", String.valueOf(this.context.getAttribute("HTTP_HOST")) + this.context.getAttribute("REQUEST_ACTION").toString());

        Session session = request.getSession();
        if (session.getAttribute("usr") != null) {
            this.usr = (User) session.getAttribute("usr");

            this.setVariable("user.status", "");
            this.setVariable("user.profile",
                    "<a href=\"javascript:void(0)\" onmousedown=\"profileMenu.show(event,'1')\">"
                            + this.usr.getEmail() + "</a>");
        } else {
            this.setVariable("user.status", "<a href=\"" + this.getLink("user/login")
                    + "\">" + this.getProperty("page.login.caption") + "</a>");
            this.setVariable("user.profile", "");
        }

        return this;
    }

    protected String createRequestString(String query, int start)
            throws UnsupportedEncodingException {
        String encoded_query = URLEncoder.encode(query, "utf8");
        StringBuffer buffer = new StringBuffer();

        buffer.append("https://www.googleapis.com/customsearch/v1?");
        buffer.append("key=").append(API_KEY);
        buffer.append("&cx=").append(CUSTOM_SEARCH_ENGINE_ID);
        buffer.append("&q=").append(encoded_query);
        buffer.append("&alt=atom");
        buffer.append("&start=").append(start);

        return buffer.toString();
    }

    private Document execute(String query, int start) throws ApplicationException {
        HttpClient httpClient = new DefaultHttpClient();
        HttpGet httpget;
        try {
            httpget = new HttpGet(createRequestString(query, start == 0 ? 1 : start));
            httpClient.getParams().setParameter(HttpProtocolParams.HTTP_CONTENT_CHARSET, "UTF-8");

            HttpResponse response = httpClient.execute(httpget);
            InputStream instream = response.getEntity().getContent();

            Document document = new Document();
            document.load(instream);
            if (document.getRoot().getElementsByTagName("errors").size() > 0) {
                if (i++ > ids.length - 1) i = 0;

                CUSTOM_SEARCH_ENGINE_ID = ids[i];
                API_KEY = keys[i];

                httpget = new HttpGet(createRequestString(query, start == 0 ? 1 : start));

                response = httpClient.execute(httpget);
                instream = response.getEntity().getContent();

                document.load(instream);
            }

            return document;
        } catch (ClientProtocolException e) {
            throw new ApplicationException(e.getMessage(), e);
        } catch (IOException e) {
            throw new ApplicationException(e.getMessage(), e);
        } catch (ParseException e) {
            throw new ApplicationException(e.getMessage(), e);
        }

    }
}

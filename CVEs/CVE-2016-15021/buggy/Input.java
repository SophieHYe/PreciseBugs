package model;

import object.Region;
import util.DBManager;
import util.FormatManager;
import java.sql.ResultSet;
import java.util.ArrayList;
import javax.servlet.http.HttpServletRequest;

/**
 *
 * @author Nick
 */
public class Input {

    public static String query;
    public static String table;
    public static ArrayList<Region> regionList = new ArrayList<Region>();
    public static String idStr;

    public static void init(HttpServletRequest request) throws Exception {
        regionList.clear();
        idStr = "";
        table = "variantList";
        Output.rvisPercentile = null;

        query = request.getParameter("query").toUpperCase();

        if (query.split("-").length == 4) {
            idStr = query;
            table = "variant";
        } else if (query.contains(":")) {
            initRegionListByStr(query);
        } else {
            initRegionListByGeneName(query);

            initRvisByGene(query);
        }
    }

    private static void initRegionListByStr(String regionStr) {
        for (String str : regionStr.split(";")) {
            regionList.add(getRegionByStr(str));
        }
    }

    private static Region getRegionByStr(String str) {
        String[] tmp = str.split(":");
        String chr = tmp[0].replace("chr", "");

        tmp = tmp[1].split("-");
        int start = Integer.valueOf(tmp[0]);
        int end = Integer.valueOf(tmp[1]);

        return new Region(chr, start, end);
    }

    private static void initRegionListByGeneName(String geneName) throws Exception {
        String sql = "SELECT * "
                + "FROM gene_region "
                + "WHERE gene_name='" + geneName + "'";

        ResultSet rset = DBManager.executeQuery(sql);

        if (rset.next()) {
            query = rset.getString("gene_name");
            String regionStr = rset.getString("region");

            initRegionListByStr(regionStr);
        }

        rset.close();
    }

    private static void initRvisByGene(String geneName) throws Exception {
        String sql = "SELECT * "
                + "FROM rvis "
                + "WHERE gene_name='" + geneName + "'";

        ResultSet rset = DBManager.executeQuery(sql);

        if (rset.next()) {
            float f = FormatManager.getFloat(rset.getObject("rvis_percent"));

            String value = FormatManager.getString(f);

            if (value.equals("-")) {
                Output.rvisPercentile = "NA";
            }

            Output.rvisPercentile = value + "%";
        }

        rset.close();
    }
}

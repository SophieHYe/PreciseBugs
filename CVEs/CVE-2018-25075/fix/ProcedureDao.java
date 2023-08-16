/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2016 Ferenc Karsany
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 * the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 * FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 * IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 */

package org.obridge.dao;

import org.obridge.context.OBridgeConfiguration;
import org.obridge.model.data.OraclePackage;
import org.obridge.model.data.Procedure;
import org.obridge.model.data.ProcedureArgument;
import org.obridge.util.jdbc.JdbcTemplate;

import javax.sql.DataSource;
import java.util.List;

/**
 * User: fkarsany Date: 2013.11.18.
 */
public class ProcedureDao {

    private static final String GET_ALL_PROCEDURE =
            "Select object_name,\n" +
                    "       procedure_name,\n" +
                    "       overload,\n" +
                    "       (Select Count(*)\n" +
                    "          From user_arguments a\n" +
                    "         Where a.object_name = t.procedure_name\n" +
                    "           And a.package_name = t.object_name\n" +
                    "           And nvl(a.overload, '##NVL##') = nvl(t.overload, '##NVL##')\n" +
                    "           And a.argument_name Is Null\n" +
                    "           And a.data_level = 0\n" +
                    "           And a.data_type Is Not Null) proc_or_func\n" +
                    "  From user_procedures t\n" +
                    " Where procedure_name Is Not Null\n" +
                    "   And object_type = 'PACKAGE'\n" +
                    "   And object_name Like ?\n" +
                    "   And procedure_name Like ?\n" +
                    "   And Not ((object_name, procedure_name, nvl(overload, -1)) In\n" +
                    "        (Select package_name, object_name, nvl(overload, -1)\n" +
                    "               From user_arguments\n" +
                    "              Where data_type In ('PL/SQL TABLE')\n" +
                    "                 Or (data_type = 'REF CURSOR' And in_out Like '%IN%')\n" +
                    "                 Or (data_type = 'PL/SQL RECORD' " +
                    (OBridgeConfiguration.GENERATE_SOURCE_FOR_PLSQL_TYPES ? "And type_name Is Null" : "") +
                    ")))\n" +
                    "    Or procedure_name = 'ASSERT'";


    private static final String GET_ALL_SIMPLE_FUNCTION_AND_PROCEDURE =
            "Select object_name,\n" +
                    "       procedure_name,\n" +
                    "       overload,\n" +
                    "       (Select Count(*)\n" +
                    "          From user_arguments a\n" +
                    "         Where a.object_name = t.object_name\n" +
                    "           And a.package_name Is Null\n" +
                    "           And nvl(a.overload, '##NVL##') = nvl(t.overload, '##NVL##')\n" +
                    "           And a.argument_name Is Null\n" +
                    "           And a.data_level = 0\n" +
                    "           And a.data_type Is Not Null) proc_or_func\n" +
                    "  From user_procedures t\n" +
                    " Where procedure_name Is Null\n" +
                    "   And object_type In ('PROCEDURE', 'FUNCTION')\n" +
                    "   And Not ((object_name, procedure_name, nvl(overload, -1)) In\n" +
                    "        (Select object_name, package_name, nvl(overload, -1)\n" +
                    "               From user_arguments\n" +
                    "              Where data_type In ('PL/SQL TABLE')\n" +
                    "                 Or (data_type = 'REF CURSOR' And in_out Like '%IN%')\n" +
                    "                 Or (data_type = 'PL/SQL RECORD' " +
                    (OBridgeConfiguration.GENERATE_SOURCE_FOR_PLSQL_TYPES ? "And type_name Is Null" : "") +
                    ")))\n" +
                    "    Or procedure_name = 'ASSERT'";

    private static final String GET_PROCEDURE_ARGUMENTS = "  select argument_name," +
            "data_type," +
            "nvl( (select max(elem_type_name) from user_coll_types w where w.TYPE_NAME = p.type_name) , p.type_name || case when p.type_subname is not null then '_' || p.type_subname end) type_name," +
            "defaulted," +
            "in_out," +
            "rownum sequen, p.type_name orig_type_name " +
            "from (Select argument_name, data_type, type_name, type_subname, defaulted, in_out\n"
            + "        From user_arguments t\n"
            + "       Where nvl(t.package_name, '###') = nvl((?), '###')\n"
            + "         And t.object_name = (?)\n"
            + "         And nvl(t.overload, '###') = nvl(?, '###')\n"
            + "         And t.data_level = 0\n"
            + "         And not(pls_type is null and argument_name is null and data_type is null)"
            + "       Order By t.sequence) p\n";


    private JdbcTemplate jdbcTemplate;

    public ProcedureDao(DataSource dataSource) {
        jdbcTemplate = new JdbcTemplate(dataSource);
    }

    public List<Procedure> getAllProcedure(OBridgeConfiguration c) {
        List<Procedure> allProcedures = getAllProcedure(c.getPackagesLike(), "");
        allProcedures.addAll(getAllSimpleFunctionAndProcedure());

        return allProcedures;
    }

    public List<Procedure> getAllSimpleFunctionAndProcedure() {
        return jdbcTemplate.query(
                GET_ALL_SIMPLE_FUNCTION_AND_PROCEDURE,
                (resultSet, i) -> new Procedure.Builder()
                        .objectName("")
                        .procedureName(resultSet.getString("object_name"))
                        .overload(resultSet.getString("overload") == null ? "" : resultSet.getString("overload"))
                        .methodType(resultSet.getInt("proc_or_func") == 0 ? "PROCEDURE" : "FUNCTION")
                        .argumentList(getProcedureArguments("",
                                resultSet.getString("object_name"),
                                resultSet.getString("overload")))
                        .build()
        );

    }

    public List<Procedure> getAllProcedure(String packageName, String procedureName) {
        String packageNameFilter;
        String procedureNameFilter;

        if (packageName == null || packageName.isEmpty() || "".equals(packageName)) {
            packageNameFilter = "%";
        } else {
            packageNameFilter = packageName;
        }

        if (procedureName == null || procedureName.isEmpty() || "".equals(procedureName)) {
            procedureNameFilter = "%";
        } else {
            procedureNameFilter = procedureName;
        }


        return jdbcTemplate.query(
                GET_ALL_PROCEDURE,
                (resultSet, i) -> new Procedure.Builder()
                        .objectName(resultSet.getString("object_name"))
                        .procedureName(resultSet.getString("procedure_name"))
                        .overload(resultSet.getString("overload") == null ? "" : resultSet.getString("overload"))
                        .methodType(resultSet.getInt("proc_or_func") == 0 ? "PROCEDURE" : "FUNCTION")
                        .argumentList(getProcedureArguments(resultSet.getString("object_name"),
                                resultSet.getString("procedure_name"),
                                resultSet.getString("overload")))
                        .build(), packageNameFilter, procedureNameFilter
        );

    }

    public List<ProcedureArgument> getProcedureArguments(String packageName, String procedureName, String overLoadNo) {
        return jdbcTemplate.query(
                GET_PROCEDURE_ARGUMENTS,
                new Object[]{packageName, procedureName, overLoadNo}, (resultSet, i) -> new ProcedureArgument(
                        resultSet.getString("argument_name"),
                        resultSet.getString("data_type"),
                        resultSet.getString("type_name"),
                        resultSet.getString("in_out").contains("IN"),
                        resultSet.getString("in_out").contains("OUT"),
                        resultSet.getString("orig_type_name")
                )
        );

    }

    public List<OraclePackage> getAllPackages(OBridgeConfiguration c) {
        List<OraclePackage> allPackage = getAllRealOraclePackage(c);
        allPackage.add(getAllStandaloneProcedureAndFunction());
        return allPackage;
    }

    private OraclePackage getAllStandaloneProcedureAndFunction() {
        OraclePackage oraclePackage = new OraclePackage();
        oraclePackage.setName("PROCEDURES_AND_FUNCTIONS");
        oraclePackage.setProcedureList(getAllSimpleFunctionAndProcedure());
        return oraclePackage;
    }

    private List<OraclePackage> getAllRealOraclePackage(OBridgeConfiguration c) {
        String query = "select object_name from user_objects where object_type = 'PACKAGE' and object_name like ?";
        return jdbcTemplate.query(query, (resultSet, i) -> {
            OraclePackage p = new OraclePackage();
            p.setName(resultSet.getString("object_name"));
            p.setProcedureList(getAllProcedure(resultSet.getString("object_name"), ""));
            return p;
        }, c.getPackagesLike());
    }

}

/*!
 * Copyright 2002 - 2021 Webdetails, a Hitachi Vantara company.  All rights reserved.
 *
 * This software was developed by Webdetails and is provided under the terms
 * of the Mozilla Public License, Version 2.0, or any later version. You may not use
 * this file except in compliance with the license. If you need a copy of the license,
 * please go to  http://mozilla.org/MPL/2.0/. The Initial Developer is Webdetails.
 *
 * Software distributed under the Mozilla Public License is distributed on an "AS IS"
 * basis, WITHOUT WARRANTY OF ANY KIND, either express or  implied. Please refer to
 * the license for the specific language governing your rights and limitations.
 */

package pt.webdetails.cpf.packager;

import junit.framework.TestCase;
import org.junit.Before;
import org.junit.Test;
import org.mockito.invocation.InvocationOnMock;
import org.mockito.stubbing.Answer;
import pt.webdetails.cpf.context.api.IUrlProvider;
import pt.webdetails.cpf.packager.dependencies.CssMinifiedDependency;
import pt.webdetails.cpf.packager.dependencies.Dependency;
import pt.webdetails.cpf.packager.dependencies.JsMinifiedDependency;
import pt.webdetails.cpf.packager.dependencies.MapDependency;
import pt.webdetails.cpf.packager.origin.PathOrigin;
import pt.webdetails.cpf.repository.api.IContentAccessFactory;

import java.util.HashMap;
import java.util.Map;

import static org.mockito.Mockito.*;

public class DependenciesPackageTest extends TestCase {

  private static DependenciesPackage jsDepPackage;
  private static DependenciesPackage cssDepPackage;
  private static DependenciesPackage mapDepPackage;

  private static final String JS_PACKAGE_NAME = "js-package";
  private static final String CSS_PACKAGE_NAME = "css-package";
  private static final String MAP_FILE_NAME = "map-file.css.map";

  private static IContentAccessFactory mockFactory;
  private static IUrlProvider mockUrlProvider;
  private static PathOrigin mockPathOrigin;

  @Before
  protected void setUp() throws Exception {
    mockFactory = mock( IContentAccessFactory.class );
    mockUrlProvider = mock( IUrlProvider.class );
    mockPathOrigin = mock( PathOrigin.class );
    when( mockPathOrigin.getUrl( anyString(), any( IUrlProvider.class ) ) ).thenAnswer( new Answer<String>() {
      @Override
      public String answer( InvocationOnMock invocation ) throws Throwable {
        return (String) invocation.getArguments()[0];
      }
    } );

    jsDepPackage =
      new DependenciesPackage( JS_PACKAGE_NAME, DependenciesPackage.PackageType.JS, mockFactory, mockUrlProvider );

    cssDepPackage =
      new DependenciesPackage( CSS_PACKAGE_NAME, DependenciesPackage.PackageType.CSS, mockFactory, mockUrlProvider );

    mapDepPackage =
      new DependenciesPackage( MAP_FILE_NAME, DependenciesPackage.PackageType.MAP, mockFactory, mockUrlProvider );


  }

  @Test
  public void testRegisterFileDependency() {
    String[] fileNames = new String[]{"file1", "file2"};
    String[] fileVersions = new String[]{"v1", "v2"};
    String[] filePaths = new String[]{"path1", "path2"};

    for ( int i = 0; i < fileNames.length; i++ ) {
      assertTrue( jsDepPackage.registerFileDependency(
          fileNames[ i ] + ".js", fileVersions[ i ], mockPathOrigin, filePaths[ i ] ) );
      assertTrue( cssDepPackage.registerFileDependency(
          fileNames[ i ] + ".css", fileVersions[ i ], mockPathOrigin, filePaths[ i ] ) );
      assertTrue( mapDepPackage.registerFileDependency(
          fileNames[ i ] + ".css.map", fileVersions[ i ], mockPathOrigin, filePaths[ i ] ) );
    }

  }

  @Test
  public void testRegisterRawDependency() {
    String[] fileNames = new String[]{"file1", "file2"};
    String[] fileVersions = new String[]{"v1", "v2"};
    String[] fileContents = new String[]{"content1", "content2"};

    for ( int i = 0; i < fileNames.length; i++ ) {
      assertTrue( jsDepPackage.registerRawDependency(
          fileNames[ i ] + ".js", fileVersions[ i ], fileContents[ i ] ) );
      assertTrue( cssDepPackage.registerRawDependency(
          fileNames[ i ] + ".css", fileVersions[ i ], fileContents[ i ] ) );
      assertTrue( mapDepPackage.registerRawDependency(
          fileNames[ i ] + ".css.map", fileVersions[ i ], fileContents[ i ] ) );
    }

  }

  @Test
  public void testRegisterDependency() {
    String[] fileNames = new String[]{"file1", "file2"};
    Map<String, Dependency> registry = new HashMap<String, Dependency>();

    for ( int i = 0; i < fileNames.length; i++ ) {
      assertTrue( jsDepPackage.registerDependency(
          fileNames[ i ] + ".js", mock( JsMinifiedDependency.class ), registry ) );
      assertTrue( cssDepPackage.registerDependency(
          fileNames[ i ] + ".css", mock( CssMinifiedDependency.class ), registry ) );
      assertTrue( mapDepPackage.registerDependency(
          fileNames[ i ] + ".css.map", mock( MapDependency.class ), registry ) );
    }
    assertEquals( fileNames.length * 3, registry.size() );
  }


  @Test
  public void testGetDependencies() {


    String jsPackagedDeps = jsDepPackage.getDependencies( true ).trim();
    assertEquals( "<script language=\"javascript\" type=\"text/javascript\" src=\"/js/"
        + JS_PACKAGE_NAME + ".js\"></script>", jsPackagedDeps );

    String cssPackagedDeps = cssDepPackage.getDependencies( true ).trim();
    assertEquals( "<link href=\"/css/"
        + CSS_PACKAGE_NAME + ".css\" rel=\"stylesheet\" type=\"text/css\" />", cssPackagedDeps );

    String mapPackagedDeps = mapDepPackage.getDependencies( true ).trim();
    assertEquals( "", mapPackagedDeps );

    String[] filePaths = new String[]{"path1", "path2"};
    addFileDependencies( filePaths );

    String jsUnpackagedDeps = jsDepPackage.getDependencies( false ).replaceAll( "\n", "" ).replaceAll( "\t", "" );
    String jsUnpackedExpected = "";
    for ( int i = 0; i < filePaths.length; i++ ) {
      jsUnpackedExpected +=
        "<script language=\"javascript\" type=\"text/javascript\" src=\"" + filePaths[i] + "\"></script>";
    }
    assertEquals( jsUnpackedExpected, jsUnpackagedDeps );

    String cssUnpackagedDeps = cssDepPackage.getDependencies( false ).replaceAll( "\n", "" ).replaceAll( "\t", "" );
    String cssUnpackedExpected = "";
    for ( int i = 0; i < filePaths.length; i++ ) {
      cssUnpackedExpected +=
        "<link href=\"" + filePaths[i] + "\" rel=\"stylesheet\" type=\"text/css\" />";
    }
    assertEquals( cssUnpackedExpected, cssUnpackagedDeps );

    String mapUnpackagedDeps = mapDepPackage.getDependencies( false ).trim();
    assertEquals( "", mapUnpackagedDeps );

  }

  @Test
  public void testGetDefaultFilter() {
    StringFilter jsFilter = jsDepPackage.getDefaultFilter();
    StringFilter cssFilter = cssDepPackage.getDefaultFilter();
    StringFilter mapFilter = mapDepPackage.getDefaultFilter();

    assertEquals( "<script language=\"javascript\" type=\"text/javascript\" src=\"JS-FILTER\"></script>",
        jsFilter.filter( "JS-FILTER" ).trim() );
    assertEquals( "<link href=\"CSS-FILTER\" rel=\"stylesheet\" type=\"text/css\" />",
        cssFilter.filter( "CSS-FILTER" ).trim() );
    assertEquals( "", mapFilter.filter( "MAP-FILTER" ) );
  }

  @Test
  public void testGetDefaultFilterEscapesUntrustedBaseUrl() {
    StringFilter jsFilter = jsDepPackage.getDefaultFilter();
    StringFilter cssFilter = cssDepPackage.getDefaultFilter();
    StringFilter mapFilter = mapDepPackage.getDefaultFilter();

    String untrustedBaseUrl = "http://foo\"/";

    assertEquals( "<script language=\"javascript\" type=\"text/javascript\" src=\"http://foo&#34;/JS-FILTER\"></script>",
            jsFilter.filter( "JS-FILTER", untrustedBaseUrl ).trim() );

    assertEquals( "<link href=\"http://foo&#34;/CSS-FILTER\" rel=\"stylesheet\" type=\"text/css\" />",
            cssFilter.filter( "CSS-FILTER", untrustedBaseUrl ).trim() );

    assertEquals( "", mapFilter.filter( "MAP-FILTER", untrustedBaseUrl ) );
  }

  private static void addFileDependencies( String[] filePaths ) {
    String[] fileNames = new String[]{"file1", "file2"};
    String[] fileVersions = new String[]{"v1", "v2"};
    for ( int i = 0; i < fileNames.length; i++ ) {
      jsDepPackage.registerFileDependency( fileNames[ i ] + ".js", fileVersions[ i ], mockPathOrigin, filePaths[ i ] );
      cssDepPackage.registerFileDependency(
          fileNames[ i ] + ".css", fileVersions[ i ], mockPathOrigin, filePaths[ i ] );
      mapDepPackage.registerFileDependency(
          fileNames[ i ] + ".css.map", fileVersions[ i ], mockPathOrigin, filePaths[ i ] );
    }
  }




}

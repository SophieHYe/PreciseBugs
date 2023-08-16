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

import org.owasp.encoder.Encode;
import pt.webdetails.cpf.context.api.IUrlProvider;
import pt.webdetails.cpf.packager.dependencies.CssMinifiedDependency;
import pt.webdetails.cpf.packager.dependencies.Dependency;
import pt.webdetails.cpf.packager.dependencies.FileDependency;
import pt.webdetails.cpf.packager.dependencies.JsMinifiedDependency;
import pt.webdetails.cpf.packager.dependencies.MapDependency;
import pt.webdetails.cpf.packager.dependencies.PackagedFileDependency;
import pt.webdetails.cpf.packager.dependencies.SnippetDependency;
import pt.webdetails.cpf.packager.origin.PathOrigin;
import pt.webdetails.cpf.packager.origin.StaticSystemOrigin;
import pt.webdetails.cpf.repository.api.IContentAccessFactory;
import pt.webdetails.cpf.repository.api.IRWAccess;

import java.util.LinkedHashMap;
import java.util.Map;

/**
 * A set of css|js files that can be packaged into a single file.<br> Encompasses former functionality of
 * DependenciesEngine/Packager<br> will be made abstract, more specific subclasses
 */
public class DependenciesPackage {

  public enum PackagingMode {
    MINIFY, CONCATENATE
  }

  public enum PackageType {
    CSS, JS, MAP
  }

  private String name;
  private PackageType type;

  protected Map<String, FileDependency> fileDependencies;

  protected PackagedFileDependency packagedDependency;
  protected Object packagingLock = new Object();

  private Map<String, SnippetDependency> rawDependencies;

  protected IContentAccessFactory factory;
  protected IUrlProvider urlProvider;

  /**
   * @param name
   * @param type
   * @param factory
   */
  public DependenciesPackage( String name, PackageType type, IContentAccessFactory factory, IUrlProvider urlProvider ) {
    this.name = name;
    this.fileDependencies = new LinkedHashMap<>();
    this.rawDependencies = new LinkedHashMap<>();
    this.type = type;
    this.factory = factory;
    this.urlProvider = urlProvider;
  }

  /**
   * Registers a dependency in this package
   *
   * @param name
   * @param version
   * @param origin
   * @param path
   * @return
   */
  public boolean registerFileDependency( String name, String version, PathOrigin origin, String path ) {
    final FileDependency dependency = new FileDependency( version, origin, path, this.urlProvider );

    synchronized ( this.packagingLock ) {
      if ( registerDependency( name, dependency, this.fileDependencies ) ) {
        //invalidate packaged if there
        this.packagedDependency = null;

        return true;
      }
    }

    return false;
  }

  public boolean registerRawDependency( String name, String version, String contents ) {
    final SnippetDependency snip = new SnippetDependency( version, contents );

    return registerDependency( name, snip, this.rawDependencies );
  }

  protected <T extends Dependency> boolean registerDependency( String name, T dependency, Map<String, T> registry ) {
    final Dependency dep = registry.get( name );
    if ( dep == null || dep.isOlderVersionThan( dependency ) ) {
      registry.put( name, dependency );

      return true;
    }

    return false;
  }

  /**
   * Get references to the dependencies with customized output.
   *
   * @param format     receives file path strings
   * @param isPackaged if to return a single compressed file
   * @return script or link tag with file references
   */
  public String getDependencies( StringFilter format, boolean isPackaged ) {
    return isPackaged ? getPackagedDependency( format, null ) : getUnpackagedDependencies( format, null );
  }

  public String getRawDependencies( boolean isPackaged ) {
    StringBuilder sb = new StringBuilder();

    for ( SnippetDependency dep : rawDependencies.values() ) {
      sb.append( dep.getContents() );
      sb.append( '\n' );
    }

    return sb.toString();
  }

  /**
   * Get references to the dependencies.
   *
   * @param isPackaged if to return a single compressed file
   * @return script or link tag with file references
   */
  public String getDependencies( boolean isPackaged ) {
    return getDependencies( getDefaultStringFilter( type ), isPackaged );
  }

  public String getName() {
    return name;
  }

  /**
   * Get references to the dependencies according to files.
   *
   * @param isPackaged if to return a single compressed file
   * @param filter     used for validating the dependency files to be include
   * @return script or link tag with file references
   */
  public String getDependencies( boolean isPackaged, IDependencyInclusionFilter filter ) {
    return getDependencies( getDefaultStringFilter( type ), isPackaged, filter );
  }

  /**
   * Get references to the dependencies that match the values of files with customized output.
   *
   * @param format     receives file path strings
   * @param isPackaged if to return a single compressed file
   * @param filter     used for validating the dependency files to be include
   * @return script or link tag with file references
   */
  public String getDependencies( StringFilter format, boolean isPackaged, IDependencyInclusionFilter filter ) {
    return isPackaged ? getPackagedDependency( format, filter ) : getUnpackagedDependencies( format, filter );
  }

  public String getUnpackagedDependencies( StringFilter format, IDependencyInclusionFilter filter ) {
    StringBuilder sb = new StringBuilder( "\n" );

    if ( filter != null ) {
      // return dashboard component dependencies
      for ( FileDependency dep : fileDependencies.values() ) {
        if ( filter.include( dep ) ) {
          sb.append( format.filter( dep.getDependencyInclude() ) );
        }
      }
    } else {
      // return all dependencies
      for ( Dependency dep : fileDependencies.values() ) {
        sb.append( format.filter( dep.getDependencyInclude() ) );
      }
    }

    return sb.toString();
  }

  protected String getPackagedDependency( StringFilter format, IDependencyInclusionFilter filter ) {
    boolean isMap = type.equals( PackageType.MAP );
    if ( filter != null ) {
      // return minified dashboard component dependencies
      Map<String, FileDependency> customDependencies = new LinkedHashMap<>();
      for ( FileDependency dep : fileDependencies.values() ) {
        if ( filter.include( dep ) ) {
          customDependencies.put( dep.getDependencyInclude(), dep );
        }
      }

      String packagedPath = isMap ? name : name + "." + type.toString().toLowerCase();
      String baseDir = type.toString().toLowerCase();

      IRWAccess writer = factory.getPluginSystemWriter( baseDir );
      PathOrigin origin = new StaticSystemOrigin( baseDir );

      switch ( type ) {
        case CSS:
          return format.filter(
            new CssMinifiedDependency( origin, packagedPath, writer, customDependencies.values(), urlProvider )
              .getDependencyInclude() );
        case JS:
          return format.filter(
            new JsMinifiedDependency( origin, packagedPath, writer, customDependencies.values(), urlProvider )
              .getDependencyInclude() );
        case MAP:
          return format.filter( new MapDependency( origin, packagedPath, writer, customDependencies.values(),
            urlProvider ).getDependencyInclude() );
        default:
          throw new IllegalStateException( getClass().getSimpleName() + " does not have a recognized type: " + type );
      }
    } else {
      // set packagedDependency if null and/or return all dependencies minified
      synchronized ( packagingLock ) {
        if ( packagedDependency == null ) {
          String packagedPath = isMap ? name : name + "." + type.toString().toLowerCase();
          String baseDir = isMap ? "css" : type.toString().toLowerCase();

          IRWAccess writer = factory.getPluginSystemWriter( baseDir );
          PathOrigin origin = new StaticSystemOrigin( baseDir );

          switch ( type ) {
            case CSS:
              packagedDependency =
                new CssMinifiedDependency( origin, packagedPath, writer, fileDependencies.values(), urlProvider );
              break;
            case JS:
              packagedDependency =
                new JsMinifiedDependency( origin, packagedPath, writer, fileDependencies.values(), urlProvider );
              break;
            case MAP:
              packagedDependency =
                new MapDependency( origin, name, writer, fileDependencies.values(), urlProvider );
              break;
            default:
              throw new IllegalStateException(
                getClass().getSimpleName() + " does not have a recognized type: " + type );
          }
        }

        return format.filter( packagedDependency.getDependencyInclude() );
      }
    }
  }

  public PackageType getType() {
    return type;
  }

  public interface IDependencyInclusionFilter {
    public boolean include( Dependency dependency );
  }

  public StringFilter getDefaultFilter() {
    return getDefaultStringFilter( this.type );
  }

  private static StringFilter getDefaultStringFilter( PackageType type ) {
    switch ( type ) {
      case CSS:
        return new StringFilter() {
          public String filter( String input ) {
            return filter( input, "" );
          }

          public String filter( String input, String baseUrl ) {
            baseUrl = Encode.forHtmlAttribute( baseUrl );
            return String.format(
              "\t\t<link href=\"%s%s\" rel=\"stylesheet\" type=\"text/css\" />\n",
              baseUrl, baseUrl.endsWith( "/" ) && input.startsWith( "/" ) ? input.replaceFirst( "/", "" ) : input );
          }
        };
      case JS:
        return new StringFilter() {
          public String filter( String input ) {
            return filter( input, "" );
          }

          public String filter( String input, String baseUrl ) {
            baseUrl = Encode.forHtmlAttribute( baseUrl );
            return String.format(
              "\t\t<script language=\"javascript\" type=\"text/javascript\" src=\"%s%s\"></script>\n",
              baseUrl, baseUrl.endsWith( "/" ) && input.startsWith( "/" ) ? input.replaceFirst( "/", "" ) : input );
          }
        };
      case MAP:
        return new StringFilter() {
          @Override
          public String filter( String input ) {
            return "";
          }

          @Override
          public String filter( String input, String absRoot ) {
            return "";
          }
        };
      default:
        return new StringFilter() {
          public String filter( String input ) {
            return filter( input, "" );
          }

          public String filter( String input, String baseUrl ) {
            return Encode.forHtmlAttribute( baseUrl ) + input + "\n";
          }
        };
    }
  }

}

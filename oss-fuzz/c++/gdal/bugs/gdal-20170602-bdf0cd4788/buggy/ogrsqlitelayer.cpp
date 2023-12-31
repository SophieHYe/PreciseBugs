/******************************************************************************
 *
 * Project:  OpenGIS Simple Features Reference Implementation
 * Purpose:  Implements OGRSQLiteLayer class, code shared between
 *           the direct table access, and the generic SQL results.
 * Author:   Frank Warmerdam, warmerdam@pobox.com
 *
 ******************************************************************************
 *
 * Contributor: Alessandro Furieri, a.furieri@lqt.it
 * Portions of this module supporting SpatiaLite's own 3D geometries
 * [XY, XYM, XYZ and XYZM] available since v.2.4.0
 * Developed for Faunalia ( http://www.faunalia.it) with funding from
 * Regione Toscana - Settore SISTEMA INFORMATIVO TERRITORIALE ED AMBIENTALE
 *
 ******************************************************************************
 * Copyright (c) 2004, Frank Warmerdam <warmerdam@pobox.com>
 * Copyright (c) 2009-2013, Even Rouault <even dot rouault at mines-paris dot org>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 ****************************************************************************/

#include "cpl_conv.h"
#include "cpl_string.h"
#include "ogr_sqlite.h"
#include "ogrsqliteutility.h"
#include <cassert>

CPL_CVSID("$Id$");

/************************************************************************/
/*                           OGRSQLiteLayer()                           */
/************************************************************************/

OGRSQLiteLayer::OGRSQLiteLayer() :
    poFeatureDefn(NULL),
    iNextShapeId(0),
    hStmt(NULL),
    bDoStep(TRUE),
    poDS(NULL),
    pszFIDColumn(NULL),
    panFieldOrdinals(NULL),
    iFIDCol(-1),
    iOGRNativeDataCol(-1),
    iOGRNativeMediaTypeCol(-1),
    bIsVirtualShape(FALSE),
    bUseComprGeom(CPLTestBool(CPLGetConfigOption("COMPRESS_GEOM", "FALSE"))),
    papszCompressedColumns(NULL),
    bAllowMultipleGeomFields(FALSE)
{}

/************************************************************************/
/*                          ~OGRSQLiteLayer()                           */
/************************************************************************/

OGRSQLiteLayer::~OGRSQLiteLayer()

{
    Finalize();
}

/************************************************************************/
/*                               Finalize()                             */
/************************************************************************/

void OGRSQLiteLayer::Finalize()
{
    /* Caution: this function can be called several times (see */
    /* OGRSQLiteExecuteSQLLayer::~OGRSQLiteExecuteSQLLayer()), so it must */
    /* be a no-op on second call */

    if( m_nFeaturesRead > 0 && poFeatureDefn != NULL )
    {
        CPLDebug( "SQLite", "%d features read on layer '%s'.",
                  (int) m_nFeaturesRead,
                  poFeatureDefn->GetName() );
    }

    if( hStmt != NULL )
    {
        sqlite3_finalize( hStmt );
        hStmt = NULL;
    }

    if( poFeatureDefn != NULL )
    {
        poFeatureDefn->Release();
        poFeatureDefn = NULL;
    }

    CPLFree( pszFIDColumn );
    pszFIDColumn = NULL;
    CPLFree( panFieldOrdinals );
    panFieldOrdinals = NULL;

    CSLDestroy(papszCompressedColumns);
    papszCompressedColumns = NULL;
}

/************************************************************************/
/*                          OGRIsBinaryGeomCol()                        */
/************************************************************************/

static
int OGRIsBinaryGeomCol( sqlite3_stmt *hStmt,
                        int iCol,
                        CPL_UNUSED OGRFieldDefn& oField,
                        OGRSQLiteGeomFormat& eGeomFormat )
{
    OGRGeometry* poGeometry = NULL;
    const int nBytes = sqlite3_column_bytes( hStmt, iCol );
    // coverity[tainted_data_return]
    GByte* pabyBlob = (GByte*)sqlite3_column_blob( hStmt, iCol );
    int nBytesConsumed = 0;
    CPLPushErrorHandler(CPLQuietErrorHandler);
    /* Try as spatialite first since createFromWkb() can sometimes */
    /* interpret spatialite blobs as WKB for certain SRID values */
    if( OGRSQLiteLayer::ImportSpatiaLiteGeometry(
            pabyBlob, nBytes,
            &poGeometry ) == OGRERR_NONE )
    {
        eGeomFormat = OSGF_SpatiaLite;
    }
    else if( OGRGeometryFactory::createFromWkb(
            pabyBlob,
            NULL, &poGeometry, nBytes ) == OGRERR_NONE )
    {
        eGeomFormat = OSGF_WKB;
    }
    else if( OGRGeometryFactory::createFromFgf(
            pabyBlob,
            NULL, &poGeometry, nBytes, &nBytesConsumed ) == OGRERR_NONE &&
             nBytes == nBytesConsumed )
    {
        eGeomFormat = OSGF_FGF;
    }
    CPLPopErrorHandler();
    CPLErrorReset();
    delete poGeometry;
    if( eGeomFormat != OSGF_None )
    {
        return TRUE;
    }
    return FALSE;
}

/************************************************************************/
/*                          BuildFeatureDefn()                          */
/*                                                                      */
/*      Build feature definition from a set of column definitions       */
/*      set on a statement.  Sift out geometry and FID fields.          */
/************************************************************************/

void OGRSQLiteLayer::BuildFeatureDefn( const char *pszLayerName,
                                       sqlite3_stmt *hStmtIn,
                                       const std::set<CPLString>* paosGeomCols,
                                       const std::set<CPLString>& aosIgnoredCols )

{
    poFeatureDefn = new OGRSQLiteFeatureDefn( pszLayerName );
    poFeatureDefn->SetGeomType(wkbNone);
    poFeatureDefn->Reference();

    const int nRawColumns = sqlite3_column_count( hStmtIn );

    panFieldOrdinals = (int *) CPLMalloc( sizeof(int) * nRawColumns );

    for( int iCol = 0; iCol < nRawColumns; iCol++ )
    {
        OGRFieldDefn    oField( SQLUnescape(sqlite3_column_name( hStmtIn, iCol )),
                                OFTString );

        // In some cases, particularly when there is a real name for
        // the primary key/_rowid_ column we will end up getting the
        // primary key column appearing twice.  Ignore any repeated names.
        if( poFeatureDefn->GetFieldIndex( oField.GetNameRef() ) != -1 )
            continue;

        if( EQUAL(oField.GetNameRef(), "OGR_NATIVE_DATA") )
        {
            iOGRNativeDataCol = iCol;
            continue;
        }

        if( EQUAL(oField.GetNameRef(), "OGR_NATIVE_MEDIA_TYPE") )
        {
            iOGRNativeMediaTypeCol = iCol;
            continue;
        }

        /* In the case of Spatialite VirtualShape, the PKUID */
        /* should be considered as a primary key */
        if( bIsVirtualShape && EQUAL(oField.GetNameRef(), "PKUID") )
        {
            CPLFree(pszFIDColumn);
            pszFIDColumn = CPLStrdup(oField.GetNameRef());
        }

        if( pszFIDColumn != NULL && EQUAL(pszFIDColumn, oField.GetNameRef()))
            continue;

        // oField.SetWidth( std::max(0,poStmt->GetColSize( iCol )) );

        if( aosIgnoredCols.find( CPLString(oField.GetNameRef()).tolower() ) != aosIgnoredCols.end() )
        {
            continue;
        }
        if( paosGeomCols != NULL &&
            paosGeomCols->find( CPLString(oField.GetNameRef()).tolower() ) != paosGeomCols->end() )
        {
            OGRSQLiteGeomFieldDefn* poGeomFieldDefn =
                new OGRSQLiteGeomFieldDefn(oField.GetNameRef(), iCol);
            poFeatureDefn->AddGeomFieldDefn(poGeomFieldDefn, FALSE);
            continue;
        }

        int nColType = sqlite3_column_type( hStmtIn, iCol );
        switch( nColType )
        {
          case SQLITE_INTEGER:
            if( CPLTestBool(CPLGetConfigOption("OGR_PROMOTE_TO_INTEGER64", "FALSE")) )
                oField.SetType( OFTInteger64 );
            else
            {
                GIntBig nVal = sqlite3_column_int64(hStmtIn, iCol);
                if( CPL_INT64_FITS_ON_INT32(nVal) )
                    oField.SetType( OFTInteger );
                else
                    oField.SetType( OFTInteger64 );
            }
            break;

          case SQLITE_FLOAT:
            oField.SetType( OFTReal );
            break;

          case SQLITE_BLOB:
            oField.SetType( OFTBinary );
            break;

          default:
            /* leave it as OFTString */;
        }

        const char * pszDeclType = sqlite3_column_decltype(hStmtIn, iCol);
        //CPLDebug("SQLITE", "decltype(%s) = %s",
        //         oField.GetNameRef(), pszDeclType ? pszDeclType : "null");
        OGRFieldType eFieldType = OFTString;
        if (pszDeclType != NULL)
        {
            if (EQUAL(pszDeclType, "INTEGER_BOOLEAN"))
            {
                oField.SetType(OFTInteger);
                oField.SetSubType(OFSTBoolean);
            }
            else if (EQUAL(pszDeclType, "INTEGER_INT16"))
            {
                oField.SetType(OFTInteger);
                oField.SetSubType(OFSTInt16);
            }
            else if (EQUAL(pszDeclType, "JSONINTEGERLIST"))
            {
                oField.SetType(OFTIntegerList);
            }
            else if (EQUAL(pszDeclType, "JSONINTEGER64LIST"))
            {
                oField.SetType(OFTInteger64List);
            }
            else if (EQUAL(pszDeclType, "JSONREALLIST"))
            {
                oField.SetType(OFTRealList);
            }
            else if (EQUAL(pszDeclType, "JSONSTRINGLIST"))
            {
                oField.SetType(OFTStringList);
            }
            else if (EQUAL(pszDeclType, "BIGINT") || EQUAL(pszDeclType, "INT8"))
            {
                oField.SetType(OFTInteger64);
            }
            else if (STARTS_WITH_CI(pszDeclType, "INTEGER"))
            {
                oField.SetType(OFTInteger);
            }
            else if (EQUAL(pszDeclType, "FLOAT_FLOAT32"))
            {
                oField.SetType(OFTReal);
                oField.SetSubType(OFSTFloat32);
            }
            else if (EQUAL(pszDeclType, "FLOAT") ||
                     EQUAL(pszDeclType, "DECIMAL"))
            {
                oField.SetType(OFTReal);
            }
            else if (STARTS_WITH_CI(pszDeclType, "BLOB"))
            {
                oField.SetType( OFTBinary );
                /* Parse format like BLOB_POINT_25D_4326 created by */
                /* OGRSQLiteExecuteSQL() */
                if( pszDeclType[4] == '_' )
                {
                    char* pszDeclTypeDup = CPLStrdup(pszDeclType);
                    char* pszNextUnderscore = strchr(pszDeclTypeDup + 5, '_');
                    const char* pszGeomType = pszDeclTypeDup + 5;
                    if( pszNextUnderscore != NULL )
                    {
                        *pszNextUnderscore = '\0';
                        pszNextUnderscore ++;
                        int nSRID = -1;
                        const char* pszCoordDimension = pszNextUnderscore;
                        pszNextUnderscore = strchr(pszNextUnderscore, '_');
                        if( pszNextUnderscore != NULL )
                        {
                            *pszNextUnderscore = '\0';
                            pszNextUnderscore ++;
                            const char* pszSRID = pszNextUnderscore;
                            nSRID = atoi(pszSRID);
                        }

                        OGRwkbGeometryType eGeomType =
                            OGRFromOGCGeomType(pszGeomType);
                        if( EQUAL(pszCoordDimension, "XYZ") )
                            eGeomType = wkbSetZ(eGeomType);
                        else if( EQUAL(pszCoordDimension, "XYM") )
                            eGeomType = wkbSetM(eGeomType);
                        else if( EQUAL(pszCoordDimension, "XYZM") )
                            eGeomType = wkbSetM(wkbSetZ(eGeomType));
                        OGRSpatialReference* poSRS = poDS->FetchSRS(nSRID);
                        OGRSQLiteGeomFieldDefn* poGeomFieldDefn =
                            new OGRSQLiteGeomFieldDefn(oField.GetNameRef(), iCol);
                        poGeomFieldDefn->eGeomFormat = OSGF_SpatiaLite;
                        poGeomFieldDefn->SetSpatialRef(poSRS);
                        poGeomFieldDefn->SetType(eGeomType);
                        poFeatureDefn->AddGeomFieldDefn(poGeomFieldDefn, FALSE);
                        CPLFree(pszDeclTypeDup);
                        continue;
                    }
                    CPLFree(pszDeclTypeDup);
                }
            }
            else if (EQUAL(pszDeclType, "TEXT") ||
                     STARTS_WITH_CI(pszDeclType, "VARCHAR"))
            {
                oField.SetType( OFTString );
                if( strstr(pszDeclType, "_deflate") != NULL )
                {
                    if( CSLFindString(papszCompressedColumns,
                                      oField.GetNameRef()) < 0 )
                    {
                        papszCompressedColumns = CSLAddString(
                            papszCompressedColumns, oField.GetNameRef());
                        CPLDebug("SQLITE", "%s is compressed", oField.GetNameRef());
                    }
                }
            }
            else if ((EQUAL(pszDeclType, "TIMESTAMP") ||
                      EQUAL(pszDeclType, "DATETIME")) &&
                     (nColType == SQLITE_TEXT || nColType == SQLITE_FLOAT || nColType == SQLITE_NULL))
                eFieldType = OFTDateTime;
            else if (EQUAL(pszDeclType, "DATE") &&
                     (nColType == SQLITE_TEXT || nColType == SQLITE_FLOAT || nColType == SQLITE_NULL))
                eFieldType = OFTDate;
            else if (EQUAL(pszDeclType, "TIME") &&
                     (nColType == SQLITE_TEXT || nColType == SQLITE_FLOAT || nColType == SQLITE_NULL))
                eFieldType = OFTTime;
        }
        else if( nColType == SQLITE_TEXT &&
                 (STARTS_WITH_CI(oField.GetNameRef(), "MIN(") ||
                  STARTS_WITH_CI(oField.GetNameRef(), "MAX(")) &&
                 sqlite3_column_text( hStmtIn, iCol ) != NULL )
        {
            int nRet = OGRSQLITEStringToDateTimeField(NULL, 0,
                              (const char*)sqlite3_column_text( hStmtIn, iCol ));
            if( nRet > 0 )
                eFieldType = (OGRFieldType) nRet;
        }

        // Recognise some common geometry column names.
        if( paosGeomCols == NULL &&
            (EQUAL(oField.GetNameRef(),"wkt_geometry")
             || EQUAL(oField.GetNameRef(),"geometry")
             || STARTS_WITH_CI(oField.GetNameRef(), "asbinary(")
             || STARTS_WITH_CI(oField.GetNameRef(), "astext(")
             || (STARTS_WITH_CI(oField.GetNameRef(), "st_") && nColType == SQLITE_BLOB ) )
            && (bAllowMultipleGeomFields || poFeatureDefn->GetGeomFieldCount() == 0) )
        {
            if( nColType == SQLITE_BLOB )
            {
                const int nBytes = sqlite3_column_bytes( hStmtIn, iCol );
                if( nBytes > 0 )
                {
                    OGRSQLiteGeomFormat eGeomFormat = OSGF_None;
                    if( OGRIsBinaryGeomCol( hStmtIn, iCol, oField, eGeomFormat ) )
                    {
                        OGRSQLiteGeomFieldDefn* poGeomFieldDefn =
                            new OGRSQLiteGeomFieldDefn(oField.GetNameRef(), iCol);
                        poGeomFieldDefn->eGeomFormat = eGeomFormat;
                        poFeatureDefn->AddGeomFieldDefn(poGeomFieldDefn, FALSE);
                        continue;
                    }
                }
                else
                {
                    /* This could also be a SpatialLite geometry, so */
                    /* we'll also try to decode as SpatialLite if */
                    /* bTriedAsSpatiaLite is not FALSE */
                    OGRSQLiteGeomFieldDefn* poGeomFieldDefn =
                        new OGRSQLiteGeomFieldDefn(oField.GetNameRef(), iCol);
                    poGeomFieldDefn->eGeomFormat = OSGF_WKB;
                    poFeatureDefn->AddGeomFieldDefn(poGeomFieldDefn, FALSE);
                    continue;
                }
            }
            else if( nColType == SQLITE_TEXT )
            {
                char* pszText = (char*) sqlite3_column_text( hStmtIn, iCol );
                if( pszText != NULL )
                {
                    OGRSQLiteGeomFormat eGeomFormat = OSGF_None;
                    CPLPushErrorHandler(CPLQuietErrorHandler);
                    OGRGeometry* poGeometry = NULL;
                    if( OGRGeometryFactory::createFromWkt(
                        &pszText, NULL, &poGeometry ) == OGRERR_NONE )
                    {
                        eGeomFormat = OSGF_WKT;
                        OGRSQLiteGeomFieldDefn* poGeomFieldDefn =
                            new OGRSQLiteGeomFieldDefn(oField.GetNameRef(), iCol);
                        poGeomFieldDefn->eGeomFormat = eGeomFormat;
                        poFeatureDefn->AddGeomFieldDefn(poGeomFieldDefn, FALSE);
                    }
                    CPLPopErrorHandler();
                    CPLErrorReset();
                    delete poGeometry;
                    if( eGeomFormat != OSGF_None )
                        continue;
                }
                else
                {
                    OGRSQLiteGeomFieldDefn* poGeomFieldDefn =
                        new OGRSQLiteGeomFieldDefn(oField.GetNameRef(), iCol);
                    poGeomFieldDefn->eGeomFormat = OSGF_WKT;
                    poFeatureDefn->AddGeomFieldDefn(poGeomFieldDefn, FALSE);
                    continue;
                }
            }
        }

        // SpatialLite / Gaia
        if( paosGeomCols == NULL &&
            EQUAL(oField.GetNameRef(),"GaiaGeometry")
            && (bAllowMultipleGeomFields || poFeatureDefn->GetGeomFieldCount() == 0) )
        {
            OGRSQLiteGeomFieldDefn* poGeomFieldDefn =
                new OGRSQLiteGeomFieldDefn(oField.GetNameRef(), iCol);
            poGeomFieldDefn->eGeomFormat = OSGF_SpatiaLite;
            poFeatureDefn->AddGeomFieldDefn(poGeomFieldDefn, FALSE);
            continue;
        }

        // Recognize a geometry column from trying to build the geometry
        // Useful for OGRSQLiteSelectLayer
        if( paosGeomCols == NULL &&
            nColType == SQLITE_BLOB &&
            (bAllowMultipleGeomFields || poFeatureDefn->GetGeomFieldCount() == 0) )
        {
            const int nBytes = sqlite3_column_bytes( hStmtIn, iCol );
            OGRSQLiteGeomFormat eGeomFormat = OSGF_None;
            if( nBytes > 0 && OGRIsBinaryGeomCol( hStmtIn, iCol, oField,
                                                  eGeomFormat ) )
            {
                OGRSQLiteGeomFieldDefn* poGeomFieldDefn =
                    new OGRSQLiteGeomFieldDefn(oField.GetNameRef(), iCol);
                poGeomFieldDefn->eGeomFormat = eGeomFormat;
                poFeatureDefn->AddGeomFieldDefn(poGeomFieldDefn, FALSE);
                continue;
            }
        }

        // The rowid is for internal use, not a real column.
        if( EQUAL(oField.GetNameRef(),"_rowid_") )
            continue;

        // The OGC_FID is for internal use, not a real user visible column.
        if( EQUAL(oField.GetNameRef(),"OGC_FID") )
            continue;

        /* Config option just in case we would not want that in some cases */
        if( (eFieldType == OFTTime || eFieldType == OFTDate ||
             eFieldType == OFTDateTime) &&
            CPLTestBool(
                CPLGetConfigOption("OGR_SQLITE_ENABLE_DATETIME", "YES")) )
        {
            oField.SetType( eFieldType );
        }

        poFeatureDefn->AddFieldDefn( &oField );
        panFieldOrdinals[poFeatureDefn->GetFieldCount() - 1] = iCol;
    }

    if( pszFIDColumn != NULL )
    {
        for( int iCol = 0; iCol < nRawColumns; iCol++ )
        {
            if( EQUAL(SQLUnescape(sqlite3_column_name(hStmtIn,iCol)).c_str(),
                      pszFIDColumn) )
            {
                iFIDCol = iCol;
                break;
            }
        }
    }
}

/************************************************************************/
/*                            GetFIDColumn()                            */
/************************************************************************/

const char *OGRSQLiteLayer::GetFIDColumn()

{
    GetLayerDefn();
    if( pszFIDColumn != NULL )
        return pszFIDColumn;
    else
        return "";
}

/************************************************************************/
/*                            ResetReading()                            */
/************************************************************************/

void OGRSQLiteLayer::ResetReading()

{
    ClearStatement();
    iNextShapeId = 0;
}

/************************************************************************/
/*                           GetNextFeature()                           */
/************************************************************************/

OGRFeature *OGRSQLiteLayer::GetNextFeature()

{
    while( true )
    {
        OGRFeature *poFeature = GetNextRawFeature();
        if( poFeature == NULL )
            return NULL;

        if( (m_poFilterGeom == NULL
            || FilterGeometry( poFeature->GetGeomFieldRef(m_iGeomFieldFilter) ) )
            && (m_poAttrQuery == NULL
                || m_poAttrQuery->Evaluate( poFeature )) )
            return poFeature;

        delete poFeature;
    }
}

/************************************************************************/
/*                         GetNextRawFeature()                          */
/************************************************************************/

OGRFeature *OGRSQLiteLayer::GetNextRawFeature()

{
    if( hStmt == NULL )
    {
        ResetStatement();
        if (hStmt == NULL)
            return NULL;
    }

/* -------------------------------------------------------------------- */
/*      Fetch a record (unless otherwise instructed)                    */
/* -------------------------------------------------------------------- */
    if( bDoStep )
    {
        const int rc = sqlite3_step( hStmt );
        if( rc != SQLITE_ROW )
        {
            if ( rc != SQLITE_DONE )
            {
                sqlite3_reset(hStmt);
                CPLError( CE_Failure, CPLE_AppDefined,
                        "In GetNextRawFeature(): sqlite3_step() : %s",
                        sqlite3_errmsg(poDS->GetDB()) );
            }

            ClearStatement();

            return NULL;
        }
    }
    else
    {
        bDoStep = TRUE;
    }

/* -------------------------------------------------------------------- */
/*      Create a feature from the current result.                       */
/* -------------------------------------------------------------------- */
    OGRFeature *poFeature = new OGRFeature( poFeatureDefn );

/* -------------------------------------------------------------------- */
/*      Set FID if we have a column to set it from.                     */
/* -------------------------------------------------------------------- */
    if( iFIDCol >= 0 )
        poFeature->SetFID( sqlite3_column_int64( hStmt, iFIDCol ) );
    else
        poFeature->SetFID( iNextShapeId );

    iNextShapeId++;

    m_nFeaturesRead++;

/* -------------------------------------------------------------------- */
/*      Process Geometry if we have a column.                           */
/* -------------------------------------------------------------------- */
    for( int iField = 0; iField < poFeatureDefn->GetGeomFieldCount(); iField++ )
    {
        OGRSQLiteGeomFieldDefn* poGeomFieldDefn =
            poFeatureDefn->myGetGeomFieldDefn(iField);
        if( !poGeomFieldDefn->IsIgnored() )
        {
            OGRGeometry *poGeometry = NULL;
            if ( poGeomFieldDefn->eGeomFormat == OSGF_WKT )
            {
                char *pszWKTCopy, *pszWKT = NULL;

                pszWKT = (char *) sqlite3_column_text( hStmt, poGeomFieldDefn->iCol );
                pszWKTCopy = pszWKT;
                OGRGeometryFactory::createFromWkt(
                        &pszWKTCopy, NULL, &poGeometry );
            }
            else if ( poGeomFieldDefn->eGeomFormat == OSGF_WKB )
            {
                const int nBytes = sqlite3_column_bytes( hStmt, poGeomFieldDefn->iCol );

                /* Try as spatialite first since createFromWkb() can sometimes */
                /* interpret spatialite blobs as WKB for certain SRID values */
                if (!poGeomFieldDefn->bTriedAsSpatiaLite)
                {
                    /* If the layer is the result of a sql select, we cannot be sure if it is */
                    /* WKB or SpatialLite format */
                    // coverity[tainted_data_return]
                    GByte* pabyBlob = (GByte*)sqlite3_column_blob( hStmt, poGeomFieldDefn->iCol );
                    if( ImportSpatiaLiteGeometry( pabyBlob, nBytes,
                        &poGeometry ) == OGRERR_NONE )
                    {
                        poGeomFieldDefn->eGeomFormat = OSGF_SpatiaLite;
                    }
                    poGeomFieldDefn->bTriedAsSpatiaLite = TRUE;
                }

                if( poGeomFieldDefn->eGeomFormat == OSGF_WKB )
                {
                    // coverity[tainted_data_return]
                    GByte* pabyBlob = (GByte*)sqlite3_column_blob( hStmt, poGeomFieldDefn->iCol );
                    CPL_IGNORE_RET_VAL(OGRGeometryFactory::createFromWkb(
                        pabyBlob, NULL, &poGeometry, nBytes ));
                }
            }
            else if ( poGeomFieldDefn->eGeomFormat == OSGF_FGF )
            {
                const int nBytes = sqlite3_column_bytes( hStmt, poGeomFieldDefn->iCol );
                // coverity[tainted_data_return]
                GByte* pabyBlob = (GByte*)sqlite3_column_blob( hStmt, poGeomFieldDefn->iCol );
                OGRGeometryFactory::createFromFgf( pabyBlob,
                        NULL, &poGeometry, nBytes, NULL );
            }
            else if ( poGeomFieldDefn->eGeomFormat == OSGF_SpatiaLite )
            {
                const int nBytes = sqlite3_column_bytes( hStmt, poGeomFieldDefn->iCol );
                // coverity[tainted_data_return]
                GByte* pabyBlob = (GByte*)sqlite3_column_blob( hStmt, poGeomFieldDefn->iCol );
                CPL_IGNORE_RET_VAL(ImportSpatiaLiteGeometry(
                        pabyBlob, nBytes, &poGeometry ));
            }

            if (poGeometry != NULL )
            {
                if( poGeomFieldDefn->GetSpatialRef() != NULL)
                    poGeometry->assignSpatialReference(poGeomFieldDefn->GetSpatialRef());
                poFeature->SetGeomFieldDirectly( iField, poGeometry );
            }
        }
    }

/* -------------------------------------------------------------------- */
/*      set the fields.                                                 */
/* -------------------------------------------------------------------- */
    for( int iField = 0; iField < poFeatureDefn->GetFieldCount(); iField++ )
    {
        OGRFieldDefn *poFieldDefn = poFeatureDefn->GetFieldDefn( iField );
        if ( poFieldDefn->IsIgnored() )
            continue;

        int iRawField = panFieldOrdinals[iField];

        int nSQLite3Type = sqlite3_column_type( hStmt, iRawField );
        if( nSQLite3Type == SQLITE_NULL )
        {
            poFeature->SetFieldNull( iField );
            continue;
        }

        switch( poFieldDefn->GetType() )
        {
        case OFTInteger:
        case OFTInteger64:
        {
            /* Possible since SQLite3 has no strong typing */
            if( nSQLite3Type == SQLITE_TEXT )
                poFeature->SetField( iField,
                        (const char *)sqlite3_column_text( hStmt, iRawField ) );
            else
                poFeature->SetField( iField,
                    sqlite3_column_int64( hStmt, iRawField ) );
            break;
        }

        case OFTReal:
        {
            /* Possible since SQLite3 has no strong typing */
            if( nSQLite3Type == SQLITE_TEXT )
                poFeature->SetField( iField,
                        (const char *)sqlite3_column_text( hStmt, iRawField ) );
            else
                poFeature->SetField( iField,
                    sqlite3_column_double( hStmt, iRawField ) );
            break;
        }

        case OFTBinary:
            {
                const int nBytes = sqlite3_column_bytes( hStmt, iRawField );
                // coverity[tainted_data_return]
                const GByte* pabyData = reinterpret_cast<const GByte*>(
                    sqlite3_column_blob( hStmt, iRawField ) );
                poFeature->SetField( iField, nBytes,
                                     const_cast<GByte*>(pabyData) );
            }
            break;

        case OFTString:
        case OFTIntegerList:
        case OFTInteger64List:
        case OFTRealList:
        case OFTStringList:
        {
            if( CSLFindString(papszCompressedColumns,
                              poFeatureDefn->GetFieldDefn(iField)->GetNameRef()) >= 0 )
            {
                const int nBytes = sqlite3_column_bytes( hStmt, iRawField );
                // coverity[tainted_data_return]
                GByte* pabyBlob = (GByte*)sqlite3_column_blob( hStmt, iRawField );

                void* pOut = CPLZLibInflate( pabyBlob, nBytes, NULL, 0, NULL );
                if( pOut != NULL )
                {
                    poFeature->SetField( iField, (const char*) pOut );
                    CPLFree(pOut);
                }
                else
                {
                    poFeature->SetField( iField,
                        (const char *)
                        sqlite3_column_text( hStmt, iRawField ) );
                }
            }
            else
            {
                poFeature->SetField( iField,
                    (const char *)
                    sqlite3_column_text( hStmt, iRawField ) );
            }
            break;
        }

        case OFTDate:
        case OFTTime:
        case OFTDateTime:
        {
            if( sqlite3_column_type( hStmt, iRawField ) == SQLITE_TEXT )
            {
                const char* pszValue = (const char *)
                    sqlite3_column_text( hStmt, iRawField );
                OGRSQLITEStringToDateTimeField( poFeature, iField, pszValue );
            }
            else if( sqlite3_column_type( hStmt, iRawField ) == SQLITE_FLOAT )
            {
                // Try converting from Julian day
                char** papszResult = NULL;
                sqlite3_get_table( poDS->GetDB(),
                                   CPLSPrintf("SELECT strftime('%%Y-%%m-%%d %%H:%%M:%%S', %.16g)",
                                               sqlite3_column_double(hStmt, iRawField)),
                                   &papszResult, NULL, NULL, NULL );
                if( papszResult && papszResult[0] && papszResult[1] )
                {
                    OGRSQLITEStringToDateTimeField( poFeature, iField,  papszResult[1] );
                }
                sqlite3_free_table(papszResult);
            }
            break;
        }

        default:
            break;
        }
    }

/* -------------------------------------------------------------------- */
/*      Set native data if found                                        */
/* -------------------------------------------------------------------- */
    if( iOGRNativeDataCol >= 0 &&
        sqlite3_column_type( hStmt, iOGRNativeDataCol ) == SQLITE_TEXT )
    {
        poFeature->SetNativeData( (const char*)sqlite3_column_text( hStmt, iOGRNativeDataCol ) );
    }
    if( iOGRNativeMediaTypeCol >= 0 &&
        sqlite3_column_type( hStmt, iOGRNativeMediaTypeCol ) == SQLITE_TEXT )
    {
        poFeature->SetNativeMediaType( (const char*)sqlite3_column_text( hStmt, iOGRNativeMediaTypeCol ) );
    }

    return poFeature;
}

/************************************************************************/
/*                             GetFeature()                             */
/************************************************************************/

OGRFeature *OGRSQLiteLayer::GetFeature( GIntBig nFeatureId )

{
    return OGRLayer::GetFeature( nFeatureId );
}

/************************************************************************/
/*                     createFromSpatialiteInternal()                   */
/************************************************************************/

/* See http://www.gaia-gis.it/spatialite/spatialite-manual-2.3.0.html#t3.3 */
/* for the specification of the spatialite BLOB geometry format */
/* Derived from WKB, but unfortunately it is not practical to reuse existing */
/* WKB encoding/decoding code */

#ifdef CPL_LSB
#define NEED_SWAP_SPATIALITE()  (eByteOrder != wkbNDR)
#else
#define NEED_SWAP_SPATIALITE()  (eByteOrder == wkbNDR)
#endif

OGRErr OGRSQLiteLayer::createFromSpatialiteInternal(const GByte *pabyData,
                                                    OGRGeometry **ppoReturn,
                                                    int nBytes,
                                                    OGRwkbByteOrder eByteOrder,
                                                    int* pnBytesConsumed,
                                                    int nRecLevel)
{
    *ppoReturn = NULL;

    /* Arbitrary value, but certainly large enough for reasonable usages ! */
    if( nRecLevel == 32 )
    {
        CPLError( CE_Failure, CPLE_AppDefined,
                  "Too many recursion levels (%d) while parsing "
                  "Spatialite geometry.",
                  nRecLevel );
        return OGRERR_CORRUPT_DATA;
    }

    if (nBytes < 4)
        return OGRERR_NOT_ENOUGH_DATA;

/* -------------------------------------------------------------------- */
/*      Decode the geometry type.                                       */
/* -------------------------------------------------------------------- */
    GInt32 nGType = 0;
    memcpy( &nGType, pabyData, 4 );
    if( NEED_SWAP_SPATIALITE() )
        CPL_SWAP32PTR( &nGType );

    if( ( nGType >= OGRSplitePointXY &&
          nGType <= OGRSpliteGeometryCollectionXY ) ||       // XY types
        ( nGType >= OGRSplitePointXYZ &&
          nGType <= OGRSpliteGeometryCollectionXYZ ) ||      // XYZ types
        ( nGType >= OGRSplitePointXYM &&
          nGType <= OGRSpliteGeometryCollectionXYM ) ||      // XYM types
        ( nGType >= OGRSplitePointXYZM &&
          nGType <= OGRSpliteGeometryCollectionXYZM ) ||     // XYZM types
        ( nGType >= OGRSpliteComprLineStringXY &&
          nGType <= OGRSpliteComprGeometryCollectionXY ) ||  // XY compressed
        ( nGType >= OGRSpliteComprLineStringXYZ &&
          nGType <= OGRSpliteComprGeometryCollectionXYZ ) || // XYZ compressed
        ( nGType >= OGRSpliteComprLineStringXYM &&
          nGType <= OGRSpliteComprGeometryCollectionXYM ) || // XYM compressed
        ( nGType >= OGRSpliteComprLineStringXYZM &&
          nGType <= OGRSpliteComprGeometryCollectionXYZM ) ) // XYZM compressed
        ;
    else
        return OGRERR_UNSUPPORTED_GEOMETRY_TYPE;

/* -------------------------------------------------------------------- */
/*      Point [XY]                                                      */
/* -------------------------------------------------------------------- */
    OGRGeometry *poGeom = NULL;
    GInt32 compressedSize = 0;

    if( nGType == OGRSplitePointXY )
    {
        if( nBytes < 4 + 2 * 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        double adfTuple[2] = { 0.0, 0.0 };

        memcpy( adfTuple, pabyData + 4, 2*8 );
        if (NEED_SWAP_SPATIALITE())
        {
            CPL_SWAP64PTR( adfTuple );
            CPL_SWAP64PTR( adfTuple + 1 );
        }

        poGeom = new OGRPoint( adfTuple[0], adfTuple[1] );

        if( pnBytesConsumed )
            *pnBytesConsumed = 4 + 2 * 8;
    }
/* -------------------------------------------------------------------- */
/*      Point [XYZ]                                                     */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSplitePointXYZ )
    {
        if( nBytes < 4 + 3 * 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        double adfTuple[3] = { 0.0, 0.0, 0.0 };

        memcpy( adfTuple, pabyData + 4, 3*8 );
        if (NEED_SWAP_SPATIALITE())
        {
            CPL_SWAP64PTR( adfTuple );
            CPL_SWAP64PTR( adfTuple + 1 );
            CPL_SWAP64PTR( adfTuple + 2 );
        }

        poGeom = new OGRPoint( adfTuple[0], adfTuple[1], adfTuple[2] );

        if( pnBytesConsumed )
            *pnBytesConsumed = 4 + 3 * 8;
    }

/* -------------------------------------------------------------------- */
/*      Point [XYM]                                                     */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSplitePointXYM )
    {
        if( nBytes < 4 + 3 * 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        double adfTuple[3] = { 0.0, 0.0, 0.0 };
        memcpy( adfTuple, pabyData + 4, 3*8 );
        if (NEED_SWAP_SPATIALITE())
        {
            CPL_SWAP64PTR( adfTuple );
            CPL_SWAP64PTR( adfTuple + 1 );
            CPL_SWAP64PTR( adfTuple + 2 );
        }

        OGRPoint* poPoint = new OGRPoint( adfTuple[0], adfTuple[1] );
        poPoint->setM( adfTuple[2] );
        poGeom = poPoint;

        if( pnBytesConsumed )
            *pnBytesConsumed = 4 + 3 * 8;
    }

/* -------------------------------------------------------------------- */
/*      Point [XYZM]                                                    */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSplitePointXYZM )
    {
        if( nBytes < 4 + 4 * 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        double  adfTuple[4];
        memcpy( adfTuple, pabyData + 4, 4*8 );
        if (NEED_SWAP_SPATIALITE())
        {
            CPL_SWAP64PTR( adfTuple );
            CPL_SWAP64PTR( adfTuple + 1 );
            CPL_SWAP64PTR( adfTuple + 2 );
            CPL_SWAP64PTR( adfTuple + 3 );
        }

        poGeom = new OGRPoint( adfTuple[0], adfTuple[1], adfTuple[2], adfTuple[3] );

        if( pnBytesConsumed )
            *pnBytesConsumed = 4 + 4 * 8;
    }

/* -------------------------------------------------------------------- */
/*      LineString [XY]                                                 */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSpliteLineStringXY )
    {
        if( nBytes < 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        GInt32 nPointCount = 0;
        memcpy( &nPointCount, pabyData + 4, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nPointCount );

        if( nPointCount < 0 || nPointCount > INT_MAX / (2 * 8))
            return OGRERR_CORRUPT_DATA;

        if (nBytes - 8 < 2 * 8 * nPointCount )
            return OGRERR_NOT_ENOUGH_DATA;

        OGRLineString *poLS = new OGRLineString();
        poGeom = poLS;
        if( !NEED_SWAP_SPATIALITE() )
        {
            poLS->setPoints( nPointCount, (OGRRawPoint*)(pabyData + 8), NULL );
        }
        else
        {
            poLS->setNumPoints( nPointCount, FALSE );
            for( int iPoint = 0; iPoint < nPointCount; iPoint++ )
            {
                double adfTuple[2] = { 0.0, 0.0 };
                memcpy( adfTuple, pabyData + 8 + 2*8*iPoint, 2*8 );
                CPL_SWAP64PTR( adfTuple );
                CPL_SWAP64PTR( adfTuple + 1 );
                poLS->setPoint( iPoint, adfTuple[0], adfTuple[1] );
            }
        }

        if( pnBytesConsumed )
            *pnBytesConsumed = 8 + 2 * 8 * nPointCount;
    }

/* -------------------------------------------------------------------- */
/*      LineString [XYZ]                                                */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSpliteLineStringXYZ )
    {
        if( nBytes < 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        GInt32 nPointCount = 0;
        memcpy( &nPointCount, pabyData + 4, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nPointCount );

        if( nPointCount < 0 || nPointCount > INT_MAX / (3 * 8))
            return OGRERR_CORRUPT_DATA;

        if (nBytes - 8 < 3 * 8 * nPointCount )
            return OGRERR_NOT_ENOUGH_DATA;

        OGRLineString *poLS = new OGRLineString();
        poGeom = poLS;
        poLS->setNumPoints( nPointCount );

        for( int iPoint = 0; iPoint < nPointCount; iPoint++ )
        {
            double adfTuple[3] = { 0.0, 0.0, 0.0 };
            memcpy( adfTuple, pabyData + 8 + 3*8*iPoint, 3*8 );
            if (NEED_SWAP_SPATIALITE())
            {
                CPL_SWAP64PTR( adfTuple );
                CPL_SWAP64PTR( adfTuple + 1 );
                CPL_SWAP64PTR( adfTuple + 2 );
            }

            poLS->setPoint( iPoint, adfTuple[0], adfTuple[1], adfTuple[2] );
        }

        if( pnBytesConsumed )
            *pnBytesConsumed = 8 + 3 * 8 * nPointCount;
    }

/* -------------------------------------------------------------------- */
/*      LineString [XYM]                                                */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSpliteLineStringXYM )
    {

        if( nBytes < 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        GInt32 nPointCount = 0;
        memcpy( &nPointCount, pabyData + 4, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nPointCount );

        if( nPointCount < 0 || nPointCount > INT_MAX / (3 * 8))
            return OGRERR_CORRUPT_DATA;

        if (nBytes - 8 < 3 * 8 * nPointCount )
            return OGRERR_NOT_ENOUGH_DATA;

        OGRLineString *poLS = new OGRLineString();
        poGeom = poLS;
        poLS->setNumPoints( nPointCount );

        for( int iPoint = 0; iPoint < nPointCount; iPoint++ )
        {
            double adfTuple[3] = { 0.0, 0.0, 0.0 };
            memcpy( adfTuple, pabyData + 8 + 3*8*iPoint, 3*8 );
            if (NEED_SWAP_SPATIALITE())
            {
                CPL_SWAP64PTR( adfTuple );
                CPL_SWAP64PTR( adfTuple + 1 );
                CPL_SWAP64PTR( adfTuple + 2 );
            }

            poLS->setPointM( iPoint, adfTuple[0], adfTuple[1], adfTuple[2] );
        }

        if( pnBytesConsumed )
            *pnBytesConsumed = 8 + 3 * 8 * nPointCount;
    }

/* -------------------------------------------------------------------- */
/*      LineString [XYZM]                                               */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSpliteLineStringXYZM )
    {
        if( nBytes < 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        GInt32 nPointCount = 0;
        memcpy( &nPointCount, pabyData + 4, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nPointCount );

        if( nPointCount < 0 || nPointCount > INT_MAX / (4 * 8))
            return OGRERR_CORRUPT_DATA;

        if (nBytes - 8 < 4 * 8 * nPointCount )
            return OGRERR_NOT_ENOUGH_DATA;

        OGRLineString *poLS = new OGRLineString();
        poGeom = poLS;
        poLS->setNumPoints( nPointCount );

        for( int iPoint = 0; iPoint < nPointCount; iPoint++ )
        {
            double adfTuple[4] = { 0.0, 0.0, 0.0, 0.0 };
            memcpy( adfTuple, pabyData + 8 + 4*8*iPoint, 4*8 );
            if (NEED_SWAP_SPATIALITE())
            {
                CPL_SWAP64PTR( adfTuple );
                CPL_SWAP64PTR( adfTuple + 1 );
                CPL_SWAP64PTR( adfTuple + 2 );
                CPL_SWAP64PTR( adfTuple + 3 );
            }

            poLS->setPoint( iPoint, adfTuple[0], adfTuple[1], adfTuple[2], adfTuple[3] );
        }

        if( pnBytesConsumed )
            *pnBytesConsumed = 8 + 4 * 8 * nPointCount;
    }

/* -------------------------------------------------------------------- */
/*      LineString [XY] Compressed                                      */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSpliteComprLineStringXY )
    {
        if( nBytes < 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        GInt32 nPointCount = 0;
        memcpy( &nPointCount, pabyData + 4, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nPointCount );

        if( nPointCount < 0 || nPointCount - 2 > (INT_MAX - 16 * 2) / 8)
            return OGRERR_CORRUPT_DATA;

        compressedSize = 16 * 2;                  // first and last Points
        compressedSize += 8 * (nPointCount - 2);  // intermediate Points

        if (nBytes - 8 < compressedSize )
            return OGRERR_NOT_ENOUGH_DATA;

        OGRLineString *poLS = new OGRLineString();
        poGeom = poLS;
        poLS->setNumPoints( nPointCount );

        int nNextByte = 8;
        double adfTupleBase[2] = { 0.0, 0.0 };

        for( int iPoint = 0; iPoint < nPointCount; iPoint++ )
        {
            double adfTuple[2] = { 0.0, 0.0 };

            if ( iPoint == 0 || iPoint == (nPointCount - 1 ) )
            {
                // first and last Points are uncompressed
                memcpy( adfTuple, pabyData + nNextByte, 2*8 );
                nNextByte += 2 * 8;

                if (NEED_SWAP_SPATIALITE())
                {
                    CPL_SWAP64PTR( adfTuple );
                    CPL_SWAP64PTR( adfTuple + 1 );
                }
            }
            else
            {
                // any other intermediate Point is compressed
                float asfTuple[2] = { 0.0f, 0.0f };
                memcpy( asfTuple, pabyData + nNextByte, 2*4 );
                nNextByte += 2 * 4;

                if (NEED_SWAP_SPATIALITE())
                {
                    CPL_SWAP32PTR( asfTuple );
                    CPL_SWAP32PTR( asfTuple + 1 );
                }
                adfTuple[0] = asfTuple[0] + adfTupleBase[0];
                adfTuple[1] = asfTuple[1] + adfTupleBase[1];
            }

            poLS->setPoint( iPoint, adfTuple[0], adfTuple[1] );
            adfTupleBase[0] = adfTuple[0];
            adfTupleBase[1] = adfTuple[1];
        }

        if( pnBytesConsumed )
            *pnBytesConsumed = nNextByte;
    }

/* -------------------------------------------------------------------- */
/*      LineString [XYZ] Compressed                                     */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSpliteComprLineStringXYZ )
    {
        if( nBytes < 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        GInt32 nPointCount = 0;
        memcpy( &nPointCount, pabyData + 4, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nPointCount );

        if( nPointCount < 0 || nPointCount - 2 > (INT_MAX - 24 * 2) / 12)
            return OGRERR_CORRUPT_DATA;

        compressedSize = 24 * 2;                  // first and last Points
        compressedSize += 12 * (nPointCount - 2);  // intermediate Points

        if (nBytes - 8 < compressedSize )
            return OGRERR_NOT_ENOUGH_DATA;

        OGRLineString *poLS = new OGRLineString();
        poGeom = poLS;
        poLS->setNumPoints( nPointCount );

        int nNextByte = 8;
        double adfTupleBase[3] = { 0.0, 0.0, 0.0 };

        for( int iPoint = 0; iPoint < nPointCount; iPoint++ )
        {
            double adfTuple[3] = { 0.0, 0.0, 0.0 };

            if ( iPoint == 0 || iPoint == (nPointCount - 1 ) )
            {
                // first and last Points are uncompressed
                memcpy( adfTuple, pabyData + nNextByte, 3*8 );
                nNextByte += 3 * 8;

                if (NEED_SWAP_SPATIALITE())
                {
                    CPL_SWAP64PTR( adfTuple );
                    CPL_SWAP64PTR( adfTuple + 1 );
                    CPL_SWAP64PTR( adfTuple + 2 );
                }
            }
            else
            {
                // any other intermediate Point is compressed
                float asfTuple[3] = { 0.0f, 0.0f, 0.0f };
                memcpy( asfTuple, pabyData + nNextByte, 3*4 );
                nNextByte += 3 * 4;

                if (NEED_SWAP_SPATIALITE())
                {
                    CPL_SWAP32PTR( asfTuple );
                    CPL_SWAP32PTR( asfTuple + 1 );
                    CPL_SWAP32PTR( asfTuple + 2 );
                }
                adfTuple[0] = asfTuple[0] + adfTupleBase[0];
                adfTuple[1] = asfTuple[1] + adfTupleBase[1];
                adfTuple[2] = asfTuple[2] + adfTupleBase[2];
            }

            poLS->setPoint( iPoint, adfTuple[0], adfTuple[1], adfTuple[2] );
            adfTupleBase[0] = adfTuple[0];
            adfTupleBase[1] = adfTuple[1];
            adfTupleBase[2] = adfTuple[2];
        }

        if( pnBytesConsumed )
            *pnBytesConsumed = nNextByte;
    }

/* -------------------------------------------------------------------- */
/*      LineString [XYM] Compressed                                     */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSpliteComprLineStringXYM )
    {
        if( nBytes < 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        GInt32 nPointCount = 0;
        memcpy( &nPointCount, pabyData + 4, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nPointCount );

        if( nPointCount < 0 || nPointCount - 2 > (INT_MAX - 24 * 2) / 16)
            return OGRERR_CORRUPT_DATA;

        compressedSize = 24 * 2;                  // first and last Points
        compressedSize += 16 * (nPointCount - 2);  // intermediate Points

        if (nBytes - 8 < compressedSize )
            return OGRERR_NOT_ENOUGH_DATA;

        OGRLineString *poLS = new OGRLineString();
        poGeom = poLS;
        poLS->setNumPoints( nPointCount );

        int nNextByte = 8;
        double adfTupleBase[2] = { 0.0, 0.0 };

        for( int iPoint = 0; iPoint < nPointCount; iPoint++ )
        {
            double adfTuple[3] = { 0.0, 0.0, 0.0 };
            if ( iPoint == 0 || iPoint == (nPointCount - 1 ) )
            {
                // first and last Points are uncompressed
                memcpy( adfTuple, pabyData + nNextByte, 3*8 );
                nNextByte += 3 * 8;

                if (NEED_SWAP_SPATIALITE())
                {
                    CPL_SWAP64PTR( adfTuple );
                    CPL_SWAP64PTR( adfTuple + 1 );
                    CPL_SWAP64PTR( adfTuple + 2 );
                }
            }
            else
            {
                // any other intermediate Point is compressed
                float asfTuple[2] = { 0.0f, 0.0f };
                memcpy( asfTuple, pabyData + nNextByte, 2*4 );
                memcpy( adfTuple + 2, pabyData + nNextByte + 2*4, 8 );
                nNextByte += 2 * 4 + 8;

                if (NEED_SWAP_SPATIALITE())
                {
                    CPL_SWAP32PTR( asfTuple );
                    CPL_SWAP32PTR( asfTuple + 1 );
                    CPL_SWAP64PTR( adfTuple + 2 ); /* adfTuple and not asfTuple is intended */
                }
                adfTuple[0] = asfTuple[0] + adfTupleBase[0];
                adfTuple[1] = asfTuple[1] + adfTupleBase[1];
            }

            poLS->setPointM( iPoint, adfTuple[0], adfTuple[1], adfTuple[2] );
            adfTupleBase[0] = adfTuple[0];
            adfTupleBase[1] = adfTuple[1];
        }

        if( pnBytesConsumed )
            *pnBytesConsumed = nNextByte;
    }

/* -------------------------------------------------------------------- */
/*      LineString [XYZM] Compressed                                    */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSpliteComprLineStringXYZM )
    {
        if( nBytes < 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        GInt32 nPointCount = 0;
        memcpy( &nPointCount, pabyData + 4, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nPointCount );

        if( nPointCount < 0 || nPointCount - 2 > (INT_MAX - 32 * 2) / 20)
            return OGRERR_CORRUPT_DATA;

        compressedSize = 32 * 2;                   // first and last Points
        /* Note 20 is not an error : x,y,z are float and the m is a double */
        compressedSize += 20 * (nPointCount - 2);  // intermediate Points

        if (nBytes - 8 < compressedSize )
            return OGRERR_NOT_ENOUGH_DATA;

        OGRLineString *poLS = new OGRLineString();
        poGeom = poLS;
        poLS->setNumPoints( nPointCount );

        int nNextByte = 8;
        double adfTupleBase[3] = { 0.0, 0.0, 0.0 };

        for( int iPoint = 0; iPoint < nPointCount; iPoint++ )
        {
            double adfTuple[4] = { 0.0, 0.0, 0.0, 0.0 };

            if ( iPoint == 0 || iPoint == (nPointCount - 1 ) )
            {
                // first and last Points are uncompressed
                memcpy( adfTuple, pabyData + nNextByte, 4*8 );
                nNextByte += 4 * 8;

                if (NEED_SWAP_SPATIALITE())
                {
                    CPL_SWAP64PTR( adfTuple );
                    CPL_SWAP64PTR( adfTuple + 1 );
                    CPL_SWAP64PTR( adfTuple + 2 );
                    CPL_SWAP64PTR( adfTuple + 3 );
                }
            }
            else
            {
                // any other intermediate Point is compressed
                float asfTuple[3] = { 0.0f, 0.0f, 0.0f };
                memcpy( asfTuple, pabyData + nNextByte, 3*4 );
                memcpy( adfTuple + 3, pabyData + nNextByte + 3*4, 8 );
                nNextByte += 3 * 4 + 8;

                if (NEED_SWAP_SPATIALITE())
                {
                    CPL_SWAP32PTR( asfTuple );
                    CPL_SWAP32PTR( asfTuple + 1 );
                    CPL_SWAP32PTR( asfTuple + 2 );
                    CPL_SWAP64PTR( adfTuple + 3 ); /* adfTuple and not asfTuple is intended */
                }
                adfTuple[0] = asfTuple[0] + adfTupleBase[0];
                adfTuple[1] = asfTuple[1] + adfTupleBase[1];
                adfTuple[2] = asfTuple[2] + adfTupleBase[2];
            }

            poLS->setPoint( iPoint, adfTuple[0], adfTuple[1], adfTuple[2], adfTuple[3] );
            adfTupleBase[0] = adfTuple[0];
            adfTupleBase[1] = adfTuple[1];
            adfTupleBase[2] = adfTuple[2];
        }

        if( pnBytesConsumed )
            *pnBytesConsumed = nNextByte;
    }

/* -------------------------------------------------------------------- */
/*      Polygon [XY]                                                    */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSplitePolygonXY )
    {
        if( nBytes < 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        GInt32 nRingCount = 0;
        memcpy( &nRingCount, pabyData + 4, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nRingCount );

        if (nRingCount < 0 || nRingCount > INT_MAX / 4)
            return OGRERR_CORRUPT_DATA;

        // Each ring has a minimum of 4 bytes
        if (nBytes - 8 < nRingCount * 4)
            return OGRERR_NOT_ENOUGH_DATA;

        int nNextByte = 8;

        OGRPolygon *poPoly = new OGRPolygon();
        poGeom = poPoly;

        for( int iRing = 0; iRing < nRingCount; iRing++ )
        {
            if( nBytes - nNextByte < 4 )
            {
                delete poPoly;
                return OGRERR_NOT_ENOUGH_DATA;
            }

            GInt32 nPointCount = 0;
            memcpy( &nPointCount, pabyData + nNextByte, 4 );
            if (NEED_SWAP_SPATIALITE())
                CPL_SWAP32PTR( &nPointCount );

            if( nPointCount < 0 || nPointCount > INT_MAX / (2 * 8))
            {
                delete poPoly;
                return OGRERR_CORRUPT_DATA;
            }

            nNextByte += 4;

            if( nBytes - nNextByte < 2 * 8 * nPointCount )
            {
                delete poPoly;
                return OGRERR_NOT_ENOUGH_DATA;
            }

            OGRLinearRing *poLR = new OGRLinearRing();
            if( !NEED_SWAP_SPATIALITE() )
            {
                poLR->setPoints( nPointCount, (OGRRawPoint*)(pabyData + nNextByte), NULL );
                nNextByte += 2 * 8 * nPointCount;
            }
            else
            {
                poLR->setNumPoints( nPointCount, FALSE );
                for( int iPoint = 0; iPoint < nPointCount; iPoint++ )
                {
                    double adfTuple[2] = { 0.0, 0.0 };
                    memcpy( adfTuple, pabyData + nNextByte, 2*8 );
                    nNextByte += 2 * 8;
                    CPL_SWAP64PTR( adfTuple );
                    CPL_SWAP64PTR( adfTuple + 1 );
                    poLR->setPoint( iPoint, adfTuple[0], adfTuple[1] );
                }
            }

            poPoly->addRingDirectly( poLR );
        }

        if( pnBytesConsumed )
            *pnBytesConsumed = nNextByte;
    }

/* -------------------------------------------------------------------- */
/*      Polygon [XYZ]                                                   */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSplitePolygonXYZ )
    {
        if( nBytes < 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        GInt32 nRingCount = 0;
        memcpy( &nRingCount, pabyData + 4, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nRingCount );

        if (nRingCount < 0 || nRingCount > INT_MAX / 4)
            return OGRERR_CORRUPT_DATA;

        // Each ring has a minimum of 4 bytes
        if (nBytes - 8 < nRingCount * 4)
            return OGRERR_NOT_ENOUGH_DATA;

        int nNextByte = 8;

        OGRPolygon *poPoly = new OGRPolygon();
        poGeom = poPoly;

        for( int iRing = 0; iRing < nRingCount; iRing++ )
        {
            if( nBytes - nNextByte < 4 )
            {
                delete poPoly;
                return OGRERR_NOT_ENOUGH_DATA;
            }

            GInt32 nPointCount = 0;
            memcpy( &nPointCount, pabyData + nNextByte, 4 );
            if (NEED_SWAP_SPATIALITE())
                CPL_SWAP32PTR( &nPointCount );

            if( nPointCount < 0 || nPointCount > INT_MAX / (3 * 8))
            {
                delete poPoly;
                return OGRERR_CORRUPT_DATA;
            }

            nNextByte += 4;

            if( nBytes - nNextByte < 3 * 8 * nPointCount )
            {
                delete poPoly;
                return OGRERR_NOT_ENOUGH_DATA;
            }

            OGRLinearRing *poLR = new OGRLinearRing();
            poLR->setNumPoints( nPointCount, FALSE );

            for( int iPoint = 0; iPoint < nPointCount; iPoint++ )
            {
                double adfTuple[3] = { 0.0, 0.0, 0.0 };
                memcpy( adfTuple, pabyData + nNextByte, 3*8 );
                nNextByte += 3 * 8;

                if (NEED_SWAP_SPATIALITE())
                {
                    CPL_SWAP64PTR( adfTuple );
                    CPL_SWAP64PTR( adfTuple + 1 );
                    CPL_SWAP64PTR( adfTuple + 2 );
                }

                poLR->setPoint( iPoint, adfTuple[0], adfTuple[1], adfTuple[2] );
            }

            poPoly->addRingDirectly( poLR );
        }

        if( pnBytesConsumed )
            *pnBytesConsumed = nNextByte;
    }

/* -------------------------------------------------------------------- */
/*      Polygon [XYM]                                                   */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSplitePolygonXYM )
    {
        if( nBytes < 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        GInt32 nRingCount = 0;
        memcpy( &nRingCount, pabyData + 4, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nRingCount );

        if (nRingCount < 0 || nRingCount > INT_MAX / 4)
            return OGRERR_CORRUPT_DATA;

        // Each ring has a minimum of 4 bytes
        if (nBytes - 8 < nRingCount * 4)
            return OGRERR_NOT_ENOUGH_DATA;

        int nNextByte = 8;

        OGRPolygon *poPoly = new OGRPolygon();
        poGeom = poPoly;

        for( int iRing = 0; iRing < nRingCount; iRing++ )
        {
            if( nBytes - nNextByte < 4 )
            {
                delete poPoly;
                return OGRERR_NOT_ENOUGH_DATA;
            }

            GInt32 nPointCount = 0;
            memcpy( &nPointCount, pabyData + nNextByte, 4 );
            if (NEED_SWAP_SPATIALITE())
                CPL_SWAP32PTR( &nPointCount );

            if( nPointCount < 0 || nPointCount > INT_MAX / (3 * 8))
            {
                delete poPoly;
                return OGRERR_CORRUPT_DATA;
            }

            nNextByte += 4;

            if( nBytes - nNextByte < 3 * 8 * nPointCount )
            {
                delete poPoly;
                return OGRERR_NOT_ENOUGH_DATA;
            }

            OGRLinearRing *poLR = new OGRLinearRing();
            poLR->setNumPoints( nPointCount, FALSE );

            for( int iPoint = 0; iPoint < nPointCount; iPoint++ )
            {
                double adfTuple[3] = { 0.0, 0.0, 0.0 };
                memcpy( adfTuple, pabyData + nNextByte, 3*8 );
                nNextByte += 3 * 8;

                if (NEED_SWAP_SPATIALITE())
                {
                    CPL_SWAP64PTR( adfTuple );
                    CPL_SWAP64PTR( adfTuple + 1 );
                    CPL_SWAP64PTR( adfTuple + 2 );
                }

                poLR->setPointM( iPoint, adfTuple[0], adfTuple[1], adfTuple[2] );
            }

            poPoly->addRingDirectly( poLR );
        }

        if( pnBytesConsumed )
            *pnBytesConsumed = nNextByte;
    }

/* -------------------------------------------------------------------- */
/*      Polygon [XYZM]                                                  */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSplitePolygonXYZM )
    {
        if( nBytes < 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        GInt32 nRingCount = 0;
        memcpy( &nRingCount, pabyData + 4, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nRingCount );

        if (nRingCount < 0 || nRingCount > INT_MAX / 4)
            return OGRERR_CORRUPT_DATA;

        // Each ring has a minimum of 4 bytes
        if (nBytes - 8 < nRingCount * 4)
            return OGRERR_NOT_ENOUGH_DATA;

        int nNextByte = 8;

        OGRPolygon *poPoly = new OGRPolygon();
        poGeom = poPoly;

        for( int iRing = 0; iRing < nRingCount; iRing++ )
        {
            if( nBytes - nNextByte < 4 )
            {
                delete poPoly;
                return OGRERR_NOT_ENOUGH_DATA;
            }

            GInt32 nPointCount = 0;
            memcpy( &nPointCount, pabyData + nNextByte, 4 );
            if (NEED_SWAP_SPATIALITE())
                CPL_SWAP32PTR( &nPointCount );

            if( nPointCount < 0 || nPointCount > INT_MAX / (4 * 8))
            {
                delete poPoly;
                return OGRERR_CORRUPT_DATA;
            }

            nNextByte += 4;

            if( nBytes - nNextByte < 4 * 8 * nPointCount )
            {
                delete poPoly;
                return OGRERR_NOT_ENOUGH_DATA;
            }

            OGRLinearRing *poLR = new OGRLinearRing();
            poLR->setNumPoints( nPointCount, FALSE );

            for( int iPoint = 0; iPoint < nPointCount; iPoint++ )
            {
                double adfTuple[4] = { 0.0, 0.0, 0.0, 0.0 };

                memcpy( adfTuple, pabyData + nNextByte, 4*8 );
                nNextByte += 4 * 8;

                if (NEED_SWAP_SPATIALITE())
                {
                    CPL_SWAP64PTR( adfTuple );
                    CPL_SWAP64PTR( adfTuple + 1 );
                    CPL_SWAP64PTR( adfTuple + 2 );
                    CPL_SWAP64PTR( adfTuple + 3 );
                }

                poLR->setPoint( iPoint, adfTuple[0], adfTuple[1], adfTuple[2], adfTuple[3] );
            }

            poPoly->addRingDirectly( poLR );
        }

        if( pnBytesConsumed )
            *pnBytesConsumed = nNextByte;
    }

/* -------------------------------------------------------------------- */
/*      Polygon [XY] Compressed                                         */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSpliteComprPolygonXY  )
    {
        if( nBytes < 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        GInt32 nRingCount = 0;
        memcpy( &nRingCount, pabyData + 4, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nRingCount );

        if (nRingCount < 0 || nRingCount > INT_MAX / 4)
            return OGRERR_CORRUPT_DATA;

        // Each ring has a minimum of 4 bytes
        if (nBytes - 8 < nRingCount * 4)
            return OGRERR_NOT_ENOUGH_DATA;

        int nNextByte = 8;

        OGRPolygon *poPoly = new OGRPolygon();
        poGeom = poPoly;

        for( int iRing = 0; iRing < nRingCount; iRing++ )
        {
            if( nBytes - nNextByte < 4 )
            {
                delete poPoly;
                return OGRERR_NOT_ENOUGH_DATA;
            }

            GInt32 nPointCount = 0;
            memcpy( &nPointCount, pabyData + nNextByte, 4 );
            if (NEED_SWAP_SPATIALITE())
                CPL_SWAP32PTR( &nPointCount );

            if( nPointCount < 0 || nPointCount - 2 > (INT_MAX - 16 * 2) / 8)
            {
                delete poPoly;
                return OGRERR_CORRUPT_DATA;
            }

            compressedSize = 16 * 2;                  // first and last Points
            compressedSize += 8 * (nPointCount - 2);  // intermediate Points

            nNextByte += 4;

            if (nBytes - nNextByte < compressedSize )
            {
                delete poPoly;
                return OGRERR_NOT_ENOUGH_DATA;
            }

            double adfTupleBase[2] = { 0.0, 0.0 };
            OGRLinearRing *poLR = new OGRLinearRing();
            poLR->setNumPoints( nPointCount, FALSE );

            for( int iPoint = 0; iPoint < nPointCount; iPoint++ )
            {
                double adfTuple[2] = { 0.0, 0.0 };
                if ( iPoint == 0 || iPoint == (nPointCount - 1 ) )
                {
                    // first and last Points are uncompressed
                    memcpy( adfTuple, pabyData + nNextByte, 2*8 );
                    nNextByte += 2 * 8;

                    if (NEED_SWAP_SPATIALITE())
                    {
                        CPL_SWAP64PTR( adfTuple );
                        CPL_SWAP64PTR( adfTuple + 1 );
                    }
                }
                else
                {
                    // any other intermediate Point is compressed
                    float asfTuple[2]  = { 0.0f, 0.0f };
                    memcpy( asfTuple, pabyData + nNextByte, 2*4 );
                    nNextByte += 2 * 4;

                    if (NEED_SWAP_SPATIALITE())
                    {
                        CPL_SWAP32PTR( asfTuple );
                        CPL_SWAP32PTR( asfTuple + 1 );
                    }
                    adfTuple[0] = asfTuple[0] + adfTupleBase[0];
                    adfTuple[1] = asfTuple[1] + adfTupleBase[1];
                }

                poLR->setPoint( iPoint, adfTuple[0], adfTuple[1] );
                adfTupleBase[0] = adfTuple[0];
                adfTupleBase[1] = adfTuple[1];
            }

            poPoly->addRingDirectly( poLR );
        }

        if( pnBytesConsumed )
            *pnBytesConsumed = nNextByte;
    }

/* -------------------------------------------------------------------- */
/*      Polygon [XYZ] Compressed                                        */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSpliteComprPolygonXYZ )
    {
        if( nBytes < 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        GInt32 nRingCount = 0;
        memcpy( &nRingCount, pabyData + 4, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nRingCount );

        if (nRingCount < 0 || nRingCount > INT_MAX / 4)
            return OGRERR_CORRUPT_DATA;

        // Each ring has a minimum of 4 bytes
        if (nBytes - 8 < nRingCount * 4)
            return OGRERR_NOT_ENOUGH_DATA;

        int nNextByte = 8;

        OGRPolygon *poPoly = new OGRPolygon();
        poGeom = poPoly;

        for( int iRing = 0; iRing < nRingCount; iRing++ )
        {
            if( nBytes - nNextByte < 4 )
            {
                delete poPoly;
                return OGRERR_NOT_ENOUGH_DATA;
            }

            GInt32 nPointCount = 0;
            memcpy( &nPointCount, pabyData + nNextByte, 4 );
            if (NEED_SWAP_SPATIALITE())
                CPL_SWAP32PTR( &nPointCount );

            if( nPointCount < 0 || nPointCount - 2 > (INT_MAX - 24 * 2) / 12)
            {
                delete poPoly;
                return OGRERR_CORRUPT_DATA;
            }

            compressedSize = 24 * 2;  // first and last Points
            compressedSize += 12 * (nPointCount - 2);  // intermediate Points

            nNextByte += 4;

            if( nBytes - nNextByte < compressedSize )
            {
                delete poPoly;
                return OGRERR_NOT_ENOUGH_DATA;
            }

            double adfTupleBase[3] = { 0.0, 0.0, 0.0 };
            OGRLinearRing *poLR = new OGRLinearRing();
            poLR->setNumPoints( nPointCount, FALSE );

            for( int iPoint = 0; iPoint < nPointCount; iPoint++ )
            {
                double adfTuple[3] = { 0.0, 0.0, 0.0 };
                if ( iPoint == 0 || iPoint == (nPointCount - 1 ) )
                {
                    // first and last Points are uncompressed
                    memcpy( adfTuple, pabyData + nNextByte, 3*8 );
                    nNextByte += 3 * 8;

                    if (NEED_SWAP_SPATIALITE())
                    {
                        CPL_SWAP64PTR( adfTuple );
                        CPL_SWAP64PTR( adfTuple + 1 );
                        CPL_SWAP64PTR( adfTuple + 2 );
                    }
                }
                else
                {
                    // any other intermediate Point is compressed
                    float asfTuple[3] = { 0.0, 0.0, 0.0 };
                    memcpy( asfTuple, pabyData + nNextByte, 3*4 );
                    nNextByte += 3 * 4;

                    if (NEED_SWAP_SPATIALITE())
                    {
                        CPL_SWAP32PTR( asfTuple );
                        CPL_SWAP32PTR( asfTuple + 1 );
                        CPL_SWAP32PTR( asfTuple + 2 );
                    }
                    adfTuple[0] = asfTuple[0] + adfTupleBase[0];
                    adfTuple[1] = asfTuple[1] + adfTupleBase[1];
                    adfTuple[2] = asfTuple[2] + adfTupleBase[2];
                }

                poLR->setPoint( iPoint, adfTuple[0], adfTuple[1], adfTuple[2] );
                adfTupleBase[0] = adfTuple[0];
                adfTupleBase[1] = adfTuple[1];
                adfTupleBase[2] = adfTuple[2];
            }

            poPoly->addRingDirectly( poLR );
        }

        if( pnBytesConsumed )
            *pnBytesConsumed = nNextByte;
    }

/* -------------------------------------------------------------------- */
/*      Polygon [XYM] Compressed                                        */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSpliteComprPolygonXYM )
    {
        if( nBytes < 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        GInt32 nRingCount = 0;
        memcpy( &nRingCount, pabyData + 4, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nRingCount );

        if (nRingCount < 0 || nRingCount > INT_MAX / 4)
            return OGRERR_CORRUPT_DATA;

        // Each ring has a minimum of 4 bytes
        if (nBytes - 8 < nRingCount * 4)
            return OGRERR_NOT_ENOUGH_DATA;

        int nNextByte = 8;

        OGRPolygon *poPoly = new OGRPolygon();
        poGeom = poPoly;

        for( int iRing = 0; iRing < nRingCount; iRing++ )
        {
            if( nBytes - nNextByte < 4 )
            {
                delete poPoly;
                return OGRERR_NOT_ENOUGH_DATA;
            }

            GInt32 nPointCount = 0;
            memcpy( &nPointCount, pabyData + nNextByte, 4 );
            if (NEED_SWAP_SPATIALITE())
                CPL_SWAP32PTR( &nPointCount );

            if( nPointCount < 0 || nPointCount - 2 > (INT_MAX - 24 * 2) / 16)
            {
                delete poPoly;
                return OGRERR_CORRUPT_DATA;
            }

            compressedSize = 24 * 2;                   // first and last Points
            compressedSize += 16 * (nPointCount - 2);  // intermediate Points

            nNextByte += 4;

            if (nBytes - nNextByte < compressedSize )
            {
                delete poPoly;
                return OGRERR_NOT_ENOUGH_DATA;
            }

            double adfTupleBase[2] = { 0.0, 0.0 };
            OGRLinearRing *poLR = new OGRLinearRing();
            poLR->setNumPoints( nPointCount, FALSE );

            for( int iPoint = 0; iPoint < nPointCount; iPoint++ )
            {
                double adfTuple[3] = { 0.0, 0.0, 0.0 };
                if ( iPoint == 0 || iPoint == (nPointCount - 1 ) )
                {
                    // first and last Points are uncompressed
                    memcpy( adfTuple, pabyData + nNextByte, 3*8 );
                    nNextByte += 3 * 8;

                    if (NEED_SWAP_SPATIALITE())
                    {
                        CPL_SWAP64PTR( adfTuple );
                        CPL_SWAP64PTR( adfTuple + 1 );
                        CPL_SWAP64PTR( adfTuple + 2 );
                    }
                }
                else
                {
                    // any other intermediate Point is compressed
                    float asfTuple[2] = { 0.0f, 0.0f };
                    memcpy( asfTuple, pabyData + nNextByte, 2*4 );
                    memcpy( adfTuple + 2, pabyData + nNextByte + 2*4, 8 );
                    nNextByte += 2 * 4 + 8;

                    if (NEED_SWAP_SPATIALITE())
                    {
                        CPL_SWAP32PTR( asfTuple );
                        CPL_SWAP32PTR( asfTuple + 1 );
                        CPL_SWAP64PTR( adfTuple + 2 ); /* adfTuple and not asfTuple is intended */
                    }
                    adfTuple[0] = asfTuple[0] + adfTupleBase[0];
                    adfTuple[1] = asfTuple[1] + adfTupleBase[1];
                }

                poLR->setPointM( iPoint, adfTuple[0], adfTuple[1], adfTuple[2] );
                adfTupleBase[0] = adfTuple[0];
                adfTupleBase[1] = adfTuple[1];
            }

            poPoly->addRingDirectly( poLR );
        }

        if( pnBytesConsumed )
            *pnBytesConsumed = nNextByte;
    }

/* -------------------------------------------------------------------- */
/*      Polygon [XYZM] Compressed                                       */
/* -------------------------------------------------------------------- */
    else if( nGType == OGRSpliteComprPolygonXYZM )
    {
        if( nBytes < 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        GInt32 nRingCount = 0;
        memcpy( &nRingCount, pabyData + 4, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nRingCount );

        if (nRingCount < 0 || nRingCount > INT_MAX / 4)
            return OGRERR_CORRUPT_DATA;

        // Each ring has a minimum of 4 bytes
        if (nBytes - 8 < nRingCount * 4)
            return OGRERR_NOT_ENOUGH_DATA;

        int nNextByte = 8;

        OGRPolygon *poPoly = new OGRPolygon();
        poGeom = poPoly;

        for( int iRing = 0; iRing < nRingCount; iRing++ )
        {
            if( nBytes - nNextByte < 4 )
            {
                delete poPoly;
                return OGRERR_NOT_ENOUGH_DATA;
            }

            GInt32 nPointCount = 0;
            memcpy( &nPointCount, pabyData + nNextByte, 4 );
            if (NEED_SWAP_SPATIALITE())
                CPL_SWAP32PTR( &nPointCount );

            if( nPointCount < 0 || nPointCount - 2 > (INT_MAX - 32 * 2) / 20)
            {
                delete poPoly;
                return OGRERR_CORRUPT_DATA;
            }

            compressedSize = 32 * 2;                   // first and last Points
            /* Note 20 is not an error : x,y,z are float and the m is a double */
            compressedSize += 20 * (nPointCount - 2);  // intermediate Points

            nNextByte += 4;

            if (nBytes - nNextByte < compressedSize )
            {
                delete poPoly;
                return OGRERR_NOT_ENOUGH_DATA;
            }

            double adfTupleBase[3] = { 0.0, 0.0, 0.0 };
            OGRLinearRing *poLR = new OGRLinearRing();
            poLR->setNumPoints( nPointCount, FALSE );

            for( int iPoint = 0; iPoint < nPointCount; iPoint++ )
            {
                double adfTuple[4] = { 0.0, 0.0, 0.0, 0.0 };
                if ( iPoint == 0 || iPoint == (nPointCount - 1 ) )
                {
                    // first and last Points are uncompressed
                    memcpy( adfTuple, pabyData + nNextByte, 4*8 );
                    nNextByte += 4 * 8;

                    if (NEED_SWAP_SPATIALITE())
                    {
                        CPL_SWAP64PTR( adfTuple );
                        CPL_SWAP64PTR( adfTuple + 1 );
                        CPL_SWAP64PTR( adfTuple + 2 );
                        CPL_SWAP64PTR( adfTuple + 3 );
                    }
                }
                else
                {
                    // any other intermediate Point is compressed
                  float asfTuple[3] = { 0.0f, 0.0f, 0.0f };
                    memcpy( asfTuple, pabyData + nNextByte, 3*4 );
                    memcpy( adfTuple + 3, pabyData + nNextByte + 3*4, 8 );
                    nNextByte += 3 * 4 + 8;

                    if (NEED_SWAP_SPATIALITE())
                    {
                        CPL_SWAP32PTR( asfTuple );
                        CPL_SWAP32PTR( asfTuple + 1 );
                        CPL_SWAP32PTR( asfTuple + 2 );
                        CPL_SWAP64PTR( adfTuple + 3 ); /* adfTuple and not asfTuple is intended */
                    }
                    adfTuple[0] = asfTuple[0] + adfTupleBase[0];
                    adfTuple[1] = asfTuple[1] + adfTupleBase[1];
                    adfTuple[2] = asfTuple[2] + adfTupleBase[2];
                }

                poLR->setPoint( iPoint, adfTuple[0], adfTuple[1], adfTuple[2], adfTuple[3] );
                adfTupleBase[0] = adfTuple[0];
                adfTupleBase[1] = adfTuple[1];
                adfTupleBase[2] = adfTuple[2];
            }

            poPoly->addRingDirectly( poLR );
        }

        if( pnBytesConsumed )
            *pnBytesConsumed = nNextByte;
    }

/* -------------------------------------------------------------------- */
/*      GeometryCollections of various kinds.                           */
/* -------------------------------------------------------------------- */
    else if( ( nGType >= OGRSpliteMultiPointXY &&
               nGType <= OGRSpliteGeometryCollectionXY ) ||       // XY types
             ( nGType >= OGRSpliteMultiPointXYZ &&
               nGType <= OGRSpliteGeometryCollectionXYZ ) ||      // XYZ types
             ( nGType >= OGRSpliteMultiPointXYM &&
               nGType <= OGRSpliteGeometryCollectionXYM ) ||      // XYM types
             ( nGType >= OGRSpliteMultiPointXYZM &&
               nGType <= OGRSpliteGeometryCollectionXYZM ) ||     // XYZM types
             ( nGType >= OGRSpliteComprMultiLineStringXY &&
               nGType <= OGRSpliteComprGeometryCollectionXY ) ||  // XY compressed
             ( nGType >= OGRSpliteComprMultiLineStringXYZ &&
               nGType <= OGRSpliteComprGeometryCollectionXYZ ) || // XYZ compressed
             ( nGType >= OGRSpliteComprMultiLineStringXYM &&
               nGType <= OGRSpliteComprGeometryCollectionXYM ) || // XYM compressed
             ( nGType >= OGRSpliteComprMultiLineStringXYZM &&
               nGType <= OGRSpliteComprGeometryCollectionXYZM ) ) // XYZM compressed
    {
        if( nBytes < 8 )
            return OGRERR_NOT_ENOUGH_DATA;

        GInt32 nGeomCount = 0;
        memcpy( &nGeomCount, pabyData + 4, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nGeomCount );

        if (nGeomCount < 0 || nGeomCount > INT_MAX / 9)
            return OGRERR_CORRUPT_DATA;

        // Each sub geometry takes at least 9 bytes
        if (nBytes - 8 < nGeomCount * 9)
            return OGRERR_NOT_ENOUGH_DATA;

        int nBytesUsed = 8;
        OGRGeometryCollection *poGC = NULL;

        switch ( nGType )
        {
            case OGRSpliteMultiPointXY:
            case OGRSpliteMultiPointXYZ:
            case OGRSpliteMultiPointXYM:
            case OGRSpliteMultiPointXYZM:
                poGC = new OGRMultiPoint();
                break;
            case OGRSpliteMultiLineStringXY:
            case OGRSpliteMultiLineStringXYZ:
            case OGRSpliteMultiLineStringXYM:
            case OGRSpliteMultiLineStringXYZM:
            case OGRSpliteComprMultiLineStringXY:
            case OGRSpliteComprMultiLineStringXYZ:
            case OGRSpliteComprMultiLineStringXYM:
            case OGRSpliteComprMultiLineStringXYZM:
                poGC = new OGRMultiLineString();
                break;
            case OGRSpliteMultiPolygonXY:
            case OGRSpliteMultiPolygonXYZ:
            case OGRSpliteMultiPolygonXYM:
            case OGRSpliteMultiPolygonXYZM:
            case OGRSpliteComprMultiPolygonXY:
            case OGRSpliteComprMultiPolygonXYZ:
            case OGRSpliteComprMultiPolygonXYM:
            case OGRSpliteComprMultiPolygonXYZM:
                poGC = new OGRMultiPolygon();
                break;
            case OGRSpliteGeometryCollectionXY:
            case OGRSpliteGeometryCollectionXYZ:
            case OGRSpliteGeometryCollectionXYM:
            case OGRSpliteGeometryCollectionXYZM:
            case OGRSpliteComprGeometryCollectionXY:
            case OGRSpliteComprGeometryCollectionXYZ:
            case OGRSpliteComprGeometryCollectionXYM:
            case OGRSpliteComprGeometryCollectionXYZM:
                poGC = new OGRGeometryCollection();
                break;
        }

        assert(NULL != poGC);

        for( int iGeom = 0; iGeom < nGeomCount; iGeom++ )
        {
            OGRGeometry *poThisGeom = NULL;

            if (nBytes - nBytesUsed < 5)
            {
                delete poGC;
                return OGRERR_NOT_ENOUGH_DATA;
            }

            if (pabyData[nBytesUsed] != 0x69)
            {
                delete poGC;
                return OGRERR_CORRUPT_DATA;
            }

            nBytesUsed++;

            int nThisGeomSize = 0;
            OGRErr eErr =
                createFromSpatialiteInternal( pabyData + nBytesUsed,
                                              &poThisGeom, nBytes - nBytesUsed,
                                              eByteOrder, &nThisGeomSize,
                                              nRecLevel + 1);
            if( eErr != OGRERR_NONE )
            {
                delete poGC;
                return eErr;
            }

            nBytesUsed += nThisGeomSize;
            eErr = poGC->addGeometryDirectly( poThisGeom );
            if( eErr != OGRERR_NONE )
            {
                delete poGC;
                return eErr;
            }
        }

        poGeom = poGC;
        if( pnBytesConsumed )
            *pnBytesConsumed = nBytesUsed;
    }

/* -------------------------------------------------------------------- */
/*      Currently unsupported geometry.                                 */
/* -------------------------------------------------------------------- */
    else
    {
        return OGRERR_UNSUPPORTED_GEOMETRY_TYPE;
    }

    *ppoReturn = poGeom;
    return OGRERR_NONE;
}

/************************************************************************/
/*                     GetSpatialiteGeometryHeader()                    */
/************************************************************************/
typedef struct
{
    int                nSpliteType;
    OGRwkbGeometryType eGType;
} SpliteOGRGeometryTypeTuple;

static const SpliteOGRGeometryTypeTuple anTypesMap[] = {
{ OGRSplitePointXY, wkbPoint },
{ OGRSplitePointXYZ, wkbPoint25D },
{ OGRSplitePointXYM, wkbPointM },
{ OGRSplitePointXYZM, wkbPointZM },
{ OGRSpliteLineStringXY, wkbLineString },
{ OGRSpliteLineStringXYZ, wkbLineString25D },
{ OGRSpliteLineStringXYM, wkbLineStringM },
{ OGRSpliteLineStringXYZM, wkbLineStringZM },
{ OGRSpliteComprLineStringXY, wkbLineString },
{ OGRSpliteComprLineStringXYZ, wkbLineString25D },
{ OGRSpliteComprLineStringXYM, wkbLineStringM },
{ OGRSpliteComprLineStringXYZM, wkbLineStringZM },
{ OGRSplitePolygonXY, wkbPolygon },
{ OGRSplitePolygonXYZ, wkbPolygon25D },
{ OGRSplitePolygonXYM, wkbPolygonM },
{ OGRSplitePolygonXYZM, wkbPolygonZM },
{ OGRSpliteComprPolygonXY, wkbPolygon },
{ OGRSpliteComprPolygonXYZ, wkbPolygon25D },
{ OGRSpliteComprPolygonXYM, wkbPolygonM },
{ OGRSpliteComprPolygonXYZM, wkbPolygonZM },

{ OGRSpliteMultiPointXY, wkbMultiPoint },
{ OGRSpliteMultiPointXYZ, wkbMultiPoint25D },
{ OGRSpliteMultiPointXYM, wkbMultiPointM },
{ OGRSpliteMultiPointXYZM, wkbMultiPointZM },
{ OGRSpliteMultiLineStringXY, wkbMultiLineString },
{ OGRSpliteMultiLineStringXYZ, wkbMultiLineString25D },
{ OGRSpliteMultiLineStringXYM, wkbMultiLineStringM },
{ OGRSpliteMultiLineStringXYZM, wkbMultiLineStringZM },
{ OGRSpliteComprMultiLineStringXY, wkbMultiLineString },
{ OGRSpliteComprMultiLineStringXYZ, wkbMultiLineString25D },
{ OGRSpliteComprMultiLineStringXYM, wkbMultiLineStringM },
{ OGRSpliteComprMultiLineStringXYZM, wkbMultiLineStringZM },
{ OGRSpliteMultiPolygonXY, wkbMultiPolygon },
{ OGRSpliteMultiPolygonXYZ, wkbMultiPolygon25D },
{ OGRSpliteMultiPolygonXYM, wkbMultiPolygonM },
{ OGRSpliteMultiPolygonXYZM, wkbMultiPolygonZM },
{ OGRSpliteComprMultiPolygonXY, wkbMultiPolygon },
{ OGRSpliteComprMultiPolygonXYZ, wkbMultiPolygon25D },
{ OGRSpliteComprMultiPolygonXYM, wkbMultiPolygonM },
{ OGRSpliteComprMultiPolygonXYZM, wkbMultiPolygonZM },

{ OGRSpliteGeometryCollectionXY, wkbGeometryCollection },
{ OGRSpliteGeometryCollectionXYZ, wkbGeometryCollection25D },
{ OGRSpliteGeometryCollectionXYM, wkbGeometryCollectionM },
{ OGRSpliteGeometryCollectionXYZM, wkbGeometryCollectionZM },
{ OGRSpliteComprGeometryCollectionXY, wkbGeometryCollection },
{ OGRSpliteComprGeometryCollectionXYZ, wkbGeometryCollection25D },
{ OGRSpliteComprGeometryCollectionXYM, wkbGeometryCollectionM },
{ OGRSpliteComprGeometryCollectionXYZM, wkbGeometryCollectionZM },
};

OGRErr OGRSQLiteLayer::GetSpatialiteGeometryHeader( const GByte *pabyData,
                                                    int nBytes,
                                                    int* pnSRID,
                                                    OGRwkbGeometryType* peType,
                                                    bool* pbIsEmpty,
                                                    double* pdfMinX,
                                                    double* pdfMinY,
                                                    double* pdfMaxX,
                                                    double* pdfMaxY )
{
    if( nBytes < 44
        || pabyData[0] != 0
        || pabyData[38] != 0x7C
        || pabyData[nBytes-1] != 0xFE )
        return OGRERR_CORRUPT_DATA;

    OGRwkbByteOrder eByteOrder = (OGRwkbByteOrder) pabyData[1];

    if( pnSRID != NULL )
    {
        int nSRID = 0;
        memcpy( &nSRID, pabyData + 2, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nSRID );
        *pnSRID = nSRID;
    }

    if( peType != NULL || pbIsEmpty != NULL )
    {
        OGRwkbGeometryType eGType = wkbUnknown;
        int nSpliteType = 0;
        memcpy( &nSpliteType, pabyData + 39, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nSpliteType );
        for( size_t i = 0; i < CPL_ARRAYSIZE(anTypesMap); ++i )
        {
            if( anTypesMap[i].nSpliteType == nSpliteType )
            {
                eGType = anTypesMap[i].eGType;
                break;
            }
        }
        if( peType != NULL )
            *peType = eGType;
        if( pbIsEmpty != NULL )
        {
            *pbIsEmpty = false;
            if ( wkbFlatten(eGType) != wkbPoint &&
                 nBytes >= 44 + 4 )
            {
                int nCount = 0;
                memcpy( &nSpliteType, pabyData + 43, 4 );
                if (NEED_SWAP_SPATIALITE())
                    CPL_SWAP32PTR( &nCount );
                *pbIsEmpty = (nCount == 0);
            }
        }
    }

    if( pdfMinX != NULL )
    {
        double dfMinX = 0.0;
        memcpy( &dfMinX, pabyData + 6, 8 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP64PTR( &dfMinX );
        *pdfMinX = dfMinX;
    }

    if( pdfMinY != NULL )
    {
        double dfMinY = 0.0;
        memcpy( &dfMinY, pabyData + 14, 8 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP64PTR( &dfMinY );
        *pdfMinY = dfMinY;
    }


    if( pdfMaxX != NULL )
    {
        double dfMaxX = 0.0;
        memcpy( &dfMaxX, pabyData + 22, 8 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP64PTR( &dfMaxX );
        *pdfMaxX = dfMaxX;
    }

    if( pdfMaxY != NULL )
    {
        double dfMaxY = 0.0;
        memcpy( &dfMaxY, pabyData + 30, 8 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP64PTR( &dfMaxY );
        *pdfMaxY = dfMaxY;
    }

    return OGRERR_NONE;
}

/************************************************************************/
/*                      ImportSpatiaLiteGeometry()                      */
/************************************************************************/

OGRErr OGRSQLiteLayer::ImportSpatiaLiteGeometry( const GByte *pabyData,
                                                 int nBytes,
                                                 OGRGeometry **ppoGeometry )

{
    return ImportSpatiaLiteGeometry(pabyData, nBytes, ppoGeometry, NULL);
}

/************************************************************************/
/*                      ImportSpatiaLiteGeometry()                      */
/************************************************************************/

OGRErr OGRSQLiteLayer::ImportSpatiaLiteGeometry( const GByte *pabyData,
                                                 int nBytes,
                                                 OGRGeometry **ppoGeometry,
                                                 int* pnSRID )

{
    OGRwkbByteOrder eByteOrder;

    *ppoGeometry = NULL;

    if( nBytes < 44
        || pabyData[0] != 0
        || pabyData[38] != 0x7C
        || pabyData[nBytes-1] != 0xFE )
        return OGRERR_CORRUPT_DATA;

    eByteOrder = (OGRwkbByteOrder) pabyData[1];

/* -------------------------------------------------------------------- */
/*      Decode the geometry type.                                       */
/* -------------------------------------------------------------------- */
    if( pnSRID != NULL )
    {
        int nSRID = 0;
        memcpy( &nSRID, pabyData + 2, 4 );
        if (NEED_SWAP_SPATIALITE())
            CPL_SWAP32PTR( &nSRID );
        *pnSRID = nSRID;
    }

    int nBytesConsumed = 0;
    OGRErr eErr = createFromSpatialiteInternal(pabyData + 39, ppoGeometry,
                                        nBytes - 39, eByteOrder, &nBytesConsumed, 0);
    if( eErr == OGRERR_NONE )
    {
        /* This is a hack: in OGR2SQLITE_ExportGeometry(), we may have added */
        /* the original curve geometry after the spatialite blob, so in case */
        /* we detect that there's still binary */
        /* content after the spatialite blob, this may be our original geometry */
        if( 39 + nBytesConsumed + 1 < nBytes && pabyData[39 + nBytesConsumed] == 0xFE )
        {
            OGRGeometry* poOriginalGeometry = NULL;
            eErr = OGRGeometryFactory::createFromWkb(
                    (unsigned char*)(pabyData + 39 + nBytesConsumed + 1),
                    NULL, &poOriginalGeometry, nBytes - (39 + nBytesConsumed + 1 + 1));
            delete *ppoGeometry;
            if( eErr == OGRERR_NONE )
            {
                *ppoGeometry = poOriginalGeometry;
            }
            else
            {
                *ppoGeometry = NULL;
            }
        }
    }
    return eErr;
}

/************************************************************************/
/*                CanBeCompressedSpatialiteGeometry()                   */
/************************************************************************/

int OGRSQLiteLayer::CanBeCompressedSpatialiteGeometry(const OGRGeometry *poGeometry)
{
    switch (wkbFlatten(poGeometry->getGeometryType()))
    {
        case wkbLineString:
        case wkbLinearRing:
        {
            int nPoints = ((OGRLineString*)poGeometry)->getNumPoints();
            return nPoints >= 2;
        }

        case wkbPolygon:
        {
            OGRPolygon* poPoly = (OGRPolygon*) poGeometry;
            if (poPoly->getExteriorRing() != NULL)
            {
                if (!CanBeCompressedSpatialiteGeometry(poPoly->getExteriorRing()))
                    return FALSE;

                int nInteriorRingCount = poPoly->getNumInteriorRings();
                for(int i=0;i<nInteriorRingCount;i++)
                {
                    if (!CanBeCompressedSpatialiteGeometry(poPoly->getInteriorRing(i)))
                        return FALSE;
                }
            }
            return TRUE;
        }

        case wkbMultiPoint:
        case wkbMultiLineString:
        case wkbMultiPolygon:
        case wkbGeometryCollection:
        {
            OGRGeometryCollection* poGeomCollection = (OGRGeometryCollection*) poGeometry;
            int nParts = poGeomCollection->getNumGeometries();
            for(int i=0;i<nParts;i++)
            {
                if (!CanBeCompressedSpatialiteGeometry(poGeomCollection->getGeometryRef(i)))
                    return FALSE;
            }
            return TRUE;
        }

        default:
            return FALSE;
    }
}

/************************************************************************/
/*                  ComputeSpatiaLiteGeometrySize()                     */
/************************************************************************/

int OGRSQLiteLayer::ComputeSpatiaLiteGeometrySize(const OGRGeometry *poGeometry,
                                                  int bSpatialite2D,
                                                  int bUseComprGeom)
{
    switch (wkbFlatten(poGeometry->getGeometryType()))
    {
        case wkbPoint:
            if ( bSpatialite2D == TRUE )
                return 16;
            return 8 * poGeometry->CoordinateDimension();

        case wkbLineString:
        case wkbLinearRing:
        {
            int nPoints = ((OGRLineString*)poGeometry)->getNumPoints();
            int nDimension = 2;
            int nPointsDouble = nPoints;
            int nPointsFloat = 0;
            bool bHasM = CPL_TO_BOOL(poGeometry->IsMeasured());
            if ( bSpatialite2D == TRUE )
            {
                // nDimension = 2;
                bHasM = false;
            }
            else
            {
                if ( bUseComprGeom && nPoints >= 2 )
                {
                    nPointsDouble = 2;
                    nPointsFloat = nPoints - 2;
                }
                nDimension = poGeometry->Is3D() ? 3 : 2;
            }
            return
                4 +
                nDimension * (8 * nPointsDouble + 4 * nPointsFloat) +
                (bHasM ? nPoints * 8 : 0);
        }

        case wkbPolygon:
        {
            int nSize = 4;
            OGRPolygon* poPoly = (OGRPolygon*) poGeometry;
            bUseComprGeom = bUseComprGeom && !bSpatialite2D && CanBeCompressedSpatialiteGeometry(poGeometry);
            if (poPoly->getExteriorRing() != NULL)
            {
                nSize += ComputeSpatiaLiteGeometrySize(poPoly->getExteriorRing(),
                                                       bSpatialite2D, bUseComprGeom);

                int nInteriorRingCount = poPoly->getNumInteriorRings();
                for(int i=0;i<nInteriorRingCount;i++)
                    nSize += ComputeSpatiaLiteGeometrySize(poPoly->getInteriorRing(i),
                                                           bSpatialite2D, bUseComprGeom );
            }
            return nSize;
        }

        case wkbMultiPoint:
        case wkbMultiLineString:
        case wkbMultiPolygon:
        case wkbGeometryCollection:
        {
            int nSize = 4;
            OGRGeometryCollection* poGeomCollection = (OGRGeometryCollection*) poGeometry;
            int nParts = poGeomCollection->getNumGeometries();
            for(int i=0;i<nParts;i++)
                nSize += 5 + ComputeSpatiaLiteGeometrySize(poGeomCollection->getGeometryRef(i),
                                                           bSpatialite2D, bUseComprGeom );
            return nSize;
        }

        default:
        {
            CPLError(CE_Failure, CPLE_AppDefined,
                     "Unexpected geometry type: %s",
                     OGRToOGCGeomType(poGeometry->getGeometryType()));
            return 0;
        }
    }
}

/************************************************************************/
/*                    GetSpatialiteGeometryCode()                       */
/************************************************************************/

int OGRSQLiteLayer::GetSpatialiteGeometryCode(const OGRGeometry *poGeometry,
                                              int bSpatialite2D,
                                              int bUseComprGeom,
                                              int bAcceptMultiGeom)
{
    OGRwkbGeometryType eType = wkbFlatten(poGeometry->getGeometryType());
    switch (eType)
    {
        case wkbPoint:
            if ( bSpatialite2D == TRUE )
                return OGRSplitePointXY;
            else if (poGeometry->Is3D())
            {
                if (poGeometry->IsMeasured())
                    return OGRSplitePointXYZM;
                else
                    return OGRSplitePointXYZ;
             }
             else
             {
                if (poGeometry->IsMeasured())
                    return OGRSplitePointXYM;
                else
                    return OGRSplitePointXY;
            }
            break;

        case wkbLineString:
        case wkbLinearRing:
            if ( bSpatialite2D == TRUE )
                return OGRSpliteLineStringXY;
            else if (poGeometry->Is3D())
            {
                if (poGeometry->IsMeasured())
                    return (bUseComprGeom) ? OGRSpliteComprLineStringXYZM : OGRSpliteLineStringXYZM;
                else
                    return (bUseComprGeom) ? OGRSpliteComprLineStringXYZ : OGRSpliteLineStringXYZ;
            }
            else
            {
                if (poGeometry->IsMeasured())
                    return (bUseComprGeom) ? OGRSpliteComprLineStringXYM : OGRSpliteLineStringXYM;
                else
                    return (bUseComprGeom) ? OGRSpliteComprLineStringXY : OGRSpliteLineStringXY;
            }
            break;

        case wkbPolygon:
            if ( bSpatialite2D == TRUE )
                return OGRSplitePolygonXY;
            else if (poGeometry->Is3D())
            {
                if (poGeometry->IsMeasured())
                    return (bUseComprGeom) ? OGRSpliteComprPolygonXYZM : OGRSplitePolygonXYZM;
                else
                    return (bUseComprGeom) ? OGRSpliteComprPolygonXYZ : OGRSplitePolygonXYZ;
            }
            else
            {
                if (poGeometry->IsMeasured())
                    return (bUseComprGeom) ? OGRSpliteComprPolygonXYM : OGRSplitePolygonXYM;
                else
                    return (bUseComprGeom) ? OGRSpliteComprPolygonXY : OGRSplitePolygonXY;
            }
            break;

        default:
            break;
    }

    if (!bAcceptMultiGeom)
    {
        CPLError(CE_Failure, CPLE_AppDefined, "Unexpected geometry type");
        return 0;
    }

    switch (eType)
    {
        case wkbMultiPoint:
            if ( bSpatialite2D == TRUE )
                return OGRSpliteMultiPointXY;
            else if (poGeometry->Is3D())
            {
                if (poGeometry->IsMeasured())
                    return OGRSpliteMultiPointXYZM;
                else
                    return OGRSpliteMultiPointXYZ;
            }
            else
            {
                if (poGeometry->IsMeasured())
                    return OGRSpliteMultiPointXYM;
                else
                    return OGRSpliteMultiPointXY;
            }
            break;

        case wkbMultiLineString:
            if ( bSpatialite2D == TRUE )
                return OGRSpliteMultiLineStringXY;
            else if (poGeometry->Is3D())
            {
                if (poGeometry->IsMeasured())
                    return /*(bUseComprGeom) ? OGRSpliteComprMultiLineStringXYZM :*/ OGRSpliteMultiLineStringXYZM;
                else
                    return /*(bUseComprGeom) ? OGRSpliteComprMultiLineStringXYZ :*/ OGRSpliteMultiLineStringXYZ;
            }
            else
            {
                if (poGeometry->IsMeasured())
                    return /*(bUseComprGeom) ? OGRSpliteComprMultiLineStringXYM :*/ OGRSpliteMultiLineStringXYM;
                else
                    return /*(bUseComprGeom) ? OGRSpliteComprMultiLineStringXY :*/ OGRSpliteMultiLineStringXY;
            }
            break;

        case wkbMultiPolygon:
            if ( bSpatialite2D == TRUE )
                return OGRSpliteMultiPolygonXY;
            else if (poGeometry->Is3D())
            {
                if (poGeometry->IsMeasured())
                    return /*(bUseComprGeom) ? OGRSpliteComprMultiPolygonXYZM :*/ OGRSpliteMultiPolygonXYZM;
                else
                    return /*(bUseComprGeom) ? OGRSpliteComprMultiPolygonXYZ :*/ OGRSpliteMultiPolygonXYZ;
            }
            else
            {
                if (poGeometry->IsMeasured())
                    return /*(bUseComprGeom) ? OGRSpliteComprMultiPolygonXYM :*/ OGRSpliteMultiPolygonXYM;
                else
                    return /*(bUseComprGeom) ? OGRSpliteComprMultiPolygonXY :*/ OGRSpliteMultiPolygonXY;
            }
            break;

        case wkbGeometryCollection:
            if ( bSpatialite2D == TRUE )
                return OGRSpliteGeometryCollectionXY;
            else if (poGeometry->Is3D())
            {
                if (poGeometry->IsMeasured())
                    return /*(bUseComprGeom) ? OGRSpliteComprGeometryCollectionXYZM :*/ OGRSpliteGeometryCollectionXYZM;
                else
                    return /*(bUseComprGeom) ? OGRSpliteComprGeometryCollectionXYZ :*/ OGRSpliteGeometryCollectionXYZ;
            }
            else
            {
                if (poGeometry->IsMeasured())
                    return /*(bUseComprGeom) ? OGRSpliteComprGeometryCollectionXYM :*/ OGRSpliteGeometryCollectionXYM;
                else
                    return /*(bUseComprGeom) ? OGRSpliteComprGeometryCollectionXY :*/ OGRSpliteGeometryCollectionXY;
            }
            break;

        default:
            CPLError(CE_Failure, CPLE_AppDefined, "Unexpected geometry type");
            return 0;
    }
}

/************************************************************************/
/*                    ExportSpatiaLiteGeometryInternal()                */
/************************************************************************/

int OGRSQLiteLayer::ExportSpatiaLiteGeometryInternal(const OGRGeometry *poGeometry,
                                                     OGRwkbByteOrder eByteOrder,
                                                     int bSpatialite2D,
                                                     int bUseComprGeom,
                                                     GByte* pabyData )
{
    switch (wkbFlatten(poGeometry->getGeometryType()))
    {
        case wkbPoint:
        {
            OGRPoint* poPoint = (OGRPoint*) poGeometry;
            double x = poPoint->getX();
            double y = poPoint->getY();
            memcpy(pabyData, &x, 8);
            memcpy(pabyData + 8, &y, 8);
            if (NEED_SWAP_SPATIALITE())
            {
                CPL_SWAP64PTR( pabyData );
                CPL_SWAP64PTR( pabyData + 8 );
            }
            if ( bSpatialite2D == TRUE )
                return 16;
            else if (poGeometry->Is3D())
            {
                double z = poPoint->getZ();
                memcpy(pabyData + 16, &z, 8);
                if (NEED_SWAP_SPATIALITE())
                    CPL_SWAP64PTR( pabyData + 16 );
                if( poGeometry->IsMeasured() )
                {
                    double m = poPoint->getM();
                    memcpy(pabyData + 24, &m, 8);
                    if (NEED_SWAP_SPATIALITE())
                        CPL_SWAP64PTR( pabyData + 24 );
                    return 32;
                }
                else
                    return 24;
            }
            else
            {
                if( poGeometry->IsMeasured() )
                {
                    double m = poPoint->getM();
                    memcpy(pabyData + 16, &m, 8);
                    if (NEED_SWAP_SPATIALITE())
                        CPL_SWAP64PTR( pabyData + 16 );
                    return 24;
                }
                else
                    return 16;
            }
        }

        case wkbLineString:
        case wkbLinearRing:
        {
            OGRLineString* poLineString = (OGRLineString*) poGeometry;
            int nTotalSize = 4;
            int nPointCount = poLineString->getNumPoints();
            memcpy(pabyData, &nPointCount, 4);
            if (NEED_SWAP_SPATIALITE())
                CPL_SWAP32PTR( pabyData );

            if( !bUseComprGeom && !NEED_SWAP_SPATIALITE() &&
                poGeometry->CoordinateDimension() == 2 )
            {
                poLineString->getPoints((OGRRawPoint*)(pabyData + 4), NULL);
                nTotalSize += nPointCount * 16;
                return nTotalSize;
            }

            for(int i=0;i<nPointCount;i++)
            {
                double x = poLineString->getX(i);
                double y = poLineString->getY(i);

                if (!bUseComprGeom || i == 0 || i == nPointCount - 1)
                {
                    memcpy(pabyData + nTotalSize, &x, 8);
                    memcpy(pabyData + nTotalSize + 8, &y, 8);
                    if (NEED_SWAP_SPATIALITE())
                    {
                        CPL_SWAP64PTR( pabyData + nTotalSize );
                        CPL_SWAP64PTR( pabyData + nTotalSize + 8 );
                    }
                    if (!bSpatialite2D && poGeometry->Is3D())
                    {
                        double z = poLineString->getZ(i);
                        memcpy(pabyData + nTotalSize + 16, &z, 8);
                        if (NEED_SWAP_SPATIALITE())
                            CPL_SWAP64PTR( pabyData + nTotalSize + 16 );
                        if( poGeometry->IsMeasured() )
                        {
                            double m = poLineString->getM(i);
                            memcpy(pabyData + nTotalSize + 24, &m, 8);
                            if (NEED_SWAP_SPATIALITE())
                                CPL_SWAP64PTR( pabyData + nTotalSize + 24 );
                            nTotalSize += 32;
                        }
                        else
                            nTotalSize += 24;
                    }
                    else
                    {
                        if( poGeometry->IsMeasured() )
                        {
                            double m = poLineString->getM(i);
                            memcpy(pabyData + nTotalSize + 16, &m, 8);
                            if (NEED_SWAP_SPATIALITE())
                                CPL_SWAP64PTR( pabyData + nTotalSize + 16 );
                            nTotalSize += 24;
                        }
                        else
                            nTotalSize += 16;
                    }
                }
                else /* Compressed intermediate points */
                {
                    float deltax = (float)(x - poLineString->getX(i-1));
                    float deltay = (float)(y - poLineString->getY(i-1));
                    memcpy(pabyData + nTotalSize, &deltax, 4);
                    memcpy(pabyData + nTotalSize + 4, &deltay, 4);
                    if (NEED_SWAP_SPATIALITE())
                    {
                        CPL_SWAP32PTR( pabyData + nTotalSize );
                        CPL_SWAP32PTR( pabyData + nTotalSize + 4 );
                    }
                    if (poGeometry->Is3D())
                    {
                        double z = poLineString->getZ(i);
                        float deltaz = (float)(z - poLineString->getZ(i-1));
                        memcpy(pabyData + nTotalSize + 8, &deltaz, 4);
                        if (NEED_SWAP_SPATIALITE())
                            CPL_SWAP32PTR( pabyData + nTotalSize + 8 );
                        if( poGeometry->IsMeasured() )
                        {
                            double m = poLineString->getM(i);
                            memcpy(pabyData + nTotalSize + 12, &m, 8);
                            if (NEED_SWAP_SPATIALITE())
                                CPL_SWAP64PTR( pabyData + nTotalSize + 12 );
                            nTotalSize += 20;
                        }
                        else
                            nTotalSize += 12;
                    }
                    else
                    {
                        if( poGeometry->IsMeasured() )
                        {
                            double m = poLineString->getM(i);
                            memcpy(pabyData + nTotalSize + 8, &m, 8);
                            if (NEED_SWAP_SPATIALITE())
                                CPL_SWAP64PTR( pabyData + nTotalSize + 8 );
                            nTotalSize += 16;
                        }
                        else
                            nTotalSize += 8;
                    }
                }
            }
            return nTotalSize;
        }

        case wkbPolygon:
        {
            OGRPolygon* poPoly = (OGRPolygon*) poGeometry;
            int nParts = 0;
            int nTotalSize = 4;
            if (poPoly->getExteriorRing() != NULL)
            {
                int nInteriorRingCount = poPoly->getNumInteriorRings();
                nParts = 1 + nInteriorRingCount;
                memcpy(pabyData, &nParts, 4);
                if (NEED_SWAP_SPATIALITE())
                    CPL_SWAP32PTR( pabyData );

                nTotalSize += ExportSpatiaLiteGeometryInternal(poPoly->getExteriorRing(),
                                                              eByteOrder,
                                                              bSpatialite2D,
                                                              bUseComprGeom,
                                                              pabyData + nTotalSize);

                for(int i=0;i<nInteriorRingCount;i++)
                {
                    nTotalSize += ExportSpatiaLiteGeometryInternal(poPoly->getInteriorRing(i),
                                                                   eByteOrder,
                                                                   bSpatialite2D,
                                                                   bUseComprGeom,
                                                                   pabyData + nTotalSize);
                }
            }
            else
            {
                memset(pabyData, 0, 4);
            }
            return nTotalSize;
        }

        case wkbMultiPoint:
        case wkbMultiLineString:
        case wkbMultiPolygon:
        case wkbGeometryCollection:
        {
            OGRGeometryCollection* poGeomCollection = (OGRGeometryCollection*) poGeometry;
            int nTotalSize = 4;
            int nParts = poGeomCollection->getNumGeometries();
            memcpy(pabyData, &nParts, 4);
            if (NEED_SWAP_SPATIALITE())
                CPL_SWAP32PTR( pabyData );

            for(int i=0;i<nParts;i++)
            {
                pabyData[nTotalSize] = 0x69;
                nTotalSize ++;
                int nCode = GetSpatialiteGeometryCode(poGeomCollection->getGeometryRef(i),
                                                      bSpatialite2D,
                                                      bUseComprGeom, FALSE);
                if (nCode == 0)
                    return 0;
                memcpy(pabyData + nTotalSize, &nCode, 4);
                if (NEED_SWAP_SPATIALITE())
                    CPL_SWAP32PTR( pabyData + nTotalSize );
                nTotalSize += 4;
                nTotalSize += ExportSpatiaLiteGeometryInternal(poGeomCollection->getGeometryRef(i),
                                                               eByteOrder,
                                                               bSpatialite2D,
                                                               bUseComprGeom,
                                                               pabyData + nTotalSize);
            }
            return nTotalSize;
        }

        default:
            return 0;
    }
}

OGRErr OGRSQLiteLayer::ExportSpatiaLiteGeometry( const OGRGeometry *poGeometry,
                                                 GInt32 nSRID,
                                                 OGRwkbByteOrder eByteOrder,
                                                 int bSpatialite2D,
                                                 int bUseComprGeom,
                                                 GByte **ppabyData,
                                                 int *pnDataLength )

{
    /* Spatialite does not support curve geometries */
    const OGRGeometry* poWorkGeom = poGeometry->hasCurveGeometry()
        ? poGeometry->getLinearGeometry()
        :  poGeometry;

    bUseComprGeom = bUseComprGeom && !bSpatialite2D && CanBeCompressedSpatialiteGeometry(poWorkGeom);

    const int nGeomSize = ComputeSpatiaLiteGeometrySize( poWorkGeom,
                                                         bSpatialite2D,
                                                         bUseComprGeom );
    if( nGeomSize == 0 )
    {
        *ppabyData = NULL;
        *pnDataLength = 0;
        return OGRERR_FAILURE;
    }
    const int nDataLen = 44 + nGeomSize;
    OGREnvelope sEnvelope;

    *ppabyData =  (GByte *) CPLMalloc( nDataLen );

    (*ppabyData)[0] = 0x00;
    (*ppabyData)[1] = (GByte) eByteOrder;

    // Write out SRID
    memcpy( *ppabyData + 2, &nSRID, 4 );

    // Write out the geometry bounding rectangle
    poGeometry->getEnvelope( &sEnvelope );
    memcpy( *ppabyData + 6, &sEnvelope.MinX, 8 );
    memcpy( *ppabyData + 14, &sEnvelope.MinY, 8 );
    memcpy( *ppabyData + 22, &sEnvelope.MaxX, 8 );
    memcpy( *ppabyData + 30, &sEnvelope.MaxY, 8 );

    (*ppabyData)[38] = 0x7C;

    int nCode = GetSpatialiteGeometryCode(poWorkGeom,
                                          bSpatialite2D,
                                          bUseComprGeom, TRUE);
    if (nCode == 0)
    {
        CPLFree(*ppabyData);
        *ppabyData = NULL;
        *pnDataLength = 0;
        if( poWorkGeom != poGeometry ) delete poWorkGeom;
        return OGRERR_FAILURE;
    }
    memcpy( *ppabyData + 39, &nCode, 4 );

    int nWritten = ExportSpatiaLiteGeometryInternal(poWorkGeom,
                                                    eByteOrder,
                                                    bSpatialite2D,
                                                    bUseComprGeom,
                                                    *ppabyData + 43);
    if( poWorkGeom != poGeometry ) delete poWorkGeom;

    if (nWritten == 0)
    {
        CPLFree(*ppabyData);
        *ppabyData = NULL;
        *pnDataLength = 0;
        return OGRERR_FAILURE;
    }

    (*ppabyData)[nDataLen - 1] = 0xFE;

    if( NEED_SWAP_SPATIALITE() )
    {
        CPL_SWAP32PTR( *ppabyData + 2 );
        CPL_SWAP64PTR( *ppabyData + 6 );
        CPL_SWAP64PTR( *ppabyData + 14 );
        CPL_SWAP64PTR( *ppabyData + 22 );
        CPL_SWAP64PTR( *ppabyData + 30 );
        CPL_SWAP32PTR( *ppabyData + 39 );
    }

    *pnDataLength = nDataLen;

    return OGRERR_NONE;
}

/************************************************************************/
/*                           TestCapability()                           */
/************************************************************************/

int OGRSQLiteLayer::TestCapability( const char * pszCap )

{
    if( EQUAL(pszCap,OLCRandomRead) )
        return FALSE;

    else if( EQUAL(pszCap,OLCFastFeatureCount) )
        return FALSE;

    else if( EQUAL(pszCap,OLCFastSpatialFilter) )
        return FALSE;

    else if( EQUAL(pszCap,OLCIgnoreFields) )
        return TRUE;

    else if( EQUAL(pszCap,OLCTransactions) )
        return TRUE;

    else
        return FALSE;
}

/************************************************************************/
/*                          StartTransaction()                          */
/************************************************************************/

OGRErr OGRSQLiteLayer::StartTransaction()

{
    return poDS->StartTransaction();
}

/************************************************************************/
/*                         CommitTransaction()                          */
/************************************************************************/

OGRErr OGRSQLiteLayer::CommitTransaction()

{
    return poDS->CommitTransaction();
}

/************************************************************************/
/*                        RollbackTransaction()                         */
/************************************************************************/

OGRErr OGRSQLiteLayer::RollbackTransaction()

{
    return poDS->RollbackTransaction();
}

/************************************************************************/
/*                           ClearStatement()                           */
/************************************************************************/

void OGRSQLiteLayer::ClearStatement()

{
    if( hStmt != NULL )
    {
#ifdef DEBUG_VERBOSE
        CPLDebug( "OGR_SQLITE", "finalize %p", hStmt );
#endif
        sqlite3_finalize( hStmt );
        hStmt = NULL;
    }
}

/************************************************************************/
/*                    OGRSQLITEStringToDateTimeField()                  */
/************************************************************************/

int OGRSQLITEStringToDateTimeField( OGRFeature* poFeature, int iField,
                                    const char* pszValue )
{
    int nYear = 0;
    int nMonth = 0;
    int nDay = 0;
    int nHour = 0;
    int nMinute = 0;
    float fSecond = 0.0f;

    /* YYYY-MM-DD HH:MM:SS.SSS */
    if( sscanf(pszValue, "%04d-%02d-%02d %02d:%02d:%f",
                &nYear, &nMonth, &nDay, &nHour, &nMinute, &fSecond) == 6 ||
        sscanf(pszValue, "%04d/%02d/%02d %02d:%02d:%f",
                &nYear, &nMonth, &nDay, &nHour, &nMinute, &fSecond) == 6 ||
        sscanf(pszValue, "%04d-%02d-%02dT%02d:%02d:%f",
                &nYear, &nMonth, &nDay, &nHour, &nMinute, &fSecond) == 6 )
    {
        if( poFeature )
            poFeature->SetField( iField, nYear, nMonth,
                                 nDay, nHour, nMinute, fSecond, 0);
        return OFTDateTime;
    }

    /* YYYY-MM-DD HH:MM */
    if( sscanf(pszValue, "%04d-%02d-%02d %02d:%02d",
                &nYear, &nMonth, &nDay, &nHour, &nMinute) == 5 ||
        sscanf(pszValue, "%04d/%02d/%02d %02d:%02d",
                &nYear, &nMonth, &nDay, &nHour, &nMinute) == 5 ||
        sscanf(pszValue, "%04d-%02d-%02dT%02d:%02d",
                &nYear, &nMonth, &nDay, &nHour, &nMinute) == 5 )
    {
        if( poFeature )
            poFeature->SetField( iField, nYear, nMonth,
                                 nDay, nHour, nMinute, 0, 0);
        return OFTDateTime;
    }

    /* YYYY-MM-DD */
    if( sscanf(pszValue, "%04d-%02d-%02d",
                &nYear, &nMonth, &nDay) == 3 ||
        sscanf(pszValue, "%04d/%02d/%02d",
                &nYear, &nMonth, &nDay) == 3 )
    {
        if( poFeature )
            poFeature->SetField( iField, nYear, nMonth, nDay,
                                 0, 0, 0, 0 );
        return OFTDate;
    }

    /*  HH:MM:SS.SSS */
    if( sscanf(pszValue, "%02d:%02d:%f",
        &nHour, &nMinute, &fSecond) == 3 )
    {
        if( poFeature )
            poFeature->SetField( iField, 0, 0, 0,
                                 nHour, nMinute, fSecond, 0 );
        return OFTTime;
    }

    /*  HH:MM */
    if( sscanf(pszValue, "%02d:%02d", &nHour, &nMinute) == 2 )
    {
        if( poFeature )
            poFeature->SetField( iField, 0, 0, 0,
                                 nHour, nMinute, 0, 0 );
        return OFTTime;
    }

    return FALSE;
}

/************************************************************************/
/*                     FormatSpatialFilterFromRTree()                   */
/************************************************************************/

CPLString OGRSQLiteLayer::FormatSpatialFilterFromRTree(OGRGeometry* poFilterGeom,
                                                       const char* pszRowIDName,
                                                       const char* pszEscapedTable,
                                                       const char* pszEscapedGeomCol)
{
    CPLString osSpatialWHERE;
    OGREnvelope  sEnvelope;

    poFilterGeom->getEnvelope( &sEnvelope );

    if( CPLIsInf(sEnvelope.MinX) && sEnvelope.MinX < 0 &&
        CPLIsInf(sEnvelope.MinY) && sEnvelope.MinY < 0 &&
        CPLIsInf(sEnvelope.MaxX) && sEnvelope.MaxX > 0 &&
        CPLIsInf(sEnvelope.MaxY) && sEnvelope.MaxY > 0 )
        return "";

    osSpatialWHERE.Printf("%s IN ( SELECT pkid FROM 'idx_%s_%s' WHERE "
                    "xmax >= %.12f AND xmin <= %.12f AND ymax >= %.12f AND ymin <= %.12f)",
                    pszRowIDName,
                    pszEscapedTable,
                    pszEscapedGeomCol,
                    sEnvelope.MinX - 1e-11,
                    sEnvelope.MaxX + 1e-11,
                    sEnvelope.MinY - 1e-11,
                    sEnvelope.MaxY + 1e-11);

    return osSpatialWHERE;
}

/************************************************************************/
/*                     FormatSpatialFilterFromMBR()                     */
/************************************************************************/

CPLString OGRSQLiteLayer::FormatSpatialFilterFromMBR(OGRGeometry* poFilterGeom,
                                                     const char* pszEscapedGeomColName)
{
    CPLString osSpatialWHERE;
    OGREnvelope  sEnvelope;

    poFilterGeom->getEnvelope( &sEnvelope );

    if( CPLIsInf(sEnvelope.MinX) && sEnvelope.MinX < 0 &&
        CPLIsInf(sEnvelope.MinY) && sEnvelope.MinY < 0 &&
        CPLIsInf(sEnvelope.MaxX) && sEnvelope.MaxX > 0 &&
        CPLIsInf(sEnvelope.MaxY) && sEnvelope.MaxY > 0 )
        return "";

    /* A bit inefficient but still faster than OGR filtering */
    osSpatialWHERE.Printf("MBRIntersects(\"%s\", BuildMBR(%.12f, %.12f, %.12f, %.12f))",
                    pszEscapedGeomColName,
                    // Insure that only Decimal.Points are used, never local settings such as Decimal.Comma.
                    sEnvelope.MinX - 1e-11,
                    sEnvelope.MinY - 1e-11,
                    sEnvelope.MaxX + 1e-11,
                    sEnvelope.MaxY + 1e-11);

    return osSpatialWHERE;
}

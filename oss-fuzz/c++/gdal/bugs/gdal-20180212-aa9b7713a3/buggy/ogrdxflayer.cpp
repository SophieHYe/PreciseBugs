/******************************************************************************
 *
 * Project:  DXF Translator
 * Purpose:  Implements OGRDXFLayer class.
 * Author:   Frank Warmerdam, warmerdam@pobox.com
 *
 ******************************************************************************
 * Copyright (c) 2009, Frank Warmerdam <warmerdam@pobox.com>
 * Copyright (c) 2011-2013, Even Rouault <even dot rouault at mines-paris dot org>
 * Copyright (c) 2017, Alan Thomas <alant@outlook.com.au>
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

#include "ogr_dxf.h"
#include "cpl_conv.h"
#include "ogrdxf_polyline_smooth.h"
#include "ogr_api.h"

#include <cmath>
#include <algorithm>
#include <stdexcept>
#include <memory>

CPL_CVSID("$Id$")

/************************************************************************/
/*                            OGRDXFLayer()                             */
/************************************************************************/

OGRDXFLayer::OGRDXFLayer( OGRDXFDataSource *poDSIn ) :
    poDS(poDSIn),
    poFeatureDefn(new OGRFeatureDefn( "entities" )),
    iNextFID(0)
{
    poFeatureDefn->Reference();

    OGRDXFDataSource::AddStandardFields( poFeatureDefn,
        !poDS->InlineBlocks(), poDS->ShouldIncludeRawCodeValues() );

    SetDescription( poFeatureDefn->GetName() );
}

/************************************************************************/
/*                           ~OGRDXFLayer()                           */
/************************************************************************/

OGRDXFLayer::~OGRDXFLayer()

{
    ClearPendingFeatures();
    if( m_nFeaturesRead > 0 && poFeatureDefn != nullptr )
    {
        CPLDebug( "DXF", "%d features read on layer '%s'.",
                  (int) m_nFeaturesRead,
                  poFeatureDefn->GetName() );
    }

    if( poFeatureDefn )
        poFeatureDefn->Release();
}

/************************************************************************/
/*                        ClearPendingFeatures()                        */
/************************************************************************/

void OGRDXFLayer::ClearPendingFeatures()

{
    while( !apoPendingFeatures.empty() )
    {
        delete apoPendingFeatures.front();
        apoPendingFeatures.pop();
    }
}

/************************************************************************/
/*                            ResetReading()                            */
/************************************************************************/

void OGRDXFLayer::ResetReading()

{
    iNextFID = 0;
    ClearPendingFeatures();
    poDS->RestartEntities();
}

/************************************************************************/
/*                      TranslateGenericProperty()                      */
/*                                                                      */
/*      Try and convert entity properties handled similarly for most    */
/*      or all entity types.                                            */
/************************************************************************/

void OGRDXFLayer::TranslateGenericProperty( OGRDXFFeature *poFeature,
                                            int nCode, char *pszValue )

{
    switch( nCode )
    {
      case 8:
        poFeature->SetField( "Layer", TextRecode(pszValue) );
        break;

      case 100:
      {
          CPLString osSubClass = poFeature->GetFieldAsString("SubClasses");
          if( !osSubClass.empty() )
              osSubClass += ":";
          osSubClass += pszValue;
          poFeature->SetField( "SubClasses", osSubClass.c_str() );
      }
      break;

      case 60:
        poFeature->oStyleProperties["Hidden"] = pszValue;
        break;

      case 62:
        poFeature->oStyleProperties["Color"] = pszValue;
        break;

      case 6:
        poFeature->SetField( "Linetype", TextRecode(pszValue) );
        break;

      case 48:
        poFeature->oStyleProperties["LinetypeScale"] = pszValue;
        break;

      case 370:
      case 39:
        poFeature->oStyleProperties["LineWeight"] = pszValue;
        break;

      case 5:
        poFeature->SetField( "EntityHandle", pszValue );
        break;

      // OCS vector.
      case 210:
        poFeature->oOCS.dfX = CPLAtof( pszValue );
        break;

      case 220:
        poFeature->oOCS.dfY = CPLAtof( pszValue );
        break;

      case 230:
        poFeature->oOCS.dfZ = CPLAtof( pszValue );
        break;

      case 330:
        // No-one cares about this, so exclude from RawCodeValues
        break;

      default:
        if( poDS->ShouldIncludeRawCodeValues() )
        {
            char** papszRawCodeValues =
                poFeature->GetFieldAsStringList( "RawCodeValues" );

            papszRawCodeValues = CSLDuplicate( papszRawCodeValues );

            papszRawCodeValues = CSLAddString( papszRawCodeValues,
                CPLString().Printf( "%d %s", nCode,
                TextRecode( pszValue ).c_str() ).c_str() );

            poFeature->SetField( "RawCodeValues", papszRawCodeValues );

            CSLDestroy(papszRawCodeValues);
        }
        break;
    }
}

/************************************************************************/
/*                        PrepareFeatureStyle()                         */
/*                                                                      */
/*     - poBlockFeature: If this is not NULL, style properties on       */
/*       poFeature with ByBlock values will be replaced with the        */
/*       corresponding property from poBlockFeature.  If this           */
/*       parameter is supplied it is assumed that poFeature is a        */
/*       clone, not an "original" feature object.                       */
/************************************************************************/

void OGRDXFLayer::PrepareFeatureStyle( OGRDXFFeature* const poFeature,
    OGRDXFFeature* const poBlockFeature /* = NULL */ )

{
    const char* pszStyleString = poFeature->GetStyleString();

    if( pszStyleString && STARTS_WITH_CI( pszStyleString, "BRUSH(" ) )
    {
        PrepareBrushStyle( poFeature, poBlockFeature );
    }
    else if( pszStyleString && STARTS_WITH_CI( pszStyleString, "LABEL(" ) )
    {
        // Find the new color of this feature, and replace it into
        // the style string
        const CPLString osNewColor = poFeature->GetColor( poDS, poBlockFeature );

        CPLString osNewStyle = pszStyleString;
        const size_t nColorStartPos = osNewStyle.rfind( ",c:" );
        if( nColorStartPos != std::string::npos )
        {
            const size_t nColorEndPos = osNewStyle.find_first_of( ",)",
                nColorStartPos + 3 );

            if( nColorEndPos != std::string::npos )
            {
                osNewStyle.replace( nColorStartPos + 3,
                    nColorEndPos - ( nColorStartPos + 3 ), osNewColor );
                poFeature->SetStyleString( osNewStyle );
            }
        }
    }
    else
    {
        PrepareLineStyle( poFeature, poBlockFeature );
    }
}

/************************************************************************/
/*                         PrepareBrushStyle()                          */
/************************************************************************/

void OGRDXFLayer::PrepareBrushStyle( OGRDXFFeature* const poFeature,
    OGRDXFFeature* const poBlockFeature /* = NULL */ )

{
    CPLString osStyle = "BRUSH(fc:";
    osStyle += poFeature->GetColor( poDS, poBlockFeature );
    osStyle += ")";

    poFeature->SetStyleString( osStyle );
}

/************************************************************************/
/*                          PrepareLineStyle()                          */
/************************************************************************/

void OGRDXFLayer::PrepareLineStyle( OGRDXFFeature* const poFeature,
    OGRDXFFeature* const poBlockFeature /* = NULL */ )

{
    const CPLString osLayer = poFeature->GetFieldAsString("Layer");

/* -------------------------------------------------------------------- */
/*      Get line weight if available.                                   */
/* -------------------------------------------------------------------- */
    double dfWeight = 0.0;
    CPLString osWeight = "-1";

    if( poFeature->oStyleProperties.count("LineWeight") > 0 )
        osWeight = poFeature->oStyleProperties["LineWeight"];

    // Use ByBlock lineweight?
    if( CPLAtof(osWeight) == -2 && poBlockFeature )
    {
        if( poBlockFeature->oStyleProperties.count("LineWeight") > 0 )
        {
            // Inherit lineweight from the owning block
            osWeight = poBlockFeature->oStyleProperties["LineWeight"];

            // Use the inherited lineweight if we regenerate the style
            // string again during block insertion
            poFeature->oStyleProperties["LineWeight"] = osWeight;
        }
        else
        {
            // If the owning block has no explicit lineweight,
            // assume ByLayer
            osWeight = "-1";
        }
    }

    // Use layer lineweight?
    if( CPLAtof(osWeight) == -1 )
    {
        osWeight = poDS->LookupLayerProperty(osLayer,"LineWeight");
    }

    // Will be zero in the case of an invalid value
    dfWeight = CPLAtof(osWeight) / 100.0;

/* -------------------------------------------------------------------- */
/*      Do we have a dash/dot line style?                               */
/* -------------------------------------------------------------------- */
    const char *pszLinetype = poFeature->GetFieldAsString("Linetype");

    // Use ByBlock line style?
    if( pszLinetype && EQUAL( pszLinetype, "ByBlock" ) && poBlockFeature )
    {
        pszLinetype = poBlockFeature->GetFieldAsString("Linetype");

        // Use the inherited line style if we regenerate the style string
        // again during block insertion
        if( pszLinetype )
            poFeature->SetField( "Linetype", pszLinetype );
    }

    // Use layer line style?
    if( pszLinetype && EQUAL( pszLinetype, "" ) )
    {
        pszLinetype = poDS->LookupLayerProperty( osLayer, "Linetype" );
    }

    const std::vector<double> oLineType = poDS->LookupLineType( pszLinetype );

    // Linetype scale is not inherited from the block feature
    double dfLineTypeScale = CPLAtof( poDS->GetVariable( "$LTSCALE", "1.0" ) );
    if( poFeature->oStyleProperties.count( "LinetypeScale" ) > 0 )
        dfLineTypeScale *= CPLAtof( poFeature->oStyleProperties["LinetypeScale"] );

    CPLString osPattern;
    for( std::vector<double>::const_iterator oIt = oLineType.begin();
        oIt != oLineType.end(); ++oIt )
    {
        // this is the format specifier %g followed by a literal 'g'
        osPattern += CPLString().Printf( "%.11gg ",
            fabs( *oIt ) * dfLineTypeScale );
    }

    if( osPattern.length() > 0 )
        osPattern.erase( osPattern.end() - 1 );

/* -------------------------------------------------------------------- */
/*      Format the style string.                                        */
/* -------------------------------------------------------------------- */

    CPLString osStyle = "PEN(c:";
    osStyle += poFeature->GetColor( poDS, poBlockFeature );

    if( dfWeight > 0.0 )
    {
        char szBuffer[64];
        CPLsnprintf(szBuffer, sizeof(szBuffer), "%.2g", dfWeight);
        osStyle += CPLString().Printf( ",w:%sg", szBuffer );
    }

    if( osPattern != "" )
    {
        osStyle += ",p:\"";
        osStyle += osPattern;
        osStyle += "\"";
    }

    osStyle += ")";

    poFeature->SetStyleString( osStyle );
}

/************************************************************************/
/*                            OCSTransformer                            */
/************************************************************************/

class OCSTransformer : public OGRCoordinateTransformation
{
private:
    double adfN[3];
    double adfAX[3];
    double adfAY[3];

    double dfDeterminant;
    double aadfInverse[4][4];

    static double Det2x2( double a, double b, double c, double d )
    {
        return a*d - b*c;
    }

public:
    OCSTransformer( double adfNIn[3], bool bInverse = false ) :
        aadfInverse()
    {
        constexpr double dSmall = 1.0 / 64.0;
        constexpr double adfWZ[3] = { 0.0, 0.0, 1.0 };
        constexpr double adfWY[3] = { 0.0, 1.0, 0.0 };

        dfDeterminant = 0.0;
        Scale2Unit( adfNIn );
        memcpy( adfN, adfNIn, sizeof(double)*3 );

    if ((std::abs(adfN[0]) < dSmall) && (std::abs(adfN[1]) < dSmall))
            CrossProduct(adfWY, adfN, adfAX);
    else
            CrossProduct(adfWZ, adfN, adfAX);

    Scale2Unit( adfAX );
    CrossProduct(adfN, adfAX, adfAY);
    Scale2Unit( adfAY );

    if( bInverse == true ) {
        const double a[4] = { 0.0, adfAX[0], adfAY[0], adfN[0] };
        const double b[4] = { 0.0, adfAX[1], adfAY[1], adfN[1] };
        const double c[4] = { 0.0, adfAX[2], adfAY[2], adfN[2] };

        dfDeterminant = a[1]*b[2]*c[3] - a[1]*b[3]*c[2]
                      + a[2]*b[3]*c[1] - a[2]*b[1]*c[3]
                      + a[3]*b[1]*c[2] - a[3]*b[2]*c[1];

        if( dfDeterminant != 0.0 ) {
            const double k = 1.0 / dfDeterminant;
            const double a11 = adfAX[0];
            const double a12 = adfAY[0];
            const double a13 = adfN[0];
            const double a21 = adfAX[1];
            const double a22 = adfAY[1];
            const double a23 = adfN[1];
            const double a31 = adfAX[2];
            const double a32 = adfAY[2];
            const double a33 = adfN[2];

            aadfInverse[1][1] = k * Det2x2( a22,a23,a32,a33 );
            aadfInverse[1][2] = k * Det2x2( a13,a12,a33,a32 );
            aadfInverse[1][3] = k * Det2x2( a12,a13,a22,a23 );

            aadfInverse[2][1] = k * Det2x2( a23,a21,a33,a31 );
            aadfInverse[2][2] = k * Det2x2( a11,a13,a31,a33 );
            aadfInverse[2][3] = k * Det2x2( a13,a11,a23,a21 );

            aadfInverse[3][1] = k * Det2x2( a21,a22,a31,a32 );
            aadfInverse[3][2] = k * Det2x2( a12,a11,a32,a31 );
            aadfInverse[3][3] = k * Det2x2( a11,a12,a21,a22 );
        }
    }
    }

    static void CrossProduct(const double *a, const double *b, double *vResult) {
        vResult[0] = a[1] * b[2] - a[2] * b[1];
        vResult[1] = a[2] * b[0] - a[0] * b[2];
        vResult[2] = a[0] * b[1] - a[1] * b[0];
    }

    static void Scale2Unit(double* adfV) {
        double dfLen=sqrt(adfV[0]*adfV[0] + adfV[1]*adfV[1] + adfV[2]*adfV[2]);
        if (dfLen != 0)
        {
                adfV[0] /= dfLen;
                adfV[1] /= dfLen;
                adfV[2] /= dfLen;
        }
    }
    OGRSpatialReference *GetSourceCS() override { return nullptr; }
    OGRSpatialReference *GetTargetCS() override { return nullptr; }
    int Transform( int nCount,
                   double *x, double *y, double *z ) override
        { return TransformEx( nCount, x, y, z, nullptr ); }

    int TransformEx( int nCount,
                     double *adfX, double *adfY, double *adfZ,
                     int *pabSuccess = nullptr ) override
        {
            for( int i = 0; i < nCount; i++ )
            {
                const double x = adfX[i];
                const double y = adfY[i];
                const double z = adfZ[i];

                adfX[i] = x * adfAX[0] + y * adfAY[0] + z * adfN[0];
                adfY[i] = x * adfAX[1] + y * adfAY[1] + z * adfN[1];
                adfZ[i] = x * adfAX[2] + y * adfAY[2] + z * adfN[2];

                if( pabSuccess )
                    pabSuccess[i] = TRUE;
            }
            return TRUE;
        }

    int InverseTransform( int nCount,
                          double *adfX, double *adfY, double *adfZ )
    {
        if( dfDeterminant == 0.0 )
            return FALSE;

        for( int i = 0; i < nCount; i++ )
        {
            const double x = adfX[i];
            const double y = adfY[i];
            const double z = adfZ[i];

            adfX[i] = x * aadfInverse[1][1] + y * aadfInverse[1][2]
                    + z * aadfInverse[1][3];
            adfY[i] = x * aadfInverse[2][1] + y * aadfInverse[2][2]
                    + z * aadfInverse[2][3];
            adfZ[i] = x * aadfInverse[3][1] + y * aadfInverse[3][2]
                    + z * aadfInverse[3][3];
        }
        return TRUE;
    }
};

/************************************************************************/
/*                         ApplyOCSTransformer()                        */
/*                                                                      */
/*      Apply a transformation from the given OCS to world              */
/*      coordinates.                                                    */
/************************************************************************/

void OGRDXFLayer::ApplyOCSTransformer( OGRGeometry *poGeometry,
    const DXFTriple& oOCS )

{
    if( poGeometry == nullptr )
        return;

    double adfN[3];
    oOCS.ToArray( adfN );

    OCSTransformer oTransformer( adfN );

    // Promote to 3D, in case the OCS transformation introduces a
    // third dimension to the geometry.
    const bool bInitially2D = !poGeometry->Is3D();
    if( bInitially2D )
        poGeometry->set3D( TRUE );

    poGeometry->transform( &oTransformer );

    // If the geometry was 2D to begin with, and is still 2D after the
    // OCS transformation, flatten it back to 2D.
    if( bInitially2D )
    {
        OGREnvelope3D oEnvelope;
        poGeometry->getEnvelope( &oEnvelope );
        if( oEnvelope.MaxZ == 0.0 && oEnvelope.MinZ == 0.0 )
            poGeometry->flattenTo2D();
    }
}

/************************************************************************/
/*                             TextRecode()                             */
/************************************************************************/

CPLString OGRDXFLayer::TextRecode( const char *pszInput )

{
    return CPLString( pszInput ).Recode( poDS->GetEncoding(), CPL_ENC_UTF8 );
}

/************************************************************************/
/*                            TextUnescape()                            */
/*                                                                      */
/*      Unexcape DXF style escape sequences such as \P for newline      */
/*      and \~ for space, and do the recoding to UTF8.                  */
/************************************************************************/

CPLString OGRDXFLayer::TextUnescape( const char *pszInput, bool bIsMText )

{
    if( poDS->ShouldTranslateEscapes() )
        return ACTextUnescape( pszInput, poDS->GetEncoding(), bIsMText );

    return TextRecode( pszInput );
}

/************************************************************************/
/*                           TranslateMTEXT()                           */
/************************************************************************/

OGRDXFFeature *OGRDXFLayer::TranslateMTEXT()

{
    char szLineBuf[257];
    int nCode = 0;
    OGRDXFFeature *poFeature = new OGRDXFFeature( poFeatureDefn );
    double dfX = 0.0;
    double dfY = 0.0;
    double dfZ = 0.0;
    double dfAngle = 0.0;
    double dfHeight = 0.0;
    double dfXDirection = 0.0;
    double dfYDirection = 0.0;
    bool bHaveZ = false;
    int nAttachmentPoint = -1;
    CPLString osText;
    CPLString osStyleName = "STANDARD";

    while( (nCode = poDS->ReadValue(szLineBuf,sizeof(szLineBuf))) > 0 )
    {
        switch( nCode )
        {
          case 10:
            dfX = CPLAtof(szLineBuf);
            break;

          case 20:
            dfY = CPLAtof(szLineBuf);
            break;

          case 30:
            dfZ = CPLAtof(szLineBuf);
            bHaveZ = true;
            break;

          case 40:
            dfHeight = CPLAtof(szLineBuf);
            break;

          case 71:
            nAttachmentPoint = atoi(szLineBuf);
            break;

          case 11:
            dfXDirection = CPLAtof(szLineBuf);
            break;

          case 21:
            dfYDirection = CPLAtof(szLineBuf);
            dfAngle = atan2( dfYDirection, dfXDirection ) * 180.0 / M_PI;
            break;

          case 1:
          case 3:
            osText += TextUnescape(szLineBuf, true);
            break;

          case 50:
            dfAngle = CPLAtof(szLineBuf);
            break;

          case 7:
            osStyleName = TextRecode(szLineBuf);
            break;

          default:
            TranslateGenericProperty( poFeature, nCode, szLineBuf );
            break;
        }
    }
    if( nCode < 0 )
    {
        DXF_LAYER_READER_ERROR();
        delete poFeature;
        return nullptr;
    }

    if( nCode == 0 )
        poDS->UnreadValue();

    OGRPoint* poGeom = nullptr;
    if( bHaveZ )
        poGeom = new OGRPoint( dfX, dfY, dfZ );
    else
        poGeom = new OGRPoint( dfX, dfY );

    /* We do NOT apply the OCS for MTEXT. See https://trac.osgeo.org/gdal/ticket/7049 */
    /* ApplyOCSTransformer( poGeom ); */

    poFeature->SetGeometryDirectly( poGeom );

/* -------------------------------------------------------------------- */
/*      Apply text after stripping off any extra terminating newline.   */
/* -------------------------------------------------------------------- */
    if( !osText.empty() && osText.back() == '\n' )
        osText.resize( osText.size() - 1 );

    poFeature->SetField( "Text", osText );

/* -------------------------------------------------------------------- */
/*      We need to escape double quotes with backslashes before they    */
/*      can be inserted in the style string.                            */
/* -------------------------------------------------------------------- */
    if( strchr( osText, '"') != nullptr )
    {
        CPLString osEscaped;

        for( size_t iC = 0; iC < osText.size(); iC++ )
        {
            if( osText[iC] == '"' )
                osEscaped += "\\\"";
            else
                osEscaped += osText[iC];
        }
        osText = osEscaped;
    }

/* -------------------------------------------------------------------- */
/*      Prepare style string.                                           */
/* -------------------------------------------------------------------- */
    CPLString osStyle;
    char szBuffer[64];

    // Font name
    osStyle.Printf("LABEL(f:\"");

    // Preserve legacy behaviour of specifying "Arial" as a default font name.
    osStyle += poDS->LookupTextStyleProperty( osStyleName, "Font", "Arial" );

    osStyle += "\"";

    // Bold, italic
    if( EQUAL( poDS->LookupTextStyleProperty( osStyleName,
        "Bold", "0" ), "1" ) )
    {
        osStyle += ",bo:1";
    }
    if( EQUAL( poDS->LookupTextStyleProperty( osStyleName,
        "Italic", "0" ), "1" ) )
    {
        osStyle += ",it:1";
    }

    // Text string itself
    osStyle += ",t:\"";
    osStyle += osText;
    osStyle += "\"";

    if( dfAngle != 0.0 )
    {
        CPLsnprintf(szBuffer, sizeof(szBuffer), "%.3g", dfAngle);
        osStyle += CPLString().Printf(",a:%s", szBuffer);
    }

    if( dfHeight != 0.0 )
    {
        CPLsnprintf(szBuffer, sizeof(szBuffer), "%.3g", dfHeight);
        osStyle += CPLString().Printf(",s:%sg", szBuffer);
    }

    const char *pszWidthFactor = poDS->LookupTextStyleProperty( osStyleName,
        "Width", "1" );
    if( pszWidthFactor && CPLAtof( pszWidthFactor ) != 1.0 )
    {
        CPLsnprintf(szBuffer, sizeof(szBuffer), "%.4g",
            CPLAtof( pszWidthFactor ) * 100.0);
        osStyle += CPLString().Printf(",w:%s", szBuffer);
    }

    if( nAttachmentPoint >= 0 && nAttachmentPoint <= 9 )
    {
        const static int anAttachmentMap[10] =
            { -1, 7, 8, 9, 4, 5, 6, 1, 2, 3 };

        osStyle +=
            CPLString().Printf(",p:%d", anAttachmentMap[nAttachmentPoint]);
    }

    // Color
    osStyle += ",c:";
    osStyle += poFeature->GetColor( poDS );

    osStyle += ")";

    poFeature->SetStyleString( osStyle );

    return poFeature;
}

/************************************************************************/
/*                           TranslateTEXT()                            */
/*                                                                      */
/*      This function translates TEXT and ATTRIB entities, as well as   */
/*      ATTDEF entities when we are not inlining blocks.                */
/************************************************************************/

OGRDXFFeature *OGRDXFLayer::TranslateTEXT( const bool bIsAttribOrAttdef )

{
    char szLineBuf[257];
    int nCode = 0;
    OGRDXFFeature *poFeature = new OGRDXFFeature( poFeatureDefn );

    double dfX = 0.0;
    double dfY = 0.0;
    double dfZ = 0.0;
    bool bHaveZ = false;

    double dfAngle = 0.0;
    double dfHeight = 0.0;
    double dfWidthFactor = 1.0;
    bool bHasAlignmentPoint = false;
    double dfAlignmentPointX = 0.0;
    double dfAlignmentPointY = 0.0;

    CPLString osText;
    CPLString osStyleName = "STANDARD";

    int nAnchorPosition = 1;
    int nHorizontalAlignment = 0;
    int nVerticalAlignment = 0;

    while( (nCode = poDS->ReadValue(szLineBuf,sizeof(szLineBuf))) > 0 )
    {
        switch( nCode )
        {
          case 10:
            dfX = CPLAtof(szLineBuf);
            break;

          case 20:
            dfY = CPLAtof(szLineBuf);
            break;

          case 11:
            dfAlignmentPointX = CPLAtof(szLineBuf);
            break;

          case 21:
            dfAlignmentPointY = CPLAtof(szLineBuf);
            bHasAlignmentPoint = true;
            break;

          case 30:
            dfZ = CPLAtof(szLineBuf);
            bHaveZ = true;
            break;

          case 40:
            dfHeight = CPLAtof(szLineBuf);
            break;

          case 41:
            dfWidthFactor = CPLAtof(szLineBuf);
            break;

          case 1:
            osText += TextUnescape(szLineBuf, false);
            break;

          case 50:
            dfAngle = CPLAtof(szLineBuf);
            break;

          case 72:
            nHorizontalAlignment = atoi(szLineBuf);
            break;

          case 73:
            if( !bIsAttribOrAttdef )
                nVerticalAlignment = atoi(szLineBuf);
            break;

          case 74:
            if( bIsAttribOrAttdef )
                nVerticalAlignment = atoi(szLineBuf);
            break;

          case 7:
            osStyleName = TextRecode(szLineBuf);
            break;

          // 2 and 70 are for ATTRIB and ATTDEF entities only
          case 2:
            if( bIsAttribOrAttdef )
            {
                if( strchr( szLineBuf, ' ' ) )
                {
                    CPLDebug( "DXF", "Attribute tags may not contain spaces" );
                    DXF_LAYER_READER_ERROR();
                    delete poFeature;
                    return nullptr;
                }
                poFeature->osAttributeTag = szLineBuf;
            }
            break;

          case 70:
            // When the LSB is set, this ATTRIB is "invisible"
            if( bIsAttribOrAttdef && atoi(szLineBuf) & 1 )
                poFeature->oStyleProperties["Hidden"] = "1";
            break;

          default:
            TranslateGenericProperty( poFeature, nCode, szLineBuf );
            break;
        }
    }
    if( nCode < 0 )
    {
        DXF_LAYER_READER_ERROR();
        delete poFeature;
        return nullptr;
    }

    if( nCode == 0 )
        poDS->UnreadValue();

    OGRPoint* poGeom = nullptr;
    if( bHaveZ )
        poGeom = new OGRPoint( dfX, dfY, dfZ );
    else
        poGeom = new OGRPoint( dfX, dfY );
    poFeature->ApplyOCSTransformer( poGeom );
    poFeature->SetGeometryDirectly( poGeom );

/* -------------------------------------------------------------------- */
/*      Determine anchor position.                                      */
/* -------------------------------------------------------------------- */
    if( nHorizontalAlignment > 0 || nVerticalAlignment > 0 )
    {
        switch( nVerticalAlignment )
        {
          case 1: // bottom
            nAnchorPosition = 10;
            break;

          case 2: // middle
            nAnchorPosition = 4;
            break;

          case 3: // top
            nAnchorPosition = 7;
            break;

          default:
            // Handle "Middle" alignment approximately (this is rather like
            // MTEXT alignment in that it uses the actual height of the text
            // string to position the text, and thus requires knowledge of
            // text metrics)
            if( nHorizontalAlignment == 4 )
                nAnchorPosition = 5;
            break;
        }
        if( nHorizontalAlignment < 3 )
            nAnchorPosition += nHorizontalAlignment;
        // TODO other alignment options
    }

    poFeature->SetField( "Text", osText );

/* -------------------------------------------------------------------- */
/*      We need to escape double quotes with backslashes before they    */
/*      can be inserted in the style string.                            */
/* -------------------------------------------------------------------- */
    if( strchr( osText, '"' ) != nullptr )
    {
        CPLString osEscaped;

        for( size_t iC = 0; iC < osText.size(); iC++ )
        {
            if( osText[iC] == '"' )
                osEscaped += "\\\"";
            else
                osEscaped += osText[iC];
        }
        osText = osEscaped;
    }

/* -------------------------------------------------------------------- */
/*      Prepare style string.                                           */
/* -------------------------------------------------------------------- */
    CPLString osStyle;
    char szBuffer[64];

    // Font name
    osStyle.Printf("LABEL(f:\"");

    // Preserve legacy behaviour of specifying "Arial" as a default font name.
    osStyle += poDS->LookupTextStyleProperty( osStyleName, "Font", "Arial" );

    osStyle += "\"";

    // Bold, italic
    if( EQUAL( poDS->LookupTextStyleProperty( osStyleName,
        "Bold", "0" ), "1" ) )
    {
        osStyle += ",bo:1";
    }
    if( EQUAL( poDS->LookupTextStyleProperty( osStyleName,
        "Italic", "0" ), "1" ) )
    {
        osStyle += ",it:1";
    }

    // Text string itself
    osStyle += ",t:\"";
    osStyle += osText;
    osStyle += "\"";

    // Other attributes
    osStyle += CPLString().Printf(",p:%d", nAnchorPosition);

    if( dfAngle != 0.0 )
    {
        CPLsnprintf(szBuffer, sizeof(szBuffer), "%.3g", dfAngle);
        osStyle += CPLString().Printf(",a:%s", szBuffer);
    }

    if( dfHeight != 0.0 )
    {
        CPLsnprintf(szBuffer, sizeof(szBuffer), "%.3g", dfHeight);
        osStyle += CPLString().Printf(",s:%sg", szBuffer);
    }

    if( dfWidthFactor != 1.0 )
    {
        CPLsnprintf(szBuffer, sizeof(szBuffer), "%.4g", dfWidthFactor * 100.0);
        osStyle += CPLString().Printf(",w:%s", szBuffer);
    }

    if( bHasAlignmentPoint && dfAlignmentPointX != dfX )
    {
        CPLsnprintf(szBuffer, sizeof(szBuffer), "%.6g", dfAlignmentPointX - dfX);
        osStyle += CPLString().Printf(",dx:%sg", szBuffer);
    }

    if( bHasAlignmentPoint && dfAlignmentPointY != dfY )
    {
        CPLsnprintf(szBuffer, sizeof(szBuffer), "%.6g", dfAlignmentPointY - dfY);
        osStyle += CPLString().Printf(",dy:%sg", szBuffer);
    }

    // Color
    osStyle += ",c:";
    osStyle += poFeature->GetColor( poDS );

    osStyle += ")";

    poFeature->SetStyleString( osStyle );

    return poFeature;
}

/************************************************************************/
/*                           TranslatePOINT()                           */
/************************************************************************/

OGRDXFFeature *OGRDXFLayer::TranslatePOINT()

{
    char szLineBuf[257];
    int nCode = 0;
    OGRDXFFeature *poFeature = new OGRDXFFeature( poFeatureDefn );
    double dfX = 0.0;
    double dfY = 0.0;
    double dfZ = 0.0;
    bool bHaveZ = false;

    while( (nCode = poDS->ReadValue(szLineBuf,sizeof(szLineBuf))) > 0 )
    {
        switch( nCode )
        {
          case 10:
            dfX = CPLAtof(szLineBuf);
            break;

          case 20:
            dfY = CPLAtof(szLineBuf);
            break;

          case 30:
            dfZ = CPLAtof(szLineBuf);
            bHaveZ = true;
            break;

          default:
            TranslateGenericProperty( poFeature, nCode, szLineBuf );
            break;
        }
    }
    if( nCode < 0 )
    {
        DXF_LAYER_READER_ERROR();
        delete poFeature;
        return nullptr;
    }

    if( nCode == 0 )
        poDS->UnreadValue();

    OGRPoint* poGeom = nullptr;
    if( bHaveZ )
        poGeom = new OGRPoint( dfX, dfY, dfZ );
    else
        poGeom = new OGRPoint( dfX, dfY );

    poFeature->SetGeometryDirectly( poGeom );

    // Set style pen color
    PrepareLineStyle( poFeature );

    return poFeature;
}

/************************************************************************/
/*                           TranslateLINE()                            */
/************************************************************************/

OGRDXFFeature *OGRDXFLayer::TranslateLINE()

{
    char szLineBuf[257];
    int nCode = 0;
    OGRDXFFeature *poFeature = new OGRDXFFeature( poFeatureDefn );
    double dfX1 = 0.0;
    double dfY1 = 0.0;
    double dfZ1 = 0.0;
    double dfX2 = 0.0;
    double dfY2 = 0.0;
    double dfZ2 = 0.0;
    bool bHaveZ = false;

/* -------------------------------------------------------------------- */
/*      Process values.                                                 */
/* -------------------------------------------------------------------- */
    while( (nCode = poDS->ReadValue(szLineBuf,sizeof(szLineBuf))) > 0 )
    {
        switch( nCode )
        {
          case 10:
            dfX1 = CPLAtof(szLineBuf);
            break;

          case 11:
            dfX2 = CPLAtof(szLineBuf);
            break;

          case 20:
            dfY1 = CPLAtof(szLineBuf);
            break;

          case 21:
            dfY2 = CPLAtof(szLineBuf);
            break;

          case 30:
            dfZ1 = CPLAtof(szLineBuf);
            bHaveZ = true;
            break;

          case 31:
            dfZ2 = CPLAtof(szLineBuf);
            bHaveZ = true;
            break;

          default:
            TranslateGenericProperty( poFeature, nCode, szLineBuf );
            break;
        }
    }
    if( nCode < 0 )
    {
        DXF_LAYER_READER_ERROR();
        delete poFeature;
        return nullptr;
    }

    if( nCode == 0 )
        poDS->UnreadValue();

/* -------------------------------------------------------------------- */
/*      Create geometry                                                 */
/* -------------------------------------------------------------------- */
    OGRLineString *poLS = new OGRLineString();
    if( bHaveZ )
    {
        poLS->addPoint( dfX1, dfY1, dfZ1 );
        poLS->addPoint( dfX2, dfY2, dfZ2 );
    }
    else
    {
        poLS->addPoint( dfX1, dfY1 );
        poLS->addPoint( dfX2, dfY2 );
    }

    poFeature->SetGeometryDirectly( poLS );

    PrepareLineStyle( poFeature );

    return poFeature;
}

/************************************************************************/
/*                         TranslateLWPOLYLINE()                        */
/************************************************************************/
OGRDXFFeature *OGRDXFLayer::TranslateLWPOLYLINE()

{
    // Collect vertices and attributes into a smooth polyline.
    // If there are no bulges, then we are a straight-line polyline.
    // Single-vertex polylines become points.
    // Group code 30 (vertex Z) is not part of this entity.
    char szLineBuf[257];
    int nCode = 0;
    int nPolylineFlag = 0;

    OGRDXFFeature *poFeature = new OGRDXFFeature( poFeatureDefn );
    double dfX = 0.0;
    double dfY = 0.0;
    double dfZ = 0.0;
    bool bHaveX = false;
    bool bHaveY = false;

    int nNumVertices = 1;   // use 1 based index
    int npolyarcVertexCount = 1;
    double dfBulge = 0.0;
    DXFSmoothPolyline smoothPolyline;

    smoothPolyline.setCoordinateDimension(2);

/* -------------------------------------------------------------------- */
/*      Collect information from the LWPOLYLINE object itself.          */
/* -------------------------------------------------------------------- */
    while( (nCode = poDS->ReadValue(szLineBuf,sizeof(szLineBuf))) > 0 )
    {
        if(npolyarcVertexCount > nNumVertices)
        {
            CPLError( CE_Failure, CPLE_AppDefined,
                      "Too many vertices found in LWPOLYLINE." );
            delete poFeature;
            return nullptr;
        }

        switch( nCode )
        {
          case 38:
            // Constant elevation.
            dfZ = CPLAtof(szLineBuf);
            smoothPolyline.setCoordinateDimension(3);
            break;

          case 90:
            nNumVertices = atoi(szLineBuf);
            break;

          case 70:
            nPolylineFlag = atoi(szLineBuf);
            break;

          case 10:
            if( bHaveX && bHaveY )
            {
                smoothPolyline.AddPoint(dfX, dfY, dfZ, dfBulge);
                npolyarcVertexCount++;
                dfBulge = 0.0;
                bHaveY = false;
            }
            dfX = CPLAtof(szLineBuf);
            bHaveX = true;
            break;

          case 20:
            if( bHaveX && bHaveY )
            {
                smoothPolyline.AddPoint( dfX, dfY, dfZ, dfBulge );
                npolyarcVertexCount++;
                dfBulge = 0.0;
                bHaveX = false;
            }
            dfY = CPLAtof(szLineBuf);
            bHaveY = true;
            break;

          case 42:
            dfBulge = CPLAtof(szLineBuf);
            break;

          default:
            TranslateGenericProperty( poFeature, nCode, szLineBuf );
            break;
        }
    }
    if( nCode < 0 )
    {
        DXF_LAYER_READER_ERROR();
        delete poFeature;
        return nullptr;
    }

    if( nCode == 0 )
        poDS->UnreadValue();

    if( bHaveX && bHaveY )
        smoothPolyline.AddPoint(dfX, dfY, dfZ, dfBulge);

    if(smoothPolyline.IsEmpty())
    {
        delete poFeature;
        return nullptr;
    }

/* -------------------------------------------------------------------- */
/*      Close polyline if necessary.                                    */
/* -------------------------------------------------------------------- */
    if(nPolylineFlag & 0x01)
        smoothPolyline.Close();

    OGRGeometry* poGeom = smoothPolyline.Tesselate();
    poFeature->ApplyOCSTransformer( poGeom );
    poFeature->SetGeometryDirectly( poGeom );

    PrepareLineStyle( poFeature );

    return poFeature;
}

/************************************************************************/
/*                         TranslatePOLYLINE()                          */
/*                                                                      */
/*      We also capture the following VERTEXes.                         */
/************************************************************************/

OGRDXFFeature *OGRDXFLayer::TranslatePOLYLINE()

{
    char szLineBuf[257];
    int nCode = 0;
    int nPolylineFlag = 0;
    OGRDXFFeature *poFeature = new OGRDXFFeature( poFeatureDefn );

/* -------------------------------------------------------------------- */
/*      Collect information from the POLYLINE object itself.            */
/* -------------------------------------------------------------------- */
    while( (nCode = poDS->ReadValue(szLineBuf,sizeof(szLineBuf))) > 0 )
    {
        switch( nCode )
        {
          case 70:
            nPolylineFlag = atoi(szLineBuf);
            break;

          default:
            TranslateGenericProperty( poFeature, nCode, szLineBuf );
            break;
        }
    }
    if( nCode < 0 )
    {
        DXF_LAYER_READER_ERROR();
        delete poFeature;
        return nullptr;
    }

    if( (nPolylineFlag & 16) != 0 )
    {
        CPLDebug( "DXF", "Polygon mesh not supported." );
        delete poFeature;
        return nullptr;
    }

/* -------------------------------------------------------------------- */
/*      Collect VERTEXes as a smooth polyline.                          */
/* -------------------------------------------------------------------- */
    double dfX = 0.0;
    double dfY = 0.0;
    double dfZ = 0.0;
    double dfBulge = 0.0;
    int nVertexFlag = 0;
    DXFSmoothPolyline   smoothPolyline;
    int                 vertexIndex71 = 0;
    int                 vertexIndex72 = 0;
    int                 vertexIndex73 = 0;
    int                 vertexIndex74 = 0;
    OGRPoint **papoPoints = nullptr;
    int nPoints = 0;
    OGRPolyhedralSurface *poPS = new OGRPolyhedralSurface();

    smoothPolyline.setCoordinateDimension(2);

    while( nCode == 0 && !EQUAL(szLineBuf,"SEQEND") )
    {
        // Eat non-vertex objects.
        if( !EQUAL(szLineBuf,"VERTEX") )
        {
            while( (nCode = poDS->ReadValue(szLineBuf,sizeof(szLineBuf)))>0 ) {}
            if( nCode < 0 )
            {
                DXF_LAYER_READER_ERROR();
                delete poFeature;
                delete poPS;
                // delete the list of points
                for (int i = 0; i < nPoints; i++)
                    delete papoPoints[i];
                CPLFree(papoPoints);

                return nullptr;
            }

            continue;
        }

        // process a Vertex
        while( (nCode = poDS->ReadValue(szLineBuf,sizeof(szLineBuf))) > 0 )
        {
            switch( nCode )
            {
              case 10:
                dfX = CPLAtof(szLineBuf);
                break;

              case 20:
                dfY = CPLAtof(szLineBuf);
                break;

              case 30:
                dfZ = CPLAtof(szLineBuf);
                smoothPolyline.setCoordinateDimension(3);
                break;

              case 42:
                dfBulge = CPLAtof(szLineBuf);
                break;

              case 70:
                nVertexFlag = atoi(szLineBuf);
                break;

              case 71:
                // See comment below about negative values for 71, 72, 73, 74
                vertexIndex71 = abs(atoi(szLineBuf));
                break;

              case 72:
                vertexIndex72 = abs(atoi(szLineBuf));
                break;

              case 73:
                vertexIndex73 = abs(atoi(szLineBuf));
                break;

              case 74:
                vertexIndex74 = abs(atoi(szLineBuf));
                break;

              default:
                break;
            }
        }

        if (((nVertexFlag & 64) != 0) && ((nVertexFlag & 128) != 0))
        {
            // add the point to the list of points
            OGRPoint *poPoint = new OGRPoint(dfX, dfY, dfZ);
            OGRPoint** papoNewPoints = (OGRPoint **) VSI_REALLOC_VERBOSE( papoPoints,
                                                     sizeof(void*) * (nPoints+1) );

            papoPoints = papoNewPoints;
            papoPoints[nPoints] = poPoint;
            nPoints++;
        }

        // Note - If any index out of vertexIndex71, vertexIndex72, vertexIndex73 or vertexIndex74
        // is negative, it means that the line starting from that vertex is invisible.
        // However, it still needs to be constructed as part of the resultant
        // polyhedral surface; there is no way to specify the visibility of individual edges
        // in a polyhedral surface at present

        if (nVertexFlag == 128 && papoPoints != nullptr)
        {
            // create a polygon and add it to the Polyhedral Surface
            OGRLinearRing *poLR = new OGRLinearRing();
            int iPoint = 0;
            int startPoint = -1;
            poLR->set3D(TRUE);
            if (vertexIndex71 != 0 && vertexIndex71 <= nPoints)
            {
                if (startPoint == -1)
                    startPoint = vertexIndex71-1;
                poLR->setPoint(iPoint,papoPoints[vertexIndex71-1]);
                iPoint++;
                vertexIndex71 = 0;
            }
            if (vertexIndex72 != 0 && vertexIndex72 <= nPoints)
            {
                if (startPoint == -1)
                    startPoint = vertexIndex72-1;
                poLR->setPoint(iPoint,papoPoints[vertexIndex72-1]);
                iPoint++;
                vertexIndex72 = 0;
            }
            if (vertexIndex73 != 0 && vertexIndex73 <= nPoints)
            {
                if (startPoint == -1)
                    startPoint = vertexIndex73-1;
                poLR->setPoint(iPoint,papoPoints[vertexIndex73-1]);
                iPoint++;
                vertexIndex73 = 0;
            }
            if (vertexIndex74 != 0 && vertexIndex74 <= nPoints)
            {
                if (startPoint == -1)
                    startPoint = vertexIndex74-1;
                poLR->setPoint(iPoint,papoPoints[vertexIndex74-1]);
                iPoint++;
                vertexIndex74 = 0;
            }
            if( startPoint >= 0 )
            {
                // complete the ring
                poLR->setPoint(iPoint,papoPoints[startPoint]);

                OGRPolygon *poPolygon = new OGRPolygon();
                poPolygon->addRing((OGRCurve *)poLR);

                poPS->addGeometryDirectly(poPolygon);
            }

            // delete the ring to prevent leakage
            delete poLR;
        }

        if( nCode < 0 )
        {
            DXF_LAYER_READER_ERROR();
            delete poFeature;
            delete poPS;
            // delete the list of points
            for (int i = 0; i < nPoints; i++)
                delete papoPoints[i];
            CPLFree(papoPoints);
            return nullptr;
        }

        // Ignore Spline frame control points ( see #4683 )
        if ((nVertexFlag & 16) == 0)
            smoothPolyline.AddPoint( dfX, dfY, dfZ, dfBulge );
        dfBulge = 0.0;
    }

    // delete the list of points
    for (int i = 0; i < nPoints; i++)
        delete papoPoints[i];
    CPLFree(papoPoints);

    if(smoothPolyline.IsEmpty())
    {
        delete poFeature;
        delete poPS;
        return nullptr;
    }

    if (poPS->getNumGeometries() > 0)
    {
        poFeature->SetGeometryDirectly((OGRGeometry *)poPS);
        PrepareBrushStyle( poFeature );
        return poFeature;
    }

    else
        delete poPS;

    /* -------------------------------------------------------------------- */
    /*      Close polyline if necessary.                                    */
    /* -------------------------------------------------------------------- */
    if(nPolylineFlag & 0x01)
        smoothPolyline.Close();

    OGRGeometry* poGeom = smoothPolyline.Tesselate();

    if( (nPolylineFlag & 8) == 0 )
        poFeature->ApplyOCSTransformer( poGeom );
    poFeature->SetGeometryDirectly( poGeom );

    PrepareLineStyle( poFeature );

    return poFeature;
}

/************************************************************************/
/*                           TranslateMLINE()                           */
/************************************************************************/

OGRDXFFeature *OGRDXFLayer::TranslateMLINE()

{
    char szLineBuf[257];
    int nCode = 0;

    OGRDXFFeature *poFeature = new OGRDXFFeature( poFeatureDefn );

    bool bIsClosed = false;
    int nNumVertices = 0;
    int nNumElements = 0;

    while( (nCode = poDS->ReadValue(szLineBuf,sizeof(szLineBuf))) > 0 &&
        nCode != 11 )
    {
        switch( nCode )
        {
          case 71:
            bIsClosed = ( atoi(szLineBuf) & 2 ) == 2;
            break;

          case 72:
            nNumVertices = atoi(szLineBuf);
            break;

          case 73:
            nNumElements = atoi(szLineBuf);
            // No-one should ever need more than 1000 elements!
            if( nNumElements <= 0 || nNumElements > 1000 )
            {
                CPLDebug( "DXF", "Invalid number of MLINE elements (73): %s",
                          szLineBuf );
                DXF_LAYER_READER_ERROR();
                delete poFeature;
                return nullptr;
            }
            break;

          default:
            TranslateGenericProperty( poFeature, nCode, szLineBuf );
            break;
        }
    }
    if( nCode < 0 )
    {
        DXF_LAYER_READER_ERROR();
        delete poFeature;
        return nullptr;
    }

    if( nCode == 0 || nCode == 11 )
        poDS->UnreadValue();

/* -------------------------------------------------------------------- */
/*      Read in the position and parameters for each vertex, and        */
/*      translate these values into line geometries.                    */
/* -------------------------------------------------------------------- */

    OGRMultiLineString *poMLS = new OGRMultiLineString();
    std::vector<std::unique_ptr<OGRLineString>> apoCurrentLines( nNumElements );

    // For use when bIsClosed is true
    std::vector<DXFTriple> aoInitialVertices( nNumElements );

#define EXPECT_CODE(code) \
    if( poDS->ReadValue( szLineBuf, sizeof(szLineBuf) ) != (code) ) \
    { \
        DXF_LAYER_READER_ERROR(); \
        delete poFeature; \
        delete poMLS; \
        return nullptr; \
    }

    for( int iVertex = 0; iVertex < nNumVertices; iVertex++ )
    {
        EXPECT_CODE(11);
        const double dfVertexX = CPLAtof(szLineBuf);
        EXPECT_CODE(21);
        const double dfVertexY = CPLAtof(szLineBuf);
        EXPECT_CODE(31);
        const double dfVertexZ = CPLAtof(szLineBuf);

        EXPECT_CODE(12);
        const double dfSegmentDirectionX = CPLAtof(szLineBuf);
        EXPECT_CODE(22);
        const double dfSegmentDirectionY = CPLAtof(szLineBuf);
        EXPECT_CODE(32);
        const double dfSegmentDirectionZ = CPLAtof(szLineBuf);

        EXPECT_CODE(13);
        const double dfMiterDirectionX = CPLAtof(szLineBuf);
        EXPECT_CODE(23);
        const double dfMiterDirectionY = CPLAtof(szLineBuf);
        EXPECT_CODE(33);
        const double dfMiterDirectionZ = CPLAtof(szLineBuf);

        for( int iElement = 0; iElement < nNumElements; iElement++ )
        {
            double dfStartSegmentX = 0.0;
            double dfStartSegmentY = 0.0;
            double dfStartSegmentZ = 0.0;

            EXPECT_CODE(74);
            const int nNumParameters = atoi(szLineBuf);

            // The first parameter is special: it is a distance along the
            // miter vector from the initial vertex to the start of the
            // element line.
            if( nNumParameters > 0 )
            {
                EXPECT_CODE(41);
                const double dfDistance = CPLAtof(szLineBuf);

                dfStartSegmentX = dfVertexX + dfMiterDirectionX * dfDistance;
                dfStartSegmentY = dfVertexY + dfMiterDirectionY * dfDistance;
                dfStartSegmentZ = dfVertexZ + dfMiterDirectionZ * dfDistance;

                if( bIsClosed && iVertex == 0 )
                {
                    aoInitialVertices[iElement] = DXFTriple( dfStartSegmentX,
                        dfStartSegmentY, dfStartSegmentZ );
                }

                // If we have an unfinished line for this element, we need
                // to close it off.
                if( apoCurrentLines[iElement] )
                {
                    apoCurrentLines[iElement]->addPoint(
                        dfStartSegmentX, dfStartSegmentY, dfStartSegmentZ );
                    poMLS->addGeometryDirectly(
                        apoCurrentLines[iElement].release() );
                }
            }

            // Parameters with an odd index give pen-up distances (breaks),
            // while even indexes are pen-down distances (line segments).
            for( int iParameter = 1;
                 iParameter < nNumParameters;
                 iParameter++ )
            {
                EXPECT_CODE(41);
                const double dfDistance = CPLAtof(szLineBuf);

                const double dfCurrentX = dfStartSegmentX +
                    dfSegmentDirectionX * dfDistance;
                const double dfCurrentY = dfStartSegmentY +
                    dfSegmentDirectionY * dfDistance;
                const double dfCurrentZ = dfStartSegmentZ +
                    dfSegmentDirectionZ * dfDistance;

                if( iParameter % 2 == 0 )
                {
                    // The dfCurrent(X,Y,Z) point is the end of a line segment
                    CPLAssert( apoCurrentLines[iElement] );
                    apoCurrentLines[iElement]->addPoint(
                        dfCurrentX, dfCurrentY, dfCurrentZ );
                    poMLS->addGeometryDirectly(
                        apoCurrentLines[iElement].release() );
                }
                else
                {
                    // The dfCurrent(X,Y,Z) point is the end of a break
                    apoCurrentLines[iElement] =
                        std::unique_ptr<OGRLineString>( new OGRLineString() );
                    apoCurrentLines[iElement]->addPoint( dfCurrentX,
                        dfCurrentY, dfCurrentZ );
                }
            }

            EXPECT_CODE(75);
            const int nNumAreaFillParams = atoi(szLineBuf);

            for( int iParameter = 0;
                 iParameter < nNumAreaFillParams;
                 iParameter++ )
            {
                EXPECT_CODE(42);
            }
        }
    }

#undef EXPECT_CODE

    // Close the MLINE if required.
    if( bIsClosed )
    {
        for( int iElement = 0; iElement < nNumElements; iElement++ )
        {
            if( apoCurrentLines[iElement] )
            {
                apoCurrentLines[iElement]->addPoint(
                    aoInitialVertices[iElement].dfX,
                    aoInitialVertices[iElement].dfY,
                    aoInitialVertices[iElement].dfZ );
                poMLS->addGeometryDirectly(
                    apoCurrentLines[iElement].release() );
            }
        }
    }

    // Apparently extrusions are ignored for MLINE entities.
    //poFeature->ApplyOCSTransformer( poMLS );
    poFeature->SetGeometryDirectly( poMLS );

    PrepareLineStyle( poFeature );

    return poFeature;
}

/************************************************************************/
/*                          TranslateCIRCLE()                           */
/************************************************************************/

OGRDXFFeature *OGRDXFLayer::TranslateCIRCLE()

{
    char szLineBuf[257];
    int nCode = 0;
    OGRDXFFeature *poFeature = new OGRDXFFeature( poFeatureDefn );
    double dfX1 = 0.0;
    double dfY1 = 0.0;
    double dfZ1 = 0.0;
    double dfRadius = 0.0;
    double dfThickness = 0.0;
    bool bHaveZ = false;

/* -------------------------------------------------------------------- */
/*      Process values.                                                 */
/* -------------------------------------------------------------------- */
    while( (nCode = poDS->ReadValue(szLineBuf,sizeof(szLineBuf))) > 0 )
    {
        switch( nCode )
        {
          case 10:
            dfX1 = CPLAtof(szLineBuf);
            break;

          case 20:
            dfY1 = CPLAtof(szLineBuf);
            break;

          case 30:
            dfZ1 = CPLAtof(szLineBuf);
            bHaveZ = true;
            break;

          case 39:
            dfThickness = CPLAtof(szLineBuf);
            break;

          case 40:
            dfRadius = CPLAtof(szLineBuf);
            break;

          default:
            TranslateGenericProperty( poFeature, nCode, szLineBuf );
            break;
        }
    }
    if( nCode < 0 )
    {
        DXF_LAYER_READER_ERROR();
        delete poFeature;
        return nullptr;
    }

    if( nCode == 0 )
        poDS->UnreadValue();

/* -------------------------------------------------------------------- */
/*      Create geometry                                                 */
/* -------------------------------------------------------------------- */
    OGRLineString *poCircle = reinterpret_cast<OGRLineString *>(
        OGRGeometryFactory::approximateArcAngles( dfX1, dfY1, dfZ1,
                                                  dfRadius, dfRadius, 0.0,
                                                  0.0, 360.0,
                                                  0.0 ) );

    const int nPoints = poCircle->getNumPoints();

    // If dfThickness is nonzero, we need to extrude a cylinder of height
    // dfThickness in the Z axis.
    if( dfThickness != 0.0 && nPoints > 1 )
    {
        OGRPolyhedralSurface *poSurface = new OGRPolyhedralSurface();

        // Add the bottom base as a polygon
        OGRLinearRing *poRing1 = new OGRLinearRing();
        poRing1->addSubLineString( poCircle );
        delete poCircle;
        poCircle = nullptr;

        OGRPolygon *poBase1 = new OGRPolygon();
        poBase1->addRingDirectly( poRing1 );
        poSurface->addGeometryDirectly( poBase1 );

        // Create and add the top base
        OGRLinearRing *poRing2 = reinterpret_cast<OGRLinearRing *>(
            poRing1->clone() );

        OGRDXFInsertTransformer oTransformer;
        oTransformer.dfZOffset = dfThickness;
        poRing2->transform( &oTransformer );

        OGRPolygon *poBase2 = new OGRPolygon();
        poBase2->addRingDirectly( poRing2 );
        poSurface->addGeometryDirectly( poBase2 );

        // Add the side of the cylinder as two "semicylindrical" polygons
        OGRLinearRing *poRect = new OGRLinearRing();
        OGRPoint oPoint;

        for( int iPoint = nPoints / 2; iPoint >= 0; iPoint-- )
        {
            poRing1->getPoint( iPoint, &oPoint );
            poRect->addPoint( &oPoint );
        }
        for( int iPoint = 0; iPoint <= nPoints / 2; iPoint++ )
        {
            poRing2->getPoint( iPoint, &oPoint );
            poRect->addPoint( &oPoint );
        }

        poRect->closeRings();

        OGRPolygon *poRectPolygon = new OGRPolygon();
        poRectPolygon->addRingDirectly( poRect );
        poSurface->addGeometryDirectly( poRectPolygon );

        poRect = new OGRLinearRing();

        for( int iPoint = nPoints - 1; iPoint >= nPoints / 2; iPoint-- )
        {
            poRing1->getPoint( iPoint, &oPoint );
            poRect->addPoint( &oPoint );
        }
        for( int iPoint = nPoints / 2; iPoint < nPoints; iPoint++ )
        {
            poRing2->getPoint( iPoint, &oPoint );
            poRect->addPoint( &oPoint );
        }

        poRect->closeRings();

        poRectPolygon = new OGRPolygon();
        poRectPolygon->addRingDirectly( poRect );
        poSurface->addGeometryDirectly( poRectPolygon );

        // That's your cylinder, folks
        poFeature->ApplyOCSTransformer( poSurface );
        poFeature->SetGeometryDirectly( poSurface );
    }
    else
    {
        if( !bHaveZ )
            poCircle->flattenTo2D();

        poFeature->ApplyOCSTransformer( poCircle );
        poFeature->SetGeometryDirectly( poCircle );
    }

    PrepareLineStyle( poFeature );

    return poFeature;
}

/************************************************************************/
/*                          TranslateELLIPSE()                          */
/************************************************************************/

OGRDXFFeature *OGRDXFLayer::TranslateELLIPSE()

{
    char szLineBuf[257];
    int nCode = 0;
    OGRDXFFeature *poFeature = new OGRDXFFeature( poFeatureDefn );
    double dfX1 = 0.0;
    double dfY1 = 0.0;
    double dfZ1 = 0.0;
    double dfRatio = 0.0;
    double dfStartAngle = 0.0;
    double dfEndAngle = 360.0;
    double dfAxisX = 0.0;
    double dfAxisY = 0.0;
    double dfAxisZ=0.0;
    bool bHaveZ = false;
    bool bApplyOCSTransform = false;

/* -------------------------------------------------------------------- */
/*      Process values.                                                 */
/* -------------------------------------------------------------------- */
    while( (nCode = poDS->ReadValue(szLineBuf,sizeof(szLineBuf))) > 0 )
    {
        switch( nCode )
        {
          case 10:
            dfX1 = CPLAtof(szLineBuf);
            break;

          case 20:
            dfY1 = CPLAtof(szLineBuf);
            break;

          case 30:
            dfZ1 = CPLAtof(szLineBuf);
            bHaveZ = true;
            break;

          case 11:
            dfAxisX = CPLAtof(szLineBuf);
            break;

          case 21:
            dfAxisY = CPLAtof(szLineBuf);
            break;

          case 31:
            dfAxisZ = CPLAtof(szLineBuf);
            break;

          case 40:
            dfRatio = CPLAtof(szLineBuf);
            break;

          case 41:
            // These *seem* to always be in radians regardless of $AUNITS
            dfEndAngle = -1 * CPLAtof(szLineBuf) * 180.0 / M_PI;
            break;

          case 42:
            // These *seem* to always be in radians regardless of $AUNITS
            dfStartAngle = -1 * CPLAtof(szLineBuf) * 180.0 / M_PI;
            break;

          default:
            TranslateGenericProperty( poFeature, nCode, szLineBuf );
            break;
        }
    }
    if( nCode < 0 )
    {
        DXF_LAYER_READER_ERROR();
        delete poFeature;
        return nullptr;
    }

    if( nCode == 0 )
        poDS->UnreadValue();

/* -------------------------------------------------------------------- */
/*      Setup coordinate system                                         */
/* -------------------------------------------------------------------- */
    double adfN[3];
    poFeature->oOCS.ToArray( adfN );

    if( (adfN[0] == 0.0 && adfN[1] == 0.0 && adfN[2] == 1.0) == false )
    {
        OCSTransformer oTransformer( adfN, true );

        bApplyOCSTransform = true;

        double *x = &dfX1;
        double *y = &dfY1;
        double *z = &dfZ1;
        oTransformer.InverseTransform( 1, x, y, z );

        x = &dfAxisX;
        y = &dfAxisY;
        z = &dfAxisZ;
        oTransformer.InverseTransform( 1, x, y, z );
    }

/* -------------------------------------------------------------------- */
/*      Compute primary and secondary axis lengths, and the angle of    */
/*      rotation for the ellipse.                                       */
/* -------------------------------------------------------------------- */
    double dfPrimaryRadius = sqrt( dfAxisX * dfAxisX
                            + dfAxisY * dfAxisY
                            + dfAxisZ * dfAxisZ );

    double dfSecondaryRadius = dfRatio * dfPrimaryRadius;

    double dfRotation = -1 * atan2( dfAxisY, dfAxisX ) * 180 / M_PI;

/* -------------------------------------------------------------------- */
/*      Create geometry                                                 */
/* -------------------------------------------------------------------- */
    if( dfStartAngle > dfEndAngle )
        dfEndAngle += 360.0;

    if( fabs(dfEndAngle - dfStartAngle) <= 361.0 )
    {
        OGRGeometry *poEllipse =
            OGRGeometryFactory::approximateArcAngles( dfX1, dfY1, dfZ1,
                                                    dfPrimaryRadius,
                                                    dfSecondaryRadius,
                                                    dfRotation,
                                                    dfStartAngle, dfEndAngle,
                                                    0.0 );

        if( !bHaveZ )
            poEllipse->flattenTo2D();

        if( bApplyOCSTransform == true )
            poFeature->ApplyOCSTransformer( poEllipse );
        poFeature->SetGeometryDirectly( poEllipse );
    }
    else
    {
        // TODO: emit error ?
    }

    PrepareLineStyle( poFeature );

    return poFeature;
}

/************************************************************************/
/*                            TranslateARC()                            */
/************************************************************************/

OGRDXFFeature *OGRDXFLayer::TranslateARC()

{
    char szLineBuf[257];
    int nCode = 0;
    OGRDXFFeature *poFeature = new OGRDXFFeature( poFeatureDefn );
    double dfX1 = 0.0;
    double dfY1 = 0.0;
    double dfZ1 = 0.0;
    double dfRadius = 0.0;
    double dfStartAngle = 0.0;
    double dfEndAngle = 360.0;
    bool bHaveZ = false;

/* -------------------------------------------------------------------- */
/*      Process values.                                                 */
/* -------------------------------------------------------------------- */
    while( (nCode = poDS->ReadValue(szLineBuf,sizeof(szLineBuf))) > 0 )
    {
        switch( nCode )
        {
          case 10:
            dfX1 = CPLAtof(szLineBuf);
            break;

          case 20:
            dfY1 = CPLAtof(szLineBuf);
            break;

          case 30:
            dfZ1 = CPLAtof(szLineBuf);
            bHaveZ = true;
            break;

          case 40:
            dfRadius = CPLAtof(szLineBuf);
            break;

          case 50:
            // This is apparently always degrees regardless of AUNITS
            dfEndAngle = -1 * CPLAtof(szLineBuf);
            break;

          case 51:
            // This is apparently always degrees regardless of AUNITS
            dfStartAngle = -1 * CPLAtof(szLineBuf);
            break;

          default:
            TranslateGenericProperty( poFeature, nCode, szLineBuf );
            break;
        }
    }
    if( nCode < 0 )
    {
        DXF_LAYER_READER_ERROR();
        delete poFeature;
        return nullptr;
    }

    if( nCode == 0 )
        poDS->UnreadValue();

/* -------------------------------------------------------------------- */
/*      Create geometry                                                 */
/* -------------------------------------------------------------------- */
    if( dfStartAngle > dfEndAngle )
        dfEndAngle += 360.0;

    if( fabs(dfEndAngle - dfStartAngle) <= 361.0 )
    {
        OGRGeometry *poArc =
            OGRGeometryFactory::approximateArcAngles( dfX1, dfY1, dfZ1,
                                                    dfRadius, dfRadius, 0.0,
                                                    dfStartAngle, dfEndAngle,
                                                    0.0 );
        if( !bHaveZ )
            poArc->flattenTo2D();

        poFeature->ApplyOCSTransformer( poArc );
        poFeature->SetGeometryDirectly( poArc );
    }
    else
    {
        // TODO: emit error ?
    }

    PrepareLineStyle( poFeature );

    return poFeature;
}

/************************************************************************/
/*                          TranslateSPLINE()                           */
/************************************************************************/

void rbspline2(int npts,int k,int p1,double b[],double h[],
        bool bCalculateKnots, double knots[], double p[]);

OGRDXFFeature *OGRDXFLayer::TranslateSPLINE()

{
    char szLineBuf[257];
    int nCode;
    OGRDXFFeature *poFeature = new OGRDXFFeature( poFeatureDefn );

    std::vector<double> adfControlPoints( 1, 0.0 );
    std::vector<double> adfKnots( 1, 0.0 );
    std::vector<double> adfWeights( 1, 0.0 );
    int nDegree = -1;
    int nControlPoints = -1;
    int nKnots = -1;

/* -------------------------------------------------------------------- */
/*      Process values.                                                 */
/* -------------------------------------------------------------------- */
    while( (nCode = poDS->ReadValue(szLineBuf,sizeof(szLineBuf))) > 0 )
    {
        bool bStop = false;
        switch( nCode )
        {
          case 10:
            adfControlPoints.push_back( CPLAtof(szLineBuf) );
            break;

          case 20:
            adfControlPoints.push_back( CPLAtof(szLineBuf) );
            adfControlPoints.push_back( 0.0 );
            break;

          case 40:
            adfKnots.push_back( CPLAtof(szLineBuf) );
            break;

          case 41:
            adfWeights.push_back( CPLAtof(szLineBuf) );
            break;

          case 70:
            break;

          case 71:
            nDegree = atoi(szLineBuf);
            // Arbitrary threshold
            if( nDegree < 0 || nDegree > 100)
            {
                DXF_LAYER_READER_ERROR();
                delete poFeature;
                return nullptr;
            }
            break;

          case 72:
            nKnots = atoi(szLineBuf);
            // Arbitrary threshold
            if( nKnots < 0 || nKnots > 10000000)
            {
                DXF_LAYER_READER_ERROR();
                delete poFeature;
                return nullptr;
            }
            break;

          case 73:
            nControlPoints = atoi(szLineBuf);
            // Arbitrary threshold
            if( nControlPoints < 0 || nControlPoints > 10000000)
            {
                DXF_LAYER_READER_ERROR();
                delete poFeature;
                return nullptr;
            }
            break;

          case 100:
            if( EQUAL(szLineBuf, "AcDbHelix") )
                bStop = true;
            TranslateGenericProperty( poFeature, nCode, szLineBuf );
            break;

          default:
            TranslateGenericProperty( poFeature, nCode, szLineBuf );
            break;
        }

        if( bStop )
            break;
    }
    if( nCode < 0 )
    {
        DXF_LAYER_READER_ERROR();
        delete poFeature;
        return nullptr;
    }

    if( nCode == 0 )
        poDS->UnreadValue();

/* -------------------------------------------------------------------- */
/*      Use the helper function to check the input data and insert      */
/*      the spline.                                                     */
/* -------------------------------------------------------------------- */
    OGRLineString *poLS = InsertSplineWithChecks( nDegree,
        adfControlPoints, nControlPoints, adfKnots, nKnots, adfWeights );

    if( !poLS )
    {
        DXF_LAYER_READER_ERROR();
        delete poFeature;
        return nullptr;
    }

    poFeature->SetGeometryDirectly( poLS );

    PrepareLineStyle( poFeature );

    return poFeature;
}

/************************************************************************/
/*                       InsertSplineWithChecks()                       */
/*                                                                      */
/*     Inserts a spline based on unchecked DXF input.  The arrays are   */
/*     one-based.                                                       */
/************************************************************************/

OGRLineString *OGRDXFLayer::InsertSplineWithChecks( const int nDegree,
    std::vector<double>& adfControlPoints, int nControlPoints,
    std::vector<double>& adfKnots, int nKnots,
    std::vector<double>& adfWeights )
{
/* -------------------------------------------------------------------- */
/*      Sanity checks                                                   */
/* -------------------------------------------------------------------- */
    const int nOrder = nDegree + 1;

    bool bResult = ( nOrder >= 2 );
    if( bResult == true )
    {
        // Check whether nctrlpts value matches number of vertices read
        int nCheck = (static_cast<int>(adfControlPoints.size()) - 1) / 3;

        if( nControlPoints == -1 )
            nControlPoints =
                (static_cast<int>(adfControlPoints.size()) - 1) / 3;

        // min( num(ctrlpts) ) = order
        bResult = ( nControlPoints >= nOrder && nControlPoints == nCheck);
    }

    bool bCalculateKnots = false;
    if( bResult == true )
    {
        int nCheck = static_cast<int>(adfKnots.size()) - 1;

        // Recalculate knots when:
        // - no knots data present, nknots is -1 and ncheck is 0
        // - nknots value present, no knot vertices
        //   nknots is (nctrlpts + order), ncheck is 0
        if( nCheck == 0 )
        {
            bCalculateKnots = true;
            for( int i = 0; i < (nControlPoints + nOrder); i++ )
                adfKnots.push_back( 0.0 );

            nCheck = static_cast<int>(adfKnots.size()) - 1;
        }
        // Adjust nknots value when:
        // - nknots value not present, knot vertices present
        //   nknots is -1, ncheck is (nctrlpts + order)
        if( nKnots == -1 )
            nKnots = static_cast<int>(adfKnots.size()) - 1;

        // num(knots) = num(ctrlpts) + order
        bResult = ( nKnots == (nControlPoints + nOrder) && nKnots == nCheck );
    }

    if( bResult == true )
    {
        int nWeights = static_cast<int>(adfWeights.size()) - 1;

        if( nWeights == 0 )
        {
            for( int i = 0; i < nControlPoints; i++ )
                adfWeights.push_back( 1.0 );

            nWeights = static_cast<int>(adfWeights.size()) - 1;
        }

        // num(weights) = num(ctrlpts)
        bResult = ( nWeights == nControlPoints );
    }

    if( bResult == false )
        return nullptr;

/* -------------------------------------------------------------------- */
/*      Interpolate spline                                              */
/* -------------------------------------------------------------------- */
    int p1 = nControlPoints * 8;
    std::vector<double> p;

    p.push_back( 0.0 );
    for( int i = 0; i < 3*p1; i++ )
        p.push_back( 0.0 );

    rbspline2( nControlPoints, nOrder, p1, &(adfControlPoints[0]),
            &(adfWeights[0]), bCalculateKnots, &(adfKnots[0]), &(p[0]) );

/* -------------------------------------------------------------------- */
/*      Turn into OGR geometry.                                         */
/* -------------------------------------------------------------------- */
    OGRLineString *poLS = new OGRLineString();

    poLS->setNumPoints( p1 );
    for( int i = 0; i < p1; i++ )
        poLS->setPoint( i, p[i*3+1], p[i*3+2] );

    return poLS;
}

/************************************************************************/
/*                          Translate3DFACE()                           */
/************************************************************************/

OGRDXFFeature *OGRDXFLayer::Translate3DFACE()

{
    char szLineBuf[257];
    int nCode = 0;
    OGRDXFFeature *poFeature = new OGRDXFFeature( poFeatureDefn );
    double dfX1 = 0.0;
    double dfY1 = 0.0;
    double dfZ1 = 0.0;
    double dfX2 = 0.0;
    double dfY2 = 0.0;
    double dfZ2 = 0.0;
    double dfX3 = 0.0;
    double dfY3 = 0.0;
    double dfZ3 = 0.0;
    double dfX4 = 0.0;
    double dfY4 = 0.0;
    double dfZ4 = 0.0;

/* -------------------------------------------------------------------- */
/*      Process values.                                                 */
/* -------------------------------------------------------------------- */
    while( (nCode = poDS->ReadValue(szLineBuf,sizeof(szLineBuf))) > 0 )
    {
        switch( nCode )
        {
          case 10:
            dfX1 = CPLAtof(szLineBuf);
            break;

          case 11:
            dfX2 = CPLAtof(szLineBuf);
            break;

          case 12:
            dfX3 = CPLAtof(szLineBuf);
            break;

          case 13:
            dfX4 = CPLAtof(szLineBuf);
            break;

          case 20:
            dfY1 = CPLAtof(szLineBuf);
            break;

          case 21:
            dfY2 = CPLAtof(szLineBuf);
            break;

          case 22:
            dfY3 = CPLAtof(szLineBuf);
            break;

          case 23:
            dfY4 = CPLAtof(szLineBuf);
            break;

          case 30:
            dfZ1 = CPLAtof(szLineBuf);
            break;

          case 31:
            dfZ2 = CPLAtof(szLineBuf);
            break;

          case 32:
            dfZ3 = CPLAtof(szLineBuf);
            break;

          case 33:
            dfZ4 = CPLAtof(szLineBuf);
            break;

          default:
            TranslateGenericProperty( poFeature, nCode, szLineBuf );
            break;
        }
    }
    if( nCode < 0 )
    {
        DXF_LAYER_READER_ERROR();
        delete poFeature;
        return nullptr;
    }

    if( nCode == 0 )
        poDS->UnreadValue();

/* -------------------------------------------------------------------- */
/*      Create geometry                                                 */
/* -------------------------------------------------------------------- */
    OGRPolygon *poPoly = new OGRPolygon();
    OGRLinearRing* poLR = new OGRLinearRing();
    poLR->addPoint( dfX1, dfY1, dfZ1 );
    poLR->addPoint( dfX2, dfY2, dfZ2 );
    poLR->addPoint( dfX3, dfY3, dfZ3 );
    if( dfX4 != dfX3 || dfY4 != dfY3 || dfZ4 != dfZ3 )
        poLR->addPoint( dfX4, dfY4, dfZ4 );
    poPoly->addRingDirectly(poLR);
    poPoly->closeRings();

    poFeature->ApplyOCSTransformer( poLR );
    poFeature->SetGeometryDirectly( poPoly );

    PrepareLineStyle( poFeature );

    return poFeature;
}

/* -------------------------------------------------------------------- */
/*      PointXAxisComparer                                              */
/*                                                                      */
/*      Returns true if oP1 is to the left of oP2, or they have the     */
/*      same x-coordinate and oP1 is below oP2.                         */
/* -------------------------------------------------------------------- */

static bool PointXAxisComparer(const OGRPoint& oP1, const OGRPoint& oP2)
{
    return oP1.getX() == oP2.getX() ?
        oP1.getY() < oP2.getY() :
        oP1.getX() < oP2.getX();
}

/* -------------------------------------------------------------------- */
/*      PointXYZEqualityComparer                                        */
/*                                                                      */
/*      Returns true if oP1 is equal to oP2 in the X, Y and Z axes.     */
/* -------------------------------------------------------------------- */

static bool PointXYZEqualityComparer(const OGRPoint& oP1, const OGRPoint& oP2)
{
    return oP1.getX() == oP2.getX() && oP1.getY() == oP2.getY() &&
        oP1.getZ() == oP2.getZ();
}

/************************************************************************/
/*                           TranslateSOLID()                           */
/************************************************************************/

OGRDXFFeature *OGRDXFLayer::TranslateSOLID()

{
    char szLineBuf[257];
    int nCode = 0;
    OGRDXFFeature *poFeature = new OGRDXFFeature(poFeatureDefn);
    double dfX1 = 0.0;
    double dfY1 = 0.0;
    double dfZ1 = 0.0;
    double dfX2 = 0.0;
    double dfY2 = 0.0;
    double dfZ2 = 0.0;
    double dfX3 = 0.0;
    double dfY3 = 0.0;
    double dfZ3 = 0.0;
    double dfX4 = 0.0;
    double dfY4 = 0.0;
    double dfZ4 = 0.0;

    while ((nCode = poDS->ReadValue(szLineBuf, sizeof(szLineBuf))) > 0) {
        switch (nCode) {
        case 10:
            dfX1 = CPLAtof(szLineBuf);
            break;

        case 20:
            dfY1 = CPLAtof(szLineBuf);
            break;

        case 30:
            dfZ1 = CPLAtof(szLineBuf);
            break;

        case 11:
            dfX2 = CPLAtof(szLineBuf);
            break;

        case 21:
            dfY2 = CPLAtof(szLineBuf);
            break;

        case 31:
            dfZ2 = CPLAtof(szLineBuf);
            break;

        case 12:
            dfX3 = CPLAtof(szLineBuf);
            break;

        case 22:
            dfY3 = CPLAtof(szLineBuf);
            break;

        case 32:
            dfZ3 = CPLAtof(szLineBuf);
            break;

        case 13:
            dfX4 = CPLAtof(szLineBuf);
            break;

        case 23:
            dfY4 = CPLAtof(szLineBuf);
            break;

        case 33:
            dfZ4 = CPLAtof(szLineBuf);
            break;

        default:
            TranslateGenericProperty(poFeature, nCode, szLineBuf);
            break;
        }
    }
    if( nCode < 0 )
    {
        DXF_LAYER_READER_ERROR();
        delete poFeature;
        return nullptr;
    }
    if (nCode == 0)
        poDS->UnreadValue();

    // do we want Z-coordinates?
    const bool bWantZ = dfZ1 != 0.0 || dfZ2 != 0.0 ||
                        dfZ3 != 0.0 || dfZ4 != 0.0;

    // check how many unique corners we have
    OGRPoint* poCorners = new OGRPoint[4];
    poCorners[0].setX(dfX1);
    poCorners[0].setY(dfY1);
    if( bWantZ )
        poCorners[0].setZ(dfZ1);
    poCorners[1].setX(dfX2);
    poCorners[1].setY(dfY2);
    if( bWantZ )
        poCorners[1].setZ(dfZ2);
    poCorners[2].setX(dfX3);
    poCorners[2].setY(dfY3);
    if( bWantZ )
        poCorners[2].setZ(dfZ3);
    poCorners[3].setX(dfX4);
    poCorners[3].setY(dfY4);
    if( bWantZ )
        poCorners[3].setZ(dfZ4);

    std::sort(poCorners, poCorners + 4, PointXAxisComparer);
    int nCornerCount = static_cast<int>(std::unique(poCorners, poCorners + 4,
        PointXYZEqualityComparer) - poCorners);
    if( nCornerCount < 1 )
    {
        DXF_LAYER_READER_ERROR();
        delete poFeature;
        delete[] poCorners;
        return nullptr;
    }

    OGRGeometry* poFinalGeom;

    // what kind of object do we need?
    if( nCornerCount == 1 )
    {
        poFinalGeom = poCorners[0].clone();

        PrepareLineStyle( poFeature );
    }
    else if( nCornerCount == 2 )
    {
        OGRLineString* poLS = new OGRLineString();
        poLS->setPoint( 0, &poCorners[0] );
        poLS->setPoint( 1, &poCorners[1] );
        poFinalGeom = poLS;

        PrepareLineStyle( poFeature );
    }
    else
    {
        // SOLID vertices seem to be joined in the order 1-2-4-3-1.
        // See trac ticket #7089
        OGRLinearRing* poLinearRing = new OGRLinearRing();
        int iIndex = 0;
        poLinearRing->setPoint( iIndex++, dfX1, dfY1, dfZ1 );
        if( dfX1 != dfX2 || dfY1 != dfY2 || dfZ1 != dfZ2 )
            poLinearRing->setPoint( iIndex++, dfX2, dfY2, dfZ2 );
        if( dfX2 != dfX4 || dfY2 != dfY4 || dfZ2 != dfZ4 )
            poLinearRing->setPoint( iIndex++, dfX4, dfY4, dfZ4 );
        if( dfX4 != dfX3 || dfY4 != dfY3 || dfZ4 != dfZ3 )
            poLinearRing->setPoint( iIndex++, dfX3, dfY3, dfZ3 );
        poLinearRing->closeRings();

        if( !bWantZ )
            poLinearRing->flattenTo2D();

        OGRPolygon* poPoly = new OGRPolygon();
        poPoly->addRingDirectly( poLinearRing );
        poFinalGeom = poPoly;

        PrepareBrushStyle( poFeature );
    }

    delete[] poCorners;

    poFeature->ApplyOCSTransformer( poFinalGeom );
    poFeature->SetGeometryDirectly( poFinalGeom );

    return poFeature;
}

/************************************************************************/
/*                       SimplifyBlockGeometry()                        */
/************************************************************************/

OGRGeometry *OGRDXFLayer::SimplifyBlockGeometry(
    OGRGeometryCollection *poCollection )
{
/* -------------------------------------------------------------------- */
/*      If there is only one geometry in the collection, just return    */
/*      it.                                                             */
/* -------------------------------------------------------------------- */
    if( poCollection->getNumGeometries() == 1 )
    {
        OGRGeometry *poReturn = poCollection->getGeometryRef(0);
        poCollection->removeGeometry(0, FALSE);
        delete poCollection;
        return poReturn;
    }

/* -------------------------------------------------------------------- */
/*      Convert to polygon, multipolygon, multilinestring or multipoint */
/* -------------------------------------------------------------------- */

    OGRwkbGeometryType eType =
                wkbFlatten(poCollection->getGeometryRef(0)->getGeometryType());
    int i;
    for(i=1;i<poCollection->getNumGeometries();i++)
    {
        if (wkbFlatten(poCollection->getGeometryRef(i)->getGeometryType())
            != eType)
        {
            eType = wkbUnknown;
            break;
        }
    }
    if (eType == wkbPoint || eType == wkbLineString)
    {
        OGRGeometryCollection* poNewColl;
        if (eType == wkbPoint)
            poNewColl = new OGRMultiPoint();
        else
            poNewColl = new OGRMultiLineString();
        while(poCollection->getNumGeometries() > 0)
        {
            OGRGeometry *poGeom = poCollection->getGeometryRef(0);
            poCollection->removeGeometry(0,FALSE);
            poNewColl->addGeometryDirectly(poGeom);
        }
        delete poCollection;
        return poNewColl;
    }
    else if (eType == wkbPolygon)
    {
        std::vector<OGRGeometry*> aosPolygons;
        while(poCollection->getNumGeometries() > 0)
        {
            OGRGeometry *poGeom = poCollection->getGeometryRef(0);
            poCollection->removeGeometry(0,FALSE);
            aosPolygons.push_back(poGeom);
        }
        delete poCollection;
        int bIsValidGeometry;
        return OGRGeometryFactory::organizePolygons(
            &aosPolygons[0], (int)aosPolygons.size(),
            &bIsValidGeometry, nullptr);
    }

    return poCollection;
}

/************************************************************************/
/*                       InsertBlockReference()                         */
/*                                                                      */
/*     Returns a point geometry located at the block's insertion        */
/*     point.                                                           */
/************************************************************************/
OGRDXFFeature *OGRDXFLayer::InsertBlockReference(
    const CPLString& osBlockName,
    const OGRDXFInsertTransformer& oTransformer,
    OGRDXFFeature* const poFeature )
{
    // Store the block's properties in the special DXF-specific members
    // on the feature object
    poFeature->bIsBlockReference = true;
    poFeature->osBlockName = osBlockName;
    poFeature->dfBlockAngle = oTransformer.dfAngle * 180 / M_PI;
    poFeature->oBlockScale = DXFTriple( oTransformer.dfXScale, 
        oTransformer.dfYScale, oTransformer.dfZScale );
    poFeature->oOriginalCoords = DXFTriple( oTransformer.dfXOffset,
        oTransformer.dfYOffset, oTransformer.dfZOffset );

    // Only if DXF_INLINE_BLOCKS is false should we ever need to expose these
    // to the end user as fields.
    if( poFeature->GetFieldIndex( "BlockName" ) != -1 )
    {
        poFeature->SetField( "BlockName", poFeature->osBlockName );
        poFeature->SetField( "BlockAngle", poFeature->dfBlockAngle );
        poFeature->SetField( "BlockScale", 3, &(poFeature->oBlockScale.dfX) );
        poFeature->SetField( "BlockOCSNormal", 3, &(poFeature->oOCS.dfX) );
        poFeature->SetField( "BlockOCSCoords", 3,
            &(poFeature->oOriginalCoords.dfX) );
    }

    // For convenience to the end user, the point geometry will be located
    // at the WCS coordinates of the insertion point.
    OGRPoint* poInsertionPoint = new OGRPoint( oTransformer.dfXOffset,
        oTransformer.dfYOffset, oTransformer.dfZOffset );

    poFeature->ApplyOCSTransformer( poInsertionPoint );
    poFeature->SetGeometryDirectly( poInsertionPoint );

    return poFeature;
}

/************************************************************************/
/*                         InsertBlockInline()                          */
/*                                                                      */
/*     Inserts the given block at the location specified by the given   */
/*     transformer.  Returns poFeature, or NULL if all features on      */
/*     the block have been pushed to the extra feature queue.           */
/*     If poFeature is not returned, it is deleted.                     */
/*     Throws std::invalid_argument if the requested block              */
/*     doesn't exist.                                                   */
/*                                                                      */
/*     - poFeature: The feature to use as a template. This feature's    */
/*       OCS will be applied to the block.                              */
/*     - bInlineRecursively: If true, INSERTs within this block         */
/*       will be recursively inserted.  Otherwise, they will be         */
/*       represented as a point geometry using InsertBlockReference.    */
/*     - bMergeGeometry: If true, all features in the block,            */
/*       apart from text features, are merged into a                    */
/*       GeometryCollection which is returned by the function.          */
/************************************************************************/

OGRDXFFeature *OGRDXFLayer::InsertBlockInline( const CPLString& osBlockName,
    OGRDXFInsertTransformer oTransformer,
    OGRDXFFeature* const poFeature,
    std::queue<OGRDXFFeature *>& apoExtraFeatures,
    const bool bInlineRecursively,
    const bool bMergeGeometry )
{
/* -------------------------------------------------------------------- */
/*      Set up protection against excessive recursion on this layer.    */
/* -------------------------------------------------------------------- */
    if( !poDS->PushBlockInsertion( osBlockName ) )
    {
        delete poFeature;
        return nullptr;
    }

/* -------------------------------------------------------------------- */
/*      Transform the insertion point from OCS into                     */
/*      world coordinates.                                              */
/* -------------------------------------------------------------------- */
    OGRPoint oInsertionPoint( oTransformer.dfXOffset, oTransformer.dfYOffset,
        oTransformer.dfZOffset );

    poFeature->ApplyOCSTransformer( &oInsertionPoint );

    oTransformer.dfXOffset = oInsertionPoint.getX();
    oTransformer.dfYOffset = oInsertionPoint.getY();
    oTransformer.dfZOffset = oInsertionPoint.getZ();

/* -------------------------------------------------------------------- */
/*      Lookup the block.                                               */
/* -------------------------------------------------------------------- */
    DXFBlockDefinition *poBlock = poDS->LookupBlock( osBlockName );

    if( poBlock == nullptr )
    {
        //CPLDebug( "DXF", "Attempt to insert missing block %s", osBlockName );
        poDS->PopBlockInsertion();
        throw std::invalid_argument("osBlockName");
    }

/* -------------------------------------------------------------------- */
/*      If we have complete features associated with the block, push    */
/*      them on the pending feature stack copying over key override     */
/*      information.                                                    */
/*                                                                      */
/*      If bMergeGeometry is true, we merge the features                */
/*      (except text) into a single GeometryCollection.                 */
/* -------------------------------------------------------------------- */
    OGRGeometryCollection *poMergedGeometry = nullptr;
    if( bMergeGeometry )
        poMergedGeometry = new OGRGeometryCollection();

    std::queue<OGRDXFFeature *> apoInnerExtraFeatures;

    for( unsigned int iSubFeat = 0;
        iSubFeat < poBlock->apoFeatures.size();
        iSubFeat++ )
    {
        OGRDXFFeature *poSubFeature =
            poBlock->apoFeatures[iSubFeat]->CloneDXFFeature();

        // Does this feature represent a block reference? If so,
        // insert that block
        if( bInlineRecursively && poSubFeature->IsBlockReference() )
        {
            // Unpack the transformation data stored in fields of this
            // feature
            OGRDXFInsertTransformer oInnerTransformer;
            oInnerTransformer.dfXOffset = poSubFeature->oOriginalCoords.dfX;
            oInnerTransformer.dfYOffset = poSubFeature->oOriginalCoords.dfY;
            oInnerTransformer.dfZOffset = poSubFeature->oOriginalCoords.dfZ;
            oInnerTransformer.dfAngle = poSubFeature->dfBlockAngle * M_PI / 180;
            oInnerTransformer.dfXScale = poSubFeature->oBlockScale.dfX;
            oInnerTransformer.dfYScale = poSubFeature->oBlockScale.dfY;
            oInnerTransformer.dfZScale = poSubFeature->oBlockScale.dfZ;

            poSubFeature->bIsBlockReference = false;

            // Insert this block recursively
            try
            {
                poSubFeature = InsertBlockInline( poSubFeature->osBlockName,
                    oInnerTransformer, poSubFeature, apoInnerExtraFeatures,
                    true, bMergeGeometry );
            }
            catch( const std::invalid_argument& )
            {
                // Block doesn't exist. Skip it and keep going
                delete poSubFeature;
                continue;
            }

            if( !poSubFeature )
            {
                if ( apoInnerExtraFeatures.empty() )
                {
                    // Block is empty. Skip it and keep going
                    continue;
                }
                else
                {
                    // Load up the first extra feature ready for
                    // transformation
                    poSubFeature = apoInnerExtraFeatures.front();
                    apoInnerExtraFeatures.pop();
                }
            }
        }

        // Go through the current feature and any extra features generated
        // by the recursive insert, and apply transformations
        while( true )
        {
            OGRGeometry *poSubFeatGeom = poSubFeature->GetGeometryRef();
            if( poSubFeatGeom != nullptr )
            {
                // Rotation and scaling first
                OGRDXFInsertTransformer oInnerTrans =
                    oTransformer.GetRotateScaleTransformer();
                poSubFeatGeom->transform( &oInnerTrans );

                // Then the OCS to WCS transformation
                poFeature->ApplyOCSTransformer( poSubFeatGeom );

                // Offset translation last
                oInnerTrans = oTransformer.GetOffsetTransformer();
                poSubFeatGeom->transform( &oInnerTrans );
            }

            // If we are merging features, and this is not text or a block
            // reference, merge it into the GeometryCollection
            if( bMergeGeometry && 
                (poSubFeature->GetStyleString() == nullptr ||
                    strstr(poSubFeature->GetStyleString(),"LABEL") == nullptr) &&
                !poSubFeature->IsBlockReference() &&
                poSubFeature->GetGeometryRef() )
            {
                poMergedGeometry->addGeometryDirectly( poSubFeature->StealGeometry() );
                delete poSubFeature;
            }
            // Import all other features, except ATTDEFs when inlining
            // recursively
            else if( !bInlineRecursively || poSubFeature->osAttributeTag == "" )
            {
                // If the subfeature is on layer 0, this is a special case: the
                // subfeature should take on the style properties of the layer
                // the block is being inserted onto.
                // But don't do this if we are inserting onto a Blocks layer
                // (that is, the owning feature has no layer).
                if( EQUAL( poSubFeature->GetFieldAsString( "Layer" ), "0" ) &&
                    !EQUAL( poFeature->GetFieldAsString( "Layer" ), "" ) )
                {
                    poSubFeature->SetField( "Layer",
                        poFeature->GetFieldAsString( "Layer" ) );
                }

                // Update the style string to replace ByBlock and ByLayer values.
                PrepareFeatureStyle( poSubFeature, poFeature );

                ACAdjustText( oTransformer.dfAngle * 180 / M_PI,
                    oTransformer.dfXScale, oTransformer.dfYScale, poSubFeature );

                if ( !EQUAL( poFeature->GetFieldAsString( "EntityHandle" ), "" ) )
                {
                    poSubFeature->SetField( "EntityHandle",
                        poFeature->GetFieldAsString( "EntityHandle" ) );
                }

                apoExtraFeatures.push( poSubFeature );
            }
            else
            {
                delete poSubFeature;
            }

            if( apoInnerExtraFeatures.empty() )
            {
                break;
            }
            else
            {
                poSubFeature = apoInnerExtraFeatures.front();
                apoInnerExtraFeatures.pop();
            }
        }
    }

    poDS->PopBlockInsertion();

/* -------------------------------------------------------------------- */
/*      Return the merged geometry if applicable.  Otherwise            */
/*      return NULL and let the machinery find the rest of the          */
/*      features in the pending feature stack.                          */
/* -------------------------------------------------------------------- */
    if( bMergeGeometry )
    {
        if( poMergedGeometry->getNumGeometries() == 0 )
        {
            delete poMergedGeometry;
        }
        else
        {
            poFeature->SetGeometryDirectly(
                SimplifyBlockGeometry( poMergedGeometry ) );

            PrepareLineStyle( poFeature );
            return poFeature;
        }
    }

    delete poFeature;
    return nullptr;
}

/************************************************************************/
/*                          TranslateINSERT()                           */
/************************************************************************/

OGRDXFFeature *OGRDXFLayer::TranslateINSERT()

{
    char szLineBuf[257];
    int nCode = 0;

    OGRDXFFeature *poTemplateFeature = new OGRDXFFeature( poFeatureDefn );
    OGRDXFInsertTransformer oTransformer;
    CPLString osBlockName;

    int nColumnCount = 1;
    int nRowCount = 1;
    double dfColumnSpacing = 0.0;
    double dfRowSpacing = 0.0;

    bool bHasAttribs = false;
    std::vector<std::unique_ptr<OGRDXFFeature>> apoAttribs;

/* -------------------------------------------------------------------- */
/*      Process values.                                                 */
/* -------------------------------------------------------------------- */
    while( (nCode = poDS->ReadValue(szLineBuf,sizeof(szLineBuf))) > 0 )
    {
        switch( nCode )
        {
          case 10:
            oTransformer.dfXOffset = CPLAtof(szLineBuf);
            break;

          case 20:
            oTransformer.dfYOffset = CPLAtof(szLineBuf);
            break;

          case 30:
            oTransformer.dfZOffset = CPLAtof(szLineBuf);
            break;

          case 41:
            oTransformer.dfXScale = CPLAtof(szLineBuf);
            break;

          case 42:
            oTransformer.dfYScale = CPLAtof(szLineBuf);
            break;

          case 43:
            oTransformer.dfZScale = CPLAtof(szLineBuf);
            break;

          case 44:
            dfColumnSpacing = CPLAtof(szLineBuf);
            break;

          case 45:
            dfRowSpacing = CPLAtof(szLineBuf);
            break;

          case 50:
            // We want to transform this to radians.
            // It is apparently always in degrees regardless of $AUNITS
            oTransformer.dfAngle = CPLAtof(szLineBuf) * M_PI / 180.0;
            break;

          case 66:
            bHasAttribs = atoi(szLineBuf) == 1;
            break;

          case 70:
            nColumnCount = atoi(szLineBuf);
            break;

          case 71:
            nRowCount = atoi(szLineBuf);
            break;

          case 2:
            osBlockName = szLineBuf;
            break;

          default:
            TranslateGenericProperty( poTemplateFeature, nCode, szLineBuf );
            break;
        }
    }
    if( nCode < 0 )
    {
        DXF_LAYER_READER_ERROR();
        delete poTemplateFeature;
        return nullptr;
    }

/* -------------------------------------------------------------------- */
/*      Process any attribute entities.                                 */
/* -------------------------------------------------------------------- */

    if ( bHasAttribs )
    {
        while( nCode == 0 && !EQUAL( szLineBuf, "SEQEND" ) )
        {
            if( !EQUAL( szLineBuf, "ATTRIB" ) )
            {
                DXF_LAYER_READER_ERROR();
                delete poTemplateFeature;
                return nullptr;
            }

            OGRDXFFeature *poAttribFeature = TranslateTEXT( true );

            if( poAttribFeature && poAttribFeature->osAttributeTag != "" )
            {
                apoAttribs.push_back(
                    std::unique_ptr<OGRDXFFeature>( poAttribFeature ) );
            }
            else
            {
                delete poAttribFeature;
            }

            nCode = poDS->ReadValue(szLineBuf,sizeof(szLineBuf));
        }
    }
    else if( nCode == 0 )
    {
        poDS->UnreadValue();
    }

/* -------------------------------------------------------------------- */
/*      Prepare a string list of the attributes and their text values   */
/*      as space-separated entries, to be stored in the                 */
/*      BlockAttributes field if we are not inlining blocks.            */
/* -------------------------------------------------------------------- */

    char** papszAttribs = nullptr;
    if( !poDS->InlineBlocks() && bHasAttribs &&
        poFeatureDefn->GetFieldIndex( "BlockAttributes" ) != -1 )
    {
        papszAttribs = static_cast<char**>(
            CPLCalloc(apoAttribs.size() + 1, sizeof(char*)));
        int iIndex = 0;

        for( auto oIt = apoAttribs.begin(); oIt != apoAttribs.end(); ++oIt )
        {
            CPLString osAttribString = (*oIt)->osAttributeTag;
            osAttribString += " ";
            osAttribString += (*oIt)->GetFieldAsString( "Text" );

            papszAttribs[iIndex] = VSIStrdup(osAttribString);

            iIndex++;
        }
    }

/* -------------------------------------------------------------------- */
/*      Perform the actual block insertion.                             */
/* -------------------------------------------------------------------- */

    for( int iRow = 0; iRow < nRowCount; iRow++ )
    {
        for( int iColumn = 0; iColumn < nColumnCount; iColumn++ )
        {
            TranslateINSERTCore( poTemplateFeature, osBlockName, oTransformer,
                iColumn * dfColumnSpacing * cos( oTransformer.dfAngle ) +
                    iRow * dfRowSpacing * -sin( oTransformer.dfAngle ),
                iColumn * dfColumnSpacing * sin( oTransformer.dfAngle ) +
                    iRow * dfRowSpacing * cos( oTransformer.dfAngle ),
                papszAttribs, apoAttribs );

            // Prevent excessive memory usage with an arbitrary limit
            if( apoPendingFeatures.size() > 100000 )
            {
                CPLError( CE_Warning, CPLE_AppDefined,
                    "Too many features generated by MInsertBlock. "
                    "Some features have been omitted." );
                break;
            }
        }
        if( apoPendingFeatures.size() > 100000 )
            break;
    }

    CSLDestroy(papszAttribs);

    // The block geometries were appended to apoPendingFeatures
    delete poTemplateFeature;
    return nullptr;
}

/************************************************************************/
/*                        TranslateINSERTCore()                         */
/*                                                                      */
/*      Helper function for TranslateINSERT.                            */
/************************************************************************/

void OGRDXFLayer::TranslateINSERTCore(
    OGRDXFFeature* const poTemplateFeature, const CPLString& osBlockName,
    OGRDXFInsertTransformer oTransformer, const double dfExtraXOffset,
    const double dfExtraYOffset, char** const papszAttribs,
    const std::vector<std::unique_ptr<OGRDXFFeature>>& apoAttribs )
{
    OGRDXFFeature* poFeature = poTemplateFeature->CloneDXFFeature();

    oTransformer.dfXOffset += dfExtraXOffset;
    oTransformer.dfYOffset += dfExtraYOffset;

    // If we are not inlining blocks, just insert a point that refers
    // to this block
    if( !poDS->InlineBlocks() )
    {
        poFeature = InsertBlockReference( osBlockName, oTransformer,
            poFeature );

        if( papszAttribs )
            poFeature->SetField( "BlockAttributes", papszAttribs );

        apoPendingFeatures.push( poFeature );
    }
    // Otherwise, try inlining the contents of this block
    else
    {
        std::queue<OGRDXFFeature *> apoExtraFeatures;
        try
        {
            poFeature = InsertBlockInline( osBlockName,
                oTransformer, poFeature, apoExtraFeatures,
                true, poDS->ShouldMergeBlockGeometries() );
        }
        catch( const std::invalid_argument& )
        {
            // Block doesn't exist
            delete poFeature;
            return;
        }

        if( poFeature )
            apoPendingFeatures.push( poFeature );

        while( !apoExtraFeatures.empty() )
        {
            apoPendingFeatures.push( apoExtraFeatures.front() );
            apoExtraFeatures.pop();
        }

        // Append the attribute features to the pending feature stack
        if( !apoAttribs.empty() )
        {
            OGRDXFInsertTransformer oAttribTransformer;
            oAttribTransformer.dfXOffset = dfExtraXOffset;
            oAttribTransformer.dfYOffset = dfExtraYOffset;

            for( auto oIt = apoAttribs.begin(); oIt != apoAttribs.end(); ++oIt )
            {
                OGRDXFFeature* poAttribFeature = (*oIt)->CloneDXFFeature();

                if( poAttribFeature->GetGeometryRef() )
                {
                    poAttribFeature->GetGeometryRef()->transform(
                        &oAttribTransformer );
                }

                apoPendingFeatures.push( poAttribFeature );
            }
        }
    }
}

/************************************************************************/
/*                      GetNextUnfilteredFeature()                      */
/************************************************************************/

OGRDXFFeature *OGRDXFLayer::GetNextUnfilteredFeature()

{
    OGRDXFFeature *poFeature = nullptr;

/* -------------------------------------------------------------------- */
/*      If we have pending features, return one of them.                */
/* -------------------------------------------------------------------- */
    if( !apoPendingFeatures.empty() )
    {
        poFeature = apoPendingFeatures.front();
        apoPendingFeatures.pop();

        poFeature->SetFID( iNextFID++ );
        return poFeature;
    }

/* -------------------------------------------------------------------- */
/*      Read the entity type.                                           */
/* -------------------------------------------------------------------- */
    char szLineBuf[257];

    while( poFeature == nullptr )
    {
        // read ahead to an entity.
        int nCode = 0;
        while( (nCode = poDS->ReadValue(szLineBuf,sizeof(szLineBuf))) > 0 ) {}
        if( nCode < 0 )
        {
            DXF_LAYER_READER_ERROR();
            return nullptr;
        }

        if( EQUAL(szLineBuf,"ENDSEC") )
        {
            //CPLDebug( "DXF", "Clean end of features at ENDSEC." );
            poDS->UnreadValue();
            return nullptr;
        }

        if( EQUAL(szLineBuf,"ENDBLK") )
        {
            //CPLDebug( "DXF", "Clean end of block at ENDBLK." );
            poDS->UnreadValue();
            return nullptr;
        }

/* -------------------------------------------------------------------- */
/*      Handle the entity.                                              */
/* -------------------------------------------------------------------- */
        if( EQUAL(szLineBuf,"POINT") )
        {
            poFeature = TranslatePOINT();
        }
        else if( EQUAL(szLineBuf,"MTEXT") )
        {
            poFeature = TranslateMTEXT();
        }
        else if( EQUAL(szLineBuf,"TEXT") )
        {
            poFeature = TranslateTEXT( false );
        }
        else if( EQUAL(szLineBuf,"ATTDEF") )
        {
            poFeature = TranslateTEXT( true );
        }
        else if( EQUAL(szLineBuf,"LINE") )
        {
            poFeature = TranslateLINE();
        }
        else if( EQUAL(szLineBuf,"POLYLINE") )
        {
            poFeature = TranslatePOLYLINE();
        }
        else if( EQUAL(szLineBuf,"LWPOLYLINE") )
        {
            poFeature = TranslateLWPOLYLINE();
        }
        else if( EQUAL(szLineBuf,"MLINE") )
        {
            poFeature = TranslateMLINE();
        }
        else if( EQUAL(szLineBuf,"CIRCLE") )
        {
            poFeature = TranslateCIRCLE();
        }
        else if( EQUAL(szLineBuf,"ELLIPSE") )
        {
            poFeature = TranslateELLIPSE();
        }
        else if( EQUAL(szLineBuf,"ARC") )
        {
            poFeature = TranslateARC();
        }
        else if( EQUAL(szLineBuf,"SPLINE") ||
            EQUAL(szLineBuf,"HELIX") )
        {
            poFeature = TranslateSPLINE();
        }
        else if( EQUAL(szLineBuf,"3DFACE") )
        {
            poFeature = Translate3DFACE();
        }
        else if( EQUAL(szLineBuf,"INSERT") )
        {
            poFeature = TranslateINSERT();
        }
        else if( EQUAL(szLineBuf,"DIMENSION") )
        {
            poFeature = TranslateDIMENSION();
        }
        else if( EQUAL(szLineBuf,"HATCH") )
        {
            poFeature = TranslateHATCH();
        }
        else if( EQUAL(szLineBuf,"SOLID") ||
            EQUAL(szLineBuf,"TRACE") )
        {
            poFeature = TranslateSOLID();
        }
        else if( EQUAL(szLineBuf,"LEADER") )
        {
            poFeature = TranslateLEADER();
        }
        else if( EQUAL(szLineBuf,"MLEADER")
            || EQUAL(szLineBuf,"MULTILEADER") )
        {
            poFeature = TranslateMLEADER();
        }
        else
        {
            if( oIgnoredEntities.count(szLineBuf) == 0 )
            {
                oIgnoredEntities.insert( szLineBuf );
                CPLDebug( "DXF", "Ignoring one or more of entity '%s'.",
                            szLineBuf );
            }
        }

        // If there are no more features, but we do still have pending features
        // (for example, after an INSERT), return the first pending feature.
        if ( poFeature == nullptr && !apoPendingFeatures.empty() )
        {
            poFeature = apoPendingFeatures.front();
            apoPendingFeatures.pop();

            poFeature->SetFID( iNextFID++ );
            return poFeature;
        }
    }

/* -------------------------------------------------------------------- */
/*      Set FID.                                                        */
/* -------------------------------------------------------------------- */
    poFeature->SetFID( iNextFID++ );
    m_nFeaturesRead++;

    return poFeature;
}

/************************************************************************/
/*                           GetNextFeature()                           */
/************************************************************************/

OGRFeature *OGRDXFLayer::GetNextFeature()

{
    while( true )
    {
        OGRFeature *poFeature = GetNextUnfilteredFeature();

        if( poFeature == nullptr )
            return nullptr;

        if( (m_poFilterGeom == nullptr
             || FilterGeometry( poFeature->GetGeometryRef() ) )
            && (m_poAttrQuery == nullptr
                || m_poAttrQuery->Evaluate( poFeature ) ) )
        {
            return poFeature;
        }

        delete poFeature;
    }
}

/************************************************************************/
/*                           TestCapability()                           */
/************************************************************************/

int OGRDXFLayer::TestCapability( const char * pszCap )

{
    return EQUAL(pszCap, OLCStringsAsUTF8);
}

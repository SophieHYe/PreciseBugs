/******************************************************************************
 *
 * Project:  GML Reader
 * Purpose:  Convenience functions for parsing with Xerces-C library
 *           Functions for translating back and forth between XMLCh and char.
 *           We assume that XMLCh is a simple numeric type that we can
 *           correspond 1:1 with char values, but that it likely is larger
 *           than a char.
 * Author:   Frank Warmerdam, warmerdam@pobox.com
 * Author:   Even Rouault, <even.rouault at spatialys.com>
 *
 ******************************************************************************
 * Copyright (c) 2002, Frank Warmerdam
 * Copyright (c) 2016, Even Rouault <even.rouault at spatialys.com>
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
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABOGRXercesTY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABOGRXercesTY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 ****************************************************************************/

// Must be first for DEBUG_BOOL case
#include "ogr_xerces.h"

#include "cpl_port.h"
#include "cpl_error.h"
#include "cpl_multiproc.h"
#include "cpl_string.h"

CPL_CVSID("$Id$")

#ifdef HAVE_XERCES

static CPLMutex* hMutex = nullptr;
static int nCounter = 0;

/************************************************************************/
/*                        OGRInitializeXerces()                         */
/************************************************************************/

bool OGRInitializeXerces()
{
    CPLMutexHolderD(&hMutex);
    if( nCounter > 0 )
    {
        nCounter++;
        return true;
    }

    try
    {
        CPLDebug("OGR", "XMLPlatformUtils::Initialize()");
        XMLPlatformUtils::Initialize();
        nCounter ++;
        return true;
    }
    catch (const XMLException& toCatch)
    {
        CPLError( CE_Failure, CPLE_AppDefined,
                  "Exception initializing Xerces: %s",
                  transcode(toCatch.getMessage()).c_str() );
        return false;
    }
}

/************************************************************************/
/*                       OGRDeinitializeXerces()                        */
/************************************************************************/

void OGRDeinitializeXerces()
{
    CPLMutexHolderD(&hMutex);
    if( nCounter == 0 )
    {
        CPLError(CE_Failure, CPLE_AppDefined,
                 "Unpaired OGRInitializeXerces / OGRDeinitializeXerces calls");
        return;
    }
    nCounter--;
    if( nCounter == 0 )
    {
        if( CPLTestBool(CPLGetConfigOption("OGR_XERCES_TERMINATE", "YES")) )
        {
            CPLDebug("OGR", "XMLPlatformUtils::Terminate()");
            XMLPlatformUtils::Terminate();
        }
    }
}

/************************************************************************/
/*                       OGRCleanupXercesMutex()                        */
/************************************************************************/

void OGRCleanupXercesMutex()
{
    if( hMutex != nullptr )
        CPLDestroyMutex(hMutex);
    hMutex = nullptr;
}

namespace OGR
{

/************************************************************************/
/*                            transcode()                               */
/************************************************************************/

CPLString transcode( const XMLCh *panXMLString, int nLimitingChars )
{
    CPLString osRet;
    transcode( panXMLString, osRet, nLimitingChars );
    return osRet;
}

CPLString& transcode( const XMLCh *panXMLString, CPLString& osRet,
                      int nLimitingChars )
{
    if( panXMLString == nullptr )
    {
        osRet = "(null)";
        return osRet;
    }

    osRet.clear();
    if( nLimitingChars > 0 )
        osRet.reserve(nLimitingChars);

    bool bSimpleASCII = true;
    int nChars = 0;
    for( int i = 0; panXMLString[i] != 0 &&
           (nLimitingChars < 0 || i < nLimitingChars); i++ )
    {
        if( panXMLString[i] > 127 )
        {
            bSimpleASCII = false;
        }
        osRet += static_cast<char>(panXMLString[i]);
        nChars++;
    }

    if( bSimpleASCII )
        return osRet;

/* -------------------------------------------------------------------- */
/*      The simple translation was wrong, because the source is not     */
/*      all simple ASCII characters.  Redo using the more expensive     */
/*      recoding API.                                                   */
/* -------------------------------------------------------------------- */
    wchar_t *pwszSource =
        static_cast<wchar_t *>(CPLMalloc(sizeof(wchar_t) * (nChars+1) ));
    for( int i = 0 ; i < nChars; i++ )
        pwszSource[i] = panXMLString[i];
    pwszSource[nChars] = 0;

    char *pszResult = CPLRecodeFromWChar( pwszSource,
                                          "WCHAR_T", CPL_ENC_UTF8 );

    osRet = pszResult;

    CPLFree( pwszSource );
    CPLFree( pszResult );

    return osRet;
}

}


/************************************************************************/
/*                      OGRXercesBinInputStream                         */
/************************************************************************/
class OGRXercesBinInputStream : public BinInputStream
{
    CPL_DISALLOW_COPY_ASSIGN(OGRXercesBinInputStream)

    VSILFILE* fp;
    XMLCh emptyString;

  public:
    explicit OGRXercesBinInputStream( VSILFILE* fp );
    ~OGRXercesBinInputStream() override;

    XMLFilePos curPos() const override;
    XMLSize_t readBytes(XMLByte* const toFill,
                                const XMLSize_t maxToRead) override;
    const XMLCh* getContentType() const override
        { return &emptyString; }
};

/************************************************************************/
/*                       OGRXercesInputSource                           */
/************************************************************************/

class OGRXercesInputSource : public InputSource
{
    OGRXercesBinInputStream* pBinInputStream;

    CPL_DISALLOW_COPY_ASSIGN(OGRXercesInputSource)

  public:
    explicit OGRXercesInputSource(VSILFILE* fp,
                         MemoryManager* const manager =
                         XMLPlatformUtils::fgMemoryManager);
    ~OGRXercesInputSource() override;

    BinInputStream* makeStream() const override
        { return pBinInputStream; }
};

/************************************************************************/
/*                      OGRXercesBinInputStream()                       */
/************************************************************************/

OGRXercesBinInputStream::OGRXercesBinInputStream(VSILFILE *fpIn) :
    fp(fpIn),
    emptyString(0)
{}

/************************************************************************/
/*                     ~OGRXercesBinInputStream()                       */
/************************************************************************/

OGRXercesBinInputStream::~OGRXercesBinInputStream() = default;

/************************************************************************/
/*                              curPos()                                */
/************************************************************************/

XMLFilePos OGRXercesBinInputStream::curPos() const
{
    return static_cast<XMLFilePos>(VSIFTellL(fp));
}

/************************************************************************/
/*                            readBytes()                               */
/************************************************************************/

XMLSize_t OGRXercesBinInputStream::readBytes(XMLByte* const toFill,
                                             const XMLSize_t maxToRead)
{
    return static_cast<XMLSize_t>(VSIFReadL(toFill, 1, maxToRead, fp));
}

/************************************************************************/
/*                       OGRXercesInputSource()                         */
/************************************************************************/

OGRXercesInputSource::OGRXercesInputSource(VSILFILE *fp,
                                           MemoryManager *const manager) :
    InputSource(manager),
    pBinInputStream(new OGRXercesBinInputStream(fp))
{}

/************************************************************************/
/*                      ~OGRXercesInputSource()                         */
/************************************************************************/

OGRXercesInputSource::~OGRXercesInputSource() = default;

/************************************************************************/
/*                     OGRCreateXercesInputSource()                     */
/************************************************************************/

InputSource* OGRCreateXercesInputSource(VSILFILE* fp)
{
    return new OGRXercesInputSource(fp);
}

/************************************************************************/
/*                     OGRDestroyXercesInputSource()                    */
/************************************************************************/

void OGRDestroyXercesInputSource(InputSource* is)
{
    delete is;
}

#endif // HAVE_XERCES

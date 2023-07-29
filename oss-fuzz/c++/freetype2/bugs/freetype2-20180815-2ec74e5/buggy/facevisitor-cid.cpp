// facevisitor-cid.cpp
//
//   Implementation of FaceVisitorCid.
//
// Copyright 2018 by
// Armin Hasitzka.
//
// This file is part of the FreeType project, and may only be used,
// modified, and distributed under the terms of the FreeType project
// license, LICENSE.TXT.  By continuing to use, modify, or distribute
// this file you indicate that you have read the license and
// understand and accept it fully.


#include "visitors/facevisitor-cid.h"

#include "utils/logging.h"

#include <ft2build.h>
#include FT_CID_H


  void
  FaceVisitorCid::
  run( Unique_FT_Face  face )
  {
    FT_Error  error;

    FT_Long  num_glyphs = face->num_glyphs;

    const char*  registry;
    const char*  ordering;
    FT_Int       supplement;

    FT_Bool  is_cid;

    FT_UInt  cid;


    error = FT_Get_CID_Registry_Ordering_Supplement( face.get(),
                                                     &registry,
                                                     &ordering,
                                                     &supplement );

    LOG_IF( ERROR, error != 0 ) <<
      "FT_Get_CID_Registry_Ordering_Supplement failed: " << error;

    LOG_IF( INFO, error == 0 ) << "cid r/o/s: "
                               << registry << "/"
                               << ordering << "/"
                               << supplement;

    error = FT_Get_CID_Is_Internally_CID_Keyed( face.get(), &is_cid );
    
    LOG_IF( ERROR, error != 0 ) <<
      "FT_Get_CID_Is_Internally_CID_Keyed failed: " << error;

    LOG_IF( INFO, error == 0 ) << "cid is "
                               << ( is_cid == 0 ? "not " : "" )
                               << "internally cid keyed";

    for ( auto  index = 0;
          index < num_glyphs &&
            index < GLYPH_INDEX_MAX;
          index++ )
    {
      error = FT_Get_CID_From_Glyph_Index( face.get(), index, &cid );

      if ( error != 0)
      {
        LOG( ERROR ) << "FT_Get_CID_From_Glyph_Index failed: " << error;
        break; // we can expect this call to fail again.
      }

      LOG_IF( INFO, error == 0 ) << index << "/" << num_glyphs
                                 << " (glyph index) <-> "
                                 << cid   << " (cid)";
    }

    WARN_ABOUT_IGNORED_VALUES( num_glyphs, GLYPH_INDEX_MAX, "glyphs" );
  }

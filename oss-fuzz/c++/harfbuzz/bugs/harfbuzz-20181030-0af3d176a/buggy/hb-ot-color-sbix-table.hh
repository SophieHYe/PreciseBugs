/*
 * Copyright © 2018  Ebrahim Byagowi
 *
 *  This is part of HarfBuzz, a text shaping library.
 *
 * Permission is hereby granted, without written agreement and without
 * license or royalty fees, to use, copy, modify, and distribute this
 * software and its documentation for any purpose, provided that the
 * above copyright notice and the following two paragraphs appear in
 * all copies of this software.
 *
 * IN NO EVENT SHALL THE COPYRIGHT HOLDER BE LIABLE TO ANY PARTY FOR
 * DIRECT, INDIRECT, SPECIAL, INCIDENTAL, OR CONSEQUENTIAL DAMAGES
 * ARISING OUT OF THE USE OF THIS SOFTWARE AND ITS DOCUMENTATION, EVEN
 * IF THE COPYRIGHT HOLDER HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * THE COPYRIGHT HOLDER SPECIFICALLY DISCLAIMS ANY WARRANTIES, INCLUDING,
 * BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND
 * FITNESS FOR A PARTICULAR PURPOSE.  THE SOFTWARE PROVIDED HEREUNDER IS
 * ON AN "AS IS" BASIS, AND THE COPYRIGHT HOLDER HAS NO OBLIGATION TO
 * PROVIDE MAINTENANCE, SUPPORT, UPDATES, ENHANCEMENTS, OR MODIFICATIONS.
 */

#ifndef HB_OT_COLOR_SBIX_TABLE_HH
#define HB_OT_COLOR_SBIX_TABLE_HH

#include "hb-open-type.hh"

/*
 * sbix -- Standard Bitmap Graphics
 * https://docs.microsoft.com/en-us/typography/opentype/spec/sbix
 * https://developer.apple.com/fonts/TrueType-Reference-Manual/RM06/Chap6sbix.html
 */
#define HB_OT_TAG_sbix HB_TAG('s','b','i','x')


namespace OT {


struct SBIXGlyph
{
  HBINT16	xOffset;	/* The horizontal (x-axis) offset from the left
				 * edge of the graphic to the glyph’s origin.
				 * That is, the x-coordinate of the point on the
				 * baseline at the left edge of the glyph. */
  HBINT16	yOffset;	/* The vertical (y-axis) offset from the bottom
				 * edge of the graphic to the glyph’s origin.
				 * That is, the y-coordinate of the point on the
				 * baseline at the left edge of the glyph. */
  Tag		graphicType;	/* Indicates the format of the embedded graphic
				 * data: one of 'jpg ', 'png ' or 'tiff', or the
				 * special format 'dupe'. */
  UnsizedArrayOf<HBUINT8>
		data;		/* The actual embedded graphic data. The total
				 * length is inferred from sequential entries in
				 * the glyphDataOffsets array and the fixed size
				 * (8 bytes) of the preceding fields. */
  public:
  DEFINE_SIZE_ARRAY (8, data);
};

struct SBIXStrike
{
  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);
    return_trace (c->check_struct (this) &&
		  imageOffsetsZ.sanitize_shallow (c, c->get_num_glyphs () + 1));
  }

  inline hb_blob_t *get_glyph_blob (unsigned int  glyph_id,
				    hb_blob_t    *sbix_blob,
				    hb_tag_t      file_type,
				    int          *x_offset,
				    int          *y_offset,
				    unsigned int  num_glyphs,
				    unsigned int *strike_ppem) const
  {
    if (unlikely (!ppem)) return hb_blob_get_empty (); /* To get Null() object out of the way. */

    unsigned int retry_count = 8;
    unsigned int sbix_len = sbix_blob->length;
    unsigned int strike_offset = (const char *) this - (const char *) sbix_blob->data;
    assert (strike_offset < sbix_len);

  retry:
    if (unlikely (glyph_id >= num_glyphs ||
		  imageOffsetsZ[glyph_id + 1] <= imageOffsetsZ[glyph_id] ||
		  imageOffsetsZ[glyph_id + 1] - imageOffsetsZ[glyph_id] <= SBIXGlyph::min_size ||
		  (unsigned int) imageOffsetsZ[glyph_id + 1] > sbix_len - strike_offset))
      return hb_blob_get_empty ();

    unsigned int glyph_offset = strike_offset + (unsigned int) imageOffsetsZ[glyph_id] + SBIXGlyph::min_size;
    unsigned int glyph_length = imageOffsetsZ[glyph_id + 1] - imageOffsetsZ[glyph_id] - SBIXGlyph::min_size;

    const SBIXGlyph *glyph = &(this+imageOffsetsZ[glyph_id]);

    if (glyph->graphicType == HB_TAG ('d','u','p','e'))
    {
      if (glyph_length >= 2)
      {
	glyph_id = *((HBUINT16 *) &glyph->data);
	if (retry_count--)
	  goto retry;
      }
      return hb_blob_get_empty ();
    }

    if (unlikely (file_type != glyph->graphicType))
      return hb_blob_get_empty ();

    if (strike_ppem) *strike_ppem = ppem;
    if (x_offset) *x_offset = glyph->xOffset;
    if (y_offset) *y_offset = glyph->yOffset;
    return hb_blob_create_sub_blob (sbix_blob, glyph_offset, glyph_length);
  }

  public:
  HBUINT16	ppem;		/* The PPEM size for which this strike was designed. */
  HBUINT16	resolution;	/* The device pixel density (in PPI) for which this
				 * strike was designed. (E.g., 96 PPI, 192 PPI.) */
  protected:
  UnsizedArrayOf<LOffsetTo<SBIXGlyph> >
		imageOffsetsZ;	/* Offset from the beginning of the strike data header
				 * to bitmap data for an individual glyph ID. */
  public:
  DEFINE_SIZE_STATIC (8);
};

struct sbix
{
  static const hb_tag_t tableTag = HB_OT_TAG_sbix;

  inline bool has_data (void) const { return version; }

  inline const SBIXStrike &get_strike (unsigned int i) const { return this+strikes[i]; }

  struct accelerator_t
  {
    inline void init (hb_face_t *face)
    {
      sbix_blob = hb_sanitize_context_t().reference_table<sbix> (face);
      table = sbix_blob->as<sbix> ();
      num_glyphs = face->get_num_glyphs ();
    }

    inline void fini (void)
    {
      hb_blob_destroy (sbix_blob);
    }

    inline bool has_data () const
    {
      /* XXX Fix somehow and remove next line.
       * https://github.com/harfbuzz/harfbuzz/issues/1146 */
      if (!num_glyphs) return false;
      return table->has_data ();
    }

    inline bool get_extents (hb_font_t          *font,
			     hb_codepoint_t      glyph,
			     hb_glyph_extents_t *extents) const
    {
      /* We only support PNG right now, and following function checks type. */
      return get_png_extents (font, glyph, extents);
    }

    inline hb_blob_t *reference_png (hb_font_t      *font,
				     hb_codepoint_t  glyph_id,
				     int            *x_offset,
				     int            *y_offset,
				     unsigned int   *available_ppem) const
    {
      return choose_strike (font).get_glyph_blob (glyph_id, sbix_blob,
						  HB_TAG ('p','n','g',' '),
						  x_offset, y_offset,
						  num_glyphs, available_ppem);
    }

    private:

    inline const SBIXStrike &choose_strike (hb_font_t *font) const
    {
      unsigned count = table->strikes.len;
      if (unlikely (!count))
        return Null(SBIXStrike);

      unsigned int requested_ppem = MAX (font->x_ppem, font->y_ppem);
      if (!requested_ppem)
        requested_ppem = 1<<30; /* Choose largest strike. */
      /* TODO Add DPI sensitivity as well? */
      unsigned int best_i = 0;
      unsigned int best_ppem = table->get_strike (0).ppem;

      for (unsigned int i = 1; i < count; i++)
      {
	unsigned int ppem = (table->get_strike (i)).ppem;
	if ((requested_ppem <= ppem && ppem < best_ppem) ||
	    (requested_ppem > best_ppem && ppem > best_ppem))
	{
	  best_i = i;
	  best_ppem = ppem;
	}
      }

      return table->get_strike (best_i);
    }

    struct PNGHeader
    {
      HBUINT8	signature[8];
      struct
      {
        struct
	{
	  HBUINT32	length;
	  Tag		type;
	}		header;
	HBUINT32	width;
	HBUINT32	height;
	HBUINT8		bitDepth;
	HBUINT8		colorType;
	HBUINT8		compressionMethod;
	HBUINT8		filterMethod;
	HBUINT8		interlaceMethod;
      } IHDR;

      public:
      DEFINE_SIZE_STATIC (29);
    };

    inline bool get_png_extents (hb_font_t          *font,
				 hb_codepoint_t      glyph,
				 hb_glyph_extents_t *extents) const
    {
      /* Following code is safe to call even without data (XXX currently
       * isn't.  See has_data()), but faster to short-circuit. */
      if (!has_data ())
        return false;

      int x_offset = 0, y_offset = 0;
      unsigned int strike_ppem = 0;
      hb_blob_t *blob = reference_png (font, glyph, &x_offset, &y_offset, &strike_ppem);

      if (unlikely (blob->length < sizeof (PNGHeader)))
        return false;

      const PNGHeader &png = *blob->as<PNGHeader>();

      extents->x_bearing = x_offset;
      extents->y_bearing = y_offset;
      extents->width     = png.IHDR.width;
      extents->height    = png.IHDR.height;

      /* Convert to font units. */
      if (strike_ppem)
      {
	double scale = font->face->upem / (double) strike_ppem;
	extents->x_bearing = round (extents->x_bearing * scale);
	extents->y_bearing = round (extents->y_bearing * scale);
	extents->width = round (extents->width * scale);
	extents->height = round (extents->height * scale);
      }

      hb_blob_destroy (blob);

      return true;
    }

    private:
    hb_blob_t *sbix_blob;
    const sbix *table;

    unsigned int num_glyphs;
  };

  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);
    return_trace (likely (c->check_struct (this) &&
			  version >= 1 &&
			  strikes.sanitize (c, this)));
  }

  protected:
  HBUINT16	version;	/* Table version number — set to 1 */
  HBUINT16	flags;		/* Bit 0: Set to 1. Bit 1: Draw outlines.
				 * Bits 2 to 15: reserved (set to 0). */
  LOffsetLArrayOf<SBIXStrike>
		strikes;	/* Offsets from the beginning of the 'sbix'
				 * table to data for each individual bitmap strike. */
  public:
  DEFINE_SIZE_ARRAY (8, strikes);
};

struct sbix_accelerator_t : sbix::accelerator_t {};

} /* namespace OT */

#endif /* HB_OT_COLOR_SBIX_TABLE_HH */

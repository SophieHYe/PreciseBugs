/*
 * Copyright © 2018 Adobe Inc.
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
 *
 * Adobe Author(s): Michiharu Ariza
 */

#ifndef HB_OT_CFF1_TABLE_HH
#define HB_OT_CFF1_TABLE_HH

#include "hb-ot-head-table.hh"
#include "hb-ot-cff-common.hh"
#include "hb-subset-cff1.hh"

namespace CFF {

/*
 * CFF -- Compact Font Format (CFF)
 * http://www.adobe.com/content/dam/acom/en/devnet/font/pdfs/5176.CFF.pdf
 */
#define HB_OT_TAG_cff1 HB_TAG('C','F','F',' ')

#define CFF_UNDEF_SID   CFF_UNDEF_CODE

enum EncodingID { StandardEncoding = 0, ExpertEncoding = 1 };
enum CharsetID { ISOAdobeCharset = 0, ExpertCharset = 1, ExpertSubsetCharset = 2 };

typedef CFFIndex<HBUINT16>  CFF1Index;
template <typename Type> struct CFF1IndexOf : CFFIndexOf<HBUINT16, Type> {};

typedef CFFIndex<HBUINT16> CFF1Index;
typedef CFF1Index          CFF1CharStrings;
typedef FDArray<HBUINT16>  CFF1FDArray;
typedef Subrs<HBUINT16>    CFF1Subrs;

struct CFF1FDSelect : FDSelect {};

/* Encoding */
struct Encoding0 {
  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);
    return_trace (c->check_struct (this) && codes[nCodes - 1].sanitize (c));
  }

  inline hb_codepoint_t get_code (hb_codepoint_t glyph) const
  {
    assert (glyph > 0);
    glyph--;
    if (glyph < nCodes)
    {
      return (hb_codepoint_t)codes[glyph];
    }
    else
      return CFF_UNDEF_CODE;
  }

  inline unsigned int get_size (void) const
  { return HBUINT8::static_size * (nCodes + 1); }

  HBUINT8     nCodes;
  HBUINT8     codes[VAR];

  DEFINE_SIZE_ARRAY(1, codes);
};

struct Encoding1_Range {
  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);
    return_trace (c->check_struct (this));
  }

  HBUINT8   first;
  HBUINT8   nLeft;

  DEFINE_SIZE_STATIC (2);
};

struct Encoding1 {
  inline unsigned int get_size (void) const
  { return HBUINT8::static_size + Encoding1_Range::static_size * nRanges; }

  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);
    return_trace (c->check_struct (this) && ((nRanges == 0) || (ranges[nRanges - 1]).sanitize (c)));
  }

  inline hb_codepoint_t get_code (hb_codepoint_t glyph) const
  {
    assert (glyph > 0);
    glyph--;
    for (unsigned int i = 0; i < nRanges; i++)
    {
      if (glyph <= ranges[i].nLeft)
      {
	return (hb_codepoint_t)ranges[i].first + glyph;
      }
      glyph -= (ranges[i].nLeft + 1);
    }
    return CFF_UNDEF_CODE;
  }

  HBUINT8	   nRanges;
  Encoding1_Range   ranges[VAR];

  DEFINE_SIZE_ARRAY (1, ranges);
};

struct SuppEncoding {
  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);
    return_trace (c->check_struct (this));
  }

  HBUINT8   code;
  HBUINT16  glyph;

  DEFINE_SIZE_STATIC (3);
};

struct CFF1SuppEncData {
  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);
    return_trace (c->check_struct (this) && ((nSups == 0) || (supps[nSups - 1]).sanitize (c)));
  }

  inline void get_codes (hb_codepoint_t sid, hb_vector_t<hb_codepoint_t> &codes) const
  {
    for (unsigned int i = 0; i < nSups; i++)
      if (sid == supps[i].glyph)
	codes.push (supps[i].code);
  }

  inline unsigned int get_size (void) const
  { return HBUINT8::static_size + SuppEncoding::static_size * nSups; }

  HBUINT8	 nSups;
  SuppEncoding   supps[VAR];

  DEFINE_SIZE_ARRAY (1, supps);
};

struct Encoding {
  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);

    if (unlikely (!c->check_struct (this)))
      return_trace (false);
    unsigned int fmt = format & 0x7F;
    if (unlikely (fmt > 1))
      return_trace (false);
    if (unlikely (!((fmt == 0)? u.format0.sanitize (c): u.format1.sanitize (c))))
      return_trace (false);
    return_trace (((format & 0x80) == 0) || suppEncData ().sanitize (c));
  }

  /* serialize a fullset Encoding */
  inline bool serialize (hb_serialize_context_t *c, const Encoding &src)
  {
    TRACE_SERIALIZE (this);
    unsigned int size = src.get_size ();
    Encoding *dest = c->allocate_size<Encoding> (size);
    if (unlikely (dest == nullptr)) return_trace (false);
    memcpy (dest, &src, size);
    return_trace (true);
  }

  /* serialize a subset Encoding */
  inline bool serialize (hb_serialize_context_t *c,
			 uint8_t format,
			 unsigned int enc_count,
			 const hb_vector_t<code_pair>& code_ranges,
			 const hb_vector_t<code_pair>& supp_codes)
  {
    TRACE_SERIALIZE (this);
    Encoding *dest = c->extend_min (*this);
    if (unlikely (dest == nullptr)) return_trace (false);
    dest->format.set (format | ((supp_codes.len > 0)? 0x80: 0));
    if (format == 0)
    {
      Encoding0 *fmt0 = c->allocate_size<Encoding0> (Encoding0::min_size + HBUINT8::static_size * enc_count);
    if (unlikely (fmt0 == nullptr)) return_trace (false);
      fmt0->nCodes.set (enc_count);
      unsigned int glyph = 0;
      for (unsigned int i = 0; i < code_ranges.len; i++)
      {
	hb_codepoint_t code = code_ranges[i].code;
	for (int left = (int)code_ranges[i].glyph; left >= 0; left--)
	  fmt0->codes[glyph++].set (code++);
	assert ((glyph <= 0x100) && (code <= 0x100));
      }
    }
    else
    {
      Encoding1 *fmt1 = c->allocate_size<Encoding1> (Encoding1::min_size + Encoding1_Range::static_size * code_ranges.len);
      if (unlikely (fmt1 == nullptr)) return_trace (false);
      fmt1->nRanges.set (code_ranges.len);
      for (unsigned int i = 0; i < code_ranges.len; i++)
      {
	assert ((code_ranges[i].code <= 0xFF) && (code_ranges[i].glyph <= 0xFF));
	fmt1->ranges[i].first.set (code_ranges[i].code);
	fmt1->ranges[i].nLeft.set (code_ranges[i].glyph);
      }
    }
    if (supp_codes.len > 0)
    {
      CFF1SuppEncData *suppData = c->allocate_size<CFF1SuppEncData> (CFF1SuppEncData::min_size + SuppEncoding::static_size * supp_codes.len);
      if (unlikely (suppData == nullptr)) return_trace (false);
      suppData->nSups.set (supp_codes.len);
      for (unsigned int i = 0; i < supp_codes.len; i++)
      {
	suppData->supps[i].code.set (supp_codes[i].code);
	suppData->supps[i].glyph.set (supp_codes[i].glyph); /* actually SID */
      }
    }
    return_trace (true);
  }

  /* parallel to above: calculate the size of a subset Encoding */
  static inline unsigned int calculate_serialized_size (
			uint8_t format,
			unsigned int enc_count,
			unsigned int supp_count)
  {
    unsigned int  size = min_size;
    if (format == 0)
      size += Encoding0::min_size + HBUINT8::static_size * enc_count;
    else
      size += Encoding1::min_size + Encoding1_Range::static_size * enc_count;
    if (supp_count > 0)
      size += CFF1SuppEncData::min_size + SuppEncoding::static_size * supp_count;
    return size;
  }

  inline unsigned int get_size (void) const
  {
    unsigned int size = min_size;
    if (table_format () == 0)
      size += u.format0.get_size ();
    else
      size += u.format1.get_size ();
    if (has_supplement ())
      size += suppEncData ().get_size ();
    return size;
  }

  inline hb_codepoint_t get_code (hb_codepoint_t glyph) const
  {
    if (table_format () == 0)
      return u.format0.get_code (glyph);
    else
      return u.format1.get_code (glyph);
  }

  inline uint8_t table_format (void) const { return (format & 0x7F); }
  inline bool  has_supplement (void) const { return (format & 0x80) != 0; }

  inline void get_supplement_codes (hb_codepoint_t sid, hb_vector_t<hb_codepoint_t> &codes) const
  {
    codes.resize (0);
    if (has_supplement ())
      suppEncData().get_codes (sid, codes);
  }

  protected:
  inline const CFF1SuppEncData &suppEncData (void) const
  {
    if ((format & 0x7F) == 0)
      return StructAfter<CFF1SuppEncData> (u.format0.codes[u.format0.nCodes-1]);
    else
      return StructAfter<CFF1SuppEncData> (u.format1.ranges[u.format1.nRanges-1]);
  }

  public:
  HBUINT8       format;

  union {
    Encoding0   format0;
    Encoding1   format1;
  } u;
  /* CFF1SuppEncData  suppEncData; */

  DEFINE_SIZE_MIN (1);
};

/* Charset */
struct Charset0 {
  inline bool sanitize (hb_sanitize_context_t *c, unsigned int num_glyphs) const
  {
    TRACE_SANITIZE (this);
    return_trace (c->check_struct (this) && sids[num_glyphs - 1].sanitize (c));
  }

  inline hb_codepoint_t get_sid (hb_codepoint_t glyph) const
  {
    if (glyph == 0)
      return 0;
    else
      return sids[glyph - 1];
  }

  inline hb_codepoint_t get_glyph (hb_codepoint_t sid, unsigned int num_glyphs) const
  {
    if (sid == 0)
      return 0;

    for (unsigned int glyph = 1; glyph < num_glyphs; glyph++)
    {
      if (sids[glyph-1] == sid)
	return glyph;
    }
    return 0;
  }

  inline unsigned int get_size (unsigned int num_glyphs) const
  {
    assert (num_glyphs > 0);
    return HBUINT16::static_size * (num_glyphs - 1);
  }

  HBUINT16  sids[VAR];

  DEFINE_SIZE_ARRAY(0, sids);
};

template <typename TYPE>
struct Charset_Range {
  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);
    return_trace (c->check_struct (this));
  }

  HBUINT16  first;
  TYPE      nLeft;

  DEFINE_SIZE_STATIC (HBUINT16::static_size + TYPE::static_size);
};

template <typename TYPE>
struct Charset1_2 {
  inline bool sanitize (hb_sanitize_context_t *c, unsigned int num_glyphs) const
  {
    TRACE_SANITIZE (this);
    if (unlikely (!c->check_struct (this)))
      return_trace (false);
    num_glyphs--;
    for (unsigned int i = 0; num_glyphs > 0; i++)
    {
      if (unlikely (!ranges[i].sanitize (c) || (num_glyphs < ranges[i].nLeft + 1)))
	return_trace (false);
      num_glyphs -= (ranges[i].nLeft + 1);
    }
    return_trace (true);
  }

  inline hb_codepoint_t get_sid (hb_codepoint_t glyph) const
  {
    if (glyph == 0) return 0;
    glyph--;
    for (unsigned int i = 0;; i++)
    {
      if (glyph <= ranges[i].nLeft)
	return (hb_codepoint_t)ranges[i].first + glyph;
      glyph -= (ranges[i].nLeft + 1);
    }

    return 0;
  }

  inline hb_codepoint_t get_glyph (hb_codepoint_t sid) const
  {
    if (sid == 0) return 0;
    hb_codepoint_t  glyph = 1;
    for (unsigned int i = 0;; i++)
    {
      if ((ranges[i].first <= sid) && sid <= ranges[i].first + ranges[i].nLeft)
	return glyph + (sid - ranges[i].first);
      glyph += (ranges[i].nLeft + 1);
    }

    return 0;
  }

  inline unsigned int get_size (unsigned int num_glyphs) const
  {
    unsigned int size = HBUINT8::static_size;
    int glyph = (int)num_glyphs;

    assert (glyph > 0);
    glyph--;
    for (unsigned int i = 0; glyph > 0; i++)
    {
      glyph -= (ranges[i].nLeft + 1);
      size += Charset_Range<TYPE>::static_size;
    }

    return size;
  }

  Charset_Range<TYPE>   ranges[VAR];

  DEFINE_SIZE_ARRAY (0, ranges);
};

typedef Charset1_2<HBUINT8>     Charset1;
typedef Charset1_2<HBUINT16>    Charset2;
typedef Charset_Range<HBUINT8>  Charset1_Range;
typedef Charset_Range<HBUINT16> Charset2_Range;

struct Charset {
  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);

    if (unlikely (!c->check_struct (this)))
      return_trace (false);
    if (format == 0)
      return_trace (u.format0.sanitize (c, c->get_num_glyphs ()));
    else if (format == 1)
      return_trace (u.format1.sanitize (c, c->get_num_glyphs ()));
    else if (likely (format == 2))
      return_trace (u.format2.sanitize (c, c->get_num_glyphs ()));
    else
      return_trace (false);
  }

  /* serialize a fullset Charset */
  inline bool serialize (hb_serialize_context_t *c, const Charset &src, unsigned int num_glyphs)
  {
    TRACE_SERIALIZE (this);
    unsigned int size = src.get_size (num_glyphs);
    Charset *dest = c->allocate_size<Charset> (size);
    if (unlikely (dest == nullptr)) return_trace (false);
    memcpy (dest, &src, size);
    return_trace (true);
  }

  /* serialize a subset Charset */
  inline bool serialize (hb_serialize_context_t *c,
			 uint8_t format,
			 unsigned int num_glyphs,
			 const hb_vector_t<code_pair>& sid_ranges)
  {
    TRACE_SERIALIZE (this);
    Charset *dest = c->extend_min (*this);
    if (unlikely (dest == nullptr)) return_trace (false);
    dest->format.set (format);
    if (format == 0)
    {
      Charset0 *fmt0 = c->allocate_size<Charset0> (Charset0::min_size + HBUINT16::static_size * (num_glyphs - 1));
    if (unlikely (fmt0 == nullptr)) return_trace (false);
      unsigned int glyph = 0;
      for (unsigned int i = 0; i < sid_ranges.len; i++)
      {
	hb_codepoint_t sid = sid_ranges[i].code;
	for (int left = (int)sid_ranges[i].glyph; left >= 0; left--)
	  fmt0->sids[glyph++].set (sid++);
      }
    }
    else if (format == 1)
    {
      Charset1 *fmt1 = c->allocate_size<Charset1> (Charset1::min_size + Charset1_Range::static_size * sid_ranges.len);
      if (unlikely (fmt1 == nullptr)) return_trace (false);
      for (unsigned int i = 0; i < sid_ranges.len; i++)
      {
	assert (sid_ranges[i].glyph <= 0xFF);
	fmt1->ranges[i].first.set (sid_ranges[i].code);
	fmt1->ranges[i].nLeft.set (sid_ranges[i].glyph);
      }
    }
    else /* format 2 */
    {
      Charset2 *fmt2 = c->allocate_size<Charset2> (Charset2::min_size + Charset2_Range::static_size * sid_ranges.len);
      if (unlikely (fmt2 == nullptr)) return_trace (false);
      for (unsigned int i = 0; i < sid_ranges.len; i++)
      {
	assert (sid_ranges[i].glyph <= 0xFFFF);
	fmt2->ranges[i].first.set (sid_ranges[i].code);
	fmt2->ranges[i].nLeft.set (sid_ranges[i].glyph);
      }
    }
    return_trace (true);
  }

  /* parallel to above: calculate the size of a subset Charset */
  static inline unsigned int calculate_serialized_size (
			uint8_t format,
			unsigned int count)
  {
    unsigned int  size = min_size;
    if (format == 0)
      size += Charset0::min_size + HBUINT16::static_size * (count - 1);
    else if (format == 1)
      size += Charset1::min_size + Charset1_Range::static_size * count;
    else
      size += Charset2::min_size + Charset2_Range::static_size * count;

    return size;
  }

  inline unsigned int get_size (unsigned int num_glyphs) const
  {
    unsigned int size = min_size;
    if (format == 0)
      size += u.format0.get_size (num_glyphs);
    else if (format == 1)
      size += u.format1.get_size (num_glyphs);
    else
      size += u.format2.get_size (num_glyphs);
    return size;
  }

  inline hb_codepoint_t get_sid (hb_codepoint_t glyph) const
  {
    if (format == 0)
      return u.format0.get_sid (glyph);
    else if (format == 1)
      return u.format1.get_sid (glyph);
    else
      return u.format2.get_sid (glyph);
  }

  inline hb_codepoint_t get_glyph (hb_codepoint_t sid, unsigned int num_glyphs) const
  {
    if (format == 0)
      return u.format0.get_glyph (sid, num_glyphs);
    else if (format == 1)
      return u.format1.get_glyph (sid);
    else
      return u.format2.get_glyph (sid);
  }

  HBUINT8       format;
  union {
    Charset0    format0;
    Charset1    format1;
    Charset2    format2;
  } u;

  DEFINE_SIZE_MIN (1);
};

struct CFF1StringIndex : CFF1Index
{
  inline bool serialize (hb_serialize_context_t *c, const CFF1StringIndex &strings, unsigned int offSize_, const Remap &sidmap)
  {
    TRACE_SERIALIZE (this);
    if (unlikely ((strings.count == 0) || (sidmap.get_count () == 0)))
    {
      if (!unlikely (c->extend_min (this->count)))
	return_trace (false);
      count.set (0);
      return_trace (true);
    }

    ByteStrArray bytesArray;
    bytesArray.init ();
    if (!bytesArray.resize (sidmap.get_count ()))
      return_trace (false);
    for (unsigned int i = 0; i < strings.count; i++)
    {
      hb_codepoint_t  j = sidmap[i];
      if (j != CFF_UNDEF_CODE)
	bytesArray[j] = strings[i];
    }

    bool result = CFF1Index::serialize (c, offSize_, bytesArray);
    bytesArray.fini ();
    return_trace (result);
  }

  /* in parallel to above */
  inline unsigned int calculate_serialized_size (unsigned int &offSize /*OUT*/, const Remap &sidmap) const
  {
    offSize = 0;
    if ((count == 0) || (sidmap.get_count () == 0))
      return count.static_size;

    unsigned int dataSize = 0;
    for (unsigned int i = 0; i < count; i++)
      if (sidmap[i] != CFF_UNDEF_CODE)
	dataSize += length_at (i);

    offSize = calcOffSize(dataSize);
    return CFF1Index::calculate_serialized_size (offSize, sidmap.get_count (), dataSize);
  }
};

struct CFF1TopDictInterpEnv : NumInterpEnv
{
  inline CFF1TopDictInterpEnv (void)
    : NumInterpEnv(), prev_offset(0), last_offset(0) {}

  unsigned int prev_offset;
  unsigned int last_offset;
};

struct NameDictValues
{
  enum NameDictValIndex
  {
      version,
      notice,
      copyright,
      fullName,
      familyName,
      weight,
      postscript,
      fontName,
      baseFontName,
      registry,
      ordering,

      ValCount
  };

  inline void init (void)
  {
    for (unsigned int i = 0; i < ValCount; i++)
      values[i] = CFF_UNDEF_SID;
  }

  inline unsigned int& operator[] (unsigned int i)
  { assert (i < ValCount); return values[i]; }

  inline unsigned int operator[] (unsigned int i) const
  { assert (i < ValCount); return values[i]; }

  static inline enum NameDictValIndex name_op_to_index (OpCode op)
  {
    switch (op) {
      default:
      case OpCode_version:
	return version;
      case OpCode_Notice:
	return notice;
      case OpCode_Copyright:
	return copyright;
      case OpCode_FullName:
	return fullName;
      case OpCode_FamilyName:
	return familyName;
      case OpCode_Weight:
	return weight;
      case OpCode_PostScript:
	return postscript;
      case OpCode_FontName:
	return fontName;
      }
  }

  unsigned int  values[ValCount];
};

struct CFF1TopDictVal : OpStr
{
  unsigned int  last_arg_offset;
};

struct CFF1TopDictValues : TopDictValues<CFF1TopDictVal>
{
  inline void init (void)
  {
    TopDictValues<CFF1TopDictVal>::init ();

    nameSIDs.init ();
    ros_supplement = 0;
    cidCount = 8720;
    EncodingOffset = 0;
    CharsetOffset = 0;
    FDSelectOffset = 0;
    privateDictInfo.init ();
  }

  inline void fini (void)
  {
    TopDictValues<CFF1TopDictVal>::fini ();
  }

  inline bool is_CID (void) const
  { return nameSIDs[NameDictValues::registry] != CFF_UNDEF_SID; }

  NameDictValues  nameSIDs;
  unsigned int    ros_supplement_offset;
  unsigned int    ros_supplement;
  unsigned int    cidCount;

  unsigned int    EncodingOffset;
  unsigned int    CharsetOffset;
  unsigned int    FDSelectOffset;
  TableInfo       privateDictInfo;
};

struct CFF1TopDictOpSet : TopDictOpSet<CFF1TopDictVal>
{
  static inline void process_op (OpCode op, CFF1TopDictInterpEnv& env, CFF1TopDictValues& dictval)
  {
    CFF1TopDictVal  val;
    val.last_arg_offset = (env.last_offset-1) - dictval.opStart;  /* offset to the last argument */

    switch (op) {
      case OpCode_version:
      case OpCode_Notice:
      case OpCode_Copyright:
      case OpCode_FullName:
      case OpCode_FamilyName:
      case OpCode_Weight:
      case OpCode_PostScript:
      case OpCode_BaseFontName:
	dictval.nameSIDs[NameDictValues::name_op_to_index (op)] = env.argStack.pop_uint ();
	env.clear_args ();
	break;
      case OpCode_isFixedPitch:
      case OpCode_ItalicAngle:
      case OpCode_UnderlinePosition:
      case OpCode_UnderlineThickness:
      case OpCode_PaintType:
      case OpCode_CharstringType:
      case OpCode_UniqueID:
      case OpCode_StrokeWidth:
      case OpCode_SyntheticBase:
      case OpCode_CIDFontVersion:
      case OpCode_CIDFontRevision:
      case OpCode_CIDFontType:
      case OpCode_UIDBase:
      case OpCode_FontBBox:
      case OpCode_XUID:
      case OpCode_BaseFontBlend:
	env.clear_args ();
	break;

      case OpCode_CIDCount:
	dictval.cidCount = env.argStack.pop_uint ();
	env.clear_args ();
	break;

      case OpCode_ROS:
	dictval.ros_supplement = env.argStack.pop_uint ();
	dictval.nameSIDs[NameDictValues::ordering] = env.argStack.pop_uint ();
	dictval.nameSIDs[NameDictValues::registry] = env.argStack.pop_uint ();
	env.clear_args ();
	break;

      case OpCode_Encoding:
	dictval.EncodingOffset = env.argStack.pop_uint ();
	env.clear_args ();
	if (unlikely (dictval.EncodingOffset == 0)) return;
	break;

      case OpCode_charset:
	dictval.CharsetOffset = env.argStack.pop_uint ();
	env.clear_args ();
	if (unlikely (dictval.CharsetOffset == 0)) return;
	break;

      case OpCode_FDSelect:
	dictval.FDSelectOffset = env.argStack.pop_uint ();
	env.clear_args ();
	break;

      case OpCode_Private:
	dictval.privateDictInfo.offset = env.argStack.pop_uint ();
	dictval.privateDictInfo.size = env.argStack.pop_uint ();
	env.clear_args ();
	break;

      default:
	env.last_offset = env.substr.offset;
	TopDictOpSet<CFF1TopDictVal>::process_op (op, env, dictval);
	/* Record this operand below if stack is empty, otherwise done */
	if (!env.argStack.is_empty ()) return;
	break;
    }

    if (unlikely (env.in_error ())) return;

    dictval.add_op (op, env.substr, val);
  }
};

struct CFF1FontDictValues : DictValues<OpStr>
{
  inline void init (void)
  {
    DictValues<OpStr>::init ();
    privateDictInfo.init ();
    fontName = CFF_UNDEF_SID;
  }

  inline void fini (void)
  {
    DictValues<OpStr>::fini ();
  }

  TableInfo       privateDictInfo;
  unsigned int    fontName;
};

struct CFF1FontDictOpSet : DictOpSet
{
  static inline void process_op (OpCode op, NumInterpEnv& env, CFF1FontDictValues& dictval)
  {
    switch (op) {
      case OpCode_FontName:
	dictval.fontName = env.argStack.pop_uint ();
	env.clear_args ();
	break;
      case OpCode_FontMatrix:
      case OpCode_PaintType:
	env.clear_args ();
	break;
      case OpCode_Private:
	dictval.privateDictInfo.offset = env.argStack.pop_uint ();
	dictval.privateDictInfo.size = env.argStack.pop_uint ();
	env.clear_args ();
	break;

      default:
	DictOpSet::process_op (op, env);
	if (!env.argStack.is_empty ()) return;
	break;
    }

    if (unlikely (env.in_error ())) return;

    dictval.add_op (op, env.substr);
  }
};

template <typename VAL>
struct CFF1PrivateDictValues_Base : DictValues<VAL>
{
  inline void init (void)
  {
    DictValues<VAL>::init ();
    subrsOffset = 0;
    localSubrs = &Null(CFF1Subrs);
  }

  inline void fini (void)
  {
    DictValues<VAL>::fini ();
  }

  inline unsigned int calculate_serialized_size (void) const
  {
    unsigned int size = 0;
    for (unsigned int i = 0; i < DictValues<VAL>::get_count; i++)
      if (DictValues<VAL>::get_value (i).op == OpCode_Subrs)
	size += OpCode_Size (OpCode_shortint) + 2 + OpCode_Size (OpCode_Subrs);
      else
	size += DictValues<VAL>::get_value (i).str.len;
    return size;
  }

  unsigned int      subrsOffset;
  const CFF1Subrs    *localSubrs;
};

typedef CFF1PrivateDictValues_Base<OpStr> CFF1PrivateDictValues_Subset;
typedef CFF1PrivateDictValues_Base<NumDictVal> CFF1PrivateDictValues;

struct CFF1PrivateDictOpSet : DictOpSet
{
  static inline void process_op (OpCode op, NumInterpEnv& env, CFF1PrivateDictValues& dictval)
  {
    NumDictVal val;
    val.init ();

    switch (op) {
      case OpCode_BlueValues:
      case OpCode_OtherBlues:
      case OpCode_FamilyBlues:
      case OpCode_FamilyOtherBlues:
      case OpCode_StemSnapH:
      case OpCode_StemSnapV:
	env.clear_args ();
	break;
      case OpCode_StdHW:
      case OpCode_StdVW:
      case OpCode_BlueScale:
      case OpCode_BlueShift:
      case OpCode_BlueFuzz:
      case OpCode_ForceBold:
      case OpCode_LanguageGroup:
      case OpCode_ExpansionFactor:
      case OpCode_initialRandomSeed:
      case OpCode_defaultWidthX:
      case OpCode_nominalWidthX:
	val.single_val = env.argStack.pop_num ();
	env.clear_args ();
	break;
      case OpCode_Subrs:
	dictval.subrsOffset = env.argStack.pop_uint ();
	env.clear_args ();
	break;

      default:
	DictOpSet::process_op (op, env);
	if (!env.argStack.is_empty ()) return;
	break;
    }

    if (unlikely (env.in_error ())) return;

    dictval.add_op (op, env.substr, val);
  }
};

struct CFF1PrivateDictOpSet_Subset : DictOpSet
{
  static inline void process_op (OpCode op, NumInterpEnv& env, CFF1PrivateDictValues_Subset& dictval)
  {
    switch (op) {
      case OpCode_BlueValues:
      case OpCode_OtherBlues:
      case OpCode_FamilyBlues:
      case OpCode_FamilyOtherBlues:
      case OpCode_StemSnapH:
      case OpCode_StemSnapV:
      case OpCode_StdHW:
      case OpCode_StdVW:
      case OpCode_BlueScale:
      case OpCode_BlueShift:
      case OpCode_BlueFuzz:
      case OpCode_ForceBold:
      case OpCode_LanguageGroup:
      case OpCode_ExpansionFactor:
      case OpCode_initialRandomSeed:
      case OpCode_defaultWidthX:
      case OpCode_nominalWidthX:
	env.clear_args ();
	break;

      case OpCode_Subrs:
	dictval.subrsOffset = env.argStack.pop_uint ();
	env.clear_args ();
	break;

      default:
	DictOpSet::process_op (op, env);
	if (!env.argStack.is_empty ()) return;
	break;
    }

    if (unlikely (env.in_error ())) return;

    dictval.add_op (op, env.substr);
  }
};

typedef DictInterpreter<CFF1TopDictOpSet, CFF1TopDictValues, CFF1TopDictInterpEnv> CFF1TopDict_Interpreter;
typedef DictInterpreter<CFF1FontDictOpSet, CFF1FontDictValues> CFF1FontDict_Interpreter;
typedef DictInterpreter<CFF1PrivateDictOpSet, CFF1PrivateDictValues> CFF1PrivateDict_Interpreter;

typedef CFF1Index CFF1NameIndex;
typedef CFF1IndexOf<TopDict> CFF1TopDictIndex;

}; /* namespace CFF */

namespace OT {

using namespace CFF;

struct cff1
{
  static const hb_tag_t tableTag	= HB_OT_TAG_cff1;

  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);
    return_trace (c->check_struct (this) &&
		  likely (version.major == 1));
  }

  template <typename PRIVOPSET, typename PRIVDICTVAL>
  struct accelerator_templ_t
  {
    inline void init (hb_face_t *face)
    {
      topDict.init ();
      fontDicts.init ();
      privateDicts.init ();

      this->blob = sc.reference_table<cff1> (face);

      /* setup for run-time santization */
      sc.init (this->blob);
      sc.start_processing ();

      const OT::cff1 *cff = this->blob->template as<OT::cff1> ();

      if (cff == &Null(OT::cff1))
      { fini (); return; }

      nameIndex = &cff->nameIndex (cff);
      if ((nameIndex == &Null (CFF1NameIndex)) || !nameIndex->sanitize (&sc))
      { fini (); return; }

      topDictIndex = &StructAtOffset<CFF1TopDictIndex> (nameIndex, nameIndex->get_size ());
      if ((topDictIndex == &Null (CFF1TopDictIndex)) || !topDictIndex->sanitize (&sc) || (topDictIndex->count == 0))
      { fini (); return; }

      { /* parse top dict */
	const ByteStr topDictStr = (*topDictIndex)[0];
	if (unlikely (!topDictStr.sanitize (&sc))) { fini (); return; }
	CFF1TopDict_Interpreter top_interp;
	top_interp.env.init (topDictStr);
	topDict.init ();
	if (unlikely (!top_interp.interpret (topDict))) { fini (); return; }
      }

      if (is_predef_charset ())
	charset = &Null(Charset);
      else
      {
	charset = &StructAtOffsetOrNull<Charset> (cff, topDict.CharsetOffset);
	if (unlikely ((charset == &Null (Charset)) || !charset->sanitize (&sc))) { fini (); return; }
      }

      fdCount = 1;
      if (is_CID ())
      {
	fdArray = &StructAtOffsetOrNull<CFF1FDArray> (cff, topDict.FDArrayOffset);
	fdSelect = &StructAtOffsetOrNull<CFF1FDSelect> (cff, topDict.FDSelectOffset);
	if (unlikely ((fdArray == &Null(CFF1FDArray)) || !fdArray->sanitize (&sc) ||
	    (fdSelect == &Null(CFF1FDSelect)) || !fdSelect->sanitize (&sc, fdArray->count)))
	{ fini (); return; }

	fdCount = fdArray->count;
      }
      else
      {
	fdArray = &Null(CFF1FDArray);
	fdSelect = &Null(CFF1FDSelect);
      }

      stringIndex = &StructAtOffset<CFF1StringIndex> (topDictIndex, topDictIndex->get_size ());
      if ((stringIndex == &Null (CFF1StringIndex)) || !stringIndex->sanitize (&sc))
      { fini (); return; }

      globalSubrs = &StructAtOffset<CFF1Subrs> (stringIndex, stringIndex->get_size ());
      if ((globalSubrs != &Null (CFF1Subrs)) && !stringIndex->sanitize (&sc))
      { fini (); return; }

      charStrings = &StructAtOffsetOrNull<CFF1CharStrings> (cff, topDict.charStringsOffset);

      if ((charStrings == &Null(CFF1CharStrings)) || unlikely (!charStrings->sanitize (&sc)))
      { fini (); return; }

      num_glyphs = charStrings->count;
      if (num_glyphs != sc.get_num_glyphs ())
      { fini (); return; }

      privateDicts.resize (fdCount);
      for (unsigned int i = 0; i < fdCount; i++)
	privateDicts[i].init ();

      // parse CID font dicts and gather private dicts
      if (is_CID ())
      {
	for (unsigned int i = 0; i < fdCount; i++)
	{
	  ByteStr fontDictStr = (*fdArray)[i];
	  if (unlikely (!fontDictStr.sanitize (&sc))) { fini (); return; }
	  CFF1FontDictValues  *font;
	  CFF1FontDict_Interpreter font_interp;
	  font_interp.env.init (fontDictStr);
	  font = fontDicts.push ();
	  font->init ();
	  if (unlikely (!font_interp.interpret (*font))) { fini (); return; }
	  PRIVDICTVAL  *priv = &privateDicts[i];
	  const ByteStr privDictStr (StructAtOffset<UnsizedByteStr> (cff, font->privateDictInfo.offset), font->privateDictInfo.size);
	  if (unlikely (!privDictStr.sanitize (&sc))) { fini (); return; }
	  DictInterpreter<PRIVOPSET, PRIVDICTVAL> priv_interp;
	  priv_interp.env.init (privDictStr);
	  priv->init ();
	  if (unlikely (!priv_interp.interpret (*priv))) { fini (); return; }

	  priv->localSubrs = &StructAtOffsetOrNull<CFF1Subrs> (privDictStr.str, priv->subrsOffset);
	  if (priv->localSubrs != &Null(CFF1Subrs) &&
	      unlikely (!priv->localSubrs->sanitize (&sc)))
	  { fini (); return; }
	}
      }
      else  /* non-CID */
      {
	CFF1TopDictValues  *font = &topDict;
	PRIVDICTVAL  *priv = &privateDicts[0];

	const ByteStr privDictStr (StructAtOffset<UnsizedByteStr> (cff, font->privateDictInfo.offset), font->privateDictInfo.size);
	if (unlikely (!privDictStr.sanitize (&sc))) { fini (); return; }
	DictInterpreter<PRIVOPSET, PRIVDICTVAL> priv_interp;
	priv_interp.env.init (privDictStr);
	priv->init ();
	if (unlikely (!priv_interp.interpret (*priv))) { fini (); return; }

	priv->localSubrs = &StructAtOffsetOrNull<CFF1Subrs> (privDictStr.str, priv->subrsOffset);
	if (priv->localSubrs != &Null(CFF1Subrs) &&
	    unlikely (!priv->localSubrs->sanitize (&sc)))
	{ fini (); return; }
      }
    }

    inline void fini (void)
    {
      sc.end_processing ();
      topDict.fini ();
      fontDicts.fini ();
      privateDicts.fini_deep ();
      hb_blob_destroy (blob);
      blob = nullptr;
    }

    inline bool is_valid (void) const { return blob != nullptr; }
    inline bool is_CID (void) const { return topDict.is_CID (); }

    inline bool is_predef_charset (void) const { return topDict.CharsetOffset <= ExpertSubsetCharset; }

    inline unsigned int  std_code_to_glyph (hb_codepoint_t code) const
    {
      hb_codepoint_t sid = lookup_standard_encoding_for_sid (code);
      if (unlikely (sid == CFF_UNDEF_SID))
	return 0;

      if (charset != &Null(Charset))
	return charset->get_glyph (sid, num_glyphs);
      else if ((topDict.CharsetOffset == ISOAdobeCharset)
	      && (code <= 228 /*zcaron*/)) return sid;
      return 0;
    }

    protected:
    hb_blob_t	       *blob;
    hb_sanitize_context_t   sc;

    public:
    const Charset	   *charset;
    const CFF1NameIndex     *nameIndex;
    const CFF1TopDictIndex  *topDictIndex;
    const CFF1StringIndex   *stringIndex;
    const CFF1Subrs	 *globalSubrs;
    const CFF1CharStrings   *charStrings;
    const CFF1FDArray       *fdArray;
    const CFF1FDSelect      *fdSelect;
    unsigned int	    fdCount;

    CFF1TopDictValues       topDict;
    hb_vector_t<CFF1FontDictValues>   fontDicts;
    hb_vector_t<PRIVDICTVAL>	  privateDicts;

    unsigned int	    num_glyphs;
  };

  struct accelerator_t : accelerator_templ_t<CFF1PrivateDictOpSet, CFF1PrivateDictValues>
  {
    HB_INTERNAL bool get_extents (hb_codepoint_t glyph, hb_glyph_extents_t *extents) const;
    HB_INTERNAL bool get_seac_components (hb_codepoint_t glyph, hb_codepoint_t *base, hb_codepoint_t *accent) const;
  };

  struct accelerator_subset_t : accelerator_templ_t<CFF1PrivateDictOpSet_Subset, CFF1PrivateDictValues_Subset>
  {
    inline void init (hb_face_t *face)
    {
      SUPER::init (face);
      if (blob == nullptr) return;

      const OT::cff1 *cff = this->blob->as<OT::cff1> ();
      encoding = &Null(Encoding);
      if (is_CID ())
      {
	if (unlikely (charset == &Null(Charset))) { fini (); return; }
      }
      else
      {
	if (!is_predef_encoding ())
	{
	  encoding = &StructAtOffsetOrNull<Encoding> (cff, topDict.EncodingOffset);
	  if (unlikely ((encoding == &Null (Encoding)) || !encoding->sanitize (&sc))) { fini (); return; }
	}
      }
    }

    inline bool is_predef_encoding (void) const { return topDict.EncodingOffset <= ExpertEncoding; }

    inline hb_codepoint_t  glyph_to_code (hb_codepoint_t glyph) const
    {
      if (encoding != &Null(Encoding))
	return encoding->get_code (glyph);
      else
      {
	hb_codepoint_t  sid = glyph_to_sid (glyph);
	if (sid == 0) return 0;
	hb_codepoint_t  code = 0;
	switch (topDict.EncodingOffset)
	{
	  case  StandardEncoding:
	    code = lookup_standard_encoding_for_code (sid);
	    break;
	  case  ExpertEncoding:
	    code = lookup_expert_encoding_for_code (sid);
	    break;
	  default:
	    break;
	}
	return code;
      }
    }

    inline hb_codepoint_t  glyph_to_sid (hb_codepoint_t glyph) const
    {
      if (charset != &Null(Charset))
	return charset->get_sid (glyph);
      else
      {
	hb_codepoint_t sid = 0;
	switch (topDict.CharsetOffset)
	{
	  case  ISOAdobeCharset:
	    if (glyph <= 228 /*zcaron*/) sid = glyph;
	    break;
	  case  ExpertCharset:
	    sid = lookup_expert_charset_for_sid (glyph);
	    break;
	  case  ExpertSubsetCharset:
	      sid = lookup_expert_subset_charset_for_sid (glyph);
	    break;
	  default:
	    break;
	}
	return sid;
      }
    }

    const Encoding	  *encoding;

    private:
    typedef accelerator_templ_t<CFF1PrivateDictOpSet_Subset, CFF1PrivateDictValues_Subset> SUPER;
  };

  inline bool subset (hb_subset_plan_t *plan) const
  {
    hb_blob_t *cff_prime = nullptr;

    bool success = true;
    if (hb_subset_cff1 (plan, &cff_prime)) {
      success = success && plan->add_table (HB_OT_TAG_cff1, cff_prime);
      hb_blob_t *head_blob = hb_sanitize_context_t().reference_table<head> (plan->source);
      success = success && head_blob && plan->add_table (HB_OT_TAG_head, head_blob);
      hb_blob_destroy (head_blob);
    } else {
      success = false;
    }
    hb_blob_destroy (cff_prime);

    return success;
  }

  protected:
  HB_INTERNAL static hb_codepoint_t lookup_standard_encoding_for_code (hb_codepoint_t sid);
  HB_INTERNAL static hb_codepoint_t lookup_expert_encoding_for_code (hb_codepoint_t sid);
  HB_INTERNAL static hb_codepoint_t lookup_expert_charset_for_sid (hb_codepoint_t glyph);
  HB_INTERNAL static hb_codepoint_t lookup_expert_subset_charset_for_sid (hb_codepoint_t glyph);
  HB_INTERNAL static hb_codepoint_t lookup_standard_encoding_for_sid (hb_codepoint_t code);

  public:
  FixedVersion<HBUINT8> version;	  /* Version of CFF table. set to 0x0100u */
  OffsetTo<CFF1NameIndex, HBUINT8> nameIndex; /* headerSize = Offset to Name INDEX. */
  HBUINT8	       offSize;	  /* offset size (unused?) */

  public:
  DEFINE_SIZE_STATIC (4);
};

struct cff1_accelerator_t : cff1::accelerator_t {};
} /* namespace OT */

#endif /* HB_OT_CFF1_TABLE_HH */

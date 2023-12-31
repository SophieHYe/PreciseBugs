/*
 * Copyright © 2018  Ebrahim Byagowi
 * Copyright © 2018  Google, Inc.
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
 * Google Author(s): Behdad Esfahbod
 */

#ifndef HB_AAT_LAYOUT_KERX_TABLE_HH
#define HB_AAT_LAYOUT_KERX_TABLE_HH

#include "hb-kern.hh"
#include "hb-aat-layout-ankr-table.hh"

/*
 * kerx -- Extended Kerning
 * https://developer.apple.com/fonts/TrueType-Reference-Manual/RM06/Chap6kerx.html
 */
#define HB_AAT_TAG_kerx HB_TAG('k','e','r','x')


namespace AAT {

using namespace OT;


static inline int
kerxTupleKern (int value,
	       unsigned int tupleCount,
	       const void *base,
	       hb_aat_apply_context_t *c)
{
  if (likely (!tupleCount)) return value;

  unsigned int offset = value;
  const FWORD *pv = &StructAtOffset<FWORD> (base, offset);
  if (unlikely (!c->sanitizer.check_array (pv, tupleCount))) return 0;
  return *pv;
}


struct hb_glyph_pair_t
{
  hb_codepoint_t left;
  hb_codepoint_t right;
};

struct KernPair
{
  inline int get_kerning (void) const
  { return value; }

  inline int cmp (const hb_glyph_pair_t &o) const
  {
    int ret = left.cmp (o.left);
    if (ret) return ret;
    return right.cmp (o.right);
  }

  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);
    return_trace (c->check_struct (this));
  }

  protected:
  GlyphID	left;
  GlyphID	right;
  FWORD		value;
  public:
  DEFINE_SIZE_STATIC (6);
};

template <typename KernSubTableHeader>
struct KerxSubTableFormat0
{
  inline int get_kerning (hb_codepoint_t left, hb_codepoint_t right) const
  {
    hb_glyph_pair_t pair = {left, right};
    int i = pairs.bsearch (pair);
    if (i == -1) return 0;
    return pairs[i].get_kerning ();
  }

  inline int get_kerning (hb_codepoint_t left, hb_codepoint_t right,
			  hb_aat_apply_context_t *c) const
  {
    hb_glyph_pair_t pair = {left, right};
    int i = pairs.bsearch (pair);
    if (i == -1) return 0;
    int v = pairs[i].get_kerning ();
    return kerxTupleKern (v, header.tuple_count (), this, c);
  }

  inline bool apply (hb_aat_apply_context_t *c) const
  {
    TRACE_APPLY (this);

    if (!c->plan->requested_kerning)
      return false;

    if (header.coverage & header.Backwards)
      return false;

    accelerator_t accel (*this, c);
    hb_kern_machine_t<accelerator_t> machine (accel, header.coverage & header.CrossStream);
    machine.kern (c->font, c->buffer, c->plan->kern_mask);

    return_trace (true);
  }

  struct accelerator_t
  {
    const KerxSubTableFormat0 &table;
    hb_aat_apply_context_t *c;

    inline accelerator_t (const KerxSubTableFormat0 &table_,
			  hb_aat_apply_context_t *c_) :
			    table (table_), c (c_) {}

    inline int get_kerning (hb_codepoint_t left, hb_codepoint_t right) const
    { return table.get_kerning (left, right, c); }
  };


  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);
    return_trace (likely (pairs.sanitize (c)));
  }

  protected:
  KernSubTableHeader	header;
  BinSearchArrayOf<KernPair, typename KernSubTableHeader::Types::HBUINT>
			pairs;	/* Sorted kern records. */
  public:
  DEFINE_SIZE_ARRAY (KernSubTableHeader::static_size + 16, pairs);
};


template <bool extended>
struct Format1Entry;

template <>
struct Format1Entry<true>
{
  enum Flags
  {
    Push		= 0x8000,	/* If set, push this glyph on the kerning stack. */
    DontAdvance		= 0x4000,	/* If set, don't advance to the next glyph
					 * before going to the new state. */
    Reset		= 0x2000,	/* If set, reset the kerning data (clear the stack) */
    Reserved		= 0x1FFF,	/* Not used; set to 0. */
  };

  struct EntryData
  {
    HBUINT16	kernActionIndex;/* Index into the kerning value array. If
				 * this index is 0xFFFF, then no kerning
				 * is to be performed. */
    public:
    DEFINE_SIZE_STATIC (2);
  };

  static inline bool performAction (const Entry<EntryData> *entry)
  { return entry->data.kernActionIndex != 0xFFFF; }

  static inline unsigned int kernActionIndex (const Entry<EntryData> *entry)
  { return entry->data.kernActionIndex; }
};
template <>
struct Format1Entry<false>
{
  enum Flags
  {
    Push		= 0x8000,	/* If set, push this glyph on the kerning stack. */
    DontAdvance		= 0x4000,	/* If set, don't advance to the next glyph
					 * before going to the new state. */
    Offset		= 0x3FFF,	/* Byte offset from beginning of subtable to the
					 * value table for the glyphs on the kerning stack. */

    Reset		= 0x0000,	/* Not supported? */
  };

  typedef void EntryData;

  static inline bool performAction (const Entry<EntryData> *entry)
  { return entry->flags & Offset; }

  static inline unsigned int kernActionIndex (const Entry<EntryData> *entry)
  { return entry->flags & Offset; }
};

template <typename KernSubTableHeader>
struct KerxSubTableFormat1
{
  typedef typename KernSubTableHeader::Types Types;
  typedef typename Types::HBUINT HBUINT;

  typedef Format1Entry<Types::extended> Format1EntryT;
  typedef typename Format1EntryT::EntryData EntryData;

  struct driver_context_t
  {
    static const bool in_place = true;
    enum
    {
      DontAdvance	= Format1EntryT::DontAdvance,
    };

    inline driver_context_t (const KerxSubTableFormat1 *table_,
			     hb_aat_apply_context_t *c_) :
	c (c_),
	table (table_),
	/* Apparently the offset kernAction is from the beginning of the state-machine,
	 * similar to offsets in morx table, NOT from beginning of this table, like
	 * other subtables in kerx.  Discovered via testing. */
	kernAction (&table->machine + table->kernAction),
	depth (0),
	crossStream (table->header.coverage & table->header.CrossStream) {}

    inline bool is_actionable (StateTableDriver<Types, EntryData> *driver HB_UNUSED,
			       const Entry<EntryData> *entry)
    {
      return Format1EntryT::performAction (entry);
    }
    inline bool transition (StateTableDriver<Types, EntryData> *driver,
			    const Entry<EntryData> *entry)
    {
      hb_buffer_t *buffer = driver->buffer;
      unsigned int flags = entry->flags;

      if (flags & Format1EntryT::Reset)
	depth = 0;

      if (flags & Format1EntryT::Push)
      {
	if (likely (depth < ARRAY_LENGTH (stack)))
	  stack[depth++] = buffer->idx;
	else
	  depth = 0; /* Probably not what CoreText does, but better? */
      }

      if (Format1EntryT::performAction (entry))
      {
	unsigned int kern_idx = Format1EntryT::kernActionIndex (entry);
	kern_idx = Types::offsetToIndex (kern_idx, &table->machine, kernAction.arrayZ);
	const FWORD *actions = &kernAction[kern_idx];
	if (!c->sanitizer.check_array (actions, depth))
	{
	  depth = 0;
	  return false;
	}

	hb_mask_t kern_mask = c->plan->kern_mask;

	/* From Apple 'kern' spec:
	 * "Each pops one glyph from the kerning stack and applies the kerning value to it.
	 * The end of the list is marked by an odd value... */
	unsigned int tuple_count = table->header.tuple_count ();
	tuple_count = tuple_count ? tuple_count : 1;
	bool last = false;
	while (!last && depth--)
	{
	  unsigned int idx = stack[depth];
	  int v = *actions;
	  actions += tuple_count;
	  if (idx >= buffer->len) continue;

	  /* "The end of the list is marked by an odd value..." */
	  last = v & 1;
	  v &= ~1;

	  hb_glyph_position_t &o = buffer->pos[idx];

	  /* Testing shows that CoreText only applies kern (cross-stream or not)
	   * if none has been applied by previous subtables.  That is, it does
	   * NOT seem to accumulate as otherwise implied by specs. */

	  /* The following flag is undocumented in the spec, but described
	   * in the 'kern' table example. */
	  if (v == -0x8000)
	  {
	    o.attach_type() = ATTACH_TYPE_NONE;
	    o.attach_chain() = 0;
	    o.x_offset = o.y_offset = 0;
	  }
	  else if (HB_DIRECTION_IS_HORIZONTAL (buffer->props.direction))
	  {
	    if (crossStream)
	    {
	      if (buffer->pos[idx].attach_type() && !buffer->pos[idx].y_offset)
	      {
		o.y_offset = c->font->em_scale_y (v);
		buffer->scratch_flags |= HB_BUFFER_SCRATCH_FLAG_HAS_GPOS_ATTACHMENT;
	      }
	    }
	    else if (buffer->info[idx].mask & kern_mask)
	    {
	      if (!buffer->pos[idx].x_offset)
	      {
		buffer->pos[idx].x_advance += c->font->em_scale_x (v);
		buffer->pos[idx].x_offset += c->font->em_scale_x (v);
	      }
	    }
	  }
	  else
	  {
	    if (crossStream)
	    {
	      /* CoreText doesn't do crossStream kerning in vertical.  We do. */
	      if (buffer->pos[idx].attach_type() && !buffer->pos[idx].x_offset)
	      {
		o.x_offset = c->font->em_scale_x (v);
		buffer->scratch_flags |= HB_BUFFER_SCRATCH_FLAG_HAS_GPOS_ATTACHMENT;
	      }
	    }
	    else if (buffer->info[idx].mask & kern_mask)
	    {
	      if (!buffer->pos[idx].y_offset)
	      {
		buffer->pos[idx].y_advance += c->font->em_scale_y (v);
		buffer->pos[idx].y_offset += c->font->em_scale_y (v);
	      }
	    }
	  }
	}
      }

      return true;
    }

    private:
    hb_aat_apply_context_t *c;
    const KerxSubTableFormat1 *table;
    const UnsizedArrayOf<FWORD> &kernAction;
    unsigned int stack[8];
    unsigned int depth;
    bool crossStream;
  };

  inline bool apply (hb_aat_apply_context_t *c) const
  {
    TRACE_APPLY (this);

    if (!c->plan->requested_kerning &&
	!(header.coverage & header.CrossStream))
      return false;

    driver_context_t dc (this, c);

    StateTableDriver<Types, EntryData> driver (machine, c->buffer, c->font->face);
    driver.drive (&dc);

    return_trace (true);
  }

  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);
    /* The rest of array sanitizations are done at run-time. */
    return_trace (likely (c->check_struct (this) &&
			  machine.sanitize (c)));
  }

  protected:
  KernSubTableHeader				header;
  StateTable<Types, EntryData>			machine;
  OffsetTo<UnsizedArrayOf<FWORD>, HBUINT, false>kernAction;
  public:
  DEFINE_SIZE_STATIC (KernSubTableHeader::static_size + 5 * sizeof (HBUINT));
};

template <typename KernSubTableHeader>
struct KerxSubTableFormat2
{
  typedef typename KernSubTableHeader::Types Types;
  typedef typename Types::HBUINT HBUINT;

  inline int get_kerning (hb_codepoint_t left, hb_codepoint_t right,
			  hb_aat_apply_context_t *c) const
  {
    unsigned int num_glyphs = c->sanitizer.get_num_glyphs ();
    unsigned int l = (this+leftClassTable).get_class (left, num_glyphs, 0);
    unsigned int r = (this+rightClassTable).get_class (right, num_glyphs, 0);
    unsigned int offset = l + r;
    const FWORD *v = &StructAtOffset<FWORD> (&(this+array), offset);
    if (unlikely (!v->sanitize (&c->sanitizer))) return 0;
    return kerxTupleKern (*v, header.tuple_count (), this, c);
  }

  inline bool apply (hb_aat_apply_context_t *c) const
  {
    TRACE_APPLY (this);

    if (!c->plan->requested_kerning)
      return false;

    if (header.coverage & header.Backwards)
      return false;

    accelerator_t accel (*this, c);
    hb_kern_machine_t<accelerator_t> machine (accel, header.coverage & header.CrossStream);
    machine.kern (c->font, c->buffer, c->plan->kern_mask);

    return_trace (true);
  }

  struct accelerator_t
  {
    const KerxSubTableFormat2 &table;
    hb_aat_apply_context_t *c;

    inline accelerator_t (const KerxSubTableFormat2 &table_,
			  hb_aat_apply_context_t *c_) :
			    table (table_), c (c_) {}

    inline int get_kerning (hb_codepoint_t left, hb_codepoint_t right) const
    { return table.get_kerning (left, right, c); }
  };

  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);
    return_trace (likely (c->check_struct (this) &&
			  leftClassTable.sanitize (c, this) &&
			  rightClassTable.sanitize (c, this) &&
			  c->check_range (this, array)));
  }

  /* Note:
   * OT kern table specifies ClassTable as having 16-bit entries, whereas
   * AAT kern table specifies them as having 8bit entries.
   * I've not seen any fonts with this format in kern table.
   * We follow AAT. */

  protected:
  KernSubTableHeader	header;
  HBUINT		rowWidth;	/* The width, in bytes, of a row in the table. */
  OffsetTo<typename Types::ClassType, HBUINT, false>
			leftClassTable;	/* Offset from beginning of this subtable to
					 * left-hand class table. */
  OffsetTo<typename Types::ClassType, HBUINT, false>
			rightClassTable;/* Offset from beginning of this subtable to
					 * right-hand class table. */
  OffsetTo<UnsizedArrayOf<FWORD>, HBUINT, false>
			 array;		/* Offset from beginning of this subtable to
					 * the start of the kerning array. */
  public:
  DEFINE_SIZE_STATIC (KernSubTableHeader::static_size + 4 * sizeof (HBUINT));
};

template <typename KernSubTableHeader>
struct KerxSubTableFormat4
{
  typedef ExtendedTypes Types;

  struct EntryData
  {
    HBUINT16	ankrActionIndex;/* Either 0xFFFF (for no action) or the index of
				 * the action to perform. */
    public:
    DEFINE_SIZE_STATIC (2);
  };

  struct driver_context_t
  {
    static const bool in_place = true;
    enum Flags
    {
      Mark		= 0x8000,	/* If set, remember this glyph as the marked glyph. */
      DontAdvance	= 0x4000,	/* If set, don't advance to the next glyph before
					 * going to the new state. */
      Reserved		= 0x3FFF,	/* Not used; set to 0. */
    };

    enum SubTableFlags
    {
      ActionType	= 0xC0000000,	/* A two-bit field containing the action type. */
      Unused		= 0x3F000000,	/* Unused - must be zero. */
      Offset		= 0x00FFFFFF,	/* Masks the offset in bytes from the beginning
					 * of the subtable to the beginning of the control
					 * point table. */
    };

    inline driver_context_t (const KerxSubTableFormat4 *table,
			     hb_aat_apply_context_t *c_) :
	c (c_),
	action_type ((table->flags & ActionType) >> 30),
	ankrData ((HBUINT16 *) ((const char *) &table->machine + (table->flags & Offset))),
	mark_set (false),
	mark (0) {}

    inline bool is_actionable (StateTableDriver<Types, EntryData> *driver HB_UNUSED,
			       const Entry<EntryData> *entry)
    {
      return entry->data.ankrActionIndex != 0xFFFF;
    }
    inline bool transition (StateTableDriver<Types, EntryData> *driver,
			    const Entry<EntryData> *entry)
    {
      hb_buffer_t *buffer = driver->buffer;

      if (mark_set && entry->data.ankrActionIndex != 0xFFFF && buffer->idx < buffer->len)
      {
	hb_glyph_position_t &o = buffer->cur_pos();
	switch (action_type)
	{
	  case 0: /* Control Point Actions.*/
	  {
	    /* indexed into glyph outline. */
	    const HBUINT16 *data = &ankrData[entry->data.ankrActionIndex];
	    if (!c->sanitizer.check_array (data, 2))
	      return false;
	    HB_UNUSED unsigned int markControlPoint = *data++;
	    HB_UNUSED unsigned int currControlPoint = *data++;
	    hb_position_t markX = 0;
	    hb_position_t markY = 0;
	    hb_position_t currX = 0;
	    hb_position_t currY = 0;
	    if (!c->font->get_glyph_contour_point_for_origin (c->buffer->info[mark].codepoint,
							      markControlPoint,
							      HB_DIRECTION_LTR /*XXX*/,
							      &markX, &markY) ||
		!c->font->get_glyph_contour_point_for_origin (c->buffer->cur ().codepoint,
							      currControlPoint,
							      HB_DIRECTION_LTR /*XXX*/,
							      &currX, &currY))
	      return true; /* True, such that the machine continues. */

	    o.x_offset = markX - currX;
	    o.y_offset = markY - currY;
	  }
	  break;

	  case 1: /* Anchor Point Actions. */
	  {
	   /* Indexed into 'ankr' table. */
	    const HBUINT16 *data = &ankrData[entry->data.ankrActionIndex];
	    if (!c->sanitizer.check_array (data, 2))
	      return false;
	    unsigned int markAnchorPoint = *data++;
	    unsigned int currAnchorPoint = *data++;
	    const Anchor markAnchor = c->ankr_table->get_anchor (c->buffer->info[mark].codepoint,
								 markAnchorPoint,
								 c->sanitizer.get_num_glyphs (),
								 c->ankr_end);
	    const Anchor currAnchor = c->ankr_table->get_anchor (c->buffer->cur ().codepoint,
								 currAnchorPoint,
								 c->sanitizer.get_num_glyphs (),
								 c->ankr_end);

	    o.x_offset = c->font->em_scale_x (markAnchor.xCoordinate) - c->font->em_scale_x (currAnchor.xCoordinate);
	    o.y_offset = c->font->em_scale_y (markAnchor.yCoordinate) - c->font->em_scale_y (currAnchor.yCoordinate);
	  }
	  break;

	  case 2: /* Control Point Coordinate Actions. */
	  {
	    const FWORD *data = (const FWORD *) &ankrData[entry->data.ankrActionIndex];
	    if (!c->sanitizer.check_array (data, 4))
	      return false;
	    int markX = *data++;
	    int markY = *data++;
	    int currX = *data++;
	    int currY = *data++;

	    o.x_offset = c->font->em_scale_x (markX) - c->font->em_scale_x (currX);
	    o.y_offset = c->font->em_scale_y (markY) - c->font->em_scale_y (currY);
	  }
	  break;
	}
	o.attach_type() = ATTACH_TYPE_MARK;
	o.attach_chain() = (int) mark - (int) buffer->idx;
	buffer->scratch_flags |= HB_BUFFER_SCRATCH_FLAG_HAS_GPOS_ATTACHMENT;
      }

      if (entry->flags & Mark)
      {
	mark_set = true;
	mark = buffer->idx;
      }

      return true;
    }

    private:
    hb_aat_apply_context_t *c;
    unsigned int action_type;
    const HBUINT16 *ankrData;
    bool mark_set;
    unsigned int mark;
  };

  inline bool apply (hb_aat_apply_context_t *c) const
  {
    TRACE_APPLY (this);

    driver_context_t dc (this, c);

    StateTableDriver<Types, EntryData> driver (machine, c->buffer, c->font->face);
    driver.drive (&dc);

    return_trace (true);
  }

  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);
    /* The rest of array sanitizations are done at run-time. */
    return_trace (likely (c->check_struct (this) &&
			  machine.sanitize (c)));
  }

  protected:
  KernSubTableHeader		header;
  StateTable<Types, EntryData>	machine;
  HBUINT32			flags;
  public:
  DEFINE_SIZE_STATIC (KernSubTableHeader::static_size + 20);
};

template <typename KernSubTableHeader>
struct KerxSubTableFormat6
{
  enum Flags
  {
    ValuesAreLong	= 0x00000001,
  };

  inline bool is_long (void) const { return flags & ValuesAreLong; }

  inline int get_kerning (hb_codepoint_t left, hb_codepoint_t right,
			  hb_aat_apply_context_t *c) const
  {
    unsigned int num_glyphs = c->sanitizer.get_num_glyphs ();
    if (is_long ())
    {
      const typename U::Long &t = u.l;
      unsigned int l = (this+t.rowIndexTable).get_value_or_null (left, num_glyphs);
      unsigned int r = (this+t.columnIndexTable).get_value_or_null (right, num_glyphs);
      unsigned int offset = l + r;
      if (unlikely (offset < l)) return 0; /* Addition overflow. */
      if (unlikely (hb_unsigned_mul_overflows (offset, sizeof (FWORD32)))) return 0;
      const FWORD32 *v = &StructAtOffset<FWORD32> (&(this+t.array), offset * sizeof (FWORD32));
      if (unlikely (!v->sanitize (&c->sanitizer))) return 0;
      return kerxTupleKern (*v, header.tuple_count (), &(this+vector), c);
    }
    else
    {
      const typename U::Short &t = u.s;
      unsigned int l = (this+t.rowIndexTable).get_value_or_null (left, num_glyphs);
      unsigned int r = (this+t.columnIndexTable).get_value_or_null (right, num_glyphs);
      unsigned int offset = l + r;
      const FWORD *v = &StructAtOffset<FWORD> (&(this+t.array), offset * sizeof (FWORD));
      if (unlikely (!v->sanitize (&c->sanitizer))) return 0;
      return kerxTupleKern (*v, header.tuple_count (), &(this+vector), c);
    }
  }

  inline bool apply (hb_aat_apply_context_t *c) const
  {
    TRACE_APPLY (this);

    if (!c->plan->requested_kerning)
      return false;

    if (header.coverage & header.Backwards)
      return false;

    accelerator_t accel (*this, c);
    hb_kern_machine_t<accelerator_t> machine (accel, header.coverage & header.CrossStream);
    machine.kern (c->font, c->buffer, c->plan->kern_mask);

    return_trace (true);
  }

  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);
    return_trace (likely (c->check_struct (this) &&
			  (is_long () ?
			   (
			     u.l.rowIndexTable.sanitize (c, this) &&
			     u.l.columnIndexTable.sanitize (c, this) &&
			     c->check_range (this, u.l.array)
			   ) : (
			     u.s.rowIndexTable.sanitize (c, this) &&
			     u.s.columnIndexTable.sanitize (c, this) &&
			     c->check_range (this, u.s.array)
			   )) &&
			  (header.tuple_count () == 0 ||
			   c->check_range (this, vector))));
  }

  struct accelerator_t
  {
    const KerxSubTableFormat6 &table;
    hb_aat_apply_context_t *c;

    inline accelerator_t (const KerxSubTableFormat6 &table_,
			  hb_aat_apply_context_t *c_) :
			    table (table_), c (c_) {}

    inline int get_kerning (hb_codepoint_t left, hb_codepoint_t right) const
    { return table.get_kerning (left, right, c); }
  };

  protected:
  KernSubTableHeader		header;
  HBUINT32			flags;
  HBUINT16			rowCount;
  HBUINT16			columnCount;
  union U
  {
    struct Long
    {
      LOffsetTo<Lookup<HBUINT32>, false>	rowIndexTable;
      LOffsetTo<Lookup<HBUINT32>, false>	columnIndexTable;
      LOffsetTo<UnsizedArrayOf<FWORD32>, false>	array;
    } l;
    struct Short
    {
      LOffsetTo<Lookup<HBUINT16>, false>	rowIndexTable;
      LOffsetTo<Lookup<HBUINT16>, false>	columnIndexTable;
      LOffsetTo<UnsizedArrayOf<FWORD>, false>	array;
    } s;
  } u;
  LOffsetTo<UnsizedArrayOf<FWORD>, false>	vector;
  public:
  DEFINE_SIZE_STATIC (KernSubTableHeader::static_size + 24);
};


struct KerxSubTableHeader
{
  typedef ExtendedTypes Types;

  inline unsigned int tuple_count (void) const { return tupleCount; }
  inline bool is_horizontal (void) const { return !(coverage & Vertical); }

  enum Coverage
  {
    Vertical	= 0x80000000u,	/* Set if table has vertical kerning values. */
    CrossStream	= 0x40000000u,	/* Set if table has cross-stream kerning values. */
    Variation	= 0x20000000u,	/* Set if table has variation kerning values. */
    Backwards	= 0x10000000u,	/* If clear, process the glyphs forwards, that
				 * is, from first to last in the glyph stream.
				 * If we, process them from last to first.
				 * This flag only applies to state-table based
				 * 'kerx' subtables (types 1 and 4). */
    Reserved	= 0x0FFFFF00u,	/* Reserved, set to zero. */
    SubtableType= 0x000000FFu,	/* Subtable type. */
  };

  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);
    return_trace (likely (c->check_struct (this)));
  }

  public:
  HBUINT32	length;
  HBUINT32	coverage;
  HBUINT32	tupleCount;
  public:
  DEFINE_SIZE_STATIC (12);
};

struct KerxSubTable
{
  friend struct kerx;

  inline unsigned int get_size (void) const { return u.header.length; }
  inline unsigned int get_type (void) const { return u.header.coverage & u.header.SubtableType; }

  template <typename context_t>
  inline typename context_t::return_t dispatch (context_t *c) const
  {
    unsigned int subtable_type = get_type ();
    TRACE_DISPATCH (this, subtable_type);
    switch (subtable_type) {
    case 0:	return_trace (c->dispatch (u.format0));
    case 1:	return_trace (c->dispatch (u.format1));
    case 2:	return_trace (c->dispatch (u.format2));
    case 4:	return_trace (c->dispatch (u.format4));
    case 6:	return_trace (c->dispatch (u.format6));
    default:	return_trace (c->default_return_value ());
    }
  }

  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);
    if (!u.header.sanitize (c) ||
	!c->check_range (this, u.header.length))
      return_trace (false);

    return_trace (dispatch (c));
  }

  public:
  union {
  KerxSubTableHeader				header;
  KerxSubTableFormat0<KerxSubTableHeader>	format0;
  KerxSubTableFormat1<KerxSubTableHeader>	format1;
  KerxSubTableFormat2<KerxSubTableHeader>	format2;
  KerxSubTableFormat4<KerxSubTableHeader>	format4;
  KerxSubTableFormat6<KerxSubTableHeader>	format6;
  } u;
  public:
  DEFINE_SIZE_MIN (12);
};


/*
 * The 'kerx' Table
 */

template <typename T>
struct KerxTable
{
  /* https://en.wikipedia.org/wiki/Curiously_recurring_template_pattern */
  inline const T* thiz (void) const { return static_cast<const T *> (this); }

  inline bool has_cross_stream (void) const
  {
    typedef typename T::SubTable SubTable;

    const SubTable *st = &thiz()->firstSubTable;
    unsigned int count = thiz()->tableCount;
    for (unsigned int i = 0; i < count; i++)
    {
      if (st->u.header.coverage & st->u.header.CrossStream)
        return true;
      st = &StructAfter<SubTable> (*st);
    }
    return false;
  }

  inline int get_h_kerning (hb_codepoint_t left, hb_codepoint_t right) const
  {
    typedef typename T::SubTable SubTable;

    int v = 0;
    const SubTable *st = &thiz()->firstSubTable;
    unsigned int count = thiz()->tableCount;
    for (unsigned int i = 0; i < count; i++)
    {
      if ((st->u.header.coverage & (st->u.header.Variation | st->u.header.CrossStream)) ||
	  !st->u.header.is_horizontal ())
        continue;
      v += st->get_kerning (left, right);
      st = &StructAfter<SubTable> (*st);
    }
    return v;
  }

  inline bool apply (AAT::hb_aat_apply_context_t *c) const
  {
    typedef typename T::SubTable SubTable;

    bool ret = false;
    bool seenCrossStream = false;
    c->set_lookup_index (0);
    const SubTable *st = &thiz()->firstSubTable;
    unsigned int count = thiz()->tableCount;
    for (unsigned int i = 0; i < count; i++)
    {
      bool reverse;

      if (!T::Types::extended && (st->u.header.coverage & st->u.header.Variation))
        goto skip;

      if (HB_DIRECTION_IS_HORIZONTAL (c->buffer->props.direction) != st->u.header.is_horizontal ())
	goto skip;

      reverse = bool (st->u.header.coverage & st->u.header.Backwards) !=
		HB_DIRECTION_IS_BACKWARD (c->buffer->props.direction);

      if (!c->buffer->message (c->font, "start %c%c%c%c subtable %d", HB_UNTAG (thiz()->tableTag), c->lookup_index))
	goto skip;

      if (!seenCrossStream &&
	  (st->u.header.coverage & st->u.header.CrossStream))
      {
        /* Attach all glyphs into a chain. */
        seenCrossStream = true;
	hb_glyph_position_t *pos = c->buffer->pos;
	unsigned int count = c->buffer->len;
	for (unsigned int i = 0; i < count; i++)
	{
	  pos[i].attach_type() = ATTACH_TYPE_CURSIVE;
	  pos[i].attach_chain() = HB_DIRECTION_IS_FORWARD (c->buffer->props.direction) ? -1 : +1;
	  /* We intentionally don't set HB_BUFFER_SCRATCH_FLAG_HAS_GPOS_ATTACHMENT,
	   * since there needs to be a non-zero attachment for post-positioning to
	   * be needed. */
	}
      }

      if (reverse)
	c->buffer->reverse ();

      c->sanitizer.set_object (*st);

      ret |= st->dispatch (c);

      if (reverse)
	c->buffer->reverse ();

      (void) c->buffer->message (c->font, "end %c%c%c%c subtable %d", HB_UNTAG (thiz()->tableTag), c->lookup_index);

    skip:
      st = &StructAfter<SubTable> (*st);
      c->set_lookup_index (c->lookup_index + 1);
    }

    return ret;
  }

  inline bool sanitize (hb_sanitize_context_t *c) const
  {
    TRACE_SANITIZE (this);
    if (unlikely (!thiz()->version.sanitize (c) ||
		  thiz()->version < T::minVersion ||
		  !thiz()->tableCount.sanitize (c)))
      return_trace (false);

    typedef typename T::SubTable SubTable;

    const SubTable *st = &thiz()->firstSubTable;
    unsigned int count = thiz()->tableCount;
    for (unsigned int i = 0; i < count; i++)
    {
      if (unlikely (!st->sanitize (c)))
	return_trace (false);
      st = &StructAfter<SubTable> (*st);
    }

    return_trace (true);
  }
};

struct kerx : KerxTable<kerx>
{
  friend struct KerxTable<kerx>;

  static const hb_tag_t tableTag = HB_AAT_TAG_kerx;
  static const uint16_t minVersion = 2;

  typedef KerxSubTableHeader SubTableHeader;
  typedef SubTableHeader::Types Types;
  typedef KerxSubTable SubTable;

  inline bool has_data (void) const { return version; }

  protected:
  HBUINT16	version;	/* The version number of the extended kerning table
				 * (currently 2, 3, or 4). */
  HBUINT16	unused;		/* Set to 0. */
  HBUINT32	tableCount;	/* The number of subtables included in the extended kerning
				 * table. */
  SubTable	firstSubTable;	/* Subtables. */
/*subtableGlyphCoverageArray*/	/* Only if version >= 3. We don't use. */

  public:
  DEFINE_SIZE_MIN (8);
};


} /* namespace AAT */


#endif /* HB_AAT_LAYOUT_KERX_TABLE_HH */

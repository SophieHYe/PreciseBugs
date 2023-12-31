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
#ifndef HB_CFF2_INTERP_CS_HH
#define HB_CFF2_INTERP_CS_HH

#include "hb.hh"
#include "hb-cff-interp-cs-common.hh"

namespace CFF {

using namespace OT;

struct BlendArg : Number
{
  inline void init (void)
  {
    Number::init ();
    deltas.init ();
  }

  inline void fini (void)
  {
    Number::fini ();
    deltas.fini_deep ();
  }

  inline void set_int (int v) { reset_blends (); Number::set_int (v); }
  inline void set_fixed (int32_t v) { reset_blends (); Number::set_fixed (v); }
  inline void set_real (double v) { reset_blends (); Number::set_real (v); }

  inline void set_blends (unsigned int numValues_, unsigned int valueIndex_,
			  unsigned int numBlends, const BlendArg *blends_)
  {
    numValues = numValues_;
    valueIndex = valueIndex_;
    deltas.resize (numBlends);
    for (unsigned int i = 0; i < numBlends; i++)
      deltas[i] = blends_[i];
  }

  inline bool blending (void) const { return deltas.len > 0; }
  inline void reset_blends (void)
  {
    numValues = valueIndex = 0;
    deltas.resize (0);
  }

  unsigned int numValues;
  unsigned int valueIndex;
  hb_vector_t<Number> deltas;
};

typedef InterpEnv<BlendArg> BlendInterpEnv;
typedef BiasedSubrs<CFF2Subrs>   CFF2BiasedSubrs;

struct CFF2CSInterpEnv : CSInterpEnv<BlendArg, CFF2Subrs>
{
  template <typename ACC>
  inline void init (const ByteStr &str, ACC &acc, unsigned int fd,
		    const int *coords_=nullptr, unsigned int num_coords_=0)
  {
    SUPER::init (str, *acc.globalSubrs, *acc.privateDicts[fd].localSubrs);

    coords = coords_;
    num_coords = num_coords_;
    varStore = acc.varStore;
    seen_blend = false;
    seen_vsindex_ = false;
    scalars.init ();
    do_blend = (coords != nullptr) && num_coords && (varStore != &Null(CFF2VariationStore));
    set_ivs (acc.privateDicts[fd].ivs);
  }

  inline void fini (void)
  {
    scalars.fini ();
    SUPER::fini ();
  }

  inline OpCode fetch_op (void)
  {
    if (this->substr.avail ())
      return SUPER::fetch_op ();

    /* make up return or endchar op */
    if (this->callStack.is_empty ())
      return OpCode_endchar;
    else
      return OpCode_return;
  }

  inline const BlendArg& eval_arg (unsigned int i)
  {
    BlendArg  &arg = argStack[i];
    blend_arg (arg);
    return arg;
  }

  inline const BlendArg& pop_arg (void)
  {
    BlendArg  &arg = argStack.pop ();
    blend_arg (arg);
    return arg;
  }

  inline void process_blend (void)
  {
    if (!seen_blend)
    {
      region_count = varStore->varStore.get_region_index_count (get_ivs ());
      if (do_blend)
      {
	scalars.resize (region_count);
	varStore->varStore.get_scalars (get_ivs (),
					(int *)coords, num_coords,
					&scalars[0], region_count);
      }
      seen_blend = true;
    }
  }

  inline void process_vsindex (void)
  {
    unsigned int  index = argStack.pop_uint ();
    if (unlikely (seen_vsindex () || seen_blend))
    {
      set_error ();
    }
    else
    {
      set_ivs (index);
    }
    seen_vsindex_ = true;
  }

  inline unsigned int get_region_count (void) const { return region_count; }
  inline void	 set_region_count (unsigned int region_count_) { region_count = region_count_; }
  inline unsigned int get_ivs (void) const { return ivs; }
  inline void	 set_ivs (unsigned int ivs_) { ivs = ivs_; }
  inline bool	 seen_vsindex (void) const { return seen_vsindex_; }

  protected:
  inline void blend_arg (BlendArg &arg)
  {
    if (do_blend && arg.blending ())
    {
      if (likely (scalars.len == arg.deltas.len))
      {
	double v = arg.to_real ();
	for (unsigned int i = 0; i < scalars.len; i++)
	{
	  v += (double)scalars[i] * arg.deltas[i].to_real ();
	}
	arg.set_real (v);
	arg.deltas.resize (0);
      }
    }
  }

  protected:
  const int     *coords;
  unsigned int  num_coords;
  const	 CFF2VariationStore *varStore;
  unsigned int  region_count;
  unsigned int  ivs;
  hb_vector_t<float>  scalars;
  bool	  do_blend;
  bool	  seen_vsindex_;
  bool	  seen_blend;

  typedef CSInterpEnv<BlendArg, CFF2Subrs> SUPER;
};
template <typename OPSET, typename PARAM, typename PATH=PathProcsNull<CFF2CSInterpEnv, PARAM> >
struct CFF2CSOpSet : CSOpSet<BlendArg, OPSET, CFF2CSInterpEnv, PARAM, PATH>
{
  static inline void process_op (OpCode op, CFF2CSInterpEnv &env, PARAM& param)
  {
    switch (op) {
      case OpCode_callsubr:
      case OpCode_callgsubr:
	/* a subroutine number shoudln't be a blended value */
	if (unlikely (env.argStack.peek ().blending ()))
	{
	  env.set_error ();
	  break;
	}
	SUPER::process_op (op, env, param);
	break;

      case OpCode_blendcs:
	OPSET::process_blend (env, param);
	break;

      case OpCode_vsindexcs:
	if (unlikely (env.argStack.peek ().blending ()))
	{
	  env.set_error ();
	  break;
	}
	OPSET::process_vsindex (env, param);
	break;

      default:
	SUPER::process_op (op, env, param);
    }
  }

  static inline void process_blend (CFF2CSInterpEnv &env, PARAM& param)
  {
    unsigned int n, k;

    env.process_blend ();
    k = env.get_region_count ();
    n = env.argStack.pop_uint ();
    if (unlikely (env.argStack.get_count () < ((k+1) * n)))
    {
      env.set_error ();
      return;
    }
    /* copy the blend values into blend array of the default values */
    unsigned int start = env.argStack.get_count () - ((k+1) * n);
    for (unsigned int i = 0; i < n; i++)
      env.argStack[start + i].set_blends (n, i, k, &env.argStack[start + n + (i * k)]);

    /* pop off blend values leaving default values now adorned with blend values */
    env.argStack.pop (k * n);
  }

  static inline void process_vsindex (CFF2CSInterpEnv &env, PARAM& param)
  {
    env.process_vsindex ();
    env.clear_args ();
  }

  private:
  typedef CSOpSet<BlendArg, OPSET, CFF2CSInterpEnv, PARAM, PATH>  SUPER;
};

template <typename OPSET, typename PARAM>
struct CFF2CSInterpreter : CSInterpreter<CFF2CSInterpEnv, OPSET, PARAM> {};

} /* namespace CFF */

#endif /* HB_CFF2_INTERP_CS_HH */

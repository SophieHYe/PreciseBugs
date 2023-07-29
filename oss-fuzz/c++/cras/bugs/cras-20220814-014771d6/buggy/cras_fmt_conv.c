/* Copyright 2012 The Chromium OS Authors. All rights reserved.
 * Use of this source code is governed by a BSD-style license that can be
 * found in the LICENSE file.
 */

/* For now just use speex, can add more resamplers later. */
#include <speex/speex_resampler.h>
#include <sys/param.h>
#include <syslog.h>
#include <endian.h>
#include <limits.h>
#include <math.h>

#include "cras_fmt_conv.h"
#include "cras_fmt_conv_ops.h"
#include "cras_audio_format.h"
#include "cras_util.h"
#include "linear_resampler.h"

/* The quality level is a value between 0 and 10. This is a tradeoff between
 * performance, latency, and quality. */
#define SPEEX_QUALITY_LEVEL 4
/* Max number of converters, src, down/up mix, 2xformat, and linear resample. */
#define MAX_NUM_CONVERTERS 5
/* Channel index for stereo. */
#define STEREO_L 0
#define STEREO_R 1

/* Channel re-mapping table for multi-channel internal speakers.
 * Multi-channel internal speakers are always exposed as a stereo output
 * device. Maps the L/R channel to left/right side speakers and drops content
 * in other channels.
 */
static const int internal_spk_channel_mapping[CRAS_CH_MAX][CRAS_CH_MAX] = {
	/*FL FR RL RR FC LFE SL SR RC FLC FRC */
	{ 1, 0, 0, 0, 0, +0, 0, 0, 0, 0, 0 }, /* FL */
	{ 0, 1, 0, 0, 0, +0, 0, 0, 0, 0, 0 }, /* FR */
	{ 1, 0, 0, 0, 0, +0, 0, 0, 0, 0, 0 }, /* RL */
	{ 0, 1, 0, 0, 0, +0, 0, 0, 0, 0, 0 }, /* RR */
	{ 0, 0, 0, 0, 0, +0, 0, 0, 0, 0, 0 }, /* FC */
	{ 0, 0, 0, 0, 0, +0, 0, 0, 0, 0, 0 }, /* LFE */
	{ 1, 0, 0, 0, 0, +0, 0, 0, 0, 0, 0 }, /* SL */
	{ 0, 1, 0, 0, 0, +0, 0, 0, 0, 0, 0 }, /* SR */
	{ 0, 0, 0, 0, 0, +0, 0, 0, 0, 0, 0 }, /* RC */
	{ 0, 0, 0, 0, 0, +0, 0, 0, 0, 0, 0 }, /* FLC */
	{ 0, 0, 0, 0, 0, +0, 0, 0, 0, 0, 0 }, /* FRC */
};

typedef void (*sample_format_converter_t)(const uint8_t *in, size_t in_samples,
					  uint8_t *out);
typedef size_t (*channel_converter_t)(struct cras_fmt_conv *conv,
				      const uint8_t *in, size_t in_frames,
				      uint8_t *out);

/* Member data for the resampler. */
struct cras_fmt_conv {
	SpeexResamplerState *speex_state;
	channel_converter_t channel_converter;
	float **ch_conv_mtx; /* Coefficient matrix for mixing channels. */
	sample_format_converter_t in_format_converter;
	sample_format_converter_t out_format_converter;
	struct linear_resampler *resampler;
	struct cras_audio_format in_fmt;
	struct cras_audio_format out_fmt;
	uint8_t *tmp_bufs[MAX_NUM_CONVERTERS - 1];
	size_t tmp_buf_frames;
	size_t pre_linear_resample;
	size_t num_converters; /* Incremented once for SRC, channel, format. */
};

static int is_channel_layout_equal(const struct cras_audio_format *a,
				   const struct cras_audio_format *b)
{
	int ch;
	for (ch = 0; ch < CRAS_CH_MAX; ch++)
		if (a->channel_layout[ch] != b->channel_layout[ch])
			return 0;

	return 1;
}

/*
 * Calculates the normalize_factor abs_sum(ci) from given coefficients.
 * Since sum(ci / abs_sum(ci)) <= 1, this could prevent sample overflow while
 * upmixing or downmixing.
 */
static float normalize_factor(float *buf, size_t n)
{
	int i;
	float abs_sum = 0.0;
	for (i = 0; i < n; i++)
		abs_sum += fabs(buf[i]);

	if (abs_sum == 0.0)
		return 0;

	return 1.0 / abs_sum;
}

/*
 * Normalize all channels with the same factor to maintain
 * the energy ratio between original channels. The factor should
 * be greater than zero.
 */
static void normalize(float **mtx, size_t m, size_t n, float factor)
{
	int i, j;

	if (factor <= 0) {
		syslog(LOG_ERR, "expect: normalize factor > 0, get: %f.",
		       factor);
	}

	for (i = 0; i < m; i++)
		for (j = 0; j < n; j++)
			mtx[i][j] *= factor;
}

/*
 * Populates the down mix matrix by rules:
 * 1. Front/side left(right) channel will mix to left(right) of
 *    full scale.
 * 2. Center and LFE will be split equally to left and right.
 *    Rear
 * 3. Rear left/right will split 1/4 of the power to opposite
 *    channel.
 */
static void surround51_to_stereo_downmix_mtx(float **mtx,
					     int8_t layout[CRAS_CH_MAX])
{
	if (layout[CRAS_CH_FC] != -1) {
		mtx[STEREO_L][layout[CRAS_CH_FC]] = 0.707;
		mtx[STEREO_R][layout[CRAS_CH_FC]] = 0.707;
	}
	if (layout[CRAS_CH_FL] != -1 && layout[CRAS_CH_FR] != -1) {
		mtx[STEREO_L][layout[CRAS_CH_FL]] = 1.0;
		mtx[STEREO_R][layout[CRAS_CH_FR]] = 1.0;
	}
	if (layout[CRAS_CH_SL] != -1 && layout[CRAS_CH_SR] != -1) {
		mtx[STEREO_L][layout[CRAS_CH_SL]] = 1.0;
		mtx[STEREO_R][layout[CRAS_CH_SR]] = 1.0;
	}
	if (layout[CRAS_CH_RL] != -1 && layout[CRAS_CH_RR] != -1) {
		/* Split 1/4 power to the other side */
		mtx[STEREO_L][layout[CRAS_CH_RL]] = 0.866;
		mtx[STEREO_R][layout[CRAS_CH_RL]] = 0.5;
		mtx[STEREO_R][layout[CRAS_CH_RR]] = 0.866;
		mtx[STEREO_L][layout[CRAS_CH_RR]] = 0.5;
	}
	if (layout[CRAS_CH_LFE] != -1) {
		mtx[STEREO_L][layout[CRAS_CH_LFE]] = 0.707;
		mtx[STEREO_R][layout[CRAS_CH_LFE]] = 0.707;
	}
	normalize(mtx, 2, 6, normalize_factor(mtx[STEREO_L], 6));
}

/* Populates the down mix matrix by rules:
 * 1. Front left(right) channel will mix to the front left(right) of
 *    full scale.
 * 2. Rear and side left(right) channel will mix to the rear left(right) of
 *    full scale.
 * 3. Center will be split equally to the front left and right.
 * 4. LFE will be split equally to the other channels.
 */
static void surround51_to_quad_downmix_mtx(float **mtx,
					   int8_t layout[CRAS_CH_MAX])
{
	if (layout[CRAS_CH_FL] != -1 && layout[CRAS_CH_FR] != -1) {
		mtx[CRAS_CH_FL][layout[CRAS_CH_FL]] = 1.0;
		mtx[CRAS_CH_FR][layout[CRAS_CH_FR]] = 1.0;
	}
	if (layout[CRAS_CH_RL] != -1 && layout[CRAS_CH_RR] != -1) {
		mtx[CRAS_CH_RL][layout[CRAS_CH_RL]] = 1.0;
		mtx[CRAS_CH_RR][layout[CRAS_CH_RR]] = 1.0;
	}
	if (layout[CRAS_CH_SL] != -1 && layout[CRAS_CH_SR] != -1) {
		mtx[CRAS_CH_RL][layout[CRAS_CH_SL]] = 1.0;
		mtx[CRAS_CH_RR][layout[CRAS_CH_SR]] = 1.0;
	}
	if (layout[CRAS_CH_FC] != -1) {
		/* Split 1/2 power to the front L/R */
		mtx[CRAS_CH_FL][layout[CRAS_CH_FC]] = 0.707;
		mtx[CRAS_CH_FR][layout[CRAS_CH_FC]] = 0.707;
	}
	if (layout[CRAS_CH_LFE] != -1) {
		/* Split 1/4 power to the other channel */
		mtx[CRAS_CH_FL][layout[CRAS_CH_LFE]] = 0.5;
		mtx[CRAS_CH_FR][layout[CRAS_CH_LFE]] = 0.5;
		mtx[CRAS_CH_RL][layout[CRAS_CH_LFE]] = 0.5;
		mtx[CRAS_CH_RR][layout[CRAS_CH_LFE]] = 0.5;
	}

	normalize(mtx, 4, 6, normalize_factor(mtx[CRAS_CH_FL], 6));
}

static int is_supported_format(const struct cras_audio_format *fmt)
{
	if (!fmt)
		return 0;

	switch (fmt->format) {
	case SND_PCM_FORMAT_U8:
	case SND_PCM_FORMAT_S16_LE:
	case SND_PCM_FORMAT_S24_3LE:
	case SND_PCM_FORMAT_S24_LE:
	case SND_PCM_FORMAT_S32_LE:
		return 1;
	default:
		return 0;
	}
}

static size_t mono_to_stereo(struct cras_fmt_conv *conv, const uint8_t *in,
			     size_t in_frames, uint8_t *out)
{
	return s16_mono_to_stereo(in, in_frames, out);
}

static size_t stereo_to_mono(struct cras_fmt_conv *conv, const uint8_t *in,
			     size_t in_frames, uint8_t *out)
{
	return s16_stereo_to_mono(in, in_frames, out);
}

static size_t mono_to_51(struct cras_fmt_conv *conv, const uint8_t *in,
			 size_t in_frames, uint8_t *out)
{
	size_t left, right, center;

	left = conv->out_fmt.channel_layout[CRAS_CH_FL];
	right = conv->out_fmt.channel_layout[CRAS_CH_FR];
	center = conv->out_fmt.channel_layout[CRAS_CH_FC];

	return s16_mono_to_51(left, right, center, in, in_frames, out);
}

static size_t stereo_to_51(struct cras_fmt_conv *conv, const uint8_t *in,
			   size_t in_frames, uint8_t *out)
{
	size_t left, right, center;

	left = conv->out_fmt.channel_layout[CRAS_CH_FL];
	right = conv->out_fmt.channel_layout[CRAS_CH_FR];
	center = conv->out_fmt.channel_layout[CRAS_CH_FC];

	return s16_stereo_to_51(left, right, center, in, in_frames, out);
}

static size_t quad_to_51(struct cras_fmt_conv *conv, const uint8_t *in,
			 size_t in_frames, uint8_t *out)
{
	size_t fl, fr, rl, rr;

	fl = conv->out_fmt.channel_layout[CRAS_CH_FL];
	fr = conv->out_fmt.channel_layout[CRAS_CH_FR];
	rl = conv->out_fmt.channel_layout[CRAS_CH_RL];
	rr = conv->out_fmt.channel_layout[CRAS_CH_RR];

	return s16_quad_to_51(fl, fr, rl, rr, in, in_frames, out);
}

static size_t mono_to_71(struct cras_fmt_conv *conv, const uint8_t *in,
			 size_t in_frames, uint8_t *out)
{
	size_t left, right, center;

	left = conv->out_fmt.channel_layout[CRAS_CH_FL];
	right = conv->out_fmt.channel_layout[CRAS_CH_FR];
	center = conv->out_fmt.channel_layout[CRAS_CH_FC];

	return s16_mono_to_71(left, right, center, in, in_frames, out);
}

static size_t stereo_to_71(struct cras_fmt_conv *conv, const uint8_t *in,
			   size_t in_frames, uint8_t *out)
{
	size_t left, right, center;

	left = conv->out_fmt.channel_layout[CRAS_CH_FL];
	right = conv->out_fmt.channel_layout[CRAS_CH_FR];
	center = conv->out_fmt.channel_layout[CRAS_CH_FC];

	return s16_stereo_to_71(left, right, center, in, in_frames, out);
}

static size_t quad_to_71(struct cras_fmt_conv *conv, const uint8_t *in,
			 size_t in_frames, uint8_t *out)
{
	size_t fl, fr, rl, rr;

	fl = conv->out_fmt.channel_layout[CRAS_CH_FL];
	fr = conv->out_fmt.channel_layout[CRAS_CH_FR];
	rl = conv->out_fmt.channel_layout[CRAS_CH_RL];
	rr = conv->out_fmt.channel_layout[CRAS_CH_RR];

	return s16_quad_to_71(fl, fr, rl, rr, in, in_frames, out);
}

static size_t _51_to_71(struct cras_fmt_conv *conv, const uint8_t *in,
			size_t in_frames, uint8_t *out)
{
	return s16_51_to_71(&conv->in_fmt, &conv->out_fmt, in, in_frames, out);
}

static size_t _51_to_stereo(struct cras_fmt_conv *conv, const uint8_t *in,
			    size_t in_frames, uint8_t *out)
{
	return s16_51_to_stereo(in, in_frames, out);
}

static size_t _51_to_quad(struct cras_fmt_conv *conv, const uint8_t *in,
			  size_t in_frames, uint8_t *out)
{
	return s16_51_to_quad(in, in_frames, out);
}

static size_t stereo_to_quad(struct cras_fmt_conv *conv, const uint8_t *in,
			     size_t in_frames, uint8_t *out)
{
	size_t front_left, front_right;

	front_left = conv->out_fmt.channel_layout[CRAS_CH_FL];
	front_right = conv->out_fmt.channel_layout[CRAS_CH_FR];

	return s16_stereo_to_quad(front_left, front_right, in, in_frames, out);
}

static size_t quad_to_stereo(struct cras_fmt_conv *conv, const uint8_t *in,
			     size_t in_frames, uint8_t *out)
{
	size_t front_left, front_right, rear_left, rear_right;

	front_left = conv->in_fmt.channel_layout[CRAS_CH_FL];
	front_right = conv->in_fmt.channel_layout[CRAS_CH_FR];
	rear_left = conv->in_fmt.channel_layout[CRAS_CH_RL];
	rear_right = conv->in_fmt.channel_layout[CRAS_CH_RR];

	return s16_quad_to_stereo(front_left, front_right, rear_left,
				  rear_right, in, in_frames, out);
}

static size_t default_all_to_all(struct cras_fmt_conv *conv, const uint8_t *in,
				 size_t in_frames, uint8_t *out)
{
	size_t num_in_ch, num_out_ch;

	num_in_ch = conv->in_fmt.num_channels;
	num_out_ch = conv->out_fmt.num_channels;

	return s16_default_all_to_all(&conv->out_fmt, num_in_ch, num_out_ch, in,
				      in_frames, out);
}

// Fill min(in channels, out_channels), leave the rest 0s.
static size_t default_some_to_some(struct cras_fmt_conv *conv,
				   const uint8_t *in,
				   size_t in_frames,
				   uint8_t *out)
{
	size_t num_in_ch, num_out_ch;

	num_in_ch = conv->in_fmt.num_channels;
	num_out_ch = conv->out_fmt.num_channels;

	return s16_some_to_some(&conv->out_fmt, num_in_ch, num_out_ch, in,
				in_frames, out);
}

static size_t convert_channels(struct cras_fmt_conv *conv, const uint8_t *in,
			       size_t in_frames, uint8_t *out)
{
	float **ch_conv_mtx;
	size_t num_in_ch, num_out_ch;

	ch_conv_mtx = conv->ch_conv_mtx;
	num_in_ch = conv->in_fmt.num_channels;
	num_out_ch = conv->out_fmt.num_channels;

	return s16_convert_channels(ch_conv_mtx, num_in_ch, num_out_ch, in,
				    in_frames, out);
}

static float **cras_internal_spk_channel_conv_matrix_create(
	const struct cras_audio_format *in, const struct cras_audio_format *out)
{
	int i, j;
	float **mtx;

	for (i = 0; i < CRAS_CH_MAX; i++) {
		if (in->channel_layout[i] >= (int)in->num_channels ||
		    out->channel_layout[i] >= (int)out->num_channels) {
			syslog(LOG_ERR, "Fail to create conversion matrix "
					"due to invalid channel layout");
			return NULL;
		}
	}

	mtx = cras_channel_conv_matrix_alloc(in->num_channels,
					     out->num_channels);
	if (!mtx)
		return NULL;

	for (i = 0; i < CRAS_CH_MAX; i++) {
		for (j = 0; j < CRAS_CH_MAX; j++) {
			if (internal_spk_channel_mapping[i][j] &&
			    out->channel_layout[i] != -1 &&
			    in->channel_layout[j] != -1)
				mtx[out->channel_layout[i]]
				   [in->channel_layout[j]] = 1;
		}
	}

	return mtx;
}

/*
 * Exported interface
 */

struct cras_fmt_conv *cras_fmt_conv_create(const struct cras_audio_format *in,
					   const struct cras_audio_format *out,
					   size_t max_frames,
					   size_t pre_linear_resample,
					   enum CRAS_NODE_TYPE node_type)
{
	struct cras_fmt_conv *conv;
	int rc;
	unsigned i;

	conv = calloc(1, sizeof(*conv));
	if (conv == NULL)
		return NULL;
	conv->in_fmt = *in;
	conv->out_fmt = *out;
	conv->tmp_buf_frames = max_frames;
	conv->pre_linear_resample = pre_linear_resample;

	if (!is_supported_format(in)) {
		syslog(LOG_ERR, "Invalid input format %d", in->format);
		cras_fmt_conv_destroy(&conv);
		return NULL;
	}

	if (!is_supported_format(out)) {
		syslog(LOG_ERR, "Invalid output format %d", out->format);
		cras_fmt_conv_destroy(&conv);
		return NULL;
	}

	/* Set up sample format conversion. */
	/* TODO(dgreid) - modify channel and sample rate conversion so
	 * converting to s16 isnt necessary. */
	if (in->format != SND_PCM_FORMAT_S16_LE) {
		conv->num_converters++;
		syslog(LOG_DEBUG, "Convert from format %d to %d.", in->format,
		       out->format);
		switch (in->format) {
		case SND_PCM_FORMAT_U8:
			conv->in_format_converter = convert_u8_to_s16le;
			break;
		case SND_PCM_FORMAT_S24_LE:
			conv->in_format_converter = convert_s24le_to_s16le;
			break;
		case SND_PCM_FORMAT_S32_LE:
			conv->in_format_converter = convert_s32le_to_s16le;
			break;
		case SND_PCM_FORMAT_S24_3LE:
			conv->in_format_converter = convert_s243le_to_s16le;
			break;
		default:
			syslog(LOG_ERR, "Should never reachable");
			break;
		}
	}
	if (out->format != SND_PCM_FORMAT_S16_LE) {
		conv->num_converters++;
		syslog(LOG_DEBUG, "Convert from format %d to %d.", in->format,
		       out->format);
		switch (out->format) {
		case SND_PCM_FORMAT_U8:
			conv->out_format_converter = convert_s16le_to_u8;
			break;
		case SND_PCM_FORMAT_S24_LE:
			conv->out_format_converter = convert_s16le_to_s24le;
			break;
		case SND_PCM_FORMAT_S32_LE:
			conv->out_format_converter = convert_s16le_to_s32le;
			break;
		case SND_PCM_FORMAT_S24_3LE:
			conv->out_format_converter = convert_s16le_to_s243le;
			break;
		default:
			syslog(LOG_ERR, "Should never reachable");
			break;
		}
	}

	/* Set up channel number conversion. */
	if (node_type == CRAS_NODE_TYPE_INTERNAL_SPEAKER &&
	    out->num_channels > 2 && in->num_channels > 1) {
		conv->num_converters++;
		conv->ch_conv_mtx =
			cras_internal_spk_channel_conv_matrix_create(in, out);
		if (conv->ch_conv_mtx == NULL) {
			syslog(LOG_ERR,
			       "Failed to create channel conversion matrix for internal speaker. "
			       "Fallback to default_all_to_all.");
			conv->channel_converter = default_all_to_all;
		} else {
			conv->channel_converter = convert_channels;
		}
	} else if (in->num_channels != out->num_channels) {
		conv->num_converters++;
		/* Populate the conversion matrix base on in/out channel count
		 * and layout. */
		if (in->num_channels == 1 && out->num_channels == 2) {
			conv->channel_converter = mono_to_stereo;
		} else if (in->num_channels == 1 && out->num_channels == 6) {
			conv->channel_converter = mono_to_51;
		} else if (in->num_channels == 2 && out->num_channels == 1) {
			conv->channel_converter = stereo_to_mono;
		} else if (in->num_channels == 2 && out->num_channels == 4) {
			conv->channel_converter = stereo_to_quad;
		} else if (in->num_channels == 4 && out->num_channels == 2) {
			conv->channel_converter = quad_to_stereo;
		} else if (in->num_channels == 2 && out->num_channels == 6) {
			conv->channel_converter = stereo_to_51;
		} else if (in->num_channels == 4 && out->num_channels == 6) {
			conv->channel_converter = quad_to_51;
		} else if (in->num_channels == 1 && out->num_channels == 8) {
			conv->channel_converter = mono_to_71;
		} else if (in->num_channels == 2 && out->num_channels == 8) {
			conv->channel_converter = stereo_to_71;
		} else if (in->num_channels == 4 && out->num_channels == 8) {
			conv->channel_converter = quad_to_71;
		} else if (in->num_channels == 6 && out->num_channels == 8) {
			conv->channel_converter = _51_to_71;
		} else if (in->num_channels == 6 &&
			   (out->num_channels == 2 || out->num_channels == 4)) {
			int in_channel_layout_set = 0;

			/* Checks if channel_layout is set in the incoming format */
			for (i = 0; i < CRAS_CH_MAX; i++)
				if (in->channel_layout[i] != -1)
					in_channel_layout_set = 1;

			/* Use the conversion matrix based converter when a
			 * channel layout is set, or default to use existing
			 * converter to downmix to stereo */
			if (in_channel_layout_set) {
				conv->ch_conv_mtx =
					cras_channel_conv_matrix_alloc(
						in->num_channels,
						out->num_channels);
				if (conv->ch_conv_mtx == NULL) {
					cras_fmt_conv_destroy(&conv);
					return NULL;
				}
				conv->channel_converter = convert_channels;
				if (out->num_channels == 4) {
					surround51_to_quad_downmix_mtx(
						conv->ch_conv_mtx,
						conv->in_fmt.channel_layout);
				} else {
					surround51_to_stereo_downmix_mtx(
						conv->ch_conv_mtx,
						conv->in_fmt.channel_layout);
				}
			} else {
				if (out->num_channels == 4)
					conv->channel_converter = _51_to_quad;
				else
					conv->channel_converter = _51_to_stereo;
			}
		} else if (in->num_channels <= 8 && out->num_channels <= 8) {
			// For average channel counts mix from all to all.
			syslog(LOG_WARNING,
			       "Using all_to_all map for %zu to %zu",
			       in->num_channels, out->num_channels);
			conv->channel_converter = default_all_to_all;
		} else {
			syslog(LOG_WARNING,
			       "Using some_to_some channel map for %zu to %zu",
			       in->num_channels, out->num_channels);
			conv->channel_converter = default_some_to_some;
		}
	} else if (in->num_channels > 2 && !is_channel_layout_equal(in, out)) {
		conv->num_converters++;
		conv->ch_conv_mtx = cras_channel_conv_matrix_create(in, out);
		if (conv->ch_conv_mtx == NULL) {
			syslog(LOG_ERR,
			       "Failed to create channel conversion matrix."
			       "Fallback to default_all_to_all.");
			conv->channel_converter = default_all_to_all;
		} else {
			conv->channel_converter = convert_channels;
		}
	}
	/* Set up sample rate conversion. */
	if (in->frame_rate != out->frame_rate) {
		conv->num_converters++;
		syslog(LOG_DEBUG, "Convert from %zu to %zu Hz.", in->frame_rate,
		       out->frame_rate);
		conv->speex_state =
			speex_resampler_init(out->num_channels, in->frame_rate,
					     out->frame_rate,
					     SPEEX_QUALITY_LEVEL, &rc);
		if (conv->speex_state == NULL) {
			syslog(LOG_ERR, "Fail to create speex:%zu %zu %zu %d",
			       out->num_channels, in->frame_rate,
			       out->frame_rate, rc);
			cras_fmt_conv_destroy(&conv);
			return NULL;
		}
	}

	/*
	 * Set up linear resampler.
	 *
	 * Note: intended to give both src_rate and dst_rate the same value
	 * (i.e. out->frame_rate).  They will be updated in runtime in
	 * update_estimated_rate() when the audio thread wants to adjust the
	 * rate for inaccurate device consumption rate.
	 */
	conv->num_converters++;
	conv->resampler =
		linear_resampler_create(out->num_channels,
					cras_get_format_bytes(out),
					out->frame_rate, out->frame_rate);
	if (conv->resampler == NULL) {
		syslog(LOG_ERR, "Fail to create linear resampler");
		cras_fmt_conv_destroy(&conv);
		return NULL;
	}

	/* Need num_converters-1 temp buffers, the final converter renders
	 * directly into the output. */
	for (i = 0; i < conv->num_converters - 1; i++) {
		conv->tmp_bufs[i] = malloc(
			max_frames * 4 * /* width in bytes largest format. */
			MAX(in->num_channels, out->num_channels));
		if (conv->tmp_bufs[i] == NULL) {
			cras_fmt_conv_destroy(&conv);
			return NULL;
		}
	}

	assert(conv->num_converters <= MAX_NUM_CONVERTERS);

	return conv;
}

void cras_fmt_conv_destroy(struct cras_fmt_conv **convp)
{
	unsigned i;
	struct cras_fmt_conv *conv = *convp;

	if (conv->ch_conv_mtx)
		cras_channel_conv_matrix_destroy(conv->ch_conv_mtx,
						 conv->out_fmt.num_channels);
	if (conv->speex_state)
		speex_resampler_destroy(conv->speex_state);
	if (conv->resampler)
		linear_resampler_destroy(conv->resampler);
	for (i = 0; i < MAX_NUM_CONVERTERS - 1; i++)
		free(conv->tmp_bufs[i]);
	free(conv);
	*convp = NULL;
}

struct cras_fmt_conv *cras_channel_remix_conv_create(unsigned int num_channels,
						     const float *coefficient)
{
	struct cras_fmt_conv *conv;
	unsigned out_ch, in_ch;

	conv = calloc(1, sizeof(*conv));
	if (conv == NULL)
		return NULL;
	conv->in_fmt.num_channels = num_channels;
	conv->out_fmt.num_channels = num_channels;

	conv->ch_conv_mtx =
		cras_channel_conv_matrix_alloc(num_channels, num_channels);
	/* Convert the coeffiencnt array to conversion matrix. */
	for (out_ch = 0; out_ch < num_channels; out_ch++)
		for (in_ch = 0; in_ch < num_channels; in_ch++)
			conv->ch_conv_mtx[out_ch][in_ch] =
				coefficient[in_ch + out_ch * num_channels];

	conv->num_converters = 1;
	conv->tmp_bufs[0] = malloc(4 * /* width in bytes largest format. */
				   num_channels);
	return conv;
}

void cras_channel_remix_convert(struct cras_fmt_conv *conv,
				const struct cras_audio_format *fmt,
				uint8_t *in_buf, size_t nframes)
{
	unsigned ch, fr;
	int16_t *tmp = (int16_t *)conv->tmp_bufs[0];
	int16_t *buf = (int16_t *)in_buf;

	/*
	 * Skip remix for non S16_LE format.
	 * TODO(tzungbi): support 24 bits remix convert.
	 */
	if (fmt->format != SND_PCM_FORMAT_S16_LE)
		return;

	/* Do remix only when input buffer has the same number of channels. */
	if (fmt->num_channels != conv->in_fmt.num_channels)
		return;

	for (fr = 0; fr < nframes; fr++) {
		for (ch = 0; ch < conv->in_fmt.num_channels; ch++)
			tmp[ch] = s16_multiply_buf_with_coef(
				conv->ch_conv_mtx[ch], buf,
				conv->in_fmt.num_channels);
		for (ch = 0; ch < conv->in_fmt.num_channels; ch++)
			buf[ch] = tmp[ch];
		buf += conv->in_fmt.num_channels;
	}
}

const struct cras_audio_format *
cras_fmt_conv_in_format(const struct cras_fmt_conv *conv)
{
	return &conv->in_fmt;
}

const struct cras_audio_format *
cras_fmt_conv_out_format(const struct cras_fmt_conv *conv)
{
	return &conv->out_fmt;
}

size_t cras_fmt_conv_in_frames_to_out(struct cras_fmt_conv *conv,
				      size_t in_frames)
{
	if (!conv)
		return in_frames;

	if (conv->pre_linear_resample)
		in_frames = linear_resampler_in_frames_to_out(conv->resampler,
							      in_frames);
	in_frames = cras_frames_at_rate(conv->in_fmt.frame_rate, in_frames,
					conv->out_fmt.frame_rate);
	if (!conv->pre_linear_resample)
		in_frames = linear_resampler_in_frames_to_out(conv->resampler,
							      in_frames);
	return in_frames;
}

size_t cras_fmt_conv_out_frames_to_in(struct cras_fmt_conv *conv,
				      size_t out_frames)
{
	if (!conv)
		return out_frames;
	if (!conv->pre_linear_resample)
		out_frames = linear_resampler_out_frames_to_in(conv->resampler,
							       out_frames);
	out_frames = cras_frames_at_rate(conv->out_fmt.frame_rate, out_frames,
					 conv->in_fmt.frame_rate);
	if (conv->pre_linear_resample)
		out_frames = linear_resampler_out_frames_to_in(conv->resampler,
							       out_frames);
	return out_frames;
}

void cras_fmt_conv_set_linear_resample_rates(struct cras_fmt_conv *conv,
					     float from, float to)
{
	linear_resampler_set_rates(conv->resampler, from, to);
}

size_t cras_fmt_conv_convert_frames(struct cras_fmt_conv *conv,
				    const uint8_t *in_buf, uint8_t *out_buf,
				    unsigned int *in_frames, size_t out_frames)
{
	uint32_t fr_in, fr_out;
	uint8_t *buffers[MAX_NUM_CONVERTERS + 1]; /* converters + out buffer. */
	size_t buf_idx = 0;
	static int logged_frames_dont_fit;
	unsigned int used_converters = conv->num_converters;
	unsigned int post_linear_resample = 0;
	unsigned int pre_linear_resample = 0;
	unsigned int linear_resample_fr = 0;

	assert(conv);
	assert(*in_frames <= conv->tmp_buf_frames);

	if (linear_resampler_needed(conv->resampler)) {
		post_linear_resample = !conv->pre_linear_resample;
		pre_linear_resample = conv->pre_linear_resample;
	}

	/* If no SRC, then in_frames should = out_frames. */
	if (conv->speex_state == NULL) {
		fr_in = MIN(*in_frames, out_frames);
		if (out_frames < *in_frames && !logged_frames_dont_fit) {
			syslog(LOG_INFO, "fmt_conv: %u to %zu no SRC.",
			       *in_frames, out_frames);
			logged_frames_dont_fit = 1;
		}
	} else {
		fr_in = *in_frames;
	}
	fr_out = fr_in;

	/* Set up a chain of buffers.  The output buffer of the first conversion
	 * is used as input to the second and so forth, ending in the output
	 * buffer. */
	if (!linear_resampler_needed(conv->resampler))
		used_converters--;

	buffers[4] = (uint8_t *)conv->tmp_bufs[3];
	buffers[3] = (uint8_t *)conv->tmp_bufs[2];
	buffers[2] = (uint8_t *)conv->tmp_bufs[1];
	buffers[1] = (uint8_t *)conv->tmp_bufs[0];
	buffers[0] = (uint8_t *)in_buf;
	buffers[used_converters] = out_buf;

	if (pre_linear_resample) {
		linear_resample_fr = fr_in;
		unsigned resample_limit = out_frames;

		/* If there is a 2nd fmt conversion we should convert the
		 * resample limit and round it to the lower bound in order
		 * not to convert too many frames in the pre linear resampler.
		 */
		if (conv->speex_state != NULL) {
			resample_limit = resample_limit *
					 conv->in_fmt.frame_rate /
					 conv->out_fmt.frame_rate;
			/*
			 * However if the limit frames count is less than
			 * |out_rate / in_rate|, the final limit value could be
			 * rounded to zero so it confuses linear resampler to
			 * do nothing. Make sure it's non-zero in that case.
			 */
			if (resample_limit == 0)
				resample_limit = 1;
		}

		resample_limit = MIN(resample_limit, conv->tmp_buf_frames);
		fr_in = linear_resampler_resample(
			conv->resampler, buffers[buf_idx], &linear_resample_fr,
			buffers[buf_idx + 1], resample_limit);
		buf_idx++;
	}

	/* If the input format isn't S16_LE convert to it. */
	if (conv->in_fmt.format != SND_PCM_FORMAT_S16_LE) {
		conv->in_format_converter(buffers[buf_idx],
					  fr_in * conv->in_fmt.num_channels,
					  (uint8_t *)buffers[buf_idx + 1]);
		buf_idx++;
	}

	/* Then channel conversion. */
	if (conv->channel_converter != NULL) {
		conv->channel_converter(conv, buffers[buf_idx], fr_in,
					buffers[buf_idx + 1]);
		buf_idx++;
	}

	/* Then SRC. */
	if (conv->speex_state != NULL) {
		unsigned int out_limit = out_frames;

		if (post_linear_resample)
			out_limit = linear_resampler_out_frames_to_in(
				conv->resampler, out_limit);
		fr_out = cras_frames_at_rate(conv->in_fmt.frame_rate, fr_in,
					     conv->out_fmt.frame_rate);
		if (fr_out > out_frames + 1 && !logged_frames_dont_fit) {
			syslog(LOG_INFO,
			       "fmt_conv: put %u frames in %zu sized buffer",
			       fr_out, out_frames);
			logged_frames_dont_fit = 1;
		}
		/* limit frames to the output size. */
		fr_out = MIN(fr_out, out_limit);
		speex_resampler_process_interleaved_int(
			conv->speex_state, (int16_t *)buffers[buf_idx], &fr_in,
			(int16_t *)buffers[buf_idx + 1], &fr_out);
		buf_idx++;
	}

	if (post_linear_resample) {
		linear_resample_fr = fr_out;
		unsigned resample_limit = MIN(conv->tmp_buf_frames, out_frames);
		fr_out = linear_resampler_resample(
			conv->resampler, buffers[buf_idx], &linear_resample_fr,
			buffers[buf_idx + 1], resample_limit);
		buf_idx++;
	}

	/* If the output format isn't S16_LE convert to it. */
	if (conv->out_fmt.format != SND_PCM_FORMAT_S16_LE) {
		conv->out_format_converter(buffers[buf_idx],
					   fr_out * conv->out_fmt.num_channels,
					   (uint8_t *)buffers[buf_idx + 1]);
		buf_idx++;
	}

	if (pre_linear_resample) {
		*in_frames = linear_resample_fr;

		/* When buffer sizes are small, there's a corner case that
		 * speex library resamples 0 frame to N-1 frames, where N
		 * is the integer ratio of output and input rate. For example,
		 * 16KHz to 48KHz. In this case fmt_conv should claim zero
		 * frames processed, instead of using the linear resampler
		 * processed frames count. Otherwise there will be a frame
		 * leak and, if accumulated, causes delay in multiple devices
		 * use case.
		 */
		if (conv->speex_state && (fr_in == 0))
			*in_frames = 0;
	} else {
		*in_frames = fr_in;
	}
	return fr_out;
}

int cras_fmt_conversion_needed(const struct cras_fmt_conv *conv)
{
	return linear_resampler_needed(conv->resampler) ||
	       (conv->num_converters > 1);
}

/* If the server cannot provide the requested format, configures an audio format
 * converter that handles transforming the input format to the format used by
 * the server. */
int config_format_converter(struct cras_fmt_conv **conv,
			    enum CRAS_STREAM_DIRECTION dir,
			    const struct cras_audio_format *from,
			    const struct cras_audio_format *to,
			    enum CRAS_NODE_TYPE node_type, unsigned int frames)
{
	struct cras_audio_format target;

	/* For input, preserve the channel count and layout of
	 * from format */
	if (dir == CRAS_STREAM_INPUT) {
		target = *from;
		target.format = to->format;
		target.frame_rate = to->frame_rate;
	} else {
		target = *to;
	}

	syslog(LOG_DEBUG,
	       "format convert: from:%d %zu %zu target: %d %zu %zu "
	       "frames = %u",
	       from->format, from->frame_rate, from->num_channels,
	       target.format, target.frame_rate, target.num_channels, frames);
	*conv = cras_fmt_conv_create(from, &target, frames,
				     (dir == CRAS_STREAM_INPUT), node_type);
	if (!*conv) {
		syslog(LOG_ERR, "Failed to create format converter");
		return -ENOMEM;
	}

	return 0;
}

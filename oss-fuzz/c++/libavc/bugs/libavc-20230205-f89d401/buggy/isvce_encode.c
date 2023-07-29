/******************************************************************************
 *
 * Copyright (C) 2022 The Android Open Source Project
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at:
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 *****************************************************************************
 * Originally developed and contributed by Ittiam Systems Pvt. Ltd, Bangalore
 */

/**
******************************************************************************
* @file
*  isvce_encode.c
*
* @brief
*  This file contains functions for encoding the input yuv frame in synchronous
*  api mode
*
* @author
*  ittiam
*
* List of Functions
*  - isvce_join_threads()
*  - isvce_wait_for_thread()
*  - isvce_encode()
*
******************************************************************************
*/
#include <stdio.h>
#include <stddef.h>
#include <stdlib.h>
#include <string.h>
#include <limits.h>
#include <assert.h>
#include <math.h>
#include <stdbool.h>

#include "ih264_typedefs.h"
/* Dependencies of ih264_buf_mgr.h */
/* Dependencies of ih264_list.h */
#include "ih264_error.h"
/* Dependencies of ih264_common_tables.h */
#include "ih264_defs.h"
#include "ih264_structs.h"
#include "ih264_buf_mgr.h"
#include "ih264_common_tables.h"
#include "ih264_list.h"
#include "ih264_platform_macros.h"
#include "ih264_trans_data.h"
#include "ih264_size_defs.h"
/* Dependencies of ih264e_cabac_structs.h */
#include "ih264_cabac_tables.h"
/* Dependencies of ime_structs.h */
#include "ime_defs.h"
#include "ime_distortion_metrics.h"
/* Dependencies of ih264e_structs.h */
#include "iv2.h"
#include "ive2.h"
#include "ih264_defs.h"
#include "ih264_deblk_edge_filters.h"
#include "ih264_inter_pred_filters.h"
#include "ih264_structs.h"
#include "ih264_trans_quant_itrans_iquant.h"
/* Dependencies of ih264e_bitstream.h */
#include "ih264e_error.h"
#include "ih264e_bitstream.h"
#include "ih264e_cabac_structs.h"
#include "irc_cntrl_param.h"
#include "irc_frame_info_collector.h"
#include "ime_statistics.h"
#include "ime_structs.h"
/* Dependencies of 'ih264e_utils.h' */
#include "ih264e_defs.h"
#include "ih264e_structs.h"
#include "ih264e_utils.h"
#include "ime.h"
#include "isvce.h"
#include "isvce_cabac.h"
#include "isvce_deblk.h"
#include "isvce_defs.h"
#include "isvce_downscaler.h"
#include "isvce_encode_header.h"
#include "isvce_fmt_conv.h"
#include "isvce_ibl_eval.h"
#include "isvce_ilp_mv.h"
#include "isvce_intra_modes_eval.h"
#include "isvce_me.h"
#include "isvce_process.h"
#include "isvce_rate_control.h"
#include "isvce_residual_pred.h"
#include "isvce_sub_pic_rc.h"
#include "isvce_utils.h"

#define SEI_BASED_FORCE_IDR 1

/*****************************************************************************/
/* Function Definitions                                                      */
/*****************************************************************************/

/**
******************************************************************************
*
* @brief This function puts the current thread to sleep for a duration
*  of sleep_us
*
* @par Description
*  ithread_yield() method causes the calling thread to yield execution to
*another thread that is ready to run on the current processor. The operating
*system selects the thread to yield to. ithread_usleep blocks the current thread
*for the specified number of milliseconds. In other words, yield just says, end
*my timeslice prematurely, look around for other threads to run. If there is
*nothing better than me, continue. Sleep says I don't want to run for x
*  milliseconds. Even if no other thread wants to run, don't make me run.
*
* @param[in] sleep_us
*  thread sleep duration
*
* @returns error_status
*
******************************************************************************
*/
IH264E_ERROR_T isvce_wait_for_thread(UWORD32 sleep_us)
{
    /* yield thread */
    ithread_yield();

    /* put thread to sleep */
    ithread_sleep(sleep_us);

    return IH264E_SUCCESS;
}

/**
******************************************************************************
*
* @brief
*  Encodes in synchronous api mode
*
* @par Description
*  This routine processes input yuv, encodes it and outputs bitstream and recon
*
* @param[in] ps_codec_obj
*  Pointer to codec object at API level
*
* @param[in] pv_api_ip
*  Pointer to input argument structure
*
* @param[out] pv_api_op
*  Pointer to output argument structure
*
* @returns  Status
*
******************************************************************************
*/
WORD32 isvce_encode(iv_obj_t *ps_codec_obj, void *pv_api_ip, void *pv_api_op)
{
    /* error status */
    IH264E_ERROR_T error_status = IH264E_SUCCESS;

    /* codec ctxt */
    isvce_codec_t *ps_codec = (isvce_codec_t *) ps_codec_obj->pv_codec_handle;

    /* input frame to encode */
    isvce_video_encode_ip_t *ps_video_encode_ip = pv_api_ip;

    /* output buffer to write stream */
    isvce_video_encode_op_t *ps_video_encode_op = pv_api_op;

    /* i/o structures */
    isvce_inp_buf_t s_inp_buf;
    isvce_out_buf_t s_out_buf;

    WORD32 ctxt_sel = 0, i4_rc_pre_enc_skip;
    WORD32 i, j;

    ASSERT(MAX_CTXT_SETS == 1);

    /********************************************************************/
    /*                            BEGIN INIT                            */
    /********************************************************************/
    /* reset output structure */
    ps_video_encode_op->s_ive_op.u4_error_code = IV_SUCCESS;
    ps_video_encode_op->s_ive_op.output_present = 0;
    ps_video_encode_op->s_ive_op.dump_recon = 0;
    ps_video_encode_op->s_ive_op.u4_encoded_frame_type = IV_NA_FRAME;

    /* Check for output memory allocation size */
    {
        UWORD32 u4_min_bufsize =
            MIN_STREAM_SIZE * ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers;
        UWORD32 u4_bufsize_per_layer = ps_video_encode_ip->s_ive_ip.s_out_buf.u4_bufsize /
                                       ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers;

        if(ps_video_encode_ip->s_ive_ip.s_out_buf.u4_bufsize < u4_min_bufsize)
        {
            error_status = IH264E_INSUFFICIENT_OUTPUT_BUFFER;

            SET_ERROR_ON_RETURN(error_status, IVE_UNSUPPORTEDPARAM,
                                ps_video_encode_op->s_ive_op.u4_error_code, IV_FAIL);
        }

        for(i = 0; i < ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers; i++)
        {
            s_out_buf.as_bits_buf[i] = ps_video_encode_ip->s_ive_ip.s_out_buf;

            s_out_buf.as_bits_buf[i].u4_bufsize = u4_bufsize_per_layer;
            s_out_buf.as_bits_buf[i].pv_buf =
                ((UWORD8 *) ps_video_encode_ip->s_ive_ip.s_out_buf.pv_buf) +
                u4_bufsize_per_layer * i;
        }
    }

    s_out_buf.u4_is_last = 0;
    s_out_buf.u4_timestamp_low = ps_video_encode_ip->s_ive_ip.u4_timestamp_low;
    s_out_buf.u4_timestamp_high = ps_video_encode_ip->s_ive_ip.u4_timestamp_high;

    /* api call cnt */
    ps_codec->i4_encode_api_call_cnt += 1;

    /* codec context selector */
    ctxt_sel = ps_codec->i4_encode_api_call_cnt % MAX_CTXT_SETS;

    /* reset status flags */
    ps_codec->ai4_pic_cnt[ctxt_sel] = -1;
    ps_codec->s_rate_control.post_encode_skip[ctxt_sel] = 0;
    ps_codec->s_rate_control.pre_encode_skip[ctxt_sel] = 0;

    /* pass output buffer to codec */
    ps_codec->as_out_buf[ctxt_sel] = s_out_buf;

    /* initialize codec ctxt with default params for the first encode api call */
    if(ps_codec->i4_encode_api_call_cnt == 0)
    {
        isvce_codec_init(ps_codec);
    }

    /* parse configuration params */
    for(i = 0; i < MAX_ACTIVE_CONFIG_PARAMS; i++)
    {
        isvce_cfg_params_t *ps_cfg = &ps_codec->as_cfg[i];

        if(1 == ps_cfg->u4_is_valid)
        {
            if(((ps_cfg->u4_timestamp_high == ps_video_encode_ip->s_ive_ip.u4_timestamp_high) &&
                (ps_cfg->u4_timestamp_low == ps_video_encode_ip->s_ive_ip.u4_timestamp_low)) ||
               ((WORD32) ps_cfg->u4_timestamp_high == -1) ||
               ((WORD32) ps_cfg->u4_timestamp_low == -1))
            {
                error_status = isvce_codec_update_config(ps_codec, ps_cfg);
                SET_ERROR_ON_RETURN(error_status, IVE_UNSUPPORTEDPARAM,
                                    ps_video_encode_op->s_ive_op.u4_error_code, IV_FAIL);

                ps_cfg->u4_is_valid = 0;
            }
        }
    }
    /* Force IDR based on SEI params */
#if SEI_BASED_FORCE_IDR
    {
        sei_mdcv_params_t *ps_sei_mdcv_params = &ps_codec->s_sei.s_sei_mdcv_params;
        sei_mdcv_params_t *ps_cfg_sei_mdcv_params = &ps_codec->s_cfg.s_sei.s_sei_mdcv_params;
        sei_cll_params_t *ps_sei_cll_params = &ps_codec->s_sei.s_sei_cll_params;
        sei_cll_params_t *ps_cfg_sei_cll_params = &ps_codec->s_cfg.s_sei.s_sei_cll_params;
        sei_ave_params_t *ps_sei_ave_params = &ps_codec->s_sei.s_sei_ave_params;
        sei_ave_params_t *ps_cfg_sei_ave_params = &ps_codec->s_cfg.s_sei.s_sei_ave_params;

        if((ps_sei_mdcv_params->au2_display_primaries_x[0] !=
            ps_cfg_sei_mdcv_params->au2_display_primaries_x[0]) ||
           (ps_sei_mdcv_params->au2_display_primaries_x[1] !=
            ps_cfg_sei_mdcv_params->au2_display_primaries_x[1]) ||
           (ps_sei_mdcv_params->au2_display_primaries_x[2] !=
            ps_cfg_sei_mdcv_params->au2_display_primaries_x[2]) ||
           (ps_sei_mdcv_params->au2_display_primaries_y[0] !=
            ps_cfg_sei_mdcv_params->au2_display_primaries_y[0]) ||
           (ps_sei_mdcv_params->au2_display_primaries_y[1] !=
            ps_cfg_sei_mdcv_params->au2_display_primaries_y[1]) ||
           (ps_sei_mdcv_params->au2_display_primaries_y[2] !=
            ps_cfg_sei_mdcv_params->au2_display_primaries_y[2]) ||
           (ps_sei_mdcv_params->u2_white_point_x != ps_cfg_sei_mdcv_params->u2_white_point_x) ||
           (ps_sei_mdcv_params->u2_white_point_y != ps_cfg_sei_mdcv_params->u2_white_point_y) ||
           (ps_sei_mdcv_params->u4_max_display_mastering_luminance !=
            ps_cfg_sei_mdcv_params->u4_max_display_mastering_luminance) ||
           (ps_sei_mdcv_params->u4_min_display_mastering_luminance !=
            ps_cfg_sei_mdcv_params->u4_min_display_mastering_luminance))
        {
            ps_codec->s_sei.s_sei_mdcv_params = ps_codec->s_cfg.s_sei.s_sei_mdcv_params;
            ps_codec->s_sei.u1_sei_mdcv_params_present_flag = 1;
        }
        else
        {
            ps_codec->s_sei.u1_sei_mdcv_params_present_flag = 0;
        }

        if((ps_sei_cll_params->u2_max_content_light_level !=
            ps_cfg_sei_cll_params->u2_max_content_light_level) ||
           (ps_sei_cll_params->u2_max_pic_average_light_level !=
            ps_cfg_sei_cll_params->u2_max_pic_average_light_level))
        {
            ps_codec->s_sei.s_sei_cll_params = ps_codec->s_cfg.s_sei.s_sei_cll_params;
            ps_codec->s_sei.u1_sei_cll_params_present_flag = 1;
        }
        else
        {
            ps_codec->s_sei.u1_sei_cll_params_present_flag = 0;
        }

        if((ps_sei_ave_params->u4_ambient_illuminance !=
            ps_cfg_sei_ave_params->u4_ambient_illuminance) ||
           (ps_sei_ave_params->u2_ambient_light_x != ps_cfg_sei_ave_params->u2_ambient_light_x) ||
           (ps_sei_ave_params->u2_ambient_light_y != ps_cfg_sei_ave_params->u2_ambient_light_y))
        {
            ps_codec->s_sei.s_sei_ave_params = ps_codec->s_cfg.s_sei.s_sei_ave_params;
            ps_codec->s_sei.u1_sei_ave_params_present_flag = 1;
        }
        else
        {
            ps_codec->s_sei.u1_sei_ave_params_present_flag = 0;
        }

        if((1 == ps_codec->s_sei.u1_sei_mdcv_params_present_flag) ||
           (1 == ps_codec->s_sei.u1_sei_cll_params_present_flag) ||
           (1 == ps_codec->s_sei.u1_sei_ave_params_present_flag))
        {
            ps_codec->force_curr_frame_type = IV_IDR_FRAME;
        }
    }
#endif

    /* In case of alt ref and B pics we will have non reference frame in stream */
    if(ps_codec->s_cfg.u4_enable_alt_ref || ps_codec->s_cfg.u4_num_bframes)
    {
        ps_codec->i4_non_ref_frames_in_stream = 1;
    }

    if(ps_codec->i4_encode_api_call_cnt == 0)
    {
        /********************************************************************/
        /*   number of mv/ref bank buffers used by the codec,               */
        /*      1 to handle curr frame                                      */
        /*      1 to store information of ref frame                         */
        /*      1 more additional because of the codec employs 2 ctxt sets  */
        /*        to assist asynchronous API                                */
        /********************************************************************/

        /* initialize mv bank buffer manager */
        error_status = isvce_svc_au_data_mgr_add_bufs(ps_codec);

        SET_ERROR_ON_RETURN(error_status, IVE_FATALERROR,
                            ps_video_encode_op->s_ive_op.u4_error_code, IV_FAIL);

        /* initialize ref bank buffer manager */
        error_status = isvce_svc_au_buf_mgr_add_bufs(ps_codec);

        SET_ERROR_ON_RETURN(error_status, IVE_FATALERROR,
                            ps_video_encode_op->s_ive_op.u4_error_code, IV_FAIL);

        /* for the first frame, generate header when not requested explicitly */
        if(ps_codec->i4_header_mode == 0 && ps_codec->u4_header_generated == 0)
        {
            ps_codec->i4_gen_header = 1;
        }
    }

    /* generate header and return when encoder is operated in header mode */
    if(ps_codec->i4_header_mode == 1)
    {
        /* whenever the header is generated, this implies a start of sequence
         * and a sequence needs to be started with IDR
         */
        ps_codec->force_curr_frame_type = IV_IDR_FRAME;

        s_inp_buf.s_svc_params = ps_codec->s_cfg.s_svc_params;
        s_inp_buf.s_inp_props.s_raw_buf = ps_video_encode_ip->s_ive_ip.s_inp_buf;
        s_inp_buf.s_inp_props.s_raw_buf.au4_wd[Y] = ps_codec->s_cfg.u4_wd;
        s_inp_buf.s_inp_props.s_raw_buf.au4_ht[Y] = ps_codec->s_cfg.u4_ht;

        isvce_init_svc_dimension(&s_inp_buf);

        /* generate header */
        error_status = isvce_generate_sps_pps(ps_codec, &s_inp_buf);

        /* send the input to app */
        ps_video_encode_op->s_ive_op.s_inp_buf = ps_video_encode_ip->s_ive_ip.s_inp_buf;
        ps_video_encode_op->s_ive_op.u4_timestamp_low =
            ps_video_encode_ip->s_ive_ip.u4_timestamp_low;
        ps_video_encode_op->s_ive_op.u4_timestamp_high =
            ps_video_encode_ip->s_ive_ip.u4_timestamp_high;

        ps_video_encode_op->s_ive_op.u4_is_last = ps_video_encode_ip->s_ive_ip.u4_is_last;

        /* send the output to app */
        ps_video_encode_op->s_ive_op.output_present = 1;
        ps_video_encode_op->s_ive_op.dump_recon = 0;
        ps_video_encode_op->s_ive_op.s_out_buf = ps_codec->as_out_buf[ctxt_sel].as_bits_buf[0];

        for(i = 1; i < ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers; i++)
        {
            memmove(((UWORD8 *) ps_video_encode_op->s_ive_op.s_out_buf.pv_buf +
                     ps_video_encode_op->s_ive_op.s_out_buf.u4_bytes),
                    ps_codec->as_out_buf[ctxt_sel].as_bits_buf[i].pv_buf,
                    ps_codec->as_out_buf[ctxt_sel].as_bits_buf[i].u4_bytes);

            ps_video_encode_op->s_ive_op.s_out_buf.u4_bytes +=
                ps_codec->as_out_buf[ctxt_sel].as_bits_buf[i].u4_bytes;
        }

        /* error status */
        SET_ERROR_ON_RETURN(error_status, IVE_FATALERROR,
                            ps_video_encode_op->s_ive_op.u4_error_code, IV_FAIL);

        /* indicates that header has been generated previously */
        ps_codec->u4_header_generated = 1;

        /* api call cnt */
        ps_codec->i4_encode_api_call_cnt--;

        /* header mode tag is not sticky */
        ps_codec->i4_header_mode = 0;
        ps_codec->i4_gen_header = 0;

        return IV_SUCCESS;
    }

    /* curr pic cnt */
    ps_codec->i4_pic_cnt += 1;

    i4_rc_pre_enc_skip = 0;
    for(i = 0; i < ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers; i++)
    {
        i4_rc_pre_enc_skip =
            isvce_input_queue_update(ps_codec, &ps_video_encode_ip->s_ive_ip, &s_inp_buf, i);
    }

    s_out_buf.u4_is_last = s_inp_buf.s_inp_props.u4_is_last;
    ps_video_encode_op->s_ive_op.u4_is_last = s_inp_buf.s_inp_props.u4_is_last;

    /* Only encode if the current frame is not pre-encode skip */
    if(!i4_rc_pre_enc_skip && s_inp_buf.s_inp_props.s_raw_buf.apv_bufs[0])
    {
        isvce_process_ctxt_t *ps_proc = &ps_codec->as_process[ctxt_sel * MAX_PROCESS_THREADS];

        WORD32 num_thread_cnt = ps_codec->s_cfg.u4_num_cores - 1;

        ps_codec->ai4_pic_cnt[ctxt_sel] = ps_codec->i4_pic_cnt;

        error_status = isvce_svc_au_init(ps_codec, &s_inp_buf);

        SET_ERROR_ON_RETURN(error_status, IVE_FATALERROR,
                            ps_video_encode_op->s_ive_op.u4_error_code, IV_FAIL);

        isvce_nalu_info_au_init(ps_codec->as_nalu_descriptors,
                                ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers);

#if ENABLE_MODE_STAT_VISUALISER
        isvce_msv_get_input_frame(ps_codec->ps_mode_stat_visualiser, &s_inp_buf);
#endif

        for(i = 0; i < ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers; i++)
        {
            isvce_svc_layer_pic_init(ps_codec, &s_inp_buf, i);

            for(j = 0; j < num_thread_cnt; j++)
            {
                ithread_create(ps_codec->apv_proc_thread_handle[j], NULL, isvce_process_thread,
                               &ps_codec->as_process[j + 1]);

                ps_codec->ai4_process_thread_created[j] = 1;

                ps_codec->i4_proc_thread_cnt++;
            }

            /* launch job */
            isvce_process_thread(ps_proc);

            /* Join threads at the end of encoding a frame */
            isvce_join_threads(ps_codec);

            ih264_list_reset(ps_codec->pv_proc_jobq);

            ih264_list_reset(ps_codec->pv_entropy_jobq);
        }

#if ENABLE_MODE_STAT_VISUALISER
        isvce_msv_dump_visualisation(ps_codec->ps_mode_stat_visualiser);
#endif

        isvce_sub_pic_rc_dump_data(ps_codec->as_process->ps_sub_pic_rc_ctxt);
    }

    /****************************************************************************
     * RECON
     *    Since we have forward dependent frames, we cannot return recon in
     *encoding order. It must be in poc order, or input pic order. To achieve this
     *we introduce a delay of 1 to the recon wrt encode. Now since we have that
     *    delay, at any point minimum of pic_cnt in our ref buffer will be the
     *    correct frame. For ex let our GOP be IBBP [1 2 3 4] . The encode order
     *    will be [1 4 2 3] .Now since we have a delay of 1, when we are done with
     *    encoding 4, the min in the list will be 1. After encoding 2, it will be
     *    2, 3 after 3 and 4 after 4. Hence we can return in sequence. Note
     *    that the 1 delay is critical. Hence if we have post enc skip, we must
     *    skip here too. Note that since post enc skip already frees the recon
     *    buffer we need not do any thing here
     *
     *    We need to return a recon when ever we consume an input buffer. This
     *    comsumption include a pre or post enc skip. Thus dump recon is set for
     *    all cases except when
     *    1) We are waiting -> ps_codec->i4_pic_cnt >
     *ps_codec->s_cfg.u4_num_bframe An exception need to be made for the case when
     *we have the last buffer since we need to flush out the on remainig recon.
     ****************************************************************************/

    ps_video_encode_op->s_ive_op.dump_recon = 0;

    if(ps_codec->s_cfg.u4_enable_recon &&
       ((ps_codec->i4_pic_cnt > (WORD32) ps_codec->s_cfg.u4_num_bframes) ||
        s_inp_buf.s_inp_props.u4_is_last))
    {
        /* error status */
        IH264_ERROR_T ret = IH264_SUCCESS;

        svc_au_buf_t *ps_pic_buf = NULL;

        WORD32 i4_buf_status, i4_curr_poc = 32768;

        /* In case of skips we return recon, but indicate that buffer is zero size
         */
        if(ps_codec->s_rate_control.post_encode_skip[ctxt_sel] || i4_rc_pre_enc_skip)
        {
            ps_video_encode_op->s_ive_op.dump_recon = 1;
            ps_video_encode_op->s_ive_op.s_recon_buf.au4_wd[0] = 0;
            ps_video_encode_op->s_ive_op.s_recon_buf.au4_wd[1] = 0;
        }
        else
        {
            for(i = 0; i < ps_codec->i4_ref_buf_cnt; i++)
            {
                if(ps_codec->as_ref_set[i].i4_pic_cnt == -1) continue;

                i4_buf_status = ih264_buf_mgr_get_status(
                    ps_codec->pv_ref_buf_mgr, ps_codec->as_ref_set[i].ps_pic_buf->i4_buf_id);

                if((i4_buf_status & BUF_MGR_IO) && (ps_codec->as_ref_set[i].i4_poc < i4_curr_poc))
                {
                    ps_pic_buf = ps_codec->as_ref_set[i].ps_pic_buf;
                    i4_curr_poc = ps_codec->as_ref_set[i].i4_poc;
                }
            }

            ps_video_encode_op->s_ive_op.s_recon_buf = ps_video_encode_ip->s_ive_ip.s_recon_buf;

            /*
             * If we get a valid buffer. output and free recon.
             *
             * we may get an invalid buffer if num_b_frames is 0. This is because
             * We assume that there will be a ref frame in ref list after encoding
             * the last frame. With B frames this is correct since its forward ref
             * pic will be in the ref list. But if num_b_frames is 0, we will not
             * have a forward ref pic
             */

            if(ps_pic_buf)
            {
                if((ps_video_encode_ip->s_ive_ip.s_recon_buf.au4_wd[Y] !=
                    ps_codec->s_cfg.u4_disp_wd) ||
                   (ps_video_encode_ip->s_ive_ip.s_recon_buf.au4_ht[Y] !=
                    ps_codec->s_cfg.u4_disp_ht))
                {
                    SET_ERROR_ON_RETURN(IH264E_NO_FREE_RECONBUF, IVE_FATALERROR,
                                        ps_video_encode_op->s_ive_op.u4_error_code, IV_FAIL);
                }

                isvce_fmt_conv(ps_codec, ps_pic_buf,
                               ps_video_encode_ip->s_ive_ip.s_recon_buf.apv_bufs[0],
                               ps_video_encode_ip->s_ive_ip.s_recon_buf.apv_bufs[1],
                               ps_video_encode_ip->s_ive_ip.s_recon_buf.apv_bufs[2],
                               ps_video_encode_ip->s_ive_ip.s_recon_buf.au4_wd[0],
                               ps_video_encode_ip->s_ive_ip.s_recon_buf.au4_wd[1], 0,
                               ps_codec->s_cfg.u4_disp_ht);

                ps_video_encode_op->s_ive_op.dump_recon = 1;

                ret = ih264_buf_mgr_release(ps_codec->pv_ref_buf_mgr, ps_pic_buf->i4_buf_id,
                                            BUF_MGR_IO);

                if(IH264_SUCCESS != ret)
                {
                    SET_ERROR_ON_RETURN((IH264E_ERROR_T) ret, IVE_FATALERROR,
                                        ps_video_encode_op->s_ive_op.u4_error_code, IV_FAIL);
                }
            }
        }
    }

    /***************************************************************************
     * Free reference buffers:
     * In case of a post enc skip, we have to ensure that those pics will not
     * be used as reference anymore. In all other cases we will not even mark
     * the ref buffers
     ***************************************************************************/
    if(ps_codec->s_rate_control.post_encode_skip[ctxt_sel])
    {
        /* pic info */
        svc_au_buf_t *ps_cur_pic;

        /* mv info */
        svc_au_data_t *ps_cur_mv_buf;

        /* error status */
        IH264_ERROR_T ret = IH264_SUCCESS;

        /* Decrement coded pic count */
        ps_codec->i4_poc--;

        /* loop through to get the min pic cnt among the list of pics stored in ref
         * list */
        /* since the skipped frame may not be on reference list, we may not have an
         * MV bank hence free only if we have allocated */
        for(i = 0; i < ps_codec->i4_ref_buf_cnt; i++)
        {
            if(ps_codec->i4_pic_cnt == ps_codec->as_ref_set[i].i4_pic_cnt)
            {
                ps_cur_pic = ps_codec->as_ref_set[i].ps_pic_buf;

                ps_cur_mv_buf = ps_codec->as_ref_set[i].ps_svc_au_data;

                /* release this frame from reference list and recon list */
                ret = ih264_buf_mgr_release(ps_codec->pv_svc_au_data_store_mgr,
                                            ps_cur_mv_buf->i4_buf_id, BUF_MGR_REF);
                ret |= ih264_buf_mgr_release(ps_codec->pv_svc_au_data_store_mgr,
                                             ps_cur_mv_buf->i4_buf_id, BUF_MGR_IO);
                SET_ERROR_ON_RETURN((IH264E_ERROR_T) ret, IVE_FATALERROR,
                                    ps_video_encode_op->s_ive_op.u4_error_code, IV_FAIL);

                ret = ih264_buf_mgr_release(ps_codec->pv_ref_buf_mgr, ps_cur_pic->i4_buf_id,
                                            BUF_MGR_REF);
                ret |= ih264_buf_mgr_release(ps_codec->pv_ref_buf_mgr, ps_cur_pic->i4_buf_id,
                                             BUF_MGR_IO);
                SET_ERROR_ON_RETURN((IH264E_ERROR_T) ret, IVE_FATALERROR,
                                    ps_video_encode_op->s_ive_op.u4_error_code, IV_FAIL);
                break;
            }
        }
    }

    /*
     * Since recon is not in sync with output, ie there can be frame to be
     * given back as recon even after last output. Hence we need to mark that
     * the output is not the last.
     * Hence search through reflist and mark appropriately
     */
    if(ps_codec->s_cfg.u4_enable_recon)
    {
        WORD32 i4_buf_status = 0;

        for(i = 0; i < ps_codec->i4_ref_buf_cnt; i++)
        {
            if(ps_codec->as_ref_set[i].i4_pic_cnt == -1) continue;

            i4_buf_status |= ih264_buf_mgr_get_status(
                ps_codec->pv_ref_buf_mgr, ps_codec->as_ref_set[i].ps_pic_buf->i4_buf_id);
        }

        if(i4_buf_status & BUF_MGR_IO)
        {
            s_out_buf.u4_is_last = 0;
            ps_video_encode_op->s_ive_op.u4_is_last = 0;
        }
    }

    /**************************************************************************
     * Signaling to APP
     *  1) If we valid a valid output mark it so
     *  2) Set the codec output ps_video_encode_op
     *  3) Set the error status
     *  4) Set the return Pic type
     *      Note that we already has marked recon properly
     *  5)Send the consumed input back to app so that it can free it if possible
     *
     *  We will have to return the output and input buffers unconditionally
     *  so that app can release them
     **************************************************************************/
    if(!i4_rc_pre_enc_skip && !ps_codec->s_rate_control.post_encode_skip[ctxt_sel] &&
       s_inp_buf.s_inp_props.s_raw_buf.apv_bufs[0])
    {
        /* receive output back from codec */
        s_out_buf = ps_codec->as_out_buf[ctxt_sel];

        /* send the output to app */
        ps_video_encode_op->s_ive_op.output_present = 1;
        ps_video_encode_op->s_ive_op.u4_error_code = IV_SUCCESS;

        /* Set the time stamps of the encodec input */
        ps_video_encode_op->s_ive_op.u4_timestamp_low = s_inp_buf.s_inp_props.u4_timestamp_low;
        ps_video_encode_op->s_ive_op.u4_timestamp_high = s_inp_buf.s_inp_props.u4_timestamp_high;

        switch(ps_codec->pic_type)
        {
            case PIC_IDR:
                ps_video_encode_op->s_ive_op.u4_encoded_frame_type = IV_IDR_FRAME;
                break;

            case PIC_I:
                ps_video_encode_op->s_ive_op.u4_encoded_frame_type = IV_I_FRAME;
                break;

            case PIC_P:
                ps_video_encode_op->s_ive_op.u4_encoded_frame_type = IV_P_FRAME;
                break;

            case PIC_B:
                ps_video_encode_op->s_ive_op.u4_encoded_frame_type = IV_B_FRAME;
                break;

            default:
                ps_video_encode_op->s_ive_op.u4_encoded_frame_type = IV_NA_FRAME;
                break;
        }

        for(i = 0; i < (WORD32) ps_codec->s_cfg.u4_num_cores; i++)
        {
            error_status = ps_codec->as_process[ctxt_sel + i].i4_error_code;
            SET_ERROR_ON_RETURN(error_status, IVE_FATALERROR,
                                ps_video_encode_op->s_ive_op.u4_error_code, IV_FAIL);
        }
    }
    else
    {
        /* receive output back from codec */
        s_out_buf = ps_codec->as_out_buf[ctxt_sel];

        ps_video_encode_op->s_ive_op.output_present = 0;
        ps_video_encode_op->s_ive_op.u4_error_code = IV_SUCCESS;

        /* Set the time stamps of the encodec input */
        ps_video_encode_op->s_ive_op.u4_timestamp_low = 0;
        ps_video_encode_op->s_ive_op.u4_timestamp_high = 0;

        ps_video_encode_op->s_ive_op.s_inp_buf = s_inp_buf.s_inp_props.s_raw_buf;

        ps_video_encode_op->s_ive_op.u4_encoded_frame_type = IV_NA_FRAME;
    }

    /* Send the input to encoder so that it can free it if possible */
    ps_video_encode_op->s_ive_op.s_out_buf = ps_codec->as_out_buf[ctxt_sel].as_bits_buf[0];

    for(i = 1; i < ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers; i++)
    {
        memmove(((UWORD8 *) ps_video_encode_op->s_ive_op.s_out_buf.pv_buf +
                 ps_video_encode_op->s_ive_op.s_out_buf.u4_bytes),
                ps_codec->as_out_buf[ctxt_sel].as_bits_buf[i].pv_buf,
                ps_codec->as_out_buf[ctxt_sel].as_bits_buf[i].u4_bytes);

        ps_video_encode_op->s_ive_op.s_out_buf.u4_bytes +=
            ps_codec->as_out_buf[ctxt_sel].as_bits_buf[i].u4_bytes;
    }

    if(ps_codec->s_cfg.b_nalu_info_export_enable && !i4_rc_pre_enc_skip &&
       !ps_codec->s_rate_control.post_encode_skip[ctxt_sel] &&
       s_inp_buf.s_inp_props.s_raw_buf.apv_bufs[0])
    {
        ps_video_encode_op->b_is_nalu_info_present = true;

        for(i = 0; i < ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers; i++)
        {
            isvce_nalu_info_csv_translator(&ps_codec->as_nalu_descriptors[i],
                                           &ps_video_encode_ip->ps_nalu_info_buf[i]);

            ps_video_encode_op->ps_nalu_info_buf[i] = ps_video_encode_ip->ps_nalu_info_buf[i];
        }
    }
    else
    {
        ps_video_encode_op->b_is_nalu_info_present = false;
    }

    ps_video_encode_op->s_ive_op.s_inp_buf = s_inp_buf.s_inp_props.s_raw_buf;

    return IV_SUCCESS;
}

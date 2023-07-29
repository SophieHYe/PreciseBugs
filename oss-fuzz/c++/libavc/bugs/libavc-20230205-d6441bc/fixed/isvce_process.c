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
*******************************************************************************
* @file
*  isvce_process.c
*
* @brief
*  Contains functions for codec thread
*
* @author
*  Harish
*
* @par List of Functions:
* - isvce_generate_sps_pps()
* - isvce_init_entropy_ctxt()
* - isvce_entropy()
* - isvce_pack_header_data()
* - isvce_update_proc_ctxt()
* - isvce_init_proc_ctxt()
* - isvce_pad_recon_buffer()
* - isvce_dblk_n_mbs()
* - isvce_process()
* - isvce_set_rc_pic_params()
* - isvce_update_rc_post_enc()
* - isvce_isvce_isvce_process_ctxt_thread()
*
* @remarks
*  None
*
*******************************************************************************
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
#include "isvce_cabac.h"
#include "isvce_cavlc.h"
#include "isvce_deblk.h"
#include "isvce_defs.h"
#include "isvce_downscaler.h"
#include "isvce_encode_header.h"
#include "isvce_ibl_eval.h"
#include "isvce_ilp_mv.h"
#include "isvce_intra_modes_eval.h"
#include "isvce_me.h"
#include "isvce_rate_control.h"
#include "isvce_residual_pred.h"
#include "isvce_sub_pic_rc.h"
#include "isvce_utils.h"

/*****************************************************************************/
/* Function Definitions                                                      */
/*****************************************************************************/

/**
******************************************************************************
*
*  @brief This function generates sps, pps set on request
*
*  @par   Description
*  When the encoder is set in header generation mode, the following function
*  is called. This generates sps and pps headers and returns the control back
*  to caller.
*
*  @param[in]    ps_codec
*  pointer to codec context
*
*  @return      success or failure error code
*
******************************************************************************
*/
IH264E_ERROR_T isvce_generate_sps_pps(isvce_codec_t *ps_codec, isvce_inp_buf_t *ps_inp_buf)
{
    sps_t *ps_sps;
    pps_t *ps_pps;
    subset_sps_t *ps_subset_sps;

    WORD32 i;

    isvce_process_ctxt_t *ps_proc = ps_codec->as_process;
    isvce_entropy_ctxt_t *ps_entropy = &ps_proc->s_entropy;
    bitstrm_t *ps_bitstrm = ps_entropy->ps_bitstrm;
    isvce_out_buf_t *ps_out_buf = ps_codec->as_out_buf;

    UWORD8 u1_profile_idc = IH264_PROFILE_BASELINE;

    ASSERT(1 == MAX_CTXT_SETS);

    ih264e_bitstrm_init(ps_bitstrm, ps_out_buf->as_bits_buf[ps_proc->u1_spatial_layer_id].pv_buf,
                        ps_out_buf->as_bits_buf[ps_proc->u1_spatial_layer_id].u4_bufsize);

    ps_sps = ps_codec->ps_sps_base;
    isvce_populate_sps(ps_codec, ps_sps, 0, u1_profile_idc, ps_inp_buf, 0);

    ps_pps = ps_codec->ps_pps_base;
    isvce_populate_pps(ps_codec, ps_pps, 0, 0, 0);

    for(i = 1; i < ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers; i++)
    {
        ps_subset_sps = ps_codec->ps_subset_sps_base + i;
        isvce_populate_subset_sps(ps_codec, ps_subset_sps, i, ps_inp_buf, i);

        ps_pps = ps_codec->ps_pps_base + i;
        isvce_populate_pps(ps_codec, ps_pps, i, i, i);
    }

    ps_entropy->i4_error_code = IH264E_SUCCESS;

    ps_entropy->i4_error_code = isvce_generate_sps(ps_bitstrm, ps_sps, NAL_SPS);
    if(ps_entropy->i4_error_code != IH264E_SUCCESS)
    {
        return ps_entropy->i4_error_code;
    }

    ps_pps = ps_codec->ps_pps_base;
    ps_entropy->i4_error_code = isvce_generate_pps(ps_bitstrm, ps_pps, ps_sps);

    for(i = 1; i < ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers; i++)
    {
        ps_subset_sps = ps_codec->ps_subset_sps_base + i;
        isvce_generate_subset_sps(ps_bitstrm, ps_subset_sps);

        /* populate pps header */
        ps_pps = ps_codec->ps_pps_base + i;
        isvce_generate_pps(ps_bitstrm, ps_pps, &ps_subset_sps->s_sps);
    }

    /* queue output buffer */
    ps_out_buf->as_bits_buf[ps_proc->u1_spatial_layer_id].u4_bytes = ps_bitstrm->u4_strm_buf_offset;

    return ps_entropy->i4_error_code;
}

/**
*******************************************************************************
*
* @brief   initialize entropy context.
*
* @par Description:
*  Before invoking the call to perform to entropy coding the entropy context
*  associated with the job needs to be initialized. This involves the start
*  mb address, end mb address, slice index and the pointer to location at
*  which the mb residue info and mb header info are packed.
*
* @param[in] ps_proc
*  Pointer to the current process context
*
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
IH264E_ERROR_T isvce_init_entropy_ctxt(isvce_process_ctxt_t *ps_proc)
{
    /* codec context */
    isvce_codec_t *ps_codec = ps_proc->ps_codec;

    /* entropy ctxt */
    isvce_entropy_ctxt_t *ps_entropy = &ps_proc->s_entropy;

    /* start address */
    ps_entropy->i4_mb_start_add = ps_entropy->i4_mb_y * ps_entropy->i4_wd_mbs + ps_entropy->i4_mb_x;

    /* end address */
    ps_entropy->i4_mb_end_add = ps_entropy->i4_mb_start_add + ps_entropy->i4_mb_cnt;

    /* slice index */
    ps_entropy->i4_cur_slice_idx = ps_proc->pu1_slice_idx[ps_entropy->i4_mb_start_add];

    /* sof */
    /* @ start of frame or start of a new slice, set sof flag */
    if(ps_entropy->i4_mb_start_add == 0)
    {
        ps_entropy->i4_sof = 1;
    }

    if(ps_entropy->i4_mb_x == 0)
    {
        /* packed mb coeff data */
        ps_entropy->pv_mb_coeff_data = ((UWORD8 *) ps_entropy->pv_pic_mb_coeff_data) +
                                       ps_entropy->i4_mb_y * ps_codec->u4_size_coeff_data;

        /* packed mb header data */
        ps_entropy->pv_mb_header_data = ((UWORD8 *) ps_entropy->pv_pic_mb_header_data) +
                                        ps_entropy->i4_mb_y * ps_codec->u4_size_header_data;
    }

    return IH264E_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Function to update rc context after encoding
*
* @par   Description
*  This function updates the rate control context after the frame is encoded.
*  Number of bits consumed by the current frame, frame distortion, frame cost,
*  number of intra/inter mb's, ... are passed on to rate control context for
*  updating the rc model.
*
* @param[in] ps_codec
*  Handle to codec context
*
* @param[in] ctxt_sel
*  frame context selector
*
* @param[in] pic_cnt
*  pic count
*
* @returns i4_stuffing_byte
*  number of stuffing bytes (if necessary)
*
* @remarks
*
*******************************************************************************
*/
WORD32 isvce_update_rc_post_enc(isvce_codec_t *ps_codec, WORD32 ctxt_sel, WORD32 i4_is_first_frm)
{
    WORD32 i4_proc_ctxt_sel_base = ctxt_sel ? (MAX_PROCESS_CTXT / 2) : 0;

    isvce_process_ctxt_t *ps_proc = &ps_codec->as_process[i4_proc_ctxt_sel_base];

#if ENABLE_RE_ENC_AS_SKIP
    isvce_entropy_ctxt_t *ps_entropy = &ps_proc->s_entropy;

    UWORD8 u1_is_post_enc_skip = 0;
#endif

    /* frame qp */
    UWORD8 u1_frame_qp = ps_codec->au4_frame_qp[ps_proc->u1_spatial_layer_id];

    /* cbr rc return status */
    WORD32 i4_stuffing_byte = 0;

    /* current frame stats */
    frame_info_t s_frame_info;
    picture_type_e rc_pic_type = I_PIC;

    /* temp var */
    WORD32 i, j;

    /********************************************************************/
    /*                            BEGIN INIT                            */
    /********************************************************************/

    /* init frame info */
    irc_init_frame_info(&s_frame_info);

    /* get frame info */
    for(i = 0; i < (WORD32) ps_codec->s_cfg.u4_num_cores; i++)
    {
        /*****************************************************************/
        /* One frame can be encoded by max of u4_num_cores threads       */
        /* Accumulating the num mbs, sad, qp and intra_mb_cost from      */
        /* u4_num_cores threads                                          */
        /*****************************************************************/
        for(j = 0; j < MAX_MB_TYPE; j++)
        {
            s_frame_info.num_mbs[j] += ps_proc[i].s_frame_info.num_mbs[j];

            s_frame_info.tot_mb_sad[j] += ps_proc[i].s_frame_info.tot_mb_sad[j];

            s_frame_info.qp_sum[j] += ps_proc[i].s_frame_info.qp_sum[j];
        }

        s_frame_info.intra_mb_cost_sum += ps_proc[i].s_frame_info.intra_mb_cost_sum;

        s_frame_info.activity_sum += ps_proc[i].s_frame_info.activity_sum;

        /*****************************************************************/
        /* gather number of residue and header bits consumed by the frame*/
        /*****************************************************************/
        isvce_update_rc_bits_info(&s_frame_info, &ps_proc[i].s_entropy);
    }

    /* get pic type */
    switch(ps_codec->pic_type)
    {
        case PIC_I:
        case PIC_IDR:
            rc_pic_type = I_PIC;
            break;
        case PIC_P:
            rc_pic_type = P_PIC;
            break;
        case PIC_B:
            rc_pic_type = B_PIC;
            break;
        default:
            assert(0);
            break;
    }

    /* update rc lib with current frame stats */
    i4_stuffing_byte = isvce_rc_post_enc(
        ps_codec->s_rate_control.apps_rate_control_api[ps_proc->u1_spatial_layer_id],
        &(s_frame_info), ps_codec->s_rate_control.pps_pd_frm_rate,
        ps_codec->s_rate_control.pps_time_stamp, ps_codec->s_rate_control.pps_frame_time,
        (ps_proc->i4_wd_mbs * ps_proc->i4_ht_mbs), &rc_pic_type, i4_is_first_frm,
        &ps_codec->s_rate_control.post_encode_skip[ctxt_sel], u1_frame_qp,
        &ps_codec->s_rate_control.ai4_num_intra_in_prev_frame[ps_proc->u1_spatial_layer_id],
        &ps_codec->s_rate_control.ai4_avg_activity[ps_proc->u1_spatial_layer_id]
#if ENABLE_RE_ENC_AS_SKIP
        ,
        &u1_is_post_enc_skip
#endif
    );

#if ENABLE_RE_ENC_AS_SKIP
    if(u1_is_post_enc_skip)
    {
        buffer_container_t s_dst;

        WORD32 i;

        isa_dependent_fxns_t *ps_isa_dependent_fxns = &ps_codec->s_isa_dependent_fxns;
        mem_fxns_t *ps_mem_fxns = &ps_isa_dependent_fxns->s_mem_fxns;
        svc_ilp_data_t *ps_svc_ilp_data = &ps_codec->s_svc_ilp_data;

        UWORD8 u1_spatial_layer_id = ps_proc->u1_spatial_layer_id;
        UWORD8 u1_num_spatial_layers = ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers;

        UWORD32 u4_wd = ps_codec->s_cfg.u4_wd;
        UWORD32 u4_ht = ps_codec->s_cfg.u4_ht;
        DOUBLE d_spatial_res_ratio = ps_codec->s_cfg.s_svc_params.d_spatial_res_ratio;

        WORD32 i4_layer_luma_wd =
            (WORD32) (((DOUBLE) u4_wd /
                       pow(d_spatial_res_ratio, u1_num_spatial_layers - u1_spatial_layer_id - 1)) +
                      0.99);
        WORD32 i4_layer_luma_ht =
            (WORD32) (((DOUBLE) u4_ht /
                       pow(d_spatial_res_ratio, u1_num_spatial_layers - u1_spatial_layer_id - 1)) +
                      0.99);

        if(CABAC == ps_entropy->u1_entropy_coding_mode_flag)
        {
            isvce_reencode_as_skip_frame_cabac(ps_entropy);
        }
        else
        {
            isvce_reencode_as_skip_frame_cavlc(ps_entropy);
        }

        if(u1_num_spatial_layers > 1)
        {
            for(i = 0; i < ps_proc->i4_ht_mbs; i++)
            {
                for(j = 0; j < ps_proc->i4_wd_mbs; j++)
                {
                    isvce_update_ibl_info(ps_proc->ps_intra_pred_ctxt, u1_num_spatial_layers,
                                          u1_spatial_layer_id, PSKIP, j, i, 0);
                }
            }

            if(ENABLE_ILP_MV)
            {
                svc_layer_data_t *ps_layer_data;

                svc_au_data_t *ps_svc_au_data = ps_svc_ilp_data->ps_svc_au_data;

                WORD32 i4_num_mbs = ps_proc->i4_ht_mbs * ps_proc->i4_wd_mbs;

                ps_layer_data = &ps_svc_au_data->ps_svc_layer_data[ps_entropy->u1_spatial_layer_id];

                memset(ps_layer_data->ps_mb_info, 0,
                       i4_num_mbs * sizeof(ps_layer_data->ps_mb_info[0]));

                for(i = 0; i < i4_num_mbs; i++)
                {
                    ps_layer_data->pu4_num_pus_in_mb[i] = 1;
                }
            }
        }

        for(i = 0; i < NUM_SP_COMPONENTS; i++)
        {
            UWORD8 u1_is_chroma = (Y != ((COMPONENT_TYPE) i));
            WORD32 i4_src_strd = ps_proc->aps_ref_pic[0]
                                     ->ps_layer_yuv_buf_props[u1_spatial_layer_id]
                                     .as_component_bufs[i]
                                     .i4_data_stride;
            WORD32 i4_dst_strd = ps_proc->ps_cur_pic->ps_layer_yuv_buf_props[u1_spatial_layer_id]
                                     .as_component_bufs[i]
                                     .i4_data_stride;

            if(u1_spatial_layer_id < (u1_num_spatial_layers - 1))
            {
                s_dst.i4_data_stride = ps_svc_ilp_data->ps_intra_recon_bufs[u1_spatial_layer_id]
                                           .as_component_bufs[i]
                                           .i4_data_stride;
                s_dst.pv_data =
                    ((UWORD8 *) ps_svc_ilp_data->ps_intra_recon_bufs[u1_spatial_layer_id]
                         .as_component_bufs[i]
                         .pv_data);

                ps_mem_fxns->pf_memset_2d((UWORD8 *) s_dst.pv_data, s_dst.i4_data_stride, 0,
                                          i4_layer_luma_wd, (i4_layer_luma_ht >> u1_is_chroma));

                if(ENABLE_RESIDUAL_PREDICTION)
                {
                    WORD16 *pi2_res;
                    yuv_buf_props_t *ps_residual_buf =
                        &ps_codec->s_svc_ilp_data.ps_residual_bufs[u1_spatial_layer_id];

                    pi2_res = ps_residual_buf->as_component_bufs[u1_is_chroma].pv_data;

                    ps_mem_fxns->pf_memset_2d(
                        (UWORD8 *) pi2_res,
                        ps_residual_buf->as_component_bufs[u1_is_chroma].i4_data_stride *
                            (sizeof(WORD16) / sizeof(UWORD8)),
                        0,
                        ps_residual_buf->as_component_bufs[u1_is_chroma].i4_data_stride *
                            (sizeof(WORD16) / sizeof(UWORD8)),
                        i4_layer_luma_ht >> u1_is_chroma);
                }
            }

            ps_mem_fxns->pf_copy_2d(
                (UWORD8 *) (ps_proc->ps_cur_pic->ps_layer_yuv_buf_props[u1_spatial_layer_id]
                                .as_component_bufs[i]
                                .pv_data) -
                    PAD_LEFT - (PAD_TOP * i4_dst_strd),
                i4_dst_strd,
                (UWORD8 *) (ps_proc->aps_ref_pic[0]
                                ->ps_layer_yuv_buf_props[u1_spatial_layer_id]
                                .as_component_bufs[i]
                                .pv_data) -
                    PAD_LEFT - (PAD_TOP * i4_src_strd),
                i4_src_strd, (i4_layer_luma_wd + PAD_WD),
                (i4_layer_luma_ht >> u1_is_chroma) + PAD_HT);
        }

        RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);
    }

#endif
    return i4_stuffing_byte;
}

/**
*******************************************************************************
*
* @brief entry point for entropy coding
*
* @par Description
*  This function calls lower level functions to perform entropy coding for a
*  group (n rows) of mb's. After encoding 1 row of mb's,  the function takes
*  back the control, updates the ctxt and calls lower level functions again.
*  This process is repeated till all the rows or group of mb's (which ever is
*  minimum) are coded
*
* @param[in] ps_proc
*  process context
*
* @returns  error status
*
* @remarks
*
*******************************************************************************
*/
IH264E_ERROR_T isvce_entropy(isvce_process_ctxt_t *ps_proc)
{
    svc_nalu_ext_t *aps_svc_nalu_ext[2];
    isvce_out_buf_t s_out_buf;
    sei_params_t s_sei;
    nalu_info_t *ps_slice_nalu_info;
    nalu_info_t *ps_non_vcl_nalu_info;

    UWORD8 *pu1_proc_map;
    UWORD8 *pu1_entropy_map_curr;
    WORD32 i4_wd_mbs, i4_ht_mbs;
    UWORD32 u4_mb_cnt, u4_mb_idx, u4_mb_end_idx, u4_insert_per_idr;
    WORD32 bitstream_start_offset, bitstream_end_offset;

    isvce_codec_t *ps_codec = ps_proc->ps_codec;
    isvce_entropy_ctxt_t *ps_entropy = &ps_proc->s_entropy;
    isvce_cabac_ctxt_t *ps_cabac_ctxt = ps_entropy->ps_cabac;
    sps_t *ps_sps = ps_entropy->ps_sps_base;
    subset_sps_t *ps_subset_sps = ps_entropy->ps_subset_sps_base;
    pps_t *ps_pps = ps_entropy->ps_pps_base;
    slice_header_t *ps_slice_hdr =
        ps_entropy->ps_slice_hdr_base + (ps_entropy->i4_cur_slice_idx % SVC_MAX_SLICE_HDR_CNT);
    svc_slice_header_t *ps_svc_slice_hdr = NULL;
    bitstrm_t *ps_bitstrm = ps_entropy->ps_bitstrm;
#if ENABLE_RE_ENC_AS_SKIP
    bitstrm_t *ps_bitstrm_after_slice_hdr = ps_entropy->ps_bitstrm_after_slice_hdr;
#endif
    nalu_descriptors_t *ps_nalu_descriptor =
        &ps_codec->as_nalu_descriptors[ps_proc->u1_spatial_layer_id];

    WORD32 i4_slice_type = ps_proc->i4_slice_type;
    WORD32 ctxt_sel = ps_proc->i4_encode_api_call_cnt % MAX_CTXT_SETS;

    aps_svc_nalu_ext[0] =
        ps_entropy->ps_svc_nalu_ext_base + (ps_entropy->i4_cur_slice_idx % SVC_MAX_SLICE_HDR_CNT);
    aps_svc_nalu_ext[1] = ps_entropy->ps_svc_nalu_ext_base + 1 +
                          (ps_entropy->i4_cur_slice_idx % SVC_MAX_SLICE_HDR_CNT);

    /********************************************************************/
    /*                            BEGIN INIT                            */
    /********************************************************************/

    /* entropy encode start address */
    u4_mb_idx = ps_entropy->i4_mb_start_add;

    /* entropy encode end address */
    u4_mb_end_idx = ps_entropy->i4_mb_end_add;

    /* width in mbs */
    i4_wd_mbs = ps_entropy->i4_wd_mbs;

    /* height in mbs */
    i4_ht_mbs = ps_entropy->i4_ht_mbs;

    /* total mb cnt */
    u4_mb_cnt = i4_wd_mbs * i4_ht_mbs;

    /* proc map */
    pu1_proc_map = ps_proc->pu1_proc_map + ps_entropy->i4_mb_y * i4_wd_mbs;

    /* entropy map */
    pu1_entropy_map_curr = ps_entropy->pu1_entropy_map + ps_entropy->i4_mb_y * i4_wd_mbs;

    /********************************************************************/
    /* @ start of frame / slice,                                        */
    /*      initialize the output buffer,                               */
    /*      initialize the bit stream buffer,                           */
    /*      check if sps and pps headers have to be generated,          */
    /*      populate and generate slice header                          */
    /********************************************************************/
    if(ps_entropy->i4_sof)
    {
        /********************************************************************/
        /*      initialize the output buffer                                */
        /********************************************************************/
        s_out_buf = ps_codec->as_out_buf[ctxt_sel];

        /* is last frame to encode */
        s_out_buf.u4_is_last = ps_entropy->u4_is_last;

        /* frame idx */
        s_out_buf.u4_timestamp_high = ps_entropy->u4_timestamp_high;
        s_out_buf.u4_timestamp_low = ps_entropy->u4_timestamp_low;

        /********************************************************************/
        /*      initialize the bit stream buffer                            */
        /********************************************************************/
        ih264e_bitstrm_init(ps_bitstrm, s_out_buf.as_bits_buf[ps_proc->u1_spatial_layer_id].pv_buf,
                            s_out_buf.as_bits_buf[ps_proc->u1_spatial_layer_id].u4_bufsize);

        /********************************************************************/
        /*                    BEGIN HEADER GENERATION                       */
        /********************************************************************/
        if(1 == ps_entropy->i4_gen_header)
        {
            WORD32 i;

            ps_non_vcl_nalu_info = isvce_get_next_nalu_info_buf(ps_nalu_descriptor);
            isvce_nalu_info_buf_init(ps_non_vcl_nalu_info,
                                     -((WORD32) isvce_get_num_bits(ps_bitstrm)), NAL_SPS,
                                     ps_proc->u1_spatial_layer_id,
                                     ps_proc->ps_cur_pic->i1_temporal_id, 1, !!ps_proc->u4_is_idr);

            ps_entropy->i4_error_code = isvce_generate_sps(ps_bitstrm, ps_sps, NAL_SPS);
            RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);

            ps_non_vcl_nalu_info->i8_num_bits += isvce_get_num_bits(ps_bitstrm);
            isvce_update_nalu_count(ps_nalu_descriptor);

            for(i = 1; i < ps_proc->s_svc_params.u1_num_spatial_layers; i++)
            {
                ps_subset_sps = ps_entropy->ps_subset_sps_base + i;

                ps_non_vcl_nalu_info = isvce_get_next_nalu_info_buf(ps_nalu_descriptor);
                isvce_nalu_info_buf_init(
                    ps_non_vcl_nalu_info, -((WORD32) isvce_get_num_bits(ps_bitstrm)),
                    NAL_SUBSET_SPS, ps_proc->u1_spatial_layer_id,
                    ps_proc->ps_cur_pic->i1_temporal_id, 1, !!ps_proc->u4_is_idr);

                ps_entropy->i4_error_code = isvce_generate_subset_sps(ps_bitstrm, ps_subset_sps);

                ps_non_vcl_nalu_info->i8_num_bits += isvce_get_num_bits(ps_bitstrm);
                isvce_update_nalu_count(ps_nalu_descriptor);
            }

            ps_non_vcl_nalu_info = isvce_get_next_nalu_info_buf(ps_nalu_descriptor);
            isvce_nalu_info_buf_init(ps_non_vcl_nalu_info,
                                     -((WORD32) isvce_get_num_bits(ps_bitstrm)), NAL_PPS,
                                     ps_proc->u1_spatial_layer_id,
                                     ps_proc->ps_cur_pic->i1_temporal_id, 1, !!ps_proc->u4_is_idr);

            ps_entropy->i4_error_code = isvce_generate_pps(ps_bitstrm, ps_pps, ps_sps);
            RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);

            ps_non_vcl_nalu_info->i8_num_bits += isvce_get_num_bits(ps_bitstrm);
            isvce_update_nalu_count(ps_nalu_descriptor);

            for(i = 1; i < ps_proc->s_svc_params.u1_num_spatial_layers; i++)
            {
                ps_pps = ps_entropy->ps_pps_base + i;
                ps_subset_sps = ps_entropy->ps_subset_sps_base + i;

                ps_non_vcl_nalu_info = isvce_get_next_nalu_info_buf(ps_nalu_descriptor);
                isvce_nalu_info_buf_init(
                    ps_non_vcl_nalu_info, -((WORD32) isvce_get_num_bits(ps_bitstrm)), NAL_PPS,
                    ps_proc->u1_spatial_layer_id, ps_proc->ps_cur_pic->i1_temporal_id, 1,
                    !!ps_proc->u4_is_idr);

                ps_entropy->i4_error_code =
                    isvce_generate_pps(ps_bitstrm, ps_pps, &ps_subset_sps->s_sps);

                RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);

                ps_non_vcl_nalu_info->i8_num_bits += isvce_get_num_bits(ps_bitstrm);
                isvce_update_nalu_count(ps_nalu_descriptor);
            }

            ps_entropy->i4_gen_header = 0;
        }

        ps_svc_slice_hdr = ps_entropy->ps_svc_slice_hdr_base +
                           (ps_entropy->i4_cur_slice_idx % SVC_MAX_SLICE_HDR_CNT);

        if((ps_codec->s_cfg.s_svc_params.u1_num_temporal_layers > 1) ||
           (ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers > 1))
        {
            isvce_populate_svc_nalu_extension(ps_proc, aps_svc_nalu_ext[0], NAL_PREFIX,
                                              ps_proc->u4_is_idr);

            if(ps_proc->u1_spatial_layer_id > 0)
            {
                isvce_populate_svc_nalu_extension(ps_proc, aps_svc_nalu_ext[1],
                                                  NAL_CODED_SLICE_EXTENSION, ps_proc->u4_is_idr);
            }
        }
        else
        {
            isvce_populate_svc_nalu_extension(ps_proc, aps_svc_nalu_ext[0], NAL_PREFIX,
                                              ps_proc->u4_is_idr);
        }

        if(ps_proc->u1_spatial_layer_id > 0)
        {
            ps_subset_sps = ps_entropy->ps_subset_sps_base + ps_proc->u1_spatial_layer_id;
            ps_pps = ps_entropy->ps_pps_base + ps_proc->u1_spatial_layer_id;

            ps_entropy->i4_error_code = isvce_populate_svc_slice(
                ps_proc, ps_svc_slice_hdr, ps_pps, ps_subset_sps, aps_svc_nalu_ext[1]);

            RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);

            ps_slice_hdr = &ps_svc_slice_hdr->s_slice_header;
        }
        else
        {
            ps_pps = ps_entropy->ps_pps_base;
            ps_sps = ps_entropy->ps_sps_base;

            ps_entropy->i4_error_code = isvce_populate_slice_header(
                ps_proc, ps_slice_hdr, ps_pps, ps_sps, aps_svc_nalu_ext[0]->u1_idr_flag);

            RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);
        }

        /* generate sei */
        u4_insert_per_idr = (NAL_SLICE_IDR == ps_slice_hdr->i1_nal_unit_type);

        memset(&s_sei, 0, sizeof(sei_params_t));
        s_sei.u1_sei_mdcv_params_present_flag =
            ps_codec->s_cfg.s_sei.u1_sei_mdcv_params_present_flag;
        s_sei.s_sei_mdcv_params = ps_codec->s_cfg.s_sei.s_sei_mdcv_params;
        s_sei.u1_sei_cll_params_present_flag = ps_codec->s_cfg.s_sei.u1_sei_cll_params_present_flag;
        s_sei.s_sei_cll_params = ps_codec->s_cfg.s_sei.s_sei_cll_params;
        s_sei.u1_sei_ave_params_present_flag = ps_codec->s_cfg.s_sei.u1_sei_ave_params_present_flag;
        s_sei.s_sei_ave_params = ps_codec->s_cfg.s_sei.s_sei_ave_params;
        s_sei.u1_sei_ccv_params_present_flag = 0;
        s_sei.s_sei_ccv_params =
            ps_codec->as_inp_list[ps_codec->i4_poc % SVC_MAX_NUM_INP_FRAMES].s_inp_props.s_sei_ccv;

        if((1 == ps_sps->i1_vui_parameters_present_flag) &&
           (1 == ps_codec->s_cfg.s_vui.u1_video_signal_type_present_flag) &&
           (1 == ps_codec->s_cfg.s_vui.u1_colour_description_present_flag) &&
           (2 != ps_codec->s_cfg.s_vui.u1_colour_primaries) &&
           (2 != ps_codec->s_cfg.s_vui.u1_matrix_coefficients) &&
           (2 != ps_codec->s_cfg.s_vui.u1_transfer_characteristics) &&
           (4 != ps_codec->s_cfg.s_vui.u1_transfer_characteristics) &&
           (5 != ps_codec->s_cfg.s_vui.u1_transfer_characteristics))
        {
            s_sei.u1_sei_ccv_params_present_flag =
                ps_codec->as_inp_list[ps_codec->i4_poc % SVC_MAX_NUM_INP_FRAMES]
                    .s_inp_props.u1_sei_ccv_params_present_flag;
        }

        if((1 == s_sei.u1_sei_mdcv_params_present_flag && u4_insert_per_idr) ||
           (1 == s_sei.u1_sei_cll_params_present_flag && u4_insert_per_idr) ||
           (1 == s_sei.u1_sei_ave_params_present_flag && u4_insert_per_idr) ||
           (1 == s_sei.u1_sei_ccv_params_present_flag))
        {
            ps_non_vcl_nalu_info = isvce_get_next_nalu_info_buf(ps_nalu_descriptor);
            isvce_nalu_info_buf_init(ps_non_vcl_nalu_info,
                                     -((WORD32) isvce_get_num_bits(ps_bitstrm)), NAL_SEI,
                                     ps_proc->u1_spatial_layer_id,
                                     ps_proc->ps_cur_pic->i1_temporal_id, 1, !!ps_proc->u4_is_idr);

            ps_entropy->i4_error_code = ih264e_generate_sei(ps_bitstrm, &s_sei, u4_insert_per_idr);
            RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);

            ps_non_vcl_nalu_info->i8_num_bits += isvce_get_num_bits(ps_bitstrm);
            isvce_update_nalu_count(ps_nalu_descriptor);
        }

        ps_codec->as_inp_list[ps_codec->i4_poc % SVC_MAX_NUM_INP_FRAMES]
            .s_inp_props.u1_sei_ccv_params_present_flag = 0;

        if((ps_proc->u1_spatial_layer_id == 0) &&
           (ps_codec->s_cfg.s_svc_params.u1_num_temporal_layers > 1 ||
            ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers > 1))
        {
            ps_non_vcl_nalu_info = isvce_get_next_nalu_info_buf(ps_nalu_descriptor);
            isvce_nalu_info_buf_init(ps_non_vcl_nalu_info,
                                     -((WORD32) isvce_get_num_bits(ps_bitstrm)), NAL_PREFIX,
                                     ps_proc->u1_spatial_layer_id,
                                     ps_proc->ps_cur_pic->i1_temporal_id, 1, !!ps_proc->u4_is_idr);

            ps_entropy->i4_error_code =
                isvce_generate_svc_nalu_extension(ps_bitstrm, aps_svc_nalu_ext[0], NAL_PREFIX);

            ps_entropy->i4_error_code = isvce_generate_prefix_nal(
                ps_bitstrm, aps_svc_nalu_ext[0], ps_slice_hdr, ps_sps->u1_max_num_ref_frames,
                ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers);
            RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);

            ps_non_vcl_nalu_info->i8_num_bits += isvce_get_num_bits(ps_bitstrm);
            isvce_update_nalu_count(ps_nalu_descriptor);
        }

        ps_slice_nalu_info = isvce_get_next_nalu_info_buf(ps_nalu_descriptor);
        isvce_nalu_info_buf_init(ps_slice_nalu_info, -((WORD32) isvce_get_num_bits(ps_bitstrm)),
                                 ps_slice_hdr->i1_nal_unit_type, ps_proc->u1_spatial_layer_id,
                                 ps_proc->ps_cur_pic->i1_temporal_id, 1, !!ps_proc->u4_is_idr);

        if(ps_proc->u1_spatial_layer_id > 0)
        {
            ps_subset_sps = ps_entropy->ps_subset_sps_base + ps_proc->u1_spatial_layer_id;
            ps_pps = ps_entropy->ps_pps_base + ps_proc->u1_spatial_layer_id;

            ps_entropy->i4_error_code = isvce_generate_svc_nalu_extension(
                ps_bitstrm, aps_svc_nalu_ext[1], NAL_CODED_SLICE_EXTENSION);

            ps_entropy->i4_error_code = isvce_generate_slice_header_svc(
                ps_bitstrm, ps_pps, aps_svc_nalu_ext[1], ps_svc_slice_hdr, ps_subset_sps);

            RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);
        }
        else
        {
            /* generate slice header */
            ps_entropy->i4_error_code = isvce_generate_slice_header(
                ps_bitstrm, ps_slice_hdr, ps_pps, ps_sps, aps_svc_nalu_ext[0]->u1_idr_flag);

            RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);
        }

        /* once start of frame / slice is done, you can reset it */
        /* it is the responsibility of the caller to set this flag */
        ps_entropy->i4_sof = 0;

        if(CABAC == ps_entropy->u1_entropy_coding_mode_flag)
        {
            BITSTREAM_BYTE_ALIGN(ps_bitstrm);
            BITSTREAM_FLUSH(ps_bitstrm, ps_entropy->i4_error_code);
            isvce_init_cabac_ctxt(ps_entropy, ps_slice_hdr);
        }

#if ENABLE_RE_ENC_AS_SKIP
        ps_bitstrm_after_slice_hdr[0] = ps_bitstrm[0];
#endif
    }

    /* begin entropy coding for the mb set */
    while(u4_mb_idx < u4_mb_end_idx)
    {
        mb_bits_info_t s_mb_bits = {
            .i8_header_bits = -((WORD64) ps_entropy->u4_header_bits[i4_slice_type == PSLICE]),
            .i8_texture_bits = -((WORD64) ps_entropy->u4_residue_bits[i4_slice_type == PSLICE])};

        /* init ptrs/indices */
        if(ps_entropy->i4_mb_x == i4_wd_mbs)
        {
            ps_entropy->i4_mb_y++;
            ps_entropy->i4_mb_x = 0;

            /* packed mb coeff data */
            ps_entropy->pv_mb_coeff_data = ((UWORD8 *) ps_entropy->pv_pic_mb_coeff_data) +
                                           ps_entropy->i4_mb_y * ps_codec->u4_size_coeff_data;

            /* packed mb header data */
            ps_entropy->pv_mb_header_data = ((UWORD8 *) ps_entropy->pv_pic_mb_header_data) +
                                            ps_entropy->i4_mb_y * ps_codec->u4_size_header_data;

            /* proc map */
            pu1_proc_map = ps_proc->pu1_proc_map + ps_entropy->i4_mb_y * i4_wd_mbs;

            /* entropy map */
            pu1_entropy_map_curr = ps_entropy->pu1_entropy_map + ps_entropy->i4_mb_y * i4_wd_mbs;
        }

        DEBUG("\nmb indices x, y %d, %d", ps_entropy->i4_mb_x, ps_entropy->i4_mb_y);
        ENTROPY_TRACE("mb index x %d", ps_entropy->i4_mb_x);
        ENTROPY_TRACE("mb index y %d", ps_entropy->i4_mb_y);

        /* wait until the curr mb is core coded */
        /* The wait for curr mb to be core coded is essential when entropy is
         * launched as a separate job
         */
        while(1)
        {
            volatile UWORD8 *pu1_buf1;
            WORD32 idx = ps_entropy->i4_mb_x;

            pu1_buf1 = pu1_proc_map + idx;
            if(*pu1_buf1) break;
            ithread_yield();
        }

        /* write mb layer */
        ps_entropy->i4_error_code =
            ps_codec->pf_write_mb_syntax_layer[ps_entropy->u1_entropy_coding_mode_flag]
                                              [i4_slice_type](ps_entropy);
        RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);

        /* Starting bitstream offset for header in bits */
        bitstream_start_offset = isvce_get_num_bits(ps_bitstrm);

        /* set entropy map */
        pu1_entropy_map_curr[ps_entropy->i4_mb_x] = 1;
        ASSERT(ps_entropy->i4_mb_x < i4_wd_mbs);

        u4_mb_idx++;
        ps_entropy->i4_mb_x++;
        /* check for eof */
        if(CABAC == ps_entropy->u1_entropy_coding_mode_flag)
        {
            if(ps_entropy->i4_mb_x < i4_wd_mbs)
            {
                isvce_cabac_encode_terminate(ps_cabac_ctxt, 0);
            }
        }

        if(ps_entropy->i4_mb_x == i4_wd_mbs)
        {
            /* if slices are enabled */
            if(ps_codec->s_cfg.e_slice_mode == IVE_SLICE_MODE_BLOCKS)
            {
                /* current slice index */
                WORD32 i4_curr_slice_idx = ps_entropy->i4_cur_slice_idx;

                /* slice map */
                UWORD8 *pu1_slice_idx = ps_entropy->pu1_slice_idx;

                /* No need to open a slice at end of frame. The current slice can be
                 * closed at the time of signaling eof flag.
                 */
                if((u4_mb_idx != u4_mb_cnt) && (i4_curr_slice_idx != pu1_slice_idx[u4_mb_idx]))
                {
                    if(CAVLC == ps_entropy->u1_entropy_coding_mode_flag)
                    { /* mb skip run */
                        if((i4_slice_type != ISLICE) && *ps_entropy->pi4_mb_skip_run)
                        {
                            if(*ps_entropy->pi4_mb_skip_run)
                            {
                                PUT_BITS_UEV(ps_bitstrm, *ps_entropy->pi4_mb_skip_run,
                                             ps_entropy->i4_error_code, "mb skip run");
                                *ps_entropy->pi4_mb_skip_run = 0;
                                RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);
                            }
                        }
                        /* put rbsp trailing bits for the previous slice */
                        ps_entropy->i4_error_code = ih264e_put_rbsp_trailing_bits(ps_bitstrm);
                        RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);
                    }
                    else
                    {
                        isvce_cabac_encode_terminate(ps_cabac_ctxt, 1);
                    }

                    /* update slice header pointer */
                    i4_curr_slice_idx = pu1_slice_idx[u4_mb_idx];
                    ps_entropy->i4_cur_slice_idx = i4_curr_slice_idx;
                    ps_slice_hdr =
                        ps_entropy->ps_slice_hdr_base + (i4_curr_slice_idx % SVC_MAX_SLICE_HDR_CNT);

                    ps_entropy->u1_spatial_layer_id = ps_proc->u1_spatial_layer_id;

                    /* populate slice header */
                    ps_entropy->i4_mb_start_add = u4_mb_idx;

                    /* generate slice header */
                    if(ps_proc->u1_spatial_layer_id > 0)
                    {
                        ps_entropy->i4_error_code =
                            isvce_generate_slice_header_svc(ps_bitstrm, ps_pps, aps_svc_nalu_ext[1],
                                                            ps_svc_slice_hdr, ps_subset_sps);

                        RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);

                        ps_slice_hdr = &ps_svc_slice_hdr->s_slice_header;
                    }
                    else
                    {
                        ps_entropy->i4_error_code =
                            isvce_populate_slice_header(ps_proc, ps_slice_hdr, ps_pps, ps_sps,
                                                        aps_svc_nalu_ext[0]->u1_idr_flag);

                        RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);

                        ps_entropy->i4_error_code =
                            isvce_generate_slice_header(ps_bitstrm, ps_slice_hdr, ps_pps, ps_sps,
                                                        aps_svc_nalu_ext[0]->u1_idr_flag);

                        RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);
                    }

                    if(CABAC == ps_entropy->u1_entropy_coding_mode_flag)
                    {
                        BITSTREAM_BYTE_ALIGN(ps_bitstrm);
                        BITSTREAM_FLUSH(ps_bitstrm, ps_entropy->i4_error_code);
                        isvce_init_cabac_ctxt(ps_entropy, ps_slice_hdr);
                    }
                }
                else
                {
                    if(CABAC == ps_entropy->u1_entropy_coding_mode_flag && u4_mb_idx != u4_mb_cnt)
                    {
                        isvce_cabac_encode_terminate(ps_cabac_ctxt, 0);
                    }
                }
            }
        }

        /* Ending bitstream offset for header in bits */
        bitstream_end_offset = isvce_get_num_bits(ps_bitstrm);
        ps_entropy->u4_header_bits[i4_slice_type == PSLICE] +=
            bitstream_end_offset - bitstream_start_offset;

        {
            svc_sub_pic_rc_ctxt_t *ps_sub_pic_rc_ctxt = ps_proc->ps_sub_pic_rc_ctxt;
            svc_sub_pic_rc_entropy_variables_t *ps_sub_pic_rc_variables =
                &ps_sub_pic_rc_ctxt->s_sub_pic_rc_entropy_variables;

            s_mb_bits.i8_header_bits += ps_entropy->u4_header_bits[i4_slice_type == PSLICE];
            s_mb_bits.i8_texture_bits += ps_entropy->u4_residue_bits[i4_slice_type == PSLICE];

            ps_sub_pic_rc_variables->s_mb_bits = s_mb_bits;
            ps_sub_pic_rc_variables->u1_spatial_layer_id = ps_proc->u1_spatial_layer_id;
            ps_sub_pic_rc_variables->s_mb_pos.i4_abscissa = ps_entropy->i4_mb_x - 1;
            ps_sub_pic_rc_variables->s_mb_pos.i4_ordinate = ps_entropy->i4_mb_y;

            isvce_sub_pic_rc_get_entropy_data(ps_proc->ps_sub_pic_rc_ctxt);
        }
    }

    /* check for eof */
    if(u4_mb_idx == u4_mb_cnt)
    {
        /* set end of frame flag */
        ps_entropy->i4_eof = 1;
    }
    else
    {
        if(CABAC == ps_entropy->u1_entropy_coding_mode_flag &&
           ps_codec->s_cfg.e_slice_mode != IVE_SLICE_MODE_BLOCKS)
        {
            isvce_cabac_encode_terminate(ps_cabac_ctxt, 0);
        }
    }

    if(ps_entropy->i4_eof)
    {
        if(CAVLC == ps_entropy->u1_entropy_coding_mode_flag)
        {
            /* mb skip run */
            if((i4_slice_type != ISLICE) && *ps_entropy->pi4_mb_skip_run)
            {
                if(*ps_entropy->pi4_mb_skip_run)
                {
                    PUT_BITS_UEV(ps_bitstrm, *ps_entropy->pi4_mb_skip_run,
                                 ps_entropy->i4_error_code, "mb skip run");
                    *ps_entropy->pi4_mb_skip_run = 0;
                    RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);
                }
            }
            /* put rbsp trailing bits */
            ps_entropy->i4_error_code = ih264e_put_rbsp_trailing_bits(ps_bitstrm);
            RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);
        }
        else
        {
            isvce_cabac_encode_terminate(ps_cabac_ctxt, 1);
        }

        /* update current frame stats to rc library */
        /* number of bytes to stuff */
        {
            WORD32 i4_stuff_bytes;

            /* update */
            i4_stuff_bytes = isvce_update_rc_post_enc(ps_codec, ctxt_sel, (ps_codec->i4_poc == 0));

            if(ps_proc->u1_spatial_layer_id == (ps_proc->s_svc_params.u1_num_spatial_layers - 1))
            {
                /* cbr rc - house keeping */
                if(ps_codec->s_rate_control.post_encode_skip[ctxt_sel])
                {
                    ps_entropy->ps_bitstrm->u4_strm_buf_offset = 0;
                }
                else if(i4_stuff_bytes > 0)
                {
                    /* add filler nal units */
                    ps_entropy->i4_error_code =
                        ih264e_add_filler_nal_unit(ps_bitstrm, i4_stuff_bytes);
                    RETURN_ENTROPY_IF_ERROR(ps_codec, ps_entropy, ctxt_sel);
                }
            }
        }

        /*
         *Frame number is to be incremented only if the current frame is a
         * reference frame. After each successful frame encode, we increment
         * frame number by 1
         */
        if(!ps_codec->s_rate_control.post_encode_skip[ctxt_sel] && ps_codec->u4_is_curr_frm_ref &&
           (ps_proc->u1_spatial_layer_id == ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers - 1))
        {
            ps_codec->i4_frame_num++;
        }

        /********************************************************************/
        /*      signal the output                                           */
        /********************************************************************/
        ps_codec->as_out_buf[ctxt_sel].as_bits_buf[ps_entropy->u1_spatial_layer_id].u4_bytes =
            ps_bitstrm->u4_strm_buf_offset;

        ps_slice_nalu_info = isvce_get_next_nalu_info_buf(ps_nalu_descriptor);
        ps_slice_nalu_info->i8_num_bits += isvce_get_num_bits(ps_bitstrm);
        isvce_update_nalu_count(ps_nalu_descriptor);

        DEBUG("entropy status %x", ps_entropy->i4_error_code);
        ps_entropy->i4_eof = 0;
    }

    /* Dont execute any further instructions until store synchronization took
     * place */
    DATA_SYNC();

    /* allow threads to dequeue entropy jobs */
    ps_codec->au4_entropy_thread_active[ctxt_sel] = 0;

    return ps_entropy->i4_error_code;
}

/**
*******************************************************************************
*
* @brief Packs header information of a mb in to a buffer
*
* @par Description:
*  After the deciding the mode info of a macroblock, the syntax elements
*  associated with the mb are packed and stored. The entropy thread unpacks
*  this buffer and generates the end bit stream.
*
* @param[in] ps_proc
*  Pointer to the current process context
*
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
IH264E_ERROR_T isvce_pack_header_data(isvce_process_ctxt_t *ps_proc)
{
    /* curr mb type */
    UWORD32 u4_mb_type = ps_proc->ps_mb_info->u2_mb_type;

    /* pack mb syntax layer of curr mb (used for entropy coding) */
    if(u4_mb_type == I4x4)
    {
        /* pointer to mb header storage space */
        UWORD8 *pu1_ptr = ps_proc->pv_mb_header_data;
        isvce_mb_hdr_i4x4_t *ps_mb_hdr = (isvce_mb_hdr_i4x4_t *) ps_proc->pv_mb_header_data;

        /* temp var */
        WORD32 i4, byte;

        /* mb type plus mode */
        ps_mb_hdr->common.u1_mb_type_mode = (ps_proc->u1_c_i8_mode << 6) + u4_mb_type;

        /* cbp */
        ps_mb_hdr->common.u1_cbp = ps_proc->u4_cbp;

        /* mb qp delta */
        ps_mb_hdr->common.u1_mb_qp = ps_proc->u1_mb_qp;

        ps_mb_hdr->common.u1_residual_prediction_flag =
            ps_proc->ps_mb_info->u1_residual_prediction_flag;

        ps_mb_hdr->common.u1_base_mode_flag = ps_proc->ps_mb_info->u1_base_mode_flag;

        /* sub mb modes */
        for(i4 = 0; i4 < 16; i4++)
        {
            byte = 0;

            if(ps_proc->au1_predicted_intra_luma_mb_4x4_modes[i4] ==
               ps_proc->au1_intra_luma_mb_4x4_modes[i4])
            {
                byte |= 1;
            }
            else
            {
                if(ps_proc->au1_intra_luma_mb_4x4_modes[i4] <
                   ps_proc->au1_predicted_intra_luma_mb_4x4_modes[i4])
                {
                    byte |= (ps_proc->au1_intra_luma_mb_4x4_modes[i4] << 1);
                }
                else
                {
                    byte |= (ps_proc->au1_intra_luma_mb_4x4_modes[i4] - 1) << 1;
                }
            }

            i4++;

            if(ps_proc->au1_predicted_intra_luma_mb_4x4_modes[i4] ==
               ps_proc->au1_intra_luma_mb_4x4_modes[i4])
            {
                byte |= 16;
            }
            else
            {
                if(ps_proc->au1_intra_luma_mb_4x4_modes[i4] <
                   ps_proc->au1_predicted_intra_luma_mb_4x4_modes[i4])
                {
                    byte |= (ps_proc->au1_intra_luma_mb_4x4_modes[i4] << 5);
                }
                else
                {
                    byte |= (ps_proc->au1_intra_luma_mb_4x4_modes[i4] - 1) << 5;
                }
            }

            ps_mb_hdr->au1_sub_blk_modes[i4 >> 1] = byte;
        }

        /* end of mb layer */
        pu1_ptr += sizeof(isvce_mb_hdr_i4x4_t);
        ps_proc->pv_mb_header_data = pu1_ptr;
    }
    else if(u4_mb_type == I16x16)
    {
        /* pointer to mb header storage space */
        UWORD8 *pu1_ptr = ps_proc->pv_mb_header_data;
        isvce_mb_hdr_i16x16_t *ps_mb_hdr = (isvce_mb_hdr_i16x16_t *) ps_proc->pv_mb_header_data;

        /* mb type plus mode */
        ps_mb_hdr->common.u1_mb_type_mode =
            (ps_proc->u1_c_i8_mode << 6) + (ps_proc->u1_l_i16_mode << 4) + u4_mb_type;

        /* cbp */
        ps_mb_hdr->common.u1_cbp = ps_proc->u4_cbp;

        /* mb qp delta */
        ps_mb_hdr->common.u1_mb_qp = ps_proc->u1_mb_qp;

        ps_mb_hdr->common.u1_residual_prediction_flag =
            ps_proc->ps_mb_info->u1_residual_prediction_flag;

        ps_mb_hdr->common.u1_base_mode_flag = ps_proc->ps_mb_info->u1_base_mode_flag;

        /* end of mb layer */
        pu1_ptr += sizeof(isvce_mb_hdr_i16x16_t);
        ps_proc->pv_mb_header_data = pu1_ptr;
    }
    else if(u4_mb_type == P16x16)
    {
        /* pointer to mb header storage space */
        UWORD8 *pu1_ptr = ps_proc->pv_mb_header_data;
        isvce_mb_hdr_p16x16_t *ps_mb_hdr = (isvce_mb_hdr_p16x16_t *) ps_proc->pv_mb_header_data;

        /* mb type */
        ps_mb_hdr->common.u1_mb_type_mode = u4_mb_type;

        /* cbp */
        ps_mb_hdr->common.u1_cbp = ps_proc->u4_cbp;

        /* mb qp delta */
        ps_mb_hdr->common.u1_mb_qp = ps_proc->u1_mb_qp;

        ps_mb_hdr->common.u1_residual_prediction_flag =
            ps_proc->ps_mb_info->u1_residual_prediction_flag;

        ps_mb_hdr->common.u1_base_mode_flag = ps_proc->ps_mb_info->u1_base_mode_flag;

        ps_mb_hdr->u1_mvp_idx = ps_proc->ps_mb_info->as_pu->au1_mvp_idx[L0];

        if(0 == ps_proc->ps_mb_info->as_pu->au1_mvp_idx[L0])
        {
            ps_mb_hdr->ai2_mvd[0] = ps_proc->ps_mb_info->as_pu->as_me_info[L0].s_mv.i2_mvx -
                                    ps_proc->ps_pred_mv[L0].s_mv.i2_mvx;
            ps_mb_hdr->ai2_mvd[1] = ps_proc->ps_mb_info->as_pu->as_me_info[L0].s_mv.i2_mvy -
                                    ps_proc->ps_pred_mv[L0].s_mv.i2_mvy;
        }
        else
        {
            ps_mb_hdr->ai2_mvd[0] = ps_proc->ps_mb_info->as_pu->as_me_info[L0].s_mv.i2_mvx -
                                    ps_proc->ps_ilp_mv->as_mv[0][L0].s_mv.i2_mvx;
            ps_mb_hdr->ai2_mvd[1] = ps_proc->ps_mb_info->as_pu->as_me_info[L0].s_mv.i2_mvy -
                                    ps_proc->ps_ilp_mv->as_mv[0][L0].s_mv.i2_mvy;
        }

        /* end of mb layer */
        pu1_ptr += sizeof(isvce_mb_hdr_p16x16_t);
        ps_proc->pv_mb_header_data = pu1_ptr;
    }
    else if(u4_mb_type == PSKIP)
    {
        /* pointer to mb header storage space */
        UWORD8 *pu1_ptr = ps_proc->pv_mb_header_data;
        isvce_mb_hdr_pskip_t *ps_mb_hdr = (isvce_mb_hdr_pskip_t *) ps_proc->pv_mb_header_data;

        /* mb type */
        ps_mb_hdr->common.u1_mb_type_mode = u4_mb_type;

        ps_mb_hdr->common.u1_residual_prediction_flag =
            ps_proc->ps_mb_info->u1_residual_prediction_flag;

        ps_mb_hdr->common.u1_base_mode_flag = ps_proc->ps_mb_info->u1_base_mode_flag;

        /* end of mb layer */
        pu1_ptr += sizeof(isvce_mb_hdr_pskip_t);
        ps_proc->pv_mb_header_data = pu1_ptr;
    }
    else if(u4_mb_type == B16x16)
    {
        WORD32 i;

        /* pointer to mb header storage space */
        UWORD8 *pu1_ptr = ps_proc->pv_mb_header_data;
        isvce_mb_hdr_b16x16_t *ps_mb_hdr = (isvce_mb_hdr_b16x16_t *) ps_proc->pv_mb_header_data;

        UWORD32 u4_pred_mode = ps_proc->ps_mb_info->as_pu->u1_pred_mode;

        /* mb type plus mode */
        ps_mb_hdr->common.u1_mb_type_mode = (u4_pred_mode << 4) + u4_mb_type;

        /* cbp */
        ps_mb_hdr->common.u1_cbp = ps_proc->u4_cbp;

        /* mb qp delta */
        ps_mb_hdr->common.u1_mb_qp = ps_proc->u1_mb_qp;

        ps_mb_hdr->common.u1_residual_prediction_flag =
            ps_proc->ps_mb_info->u1_residual_prediction_flag;

        ps_mb_hdr->common.u1_base_mode_flag = ps_proc->ps_mb_info->u1_base_mode_flag;

        for(i = 0; i < NUM_PRED_DIRS; i++)
        {
            PRED_MODE_T e_pred_mode = (PRED_MODE_T) i;
            PRED_MODE_T e_cmpl_pred_mode = (e_pred_mode == L0) ? L1 : L0;

            if(u4_pred_mode != e_pred_mode)
            {
                ps_mb_hdr->au1_mvp_idx[e_cmpl_pred_mode] =
                    ps_proc->ps_mb_info->as_pu->au1_mvp_idx[e_cmpl_pred_mode];

                if(0 == ps_proc->ps_mb_info->as_pu->au1_mvp_idx[e_cmpl_pred_mode])
                {
                    ps_mb_hdr->ai2_mvd[e_cmpl_pred_mode][0] =
                        ps_proc->ps_mb_info->as_pu->as_me_info[e_cmpl_pred_mode].s_mv.i2_mvx -
                        ps_proc->ps_pred_mv[e_cmpl_pred_mode].s_mv.i2_mvx;
                    ps_mb_hdr->ai2_mvd[e_cmpl_pred_mode][1] =
                        ps_proc->ps_mb_info->as_pu->as_me_info[e_cmpl_pred_mode].s_mv.i2_mvy -
                        ps_proc->ps_pred_mv[e_cmpl_pred_mode].s_mv.i2_mvy;
                }
                else
                {
                    ps_mb_hdr->ai2_mvd[e_cmpl_pred_mode][0] =
                        ps_proc->ps_mb_info->as_pu->as_me_info[e_cmpl_pred_mode].s_mv.i2_mvx -
                        ps_proc->ps_ilp_mv->as_mv[0][e_cmpl_pred_mode].s_mv.i2_mvx;
                    ps_mb_hdr->ai2_mvd[e_cmpl_pred_mode][1] =
                        ps_proc->ps_mb_info->as_pu->as_me_info[e_cmpl_pred_mode].s_mv.i2_mvy -
                        ps_proc->ps_ilp_mv->as_mv[0][e_cmpl_pred_mode].s_mv.i2_mvy;
                }
            }
        }

        /* end of mb layer */
        pu1_ptr += sizeof(isvce_mb_hdr_b16x16_t);
        ps_proc->pv_mb_header_data = pu1_ptr;
    }
    else if(u4_mb_type == BDIRECT)
    {
        /* pointer to mb header storage space */
        UWORD8 *pu1_ptr = ps_proc->pv_mb_header_data;
        isvce_mb_hdr_bdirect_t *ps_mb_hdr = (isvce_mb_hdr_bdirect_t *) ps_proc->pv_mb_header_data;

        /* mb type plus mode */
        ps_mb_hdr->common.u1_mb_type_mode = u4_mb_type;

        /* cbp */
        ps_mb_hdr->common.u1_cbp = ps_proc->u4_cbp;

        /* mb qp delta */
        ps_mb_hdr->common.u1_mb_qp = ps_proc->u1_mb_qp;

        ps_mb_hdr->common.u1_residual_prediction_flag =
            ps_proc->ps_mb_info->u1_residual_prediction_flag;

        ps_mb_hdr->common.u1_base_mode_flag = ps_proc->ps_mb_info->u1_base_mode_flag;

        /* end of mb layer */
        pu1_ptr += sizeof(isvce_mb_hdr_bdirect_t);
        ps_proc->pv_mb_header_data = pu1_ptr;
    }
    else if(u4_mb_type == BSKIP)
    {
        UWORD32 u4_pred_mode = ps_proc->ps_mb_info->as_pu->u1_pred_mode;

        /* pointer to mb header storage space */
        UWORD8 *pu1_ptr = ps_proc->pv_mb_header_data;
        isvce_mb_hdr_bskip_t *ps_mb_hdr = (isvce_mb_hdr_bskip_t *) ps_proc->pv_mb_header_data;

        /* mb type plus mode */
        ps_mb_hdr->common.u1_mb_type_mode = (u4_pred_mode << 4) + u4_mb_type;

        ps_mb_hdr->common.u1_residual_prediction_flag =
            ps_proc->ps_mb_info->u1_residual_prediction_flag;

        ps_mb_hdr->common.u1_base_mode_flag = ps_proc->ps_mb_info->u1_base_mode_flag;

        /* end of mb layer */
        pu1_ptr += sizeof(isvce_mb_hdr_bskip_t);
        ps_proc->pv_mb_header_data = pu1_ptr;
    }
    else if(u4_mb_type == BASE_MODE)
    {
        isvce_mb_hdr_base_mode_t *ps_mb_hdr =
            (isvce_mb_hdr_base_mode_t *) ps_proc->pv_mb_header_data;

        UWORD8 *pu1_ptr = ps_proc->pv_mb_header_data;

        ASSERT(ps_proc->ps_mb_info->u1_base_mode_flag == 1);

        ps_mb_hdr->common.u1_mb_type_mode = u4_mb_type;

        ps_mb_hdr->common.u1_cbp = ps_proc->u4_cbp;

        ps_mb_hdr->common.u1_mb_qp = ps_proc->u1_mb_qp;

        ps_mb_hdr->common.u1_residual_prediction_flag =
            ps_proc->ps_mb_info->u1_residual_prediction_flag;

        ps_mb_hdr->common.u1_base_mode_flag = ps_proc->ps_mb_info->u1_base_mode_flag;

        pu1_ptr += sizeof(isvce_mb_hdr_base_mode_t);
        ps_proc->pv_mb_header_data = pu1_ptr;
    }

    return IH264E_SUCCESS;
}

/**
*******************************************************************************
*
* @brief   update process context after encoding an mb. This involves preserving
* the current mb information for later use, initialize the proc ctxt elements to
* encode next mb.
*
* @par Description:
*  This function performs house keeping tasks after encoding an mb.
*  After encoding an mb, various elements of the process context needs to be
*  updated to encode the next mb. For instance, the source, recon and reference
*  pointers, mb indices have to be adjusted to the next mb. The slice index of
*  the current mb needs to be updated. If mb qp modulation is enabled, then if
*  the qp changes the quant param structure needs to be updated. Also to
*encoding the next mb, the current mb info is used as part of mode prediction or
*mv prediction. Hence the current mb info has to preserved at top/top left/left
*  locations.
*
* @param[in] ps_proc
*  Pointer to the current process context
*
* @returns none
*
* @remarks none
*
*******************************************************************************
*/
WORD32 isvce_update_proc_ctxt(isvce_process_ctxt_t *ps_proc)
{
    /* error status */
    WORD32 error_status = IH264_SUCCESS;

    /* codec context */
    isvce_codec_t *ps_codec = ps_proc->ps_codec;
    isa_dependent_fxns_t *ps_isa_dependent_fxns = &ps_codec->s_isa_dependent_fxns;
    mem_fxns_t *ps_mem_fxns = &ps_isa_dependent_fxns->s_mem_fxns;

    /* curr mb indices */
    WORD32 i4_mb_x = ps_proc->i4_mb_x;
    WORD32 i4_mb_y = ps_proc->i4_mb_y;

    /* mb syntax elements of neighbors */
    isvce_mb_info_t *ps_left_syn = ps_proc->s_nbr_info.ps_left_mb_info;
    isvce_mb_info_t *ps_top_syn =
        ps_proc->s_nbr_info_base.ps_layer_nbr_info[ps_proc->u1_spatial_layer_id]
            .ps_top_row_mb_info +
        i4_mb_x + i4_mb_y * ps_proc->i4_wd_mbs;

    /* curr mb type */
    UWORD32 u4_mb_type = ps_proc->ps_mb_info->u2_mb_type;

    /* curr mb type */
    UWORD32 u4_is_intra = ps_proc->ps_mb_info->u1_is_intra;

    /* width in mbs */
    WORD32 i4_wd_mbs = ps_proc->i4_wd_mbs;

    /*height in mbs*/
    WORD32 i4_ht_mbs = ps_proc->i4_ht_mbs;

    /* proc map */
    UWORD8 *pu1_proc_map = ps_proc->pu1_proc_map + (i4_mb_y * i4_wd_mbs);

    /* deblk context */
    isvce_deblk_ctxt_t *ps_deblk = &ps_proc->s_deblk_ctxt;

    /* deblk bs context */
    isvce_bs_ctxt_t *ps_bs = &(ps_deblk->s_bs_ctxt);

    /* sub mb modes */
    UWORD8 *pu1_top_mb_intra_modes =
        (ps_proc->s_nbr_info_base.ps_layer_nbr_info[ps_proc->u1_spatial_layer_id]
             .ps_top_mb_intra_modes +
         i4_mb_x + i4_mb_y * ps_proc->i4_wd_mbs)
            ->au1_intra_modes;

    /*************************************************************/
    /* During MV prediction, when top right mb is not available, */
    /* top left mb info. is used for prediction. Hence the curr  */
    /* top, which will be top left for the next mb needs to be   */
    /* preserved before updating it with curr mb info.           */
    /*************************************************************/

    /*************************************************/
    /* update top and left with curr mb info results */
    /*************************************************/
    ps_left_syn[0] = ps_top_syn[0] = ps_proc->ps_mb_info[0];
    ps_left_syn->u2_mb_type = ps_top_syn->u2_mb_type = u4_mb_type;
    ps_left_syn->i4_mb_distortion = ps_top_syn->i4_mb_distortion = ps_proc->i4_mb_distortion;

    if(u4_is_intra)
    {
        /* mb / sub mb modes */
        if(I16x16 == u4_mb_type)
        {
            pu1_top_mb_intra_modes[0] =
                ps_proc->s_nbr_info.ps_left_mb_intra_modes->au1_intra_modes[0] =
                    ps_proc->u1_l_i16_mode;
        }
        else if(I4x4 == u4_mb_type)
        {
            ps_mem_fxns->pf_mem_cpy_mul8(
                ps_proc->s_nbr_info.ps_left_mb_intra_modes->au1_intra_modes,
                ps_proc->au1_intra_luma_mb_4x4_modes, 16);
            ps_mem_fxns->pf_mem_cpy_mul8(pu1_top_mb_intra_modes,
                                         ps_proc->au1_intra_luma_mb_4x4_modes, 16);
        }
        else if(I8x8 == u4_mb_type)
        {
            memcpy(ps_proc->s_nbr_info.ps_left_mb_intra_modes->au1_intra_modes,
                   ps_proc->au1_intra_luma_mb_8x8_modes, 4);
            memcpy(pu1_top_mb_intra_modes, ps_proc->au1_intra_luma_mb_8x8_modes, 4);
        }

        *ps_proc->pu4_mb_pu_cnt = 1;
    }

    /*
     * Mark that the MB has been coded intra
     * So that future AIRs can skip it
     */
    ps_proc->pu1_is_intra_coded[i4_mb_x + (i4_mb_y * i4_wd_mbs)] = u4_is_intra;

    /**************************************************/
    /* pack mb header info. for entropy coding        */
    /**************************************************/
    isvce_pack_header_data(ps_proc);

    /*
     * We need to sync the cache to make sure that the nmv content of proc
     * is updated to cache properly
     */
    DATA_SYNC();

    /* Just before finishing the row, enqueue the job in to entropy queue.
     * The master thread depending on its convenience shall dequeue it and
     * performs entropy.
     *
     * WARN !! Placing this block post proc map update can cause queuing of
     * entropy jobs in out of order.
     */
    if(i4_mb_x == i4_wd_mbs - 1)
    {
        /* job structures */
        job_t s_job;

        /* job class */
        s_job.i4_cmd = CMD_ENTROPY;

        /* number of mbs to be processed in the current job */
        s_job.i2_mb_cnt = ps_proc->i4_wd_mbs;

        /* job start index x */
        s_job.i2_mb_x = 0;

        /* job start index y */
        s_job.i2_mb_y = ps_proc->i4_mb_y;

        /* queue the job */
        error_status = ih264_list_queue(ps_proc->pv_entropy_jobq, &s_job, 1);

        if(error_status != IH264_SUCCESS)
        {
            return error_status;
        }

        if(ps_proc->i4_mb_y == (i4_ht_mbs - 1))
        {
            ih264_list_terminate(ps_codec->pv_entropy_jobq);
        }
    }

    /* update proc map */
    pu1_proc_map[i4_mb_x] = 1;
    ASSERT(i4_mb_x < i4_wd_mbs);

    /**************************************************/
    /* update proc ctxt elements for encoding next mb */
    /**************************************************/
    /* update indices */
    i4_mb_x++;
    ps_proc->i4_mb_x = i4_mb_x;

    if(ps_proc->i4_mb_x == i4_wd_mbs)
    {
        ps_proc->i4_mb_y++;
        ps_proc->i4_mb_x = 0;
    }

    /* update slice index */
    ps_proc->i4_cur_slice_idx =
        ps_proc->pu1_slice_idx[ps_proc->i4_mb_y * i4_wd_mbs + ps_proc->i4_mb_x];

    /* update buffers pointers */
    ps_proc->s_src_buf_props.as_component_bufs[0].pv_data =
        ((UWORD8 *) ps_proc->s_src_buf_props.as_component_bufs[0].pv_data) + MB_SIZE;
    ps_proc->s_rec_buf_props.as_component_bufs[0].pv_data =
        ((UWORD8 *) ps_proc->s_rec_buf_props.as_component_bufs[0].pv_data) + MB_SIZE;
    ps_proc->as_ref_buf_props[0].as_component_bufs[0].pv_data =
        ((UWORD8 *) ps_proc->as_ref_buf_props[0].as_component_bufs[0].pv_data) + MB_SIZE;
    ps_proc->as_ref_buf_props[1].as_component_bufs[0].pv_data =
        ((UWORD8 *) ps_proc->as_ref_buf_props[1].as_component_bufs[0].pv_data) + MB_SIZE;

    /*
     * Note: Although chroma mb size is 8, as the chroma buffers are
     * interleaved, the stride per MB is MB_SIZE
     */
    ps_proc->s_src_buf_props.as_component_bufs[1].pv_data =
        ((UWORD8 *) ps_proc->s_src_buf_props.as_component_bufs[1].pv_data) + MB_SIZE;
    ps_proc->s_rec_buf_props.as_component_bufs[1].pv_data =
        ((UWORD8 *) ps_proc->s_rec_buf_props.as_component_bufs[1].pv_data) + MB_SIZE;
    ps_proc->as_ref_buf_props[0].as_component_bufs[1].pv_data =
        ((UWORD8 *) ps_proc->as_ref_buf_props[0].as_component_bufs[1].pv_data) + MB_SIZE;
    ps_proc->as_ref_buf_props[1].as_component_bufs[1].pv_data =
        ((UWORD8 *) ps_proc->as_ref_buf_props[1].as_component_bufs[1].pv_data) + MB_SIZE;

    /* Reset cost, distortion params */
    ps_proc->i4_mb_cost = INT_MAX;
    ps_proc->i4_mb_distortion = SHRT_MAX;

    ps_proc->ps_mb_info++;
    ps_proc->pu4_mb_pu_cnt++;

    /* Update colocated pu */
    if(ps_proc->i4_slice_type == BSLICE)
    {
        ps_proc->ps_col_mb++;
    }

    if(ps_proc->u4_disable_deblock_level != 1)
    {
        ps_bs->i4_mb_x = ps_proc->i4_mb_x;
        ps_bs->i4_mb_y = ps_proc->i4_mb_y;

#ifndef N_MB_ENABLE /* For N MB processing update take place inside deblocking \
                       function */
        ASSERT(0);
        ps_deblk->i4_mb_x++;

        ((UWORD8 *) ps_deblk->s_rec_pic_buf_props.as_component_bufs[0].pv_data) += MB_SIZE;
        /*
         * Note: Although chroma mb size is 8, as the chroma buffers are
         * interleaved, the stride per MB is MB_SIZE
         */
        ((UWORD8 *) ps_deblk->s_rec_pic_buf_props.as_component_bufs[1].pv_data) += MB_SIZE;
#endif
    }

    return error_status;
}

/**
*******************************************************************************
*
* @brief This function performs luma & chroma padding
*
* @par Description:
*
* @param[in] ps_proc
*  Process context corresponding to the job
*
* @param[in] pu1_curr_pic_luma
*  Pointer to luma buffer
*
* @param[in] pu1_curr_pic_chroma
*  Pointer to chroma buffer
*
* @param[in] i4_mb_x
*  mb index x
*
* @param[in] i4_mb_y
*  mb index y
*
*  @param[in] i4_pad_ht
*  number of rows to be padded
*
* @returns  error status
*
* @remarks none
*
*******************************************************************************
*/
IH264E_ERROR_T isvce_pad_recon_buffer(isvce_process_ctxt_t *ps_proc, UWORD8 *pu1_curr_pic_luma,
                                      WORD32 i4_luma_stride, UWORD8 *pu1_curr_pic_chroma,
                                      WORD32 i4_chroma_stride, WORD32 i4_mb_x, WORD32 i4_mb_y,
                                      WORD32 i4_pad_ht)
{
    /* codec context */
    isvce_codec_t *ps_codec = ps_proc->ps_codec;

    if(i4_mb_x == 0)
    {
        /* padding left luma */
        ps_codec->pf_pad_left_luma(pu1_curr_pic_luma, i4_luma_stride, i4_pad_ht, PAD_LEFT);

        /* padding left chroma */
        ps_codec->pf_pad_left_chroma(pu1_curr_pic_chroma, i4_chroma_stride, i4_pad_ht >> 1,
                                     PAD_LEFT);
    }
    if(i4_mb_x == ps_proc->i4_wd_mbs - 1)
    {
        /* padding right luma */
        ps_codec->pf_pad_right_luma(pu1_curr_pic_luma + MB_SIZE, i4_luma_stride, i4_pad_ht,
                                    PAD_RIGHT);

        /* padding right chroma */
        ps_codec->pf_pad_right_chroma(pu1_curr_pic_chroma + MB_SIZE, i4_chroma_stride,
                                      i4_pad_ht >> 1, PAD_RIGHT);

        if(i4_mb_y == ps_proc->i4_ht_mbs - 1)
        {
            UWORD8 *pu1_rec_luma =
                pu1_curr_pic_luma + MB_SIZE + PAD_RIGHT + ((i4_pad_ht - 1) * i4_luma_stride);
            UWORD8 *pu1_rec_chroma = pu1_curr_pic_chroma + MB_SIZE + PAD_RIGHT +
                                     (((i4_pad_ht >> 1) - 1) * i4_chroma_stride);

            /* padding bottom luma */
            ps_codec->pf_pad_bottom(pu1_rec_luma, i4_luma_stride, i4_luma_stride, PAD_BOT);

            /* padding bottom chroma */
            ps_codec->pf_pad_bottom(pu1_rec_chroma, i4_chroma_stride, i4_chroma_stride,
                                    (PAD_BOT >> 1));
        }
    }

    if(i4_mb_y == 0)
    {
        UWORD8 *pu1_rec_luma = pu1_curr_pic_luma;
        UWORD8 *pu1_rec_chroma = pu1_curr_pic_chroma;
        WORD32 wd = MB_SIZE;

        if(i4_mb_x == 0)
        {
            pu1_rec_luma -= PAD_LEFT;
            pu1_rec_chroma -= PAD_LEFT;

            wd += PAD_LEFT;
        }
        if(i4_mb_x == ps_proc->i4_wd_mbs - 1)
        {
            wd += PAD_RIGHT;
        }

        /* padding top luma */
        ps_codec->pf_pad_top(pu1_rec_luma, i4_luma_stride, wd, PAD_TOP);

        /* padding top chroma */
        ps_codec->pf_pad_top(pu1_rec_chroma, i4_chroma_stride, wd, (PAD_TOP >> 1));
    }

    return IH264E_SUCCESS;
}

/**
*******************************************************************************
*
* @brief This function performs deblocking
*
* @par Description:
*
* @param[in] ps_proc
*  Process context corresponding to the job
*
* @returns  error status
*
* @remarks none
*
*******************************************************************************
*/
static IH264E_ERROR_T isvce_dblk_n_mbs(isvce_process_ctxt_t *ps_proc,
                                       UWORD8 u1_inter_layer_deblk_flag)
{
    WORD32 i;
    WORD32 row, col;

    n_mb_process_ctxt_t *ps_n_mb_ctxt = &ps_proc->s_n_mb_ctxt;
    isvce_deblk_ctxt_t *ps_deblk = &ps_proc->s_deblk_ctxt;

    UWORD8 *pu1_deblk_map = ps_proc->pu1_deblk_map + ps_deblk->i4_mb_y * ps_proc->i4_wd_mbs;
    UWORD8 *pu1_deblk_map_prev_row = pu1_deblk_map - ps_proc->i4_wd_mbs;
    WORD32 u4_deblk_prev_row = 0;
    WORD32 i4_n_mbs = ps_n_mb_ctxt->i4_n_mbs;
    WORD32 i4_n_mb_process_count = 0;
    WORD32 i4_mb_x = ps_proc->i4_mb_x;
    WORD32 i4_mb_y = ps_proc->i4_mb_y;

    ASSERT(i4_n_mbs == ps_proc->i4_wd_mbs);

    if(ps_proc->u4_disable_deblock_level != 1)
    {
        if((i4_mb_y > 0) || (i4_mb_y == (ps_proc->i4_ht_mbs - 1)))
        {
            /* if number of mb's to be processed are less than 'N', go back.
             * exception to the above clause is end of row */
            if(((i4_mb_x - (ps_n_mb_ctxt->i4_mb_x - 1)) < i4_n_mbs) &&
               (i4_mb_x < (ps_proc->i4_wd_mbs - 1)))
            {
                return IH264E_SUCCESS;
            }
            else
            {
                WORD32 i4_num_deblk_rows = 1;

                if(i4_mb_y == (ps_proc->i4_ht_mbs - 1))
                {
                    i4_num_deblk_rows += (ps_proc->i4_ht_mbs > 1);
                }

                if(1 == ps_proc->i4_ht_mbs)
                {
                    ps_deblk->i4_mb_y = 0;
                    pu1_deblk_map_prev_row = pu1_deblk_map;
                }

                for(i = 0; i < i4_num_deblk_rows; i++)
                {
                    if(i == 1)
                    {
                        /* Deblock last row */
                        ps_n_mb_ctxt->i4_mb_x = 0;
                        ps_n_mb_ctxt->i4_mb_y = ps_proc->i4_mb_y;
                        ps_deblk->i4_mb_x = 0;
                        ps_deblk->i4_mb_y = ps_proc->i4_mb_y;
                        pu1_deblk_map_prev_row = pu1_deblk_map;
                        pu1_deblk_map += ps_proc->i4_wd_mbs;
                    }

                    i4_n_mb_process_count = MIN(i4_mb_x - (ps_n_mb_ctxt->i4_mb_x - 1), i4_n_mbs);

                    /* performing deblocking for required number of MBs */
                    u4_deblk_prev_row = 1;

                    /* checking whether the top rows are deblocked */
                    for(col = 0; col < i4_n_mb_process_count; col++)
                    {
                        u4_deblk_prev_row &= pu1_deblk_map_prev_row[ps_deblk->i4_mb_x + col];
                    }

                    /* checking whether the top right MB is deblocked */
                    if((ps_deblk->i4_mb_x + i4_n_mb_process_count) != ps_proc->i4_wd_mbs)
                    {
                        u4_deblk_prev_row &=
                            pu1_deblk_map_prev_row[ps_deblk->i4_mb_x + i4_n_mb_process_count];
                    }

                    /* Top or Top right MBs not deblocked */
                    if((u4_deblk_prev_row != 1) && (i4_mb_y > 0))
                    {
                        return IH264E_SUCCESS;
                    }

                    for(row = 0; row < i4_n_mb_process_count; row++)
                    {
                        isvce_deblock_mb(ps_proc, ps_deblk, u1_inter_layer_deblk_flag);

                        pu1_deblk_map[ps_deblk->i4_mb_x] = 1;

                        ps_deblk->i4_mb_x++;
                    }
                }
            }
        }
    }

    return IH264E_SUCCESS;
}

/**
*******************************************************************************
*
* @brief This function performs 'intra base' deblocking
*
* @par Description:
*
* @param[in] ps_proc
*  Process context corresponding to the job
*
* @returns  error status
*
* @remarks none
*
*******************************************************************************
*/
static IH264E_ERROR_T isvce_intra_base_dblk(isvce_process_ctxt_t *ps_proc)
{
    isvce_codec_t *ps_codec = ps_proc->ps_codec;
    isvce_deblk_ctxt_t *ps_deblk = &ps_proc->s_deblk_ctxt;

    IH264E_ERROR_T e_ret = IH264E_SUCCESS;

    if(ps_proc->u1_spatial_layer_id < (ps_proc->s_svc_params.u1_num_spatial_layers - 1))
    {
        ps_deblk->i4_mb_x = ps_proc->i4_mb_x;
        ps_deblk->i4_mb_y = ps_proc->i4_mb_y - 1;

        ps_deblk->s_rec_pic_buf_props =
            ps_codec->s_svc_ilp_data.ps_intra_recon_bufs[ps_proc->u1_spatial_layer_id];

        e_ret = isvce_dblk_n_mbs(ps_proc, 1);

        ps_deblk->s_rec_pic_buf_props = ps_proc->s_rec_pic_buf_props;
    }

    return e_ret;
}

/**
*******************************************************************************
*
* @brief This function performs luma & chroma core coding for a set of mb's.
*
* @par Description:
*  The mb to be coded is taken and is evaluated over a predefined set of modes
*  (intra (i16, i4, i8)/inter (mv, skip)) for best cost. The mode with least
*cost is selected and using intra/inter prediction filters, prediction is
*carried out. The deviation between src and pred signal constitutes error
*signal. This error signal is transformed (hierarchical transform if necessary)
*and quantized. The quantized residue is packed in to entropy buffer for entropy
*coding. This is repeated for all the mb's enlisted under the job.
*
* @param[in] ps_proc
*  Process context corresponding to the job
*
* @returns  error status
*
* @remarks none
*
*******************************************************************************
*/
WORD32 isvce_process(isvce_process_ctxt_t *ps_proc)
{
    UWORD32 u4_cbp_l, u4_cbp_c;
    WORD32 i4_mb_idx;
    WORD32 luma_idx, chroma_idx, is_intra;

    isvce_codec_t *ps_codec = ps_proc->ps_codec;
    isa_dependent_fxns_t *ps_isa_dependent_fxns = &ps_codec->s_isa_dependent_fxns;
    enc_loop_fxns_t *ps_enc_loop_fxns = &ps_isa_dependent_fxns->s_enc_loop_fxns;

    WORD32 error_status = IH264_SUCCESS;
    WORD32 i4_wd_mbs = ps_proc->i4_wd_mbs;
    WORD32 i4_mb_cnt = ps_proc->i4_mb_cnt;
    UWORD32 u4_valid_modes = 0;
    WORD32 i4_gate_threshold = 0;
    WORD32 ctxt_sel = ps_proc->i4_encode_api_call_cnt % MAX_CTXT_SETS;
    bool b_enable_intra4x4_eval = true;

    /*
     * list of modes for evaluation
     * -------------------------------------------------------------------------
     * Note on enabling I4x4 and I16x16
     * At very low QP's the hadamard transform in I16x16 will push up the maximum
     * coeff value very high. CAVLC may not be able to represent the value and
     * hence the stream may not be decodable in some clips.
     * Hence at low QPs, we will enable I4x4 and disable I16x16 irrespective of
     * preset.
     */
    if(ps_proc->i4_slice_type == ISLICE)
    {
        u4_valid_modes |= ps_codec->s_cfg.u4_enable_intra_16x16 ? (1 << I16x16) : 0;

        /* enable intra 8x8 */
        u4_valid_modes |= ps_codec->s_cfg.u4_enable_intra_8x8 ? (1 << I8x8) : 0;

        /* enable intra 4x4 */
        u4_valid_modes |= ps_codec->s_cfg.u4_enable_intra_4x4 ? (1 << I4x4) : 0;
        u4_valid_modes |= (ps_proc->u1_frame_qp <= 10) << I4x4;
    }
    else if(ps_proc->i4_slice_type == PSLICE)
    {
        u4_valid_modes |= ps_codec->s_cfg.u4_enable_intra_16x16 ? (1 << I16x16) : 0;

        /* enable intra 4x4 */
        if(ps_codec->s_cfg.u4_enc_speed_preset == IVE_SLOWEST)
        {
            u4_valid_modes |= ps_codec->s_cfg.u4_enable_intra_4x4 ? (1 << I4x4) : 0;
        }
        u4_valid_modes |= (ps_proc->u1_frame_qp <= 10) << I4x4;

        /* enable inter P16x16 */
        u4_valid_modes |= (1 << P16x16);
    }
    else if(ps_proc->i4_slice_type == BSLICE)
    {
        u4_valid_modes |= ps_codec->s_cfg.u4_enable_intra_16x16 ? (1 << I16x16) : 0;

        /* enable intra 4x4 */
        if(ps_codec->s_cfg.u4_enc_speed_preset == IVE_SLOWEST)
        {
            u4_valid_modes |= ps_codec->s_cfg.u4_enable_intra_4x4 ? (1 << I4x4) : 0;
        }
        u4_valid_modes |= (ps_proc->u1_frame_qp <= 10) << I4x4;

        /* enable inter B16x16 */
        u4_valid_modes |= (1 << B16x16);
    }

    ps_proc->s_entropy.i4_mb_x = ps_proc->i4_mb_x;
    ps_proc->s_entropy.i4_mb_y = ps_proc->i4_mb_y;
    ps_proc->s_entropy.i4_mb_cnt = MIN(ps_proc->i4_nmb_ntrpy, i4_wd_mbs - ps_proc->i4_mb_x);

    /* compute recon when :
     *   1. current frame is to be used as a reference
     *   2. dump recon for bit stream sanity check
     */
    ps_proc->u4_compute_recon = ((ps_proc->s_svc_params.u1_num_spatial_layers > 1) &&
                                 (ENABLE_RESIDUAL_PREDICTION || ENABLE_IBL_MODE)) ||
                                ps_codec->u4_is_curr_frm_ref || ps_codec->s_cfg.u4_enable_recon;

    for(i4_mb_idx = 0; i4_mb_idx < i4_mb_cnt; i4_mb_idx++)
    {
        /* since we have not yet found sad, we have not yet got min sad */
        /* we need to initialize these variables for each MB */
        /* TODO how to get the min sad into the codec */
        ps_proc->u4_min_sad = ps_codec->s_cfg.i4_min_sad;
        ps_proc->u4_min_sad_reached = 0;

        ps_proc->ps_mb_info->u1_mb_qp = ps_proc->u1_mb_qp;

        /* wait until the proc of [top + 1] mb is computed.
         * We wait till the proc dependencies are satisfied */
        if(ps_proc->i4_mb_y > 0)
        {
            UWORD8 *pu1_proc_map_top;

            pu1_proc_map_top = ps_proc->pu1_proc_map + ((ps_proc->i4_mb_y - 1) * i4_wd_mbs);

            while(1)
            {
                volatile UWORD8 *pu1_buf;
                WORD32 idx = MIN(i4_mb_cnt - 1, i4_mb_idx + 1);

                idx = MIN(idx, ((WORD32) ps_codec->s_cfg.i4_wd_mbs - 1));
                pu1_buf = pu1_proc_map_top + idx;
                if(*pu1_buf) break;
                ithread_yield();
            }
        }

        if(ENABLE_ILP_MV && (ps_proc->u1_spatial_layer_id > 0) &&
           (ps_proc->i4_slice_type != ISLICE))
        {
            svc_ilp_mv_ctxt_t *ps_svc_ilp_mv_ctxt = ps_proc->ps_svc_ilp_mv_ctxt;
            coordinates_t s_mb_pos = {ps_proc->i4_mb_x, ps_proc->i4_mb_y};

            ps_svc_ilp_mv_ctxt->s_ilp_mv_variables.ps_svc_ilp_data = &ps_codec->s_svc_ilp_data;
            ps_svc_ilp_mv_ctxt->s_ilp_mv_variables.s_mb_pos = s_mb_pos;
            ps_svc_ilp_mv_ctxt->s_ilp_mv_variables.u1_spatial_layer_id =
                ps_proc->u1_spatial_layer_id;

            isvce_get_mb_ilp_mv(ps_svc_ilp_mv_ctxt);

            ps_proc->ps_ilp_mv = &ps_svc_ilp_mv_ctxt->s_ilp_mv_outputs.s_ilp_mv;
            ps_proc->s_me_ctxt.ps_ilp_me_cands =
                &ps_svc_ilp_mv_ctxt->s_ilp_mv_outputs.s_ilp_me_cands;
        }
        else
        {
            ps_proc->ps_ilp_mv = NULL;
            ps_proc->s_me_ctxt.ps_ilp_me_cands = NULL;
        }

        ps_proc->ps_mb_info->u2_mb_type = INVALID_MB_TYPE;
        ps_proc->i4_mb_distortion = SHRT_MAX;

        {
            WORD32 i4_mb_id = ps_proc->i4_mb_x + ps_proc->i4_mb_y * i4_wd_mbs;

            WORD32 i4_air_enable_inter =
                (ps_codec->s_cfg.e_air_mode == IVE_AIR_MODE_NONE) ||
                (ps_codec->pu2_intr_rfrsh_map[i4_mb_id] != ps_codec->i4_air_pic_cnt);

            if((u4_valid_modes & (1 << P16x16)) || (u4_valid_modes & (1 << B16x16)))
            {
                if(ps_proc->i4_mb_x % ps_proc->u4_nmb_me == 0)
                {
                    isvce_compute_me_nmb(
                        ps_proc, MIN((WORD32) ps_proc->u4_nmb_me, i4_wd_mbs - ps_proc->i4_mb_x));
                }

                {
                    UWORD32 u4_mb_index = ps_proc->i4_mb_x % ps_proc->u4_nmb_me;

                    ps_proc->u4_min_sad_reached =
                        ps_proc->ps_nmb_info[u4_mb_index].u4_min_sad_reached;
                    ps_proc->u4_min_sad = ps_proc->ps_nmb_info[u4_mb_index].u4_min_sad;

                    ps_proc->ps_skip_mv = &(ps_proc->ps_nmb_info[u4_mb_index].as_skip_mv[0]);
                    ps_proc->ps_ngbr_avbl = &(ps_proc->ps_nmb_info[u4_mb_index].s_ngbr_avbl);
                    ps_proc->ps_pred_mv = &(ps_proc->ps_nmb_info[u4_mb_index].as_pred_mv[0]);

                    ps_proc->i4_mb_distortion = ps_proc->ps_nmb_info[u4_mb_index].i4_mb_distortion;

                    ps_proc->i4_mb_cost = ps_proc->ps_nmb_info[u4_mb_index].i4_mb_cost;
                    ps_proc->u4_min_sad = ps_proc->ps_nmb_info[u4_mb_index].u4_min_sad;
                    ps_proc->u4_min_sad_reached =
                        ps_proc->ps_nmb_info[u4_mb_index].u4_min_sad_reached;
                    ps_proc->ps_mb_info->u2_mb_type = ps_proc->ps_nmb_info[u4_mb_index].u4_mb_type;

                    ps_proc->pu1_best_subpel_buf =
                        ps_proc->ps_nmb_info[u4_mb_index].pu1_best_sub_pel_buf;
                    ps_proc->u4_bst_spel_buf_strd =
                        ps_proc->ps_nmb_info[u4_mb_index].u4_bst_spel_buf_strd;
                }

                isvce_derive_nghbr_avbl_of_mbs(ps_proc);
            }
            else
            {
                ps_proc->ps_ngbr_avbl = &ps_proc->s_ngbr_avbl;

                isvce_derive_nghbr_avbl_of_mbs(ps_proc);
            }

            /*
             * If air says intra, we need to force the following code path to evaluate
             * intra The easy way is just to say that the inter cost is too much
             */
            if(!i4_air_enable_inter)
            {
                ps_proc->u4_min_sad_reached = 0;
                ps_proc->i4_mb_cost = INT_MAX;
                ps_proc->i4_mb_distortion = INT_MAX;
            }
            else if(ps_proc->ps_mb_info->u2_mb_type == PSKIP)
            {
                ps_proc->ps_mb_info->u1_base_mode_flag = 0;
                ps_proc->ps_mb_info->u1_residual_prediction_flag = 0;
                goto UPDATE_MB_INFO;
            }

            /* If we already have the minimum sad, there is no point in searching for
             * sad again */
            if((ps_proc->u4_min_sad_reached == 0) ||
               (ps_codec->s_cfg.u4_enc_speed_preset != IVE_FASTEST))
            {
                /* intra gating in inter slices */
                /* No need of gating if we want to force intra, we need to find the
                 * threshold only if inter is enabled by AIR*/
                if((ps_proc->i4_slice_type != ISLICE) &&
                   (FORCE_DISTORTION_BASED_INTRA_4X4_GATING ||
                    (i4_air_enable_inter && ps_codec->u4_inter_gate)))
                {
                    WORD32 i4_distortion[4];

                    if((ps_proc->i4_mb_x > 0) && (ps_proc->i4_mb_y > 0))
                    {
                        i4_distortion[0] = ps_proc->s_nbr_info.ps_left_mb_info->i4_mb_distortion;

                        i4_distortion[1] = ps_proc->s_nbr_info.ps_top_row_mb_info[ps_proc->i4_mb_x]
                                               .i4_mb_distortion;

                        i4_distortion[2] =
                            ps_proc->s_nbr_info.ps_top_row_mb_info[ps_proc->i4_mb_x + 1]
                                .i4_mb_distortion;

                        i4_distortion[3] =
                            ps_proc->s_nbr_info.ps_top_row_mb_info[ps_proc->i4_mb_x - 1]
                                .i4_mb_distortion;

                        i4_gate_threshold = (i4_distortion[0] + i4_distortion[1] +
                                             i4_distortion[2] + i4_distortion[3]) /
                                            4;
                    }
                }

                b_enable_intra4x4_eval = true;

                if(ENABLE_IBL_MODE && (ps_proc->u1_spatial_layer_id > 0) &&
                   (ps_proc->s_svc_params.d_spatial_res_ratio == 2.) && !ps_proc->ps_ilp_mv)
                {
                    isvce_evaluate_IBL_mode(ps_proc);
                }
                else
                {
                    ps_proc->ps_mb_info->u1_base_mode_flag = 0;
                }

                if(u4_valid_modes & (1 << I16x16))
                {
                    isvce_evaluate_intra16x16_modes_for_least_cost_rdoptoff(ps_proc);

                    if(ENABLE_INTRA16X16_BASED_INTRA4X4_GATING &&
                       (ps_proc->i4_slice_type != ISLICE) &&
                       (ps_proc->ps_mb_info->u2_mb_type == I16x16))
                    {
                        b_enable_intra4x4_eval = false;
                    }
                }

                if(u4_valid_modes & (1 << I8x8))
                {
                    isvce_evaluate_intra8x8_modes_for_least_cost_rdoptoff(ps_proc);
                }

                if(ENABLE_ILP_BASED_INTRA4X4_GATING && (ps_proc->i4_slice_type != ISLICE))
                {
                    b_enable_intra4x4_eval =
                        !(ps_proc->ps_ilp_mv && (INVALID_MB_TYPE != ps_proc->ps_ilp_mv->e_mb_type));
                }

                /* If we are going to force intra we need to evaluate intra irrespective
                 * of gating */
                if((!i4_air_enable_inter) ||
                   ((i4_gate_threshold + 16 * ((WORD32) ps_proc->u4_lambda)) <
                    ps_proc->i4_mb_distortion))
                {
                    if(b_enable_intra4x4_eval && (u4_valid_modes & (1 << I4x4)))
                    {
                        if(!FORCE_FAST_INTRA4X4 &&
                           (ps_codec->s_cfg.u4_enc_speed_preset == IVE_SLOWEST))
                        {
                            isvce_evaluate_intra4x4_modes_for_least_cost_rdopton(ps_proc);
                        }
                        else
                        {
                            isvce_evaluate_intra4x4_modes_for_least_cost_rdoptoff(ps_proc);
                        }
                    }
                }
            }
        }

        if(ps_proc->ps_mb_info->u2_mb_type == I4x4 || ps_proc->ps_mb_info->u2_mb_type == I16x16 ||
           ps_proc->ps_mb_info->u2_mb_type == I8x8)
        {
            luma_idx = ps_proc->ps_mb_info->u2_mb_type;
            chroma_idx = 0;
            is_intra = 1;

            isvce_evaluate_chroma_intra8x8_modes_for_least_cost_rdoptoff(ps_proc);
        }
        else if(ps_proc->ps_mb_info->u2_mb_type == BASE_MODE)
        {
            luma_idx = 3;
            chroma_idx = 1;
            is_intra = 1;
            ps_proc->u4_min_sad_reached = 0;
        }
        else
        {
            luma_idx = 3;
            chroma_idx = 1;
            is_intra = 0;
        }

        ps_proc->ps_mb_info->u1_is_intra = is_intra;

        if(is_intra)
        {
            ps_proc->ps_mb_info->as_pu->as_me_info[L0].i1_ref_idx = -1;
            ps_proc->ps_mb_info->as_pu->as_me_info[L0].s_mv.i2_mvx = 0;
            ps_proc->ps_mb_info->as_pu->as_me_info[L0].s_mv.i2_mvy = 0;

            ps_proc->ps_mb_info->as_pu->as_me_info[L1].i1_ref_idx = -1;
            ps_proc->ps_mb_info->as_pu->as_me_info[L1].s_mv.i2_mvx = 0;
            ps_proc->ps_mb_info->as_pu->as_me_info[L1].s_mv.i2_mvy = 0;
        }
        else
        {
            isvce_mv_pred(ps_proc, ps_proc->i4_slice_type);
        }

        if(ENABLE_RESIDUAL_PREDICTION && !is_intra && (ps_proc->u1_spatial_layer_id > 0) &&
           (ps_proc->i4_slice_type == PSLICE) && (ps_proc->ps_mb_info->u2_mb_type != PSKIP))
        {
            svc_res_pred_ctxt_t *ps_res_pred_ctxt = ps_proc->ps_res_pred_ctxt;

            UWORD32 u4_res_pred_sad;

            isvce_me_ctxt_t *ps_me_ctxt = &ps_proc->s_me_ctxt;
            yuv_buf_props_t s_pred = ps_proc->s_src_buf_props;

            if(!(ps_proc->ps_mb_info->as_pu->as_me_info[L0].s_mv.i2_mvx % 4) &&
               !(ps_proc->ps_mb_info->as_pu->as_me_info[L0].s_mv.i2_mvy % 4))
            {
                s_pred.as_component_bufs[Y].pv_data =
                    ps_me_ctxt->apu1_ref_buf_luma[L0] +
                    (ps_me_ctxt->as_mb_part[L0].s_mv_curr.i2_mvx >> 2) +
                    (ps_me_ctxt->as_mb_part[L0].s_mv_curr.i2_mvy >> 2) *
                        ps_me_ctxt->ai4_rec_strd[L0];
                s_pred.as_component_bufs[Y].i4_data_stride = ps_me_ctxt->ai4_rec_strd[L0];
            }
            else
            {
                s_pred.as_component_bufs[Y].pv_data = ps_proc->pu1_best_subpel_buf;
                s_pred.as_component_bufs[Y].i4_data_stride = ps_proc->u4_bst_spel_buf_strd;
            }

            s_pred.as_component_bufs[U].pv_data = s_pred.as_component_bufs[V].pv_data = NULL;

            ps_res_pred_ctxt->s_res_pred_variables.ps_svc_ilp_data = &ps_codec->s_svc_ilp_data;
            ps_res_pred_ctxt->s_res_pred_variables.s_mb_pos.i4_abscissa = ps_proc->i4_mb_x;
            ps_res_pred_ctxt->s_res_pred_variables.s_mb_pos.i4_ordinate = ps_proc->i4_mb_y;
            ps_res_pred_ctxt->s_res_pred_variables.u1_spatial_layer_id =
                ps_proc->u1_spatial_layer_id;

            if(ps_proc->s_svc_params.d_spatial_res_ratio == 2.)
            {
                isvce_get_mb_residual_pred(ps_proc->ps_res_pred_ctxt);
            }
            else
            {
                isvce_get_mb_residual_pred_non_dyadic(ps_proc->ps_res_pred_ctxt);
            }

            isvce_residual_pred_eval(ps_proc->ps_res_pred_ctxt, &ps_proc->s_src_buf_props, &s_pred,
                                     ps_proc->ps_mb_res_buf, &u4_res_pred_sad,
                                     &ps_proc->ps_mb_info->u1_residual_prediction_flag,
                                     ps_proc->i4_mb_distortion);

            if(ps_proc->ps_mb_info->u1_residual_prediction_flag)
            {
                ps_proc->i4_mb_cost -= ps_proc->i4_mb_distortion;
                ps_proc->i4_mb_cost += (WORD32) u4_res_pred_sad;
                ps_proc->i4_mb_distortion = (WORD32) u4_res_pred_sad;
            }
        }
        else
        {
            ps_proc->ps_mb_info->u1_residual_prediction_flag = 0;
        }

        if(isvce_is_ilp_mv_winning_mv(ps_proc->ps_mb_info, ps_proc->ps_ilp_mv))
        {
            ps_proc->ps_mb_info->as_pu->as_me_info[L0] = ps_proc->ps_ilp_mv->as_mv[0][L0];
            ps_proc->ps_mb_info->as_pu->as_me_info[L1] = ps_proc->ps_ilp_mv->as_mv[0][L1];

            ps_proc->ps_mb_info->u1_base_mode_flag = 1;
            ps_proc->ps_mb_info->u2_mb_type = BASE_MODE;
        }
        else if(ps_proc->ps_mb_info->u2_mb_type != BASE_MODE)
        {
            ps_proc->ps_mb_info->u1_base_mode_flag = 0;
        }

        isvce_mvp_idx_eval(ps_proc->ps_mb_info, ps_proc->ps_pred_mv,
                           ps_proc->ps_ilp_mv ? ps_proc->ps_ilp_mv->as_mv[0] : NULL,
                           ps_proc->s_me_ctxt.pu1_mv_bits);

        /* 8x8 Tx is not supported, and I8x8 is also unsupported */
        ASSERT((luma_idx == 0) || (luma_idx == 1) || (luma_idx == 3));
        ps_proc->ps_mb_info->u1_tx_size = 4;

        /* Perform luma mb core coding */
        u4_cbp_l = (ps_enc_loop_fxns->apf_luma_energy_compaction)[luma_idx](ps_proc);

        /* Perform chroma mb core coding */
        u4_cbp_c = (ps_enc_loop_fxns->apf_chroma_energy_compaction)[chroma_idx](ps_proc);

        ps_proc->u4_cbp = (u4_cbp_c << 4) | u4_cbp_l;
        ps_proc->ps_mb_info->u4_cbp = (u4_cbp_c << 4) | u4_cbp_l;
        ps_proc->ps_mb_info->u4_csbp = isvce_calculate_csbp(ps_proc);

        if(ps_proc->ps_mb_info->u1_is_intra)
        {
            switch(ps_proc->ps_mb_info->u2_mb_type)
            {
                case I16x16:
                {
                    ps_proc->ps_mb_info->s_intra_pu.s_i16x16_mode_data.u1_mode =
                        ps_proc->u1_l_i16_mode;

                    break;
                }
                case I4x4:
                {
                    WORD32 i;

                    for(i = 0; i < MAX_TU_IN_MB; i++)
                    {
                        ps_proc->ps_mb_info->s_intra_pu.as_i4x4_mode_data[i].u1_mode =
                            ps_proc->au1_intra_luma_mb_4x4_modes[i];
                        ps_proc->ps_mb_info->s_intra_pu.as_i4x4_mode_data[i].u1_predicted_mode =
                            ps_proc->au1_predicted_intra_luma_mb_4x4_modes[i];
                    }

                    break;
                }
                case BASE_MODE:
                {
                    break;
                }
                default:
                {
                    ASSERT(false);
                }
            }

            ps_proc->ps_mb_info->s_intra_pu.u1_chroma_intra_mode = ps_proc->u1_c_i8_mode;
        }

        if(!ps_proc->ps_mb_info->u1_is_intra && !ps_proc->ps_mb_info->u1_residual_prediction_flag)
        {
            if(ps_proc->i4_slice_type == BSLICE)
            {
                if(isvce_find_bskip_params(ps_proc, L0))
                {
                    ps_proc->ps_mb_info->u2_mb_type = (ps_proc->u4_cbp) ? BDIRECT : BSKIP;
                }
            }
            else if(!ps_proc->u4_cbp)
            {
                if(isvce_find_pskip_params(ps_proc, L0))
                {
                    ps_proc->ps_mb_info->u2_mb_type = PSKIP;
                }
            }
        }

    UPDATE_MB_INFO:
        isvce_svc_ilp_buf_update(ps_proc);

        isvce_update_ibl_info(
            ps_proc->ps_intra_pred_ctxt, ps_proc->s_svc_params.u1_num_spatial_layers,
            ps_proc->u1_spatial_layer_id, ps_proc->ps_mb_info->u2_mb_type, ps_proc->i4_mb_x,
            ps_proc->i4_mb_y, ps_proc->ps_mb_info->u1_base_mode_flag);

        isvce_update_res_pred_info(ps_proc);

        /* Update mb sad, mb qp and intra mb cost. Will be used by rate control */
        isvce_update_rc_mb_info(&ps_proc->s_frame_info, ps_proc);

        {
            svc_sub_pic_rc_ctxt_t *ps_sub_pic_rc_ctxt = ps_proc->ps_sub_pic_rc_ctxt;
            svc_sub_pic_rc_mb_variables_t *ps_sub_pic_rc_variables =
                &ps_sub_pic_rc_ctxt->s_sub_pic_rc_variables.s_mb_variables;

            ps_sub_pic_rc_variables->ps_mb_info = ps_proc->ps_mb_info;
            ps_sub_pic_rc_variables->s_mb_pos.i4_abscissa = ps_proc->i4_mb_x;
            ps_sub_pic_rc_variables->s_mb_pos.i4_ordinate = ps_proc->i4_mb_y;
            ps_sub_pic_rc_variables->u4_cbp = ps_proc->u4_cbp;
            ps_sub_pic_rc_variables->aps_mvps[0] = ps_proc->ps_pred_mv;
#if MAX_MVP_IDX == 1
            ps_sub_pic_rc_variables->aps_mvps[1] =
                ps_proc->ps_ilp_mv ? ps_proc->ps_ilp_mv->as_mv[0] : NULL;
#endif
            ps_sub_pic_rc_variables->apu1_nnzs[Y] = (UWORD8 *) ps_proc->au4_nnz;
            ps_sub_pic_rc_variables->apu1_nnzs[UV] = ps_proc->au1_chroma_nnz;

            /* Quant coeffs are arranged TU by TU */
            switch(ps_proc->ps_mb_info->u2_mb_type)
            {
                case I16x16:
                case I4x4:
                case P16x16:
                case B16x16:
                case BASE_MODE:
                {
                    ps_sub_pic_rc_variables->as_quant_coeffs[Y].pv_data =
                        ps_proc->pi2_res_buf_intra_4x4;
                    ps_sub_pic_rc_variables->as_quant_coeffs[Y].i4_data_stride =
                        ps_proc->i4_res_strd;
                    ps_sub_pic_rc_variables->as_quant_coeffs[UV].pv_data = ps_proc->pi2_res_buf;
                    ps_sub_pic_rc_variables->as_quant_coeffs[UV].i4_data_stride =
                        ps_proc->i4_res_strd;

                    break;
                }
                case PSKIP:
                case BSKIP:
                {
                    ps_sub_pic_rc_variables->as_quant_coeffs[Y].pv_data = NULL;
                    ps_sub_pic_rc_variables->as_quant_coeffs[UV].pv_data = NULL;

                    break;
                }
                default:
                {
                    ASSERT(false);

                    break;
                }
            }

            isvce_sub_pic_rc_ctxt_update(ps_proc->ps_sub_pic_rc_ctxt);
        }

#if ENABLE_MODE_STAT_VISUALISER
        if(ps_proc->u1_spatial_layer_id == (ps_proc->s_svc_params.u1_num_spatial_layers - 1))
        {
            coordinates_t s_mb_pos = {ps_proc->i4_mb_x, ps_proc->i4_mb_y};

            isvce_msv_set_mode(ps_codec->ps_mode_stat_visualiser, ps_proc->ps_mb_info, &s_mb_pos);
        }
#endif

        /**********************************************************************/
        /* if disable deblock level is '0' this implies enable deblocking for */
        /* all edges of all macroblocks with out any restrictions             */
        /*                                                                    */
        /* if disable deblock level is '1' this implies disable deblocking for*/
        /* all edges of all macroblocks with out any restrictions             */
        /*                                                                    */
        /* if disable deblock level is '2' this implies enable deblocking for */
        /* all edges of all macroblocks except edges overlapping with slice   */
        /* boundaries. This option is not currently supported by the encoder  */
        /* hence the slice map should be of no significance to perform debloc */
        /* king                                                               */
        /**********************************************************************/

        if(ps_proc->u4_compute_recon)
        {
            /* compute blocking strength */
            if(ps_proc->u4_disable_deblock_level != 1)
            {
                isvce_compute_bs(ps_proc, 0);

                if(ENABLE_INTRA_BASE_DEBLOCK && (ps_proc->u1_spatial_layer_id <
                                                 (ps_proc->s_svc_params.u1_num_spatial_layers - 1)))
                {
                    isvce_compute_bs(ps_proc, 1);
                }
            }
            /* nmb deblocking and hpel and padding */
            isvce_dblk_n_mbs(ps_proc, 0);

            if(ENABLE_INTRA_BASE_DEBLOCK &&
               (ps_proc->u1_spatial_layer_id < (ps_proc->s_svc_params.u1_num_spatial_layers - 1)))
            {
                isvce_intra_base_dblk(ps_proc);
            }

            if(ps_proc->i4_mb_x == (ps_proc->i4_wd_mbs - 1) &&
               ps_proc->i4_mb_y == (ps_proc->i4_ht_mbs - 1))
            {
                isvce_svc_pad_frame(ps_proc);

                isvce_pad_mb_mode_buf(ps_proc->ps_intra_pred_ctxt, ps_proc->u1_spatial_layer_id,
                                      ps_proc->s_svc_params.u1_num_spatial_layers,
                                      ps_proc->s_svc_params.d_spatial_res_ratio,
                                      ps_codec->s_cfg.u4_wd, ps_codec->s_cfg.u4_ht);
            }
        }

        /* update the context after for coding next mb */
        error_status = isvce_update_proc_ctxt(ps_proc);

        if(error_status != IH264E_SUCCESS)
        {
            return error_status;
        }

        {
            UWORD8 u1_new_mb_qp;

            u1_new_mb_qp =
                isvce_sub_pic_rc_get_mb_qp(ps_proc->ps_sub_pic_rc_ctxt, ps_proc->u1_mb_qp);

            if(u1_new_mb_qp != ps_proc->u1_mb_qp)
            {
                ps_proc->u1_mb_qp = u1_new_mb_qp;
                ps_proc->u4_lambda = gu1_qp0[u1_new_mb_qp];

                isvce_init_quant_params(ps_proc, ps_proc->u1_mb_qp);
            }
        }

        /* Once the last row is processed, mark the buffer status appropriately */
        if(ps_proc->i4_ht_mbs == ps_proc->i4_mb_y)
        {
            /* Pointer to current picture buffer structure */
            svc_au_buf_t *ps_cur_pic = ps_proc->ps_cur_pic;

            /* Pointer to current picture's mv buffer structure */
            svc_au_data_t *ps_cur_mv_buf = ps_proc->ps_cur_mv_buf;

            /**********************************************************************/
            /* if disable deblock level is '0' this implies enable deblocking for */
            /* all edges of all macroblocks with out any restrictions             */
            /*                                                                    */
            /* if disable deblock level is '1' this implies disable deblocking for*/
            /* all edges of all macroblocks with out any restrictions             */
            /*                                                                    */
            /* if disable deblock level is '2' this implies enable deblocking for */
            /* all edges of all macroblocks except edges overlapping with slice   */
            /* boundaries. This option is not currently supported by the encoder  */
            /* hence the slice map should be of no significance to perform debloc */
            /* king                                                               */
            /**********************************************************************/
            error_status = ih264_buf_mgr_release(ps_codec->pv_svc_au_data_store_mgr,
                                                 ps_cur_mv_buf->i4_buf_id, BUF_MGR_CODEC);
            if(error_status != IH264E_SUCCESS)
            {
                return error_status;
            }
            error_status = ih264_buf_mgr_release(ps_codec->pv_ref_buf_mgr, ps_cur_pic->i4_buf_id,
                                                 BUF_MGR_CODEC);
            if(error_status != IH264E_SUCCESS)
            {
                return error_status;
            }
            if(ps_codec->s_cfg.u4_enable_recon)
            {
                /* pic cnt */
                ps_codec->as_rec_buf[ctxt_sel].i4_pic_cnt = ps_proc->i4_pic_cnt;

                /* rec buffers */
                ps_codec->as_rec_buf[ctxt_sel].s_pic_buf = *ps_proc->ps_cur_pic;

                /* is last? */
                ps_codec->as_rec_buf[ctxt_sel].u4_is_last = ps_proc->s_entropy.u4_is_last;

                /* frame time stamp */
                ps_codec->as_rec_buf[ctxt_sel].u4_timestamp_high =
                    ps_proc->s_entropy.u4_timestamp_high;
                ps_codec->as_rec_buf[ctxt_sel].u4_timestamp_low =
                    ps_proc->s_entropy.u4_timestamp_low;
            }
        }
    }

    DEBUG_HISTOGRAM_DUMP(ps_codec->s_cfg.i4_ht_mbs == ps_proc->i4_mb_y);

    return error_status;
}

/**
*******************************************************************************
*
* @brief
*  entry point of a spawned encoder thread
*
* @par Description:
*  The encoder thread dequeues a proc/entropy job from the encoder queue and
*  calls necessary routines.
*
* @param[in] pv_proc
*  Process context corresponding to the thread
*
* @returns  error status
*
* @remarks
*
*******************************************************************************
*/
WORD32 isvce_process_thread(void *pv_proc)
{
    job_t s_job;

    isvce_process_ctxt_t *ps_proc = pv_proc;
    isvce_codec_t *ps_codec = ps_proc->ps_codec;

    IH264_ERROR_T ret = IH264_SUCCESS;

    WORD32 error_status = IH264_SUCCESS;
    WORD32 is_blocking = 0;

    ps_proc->i4_error_code = IH264_SUCCESS;

    while(1)
    {
        /* dequeue a job from the entropy queue */
        {
            WORD32 retval = ithread_mutex_lock(ps_codec->pv_entropy_mutex);

            /* codec context selector */
            WORD32 ctxt_sel = ps_codec->i4_encode_api_call_cnt % MAX_CTXT_SETS;

            volatile UWORD32 *pu4_buf = &ps_codec->au4_entropy_thread_active[ctxt_sel];

            /* have the lock */
            if(retval == 0)
            {
                if(*pu4_buf == 0)
                {
                    /* no entropy threads are active, try dequeuing a job from the entropy
                     * queue */
                    ret = ih264_list_dequeue(ps_proc->pv_entropy_jobq, &s_job, is_blocking);
                    if(IH264_SUCCESS == ret)
                    {
                        *pu4_buf = 1;
                        ithread_mutex_unlock(ps_codec->pv_entropy_mutex);
                        goto WORKER;
                    }
                    else if(is_blocking)
                    {
                        ithread_mutex_unlock(ps_codec->pv_entropy_mutex);
                        break;
                    }
                }
                ithread_mutex_unlock(ps_codec->pv_entropy_mutex);
            }
        }

        /* dequeue a job from the process queue */
        ret = ih264_list_dequeue(ps_proc->pv_proc_jobq, &s_job, 1);
        if(IH264_SUCCESS != ret)
        {
            if(ps_proc->i4_id)
                break;
            else
            {
                is_blocking = 1;
                continue;
            }
        }

    WORKER:
        /* choose appropriate proc context based on proc_base_idx */
        switch(s_job.i4_cmd)
        {
            case CMD_PROCESS:
            {
                ps_proc->i4_mb_cnt = s_job.i2_mb_cnt;
                ps_proc->i4_mb_x = s_job.i2_mb_x;
                ps_proc->i4_mb_y = s_job.i2_mb_y;

                isvce_init_layer_proc_ctxt(ps_proc);

                error_status = isvce_process(ps_proc);

                if(error_status != IH264_SUCCESS)
                {
                    ps_proc->i4_error_code = error_status;
                    return ret;
                }

                break;
            }
            case CMD_ENTROPY:
            {
                ps_proc->s_entropy.i4_mb_x = s_job.i2_mb_x;
                ps_proc->s_entropy.i4_mb_y = s_job.i2_mb_y;
                ps_proc->s_entropy.i4_mb_cnt = s_job.i2_mb_cnt;

                isvce_init_entropy_ctxt(ps_proc);

                error_status = isvce_entropy(ps_proc);

                if(error_status != IH264_SUCCESS)
                {
                    ps_proc->i4_error_code = error_status;
                    return ret;
                }

                break;
            }
            default:
            {
                ps_proc->i4_error_code = IH264_FAIL;
                return ret;
            }
        }
    }

    return ret;
}

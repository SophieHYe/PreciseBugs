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
*  isvce_api.c
*
* @brief
*  Contains api function definitions for H264 encoder
*
* @author
*  ittiam
*
* @par List of Functions:
*  - api_check_struct_sanity()
*  - isvce_codec_update_config()
*  - isvce_set_default_params()
*  - isvce_init()
*  - isvce_get_num_rec()
*  - isvce_fill_num_mem_rec()
*  - isvce_init_mem_rec()
*  - isvce_retrieve_memrec()
*  - isvce_set_flush_mode()
*  - isvce_get_buf_info()
*  - isvce_set_dimensions()
*  - isvce_set_frame_rate()
*  - isvce_set_bit_rate()
*  - isvce_set_frame_type()
*  - isvce_set_qp()
*  - isvce_set_enc_mode()
*  - isvce_set_vbv_params()
*  - isvc_set_air_params()
*  - isvc_set_me_params()
*  - isvc_set_ipe_params()
*  - isvc_set_gop_params()
*  - isvc_set_profile_params()
*  - isvc_set_deblock_params()
*  - isvce_set_num_cores()
*  - isvce_reset()
*  - isvce_ctl()
*  - isvce_api_function()
*
* @remarks
*  None
*
*******************************************************************************
*/

/*****************************************************************************/
/* File Includes                                                             */
/*****************************************************************************/

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
#include "ih264_dpb_mgr.h"
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
#include "ih264e_rc_mem_interface.h"
#include "ih264e_structs.h"
#include "ih264e_utils.h"
#include "ih264e_version.h"
#include "ime.h"
#include "isvce.h"
#include "isvce_cabac.h"
#include "isvce_deblk.h"
#include "isvce_defs.h"
#include "isvce_downscaler.h"
#include "isvce_encode.h"
#include "isvce_encode_header.h"
#include "isvce_ibl_eval.h"
#include "isvce_ilp_mv.h"
#include "isvce_intra_modes_eval.h"
#include "isvce_me.h"
#include "isvce_platform_macros.h"
#include "isvce_rate_control.h"
#include "isvce_rc_mem_interface.h"
#include "isvce_residual_pred.h"
#include "isvce_sub_pic_rc.h"
#include "isvce_utils.h"

/*****************************************************************************/
/* Function Declarations                                                     */
/*****************************************************************************/

/*****************************************************************************/
/* Function Definitions                                                      */
/*****************************************************************************/

/**
*******************************************************************************
*
* @brief
*  Used to test arguments for corresponding API call
*
* @par Description:
*  For each command the arguments are validated
*
* @param[in] ps_handle
*  Codec handle at API level
*
* @param[in] pv_api_ip
*  Pointer to input structure
*
* @param[out] pv_api_op
*  Pointer to output structure
*
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static IV_STATUS_T api_check_struct_sanity(iv_obj_t *ps_handle, void *pv_api_ip, void *pv_api_op,
                                           isvce_api_cmds_t *ps_iv_api_cmds)
{
    WORD32 i, j;

    /* output structure expected by the api call */
    UWORD32 *pu4_api_op = pv_api_op;

    ISVCE_API_COMMAND_TYPE_T e_cmd = ps_iv_api_cmds->e_cmd;
    ISVCE_CONTROL_API_COMMAND_TYPE_T e_ctl_cmd = ps_iv_api_cmds->e_ctl_cmd;

    if(NULL == pv_api_op || NULL == pv_api_ip)
    {
        return (IV_FAIL);
    }

    /* set error code */
    pu4_api_op[1] = 0;

    /* error checks on handle */
    switch(e_cmd)
    {
        case ISVCE_CMD_GET_NUM_MEM_REC:
        case ISVCE_CMD_FILL_NUM_MEM_REC:
        {
            break;
        }

        case ISVCE_CMD_INIT:
        {
            if(ps_handle == NULL)
            {
                *(pu4_api_op + 1) |= 1 << IVE_UNSUPPORTEDPARAM;
                *(pu4_api_op + 1) |= IVE_ERR_HANDLE_NULL;
                return IV_FAIL;
            }

            if(ps_handle->u4_size != sizeof(iv_obj_t))
            {
                *(pu4_api_op + 1) |= 1 << IVE_UNSUPPORTEDPARAM;
                *(pu4_api_op + 1) |= IVE_ERR_HANDLE_STRUCT_SIZE_INCORRECT;
                return IV_FAIL;
            }

            break;
        }
        case ISVCE_CMD_RETRIEVE_MEMREC:
        case ISVCE_CMD_VIDEO_CTL:
        case ISVCE_CMD_VIDEO_ENCODE:
        {
            if(ps_handle == NULL)
            {
                *(pu4_api_op + 1) |= 1 << IVE_UNSUPPORTEDPARAM;
                *(pu4_api_op + 1) |= IVE_ERR_HANDLE_NULL;
                return IV_FAIL;
            }

            if(ps_handle->u4_size != sizeof(iv_obj_t))
            {
                *(pu4_api_op + 1) |= 1 << IVE_UNSUPPORTEDPARAM;
                *(pu4_api_op + 1) |= IVE_ERR_HANDLE_STRUCT_SIZE_INCORRECT;
                return IV_FAIL;
            }

            if(ps_handle->pv_fxns != isvce_api_function)
            {
                *(pu4_api_op + 1) |= 1 << IVE_UNSUPPORTEDPARAM;
                *(pu4_api_op + 1) |= IVE_ERR_API_FUNCTION_PTR_NULL;
                return IV_FAIL;
            }

            if(ps_handle->pv_codec_handle == NULL)
            {
                *(pu4_api_op + 1) |= 1 << IVE_UNSUPPORTEDPARAM;
                *(pu4_api_op + 1) |= IVE_ERR_INVALID_CODEC_HANDLE;
                return IV_FAIL;
            }

            break;
        }
        default:
        {
            *(pu4_api_op + 1) |= 1 << IVE_UNSUPPORTEDPARAM;
            *(pu4_api_op + 1) |= IVE_ERR_INVALID_API_CMD;

            return IV_FAIL;
        }
    }

    /* error checks on input output structures */
    switch(e_cmd)
    {
        case ISVCE_CMD_GET_NUM_MEM_REC:
        {
            isvce_num_mem_rec_ip_t *ps_ip = pv_api_ip;
            isvce_num_mem_rec_op_t *ps_op = pv_api_op;

            ps_op->s_ive_op.u4_error_code = 0;

            if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_num_mem_rec_ip_t))
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IVE_ERR_IP_GET_MEM_REC_API_STRUCT_SIZE_INCORRECT;
                return (IV_FAIL);
            }

            if(ps_op->s_ive_op.u4_size != sizeof(isvce_num_mem_rec_op_t))
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IVE_ERR_OP_GET_MEM_REC_API_STRUCT_SIZE_INCORRECT;
                return (IV_FAIL);
            }

            break;
        }
        case ISVCE_CMD_FILL_NUM_MEM_REC:
        {
            isvce_fill_mem_rec_ip_t *ps_ip = pv_api_ip;
            isvce_fill_mem_rec_op_t *ps_op = pv_api_op;

            iv_mem_rec_t *ps_mem_rec = NULL;

            WORD32 max_wd = ALIGN16(ps_ip->s_ive_ip.u4_max_wd);
            WORD32 max_ht = ALIGN16(ps_ip->s_ive_ip.u4_max_ht);

            ps_op->s_ive_op.u4_error_code = 0;

            if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_fill_mem_rec_ip_t))
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IVE_ERR_IP_FILL_MEM_REC_API_STRUCT_SIZE_INCORRECT;
                return (IV_FAIL);
            }

            if(ps_op->s_ive_op.u4_size != sizeof(isvce_fill_mem_rec_op_t))
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IVE_ERR_OP_FILL_MEM_REC_API_STRUCT_SIZE_INCORRECT;
                return (IV_FAIL);
            }

            if(max_wd < MIN_WD || max_wd > MAX_WD)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IH264E_WIDTH_NOT_SUPPORTED;
                return (IV_FAIL);
            }

            if(max_ht < MIN_HT || max_ht > MAX_HT)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IH264E_HEIGHT_NOT_SUPPORTED;
                return (IV_FAIL);
            }

            /* verify number of mem rec ptr */
            if(NULL == ps_ip->s_ive_ip.ps_mem_rec)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IVE_ERR_FILL_NUM_MEM_RECS_POINTER_NULL;
                return (IV_FAIL);
            }

            /* verify number of mem records */
            if(ps_ip->s_ive_ip.u4_num_mem_rec != ISVCE_MEM_REC_CNT)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IVE_ERR_NUM_MEM_REC_NOT_SUFFICIENT;
                return IV_FAIL;
            }

            /* check mem records sizes are correct */
            ps_mem_rec = ps_ip->s_ive_ip.ps_mem_rec;
            for(i = 0; i < ISVCE_MEM_REC_CNT; i++)
            {
                if(ps_mem_rec[i].u4_size != sizeof(iv_mem_rec_t))
                {
                    ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                    ps_op->s_ive_op.u4_error_code |= IVE_ERR_MEM_REC_STRUCT_SIZE_INCORRECT;
                    return IV_FAIL;
                }
            }

            break;
        }
        case ISVCE_CMD_INIT:
        {
            isvce_init_ip_t *ps_ip = pv_api_ip;
            isvce_init_op_t *ps_op = pv_api_op;

            iv_mem_rec_t *ps_mem_rec = NULL;

            WORD32 max_wd = ALIGN16(ps_ip->s_ive_ip.u4_max_wd);
            WORD32 max_ht = ALIGN16(ps_ip->s_ive_ip.u4_max_ht);
            WORD32 wd = ALIGN16(ps_ip->u4_wd);
            WORD32 ht = ALIGN16(ps_ip->u4_ht);

            ps_op->s_ive_op.u4_error_code = 0;

            if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_init_ip_t))
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IVE_ERR_IP_INIT_API_STRUCT_SIZE_INCORRECT;
                return (IV_FAIL);
            }

            if(ps_op->s_ive_op.u4_size != sizeof(isvce_init_op_t))
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IVE_ERR_OP_INIT_API_STRUCT_SIZE_INCORRECT;
                return (IV_FAIL);
            }

            if(max_wd < MIN_WD || max_wd > MAX_WD)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IH264E_WIDTH_NOT_SUPPORTED;
                return (IV_FAIL);
            }

            if(max_ht < MIN_HT || max_ht > MAX_HT)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IH264E_HEIGHT_NOT_SUPPORTED;
                return (IV_FAIL);
            }

            if(ps_ip->s_ive_ip.u4_max_ref_cnt > MAX_REF_PIC_CNT ||
               ps_ip->s_ive_ip.u4_max_ref_cnt < MIN_REF_PIC_CNT)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IH264E_NUM_REF_UNSUPPORTED;
                return (IV_FAIL);
            }

            if(ps_ip->s_ive_ip.u4_max_reorder_cnt != 0)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IH264E_NUM_REORDER_UNSUPPORTED;
                return (IV_FAIL);
            }

            if((ps_ip->s_ive_ip.u4_max_level != IH264_LEVEL_10) &&
               (ps_ip->s_ive_ip.u4_max_level != IH264_LEVEL_1B) &&
               (ps_ip->s_ive_ip.u4_max_level != IH264_LEVEL_11) &&
               (ps_ip->s_ive_ip.u4_max_level != IH264_LEVEL_12) &&
               (ps_ip->s_ive_ip.u4_max_level != IH264_LEVEL_13) &&
               (ps_ip->s_ive_ip.u4_max_level != IH264_LEVEL_20) &&
               (ps_ip->s_ive_ip.u4_max_level != IH264_LEVEL_21) &&
               (ps_ip->s_ive_ip.u4_max_level != IH264_LEVEL_22) &&
               (ps_ip->s_ive_ip.u4_max_level != IH264_LEVEL_30) &&
               (ps_ip->s_ive_ip.u4_max_level != IH264_LEVEL_31) &&
               (ps_ip->s_ive_ip.u4_max_level != IH264_LEVEL_32) &&
               (ps_ip->s_ive_ip.u4_max_level != IH264_LEVEL_40) &&
               (ps_ip->s_ive_ip.u4_max_level != IH264_LEVEL_41) &&
               (ps_ip->s_ive_ip.u4_max_level != IH264_LEVEL_42) &&
               (ps_ip->s_ive_ip.u4_max_level != IH264_LEVEL_50) &&
               (ps_ip->s_ive_ip.u4_max_level != IH264_LEVEL_51))
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IH264E_CODEC_LEVEL_NOT_SUPPORTED;
                return (IV_FAIL);
            }

            if(ps_ip->s_ive_ip.e_inp_color_fmt != IV_YUV_420P)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IH264E_INPUT_CHROMA_FORMAT_NOT_SUPPORTED;
                return (IV_FAIL);
            }

            if(ps_ip->s_ive_ip.e_recon_color_fmt != IV_YUV_420P)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IH264E_RECON_CHROMA_FORMAT_NOT_SUPPORTED;
                return (IV_FAIL);
            }

            if((ps_ip->s_ive_ip.e_rc_mode != IVE_RC_NONE) &&
               (ps_ip->s_ive_ip.e_rc_mode != IVE_RC_STORAGE) &&
               (ps_ip->s_ive_ip.e_rc_mode != IVE_RC_CBR_NON_LOW_DELAY))
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IH264E_RATE_CONTROL_MODE_NOT_SUPPORTED;
                return (IV_FAIL);
            }

            if(ps_ip->s_ive_ip.u4_max_framerate > DEFAULT_MAX_FRAMERATE)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IH264E_FRAME_RATE_NOT_SUPPORTED;
                return (IV_FAIL);
            }

            for(i = 0; i < ps_ip->s_svc_inp_params.u1_num_spatial_layers; i++)
            {
                if(ps_ip->pu4_max_bitrate[i] > DEFAULT_MAX_BITRATE)
                {
                    ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                    ps_op->s_ive_op.u4_error_code |= IH264E_BITRATE_NOT_SUPPORTED;
                    return (IV_FAIL);
                }
            }

            if(ps_ip->s_ive_ip.u4_num_bframes > SVC_MAX_NUM_BFRAMES)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IH264E_BFRAMES_NOT_SUPPORTED;
                return (IV_FAIL);
            }

            if(ps_ip->s_ive_ip.u4_num_bframes && (ps_ip->s_ive_ip.u4_max_ref_cnt < 2))
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IH264E_BFRAMES_NOT_SUPPORTED;
                return (IV_FAIL);
            }

            if(ps_ip->s_ive_ip.e_content_type != IV_PROGRESSIVE)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IH264E_CONTENT_TYPE_NOT_SUPPORTED;
                return (IV_FAIL);
            }

            if(ps_ip->s_ive_ip.u4_max_srch_rng_x > DEFAULT_MAX_SRCH_RANGE_X)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IH264E_HORIZONTAL_SEARCH_RANGE_NOT_SUPPORTED;
                return (IV_FAIL);
            }

            if(ps_ip->s_ive_ip.u4_max_srch_rng_y > DEFAULT_MAX_SRCH_RANGE_Y)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IH264E_VERTICAL_SEARCH_RANGE_NOT_SUPPORTED;
                return (IV_FAIL);
            }

            if(ps_ip->s_ive_ip.e_slice_mode != IVE_SLICE_MODE_NONE)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IH264E_SLICE_TYPE_INPUT_INVALID;
                return (IV_FAIL);
            }

            if(NULL == ps_ip->s_ive_ip.ps_mem_rec)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IVE_ERR_FILL_NUM_MEM_RECS_POINTER_NULL;
                return (IV_FAIL);
            }

            /* verify number of mem records */
            if(ps_ip->s_ive_ip.u4_num_mem_rec != ISVCE_MEM_REC_CNT)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IVE_ERR_NUM_MEM_REC_NOT_SUFFICIENT;
                return (IV_FAIL);
            }

            ps_mem_rec = ps_ip->s_ive_ip.ps_mem_rec;

            /* check memrecords sizes are correct */
            for(i = 0; i < ((WORD32) ps_ip->s_ive_ip.u4_num_mem_rec); i++)
            {
                if(ps_mem_rec[i].u4_size != sizeof(iv_mem_rec_t))
                {
                    ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                    ps_op->s_ive_op.u4_error_code |= IVE_ERR_MEM_REC_STRUCT_SIZE_INCORRECT;
                    return IV_FAIL;
                }

                /* check memrecords pointers are not NULL */
                if(ps_mem_rec[i].pv_base == NULL)
                {
                    ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                    ps_op->s_ive_op.u4_error_code |= IVE_ERR_MEM_REC_BASE_POINTER_NULL;
                    return IV_FAIL;
                }
            }

            /* verify memtabs for overlapping regions */
            {
                void *start[ISVCE_MEM_REC_CNT];
                void *end[ISVCE_MEM_REC_CNT];

                start[0] = (ps_mem_rec[0].pv_base);
                end[0] = ((UWORD8 *) ps_mem_rec[0].pv_base) + ps_mem_rec[0].u4_mem_size - 1;

                for(i = 1; i < ISVCE_MEM_REC_CNT; i++)
                {
                    /* This array is populated to check memtab overlap */
                    start[i] = (ps_mem_rec[i].pv_base);
                    end[i] = ((UWORD8 *) ps_mem_rec[i].pv_base) + ps_mem_rec[i].u4_mem_size - 1;

                    for(j = 0; j < i; j++)
                    {
                        if((start[i] >= start[j]) && (start[i] <= end[j]))
                        {
                            ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->s_ive_op.u4_error_code |= IVE_ERR_MEM_REC_OVERLAP_ERR;
                            return IV_FAIL;
                        }

                        if((end[i] >= start[j]) && (end[i] <= end[j]))
                        {
                            ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->s_ive_op.u4_error_code |= IVE_ERR_MEM_REC_OVERLAP_ERR;
                            return IV_FAIL;
                        }

                        if((start[i] < start[j]) && (end[i] > end[j]))
                        {
                            ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->s_ive_op.u4_error_code |= IVE_ERR_MEM_REC_OVERLAP_ERR;
                            return IV_FAIL;
                        }
                    }
                }
            }

            /* re-validate mem records with init config */
            {
                /* mem records */
                iv_mem_rec_t s_mem_rec_ittiam_api[ISVCE_MEM_REC_CNT];

                /* api interface structs */
                isvce_fill_mem_rec_ip_t s_ip;
                isvce_fill_mem_rec_op_t s_op;

                /* error status */
                IV_STATUS_T e_status;

                /* temp var */
                WORD32 i;

                isvce_api_cmds_t s_api_cmds = {ISVCE_CMD_FILL_NUM_MEM_REC, ISVCE_CMD_CT_NA};

                s_ip.s_ive_ip.u4_size = sizeof(isvce_fill_mem_rec_ip_t);
                s_op.s_ive_op.u4_size = sizeof(isvce_fill_mem_rec_op_t);

                s_ip.s_ive_ip.ps_mem_rec = s_mem_rec_ittiam_api;
                s_ip.s_ive_ip.u4_max_wd = max_wd;
                s_ip.s_ive_ip.u4_max_ht = max_ht;
                s_ip.u4_wd = wd;
                s_ip.u4_ht = ht;
                s_ip.s_ive_ip.u4_num_mem_rec = ps_ip->s_ive_ip.u4_num_mem_rec;
                s_ip.s_ive_ip.u4_max_level = ps_ip->s_ive_ip.u4_max_level;
                s_ip.s_ive_ip.u4_max_ref_cnt = ps_ip->s_ive_ip.u4_max_ref_cnt;
                s_ip.s_ive_ip.u4_max_reorder_cnt = ps_ip->s_ive_ip.u4_max_reorder_cnt;
                s_ip.s_ive_ip.e_color_format = ps_ip->s_ive_ip.e_inp_color_fmt;
                s_ip.s_ive_ip.u4_max_srch_rng_x = ps_ip->s_ive_ip.u4_max_srch_rng_x;
                s_ip.s_ive_ip.u4_max_srch_rng_y = ps_ip->s_ive_ip.u4_max_srch_rng_y;

                s_ip.s_svc_inp_params = ps_ip->s_svc_inp_params;

                for(i = 0; i < ISVCE_MEM_REC_CNT; i++)
                {
                    s_mem_rec_ittiam_api[i].u4_size = sizeof(iv_mem_rec_t);
                }

                /* fill mem records */
                e_status = isvce_api_function(NULL, (void *) &s_ip, (void *) &s_op, &s_api_cmds);

                if(IV_FAIL == e_status)
                {
                    ps_op->s_ive_op.u4_error_code = s_op.s_ive_op.u4_error_code;
                    return (IV_FAIL);
                }

                /* verify mem records */
                for(i = 0; i < ISVCE_MEM_REC_CNT; i++)
                {
                    if(ps_mem_rec[i].u4_mem_size < s_mem_rec_ittiam_api[i].u4_mem_size)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IVE_ERR_MEM_REC_INSUFFICIENT_SIZE;

                        return IV_FAIL;
                    }

                    if(ps_mem_rec[i].u4_mem_alignment != s_mem_rec_ittiam_api[i].u4_mem_alignment)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IVE_ERR_MEM_REC_ALIGNMENT_ERR;

                        return IV_FAIL;
                    }

                    if(ps_mem_rec[i].e_mem_type != s_mem_rec_ittiam_api[i].e_mem_type)
                    {
                        UWORD32 check = IV_SUCCESS;
                        UWORD32 diff =
                            s_mem_rec_ittiam_api[i].e_mem_type - ps_mem_rec[i].e_mem_type;

                        if((ps_mem_rec[i].e_mem_type <= IV_EXTERNAL_CACHEABLE_SCRATCH_MEM) &&
                           (s_mem_rec_ittiam_api[i].e_mem_type >=
                            IV_INTERNAL_NONCACHEABLE_PERSISTENT_MEM))
                        {
                            check = IV_FAIL;
                        }

                        if(3 != (s_mem_rec_ittiam_api[i].e_mem_type % 4))
                        {
                            /* It is not IV_EXTERNAL_NONCACHEABLE_PERSISTENT_MEM or
                             * IV_EXTERNAL_CACHEABLE_PERSISTENT_MEM */

                            if((diff < 1) || (diff > 3))
                            {
                                /* Difference between 1 and 3 is okay for all cases other than
                                 * the two filtered with the MOD condition above */
                                check = IV_FAIL;
                            }
                        }
                        else
                        {
                            if(diff == 1)
                            {
                                /* This particular case is when codec asked for External
                                 * Persistent, but got Internal Scratch */
                                check = IV_FAIL;
                            }
                            if((diff != 2) && (diff != 3))
                            {
                                check = IV_FAIL;
                            }
                        }

                        if(check == IV_FAIL)
                        {
                            ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->s_ive_op.u4_error_code |= IVE_ERR_MEM_REC_INCORRECT_TYPE;

                            return IV_FAIL;
                        }
                    }
                }
            }

            break;
        }
        case ISVCE_CMD_RETRIEVE_MEMREC:
        {
            isvce_retrieve_mem_rec_ip_t *ps_ip = pv_api_ip;
            isvce_retrieve_mem_rec_op_t *ps_op = pv_api_op;

            iv_mem_rec_t *ps_mem_rec = NULL;

            ps_op->s_ive_op.u4_error_code = 0;

            if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_retrieve_mem_rec_ip_t))
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |=
                    IVE_ERR_IP_RETRIEVE_MEM_REC_API_STRUCT_SIZE_INCORRECT;
                return (IV_FAIL);
            }

            if(ps_op->s_ive_op.u4_size != sizeof(isvce_retrieve_mem_rec_op_t))
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |=
                    IVE_ERR_OP_RETRIEVE_MEM_REC_API_STRUCT_SIZE_INCORRECT;
                return (IV_FAIL);
            }

            if(NULL == ps_ip->s_ive_ip.ps_mem_rec)
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IVE_ERR_FILL_NUM_MEM_RECS_POINTER_NULL;
                return (IV_FAIL);
            }

            ps_mem_rec = ps_ip->s_ive_ip.ps_mem_rec;

            /* check memrecords sizes are correct */
            for(i = 0; i < ISVCE_MEM_REC_CNT; i++)
            {
                if(ps_mem_rec[i].u4_size != sizeof(iv_mem_rec_t))
                {
                    ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                    ps_op->s_ive_op.u4_error_code |= IVE_ERR_MEM_REC_STRUCT_SIZE_INCORRECT;
                    return IV_FAIL;
                }
            }

            break;
        }
        case ISVCE_CMD_VIDEO_ENCODE:
        {
            isvce_video_encode_ip_t *ps_ip = pv_api_ip;
            isvce_video_encode_op_t *ps_op = pv_api_op;

            if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_video_encode_ip_t))
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IVE_ERR_IP_ENCODE_API_STRUCT_SIZE_INCORRECT;
                return (IV_FAIL);
            }

            if(ps_op->s_ive_op.u4_size != sizeof(isvce_video_encode_op_t))
            {
                ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                ps_op->s_ive_op.u4_error_code |= IVE_ERR_OP_ENCODE_API_STRUCT_SIZE_INCORRECT;
                return (IV_FAIL);
            }

            break;
        }
        case ISVCE_CMD_VIDEO_CTL:
        {
            switch(e_ctl_cmd)
            {
                case ISVCE_CMD_CTL_GET_ENC_FRAME_DIMENSIONS:
                {
                    break;
                }
                case ISVCE_CMD_CTL_SETDEFAULT:
                {
                    isvce_ctl_setdefault_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_setdefault_op_t *ps_op = pv_api_op;

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_setdefault_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_SETDEF_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_setdefault_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_SETDEF_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    break;
                }
                case ISVCE_CMD_CTL_GETBUFINFO:
                {
                    isvce_ctl_getbufinfo_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_getbufinfo_op_t *ps_op = pv_api_op;

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_getbufinfo_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_GETBUFINFO_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_getbufinfo_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_GETBUFINFO_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_ip->s_ive_ip.u4_max_wd < MIN_WD)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_WIDTH_NOT_SUPPORTED;
                        return (IV_FAIL);
                    }

                    if(ps_ip->s_ive_ip.u4_max_ht < MIN_HT)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_HEIGHT_NOT_SUPPORTED;
                        return (IV_FAIL);
                    }

                    if((ps_ip->s_ive_ip.e_inp_color_fmt != IV_YUV_420P) &&
                       (ps_ip->s_ive_ip.e_inp_color_fmt != IV_YUV_422ILE) &&
                       (ps_ip->s_ive_ip.e_inp_color_fmt != IV_YUV_420SP_UV) &&
                       (ps_ip->s_ive_ip.e_inp_color_fmt != IV_YUV_420SP_VU))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_INPUT_CHROMA_FORMAT_NOT_SUPPORTED;
                        return (IV_FAIL);
                    }

                    break;
                }
                case ISVCE_CMD_CTL_GETVERSION:
                {
                    isvce_ctl_getversioninfo_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_getversioninfo_op_t *ps_op = pv_api_op;

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_getversioninfo_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_GETVERSION_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_getversioninfo_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_GETVERSION_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_ip->s_ive_ip.pu1_version == NULL)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IVE_ERR_CTL_GET_VERSION_BUFFER_IS_NULL;
                        return IV_FAIL;
                    }

                    break;
                }
                case ISVCE_CMD_CTL_FLUSH:
                {
                    isvce_ctl_flush_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_flush_op_t *ps_op = pv_api_op;

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_flush_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_FLUSH_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_flush_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_FLUSH_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    break;
                }
                case ISVCE_CMD_CTL_RESET:
                {
                    isvce_ctl_reset_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_reset_op_t *ps_op = pv_api_op;

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_reset_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_RESET_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_reset_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_RESET_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_NUM_CORES:
                {
                    isvce_ctl_set_num_cores_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_set_num_cores_op_t *ps_op = pv_api_op;

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_set_num_cores_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_SETCORES_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_set_num_cores_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_SETCORES_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if((ps_ip->s_ive_ip.u4_num_cores < 1) ||
                       (ps_ip->s_ive_ip.u4_num_cores > MAX_NUM_CORES))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_NUM_CORES;
                        return IV_FAIL;
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_DIMENSIONS:
                {
                    isvce_codec_t *ps_codec = (isvce_codec_t *) (ps_handle->pv_codec_handle);

                    isvce_ctl_set_dimensions_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_set_dimensions_op_t *ps_op = pv_api_op;

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_set_dimensions_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_SETDIM_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_set_dimensions_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_SETDIM_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_ip->s_ive_ip.u4_wd < MIN_WD)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_WIDTH_NOT_SUPPORTED;
                        return (IV_FAIL);
                    }

                    if(ps_ip->s_ive_ip.u4_wd > ps_codec->s_cfg.u4_max_wd)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_WIDTH_NOT_SUPPORTED;
                        return (IV_FAIL);
                    }

                    if(ps_ip->s_ive_ip.u4_ht < MIN_HT)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_HEIGHT_NOT_SUPPORTED;
                        return (IV_FAIL);
                    }

                    if(ps_ip->s_ive_ip.u4_ht > ps_codec->s_cfg.u4_max_ht)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_HEIGHT_NOT_SUPPORTED;
                        return (IV_FAIL);
                    }

                    if(ps_ip->s_ive_ip.u4_wd & 1)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_WIDTH_NOT_SUPPORTED;
                        return (IV_FAIL);
                    }

                    if(ps_ip->s_ive_ip.u4_ht & 1)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_HEIGHT_NOT_SUPPORTED;
                        return (IV_FAIL);
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_FRAMERATE:
                {
                    isvce_ctl_set_frame_rate_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_set_frame_rate_op_t *ps_op = pv_api_op;

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_set_frame_rate_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_SETFRAMERATE_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_set_frame_rate_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_SETFRAMERATE_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(((ps_ip->s_ive_ip.u4_src_frame_rate * 1000) > DEFAULT_MAX_FRAMERATE) ||
                       ((ps_ip->s_ive_ip.u4_tgt_frame_rate * 1000) > DEFAULT_MAX_FRAMERATE))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_FRAME_RATE_NOT_SUPPORTED;
                        return (IV_FAIL);
                    }

                    if((ps_ip->s_ive_ip.u4_src_frame_rate == 0) ||
                       (ps_ip->s_ive_ip.u4_tgt_frame_rate == 0))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_FRAME_RATE_NOT_SUPPORTED;
                        return (IV_FAIL);
                    }

                    if(ps_ip->s_ive_ip.u4_tgt_frame_rate > ps_ip->s_ive_ip.u4_src_frame_rate)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IH264E_TGT_FRAME_RATE_EXCEEDS_SRC_FRAME_RATE;
                        return (IV_FAIL);
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_BITRATE:
                {
                    isvce_ctl_set_bitrate_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_set_bitrate_op_t *ps_op = pv_api_op;

                    isvce_codec_t *ps_codec = (isvce_codec_t *) (ps_handle->pv_codec_handle);

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_set_bitrate_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_SETBITRATE_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_set_bitrate_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_SETBITRATE_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    for(i = 0; i < ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers; i++)
                    {
                        if((ps_ip->pu4_target_bitrate[i] > DEFAULT_MAX_BITRATE) ||
                           (ps_ip->pu4_target_bitrate[i] == 0))
                        {
                            ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->s_ive_op.u4_error_code |= IH264E_BITRATE_NOT_SUPPORTED;
                            return (IV_FAIL);
                        }
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_FRAMETYPE:
                {
                    isvce_ctl_set_frame_type_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_set_frame_type_op_t *ps_op = pv_api_op;

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_set_frame_type_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_SETFRAMETYPE_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_set_frame_type_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_SETFRAMETYPE_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if((ps_ip->s_ive_ip.e_frame_type != IV_I_FRAME) &&
                       (ps_ip->s_ive_ip.e_frame_type != IV_P_FRAME) &&
                       (ps_ip->s_ive_ip.e_frame_type != IV_IDR_FRAME))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_FORCE_FRAME_INPUT;
                        return IV_FAIL;
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_ME_PARAMS:
                {
                    isvce_codec_t *ps_codec = (isvce_codec_t *) (ps_handle->pv_codec_handle);

                    isvce_ctl_set_me_params_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_set_me_params_op_t *ps_op = pv_api_op;

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_set_me_params_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_SETMEPARAMS_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_set_me_params_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_SETMEPARAMS_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_ip->s_ive_ip.u4_me_speed_preset != DMND_SRCH)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_ME_SPEED_PRESET;
                        return IV_FAIL;
                    }

                    if((ps_ip->s_ive_ip.u4_enable_hpel != 0) &&
                       (ps_ip->s_ive_ip.u4_enable_hpel != 1))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_HALFPEL_OPTION;
                        return IV_FAIL;
                    }

                    if((ps_ip->s_ive_ip.u4_enable_qpel != 0) &&
                       (ps_ip->s_ive_ip.u4_enable_qpel != 1))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_QPEL_OPTION;
                        return IV_FAIL;
                    }

                    if((ps_ip->s_ive_ip.u4_enable_fast_sad != 0))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_FAST_SAD_OPTION;
                        return IV_FAIL;
                    }

                    if(ps_ip->s_ive_ip.u4_enable_alt_ref > 0)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_ALT_REF_OPTION;
                        return IV_FAIL;
                    }

                    if(ps_ip->s_ive_ip.u4_srch_rng_x > ps_codec->s_cfg.u4_max_srch_rng_x)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IH264E_HORIZONTAL_SEARCH_RANGE_NOT_SUPPORTED;
                        return (IV_FAIL);
                    }

                    if(ps_ip->s_ive_ip.u4_srch_rng_y > ps_codec->s_cfg.u4_max_srch_rng_y)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_VERTICAL_SEARCH_RANGE_NOT_SUPPORTED;
                        return (IV_FAIL);
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_IPE_PARAMS:
                {
                    isvce_ctl_set_ipe_params_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_set_ipe_params_op_t *ps_op = pv_api_op;

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_set_ipe_params_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_SETIPEPARAMS_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_set_ipe_params_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_SETIPEPARAMS_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if((ps_ip->s_ive_ip.u4_enable_intra_4x4 != 0) &&
                       (ps_ip->s_ive_ip.u4_enable_intra_4x4 != 1))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_INTRA4x4_OPTION;
                        return IV_FAIL;
                    }

                    if((ps_ip->s_ive_ip.u4_enc_speed_preset != IVE_CONFIG) &&
                       (ps_ip->s_ive_ip.u4_enc_speed_preset != IVE_SLOWEST) &&
                       (ps_ip->s_ive_ip.u4_enc_speed_preset != IVE_NORMAL) &&
                       (ps_ip->s_ive_ip.u4_enc_speed_preset != IVE_FAST) &&
                       (ps_ip->s_ive_ip.u4_enc_speed_preset != IVE_HIGH_SPEED) &&
                       (ps_ip->s_ive_ip.u4_enc_speed_preset != IVE_FASTEST))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_ENC_SPEED_PRESET;
                        return IV_FAIL;
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_GOP_PARAMS:
                {
                    isvce_ctl_set_gop_params_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_set_gop_params_op_t *ps_op = pv_api_op;

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_set_gop_params_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_SETGOPPARAMS_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_set_gop_params_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_SETGOPPARAMS_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if((ps_ip->s_ive_ip.u4_i_frm_interval < DEFAULT_MIN_INTRA_FRAME_RATE) ||
                       (ps_ip->s_ive_ip.u4_i_frm_interval > DEFAULT_MAX_INTRA_FRAME_RATE))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_INTRA_FRAME_INTERVAL;
                        return IV_FAIL;
                    }

                    if((ps_ip->s_ive_ip.u4_idr_frm_interval < DEFAULT_MIN_INTRA_FRAME_RATE) ||
                       (ps_ip->s_ive_ip.u4_idr_frm_interval > DEFAULT_MAX_INTRA_FRAME_RATE))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_IDR_FRAME_INTERVAL;
                        return IV_FAIL;
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_DEBLOCK_PARAMS:
                {
                    isvce_ctl_set_deblock_params_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_set_deblock_params_op_t *ps_op = pv_api_op;

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_set_deblock_params_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_SETDEBLKPARAMS_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_set_deblock_params_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_SETDEBLKPARAMS_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if((ps_ip->s_ive_ip.u4_disable_deblock_level != DISABLE_DEBLK_LEVEL_0) &&
                       (ps_ip->s_ive_ip.u4_disable_deblock_level != DISABLE_DEBLK_LEVEL_2) &&
                       (ps_ip->s_ive_ip.u4_disable_deblock_level != DISABLE_DEBLK_LEVEL_3) &&
                       (ps_ip->s_ive_ip.u4_disable_deblock_level != DISABLE_DEBLK_LEVEL_4))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_DEBLOCKING_TYPE_INPUT;
                        return IV_FAIL;
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_QP:
                {
                    isvce_ctl_set_qp_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_set_qp_op_t *ps_op = pv_api_op;

                    isvce_codec_t *ps_codec = (isvce_codec_t *) (ps_handle->pv_codec_handle);

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_set_qp_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_SETQPPARAMS_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_set_qp_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_SETQPPARAMS_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    for(i = 0; i < ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers; i++)
                    {
                        if((ps_ip->pu4_i_qp_max[i] > MAX_H264_QP) ||
                           (ps_ip->pu4_p_qp_max[i] > MAX_H264_QP) ||
                           (ps_ip->pu4_b_qp_max[i] > MAX_H264_QP))
                        {
                            ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_MAX_FRAME_QP;
                            return IV_FAIL;
                        }

                        /* We donot support QP < 4 */
                        if((((WORD32) ps_ip->pu4_i_qp_min[i]) < MIN_H264_QP) ||
                           ((WORD32) ps_ip->pu4_p_qp_min[i] < MIN_H264_QP) ||
                           (((WORD32) ps_ip->pu4_b_qp_min[i]) < MIN_H264_QP) ||
                           (ps_ip->pu4_i_qp_min[i] > ps_ip->pu4_i_qp_max[i]) ||
                           (ps_ip->pu4_p_qp_min[i] > ps_ip->pu4_p_qp_max[i]) ||
                           (ps_ip->pu4_b_qp_min[i] > ps_ip->pu4_b_qp_max[i]))
                        {
                            ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_MIN_FRAME_QP;
                            return IV_FAIL;
                        }

                        if((ps_ip->pu4_i_qp[i] > ps_ip->pu4_i_qp_max[i]) ||
                           (ps_ip->pu4_p_qp[i] > ps_ip->pu4_p_qp_max[i]) ||
                           (ps_ip->pu4_b_qp[i] > ps_ip->pu4_b_qp_max[i]))
                        {
                            ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_INIT_QP;
                            return IV_FAIL;
                        }

                        if((ps_ip->pu4_i_qp[i] < ps_ip->pu4_i_qp_min[i]) ||
                           (ps_ip->pu4_p_qp[i] < ps_ip->pu4_p_qp_min[i]) ||
                           (ps_ip->pu4_b_qp[i] < ps_ip->pu4_b_qp_min[i]))
                        {
                            ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_INIT_QP;
                            return IV_FAIL;
                        }
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_VUI_PARAMS:
                {
                    isvce_vui_ip_t *ps_ip = pv_api_ip;
                    isvce_vui_op_t *ps_op = pv_api_op;

                    if(ps_ip->u4_size != sizeof(isvce_vui_ip_t))
                    {
                        ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->u4_error_code |= IVE_ERR_IP_CTL_SET_VUI_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->u4_size != sizeof(isvce_vui_op_t))
                    {
                        ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->u4_error_code |= IVE_ERR_OP_CTL_SET_VUI_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_SEI_MDCV_PARAMS:
                {
                    isvce_ctl_set_sei_mdcv_params_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_set_sei_mdcv_params_op_t *ps_op = pv_api_op;

                    if(ps_ip->u4_size != sizeof(isvce_ctl_set_sei_mdcv_params_ip_t))
                    {
                        ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->u4_error_code |= IVE_ERR_IP_CTL_SET_SEI_MDCV_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->u4_size != sizeof(isvce_ctl_set_sei_mdcv_params_op_t))
                    {
                        ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->u4_error_code |= IVE_ERR_OP_CTL_SET_SEI_MDCV_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if((ps_ip->u1_sei_mdcv_params_present_flag != 0) &&
                       (ps_ip->u1_sei_mdcv_params_present_flag) != 1)
                    {
                        ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->u4_error_code |= IH264E_INVALID_SEI_MDCV_PARAMS;
                        return IV_FAIL;
                    }

                    if(1 == ps_ip->u1_sei_mdcv_params_present_flag)
                    {
                        /* Check values for u2_display_primaries_x and
                         * u2_display_primaries_y */
                        for(i = 0; i < 3; i++)
                        {
                            if((ps_ip->au2_display_primaries_x[i] >
                                DISPLAY_PRIMARIES_X_UPPER_LIMIT) ||
                               (ps_ip->au2_display_primaries_x[i] <
                                DISPLAY_PRIMARIES_X_LOWER_LIMIT) ||
                               ((ps_ip->au2_display_primaries_x[i] %
                                 DISPLAY_PRIMARIES_X_DIVISION_FACTOR) != 0))
                            {
                                ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                                ps_op->u4_error_code |= IH264E_INVALID_SEI_MDCV_PARAMS;
                                return IV_FAIL;
                            }

                            if((ps_ip->au2_display_primaries_y[i] >
                                DISPLAY_PRIMARIES_Y_UPPER_LIMIT) ||
                               (ps_ip->au2_display_primaries_y[i] <
                                DISPLAY_PRIMARIES_Y_LOWER_LIMIT) ||
                               ((ps_ip->au2_display_primaries_y[i] %
                                 DISPLAY_PRIMARIES_Y_DIVISION_FACTOR) != 0))
                            {
                                ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                                ps_op->u4_error_code |= IH264E_INVALID_SEI_MDCV_PARAMS;
                                return IV_FAIL;
                            }
                        }

                        if((ps_ip->u2_white_point_x > WHITE_POINT_X_UPPER_LIMIT) ||
                           (ps_ip->u2_white_point_x < WHITE_POINT_X_LOWER_LIMIT) ||
                           ((ps_ip->u2_white_point_x % WHITE_POINT_X_DIVISION_FACTOR) != 0))
                        {
                            ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->u4_error_code |= IH264E_INVALID_SEI_MDCV_PARAMS;
                            return IV_FAIL;
                        }

                        if((ps_ip->u2_white_point_y > WHITE_POINT_Y_UPPER_LIMIT) ||
                           (ps_ip->u2_white_point_y < WHITE_POINT_Y_LOWER_LIMIT) ||
                           ((ps_ip->u2_white_point_y % WHITE_POINT_Y_DIVISION_FACTOR) != 0))
                        {
                            ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->u4_error_code |= IH264E_INVALID_SEI_MDCV_PARAMS;
                            return IV_FAIL;
                        }

                        if((ps_ip->u4_max_display_mastering_luminance >
                            MAX_DISPLAY_MASTERING_LUMINANCE_UPPER_LIMIT) ||
                           (ps_ip->u4_max_display_mastering_luminance <
                            MAX_DISPLAY_MASTERING_LUMINANCE_LOWER_LIMIT) ||
                           ((ps_ip->u4_max_display_mastering_luminance %
                             MAX_DISPLAY_MASTERING_LUMINANCE_DIVISION_FACTOR) != 0))
                        {
                            ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->u4_error_code |= IH264E_INVALID_SEI_MDCV_PARAMS;
                            return IV_FAIL;
                        }

                        if((ps_ip->u4_min_display_mastering_luminance >
                            MIN_DISPLAY_MASTERING_LUMINANCE_UPPER_LIMIT) ||
                           (ps_ip->u4_min_display_mastering_luminance <
                            MIN_DISPLAY_MASTERING_LUMINANCE_LOWER_LIMIT))
                        {
                            ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->u4_error_code |= IH264E_INVALID_SEI_MDCV_PARAMS;
                            return IV_FAIL;
                        }

                        if(ps_ip->u4_max_display_mastering_luminance <=
                           ps_ip->u4_min_display_mastering_luminance)
                        {
                            ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->u4_error_code |= IH264E_INVALID_SEI_MDCV_PARAMS;
                            return IV_FAIL;
                        }
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_SEI_CLL_PARAMS:
                {
                    isvce_ctl_set_sei_cll_params_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_set_sei_cll_params_op_t *ps_op = pv_api_op;

                    if(ps_ip->u4_size != sizeof(isvce_ctl_set_sei_cll_params_ip_t))
                    {
                        ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->u4_error_code |= IVE_ERR_IP_CTL_SET_SEI_CLL_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->u4_size != sizeof(isvce_ctl_set_sei_cll_params_op_t))
                    {
                        ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->u4_error_code |= IVE_ERR_OP_CTL_SET_SEI_CLL_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if((ps_ip->u1_sei_cll_params_present_flag != 0) &&
                       (ps_ip->u1_sei_cll_params_present_flag != 1))
                    {
                        ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->u4_error_code |= IH264E_INVALID_SEI_CLL_PARAMS;
                        return IV_FAIL;
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_SEI_AVE_PARAMS:
                {
                    isvce_ctl_set_sei_ave_params_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_set_sei_ave_params_op_t *ps_op = pv_api_op;

                    if(ps_ip->u4_size != sizeof(isvce_ctl_set_sei_ave_params_ip_t))
                    {
                        ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->u4_error_code |= IVE_ERR_IP_CTL_SET_SEI_AVE_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->u4_size != sizeof(isvce_ctl_set_sei_ave_params_op_t))
                    {
                        ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->u4_error_code |= IVE_ERR_OP_CTL_SET_SEI_AVE_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if((ps_ip->u1_sei_ave_params_present_flag != 0) &&
                       (ps_ip->u1_sei_ave_params_present_flag != 1))
                    {
                        ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->u4_error_code |= IH264E_INVALID_SEI_AVE_PARAMS;
                        return IV_FAIL;
                    }

                    if(1 == ps_ip->u1_sei_ave_params_present_flag)
                    {
                        if((0 == ps_ip->u4_ambient_illuminance))
                        {
                            ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->u4_error_code |= IH264E_INVALID_SEI_AVE_PARAMS;
                            return IV_FAIL;
                        }

                        if(ps_ip->u2_ambient_light_x > AMBIENT_LIGHT_X_UPPER_LIMIT)
                        {
                            ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->u4_error_code |= IH264E_INVALID_SEI_AVE_PARAMS;
                            return IV_FAIL;
                        }

                        if(ps_ip->u2_ambient_light_y > AMBIENT_LIGHT_Y_UPPER_LIMIT)
                        {
                            ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->u4_error_code |= IH264E_INVALID_SEI_AVE_PARAMS;
                            return IV_FAIL;
                        }
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_SEI_CCV_PARAMS:
                {
                    isvce_ctl_set_sei_ccv_params_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_set_sei_ccv_params_op_t *ps_op = pv_api_op;

                    if(ps_ip->u4_size != sizeof(isvce_ctl_set_sei_ccv_params_ip_t))
                    {
                        ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->u4_error_code |= IVE_ERR_IP_CTL_SET_SEI_CCV_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->u4_size != sizeof(isvce_ctl_set_sei_ccv_params_op_t))
                    {
                        ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->u4_error_code |= IVE_ERR_OP_CTL_SET_SEI_CCV_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if((ps_ip->u1_sei_ccv_params_present_flag != 0) &&
                       (ps_ip->u1_sei_ccv_params_present_flag != 1))
                    {
                        ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->u4_error_code |= IH264E_INVALID_SEI_CCV_PARAMS;
                        return IV_FAIL;
                    }

                    if(1 == ps_ip->u1_sei_ccv_params_present_flag)
                    {
                        if((ps_ip->u1_ccv_cancel_flag != 0) && (ps_ip->u1_ccv_cancel_flag != 1))
                        {
                            ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->u4_error_code |= IH264E_INVALID_SEI_CCV_PARAMS;
                            return IV_FAIL;
                        }

                        if(0 == ps_ip->u1_ccv_cancel_flag)
                        {
                            if((ps_ip->u1_ccv_persistence_flag != 0) &&
                               (ps_ip->u1_ccv_persistence_flag != 1))
                            {
                                ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                                ps_op->u4_error_code |= IH264E_INVALID_SEI_CCV_PARAMS;
                                return IV_FAIL;
                            }
                            if((ps_ip->u1_ccv_primaries_present_flag != 0) &&
                               (ps_ip->u1_ccv_primaries_present_flag != 1))
                            {
                                ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                                ps_op->u4_error_code |= IH264E_INVALID_SEI_CCV_PARAMS;
                                return IV_FAIL;
                            }
                            if((ps_ip->u1_ccv_min_luminance_value_present_flag != 0) &&
                               (ps_ip->u1_ccv_min_luminance_value_present_flag != 1))
                            {
                                ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                                ps_op->u4_error_code |= IH264E_INVALID_SEI_CCV_PARAMS;
                                return IV_FAIL;
                            }
                            if((ps_ip->u1_ccv_max_luminance_value_present_flag != 0) &&
                               (ps_ip->u1_ccv_max_luminance_value_present_flag != 1))
                            {
                                ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                                ps_op->u4_error_code |= IH264E_INVALID_SEI_CCV_PARAMS;
                                return IV_FAIL;
                            }
                            if((ps_ip->u1_ccv_avg_luminance_value_present_flag != 0) &&
                               (ps_ip->u1_ccv_avg_luminance_value_present_flag != 1))
                            {
                                ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                                ps_op->u4_error_code |= IH264E_INVALID_SEI_CCV_PARAMS;
                                return IV_FAIL;
                            }
                            if((ps_ip->u1_ccv_primaries_present_flag == 0) &&
                               (ps_ip->u1_ccv_min_luminance_value_present_flag == 0) &&
                               (ps_ip->u1_ccv_max_luminance_value_present_flag == 0) &&
                               (ps_ip->u1_ccv_avg_luminance_value_present_flag == 0))
                            {
                                ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                                ps_op->u4_error_code |= IH264E_INVALID_SEI_CCV_PARAMS;
                                return IV_FAIL;
                            }

                            if((ps_ip->u1_ccv_reserved_zero_2bits != 0))
                            {
                                ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                                ps_op->u4_error_code |= IH264E_INVALID_SEI_CCV_PARAMS;
                                return IV_FAIL;
                            }

                            if(1 == ps_ip->u1_ccv_primaries_present_flag)
                            {
                                for(i = 0; i < 3; i++)
                                {
                                    if((ps_ip->ai4_ccv_primaries_x[i] >
                                        CCV_PRIMARIES_X_UPPER_LIMIT) ||
                                       (ps_ip->ai4_ccv_primaries_x[i] <
                                        CCV_PRIMARIES_X_LOWER_LIMIT))
                                    {
                                        ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                                        ps_op->u4_error_code |= IH264E_INVALID_SEI_CCV_PARAMS;
                                        return IV_FAIL;
                                    }

                                    if((ps_ip->ai4_ccv_primaries_y[i] >
                                        CCV_PRIMARIES_Y_UPPER_LIMIT) ||
                                       (ps_ip->ai4_ccv_primaries_y[i] <
                                        CCV_PRIMARIES_Y_LOWER_LIMIT))
                                    {
                                        ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                                        ps_op->u4_error_code |= IH264E_INVALID_SEI_CCV_PARAMS;
                                        return IV_FAIL;
                                    }
                                }
                            }

                            if((1 == ps_ip->u1_ccv_min_luminance_value_present_flag) &&
                               (1 == ps_ip->u1_ccv_avg_luminance_value_present_flag))
                            {
                                if((ps_ip->u4_ccv_avg_luminance_value <
                                    ps_ip->u4_ccv_min_luminance_value))
                                {
                                    ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                                    ps_op->u4_error_code |= IH264E_INVALID_SEI_CCV_PARAMS;
                                    return IV_FAIL;
                                }
                            }

                            if((1 == ps_ip->u1_ccv_min_luminance_value_present_flag) &&
                               (1 == ps_ip->u1_ccv_max_luminance_value_present_flag))
                            {
                                if((ps_ip->u4_ccv_max_luminance_value <
                                    ps_ip->u4_ccv_min_luminance_value))
                                {
                                    ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                                    ps_op->u4_error_code |= IH264E_INVALID_SEI_CCV_PARAMS;
                                    return IV_FAIL;
                                }
                            }
                            if((1 == ps_ip->u1_ccv_avg_luminance_value_present_flag) &&
                               (1 == ps_ip->u1_ccv_max_luminance_value_present_flag))
                            {
                                if((ps_ip->u4_ccv_max_luminance_value <
                                    ps_ip->u4_ccv_avg_luminance_value))
                                {
                                    ps_op->u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                                    ps_op->u4_error_code |= IH264E_INVALID_SEI_CCV_PARAMS;
                                    return IV_FAIL;
                                }
                            }
                        }
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_ENC_MODE:
                {
                    isvce_ctl_set_enc_mode_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_set_enc_mode_op_t *ps_op = pv_api_op;

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_set_enc_mode_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_SETENCMODE_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_set_enc_mode_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_SETENCMODE_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if((ps_ip->s_ive_ip.e_enc_mode != IVE_ENC_MODE_HEADER) &&
                       (ps_ip->s_ive_ip.e_enc_mode != IVE_ENC_MODE_PICTURE))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_ENC_OPERATION_MODE;
                        return IV_FAIL;
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_VBV_PARAMS:
                {
                    isvce_ctl_set_vbv_params_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_set_vbv_params_op_t *ps_op = pv_api_op;

                    isvce_codec_t *ps_codec = (isvce_codec_t *) (ps_handle->pv_codec_handle);

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_set_vbv_params_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_SETVBVPARAMS_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_set_vbv_params_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_SETVBVPARAMS_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    for(i = 0; i < ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers; i++)
                    {
                        if((ps_ip->pu4_vbv_buffer_delay[i] < DEFAULT_MIN_BUFFER_DELAY) ||
                           (ps_ip->pu4_vbv_buffer_delay[i] > DEFAULT_MAX_BUFFER_DELAY))
                        {
                            ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                            ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_BUFFER_DELAY;
                            return IV_FAIL;
                        }
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_AIR_PARAMS:
                {
                    isvce_ctl_set_air_params_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_set_air_params_op_t *ps_op = pv_api_op;

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_set_air_params_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_SETAIRPARAMS_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_set_air_params_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_SETAIRPARAMS_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if((ps_ip->s_ive_ip.e_air_mode != IVE_AIR_MODE_NONE) &&
                       (ps_ip->s_ive_ip.e_air_mode != IVE_AIR_MODE_CYCLIC) &&
                       (ps_ip->s_ive_ip.e_air_mode != IVE_AIR_MODE_RANDOM))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_AIR_MODE;
                        return IV_FAIL;
                    }

                    if(ps_ip->s_ive_ip.u4_air_refresh_period == 0)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_AIR_REFRESH_PERIOD;
                        return IV_FAIL;
                    }

                    break;
                }
                case ISVCE_CMD_CTL_SET_PROFILE_PARAMS:
                {
                    isvce_ctl_set_profile_params_ip_t *ps_ip = pv_api_ip;
                    isvce_ctl_set_profile_params_op_t *ps_op = pv_api_op;

                    if(ps_ip->s_ive_ip.u4_size != sizeof(isvce_ctl_set_profile_params_ip_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_IP_CTL_SETPROFILE_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_op->s_ive_op.u4_size != sizeof(isvce_ctl_set_profile_params_op_t))
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |=
                            IVE_ERR_OP_CTL_SETPROFILE_API_STRUCT_SIZE_INCORRECT;
                        return IV_FAIL;
                    }

                    if(ps_ip->s_ive_ip.e_profile != IV_PROFILE_BASE &&
                       ps_ip->s_ive_ip.e_profile != IV_PROFILE_MAIN)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_PROFILE_NOT_SUPPORTED;
                        return IV_FAIL;
                    }

                    if(ps_ip->s_ive_ip.u4_entropy_coding_mode > 1)
                    {
                        ps_op->s_ive_op.u4_error_code |= 1 << IVE_UNSUPPORTEDPARAM;
                        ps_op->s_ive_op.u4_error_code |= IH264E_INVALID_ENTROPY_CODING_MODE;
                        return IV_FAIL;
                    }

                    break;
                }
                default:
                {
                    *(pu4_api_op + 1) |= 1 << IVE_UNSUPPORTEDPARAM;
                    *(pu4_api_op + 1) |= IVE_ERR_INVALID_API_SUB_CMD;
                    return IV_FAIL;
                }
            }

            break;
        }
        default:
        {
            *(pu4_api_op + 1) |= 1 << IVE_UNSUPPORTEDPARAM;
            *(pu4_api_op + 1) |= IVE_ERR_INVALID_API_CMD;
            return IV_FAIL;
        }
    }

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Sets default encoder config parameters
*
* @par Description:
*  Sets default dynamic parameters. Will be called in isvce_init() to ensure
*  that even if set_params is not called, codec continues to work
*
* @param[in] ps_cfg
*  Pointer to encoder config params
*
* @returns  error status
*
* @remarks none
*
*******************************************************************************
*/
static WORD32 isvce_set_default_params(isvce_cfg_params_t *ps_cfg)
{
    WORD32 ret = IV_SUCCESS;
    WORD32 i;

    ps_cfg->u4_max_wd = MAX_WD;
    ps_cfg->u4_max_ht = MAX_HT;
    ps_cfg->u4_max_ref_cnt = MAX_REF_CNT;
    ps_cfg->u4_max_reorder_cnt = MAX_REF_CNT;
    ps_cfg->u4_max_level = DEFAULT_MAX_LEVEL;
    ps_cfg->e_inp_color_fmt = IV_YUV_420SP_UV;
    ps_cfg->u4_enable_recon = DEFAULT_RECON_ENABLE;
    ps_cfg->e_recon_color_fmt = IV_YUV_420P;
    ps_cfg->u4_enc_speed_preset = IVE_FASTEST;
    ps_cfg->e_rc_mode = DEFAULT_RC;
    ps_cfg->u4_max_framerate = DEFAULT_MAX_FRAMERATE;
    ps_cfg->u4_num_bframes = DEFAULT_MAX_NUM_BFRAMES;
    ps_cfg->e_content_type = IV_PROGRESSIVE;
    ps_cfg->u4_max_srch_rng_x = DEFAULT_MAX_SRCH_RANGE_X;
    ps_cfg->u4_max_srch_rng_y = DEFAULT_MAX_SRCH_RANGE_Y;
    ps_cfg->e_slice_mode = IVE_SLICE_MODE_NONE;
    ps_cfg->u4_slice_param = DEFAULT_SLICE_PARAM;
    ps_cfg->e_arch = isvce_default_arch();
    ps_cfg->e_soc = SOC_GENERIC;
    ps_cfg->u4_disp_wd = MAX_WD;
    ps_cfg->u4_disp_ht = MAX_HT;
    ps_cfg->u4_wd = MAX_WD;
    ps_cfg->u4_ht = MAX_HT;
    ps_cfg->u4_src_frame_rate = DEFAULT_SRC_FRAME_RATE;
    ps_cfg->u4_tgt_frame_rate = DEFAULT_TGT_FRAME_RATE;
    ps_cfg->e_frame_type = IV_NA_FRAME;
    ps_cfg->e_enc_mode = IVE_ENC_MODE_DEFAULT;
    ps_cfg->e_air_mode = DEFAULT_AIR_MODE;
    ps_cfg->u4_air_refresh_period = DEFAULT_AIR_REFRESH_PERIOD;
    ps_cfg->u4_num_cores = DEFAULT_NUM_CORES;
    ps_cfg->u4_me_speed_preset = DEFAULT_ME_SPEED_PRESET;
    ps_cfg->u4_enable_hpel = DEFAULT_HPEL;
    ps_cfg->u4_enable_qpel = DEFAULT_QPEL;
    ps_cfg->u4_enable_intra_4x4 = DEFAULT_I4;
    ps_cfg->u4_enable_intra_8x8 = DEFAULT_I8;
    ps_cfg->u4_enable_intra_16x16 = DEFAULT_I16;
    ps_cfg->u4_enable_fast_sad = DEFAULT_ENABLE_FAST_SAD;
    ps_cfg->u4_enable_satqd = DEFAULT_ENABLE_SATQD;
    ps_cfg->i4_min_sad = (ps_cfg->u4_enable_satqd == DEFAULT_ENABLE_SATQD)
                             ? DEFAULT_MIN_SAD_ENABLE
                             : DEFAULT_MIN_SAD_DISABLE;
    ps_cfg->u4_srch_rng_x = DEFAULT_SRCH_RNG_X;
    ps_cfg->u4_srch_rng_y = DEFAULT_SRCH_RNG_Y;
    ps_cfg->u4_i_frm_interval = DEFAULT_I_INTERVAL;
    ps_cfg->u4_idr_frm_interval = DEFAULT_IDR_INTERVAL;
    ps_cfg->u4_disable_deblock_level = DEFAULT_DISABLE_DEBLK_LEVEL;
    ps_cfg->e_profile = DEFAULT_PROFILE;
    ps_cfg->u4_timestamp_low = 0;
    ps_cfg->u4_timestamp_high = 0;
    ps_cfg->u4_is_valid = 1;
    ps_cfg->e_cmd = ISVCE_CMD_CT_NA;
    ps_cfg->i4_wd_mbs = ps_cfg->u4_max_wd >> 4;
    ps_cfg->i4_ht_mbs = ps_cfg->u4_max_ht >> 4;
    ps_cfg->u4_entropy_coding_mode = CAVLC;
    ps_cfg->u4_weighted_prediction = 0;
    ps_cfg->u4_pic_info_type = 0;
    ps_cfg->u4_isvce_mb_info_type = 0;
    ps_cfg->s_vui.u1_video_signal_type_present_flag = 1;
    ps_cfg->s_vui.u1_colour_description_present_flag = 1;

    ps_cfg->b_nalu_info_export_enable = false;

    for(i = 0; i < MAX_NUM_SPATIAL_LAYERS; i++)
    {
        ps_cfg->au4_i_qp_max[i] = MAX_H264_QP;
        ps_cfg->au4_i_qp_min[i] = MIN_H264_QP;
        ps_cfg->au4_i_qp[i] = DEFAULT_I_QP;
        ps_cfg->au4_p_qp_max[i] = MAX_H264_QP;
        ps_cfg->au4_p_qp_min[i] = MIN_H264_QP;
        ps_cfg->au4_p_qp[i] = DEFAULT_P_QP;
        ps_cfg->au4_b_qp_max[i] = MAX_H264_QP;
        ps_cfg->au4_b_qp_min[i] = MIN_H264_QP;
        ps_cfg->au4_b_qp[i] = DEFAULT_B_QP;
    }

    ps_cfg->s_svc_params.d_spatial_res_ratio = 2.0;
    ps_cfg->s_svc_params.u1_num_spatial_layers = 1;
    ps_cfg->s_svc_params.u1_num_temporal_layers = 1;

    return ret;
}

/**
*******************************************************************************
*
* @brief
*  Initialize encoder context. This will be called by init_mem_rec and during
*  codec reset
*
* @par Description:
*  Initializes the context
*
* @param[in] ps_codec
*  Codec context pointer
*
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static WORD32 isvce_init(isvce_codec_t *ps_codec)
{
    /* enc config param set */
    isvce_cfg_params_t *ps_cfg = &(ps_codec->s_cfg);

    UWORD32 i;

    /* coded pic count */
    ps_codec->i4_poc = 0;

    /* Number of API calls to encode are made */
    ps_codec->i4_encode_api_call_cnt = -1;

    /* Indicates no header has been generated yet */
    ps_codec->u4_header_generated = 0;

    /* Number of pictures encoded */
    ps_codec->i4_pic_cnt = -1;

    /* Number of threads created */
    ps_codec->i4_proc_thread_cnt = 0;

    /* ctl mutex init */
    ithread_mutex_init(ps_codec->pv_ctl_mutex);

    /* Set encoder chroma format */
    ps_codec->e_codec_color_format =
        (ps_cfg->e_inp_color_fmt == IV_YUV_420SP_VU) ? IV_YUV_420SP_VU : IV_YUV_420SP_UV;

    /* Number of continuous frames where deblocking was disabled */
    ps_codec->u4_disable_deblock_level_cnt = 0;

    /* frame num */
    ps_codec->i4_frame_num = 0;

    /* set the current frame type to I frame, since we are going to start
     * encoding*/
    ps_codec->force_curr_frame_type = IV_NA_FRAME;

    /* idr_pic_id */
    ps_codec->i4_idr_pic_id = -1;

    /* Flush mode */
    ps_codec->i4_flush_mode = 0;

    /* Encode header mode */
    ps_codec->i4_header_mode = 0;

    /* Encode generate header */
    ps_codec->i4_gen_header = 0;

    /* To signal successful completion of init */
    ps_codec->i4_init_done = 1;

    /* To signal that at least one picture was decoded */
    ps_codec->i4_first_pic_done = 0;

    /* Reset Codec */
    ps_codec->i4_reset_flag = 0;

    /* Current error code */
    ps_codec->i4_error_code = IH264E_SUCCESS;

    /* threshold residue */
    ps_codec->u4_thres_resi = 1;

    /* inter gating enable */
    ps_codec->u4_inter_gate = 0;

    /* entropy mutex init */
    ithread_mutex_init(ps_codec->pv_entropy_mutex);

    /* Process thread created status */
    memset(ps_codec->ai4_process_thread_created, 0, sizeof(ps_codec->ai4_process_thread_created));

    /* Number of MBs processed together */
    ps_codec->i4_proc_nmb = 8;

    /* Previous POC msb */
    ps_codec->i4_prev_poc_msb = 0;

    /* Previous POC lsb */
    ps_codec->i4_prev_poc_lsb = -1;

    /* max Previous POC lsb */
    ps_codec->i4_max_prev_poc_lsb = -1;

    /* sps, pps status */
    {
        sps_t *ps_sps = ps_codec->ps_sps_base;
        pps_t *ps_pps = ps_codec->ps_pps_base;

        for(i = 0; i < MAX_SPS_CNT; i++)
        {
            ps_sps->i1_sps_valid = 0;
            ps_sps++;
        }

        for(i = 0; i < MAX_PPS_CNT; i++)
        {
            ps_pps->i1_pps_valid = 0;
            ps_pps++;
        }
    }

    {
        WORD32 max_mb_rows;
        UWORD32 u4_ht, u4_wd;

        isvce_get_svc_compliant_dimensions(ps_cfg->s_svc_params.u1_num_spatial_layers,
                                           ps_cfg->s_svc_params.d_spatial_res_ratio, ps_cfg->u4_wd,
                                           ps_cfg->u4_ht, &u4_wd, &u4_ht);

        /* frame dimensions */
        u4_ht = ALIGN16(u4_ht);
        max_mb_rows = u4_ht / MB_SIZE;

        {
            WORD32 clz;

            WORD32 num_jobs = max_mb_rows * MAX_CTXT_SETS;

            /* Use next power of two number of entries*/
            clz = CLZ(num_jobs);
            num_jobs = 1 << (32 - clz);

            /* init process jobq */
            ps_codec->pv_proc_jobq =
                ih264_list_init(ps_codec->pv_proc_jobq_buf, ps_codec->i4_proc_jobq_buf_size,
                                num_jobs, sizeof(job_t), 10);
            RETURN_IF((ps_codec->pv_proc_jobq == NULL), IV_FAIL);
            ih264_list_reset(ps_codec->pv_proc_jobq);

            /* init entropy jobq */
            ps_codec->pv_entropy_jobq =
                ih264_list_init(ps_codec->pv_entropy_jobq_buf, ps_codec->i4_entropy_jobq_buf_size,
                                num_jobs, sizeof(job_t), 10);
            RETURN_IF((ps_codec->pv_entropy_jobq == NULL), IV_FAIL);
            ih264_list_reset(ps_codec->pv_entropy_jobq);
        }
    }

    /* Update the jobq context to all the threads */
    for(i = 0; i < MAX_PROCESS_CTXT; i++)
    {
        ps_codec->as_process[i].pv_proc_jobq = ps_codec->pv_proc_jobq;
        ps_codec->as_process[i].pv_entropy_jobq = ps_codec->pv_entropy_jobq;

        /* i4_id always stays between 0 and MAX_PROCESS_THREADS */
        ps_codec->as_process[i].i4_id = i % MAX_PROCESS_THREADS;
        ps_codec->as_process[i].ps_codec = ps_codec;

        ps_codec->as_process[i].s_entropy.pv_proc_jobq = ps_codec->pv_proc_jobq;
        ps_codec->as_process[i].s_entropy.pv_entropy_jobq = ps_codec->pv_entropy_jobq;
        ps_codec->as_process[i].s_entropy.i4_abs_pic_order_cnt = -1;
    }

    /* Initialize MV Bank buffer manager */
    ps_codec->pv_svc_au_data_store_mgr =
        ih264_buf_mgr_init(ps_codec->pv_svc_au_data_store_mgr_base);

    /* Initialize Picture buffer manager for reference buffers*/
    ps_codec->pv_ref_buf_mgr = ih264_buf_mgr_init(ps_codec->pv_ref_buf_mgr_base);

    /* Initialize Picture buffer manager for input buffers*/
    ps_codec->pv_inp_buf_mgr = ih264_buf_mgr_init(ps_codec->pv_inp_buf_mgr_base);

    /* Initialize buffer manager for output buffers*/
    ps_codec->pv_out_buf_mgr = ih264_buf_mgr_init(ps_codec->pv_out_buf_mgr_base);

    /* buffer cnt in buffer manager */
    ps_codec->i4_inp_buf_cnt = 0;
    ps_codec->i4_out_buf_cnt = 0;
    ps_codec->i4_ref_buf_cnt = 0;

    ps_codec->ps_pic_buf = ps_codec->ps_pic_buf_base;
    memset(ps_codec->ps_pic_buf, 0, BUF_MGR_MAX_CNT * sizeof(svc_au_buf_t));

    for(i = 0; i < BUF_MGR_MAX_CNT; i++)
    {
        isvce_svc_au_buf_init(&((svc_au_buf_t *) ps_codec->ps_pic_buf)[i], &ps_cfg->s_svc_params);
    }

    /* Initialize dpb manager */
    ih264_dpb_mgr_init((dpb_mgr_t *) ps_codec->pv_dpb_mgr);

    memset(ps_codec->as_ref_set, 0, sizeof(ps_codec->as_ref_set));
    for(i = 0; i < (sizeof(ps_codec->as_ref_set) / sizeof(ps_codec->as_ref_set[0])); i++)
    {
        ps_codec->as_ref_set[i].i4_pic_cnt = -1;
    }

    /* fn ptr init */
    isvce_init_function_ptr(ps_codec);

    /* reset status flags */
    for(i = 0; i < MAX_CTXT_SETS; i++)
    {
        ps_codec->au4_entropy_thread_active[i] = 0;
        ps_codec->ai4_pic_cnt[i] = -1;

        ps_codec->s_rate_control.pre_encode_skip[i] = 0;
        ps_codec->s_rate_control.post_encode_skip[i] = 0;
    }

    for(i = 0; i < ps_cfg->s_svc_params.u1_num_spatial_layers; i++)
    {
        ps_codec->s_rate_control.ai4_num_intra_in_prev_frame[i] = 0;
        ps_codec->s_rate_control.ai4_avg_activity[i] = 0;
    }

    ps_codec->i4_max_num_reference_frames =
        MIN((gas_ih264_lvl_tbl[ih264e_get_lvl_idx(ps_codec->s_cfg.u4_max_level)].u4_max_dpb_size /
             (ps_codec->s_cfg.i4_wd_mbs * ps_codec->s_cfg.i4_ht_mbs)),
            16);

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Gets number of memory records required by the codec
*
* @par Description:
*  Gets codec memory requirements
*
* @param[in] pv_api_ip
*  Pointer to input argument structure
*
* @param[out] pv_api_op
*  Pointer to output argument structure
*
* @returns  status
*
* @remarks
*
*******************************************************************************
*/
static WORD32 isvce_get_num_rec(void *pv_api_ip, void *pv_api_op)
{
    /* api call I/O structures */
    isvce_num_mem_rec_op_t *ps_op = pv_api_op;

    UNUSED(pv_api_ip);

    ps_op->s_ive_op.u4_num_mem_rec = ISVCE_MEM_REC_CNT;

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Fills memory records of the codec
*
* @par Description:
*  Fills codec memory requirements
*
* @param[in] pv_api_ip
*  Pointer to input argument structure
*
* @param[out] pv_api_op
*  Pointer to output argument structure
*
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static WORD32 isvce_fill_num_mem_rec(void *pv_api_ip, void *pv_api_op)
{
    isvce_fill_mem_rec_ip_t *ps_ip = pv_api_ip;
    isvce_fill_mem_rec_op_t *ps_op = pv_api_op;

    WORD32 level;
    WORD32 num_reorder_frames;
    WORD32 num_ref_frames;

    WORD32 no_of_mem_rec;
    iv_mem_rec_t *ps_mem_rec_base, *ps_mem_rec;

    WORD32 max_wd_luma, max_ht_luma;
    WORD32 max_mb_rows, max_mb_cols, max_mb_cnt;
    UWORD32 u4_wd, u4_ht;

    WORD32 i;

    IV_STATUS_T status = IV_SUCCESS;

    num_reorder_frames = ps_ip->s_ive_ip.u4_max_reorder_cnt;
    num_ref_frames = ps_ip->s_ive_ip.u4_max_ref_cnt;

    ps_mem_rec_base = ps_ip->s_ive_ip.ps_mem_rec;
    no_of_mem_rec = ps_ip->s_ive_ip.u4_num_mem_rec;

    isvce_get_svc_compliant_dimensions(ps_ip->s_svc_inp_params.u1_num_spatial_layers,
                                       ps_ip->s_svc_inp_params.d_spatial_res_ratio, ps_ip->u4_wd,
                                       ps_ip->u4_ht, &u4_wd, &u4_ht);

    /* frame dimensions */
    max_ht_luma = ALIGN16(u4_ht);
    max_wd_luma = ALIGN16(u4_wd);
    max_mb_rows = max_ht_luma / MB_SIZE;
    max_mb_cols = max_wd_luma / MB_SIZE;
    max_mb_cnt = max_mb_rows * max_mb_cols;

    /* profile / level info */
    level = ih264e_get_min_level(max_ht_luma, max_wd_luma);

    /* Validate params */
    ps_op->s_ive_op.u4_error_code |= isvce_svc_au_props_validate(
        &ps_ip->s_svc_inp_params, ps_ip->u4_wd, ps_ip->u4_ht, u4_wd, u4_ht);

    if(ps_op->s_ive_op.u4_error_code != IV_SUCCESS)
    {
        return IV_FAIL;
    }

    if((level < MIN_LEVEL) || (level > MAX_LEVEL))
    {
        ps_op->s_ive_op.u4_error_code |= IH264E_CODEC_LEVEL_NOT_SUPPORTED;
        level = MAX_LEVEL;
    }

    if(num_ref_frames > MAX_REF_CNT)
    {
        ps_op->s_ive_op.u4_error_code |= IH264E_NUM_REF_UNSUPPORTED;
        num_ref_frames = MAX_REF_CNT;
    }

    if(num_reorder_frames > MAX_REF_CNT)
    {
        ps_op->s_ive_op.u4_error_code |= IH264E_NUM_REORDER_UNSUPPORTED;
        num_reorder_frames = MAX_REF_CNT;
    }

    /* Set all memory records as persistent and alignment as 128 by default */
    ps_mem_rec = ps_mem_rec_base;
    for(i = 0; i < no_of_mem_rec; i++)
    {
        ps_mem_rec->u4_mem_alignment = 128;
        ps_mem_rec->e_mem_type = IV_EXTERNAL_CACHEABLE_PERSISTENT_MEM;
        ps_mem_rec++;
    }

    /************************************************************************
     * Request memory for h264 encoder handle                               *
     ***********************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_IV_OBJ];
    {
        ps_mem_rec->u4_mem_size = sizeof(iv_obj_t);
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_IV_OBJ, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * Request memory for h264 encoder context                              *
     ***********************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_CODEC];
    {
        ps_mem_rec->u4_mem_size = sizeof(isvce_codec_t);
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_CODEC, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * Request memory for CABAC context                                     *
     ***********************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_CABAC];
    {
        ps_mem_rec->u4_mem_size = sizeof(isvce_cabac_ctxt_t);
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_CABAC, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * Request memory for CABAC MB info                                     *
     ***********************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_CABAC_MB_INFO];
    {
        ps_mem_rec->u4_mem_size = ((max_mb_cols + 1) + 1) * sizeof(isvce_mb_info_ctxt_t);
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_CABAC_MB_INFO, ps_mem_rec->u4_mem_size);

    /************************************************************************
     *  Request memory for entropy context                                  *
     *  In multi core encoding, each row is assumed to be launched on a     *
     *  thread. The rows below can only start after its neighbors are coded *
     *  The status of an mb coded/uncoded is signaled via entropy map.      *
     *         1. One word32 to store skip run cnt                          *
     *         2. mb entropy map (mb status entropy coded/uncoded). The size*
     *            of the entropy map is max mb cols. Further allocate one   *
     *            more additional row to evade checking for row -1.         *
     *         3. size of bit stream buffer to store bit stream ctxt.       *
     *         4. Entropy coding is dependent on nnz coefficient count for  *
     *            the neighbor blocks. It is sufficient to maintain one row *
     *            worth of nnz as entropy for lower row waits on entropy map*
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_ENTROPY];
    {
        /* total size of the mem record */
        WORD32 total_size = 0;

        /* size of skip mb run */
        total_size += sizeof(WORD32);
        total_size = ALIGN8(total_size);

        /* size in bytes to store entropy status of an entire frame */
        total_size += (max_mb_cols * max_mb_rows);
        /* add an additional 1 row of bytes to evade the special case of row 0 */
        total_size += max_mb_cols;
        total_size = ALIGN128(total_size);

        /* size of bit stream buffer */
        total_size += sizeof(bitstrm_t);
        total_size = ALIGN128(total_size);

        /* size of bit stream buffer */
        total_size += sizeof(bitstrm_t);
        total_size = ALIGN128(total_size);

        /* top nnz luma */
        total_size += (max_mb_cols * 4 * sizeof(UWORD8));
        total_size = ALIGN128(total_size);

        /* top nnz cbcr */
        total_size += (max_mb_cols * 4 * sizeof(UWORD8));
        total_size = ALIGN128(total_size);

        /* ps_mb_qp_ctxt */
        total_size += ALIGN128(sizeof(mb_qp_ctxt_t));

        /* total size per each proc ctxt */
        total_size *= MAX_CTXT_SETS;

        ps_mem_rec->u4_mem_size = total_size;
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_ENTROPY, ps_mem_rec->u4_mem_size);

    /************************************************************************
     *  The residue coefficients that needs to be entropy coded are packed  *
     *  at a buffer space by the proc threads. The entropy thread shall     *
     *  read from the buffer space, unpack them and encode the same. The    *
     *  buffer space required to pack a row of mbs are as follows.          *
     *  Assuming transform_8x8_flag is disabled,                            *
     *  In the worst case, 1 mb contains 1 dc 4x4 luma sub block, followed  *
     *  by 16 ac 4x4 luma sub blocks, 2 dc chroma 2x2 sub blocks, followed  *
     *  by 8 ac 4x4 chroma sub blocks.                                      *
     *  For the sake of simplicity we assume that all sub blocks are of     *
     *  type 4x4. The packing of each 4x4 is depicted by the structure      *
     *  tu_sblk_coeff_data_t                                                *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_MB_COEFF_DATA];
    {
        /* temp var */
        WORD32 size = 0;

        /* size of coeff data of 1 mb */
        size += sizeof(tu_sblk_coeff_data_t) * MAX_4x4_SUBBLKS;

        /* size of coeff data of 1 row of mb's */
        size *= max_mb_cols;

        /* align to avoid any false sharing across threads */
        size = ALIGN64(size);

        /* size for one full frame */
        size *= max_mb_rows;

        /* size of each proc buffer set (ping, pong) */
        size *= MAX_CTXT_SETS;

        ps_mem_rec->u4_mem_size = size;
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_MB_COEFF_DATA, ps_mem_rec->u4_mem_size);

    /************************************************************************
     *  while encoding an mb, the mb header data is signaled to the entropy*
     *  thread by writing to a buffer space. the size of header data per mb *
     *  is assumed to be 40 bytes                                           *
     *  TODO: revisit this inference                                        *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_MB_HEADER_DATA];
    {
        /* temp var */
        WORD32 size;

        /* size per MB */
        size = sizeof(isvce_mb_hdr_t);

        /* size for 1 row of mbs */
        size = size * max_mb_cols;

        /* align to avoid any false sharing across threads */
        size = ALIGN64(size);

        /* size for one full frame */
        size *= max_mb_rows;

        /* size of each proc buffer set (ping, pong) */
        size *= MAX_CTXT_SETS;

        ps_mem_rec->u4_mem_size = size;
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_MB_HEADER_DATA, ps_mem_rec->u4_mem_size);

    /************************************************************************
     *  While encoding inter slices, to compute the cost of encoding an mb  *
     *  with the mv's at hand, we employ the expression cost = sad + lambda *
     *  x mv_bits. Here mv_bits is the total number of bits taken to represe*
     *  nt the mv in the stream. The mv bits for all the possible mv are    *
     *  stored in the look up table. The mem record for this look up table  *
     *  is given below.                                                     *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_MVBITS];
    {
        /* max srch range x */
        UWORD32 u4_srch_range_x = ps_ip->s_ive_ip.u4_max_srch_rng_x;

        /* max srch range y */
        UWORD32 u4_srch_range_y = ps_ip->s_ive_ip.u4_max_srch_rng_y;

        /* max srch range */
        UWORD32 u4_max_srch_range = MAX(u4_srch_range_x, u4_srch_range_y);

        /* due to subpel */
        u4_max_srch_range <<= 2;

        /* due to mv on either direction */
        u4_max_srch_range = (u4_max_srch_range << 1);

        /* due to pred mv + zero */
        u4_max_srch_range = (u4_max_srch_range << 1) + 1;

        u4_max_srch_range = ALIGN128(u4_max_srch_range);

        ps_mem_rec->u4_mem_size = u4_max_srch_range;
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_MVBITS, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * Request memory for SPS                                               *
     ***********************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_SPS];
    {
        ps_mem_rec->u4_mem_size = MAX_SPS_CNT * sizeof(sps_t);
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_SPS, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * Request memory for PPS                                               *
     ***********************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_PPS];
    {
        ps_mem_rec->u4_mem_size = MAX_PPS_CNT * sizeof(pps_t);
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_PPS, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * Request memory for SVC NALU Extension                                 *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_SVC_NALU_EXT];
    {
        /* 2 implies allocation for NAL_PREFIX and NAL_CODED_SLICE_EXTENSION */
        ps_mem_rec->u4_mem_size =
            2 * MAX_CTXT_SETS * SVC_MAX_SLICE_HDR_CNT * sizeof(svc_nalu_ext_t);
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_SVC_NALU_EXT, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * Request memory for subset SPS                                         *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_SUBSET_SPS];
    {
        ps_mem_rec->u4_mem_size =
            MAX_SPS_CNT * ps_ip->s_svc_inp_params.u1_num_spatial_layers * sizeof(subset_sps_t);
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_SUBSET_SPS, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * Request memory for Slice Header                                      *
     ***********************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_SLICE_HDR];
    {
        ps_mem_rec->u4_mem_size = MAX_CTXT_SETS * SVC_MAX_SLICE_HDR_CNT * sizeof(slice_header_t);
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_SLICE_HDR, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * Request memory for SVC Slice Header                                  *
     ***********************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_SVC_SLICE_HDR];
    {
        ps_mem_rec->u4_mem_size =
            MAX_CTXT_SETS * SVC_MAX_SLICE_HDR_CNT * sizeof(svc_slice_header_t);
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_SVC_SLICE_HDR, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * Request memory for Adaptive Intra Refresh                            *
     ***********************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_AIR_MAP];
    {
        /* total size of the mem record */
        WORD32 total_size = 0;

        /* intra coded map */
        total_size += max_mb_cnt;
        total_size *= MAX_CTXT_SETS;

        /* mb refresh map */
        total_size += sizeof(UWORD16) * max_mb_cnt;

        /* alignment */
        total_size = ALIGN128(total_size);

        ps_mem_rec->u4_mem_size = total_size;
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_AIR_MAP, ps_mem_rec->u4_mem_size);

    /************************************************************************
     *  In multi slice encoding, this memory record helps tracking the start*
     *  of slice with reference to mb.                                      *
     *  MEM RECORD for holding                                              *
     *         1. mb slice map                                              *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_SLICE_MAP];
    {
        /* total size of the mem record */
        WORD32 total_size = 0;

        /* size in bytes to slice index of all mbs of a frame */
        total_size = ALIGN64(max_mb_cnt);

        /* isvce_update_proc_ctxt can overread by 1 at the end */
        total_size += 1;

        /* total size per each proc ctxt */
        total_size *= MAX_CTXT_SETS;
        ps_mem_rec->u4_mem_size = total_size;
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_SLICE_MAP, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * Request memory to hold thread handles for each processing thread     *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_THREAD_HANDLE];
    {
        WORD32 handle_size = ithread_get_handle_size();

        ps_mem_rec->u4_mem_size = MAX_PROCESS_THREADS * handle_size;
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_THREAD_HANDLE, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * Request memory to hold mutex for control calls                       *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_CTL_MUTEX];
    {
        ps_mem_rec->u4_mem_size = ithread_get_mutex_lock_size();
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_CTL_MUTEX, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * Request memory to hold mutex for entropy calls                       *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_ENTROPY_MUTEX];
    {
        ps_mem_rec->u4_mem_size = ithread_get_mutex_lock_size();
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_ENTROPY_MUTEX, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * Request memory to hold process jobs                                  *
     ***********************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_PROC_JOBQ];
    {
        /* One process job per row of MBs */
        /* Allocate for two pictures, so that wrap around can be handled easily */
        WORD32 num_jobs = max_mb_rows * MAX_CTXT_SETS;

        WORD32 job_queue_size = ih264_list_size(num_jobs, sizeof(job_t));

        ps_mem_rec->u4_mem_size = job_queue_size;
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_PROC_JOBQ, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * Request memory to hold entropy jobs                                  *
     ***********************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_ENTROPY_JOBQ];
    {
        /* One process job per row of MBs */
        /* Allocate for two pictures, so that wrap around can be handled easily */
        WORD32 num_jobs = max_mb_rows * MAX_CTXT_SETS;

        WORD32 job_queue_size = ih264_list_size(num_jobs, sizeof(job_t));

        ps_mem_rec->u4_mem_size = job_queue_size;
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_ENTROPY_JOBQ, ps_mem_rec->u4_mem_size);

    /************************************************************************
     *  In multi core encoding, each row is assumed to be launched on a     *
     *  thread. The rows below can only start after its neighbors are coded *
     *  The status of an mb coded/uncoded is signaled via proc map.        *
     *  MEM RECORD for holding                                              *
     *         1. mb proc map (mb status core coded/uncoded)                *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_PROC_MAP];
    {
        /* total size of the mem record */
        WORD32 total_size = 0;

        /* size in bytes to mb core coding status of an entire frame */
        total_size = max_mb_cnt;

        /* add an additional 1 row of bytes to evade the special case of row 0 */
        total_size += max_mb_cols;

        /* total size per each proc ctxt */
        total_size *= MAX_CTXT_SETS;
        ps_mem_rec->u4_mem_size = total_size;
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_PROC_MAP, ps_mem_rec->u4_mem_size);

    /************************************************************************
     *  mem record for holding a particular MB is deblocked or not          *
     *         1. mb deblk map (mb status deblocked/not deblocked)          *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_DBLK_MAP];
    {
        /* total size of the mem record */
        WORD32 total_size = 0;

        /* size in bytes to mb core coding status of an entire frame */
        total_size = max_mb_cnt;

        /* add an additional 1 row of bytes to evade the special case of row 0 */
        total_size += max_mb_cols;

        total_size = ALIGN64(total_size);

        /* total size per each proc ctxt */
        total_size *= MAX_CTXT_SETS;
        ps_mem_rec->u4_mem_size = total_size;
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_DBLK_MAP, ps_mem_rec->u4_mem_size);

    /************************************************************************
     *  mem record for holding a particular MB's me is done or not          *
     *         1. mb me map                                                 *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_ME_MAP];
    {
        /* total size of the mem record */
        WORD32 total_size = 0;

        /* size in bytes to mb core coding status of an entire frame */
        total_size = max_mb_cnt;

        /* add an additional 1 row of bytes to evade the special case of row 0 */
        total_size += max_mb_cols;

        /* total size per each proc ctxt */
        total_size *= MAX_CTXT_SETS;

        ps_mem_rec->u4_mem_size = total_size;
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_ME_MAP, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * size for holding dpb manager context                                 *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_DPB_MGR];
    {
        ps_mem_rec->u4_mem_size = sizeof(dpb_mgr_t);
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_DPB_MGR, ps_mem_rec->u4_mem_size);

    /************************************************************************
     *  luma or chroma core coding involves mb estimation, error computation*
     *  between the estimated singnal and the actual signal, transform the  *
     *  error, quantize the error, then inverse transform and inverse quant *
     *  ize the residue and add the result back to estimated signal.        *
     *  To perform all these, a set of temporary buffers are needed.        *
     *  MEM RECORD for holding scratch buffers                              *
     *         1. prediction buffer used during mb mode analysis            *
     *         2  temp. reference buffer when intra 4x4 with rdopt on is    *
     *            enabled                                                   *
     *            - when intra 4x4 is enabled, rdopt is on, to store the    *
     *            reconstructed values and use them later this temp. buffer *
     *            is used.                                                  *
     *         3. prediction buffer used during intra mode analysis         *
     *         4. prediction buffer used during intra 16x16 plane mode      *
     *            analysis
     *         5. prediction buffer used during intra chroma mode analysis  *
     *         6. prediction buffer used during intra chroma 16x16 plane    *
     *            mode analysis
     *         7. forward transform output buffer                           *
     *            - to store the error between estimated and the actual inp *
     *              ut and to store the fwd transformed quantized output    *
     *         8. forward transform output buffer                           *
     *            - when intra 4x4 is enabled, rdopt is on, to store the    *
     *            fwd transform values and use them later this temp. buffer *
     *            is used.                                                  *
     *         9. temporary buffer for inverse transform                    *
     *            - temporary buffer used in inverse transform and inverse  *
     *              quantization                                            *
     *         A. Buffers for holding half_x , half_y and half_xy planes    *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_PROC_SCRATCH];
    {
        WORD32 total_size = 0;
        WORD32 i4_tmp_size;

        /* size to hold prediction buffer */
        total_size += sizeof(UWORD8) * 16 * 16;
        total_size = ALIGN64(total_size);

        /* size to hold recon for intra 4x4 buffer */
        total_size += sizeof(UWORD8) * 16 * 16;
        total_size = ALIGN64(total_size);

        /* prediction buffer intra 16x16 */
        total_size += sizeof(UWORD8) * 16 * 16;
        total_size = ALIGN64(total_size);

        /* prediction buffer intra 16x16 plane*/
        total_size += sizeof(UWORD8) * 16 * 16;
        total_size = ALIGN64(total_size);

        /* prediction buffer intra chroma*/
        total_size += sizeof(UWORD8) * 16 * 8;
        total_size = ALIGN64(total_size);

        /* prediction buffer intra chroma plane*/
        total_size += sizeof(UWORD8) * 16 * 8;
        total_size = ALIGN64(total_size);

        /* size to hold fwd transform output */
        total_size += sizeof(WORD16) * SIZE_TRANS_BUFF;
        total_size = ALIGN64(total_size);

        /* size to hold fwd transform output */
        total_size += sizeof(WORD16) * SIZE_TRANS_BUFF;
        total_size = ALIGN64(total_size);

        /* size to hold temporary data during inverse transform */
        total_size += sizeof(WORD32) * SIZE_TMP_BUFF_ITRANS;
        total_size = ALIGN64(total_size);

        /* Buffers for holding half_x , half_y and half_xy planes */
        i4_tmp_size = sizeof(UWORD8) * (HP_BUFF_WD * HP_BUFF_HT);
        total_size += (ALIGN64(i4_tmp_size) * SUBPEL_BUFF_CNT);

        /* Allocate for each process thread */
        total_size *= MAX_PROCESS_CTXT;

        ps_mem_rec->u4_mem_size = total_size;
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_PROC_SCRATCH, ps_mem_rec->u4_mem_size);

    /************************************************************************
     *  When transform_8x8_flag is disabled, the size of a sub block is     *
     *  4x4 and when the transform_8x8_flag is enabled the size of the sub  *
     *  block is 8x8. The threshold matrix and the forward scaling list     *
     *  is of the size of the sub block.                                    *
     *  MEM RECORD for holding                                              *
     *         1. quantization parameters for plane y, cb, cr               *
     *            - threshold matrix for quantization                       *
     *            - forward weight matrix                                   *
     *            - satqd threshold matrix                                  *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_QUANT_PARAM];
    {
        /* total size of the mem record */
        WORD32 total_size = 0;

        /* quantization parameter list for planes y,cb and cr */
        total_size += ALIGN64(sizeof(quant_params_t)) * 3;

        /* size of threshold matrix for quantization
         * (assuming the transform_8x8_flag is disabled).
         * for all 3 planes */
        total_size += ALIGN64(sizeof(WORD16) * 4 * 4) * 3;

        /* size of forward weight matrix for quantization
         * (assuming the transform_8x8_flag is disabled).
         * for all 3 planes */
        total_size += ALIGN64(sizeof(WORD16) * 4 * 4) * 3;

        /* Size for SATDQ threshold matrix for palnes y, cb and cr */
        total_size += ALIGN64(sizeof(UWORD16) * 9) * 3;

        total_size = ALIGN128(total_size);

        /* total size per each proc thread */
        total_size *= MAX_PROCESS_CTXT;

        ps_mem_rec->u4_mem_size = total_size;
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_QUANT_PARAM, ps_mem_rec->u4_mem_size);

    /************************************************************************
     *  While computing blocking strength for the current mb, the csbp, mb  *
     *  type for the neighboring mbs are necessary. memtab for storing top  *
     *  row mbtype and csbp is evaluated here.                              *
     *                                                                      *
     *  when encoding intra 4x4 or intra 8x8 the submb types are estimated  *
     *  and sent. The estimation is dependent on neighbor mbs. For this     *
     *  store the top row sub mb types for intra mbs                        *
     *                                                                      *
     *  During motion vector prediction, the curr mb mv is predicted from   *
     *  neigbors left, top, top right and sometimes top left depending on   *
     *  the availability. The top and top right content is accessed from    *
     *  the memtab specified below.                                         *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_TOP_ROW_SYN_INFO];
    {
        UWORD32 total_size = isvce_get_svc_nbr_info_buf_size(
            ps_ip->s_svc_inp_params.u1_num_spatial_layers,
            ps_ip->s_svc_inp_params.d_spatial_res_ratio, u4_wd, u4_ht);

        total_size = ALIGN128(total_size);
        ps_mem_rec->u4_mem_size = total_size;
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_TOP_ROW_SYN_INFO, ps_mem_rec->u4_mem_size);

    /************************************************************************
     *  When transform_8x8_flag is disabled, the mb is partitioned into     *
     *  4 sub blocks. This corresponds to 1 vertical left edge and 1        *
     *  vertical inner edge, 1 horizontal top edge and 1 horizontal         *
     *  inner edge per mb. Further, When transform_8x8_flag is enabled,     *
     *  the mb is partitioned in to 16 sub blocks. This corresponds to      *
     *  1 vertical left edge and 3 vertical inner edges, 1 horizontal top   *
     *  edge and 3 horizontal inner edges per mb.                           *
     *  MEM RECORD for holding                                              *
     *         1. vertical edge blocking strength                           *
     *         2. horizontal edge blocking strength                         *
     *         3. mb qp                                                     *
     *         all are frame level                                          *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_BS_QP];
    {
        /* total size of the mem record */
        WORD32 total_size = 0;

        /* size in bytes to store vertical edge bs, horizontal edge bs and qp of
         * every mb*/
        WORD32 vert_bs_size, horz_bs_size, qp_size;

        /* vertical edge bs = total number of vertical edges * number of bytes per
         * each edge */
        /* total num of v edges = total mb * 4 (assuming transform_8x8_flag = 0),
         * each edge is formed by 4 pairs of subblks, requiring 4 bytes to storing
         * bs */
        vert_bs_size = 2 * ALIGN64(max_mb_cnt * 4 * 4);

        /* horizontal edge bs = total number of horizontal edges * number of bytes
         * per each edge */
        /* total num of h edges = total mb * 4 (assuming transform_8x8_flag = 0),
         * each edge is formed by 4 pairs of subblks, requiring 4 bytes to storing
         * bs */
        horz_bs_size = 2 * ALIGN64(max_mb_cnt * 4 * 4);

        /* qp of each mb requires 1 byte */
        qp_size = ALIGN64(max_mb_cnt);

        /* total size */
        total_size = vert_bs_size + horz_bs_size + qp_size;

        /* total size per each proc ctxt */
        total_size *= MAX_CTXT_SETS;

        ps_mem_rec->u4_mem_size = total_size;
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_BS_QP, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * size for holding input pic buf                                       *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_INP_PIC];
    {
        ps_mem_rec->u4_mem_size = ih264_buf_mgr_size();
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_INP_PIC, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * size for holding putput pic buf                                      *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_OUT];
    {
        ps_mem_rec->u4_mem_size = ih264_buf_mgr_size();
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_OUT, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * Size for color space conversion                                      *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_CSC];
    {
        /* We need a total a memory for a single frame of 420 sp, ie
         * (wd * ht) for luma and (wd * ht / 2) for chroma*/
        ps_mem_rec->u4_mem_size = MAX_CTXT_SETS * ((3 * max_ht_luma * max_wd_luma) >> 1);
        /* Allocate an extra row, since inverse transform functions for
         * chroma access(only read, not used) few extra bytes due to
         * interleaved input
         */
        ps_mem_rec->u4_mem_size += max_wd_luma;
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_CSC, ps_mem_rec->u4_mem_size);

    /************************************************************************
     *  Size for holding pic_buf_t for each reference picture               *
     *  Note this allocation is done for BUF_MGR_MAX_CNT instead of         *
     *  MAX_DPB_SIZE or max_dpb_size for following reasons                  *
     *  max_dpb_size will be based on max_wd and max_ht                     *
     *  For higher max_wd and max_ht this number will be smaller than       *
     *  MAX_DPB_SIZE But during actual initialization number of buffers     *
     *  allocated can be more.                                              *
     *                                                                      *
     *  Also to handle display depth application can allocate more than     *
     *  what codec asks for in case of non-shared mode                      *
     *  Since this is only a structure allocation and not actual buffer     *
     *  allocation, it is allocated for BUF_MGR_MAX_CNT entries             *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_REF_PIC];
    {
        ps_mem_rec->u4_mem_size = ih264_buf_mgr_size();
        ps_mem_rec->u4_mem_size += BUF_MGR_MAX_CNT * sizeof(svc_au_buf_t);

        /************************************************************************
         * Note: Number of luma samples is not max_wd * max_ht here, instead it *
         * is set to maximum number of luma samples allowed at the given level. *
         * This is done to ensure that any stream with width and height lesser  *
         * than max_wd and max_ht is supported. Number of buffers required can  *
         * be greater for lower width and heights at a given level and this     *
         * increased number of buffers might require more memory than what      *
         * max_wd and max_ht buffer would have required. Number of buffers is   *
         * doubled in order to return one frame at a time instead of sending    *
         * multiple outputs during dpb full case. Also note one extra buffer is *
         * allocted to store current picture.                                   *
         *                                                                      *
         * Half-pel planes for each reference buffer are allocated along with   *
         * the reference buffer. So each reference buffer is 4 times the        *
         * required size. This way buffer management for the half-pel planes is *
         * easier and while using the half-pel planes in MC, an offset can be   *
         * used from a single pointer                                           *
         ***********************************************************************/
        ps_mem_rec->u4_mem_size +=
            HPEL_PLANES_CNT * isvce_get_total_svc_au_buf_size(&ps_ip->s_svc_inp_params,
                                                              u4_wd * u4_ht, level, PAD_WD, PAD_HT,
                                                              num_ref_frames, num_reorder_frames);
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_REF_PIC, ps_mem_rec->u4_mem_size);

    /************************************************************************
     *  Size for holding svc_au_data_t for each MV Bank.                    *
     *  Note this allocation is done for BUF_MGR_MAX_CNT instead of         *
     *  MAX_DPB_SIZE or max_dpb_size for following reasons                  *
     *  max_dpb_size will be based on max_wd and max_ht                     *
     *  For higher max_wd and max_ht this number will be smaller than       *
     *  MAX_DPB_SIZE But during actual initialization number of buffers     *
     *  allocated can be more.                                              *
     *                                                                      *
     *  One extra MV Bank is needed to hold current pics MV bank.           *
     *  Since this is only a structure allocation and not actual buffer     *
     *  allocation, it is allocated for BUF_MGR_MAX_CNT entries             *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_MVBANK];
    {
        ps_mem_rec->u4_mem_size = ih264_buf_mgr_size();

        /************************************************************************
         * Allocate for pu_map, isvce_enc_pu_t and pic_pu_idx for each MV bank        *
         * Note: Number of luma samples is not max_wd * max_ht here, instead it *
         * is set to maximum number of luma samples allowed at the given level. *
         * This is done to ensure that any stream with width and height lesser  *
         * than max_wd and max_ht is supported. Number of buffers required can  *
         * be greater for lower width and heights at a given level and this     *
         * increased number of buffers might require more memory than what      *
         * max_wd and max_ht buffer would have required Also note one extra     *
         * buffer is allocated to store current pictures MV bank.                *
         ***********************************************************************/

        ps_mem_rec->u4_mem_size += BUF_MGR_MAX_CNT * sizeof(svc_au_data_t);

        ps_mem_rec->u4_mem_size +=
            (num_ref_frames + num_reorder_frames + ps_ip->s_svc_inp_params.u1_num_temporal_layers +
             MAX_CTXT_SETS) *
            isvce_get_total_svc_au_data_size(u4_wd * u4_ht,
                                             ps_ip->s_svc_inp_params.u1_num_spatial_layers,
                                             ps_ip->s_svc_inp_params.d_spatial_res_ratio);
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_MVBANK, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * Request memory to hold mem recs to be returned during retrieve call  *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_BACKUP];
    {
        ps_mem_rec->u4_mem_size = ISVCE_MEM_REC_CNT * sizeof(iv_mem_rec_t);
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_BACKUP, ps_mem_rec->u4_mem_size);

    /************************************************************************
     * size for memory required by NMB info structs and buffer for storing  *
     * half pel plane                                                       *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_MB_INFO_NMB];
    {
        /* Additional 4 bytes to allow use of '_mm_loadl_epi64' */
        ps_mem_rec->u4_mem_size =
            MAX_PROCESS_CTXT * max_mb_cols *
            (sizeof(isvce_mb_info_nmb_t) + (MB_SIZE * MB_SIZE + 4) * sizeof(UWORD8));
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_MB_INFO_NMB, ps_mem_rec->u4_mem_size);

    /* Buffers for storing SVC Spatial data */
    {
        ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_SVC_SPAT_INP];

        ps_mem_rec->u4_mem_size =
            isvce_get_svc_inp_buf_size(ps_ip->s_svc_inp_params.u1_num_spatial_layers,
                                       ps_ip->s_svc_inp_params.d_spatial_res_ratio, u4_wd, u4_ht);

        DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_SVC_SPAT_INP, ps_mem_rec->u4_mem_size);
    }

    /* Buffer for storing Downscaler data */
    {
        ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_DOWN_SCALER];

        ps_mem_rec->u4_mem_size = isvce_get_downscaler_data_size(
            ps_ip->s_svc_inp_params.u1_num_spatial_layers,
            ps_ip->s_svc_inp_params.d_spatial_res_ratio, u4_wd, u4_ht);

        DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_DOWN_SCALER, ps_mem_rec->u4_mem_size);
    }

    {
        ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_SVC_ILP_DATA];

        ps_mem_rec->u4_mem_size =
            isvce_get_svc_ilp_buf_size(ps_ip->s_svc_inp_params.u1_num_spatial_layers,
                                       ps_ip->s_svc_inp_params.d_spatial_res_ratio, u4_wd, u4_ht);

        DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_SVC_ILP_DATA, ps_mem_rec->u4_mem_size);
    }

    {
        ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_SVC_ILP_MV_CTXT];

        ps_mem_rec->u4_mem_size =
            isvce_get_ilp_mv_ctxt_size(ps_ip->s_svc_inp_params.u1_num_spatial_layers,
                                       ps_ip->s_svc_inp_params.d_spatial_res_ratio, u4_wd, u4_ht);

        DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_SVC_ILP_MV_CTXT, ps_mem_rec->u4_mem_size);
    }

    {
        ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_SVC_RES_PRED_CTXT];

        ps_mem_rec->u4_mem_size = isvce_get_svc_res_pred_ctxt_size(
            ps_ip->s_svc_inp_params.u1_num_spatial_layers,
            ps_ip->s_svc_inp_params.d_spatial_res_ratio, u4_wd, u4_ht);

        DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_SVC_RES_PRED_CTXT,
              ps_mem_rec->u4_mem_size);
    }

    {
        ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_SVC_INTRA_PRED_CTXT];

        ps_mem_rec->u4_mem_size = isvce_get_svc_intra_pred_ctxt_size(
            ps_ip->s_svc_inp_params.u1_num_spatial_layers,
            ps_ip->s_svc_inp_params.d_spatial_res_ratio, u4_wd, u4_ht);

        DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_SVC_INTRA_PRED_CTXT,
              ps_mem_rec->u4_mem_size);
    }

    {
        ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_SVC_RC_UTILS_CTXT];

        ps_mem_rec->u4_mem_size = isvce_get_rc_utils_data_size();

        DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_SVC_RC_UTILS_CTXT,
              ps_mem_rec->u4_mem_size);
    }

    {
        ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_SVC_SUB_PIC_RC_CTXT];

        ps_mem_rec->u4_mem_size = isvce_get_sub_pic_rc_ctxt_size(
            ps_ip->s_svc_inp_params.u1_num_spatial_layers,
            ps_ip->s_svc_inp_params.d_spatial_res_ratio, u4_wd, u4_ht);

        DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_SVC_SUB_PIC_RC_CTXT,
              ps_mem_rec->u4_mem_size);
    }

#if ENABLE_MODE_STAT_VISUALISER
    {
        ps_mem_rec = &ps_mem_rec_base[MEM_MODE_STAT_VISUALISER_BUF];

        ps_mem_rec->u4_mem_size = isvce_get_msv_ctxt_size(u4_wd, u4_ht);

        DEBUG("\nMemory record Id %d = %d \n", MEM_MODE_STAT_VISUALISER_BUF,
              ps_mem_rec->u4_mem_size);
    }
#endif

    /************************************************************************
     * RC mem records                                                       *
     ************************************************************************/
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_RC];
    {
        isvce_get_rate_control_mem_tab(NULL, ps_mem_rec, FILL_MEMTAB);
    }
    DEBUG("\nMemory record Id %d = %d \n", ISVCE_MEM_REC_RC, ps_mem_rec->u4_mem_size);

    /* Each memtab size is aligned to next multiple of 128 bytes */
    /* This is to ensure all the memtabs start at different cache lines */
    ps_mem_rec = ps_mem_rec_base;
    for(i = 0; i < ISVCE_MEM_REC_CNT; i++)
    {
        ps_mem_rec->u4_mem_size = ALIGN128(ps_mem_rec->u4_mem_size);
        ps_mem_rec++;
    }

    ps_op->s_ive_op.u4_num_mem_rec = ISVCE_MEM_REC_CNT;

    DEBUG("Num mem recs in fill call : %d\n", ps_op->s_ive_op.u4_num_mem_rec);

    return (status);
}

/**
*******************************************************************************
*
* @brief
*  Initializes from mem records passed to the codec
*
* @par Description:
*  Initializes pointers based on mem records passed
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
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static WORD32 isvce_init_mem_rec(iv_obj_t *ps_codec_obj, void *pv_api_ip, void *pv_api_op)
{
    /* api call I/O structures */
    isvce_init_ip_t *ps_ip = pv_api_ip;
    isvce_init_op_t *ps_op = pv_api_op;

    /* mem records */
    iv_mem_rec_t *ps_mem_rec_base, *ps_mem_rec;

    /* codec variables */
    isvce_codec_t *ps_codec;
    isvce_cabac_ctxt_t *ps_cabac;
    isvce_mb_info_ctxt_t *ps_mb_map_ctxt_inc;

    isvce_cfg_params_t *ps_cfg;

    /* frame dimensions */
    WORD32 max_wd_luma, max_ht_luma;
    WORD32 max_mb_rows, max_mb_cols, max_mb_cnt;

    /* temp var */
    WORD32 i, j;
    WORD32 status = IV_SUCCESS;

    /* mem records */
    ps_mem_rec_base = ps_ip->s_ive_ip.ps_mem_rec;

    /* memset all allocated memory, except the first one. First buffer (i.e. i == MEM_REC_IV_OBJ)
       is initialized by application before calling this init function */
    for(i = ISVCE_MEM_REC_CODEC; i < ISVCE_MEM_REC_CNT; i++)
    {
        ps_mem_rec = &ps_mem_rec_base[i];
        memset(ps_mem_rec->pv_base, 0, ps_mem_rec->u4_mem_size);
    }

    /* Init mem records */
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_CODEC];
    {
        ps_codec_obj->pv_codec_handle = ps_mem_rec->pv_base;
        ps_codec = (isvce_codec_t *) (ps_codec_obj->pv_codec_handle);
    }
    /* Init mem records_cabac ctxt */
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_CABAC];
    {
        ps_cabac = (isvce_cabac_ctxt_t *) (ps_mem_rec->pv_base);
    }

    /* Init mem records mb info array for CABAC */
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_CABAC_MB_INFO];
    {
        ps_mb_map_ctxt_inc = (isvce_mb_info_ctxt_t *) (ps_mem_rec->pv_base);
    }

    /* Note this memset can not be done in init() call, since init will called
     during reset as well. And calling this during reset will mean all pointers
     need to reinitialized */
    memset(ps_codec, 0, sizeof(isvce_codec_t));
    memset(ps_cabac, 0, sizeof(isvce_cabac_ctxt_t));

    /* Set default Config Params */
    ps_cfg = &ps_codec->s_cfg;
    isvce_set_default_params(ps_cfg);

    /* get new input dimensions that satisfy the SVC and libavc constraints
    constraint 1) All layers of SVC should have dimensions that are a multiple of
    16 constraint 2) Dimension of Li layer = dimension of Li-1 layer * scaling
    factor*/

    isvce_get_svc_compliant_dimensions(ps_ip->s_svc_inp_params.u1_num_spatial_layers,
                                       ps_ip->s_svc_inp_params.d_spatial_res_ratio, ps_ip->u4_wd,
                                       ps_ip->u4_ht, &ps_cfg->u4_wd, &ps_cfg->u4_ht);

    /* Update config params as per input */
    ps_cfg->u4_max_wd = ps_cfg->u4_disp_wd = ALIGN16(ps_cfg->u4_wd);
    ps_cfg->u4_max_ht = ps_cfg->u4_disp_ht = ALIGN16(ps_cfg->u4_ht);
    ps_cfg->i4_wd_mbs = ps_cfg->u4_max_wd >> 4;
    ps_cfg->i4_ht_mbs = ps_cfg->u4_max_ht >> 4;
    ps_cfg->u4_max_ref_cnt = ps_ip->s_ive_ip.u4_max_ref_cnt;
    ps_cfg->u4_max_reorder_cnt = ps_ip->s_ive_ip.u4_max_reorder_cnt;
    ps_cfg->u4_max_level = ps_ip->s_ive_ip.u4_max_level;
    ps_cfg->e_inp_color_fmt = ps_ip->s_ive_ip.e_inp_color_fmt;
    ps_cfg->e_recon_color_fmt = ps_ip->s_ive_ip.e_recon_color_fmt;
    ps_cfg->u4_max_framerate = ps_ip->s_ive_ip.u4_max_framerate;
    for(i = 0; i < ps_ip->s_svc_inp_params.u1_num_spatial_layers; i++)
    {
        ps_cfg->au4_max_bitrate[i] = ps_ip->pu4_max_bitrate[i];
    }
    ps_cfg->u4_num_bframes = ps_ip->s_ive_ip.u4_num_bframes;
    ps_cfg->e_content_type = ps_ip->s_ive_ip.e_content_type;
    ps_cfg->u4_max_srch_rng_x = ps_ip->s_ive_ip.u4_max_srch_rng_x;
    ps_cfg->u4_max_srch_rng_y = ps_ip->s_ive_ip.u4_max_srch_rng_y;
    ps_cfg->e_slice_mode = ps_ip->s_ive_ip.e_slice_mode;
    ps_cfg->u4_slice_param = ps_ip->s_ive_ip.u4_slice_param;
    ps_cfg->e_arch = ps_ip->s_ive_ip.e_arch;
    ps_cfg->e_soc = ps_ip->s_ive_ip.e_soc;
    ps_cfg->u4_enable_recon = ps_ip->s_ive_ip.u4_enable_recon;
    ps_cfg->e_rc_mode = ps_ip->s_ive_ip.e_rc_mode;
    ps_cfg->u4_disable_vui = ps_ip->b_use_default_vui;

    ps_cfg->s_svc_params.u1_num_temporal_layers = ps_ip->s_svc_inp_params.u1_num_temporal_layers;

    ps_cfg->s_svc_params.u1_num_spatial_layers = ps_ip->s_svc_inp_params.u1_num_spatial_layers;

    ps_cfg->s_svc_params.d_spatial_res_ratio = ps_ip->s_svc_inp_params.d_spatial_res_ratio;

    ps_cfg->b_nalu_info_export_enable = ps_ip->b_nalu_info_export_enable;

    /* frame dimensions */
    max_ht_luma = ALIGN16(ps_cfg->u4_ht);
    max_wd_luma = ALIGN16(ps_cfg->u4_wd);
    max_mb_rows = max_ht_luma / MB_SIZE;
    max_mb_cols = max_wd_luma / MB_SIZE;
    max_mb_cnt = max_mb_rows * max_mb_cols;

    /* Validate params */
    ps_op->s_ive_op.u4_error_code |= isvce_svc_inp_params_validate(ps_ip, ps_cfg);

    if(ps_op->s_ive_op.u4_error_code != IV_SUCCESS)
    {
        return IV_FAIL;
    }

#if defined(X86)
    if((ps_cfg->e_arch != ARCH_X86_GENERIC) && (ps_cfg->e_arch != ARCH_X86_SSSE3) &&
       (ps_cfg->e_arch != ARCH_X86_SSE42))
    {
        ps_cfg->e_arch = ARCH_X86_SSE42;
    }
#else
    if((ps_cfg->e_arch == ARCH_X86_GENERIC) || (ps_cfg->e_arch == ARCH_X86_SSSE3) ||
       (ps_cfg->e_arch == ARCH_X86_SSE42))
    {
#if defined(DISABLE_NEON)
        ps_cfg->e_arch = ARCH_ARM_NONEON;
#elif defined(ARMV8)
        ps_cfg->e_arch = ARCH_ARM_V8_NEON;
#else
        ps_cfg->e_arch = ARCH_ARM_A7;
#endif
    }
#endif

    if((ps_ip->s_ive_ip.u4_max_level < MIN_LEVEL) || (ps_ip->s_ive_ip.u4_max_level > MAX_LEVEL))
    {
        ps_op->s_ive_op.u4_error_code |= IH264E_CODEC_LEVEL_NOT_SUPPORTED;
        ps_cfg->u4_max_level = DEFAULT_MAX_LEVEL;
    }

    if(ps_ip->s_ive_ip.u4_max_ref_cnt > MAX_REF_CNT)
    {
        ps_op->s_ive_op.u4_error_code |= IH264E_NUM_REF_UNSUPPORTED;
        ps_cfg->u4_max_ref_cnt = MAX_REF_CNT;
    }

    if(ps_ip->s_ive_ip.u4_max_reorder_cnt > MAX_REF_CNT)
    {
        ps_op->s_ive_op.u4_error_code |= IH264E_NUM_REORDER_UNSUPPORTED;
        ps_cfg->u4_max_reorder_cnt = MAX_REF_CNT;
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_BACKUP];
    {
        ps_codec->ps_mem_rec_backup = (iv_mem_rec_t *) ps_mem_rec->pv_base;

        memcpy(ps_codec->ps_mem_rec_backup, ps_mem_rec_base,
               ISVCE_MEM_REC_CNT * sizeof(iv_mem_rec_t));
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_ENTROPY];
    {
        /* temp var */
        WORD32 size = 0, offset;

        for(i = 0; i < MAX_PROCESS_CTXT; i++)
        {
            if(i < MAX_PROCESS_CTXT / MAX_CTXT_SETS)
            {
                /* base ptr */
                UWORD8 *pu1_buf = ps_mem_rec->pv_base;

                /* reset size */
                size = 0;

                /* skip mb run */
                ps_codec->as_process[i].s_entropy.pi4_mb_skip_run = (WORD32 *) (pu1_buf + size);
                size += sizeof(WORD32);
                size = ALIGN8(size);

                /* entropy map */
                ps_codec->as_process[i].s_entropy.pu1_entropy_map =
                    (UWORD8 *) (pu1_buf + size + max_mb_cols);
                /* size in bytes to store entropy status of an entire frame */
                size += (max_mb_cols * max_mb_rows);
                /* add an additional 1 row of bytes to evade the special case of row 0
                 */
                size += max_mb_cols;
                size = ALIGN128(size);

                /* bit stream ptr */
                ps_codec->as_process[i].s_entropy.ps_bitstrm = (bitstrm_t *) (pu1_buf + size);
                size += sizeof(ps_codec->as_process[i].s_entropy.ps_bitstrm);
                size = ALIGN128(size);

#if ENABLE_RE_ENC_AS_SKIP
                /* bit stream ptr */
                ps_codec->as_process[i].s_entropy.ps_bitstrm_after_slice_hdr =
                    (bitstrm_t *) (pu1_buf + size);
                size += sizeof(ps_codec->as_process[i].s_entropy.ps_bitstrm_after_slice_hdr);
                size = ALIGN128(size);
#endif

                /* nnz luma */
                ps_codec->as_process[i].s_entropy.pu1_top_nnz_luma = (UWORD8(*)[4])(pu1_buf + size);
                size += (max_mb_cols * 4 * sizeof(UWORD8));
                size = ALIGN128(size);

                /* nnz chroma */
                ps_codec->as_process[i].s_entropy.pu1_top_nnz_cbcr = (UWORD8(*)[4])(pu1_buf + size);
                size += (max_mb_cols * 4 * sizeof(UWORD8));
                size = ALIGN128(size);

                /* ps_mb_qp_ctxt */
                ps_codec->as_process[i].s_entropy.ps_mb_qp_ctxt = (mb_qp_ctxt_t *) (pu1_buf + size);
                size = ALIGN128(sizeof(ps_codec->as_process[i].s_entropy.ps_mb_qp_ctxt[0]));

                offset = size;

                /* cabac Context */
                ps_codec->as_process[i].s_entropy.ps_cabac = ps_cabac;
            }
            else
            {
                /* base ptr */
                UWORD8 *pu1_buf = ps_mem_rec->pv_base;

                /* reset size */
                size = offset;

                /* skip mb run */
                ps_codec->as_process[i].s_entropy.pi4_mb_skip_run = (WORD32 *) (pu1_buf + size);
                size += sizeof(WORD32);
                size = ALIGN8(size);

                /* entropy map */
                ps_codec->as_process[i].s_entropy.pu1_entropy_map =
                    (UWORD8 *) (pu1_buf + size + max_mb_cols);
                /* size in bytes to store entropy status of an entire frame */
                size += (max_mb_cols * max_mb_rows);
                /* add an additional 1 row of bytes to evade the special case of row 0
                 */
                size += max_mb_cols;
                size = ALIGN128(size);

                /* bit stream ptr */
                ps_codec->as_process[i].s_entropy.ps_bitstrm = (bitstrm_t *) (pu1_buf + size);
                size += sizeof(ps_codec->as_process[i].s_entropy.ps_bitstrm);
                size = ALIGN128(size);

#if ENABLE_RE_ENC_AS_SKIP
                /* bit stream ptr */
                ps_codec->as_process[i].s_entropy.ps_bitstrm_after_slice_hdr =
                    (bitstrm_t *) (pu1_buf + size);
                size += sizeof(ps_codec->as_process[i].s_entropy.ps_bitstrm_after_slice_hdr);
                size = ALIGN128(size);
#endif

                /* nnz luma */
                ps_codec->as_process[i].s_entropy.pu1_top_nnz_luma =
                    (UWORD8(*)[4])(UWORD8(*)[4])(pu1_buf + size);
                size += (max_mb_cols * 4 * sizeof(UWORD8));
                size = ALIGN128(size);

                /* nnz chroma */
                ps_codec->as_process[i].s_entropy.pu1_top_nnz_cbcr = (UWORD8(*)[4])(pu1_buf + size);
                size += (max_mb_cols * 4 * sizeof(UWORD8));
                size = ALIGN128(size);

                /* ps_mb_qp_ctxt */
                ps_codec->as_process[i].s_entropy.ps_mb_qp_ctxt = (mb_qp_ctxt_t *) (pu1_buf + size);
                size = ALIGN128(sizeof(ps_codec->as_process[i].s_entropy.ps_mb_qp_ctxt[0]));

                /* cabac Context */
                ps_codec->as_process[i].s_entropy.ps_cabac = ps_cabac;
            }
        }
        ps_codec->as_process[0].s_entropy.ps_cabac->ps_mb_map_ctxt_inc_base = ps_mb_map_ctxt_inc;
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_MB_COEFF_DATA];
    {
        /* temp var */
        WORD32 size = 0, size_of_row;
        UWORD8 *pu1_buf = ps_mem_rec->pv_base;

        /* size of coeff data of 1 mb */
        size += sizeof(tu_sblk_coeff_data_t) * MAX_4x4_SUBBLKS;

        /* size of coeff data of 1 row of mb's */
        size *= max_mb_cols;

        /* align to avoid false sharing */
        size = ALIGN64(size);
        size_of_row = size;

        /* size for one full frame */
        size *= max_mb_rows;

        ps_codec->u4_size_coeff_data = size_of_row;

        for(i = 0; i < MAX_PROCESS_CTXT; i++)
        {
            if(i < MAX_PROCESS_CTXT / MAX_CTXT_SETS)
            {
                ps_codec->as_process[i].pv_pic_mb_coeff_data = pu1_buf;
                ps_codec->as_process[i].s_entropy.pv_pic_mb_coeff_data = pu1_buf;
            }
            else
            {
                ps_codec->as_process[i].pv_pic_mb_coeff_data = pu1_buf + size;
                ps_codec->as_process[i].s_entropy.pv_pic_mb_coeff_data = pu1_buf + size;
            }
        }
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_MB_HEADER_DATA];
    {
        /* temp var */
        WORD32 size, size_of_row;
        UWORD8 *pu1_buf = ps_mem_rec->pv_base;

        /* size of header data of 1 mb */
        size = sizeof(isvce_mb_hdr_t);

        /* size for 1 row of mbs */
        size = size * max_mb_cols;

        /* align to avoid any false sharing across threads */
        size = ALIGN64(size);
        size_of_row = size;

        /* size for one full frame */
        size *= max_mb_rows;

        ps_codec->u4_size_header_data = size_of_row;

        for(i = 0; i < MAX_PROCESS_CTXT; i++)
        {
            if(i < MAX_PROCESS_CTXT / MAX_CTXT_SETS)
            {
                ps_codec->as_process[i].pv_pic_mb_header_data = pu1_buf;
                ps_codec->as_process[i].s_entropy.pv_pic_mb_header_data = pu1_buf;
            }
            else
            {
                ps_codec->as_process[i].pv_pic_mb_header_data = pu1_buf + size;
                ps_codec->as_process[i].s_entropy.pv_pic_mb_header_data = pu1_buf + size;
            }
        }
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_MVBITS];
    {
        /* max srch range x */
        UWORD32 u4_srch_range_x = ps_ip->s_ive_ip.u4_max_srch_rng_x;

        /* max srch range y */
        UWORD32 u4_srch_range_y = ps_ip->s_ive_ip.u4_max_srch_rng_y;

        /* max srch range */
        UWORD32 u4_max_srch_range = MAX(u4_srch_range_x, u4_srch_range_y);

        /* temp var */
        UWORD8 *pu1_buf = ps_mem_rec->pv_base;

        /* due to subpel */
        u4_max_srch_range <<= 2;

        //        /* due to mv on either direction */
        //        u4_max_srch_range = (u4_max_srch_range << 1);

        /* due to pred mv + zero */
        u4_max_srch_range = (u4_max_srch_range << 1) + 1;

        for(i = 0; i < MAX_PROCESS_CTXT; i++)
        {
            /* me ctxt */
            isvce_me_ctxt_t *ps_mem_ctxt = &(ps_codec->as_process[i].s_me_ctxt);

            /* init at zero mv */
            ps_mem_ctxt->pu1_mv_bits = pu1_buf + u4_max_srch_range;
        }
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_SPS];
    {
        ps_codec->ps_sps_base = (sps_t *) ps_mem_rec->pv_base;
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_PPS];
    {
        ps_codec->ps_pps_base = (pps_t *) ps_mem_rec->pv_base;
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_SVC_NALU_EXT];
    {
        ps_codec->ps_svc_nalu_ext_base = ps_mem_rec->pv_base;

        for(i = 0; i < MAX_PROCESS_CTXT; i++)
        {
            if(i < MAX_PROCESS_CTXT / MAX_CTXT_SETS)
            {
                ps_codec->as_process[i].ps_svc_nalu_ext_base = ps_mem_rec->pv_base;
            }
            else
            {
                WORD32 size = SVC_MAX_SLICE_HDR_CNT * sizeof(slice_header_t);
                void *pv_buf = (UWORD8 *) ps_mem_rec->pv_base + size;

                ps_codec->as_process[i].ps_svc_nalu_ext_base = pv_buf;
            }
        }
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_SUBSET_SPS];
    {
        ps_codec->ps_subset_sps_base = ps_mem_rec->pv_base;
        for(i = 0; i < MAX_PROCESS_CTXT; i++)
        {
            ps_codec->as_process[i].ps_subset_sps_base = ps_mem_rec->pv_base;
        }
    }
    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_SLICE_HDR];
    {
        ps_codec->ps_slice_hdr_base = ps_mem_rec->pv_base;

        for(i = 0; i < MAX_PROCESS_CTXT; i++)
        {
            if(i < MAX_PROCESS_CTXT / MAX_CTXT_SETS)
            {
                ps_codec->as_process[i].ps_slice_hdr_base = ps_mem_rec->pv_base;
            }
            else
            {
                /* temp var */
                WORD32 size = SVC_MAX_SLICE_HDR_CNT * sizeof(slice_header_t);
                void *pv_buf = (UWORD8 *) ps_mem_rec->pv_base + size;

                ps_codec->as_process[i].ps_slice_hdr_base = pv_buf;
            }
        }
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_SVC_SLICE_HDR];
    {
        ps_codec->ps_svc_slice_hdr_base = ps_mem_rec->pv_base;

        for(i = 0; i < MAX_PROCESS_CTXT; i++)
        {
            ps_codec->as_process[i].ps_svc_slice_hdr_base = ps_mem_rec->pv_base;
        }
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_AIR_MAP];
    {
        /* temp var */
        UWORD8 *pu1_buf = ps_mem_rec->pv_base;

        for(i = 0; i < MAX_PROCESS_CTXT; i++)
        {
            if(i < MAX_PROCESS_CTXT / MAX_CTXT_SETS)
            {
                ps_codec->as_process[i].pu1_is_intra_coded = pu1_buf;
            }
            else
            {
                ps_codec->as_process[i].pu1_is_intra_coded = pu1_buf + max_mb_cnt;
            }
        }

        ps_codec->pu2_intr_rfrsh_map = (UWORD16 *) (pu1_buf + max_mb_cnt * MAX_CTXT_SETS);
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_SLICE_MAP];
    {
        /* pointer to storage space */
        UWORD8 *pu1_buf_ping, *pu1_buf_pong;

        /* init pointer */
        pu1_buf_ping = ps_mem_rec->pv_base;
        pu1_buf_pong = pu1_buf_ping + ALIGN64(max_mb_cnt);

        for(i = 0; i < MAX_PROCESS_CTXT; i++)
        {
            if(i < MAX_PROCESS_CTXT / MAX_CTXT_SETS)
            {
                ps_codec->as_process[i].pu1_slice_idx = pu1_buf_ping;
            }
            else
            {
                ps_codec->as_process[i].pu1_slice_idx = pu1_buf_pong;
            }
        }
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_THREAD_HANDLE];
    {
        WORD32 handle_size = ithread_get_handle_size();

        for(i = 0; i < MAX_PROCESS_THREADS; i++)
        {
            ps_codec->apv_proc_thread_handle[i] =
                (UWORD8 *) ps_mem_rec->pv_base + (i * handle_size);
        }
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_CTL_MUTEX];
    {
        ps_codec->pv_ctl_mutex = ps_mem_rec->pv_base;
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_ENTROPY_MUTEX];
    {
        ps_codec->pv_entropy_mutex = ps_mem_rec->pv_base;
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_PROC_JOBQ];
    {
        ps_codec->pv_proc_jobq_buf = ps_mem_rec->pv_base;
        ps_codec->i4_proc_jobq_buf_size = ps_mem_rec->u4_mem_size;
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_ENTROPY_JOBQ];
    {
        ps_codec->pv_entropy_jobq_buf = ps_mem_rec->pv_base;
        ps_codec->i4_entropy_jobq_buf_size = ps_mem_rec->u4_mem_size;
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_PROC_MAP];
    {
        /* pointer to storage space */
        UWORD8 *pu1_buf = ps_mem_rec->pv_base;

        /* total size of the mem record */
        WORD32 total_size = 0;

        /* size in bytes to mb core coding status of an entire frame */
        total_size = max_mb_cnt;

        /* add an additional 1 row of bytes to evade the special case of row 0 */
        total_size += max_mb_cols;

        for(i = 0; i < MAX_PROCESS_CTXT; i++)
        {
            if(i < MAX_PROCESS_CTXT / MAX_CTXT_SETS)
            {
                ps_codec->as_process[i].pu1_proc_map = pu1_buf + max_mb_cols;
            }
            else
            {
                ps_codec->as_process[i].pu1_proc_map = pu1_buf + total_size + max_mb_cols;
            }
        }
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_DBLK_MAP];
    {
        /* pointer to storage space */
        UWORD8 *pu1_buf = ps_mem_rec->pv_base;

        /* total size of the mem record */
        WORD32 total_size = 0;

        /* size in bytes to mb core coding status of an entire frame */
        total_size = max_mb_cnt;

        /* add an additional 1 row of bytes to evade the special case of row 0 */
        total_size += max_mb_cols;

        /*Align the memory offsets*/
        total_size = ALIGN64(total_size);

        for(i = 0; i < MAX_PROCESS_CTXT; i++)
        {
            if(i < MAX_PROCESS_CTXT / MAX_CTXT_SETS)
            {
                ps_codec->as_process[i].pu1_deblk_map = pu1_buf + max_mb_cols;
            }
            else
            {
                ps_codec->as_process[i].pu1_deblk_map = pu1_buf + total_size + max_mb_cols;
            }
        }
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_ME_MAP];
    {
        /* pointer to storage space */
        UWORD8 *pu1_buf = (UWORD8 *) ps_mem_rec->pv_base;

        /* total size of the mem record */
        WORD32 total_size = 0;

        /* size in bytes to mb core coding status of an entire frame */
        total_size = max_mb_cnt;

        /* add an additional 1 row of bytes to evade the special case of row 0 */
        total_size += max_mb_cols;

        for(i = 0; i < MAX_PROCESS_CTXT; i++)
        {
            if(i < MAX_PROCESS_CTXT / MAX_CTXT_SETS)
            {
                ps_codec->as_process[i].pu1_me_map = pu1_buf + max_mb_cols;
            }
            else
            {
                ps_codec->as_process[i].pu1_me_map = pu1_buf + total_size + max_mb_cols;
            }
        }
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_DPB_MGR];
    {
        ps_codec->pv_dpb_mgr = ps_mem_rec->pv_base;
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_PROC_SCRATCH];
    {
        /* pointer to storage space */
        UWORD8 *pu1_buf = (UWORD8 *) ps_mem_rec->pv_base;

        /* size of pred buffer, fwd transform output, temp buffer for inv tra */
        WORD32 size_pred_luma, size_pred_chroma, size_fwd, size_inv, size_hp;

        /* temp var */
        WORD32 size = 0;

        /* size to hold intra/inter prediction buffer */
        size_pred_luma = sizeof(UWORD8) * 16 * 16;
        size_pred_chroma = sizeof(UWORD8) * 8 * 16;

        /* size to hold fwd transform output */
        size_fwd = sizeof(WORD16) * SIZE_TRANS_BUFF;

        /* size to hold temporary data during inverse transform */
        size_inv = sizeof(WORD32) * SIZE_TMP_BUFF_ITRANS;

        /* size to hold half pel plane buffers */
        size_hp = sizeof(UWORD8) * (HP_BUFF_WD * HP_BUFF_HT);

        for(i = 0; i < MAX_PROCESS_CTXT; i++)
        {
            /* prediction buffer */
            ps_codec->as_process[i].pu1_pred_mb = (void *) (pu1_buf + size);
            ps_codec->as_process[i].i4_pred_strd = 16;
            size += size_pred_luma;
            size = ALIGN64(size);

            /* prediction buffer */
            ps_codec->as_process[i].pu1_ref_mb_intra_4x4 = (void *) (pu1_buf + size);
            size += size_pred_luma;
            size = ALIGN64(size);

            /* prediction buffer intra 16x16 */
            ps_codec->as_process[i].pu1_pred_mb_intra_16x16 = (void *) (pu1_buf + size);
            size += size_pred_luma;
            size = ALIGN64(size);

            /* prediction buffer intra 16x16 plane*/
            ps_codec->as_process[i].pu1_pred_mb_intra_16x16_plane = (void *) (pu1_buf + size);
            size += size_pred_luma;
            size = ALIGN64(size);

            /* prediction buffer intra chroma*/
            ps_codec->as_process[i].pu1_pred_mb_intra_chroma = (void *) (pu1_buf + size);
            size += size_pred_chroma;
            size = ALIGN64(size);

            /* prediction buffer intra chroma plane*/
            ps_codec->as_process[i].pu1_pred_mb_intra_chroma_plane = (void *) (pu1_buf + size);
            size += size_pred_chroma;
            size = ALIGN64(size);

            /* Fwd transform output */
            ps_codec->as_process[i].pi2_res_buf = (void *) (pu1_buf + size);
            ps_codec->as_process[i].i4_res_strd = 16;
            size += size_fwd;
            size = ALIGN64(size);

            /* Fwd transform output */
            ps_codec->as_process[i].pi2_res_buf_intra_4x4 = (void *) (pu1_buf + size);
            size += size_fwd;
            size = ALIGN64(size);

            /* scratch buffer used during inverse transform */
            ps_codec->as_process[i].pv_scratch_buff = (void *) (pu1_buf + size);
            size += size_inv;
            size = ALIGN64(size);

            for(j = 0; j < SUBPEL_BUFF_CNT; j++)
            {
                ps_codec->as_process[i].apu1_subpel_buffs[j] = (pu1_buf + size);
                size += ALIGN64(size_hp);
            }
        }
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_QUANT_PARAM];
    {
        /* pointer to storage space */
        UWORD8 *pu1_buf = (UWORD8 *) ps_mem_rec->pv_base;

        /* size of qp, threshold matrix, fwd scaling list for one plane */
        WORD32 size_quant_param, size_thres_mat, size_fwd_weight_mat, size_satqd_weight_mat;

        /* temp var */
        WORD32 total_size = 0;

        /* size of quantization parameter list of 1 plane */
        size_quant_param = ALIGN64(sizeof(quant_params_t));

        /* size of threshold matrix for quantization
         * (assuming the transform_8x8_flag is disabled).
         * for 1 plane */
        size_thres_mat = ALIGN64(sizeof(WORD16) * 4 * 4);

        /* size of forward weight matrix for quantization
         * (assuming the transform_8x8_flag is disabled).
         * for 1 plane */
        size_fwd_weight_mat = ALIGN64(sizeof(WORD16) * 4 * 4);

        /* size of SATQD matrix*/
        size_satqd_weight_mat = ALIGN64(sizeof(UWORD16) * 9);

        for(i = 0; i < MAX_PROCESS_CTXT; i++)
        {
            quant_params_t **ps_qp_params = ps_codec->as_process[i].ps_qp_params;

            /* quantization param structure */
            ps_qp_params[0] = (quant_params_t *) (pu1_buf + total_size);
            total_size = total_size + size_quant_param;
            ps_qp_params[1] = (quant_params_t *) (pu1_buf + total_size);
            total_size = total_size + size_quant_param;
            ps_qp_params[2] = (quant_params_t *) (pu1_buf + total_size);
            total_size = total_size + size_quant_param;

            /* threshold matrix for quantization */
            ps_qp_params[0]->pu2_thres_mat = (void *) (pu1_buf + total_size);
            total_size = total_size + size_thres_mat;
            ps_qp_params[1]->pu2_thres_mat = (void *) (pu1_buf + total_size);
            total_size = total_size + size_thres_mat;
            ps_qp_params[2]->pu2_thres_mat = (void *) (pu1_buf + total_size);
            total_size = total_size + size_thres_mat;

            /* fwd weight matrix */
            ps_qp_params[0]->pu2_weigh_mat = (void *) (pu1_buf + total_size);
            total_size = total_size + size_fwd_weight_mat;
            ps_qp_params[1]->pu2_weigh_mat = (void *) (pu1_buf + total_size);
            total_size = total_size + size_fwd_weight_mat;
            ps_qp_params[2]->pu2_weigh_mat = (void *) (pu1_buf + total_size);
            total_size = total_size + size_fwd_weight_mat;

            /* threshold matrix for SATQD */
            ps_qp_params[0]->pu2_sad_thrsh = (void *) (pu1_buf + total_size);
            total_size = total_size + size_satqd_weight_mat;
            ps_qp_params[1]->pu2_sad_thrsh = (void *) (pu1_buf + total_size);
            total_size = total_size + size_satqd_weight_mat;
            ps_qp_params[2]->pu2_sad_thrsh = (void *) (pu1_buf + total_size);
            total_size = total_size + size_satqd_weight_mat;

            total_size = ALIGN128(total_size);
        }
    }

    isvce_svc_nbr_info_buf_init(ps_codec, &ps_mem_rec_base[ISVCE_MEM_REC_TOP_ROW_SYN_INFO]);

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_BS_QP];
    {
        UWORD8 *pu1_buf_ping;

        /* size in bytes to store vertical edge bs, horizontal edge bs and qp of
         * every mb*/
        WORD32 vert_bs_size, horz_bs_size, qp_size;

        /* vertical edge bs = total number of vertical edges * number of bytes per
         * each edge */
        /* total num of v edges = total mb * 4 (assuming transform_8x8_flag = 0),
         * each edge is formed by 4 pairs of subblks, requiring 4 bytes to storing
         * bs */
        vert_bs_size = ALIGN64(max_mb_cnt * 4 * 4);

        /* horizontal edge bs = total number of horizontal edges * number of bytes
         * per each edge */
        /* total num of h edges = total mb * 4 (assuming transform_8x8_flag = 0),
         * each edge is formed by 4 pairs of subblks, requiring 4 bytes to storing
         * bs */
        horz_bs_size = ALIGN64(max_mb_cnt * 4 * 4);

        /* qp of each mb requires 1 byte */
        qp_size = ALIGN64(max_mb_cnt);

        for(i = 0; i < MAX_PROCESS_CTXT; i++)
        {
            pu1_buf_ping = (UWORD8 *) ps_mem_rec->pv_base;

            /* vertical edge bs storage space */
            ps_codec->as_process[i].s_deblk_ctxt.s_bs_ctxt.pu4_pic_vert_bs =
                (UWORD32 *) pu1_buf_ping;
            pu1_buf_ping += vert_bs_size;

            ps_codec->as_process[i].s_deblk_ctxt.s_bs_ctxt.pu4_intra_base_vert_bs =
                (UWORD32 *) pu1_buf_ping;
            pu1_buf_ping += vert_bs_size;

            /* horizontal edge bs storage space */
            ps_codec->as_process[i].s_deblk_ctxt.s_bs_ctxt.pu4_pic_horz_bs =
                (UWORD32 *) pu1_buf_ping;
            pu1_buf_ping += horz_bs_size;

            ps_codec->as_process[i].s_deblk_ctxt.s_bs_ctxt.pu4_intra_base_horz_bs =
                (UWORD32 *) pu1_buf_ping;
            pu1_buf_ping += horz_bs_size;

            /* qp */
            ps_codec->as_process[i].s_deblk_ctxt.s_bs_ctxt.pu1_pic_qp = (UWORD8 *) pu1_buf_ping;
            pu1_buf_ping += qp_size;
        }
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_INP_PIC];
    {
        ps_codec->pv_inp_buf_mgr_base = ps_mem_rec->pv_base;
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_OUT];
    {
        ps_codec->pv_out_buf_mgr_base = ps_mem_rec->pv_base;
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_CSC];
    {
        ps_codec->pu1_y_csc_buf_base = ps_mem_rec->pv_base;
        ps_codec->pu1_uv_csc_buf_base =
            (UWORD8 *) ps_mem_rec->pv_base + (max_ht_luma * max_wd_luma);
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_REF_PIC];
    {
        /* size of buf mgr struct */
        WORD32 size = ih264_buf_mgr_size();

        /* temp var */
        UWORD8 *pu1_buf = ps_mem_rec->pv_base;

        /* pic buffer mgr */
        ps_codec->pv_ref_buf_mgr_base = pu1_buf;

        /* picture bank */
        ps_codec->ps_pic_buf_base = (svc_au_buf_t *) (pu1_buf + size);
        ps_codec->i4_total_pic_buf_size = ps_mem_rec->u4_mem_size - size;
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_MVBANK];
    {
        /* size of buf mgr struct */
        WORD32 size = ih264_buf_mgr_size();

        /* temp var */
        UWORD8 *pu1_buf = ps_mem_rec->pv_base;

        /* mv buffer mgr */
        ps_codec->pv_svc_au_data_store_mgr_base = pu1_buf;

        /* mv bank */
        ps_codec->ps_svc_au_data_base = (svc_au_data_t *) (pu1_buf + size);
        ps_codec->i4_svc_au_data_size = ps_mem_rec->u4_mem_size - size;
    }

    ps_mem_rec = &ps_mem_rec_base[ISVCE_MEM_REC_MB_INFO_NMB];
    {
        /* temp var */
        UWORD8 *pu1_buf = ps_mem_rec->pv_base;

        /* size of nmb ctxt */
        WORD32 size = max_mb_cols * sizeof(isvce_mb_info_nmb_t);

        WORD32 nmb_cntr, subpel_buf_size;

        /* init nmb info structure pointer in all proc ctxts */
        for(i = 0; i < MAX_PROCESS_CTXT; i++)
        {
            ps_codec->as_process[i].ps_nmb_info = (isvce_mb_info_nmb_t *) (pu1_buf);

            pu1_buf += size;
        }

        /* Additional 4 bytes to allow use of '_mm_loadl_epi64' */
        subpel_buf_size = (MB_SIZE * MB_SIZE + 4) * sizeof(UWORD8);

        /* adjusting pointers for nmb halfpel buffer */
        for(i = 0; i < MAX_PROCESS_CTXT; i++)
        {
            isvce_mb_info_nmb_t *ps_mb_info_nmb = &ps_codec->as_process[i].ps_nmb_info[0];

            for(nmb_cntr = 0; nmb_cntr < max_mb_cols; nmb_cntr++)
            {
                ps_mb_info_nmb[nmb_cntr].pu1_best_sub_pel_buf = pu1_buf;

                pu1_buf = pu1_buf + subpel_buf_size;

                ps_mb_info_nmb[nmb_cntr].u4_bst_spel_buf_strd = MB_SIZE;
            }
        }
    }

    isvce_svc_inp_buf_init(ps_codec, &ps_mem_rec_base[ISVCE_MEM_SVC_SPAT_INP]);

    isvce_initialize_downscaler(&ps_codec->s_scaler, &ps_mem_rec_base[ISVCE_MEM_DOWN_SCALER],
                                ps_codec->s_cfg.s_svc_params.d_spatial_res_ratio,
                                ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers,
                                ps_codec->s_cfg.u4_wd, ps_codec->s_cfg.u4_ht,
                                ps_codec->s_cfg.e_arch);

    isvce_svc_ilp_buf_init(ps_codec, &ps_mem_rec_base[ISVCE_MEM_SVC_ILP_DATA]);

    isvce_ilp_mv_ctxt_init(ps_codec, &ps_mem_rec_base[ISVCE_MEM_SVC_ILP_MV_CTXT]);

    isvce_svc_res_pred_ctxt_init(ps_codec, &ps_mem_rec_base[ISVCE_MEM_SVC_RES_PRED_CTXT]);

    isvce_intra_pred_ctxt_init(ps_codec, &ps_mem_rec_base[ISVCE_MEM_SVC_INTRA_PRED_CTXT]);

    isvce_rc_utils_init(&ps_codec->s_rate_control.s_rc_utils,
                        &ps_mem_rec_base[ISVCE_MEM_SVC_RC_UTILS_CTXT], ps_codec->s_cfg.e_arch);

#if ENABLE_MODE_STAT_VISUALISER
    isvce_msv_ctxt_init(ps_codec, &ps_mem_rec_base[MEM_MODE_STAT_VISUALISER_BUF]);
#endif

    isvce_get_rate_control_mem_tab(&ps_codec->s_rate_control, &ps_mem_rec_base[ISVCE_MEM_REC_RC],
                                   USE_BASE);

    isvce_sub_pic_rc_ctxt_init(ps_codec, &ps_mem_rec_base[ISVCE_MEM_SVC_SUB_PIC_RC_CTXT]);

    status = isvce_init(ps_codec);

    return status;
}

/**
*******************************************************************************
*
* @brief
*  Retrieves mem records passed to the codec
*
* @par Description:
*  Retrieves mem recs passed during init
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
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static WORD32 isvce_retrieve_memrec(iv_obj_t *ps_codec_obj, void *pv_api_ip, void *pv_api_op)
{
    isvce_codec_t *ps_codec = (isvce_codec_t *) ps_codec_obj->pv_codec_handle;

    /* ctrl call I/O structures */
    isvce_retrieve_mem_rec_ip_t *ps_ip = pv_api_ip;
    isvce_retrieve_mem_rec_op_t *ps_op = pv_api_op;

    if(ps_codec->i4_init_done != 1)
    {
        ps_op->s_ive_op.u4_error_code |= 1 << IVE_FATALERROR;
        ps_op->s_ive_op.u4_error_code |= IH264E_INIT_NOT_DONE;
        return IV_FAIL;
    }

    /* join threads upon at end of sequence */
    isvce_join_threads(ps_codec);

    /* collect list of memory records used by the encoder library */
    memcpy(ps_ip->s_ive_ip.ps_mem_rec, ps_codec->ps_mem_rec_backup,
           ISVCE_MEM_REC_CNT * (sizeof(iv_mem_rec_t)));
    ps_op->s_ive_op.u4_num_mem_rec_filled = ISVCE_MEM_REC_CNT;

    /* clean up mutex memory */
    ih264_list_free(ps_codec->pv_entropy_jobq);
    ih264_list_free(ps_codec->pv_proc_jobq);
    ithread_mutex_destroy(ps_codec->pv_ctl_mutex);
    ithread_mutex_destroy(ps_codec->pv_entropy_mutex);

    ih264_buf_mgr_free((buf_mgr_t *) ps_codec->pv_svc_au_data_store_mgr);
    ih264_buf_mgr_free((buf_mgr_t *) ps_codec->pv_ref_buf_mgr);
    ih264_buf_mgr_free((buf_mgr_t *) ps_codec->pv_inp_buf_mgr);
    ih264_buf_mgr_free((buf_mgr_t *) ps_codec->pv_out_buf_mgr);

#if ENABLE_MODE_STAT_VISUALISER
    isvce_msv_ctxt_delete(ps_codec->ps_mode_stat_visualiser);
#endif

    isvce_sub_pic_rc_ctxt_delete(ps_codec->as_process->ps_sub_pic_rc_ctxt);

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Sets the encoder in flush mode.
*
* @par Description:
*  Sets the encoder in flush mode
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
* @returns error status
*
* @remarks This call has no real effect on encoder
*
*******************************************************************************
*/
static WORD32 isvce_set_flush_mode(iv_obj_t *ps_codec_obj, void *pv_api_ip, void *pv_api_op)
{
    /* codec ctxt */
    isvce_codec_t *ps_codec = (isvce_codec_t *) ps_codec_obj->pv_codec_handle;

    /* ctrl call I/O structures */
    isvce_ctl_flush_op_t *ps_ctl_op = pv_api_op;

    UNUSED(pv_api_ip);

    ps_ctl_op->s_ive_op.u4_error_code = 0;

    /* signal flush frame control call */
    ps_codec->i4_flush_mode = 1;

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Gets encoder buffer requirements
*
* @par Description:
*  Gets the encoder buffer requirements. Basing on max width and max height
*  configuration settings, this routine, computes the sizes of necessary input,
*  output buffers returns this info to callee.
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
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static WORD32 isvce_get_buf_info(void *pv_codec_handle, void *pv_api_ip, void *pv_api_op)
{
    WORD32 i;
    UWORD32 wd, ht;

    isvce_codec_t *ps_codec = (isvce_codec_t *) pv_codec_handle;
    isvce_ctl_getbufinfo_ip_t *ps_ip = pv_api_ip;
    isvce_ctl_getbufinfo_op_t *ps_op = pv_api_op;

    isvce_get_svc_compliant_dimensions(ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers,
                                       ps_codec->s_cfg.s_svc_params.d_spatial_res_ratio,
                                       ALIGN16(ps_ip->s_ive_ip.u4_max_wd),
                                       ALIGN16(ps_ip->s_ive_ip.u4_max_ht), &wd, &ht);

    ps_op->s_ive_op.u4_error_code = 0;

    /* Number of components in input buffers required for codec  &
     * Minimum sizes of each component in input buffer required */
    if(ps_ip->s_ive_ip.e_inp_color_fmt == IV_YUV_420P)
    {
        ps_op->s_ive_op.u4_inp_comp_cnt = MIN_RAW_BUFS_420_COMP;

        ps_op->s_ive_op.au4_min_in_buf_size[0] = wd * ht;
        ps_op->s_ive_op.au4_min_in_buf_size[1] = (wd >> 1) * (ht >> 1);
        ps_op->s_ive_op.au4_min_in_buf_size[2] = (wd >> 1) * (ht >> 1);
    }
    else if(ps_ip->s_ive_ip.e_inp_color_fmt == IV_YUV_422ILE)
    {
        ps_op->s_ive_op.u4_inp_comp_cnt = MIN_RAW_BUFS_422ILE_COMP;

        ps_op->s_ive_op.au4_min_in_buf_size[0] = wd * ht * 2;
        ps_op->s_ive_op.au4_min_in_buf_size[1] = ps_op->s_ive_op.au4_min_in_buf_size[2] = 0;
    }
    else if(ps_ip->s_ive_ip.e_inp_color_fmt == IV_RGB_565)
    {
        ps_op->s_ive_op.u4_inp_comp_cnt = MIN_RAW_BUFS_RGB565_COMP;

        ps_op->s_ive_op.au4_min_in_buf_size[0] = wd * ht * 2;
        ps_op->s_ive_op.au4_min_in_buf_size[1] = ps_op->s_ive_op.au4_min_in_buf_size[2] = 0;
    }
    else if(ps_ip->s_ive_ip.e_inp_color_fmt == IV_RGBA_8888)
    {
        ps_op->s_ive_op.u4_inp_comp_cnt = MIN_RAW_BUFS_RGBA8888_COMP;

        ps_op->s_ive_op.au4_min_in_buf_size[0] = wd * ht * 4;
        ps_op->s_ive_op.au4_min_in_buf_size[1] = ps_op->s_ive_op.au4_min_in_buf_size[2] = 0;
    }
    else if((ps_ip->s_ive_ip.e_inp_color_fmt == IV_YUV_420SP_UV) ||
            (ps_ip->s_ive_ip.e_inp_color_fmt == IV_YUV_420SP_VU))
    {
        ps_op->s_ive_op.u4_inp_comp_cnt = MIN_RAW_BUFS_420SP_COMP;

        ps_op->s_ive_op.au4_min_in_buf_size[0] = wd * ht;
        ps_op->s_ive_op.au4_min_in_buf_size[1] = wd * (ht >> 1);
        ps_op->s_ive_op.au4_min_in_buf_size[2] = 0;
    }

    /* Number of components in output buffers required for codec  &
     * Minimum sizes of each component in output buffer required */
    ps_op->s_ive_op.u4_out_comp_cnt = MIN_BITS_BUFS_COMP;

    for(i = 0; i < (WORD32) ps_op->s_ive_op.u4_out_comp_cnt; i++)
    {
        ps_op->s_ive_op.au4_min_out_buf_size[i] =
            MAX(((wd * ht * 3) >> 1), MIN_STREAM_SIZE) *
            ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers;
    }

    ps_op->u4_rec_comp_cnt = MIN_RAW_BUFS_420_COMP;
    ps_op->au4_min_rec_buf_size[0] = wd * ht;
    ps_op->au4_min_rec_buf_size[1] = (wd >> 1) * (ht >> 1);
    ps_op->au4_min_rec_buf_size[2] = (wd >> 1) * (ht >> 1);

    if(ps_codec->s_cfg.b_nalu_info_export_enable)
    {
        ps_op->u4_min_nalu_info_buf_size =
            isvce_get_nalu_info_buf_size(ps_codec->s_cfg.s_svc_params.u1_num_spatial_layers);
    }
    else
    {
        ps_op->u4_min_nalu_info_buf_size = 0;
    }

    ps_op->s_ive_op.u4_min_inp_bufs = MIN_INP_BUFS;
    ps_op->s_ive_op.u4_min_out_bufs = MIN_OUT_BUFS;
    ps_op->u4_min_rec_bufs = MIN_OUT_BUFS;
    ps_op->u4_min_nalu_info_bufs = MIN_OUT_BUFS;

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Sets the picture dimensions
*
* @par Description:
*  Sets width, height, display width, display height and strides
*
* @param[in] pv_api_ip
*  Pointer to input argument structure
*
* @param[out] pv_api_op
*  Pointer to output argument structure
*
* @param[out] ps_cfg
*  Pointer to config structure to be updated
*
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static IV_STATUS_T isvce_set_dimensions(void *pv_api_ip, void *pv_api_op,
                                        isvce_cfg_params_t *ps_cfg)
{
    isvce_ctl_set_dimensions_ip_t *ps_ip = pv_api_ip;
    isvce_ctl_set_dimensions_op_t *ps_op = pv_api_op;

    ps_op->s_ive_op.u4_error_code = 0;

    isvce_get_svc_compliant_dimensions(
        ps_cfg->s_svc_params.u1_num_spatial_layers, ps_cfg->s_svc_params.d_spatial_res_ratio,
        ps_ip->s_ive_ip.u4_wd, ps_ip->s_ive_ip.u4_ht, &ps_cfg->u4_wd, &ps_cfg->u4_ht);

    ASSERT(0 == (ps_cfg->u4_wd % MB_SIZE));
    ASSERT(0 == (ps_cfg->u4_ht % MB_SIZE));

    ps_cfg->i4_wd_mbs = ps_cfg->u4_wd / MB_SIZE;
    ps_cfg->i4_ht_mbs = ps_cfg->u4_ht / MB_SIZE;
    ps_cfg->u4_disp_wd = ps_cfg->u4_wd;
    ps_cfg->u4_disp_ht = ps_cfg->u4_ht;

    ps_cfg->u4_timestamp_high = ps_ip->s_ive_ip.u4_timestamp_high;
    ps_cfg->u4_timestamp_low = ps_ip->s_ive_ip.u4_timestamp_low;

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Provide dimensions used for encoding
*
* @param[in] pv_api_ip
*  Pointer to input argument structure
*
* @param[out] pv_api_op
*  Pointer to output argument structure
*
* @param[out] ps_cfg
*  Pointer to config structure
*
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static IV_STATUS_T isvce_get_enc_frame_dimensions(isvce_ctl_get_enc_dimensions_ip_t *ps_ip,
                                                  isvce_ctl_get_enc_dimensions_op_t *ps_op,
                                                  isvce_cfg_params_t *ps_cfg)
{
    ps_op->u4_error_code = IVE_ERR_NONE;

    isvce_get_svc_compliant_dimensions(ps_cfg->s_svc_params.u1_num_spatial_layers,
                                       ps_cfg->s_svc_params.d_spatial_res_ratio,
                                       ps_ip->u4_inp_frame_wd, ps_ip->u4_inp_frame_ht,
                                       &ps_op->u4_enc_frame_wd, &ps_op->u4_enc_frame_ht);

    ASSERT(ps_cfg->u4_wd == ps_op->u4_enc_frame_wd);
    ASSERT(ps_cfg->u4_ht == ps_op->u4_enc_frame_ht);

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Sets source and target frame rates
*
* @par Description:
*  Sets source and target frame rates
*
* @param[in] pv_api_ip
*  Pointer to input argument structure
*
* @param[out] pv_api_op
*  Pointer to output argument structure
*
* @param[out] ps_cfg
*  Pointer to config structure to be updated
*
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static IV_STATUS_T isvce_set_frame_rate(void *pv_api_ip, void *pv_api_op,
                                        isvce_cfg_params_t *ps_cfg)
{
    /* ctrl call I/O structures */
    isvce_ctl_set_frame_rate_ip_t *ps_ip = pv_api_ip;
    isvce_ctl_set_frame_rate_op_t *ps_op = pv_api_op;

    ps_op->s_ive_op.u4_error_code = 0;

    ps_cfg->u4_src_frame_rate = ps_ip->s_ive_ip.u4_src_frame_rate;
    ps_cfg->u4_tgt_frame_rate = ps_ip->s_ive_ip.u4_tgt_frame_rate;

    ps_cfg->u4_timestamp_high = ps_ip->s_ive_ip.u4_timestamp_high;
    ps_cfg->u4_timestamp_low = ps_ip->s_ive_ip.u4_timestamp_low;

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Sets target bit rate
*
* @par Description:
*  Sets target bit rate
*
* @param[in] pv_api_ip
*  Pointer to input argument structure
*
* @param[out] pv_api_op
*  Pointer to output argument structure
*
* @param[out] ps_cfg
*  Pointer to config structure to be updated
*
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static IV_STATUS_T isvce_set_bit_rate(void *pv_api_ip, void *pv_api_op, isvce_cfg_params_t *ps_cfg)
{
    /* ctrl call I/O structures */
    isvce_ctl_set_bitrate_ip_t *ps_ip = pv_api_ip;
    isvce_ctl_set_bitrate_op_t *ps_op = pv_api_op;
    WORD8 i;

    ps_op->s_ive_op.u4_error_code = 0;

    for(i = 0; i < ps_cfg->s_svc_params.u1_num_spatial_layers; i++)
    {
        ps_cfg->au4_target_bitrate[i] = ps_ip->pu4_target_bitrate[i];
    }

    ps_cfg->u4_timestamp_high = ps_ip->s_ive_ip.u4_timestamp_high;
    ps_cfg->u4_timestamp_low = ps_ip->s_ive_ip.u4_timestamp_low;

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Sets frame type
*
* @par Description:
*  Sets frame type
*
* @param[in] pv_api_ip
*  Pointer to input argument structure
*
* @param[out] pv_api_op
*  Pointer to output argument structure
*
* @param[out] ps_cfg
*  Pointer to config structure to be updated
*
* @returns error status
*
* @remarks not a sticky tag
*
*******************************************************************************
*/
static IV_STATUS_T isvce_set_frame_type(void *pv_api_ip, void *pv_api_op,
                                        isvce_cfg_params_t *ps_cfg)
{
    /* ctrl call I/O structures */
    isvce_ctl_set_frame_type_ip_t *ps_ip = pv_api_ip;
    isvce_ctl_set_frame_type_op_t *ps_op = pv_api_op;

    ps_op->s_ive_op.u4_error_code = 0;

    ps_cfg->e_frame_type = ps_ip->s_ive_ip.e_frame_type;

    ps_cfg->u4_timestamp_high = ps_ip->s_ive_ip.u4_timestamp_high;
    ps_cfg->u4_timestamp_low = ps_ip->s_ive_ip.u4_timestamp_low;

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Sets quantization params
*
* @par Description:
*  Sets the max, min and default qp for I frame, P frame and B frame
*
* @param[in] pv_api_ip
*  Pointer to input argument structure
*
* @param[out] pv_api_op
*  Pointer to output argument structure
*
* @param[out] ps_cfg
*  Pointer to config structure to be updated
*
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static IV_STATUS_T isvce_set_qp(void *pv_api_ip, void *pv_api_op, isvce_cfg_params_t *ps_cfg)
{
    /* ctrl call I/O structures */
    isvce_ctl_set_qp_ip_t *ps_set_qp_ip = pv_api_ip;
    isvce_ctl_set_qp_op_t *ps_set_qp_op = pv_api_op;
    WORD8 i;

    ps_set_qp_op->s_ive_op.u4_error_code = 0;

    for(i = 0; i < ps_cfg->s_svc_params.u1_num_spatial_layers; i++)
    {
        ps_cfg->au4_i_qp_max[i] =
            CLIP3(MIN_H264_QP, MAX_H264_QP, (WORD32) ps_set_qp_ip->pu4_i_qp_max[i]);
        ps_cfg->au4_i_qp_min[i] =
            CLIP3(MIN_H264_QP, MAX_H264_QP, (WORD32) ps_set_qp_ip->pu4_i_qp_min[i]);
        ps_cfg->au4_i_qp[i] = CLIP3(ps_set_qp_ip->pu4_i_qp_min[i], ps_set_qp_ip->pu4_i_qp_max[i],
                                    ps_set_qp_ip->pu4_i_qp[i]);
        ps_cfg->au4_i_qp_max[i] =
            CLIP3(MIN_H264_QP, MAX_H264_QP, (WORD32) ps_set_qp_ip->pu4_i_qp_max[i]);
        ps_cfg->au4_i_qp_min[i] =
            CLIP3(MIN_H264_QP, MAX_H264_QP, (WORD32) ps_set_qp_ip->pu4_i_qp_min[i]);
        ps_cfg->au4_i_qp[i] = CLIP3(ps_set_qp_ip->pu4_i_qp_min[i], ps_set_qp_ip->pu4_i_qp_max[i],
                                    ps_set_qp_ip->pu4_i_qp[i]);
        ps_cfg->au4_i_qp_max[i] =
            CLIP3(MIN_H264_QP, MAX_H264_QP, (WORD32) ps_set_qp_ip->pu4_i_qp_max[i]);
        ps_cfg->au4_i_qp_min[i] =
            CLIP3(MIN_H264_QP, MAX_H264_QP, (WORD32) ps_set_qp_ip->pu4_i_qp_min[i]);
        ps_cfg->au4_i_qp[i] = CLIP3(ps_set_qp_ip->pu4_i_qp_min[i], ps_set_qp_ip->pu4_i_qp_max[i],
                                    ps_set_qp_ip->pu4_i_qp[i]);
    }

    ps_cfg->u4_timestamp_high = ps_set_qp_ip->s_ive_ip.u4_timestamp_high;
    ps_cfg->u4_timestamp_low = ps_set_qp_ip->s_ive_ip.u4_timestamp_low;

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Sets encoding mode
*
* @par Description:
*  Sets encoding mode
*
* @param[in] pv_api_ip
*  Pointer to input argument structure
*
* @param[out] pv_api_op
*  Pointer to output argument structure
*
* @param[out] ps_cfg
*  Pointer to config structure to be updated
*
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static IV_STATUS_T isvce_set_enc_mode(void *pv_api_ip, void *pv_api_op, isvce_cfg_params_t *ps_cfg)
{
    /* ctrl call I/O structures */
    isvce_ctl_set_enc_mode_ip_t *ps_ip = pv_api_ip;
    isvce_ctl_set_enc_mode_op_t *ps_op = pv_api_op;

    ps_op->s_ive_op.u4_error_code = 0;

    ps_cfg->e_enc_mode = ps_ip->s_ive_ip.e_enc_mode;

    ps_cfg->u4_timestamp_high = ps_ip->s_ive_ip.u4_timestamp_high;
    ps_cfg->u4_timestamp_low = ps_ip->s_ive_ip.u4_timestamp_low;

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Sets vbv parameters
*
* @par Description:
*  Sets vbv parameters
*
* @param[in] pv_api_ip
*  Pointer to input argument structure
*
* @param[out] pv_api_op
*  Pointer to output argument structure
*
* @param[out] ps_cfg
*  Pointer to config structure to be updated
*
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static IV_STATUS_T isvce_set_vbv_params(void *pv_api_ip, void *pv_api_op,
                                        isvce_cfg_params_t *ps_cfg)
{
    /* ctrl call I/O structures */
    isvce_ctl_set_vbv_params_ip_t *ps_ip = pv_api_ip;
    isvce_ctl_set_vbv_params_op_t *ps_op = pv_api_op;
    WORD8 i;

    ps_op->s_ive_op.u4_error_code = 0;

    for(i = 0; i < ps_cfg->s_svc_params.u1_num_spatial_layers; i++)
    {
        ps_cfg->au4_vbv_buffer_delay[i] = ps_ip->pu4_vbv_buffer_delay[i];
    }

    ps_cfg->u4_timestamp_high = ps_ip->s_ive_ip.u4_timestamp_high;
    ps_cfg->u4_timestamp_low = ps_ip->s_ive_ip.u4_timestamp_low;

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Sets AIR parameters
*
* @par Description:
*  Sets AIR parameters
*
* @param[in] pv_api_ip
*  Pointer to input argument structure
*
* @param[out] pv_api_op
*  Pointer to output argument structure
*
* @param[out] ps_cfg
*  Pointer to config structure to be updated
*
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static IV_STATUS_T isvc_set_air_params(void *pv_api_ip, void *pv_api_op, isvce_cfg_params_t *ps_cfg)
{
    /* ctrl call I/O structures */
    isvce_ctl_set_air_params_ip_t *ps_ip = pv_api_ip;
    isvce_ctl_set_air_params_op_t *ps_op = pv_api_op;

    ps_op->s_ive_op.u4_error_code = 0;

    ps_cfg->e_air_mode = ps_ip->s_ive_ip.e_air_mode;
    ps_cfg->u4_air_refresh_period = ps_ip->s_ive_ip.u4_air_refresh_period;

    ps_cfg->u4_timestamp_high = ps_ip->s_ive_ip.u4_timestamp_high;
    ps_cfg->u4_timestamp_low = ps_ip->s_ive_ip.u4_timestamp_low;

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Sets motion estimation parameters
*
* @par Description:
*  Sets motion estimation parameters
*
* @param[in] pv_api_ip
*  Pointer to input argument structure
*
* @param[out] pv_api_op
*  Pointer to output argument structure
*
* @param[out] ps_cfg
*  Pointer to config structure to be updated
*
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static IV_STATUS_T isvc_set_me_params(void *pv_api_ip, void *pv_api_op, isvce_cfg_params_t *ps_cfg)
{
    /* ctrl call I/O structures */
    isvce_ctl_set_me_params_ip_t *ps_ip = pv_api_ip;
    isvce_ctl_set_me_params_op_t *ps_op = pv_api_op;

    ps_op->s_ive_op.u4_error_code = 0;

    ps_cfg->u4_enable_hpel = ps_ip->s_ive_ip.u4_enable_hpel;
    ps_cfg->u4_enable_qpel = ps_ip->s_ive_ip.u4_enable_qpel;
    ps_cfg->u4_enable_fast_sad = ps_ip->s_ive_ip.u4_enable_fast_sad;
    ps_cfg->u4_enable_alt_ref = ps_ip->s_ive_ip.u4_enable_alt_ref;
    ps_cfg->u4_srch_rng_x = ps_ip->s_ive_ip.u4_srch_rng_x;
    ps_cfg->u4_srch_rng_y = ps_ip->s_ive_ip.u4_srch_rng_y;
    ps_cfg->u4_me_speed_preset = ps_ip->s_ive_ip.u4_me_speed_preset;

    ps_cfg->u4_timestamp_high = ps_ip->s_ive_ip.u4_timestamp_high;
    ps_cfg->u4_timestamp_low = ps_ip->s_ive_ip.u4_timestamp_low;

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Sets Intra/Inter Prediction estimation parameters
*
* @par Description:
*  Sets Intra/Inter Prediction estimation parameters
*
* @param[in] pv_api_ip
*  Pointer to input argument structure
*
* @param[out] pv_api_op
*  Pointer to output argument structure
*
* @param[out] ps_cfg
*  Pointer to config structure to be updated
*
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static IV_STATUS_T isvc_set_ipe_params(void *pv_api_ip, void *pv_api_op, isvce_cfg_params_t *ps_cfg)
{
    /* ctrl call I/O structures */
    isvce_ctl_set_ipe_params_ip_t *ps_ip = pv_api_ip;
    isvce_ctl_set_ipe_params_op_t *ps_op = pv_api_op;

    ps_op->s_ive_op.u4_error_code = 0;

    ps_cfg->u4_enable_intra_4x4 = ps_ip->s_ive_ip.u4_enable_intra_4x4;
    ps_cfg->u4_enc_speed_preset = ps_ip->s_ive_ip.u4_enc_speed_preset;

    ps_cfg->u4_timestamp_high = ps_ip->s_ive_ip.u4_timestamp_high;
    ps_cfg->u4_timestamp_low = ps_ip->s_ive_ip.u4_timestamp_low;

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Sets GOP parameters
*
* @par Description:
*  Sets GOP parameters
*
* @param[in] pv_api_ip
*  Pointer to input argument structure
*
* @param[out] pv_api_op
*  Pointer to output argument structure
*
* @param[out] ps_cfg
*  Pointer to config structure to be updated
*
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static IV_STATUS_T isvc_set_gop_params(void *pv_api_ip, void *pv_api_op, isvce_cfg_params_t *ps_cfg)
{
    /* ctrl call I/O structures */
    isvce_ctl_set_gop_params_ip_t *ps_ip = pv_api_ip;
    isvce_ctl_set_gop_params_op_t *ps_op = pv_api_op;

    ps_op->s_ive_op.u4_error_code = 0;

    ps_cfg->u4_i_frm_interval = ps_ip->s_ive_ip.u4_i_frm_interval;
    ps_cfg->u4_idr_frm_interval = ps_ip->s_ive_ip.u4_idr_frm_interval;

    ps_cfg->u4_timestamp_high = ps_ip->s_ive_ip.u4_timestamp_high;
    ps_cfg->u4_timestamp_low = ps_ip->s_ive_ip.u4_timestamp_low;

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Sets profile parameters
*
* @par Description:
*  Sets profile parameters
*
* @param[in] pv_api_ip
*  Pointer to input argument structure
*
* @param[out] pv_api_op
*  Pointer to output argument structure
*
* @param[out] ps_cfg
*  Pointer to config structure to be updated
*
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static IV_STATUS_T isvc_set_profile_params(void *pv_api_ip, void *pv_api_op,
                                           isvce_cfg_params_t *ps_cfg)
{
    /* ctrl call I/O structures */
    isvce_ctl_set_profile_params_ip_t *ps_ip = pv_api_ip;
    isvce_ctl_set_profile_params_op_t *ps_op = pv_api_op;

    ps_op->s_ive_op.u4_error_code = 0;

    ps_cfg->e_profile = ps_ip->s_ive_ip.e_profile;

    ps_cfg->u4_entropy_coding_mode = ps_ip->s_ive_ip.u4_entropy_coding_mode;

    ps_cfg->u4_timestamp_high = ps_ip->s_ive_ip.u4_timestamp_high;
    ps_cfg->u4_timestamp_low = ps_ip->s_ive_ip.u4_timestamp_low;

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Sets disable deblock level
*
* @par Description:
*  Sets disable deblock level. Level 0 means no disabling  and level 4 means
*  disable completely. 1, 2, 3 are intermediate levels that control amount
*  of deblocking done.
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
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static WORD32 isvc_set_deblock_params(void *pv_api_ip, void *pv_api_op, isvce_cfg_params_t *ps_cfg)
{
    /* ctrl call I/O structures */
    isvce_ctl_set_deblock_params_ip_t *ps_ip = pv_api_ip;
    isvce_ctl_set_deblock_params_op_t *ps_op = pv_api_op;

    ps_op->s_ive_op.u4_error_code = 0;

    ps_cfg->u4_disable_deblock_level = ps_ip->s_ive_ip.u4_disable_deblock_level;

    ps_cfg->u4_timestamp_high = ps_ip->s_ive_ip.u4_timestamp_high;
    ps_cfg->u4_timestamp_low = ps_ip->s_ive_ip.u4_timestamp_low;

    return IV_SUCCESS;
}
/**
 *******************************************************************************
 *
 * @brief
 *  Sets vui params
 *
 * @par Description:
 *  Video usability information
 *
 * @param[in] pv_api_ip
 *  Pointer to input argument structure
 *
 * @param[out] pv_api_op
 *  Pointer to output argument structure
 *
 * @param[out] ps_cfg
 *  Pointer to config structure to be updated
 *
 * @returns error status
 *
 * @remarks none
 *
 *******************************************************************************
 */
static WORD32 isvce_set_vui_params(void *pv_api_ip, void *pv_api_op, isvce_cfg_params_t *ps_cfg)
{
    /* ctrl call I/O structures */
    isvce_vui_ip_t *ps_ip = pv_api_ip;
    isvce_vui_op_t *ps_op = pv_api_op;
    vui_t *ps_vui = &ps_cfg->s_vui;

    ps_op->u4_error_code = 0;

    ps_vui->u1_aspect_ratio_info_present_flag = ps_ip->u1_aspect_ratio_info_present_flag;
    ps_vui->u1_aspect_ratio_idc = ps_ip->u1_aspect_ratio_idc;
    ps_vui->u2_sar_width = ps_ip->u2_sar_width;
    ps_vui->u2_sar_height = ps_ip->u2_sar_height;
    ps_vui->u1_overscan_info_present_flag = ps_ip->u1_overscan_info_present_flag;
    ps_vui->u1_overscan_appropriate_flag = ps_ip->u1_overscan_appropriate_flag;
    ps_vui->u1_video_signal_type_present_flag = ps_ip->u1_video_signal_type_present_flag;
    ps_vui->u1_video_format = ps_ip->u1_video_format;
    ps_vui->u1_video_full_range_flag = ps_ip->u1_video_full_range_flag;
    ps_vui->u1_colour_description_present_flag = ps_ip->u1_colour_description_present_flag;
    ps_vui->u1_colour_primaries = ps_ip->u1_colour_primaries;
    ps_vui->u1_transfer_characteristics = ps_ip->u1_transfer_characteristics;
    ps_vui->u1_matrix_coefficients = ps_ip->u1_matrix_coefficients;
    ps_vui->u1_chroma_loc_info_present_flag = ps_ip->u1_chroma_loc_info_present_flag;
    ps_vui->u1_chroma_sample_loc_type_top_field = ps_ip->u1_chroma_sample_loc_type_top_field;
    ps_vui->u1_chroma_sample_loc_type_bottom_field = ps_ip->u1_chroma_sample_loc_type_bottom_field;
    ps_vui->u1_vui_timing_info_present_flag = ps_ip->u1_vui_timing_info_present_flag;
    ps_vui->u4_vui_num_units_in_tick = ps_ip->u4_vui_num_units_in_tick;
    ps_vui->u4_vui_time_scale = ps_ip->u4_vui_time_scale;
    ps_vui->u1_fixed_frame_rate_flag = ps_ip->u1_fixed_frame_rate_flag;
    ps_vui->u1_nal_hrd_parameters_present_flag = ps_ip->u1_nal_hrd_parameters_present_flag;
    ps_vui->u1_vcl_hrd_parameters_present_flag = ps_ip->u1_vcl_hrd_parameters_present_flag;
    ps_vui->u1_low_delay_hrd_flag = ps_ip->u1_low_delay_hrd_flag;
    ps_vui->u1_pic_struct_present_flag = ps_ip->u1_pic_struct_present_flag;
    ps_vui->u1_bitstream_restriction_flag = ps_ip->u1_bitstream_restriction_flag;
    ps_vui->u1_motion_vectors_over_pic_boundaries_flag =
        ps_ip->u1_motion_vectors_over_pic_boundaries_flag;
    ps_vui->u1_max_bytes_per_pic_denom = ps_ip->u1_max_bytes_per_pic_denom;
    ps_vui->u1_max_bits_per_mb_denom = ps_ip->u1_max_bits_per_mb_denom;
    ps_vui->u1_log2_max_mv_length_horizontal = ps_ip->u1_log2_max_mv_length_horizontal;
    ps_vui->u1_log2_max_mv_length_vertical = ps_ip->u1_log2_max_mv_length_vertical;
    ps_vui->u1_num_reorder_frames = ps_ip->u1_num_reorder_frames;
    ps_vui->u1_max_dec_frame_buffering = ps_ip->u1_max_dec_frame_buffering;

    return IV_SUCCESS;
}

/**
 *******************************************************************************
 *
 * @brief
 *  Sets Mastering display color volume sei params
 *
 * @par Description:
 *  Supplemental enhancement information
 *
 * @param[in] pv_api_ip
 *  Pointer to input argument structure
 *
 * @param[out] pv_api_op
 *  Pointer to output argument structure
 *
 * @param[out] ps_cfg
 *  Pointer to config structure to be updated
 *
 * @return error status
 *
 * @remarks none
 *
 *******************************************************************************
 */
static WORD32 isvce_set_sei_mdcv_params(void *pv_api_ip, void *pv_api_op,
                                        isvce_cfg_params_t *ps_cfg)
{
    WORD32 i4_count;
    /* ctrl call I/O structures */
    isvce_ctl_set_sei_mdcv_params_ip_t *ps_ip = pv_api_ip;
    isvce_ctl_set_sei_mdcv_params_op_t *ps_op = pv_api_op;
    sei_params_t *ps_sei = &ps_cfg->s_sei;

    ps_op->u4_error_code = 0;

    ps_sei->u1_sei_mdcv_params_present_flag = ps_ip->u1_sei_mdcv_params_present_flag;
    for(i4_count = 0; i4_count < NUM_SEI_MDCV_PRIMARIES; i4_count++)
    {
        ps_sei->s_sei_mdcv_params.au2_display_primaries_x[i4_count] =
            ps_ip->au2_display_primaries_x[i4_count];
        ps_sei->s_sei_mdcv_params.au2_display_primaries_y[i4_count] =
            ps_ip->au2_display_primaries_y[i4_count];
    }

    ps_sei->s_sei_mdcv_params.u2_white_point_x = ps_ip->u2_white_point_x;
    ps_sei->s_sei_mdcv_params.u2_white_point_y = ps_ip->u2_white_point_y;
    ps_sei->s_sei_mdcv_params.u4_max_display_mastering_luminance =
        ps_ip->u4_max_display_mastering_luminance;
    ps_sei->s_sei_mdcv_params.u4_min_display_mastering_luminance =
        ps_ip->u4_min_display_mastering_luminance;

    ps_cfg->u4_timestamp_high = ps_ip->u4_timestamp_high;
    ps_cfg->u4_timestamp_low = ps_ip->u4_timestamp_low;

    return IV_SUCCESS;
}

/**
 *******************************************************************************
 *
 * @brief
 *  Sets content light level sei params
 *
 * @par Description:
 *  Supplemental enhancement information
 *
 * @param[in] pv_api_ip
 *  Pointer to input argument structure
 *
 * @param[out] pv_api_op
 *  Pointer to output argument structure
 *
 * @param[out] ps_cfg
 *  Pointer to config structure to be updated
 *
 * @return error status
 *
 * @remarks none
 *
 *******************************************************************************
 */
static WORD32 isvce_set_sei_cll_params(void *pv_api_ip, void *pv_api_op, isvce_cfg_params_t *ps_cfg)
{
    /* ctrl call I/O structures */
    isvce_ctl_set_sei_cll_params_ip_t *ps_ip = pv_api_ip;
    isvce_ctl_set_sei_cll_params_op_t *ps_op = pv_api_op;
    sei_params_t *ps_sei = &ps_cfg->s_sei;

    ps_op->u4_error_code = 0;

    ps_sei->u1_sei_cll_params_present_flag = ps_ip->u1_sei_cll_params_present_flag;

    ps_sei->s_sei_cll_params.u2_max_content_light_level = ps_ip->u2_max_content_light_level;
    ps_sei->s_sei_cll_params.u2_max_pic_average_light_level = ps_ip->u2_max_pic_average_light_level;

    ps_cfg->u4_timestamp_high = ps_ip->u4_timestamp_high;
    ps_cfg->u4_timestamp_low = ps_ip->u4_timestamp_low;

    return IV_SUCCESS;
}

/**
 *******************************************************************************
 *
 * @brief
 *  Sets ambient viewing environment sei params
 *
 * @par Description:
 *  Supplemental enhancement information
 *
 * @param[in] pv_api_ip
 *  Pointer to input argument structure
 *
 * @param[out] pv_api_op
 *  Pointer to output argument structure
 *
 * @param[out] ps_cfg
 *  Pointer to config structure to be updated
 *
 * @return error status
 *
 * @remarks none
 *
 *******************************************************************************
 */
static WORD32 isvce_set_sei_ave_params(void *pv_api_ip, void *pv_api_op, isvce_cfg_params_t *ps_cfg)
{
    /* ctrl call I/O structures */
    isvce_ctl_set_sei_ave_params_ip_t *ps_ip = pv_api_ip;
    isvce_ctl_set_sei_ave_params_op_t *ps_op = pv_api_op;
    sei_params_t *ps_sei = &ps_cfg->s_sei;

    ps_op->u4_error_code = 0;

    ps_sei->u1_sei_ave_params_present_flag = ps_ip->u1_sei_ave_params_present_flag;

    ps_sei->s_sei_ave_params.u4_ambient_illuminance = ps_ip->u4_ambient_illuminance;
    ps_sei->s_sei_ave_params.u2_ambient_light_x = ps_ip->u2_ambient_light_x;
    ps_sei->s_sei_ave_params.u2_ambient_light_y = ps_ip->u2_ambient_light_y;

    ps_cfg->u4_timestamp_high = ps_ip->u4_timestamp_high;
    ps_cfg->u4_timestamp_low = ps_ip->u4_timestamp_low;

    return IV_SUCCESS;
}

/**
 *******************************************************************************
 *
 * @brief
 *  Sets content color volume sei params
 *
 * @par Description:
 *  Supplemental enhancement information
 *
 * @param[in] pv_api_ip
 *  Pointer to input argument structure
 *
 * @param[out] pv_api_op
 *  Pointer to output argument structure
 *
 * @param[out] ps_cfg
 *  Pointer to config structure to be updated
 *
 * @return error status
 *
 * @remarks none
 *
 *******************************************************************************
 */
static WORD32 isvce_set_sei_ccv_params(void *pv_api_ip, void *pv_api_op, isvce_cfg_params_t *ps_cfg)
{
    WORD32 i4_count;
    /* ctrl call I/O structures */
    isvce_ctl_set_sei_ccv_params_ip_t *ps_ip = pv_api_ip;
    isvce_ctl_set_sei_ccv_params_op_t *ps_op = pv_api_op;
    sei_params_t *ps_sei = &ps_cfg->s_sei;

    ps_op->u4_error_code = 0;

    ps_sei->u1_sei_ccv_params_present_flag = ps_ip->u1_sei_ccv_params_present_flag;

    ps_sei->s_sei_ccv_params.u1_ccv_cancel_flag = ps_ip->u1_ccv_cancel_flag;
    ps_sei->s_sei_ccv_params.u1_ccv_persistence_flag = ps_ip->u1_ccv_persistence_flag;
    ps_sei->s_sei_ccv_params.u1_ccv_primaries_present_flag = ps_ip->u1_ccv_primaries_present_flag;
    ps_sei->s_sei_ccv_params.u1_ccv_min_luminance_value_present_flag =
        ps_ip->u1_ccv_min_luminance_value_present_flag;
    ps_sei->s_sei_ccv_params.u1_ccv_max_luminance_value_present_flag =
        ps_ip->u1_ccv_max_luminance_value_present_flag;
    ps_sei->s_sei_ccv_params.u1_ccv_avg_luminance_value_present_flag =
        ps_ip->u1_ccv_avg_luminance_value_present_flag;
    ps_sei->s_sei_ccv_params.u1_ccv_reserved_zero_2bits = ps_ip->u1_ccv_reserved_zero_2bits;

    for(i4_count = 0; i4_count < NUM_SEI_CCV_PRIMARIES; i4_count++)
    {
        ps_sei->s_sei_ccv_params.ai4_ccv_primaries_x[i4_count] =
            ps_ip->ai4_ccv_primaries_x[i4_count];
        ps_sei->s_sei_ccv_params.ai4_ccv_primaries_y[i4_count] =
            ps_ip->ai4_ccv_primaries_y[i4_count];
    }

    ps_sei->s_sei_ccv_params.u4_ccv_min_luminance_value = ps_ip->u4_ccv_min_luminance_value;
    ps_sei->s_sei_ccv_params.u4_ccv_max_luminance_value = ps_ip->u4_ccv_max_luminance_value;
    ps_sei->s_sei_ccv_params.u4_ccv_avg_luminance_value = ps_ip->u4_ccv_avg_luminance_value;

    ps_cfg->u4_timestamp_high = ps_ip->u4_timestamp_high;
    ps_cfg->u4_timestamp_low = ps_ip->u4_timestamp_low;

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Sets number of cores
*
* @par Description:
*  Sets number of cores
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
* @returns error status
*
* @remarks The number of encoder threads is limited to MAX_PROCESS_THREADS
*
*******************************************************************************
*/
static WORD32 isvce_set_num_cores(void *pv_api_ip, void *pv_api_op, isvce_cfg_params_t *ps_cfg)
{
    /* ctrl call I/O structures */
    isvce_ctl_set_num_cores_ip_t *ps_ip = pv_api_ip;
    isvce_ctl_set_num_cores_op_t *ps_op = pv_api_op;

    ps_op->s_ive_op.u4_error_code = 0;

    ps_cfg->u4_num_cores = MIN(ps_ip->s_ive_ip.u4_num_cores, MAX_PROCESS_THREADS);

    ps_cfg->u4_timestamp_high = ps_ip->s_ive_ip.u4_timestamp_high;
    ps_cfg->u4_timestamp_low = ps_ip->s_ive_ip.u4_timestamp_low;

    return IV_SUCCESS;
}

/**
*******************************************************************************
*
* @brief
*  Resets encoder state
*
* @par Description:
*  Resets encoder state by calling isvce_init()
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
* @returns  error status
*
* @remarks none
*
*******************************************************************************
*/
static WORD32 isvce_reset(iv_obj_t *ps_codec_obj, void *pv_api_ip, void *pv_api_op)
{
    /* codec ctxt */
    isvce_codec_t *ps_codec = (isvce_codec_t *) (ps_codec_obj->pv_codec_handle);

    /* ctrl call I/O structures */
    isvce_ctl_reset_op_t *ps_op = pv_api_op;

    UNUSED(pv_api_ip);

    ps_op->s_ive_op.u4_error_code = 0;

    if(ps_codec != NULL)
    {
        isvce_init(ps_codec);
    }
    else
    {
        ps_op->s_ive_op.u4_error_code = IH264E_INIT_NOT_DONE;
    }

    return IV_SUCCESS;
}

static void isvce_ctl_set_error_code(void *pv_api_op, ISVCE_CONTROL_API_COMMAND_TYPE_T e_sub_cmd)
{
    switch(e_sub_cmd)
    {
        case ISVCE_CMD_CTL_SET_DIMENSIONS:
        {
            ((isvce_ctl_set_dimensions_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                1 << IVE_FATALERROR;
            ((isvce_ctl_set_dimensions_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SET_FRAMERATE:
        {
            ((isvce_ctl_set_frame_rate_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                1 << IVE_FATALERROR;
            ((isvce_ctl_set_frame_rate_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SET_BITRATE:
        {
            ((isvce_ctl_set_bitrate_op_t *) pv_api_op)->s_ive_op.u4_error_code |= 1
                                                                                  << IVE_FATALERROR;
            ((isvce_ctl_set_bitrate_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SET_FRAMETYPE:
        {
            ((isvce_ctl_set_frame_type_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                1 << IVE_FATALERROR;
            ((isvce_ctl_set_frame_type_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SET_QP:
        {
            ((isvce_ctl_set_qp_op_t *) pv_api_op)->s_ive_op.u4_error_code |= 1 << IVE_FATALERROR;
            ((isvce_ctl_set_qp_op_t *) pv_api_op)->s_ive_op.u4_error_code |= IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SET_ENC_MODE:
        {
            ((isvce_ctl_set_enc_mode_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                1 << IVE_FATALERROR;
            ((isvce_ctl_set_enc_mode_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SET_VBV_PARAMS:
        {
            ((isvce_ctl_set_vbv_params_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                1 << IVE_FATALERROR;
            ((isvce_ctl_set_vbv_params_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SET_AIR_PARAMS:
        {
            ((isvce_ctl_set_air_params_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                1 << IVE_FATALERROR;
            ((isvce_ctl_set_air_params_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SET_ME_PARAMS:
        {
            ((isvce_ctl_set_me_params_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                1 << IVE_FATALERROR;
            ((isvce_ctl_set_me_params_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SET_IPE_PARAMS:
        {
            ((isvce_ctl_set_ipe_params_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                1 << IVE_FATALERROR;
            ((isvce_ctl_set_ipe_params_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SET_GOP_PARAMS:
        {
            ((isvce_ctl_set_gop_params_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                1 << IVE_FATALERROR;
            ((isvce_ctl_set_gop_params_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SET_PROFILE_PARAMS:
        {
            ((isvce_ctl_set_profile_params_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                1 << IVE_FATALERROR;
            ((isvce_ctl_set_profile_params_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SET_DEBLOCK_PARAMS:
        {
            ((isvce_ctl_set_deblock_params_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                1 << IVE_FATALERROR;
            ((isvce_ctl_set_deblock_params_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SET_VUI_PARAMS:
        {
            ((isvce_vui_op_t *) pv_api_op)->u4_error_code |= 1 << IVE_FATALERROR;
            ((isvce_vui_op_t *) pv_api_op)->u4_error_code |= IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SET_SEI_MDCV_PARAMS:
        {
            ((isvce_ctl_set_sei_mdcv_params_op_t *) pv_api_op)->u4_error_code |= 1
                                                                                 << IVE_FATALERROR;
            ((isvce_ctl_set_sei_mdcv_params_op_t *) pv_api_op)->u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SET_SEI_CLL_PARAMS:
        {
            ((isvce_ctl_set_sei_cll_params_op_t *) pv_api_op)->u4_error_code |= 1 << IVE_FATALERROR;
            ((isvce_ctl_set_sei_cll_params_op_t *) pv_api_op)->u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SET_SEI_AVE_PARAMS:
        {
            ((isvce_ctl_set_sei_ave_params_op_t *) pv_api_op)->u4_error_code |= 1 << IVE_FATALERROR;
            ((isvce_ctl_set_sei_ave_params_op_t *) pv_api_op)->u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SET_SEI_CCV_PARAMS:
        {
            ((isvce_ctl_set_sei_ccv_params_op_t *) pv_api_op)->u4_error_code |= 1 << IVE_FATALERROR;
            ((isvce_ctl_set_sei_ccv_params_op_t *) pv_api_op)->u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_RESET:
        {
            ((isvce_ctl_reset_op_t *) pv_api_op)->s_ive_op.u4_error_code |= 1 << IVE_FATALERROR;
            ((isvce_ctl_reset_op_t *) pv_api_op)->s_ive_op.u4_error_code |= IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SETDEFAULT:
        {
            ((isvce_ctl_setdefault_op_t *) pv_api_op)->s_ive_op.u4_error_code |= 1
                                                                                 << IVE_FATALERROR;
            ((isvce_ctl_setdefault_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_FLUSH:
        {
            ((isvce_ctl_flush_op_t *) pv_api_op)->s_ive_op.u4_error_code |= 1 << IVE_FATALERROR;
            ((isvce_ctl_flush_op_t *) pv_api_op)->s_ive_op.u4_error_code |= IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_GETBUFINFO:
        {
            ((isvce_ctl_getbufinfo_op_t *) pv_api_op)->s_ive_op.u4_error_code |= 1
                                                                                 << IVE_FATALERROR;
            ((isvce_ctl_getbufinfo_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_GETVERSION:
        {
            ((isvce_ctl_getversioninfo_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                1 << IVE_FATALERROR;
            ((isvce_ctl_getversioninfo_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_SET_NUM_CORES:
        {
            ((isvce_ctl_set_num_cores_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                1 << IVE_FATALERROR;
            ((isvce_ctl_set_num_cores_op_t *) pv_api_op)->s_ive_op.u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        case ISVCE_CMD_CTL_GET_ENC_FRAME_DIMENSIONS:
        {
            ((isvce_ctl_get_enc_dimensions_op_t *) pv_api_op)->u4_error_code |= 1 << IVE_FATALERROR;
            ((isvce_ctl_get_enc_dimensions_op_t *) pv_api_op)->u4_error_code |=
                IH264E_INIT_NOT_DONE;

            break;
        }
        default:
        {
            ASSERT(0);
        }
    }
}

/**
*******************************************************************************
*
* @brief
*  Codec control call
*
* @par Description:
*  Codec control call which in turn calls appropriate calls  based on
*sub-command
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
* @returns error status
*
* @remarks none
*
*******************************************************************************
*/
static WORD32 isvce_ctl(iv_obj_t *ps_codec_obj, void *pv_api_ip, void *pv_api_op,
                        ISVCE_CONTROL_API_COMMAND_TYPE_T e_ctl_cmd)
{
    WORD32 i;

    isvce_codec_t *ps_codec = (isvce_codec_t *) ps_codec_obj->pv_codec_handle;
    isvce_cfg_params_t *ps_cfg = NULL;

    IV_STATUS_T ret = IV_SUCCESS;

    /* control call is for configuring encoding params, this is not to be called
     * before a successful init call */
    if(ps_codec->i4_init_done != 1)
    {
        isvce_ctl_set_error_code(pv_api_op, e_ctl_cmd);

        return IV_FAIL;
    }

    /* make it thread safe */
    ithread_mutex_lock(ps_codec->pv_ctl_mutex);

    /* find a free config param set to hold current parameters */
    if(e_ctl_cmd != ISVCE_CMD_CTL_GET_ENC_FRAME_DIMENSIONS)
    {
        for(i = 0; i < MAX_ACTIVE_CONFIG_PARAMS; i++)
        {
            if(0 == ps_codec->as_cfg[i].u4_is_valid)
            {
                ps_cfg = &ps_codec->as_cfg[i];
                break;
            }
        }

        /* If all are invalid, then start overwriting from the head config params */
        if(NULL == ps_cfg)
        {
            ps_cfg = &ps_codec->as_cfg[0];
        }

        ps_cfg->u4_is_valid = 1;

        ps_cfg->s_svc_params = ps_codec->s_cfg.s_svc_params;
        ps_cfg->e_cmd = e_ctl_cmd;
    }

    switch(e_ctl_cmd)
    {
        case ISVCE_CMD_CTL_SET_DIMENSIONS:
            ret = isvce_set_dimensions(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_SET_FRAMERATE:
            ret = isvce_set_frame_rate(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_SET_BITRATE:
            ret = isvce_set_bit_rate(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_SET_FRAMETYPE:
            ret = isvce_set_frame_type(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_SET_QP:
            ret = isvce_set_qp(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_SET_ENC_MODE:
            ret = isvce_set_enc_mode(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_SET_VBV_PARAMS:
            ret = isvce_set_vbv_params(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_SET_AIR_PARAMS:
            ret = isvc_set_air_params(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_SET_ME_PARAMS:
            ret = isvc_set_me_params(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_SET_IPE_PARAMS:
            ret = isvc_set_ipe_params(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_SET_GOP_PARAMS:
            ret = isvc_set_gop_params(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_SET_PROFILE_PARAMS:
            ret = isvc_set_profile_params(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_SET_DEBLOCK_PARAMS:
            ret = isvc_set_deblock_params(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_SET_VUI_PARAMS:
            ret = isvce_set_vui_params(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_SET_SEI_MDCV_PARAMS:
            ret = isvce_set_sei_mdcv_params(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_SET_SEI_CLL_PARAMS:
            ret = isvce_set_sei_cll_params(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_SET_SEI_AVE_PARAMS:
            ret = isvce_set_sei_ave_params(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_SET_SEI_CCV_PARAMS:
            ret = isvce_set_sei_ccv_params(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_RESET:

            /* invalidate config param struct as it is being served right away */
            ps_codec->as_cfg[i].u4_is_valid = 0;

            ret = isvce_reset(ps_codec_obj, pv_api_ip, pv_api_op);
            break;

        case ISVCE_CMD_CTL_SETDEFAULT:
        {
            /* ctrl call I/O structures */
            isvce_ctl_setdefault_op_t *ps_op = pv_api_op;

            /* invalidate config param struct as it is being served right away */
            ps_codec->as_cfg[i].u4_is_valid = 0;

            /* error status */
            ret = isvce_set_default_params(ps_cfg);

            ps_op->s_ive_op.u4_error_code = ret;

            break;
        }

        case ISVCE_CMD_CTL_FLUSH:

            /* invalidate config param struct as it is being served right away */
            ps_codec->as_cfg[i].u4_is_valid = 0;

            ret = isvce_set_flush_mode(ps_codec_obj, pv_api_ip, pv_api_op);
            break;

        case ISVCE_CMD_CTL_GETBUFINFO:

            /* invalidate config param struct as it is being served right away */
            ps_codec->as_cfg[i].u4_is_valid = 0;

            ret = isvce_get_buf_info(ps_codec_obj->pv_codec_handle, pv_api_ip, pv_api_op);
            break;

        case ISVCE_CMD_CTL_GETVERSION:
        {
            /* ctrl call I/O structures */
            isvce_ctl_getversioninfo_ip_t *ps_ip = pv_api_ip;
            isvce_ctl_getversioninfo_op_t *ps_op = pv_api_op;

            /* invalidate config param struct as it is being served right away */
            ps_codec->as_cfg[i].u4_is_valid = 0;

            /* error status */
            ps_op->s_ive_op.u4_error_code = IV_SUCCESS;

            if(ps_ip->s_ive_ip.u4_version_bufsize <= 0)
            {
                ps_op->s_ive_op.u4_error_code = IH264E_CXA_VERS_BUF_INSUFFICIENT;
                ret = IV_FAIL;
            }
            else
            {
                ret = ih264e_get_version((CHAR *) ps_ip->s_ive_ip.pu1_version,
                                         ps_ip->s_ive_ip.u4_version_bufsize);

                if(ret != IV_SUCCESS)
                {
                    ps_op->s_ive_op.u4_error_code = IH264E_CXA_VERS_BUF_INSUFFICIENT;
                    ret = IV_FAIL;
                }
            }
            break;
        }

        case ISVCE_CMD_CTL_SET_NUM_CORES:
            ret = isvce_set_num_cores(pv_api_ip, pv_api_op, ps_cfg);
            break;

        case ISVCE_CMD_CTL_GET_ENC_FRAME_DIMENSIONS:
        {
            ps_cfg = NULL;

            for(i = 0; i < MAX_ACTIVE_CONFIG_PARAMS; i++)
            {
                if(ps_codec->as_cfg[i].u4_is_valid &&
                   (ps_codec->as_cfg[i].e_cmd == ISVCE_CMD_CTL_SET_DIMENSIONS))
                {
                    ps_cfg = &ps_codec->as_cfg[i];

                    break;
                }
            }

            if(NULL == ps_cfg)
            {
                ((isvce_ctl_get_enc_dimensions_op_t *) pv_api_op)->u4_error_code |=
                    1 << IVE_FATALERROR;
                ((isvce_ctl_get_enc_dimensions_op_t *) pv_api_op)->u4_error_code |=
                    IH264E_WIDTH_NOT_SUPPORTED;
                ((isvce_ctl_get_enc_dimensions_op_t *) pv_api_op)->u4_error_code |=
                    IH264E_HEIGHT_NOT_SUPPORTED;

                return IV_FAIL;
            }

            ret = isvce_get_enc_frame_dimensions((isvce_ctl_get_enc_dimensions_ip_t *) pv_api_ip,
                                                 (isvce_ctl_get_enc_dimensions_op_t *) pv_api_op,
                                                 ps_cfg);

            break;
        }

        default:
            /* invalidate config param struct as it is being served right away */
            ps_codec->as_cfg[i].u4_is_valid = 0;

            DEBUG("Warning !! unrecognized control api command \n");
            break;
    }

    ithread_mutex_unlock(ps_codec->pv_ctl_mutex);

    return ret;
}

/**
*******************************************************************************
*
* @brief
*  Codec entry point function. All the function calls to  the codec are done
*  using this function with different values specified in command
*
* @par Description:
*  Arguments are tested for validity and then based on the command
*  appropriate function is called
*
* @param[in] ps_handle
*  API level handle for codec
*
* @param[in] pv_api_ip
*  Input argument structure
*
* @param[out] pv_api_op
*  Output argument structure
*
* @returns  error_status
*
* @remarks
*
*******************************************************************************
*/
IV_STATUS_T isvce_api_function(iv_obj_t *ps_handle, void *pv_api_ip, void *pv_api_op,
                               isvce_api_cmds_t *ps_iv_api_cmds)
{
    IV_STATUS_T e_status;
    WORD32 ret;

    ISVCE_API_COMMAND_TYPE_T e_cmd = ps_iv_api_cmds->e_cmd;
    ISVCE_CONTROL_API_COMMAND_TYPE_T e_ctl_cmd = ps_iv_api_cmds->e_ctl_cmd;

    /* validate input / output structures */
    e_status = api_check_struct_sanity(ps_handle, pv_api_ip, pv_api_op, ps_iv_api_cmds);

    if(e_status != IV_SUCCESS)
    {
        DEBUG("error code = %d\n", *((UWORD32 *) pv_api_op + 1));
        return IV_FAIL;
    }

    switch(e_cmd)
    {
        case ISVCE_CMD_GET_NUM_MEM_REC:
            ret = isvce_get_num_rec(pv_api_ip, pv_api_op);
            break;

        case ISVCE_CMD_FILL_NUM_MEM_REC:
            ret = isvce_fill_num_mem_rec(pv_api_ip, pv_api_op);
            break;

        case ISVCE_CMD_INIT:
            ret = isvce_init_mem_rec(ps_handle, pv_api_ip, pv_api_op);
            break;

        case ISVCE_CMD_RETRIEVE_MEMREC:
            ret = isvce_retrieve_memrec(ps_handle, pv_api_ip, pv_api_op);
            break;

        case ISVCE_CMD_VIDEO_CTL:
            ret = isvce_ctl(ps_handle, pv_api_ip, pv_api_op, e_ctl_cmd);
            break;

        case ISVCE_CMD_VIDEO_ENCODE:
            ret = isvce_encode(ps_handle, pv_api_ip, pv_api_op);
            break;

        default:
            ret = IV_FAIL;
            break;
    }

    return (IV_STATUS_T) ret;
}

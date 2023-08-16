/************************************************************************
 * Copyright (c) 2019, Gil Treibush
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details.
 *
 * A copy of the full GNU General Public License is included in this
 * distribution in a file called "COPYING" or "LICENSE".
 ***********************************************************************/

#include <unistd.h>
#include <string.h>
#include <fcntl.h>
#include <stdio.h>
#include <stdlib.h>

#include "src_parser.h"

#define TMP_FILE_NAME           ".gilcc-tmpfile-XXXXXX"
#define TMP_FILE_NAME_SIZE      22

/* Parse states: */
#define P_STATE_CODE        0
#define P_STATE_COMMENT_C   1

/* Parser buffer */

#define SRC_PARSER_F_BUF_SIZE   200
#define SRC_PARSER_TMP_BUF_SIZE 5

struct parser_buf {
    char f_buf[SRC_PARSER_F_BUF_SIZE];
    char tmp_buf[SRC_PARSER_TMP_BUF_SIZE];

    int f_indx;
    int tmp_indx;
    int f_read_size;
};

#define PBUF_F_CHAR(BUF) (BUF.f_buf[BUF.f_indx])
#define PBUF_F_REMD(BUF) (BUF.f_read_size - BUF.f_indx)

#define PBUF_TMP_PREV_CHAR(BUF) (BUF.tmp_buf[BUF.tmp_indx-1])

static inline int p_buf_refill(struct parser_buf *buf, const int input_fd)
{
    int read_size;

    read_size = read(input_fd, buf->f_buf, SRC_PARSER_F_BUF_SIZE);
    buf->f_indx = 0;
    buf->f_read_size = read_size;
    return read_size;
}

static inline int p_buf_push_tmp_char(struct parser_buf *buf, const char c)
{
    buf->tmp_buf[buf->tmp_indx++] = c;
    buf->f_indx++;
    return buf->tmp_indx;
}

static inline int p_buf_write_tmp(struct parser_buf *buf, const int output_fd)
{
    int write_size;

    if (!buf->tmp_indx)
        return 0;

    write_size = write(output_fd, buf->tmp_buf, buf->tmp_indx);
    buf->tmp_indx = 0;

    return write_size;
}

static inline int p_buf_write_f_char(struct parser_buf *buf, const int output_fd)
{
    return write(output_fd, &buf->f_buf[buf->f_indx++], 1);
}

/* Parser impl. */

static void print_file_full(int fd)
{
    char f_buf[SRC_PARSER_F_BUF_SIZE];
    int read_size;

    if (lseek(fd, 0, SEEK_SET)) {
        fprintf(stderr, "**Error: Could not set offset.\n");
        return;
    }

    while ((read_size = read(fd, f_buf, SRC_PARSER_F_BUF_SIZE)) > 0) {
        int read_indx = 0;

        while (read_indx < read_size)
            putchar(f_buf[read_indx++]);
    }
}

static int src_parser_trans_stage_1_2_3(const int tmp_fd, const char *src, const struct trans_config cfg)
{
    struct parser_buf pbuf = {
        .f_indx = 0,
        .tmp_indx = 0,
        .f_read_size = 0
    };

    int write_count = 0;
    int src_fd;
    int p_state = P_STATE_CODE;

    src_fd = open(src, O_RDONLY);
    if (src_fd == -1) {
        fprintf(stderr, "**Error: Could not open source file: %s.\n", src);
        return -1;
    }

    while (p_buf_refill(&pbuf, src_fd) > 0) {

        while (PBUF_F_REMD(pbuf)) {

            switch (p_state) {
            case P_STATE_COMMENT_C:

                switch (PBUF_F_CHAR(pbuf)) {
                case '*':
                    p_buf_push_tmp_char(&pbuf, '*');
                    continue;

                case '/':
                    if (pbuf.tmp_indx && (PBUF_TMP_PREV_CHAR(pbuf) == '*')) {
                        pbuf.tmp_indx--;
                        p_state = P_STATE_CODE;
                    }
                    break;

                default:
                    if (pbuf.tmp_indx && (PBUF_TMP_PREV_CHAR(pbuf) == '*'))
                        pbuf.tmp_indx--;
                    break;
                }

                pbuf.f_indx++;

            case P_STATE_CODE:
            default:

                /* TODO: add trigraph support */

                switch (PBUF_F_CHAR(pbuf)) {
                case ' ':
                case '\t':
                    if (pbuf.tmp_indx &&
                            (PBUF_TMP_PREV_CHAR(pbuf) == ' ' || PBUF_TMP_PREV_CHAR(pbuf) == '\t' ||
                             PBUF_TMP_PREV_CHAR(pbuf) == '\n'))
                        pbuf.f_indx++;
                    else
                        p_buf_push_tmp_char(&pbuf, ' ');

                    continue;

                case '\r':
                case '\n':
                    if (pbuf.tmp_indx &&
                            (PBUF_TMP_PREV_CHAR(pbuf) == ' ' || PBUF_TMP_PREV_CHAR(pbuf) == '\t' ||
                             PBUF_TMP_PREV_CHAR(pbuf) == '\n')) {
                        pbuf.f_indx++;
                    } else if (pbuf.tmp_indx &&
                            (PBUF_TMP_PREV_CHAR(pbuf) == '\\')) {
                        pbuf.tmp_indx--;
                        pbuf.f_indx++;
                    } else {
                        p_buf_push_tmp_char(&pbuf, '\n');
                    }

                    continue;

                case '\\':
                    p_buf_write_tmp(&pbuf, tmp_fd);
                    p_buf_push_tmp_char(&pbuf, '\\');
                    continue;

                case '/':
                    p_buf_write_tmp(&pbuf, tmp_fd);
                    p_buf_push_tmp_char(&pbuf, '/');
                    continue;

                case '*':
                    if (pbuf.tmp_indx &&
                            (PBUF_TMP_PREV_CHAR(pbuf) == '/')) {
                        pbuf.tmp_indx--;
                        pbuf.f_indx++;
                        p_state = P_STATE_COMMENT_C;
                        continue;
                    }

                default:
                    break;
                }

                /* TODO: check return values */
                p_buf_write_tmp(&pbuf, tmp_fd);
                p_buf_write_f_char(&pbuf, tmp_fd);
            }
        }
    }

    p_buf_write_tmp(&pbuf, tmp_fd);
    return 0;
}

int src_parser_cpp(const char *src, const struct trans_config cfg)
{
    int tmp_fd;
    char fname[TMP_FILE_NAME_SIZE];

    strncpy(fname, TMP_FILE_NAME, TMP_FILE_NAME_SIZE);
    tmp_fd = mkstemp(fname);
    if (tmp_fd == -1) {
        fprintf(stderr, "**Error: could not create a working file.\n");
        return -1;
    }

    src_parser_trans_stage_1_2_3(tmp_fd, src, cfg);

    print_file_full(tmp_fd);

    unlink(fname);
}


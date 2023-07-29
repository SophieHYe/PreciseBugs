/* Copyright (c) 2012 The Chromium OS Authors. All rights reserved.
 * Use of this source code is governed by a BSD-style license that can be
 * found in the LICENSE file.
 */

#ifndef CRAS_SHM_H_
#define CRAS_SHM_H_

#include <assert.h>
#include <stdint.h>
#include <sys/param.h>

#include "cras_types.h"
#include "cras_util.h"

#define CRAS_NUM_SHM_BUFFERS 2U /* double buffer */
#define CRAS_SHM_BUFFERS_MASK (CRAS_NUM_SHM_BUFFERS - 1)

/* Configuration of the shm area.
 *
 *  used_size - The size in bytes of the sample area being actively used.
 *  frame_bytes - The size of each frame in bytes.
 */
struct __attribute__ ((__packed__)) cras_audio_shm_config {
	uint32_t used_size;
	uint32_t frame_bytes;
};

/* Structure containing stream metadata shared between client and server.
 *
 *  config - Size config data.  A copy of the config shared with clients.
 *  read_buf_idx - index of the current buffer to read from (0 or 1 if double
 *    buffered).
 *  write_buf_idx - index of the current buffer to write to (0 or 1 if double
 *    buffered).
 *  read_offset - offset of the next sample to read (one per buffer).
 *  write_offset - offset of the next sample to write (one per buffer).
 *  write_in_progress - non-zero when a write is in progress.
 *  volume_scaler - volume scaling factor (0.0-1.0).
 *  muted - bool, true if stream should be muted.
 *  num_overruns - Starting at 0 this is incremented very time data is over
 *    written because too much accumulated before a read.
 *  ts - For capture, the time stamp of the next sample at read_index.  For
 *    playback, this is the time that the next sample written will be played.
 *    This is only valid in audio callbacks.
 *
 *  TODO(fletcherw) remove once libcras in ARC++ has been upreved
 *  samples - Audio data - a double buffered area that is used to exchange
 *    audio samples.
 */
struct __attribute__((__packed__)) cras_audio_shm_header {
	struct cras_audio_shm_config config;
	uint32_t read_buf_idx; /* use buffer A or B */
	uint32_t write_buf_idx;
	uint32_t read_offset[CRAS_NUM_SHM_BUFFERS];
	uint32_t write_offset[CRAS_NUM_SHM_BUFFERS];
	int32_t write_in_progress[CRAS_NUM_SHM_BUFFERS];
	float volume_scaler;
	int32_t mute;
	int32_t callback_pending;
	uint32_t num_overruns;
	struct cras_timespec ts;
	uint8_t samples[];
};

/* Returns the number of bytes needed to hold a cras_audio_shm_header. */
inline uint32_t cras_shm_header_size()
{
	return sizeof(struct cras_audio_shm_header);
}

/* Returns the number of bytes needed to hold the samples area for an audio
 * shm with the given used_size */
inline uint32_t cras_shm_calculate_samples_size(uint32_t used_size)
{
	return used_size * CRAS_NUM_SHM_BUFFERS;
}

/* Holds identifiers for a shm segment. All valid cras_shm_info objects will
 * have an fd and a length, and they may have the name of the shm file as well.
 *
 *  fd - File descriptor to access shm (shared between client/server).
 *  name - Name of the shm area. May be empty.
 *  length - Size of the shm region.
 */
struct cras_shm_info {
	int fd;
	char name[NAME_MAX];
	size_t length;
};

/* Initializes a cras_shm_info to be used as the backing shared memory for a
 * cras_audio_shm.
 *
 * shm_name - the name of the shm area to create.
 * length - the length of the shm area to create.
 * info_out - pointer where the created cras_shm_info will be stored.
 */
int cras_shm_info_init(const char *shm_name, uint32_t length,
		       struct cras_shm_info *info_out);

/* Initializes a cras_shm_info to be used as the backing shared memory for a
 * cras_audio_shm.
 *
 * fd - file descriptor for the shm to be used. fd must be closed after
 *      calling this function.
 * length - the size of the shm referenced by fd.
 * info_out - pointer where the created cras_shm_info will be stored.
 */
int cras_shm_info_init_with_fd(int fd, size_t length,
			       struct cras_shm_info *info_out);

/* Cleans up the resources for a cras_shm_info returned from cras_shm_info_init.
 *
 * info - the cras_shm_info to cleanup.
 */
void cras_shm_info_cleanup(struct cras_shm_info *info);

/* Structure that holds the config for and a pointer to the audio shm header and
 * samples area.
 *
 *  config - Size config data, kept separate so it can be checked.
 *  header_info - fd, name, and length of shm containing header.
 *  header - Shm region containing audio metadata
 *  samples_info - fd, name, and length of shm containing samples.
 *  samples - Shm region containing audio data.
 */
struct cras_audio_shm {
	struct cras_audio_shm_config config;
	struct cras_shm_info header_info;
	struct cras_audio_shm_header *header;
	struct cras_shm_info samples_info;
	uint8_t *samples;
};

/* Sets up a cras_audio_shm given info about the shared memory to use
 *
 * header_info - the underlying shm area to use for the header. The shm
 *               will be managed by the created cras_audio_shm object.
 *               The header_info parameter will be returned to an uninitialized
 *               state, and the client need not call cras_shm_info_destroy.
 * samples_info - the underlying shm area to use for the samples. The shm
 *               will be managed by the created cras_audio_shm object.
 *               The samples_info parameter will be returned to an
 *               uninitialized state, and the client need not call
 *               cras_shm_info_destroy.
 *               This parameter may be NULL. In that case, no shared memory will
 *               be mapped for the samples.
 * shm_out - pointer where the created cras_audio_shm will be stored.
 */
int cras_audio_shm_create(struct cras_shm_info *header_info,
			  struct cras_shm_info *samples_info,
			  struct cras_audio_shm **shm_out);

/* Sets up a legacy cras_audio_shm given info about the shared memory to use
 *
 * shm_info - the underlying shm area to use for the audio shm. The shm
 *            will be managed by the created cras_audio_shm object.
 *            The shm_info parameter will be returned to an uninitialized
 *            state, and the client need not call cras_shm_info_destroy.
 * shm_out - pointer where the created cras_audio_shm will be stored.
 */
int cras_audio_unsplit_shm_create(struct cras_shm_info *shm_info,
				  struct cras_audio_shm **shm_out);

/* Destroys a cras_audio_shm returned from cras_audio_shm_create.
 *
 * shm - the cras_audio_shm to destroy.
 */
void cras_audio_shm_destroy(struct cras_audio_shm *shm);

/* Get a pointer to the buffer at idx. */
static inline uint8_t *cras_shm_buff_for_idx(const struct cras_audio_shm *shm,
					     size_t idx)
{
	assert_on_compile_is_power_of_2(CRAS_NUM_SHM_BUFFERS);
	idx = idx & CRAS_SHM_BUFFERS_MASK;

	return shm->samples + shm->config.used_size * idx;
}

/* Limit a read offset to within the buffer size. */
static inline
unsigned cras_shm_check_read_offset(const struct cras_audio_shm *shm,
				    unsigned offset)
{
	/* The offset is allowed to be the total size, indicating that the
	 * buffer is full. If read pointer is invalid assume it is at the
	 * beginning. */
	if (offset > shm->config.used_size)
		return 0;
	return offset;
}

/* Limit a write offset to within the buffer size. */
static inline
unsigned cras_shm_check_write_offset(const struct cras_audio_shm *shm,
				     unsigned offset)
{
	/* The offset is allowed to be the total size, indicating that the
	 * buffer is full. If write pointer is invalid assume it is at the
	 * end. */
	if (offset > shm->config.used_size)
		return shm->config.used_size;
	return offset;
}

/* Get the number of frames readable in current read buffer */
static inline
unsigned cras_shm_get_curr_read_frames(const struct cras_audio_shm *shm)
{
	unsigned i = shm->header->read_buf_idx & CRAS_SHM_BUFFERS_MASK;
	unsigned read_offset, write_offset;

	read_offset =
		cras_shm_check_read_offset(shm, shm->header->read_offset[i]);
	write_offset =
		cras_shm_check_write_offset(shm, shm->header->write_offset[i]);

	if (read_offset > write_offset)
		return 0;
	else
		return (write_offset - read_offset) / shm->config.frame_bytes;
}

/* Get the base of the current read buffer. */
static inline
uint8_t *cras_shm_get_read_buffer_base(const struct cras_audio_shm *shm)
{
	unsigned i = shm->header->read_buf_idx & CRAS_SHM_BUFFERS_MASK;
	return cras_shm_buff_for_idx(shm, i);
}

/* Get the base of the current write buffer. */
static inline
uint8_t *cras_shm_get_write_buffer_base(const struct cras_audio_shm *shm)
{
	unsigned i = shm->header->write_buf_idx & CRAS_SHM_BUFFERS_MASK;

	return cras_shm_buff_for_idx(shm, i);
}

/* Get a pointer to the next buffer to write */
static inline
uint8_t *cras_shm_get_writeable_frames(const struct cras_audio_shm *shm,
				       unsigned limit_frames,
				       unsigned *frames)
{
	unsigned i = shm->header->write_buf_idx & CRAS_SHM_BUFFERS_MASK;
	unsigned write_offset;
	const unsigned frame_bytes = shm->config.frame_bytes;
	unsigned written;

	write_offset =
		cras_shm_check_write_offset(shm, shm->header->write_offset[i]);
	written = write_offset / frame_bytes;
	if (frames) {
		if (limit_frames >= written)
			*frames = limit_frames - written;
		else
			*frames = 0;
	}

	return cras_shm_buff_for_idx(shm, i) + write_offset;
}

/* Get a pointer to the current read buffer plus an offset.  The offset might be
 * in the next buffer. 'frames' is filled with the number of frames that can be
 * copied from the returned buffer.
 */
static inline
uint8_t *cras_shm_get_readable_frames(const struct cras_audio_shm *shm,
				      size_t offset,
				      size_t *frames)
{
	unsigned buf_idx = shm->header->read_buf_idx & CRAS_SHM_BUFFERS_MASK;
	unsigned read_offset, write_offset, final_offset;

	assert(frames != NULL);

	read_offset = cras_shm_check_read_offset(
		shm, shm->header->read_offset[buf_idx]);
	write_offset = cras_shm_check_write_offset(
		shm, shm->header->write_offset[buf_idx]);
	final_offset = read_offset + offset * shm->config.frame_bytes;
	if (final_offset >= write_offset) {
		final_offset -= write_offset;
		assert_on_compile_is_power_of_2(CRAS_NUM_SHM_BUFFERS);
		buf_idx = (buf_idx + 1) & CRAS_SHM_BUFFERS_MASK;
		write_offset = cras_shm_check_write_offset(
			shm, shm->header->write_offset[buf_idx]);
	}
	if (final_offset >= write_offset) {
		/* Past end of samples. */
		*frames = 0;
		return NULL;
	}
	*frames = (write_offset - final_offset) / shm->config.frame_bytes;
	return cras_shm_buff_for_idx(shm, buf_idx) + final_offset;
}

/* How many bytes are queued? */
static inline size_t cras_shm_get_bytes_queued(const struct cras_audio_shm *shm)
{
	size_t total, i;
	const unsigned used_size = shm->config.used_size;

	total = 0;
	for (i = 0; i < CRAS_NUM_SHM_BUFFERS; i++) {
		unsigned read_offset, write_offset;

		read_offset = MIN(shm->header->read_offset[i], used_size);
		write_offset = MIN(shm->header->write_offset[i], used_size);

		if (write_offset > read_offset)
			total += write_offset - read_offset;
	}
	return total;
}

/* How many frames are queued? */
static inline int cras_shm_get_frames(const struct cras_audio_shm *shm)
{
	size_t bytes;

	bytes = cras_shm_get_bytes_queued(shm);
	if (bytes % shm->config.frame_bytes != 0)
		return -EIO;
	return bytes / shm->config.frame_bytes;
}

/* How many frames in the current buffer? */
static inline
size_t cras_shm_get_frames_in_curr_buffer(const struct cras_audio_shm *shm)
{
	size_t buf_idx = shm->header->read_buf_idx & CRAS_SHM_BUFFERS_MASK;
	unsigned read_offset, write_offset;
	const unsigned used_size = shm->config.used_size;

	read_offset = MIN(shm->header->read_offset[buf_idx], used_size);
	write_offset = MIN(shm->header->write_offset[buf_idx], used_size);

	if (write_offset <= read_offset)
		return 0;

	return (write_offset - read_offset) / shm->config.frame_bytes;
}

/* Return 1 if there is an empty buffer in the list. */
static inline int cras_shm_is_buffer_available(const struct cras_audio_shm *shm)
{
	size_t buf_idx = shm->header->write_buf_idx & CRAS_SHM_BUFFERS_MASK;

	return (shm->header->write_offset[buf_idx] == 0);
}

/* How many are available to be written? */
static inline
size_t cras_shm_get_num_writeable(const struct cras_audio_shm *shm)
{
	/* Not allowed to write to a buffer twice. */
	if (!cras_shm_is_buffer_available(shm))
		return 0;

	return shm->config.used_size / shm->config.frame_bytes;
}

/* Flags an overrun if writing would cause one and reset the write offset.
 * Return 1 if overrun happens, otherwise return 0. */
static inline int cras_shm_check_write_overrun(struct cras_audio_shm *shm)
{
	int ret = 0;
	size_t write_buf_idx =
		shm->header->write_buf_idx & CRAS_SHM_BUFFERS_MASK;

	if (!shm->header->write_in_progress[write_buf_idx]) {
		unsigned int used_size = shm->config.used_size;

		if (shm->header->write_offset[write_buf_idx]) {
			shm->header->num_overruns++; /* Will over-write unread */
			ret = 1;
		}

		memset(cras_shm_buff_for_idx(shm, write_buf_idx), 0, used_size);

		shm->header->write_in_progress[write_buf_idx] = 1;
		shm->header->write_offset[write_buf_idx] = 0;
	}
	return ret;
}

/* Increment the write pointer for the current buffer. */
static inline
void cras_shm_buffer_written(struct cras_audio_shm *shm, size_t frames)
{
	size_t buf_idx = shm->header->write_buf_idx & CRAS_SHM_BUFFERS_MASK;

	if (frames == 0)
		return;

	shm->header->write_offset[buf_idx] += frames * shm->config.frame_bytes;
	shm->header->read_offset[buf_idx] = 0;
}

/* Returns the number of frames that have been written to the current buffer. */
static inline
unsigned int cras_shm_frames_written(const struct cras_audio_shm *shm)
{
	size_t buf_idx = shm->header->write_buf_idx & CRAS_SHM_BUFFERS_MASK;

	return shm->header->write_offset[buf_idx] / shm->config.frame_bytes;
}

/* Signals the writing to this buffer is complete and moves to the next one. */
static inline void cras_shm_buffer_write_complete(struct cras_audio_shm *shm)
{
	size_t buf_idx = shm->header->write_buf_idx & CRAS_SHM_BUFFERS_MASK;

	shm->header->write_in_progress[buf_idx] = 0;

	assert_on_compile_is_power_of_2(CRAS_NUM_SHM_BUFFERS);
	buf_idx = (buf_idx + 1) & CRAS_SHM_BUFFERS_MASK;
	shm->header->write_buf_idx = buf_idx;
}

/* Set the write pointer for the current buffer and complete the write. */
static inline
void cras_shm_buffer_written_start(struct cras_audio_shm *shm, size_t frames)
{
	size_t buf_idx = shm->header->write_buf_idx & CRAS_SHM_BUFFERS_MASK;

	shm->header->write_offset[buf_idx] = frames * shm->config.frame_bytes;
	shm->header->read_offset[buf_idx] = 0;
	cras_shm_buffer_write_complete(shm);
}

/* Increment the read pointer.  If it goes past the write pointer for this
 * buffer, move to the next buffer. */
static inline
void cras_shm_buffer_read(struct cras_audio_shm *shm, size_t frames)
{
	size_t buf_idx = shm->header->read_buf_idx & CRAS_SHM_BUFFERS_MASK;
	size_t remainder;
	struct cras_audio_shm_header *header = shm->header;
	struct cras_audio_shm_config *config = &shm->config;

	if (frames == 0)
		return;

	header->read_offset[buf_idx] += frames * config->frame_bytes;
	if (header->read_offset[buf_idx] >= header->write_offset[buf_idx]) {
		remainder = header->read_offset[buf_idx] -
			    header->write_offset[buf_idx];
		header->read_offset[buf_idx] = 0;
		header->write_offset[buf_idx] = 0;
		assert_on_compile_is_power_of_2(CRAS_NUM_SHM_BUFFERS);
		buf_idx = (buf_idx + 1) & CRAS_SHM_BUFFERS_MASK;
		if (remainder < header->write_offset[buf_idx]) {
			header->read_offset[buf_idx] = remainder;
		} else if (remainder) {
			/* Read all of this buffer too. */
			header->write_offset[buf_idx] = 0;
			buf_idx = (buf_idx + 1) & CRAS_SHM_BUFFERS_MASK;
		}
		header->read_buf_idx = buf_idx;
	}
}

/* Read from the current buffer. This is similar to cras_shm_buffer_read(), but
 * it doesn't check for the case we may read from two buffers. */
static inline
void cras_shm_buffer_read_current(struct cras_audio_shm *shm, size_t frames)
{
	size_t buf_idx = shm->header->read_buf_idx & CRAS_SHM_BUFFERS_MASK;
	struct cras_audio_shm_header *header = shm->header;
	struct cras_audio_shm_config *config = &shm->config;

	header->read_offset[buf_idx] += frames * config->frame_bytes;
	if (header->read_offset[buf_idx] >= header->write_offset[buf_idx]) {
		header->read_offset[buf_idx] = 0;
		header->write_offset[buf_idx] = 0;
		buf_idx = (buf_idx + 1) & CRAS_SHM_BUFFERS_MASK;
		header->read_buf_idx = buf_idx;
	}
}

/* Sets the volume for the stream.  The volume level is a scaling factor that
 * will be applied to the stream before mixing. */
static inline
void cras_shm_set_volume_scaler(struct cras_audio_shm *shm, float volume_scaler)
{
	volume_scaler = MAX(volume_scaler, 0.0);
	shm->header->volume_scaler = MIN(volume_scaler, 1.0);
}

/* Returns the volume of the stream(0.0-1.0). */
static inline float cras_shm_get_volume_scaler(const struct cras_audio_shm *shm)
{
	return shm->header->volume_scaler;
}

/* Indicates that the stream should be muted/unmuted */
static inline void cras_shm_set_mute(struct cras_audio_shm *shm, size_t mute)
{
	shm->header->mute = !!mute;
}

/* Returns the mute state of the stream.  0 if not muted, non-zero if muted. */
static inline size_t cras_shm_get_mute(const struct cras_audio_shm *shm)
{
	return shm->header->mute;
}

/* Sets the size of a frame in bytes. */
static inline void cras_shm_set_frame_bytes(struct cras_audio_shm *shm,
					    unsigned frame_bytes)
{
	shm->config.frame_bytes = frame_bytes;
	if (shm->header)
		shm->header->config.frame_bytes = frame_bytes;
}

/* Returns the size of a frame in bytes. */
static inline unsigned cras_shm_frame_bytes(const struct cras_audio_shm *shm)
{
	return shm->config.frame_bytes;
}

/* Sets if a callback is pending with the client. */
static inline
void cras_shm_set_callback_pending(struct cras_audio_shm *shm, int pending)
{
	shm->header->callback_pending = !!pending;
}

/* Returns non-zero if a callback is pending for this shm region. */
static inline int cras_shm_callback_pending(const struct cras_audio_shm *shm)
{
	return shm->header->callback_pending;
}

/* Sets the used_size of the shm region.  This is the maximum number of bytes
 * that is exchanged each time a buffer is passed from client to server.
 */
static inline
void cras_shm_set_used_size(struct cras_audio_shm *shm, unsigned used_size)
{
	shm->config.used_size = used_size;
	if (shm->header)
		shm->header->config.used_size = used_size;
}

/* Returns the used size of the shm region in bytes. */
static inline unsigned cras_shm_used_size(const struct cras_audio_shm *shm)
{
	return shm->config.used_size;
}

/* Returns the used size of the shm region in frames. */
static inline unsigned cras_shm_used_frames(const struct cras_audio_shm *shm)
{
	return shm->config.used_size / shm->config.frame_bytes;
}

/* Returns the size of the samples shm region. */
static inline unsigned cras_shm_samples_size(const struct cras_audio_shm *shm)
{
	return cras_shm_used_size(shm) * CRAS_NUM_SHM_BUFFERS;
}

/* Gets the counter of over-runs. */
static inline
unsigned cras_shm_num_overruns(const struct cras_audio_shm *shm)
{
	return shm->header->num_overruns;
}

/* Copy the config from the shm region to the local config.  Used by clients
 * when initially setting up the region.
 */
static inline void cras_shm_copy_shared_config(struct cras_audio_shm *shm)
{
	memcpy(&shm->config, &shm->header->config, sizeof(shm->config));
}

/* Open a read/write shared memory area with the given name.
 * Args:
 *    name - Name of the shared-memory area.
 *    size - Size of the shared-memory area.
 * Returns:
 *    >= 0 file descriptor value, or negative errno value on error.
 */
int cras_shm_open_rw (const char *name, size_t size);

/* Reopen an existing shared memory area read-only.
 * Args:
 *    name - Name of the shared-memory area.
 *    fd - Existing file descriptor.
 * Returns:
 *    >= 0 new file descriptor value, or negative errno value on error.
 */
int cras_shm_reopen_ro (const char *name, int fd);

/* Close and delete a shared memory area.
 * Args:
 *    name - Name of the shared-memory area.
 *    fd - Existing file descriptor.
 * Returns:
 *    >= 0 new file descriptor value, or negative errno value on error.
 */
void cras_shm_close_unlink (const char *name, int fd);

/*
 * Configure shared memory for the system state.
 * Args:
 *    name - Name of the shared-memory area.
 *    mmap_size - Amount of shared memor to map.
 *    rw_fd_out - Filled with the RW fd for the shm region.
 *    ro_fd_out - Filled with the RO fd for the shm region.
 * Returns a pointer to the new shared memory region. Or NULL on error.
 */
void *cras_shm_setup(const char *name,
		     size_t mmap_size,
		     int *rw_fd_out,
		     int *ro_fd_out);

#ifdef CRAS_SELINUX
/*
 * Wrapper around selinux_restorecon(). This is helpful in unit tests because
 * we can mock out the selinux_restorecon() behaviour there. That is required
 * because selinux_restorecon() would fail in the unit tests, since there
 * is no file_contexts file.
 * Args:
 *    pathname - Name of the file on which to run restorecon
 * Returns 0 on success, otherwise -1 and errno is set appropriately.
 */
int cras_selinux_restorecon(const char *pathname);
#endif

#endif /* CRAS_SHM_H_ */

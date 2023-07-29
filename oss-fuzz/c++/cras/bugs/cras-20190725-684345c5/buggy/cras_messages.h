/* Copyright (c) 2012 The Chromium OS Authors. All rights reserved.
 * Use of this source code is governed by a BSD-style license that can be
 * found in the LICENSE file.
 */

/*
 * Messages sent between the server and clients.
 */
#ifndef CRAS_MESSAGES_H_
#define CRAS_MESSAGES_H_

#include <stdint.h>

#include "cras_iodev_info.h"
#include "cras_types.h"

/* Rev when message format changes. If new messages are added, or message ID
 * values change. */
#define CRAS_PROTO_VER 3
#define CRAS_SERV_MAX_MSG_SIZE 256
#define CRAS_CLIENT_MAX_MSG_SIZE 256
#define CRAS_MAX_HOTWORD_MODELS 244
#define CRAS_MAX_REMIX_CHANNELS 32
#define CRAS_MAX_TEST_DATA_LEN 224
#define CRAS_AEC_DUMP_FILE_NAME_LEN 128

/* Message IDs. */
enum CRAS_SERVER_MESSAGE_ID {
	/* Client -> Server*/
	CRAS_SERVER_CONNECT_STREAM,
	CRAS_SERVER_DISCONNECT_STREAM,
	CRAS_SERVER_SWITCH_STREAM_TYPE_IODEV, /* Unused */
	CRAS_SERVER_SET_SYSTEM_VOLUME,
	CRAS_SERVER_SET_SYSTEM_MUTE,
	CRAS_SERVER_SET_USER_MUTE,
	CRAS_SERVER_SET_SYSTEM_MUTE_LOCKED,
	CRAS_SERVER_SET_SYSTEM_CAPTURE_GAIN,
	CRAS_SERVER_SET_SYSTEM_CAPTURE_MUTE,
	CRAS_SERVER_SET_SYSTEM_CAPTURE_MUTE_LOCKED,
	CRAS_SERVER_SET_NODE_ATTR,
	CRAS_SERVER_SELECT_NODE,
	CRAS_SERVER_RELOAD_DSP,
	CRAS_SERVER_DUMP_DSP_INFO,
	CRAS_SERVER_DUMP_AUDIO_THREAD,
	CRAS_SERVER_DUMP_SNAPSHOTS,
	CRAS_SERVER_ADD_ACTIVE_NODE,
	CRAS_SERVER_RM_ACTIVE_NODE,
	CRAS_SERVER_ADD_TEST_DEV,
	CRAS_SERVER_TEST_DEV_COMMAND,
	CRAS_SERVER_SUSPEND,
	CRAS_SERVER_RESUME,
	CRAS_CONFIG_GLOBAL_REMIX,
	CRAS_SERVER_GET_HOTWORD_MODELS,
	CRAS_SERVER_SET_HOTWORD_MODEL,
	CRAS_SERVER_REGISTER_NOTIFICATION,
	CRAS_SERVER_SET_AEC_DUMP,
	CRAS_SERVER_RELOAD_AEC_CONFIG,
	CRAS_SERVER_DUMP_BT,
	CRAS_SERVER_SET_BT_WBS_ENABLED,
};

enum CRAS_CLIENT_MESSAGE_ID {
	/* Server -> Client */
	CRAS_CLIENT_CONNECTED,
	CRAS_CLIENT_STREAM_CONNECTED,
	CRAS_CLIENT_AUDIO_DEBUG_INFO_READY,
	CRAS_CLIENT_GET_HOTWORD_MODELS_READY,
	/* System status messages */
	CRAS_CLIENT_OUTPUT_VOLUME_CHANGED,
	CRAS_CLIENT_OUTPUT_MUTE_CHANGED,
	CRAS_CLIENT_CAPTURE_GAIN_CHANGED,
	CRAS_CLIENT_CAPTURE_MUTE_CHANGED,
	CRAS_CLIENT_NODES_CHANGED,
	CRAS_CLIENT_ACTIVE_NODE_CHANGED,
	CRAS_CLIENT_OUTPUT_NODE_VOLUME_CHANGED,
	CRAS_CLIENT_NODE_LEFT_RIGHT_SWAPPED_CHANGED,
	CRAS_CLIENT_INPUT_NODE_GAIN_CHANGED,
	CRAS_CLIENT_NUM_ACTIVE_STREAMS_CHANGED,
};

/* Messages that control the server. These are sent from the client to affect
 * and action on the server. */
struct __attribute__((__packed__)) cras_server_message {
	uint32_t length;
	enum CRAS_SERVER_MESSAGE_ID id;
};

/* Messages that control the client. These are sent from the server to affect
 * and action on the client. */
struct __attribute__((__packed__)) cras_client_message {
	uint32_t length;
	enum CRAS_CLIENT_MESSAGE_ID id;
};

/*
 * Messages from client to server.
 */

/* Sent by a client to connect a stream to the server. */
struct __attribute__((__packed__)) cras_connect_message {
	struct cras_server_message header;
	uint32_t proto_version;
	enum CRAS_STREAM_DIRECTION direction; /* input/output/loopback */
	cras_stream_id_t stream_id; /* unique id for this stream */
	enum CRAS_STREAM_TYPE stream_type; /* media, or call, etc. */
	uint32_t buffer_frames; /* Buffer size in frames. */
	uint32_t cb_threshold; /* callback client when this much is left */
	uint32_t flags;
	struct cras_audio_format_packed format; /* rate, channel, sample size */
	uint32_t dev_idx; /* device to attach stream, 0 if none */
	uint64_t effects; /* Bit map of requested effects. */
};

static inline void cras_fill_connect_message(
	struct cras_connect_message *m, enum CRAS_STREAM_DIRECTION direction,
	cras_stream_id_t stream_id, enum CRAS_STREAM_TYPE stream_type,
	size_t buffer_frames, size_t cb_threshold, uint32_t flags,
	uint64_t effects, struct cras_audio_format format, uint32_t dev_idx)
{
	m->proto_version = CRAS_PROTO_VER;
	m->direction = direction;
	m->stream_id = stream_id;
	m->stream_type = stream_type;
	m->buffer_frames = buffer_frames;
	m->cb_threshold = cb_threshold;
	m->flags = flags;
	m->effects = effects;
	pack_cras_audio_format(&m->format, &format);
	m->dev_idx = dev_idx;
	m->header.id = CRAS_SERVER_CONNECT_STREAM;
	m->header.length = sizeof(struct cras_connect_message);
}

/* Sent by a client to remove a stream from the server. */
struct __attribute__((__packed__)) cras_disconnect_stream_message {
	struct cras_server_message header;
	cras_stream_id_t stream_id;
};
static inline void
cras_fill_disconnect_stream_message(struct cras_disconnect_stream_message *m,
				    cras_stream_id_t stream_id)
{
	m->stream_id = stream_id;
	m->header.id = CRAS_SERVER_DISCONNECT_STREAM;
	m->header.length = sizeof(struct cras_disconnect_stream_message);
}

/* Move streams of "type" to the iodev at "iodev_idx". */
struct __attribute__((__packed__)) cras_switch_stream_type_iodev {
	struct cras_server_message header;
	enum CRAS_STREAM_TYPE stream_type;
	uint32_t iodev_idx;
};

/* Set the system volume. */
struct __attribute__((__packed__)) cras_set_system_volume {
	struct cras_server_message header;
	uint32_t volume;
};
static inline void cras_fill_set_system_volume(struct cras_set_system_volume *m,
					       size_t volume)
{
	m->volume = volume;
	m->header.id = CRAS_SERVER_SET_SYSTEM_VOLUME;
	m->header.length = sizeof(*m);
}

/* Sets the capture gain. */
struct __attribute__((__packed__)) cras_set_system_capture_gain {
	struct cras_server_message header;
	int32_t gain;
};
static inline void
cras_fill_set_system_capture_gain(struct cras_set_system_capture_gain *m,
				  long gain)
{
	m->gain = gain;
	m->header.id = CRAS_SERVER_SET_SYSTEM_CAPTURE_GAIN;
	m->header.length = sizeof(*m);
}

/* Set the system mute state. */
struct __attribute__((__packed__)) cras_set_system_mute {
	struct cras_server_message header;
	int32_t mute; /* 0 = un-mute, 1 = mute. */
};
static inline void cras_fill_set_system_mute(struct cras_set_system_mute *m,
					     int mute)
{
	m->mute = mute;
	m->header.id = CRAS_SERVER_SET_SYSTEM_MUTE;
	m->header.length = sizeof(*m);
}
static inline void cras_fill_set_user_mute(struct cras_set_system_mute *m,
					   int mute)
{
	m->mute = mute;
	m->header.id = CRAS_SERVER_SET_USER_MUTE;
	m->header.length = sizeof(*m);
}
static inline void
cras_fill_set_system_mute_locked(struct cras_set_system_mute *m, int locked)
{
	m->mute = locked;
	m->header.id = CRAS_SERVER_SET_SYSTEM_MUTE_LOCKED;
	m->header.length = sizeof(*m);
}
static inline void
cras_fill_set_system_capture_mute(struct cras_set_system_mute *m, int mute)
{
	m->mute = mute;
	m->header.id = CRAS_SERVER_SET_SYSTEM_CAPTURE_MUTE;
	m->header.length = sizeof(*m);
}
static inline void
cras_fill_set_system_capture_mute_locked(struct cras_set_system_mute *m,
					 int locked)
{
	m->mute = locked;
	m->header.id = CRAS_SERVER_SET_SYSTEM_CAPTURE_MUTE_LOCKED;
	m->header.length = sizeof(*m);
}

/* Set an attribute of an ionode. */
struct __attribute__((__packed__)) cras_set_node_attr {
	struct cras_server_message header;
	cras_node_id_t node_id;
	enum ionode_attr attr;
	int32_t value;
};
static inline void cras_fill_set_node_attr(struct cras_set_node_attr *m,
					   cras_node_id_t node_id,
					   enum ionode_attr attr, int value)
{
	m->header.id = CRAS_SERVER_SET_NODE_ATTR;
	m->node_id = node_id;
	m->attr = attr;
	m->value = value;
	m->header.length = sizeof(*m);
}

/* Set an attribute of an ionode. */
struct __attribute__((__packed__)) cras_select_node {
	struct cras_server_message header;
	enum CRAS_STREAM_DIRECTION direction;
	cras_node_id_t node_id;
};
static inline void cras_fill_select_node(struct cras_select_node *m,
					 enum CRAS_STREAM_DIRECTION direction,
					 cras_node_id_t node_id)
{
	m->header.id = CRAS_SERVER_SELECT_NODE;
	m->direction = direction;
	m->node_id = node_id;
	m->header.length = sizeof(*m);
}

/* Add an active ionode. */
struct __attribute__((__packed__)) cras_add_active_node {
	struct cras_server_message header;
	enum CRAS_STREAM_DIRECTION direction;
	cras_node_id_t node_id;
};
static inline void
cras_fill_add_active_node(struct cras_add_active_node *m,
			  enum CRAS_STREAM_DIRECTION direction,
			  cras_node_id_t node_id)
{
	m->header.id = CRAS_SERVER_ADD_ACTIVE_NODE;
	m->direction = direction;
	m->node_id = node_id;
	m->header.length = sizeof(*m);
}

/* Remove an active ionode. */
struct __attribute__((__packed__)) cras_rm_active_node {
	struct cras_server_message header;
	enum CRAS_STREAM_DIRECTION direction;
	cras_node_id_t node_id;
};
static inline void
cras_fill_rm_active_node(struct cras_rm_active_node *m,
			 enum CRAS_STREAM_DIRECTION direction,
			 cras_node_id_t node_id)
{
	m->header.id = CRAS_SERVER_RM_ACTIVE_NODE;
	m->direction = direction;
	m->node_id = node_id;
	m->header.length = sizeof(*m);
}

/* Reload the dsp configuration. */
struct __attribute__((__packed__)) cras_reload_dsp {
	struct cras_server_message header;
};
static inline void cras_fill_reload_dsp(struct cras_reload_dsp *m)
{
	m->header.id = CRAS_SERVER_RELOAD_DSP;
	m->header.length = sizeof(*m);
}

/* Dump current dsp information to syslog. */
struct __attribute__((__packed__)) cras_dump_dsp_info {
	struct cras_server_message header;
};

static inline void cras_fill_dump_dsp_info(struct cras_dump_dsp_info *m)
{
	m->header.id = CRAS_SERVER_DUMP_DSP_INFO;
	m->header.length = sizeof(*m);
}

/* Dump current audio thread information to syslog. */
struct __attribute__((__packed__)) cras_dump_audio_thread {
	struct cras_server_message header;
};

static inline void cras_fill_dump_audio_thread(struct cras_dump_audio_thread *m)
{
	m->header.id = CRAS_SERVER_DUMP_AUDIO_THREAD;
	m->header.length = sizeof(*m);
}

/* Dump bluetooth events and state changes. */
struct __attribute__((__packed__)) cras_dump_bt {
	struct cras_server_message header;
};

static inline void cras_fill_dump_bt(struct cras_dump_bt *m)
{
	m->header.id = CRAS_SERVER_DUMP_BT;
	m->header.length = sizeof(*m);
}

/* Dump current audio thread snapshots to shard memory with the client. */
struct __attribute__((__packed__)) cras_dump_snapshots {
	struct cras_server_message header;
};

static inline void cras_fill_dump_snapshots(struct cras_dump_snapshots *m)
{
	m->header.id = CRAS_SERVER_DUMP_SNAPSHOTS;
	m->header.length = sizeof(*m);
}

/* Add a test device. */
struct __attribute__((__packed__)) cras_add_test_dev {
	struct cras_server_message header;
	enum TEST_IODEV_TYPE type;
};

static inline void cras_fill_add_test_dev(struct cras_add_test_dev *m,
					  enum TEST_IODEV_TYPE type)
{
	m->header.id = CRAS_SERVER_ADD_TEST_DEV;
	m->header.length = sizeof(*m);
	m->type = type;
}

/* Command a test device. */
struct __attribute__((__packed__)) cras_test_dev_command {
	struct cras_server_message header;
	unsigned int command;
	unsigned int iodev_idx;
	unsigned int data_len;
	uint8_t data[CRAS_MAX_TEST_DATA_LEN];
};

static inline void cras_fill_test_dev_command(struct cras_test_dev_command *m,
					      unsigned int iodev_idx,
					      enum CRAS_TEST_IODEV_CMD command,
					      unsigned int data_len,
					      const uint8_t *data)
{
	m->header.id = CRAS_SERVER_TEST_DEV_COMMAND;
	m->header.length = sizeof(*m) + data_len;
	m->iodev_idx = iodev_idx;
	m->command = command;
	m->data_len = data_len;
	memcpy(m->data, data, data_len);
}

static inline void cras_fill_suspend_message(struct cras_server_message *m,
					     int is_suspend)
{
	m->id = is_suspend ? CRAS_SERVER_SUSPEND : CRAS_SERVER_RESUME;
	m->length = sizeof(*m);
}

/*
 * Configures the global remix converter.
 * `num_channels` must be less than `CRAS_MAX_REMIX_CHANNELS`.
 */
struct __attribute__((__packed__)) cras_config_global_remix {
	struct cras_server_message header;
	unsigned int num_channels;
	float coefficient[CRAS_MAX_REMIX_CHANNELS];
};

static inline void
cras_fill_config_global_remix_command(struct cras_config_global_remix *m,
				      unsigned int num_channels, float *coeff,
				      unsigned int count)
{
	m->header.id = CRAS_CONFIG_GLOBAL_REMIX;
	m->header.length = sizeof(*m) + count * sizeof(*coeff);
	m->num_channels = num_channels;
	memcpy(m->coefficient, coeff, count * sizeof(*coeff));
}

/* Get supported hotword models. */
struct __attribute__((__packed__)) cras_get_hotword_models {
	struct cras_server_message header;
	cras_node_id_t node_id;
};

static inline void
cras_fill_get_hotword_models_message(struct cras_get_hotword_models *m,
				     cras_node_id_t node_id)
{
	m->header.id = CRAS_SERVER_GET_HOTWORD_MODELS;
	m->header.length = sizeof(*m);
	m->node_id = node_id;
}

/* Set desired hotword model. */
struct __attribute__((__packed__)) cras_set_hotword_model {
	struct cras_server_message header;
	cras_node_id_t node_id;
	char model_name[CRAS_MAX_HOTWORD_MODEL_NAME_SIZE];
};

static inline void
cras_fill_set_hotword_model_message(struct cras_set_hotword_model *m,
				    cras_node_id_t node_id,
				    const char *model_name)
{
	m->header.id = CRAS_SERVER_SET_HOTWORD_MODEL;
	m->header.length = sizeof(*m);
	m->node_id = node_id;
	memcpy(m->model_name, model_name, CRAS_MAX_HOTWORD_MODEL_NAME_SIZE);
}

/* Set aec dump to start or stop. */
struct __attribute__((__packed__)) cras_set_aec_dump {
	struct cras_server_message header;
	cras_stream_id_t stream_id;
	unsigned int start;
};

static inline void cras_fill_set_aec_dump_message(struct cras_set_aec_dump *m,
						  cras_stream_id_t stream_id,
						  unsigned int start)
{
	m->header.id = CRAS_SERVER_SET_AEC_DUMP;
	m->header.length = sizeof(*m);
	m->stream_id = stream_id;
	m->start = start;
}

/* Reload the aec configuration. */
struct __attribute__((__packed__)) cras_reload_aec_config {
	struct cras_server_message header;
};
static inline void cras_fill_reload_aec_config(struct cras_reload_aec_config *m)
{
	m->header.id = CRAS_SERVER_RELOAD_AEC_CONFIG;
	m->header.length = sizeof(*m);
}

/* Sets the flag to enable or disable bluetooth wideband speech feature. */
struct __attribute__((__packed__)) cras_set_bt_wbs_enabled {
	struct cras_server_message header;
	unsigned int enabled;
};
static inline void
cras_fill_set_bt_wbs_enabled(struct cras_set_bt_wbs_enabled *m,
			     unsigned int enabled)
{
	m->header.id = CRAS_SERVER_SET_BT_WBS_ENABLED;
	m->header.length = sizeof(*m);
	m->enabled = enabled;
}

struct __attribute__((__packed__)) cras_register_notification {
	struct cras_server_message header;
	uint32_t msg_id;
	int do_register;
};
static inline void
cras_fill_register_notification_message(struct cras_register_notification *m,
					enum CRAS_CLIENT_MESSAGE_ID msg_id,
					int do_register)
{
	m->header.id = CRAS_SERVER_REGISTER_NOTIFICATION;
	m->header.length = sizeof(*m);
	m->msg_id = msg_id;
	m->do_register = do_register;
}

/*
 * Messages sent from server to client.
 */

/* Reply from the server indicating that the client has connected. */
struct __attribute__((__packed__)) cras_client_connected {
	struct cras_client_message header;
	uint32_t client_id;
};
static inline void cras_fill_client_connected(struct cras_client_connected *m,
					      size_t client_id)
{
	m->client_id = client_id;
	m->header.id = CRAS_CLIENT_CONNECTED;
	m->header.length = sizeof(struct cras_client_connected);
}

/*
 * Reply from server that a stream has been successfully added.
 * Two file descriptors are added, input shm followed by out shm.
 *
 * samples_shm_size is shm_max_size for old clients.
 * TODO(fletcherw) remove comment once all clients are on CRAS_PROTO_VER >= 3.
 */
struct __attribute__((__packed__)) cras_client_stream_connected {
	struct cras_client_message header;
	int32_t err;
	cras_stream_id_t stream_id;
	struct cras_audio_format_packed format;
	uint32_t samples_shm_size;
	uint64_t effects;
};

static inline void
cras_fill_client_stream_connected(struct cras_client_stream_connected *m,
				  int err, cras_stream_id_t stream_id,
				  struct cras_audio_format *format,
				  size_t samples_shm_size, uint64_t effects)
{
	m->err = err;
	m->stream_id = stream_id;
	pack_cras_audio_format(&m->format, format);
	m->samples_shm_size = samples_shm_size;
	m->effects = effects;
	m->header.id = CRAS_CLIENT_STREAM_CONNECTED;
	m->header.length = sizeof(struct cras_client_stream_connected);
}

/* Sent from server to client when audio debug information is requested. */
struct cras_client_audio_debug_info_ready {
	struct cras_client_message header;
};
static inline void cras_fill_client_audio_debug_info_ready(
	struct cras_client_audio_debug_info_ready *m)
{
	m->header.id = CRAS_CLIENT_AUDIO_DEBUG_INFO_READY;
	m->header.length = sizeof(*m);
}

/* Sent from server to client when hotword models info is ready. */
struct cras_client_get_hotword_models_ready {
	struct cras_client_message header;
	int32_t hotword_models_size;
	uint8_t hotword_models[CRAS_MAX_HOTWORD_MODELS];
};
static inline void cras_fill_client_get_hotword_models_ready(
	struct cras_client_get_hotword_models_ready *m,
	const char *hotword_models, size_t hotword_models_size)
{
	m->header.id = CRAS_CLIENT_GET_HOTWORD_MODELS_READY;
	m->header.length = sizeof(*m);
	m->hotword_models_size = hotword_models_size;
	/* Copy string data with terminator. */
	strncpy((char *)m->hotword_models, hotword_models,
		CRAS_MAX_HOTWORD_MODELS);
}

/* System status messages sent from server to client when state changes. */
struct __attribute__((__packed__)) cras_client_volume_changed {
	struct cras_client_message header;
	int32_t volume;
};
static inline void
cras_fill_client_output_volume_changed(struct cras_client_volume_changed *m,
				       int32_t volume)
{
	m->header.id = CRAS_CLIENT_OUTPUT_VOLUME_CHANGED;
	m->header.length = sizeof(*m);
	m->volume = volume;
}
static inline void
cras_fill_client_capture_gain_changed(struct cras_client_volume_changed *m,
				      int32_t gain)
{
	m->header.id = CRAS_CLIENT_CAPTURE_GAIN_CHANGED;
	m->header.length = sizeof(*m);
	m->volume = gain;
}

struct __attribute__((__packed__)) cras_client_mute_changed {
	struct cras_client_message header;
	int32_t muted;
	int32_t user_muted;
	int32_t mute_locked;
};
static inline void
cras_fill_client_output_mute_changed(struct cras_client_mute_changed *m,
				     int32_t muted, int32_t user_muted,
				     int32_t mute_locked)
{
	m->header.id = CRAS_CLIENT_OUTPUT_MUTE_CHANGED;
	m->header.length = sizeof(*m);
	m->muted = muted;
	m->user_muted = user_muted;
	m->mute_locked = mute_locked;
}
static inline void
cras_fill_client_capture_mute_changed(struct cras_client_mute_changed *m,
				      int32_t muted, int32_t mute_locked)
{
	m->header.id = CRAS_CLIENT_CAPTURE_MUTE_CHANGED;
	m->header.length = sizeof(*m);
	m->muted = muted;
	m->user_muted = 0;
	m->mute_locked = mute_locked;
}

struct __attribute__((__packed__)) cras_client_nodes_changed {
	struct cras_client_message header;
};
static inline void
cras_fill_client_nodes_changed(struct cras_client_nodes_changed *m)
{
	m->header.id = CRAS_CLIENT_NODES_CHANGED;
	m->header.length = sizeof(*m);
}

struct __attribute__((__packed__)) cras_client_active_node_changed {
	struct cras_client_message header;
	uint32_t direction;
	cras_node_id_t node_id;
};
static inline void
cras_fill_client_active_node_changed(struct cras_client_active_node_changed *m,
				     enum CRAS_STREAM_DIRECTION direction,
				     cras_node_id_t node_id)
{
	m->header.id = CRAS_CLIENT_ACTIVE_NODE_CHANGED;
	m->header.length = sizeof(*m);
	m->direction = direction;
	m->node_id = node_id;
};

struct __attribute__((__packed__)) cras_client_node_value_changed {
	struct cras_client_message header;
	cras_node_id_t node_id;
	int32_t value;
};
static inline void cras_fill_client_output_node_volume_changed(
	struct cras_client_node_value_changed *m, cras_node_id_t node_id,
	int32_t volume)
{
	m->header.id = CRAS_CLIENT_OUTPUT_NODE_VOLUME_CHANGED;
	m->header.length = sizeof(*m);
	m->node_id = node_id;
	m->value = volume;
};
static inline void cras_fill_client_node_left_right_swapped_changed(
	struct cras_client_node_value_changed *m, cras_node_id_t node_id,
	int swapped)
{
	m->header.id = CRAS_CLIENT_NODE_LEFT_RIGHT_SWAPPED_CHANGED;
	m->header.length = sizeof(*m);
	m->node_id = node_id;
	m->value = swapped;
};
static inline void cras_fill_client_input_node_gain_changed(
	struct cras_client_node_value_changed *m, cras_node_id_t node_id,
	int32_t gain)
{
	m->header.id = CRAS_CLIENT_INPUT_NODE_GAIN_CHANGED;
	m->header.length = sizeof(*m);
	m->node_id = node_id;
	m->value = gain;
};

struct __attribute__((__packed__)) cras_client_num_active_streams_changed {
	struct cras_client_message header;
	uint32_t direction;
	uint32_t num_active_streams;
};
static inline void cras_fill_client_num_active_streams_changed(
	struct cras_client_num_active_streams_changed *m,
	enum CRAS_STREAM_DIRECTION direction, uint32_t num_active_streams)
{
	m->header.id = CRAS_CLIENT_NUM_ACTIVE_STREAMS_CHANGED;
	m->header.length = sizeof(*m);
	m->direction = direction;
	m->num_active_streams = num_active_streams;
};

/*
 * Messages specific to passing audio between client and server
 */
enum CRAS_AUDIO_MESSAGE_ID {
	AUDIO_MESSAGE_REQUEST_DATA,
	AUDIO_MESSAGE_DATA_READY,
	AUDIO_MESSAGE_DATA_CAPTURED,
	NUM_AUDIO_MESSAGES
};

struct __attribute__((__packed__)) audio_message {
	enum CRAS_AUDIO_MESSAGE_ID id;
	int32_t error;
	uint32_t frames; /* number of samples per channel */
};

#endif /* CRAS_MESSAGES_H_ */

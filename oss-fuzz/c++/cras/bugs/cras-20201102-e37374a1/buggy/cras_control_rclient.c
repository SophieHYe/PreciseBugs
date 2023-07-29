/* Copyright 2019 The Chromium OS Authors. All rights reserved.
 * Use of this source code is governed by a BSD-style license that can be
 * found in the LICENSE file.
 */
#include <assert.h>
#include <stdlib.h>
#include <syslog.h>

#include "audio_thread.h"
#include "audio_thread_log.h"
#include "cras_apm_list.h"
#include "cras_bt_log.h"
#include "cras_config.h"
#include "cras_control_rclient.h"
#include "cras_dsp.h"
#include "cras_iodev.h"
#include "cras_iodev_list.h"
#include "cras_hfp_ag_profile.h"
#include "cras_main_thread_log.h"
#include "cras_messages.h"
#include "cras_observer.h"
#include "cras_rclient.h"
#include "cras_rclient_util.h"
#include "cras_rstream.h"
#include "cras_system_state.h"
#include "cras_types.h"
#include "cras_util.h"
#include "utlist.h"

/* Handles dumping audio thread debug info back to the client. */
static void dump_audio_thread_info(struct cras_rclient *client)
{
	struct cras_client_audio_debug_info_ready msg;
	struct cras_server_state *state;

	cras_fill_client_audio_debug_info_ready(&msg);
	state = cras_system_state_get_no_lock();
	audio_thread_dump_thread_info(cras_iodev_list_get_audio_thread(),
				      &state->audio_debug_info);
	client->ops->send_message_to_client(client, &msg.header, NULL, 0);
}

/* Sends shared memory fd for audio thread event log back to the client. */
static void get_atlog_fd(struct cras_rclient *client)
{
	struct cras_client_atlog_fd_ready msg;
	int atlog_fd;

	cras_fill_client_atlog_fd_ready(&msg);
	atlog_fd = audio_thread_event_log_shm_fd();
	client->ops->send_message_to_client(client, &msg.header, &atlog_fd, 1);
}

/* Handles dumping audio snapshots to shared memory for the client. */
static void dump_audio_thread_snapshots(struct cras_rclient *client)
{
	struct cras_client_audio_debug_info_ready msg;

	cras_fill_client_audio_debug_info_ready(&msg);
	cras_system_state_dump_snapshots();
	client->ops->send_message_to_client(client, &msg.header, NULL, 0);
}

static void handle_get_hotword_models(struct cras_rclient *client,
				      cras_node_id_t node_id)
{
	struct cras_client_get_hotword_models_ready *msg;
	char *hotword_models;
	unsigned hotword_models_size;
	uint8_t buf[CRAS_CLIENT_MAX_MSG_SIZE];

	msg = (struct cras_client_get_hotword_models_ready *)buf;
	hotword_models = cras_iodev_list_get_hotword_models(node_id);
	if (!hotword_models)
		goto empty_reply;
	hotword_models_size = strlen(hotword_models);
	if (hotword_models_size > CRAS_MAX_HOTWORD_MODELS) {
		free(hotword_models);
		goto empty_reply;
	}

	cras_fill_client_get_hotword_models_ready(msg, hotword_models,
						  hotword_models_size);
	client->ops->send_message_to_client(client, &msg->header, NULL, 0);
	free(hotword_models);
	return;

empty_reply:
	cras_fill_client_get_hotword_models_ready(msg, NULL, 0);
	client->ops->send_message_to_client(client, &msg->header, NULL, 0);
}

/* Client notification callback functions. */

static void send_output_volume_changed(void *context, int32_t volume)
{
	struct cras_client_volume_changed msg;
	struct cras_rclient *client = (struct cras_rclient *)context;

	cras_fill_client_output_volume_changed(&msg, volume);
	client->ops->send_message_to_client(client, &msg.header, NULL, 0);
}

static void send_output_mute_changed(void *context, int muted, int user_muted,
				     int mute_locked)
{
	struct cras_client_mute_changed msg;
	struct cras_rclient *client = (struct cras_rclient *)context;

	cras_fill_client_output_mute_changed(&msg, muted, user_muted,
					     mute_locked);
	client->ops->send_message_to_client(client, &msg.header, NULL, 0);
}

static void send_capture_gain_changed(void *context, int32_t gain)
{
	struct cras_client_volume_changed msg;
	struct cras_rclient *client = (struct cras_rclient *)context;

	cras_fill_client_capture_gain_changed(&msg, gain);
	client->ops->send_message_to_client(client, &msg.header, NULL, 0);
}

static void send_capture_mute_changed(void *context, int muted, int mute_locked)
{
	struct cras_client_mute_changed msg;
	struct cras_rclient *client = (struct cras_rclient *)context;

	cras_fill_client_capture_mute_changed(&msg, muted, mute_locked);
	client->ops->send_message_to_client(client, &msg.header, NULL, 0);
}

static void send_nodes_changed(void *context)
{
	struct cras_client_nodes_changed msg;
	struct cras_rclient *client = (struct cras_rclient *)context;

	cras_fill_client_nodes_changed(&msg);
	client->ops->send_message_to_client(client, &msg.header, NULL, 0);
}

static void send_active_node_changed(void *context,
				     enum CRAS_STREAM_DIRECTION dir,
				     cras_node_id_t node_id)
{
	struct cras_client_active_node_changed msg;
	struct cras_rclient *client = (struct cras_rclient *)context;

	cras_fill_client_active_node_changed(&msg, dir, node_id);
	client->ops->send_message_to_client(client, &msg.header, NULL, 0);
}

static void send_output_node_volume_changed(void *context,
					    cras_node_id_t node_id,
					    int32_t volume)
{
	struct cras_client_node_value_changed msg;
	struct cras_rclient *client = (struct cras_rclient *)context;

	cras_fill_client_output_node_volume_changed(&msg, node_id, volume);
	client->ops->send_message_to_client(client, &msg.header, NULL, 0);
}

static void send_node_left_right_swapped_changed(void *context,
						 cras_node_id_t node_id,
						 int swapped)
{
	struct cras_client_node_value_changed msg;
	struct cras_rclient *client = (struct cras_rclient *)context;

	cras_fill_client_node_left_right_swapped_changed(&msg, node_id,
							 swapped);
	client->ops->send_message_to_client(client, &msg.header, NULL, 0);
}

static void send_input_node_gain_changed(void *context, cras_node_id_t node_id,
					 int32_t gain)
{
	struct cras_client_node_value_changed msg;
	struct cras_rclient *client = (struct cras_rclient *)context;

	cras_fill_client_input_node_gain_changed(&msg, node_id, gain);
	client->ops->send_message_to_client(client, &msg.header, NULL, 0);
}

static void send_num_active_streams_changed(void *context,
					    enum CRAS_STREAM_DIRECTION dir,
					    uint32_t num_active_streams)
{
	struct cras_client_num_active_streams_changed msg;
	struct cras_rclient *client = (struct cras_rclient *)context;

	cras_fill_client_num_active_streams_changed(&msg, dir,
						    num_active_streams);
	client->ops->send_message_to_client(client, &msg.header, NULL, 0);
}

static void register_for_notification(struct cras_rclient *client,
				      enum CRAS_CLIENT_MESSAGE_ID msg_id,
				      int do_register)
{
	struct cras_observer_ops observer_ops;
	int empty;

	cras_observer_get_ops(client->observer, &observer_ops);

	switch (msg_id) {
	case CRAS_CLIENT_OUTPUT_VOLUME_CHANGED:
		observer_ops.output_volume_changed =
			do_register ? send_output_volume_changed : NULL;
		break;
	case CRAS_CLIENT_OUTPUT_MUTE_CHANGED:
		observer_ops.output_mute_changed =
			do_register ? send_output_mute_changed : NULL;
		break;
	case CRAS_CLIENT_CAPTURE_GAIN_CHANGED:
		observer_ops.capture_gain_changed =
			do_register ? send_capture_gain_changed : NULL;
		break;
	case CRAS_CLIENT_CAPTURE_MUTE_CHANGED:
		observer_ops.capture_mute_changed =
			do_register ? send_capture_mute_changed : NULL;
		break;
	case CRAS_CLIENT_NODES_CHANGED:
		observer_ops.nodes_changed =
			do_register ? send_nodes_changed : NULL;
		break;
	case CRAS_CLIENT_ACTIVE_NODE_CHANGED:
		observer_ops.active_node_changed =
			do_register ? send_active_node_changed : NULL;
		break;
	case CRAS_CLIENT_OUTPUT_NODE_VOLUME_CHANGED:
		observer_ops.output_node_volume_changed =
			do_register ? send_output_node_volume_changed : NULL;
		break;
	case CRAS_CLIENT_NODE_LEFT_RIGHT_SWAPPED_CHANGED:
		observer_ops.node_left_right_swapped_changed =
			do_register ? send_node_left_right_swapped_changed :
				      NULL;
		break;
	case CRAS_CLIENT_INPUT_NODE_GAIN_CHANGED:
		observer_ops.input_node_gain_changed =
			do_register ? send_input_node_gain_changed : NULL;
		break;
	case CRAS_CLIENT_NUM_ACTIVE_STREAMS_CHANGED:
		observer_ops.num_active_streams_changed =
			do_register ? send_num_active_streams_changed : NULL;
		break;
	default:
		syslog(LOG_ERR, "Invalid client notification message ID: %u",
		       msg_id);
		break;
	}

	empty = cras_observer_ops_are_empty(&observer_ops);
	if (client->observer) {
		if (empty) {
			cras_observer_remove(client->observer);
			client->observer = NULL;
		} else {
			cras_observer_set_ops(client->observer, &observer_ops);
		}
	} else if (!empty) {
		client->observer = cras_observer_add(&observer_ops, client);
	}
}

static int direction_valid(enum CRAS_STREAM_DIRECTION direction)
{
	return direction < CRAS_NUM_DIRECTIONS &&
	       direction != CRAS_STREAM_UNDEFINED;
}

/* Entry point for handling a message from the client.  Called from the main
 * server context.
 *
 * If the message from clients has incorrect length (truncated message), return
 * an error up to CRAS server.
 * If the message from clients has invalid content, should return the errors to
 * clients by send_message_to_client and return 0 here.
 *
 */
static int ccr_handle_message_from_client(struct cras_rclient *client,
					  const struct cras_server_message *msg,
					  int *fds, unsigned int num_fds)
{
	int rc = 0;
	assert(client && msg);

	rc = rclient_validate_message_fds(msg, fds, num_fds);
	if (rc < 0) {
		for (int i = 0; i < (int)num_fds; i++)
			if (fds[i] >= 0)
				close(fds[i]);
		return rc;
	}
	int fd = num_fds > 0 ? fds[0] : -1;

	switch (msg->id) {
	case CRAS_SERVER_CONNECT_STREAM: {
		int client_shm_fd = num_fds > 1 ? fds[1] : -1;
		struct cras_connect_message cmsg;
		if (MSG_LEN_VALID(msg, struct cras_connect_message)) {
			rclient_handle_client_stream_connect(
				client,
				(const struct cras_connect_message *)msg, fd,
				client_shm_fd);
		} else if (!convert_connect_message_old(msg, &cmsg)) {
			rclient_handle_client_stream_connect(client, &cmsg, fd,
							     client_shm_fd);
		} else {
			return -EINVAL;
		}
		break;
	}
	case CRAS_SERVER_DISCONNECT_STREAM:
		if (!MSG_LEN_VALID(msg, struct cras_disconnect_stream_message))
			return -EINVAL;
		rclient_handle_client_stream_disconnect(
			client,
			(const struct cras_disconnect_stream_message *)msg);
		break;
	case CRAS_SERVER_SET_SYSTEM_VOLUME:
		if (!MSG_LEN_VALID(msg, struct cras_set_system_volume))
			return -EINVAL;
		cras_system_set_volume(
			((const struct cras_set_system_volume *)msg)->volume);
		break;
	case CRAS_SERVER_SET_SYSTEM_MUTE:
		if (!MSG_LEN_VALID(msg, struct cras_set_system_mute))
			return -EINVAL;
		cras_system_set_mute(
			((const struct cras_set_system_mute *)msg)->mute);
		break;
	case CRAS_SERVER_SET_USER_MUTE:
		if (!MSG_LEN_VALID(msg, struct cras_set_system_mute))
			return -EINVAL;
		cras_system_set_user_mute(
			((const struct cras_set_system_mute *)msg)->mute);
		break;
	case CRAS_SERVER_SET_SYSTEM_MUTE_LOCKED:
		if (!MSG_LEN_VALID(msg, struct cras_set_system_mute))
			return -EINVAL;
		cras_system_set_mute_locked(
			((const struct cras_set_system_mute *)msg)->mute);
		break;
	case CRAS_SERVER_SET_SYSTEM_CAPTURE_MUTE:
		if (!MSG_LEN_VALID(msg, struct cras_set_system_mute))
			return -EINVAL;
		cras_system_set_capture_mute(
			((const struct cras_set_system_mute *)msg)->mute);
		break;
	case CRAS_SERVER_SET_SYSTEM_CAPTURE_MUTE_LOCKED:
		if (!MSG_LEN_VALID(msg, struct cras_set_system_mute))
			return -EINVAL;
		cras_system_set_capture_mute_locked(
			((const struct cras_set_system_mute *)msg)->mute);
		break;
	case CRAS_SERVER_SET_NODE_ATTR: {
		const struct cras_set_node_attr *m =
			(const struct cras_set_node_attr *)msg;
		if (!MSG_LEN_VALID(msg, struct cras_set_node_attr))
			return -EINVAL;
		cras_iodev_list_set_node_attr(m->node_id, m->attr, m->value);
		break;
	}
	case CRAS_SERVER_SELECT_NODE: {
		const struct cras_select_node *m =
			(const struct cras_select_node *)msg;
		if (!MSG_LEN_VALID(msg, struct cras_select_node) ||
		    !direction_valid(m->direction))
			return -EINVAL;
		cras_iodev_list_select_node(m->direction, m->node_id);
		break;
	}
	case CRAS_SERVER_ADD_ACTIVE_NODE: {
		const struct cras_add_active_node *m =
			(const struct cras_add_active_node *)msg;
		if (!MSG_LEN_VALID(msg, struct cras_add_active_node) ||
		    !direction_valid(m->direction))
			return -EINVAL;
		cras_iodev_list_add_active_node(m->direction, m->node_id);
		break;
	}
	case CRAS_SERVER_RM_ACTIVE_NODE: {
		const struct cras_rm_active_node *m =
			(const struct cras_rm_active_node *)msg;
		if (!MSG_LEN_VALID(msg, struct cras_rm_active_node) ||
		    !direction_valid(m->direction))
			return -EINVAL;
		cras_iodev_list_rm_active_node(m->direction, m->node_id);
		break;
	}
	case CRAS_SERVER_RELOAD_DSP:
		cras_dsp_reload_ini();
		break;
	case CRAS_SERVER_DUMP_DSP_INFO:
		cras_dsp_dump_info();
		break;
	case CRAS_SERVER_DUMP_AUDIO_THREAD:
		dump_audio_thread_info(client);
		break;
	case CRAS_SERVER_GET_ATLOG_FD:
		get_atlog_fd(client);
		break;
	case CRAS_SERVER_DUMP_MAIN: {
		struct cras_client_audio_debug_info_ready msg;
		struct cras_server_state *state;

		state = cras_system_state_get_no_lock();
		memcpy(&state->main_thread_debug_info.main_log, main_log,
		       sizeof(struct main_thread_event_log));

		cras_fill_client_audio_debug_info_ready(&msg);
		client->ops->send_message_to_client(client, &msg.header, NULL,
						    0);
		break;
	}
	case CRAS_SERVER_DUMP_BT: {
		struct cras_client_audio_debug_info_ready msg;
		struct cras_server_state *state;

		state = cras_system_state_get_no_lock();
#ifdef CRAS_DBUS
		memcpy(&state->bt_debug_info.bt_log, btlog,
		       sizeof(struct cras_bt_debug_info));
		memcpy(&state->bt_debug_info.wbs_logger,
		       cras_hfp_ag_get_wbs_logger(),
		       sizeof(struct packet_status_logger));
#else
		memset(&state->bt_debug_info.bt_log, 0,
		       sizeof(struct cras_bt_debug_info));
		memset(&state->bt_debug_info.wbs_logger, 0,
		       sizeof(struct packet_status_logger));
#endif

		cras_fill_client_audio_debug_info_ready(&msg);
		client->ops->send_message_to_client(client, &msg.header, NULL,
						    0);
		break;
	}
	case CRAS_SERVER_SET_BT_WBS_ENABLED: {
		const struct cras_set_bt_wbs_enabled *m =
			(const struct cras_set_bt_wbs_enabled *)msg;
		if (!MSG_LEN_VALID(msg, struct cras_set_bt_wbs_enabled))
			return -EINVAL;
		cras_system_set_bt_wbs_enabled(m->enabled);
		break;
	}
	case CRAS_SERVER_DUMP_SNAPSHOTS:
		dump_audio_thread_snapshots(client);
		break;
	case CRAS_SERVER_ADD_TEST_DEV: {
		const struct cras_add_test_dev *m =
			(const struct cras_add_test_dev *)msg;
		if (!MSG_LEN_VALID(msg, struct cras_add_test_dev))
			return -EINVAL;
		cras_iodev_list_add_test_dev(m->type);
		break;
	}
	case CRAS_SERVER_TEST_DEV_COMMAND: {
		const struct cras_test_dev_command *m =
			(const struct cras_test_dev_command *)msg;
		if (!MSG_LEN_VALID(msg, struct cras_test_dev_command))
			return -EINVAL;
		cras_iodev_list_test_dev_command(
			m->iodev_idx, (enum CRAS_TEST_IODEV_CMD)m->command,
			m->data_len, m->data);
		break;
	}
	case CRAS_SERVER_SUSPEND:
		cras_system_set_suspended(1);
		break;
	case CRAS_SERVER_RESUME:
		cras_system_set_suspended(0);
		break;
	case CRAS_CONFIG_GLOBAL_REMIX: {
		const struct cras_config_global_remix *m =
			(const struct cras_config_global_remix *)msg;
		float *coefficient;

		if (!MSG_LEN_VALID(msg, struct cras_config_global_remix) ||
		    m->num_channels > CRAS_MAX_REMIX_CHANNELS)
			return -EINVAL;
		const size_t coefficient_len =
			(size_t)m->num_channels * (size_t)m->num_channels;
		const size_t size_with_coefficients =
			sizeof(*m) +
			coefficient_len * sizeof(m->coefficient[0]);
		if (size_with_coefficients != msg->length)
			return -EINVAL;

		coefficient =
			(float *)calloc(coefficient_len, sizeof(coefficient));
		if (!coefficient) {
			syslog(LOG_ERR,
			       "Failed to create local coefficient array.");
			break;
		}
		memcpy(coefficient, m->coefficient,
		       coefficient_len * sizeof(coefficient));

		audio_thread_config_global_remix(
			cras_iodev_list_get_audio_thread(), m->num_channels,
			coefficient);
		free(coefficient);
		break;
	}
	case CRAS_SERVER_GET_HOTWORD_MODELS: {
		if (!MSG_LEN_VALID(msg, struct cras_get_hotword_models))
			return -EINVAL;
		handle_get_hotword_models(
			client,
			((const struct cras_get_hotword_models *)msg)->node_id);
		break;
	}
	case CRAS_SERVER_SET_HOTWORD_MODEL: {
		const struct cras_set_hotword_model *m =
			(const struct cras_set_hotword_model *)msg;
		if (!MSG_LEN_VALID(msg, struct cras_set_hotword_model))
			return -EINVAL;
		cras_iodev_list_set_hotword_model(m->node_id, m->model_name);
		break;
	}
	case CRAS_SERVER_REGISTER_NOTIFICATION: {
		const struct cras_register_notification *m =
			(struct cras_register_notification *)msg;
		if (!MSG_LEN_VALID(msg, struct cras_register_notification))
			return -EINVAL;
		register_for_notification(
			client, (enum CRAS_CLIENT_MESSAGE_ID)m->msg_id,
			m->do_register);
		break;
	}
	case CRAS_SERVER_SET_AEC_DUMP: {
		const struct cras_set_aec_dump *m =
			(const struct cras_set_aec_dump *)msg;
		if (!MSG_LEN_VALID(msg, struct cras_set_aec_dump))
			return -EINVAL;
		audio_thread_set_aec_dump(cras_iodev_list_get_audio_thread(),
					  m->stream_id, m->start, fd);
		break;
	}
	case CRAS_SERVER_RELOAD_AEC_CONFIG:
		cras_apm_list_reload_aec_config();
		break;
	default:
		break;
	}

	return 0;
}

/* Declarations of cras_rclient operators for cras_control_rclient. */
static const struct cras_rclient_ops cras_control_rclient_ops = {
	.handle_message_from_client = ccr_handle_message_from_client,
	.send_message_to_client = rclient_send_message_to_client,
	.destroy = rclient_destroy,
};

/*
 * Exported Functions.
 */

/* Creates a client structure and sends a message back informing the client that
 * the conneciton has succeeded. */
struct cras_rclient *cras_control_rclient_create(int fd, size_t id)
{
	/* Supports all directions but not CRAS_STREAM_UNDEFINED. */
	int supported_directions =
		CRAS_STREAM_ALL_DIRECTION ^
		cras_stream_direction_mask(CRAS_STREAM_UNDEFINED);

	return rclient_generic_create(fd, id, &cras_control_rclient_ops,
				      supported_directions);
}

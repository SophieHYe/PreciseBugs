/*
 * florensia.c
 *
 * Copyright (C) 2009-11 - ipoque GmbH
 * Copyright (C) 2011-22 - ntop.org
 *
 * This file is part of nDPI, an open source deep packet inspection
 * library based on the OpenDPI and PACE technology by ipoque GmbH
 *
 * nDPI is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * nDPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with nDPI.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

#include "ndpi_protocol_ids.h"

#define NDPI_CURRENT_PROTO NDPI_PROTOCOL_FLORENSIA

#include "ndpi_api.h"


static void ndpi_florensia_add_connection(struct ndpi_detection_module_struct *ndpi_struct, struct ndpi_flow_struct *flow)
{
  ndpi_set_detected_protocol(ndpi_struct, flow, NDPI_PROTOCOL_FLORENSIA, NDPI_PROTOCOL_UNKNOWN, NDPI_CONFIDENCE_DPI);
}

void ndpi_search_florensia(struct ndpi_detection_module_struct *ndpi_struct, struct ndpi_flow_struct *flow)
{
  struct ndpi_packet_struct *packet = &ndpi_struct->packet;
	
  NDPI_LOG_DBG(ndpi_struct, "search florensia\n");

  if (packet->tcp != NULL) {
    if (packet->payload_packet_len == 5 && get_l16(packet->payload, 0) == packet->payload_packet_len
	&& packet->payload[2] == 0x65 && packet->payload[4] == 0xff) {
      if (flow->florensia_stage == 1) {
	NDPI_LOG_INFO(ndpi_struct, "found florensia\n");
	ndpi_florensia_add_connection(ndpi_struct, flow);
	return;
      }
      NDPI_LOG_DBG2(ndpi_struct, "maybe florensia -> stage is set to 1\n");
      flow->florensia_stage = 1;
      return;
    }
    if (packet->payload_packet_len > 8 && get_l16(packet->payload, 0) == packet->payload_packet_len
	&& get_u_int16_t(packet->payload, 2) == htons(0x0201) && get_u_int32_t(packet->payload, 4) == htonl(0xFFFFFFFF)) {
      NDPI_LOG_DBG2(ndpi_struct, "maybe florensia -> stage is set to 1\n");
      flow->florensia_stage = 1;
      return;
    }
    if (packet->payload_packet_len == 406 && get_l16(packet->payload, 0) == packet->payload_packet_len
	&& packet->payload[2] == 0x63) {
      NDPI_LOG_DBG2(ndpi_struct, "maybe florensia -> stage is set to 1\n");
      flow->florensia_stage = 1;
      return;
    }
    if (packet->payload_packet_len == 12 && get_l16(packet->payload, 0) == packet->payload_packet_len
	&& get_u_int16_t(packet->payload, 2) == htons(0x0301)) {
      if (flow->florensia_stage == 1) {
	NDPI_LOG_INFO(ndpi_struct, "found florensia\n");
	ndpi_florensia_add_connection(ndpi_struct, flow);
	return;
      }
      NDPI_LOG_DBG2(ndpi_struct, "maybe florensia -> stage is set to 1\n");
      flow->florensia_stage = 1;
      return;
    }

    if (flow->florensia_stage == 1) {
      if (packet->payload_packet_len == 8 && get_l16(packet->payload, 0) == packet->payload_packet_len
	  && get_u_int16_t(packet->payload, 2) == htons(0x0302) && get_u_int32_t(packet->payload, 4) == htonl(0xFFFFFFFF)) {
	NDPI_LOG_INFO(ndpi_struct, "found florensia asymmetrically\n");
	ndpi_florensia_add_connection(ndpi_struct, flow);
	return;
      }
      if (packet->payload_packet_len == 24 && get_l16(packet->payload, 0) == packet->payload_packet_len
	  && get_u_int16_t(packet->payload, 2) == htons(0x0202)
	  && get_u_int32_t(packet->payload, packet->payload_packet_len - 4) == htonl(0xFFFFFFFF)) {
	NDPI_LOG_INFO(ndpi_struct, "found florensia\n");
	ndpi_florensia_add_connection(ndpi_struct, flow);
	return;
      }
      if (flow->packet_counter < 10 && get_l16(packet->payload, 0) == packet->payload_packet_len) {
	NDPI_LOG_DBG2(ndpi_struct, "maybe florensia\n");
	return;
      }
    }
  }

  if (packet->udp != NULL) {
    if (flow->florensia_stage == 0 && packet->payload_packet_len == 6
	&& get_u_int16_t(packet->payload, 0) == ntohs(0x0503) && get_u_int32_t(packet->payload, 2) == htonl(0xFFFF0000)) {
      NDPI_LOG_DBG2(ndpi_struct, "maybe florensia -> stage is set to 1\n");
      flow->florensia_stage = 1;
      return;
    }
    if (flow->florensia_stage == 1 && packet->payload_packet_len == 8
	&& get_u_int16_t(packet->payload, 0) == ntohs(0x0500) && get_u_int16_t(packet->payload, 4) == htons(0x4191)) {
      NDPI_LOG_INFO(ndpi_struct, "found florensia\n");
      ndpi_florensia_add_connection(ndpi_struct, flow);
      return;
    }
  }

  NDPI_EXCLUDE_PROTO(ndpi_struct, flow);
}


void init_florensia_dissector(struct ndpi_detection_module_struct *ndpi_struct, u_int32_t *id, NDPI_PROTOCOL_BITMASK *detection_bitmask)
{
  ndpi_set_bitmask_protocol_detection("Florensia", ndpi_struct, detection_bitmask, *id,
				      NDPI_PROTOCOL_FLORENSIA,
				      ndpi_search_florensia,
				      NDPI_SELECTION_BITMASK_PROTOCOL_V4_V6_TCP_OR_UDP_WITH_PAYLOAD_WITHOUT_RETRANSMISSION,
				      SAVE_DETECTION_BITMASK_AS_UNKNOWN,
				      ADD_TO_DETECTION_BITMASK);

  *id += 1;
}

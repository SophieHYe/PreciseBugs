/* Copyright 2017 The Chromium OS Authors. All rights reserved.
 * Use of this source code is governed by a BSD-style license that can be
 * found in the LICENSE file.
 */

#include <assert.h>
#include <fuzzer/FuzzedDataProvider.h>
#include <stddef.h>
#include <stdint.h>

extern "C" {
#include "cras_apm_list.h"
#include "cras_bt_log.h"
#include "cras_dsp.h"
#include "cras_iodev_list.h"
#include "cras_mix.h"
#include "cras_observer.h"
#include "cras_rclient.h"
#include "cras_shm.h"
#include "cras_system_state.h"
}

extern "C" int LLVMFuzzerTestOneInput(const uint8_t* data, size_t size) {
  cras_rclient* client = cras_rclient_create(0, 0, CRAS_CONTROL);
  if (size < 300) {
    /* Feeds input data directly if the given bytes is too short. */
    cras_rclient_buffer_from_client(client, data, size, NULL, 0);
  } else {
    FuzzedDataProvider data_provider(data, size);
    int fds[1] = {0};
    int num_fds = data_provider.ConsumeIntegralInRange(0, 1);
    std::vector<uint8_t> msg = data_provider.ConsumeRemainingBytes<uint8_t>();
    cras_rclient_buffer_from_client(client, msg.data(), msg.size(), fds,
                                    num_fds);
  }
  cras_rclient_destroy(client);

  return 0;
}

extern "C" int LLVMFuzzerInitialize(int* argc, char*** argv) {
  char* shm_name;
  if (asprintf(&shm_name, "/cras-%d", getpid()) < 0)
    exit(-ENOMEM);
  struct cras_server_state* exp_state =
      (struct cras_server_state*)calloc(1, sizeof(*exp_state));
  if (!exp_state)
    exit(-1);
  int rw_shm_fd = open("/dev/null", O_RDWR);
  int ro_shm_fd = open("/dev/null", O_RDONLY);
  cras_system_state_init("/tmp", shm_name, rw_shm_fd, ro_shm_fd, exp_state,
                         sizeof(*exp_state));
  free(shm_name);

  cras_observer_server_init();
  cras_mix_init(0);
  cras_apm_list_init("/etc/cras");
  cras_iodev_list_init();
  /* For cros fuzz, emerge adhd with USE=fuzzer will copy dsp.ini.sample to
   * etc/cras. For OSS-Fuzz the Dockerfile will be responsible for copying the
   * file. This shouldn't crash CRAS even if the dsp file does not exist. */
  cras_dsp_init("/etc/cras/dsp.ini.sample");
  /* Initializes btlog for CRAS_SERVER_DUMP_BT path with CRAS_DBUS defined. */
  btlog = cras_bt_event_log_init();
  return 0;
}

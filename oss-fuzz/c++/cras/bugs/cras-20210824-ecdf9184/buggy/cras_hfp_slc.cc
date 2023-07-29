/* Copyright 2020 The Chromium OS Authors. All rights reserved.
 * Use of this source code is governed by a BSD-style license that can be
 * found in the LICENSE file.
 */

#include <assert.h>
#include <fuzzer/FuzzedDataProvider.h>
#include <stddef.h>
#include <stdint.h>

extern "C" {
#include "cras_bt_device.h"
#include "cras_bt_log.h"
#include "cras_hfp_slc.h"
#include "cras_iodev_list.h"
#include "cras_mix.h"
#include "cras_observer.h"
#include "cras_shm.h"
#include "cras_system_state.h"

struct cras_bt_event_log* btlog;
}

int disconnect_cb(struct hfp_slc_handle*) {
  return 0;
}

extern "C" int LLVMFuzzerTestOneInput(const uint8_t* data, size_t size) {
  FuzzedDataProvider data_provider(data, size);
  int ag_supported_features = data_provider.ConsumeIntegral<int>();
  std::string command = data_provider.ConsumeRemainingBytesAsString();
  int fd = open("/dev/null", O_RDWR);

  struct cras_bt_device* bt_dev = cras_bt_device_create(NULL, "");
  struct hfp_slc_handle* handle =
      hfp_slc_create(fd, ag_supported_features, bt_dev, NULL, &disconnect_cb);
  if (!handle)
    return 0;

  handle_at_command_for_test(handle, command.c_str());

  hfp_slc_destroy(handle);
  cras_bt_device_remove(bt_dev);
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
  cras_iodev_list_init();
  btlog = cras_bt_event_log_init();
  return 0;
}

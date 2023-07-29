# Copyright 2013 The Chromium OS Authors. All rights reserved.
# Use of this source code is governed by a BSD-style license that can be
# found in the LICENSE file.

AUTOMAKE_OPTIONS = subdir-objects
ACLOCAL_AMFLAGS = ${ACLOCAL_FLAGS}

if HAVE_SSE42
CRAS_SSE4_2 = libcrasmix_sse42.la
else
CRAS_SSE4_2 =
endif

if HAVE_AVX
CRAS_AVX = libcrasmix_avx.la
else
CRAS_AVX =
endif

if HAVE_AVX2
CRAS_AVX2 = libcrasmix_avx2.la
else
CRAS_AVX2 =
endif

if HAVE_FMA
CRAS_FMA = libcrasmix_fma.la
else
CRAS_FMA =
endif

if HAVE_WEBRTC_APM
CRAS_WEBRTC_APM_SOURCES = \
	server/cras_apm_list.c
else
CRAS_WEBRTC_APM_SOURCES =
endif

CRAS_UT_TMPDIR_CFLAGS=-DCRAS_UT_TMPDIR=\"/tmp\"
COMMON_CPPFLAGS = -O2 -Wall -Werror -Wno-error=cpp
COMMON_CPPFLAGS += -ffunction-sections -fdata-sections
COMMON_SIMD_CPPFLAGS = -O3 -Wall -Werror -Wno-error=cpp

bin_PROGRAMS = cras cras_test_client cras_monitor cras_router
noinst_PROGRAMS =

BUILT_SOURCES =

if HAVE_DBUS
DBUS_XML_FILES = \
	$(top_srcdir)/dbus_bindings/org.chromium.cras.Control.xml \
	$(top_srcdir)/dbus_bindings/org.chromium.cras.Telephony.xml
BUILT_SOURCES += common/cras_dbus_bindings.h

common/cras_dbus_bindings.h: Makefile ${DBUS_XML_FILES}
	cp ${DBUS_XML_FILES} .
	echo "#ifndef CRAS_DBUS_BINDINGS_H_" >> $@.tmp
	echo "#define CRAS_DBUS_BINDINGS_H_" >> $@.tmp
	for xml in ${DBUS_XML_FILES}; do \
		local_xml=$$(basename $$xml); \
		xxd -i $$local_xml >> $@.tmp; \
		rm $$local_xml; \
	done
	sed -i 's/unsigned char/static const char/' $@.tmp
	sed -i 's/unsigned int/__attribute__((unused)) static unsigned int/' $@.tmp
	sed -i 's/}/,0x00\}/' $@.tmp
	echo "#endif /* CRAS_DBUS_BINDINGS_H_ */" >> $@.tmp
	mv $@{.tmp,}
DBUS_CFLAGS += -I$(top_builddir)/src/common

CRAS_DBUS_SOURCES = \
	common/cras_sbc_codec.c \
	common/packet_status_logger.c \
	server/cras_bt_manager.c \
	server/cras_bt_adapter.c \
	server/cras_bt_device.c \
	server/cras_bt_transport.c \
	server/cras_bt_endpoint.c \
	server/cras_bt_player.c \
	server/cras_bt_io.c \
	server/cras_bt_profile.c \
	server/cras_bt_battery_provider.c \
	server/cras_dbus.c \
	server/cras_dbus_util.c \
	server/cras_dbus_control.c \
	server/cras_fl_manager.c \
	server/cras_fl_media.c \
	server/cras_hfp_ag_profile.c \
	server/cras_hfp_iodev.c \
	server/cras_hfp_alsa_iodev.c \
	server/cras_hfp_slc.c \
	server/cras_a2dp_endpoint.c \
	server/cras_a2dp_info.c \
	server/cras_a2dp_iodev.c \
	server/cras_sco.c \
	server/cras_telephony.c \
	server/cras_utf8.c
else
CRAS_DBUS_SOURCES =
endif

if HAVE_SELINUX
CRAS_SELINUX_SOURCES = common/cras_selinux_helper.c
CRAS_SELINUX_UNITTEST_SOURCES = tests/cras_selinux_helper_unittest.c
else
CRAS_SELINUX_SOURCES =
CRAS_SELINUX_UNITTEST_SOURCES =
endif

cras_server_SOURCES = \
	$(CRAS_DBUS_SOURCES) \
	$(CRAS_SELINUX_SOURCES) \
	$(CRAS_WEBRTC_APM_SOURCES) \
	common/cras_audio_format.c \
	common/cras_checksum.c \
	common/cras_config.c \
	common/cras_metrics.c \
	common/cras_shm.c \
	common/cras_util.c \
	common/dumper.c \
	common/edid_utils.c \
	common/sfh.c \
	dsp/biquad.c \
	dsp/crossover.c \
	dsp/crossover2.c \
	dsp/dcblock.c \
	dsp/drc.c \
	dsp/drc_kernel.c \
	dsp/drc_math.c \
	dsp/dsp_util.c \
	dsp/eq.c \
	dsp/eq2.c \
	dsp/quad_rotation.c \
	plc/cras_plc.c\
	server/audio_thread.c \
	server/buffer_share.c \
	server/config/cras_board_config.c \
	server/config/cras_card_config.c \
	server/config/cras_device_blocklist.c \
	server/cras_a2dp_manager.c \
	server/cras_a2dp_pcm_iodev.c \
	server/cras_alert.c \
	server/cras_alsa_card.c \
	server/cras_alsa_helpers.c \
	server/cras_alsa_io.c \
	server/cras_alsa_jack.c \
	server/cras_alsa_mixer.c \
	server/cras_alsa_mixer_name.c \
	server/cras_alsa_plugin_io.c \
	server/cras_alsa_ucm.c \
	server/cras_alsa_ucm_section.c \
	server/cras_audio_area.c \
	server/cras_audio_thread_monitor.c \
	server/cras_device_monitor.c \
	server/cras_dsp.c \
	server/cras_dsp_ini.c \
	server/cras_dsp_mod_builtin.c \
	server/cras_dsp_mod_ladspa.c \
	server/cras_dsp_pipeline.c \
	server/cras_empty_iodev.c \
	server/cras_expr.c \
	server/cras_fmt_conv.c \
	server/cras_fmt_conv_ops.c \
	server/cras_gpio_jack.c \
	server/cras_hotword_handler.c \
	server/cras_iodev.c \
	server/cras_iodev_list.c \
	server/cras_loopback_iodev.c \
	server/cras_main_message.c \
	server/cras_mix.c \
	server/cras_non_empty_audio_handler.c \
	server/cras_observer.c \
	server/cras_ramp.c \
	server/cras_rclient.c \
	server/cras_rclient_util.c \
	server/cras_control_rclient.c \
	server/cras_playback_rclient.c \
	server/cras_capture_rclient.c \
	server/cras_unified_rclient.c \
	server/cras_rstream.c \
	server/cras_rstream_config.c \
	server/cras_server_metrics.c \
	server/cras_system_state.c \
	server/cras_tm.c \
	server/cras_udev.c \
	server/cras_volume_curve.c \
	server/dev_io.c \
	server/dev_stream.c \
	server/ewma_power.c \
	server/input_data.c \
	server/linear_resampler.c \
	server/polled_interval_checker.c \
	server/server_stream.c \
	server/stream_list.c \
	server/test_iodev.c \
	server/softvol_curve.c \
	server/cras_rtc.c

SERVER_RUST_SRCDIR = $(top_srcdir)/src/server/rust

libcrasserver_la_SOURCES = \
	$(cras_server_SOURCES)
libcrasserver_la_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/dsp -I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/server/config -I$(top_srcdir)/src/plc \
	-I$(SERVER_RUST_SRCDIR)/src/headers \
	$(DBUS_CFLAGS) $(SBC_CFLAGS) $(SELINUX_CFLAGS)
libcrasserver_la_LIBADD = \
	$(CRAS_RUST) \
	libcrasmix.la \
	$(CRAS_SSE4_2) \
	$(CRAS_AVX) \
	$(CRAS_AVX2) \
	$(CRAS_FMA) \
	-lpthread -lasound -lrt -liniparser -ludev -ldl -lm -lspeexdsp \
	$(SBC_LIBS) \
	$(DBUS_LIBS) \
	$(SELINUX_LIBS)

cras_SOURCES = \
	server/cras.c \
	server/cras_server.c

cras_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/dsp -I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/server/config -I$(top_srcdir)/src/plc \
	$(DBUS_CFLAGS) $(SBC_CFLAGS)

cras_LDADD = \
	libcrasmix.la \
	libcrasserver.la \
	$(CRAS_SSE4_2) \
	$(CRAS_AVX) \
	$(CRAS_AVX2) \
	$(CRAS_FMA) \
	$(CRAS_RUST) \
	-lpthread -lasound -lrt -liniparser -ludev -ldl -lm -lspeexdsp \
	$(METRICS_LIBS) \
	$(SBC_LIBS) \
	$(DBUS_LIBS) \
	$(WEBRTC_APM_LIBS)

noinst_LTLIBRARIES = \
	$(CRAS_SSE4_2) \
	$(CRAS_AVX) \
	$(CRAS_AVX2) \
	$(CRAS_FMA) \
	libcrasmix.la \
	libcrasserver.la

libcrasmix_la_SOURCES = \
	server/cras_mix_ops.c

libcrasmix_la_CFLAGS = \
	$(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/dsp -I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/server/config -I$(top_srcdir)/src/plc \
	$(DBUS_CFLAGS) $(SBC_CFLAGS)

libcrasmix_sse42_la_SOURCES = \
	server/cras_mix_ops.c

libcrasmix_sse42_la_CFLAGS = \
	$(COMMON_SIMD_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/dsp -I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/server/config -I$(top_srcdir)/src/plc \
	$(DBUS_CFLAGS) $(SSE42_CFLAGS)

libcrasmix_avx_la_SOURCES = \
	server/cras_mix_ops.c

libcrasmix_avx_la_CFLAGS = \
	$(COMMON_SIMD_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/dsp -I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/server/config -I$(top_srcdir)/src/plc \
	$(DBUS_CFLAGS) $(AVX_CFLAGS)

libcrasmix_avx2_la_SOURCES = \
	server/cras_mix_ops.c

libcrasmix_avx2_la_CFLAGS = \
	$(COMMON_SIMD_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/dsp -I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/server/config -I$(top_srcdir)/src/plc \
	$(DBUS_CFLAGS) $(AVX2_CFLAGS)

libcrasmix_fma_la_SOURCES = \
	server/cras_mix_ops.c

libcrasmix_fma_la_CFLAGS = \
	$(COMMON_SIMD_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/dsp -I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/server/config -I$(top_srcdir)/src/plc \
	$(DBUS_CFLAGS) $(FMA_CFLAGS)

lib_LTLIBRARIES = libcras.la
libcras_la_SOURCES = \
	common/cras_audio_format.c \
	common/cras_config.c \
	common/cras_file_wait.c \
	common/cras_shm.c \
	common/cras_util.c \
	common/edid_utils.c \
	libcras/cras_client.c \
	libcras/cras_helpers.c

include_HEADERS = \
	common/cras_audio_format.h \
	common/cras_config.h \
	common/cras_iodev_info.h \
	common/cras_messages.h \
	common/cras_shm.h \
	common/cras_types.h \
	common/cras_util.h \
	common/edid_utils.h \
	common/packet_status_logger.h \
	common/utlist.h \
	libcras/cras_client.h \
	libcras/cras_helpers.h

libcras_la_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/libcras
libcras_la_LIBADD = -lpthread -lasound -lrt
libcras_la_LDFLAGS = -version-info 0:0:0

asound_module_pcm_cras_LTLIBRARIES = libasound_module_pcm_cras.la
asound_module_ctl_cras_LTLIBRARIES = libasound_module_ctl_cras.la
asound_module_pcm_crasdir = @ALSA_PLUGIN_DIR@
asound_module_ctl_crasdir = @ALSA_PLUGIN_DIR@
libasound_module_pcm_cras_la_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/libcras
libasound_module_pcm_cras_la_LDFLAGS = -module -avoid-version -export-dynamic -no-undefined \
	$(LDFLAGS_NOUNDEFINED)
libasound_module_ctl_cras_la_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/libcras
libasound_module_ctl_cras_la_LDFLAGS = -module -avoid-version -export-dynamic -no-undefined \
	$(LDFLAGS_NOUNDEFINED)
libasound_module_pcm_cras_la_SOURCES = alsa_plugin/pcm_cras.c
libasound_module_pcm_cras_la_LIBADD = -lasound libcras.la
libasound_module_ctl_cras_la_SOURCES = alsa_plugin/ctl_cras.c
libasound_module_ctl_cras_la_LIBADD = -lasound libcras.la

if !WITH_SYSTEM_RUST
RUST_FILES = \
	$(SERVER_RUST_SRCDIR)/Cargo.toml \
	$(SERVER_RUST_SRCDIR)/src/rate_estimator_bindings.rs \
	$(SERVER_RUST_SRCDIR)/src/rate_estimator.rs

CRAS_RUST_TARGET_DIR = $(top_builddir)/src/server/rust/target
CRAS_RUST = $(CRAS_RUST_TARGET_DIR)/release/libcras_rust.a
$(CRAS_RUST): $(RUST_FILES)
	cargo build --release \
		--manifest-path $(SERVER_RUST_SRCDIR)/Cargo.toml \
		--target-dir $(CRAS_RUST_TARGET_DIR)

clean-local:
	cargo clean --release \
		--manifest-path $(SERVER_RUST_SRCDIR)/Cargo.toml \
		--target-dir $(CRAS_RUST_TARGET_DIR)

else
CRAS_RUST = -lcras_rust
endif

# Inject a dependency between the installation rules of libcras and its modules.
# This avoids a race when the modules are relinked before libcras is actually
# installed.
#
# Automake will refuse to generate a rule that collides with a user-specified
# one. The hide_install variable prevents automake from noticing these rules,
# so everything else will behave as usual.
hide_install=install
$(hide_install)-asound_module_pcm_crasLTLIBRARIES: install-libLTLIBRARIES
$(hide_install)-asound_module_ctl_crasLTLIBRARIES: install-libLTLIBRARIES

# ==== Fuzzer section
if HAVE_FUZZER
FUZZERS = \
	cras_rclient_message_fuzzer \
	cras_hfp_slc_fuzzer

noinst_PROGRAMS += $(FUZZERS)

FUZZER_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/dsp -I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/server/config -I$(top_srcdir)/src/plc \
	$(DBUS_CFLAGS) $(SBC_CFLAGS)

FUZZER_LDADD = \
	libcrasmix.la \
	libcrasserver.la \
	$(CRAS_SSE4_2) \
	$(CRAS_AVX) \
	$(CRAS_AVX2) \
	$(CRAS_FMA) \
	$(CRAS_RUST) \
	-lpthread -lasound -lrt -liniparser -ludev -ldl -lm -lspeexdsp \
	$(METRICS_LIBS) \
	$(SBC_LIBS) \
	$(DBUS_LIBS) \
	$(WEBRTC_APM_LIBS)

cras_rclient_message_fuzzer_SOURCES = \
	fuzz/rclient_message.cc

cras_rclient_message_fuzzer_CPPFLAGS = $(FUZZER_CPPFLAGS)
cras_rclient_message_fuzzer_LDFLAGS = $(FUZZER_LDFLAGS)
cras_rclient_message_fuzzer_LDADD = $(FUZZER_LDADD)

cras_hfp_slc_fuzzer_SOURCES = \
	fuzz/cras_hfp_slc.cc

cras_hfp_slc_fuzzer_CPPFLAGS = $(FUZZER_CPPFLAGS)
cras_hfp_slc_fuzzer_LDFLAGS = $(FUZZER_LDFLAGS)
cras_hfp_slc_fuzzer_LDADD = $(FUZZER_LDADD)
endif

# ==== Tests section
if HAVE_DBUS
DBUS_TESTS = \
	a2dp_info_unittest \
	a2dp_iodev_unittest \
	a2dp_manager_unittest \
	alsa_io_unittest \
	bt_device_unittest \
	bt_io_unittest \
	bt_manager_unittest \
	hfp_iodev_unittest \
	hfp_alsa_iodev_unittest \
	hfp_ag_profile_unittest \
	hfp_slc_unittest
else
DBUS_TESTS =
endif

if HAVE_WEBRTC_APM
CRAS_WEBRTC_APM_TESTS = \
	apm_list_unittest
else
CRAS_WEBRTC_APM_TESTS =
endif

TESTS = \
	$(DBUS_TESTS) \
	$(CRAS_WEBRTC_APM_TESTS) \
	a2dp_pcm_iodev_unittest \
	audio_area_unittest \
	audio_format_unittest \
	audio_thread_unittest \
	audio_thread_monitor_unittest \
	alert_unittest \
	alsa_card_unittest \
	alsa_helpers_unittest \
	alsa_jack_unittest \
	alsa_mixer_unittest \
	alsa_ucm_unittest \
	array_unittest \
	biquad_unittest \
	byte_buffer_unittest \
	card_config_unittest \
	checksum_unittest \
	cras_abi_unittest \
	cras_client_unittest \
	cras_rtc_unittest \
	cras_tm_unittest \
	device_monitor_unittest \
	dev_io_unittest \
	dev_stream_unittest \
	device_blocklist_unittest \
	dsp_core_unittest \
	dsp_ini_unittest \
	dsp_pipeline_unittest \
	dsp_unittest \
	dumper_unittest \
	edid_utils_unittest \
	empty_iodev_unittest \
	expr_unittest \
	ewma_power_unittest \
	file_wait_unittest \
	float_buffer_unittest \
	fmt_conv_unittest \
	fmt_conv_ops_unittest \
	cras_sco_unittest \
	buffer_share_unittest \
	input_data_unittest \
	iodev_list_unittest \
	iodev_unittest \
	loopback_iodev_unittest \
	mix_unittest \
	linear_resampler_unittest \
	observer_unittest \
	polled_interval_checker_unittest \
	ramp_unittest \
	rate_estimator_unittest \
	control_rclient_unittest \
	playback_rclient_unittest \
	capture_rclient_unittest \
	rstream_unittest \
	shm_unittest \
	server_metrics_unittest \
	softvol_curve_unittest \
	stream_list_unittest \
	system_state_unittest \
	timing_unittest \
	utf8_unittest \
	util_unittest \
	volume_curve_unittest

check_PROGRAMS = $(TESTS)

cras_test_client_SOURCES = tools/cras_test_client/cras_test_client.c
cras_test_client_LDADD = -lm libcras.la
cras_test_client_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/libcras \
	-I$(top_srcdir)/src/common -I$(top_builddir)/src/common

tools/cras_test_client/cras_test_client.c: common/cras_version.h

cras_monitor_SOURCES = tools/cras_monitor/cras_monitor.c
cras_monitor_LDADD = -lm libcras.la
cras_monitor_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/libcras \
	-I$(top_srcdir)/src/common -I$(top_builddir)/src/common

tools/cras_monitor/cras_monitor.c: common/cras_version.h

cras_router_SOURCES = tools/cras_router/cras_router.c
cras_router_LDADD = -lm libcras.la
cras_router_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/libcras \
        -I$(top_srcdir)/src/common -I$(top_srcdir)/src/dsp \
	-I$(top_srcdir)/src/server -I$(top_builddir)/src/common

tools/cras_router/cras_router.c: common/cras_version.h

CLEANFILES = common/cras_version.h $(BUILT_SOURCES)
.PHONY: common/cras_version.h
common/cras_version.h:
	if echo "$(CPPFLAGS)" | grep -q -- '-DVCSID='; then \
		echo > $@.tmp; \
	else \
		commit="outoftree-$$(git rev-parse HEAD)"; \
		if [ -n "$$(git diff-index --name-only HEAD)" ]; then \
			commit="$${commit}-dirty"; \
		fi; \
		echo "#define VCSID \"$$commit\"" > $@.tmp; \
	fi
	diff $@.tmp $@ && rm -f $@.tmp || mv $@.tmp $@

# dsp test programs (not run automatically)
check_PROGRAMS += \
	crossover_test \
	crossover2_test \
	dcblock_test \
	drc_test \
	dsp_util_test \
	eq_test \
	eq2_test \
	cmpraw

DSP_INCLUDE_PATHS = -I$(top_srcdir)/src/dsp -I$(top_srcdir)/src/common

crossover_test_SOURCES = dsp/crossover.c dsp/biquad.c dsp/dsp_util.c \
	dsp/tests/crossover_test.c dsp/tests/dsp_test_util.c dsp/tests/raw.c
crossover_test_LDADD = -lrt -lm
crossover_test_CPPFLAGS = $(COMMON_CPPFLAGS) $(DSP_INCLUDE_PATHS)

crossover2_test_SOURCES = dsp/crossover2.c dsp/biquad.c dsp/dsp_util.c \
	dsp/tests/crossover2_test.c dsp/tests/dsp_test_util.c dsp/tests/raw.c
crossover2_test_LDADD = -lrt -lm
crossover2_test_CPPFLAGS = $(COMMON_CPPFLAGS) $(DSP_INCLUDE_PATHS)

dcblock_test_SOURCES = dsp/dcblock.c dsp/dsp_util.c dsp/tests/dcblock_test.c \
	dsp/tests/dsp_test_util.c dsp/tests/raw.c
dcblock_test_LDADD = -lrt -lm
dcblock_test_CPPFLAGS = $(COMMON_CPPFLAGS) $(DSP_INCLUDE_PATHS)

drc_test_SOURCES = dsp/drc.c dsp/drc_kernel.c dsp/drc_math.c \
	dsp/crossover2.c dsp/eq2.c dsp/biquad.c dsp/dsp_util.c \
	dsp/tests/drc_test.c dsp/tests/dsp_test_util.c dsp/tests/raw.c
drc_test_LDADD = -lrt -lm
drc_test_CPPFLAGS = $(COMMON_CPPFLAGS) $(DSP_INCLUDE_PATHS)

dsp_util_test_SOURCES = dsp/tests/dsp_util_test.c dsp/dsp_util.c
dsp_util_test_LDADD = -lm
dsp_util_test_CPPFLAGS = $(COMMON_CPPFLAGS) $(DSP_INCLUDE_PATHS) -Wno-error=strict-aliasing

eq_test_SOURCES = dsp/biquad.c dsp/eq.c dsp/dsp_util.c dsp/tests/eq_test.c \
	dsp/tests/dsp_test_util.c dsp/tests/raw.c
eq_test_LDADD = -lrt -lm
eq_test_CPPFLAGS = $(COMMON_CPPFLAGS) $(DSP_INCLUDE_PATHS)

eq2_test_SOURCES = dsp/biquad.c dsp/eq2.c dsp/dsp_util.c dsp/tests/eq2_test.c \
	dsp/tests/dsp_test_util.c dsp/tests/raw.c
eq2_test_LDADD = -lrt -lm
eq2_test_CPPFLAGS = $(COMMON_CPPFLAGS) $(DSP_INCLUDE_PATHS)

cmpraw_SOURCES = dsp/tests/cmpraw.c dsp/tests/raw.c
cmpraw_LDADD = -lm
cmpraw_CPPFLAGS = $(COMMON_CPPFLAGS) $(DSP_INCLUDE_PATHS)

cras_plc_test_SOURCES = plc/cras_plc_test.c plc/cras_plc.c \
	common/cras_sbc_codec.c
cras_plc_test_LDADD = -lrt -lm $(SBC_LIBS)
cras_plc_test_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/plc \
	-I$(top_srcdir)/src/common \
	$(SBC_CFLAGS)
check_PROGRAMS += cras_plc_test

# unit tests
alert_unittest_SOURCES = tests/alert_unittest.cc \
	server/cras_alert.c
alert_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server
alert_unittest_LDADD = -lgtest -lpthread

alsa_card_unittest_SOURCES = tests/alsa_card_unittest.cc \
	server/cras_alsa_card.c server/cras_alsa_mixer_name.c \
	server/cras_alsa_ucm_section.c
alsa_card_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/server/config
alsa_card_unittest_LDADD = -lgtest -lpthread

alsa_helpers_unittest_SOURCES = tests/alsa_helpers_unittest.cc \
	common/cras_audio_format.c
alsa_helpers_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server
alsa_helpers_unittest_LDADD = -lgtest -lpthread

audio_area_unittest_SOURCES = tests/audio_area_unittest.cc \
	server/cras_audio_area.c
audio_area_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/common
audio_area_unittest_LDADD = -lgtest -lpthread

audio_format_unittest_SOURCES = tests/audio_format_unittest.cc
audio_format_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/common
audio_format_unittest_LDADD = -lgtest -lpthread

if HAVE_DBUS
a2dp_info_unittest_SOURCES =  \
	server/cras_a2dp_info.c \
	tests/a2dp_info_unittest.cc \
	tests/sbc_codec_stub.cc
a2dp_info_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/common
a2dp_info_unittest_LDADD = -lgtest -lpthread

a2dp_iodev_unittest_SOURCES = tests/a2dp_iodev_unittest.cc
a2dp_iodev_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/common $(DBUS_CFLAGS)
a2dp_iodev_unittest_LDADD = -lgtest -lpthread $(DBUS_LIBS)

a2dp_manager_unittest_SOURCES = tests/a2dp_manager_unittest.cc \
	server/cras_a2dp_manager.c
a2dp_manager_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/server -I$(top_srcdir)/src/common $(DBUS_CFLAGS)
a2dp_manager_unittest_LDADD = -lgtest -lpthread $(DBUS_LIBS)

endif

a2dp_pcm_iodev_unittest_SOURCES = tests/a2dp_pcm_iodev_unittest.cc \
	common/sfh.c
a2dp_pcm_iodev_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/server -I$(top_srcdir)/src/common
a2dp_pcm_iodev_unittest_LDADD = -lgtest -lpthread

alsa_io_unittest_SOURCES = tests/alsa_io_unittest.cc server/softvol_curve.c \
	common/sfh.c \
	server/cras_alsa_ucm_section.c \
	server/cras_alsa_mixer_name.c
alsa_io_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) $(DBUS_CFLAGS) \
	-I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server/config
alsa_io_unittest_LDADD = -lgtest -lpthread

alsa_jack_unittest_SOURCES = tests/alsa_jack_unittest.cc \
	server/cras_alsa_jack.c \
	server/cras_alsa_ucm_section.c \
	server/cras_alsa_mixer_name.c
alsa_jack_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server
alsa_jack_unittest_LDADD = -lgtest -lpthread

alsa_mixer_unittest_SOURCES = tests/alsa_mixer_unittest.cc \
	server/cras_alsa_mixer_name.c \
	server/cras_alsa_ucm_section.c
alsa_mixer_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/server/config
alsa_mixer_unittest_LDADD = -lgtest -lpthread

alsa_ucm_unittest_SOURCES = tests/alsa_ucm_unittest.cc \
	server/cras_alsa_mixer_name.c \
	server/cras_alsa_ucm_section.c
alsa_ucm_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/server/config
alsa_ucm_unittest_LDADD = -lgtest -lpthread

if HAVE_WEBRTC_APM
apm_list_unittest_SOURCES = tests/apm_list_unittest.cc \
	server/cras_apm_list.c
apm_list_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	$(DSP_INCLUDE_PATHS) \
	-I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/server/config \
	$(WEBRTC_APM_CFLAGS)
apm_list_unittest_LDADD = -lgtest -liniparser
endif

array_unittest_SOURCES = tests/array_unittest.cc
array_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common
array_unittest_LDADD = -lgtest -lpthread

audio_thread_unittest_SOURCES = tests/audio_thread_unittest.cc \
	server/dev_io.c tests/empty_audio_stub.cc tests/metrics_stub.cc \
	common/cras_shm.c
audio_thread_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common -I$(top_srcdir)/src/server \
	-I$(SERVER_RUST_SRCDIR)/src/headers
audio_thread_unittest_LDADD = -lgtest -lpthread -lrt

audio_thread_monitor_unittest_SOURCES = tests/audio_thread_monitor_unittest.cc
audio_thread_monitor_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common -I$(top_srcdir)/src/server
audio_thread_monitor_unittest_LDADD = -lgtest -lpthread -lrt

if HAVE_DBUS
bt_device_unittest_SOURCES = tests/bt_device_unittest.cc \
	server/cras_bt_device.c \
	tests/metrics_stub.cc common/sfh.c
bt_device_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/common $(DBUS_CFLAGS)
bt_device_unittest_LDADD = -lgtest -lpthread $(DBUS_LIBS)

bt_io_unittest_SOURCES = tests/bt_io_unittest.cc common/sfh.c
bt_io_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/common $(DBUS_CFLAGS)
bt_io_unittest_LDADD = -lgtest -lpthread $(DBUS_LIBS)

bt_manager_unittest_SOURCES = tests/bt_manager_unittest.cc \
	server/cras_bt_manager.c
bt_manager_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/common $(DBUS_CFLAGS)
bt_manager_unittest_LDADD = -lgtest -lpthread $(DBUS_LIBS)
endif

biquad_unittest_SOURCES = tests/biquad_unittest.cc \
	dsp/biquad.c
biquad_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) $(DSP_INCLUDE_PATHS) \
	-I$(top_srcdir)/src/server
biquad_unittest_LDADD = -lgtest -lpthread -lm

byte_buffer_unittest_SOURCES = tests/byte_buffer_unittest.cc
byte_buffer_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common
byte_buffer_unittest_LDADD = -lgtest -lpthread

card_config_unittest_SOURCES = tests/card_config_unittest.cc \
	server/config/cras_card_config.c
card_config_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common -I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/server/config $(CRAS_UT_TMPDIR_CFLAGS)
card_config_unittest_LDADD = -lgtest -liniparser -lpthread

checksum_unittest_SOURCES = tests/checksum_unittest.cc common/cras_checksum.c
checksum_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common
checksum_unittest_LDADD = -lgtest -lpthread

cras_abi_unittest_SOURCES = tests/cras_abi_unittest.cc \
	common/cras_config.c common/cras_shm.c common/cras_util.c \
	common/cras_file_wait.c common/cras_audio_format.c
cras_abi_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/libcras
cras_abi_unittest_LDADD = -lgtest -lpthread -lrt -lspeexdsp

cras_client_unittest_SOURCES = tests/cras_client_unittest.cc \
	common/cras_config.c common/cras_shm.c common/cras_util.c \
	common/cras_file_wait.c
cras_client_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/libcras
cras_client_unittest_LDADD = -lgtest -lpthread -lrt -lspeexdsp

cras_rtc_unittest_SOURCES = tests/cras_rtc_unittest.cc server/cras_rtc.c
cras_rtc_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	 -I$(top_srcdir)/src/server
cras_rtc_unittest_LDADD = -lgtest -lpthread

cras_tm_unittest_SOURCES = tests/cras_tm_unittest.cc server/cras_tm.c
cras_tm_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	 -I$(top_srcdir)/src/server
cras_tm_unittest_LDADD = -lgtest -lpthread

dev_io_unittest_SOURCES = \
	$(CRAS_SELINUX_UNITTEST_SOURCES) \
	common/cras_audio_format.c \
	server/dev_io.c \
	tests/dev_io_stubs.cc \
	tests/iodev_stub.cc \
	tests/empty_audio_stub.cc \
	tests/metrics_stub.cc \
	tests/rstream_stub.cc \
	tests/dev_io_unittest.cc
dev_io_unittest_CXXFLAGS = \
	-std=c++11 -Wno-noexcept-type
dev_io_unittest_CPPFLAGS = \
	$(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/server/config \
	-I$(SERVER_RUST_SRCDIR)/src/headers \
	$(SELINUX_CFLAGS)
dev_io_unittest_LDADD = \
	libcrasmix.la \
	$(CRAS_SSE4_2) \
	$(CRAS_AVX) \
	$(CRAS_AVX2) \
	$(CRAS_FMA) \
	$(SELINUX_LIBS) \
	-lgtest -lrt -lpthread -ldl -lm -lspeexdsp

dev_stream_unittest_SOURCES = tests/dev_stream_unittest.cc \
	server/dev_stream.c common/cras_shm.c tests/dev_io_stubs.cc
dev_stream_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common -I$(top_srcdir)/src/server
dev_stream_unittest_LDADD = -lgtest -liniparser -lpthread -lrt

device_blocklist_unittest_SOURCES = tests/device_blocklist_unittest.cc \
	server/config/cras_device_blocklist.c
device_blocklist_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common -I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/server/config $(CRAS_UT_TMPDIR_CFLAGS)
device_blocklist_unittest_LDADD = -lgtest -liniparser -lpthread

device_monitor_unittest_SOURCES = tests/device_monitor_unittest.cc
device_monitor_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server
device_monitor_unittest_LDADD = -lgtest -lpthread

dsp_core_unittest_SOURCES = tests/dsp_core_unittest.cc dsp/eq.c dsp/eq2.c \
	dsp/biquad.c dsp/dsp_util.c dsp/crossover.c dsp/crossover2.c dsp/drc.c \
	dsp/drc_kernel.c dsp/drc_math.c dsp/quad_rotation.c
dsp_core_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) $(DSP_INCLUDE_PATHS) \
  -I$(top_srcdir)/src/server
dsp_core_unittest_LDADD = -lgtest -lpthread

dsp_ini_unittest_SOURCES = tests/dsp_ini_unittest.cc \
	server/cras_dsp_ini.c server/cras_expr.c common/dumper.c
dsp_ini_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server
dsp_ini_unittest_LDADD = -lgtest -liniparser -lpthread

dsp_pipeline_unittest_SOURCES = tests/cras_dsp_pipeline_unittest.cc \
	server/cras_dsp_ini.c server/cras_expr.c server/cras_dsp_pipeline.c \
	common/dumper.c dsp/dsp_util.c
dsp_pipeline_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/server $(DSP_INCLUDE_PATHS)
dsp_pipeline_unittest_LDADD = -lgtest -lrt -liniparser -lpthread

dsp_unittest_SOURCES = tests/dsp_unittest.cc \
	server/cras_dsp.c server/cras_dsp_ini.c server/cras_dsp_pipeline.c \
	server/cras_expr.c common/dumper.c dsp/dsp_util.c \
	dsp/tests/dsp_test_util.c
dsp_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/server $(DSP_INCLUDE_PATHS)
dsp_unittest_LDADD = -lgtest -lrt -liniparser -lpthread

dumper_unittest_SOURCES = tests/dumper_unittest.cc common/dumper.c
dumper_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common
dumper_unittest_LDADD = -lgtest -lpthread

edid_utils_unittest_SOURCES = tests/edid_utils_unittest.cc common/edid_utils.c
edid_utils_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common
edid_utils_unittest_LDADD = -lgtest -lpthread

empty_iodev_unittest_SOURCES = tests/empty_iodev_unittest.cc \
	server/cras_empty_iodev.c
empty_iodev_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server
empty_iodev_unittest_LDADD = -lgtest -lpthread

expr_unittest_SOURCES = tests/expr_unittest.cc server/cras_expr.c common/dumper.c
expr_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server
expr_unittest_LDADD = -lgtest -lpthread

file_wait_unittest_SOURCES = tests/file_wait_unittest.cc \
	common/cras_file_wait.c common/cras_util.c
file_wait_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	$(CRAS_UT_TMPDIR_CFLAGS)
file_wait_unittest_LDADD = -lgtest -lpthread


float_buffer_unittest_SOURCES = tests/float_buffer_unittest.cc
float_buffer_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server
float_buffer_unittest_LDADD = -lgtest -lpthread

fmt_conv_unittest_SOURCES = tests/fmt_conv_unittest.cc server/cras_fmt_conv.c \
	server/cras_fmt_conv_ops.c
fmt_conv_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	 -I$(top_srcdir)/src/server
fmt_conv_unittest_LDADD = -lasound -lspeexdsp -lgtest -lpthread

fmt_conv_ops_unittest_SOURCES = tests/fmt_conv_ops_unittest.cc \
	server/cras_fmt_conv_ops.c
fmt_conv_ops_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	 -I$(top_srcdir)/src/server
fmt_conv_ops_unittest_LDADD = -lasound -lspeexdsp -lgtest -lpthread

cras_sco_unittest_SOURCES = tests/cras_sco_unittest.cc \
	tests/metrics_stub.cc tests/sbc_codec_stub.cc
cras_sco_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server -I$(top_srcdir)/src/plc
cras_sco_unittest_LDADD = -lgtest -lpthread

if HAVE_DBUS
hfp_iodev_unittest_SOURCES = tests/hfp_iodev_unittest.cc \
	server/cras_hfp_iodev.c
hfp_iodev_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server $(DBUS_CFLAGS)
hfp_iodev_unittest_LDADD = -lgtest -lpthread $(DBUS_LIBS)

hfp_alsa_iodev_unittest_SOURCES = tests/hfp_alsa_iodev_unittest.cc \
	server/cras_hfp_alsa_iodev.c
hfp_alsa_iodev_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common -I$(top_srcdir)/src/server $(DBUS_CFLAGS)
hfp_alsa_iodev_unittest_LDADD = -lgtest -lpthread $(DBUS_LIBS)

hfp_ag_profile_unittest_SOURCES = tests/hfp_ag_profile_unittest.cc \
	server/cras_hfp_ag_profile.c common/sfh.c tests/metrics_stub.cc
hfp_ag_profile_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common -I$(top_srcdir)/src/server $(DBUS_CFLAGS)
hfp_ag_profile_unittest_LDADD = -lgtest -lpthread $(DBUS_LIBS)

hfp_slc_unittest_SOURCES = tests/hfp_slc_unittest.cc \
	server/cras_hfp_slc.c tests/metrics_stub.cc
hfp_slc_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server $(DBUS_CFLAGS)
hfp_slc_unittest_LDADD = -lgtest -lpthread $(DBUS_LIBS)
endif

buffer_share_unittest_SOURCES = tests/buffer_share_unittest.cc \
	server/buffer_share.c
buffer_share_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common -I$(top_srcdir)/src/server
buffer_share_unittest_LDADD = -lgtest -liniparser -lpthread

ewma_power_unittest_SOURCES = tests/ewma_power_unittest.cc \
	common/cras_audio_format.c server/cras_audio_area.c \
	server/ewma_power.c

ewma_power_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common -I$(top_srcdir)/src/server
ewma_power_unittest_LDADD = -lgtest -lpthread

iodev_list_unittest_SOURCES = tests/iodev_list_unittest.cc \
	server/cras_iodev_list.c
iodev_list_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	 -I$(top_srcdir)/src/server
iodev_list_unittest_LDADD = -lgtest -lpthread

loopback_iodev_unittest_SOURCES = tests/loopback_iodev_unittest.cc \
	server/cras_loopback_iodev.c common/cras_shm.c common/sfh.c
loopback_iodev_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server
loopback_iodev_unittest_LDADD = -lgtest -lpthread -lrt

input_data_unittest_SOURCES = tests/input_data_unittest.cc \
	server/input_data.c
input_data_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	 -I$(top_srcdir)/src/dsp -I$(top_srcdir)/src/server
input_data_unittest_LDADD = -lgtest -lpthread

iodev_unittest_SOURCES = tests/iodev_unittest.cc \
	server/cras_iodev.c common/cras_shm.c
iodev_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server \
	-I$(SERVER_RUST_SRCDIR)/src/headers
iodev_unittest_LDADD = -lgtest -lpthread -lrt

mix_unittest_SOURCES = tests/mix_unittest.cc server/cras_mix.c
mix_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	 -I$(top_srcdir)/src/server
mix_unittest_LDADD = libcrasmix.la \
	$(CRAS_SSE4_2) \
	$(CRAS_AVX) \
	$(CRAS_AVX2) \
	$(CRAS_FMA) \
	-lgtest \
	-lpthread

linear_resampler_unittest_SOURCES = tests/linear_resampler_unittest.cc \
	server/linear_resampler.c server/cras_audio_area.c
linear_resampler_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	 -I$(top_srcdir)/src/server
linear_resampler_unittest_LDADD = -lgtest -lpthread

observer_unittest_SOURCES = tests/observer_unittest.cc
observer_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server
observer_unittest_LDADD = -lgtest -lpthread

polled_interval_checker_unittest_SOURCES = tests/polled_interval_checker_unittest.cc \
    server/polled_interval_checker.c
polled_interval_checker_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server
polled_interval_checker_unittest_LDADD = -lgtest -lpthread

ramp_unittest_SOURCES = tests/ramp_unittest.cc
ramp_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server
ramp_unittest_LDADD = -lgtest -lpthread

rate_estimator_unittest_SOURCES = tests/rate_estimator_unittest.cc
rate_estimator_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	 -I$(top_srcdir)/src/server \
	 -I$(SERVER_RUST_SRCDIR)/src/headers
rate_estimator_unittest_LDADD = $(CRAS_RUST) -lgtest -ldl -lpthread

control_rclient_unittest_SOURCES = tests/control_rclient_unittest.cc \
				   server/cras_rstream_config.c
control_rclient_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server $(CRAS_UT_TMPDIR_CFLAGS) \
	$(DBUS_CFLAGS)
control_rclient_unittest_LDADD = -lgtest -lpthread $(DBUS_LIBS)

playback_rclient_unittest_SOURCES = tests/playback_rclient_unittest.cc \
				    server/cras_rstream_config.c
playback_rclient_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server $(CRAS_UT_TMPDIR_CFLAGS)
playback_rclient_unittest_LDADD = -lgtest -lpthread

capture_rclient_unittest_SOURCES = tests/capture_rclient_unittest.cc \
				   server/cras_rstream_config.c
capture_rclient_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server $(CRAS_UT_TMPDIR_CFLAGS)
capture_rclient_unittest_LDADD = -lgtest -lpthread

rstream_unittest_SOURCES = tests/rstream_unittest.cc server/cras_rstream.c \
	common/cras_shm.c tests/metrics_stub.cc \
	server/cras_rstream_config.c $(CRAS_SELINUX_UNITTEST_SOURCES)
rstream_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	 -I$(top_srcdir)/src/server $(SELINUX_CFLAGS)
rstream_unittest_LDADD = $(SELINUX_LIBS) \
	-lasound -lgtest -lpthread -lrt

server_metrics_unittest_SOURCES = tests/server_metrics_unittest.cc
server_metrics_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server
server_metrics_unittest_LDADD = -lgtest -lpthread

shm_unittest_SOURCES = tests/shm_unittest.cc
shm_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common
shm_unittest_LDADD = -lgtest -lpthread

softvol_curve_unittest_SOURCES = tests/softvol_curve_unittest.cc server/softvol_curve.c
softvol_curve_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	 -I$(top_srcdir)/src/server
softvol_curve_unittest_LDADD = -lgtest -lpthread

stream_list_unittest_SOURCES = tests/stream_list_unittest.cc \
	server/stream_list.c
stream_list_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	 -I$(top_srcdir)/src/server
stream_list_unittest_LDADD = -lgtest -lpthread

system_state_unittest_SOURCES = tests/system_state_unittest.cc \
	server/cras_system_state.c common/cras_shm.c \
       	$(CRAS_SELINUX_UNITTEST_SOURCES)
system_state_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common -I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/server/config $(SELINUX_CFLAGS)
system_state_unittest_LDADD = $(SELINUX_LIBS) \
	-lgtest -liniparser -lpthread -lrt

timing_unittest_SOURCES = \
	$(CRAS_SELINUX_UNITTEST_SOURCES) \
	common/cras_audio_format.c \
	common/cras_shm.c \
	server/cras_audio_area.c \
	server/cras_fmt_conv.c \
	server/cras_fmt_conv_ops.c \
	server/cras_mix.c \
	server/cras_mix_ops.c \
	server/dev_io.c \
	server/dev_stream.c \
	server/linear_resampler.c \
	tests/dev_io_stubs.cc \
	tests/iodev_stub.cc \
	tests/empty_audio_stub.cc \
	tests/metrics_stub.cc \
	tests/rstream_stub.cc \
	tests/timing_unittest.cc
timing_unittest_CXXFLAGS = \
	-std=c++11 -Wno-noexcept-type
timing_unittest_CPPFLAGS = \
	$(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server \
	-I$(top_srcdir)/src/server/config \
	-I$(SERVER_RUST_SRCDIR)/src/headers \
	$(SELINUX_CFLAGS)
timing_unittest_LDADD = \
	libcrasmix.la \
	$(CRAS_SSE4_2) \
	$(CRAS_AVX) \
	$(CRAS_AVX2) \
	$(CRAS_FMA) \
	$(SELINUX_LIBS) \
	-lgtest -lrt -lpthread -ldl -lm -lspeexdsp

utf8_unittest_SOURCES = tests/utf8_unittest.cc server/cras_utf8.c
utf8_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common \
	-I$(top_srcdir)/src/server
utf8_unittest_LDADD = -lgtest -lpthread

util_unittest_SOURCES = tests/util_unittest.cc common/cras_util.c \
	common/cras_config.c
util_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) -I$(top_srcdir)/src/common
util_unittest_LDADD = -lgtest -lpthread

volume_curve_unittest_SOURCES = tests/volume_curve_unittest.cc \
	server/cras_volume_curve.c
volume_curve_unittest_CPPFLAGS = $(COMMON_CPPFLAGS) \
	-I$(top_srcdir)/src/common -I$(top_srcdir)/src/server
volume_curve_unittest_LDADD = -lgtest -lpthread

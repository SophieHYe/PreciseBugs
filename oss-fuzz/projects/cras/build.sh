#!/bin/bash -eux
# Copyright 2017 The Chromium Authors. All rights reserved.
# Use of this source code is governed by a BSD-style license that can be
# found in the LICENSE file.
#
# Copyright 2018 Google Inc.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#      http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#
################################################################################
# Builds fuzzers from within a container into /out/ directory.
# Expects /src/cras to contain a cras checkout.


cd ${SRC}/adhd

#
# Build Rust code.
#
export CARGO_BUILD_TARGET="x86_64-unknown-linux-gnu"
cargo build --package=cras_rust --release --target-dir=${WORK}/cargo_out
cp ${WORK}/cargo_out/${CARGO_BUILD_TARGET}/release/libcras_rust.a /usr/local/lib

#
# Build C code.
#

# Set bazel options.
# See also:
# https://github.com/google/oss-fuzz/blob/master/infra/base-images/base-builder/bazel_build_fuzz_tests
# https://github.com/bazelbuild/rules_fuzzing/blob/master/fuzzing/private/oss_fuzz/repository.bzl
bazel_opts=(
    "--verbose_failures"
    "--curses=no"
    "--spawn_strategy=standalone"
    "--action_env=CC=${CC}"
    "--action_env=CXX=${CXX}"
    "-c" "opt"
    "--cxxopt=-stdlib=libc++"
    "--linkopt=-lc++"
    "--config=fuzzer"
    "--//:system_cras_rust"
)
for f in ${CFLAGS}; do
    bazel_opts+=("--conlyopt=${f}")
done
for f in ${CXXFLAGS}; do
    bazel_opts+=(
        "--cxxopt=${f}"
        "--linkopt=${f}"
    )
done
if [[ "$SANITIZER" == "undefined" ]]; then
    bazel_opts+=("--linkopt=-fsanitize-link-c++-runtime")
fi
if [[ "$SANITIZER" == "coverage" ]]; then
    # Fix up paths.
    bazel_opts+=("--copt=-fcoverage-compilation-dir=${SRC}/adhd")
fi

# Statlic linking hacks
export OSS_FUZZ_STATIC_PKG_CONFIG_DEPS=1
bazel_opts+=("--linkopt=-lsystemd")

# Print inferred @fuzz_engine
bazel cquery  "${bazel_opts[@]}" --output=build @fuzz_engine//:fuzz_engine

bazel run "${bazel_opts[@]}" //dist -- ${WORK}/build

# Preserve historical names
mv ${WORK}/build/fuzzer/cras_rclient_message_fuzzer ${OUT}/rclient_message
mv ${WORK}/build/fuzzer/cras_hfp_slc_fuzzer ${OUT}/cras_hfp_slc

mv ${WORK}/build/fuzzer/* ${OUT}/

zip -j ${OUT}/rclient_message_corpus.zip ${SRC}/adhd/cras/src/fuzz/corpus/*
cp "${SRC}/adhd/cras/src/fuzz/cras_hfp_slc.dict" "${OUT}/cras_hfp_slc.dict"

if [ "$SANITIZER" = "coverage" ]; then
    echo "Collecting the repository source files for coverage tracking."

    ln -s ${SRC}/adhd/cras/src/server/rust/src/* ${SRC}
    declare -r EXTERNAL_SOURCES="${OUT}/src/adhd/external"
    mkdir -p "${EXTERNAL_SOURCES}"
    declare -r RSYNC_FILTER_ARGS=(
        "--include" "*.h"
        "--include" "*.cc"
        "--include" "*.hpp"
        "--include" "*.cpp"
        "--include" "*.c"
        "--include" "*.inc"
        "--include" "*/"
        "--exclude" "*"
    )
    rsync -avLk "${RSYNC_FILTER_ARGS[@]}" \
        "$(bazel info execution_root)/external/" \
        "${EXTERNAL_SOURCES}"
fi

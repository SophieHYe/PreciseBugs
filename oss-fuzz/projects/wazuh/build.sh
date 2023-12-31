#!/bin/bash -eu
# Copyright 2021 Google LLC
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

cd src
export LDFLAGS="$CFLAGS"
sed -i 's/CC=gcc/CC=clang/g' Makefile

make deps
make libwazuh.a
$CC $CFLAGS $LIB_FUZZING_ENGINE $SRC/fuzz_xml.c -o $OUT/fuzz_xml -I./ -I./os_xml \
    ./libwazuh.a ./external/sqlite/libsqlite3.a ./external/cJSON/libcjson.a \
    ./external/zlib/libz.a ./external/bzip2/libbz2.a

#!/bin/bash -eu
# Copyright 2016 Google Inc.
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

# build the library.
pmake -C libteken teken_state.h

export CFLAGS="$CFLAGS -D__unused="
$CC $CFLAGS -c teken.c -o teken.o -I./libteken
ar -q libteken.a ./teken.o
ranlib libteken.a

$CC $CFLAGS -c $SRC/libteken_fuzzer.c -o $SRC/libteken_fuzzer.o -I.
$CXX $CXXFLAGS $SRC/libteken_fuzzer.o \
    -o $OUT/libteken_fuzzer \
    $LIB_FUZZING_ENGINE libteken.a

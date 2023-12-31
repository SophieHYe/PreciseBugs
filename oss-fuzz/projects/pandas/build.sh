#!/bin/bash -eu
# Copyright 2022 Google LLC
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
###############################################################################

if [ $SANITIZER == "coverage" ]; then
    export CFLAGS=""
    export CXXFLAGS=""
fi
PYTHON_CONFIG_STR=$(python3.9-config --cflags --ldflags)
export LDSHARED="${CC} ${PYTHON_CONFIG_STR} -nostartfiles -shared"
python3 setup.py install
python3 -m pip install .
for fuzzer in $(find $SRC -name 'fuzz_*.py'); do
  LD_PRELOAD=$OUT/sanitizer_with_fuzzer.so ASAN_OPTIONS=detect_leaks=0 compile_python_fuzzer $fuzzer --hidden-import cmath
done

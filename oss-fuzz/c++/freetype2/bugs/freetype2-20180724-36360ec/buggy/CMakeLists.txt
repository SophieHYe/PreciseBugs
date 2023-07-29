# CMakeLists.txt
#
# Copyright 2018 by
# Armin Hasitzka.
#
# This file is part of the FreeType project, and may only be used, modified,
# and distributed under the terms of the FreeType project license,
# LICENSE.TXT.  By continuing to use, modify, or distribute this file you
# indicate that you have read the license and understand and accept it
# fully.

cmake_minimum_required(VERSION 3.0)

project(fuzzing)

set(FUZZING_ENGINE "$ENV{CMAKE_FUZZING_ENGINE}")

if("$ENV{CMAKE_USE_LOGGER_GLOG}")
  set(LOGGER_NAME "LOGGER_GLOG")
endif()

set(FREETYPE_BASE_DIR "${CMAKE_CURRENT_SOURCE_DIR}/../external/freetype2")
set(FREETYPE_SRC_DIR        "${FREETYPE_BASE_DIR}/src")
set(FREETYPE_STATIC_LIBRARY "${FREETYPE_BASE_DIR}/objs/.libs/libfreetype.a")

set(LIBARCHIVE_BASE_DIR "${CMAKE_CURRENT_SOURCE_DIR}/../external/libarchive")
set(LIBARCHIVE_SRC_DIR        "${LIBARCHIVE_BASE_DIR}/src")
set(LIBARCHIVE_STATIC_LIBRARY "${LIBARCHIVE_BASE_DIR}/.libs/libarchive.a")

set(FUZZING_BASE_DIR "${CMAKE_CURRENT_SOURCE_DIR}")
set(FUZZING_SRC_DIR  "${FUZZING_BASE_DIR}/src")

set(FUZZING_CORPORA_DIR    "${FUZZING_BASE_DIR}/corpora")
set(FUZZING_CORPORA_SUITES
  "cff"
  "cidtype1"
  "legacy"
  "truetype")

set(CMAKE_ARCHIVE_OUTPUT_DIRECTORY ${CMAKE_BINARY_DIR}/lib)
set(CMAKE_LIBRARY_OUTPUT_DIRECTORY ${CMAKE_BINARY_DIR}/lib)
set(CMAKE_RUNTIME_OUTPUT_DIRECTORY ${CMAKE_BINARY_DIR}/bin)

include_directories(
  "${FREETYPE_BASE_DIR}/include"
  "${FUZZING_SRC_DIR}"
  "${LIBARCHIVE_BASE_DIR}/libarchive")

link_directories("${FREETYPE_BASE_DIR}/objs/.libs")

enable_testing()

add_subdirectory(src)

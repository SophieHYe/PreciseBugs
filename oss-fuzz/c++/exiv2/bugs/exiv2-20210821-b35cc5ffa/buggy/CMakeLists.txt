# CMakeLists.txt for exiv2 library and command-line program

# Note that this is a hack for testing the internals of the library. If EXIV2_BUILD_UNIT_TESTS==OFF
# Then we only export the symbols that are explicitly exported
if( EXIV2_BUILD_UNIT_TESTS )
    set(CMAKE_WINDOWS_EXPORT_ALL_SYMBOLS ON) # Requires CMake 3.3.3
endif()

include(CMakePackageConfigHelpers)

include_directories(${CMAKE_CURRENT_BINARY_DIR})


add_library( exiv2lib_int OBJECT
    canonmn_int.cpp         canonmn_int.hpp
    casiomn_int.cpp         casiomn_int.hpp
    cr2header_int.cpp       cr2header_int.hpp
    crwimage_int.cpp        crwimage_int.hpp
    fujimn_int.cpp          fujimn_int.hpp
    helper_functions.cpp    helper_functions.hpp
    image_int.cpp           image_int.hpp
    makernote_int.cpp       makernote_int.hpp
    minoltamn_int.cpp       minoltamn_int.hpp
    nikonmn_int.cpp         nikonmn_int.hpp
    olympusmn_int.cpp       olympusmn_int.hpp
    orfimage_int.cpp        orfimage_int.hpp
    panasonicmn_int.cpp     panasonicmn_int.hpp
    pentaxmn_int.cpp        pentaxmn_int.hpp
    rw2image_int.cpp        rw2image_int.hpp
    safe_op.hpp
    samsungmn_int.cpp       samsungmn_int.hpp
    sigmamn_int.cpp         sigmamn_int.hpp
    sonymn_int.cpp          sonymn_int.hpp
    tags_int.cpp            tags_int.hpp
    tiffcomposite_int.cpp   tiffcomposite_int.hpp
    tiffimage_int.cpp       tiffimage_int.hpp
    tiffvisitor_int.cpp     tiffvisitor_int.hpp
    tifffwd_int.hpp
    timegm.h
    unused.h
    utils.cpp               utils.hpp
)

set(PUBLIC_HEADERS
    ../include/exiv2/basicio.hpp
    ../include/exiv2/bmffimage.hpp
    ../include/exiv2/bmpimage.hpp
    ../include/exiv2/config.h
    ../include/exiv2/convert.hpp
    ../include/exiv2/cr2image.hpp
    ../include/exiv2/crwimage.hpp
    ../include/exiv2/datasets.hpp
    ../include/exiv2/easyaccess.hpp
    ../include/exiv2/epsimage.hpp
    ../include/exiv2/error.hpp
    ../include/exiv2/exif.hpp
    ../include/exiv2/exiv2.hpp
    ../include/exiv2/futils.hpp
    ../include/exiv2/gifimage.hpp
    ../include/exiv2/http.hpp
    ../include/exiv2/image.hpp
    ../include/exiv2/ini.hpp
    ../include/exiv2/iptc.hpp
    ../include/exiv2/jp2image.hpp
    ../include/exiv2/jpgimage.hpp
    ../include/exiv2/metadatum.hpp
    ../include/exiv2/mrwimage.hpp
    ../include/exiv2/orfimage.hpp
    ../include/exiv2/pgfimage.hpp
    ../include/exiv2/preview.hpp
    ../include/exiv2/properties.hpp
    ../include/exiv2/psdimage.hpp
    ../include/exiv2/rafimage.hpp
    ../include/exiv2/rw2image.hpp
    ../include/exiv2/slice.hpp
    ../include/exiv2/tags.hpp
    ../include/exiv2/tgaimage.hpp
    ../include/exiv2/tiffimage.hpp
    ../include/exiv2/types.hpp
    ../include/exiv2/value.hpp
    ../include/exiv2/version.hpp
    ../include/exiv2/webpimage.hpp
    ../include/exiv2/xmp_exiv2.hpp
    ../include/exiv2/xmpsidecar.hpp
)

add_library( exiv2lib
    basicio.cpp
    bmffimage.cpp
    bmpimage.cpp
    convert.cpp
    cr2image.cpp
    crwimage.cpp
    datasets.cpp
    easyaccess.cpp
    epsimage.cpp
    error.cpp
    exif.cpp
    futils.cpp
    gifimage.cpp
    http.cpp
    image.cpp
    ini.cpp
    iptc.cpp
    jp2image.cpp
    jpgimage.cpp
    metadatum.cpp
    mrwimage.cpp
    orfimage.cpp
    pgfimage.cpp
    preview.cpp
    properties.cpp
    psdimage.cpp
    rafimage.cpp
    rw2image.cpp
    tags.cpp
    tgaimage.cpp
    tiffimage.cpp
    types.cpp
    value.cpp
    version.cpp
    webpimage.cpp
    xmp.cpp
    xmpsidecar.cpp
    ${PUBLIC_HEADERS}
    $<TARGET_OBJECTS:exiv2lib_int>
)

generate_export_header(exiv2lib
    EXPORT_MACRO_NAME EXIV2API
    EXPORT_FILE_NAME ${CMAKE_BINARY_DIR}/exiv2lib_export.h
    STATIC_DEFINE exiv2lib_STATIC
)

# Conditional addition of sources to library targets
# ---------------------------------------------------------

if( EXIV2_ENABLE_PNG )
    set(PUBLIC_HEADERS ${PUBLIC_HEADERS} ../include/exiv2/pngimage.hpp)
    target_sources(exiv2lib_int PRIVATE pngchunk_int.cpp)
    target_sources(exiv2lib PRIVATE pngimage.cpp ../include/exiv2/pngimage.hpp)
endif()


# Other library target properties
# ---------------------------------------------------------

if (${CMAKE_CXX_COMPILER_ID} STREQUAL GNU)
    # Do not check format overflows on this file, to skip a false positive warning
    set_source_files_properties(value.cpp PROPERTIES COMPILE_FLAGS -Wno-format-overflow)
endif()

set_target_properties( exiv2lib PROPERTIES
    VERSION       ${PROJECT_VERSION}
    SOVERSION     ${PROJECT_VERSION_MINOR}
    OUTPUT_NAME   exiv2
    PDB_OUTPUT_DIRECTORY ${CMAKE_CURRENT_BINARY_DIR}
    COMPILE_FLAGS ${EXTRA_COMPILE_FLAGS}
)

if (MSVC)
    set_target_properties(exiv2lib PROPERTIES LINK_FLAGS "/ignore:4099")
endif()

set_target_properties( exiv2lib_int PROPERTIES
    POSITION_INDEPENDENT_CODE ON
    COMPILE_DEFINITIONS exiv2lib_EXPORTS
)

# NOTE: Cannot use target_link_libraries on OBJECT libraries with old versions of CMake
target_include_directories(exiv2lib_int PRIVATE ${ZLIB_INCLUDE_DIR})
target_include_directories(exiv2lib SYSTEM PRIVATE 
    $<BUILD_INTERFACE:${PROJECT_SOURCE_DIR}/xmpsdk/include>
)

if (EXIV2_ENABLE_XMP)
    target_link_libraries(exiv2lib PRIVATE exiv2-xmp)
elseif(EXIV2_ENABLE_EXTERNAL_XMP)
    target_link_libraries(exiv2lib PUBLIC ${XMPSDK_LIBRARY})
    target_include_directories(exiv2lib PUBLIC ${XMPSDK_INCLUDE_DIR})
    target_compile_definitions(exiv2lib PUBLIC EXV_ADOBE_XMPSDK=2016)
endif()

# Use include/exiv2 to build libary code
target_include_directories(exiv2lib PUBLIC
    $<BUILD_INTERFACE:${PROJECT_SOURCE_DIR}/include/exiv2>
    $<BUILD_INTERFACE:${PROJECT_SOURCE_DIR}/include>
    $<INSTALL_INTERFACE:${CMAKE_INSTALL_INCLUDEDIR}>
)

target_include_directories(exiv2lib_int PUBLIC
    $<BUILD_INTERFACE:${PROJECT_SOURCE_DIR}/include/exiv2>
)

if (EXIV2_ENABLE_WEBREADY)

    if( EXIV2_ENABLE_CURL )
        target_include_directories(exiv2lib SYSTEM PRIVATE ${CURL_INCLUDE_DIR} )
        target_link_libraries(exiv2lib PRIVATE ${CURL_LIBRARIES})
    endif()

endif()

if (WIN32)
    target_compile_definitions(exiv2lib PRIVATE PSAPI_VERSION=1)    # to be compatible with <= WinVista (#905)
    # Since windows.h is included in some headers, we need to propagate this definition
    target_compile_definitions(exiv2lib PUBLIC WIN32_LEAN_AND_MEAN) 
endif()

if (NOT MSVC)
    if ( CMAKE_SYSTEM_NAME STREQUAL "FreeBSD" )
        target_link_libraries( exiv2lib PRIVATE -lprocstat)
    endif()

    if (CYGWIN OR MINGW OR MSYS)
        target_link_libraries( exiv2lib PRIVATE psapi ws2_32 shell32 )
    endif()

else()
    target_link_libraries( exiv2lib PRIVATE psapi ws2_32 shell32 )
endif()

if( EXIV2_ENABLE_PNG )
	target_link_libraries( exiv2lib PRIVATE ZLIB::ZLIB)
endif()

if( EXIV2_ENABLE_NLS )
    target_link_libraries(exiv2lib PRIVATE ${Intl_LIBRARIES})
    target_include_directories(exiv2lib PRIVATE ${Intl_INCLUDE_DIRS})
    target_include_directories(exiv2lib_int PRIVATE ${Intl_INCLUDE_DIRS})
    # Definition needed for translations
    join_paths(EXV_LOCALEDIR ".." "${CMAKE_INSTALL_LOCALEDIR}")
    target_compile_definitions(exiv2lib PUBLIC EXV_LOCALEDIR="${EXV_LOCALEDIR}")
endif()

if( ICONV_FOUND )
    target_link_libraries( exiv2lib PRIVATE Iconv::Iconv )
endif()

write_basic_package_version_file(exiv2ConfigVersion.cmake COMPATIBILITY ExactVersion)

install(TARGETS exiv2lib EXPORT exiv2Config
    RUNTIME DESTINATION ${CMAKE_INSTALL_BINDIR}
    LIBRARY DESTINATION ${CMAKE_INSTALL_LIBDIR}
    ARCHIVE DESTINATION ${CMAKE_INSTALL_LIBDIR}
)

install(FILES
    ${PUBLIC_HEADERS}
    ${CMAKE_BINARY_DIR}/exv_conf.h
    ${CMAKE_BINARY_DIR}/exiv2lib_export.h
    DESTINATION ${CMAKE_INSTALL_INCLUDEDIR}/exiv2)

install(EXPORT exiv2Config DESTINATION "${CMAKE_INSTALL_LIBDIR}/cmake/exiv2")

install(FILES ${CMAKE_CURRENT_BINARY_DIR}/exiv2ConfigVersion.cmake DESTINATION "${CMAKE_INSTALL_LIBDIR}/cmake/exiv2")

# ******************************************************************************
# exiv2 application

if(EXIV2_BUILD_EXIV2_COMMAND)
    add_executable( exiv2
         exiv2.cpp
         exiv2app.hpp
         actions.cpp actions.hpp
         getopt.cpp getopt.hpp
         utils.cpp utils.hpp
    )

    set_target_properties( exiv2 PROPERTIES
        COMPILE_FLAGS ${EXTRA_COMPILE_FLAGS}
        XCODE_ATTRIBUTE_GCC_GENERATE_DEBUGGING_SYMBOLS[variant=Debug] "YES"
    )
    if (MSVC)
        set_target_properties(exiv2 PROPERTIES LINK_FLAGS "/ignore:4099") # Ignore missing PDBs
    endif()

    target_link_libraries( exiv2 PRIVATE exiv2lib )

    if( EXIV2_ENABLE_NLS )
        target_link_libraries(exiv2 PRIVATE ${Intl_LIBRARIES})
        target_include_directories(exiv2 PRIVATE ${Intl_INCLUDE_DIRS})
    endif()

    if (USING_CONAN AND WIN32 AND EXISTS ${PROJECT_BINARY_DIR}/conanDlls)
        # In case of using conan recipes with their 'shared' option turned on, we will have dlls of
        # the 3rd party dependencies in the conanDlls folder.

        # Copy 3rd party DLLs the bin folder. [build step]
        add_custom_command(TARGET exiv2 POST_BUILD
            COMMAND ${CMAKE_COMMAND} -E copy_directory ${PROJECT_BINARY_DIR}/conanDlls ${CMAKE_RUNTIME_OUTPUT_DIRECTORY})

        # Copy 3rd party DLLs the bin folder. [install step]
        install(DIRECTORY ${PROJECT_BINARY_DIR}/conanDlls/ DESTINATION bin)
    endif()

    install(TARGETS exiv2 RUNTIME DESTINATION ${CMAKE_INSTALL_BINDIR})
endif()

# That's all Folks!
##

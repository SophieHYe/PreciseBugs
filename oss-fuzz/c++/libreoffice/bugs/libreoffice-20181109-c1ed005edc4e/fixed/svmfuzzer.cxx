/* -*- Mode: C++; tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
 * This file is part of the LibreOffice project.
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */

#include <tools/stream.hxx>
#include "commonfuzzer.hxx"

#include <config_features.h>
#include <osl/detail/component-mapping.h>

extern "C" bool TestImportSVM(SvStream &rStream);

extern "C" {
void * com_sun_star_i18n_LocaleDataImpl_get_implementation( void *, void * );
void * com_sun_star_i18n_BreakIterator_Unicode_get_implementation( void *, void * );
void * com_sun_star_i18n_BreakIterator_get_implementation( void *, void * );
void * com_sun_star_i18n_NativeNumberSupplier_get_implementation( void *, void * );
void * com_sun_star_i18n_NumberFormatCodeMapper_get_implementation( void *, void * );
void * com_sun_star_comp_rendering_CanvasFactory_get_implementation( void *, void * );
void * com_sun_star_comp_rendering_Canvas_VCL_get_implementation( void *, void * );
void * linguistic_ConvDicList_get_implementation( void *, void * );
void * linguistic_DicList_get_implementation( void *, void * );
void * linguistic_LinguProps_get_implementation( void *, void * );
void * linguistic_LngSvcMgr_get_implementation( void *, void * );
void * linguistic_GrammarCheckingIterator_get_implementation( void *, void * );
}

const lib_to_factory_mapping *
lo_get_factory_map(void)
{
    static lib_to_factory_mapping map[] = {
        { 0, 0 }
    };

    return map;
}

const lib_to_constructor_mapping *
lo_get_constructor_map(void)
{
    static lib_to_constructor_mapping map[] = {
        { "com_sun_star_i18n_LocaleDataImpl_get_implementation", com_sun_star_i18n_LocaleDataImpl_get_implementation },
        { "com_sun_star_i18n_BreakIterator_Unicode_get_implementation", com_sun_star_i18n_BreakIterator_Unicode_get_implementation },
        { "com_sun_star_i18n_BreakIterator_get_implementation", com_sun_star_i18n_BreakIterator_get_implementation },
        { "com_sun_star_i18n_NativeNumberSupplier_get_implementation", com_sun_star_i18n_NativeNumberSupplier_get_implementation },
        { "com_sun_star_i18n_NumberFormatCodeMapper_get_implementation", com_sun_star_i18n_NumberFormatCodeMapper_get_implementation },
        { "com_sun_star_comp_rendering_CanvasFactory_get_implementation", com_sun_star_comp_rendering_CanvasFactory_get_implementation },
        { "com_sun_star_comp_rendering_Canvas_VCL_get_implementation", com_sun_star_comp_rendering_Canvas_VCL_get_implementation },
        { "linguistic_ConvDicList_get_implementation", linguistic_ConvDicList_get_implementation },
        { "linguistic_DicList_get_implementation", linguistic_DicList_get_implementation },
        { "linguistic_LinguProps_get_implementation", linguistic_LinguProps_get_implementation },
        { "linguistic_LngSvcMgr_get_implementation", linguistic_LngSvcMgr_get_implementation },
        { "linguistic_GrammarCheckingIterator_get_implementation", linguistic_GrammarCheckingIterator_get_implementation },
        { 0, 0 }
    };

    return map;
}

extern "C" void* lo_get_custom_widget_func(const char*)
{
    return nullptr;
}

extern "C" int LLVMFuzzerInitialize(int *argc, char ***argv)
{
    TypicalFuzzerInitialize(argc, argv);
    return 0;
}

extern "C" int LLVMFuzzerTestOneInput(const uint8_t* data, size_t size)
{
    SvMemoryStream aStream(const_cast<uint8_t*>(data), size, StreamMode::READ);
    (void)TestImportSVM(aStream);
    return 0;
}

/* vim:set shiftwidth=4 softtabstop=4 expandtab: */

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

#include "ua_util.h"
#include "ua_types.h"
#include "ua_types_generated.h"
#include "ua_types_generated_handling.h"

#include "pcg_basic.h"
#include "libc_time.h"

/* Datatype Handling
 * -----------------
 * This file contains handling functions for the builtin types and functions
 * handling of structured types and arrays. These need type descriptions in a
 * UA_DataType structure. The UA_DataType structures as well as all non-builtin
 * datatypes are autogenerated. */

/* Global definition of NULL type instances. These are always zeroed out, as
 * mandated by the C/C++ standard for global values with no initializer. */
const UA_String UA_STRING_NULL = {0, NULL};
const UA_ByteString UA_BYTESTRING_NULL = {0, NULL};
const UA_Guid UA_GUID_NULL = {0, 0, 0, {0,0,0,0,0,0,0,0}};
const UA_NodeId UA_NODEID_NULL = {0, UA_NODEIDTYPE_NUMERIC, {0}};
const UA_ExpandedNodeId UA_EXPANDEDNODEID_NULL = {{0, UA_NODEIDTYPE_NUMERIC, {0}}, {0, NULL}, 0};

/* TODO: The standard-defined types are ordered. See if binary search is
 * more efficient. */
const UA_DataType *
UA_findDataType(const UA_NodeId *typeId) {
    if(typeId->identifierType != UA_NODEIDTYPE_NUMERIC ||
       typeId->namespaceIndex != 0)
        return NULL;
    for(size_t i = 0; i < UA_TYPES_COUNT; ++i) {
        if(UA_TYPES[i].typeId.identifier.numeric == typeId->identifier.numeric)
            return &UA_TYPES[i];
    }
    return NULL;
}

/***************************/
/* Random Number Generator */
/***************************/

static UA_THREAD_LOCAL pcg32_random_t UA_rng = PCG32_INITIALIZER;

void
UA_random_seed(u64 seed) {
    pcg32_srandom_r(&UA_rng, seed, (u64)UA_DateTime_now());
}

u32
UA_UInt32_random(void) {
    return (u32)pcg32_random_r(&UA_rng);
}

/*****************/
/* Builtin Types */
/*****************/

static void deleteMembers_noInit(void *p, const UA_DataType *type);
static UA_StatusCode copy_noInit(const void *src, void *dst, const UA_DataType *type);

UA_String
UA_String_fromChars(char const src[]) {
    UA_String str;
    str.length = strlen(src);
    if(str.length > 0) {
        str.data = (u8*)UA_malloc(str.length);
        if(!str.data)
            return UA_STRING_NULL;
        memcpy(str.data, src, str.length);
    } else {
        str.data = (u8*)UA_EMPTY_ARRAY_SENTINEL;
    }
    return str;
}

UA_Boolean
UA_String_equal(const UA_String *s1, const UA_String *s2) {
    if(s1->length != s2->length)
        return false;
    i32 is = memcmp((char const*)s1->data,
                    (char const*)s2->data, s1->length);
    return (is == 0) ? true : false;
}

static void
String_deleteMembers(UA_String *s, const UA_DataType *_) {
    UA_free((void*)((uintptr_t)s->data & ~(uintptr_t)UA_EMPTY_ARRAY_SENTINEL));
}

/* DateTime */
UA_DateTimeStruct
UA_DateTime_toStruct(UA_DateTime t) {
    /* Calculating the the milli-, micro- and nanoseconds */
    UA_DateTimeStruct dateTimeStruct;
    dateTimeStruct.nanoSec  = (u16)((t % 10) * 100);
    dateTimeStruct.microSec = (u16)((t % 10000) / 10);
    dateTimeStruct.milliSec = (u16)((t % 10000000) / 10000);

    /* Calculating the unix time with #include <time.h> */
    long long secSinceUnixEpoch = (long long)
        ((t - UA_DATETIME_UNIX_EPOCH) / UA_SEC_TO_DATETIME);
    struct mytm ts;
    memset(&ts, 0, sizeof(struct mytm));
    __secs_to_tm(secSinceUnixEpoch, &ts);
    dateTimeStruct.sec    = (u16)ts.tm_sec;
    dateTimeStruct.min    = (u16)ts.tm_min;
    dateTimeStruct.hour   = (u16)ts.tm_hour;
    dateTimeStruct.day    = (u16)ts.tm_mday;
    dateTimeStruct.month  = (u16)(ts.tm_mon + 1);
    dateTimeStruct.year   = (u16)(ts.tm_year + 1900);
    return dateTimeStruct;
}

static void
printNumber(u16 n, u8 *pos, size_t digits) {
    for(size_t i = digits; i > 0; --i) {
        pos[i-1] = (u8)((n % 10) + '0');
        n = n / 10;
    }
}

UA_String
UA_DateTime_toString(UA_DateTime t) {
    /* length of the string is 31 (plus \0 at the end) */
    UA_String str = {31, (u8*)UA_malloc(32)};
    if(!str.data)
        return UA_STRING_NULL;
    UA_DateTimeStruct tSt = UA_DateTime_toStruct(t);
    printNumber(tSt.month, str.data, 2);
    str.data[2] = '/';
    printNumber(tSt.day, &str.data[3], 2);
    str.data[5] = '/';
    printNumber(tSt.year, &str.data[6], 4);
    str.data[10] = ' ';
    printNumber(tSt.hour, &str.data[11], 2);
    str.data[13] = ':';
    printNumber(tSt.min, &str.data[14], 2);
    str.data[16] = ':';
    printNumber(tSt.sec, &str.data[17], 2);
    str.data[19] = '.';
    printNumber(tSt.milliSec, &str.data[20], 3);
    str.data[23] = '.';
    printNumber(tSt.microSec, &str.data[24], 3);
    str.data[27] = '.';
    printNumber(tSt.nanoSec, &str.data[28], 3);
    return str;
}

/* Guid */
UA_Boolean
UA_Guid_equal(const UA_Guid *g1, const UA_Guid *g2) {
    if(memcmp(g1, g2, sizeof(UA_Guid)) == 0)
        return true;
    return false;
}

UA_Guid
UA_Guid_random(void) {
    UA_Guid result;
    result.data1 = (u32)pcg32_random_r(&UA_rng);
    u32 r = (u32)pcg32_random_r(&UA_rng);
    result.data2 = (u16) r;
    result.data3 = (u16) (r >> 16);
    r = (u32)pcg32_random_r(&UA_rng);
    result.data4[0] = (u8)r;
    result.data4[1] = (u8)(r >> 4);
    result.data4[2] = (u8)(r >> 8);
    result.data4[3] = (u8)(r >> 12);
    r = (u32)pcg32_random_r(&UA_rng);
    result.data4[4] = (u8)r;
    result.data4[5] = (u8)(r >> 4);
    result.data4[6] = (u8)(r >> 8);
    result.data4[7] = (u8)(r >> 12);
    return result;
}

/* ByteString */
UA_StatusCode
UA_ByteString_allocBuffer(UA_ByteString *bs, size_t length) {
    UA_ByteString_init(bs);
    if(length == 0)
        return UA_STATUSCODE_GOOD;
    bs->data = (u8*)UA_malloc(length);
    if(!bs->data)
        return UA_STATUSCODE_BADOUTOFMEMORY;
    bs->length = length;
    return UA_STATUSCODE_GOOD;
}

/* NodeId */
static void
NodeId_deleteMembers(UA_NodeId *p, const UA_DataType *_) {
    switch(p->identifierType) {
    case UA_NODEIDTYPE_STRING:
    case UA_NODEIDTYPE_BYTESTRING:
        String_deleteMembers(&p->identifier.string, NULL);
        break;
    default: break;
    }
}

static UA_StatusCode
NodeId_copy(UA_NodeId const *src, UA_NodeId *dst, const UA_DataType *_) {
    UA_StatusCode retval = UA_STATUSCODE_GOOD;
    switch(src->identifierType) {
    case UA_NODEIDTYPE_NUMERIC:
        *dst = *src;
        return UA_STATUSCODE_GOOD;
    case UA_NODEIDTYPE_STRING:
        retval |= UA_String_copy(&src->identifier.string,
                                 &dst->identifier.string);
        break;
    case UA_NODEIDTYPE_GUID:
        retval |= UA_Guid_copy(&src->identifier.guid, &dst->identifier.guid);
        break;
    case UA_NODEIDTYPE_BYTESTRING:
        retval |= UA_ByteString_copy(&src->identifier.byteString,
                                     &dst->identifier.byteString);
        break;
    default:
        return UA_STATUSCODE_BADINTERNALERROR;
    }
    dst->namespaceIndex = src->namespaceIndex;
    dst->identifierType = src->identifierType;
    return retval;
}

UA_Boolean
UA_NodeId_isNull(const UA_NodeId *p) {
    if(p->namespaceIndex != 0)
        return false;
    switch(p->identifierType) {
    case UA_NODEIDTYPE_NUMERIC:
        return (p->identifier.numeric == 0);
    case UA_NODEIDTYPE_GUID:
        return (p->identifier.guid.data1 == 0 &&
                p->identifier.guid.data2 == 0 &&
                p->identifier.guid.data3 == 0 &&
                p->identifier.guid.data4[0] == 0 &&
                p->identifier.guid.data4[1] == 0 &&
                p->identifier.guid.data4[2] == 0 &&
                p->identifier.guid.data4[3] == 0 &&
                p->identifier.guid.data4[4] == 0 &&
                p->identifier.guid.data4[5] == 0 &&
                p->identifier.guid.data4[6] == 0 &&
                p->identifier.guid.data4[7] == 0);
    default:
        break;
    }
    return (p->identifier.string.length == 0);
}

UA_Boolean
UA_NodeId_equal(const UA_NodeId *n1, const UA_NodeId *n2) {
    if(n1->namespaceIndex != n2->namespaceIndex ||
       n1->identifierType!=n2->identifierType)
        return false;
    switch(n1->identifierType) {
    case UA_NODEIDTYPE_NUMERIC:
        if(n1->identifier.numeric == n2->identifier.numeric)
            return true;
        else
            return false;
    case UA_NODEIDTYPE_STRING:
        return UA_String_equal(&n1->identifier.string,
                               &n2->identifier.string);
    case UA_NODEIDTYPE_GUID:
        return UA_Guid_equal(&n1->identifier.guid,
                             &n2->identifier.guid);
    case UA_NODEIDTYPE_BYTESTRING:
        return UA_ByteString_equal(&n1->identifier.byteString,
                                   &n2->identifier.byteString);
    }
    return false;
}

/* FNV non-cryptographic hash function. See
 * https://en.wikipedia.org/wiki/Fowler%E2%80%93Noll%E2%80%93Vo_hash_function */
#define FNV_PRIME_32 16777619
static u32
fnv32(u32 fnv, const u8 *buf, size_t size) {
    for(size_t i = 0; i < size; ++i) {
        fnv = fnv ^ (buf[i]);
        fnv = fnv * FNV_PRIME_32;
    }
    return fnv;
}

u32
UA_NodeId_hash(const UA_NodeId *n) {
    switch(n->identifierType) {
    case UA_NODEIDTYPE_NUMERIC:
    default:
        // shift knuth multiplication to use highest 32 bits and after addition make sure we don't have an integer overflow
        return (u32)((n->namespaceIndex + ((n->identifier.numeric * (u64)2654435761) >> (32))) & UINT32_C(4294967295)); /*  Knuth's multiplicative hashing */
    case UA_NODEIDTYPE_STRING:
    case UA_NODEIDTYPE_BYTESTRING:
        return fnv32(n->namespaceIndex, n->identifier.string.data, n->identifier.string.length);
    case UA_NODEIDTYPE_GUID:
        return fnv32(n->namespaceIndex, (const u8*)&n->identifier.guid, sizeof(UA_Guid));
    }
}

/* ExpandedNodeId */
static void
ExpandedNodeId_deleteMembers(UA_ExpandedNodeId *p, const UA_DataType *_) {
    NodeId_deleteMembers(&p->nodeId, _);
    String_deleteMembers(&p->namespaceUri, NULL);
}

static UA_StatusCode
ExpandedNodeId_copy(UA_ExpandedNodeId const *src, UA_ExpandedNodeId *dst,
                    const UA_DataType *_) {
    UA_StatusCode retval = NodeId_copy(&src->nodeId, &dst->nodeId, NULL);
    retval |= UA_String_copy(&src->namespaceUri, &dst->namespaceUri);
    dst->serverIndex = src->serverIndex;
    return retval;
}

/* ExtensionObject */
static void
ExtensionObject_deleteMembers(UA_ExtensionObject *p, const UA_DataType *_) {
    switch(p->encoding) {
    case UA_EXTENSIONOBJECT_ENCODED_NOBODY:
    case UA_EXTENSIONOBJECT_ENCODED_BYTESTRING:
    case UA_EXTENSIONOBJECT_ENCODED_XML:
        NodeId_deleteMembers(&p->content.encoded.typeId, NULL);
        String_deleteMembers(&p->content.encoded.body, NULL);
        break;
    case UA_EXTENSIONOBJECT_DECODED:
        if(p->content.decoded.data)
            UA_delete(p->content.decoded.data, p->content.decoded.type);
        break;
    default:
        break;
    }
}

static UA_StatusCode
ExtensionObject_copy(UA_ExtensionObject const *src, UA_ExtensionObject *dst,
                     const UA_DataType *_) {
    UA_StatusCode retval = UA_STATUSCODE_GOOD;
    switch(src->encoding) {
    case UA_EXTENSIONOBJECT_ENCODED_NOBODY:
    case UA_EXTENSIONOBJECT_ENCODED_BYTESTRING:
    case UA_EXTENSIONOBJECT_ENCODED_XML:
        dst->encoding = src->encoding;
        retval = NodeId_copy(&src->content.encoded.typeId,
                             &dst->content.encoded.typeId, NULL);
        retval |= UA_ByteString_copy(&src->content.encoded.body,
                                     &dst->content.encoded.body);
        break;
    case UA_EXTENSIONOBJECT_DECODED:
    case UA_EXTENSIONOBJECT_DECODED_NODELETE:
        if(!src->content.decoded.type || !src->content.decoded.data)
            return UA_STATUSCODE_BADINTERNALERROR;
        dst->encoding = UA_EXTENSIONOBJECT_DECODED;
        dst->content.decoded.type = src->content.decoded.type;
        retval = UA_Array_copy(src->content.decoded.data, 1,
            &dst->content.decoded.data, src->content.decoded.type);
        break;
    default:
        break;
    }
    return retval;
}

/* Variant */
static void
Variant_deletemembers(UA_Variant *p, const UA_DataType *_) {
    if(p->storageType != UA_VARIANT_DATA)
        return;
    if(p->type && p->data > UA_EMPTY_ARRAY_SENTINEL) {
        if(p->arrayLength == 0)
            p->arrayLength = 1;
        UA_Array_delete(p->data, p->arrayLength, p->type);
    }
    if((void*)p->arrayDimensions > UA_EMPTY_ARRAY_SENTINEL)
        UA_free(p->arrayDimensions);
}

static UA_StatusCode
Variant_copy(UA_Variant const *src, UA_Variant *dst, const UA_DataType *_) {
    size_t length = src->arrayLength;
    if(UA_Variant_isScalar(src))
        length = 1;
    UA_StatusCode retval = UA_Array_copy(src->data, length,
                                         &dst->data, src->type);
    if(retval != UA_STATUSCODE_GOOD)
        return retval;
    dst->arrayLength = src->arrayLength;
    dst->type = src->type;
    if(src->arrayDimensions) {
        retval = UA_Array_copy(src->arrayDimensions, src->arrayDimensionsSize,
            (void**)&dst->arrayDimensions, &UA_TYPES[UA_TYPES_INT32]);
        if(retval != UA_STATUSCODE_GOOD)
            return retval;
        dst->arrayDimensionsSize = src->arrayDimensionsSize;
    }
    return UA_STATUSCODE_GOOD;
}

void
UA_Variant_setScalar(UA_Variant *v, void * UA_RESTRICT p,
                     const UA_DataType *type) {
    UA_Variant_init(v);
    v->type = type;
    v->arrayLength = 0;
    v->data = p;
}

UA_StatusCode
UA_Variant_setScalarCopy(UA_Variant *v, const void *p,
                         const UA_DataType *type) {
    void *n = UA_malloc(type->memSize);
    if(!n)
        return UA_STATUSCODE_BADOUTOFMEMORY;
    UA_StatusCode retval = UA_copy(p, n, type);
    if(retval != UA_STATUSCODE_GOOD) {
        UA_free(n);
        //cppcheck-suppress memleak
        return retval;
    }
    UA_Variant_setScalar(v, n, type);
    //cppcheck-suppress memleak
    return UA_STATUSCODE_GOOD;
}

void UA_Variant_setArray(UA_Variant *v, void * UA_RESTRICT array,
                         size_t arraySize, const UA_DataType *type) {
    UA_Variant_init(v);
    v->data = array;
    v->arrayLength = arraySize;
    v->type = type;
}

UA_StatusCode
UA_Variant_setArrayCopy(UA_Variant *v, const void *array,
                        size_t arraySize, const UA_DataType *type) {
    UA_Variant_init(v);
    UA_StatusCode retval = UA_Array_copy(array, arraySize, &v->data, type);
    if(retval != UA_STATUSCODE_GOOD)
        return retval;
    v->arrayLength = arraySize;
    v->type = type;
    return UA_STATUSCODE_GOOD;
}

/* Test if a range is compatible with a variant. If yes, the following values
 * are set:
 * - total: how many elements are in the range
 * - block: how big is each contiguous block of elements in the variant that
 *   maps into the range
 * - stride: how many elements are between the blocks (beginning to beginning)
 * - first: where does the first block begin */
static UA_StatusCode
computeStrides(const UA_Variant *v, const UA_NumericRange range,
               size_t *total, size_t *block, size_t *stride, size_t *first) {
    /* Test for max array size (64bit only) */
#if (SIZE_MAX > 0xffffffff)
    if(v->arrayLength > UA_UINT32_MAX)
        return UA_STATUSCODE_BADINTERNALERROR;
#endif

    /* Test the integrity of the source variant dimensions, make dimensions
     * vector of one dimension if none defined */
    u32 arrayLength = (u32)v->arrayLength;
    const u32 *dims = &arrayLength;
    size_t dims_count = 1;
    if(v->arrayDimensionsSize > 0) {
        size_t elements = 1;
        dims_count = v->arrayDimensionsSize;
        dims = (u32*)v->arrayDimensions;
        for(size_t i = 0; i < dims_count; ++i)
            elements *= dims[i];
        if(elements != v->arrayLength)
            return UA_STATUSCODE_BADINTERNALERROR;
    }
    UA_assert(dims_count > 0);

    /* Test the integrity of the range and compute the max index used for every
     * dimension. The standard says in Part 4, Section 7.22:
     *
     * When reading a value, the indexes may not specify a range that is within
     * the bounds of the array. The Server shall return a partial result if some
     * elements exist within the range. */
    size_t count = 1;
    UA_UInt32 *realmax = (UA_UInt32*)UA_alloca(sizeof(UA_UInt32) * dims_count);
    if(range.dimensionsSize != dims_count)
        return UA_STATUSCODE_BADINDEXRANGENODATA;
    for(size_t i = 0; i < dims_count; ++i) {
        if(range.dimensions[i].min > range.dimensions[i].max)
            return UA_STATUSCODE_BADINDEXRANGEINVALID;
        if(range.dimensions[i].min >= dims[i])
            return UA_STATUSCODE_BADINDEXRANGENODATA;

        if(range.dimensions[i].max < dims[i])
            realmax[i] = range.dimensions[i].max;
        else
            realmax[i] = dims[i] - 1;

        count *= (realmax[i] - range.dimensions[i].min) + 1;
    }

    *total = count;

    /* Compute the stride length and the position of the first element */
    *block = count;           /* Assume the range describes the entire array. */
    *stride = v->arrayLength; /* So it can be copied as a contiguous block.   */
    *first = 0;
    size_t running_dimssize = 1;
    bool found_contiguous = false;
    for(size_t k = dims_count; k > 0;) {
        --k;
        size_t dimrange = 1 + realmax[k] - range.dimensions[k].min;
        if(!found_contiguous && dimrange != dims[k]) {
            /* Found the maximum block that can be copied contiguously */
            found_contiguous = true;
            *block = running_dimssize * dimrange;
            *stride = running_dimssize * dims[k];
        }
        *first += running_dimssize * range.dimensions[k].min;
        running_dimssize *= dims[k];
    }
    return UA_STATUSCODE_GOOD;
}

/* Is the type string-like? */
static bool
isStringLike(const UA_DataType *type) {
    if(type->membersSize == 1 && type->members[0].isArray &&
       type->members[0].namespaceZero &&
       type->members[0].memberTypeIndex == UA_TYPES_BYTE)
        return true;
    return false;
}

/* Returns the part of the string that lies within the rangedimension */
static UA_StatusCode
copySubString(const UA_String *src, UA_String *dst,
              const UA_NumericRangeDimension *dim) {
    if(dim->min > dim->max)
        return UA_STATUSCODE_BADINDEXRANGEINVALID;
    if(dim->min >= src->length)
        return UA_STATUSCODE_BADINDEXRANGENODATA;

    size_t length;
    if(dim->max < src->length)
       length = dim->max - dim->min + 1;
    else
        length = src->length - dim->min;

    UA_StatusCode retval = UA_ByteString_allocBuffer(dst, length);
    if(retval != UA_STATUSCODE_GOOD)
        return retval;

    memcpy(dst->data, &src->data[dim->min], length);
    return UA_STATUSCODE_GOOD;
}

UA_StatusCode
UA_Variant_copyRange(const UA_Variant *src, UA_Variant *dst,
                     const UA_NumericRange range) {
    bool isScalar = UA_Variant_isScalar(src);
    bool stringLike = isStringLike(src->type);
    UA_Variant arraySrc;

    /* Extract the range for copying at this level. The remaining range is dealt
     * with in the "scalar" type that may define an array by itself (string,
     * variant, ...). */
    UA_NumericRange thisrange, nextrange;
    UA_NumericRangeDimension scalarThisDimension = {0,0}; /* a single entry */
    if(isScalar) {
        /* Replace scalar src with array of length 1 */
        arraySrc = *src;
        arraySrc.arrayLength = 1;
        src = &arraySrc;
        /* Deal with all range dimensions within the scalar */
        thisrange.dimensions = &scalarThisDimension;
        thisrange.dimensionsSize = 1;
        nextrange = range;
    } else {
        /* Deal with as many range dimensions as possible right now */
        size_t dims = src->arrayDimensionsSize;
        if(dims == 0)
            dims = 1;
        if(dims > range.dimensionsSize)
            return UA_STATUSCODE_BADINDEXRANGEINVALID;
       thisrange = range;
       thisrange.dimensionsSize = dims;
       nextrange.dimensions = &range.dimensions[dims];
       nextrange.dimensionsSize = range.dimensionsSize - dims;
    }
        
    /* Compute the strides */
    size_t count, block, stride, first;
    UA_StatusCode retval = computeStrides(src, thisrange, &count,
                                          &block, &stride, &first);
    if(retval != UA_STATUSCODE_GOOD)
        return retval;

    /* Allocate the array */
    UA_Variant_init(dst);
    dst->data = UA_Array_new(count, src->type);
    if(!dst->data)
        return UA_STATUSCODE_BADOUTOFMEMORY;

    /* Copy the range */
    size_t block_count = count / block;
    size_t elem_size = src->type->memSize;
    uintptr_t nextdst = (uintptr_t)dst->data;
    uintptr_t nextsrc = (uintptr_t)src->data + (elem_size * first);
    if(nextrange.dimensionsSize == 0) {
        /* no nextrange */
        if(src->type->pointerFree) {
            for(size_t i = 0; i < block_count; ++i) {
                memcpy((void*)nextdst, (void*)nextsrc, elem_size * block);
                nextdst += block * elem_size;
                nextsrc += stride * elem_size;
            }
        } else {
            for(size_t i = 0; i < block_count; ++i) {
                for(size_t j = 0; j < block; ++j) {
                    retval = UA_copy((const void*)nextsrc,
                                     (void*)nextdst, src->type);
                    nextdst += elem_size;
                    nextsrc += elem_size;
                }
                nextsrc += (stride - block) * elem_size;
            }
        }
    } else {
        /* nextrange can only be used for variants and stringlike with remaining
         * range of dimension 1 */
        if(src->type != &UA_TYPES[UA_TYPES_VARIANT]) {
            if(!stringLike)
                retval = UA_STATUSCODE_BADINDEXRANGENODATA;
            if(nextrange.dimensionsSize != 1)
                retval = UA_STATUSCODE_BADINDEXRANGENODATA;
        }

        /* Copy the content */
        for(size_t i = 0; i < block_count; ++i) {
            for(size_t j = 0; j < block && retval == UA_STATUSCODE_GOOD; ++j) {
                if(stringLike)
                    retval = copySubString((const UA_String*)nextsrc,
                                           (UA_String*)nextdst,
                                           nextrange.dimensions);
                else
                    retval = UA_Variant_copyRange((const UA_Variant*)nextsrc,
                                                  (UA_Variant*)nextdst,
                                                  nextrange);
                nextdst += elem_size;
                nextsrc += elem_size;
            }
            nextsrc += (stride - block) * elem_size;
        }
    }

    /* Clean up if copying failed */
    if(retval != UA_STATUSCODE_GOOD) {
        UA_Array_delete(dst->data, count, src->type);
        dst->data = NULL;
        return retval;
    }

    /* Done if scalar */
    dst->type = src->type;
    if(isScalar)
        return retval;

    /* Copy array dimensions */
    dst->arrayLength = count;
    if(src->arrayDimensionsSize > 0) {
        dst->arrayDimensions =
            (u32*)UA_Array_new(thisrange.dimensionsSize, &UA_TYPES[UA_TYPES_UINT32]);
        if(!dst->arrayDimensions) {
            Variant_deletemembers(dst, NULL);
            return UA_STATUSCODE_BADOUTOFMEMORY;
        }
        dst->arrayDimensionsSize = thisrange.dimensionsSize;
        for(size_t k = 0; k < thisrange.dimensionsSize; ++k)
            dst->arrayDimensions[k] =
                thisrange.dimensions[k].max - thisrange.dimensions[k].min + 1;
    }
    return UA_STATUSCODE_GOOD;
}

/* TODO: Allow ranges to reach inside a scalars that are array-like, e.g.
 * variant and strings. This is already possible for reading... */
static UA_StatusCode
Variant_setRange(UA_Variant *v, void *array, size_t arraySize,
                 const UA_NumericRange range, bool copy) {
    /* Compute the strides */
    size_t count, block, stride, first;
    UA_StatusCode retval = computeStrides(v, range, &count,
                                          &block, &stride, &first);
    if(retval != UA_STATUSCODE_GOOD)
        return retval;
    if(count != arraySize)
        return UA_STATUSCODE_BADINDEXRANGEINVALID;

    /* Move/copy the elements */
    size_t block_count = count / block;
    size_t elem_size = v->type->memSize;
    uintptr_t nextdst = (uintptr_t)v->data + (first * elem_size);
    uintptr_t nextsrc = (uintptr_t)array;
    if(v->type->pointerFree || !copy) {
        for(size_t i = 0; i < block_count; ++i) {
            memcpy((void*)nextdst, (void*)nextsrc, elem_size * block);
            nextsrc += block * elem_size;
            nextdst += stride * elem_size;
        }
    } else {
        for(size_t i = 0; i < block_count; ++i) {
            for(size_t j = 0; j < block; ++j) {
                deleteMembers_noInit((void*)nextdst, v->type);
                retval |= UA_copy((void*)nextsrc, (void*)nextdst, v->type);
                nextdst += elem_size;
                nextsrc += elem_size;
            }
            nextdst += (stride - block) * elem_size;
        }
    }

    /* If members were moved, initialize original array to prevent reuse */
    if(!copy && !v->type->pointerFree)
        memset(array, 0, sizeof(elem_size)*arraySize);

    return retval;
}

UA_StatusCode
UA_Variant_setRange(UA_Variant *v, void * UA_RESTRICT array,
                    size_t arraySize, const UA_NumericRange range) {
    return Variant_setRange(v, array, arraySize, range, false);
}

UA_StatusCode
UA_Variant_setRangeCopy(UA_Variant *v, const void *array,
                        size_t arraySize, const UA_NumericRange range) {
    return Variant_setRange(v, (void*)(uintptr_t)array,
                            arraySize, range, true);
}

/* LocalizedText */
static void
LocalizedText_deleteMembers(UA_LocalizedText *p, const UA_DataType *_) {
    String_deleteMembers(&p->locale, NULL);
    String_deleteMembers(&p->text, NULL);
}

static UA_StatusCode
LocalizedText_copy(UA_LocalizedText const *src, UA_LocalizedText *dst,
                   const UA_DataType *_) {
    UA_StatusCode retval = UA_String_copy(&src->locale, &dst->locale);
    retval |= UA_String_copy(&src->text, &dst->text);
    return retval;
}

/* DataValue */
static void
DataValue_deleteMembers(UA_DataValue *p, const UA_DataType *_) {
    Variant_deletemembers(&p->value, NULL);
}

static UA_StatusCode
DataValue_copy(UA_DataValue const *src, UA_DataValue *dst,
               const UA_DataType *_) {
    memcpy(dst, src, sizeof(UA_DataValue));
    UA_Variant_init(&dst->value);
    UA_StatusCode retval = Variant_copy(&src->value, &dst->value, NULL);
    if(retval != UA_STATUSCODE_GOOD)
        DataValue_deleteMembers(dst, NULL);
    return retval;
}

/* DiagnosticInfo */
static void
DiagnosticInfo_deleteMembers(UA_DiagnosticInfo *p, const UA_DataType *_) {
    String_deleteMembers(&p->additionalInfo, NULL);
    if(p->hasInnerDiagnosticInfo && p->innerDiagnosticInfo) {
        DiagnosticInfo_deleteMembers(p->innerDiagnosticInfo, NULL);
        UA_free(p->innerDiagnosticInfo);
    }
}

static UA_StatusCode
DiagnosticInfo_copy(UA_DiagnosticInfo const *src, UA_DiagnosticInfo *dst,
                    const UA_DataType *_) {
    memcpy(dst, src, sizeof(UA_DiagnosticInfo));
    UA_String_init(&dst->additionalInfo);
    dst->innerDiagnosticInfo = NULL;
    UA_StatusCode retval = UA_STATUSCODE_GOOD;
    if(src->hasAdditionalInfo)
       retval = UA_String_copy(&src->additionalInfo, &dst->additionalInfo);
    if(src->hasInnerDiagnosticInfo && src->innerDiagnosticInfo) {
        dst->innerDiagnosticInfo = (UA_DiagnosticInfo*)UA_malloc(sizeof(UA_DiagnosticInfo));
        if(dst->innerDiagnosticInfo) {
            retval |= DiagnosticInfo_copy(src->innerDiagnosticInfo,
                                          dst->innerDiagnosticInfo, NULL);
            dst->hasInnerDiagnosticInfo = true;
        } else {
            dst->hasInnerDiagnosticInfo = false;
            retval |= UA_STATUSCODE_BADOUTOFMEMORY;
        }
    }
    return retval;
}

/********************/
/* Structured Types */
/********************/

void *
UA_new(const UA_DataType *type) {
    void *p = UA_calloc(1, type->memSize);
    return p;
}

static UA_StatusCode
copyByte(const u8 *src, u8 *dst, const UA_DataType *_) {
    *dst = *src;
    return UA_STATUSCODE_GOOD;
}

static UA_StatusCode
copy2Byte(const u16 *src, u16 *dst, const UA_DataType *_) {
    *dst = *src;
    return UA_STATUSCODE_GOOD;
}

static UA_StatusCode
copy4Byte(const u32 *src, u32 *dst, const UA_DataType *_) {
    *dst = *src;
    return UA_STATUSCODE_GOOD;
}

static UA_StatusCode
copy8Byte(const u64 *src, u64 *dst, const UA_DataType *_) {
    *dst = *src;
    return UA_STATUSCODE_GOOD;
}

static UA_StatusCode
copyGuid(const UA_Guid *src, UA_Guid *dst, const UA_DataType *_) {
    *dst = *src;
    return UA_STATUSCODE_GOOD;
}

typedef UA_StatusCode
(*UA_copySignature)(const void *src, void *dst, const UA_DataType *type);

static const UA_copySignature copyJumpTable[UA_BUILTIN_TYPES_COUNT + 1] = {
    (UA_copySignature)copyByte, // Boolean
    (UA_copySignature)copyByte, // SByte
    (UA_copySignature)copyByte, // Byte
    (UA_copySignature)copy2Byte, // Int16
    (UA_copySignature)copy2Byte, // UInt16
    (UA_copySignature)copy4Byte, // Int32
    (UA_copySignature)copy4Byte, // UInt32
    (UA_copySignature)copy8Byte, // Int64
    (UA_copySignature)copy8Byte, // UInt64
    (UA_copySignature)copy4Byte, // Float
    (UA_copySignature)copy8Byte, // Double
    (UA_copySignature)copy_noInit, // String
    (UA_copySignature)copy8Byte, // DateTime
    (UA_copySignature)copyGuid, // Guid
    (UA_copySignature)copy_noInit, // ByteString
    (UA_copySignature)copy_noInit, // XmlElement
    (UA_copySignature)NodeId_copy,
    (UA_copySignature)ExpandedNodeId_copy,
    (UA_copySignature)copy4Byte, // StatusCode
    (UA_copySignature)copy_noInit, // QualifiedName
    (UA_copySignature)LocalizedText_copy, // LocalizedText
    (UA_copySignature)ExtensionObject_copy,
    (UA_copySignature)DataValue_copy,
    (UA_copySignature)Variant_copy,
    (UA_copySignature)DiagnosticInfo_copy,
    (UA_copySignature)copy_noInit // all others
};

static UA_StatusCode
copy_noInit(const void *src, void *dst, const UA_DataType *type) {
    UA_StatusCode retval = UA_STATUSCODE_GOOD;
    uintptr_t ptrs = (uintptr_t)src;
    uintptr_t ptrd = (uintptr_t)dst;
    u8 membersSize = type->membersSize;
    for(size_t i = 0; i < membersSize; ++i) {
        const UA_DataTypeMember *m= &type->members[i];
        const UA_DataType *typelists[2] = { UA_TYPES, &type[-type->typeIndex] };
        const UA_DataType *mt = &typelists[!m->namespaceZero][m->memberTypeIndex];
        if(!m->isArray) {
            ptrs += m->padding;
            ptrd += m->padding;
            size_t fi = mt->builtin ? mt->typeIndex : UA_BUILTIN_TYPES_COUNT;
            retval |= copyJumpTable[fi]((const void*)ptrs, (void*)ptrd, mt);
            ptrs += mt->memSize;
            ptrd += mt->memSize;
        } else {
            ptrs += m->padding;
            ptrd += m->padding;
            size_t *dst_size = (size_t*)ptrd;
            const size_t size = *((const size_t*)ptrs);
            ptrs += sizeof(size_t);
            ptrd += sizeof(size_t);
            retval |= UA_Array_copy(*(void* const*)ptrs, size, (void**)ptrd, mt);
            if(retval == UA_STATUSCODE_GOOD)
                *dst_size = size;
            else
                *dst_size = 0;
            ptrs += sizeof(void*);
            ptrd += sizeof(void*);
        }
    }
    return retval;
}

UA_StatusCode
UA_copy(const void *src, void *dst, const UA_DataType *type) {
    memset(dst, 0, type->memSize); /* init */
    UA_StatusCode retval = copy_noInit(src, dst, type);
    if(retval != UA_STATUSCODE_GOOD)
        UA_deleteMembers(dst, type);
    return retval;
}

static void nopDeleteMembers(void *p, const UA_DataType *type) { }

typedef void (*UA_deleteMembersSignature)(void *p, const UA_DataType *type);

static const
UA_deleteMembersSignature deleteMembersJumpTable[UA_BUILTIN_TYPES_COUNT + 1] = {
    (UA_deleteMembersSignature)nopDeleteMembers, // Boolean
    (UA_deleteMembersSignature)nopDeleteMembers, // SByte
    (UA_deleteMembersSignature)nopDeleteMembers, // Byte
    (UA_deleteMembersSignature)nopDeleteMembers, // Int16
    (UA_deleteMembersSignature)nopDeleteMembers, // UInt16
    (UA_deleteMembersSignature)nopDeleteMembers, // Int32
    (UA_deleteMembersSignature)nopDeleteMembers, // UInt32
    (UA_deleteMembersSignature)nopDeleteMembers, // Int64
    (UA_deleteMembersSignature)nopDeleteMembers, // UInt64
    (UA_deleteMembersSignature)nopDeleteMembers, // Float
    (UA_deleteMembersSignature)nopDeleteMembers, // Double
    (UA_deleteMembersSignature)String_deleteMembers, // String
    (UA_deleteMembersSignature)nopDeleteMembers, // DateTime
    (UA_deleteMembersSignature)nopDeleteMembers, // Guid
    (UA_deleteMembersSignature)String_deleteMembers, // ByteString
    (UA_deleteMembersSignature)String_deleteMembers, // XmlElement
    (UA_deleteMembersSignature)NodeId_deleteMembers,
    (UA_deleteMembersSignature)ExpandedNodeId_deleteMembers, // ExpandedNodeId
    (UA_deleteMembersSignature)nopDeleteMembers, // StatusCode
    (UA_deleteMembersSignature)deleteMembers_noInit, // QualifiedName
    (UA_deleteMembersSignature)LocalizedText_deleteMembers, // LocalizedText
    (UA_deleteMembersSignature)ExtensionObject_deleteMembers,
    (UA_deleteMembersSignature)DataValue_deleteMembers,
    (UA_deleteMembersSignature)Variant_deletemembers,
    (UA_deleteMembersSignature)DiagnosticInfo_deleteMembers,
    (UA_deleteMembersSignature)deleteMembers_noInit,
};

static void
deleteMembers_noInit(void *p, const UA_DataType *type) {
    uintptr_t ptr = (uintptr_t)p;
    u8 membersSize = type->membersSize;
    for(size_t i = 0; i < membersSize; ++i) {
        const UA_DataTypeMember *m= &type->members[i];
        const UA_DataType *typelists[2] = { UA_TYPES, &type[-type->typeIndex] };
        const UA_DataType *mt = &typelists[!m->namespaceZero][m->memberTypeIndex];
        if(!m->isArray) {
            ptr += m->padding;
            size_t fi = mt->builtin ? mt->typeIndex : UA_BUILTIN_TYPES_COUNT;
            deleteMembersJumpTable[fi]((void*)ptr, mt);
            ptr += mt->memSize;
        } else {
            ptr += m->padding;
            size_t length = *(size_t*)ptr;
            ptr += sizeof(size_t);
            UA_Array_delete(*(void**)ptr, length, mt);
            ptr += sizeof(void*);
        }
    }
}

void
UA_deleteMembers(void *p, const UA_DataType *type) {
    deleteMembers_noInit(p, type);
    memset(p, 0, type->memSize); /* init */
}

void
UA_delete(void *p, const UA_DataType *type) {
    deleteMembers_noInit(p, type);
    UA_free(p);
}

/******************/
/* Array Handling */
/******************/

void *
UA_Array_new(size_t size, const UA_DataType *type) {
    if(size > UA_INT32_MAX)
        return NULL;
    if(size == 0)
        return UA_EMPTY_ARRAY_SENTINEL;
    return UA_calloc(size, type->memSize);
}

UA_StatusCode
UA_Array_copy(const void *src, size_t size,
              void **dst, const UA_DataType *type) {
    if(size == 0) {
        if(src == NULL)
            *dst = NULL;
        else
            *dst= UA_EMPTY_ARRAY_SENTINEL;
        return UA_STATUSCODE_GOOD;
    }

    if(!type)
        return UA_STATUSCODE_BADINTERNALERROR;

    /* calloc, so we don't have to check retval in every iteration of copying */
    *dst = UA_calloc(size, type->memSize);
    if(!*dst)
        return UA_STATUSCODE_BADOUTOFMEMORY;

    if(type->pointerFree) {
        memcpy(*dst, src, type->memSize * size);
        return UA_STATUSCODE_GOOD;
    }

    uintptr_t ptrs = (uintptr_t)src;
    uintptr_t ptrd = (uintptr_t)*dst;
    UA_StatusCode retval = UA_STATUSCODE_GOOD;
    for(size_t i = 0; i < size; ++i) {
        retval |= UA_copy((void*)ptrs, (void*)ptrd, type);
        ptrs += type->memSize;
        ptrd += type->memSize;
    }
    if(retval != UA_STATUSCODE_GOOD) {
        UA_Array_delete(*dst, size, type);
        *dst = NULL;
    }
    return retval;
}

void
UA_Array_delete(void *p, size_t size, const UA_DataType *type) {
    if(!type->pointerFree) {
        uintptr_t ptr = (uintptr_t)p;
        for(size_t i = 0; i < size; ++i) {
            UA_deleteMembers((void*)ptr, type);
            ptr += type->memSize;
        }
    }
    UA_free((void*)((uintptr_t)p & ~(uintptr_t)UA_EMPTY_ARRAY_SENTINEL));
}

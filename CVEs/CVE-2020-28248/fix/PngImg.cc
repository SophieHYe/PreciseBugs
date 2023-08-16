#include "./PngImg.h"
#include "./PngStructs.h"

#include <png.h>
#include <string.h>
#include <memory>
#include <algorithm>

using namespace std;

struct BufPtr {
    const char* ptr;
    size_t len;
};

///
void readFromBuf(png_structp pngPtr, png_bytep data, png_size_t length) {
    BufPtr* bufPtr = (BufPtr*)png_get_io_ptr(pngPtr);

    memcpy((char*)data, bufPtr->ptr, length);
    bufPtr->ptr += length;
    bufPtr->len -= length;
}

///
PngImg::PngImg(const char* buf, const size_t bufLen)
    : data_(nullptr)
{
    memset(&info_, 0, sizeof(info_));
    PngReadStruct rs;
    if(rs.Valid()) {
        BufPtr bufPtr = {buf, bufLen};
        png_set_read_fn(rs.pngPtr, (png_voidp)&bufPtr, readFromBuf);
        ReadInfo_(rs);

        InitStorage_();
        png_read_image(rs.pngPtr, &rowPtrs_[0]);
    }
}

///
PngImg::~PngImg() {
    if(data_) delete [] data_;
}

///
void PngImg::ReadInfo_(PngReadStruct& rs) {
    png_read_info(rs.pngPtr, rs.infoPtr);
    info_.width = png_get_image_width(rs.pngPtr, rs.infoPtr);
    info_.height = png_get_image_height(rs.pngPtr, rs.infoPtr);
    info_.bit_depth = png_get_bit_depth(rs.pngPtr, rs.infoPtr);
    info_.color_type = png_get_color_type(rs.pngPtr, rs.infoPtr);
    info_.interlace_type = png_get_interlace_type(rs.pngPtr, rs.infoPtr);
    info_.compression_type = png_get_compression_type(rs.pngPtr, rs.infoPtr);
    info_.filter_type = png_get_filter_type(rs.pngPtr, rs.infoPtr);
    info_.rowbytes = png_get_rowbytes(rs.pngPtr, rs.infoPtr);
    info_.pxlsize = info_.rowbytes / info_.width;
}

///
void PngImg::InitStorage_() {
    rowPtrs_.resize(info_.height, nullptr);
    // Extend height and rowbytes from uint32_t to size_t to avoid multiplication overflow when size_t is larger
    size_t h = info_.height;
    size_t rb = info_.rowbytes;
    // We need to make sure that info_.height * info_.rowbytes will not overflow size_t
    // Unfotunately, there's no simple and portable way to do this in C++
    // For integer division of positive numbers a * b > c <==> a > c / b holds
    if (h > std::numeric_limits<size_t>::max() / rb) {
        // TODO Propagate this exception to JS, and test it
        throw std::runtime_error("Image is too large to allocate single buffer");
    }
    data_ = new png_byte[h * rb];

    for(size_t i = 0; i < info_.height; ++i) {
        rowPtrs_[i] = data_ + i * rb;
    }
}

///
unique_ptr<Pxl> PngImg::Get(png_uint_32 x, png_uint_32 y) const
{
    if(x >= info_.width || y >= info_.height)
    {
        error_ = "Out of the bounds";
        return nullptr;
    }

    png_bytep p = rowPtrs_[y] + info_.pxlsize * x;
    unique_ptr<Pxl> pPxl(new Pxl{0, 0, 0, 0});
    pPxl->r = p[0];
    pPxl->g = p[1];
    pPxl->b = p[2];
    pPxl->a = info_.pxlsize > 3 ? p[3] : 255;

    return pPxl;
}

///
bool PngImg::Fill(png_uint_32 offsetX, png_uint_32 offsetY, png_uint_32 width, png_uint_32 height, const Pxl& pxl)
{
    if(!InBounds_(offsetX, offsetY, width, height))
    {
        error_ = "Out of the bounds";
        return false;
    }

    for(size_t i = 0; i < height; ++i) {
        for(size_t j = 0; j < width; ++j) {
            Set_(offsetX + j, offsetY + i, pxl);
        }
    }

    return true;
}

///
void PngImg::Set_(png_uint_32 x, png_uint_32 y, const Pxl& pxl)
{
    png_bytep p = rowPtrs_[y] + info_.pxlsize * x;
    p[0] = pxl.r;
    p[1] = pxl.g;
    p[2] = pxl.b;
    if(info_.pxlsize > 3) {
        p[3] = pxl.a;
    }
}

///
bool PngImg::Crop(png_uint_32 offsetX, png_uint_32 offsetY, png_uint_32 width, png_uint_32 height)
{
    if(!InBounds_(offsetX, offsetY, width, height))
    {
        error_ = "Out of the bounds";
        return false;
    }

    for(size_t i = 0; i < height; ++i) {
        rowPtrs_[i] = rowPtrs_[i + offsetY] + offsetX * info_.pxlsize;
    }
    rowPtrs_.resize(height);

    info_.width = width;
    info_.height = height;
    info_.rowbytes = info_.pxlsize * width;
    return true;
}

///
bool PngImg::InBounds_(png_uint_32 offsetX, png_uint_32 offsetY, png_uint_32 width, png_uint_32 height) const
{
    return width != 0
        && height != 0
        && width <= info_.width
        && height <= info_.height
        && offsetX < info_.width
        && offsetY < info_.height
        && offsetX + width <= info_.width
        && offsetY + height <= info_.height;
}

///
void PngImg::SetSize(png_uint_32 width, png_uint_32 height)
{
    const ImgInfo oldInfo = info_;
    const unique_ptr<png_byte[]> oldData{data_};
    const vector<png_bytep> oldRowPtrs{rowPtrs_};

    info_.width = width;
    info_.height = height;
    info_.rowbytes = info_.pxlsize * width;

    InitStorage_();
    memset(data_, 0, info_.height * info_.rowbytes);
    CopyRows_(oldRowPtrs, min(height, oldInfo.height), min(oldInfo.rowbytes, info_.rowbytes));
}

///
void PngImg::Insert(const PngImg& img, png_uint_32 offsetX, png_uint_32 offsetY)
{
    if(info_.pxlsize == img.info_.pxlsize) {
        CopyRows_(img.rowPtrs_, img.info_.height, img.info_.rowbytes, offsetX, offsetY);
    } else {
        CopyPxlByPxl_(img, offsetX, offsetY);
    }
}

///
void PngImg::CopyPxlByPxl_(const PngImg& img, png_uint_32 offsetX, png_uint_32 offsetY)
{
    for(size_t x = 0; x < img.info_.width; ++x) {
        for(size_t y = 0; y < img.info_.height; ++y) {
            Set_(offsetX + x, offsetY + y, *img.Get(x, y));
        }
    }
}

///
void PngImg::CopyRows_(const vector<png_bytep>& rowPtrs, const size_t numRows, const size_t rowLen,
    png_uint_32 offsetX, png_uint_32 offsetY)
{
    for(size_t y = 0; y < numRows; ++y) {
        memcpy(rowPtrs_[y + offsetY] + offsetX * info_.pxlsize, rowPtrs[y], rowLen);
    }
}

///
void PngImg::RotateRight() {
    Rotate_([](const Point& p, const ImgInfo& img) {
        return Point{img.height - p.y - 1, p.x};
    });
}

///
void PngImg::RotateLeft() {
    Rotate_([](const Point& p, const ImgInfo& img) {
        return Point{p.y, img.width - p.x - 1};
    });
}

///
void PngImg::Rotate_(function<Point(const Point&, const ImgInfo&)> moveFn) {
    const ImgInfo oldInfo = info_;
    const unique_ptr<png_byte[]> oldData{data_};
    const vector<png_bytep> oldRowPtrs{rowPtrs_};

    info_.width = oldInfo.height;
    info_.height = oldInfo.width;
    info_.rowbytes = info_.pxlsize * info_.width;

    InitStorage_();

    for(size_t x = 0; x < oldInfo.width; ++x) {
        for(size_t y = 0; y < oldInfo.height; ++y) {
            auto newPoint = moveFn({x, y}, oldInfo);

            png_bytep p = oldRowPtrs[y] + oldInfo.pxlsize * x;
            png_bytep newP = rowPtrs_[newPoint.y] + info_.pxlsize * newPoint.x;

            copy(p, p + info_.pxlsize, newP);
        }
    }
}

///
bool PngImg::Write(const string& file) {
    auto fileClose = [](FILE* fp){ if(fp) fclose(fp); };
    unique_ptr<FILE, decltype(fileClose)> fp(fopen(file.c_str(), "wb"), fileClose);
    if(!fp) {
        error_ = "Can't open file for writing";
        return false;
    }

    PngWriteStruct pws;
    if(!pws.Valid()) {
        error_ = "Can't create png structs";
        return false;
    }

    if(setjmp(png_jmpbuf(pws.pngPtr))) {
        error_ = "Can't write file";
        return false;
    }

    png_init_io(pws.pngPtr, fp.get());
    png_set_IHDR(pws.pngPtr, pws.infoPtr,
        info_.width,
        info_.height,
        info_.bit_depth,
        info_.color_type,
        info_.interlace_type,
        info_.compression_type,
        info_.filter_type
    );
    png_set_rows(pws.pngPtr, pws.infoPtr, &rowPtrs_[0]);
    png_write_png(pws.pngPtr, pws.infoPtr, PNG_TRANSFORM_IDENTITY, NULL);

    return true;
}

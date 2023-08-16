// anti-copyright Lucy Phipps 2022
// vi: sw=2 tw=80
#define VERSION "v1.0.4"
#include <errno.h>
#include <inttypes.h>
#include <limits.h>
#include <setjmp.h>
#include <stdbool.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#if CHAR_BIT != 8
#error "char isn't 8-bit"
#endif
#if __STDC_VERSION__ < 201112L && !defined NOFOPENX
#define NOFOPENX
#endif
#ifdef NOFOPENX
#include <fcntl.h>
#include <sys/stat.h>
#ifndef O_BINARY
#define O_BINARY 0
#endif
#endif
#ifdef _WIN32
#define _CRT_NONSTDC_NO_WARNINGS
#include <fcntl.h>
#include <io.h>
#else
#include <unistd.h>
#define setmode(x, y) 0
#endif
#include "png.h"
#include "webp/decode.h"
#include "webp/encode.h"
static int help(void) {
  fputs("PNG2WebP " VERSION "\n\
\n\
Usage:\n\
png2webp [-refv-] INFILE ...\n\
png2webp -p[refv-] [{INFILE|-} [OUTFILE|-]]\n\
\n\
-p: Work with a single file, allowing Piping from stdin or to stdout,\n\
    or using a different output filename to the input.\n\
    `INFILE` and `OUTFILE` default to stdin and stdout respectively,\n\
    or explicitly as \"-\".\n\
    Will show this message if stdin/stdout is used and is a terminal.\n\
-r: Convert from WebP to PNG instead.\n\
-e: Keep RGB data on pixels where alpha is 0. Always enabled for `-r`.\n\
-f: Force overwrite of output files (has no effect on stdout).\n\
-v: Be verbose.\n\
--: Explicitly stop parsing options.\n",
    stderr);
  return -1;
}
static bool exact = 0, force = 0, verbose = 0;
#define PF(x, ...) fprintf(stderr, x "\n", __VA_ARGS__)
#define PFV(...) \
  if(verbose) PF(__VA_ARGS__)
#define IP (ip ? ip : "<stdin>")
#define OP (op ? op : "<stdout>")
static FILE *openr(char *ip) {
  PFV("Decoding %s ...", IP);
  if(!ip) return stdin;
  FILE *fp;
#ifdef NOFOPENX
  int fd = open(ip, O_RDONLY | O_BINARY);
  if(fd == -1) {
    PF("ERROR opening %s for %s: %s", ip, "reading", strerror(errno));
    return 0;
  }
  if(!(fp = fdopen(fd, "rb"))) {
    PF("ERROR opening %s for %s: %s", ip, "reading", strerror(errno));
    close(fd);
    return 0;
  }
#else
  if(!(fp = fopen(ip, "rb"))) {
    PF("ERROR opening %s for %s: %s", ip, "reading", strerror(errno));
    return 0;
  }
#endif
  return fp;
}
static FILE *openw(char *op) {
  PFV("Encoding %s ...", OP);
  if(!op) return stdout;
  FILE *fp;
#define EO(x) \
  if(!(x)) { \
    PF("ERROR opening %s for %s: %s", op, force ? "writing" : "creation", \
      strerror(errno)); \
    return 0; \
  }
#ifdef NOFOPENX
  int fd = open(op, O_WRONLY | O_CREAT | O_TRUNC | (!force * O_EXCL) | O_BINARY,
#ifdef _WIN32
    S_IREAD | S_IWRITE
#else
    S_IRUSR | S_IWUSR | S_IRGRP | S_IWGRP | S_IROTH | S_IWOTH
#endif
  );
  EO(fd != -1)
  if(!(fp = fdopen(fd, "wb"))) {
    PF("ERROR opening %s for %s: %s", op, force ? "writing" : "creation",
      strerror(errno));
    close(fd);
    remove(op);
    return 0;
  }
#else
  EO(fp = fopen(op, force ? "wb" : "wbx"))
#endif
  return fp;
}
static size_t pnglen;
static void pngread(png_struct *p, uint8_t *d, size_t s) {
  if(!fread(d, s, 1, png_get_io_ptr(p))) png_error(p, "I/O error");
  pnglen += s;
}
static void pngwrite(png_struct *p, uint8_t *d, size_t s) {
  if(!fwrite(d, s, 1, png_get_io_ptr(p))) png_error(p, "I/O error");
  pnglen += s;
}
static void pngflush(png_struct *p) {
#ifdef DOFLUSH
  fflush(png_get_io_ptr(p));
#else
  (void)p;
#endif
}
static void pngrerr(png_struct *p, const char *s) {
  PF("ERROR reading %s: %s", (char *)png_get_error_ptr(p), s);
  png_longjmp(p, 1);
}
static void pngwerr(png_struct *p, const char *s) {
  PF("ERROR writing %s: %s", (char *)png_get_error_ptr(p), s);
  png_longjmp(p, 1);
}
static void pngwarn(png_struct *p, const char *s) {
  PF("Warning: %s: %s", (char *)png_get_error_ptr(p), s);
}
static int webpwrite(const uint8_t *d, size_t s, const WebPPicture *p) {
  return (int)fwrite(d, s, 1, p->custom_ptr);
}
#define E(x, ...) \
  if(!(x)) { \
    PF("ERROR " __VA_ARGS__); \
    return 1; \
  }
static bool p2w(char *ip, char *op) {
  FILE *fp = openr(ip);
  if(!fp) return 1;
  uint32_t *b = 0;
  png_info *n = 0;
  char *k[] = {"Out of memory",
    "???", // oom flushing bitstream, unused in libwebp
    "???", // null param
    "Broken config, file a bug report",
    "???", // image too big (already checked)
    "???", "???", // lossy
    "I/O error",
    "???", // lossy
    "???"}; // canceled
  png_struct *p =
    png_create_read_struct(PNG_LIBPNG_VER_STRING, ip, pngrerr, pngwarn);
  if(!p) {
    PF("ERROR reading %s: %s", IP, *k);
    goto p2w_close;
  }
  n = png_create_info_struct(p);
  if(!n) {
    PF("ERROR reading %s: %s", IP, *k);
    goto p2w_close;
  }
  if(setjmp(png_jmpbuf(p))) {
  p2w_close:
    fclose(fp);
    png_destroy_read_struct(&p, &n, 0);
  p2w_free:
    free(b);
    return 1;
  }
  pnglen = 0;
  png_set_read_fn(p, fp, pngread);
  png_read_info(p, n);
  uint32_t width, height;
  int bitdepth, colortype;
  png_get_IHDR(p, n, &width, &height, &bitdepth, &colortype, 0, 0, 0);
  if(width > 16383 || height > 16383) {
    PF("ERROR reading %s: Image too big (%" PRIu32 " x %" PRIu32
       ", max. 16383 x 16383 px)",
      IP, width, height);
    goto p2w_close;
  }
  if((unsigned)bitdepth > 8)
    PF("Warning: %s is 16-bit, will be downsampled to 8-bit", IP);
  bool trns = png_get_valid(p, n, PNG_INFO_tRNS);
#ifdef FIXEDGAMMA
#define GAMMA ((uint32_t)gamma) / 1e5
  int32_t gamma = 45455;
  if(png_get_valid(p, n, PNG_INFO_sRGB) || png_get_gAMA_fixed(p, n, &gamma))
    png_set_gamma_fixed(p, 22e4, gamma);
#else
#define GAMMA gamma
  double gamma = 1 / 2.2;
  if(png_get_valid(p, n, PNG_INFO_sRGB) || png_get_gAMA(p, n, &gamma))
    png_set_gamma(p, 2.2, gamma);
#endif
#define S(x) png_set_##x(p)
  S(scale_16);
  S(expand);
  S(gray_to_rgb);
  S(packing);
  if(*(uint8_t *)&(uint16_t){1}) {
    S(bgr);
    png_set_add_alpha(p, 255, PNG_FILLER_AFTER);
  } else {
    // TODO: test somehow
    S(swap_alpha);
    png_set_add_alpha(p, 255, PNG_FILLER_BEFORE);
  }
  int passes = S(interlace_handling);
  png_read_update_info(p, n);
#ifndef NDEBUG
  size_t rowbytes = png_get_rowbytes(p, n);
  if(rowbytes != (size_t)4 * width) {
    PF("ERROR reading %s: rowbytes is %zu, should be %zu", IP, rowbytes,
      (size_t)4 * width);
    goto p2w_close;
  }
#endif
  b = malloc(width * height * 4);
  if(!b) {
    PF("ERROR reading %s: %s", IP, *k);
    goto p2w_close;
  }
  for(unsigned x = (unsigned)passes; x; x--) {
    uint8_t *w = (uint8_t *)b;
    for(unsigned y = height; y; y--) {
      png_read_row(p, w, 0);
      w += width * 4;
    }
  }
  png_read_end(p, 0);
  png_destroy_read_struct(&p, &n, 0);
  fclose(fp);
  char *f[] = {
    "greyscale", "???", "RGB", "paletted", "greyscale + alpha", "???", "RGBA"};
  PFV("Info: %s:\nDimensions: %" PRIu32 " x %" PRIu32
      "\nSize: %zu bytes (%.15g bpp)\nFormat: %u-bit %s%s%s\nGamma: %.5g",
    IP, width, height, pnglen, (double)pnglen * 8 / (width * height), bitdepth,
    f[(unsigned)colortype], trns ? ", with transparency" : "",
    (unsigned)passes > 1 ? ", interlaced" : "", GAMMA);
  WebPConfig c;
  if(!WebPConfigPreset(&c, WEBP_PRESET_ICON, 100)) {
    PF("ERROR writing %s: %s", OP, k[3]);
    goto p2w_free;
  }
  if(!(fp = openw(op))) goto p2w_free;
  c.lossless = 1;
  c.method = 6;
#ifndef NOTHREADS
  c.thread_level = 1; // doesn't seem to affect output
#endif
  c.exact = exact;
  WebPAuxStats s;
  WebPPicture o = {1, .width = (int)width, (int)height, .argb = b,
    .argb_stride = (int)width, .writer = webpwrite, .custom_ptr = fp,
    .stats = verbose ? &s : 0};
  // progress_hook only reports 1, 5, 90, 100 for lossless
  trns = (trns || (colortype & PNG_COLOR_MASK_ALPHA)) &&
    WebPPictureHasTransparency(&o);
  if(!WebPEncode(&c, &o)) {
    PF("ERROR writing %s: %s", OP, k[o.error_code - 1]);
    fclose(fp);
  p2w_rm:
    if(op) remove(op);
    goto p2w_free;
  }
  if(fclose(fp)) {
    PF("ERROR closing %s: %s", OP, strerror(errno));
    goto p2w_rm;
  }
  free(b);
#define F s.lossless_features
#define C s.palette_size
  PFV("Info: %s:\nDimensions: %u x %u\nSize: %u bytes (%.15g bpp)\n\
Header size: %u, image data size: %u\nUses alpha: %s\n\
Precision bits: histogram=%u transform=%u cache=%u\n\
Lossless features:%s%s%s%s\nColors: %s%u",
    OP, o.width, o.height, s.lossless_size,
    (unsigned)s.lossless_size * 8. / (unsigned)(o.width * o.height),
    s.lossless_hdr_size, s.lossless_data_size, trns ? "yes" : "no",
    s.histogram_bits, s.transform_bits, s.cache_bits,
    F ? F & 1 ? " prediction" : "" : " none", F && F & 2 ? " cross-color" : "",
    F && F & 4 ? " subtract-green" : "", F && F & 8 ? " palette" : "",
    C ? "" : ">", C ? C : 256);
  return 0;
}
static bool w2p(char *ip, char *op) {
  FILE *fp = openr(ip);
  if(!fp) return 1;
  bool openwdone = 0;
  uint8_t *x = 0, *b = 0;
  png_struct *p = 0;
  png_info *n = 0;
  uint8_t i[12];
  char *k[] = {"Out of memory", "Broken config, file a bug report",
    "Invalid WebP", "???", "???", "???", "I/O error"};
  // unsupported feature, suspended, canceled
  if(!fread(i, 12, 1, fp)) {
    PF("ERROR reading %s: %s", IP, k[6]);
    goto w2p_close;
  }
  if(memcmp(i, (char[4]){"RIFF"}, 4) || memcmp(i + 8, (char[4]){"WEBP"}, 4)) {
    PF("ERROR reading %s: %s", IP, k[2]);
    goto w2p_close;
  }
  size_t l = ((uint32_t)(i[4] | (i[5] << 8) | (i[6] << 16) | (i[7] << 24))) + 8;
  // ^ RIFF header size
  x = malloc(l);
  if(!x) {
    PF("ERROR reading %s: %s", IP, *k);
    goto w2p_close;
  }
  memcpy(x, i, 12); // should optimize out
  if(!fread(x + 12, l - 12, 1, fp)) {
    PF("ERROR reading %s: %s", IP, k[6]);
    goto w2p_close;
  }
  fclose(fp);
#if defined LOSSYISERROR || defined NOTHREADS
  WebPBitstreamFeatures I;
#else
  WebPDecoderConfig c = {.options.use_threads = 1};
#define I c.input
#endif
  VP8StatusCode r = WebPGetFeatures(x, l, &I);
  if(r) {
    PF("ERROR reading %s: %s", IP, k[r - 1]);
    goto w2p_free;
  }
#define V I.format
#define W ((uint32_t)I.width)
#define H ((uint32_t)I.height)
#define A I.has_alpha
#ifdef LOSSYISERROR
#define FMTSTR
#define FMTARG
#define ANMSTR "%s"
#define ANMARG , "animat"
#else
  char *f[] = {"undefined/mixed", "lossy", "lossless"};
#define FMTSTR "\nFormat: %s"
#define FMTARG , f[V]
#define ANMSTR "animat"
#define ANMARG
#endif
  PFV("Info: %s:\nDimensions: %" PRIu32 " x %" PRIu32
      "\nSize: %zu bytes (%.15g bpp)\nUses alpha: %s" FMTSTR,
    IP, W, H, l, (double)l * 8 / (W * H), A ? "yes" : "no" FMTARG);
  if(I.has_animation) {
    PF("ERROR reading %s: Unsupported feature: " ANMSTR "ion", IP ANMARG);
    goto w2p_free;
  }
#ifdef LOSSYISERROR
  if(V != 2) {
    PF("ERROR reading %s: Unsupported feature: %sion", IP, "lossy compress");
    goto w2p_free;
  }
#endif
#define B ((unsigned)(3 + A))
  b = malloc(W * H * B);
  if(!b) {
    PF("ERROR reading %s: %s", IP, *k);
    goto w2p_free;
  }
#if defined LOSSYISERROR || defined NOTHREADS
  if(!(A ? WebPDecodeRGBAInto : WebPDecodeRGBInto)(
       x, l, b, W * H * B, (int)(W * B))) {
    PF("ERROR reading %s: %s", IP, k[2]);
    goto w2p_free;
  }
#else
  c.output.colorspace = A ? MODE_RGBA : MODE_RGB;
  c.output.is_external_memory = 1;
#define D c.output.u.RGBA
  D.rgba = b;
  D.stride = (int)(W * B);
  D.size = W * H * B;
  r = WebPDecode(x, l, &c);
  if(r) {
    PF("ERROR reading %s: %s", IP, k[r - 1]);
    goto w2p_free;
  }
#endif
  free(x);
  x = 0;
  if(!(fp = openw(op))) goto w2p_free;
  openwdone = !!op;
  p = png_create_write_struct(PNG_LIBPNG_VER_STRING, op, pngwerr, pngwarn);
  if(!p) {
    PF("ERROR writing %s: %s", OP, *k);
    goto w2p_close;
  }
  n = png_create_info_struct(p);
  if(!n) {
    PF("ERROR writing %s: %s", OP, *k);
    goto w2p_close;
  }
  if(setjmp(png_jmpbuf(p))) {
  w2p_close:
    fclose(fp);
  w2p_free:
    if(openwdone) remove(op);
    free(x);
    free(b);
    png_destroy_write_struct(&p, &n);
    return 1;
  }
  pnglen = 0;
  png_set_write_fn(p, fp, pngwrite, pngflush);
  png_set_filter(p, 0, PNG_ALL_FILTERS);
  png_set_compression_level(p, 9);
  // png_set_compression_memlevel(p, 9);
  png_set_IHDR(p, n, W, H, 8, A ? 6 : 2, 0, 0, 0);
  png_write_info(p, n);
  uint8_t *w = b;
  for(unsigned y = H; y; y--) {
    png_write_row(p, w);
    w += W * B;
  }
  png_write_end(p, n);
  png_destroy_write_struct(&p, &n);
  p = 0;
  n = 0;
  free(b);
  b = 0;
  if(fclose(fp)) {
    PF("ERROR closing %s: %s", OP, strerror(errno));
    goto w2p_free;
  }
  PFV("Info: %s:\nDimensions: %" PRIu32 " x %" PRIu32
      "\nSize: %zu bytes (%.15g bpp)\nFormat: %u-bit %s%s%s\nGamma: %.5g",
    OP, W, H, pnglen, (double)pnglen * 8 / (W * H), 8, A ? "RGBA" : "RGB", "",
    "", 1 / 2.2);
  return 0;
}
int main(int argc, char **argv) {
  { // should optimize out
    uint32_t endian;
    memcpy(&endian, (char[4]){"\xAA\xBB\xCC\xDD"}, 4);
    E(endian == 0xAABBCCDD || endian == 0xDDCCBBAA,
      "32-bit mixed-endianness (%X) not supported", endian)
  }
  bool pipe = 0, usestdin = 0, usestdout = 0, reverse = 0;
#ifdef USEGETOPT
  for(int c; (c = getopt(argc, argv, ":prefv")) != -1;)
    switch(c)
#else
  while(--argc && **++argv == '-' && argv[0][1])
    while(*++*argv)
      switch(**argv)
#endif
    {
      case 'p': pipe = 1; break;
      case 'r': reverse = 1; break;
      case 'e': exact = 1; break;
      case 'f': force = 1; break;
      case 'v': verbose = 1; break;
#ifndef USEGETOPT
      case '-':
	if(argv[0][1]) return help();
	argc--;
	argv++;
	goto endflagloop;
#endif
      default: return help();
    }
#ifdef USEGETOPT
  argc -= optind;
  argv += optind;
#else
endflagloop:
#endif
#define URGC (unsigned)argc
#define PIPEARG(x) (*argv[x] == '-' && !argv[x][1])
  if(pipe) {
    if(URGC > 2 || ((usestdin = (!argc || PIPEARG(0))) && isatty(0)) ||
      ((usestdout = (URGC < 2 || PIPEARG(1))) && isatty(1)))
      return help();
    if(usestdin) setmode(0, O_BINARY);
    if(usestdout) setmode(1, O_BINARY);
    return (reverse ? w2p : p2w)(usestdin ? 0 : *argv, usestdout ? 0 : argv[1]);
  }
  if(!argc) return help();
  bool ret = 0;
  if(reverse)
    for(; argc; argc--, argv++) {
      size_t len = strlen(*argv);
      if(len > 4) {
	uint32_t ext, extmatch;
	memcpy(&ext, *argv + len - 4, 4);
	memcpy(&extmatch, (char[4]){"webp"}, 4);
	if(argv[0][len - 5] == '.' && (ext | 0x20202020) == extmatch) len -= 5;
      }
      {
#if defined __STDC_NO_VLA__ && !defined NOVLA
#define NOVLA
#endif
#ifdef NOVLA
	char *op = malloc(len + 5);
	E(op, "adding .%s extension to %s: Out of memory", "png", *argv)
#else
#pragma GCC diagnostic push
#pragma GCC diagnostic ignored "-Wvla"
	char op[len + 5];
#pragma GCC diagnostic pop
#endif
	memcpy(op, *argv, len); // the only real memcpy
	memcpy(op + len, ".png", 5);
	ret = w2p(*argv, op) || ret;
#ifdef NOVLA
	free(op);
#endif
      }
    }
  else
    for(; argc; argc--, argv++) {
      size_t len = strlen(*argv);
      if(len > 3) {
	uint32_t ext, extmask, extmatch;
	memcpy(&ext, *argv + len - 4, 4);
	memcpy(&extmask, (char[4]){"\0   "}, 4);
	memcpy(&extmatch, (char[4]){".png"}, 4);
	if((ext | extmask) == extmatch) len -= 4;
      }
      {
#ifdef NOVLA
	char *op = malloc(len + 6);
	E(op, "adding .%s extension to %s: Out of memory", "webp", *argv)
#else
#pragma GCC diagnostic push
#pragma GCC diagnostic ignored "-Wvla"
	char op[len + 6];
#pragma GCC diagnostic pop
#endif
	memcpy(op, *argv, len); // the only real memcpy
	memcpy(op + len, ".webp", 6);
	ret = p2w(*argv, op) || ret;
#ifdef NOVLA
	free(op);
#endif
      }
    }
  return ret;
}

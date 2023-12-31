//========================================================================
//
// Parser.cc
//
// Copyright 1996-2003 Glyph & Cog, LLC
//
//========================================================================

//========================================================================
//
// Modified under the Poppler project - http://poppler.freedesktop.org
//
// All changes made under the Poppler project to this file are licensed
// under GPL version 2 or later
//
// Copyright (C) 2006, 2009, 201, 2010, 2013, 2014, 2017, 2018 Albert Astals Cid <aacid@kde.org>
// Copyright (C) 2006 Krzysztof Kowalczyk <kkowalczyk@gmail.com>
// Copyright (C) 2009 Ilya Gorenbein <igorenbein@finjan.com>
// Copyright (C) 2012 Hib Eris <hib@hiberis.nl>
// Copyright (C) 2013 Adrian Johnson <ajohnson@redneon.com>
// Copyright (C) 2013 Thomas Freitag <Thomas.Freitag@alfa.de>
// Copyright (C) 2018 Klarälvdalens Datakonsult AB, a KDAB Group company, <info@kdab.com>. Work sponsored by the LiMux project of the city of Munich
// Copyright (C) 2018 Adam Reichold <adam.reichold@t-online.de>
// Copyright (C) 2018 Marek Kasik <mkasik@redhat.com>
//
// To see a description of the changes please see the Changelog file that
// came with your tarball or type make ChangeLog if you are building from git
//
//========================================================================

#include <config.h>

#include <stddef.h>
#include "Object.h"
#include "Array.h"
#include "Dict.h"
#include "Decrypt.h"
#include "Parser.h"
#include "XRef.h"
#include "Error.h"

// Max number of nested objects.  This is used to catch infinite loops
// in the object structure. And also technically valid files with
// lots of nested arrays that made us consume all the stack
#define recursionLimit 500

Parser::Parser(XRef *xrefA, Lexer *lexerA, bool allowStreamsA) {
  xref = xrefA;
  lexer = lexerA;
  inlineImg = 0;
  allowStreams = allowStreamsA;
  buf1 = lexer->getObj();
  buf2 = lexer->getObj();
}

Parser::~Parser() {
  delete lexer;
}

Object Parser::getObj(int recursion)
{
  return getObj(false, nullptr, cryptRC4, 0, 0, 0, recursion);
}

Object Parser::getObj(bool simpleOnly,
           Guchar *fileKey,
		       CryptAlgorithm encAlgorithm, int keyLength,
		       int objNum, int objGen, int recursion,
		       bool strict) {
  Object obj;
  Stream *str;
  DecryptStream *decrypt;
  const GooString *s;
  GooString *s2;
  int c;

  // refill buffer after inline image data
  if (inlineImg == 2) {
    buf1 = lexer->getObj();
    buf2 = lexer->getObj();
    inlineImg = 0;
  }

  if (unlikely(recursion >= recursionLimit)) {
    return Object(objError);
  }

  // array
  if (!simpleOnly && buf1.isCmd("[")) {
    shift();
    obj = Object(new Array(xref));
    while (!buf1.isCmd("]") && !buf1.isEOF() && recursion + 1 < recursionLimit) {
      Object obj2 = getObj(false, fileKey, encAlgorithm, keyLength, objNum, objGen, recursion + 1);
      obj.arrayAdd(std::move(obj2));
    }
    if (recursion + 1 >= recursionLimit && strict) goto err;
    if (buf1.isEOF()) {
      error(errSyntaxError, getPos(), "End of file inside array");
      if (strict) goto err;
    }
    shift();

  // dictionary or stream
  } else if (!simpleOnly && buf1.isCmd("<<")) {
    shift(objNum);
    obj = Object(new Dict(xref));
    while (!buf1.isCmd(">>") && !buf1.isEOF()) {
      if (!buf1.isName()) {
	error(errSyntaxError, getPos(), "Dictionary key must be a name object");
	if (strict) goto err;
	shift();
      } else {
	// buf1 will go away in shift(), so keep the key
	const auto key = std::move(buf1);
	shift();
	if (buf1.isEOF() || buf1.isError()) {
	  if (strict && buf1.isError()) goto err;
	  break;
	}
	Object obj2 = getObj(false, fileKey, encAlgorithm, keyLength, objNum, objGen, recursion + 1);
	if (unlikely(obj2.isError() && recursion + 1 >= recursionLimit)) {
	  break;
	}
	obj.dictAdd(key.getName(), std::move(obj2));
      }
    }
    if (buf1.isEOF()) {
      error(errSyntaxError, getPos(), "End of file inside dictionary");
      if (strict) goto err;
    }
    // stream objects are not allowed inside content streams or
    // object streams
    if (buf2.isCmd("stream")) {
      if (allowStreams && (str = makeStream(std::move(obj), fileKey, encAlgorithm, keyLength,
                                            objNum, objGen, recursion + 1,
                                            strict))) {
        return Object(str);
      } else {
        return Object(objError);
      }
    } else {
      shift();
    }

  // indirect reference or integer
  } else if (buf1.isInt()) {
    const int num = buf1.getInt();
    shift();
    if (buf1.isInt() && buf2.isCmd("R")) {
      const int gen = buf1.getInt();
      shift();
      shift();

      if (unlikely(num <= 0 || gen < 0)) {
          return Object();
      }

      return Object(num, gen);
    } else {
      return Object(num);
    }

  // string
  } else if (buf1.isString() && fileKey) {
    s = buf1.getString();
    s2 = new GooString();
    decrypt = new DecryptStream(new MemStream(s->getCString(), 0, s->getLength(), Object(objNull)),
				fileKey, encAlgorithm, keyLength,
				objNum, objGen);
    decrypt->reset();
    while ((c = decrypt->getChar()) != EOF) {
      s2->append((char)c);
    }
    delete decrypt;
    obj = Object(s2);
    shift();

  // simple object
  } else {
    // avoid re-allocating memory for complex objects like strings by
    // shallow copy of <buf1> to <obj> and nulling <buf1> so that
    // subsequent buf1.free() won't free this memory
    obj = std::move(buf1);
    shift();
  }

  return obj;

err:
  return Object(objError);
}

Stream *Parser::makeStream(Object &&dict, Guchar *fileKey,
			   CryptAlgorithm encAlgorithm, int keyLength,
			   int objNum, int objGen, int recursion,
                           bool strict) {
  BaseStream *baseStr;
  Stream *str;
  Goffset length;
  Goffset pos, endPos;
  XRefEntry *entry = nullptr;

  if (xref && (entry = xref->getEntry(objNum, false))) {
    if (!entry->getFlag(XRefEntry::Parsing) ||
        (objNum == 0 && objGen == 0)) {
      entry->setFlag(XRefEntry::Parsing, true);
    } else {
      error(errSyntaxError, getPos(),
            "Object '{0:d} {1:d} obj' is being already parsed", objNum, objGen);
      return nullptr;
    }
  }

  // get stream start position
  lexer->skipToNextLine();
  if (!(str = lexer->getStream())) {
    return nullptr;
  }
  pos = str->getPos();

  // get length
  Object obj = dict.dictLookup("Length", recursion);
  if (obj.isInt()) {
    length = obj.getInt();
  } else if (obj.isInt64()) {
    length = obj.getInt64();
  } else {
    error(errSyntaxError, getPos(), "Bad 'Length' attribute in stream");
    if (strict) return nullptr;
    length = 0;
  }

  // check for length in damaged file
  if (xref && xref->getStreamEnd(pos, &endPos)) {
    length = endPos - pos;
  }

  // in badly damaged PDF files, we can run off the end of the input
  // stream immediately after the "stream" token
  if (!lexer->getStream()) {
    return nullptr;
  }
  baseStr = lexer->getStream()->getBaseStream();

  // skip over stream data
  if (Lexer::LOOK_VALUE_NOT_CACHED != lexer->lookCharLastValueCached) {
      // take into account the fact that we've cached one value
      pos = pos - 1;
      lexer->lookCharLastValueCached = Lexer::LOOK_VALUE_NOT_CACHED;
  }
  if (unlikely(length < 0)) {
      return nullptr;
  }
  if (unlikely(pos > LLONG_MAX - length)) {
      return nullptr;
  }
  lexer->setPos(pos + length);

  // refill token buffers and check for 'endstream'
  shift();  // kill '>>'
  shift("endstream", objNum);  // kill 'stream'
  if (buf1.isCmd("endstream")) {
    shift();
  } else {
    error(errSyntaxError, getPos(), "Missing 'endstream' or incorrect stream length");
    if (strict) return nullptr;
    if (xref && lexer->getStream()) {
      // shift until we find the proper endstream or we change to another object or reach eof
      length = lexer->getPos() - pos;
      if (buf1.isCmd("endstream")) {
        dict.dictSet("Length", Object(length));
      }
    } else {
      // When building the xref we can't use it so use this
      // kludge for broken PDF files: just add 5k to the length, and
      // hope its enough
      if (length < LLONG_MAX - pos - 5000)
        length += 5000;
    }
  }

  // make base stream
  str = baseStr->makeSubStream(pos, true, length, std::move(dict));

  // handle decryption
  if (fileKey) {
    str = new DecryptStream(str, fileKey, encAlgorithm, keyLength,
			    objNum, objGen);
  }

  // get filters
  str = str->addFilters(str->getDict(), recursion);

  if (entry)
    entry->setFlag(XRefEntry::Parsing, false);

  return str;
}

void Parser::shift(int objNum) {
  if (inlineImg > 0) {
    if (inlineImg < 2) {
      ++inlineImg;
    } else {
      // in a damaged content stream, if 'ID' shows up in the middle
      // of a dictionary, we need to reset
      inlineImg = 0;
    }
  } else if (buf2.isCmd("ID")) {
    lexer->skipChar();		// skip char after 'ID' command
    inlineImg = 1;
  }
  buf1 = std::move(buf2);
  if (inlineImg > 0)		// don't buffer inline image data
    buf2.setToNull();
  else {
    buf2 = lexer->getObj(objNum);
  }
}

void Parser::shift(const char *cmdA, int objNum) {
  if (inlineImg > 0) {
    if (inlineImg < 2) {
      ++inlineImg;
    } else {
      // in a damaged content stream, if 'ID' shows up in the middle
      // of a dictionary, we need to reset
      inlineImg = 0;
    }
  } else if (buf2.isCmd("ID")) {
    lexer->skipChar();		// skip char after 'ID' command
    inlineImg = 1;
  }
  buf1 = std::move(buf2);
  if (inlineImg > 0) {
    buf2.setToNull();
  } else if (buf1.isCmd(cmdA)) {
    buf2 = lexer->getObj(objNum);
  } else {
    buf2 = lexer->getObj(cmdA, objNum);
  }
}

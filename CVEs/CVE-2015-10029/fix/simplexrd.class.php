<?php
/*
 * SimpleXRD
 *
 * Copyright (C) Kelvin Mo 2012
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above
 *    copyright notice, this list of conditions and the following
 *    disclaimer in the documentation and/or other materials provided
 *    with the distribution.
 *
 * 3. The name of the author may not be used to endorse or promote
 *    products derived from this software without specific prior
 *    written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS
 * OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE
 * GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER
 * IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
 * OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN
 * IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * A simple XRD parser.
 *
 * This XRD parser supports all the features of XRD which can be translated into
 * its JSON representation under RFC 6415.  This means that the parser does
 * not support extensibility under the XRD specification.
 *
 * Using the parser is straightforward.  Assuming the XRD code has been loaded
 * into a variable called $xml. Then the code is simply
 *
 * <code>
 * $parser = new SimpleXRD();
 * $parser->load($xml);
 * $jrd = $parser->parse();
 * $parser->close();
 * </code>
 *
 * @see http://docs.oasis-open.org/xri/xrd/v1.0/xrd-1.0.html, RFC 6415, RFC 7033
 */
class SimpleXRD {
    /**
     * XRD namespace constant
     */
    const XRD_NS = 'http://docs.oasis-open.org/ns/xri/xrd-1.0';

    /**
     * XSI namespace constant
     */
    const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * XML reader
     * @var resource
     */
    private $reader;

    /**
     * XML namespace constant
     * @var string
     */
    private $XML_NS = 'http://www.w3.org/XML/1998/namespace';

    /**
     * JRD equivalent document
     * @var array
     */
    private $jrd = array();

    /**
     * Creates an instance of the XRD parser.
     *
     * This constructor also initialises the underlying XML parser.
     */
    public function __construct() {
        $this->reader = new XMLReader();
    }

    /**
     * Frees memory associated with the underlying XML parser.
     *
     * Note that only the memory associated with the underlying XML parser is
     * freed.  Memory associated with the class itself is not freed.
     *
     */
    public function close() {
        $this->reader->close();
    }

    /**
     * Loads an XRD document.
     *
     * @param string $xml the XML document to load
     */
    public function load($xml) {
        $this->reader->xml($xml, null, LIBXML_NONET);
    }

    /**
     * Parses the loaded XRD document and returns the JRD-equivalent structure.
     *
     * The $include_expires parameter determines whether the Expires element should
     * be parsed.  Under the original host-meta JRD RFC 6415, the Expires element is
     * part of the JRD specification.  However, that element has been removed from the
     * WebFinger RFC 7033.
     *
     * @param bool $include_expires whether the Expires element should be parsed
     * @return array the JRD equivalent structure
     */
    public function parse($include_expires = false) {
        while ($this->reader->read()) {
            if (($this->reader->nodeType == XMLReader::ELEMENT)
                && ($this->reader->namespaceURI == self::XRD_NS)) {
                switch ($this->reader->localName) {
                    case 'XRD':
                        $this->jrd = array();
                        break;
                    case 'Expires':
                        if ($include_expires) $this->jrd['expires'] = $this->reader->readString();
                        break;
                    case 'Subject':
                        $this->jrd['subject'] = $this->reader->readString();
                        break;
                    case 'Alias':
                        if (!isset($this->jrd['aliases'])) $this->jrd['aliases'] = array();
                        $this->jrd['aliases'][] = $this->reader->readString();
                        break;
                    case 'Link':
                        if (!isset($this->jrd['links'])) $this->jrd['links'] = array();
                        $this->jrd['links'][] = $this->parseLink();
                        break;
                    case 'Property':
                        if (!isset($this->jrd['properties'])) $this->jrd['properties'] = array();
                        $this->parseProperty($this->jrd['properties']);
                        break;
                }

            }
        }
        return $this->jrd;
    }

    /**
     * Parses the Link element.
     *
     * @return array the parsed JRD element
     */
    private function parseLink() {
        $link = array();

        while ($this->reader->moveToNextAttribute()) {
            if ($this->reader->namespaceURI == '') {
                $link[$this->reader->localName] = $this->reader->value;
            }
        }

        $this->reader->moveToElement();
        if ($this->reader->isEmptyElement) return $link;

        while ($this->reader->read()) {
            if (($this->reader->nodeType == XMLReader::END_ELEMENT) &&
                ($this->reader->namespaceURI == self::XRD_NS) &&
                ($this->reader->localName == 'Link'))
                break;

            if (($this->reader->nodeType == XMLReader::ELEMENT)
                && ($this->reader->namespaceURI == self::XRD_NS)) {
                switch ($this->reader->localName) {
                    case 'Property':
                        if (!isset($link['properties'])) $link['properties'] = array();
                        $this->parseProperty($link['properties']);
                        break;
                    case 'Title':
                        if ($this->reader->xmlLang) {
                            $lang = $this->reader->xmlLang;
                        } else {
                            $lang = 'und';
                        }
                        if (!isset($link['titles'])) $link['titles'] = array();
                        $link['titles'][$lang] = $this->reader->readString();
                        break;
                }
            }
        }

        return $link;
    }

    /**
     * Parses the Property element.
     *
     * The Property element can be a child of either the root XRD
     * element or the Link element
     *
     * @param array &$el the parent JRD element
     */
    private function parseProperty(&$el) {
        $type = $this->reader->getAttribute('type');
        if ($this->reader->getAttributeNs('nil', self::XSI_NS)) {
            $value = null;
        } else {
            $value = $this->reader->readString();
        }
        $el[$this->reader->getAttribute('type')] = $value;

        $this->reader->next();
    }
}
?>

<?php


namespace Neoan3\Apps;


class Template
{

    /**
     * @param $content
     * @param $array
     *
     * @return mixed
     */
    static function embrace($content, $array)
    {
        $flatArray = self::flattenArray($array);
        $templateFunctions = ['nFor', 'nIf'];
        foreach ($templateFunctions as $function) {
            $content = self::enforceEmbraceInAttributes(TemplateFunctions::$function($content, $array));
        }
        $saveOpening = preg_quote(TemplateFunctions::getDelimiters()[0]);
        $saveClosing = preg_quote(TemplateFunctions::getDelimiters()[1]);
        foreach ($flatArray as $flatKey => $value){
            $flatKey = preg_replace('/[\/\.\\\+\*\?\[\^\]\$\(\)\{\}\=\!\<\>\|\:\-]/', "\\\\$0",$flatKey);
            if(is_callable($value)){
                TemplateFunctions::registerClosure($flatKey,$value);
            } else {
                $content = preg_replace("/$saveOpening\s*$flatKey\s*$saveClosing/", $value, $content);
                $content = TemplateFunctions::tryClosures($flatArray, $content, false);
            }
        }

        return $content;
    }

    /**
     * @param $content
     * @param $array
     *
     * @return mixed
     */
    static function hardEmbrace($content, $array)
    {
        TemplateFunctions::setDelimiter('[[',']]');
        return self::embrace($content, $array);
    }

    /**
     * @param $content
     * @param $array
     *
     * @return mixed
     */
    static function tEmbrace($content, $array)
    {
        return str_replace(array_map('self::tBraces', array_keys($array)), array_values($array), $content);
    }

    /**
     * @param $location
     * @param $array
     *
     * @return mixed
     */
    static function embraceFromFile($location, $array)
    {
        $appRoot = defined('path') ? path : dirname(dirname(dirname(__DIR__)));
        $file = file_get_contents($appRoot . '/' . $location);
        return self::embrace($file, $array);
    }


    /**
     * @param $input
     *
     * @return string
     */
    private static function tBraces($input): string
    {
        return '<t>' . $input . '</t>';
    }

    /**
     * @param $parentDoc
     * @param $hitNode
     * @param $stringContent
     */
    static function clone(\DOMDocument $parentDoc, \DOMElement $hitNode, string $stringContent)
    {
        $newDD = new \DOMDocument();
        @$newDD->loadHTML(
            '<root>' . $stringContent . '</root>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOBLANKS
        );
        foreach ($newDD->firstChild->childNodes as $subNode) {
            if ($subNode->hasChildNodes() > 0 && $subNode->childNodes->length > 0) {
                $isNode = $parentDoc->importNode($subNode, true);
                $hitNode->parentNode->appendChild($isNode);
            }
        }
        $hitNode->parentNode->removeChild($hitNode);
    }

    /**
     * @param $content
     *
     * @return string|string[]|null
     */
    private static function enforceEmbraceInAttributes($content)
    {
        return preg_replace('/="(.*)(%7B%7B)(.*)(%7D%7D)(.*)"/', '="$1{{$3}}$5"', $content);
    }

    /**
     * @param \DOMElement $domNode
     *
     * @return string
     */
    static function nodeStringify(\DOMElement $domNode): string
    {
        $string = '<' . $domNode->tagName;
        foreach ($domNode->attributes as $attribute) {
            $string .= ' ' . $attribute->name . '="' . $attribute->value . '"';
        }
        $string .= '>';
        if ($domNode->hasChildNodes()) {
            foreach ($domNode->childNodes as $node) {
                $string .= $domNode->ownerDocument->saveHTML($node);
            }
        }
        $string .= '</' . $domNode->tagName . '>';
        return $string;
    }

    /**
     * @param      $array
     * @param bool $parentKey
     *
     * @return array
     */
    static function flattenArray($array, $parentKey = false): array
    {
        $answer = [];
        foreach ($array as $key => $value) {
            if ($parentKey) {
                $key = $parentKey . '.' . $key;
            }
            if (!is_array($value)) {
                $answer[$key] = $value;
            } else {
                $answer[$key] = 'Array';
                $answer[$key.'.length'] = sizeof($value);
                $answer = array_merge($answer, self::flattenArray($value, $key));
            }
        }
        return $answer;
    }
}
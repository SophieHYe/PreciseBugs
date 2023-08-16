<?php

namespace luya\yii\helpers;

use luya\Exception;
use yii\base\Model;
use yii\db\QueryInterface;

/**
 * Exporting into Formats.
 *
 * @author Basil Suter <basil@nadar.io>
 * @since 1.0.0
 */
class ExportHelper
{
    /**
     * Export an Array or QueryInterface instance into a CSV formated string.
     *
     * @param array|QueryInterface $input The data to export into a csv
     * @param array $keys Defines which keys should be packed into the generated CSV. The defined keys does not change the sort behavior of the generated csv.
     * @param string $header Whether the column name should be set as header inside the csv or not.
     * @param array $options Options
     * + `sort`: boolean, whether they row should be sorted by its keys, default is true.
     * @return string The generated CSV as string.
     */
    public static function csv($input, array $keys = [], $header = true, array $options = [])
    {
        $delimiter = ",";
        $input = self::transformInput($input);
        $array = self::generateContentArray($input, $keys, $header, $options);

        return self::generateOutputString($array, $delimiter);
    }

    /**
     * Export an Array or QueryInterface instance into a Excel formatted string.
     *
     * @param array|QueryInterface $input
     * @param array $keys Defines which keys should be packed into the generated xlsx. The defined keys does not change the sort behavior of the generated xls.
     * @param bool $header
     * @param array $options Options
     * + `sort`: boolean, whether they row should be sorted by its keys, default is true.
     * @return mixed
     * @throws Exception
     */
    public static function xlsx($input, array $keys = [], $header = true, array $options = [])
    {
        $input = self::transformInput($input);

        $array = self::generateContentArray($input, $keys, $header, $options);

        $writer = new XLSXWriter();
        $writer->writeSheet($array);

        return $writer->writeToString();
    }

    /**
     * Check type of input and return correct array.
     *
     * @param array|QueryInterface $input
     * @return array
     */
    protected static function transformInput($input)
    {
        if ($input instanceof QueryInterface) {
            return $input->all();
        }

        return $input;
    }

    /**
     * Generate content by rows.
     *
     * @param array $contentRows
     * @param string $delimiter
     * @param array $keys
     * @param bool $generateHeader
     * @param array $options Options
     * + `sort`: boolean, whether they row should be sorted by its keys, default is true.
     * @return array
     * @throws Exception
     */
    protected static function generateContentArray($contentRows, array $keys, $generateHeader = true, $options  = [])
    {
        if (is_scalar($contentRows)) {
            throw new Exception("Content must be either an array, object or traversable.");
        }

        $attributeKeys = $keys;
        $header = [];
        $rows = [];
        $i = 0;
        foreach ($contentRows as $content) {
            // handle rows content
            if (!empty($keys) && is_array($content)) {
                foreach ($content as $k => $v) {
                    if (!in_array($k, $keys)) {
                        unset($content[$k]);
                    }
                }
            } elseif (!empty($keys) && is_object($content)) {
                $attributeKeys[get_class($content)] = $keys;
            }
            $row = ArrayHelper::toArray($content, $attributeKeys, false);

            if (ArrayHelper::getValue($options, 'sort', true)) {
                ksort($row);
            }
            
            $rows[$i] = $row;

            // handle header
            if ($i == 0 && $generateHeader) {
                if ($content instanceof Model) {
                    /** @var Model $content */
                    foreach ($content as $k => $v) {
                        if (empty($keys)) {
                            $header[$k] = $content->getAttributeLabel($k);
                        } elseif (in_array($k, $keys)) {
                            $header[$k] = $content->getAttributeLabel($k);
                        }
                    }
                } else {
                    $header = array_keys($rows[0]);
                }

                if (ArrayHelper::getValue($options, 'sort', true)) {
                    ksort($header);
                }
            }

            unset($row);
            gc_collect_cycles();
            $i++;
        }

        if ($generateHeader) {
            return ArrayHelper::merge([$header], $rows);
        }

        return $rows;
    }

    /**
     * Generate the output string with delimiters.
     *
     * @param array $input
     * @param string $delimiter
     * @return null|string
     */
    protected static function generateOutputString(array $input, $delimiter)
    {
        $output = null;
        foreach ($input as $row) {
            $output.= self::generateRow($row, $delimiter, '"');
        }

        return $output;
    }

    /**
     * Generate a row by its items.
     *
     * @param array $row
     * @param string $delimiter
     * @param string $enclose
     * @return string
     */
    protected static function generateRow(array $row, $delimiter, $enclose)
    {
        array_walk($row, function (&$item) use ($enclose) {
            if (is_bool($item)) {
                $item = (int) $item;
            } elseif (is_null($item)) {
                $item = '';
            } elseif (!is_scalar($item)) {
                $item = "[array]";
            }
            $item = $enclose.self::sanitizeValue($item).$enclose;
        });

        return implode($delimiter, $row) . PHP_EOL;
    }

    /**
     * Sanitize Certain Values to increase security from user generated output.
     * 
     * @param string $value
     * @return string
     * @see https://owasp.org/www-community/attacks/CSV_Injection
     * @since 1.2.1
     */
    public static function sanitizeValue($value)
    {
        $value = str_replace([
            '"',
        ], [
            '""',
        ], trim($value));

        $firstChar = substr($value, 0, 1);
        if (in_array($firstChar, ['=', '+', '-', '@', PHP_EOL, "\t", "\n"])) {
            $value = StringHelper::replaceFirst($firstChar, "'$firstChar", $value);
        }

        return $value;
    }
}

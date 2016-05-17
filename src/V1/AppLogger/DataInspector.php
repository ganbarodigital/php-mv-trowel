<?php

/**
 * Copyright (c) 2015-present Ganbaro Digital Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the names of the copyright holders nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category  Libraries
 * @package   Trowel
 * @author    Stuart Herbert <stuherbert@ganbarodigital.com>
 * @copyright 2015-present Ganbaro Digital Ltd www.ganbarodigital.com
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link      http://ganbarodigital.github.io/php-mv-trowel
 */

namespace GanbaroDigital\Trowel\V1\AppLogger;

class DataInspector
{
    private $objectStack = [];

    public function convertToPrintable($value)
    {
        if (is_object($value)) {
            return json_encode($this->convertObject($value), JSON_PRETTY_PRINT);
        }
        else if (is_array($value)) {
            return json_encode($this->convertArray($value), JSON_PRETTY_PRINT);
        }
        else {
            return var_export($value, true);
        }
    }

    private function convertObject($obj)
    {
        // special case - have we seen this object before?
        foreach ($this->objectStack as $seenObj) {
            if ($seenObj === $obj) {
                // yes we have == circular dependency
                return [ "CIRCULAR DEPENDENCY!!" ];
            }
        }

        // if we get here, then we have a new object to examine
        $this->objectStack[] = $obj;

        // special case - a Doctrine collection
        if (method_exists($obj, 'toArray') && substr(get_class($obj), 0, 8) == 'Doctrine') {
            return [ 'DOCTRINE COLLECTION - CANNOT DUMP ATM' ];
        }

        $refObj = $this->filterOutDoctrine($obj);
        $refProps = $refObj->getProperties();

        $props = [];
        foreach ($refProps as $refProp) {
            if (substr($refProp->getName(), 0, 1) == '_') {
                // take advantage of Doctrine's history, and skip over anything
                // that starts with an underscore
                continue;
            }

            $refProp->setAccessible(true);

            // we want to skip Doctrine internals
            // they're just nasty and undumpable
            $value = $refProp->getValue($obj);
            if ($value instanceof \Doctrine\ORM\EntityManager) {
                continue;
            }

            $props[$refProp->getName()] = stuExtractVars($value);
        }

        return $props;
    }

    private function convertArray($arr)
    {
        $retval = [];
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                $retval[$key] = $this->convertArray($value);
            }
            else if (is_object($value)) {
                $retval[$key] = $this->convertObject($value);
            }
            else {
                $retval[$key] = $value;
            }
        }

        return $retval;
    }

    private function filterOutDoctrine($obj) {
        $types = array_reverse(class_parents($obj));
        $types = array_merge([get_class($obj)], $types);

        foreach ($types as $type) {
            if (substr($type, 0, 8) !== 'Doctrine') {
            return new ReflectionClass($type);
            }
        }

        return new ReflectionObject($obj);
    }
}

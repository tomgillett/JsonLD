<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\JsonLD;

use ML\JsonLD\Exception\JsonLdException;
use ML\IRI\IRI;

/**
 *
 * @author Tom Gillett <tom@tomgillett.co.uk>
 */
abstract class DocumentLoader implements DocumentLoaderInterface
{


    protected function parseContentType(string $contentType) 
    {
        // Drop any media type parameters such as profiles
        if (false !== ($pos = strpos($contentType, ';'))) {
            $contentType = substr($contentType, 0, $pos);
        }

        return trim($contentType);
    }

    protected function verifyContentType(string $contentType)
    {
        return in_array($this->parseContentType($contentType), [ 
            'application/ld+json', 
            'application/json' 
        ]);
    }

    /**
     * Parses Link headers.
     *
     * @param  array  $values  An array of HTTP Link headers
     * @param  IRI  $baseIri The document's URL (used to expand relative URLs to absolutes)
     * 
     * @return array  $links  A structured array of Link header data
     */
    public function parseLinkHeaders(array $values, IRI $baseIri)
    {
        // Separate multiple links contained in a single header value
        for ($i = 0, $total = count($values); $i < $total; $i++) {
            if (strpos($values[$i], ',') !== false) {
                foreach (preg_split('/,(?=([^"]*"[^"]*")*[^"]*$)/', $values[$i]) as $v) {
                    $values[] = trim($v);
                }
                unset($values[$i]);
            }
        }

        $contexts = $matches = array();
        $trimWhitespaceCallback = function ($str) {
            return trim($str, "\"'  \n\t");
        };

        // Split the header in key-value pairs
        $result = array();

        foreach ($values as $val) {
            $part = array();

            foreach (preg_split('/;(?=([^"]*"[^"]*")*[^"]*$)/', $val) as $kvp) {
                preg_match_all('/<[^>]+>|[^=]+/', $kvp, $matches);
                $pieces = array_map($trimWhitespaceCallback, $matches[0]);

                if (count($pieces) > 1) {
                    $part[$pieces[0]] = $pieces[1];
                } elseif(count($pieces) === 1) {
                    $part['uri'] = (string) $baseIri->resolve(trim($pieces[0], '<> '));
                }
            }
        
            if (!empty($part)) {
                $result[] = $part;
            }
        }

        return $result;
    }

    protected function getLinkHeaderAltValues(array $linkHeaderValues)
    {
        return array_filter($linkHeaderValues, function ($link) {
            return (isset($link['rel']) && isset($link['type']) 
                && ($link['rel'] === 'alternate') && ($link['type'] === 'application/ld+json'));
        });
    }

    protected function getLinkHeaderContextValues(array $linkHeaderValues)
    {
        return array_filter($linkHeaderValues, function ($link) {
            return (isset($link['rel']) && in_array('http://www.w3.org/ns/json-ld#context', explode(' ', $link['rel'])));
        });
    }

}

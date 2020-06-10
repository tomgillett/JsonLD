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
     * Attempts to retrieve any Link header being offered for application/ld+json content negotiation.
     *
     * @param  array  $headers  An array of HTTP Link headers
     * @param  IRI  $baseIri The document's URL (used to expand relative URLs to absolutes)
     * 
     * @return array  $links  A structured array of Link header data
     */
    protected function parseLinkHeaders(array $headers, IRI $baseIri)
    {
        $links = array();

        foreach ($headers as $header) { // Foreach individual Link header
            foreach (explode(',', $header) as $value) { // Handle case of multiple links within a single Link header
                if (preg_match("/<(.[^>]+)>;/", $value, $uri)) {
                    $iri = new IRI(trim($uri[1]));

                    $link = array('uri' => $iri->isAbsolute() ? (string) $iri : (string) $baseIri->resolve($iri));

                    preg_match_all("/;\s?([A-z][^,=]+)=\"?(.[^\";]+)/", $value, $parameters);

                    if (count($parameters) == 3) {
                        $keys = $parameters[1];
                        $values = $parameters[2];

                        for ($i=0; $i < count($keys); $i++) {
                            $link[trim($keys[$i])] = trim($values[$i]);
                        }
                    }

                    $links[] = $link;
                }
            }
        }

        return $links;
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

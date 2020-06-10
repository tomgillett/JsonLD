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
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The Psr18Loader loads remote documents using using a PSR-17/PSR-18 compliant HTTP client.
 *
 * @author Tom Gillett <tom@tomgillett.co.uk>
 */
class Psr18Loader extends DocumentLoader
{

    protected $client;

    protected $factory;

    public function __construct(ClientInterface $client, RequestFactoryInterface $factory)
    {
        $this->client = $client;
        $this->factory = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function loadDocument($url)
    {
        // Setup the request
        $request = $this->factory->createRequest('GET', $url)
            ->withHeader('Accept', 'application/ld+json, application/json; q=0.9, */*; q=0.1')
            ->withHeader('User-Agent', 'lanthaler JsonLD');

        // Make the request and handle any errors
        try {
            $response = $this->client->sendRequest($request);

        } catch(ClientExceptionInterface $ex) {
            throw new JsonLdException(
                JsonLdException::LOADING_DOCUMENT_FAILED,
                sprintf('Unable to load the remote document "%s".', $url),
                $ex->getMessage()
            );
        }

        // Extract HTTP Link headers
        $linkHeaderValues = $this->parseLinkHeaders($response->getHeader('Link'), new IRI($url));

        // If the Media type was not as expected, check to see if the desired content type
        // is being offered in a Link header (this is what schema.org now does).
        if (!$this->verifyContentType($response->getHeaderLine('Content-Type'))) {
            $linkHeaderAltValues = $this->getLinkHeaderAltValues($linkHeaderValues);

            if (count($linkHeaderAltValues) && $linkHeaderAltValues[0]['uri']) {
                return $this->loadDocument($linkHeaderAltValues[0]['uri']);

            } else {
                throw new JsonLdException(
                    JsonLdException::LOADING_DOCUMENT_FAILED,
                    'Invalid media type',
                    $this->parseContentType($response->getHeaderLine('Content-Type'))
                );
            }
        }

        return $this->makeRemoteDocument($request,  $response, $linkHeaderValues);
    }

    protected function makeRemoteDocument(RequestInterface $request, ResponseInterface $response, array $linkHeaderValues)
    {
        // Extract Link headers where "rel" is a JSON-LD context
        $contextLinkHeaderValues = $this->getLinkHeaderContextValues($linkHeaderValues);

        if (count($contextLinkHeaderValues) > 1) {
            throw new JsonLdException(
                JsonLdException::MULTIPLE_CONTEXT_LINK_HEADERS,
                'Found multiple contexts in HTTP Link headers',
                $contextLinkHeaderValues
            );
        }

        $remoteDocument = new RemoteDocument($request->getUri());

        $remoteDocument->mediaType = $this->parseContentType($response->getHeaderLine('Content-Type'));

        if (count($contextLinkHeaderValues) && ($remoteDocument->mediaType !== 'application/ld+json')) {
            $remoteDocument->contextUrl = $contextLinkHeaderValues[0]['uri'];
        }

        $remoteDocument->document = Processor::parse($response->getBody());

        return $remoteDocument;
    }

}

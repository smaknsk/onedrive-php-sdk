<?php

/**
 * This file is part of Krizalys' OneDrive SDK for PHP.
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @author    Christophe Vidal
 * @copyright 2008-2019 Christophe Vidal (http://www.krizalys.com)
 * @license   https://opensource.org/licenses/BSD-3-Clause 3-Clause BSD License
 * @link      https://github.com/krizalys/onedrive-php-sdk
 */

namespace Krizalys\Onedrive\Proxy;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Stream;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\DriveItem;
use Microsoft\Graph\Model\UploadSession;

/**
 * A proxy to a \Microsoft\Graph\Model\UploadSession instance.
 *
 * @property-read \DateTime $expirationDateTime
 *                The expiration date/time.
 * @property-read string $nextExpectedRanges
 *                The next expected ranges.
 * @property-read string $uploadUrl
 *                The upload URL.
 *
 * @since 2.1.0
 *
 * @link https://github.com/microsoftgraph/msgraph-sdk-php/blob/dev/src/Model/UploadSession.php
 */
class UploadSessionProxy extends EntityProxy
{
    /**
     * @var int
     *      Range size multiple. OneDrive requires 320 KiB.
     */
    const RANGE_SIZE_MULTIPLE = 320 * 1024;

    /**
     * @var int
     *      Minimum range size.
     */
    const MIN_RANGE_SIZE = self::RANGE_SIZE_MULTIPLE;

    /**
     * @var int
     *      Maximal range size. OneDrive limits to 60 MiB.
     */
    const MAX_RANGE_SIZE = 60 * 1024 * 1024;

    /**
     * @var string|resource|\GuzzleHttp\Psr7\Stream
     *      The content.
     */
    private $content;

    /**
     * @var int
     *      The type.
     */
    private $type;

    /**
     * @var int
     *      The chunk size, in bytes.
     */
    private $rangeSize;

    /**
     * Constructor.
     *
     * @param Graph $graph
     *        The Microsoft Graph.
     * @param UploadSession $uploadSession
     *        The upload session.
     * @param string|resource|\GuzzleHttp\Psr7\Stream $content
     *        The content.
     * @param array $options
     *        The options.
     *
     * @since 2.1.0
     */
    public function __construct(
        Graph $graph,
        UploadSession $uploadSession,
        $content,
        array $options = []
    ) {
        parent::__construct($graph, $uploadSession);
        $this->content   = $content;
        $this->type      = array_key_exists('type', $options) ? $options['type'] : null;
        $this->rangeSize = array_key_exists('range_size', $options) ? $options['range_size'] : null;
    }

    /**
     * Getter.
     *
     * @param string $name
     *        The name.
     *
     * @return mixed
     *         The value.
     *
     * @since 2.1.0
     */
    public function __get($name)
    {
        $uploadSession = $this->entity;

        switch ($name) {
            case 'expirationDateTime':
                return $uploadSession->getExpirationDateTime();

            case 'nextExpectedRanges':
                return $uploadSession->getNextExpectedRanges();

            case 'uploadUrl':
                return $uploadSession->getUploadUrl();

            default:
                return parent::__get($name);
        }
    }

    /**
     * Uploads the content in multiple ranges and completes this session.
     *
     * @return DriveItemProxy
     *         The drive item created.
     *
     * @since 2.1.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/driveitem_createuploadsession?view=odsp-graph-online#upload-bytes-to-the-upload-session
     *       Upload bytes to the upload session
     *
     * @todo Support retries on errors.
     */
    public function complete()
    {
        $stream = $this->content instanceof Stream ?
            $this->content
            : Psr7\stream_for($this->content);

        if ($this->rangeSize !== null) {
            $rangeSize = $this->rangeSize;
            $rangeSize = $rangeSize - $rangeSize % self::RANGE_SIZE_MULTIPLE;
            $rangeSize = min($rangeSize, self::MAX_RANGE_SIZE);
            $rangeSize = max($rangeSize, self::MIN_RANGE_SIZE);
        } else {
            $rangeSize = self::RANGE_SIZE_MULTIPLE;
        }

        $size   = $stream->getSize();
        $offset = 0;

        while (!$stream->eof()) {
            $rangeStream = new LimitStream($stream, $rangeSize, $offset);
            $rangeSize   = $rangeStream->getSize();
            $body        = $rangeStream->getContents();
            $rangeFirst  = $offset;
            $offset += $rangeSize;
            $rangeLast = $offset - 1;

            $headers = [
                'Content-Length' => $rangeSize,
                'Content-Range'  => "bytes $rangeFirst-$rangeLast/$size",
            ];

            if ($this->type !== null) {
                $headers['Content-Type'] = $this->type;
            }

            $response = $this
                ->graph
                ->createRequest('PUT', $this->uploadUrl)
                ->addHeaders($headers)
                ->attachBody($body)
                ->execute();

            $status = $response->getStatus();

            if ($status == 200 || $status == 201) {
                $driveItem = $response->getResponseAsObject(DriveItem::class);

                return new DriveItemProxy($this->graph, $driveItem);
            }

            if ($status != 202) {
                throw new \Exception("Unexpected status code produced by 'PUT {$this->uploadUrl}': $status");
            }
        }

        throw new \Exception('OneDrive did not create a drive item for the uploaded file');
    }
}

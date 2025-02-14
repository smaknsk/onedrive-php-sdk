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
use GuzzleHttp\Psr7\Stream;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\DriveItem;
use Microsoft\Graph\Model\DriveItemVersion;
use Microsoft\Graph\Model\Permission;
use Microsoft\Graph\Model\Thumbnail;
use Microsoft\Graph\Model\UploadSession;

/**
 * A proxy to a \Microsoft\Graph\Model\DriveItem instance.
 *
 * @property-read \Krizalys\Onedrive\Proxy\AudioProxy $audio
 *                The audio.
 * @property-read \GuzzleHttp\Psr7\Stream $content
 *                The content.
 * @property-read string $cTag
 *                The CTag.
 * @property-read \Krizalys\Onedrive\Proxy\DeletedProxy $deleted
 *                The deleted.
 * @property-read \Krizalys\Onedrive\Proxy\FileProxy $file
 *                The file.
 * @property-read \Krizalys\Onedrive\Proxy\FileSystemInfoProxy $fileSystemInfo
 *                The file system info.
 * @property-read \Krizalys\Onedrive\Proxy\FolderProxy $folder
 *                The folder.
 * @property-read \Krizalys\Onedrive\Proxy\ImageProxy $image
 *                The image.
 * @property-read \Krizalys\Onedrive\Proxy\GeoCoordinatesProxy $location
 *                The location.
 * @property-read \Krizalys\Onedrive\Proxy\PackageProxy $package
 *                The package.
 * @property-read \Krizalys\Onedrive\Proxy\PhotoProxy $photo
 *                The photo.
 * @property-read \Krizalys\Onedrive\Proxy\PublicationFacetProxy $publication
 *                The publication.
 * @property-read \Krizalys\Onedrive\Proxy\RemoteItemProxy $remoteItem
 *                The remote item.
 * @property-read \Krizalys\Onedrive\Proxy\RootProxy $root
 *                The root.
 * @property-read \Krizalys\Onedrive\Proxy\SearchResultProxy $searchResult
 *                The search result.
 * @property-read \Krizalys\Onedrive\Proxy\SharedProxy $shared
 *                The shared.
 * @property-read \Krizalys\Onedrive\Proxy\SharepointIdsProxy $sharepointIds
 *                The SharePoint IDs.
 * @property-read int $size
 *                The size.
 * @property-read \Krizalys\Onedrive\Proxy\SpecialFolderProxy $specialFolder
 *                The special folder.
 * @property-read \Krizalys\Onedrive\Proxy\VideoProxy $video
 *                The video.
 * @property-read string $webDavUrl
 *                The WebDAV URL.
 * @property-read \Krizalys\Onedrive\Proxy\DriveItem[] $children
 *                The children.
 * @property-read \Krizalys\Onedrive\Proxy\ListItemProxy $listItem
 *                The list item.
 * @property-read \Krizalys\Onedrive\Proxy\PermissionProxy[] $permissions
 *                The permissions.
 * @property-read \Krizalys\Onedrive\Proxy\ThumbnailProxy[] $thumbnails
 *                The thumbnails.
 * @property-read \Krizalys\Onedrive\Proxy\DriveItemVersionProxy[] $versions
 *                The versions.
 * @property-read \Krizalys\Onedrive\Proxy\WorkbookProxy $workbook
 *                The workbook.
 *
 * @since 2.0.0
 *
 * @link https://github.com/microsoftgraph/msgraph-sdk-php/blob/dev/src/Model/DriveItem.php
 */
class DriveItemProxy extends BaseItemProxy
{
    /**
     * Constructor.
     *
     * @param Graph
     *        The Microsoft Graph.
     * @param DriveItem
     *        The drive item.
     *
     * @since 2.0.0
     */
    public function __construct(Graph $graph, DriveItem $driveItem)
    {
        parent::__construct($graph, $driveItem);
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
     * @since 2.0.0
     */
    public function __get($name)
    {
        $driveItem = $this->entity;

        switch ($name) {
            case 'audio':
                $audio = $driveItem->getAudio();
                return $audio !== null ? new AudioProxy($this->graph, $audio) : null;

            case 'content':
                return $this->download();

            case 'cTag':
                return $driveItem->getCTag();

            case 'deleted':
                $deleted = $driveItem->getDeleted();
                return $deleted !== null ? new DeletedProxy($this->graph, $deleted) : null;

            case 'file':
                $file = $driveItem->getFile();
                return $file !== null ? new FileProxy($this->graph, $file) : null;

            case 'fileSystemInfo':
                $fileSystemInfo = $driveItem->getFileSystemInfo();
                return $fileSystemInfo !== null ? new FileSystemInfoProxy($this->graph, $fileSystemInfo) : null;

            case 'folder':
                $folder = $driveItem->getFolder();
                return $folder !== null ? new FolderProxy($this->graph, $folder) : null;

            case 'image':
                $image = $driveItem->getImage();
                return $image !== null ? new ImageProxy($this->graph, $image) : null;

            case 'location':
                $location = $driveItem->getLocation();
                return $location !== null ? new GeoCoordinatesProxy($this->graph, $location) : null;

            case 'package':
                $package = $driveItem->getPackage();
                return $package !== null ? new PackageProxy($this->graph, $package) : null;

            case 'photo':
                $photo = $driveItem->getPhoto();
                return $photo !== null ? new PhotoProxy($this->graph, $photo) : null;

            case 'publication':
                $publication = $driveItem->getPublication();
                return $publication !== null ? new PublicationFacetProxy($this->graph, $publication) : null;

            case 'remoteItem':
                $remoteItem = $driveItem->getRemoteItem();
                return $remoteItem !== null ? new RemoteItemProxy($this->graph, $remoteItem) : null;

            case 'root':
                $root = $driveItem->getRoot();
                return $root !== null ? new RootProxy($this->graph, $root) : null;

            case 'searchResult':
                $searchResult = $driveItem->getSearchResult();
                return $searchResult !== null ? new SearchResultProxy($this->graph, $searchResult) : null;

            case 'shared':
                $shared = $driveItem->getShared();
                return $shared !== null ? new SharedProxy($this->graph, $shared) : null;

            case 'sharepointIds':
                $sharepointIds = $driveItem->getSharepointIds();
                return $sharepointIds !== null ? new SharepointIdsProxy($this->graph, $sharepointIds) : null;

            case 'size':
                return $driveItem->getSize();

            case 'specialFolder':
                $specialFolder = $driveItem->getSpecialFolder();
                return $specialFolder !== null ? new SpecialFolderProxy($this->graph, $specialFolder) : null;

            case 'video':
                $video = $driveItem->getVideo();
                return $video !== null ? new VideoProxy($this->graph, $video) : null;

            case 'webDavUrl':
                return $driveItem->getWebDavUrl();

            case 'children':
                return $this->getChildren();

            case 'listItem':
                $listItem = $driveItem->getListItem();
                return $listItem !== null ? new ListItemProxy($this->graph, $listItem) : null;

            case 'permissions':
                $permissions = $driveItem->getPermissions();

                return $permissions !== null ? array_map(function (Permission $permission) {
                    return new PermissionProxy($this->graph, $permission);
                }, $permissions) : null;

            case 'thumbnails':
                $thumbnails = $driveItem->getThumbnails();

                return $thumbnails !== null ? array_map(function (Thumbnail $thumbnail) {
                    return new ThumbnailProxy($this->graph, $thumbnail);
                }, $thumbnails) : null;

            case 'versions':
                $versions = $driveItem->getVersions();

                return $versions !== null ? array_map(function (DriveItemVersion $driveItemVersion) {
                    return new DriveItemVersionProxy($this->graph, $driveItemVersion);
                }, $versions) : null;

            case 'workbook':
                $workbook = $driveItem->getWorkbook();
                return $workbook !== null ? new WorkbookProxy($this->graph, $workbook) : null;

            default:
                return parent::__get($name);
        }
    }

    /**
     * Creates a folder under this folder drive item.
     *
     * This operation is supported only on folders (as opposed to files): it
     * fails if this `DriveItemProxy` instance does not refer to a folder.
     *
     * @param string $name
     *        The name.
     * @param array $options
     *        The options.
     *
     * @return DriveItemProxy
     *         The drive item created.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/driveitem_post_children?view=odsp-graph-online
     *       Create a new folder in a drive
     *
     * @todo Support name conflict behavior.
     */
    public function createFolder($name, array $options = [])
    {
        $driveLocator = "/drives/{$this->parentReference->driveId}";
        $itemLocator  = "/items/{$this->id}";
        $endpoint     = "$driveLocator$itemLocator/children";

        $body = [
            'folder' => [
                '@odata.type' => 'microsoft.graph.folder',
            ],
            'name' => (string) $name,
        ];

        $response = $this
            ->graph
            ->createRequest('POST', $endpoint)
            ->attachBody($body + $options)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200 && $status != 201) {
            throw new \Exception("Unexpected status code produced by 'POST $endpoint': $status");
        }

        $driveItem = $response->getResponseAsObject(DriveItem::class);

        return new self($this->graph, $driveItem);
    }

    /**
     * Gets this folder drive item's children.
     *
     * This operation is supported only on folders (as opposed to files): it
     * fails if this `DriveItemProxy` instance does not refer to a folder.
     *
     * @return array
     *         The child drive items.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/driveitem_list_children?view=odsp-graph-online
     *       List children of a driveItem
     *
     * @todo Support pagination using a native iterator.
     */
    public function getChildren()
    {
        $driveLocator = "/drives/{$this->parentReference->driveId}";
        $itemLocator  = "/items/{$this->id}";
        $endpoint     = "$driveLocator$itemLocator/children";

        $response = $this
            ->graph
            ->createCollectionRequest('GET', $endpoint)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200) {
            throw new \Exception("Unexpected status code produced by 'GET $endpoint': $status");
        }

        $driveItems = $response->getResponseAsObject(DriveItem::class);

        if (!is_array($driveItems)) {
            return [];
        }

        return array_map(function (DriveItem $driveItem) {
            return new self($this->graph, $driveItem);
        }, $driveItems);
    }

    /**
     * Deletes this drive item.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/driveitem_delete?view=odsp-graph-online
     *       Delete a DriveItem
     */
    public function delete()
    {
        $driveLocator = "/drives/{$this->parentReference->driveId}";
        $itemLocator  = "/items/{$this->id}";
        $endpoint     = "$driveLocator$itemLocator";

        $response = $this
            ->graph
            ->createRequest('DELETE', $endpoint)
            ->execute();

        $status = $response->getStatus();

        if ($status != 204) {
            throw new \Exception("Unexpected status code produced by 'DELETE $endpoint': $status");
        }
    }

    /**
     * Uploads a file under this folder drive item.
     *
     * This operation is supported only on folders (as opposed to files): it
     * fails if this `DriveItemProxy` instance does not refer to a folder.
     *
     * @param string $name
     *        The name.
     * @param string|resource|\GuzzleHttp\Psr7\Stream $content
     *        The content.
     * @param array $options
     *        The options.
     *
     * @return DriveItemProxy
     *         The drive item created.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/driveitem_put_content?view=odsp-graph-online
     *       Upload or replace the contents of a DriveItem
     *
     * @todo Support name conflict behavior.
     * @todo Support content type in options.
     */
    public function upload($name, $content, array $options = [])
    {
        $name         = rawurlencode($name);
        $driveLocator = "/drives/{$this->parentReference->driveId}";
        $itemLocator  = "/items/{$this->id}";
        $endpoint     = "$driveLocator$itemLocator:/$name:/content";

        $body = $content instanceof Stream ?
            $content
            : Psr7\stream_for($content);

        $response = $this
            ->graph
            ->createRequest('PUT', $endpoint)
            ->addHeaders($options)
            ->attachBody($body)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200 && $status != 201) {
            throw new \Exception("Unexpected status code produced by 'PUT $endpoint': $status");
        }

        $driveItem = $response->getResponseAsObject(DriveItem::class);

        return new self($this->graph, $driveItem);
    }

    /**
     * Creates an upload session to upload a large file in multiple ranges under
     * this folder drive item.
     *
     * This operation is supported only on folders (as opposed to files): it
     * fails if this `DriveItemProxy` instance does not refer to a folder.
     *
     * Uploading files using this method involves two steps:
     *     1. first, create the upload session for a given file using this
     *        method ;
     *     2. then, complete it using
     *        {@see \Krizalys\Onedrive\Proxy\UploadSessionProxy::complete() complete()}
     *        on the instance it returns.
     *
     * For example:
     *
     * ```php
     * $uploadSession = $driveItem->startUpload(
     *     'file.txt',
     *     'Some content',
     *     ['type' => 'text/plain']
     * );
     *
     * $uploadSession->complete();
     * ```
     *
     * @param string $name
     *        The name.
     * @param string|resource|\GuzzleHttp\Psr7\Stream $content
     *        The content.
     * @param array $options
     *        The options.
     *
     * @return UploadSessionProxy
     *         The upload session created.
     *
     * @since 2.1.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/driveitem_createuploadsession?view=odsp-graph-online
     *       Upload large files with an upload session
     *
     * @todo Support name conflict behavior.
     * @todo Support content type in options.
     */
    public function startUpload($name, $content, array $options = [])
    {
        $name         = rawurlencode($name);
        $driveLocator = "/drives/{$this->parentReference->driveId}";
        $itemLocator  = "/items/{$this->id}";
        $endpoint     = "$driveLocator$itemLocator:/$name:/createUploadSession";

        $response = $this
            ->graph
            ->createRequest('POST', $endpoint)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200) {
            throw new \Exception("Unexpected status code produced by 'POST $endpoint': $status");
        }

        $uploadSession = $response->getResponseAsObject(UploadSession::class);

        return new UploadSessionProxy($this->graph, $uploadSession, $content, $options);
    }

    /**
     * Downloads this file drive item.
     *
     * This operation is supported only on files (as opposed to folders): it
     * fails if this `DriveItemProxy` instance does not refer to a file.
     *
     * @return \GuzzleHttp\Psr7\Stream
     *         The content.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/driveitem_get_content?view=odsp-graph-online
     *       Download the contents of a DriveItem
     */
    public function download()
    {
        $driveLocator = "/drives/{$this->parentReference->driveId}";
        $itemLocator  = "/items/{$this->id}";
        $endpoint     = "$driveLocator$itemLocator/content";

        return $this
            ->graph
            ->createRequest('GET', $endpoint)
            ->setReturnType(Stream::class)
            ->execute();
    }

    /**
     * Renames this drive item.
     *
     * @param string $name
     *        The name.
     * @param array $options
     *        The options.
     *
     * @return DriveItemProxy
     *         The drive item renamed.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/driveitem_move?view=odsp-graph-online
     *       Move a DriveItem to a new folder
     */
    public function rename($name, array $options = [])
    {
        $driveLocator = "/drives/{$this->parentReference->driveId}";
        $itemLocator  = "/items/{$this->id}";
        $endpoint     = "$driveLocator$itemLocator";

        $body = [
            'name' => (string) $name,
        ];

        $response = $this
            ->graph
            ->createRequest('PATCH', $endpoint)
            ->attachBody($body + $options)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200) {
            throw new \Exception("Unexpected status code produced by 'PATCH $endpoint': $status");
        }

        $driveItem = $response->getResponseAsObject(DriveItem::class);

        return new self($this->graph, $driveItem);
    }

    /**
     * Moves this drive item.
     *
     * The `$destinationItem` instance must refer to a folder.
     *
     * @param DriveItemProxy $destinationItem
     *        The destination item.
     * @param array $options
     *        The options.
     *
     * @return DriveItemProxy
     *         The drive item.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/driveitem_move?view=odsp-graph-online
     *       Move a DriveItem to a new folder
     */
    public function move(self $destinationItem, array $options = [])
    {
        $driveLocator = "/drives/{$this->parentReference->driveId}";
        $itemLocator  = "/items/{$this->id}";
        $endpoint     = "$driveLocator$itemLocator";

        $body = [
            'parentReference' => [
                'id' => $destinationItem->id,
            ],
        ];

        $response = $this
            ->graph
            ->createRequest('PATCH', $endpoint)
            ->attachBody($body + $options)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200) {
            throw new \Exception("Unexpected status code produced by 'PATCH $endpoint': $status");
        }

        $driveItem = $response->getResponseAsObject(DriveItem::class);

        return new self($this->graph, $driveItem);
    }

    /**
     * Copies this file drive item.
     *
     * This operation is supported only on files (as opposed to folders): it
     * fails if this `DriveItemProxy` instance does not refer to a file.
     *
     * Additionally, the `$destinationItem` instance must refer to a folder.
     *
     * To copy a whole folder and its children, applications can explicitly
     * create an empty folder, using
     * {@see \Krizalys\Onedrive\Proxy\DriveItemProxy::createFolder() createFolder()},
     * and copy the children from the original folder to the new folder, using
     * {@see \Krizalys\Onedrive\Proxy\DriveItemProxy::copy() copy()}. This
     * process can be repeated recursively if support for multiple levels
     * of children is needed.
     *
     * @param DriveItemProxy $destinationItem
     *        The destination item.
     * @param array $options
     *        The options.
     *
     * @return string
     *         The progress URI.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/driveitem_copy?view=odsp-graph-online
     *       Copy a DriveItem
     *
     * @todo Support asynchronous Graph operation.
     */
    public function copy(self $destinationItem, array $options = [])
    {
        $driveLocator = "/drives/{$this->parentReference->driveId}";
        $itemLocator  = "/items/{$this->id}";
        $endpoint     = "$driveLocator$itemLocator/copy";

        $body = [
            'parentReference' => [
                'id' => $destinationItem->id,
            ],
        ];

        $response = $this
            ->graph
            ->createRequest('POST', $endpoint)
            ->attachBody($body + $options)
            ->execute();

        $status = $response->getStatus();

        if ($status != 202) {
            throw new \Exception("Unexpected status code produced by 'POST $endpoint': $status");
        }

        $headers = $response->getHeaders();

        return $headers['Location'][0];
    }
}

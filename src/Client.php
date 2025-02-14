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

namespace Krizalys\Onedrive;

use GuzzleHttp\ClientInterface;
use Krizalys\Onedrive\Proxy\DriveItemProxy;
use Krizalys\Onedrive\Proxy\DriveProxy;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;

/**
 * A client interface to communicate with the OneDrive API.
 *
 * Client applications use `Client` instances to perform OneDrive operations
 * programmatically.
 *
 * Applications are managed via Microsoft accounts. Two types of applications
 * are supported:
 *   - Microsoft identity platform (v2.0) applications, recommended for new
 *     applications; see
 *     {@link https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade "App registrations" in Microsoft Azure} ;
 *   - Live SDK applications, deprecated; see
 *     {@link https://apps.dev.microsoft.com/#/appList "My applications" in Microsoft
 *     Application Registration Portal}.
 */
class Client
{
    /**
     * @var string
     *      The base URL for authorization requests.
     */
    const AUTH_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';

    /**
     * @var string
     *      The base URL for token requests.
     */
    const TOKEN_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';

    /**
     * @var string
     *      The legacy date/time format.
     *
     * @deprecated 2.0.0 Non-standard format. Use ISO-8601 date/times instead.
     */
    const LEGACY_DATETIME_FORMAT = 'Y-m-d\TH:i:sO';

    /**
     * @var string
     *      The client ID.
     */
    private $clientId;

    /**
     * @var \Microsoft\Graph\Graph
     *      The Microsoft Graph.
     */
    private $graph;

    /**
     * @var \GuzzleHttp\ClientInterface
     *      The Guzzle HTTP client.
     */
    private $httpClient;

    /**
     * @var object
     *      The OAuth state (token, etc...).
     */
    private $_state;

    /**
     * Constructor.
     *
     * @param string $clientId
     *        The client ID.
     * @param \Microsoft\Graph\Graph $graph
     *        The Microsoft Graph.
     * @param \GuzzleHttp\ClientInterface $httpClient
     *        The Guzzle HTTP client.
     * @param mixed $logger
     *        Deprecated and will be removed in version 3; omit this parameter,
     *        or pass null or options instead.
     * @param array $options
     *        The options to use while creating this object.
     *        Valid supported keys are:
     *          - 'state' (object) When defined, it should contain a valid
     *            OneDrive client state, as returned by getState(). Default: [].
     *
     * @throws \Exception
     *         Thrown if `$clientId` is null.
     *
     * @since 1.0.0
     */
    public function __construct(
        $clientId,
        Graph $graph,
        ClientInterface $httpClient,
        $logger = null,
        array $options = []
    ) {
        if (func_num_args() == 4 && is_array($logger)) {
            $options = $logger;
            $logger  = null;
        } elseif ($logger !== null) {
            $message = '$logger is deprecated and will be removed in version 3;'
                . ' omit this parameter, or pass null or options instead';

            @trigger_error($message, E_USER_DEPRECATED);
        }

        if ($clientId === null) {
            throw new \Exception('The client ID must be set');
        }

        $this->clientId   = $clientId;
        $this->graph      = $graph;
        $this->httpClient = $httpClient;

        $this->_state = array_key_exists('state', $options)
            ? $options['state'] : (object) [
                'redirect_uri' => null,
                'token'        => null,
            ];
    }

    /**
     * Gets the current state of this Client instance.
     *
     * Typically saved in the session and passed back to the `Client`
     * constructor for further requests.
     *
     * @return object
     *         The state of this `Client` instance.
     *
     * @since 1.0.0
     */
    public function getState()
    {
        return $this->_state;
    }

    /**
     * Gets the URL of the log in form.
     *
     * Users should visit this URL in their browser to first be presented a form
     * where the user is first allowed to log in to their OneDrive account, and
     * then to grant the requested permissions to the OneDrive application.
     *
     * After login, the browser is redirected to the given redirect URI, and a
     * code is passed as a query string parameter to this URI.
     *
     * The browser is also directly redirected to the given redirect URI if the
     * user is already logged in.
     *
     * @param array $scopes
     *        The OneDrive scopes requested by the application. Supported
     *        values:
     *          - 'offline_access' ;
     *          - 'files.read' ;
     *          - 'files.read.all' ;
     *          - 'files.readwrite' ;
     *          - 'files.readwrite.all'.
     * @param string $redirectUri
     *        The URI to which to redirect to upon successful log in.
     *
     * @return string
     *         The log in URL.
     *
     * @since 1.0.0
     */
    public function getLogInUrl(array $scopes, $redirectUri)
    {
        $redirectUri                = (string) $redirectUri;
        $this->_state->redirect_uri = $redirectUri;

        $values = [
            'client_id'     => $this->clientId,
            'response_type' => 'code',
            'redirect_uri'  => $redirectUri,
            'scope'         => implode(' ', $scopes),
            'response_mode' => 'query',
        ];

        $query = http_build_query($values, '', '&', PHP_QUERY_RFC3986);

        // When visiting this URL and authenticating successfully, the agent is
        // redirected to the redirect URI, with a code passed in the query
        // string (the name of the variable is "code"). This is suitable for
        // PHP.
        return self::AUTH_URL . "?$query";
    }

    /**
     * Gets the access token expiration delay.
     *
     * @return int
     *         The token expiration delay, in seconds.
     *
     * @since 1.0.0
     */
    public function getTokenExpire()
    {
        return $this->_state->token->obtained
            + $this->_state->token->data->expires_in - time();
    }

    /**
     * Gets the status of the current access token.
     *
     * @return int
     *         The status of the current access token:
     *           - `0`: No access token ;
     *           - `-1`: Access token will expire soon (1 minute or less) ;
     *           - `-2`: Access token is expired ;
     *           - `1`: Access token is valid.
     *
     * @since 1.0.0
     */
    public function getAccessTokenStatus()
    {
        if (null === $this->_state->token) {
            return 0;
        }

        $remaining = $this->getTokenExpire();

        if (0 >= $remaining) {
            return -2;
        }

        if (60 >= $remaining) {
            return -1;
        }

        return 1;
    }

    /**
     * Obtains a new access token from OAuth.
     *
     * This token is valid for one hour.
     *
     * @param string $clientSecret
     *        The OneDrive client secret.
     * @param string $code
     *        The code returned by OneDrive after successful log in.
     *
     * @throws \Exception
     *         Thrown if the redirect URI of this `Client` instance's state is
     *         not set.
     * @throws \Exception
     *         Thrown if the HTTP response body cannot be JSON-decoded.
     *
     * @since 1.0.0
     */
    public function obtainAccessToken($clientSecret, $code)
    {
        if (null === $this->_state->redirect_uri) {
            throw new \Exception(
                'The state\'s redirect URI must be set to call'
                    . ' obtainAccessToken()'
            );
        }

        $values = [
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->_state->redirect_uri,
            'client_secret' => (string) $clientSecret,
            'code'          => (string) $code,
            'grant_type'    => 'authorization_code',
        ];

        $response = $this->httpClient->post(
            self::TOKEN_URL,
            ['form_params' => $values]
        );

        $body = $response->getBody();
        $data = json_decode($body);

        if ($data === null) {
            throw new \Exception('json_decode() failed');
        }

        $this->_state->redirect_uri = null;

        $this->_state->token = (object) [
            'obtained' => time(),
            'data'     => $data,
        ];

        $this->graph->setAccessToken($this->_state->token->data->access_token);
    }

    /**
     * Renews the access token from OAuth.
     *
     * This token is valid for one hour.
     *
     * @param string $clientSecret
     *        The client secret.
     *
     * @since 1.1.0
     */
    public function renewAccessToken($clientSecret)
    {
        if (null === $this->_state->token->data->refresh_token) {
            throw new \Exception(
                'The refresh token is not set or no permission for'
                    . ' \'wl.offline_access\' was given to renew the token'
            );
        }

        $values = [
            'client_id'     => $this->clientId,
            'client_secret' => $clientSecret,
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->_state->token->data->refresh_token,
        ];

        $response = $this->httpClient->post(
            self::TOKEN_URL,
            ['form_params' => $values]
        );

        $body = $response->getBody();
        $data = json_decode($body);

        if ($data === null) {
            throw new \Exception('json_decode() failed');
        }

        $this->_state->token = (object) [
            'obtained' => time(),
            'data'     => $data,
        ];

        $this->graph->setAccessToken($this->_state->token->data->access_token);
    }

    /**
     * Gets the current user's drive.
     *
     * @return array
     *         The drives.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/drive_list?view=odsp-graph-online#list-the-current-users-drives
     *       List the current user's drives
     */
    public function getDrives()
    {
        $driveLocator = '/me/drives';
        $endpoint     = "$driveLocator";

        $response = $this
            ->graph
            ->createCollectionRequest('GET', $endpoint)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200) {
            throw new \Exception("Unexpected status code produced by 'GET $endpoint': $status");
        }

        $drives = $response->getResponseAsObject(Model\Drive::class);

        if (!is_array($drives)) {
            return [];
        }

        return array_map(function (Model\Drive $drive) {
            return new DriveProxy($this->graph, $drive);
        }, $drives);
    }

    /**
     * Gets the signed in user's drive.
     *
     * @return DriveProxy
     *         The drive.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/drive_get?view=odsp-graph-online#get-current-users-onedrive
     *       Get current user's OneDrive
     */
    public function getMyDrive()
    {
        $driveLocator = '/me/drive';
        $endpoint     = "$driveLocator";

        $response = $this
            ->graph
            ->createRequest('GET', $endpoint)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200) {
            throw new \Exception();
        }

        $drive = $response->getResponseAsObject(Model\Drive::class);

        return new DriveProxy($this->graph, $drive);
    }

    /**
     * Gets a drive by ID.
     *
     * @param string $driveId
     *        The drive ID.
     *
     * @return DriveProxy
     *         The drive.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/drive_get?view=odsp-graph-online#get-a-drive-by-id
     *       Get a drive by ID
     */
    public function getDriveById($driveId)
    {
        $driveLocator = "/drives/$driveId";
        $endpoint     = "$driveLocator";

        $response = $this
            ->graph
            ->createRequest('GET', $endpoint)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200) {
            throw new \Exception();
        }

        $drive = $response->getResponseAsObject(Model\Drive::class);

        return new DriveProxy($this->graph, $drive);
    }

    /**
     * Gets a user's OneDrive.
     *
     * @param string $idOrUserPrincipalName
     *        The ID or user principal name.
     *
     * @return DriveProxy
     *         The drive.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/drive_get?view=odsp-graph-online#get-a-users-onedrive
     *       Get a user's OneDrive
     */
    public function getDriveByUser($idOrUserPrincipalName)
    {
        $userLocator  = "/users/$idOrUserPrincipalName";
        $driveLocator = '/drive';
        $endpoint     = "$userLocator$driveLocator";

        $response = $this
            ->graph
            ->createRequest('GET', $endpoint)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200) {
            throw new \Exception();
        }

        $drive = $response->getResponseAsObject(Model\Drive::class);

        return new DriveProxy($this->graph, $drive);
    }

    /**
     * Gets the document library associated with a group.
     *
     * @param string $groupId
     *        The group ID.
     *
     * @return DriveProxy
     *         The drive.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/drive_get?view=odsp-graph-online#get-the-document-library-associated-with-a-group
     *       Get the document library associated with a group
     */
    public function getDriveByGroup($groupId)
    {
        $groupLocator = "/groups/$groupId";
        $driveLocator = '/drive';
        $endpoint     = "$groupLocator$driveLocator";

        $response = $this
            ->graph
            ->createRequest('GET', $endpoint)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200) {
            throw new \Exception();
        }

        $drive = $response->getResponseAsObject(Model\Drive::class);

        return new DriveProxy($this->graph, $drive);
    }

    /**
     * Gets the document library for a site.
     *
     * @param string $siteId
     *        The site ID.
     *
     * @return DriveProxy
     *         The drive.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/drive_get?view=odsp-graph-online#get-the-document-library-for-a-site
     *       Get the document library for a site
     */
    public function getDriveBySite($siteId)
    {
        $siteLocator  = "/sites/$siteId";
        $driveLocator = '/drive';
        $endpoint     = "$siteLocator$driveLocator";

        $response = $this
            ->graph
            ->createRequest('GET', $endpoint)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200) {
            throw new \Exception();
        }

        $drive = $response->getResponseAsObject(Model\Drive::class);

        return new DriveProxy($this->graph, $drive);
    }

    /**
     * Gets a drive item by ID and drive ID.
     *
     * @param string $driveId
     *        The drive ID.
     * @param string $itemId
     *        The drive item ID.
     *
     * @return DriveItemProxy
     *         The drive item.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/driveitem_get?view=odsp-graph-online
     *       Get a DriveItem resource
     */
    public function getDriveItemById($driveId, $itemId)
    {
        $driveLocator = "/drives/$driveId";
        $itemLocator  = "/items/$itemId";
        $endpoint     = "$driveLocator$itemLocator";

        $response = $this
            ->graph
            ->createRequest('GET', $endpoint)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200) {
            throw new \Exception();
        }

        $driveItem = $response->getResponseAsObject(Model\DriveItem::class);

        return new DriveItemProxy($this->graph, $driveItem);
    }

    /**
     * Gets a drive item by path.
     *
     * @param string $path
     *        The path.
     *
     * @return DriveItemProxy
     *         The drive item.
     *
     * @since 2.2.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/driveitem_get?view=odsp-graph-online
     *       Get a DriveItem resource
     */
    public function getDriveItemByPath($path)
    {
        $driveLocator = '/me/drive';
        $itemLocator  = "/root:$path";
        $endpoint     = "$driveLocator$itemLocator";

        $response = $this
            ->graph
            ->createRequest('GET', $endpoint)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200) {
            throw new \Exception("Unexpected status code produced by 'GET $endpoint': $status");
        }

        $driveItem = $response->getResponseAsObject(Model\DriveItem::class);

        return new DriveItemProxy($this->graph, $driveItem);
    }

    /**
     * Gets the root drive item.
     *
     * @return DriveItemProxy
     *         The root drive item.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/driveitem_get?view=odsp-graph-online
     *       Get a DriveItem resource
     */
    public function getRoot()
    {
        $driveLocator = '/me/drive';
        $itemLocator  = '/root';
        $endpoint     = "$driveLocator$itemLocator";

        $response = $this
            ->graph
            ->createRequest('GET', $endpoint)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200) {
            throw new \Exception("Unexpected status code produced by 'GET $endpoint': $status");
        }

        $driveItem = $response->getResponseAsObject(Model\DriveItem::class);

        return new DriveItemProxy($this->graph, $driveItem);
    }

    /**
     * Gets a special folder by name.
     *
     * @param string $specialFolderName
     *        The special folder name. Supported values:
     *          - 'documents' ;
     *          - 'photos' ;
     *          - 'cameraroll' ;
     *          - 'approot' ;
     *          - 'music'.
     *
     * @return DriveItemProxy
     *         The root drive item.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/drive_get_specialfolder?view=odsp-graph-online
     *       Get a special folder by name
     */
    public function getSpecialFolder($specialFolderName)
    {
        $driveLocator         = '/me/drive';
        $specialFolderLocator = "/special/$specialFolderName";
        $endpoint             = "$driveLocator$specialFolderLocator";

        $response = $this
            ->graph
            ->createRequest('GET', $endpoint)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200) {
            throw new \Exception("Unexpected status code produced by 'GET $endpoint': $status");
        }

        $driveItem = $response->getResponseAsObject(Model\DriveItem::class);

        return new DriveItemProxy($this->graph, $driveItem);
    }

    /**
     * Gets items shared with the signed-in user.
     *
     * @return array
     *         The shared drive items.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/drive_sharedwithme?view=odsp-graph-online
     *       List items shared with the signed-in user
     */
    public function getShared()
    {
        $driveLocator = '/me/drive';
        $endpoint     = "$driveLocator/sharedWithMe";

        $response = $this
            ->graph
            ->createCollectionRequest('GET', $endpoint)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200) {
            throw new \Exception("Unexpected status code produced by 'GET $endpoint': $status");
        }

        $driveItems = $response->getResponseAsObject(Model\DriveItem::class);

        if (!is_array($driveItems)) {
            return [];
        }

        return array_map(function (Model\DriveItem $driveItem) {
            return new DriveItemProxy($this->graph, $driveItem);
        }, $driveItems);
    }

    /**
     * Gets recent files.
     *
     * @return array
     *         The recent drive items.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/drive_recent?view=odsp-graph-online
     *       List recent files
     */
    public function getRecent()
    {
        $driveLocator = '/me/drive';
        $endpoint     = "$driveLocator/recent";

        $response = $this
            ->graph
            ->createCollectionRequest('GET', $endpoint)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200) {
            throw new \Exception("Unexpected status code produced by 'GET $endpoint': $status");
        }

        $driveItems = $response->getResponseAsObject(Model\DriveItem::class);

        if (!is_array($driveItems)) {
            return [];
        }

        return array_map(function (Model\DriveItem $driveItem) {
            return new DriveItemProxy($this->graph, $driveItem);
        }, $driveItems);
    }

    // Legacy support //////////////////////////////////////////////////////////

    /**
     * Creates a folder in the current OneDrive account.
     *
     * This operation is supported only on folders (as opposed to files): it
     * fails if `$parentId` does not refer to a folder.
     *
     * @param string $name
     *        The name of the OneDrive folder to be created.
     * @param null|string $parentId
     *        The ID of the OneDrive folder into which to create the OneDrive
     *        folder, or null to create it in the OneDrive root folder. Default:
     *        null.
     * @param null|string $description
     *        The description of the OneDrive folder to be created, or null to
     *        create it without a description. Default: null.
     *
     * @return Folder
     *         The folder created, as a Folder instance referencing to the
     *         OneDrive folder created.
     *
     * @since 1.0.0
     *
     * @deprecated 2.0.0 Superseded by
     *             \Krizalys\Onedrive\Proxy\DriveItemProxy::createFolder().
     *
     * @see \Krizalys\Onedrive\Proxy\DriveItemProxy::createFolder()
     */
    public function createFolder($name, $parentId = null, $description = null)
    {
        $message = sprintf(
            '%s() is deprecated and will be removed in version 3;'
                . ' use \Krizalys\Onedrive\Proxy\DriveItemProxy::createFolder()'
                . ' instead',
            __METHOD__
        );

        @trigger_error($message, E_USER_DEPRECATED);
        $drive = $this->getMyDrive();

        $item = $parentId !== null ?
            $this->getDriveItemById($drive->id, $parentId)
            : $drive->getRoot();

        $options = [];

        if ($description !== null) {
            $options += [
                'description' => (string) $description,
            ];
        }

        $item    = $item->createFolder($name, $options);
        $options = $this->buildOptions($item, ['parent_id' => $parentId]);

        return new Folder($this, $item->id, $options);
    }

    /**
     * Creates a file in the current OneDrive account.
     *
     * This operation is supported only on folders (as opposed to files): it
     * fails if `$parentId` does not refer to a folder.
     *
     * @param string $name
     *        The name of the OneDrive file to be created.
     * @param null|string $parentId
     *        The ID of the OneDrive folder into which to create the OneDrive
     *        file, or null to create it in the OneDrive root folder. Default:
     *        null.
     * @param string|resource|\GuzzleHttp\Psr7\Stream $content
     *        The content of the OneDrive file to be created, as a string or as
     *        a resource to an already opened file. Default: ''.
     * @param array $options
     *        The options.
     *
     * @return File
     *         The file created, as File instance referencing to the OneDrive
     *         file created.
     *
     * @throws \Exception
     *         Thrown on I/O errors.
     *
     * @since 1.0.0
     *
     * @deprecated 2.0.0 Superseded by
     *             \Krizalys\Onedrive\Proxy\DriveItemProxy::upload().
     *
     * @see \Krizalys\Onedrive\Proxy\DriveItemProxy::upload()
     *
     * @todo Support name conflict behavior.
     * @todo Support content type in options.
     */
    public function createFile(
        $name,
        $parentId = null,
        $content = '',
        array $options = []
    ) {
        $message = sprintf(
            '%s() is deprecated and will be removed in version 3;'
                . ' use \Krizalys\Onedrive\Proxy\DriveItemProxy::upload()'
                . ' instead',
            __METHOD__
        );

        @trigger_error($message, E_USER_DEPRECATED);
        $drive = $this->getMyDrive();

        $item = $parentId !== null ?
            $this->getDriveItemById($drive->id, $parentId)
            : $drive->getRoot();

        $item    = $item->upload($name, $content);
        $options = $this->buildOptions($item, ['parent_id' => $parentId]);

        return new File($this, $item->id, $options);
    }

    /**
     * Fetches a drive item from the current OneDrive account.
     *
     * @param null|string $driveItemId
     *        The unique ID of the OneDrive drive item to fetch, or null to
     *        fetch the OneDrive root folder. Default: null.
     *
     * @return object
     *         The drive item fetched, as a DriveItem instance referencing to
     *         the OneDrive drive item fetched.
     *
     * @since 2.0.0
     *
     * @deprecated 2.0.0 Superseded by
     *             \Krizalys\Onedrive\Client::getDriveItemById()..
     *
     * @see \Krizalys\Onedrive\Client::getDriveItemById()
     */
    public function fetchDriveItem($driveItemId = null)
    {
        $message = sprintf(
            '%s() is deprecated and will be removed in version 3;'
                . ' use \Krizalys\Onedrive\Client::getDriveItemById() instead.',
            __METHOD__
        );

        @trigger_error($message, E_USER_DEPRECATED);
        $drive = $this->getMyDrive();

        $item = $driveItemId !== null ?
            $this->getDriveItemById($drive->id, $driveItemId)
            : $drive->getRoot();

        $options = $this->buildOptions($item, ['parent_id' => $driveItemId]);

        return $this->isFolder($item) ?
            new Folder($this, $item->id, $options)
            : new File($this, $item->id, $options);
    }

    /**
     * Fetches the root folder from the current OneDrive account.
     *
     * @return Folder
     *         The root folder, as a Folder instance referencing to the OneDrive
     *         root folder.
     *
     * @since 1.0.0
     *
     * @deprecated 2.0.0 Superseded by
     *             \Krizalys\Onedrive\Client::getRoot().
     *
     * @see \Krizalys\Onedrive\Client::getRoot()
     */
    public function fetchRoot()
    {
        $message = sprintf(
            '%s() is deprecated and will be removed in version 3;'
                . ' use \Krizalys\Onedrive\Client::getRoot() instead.',
            __METHOD__
        );

        @trigger_error($message, E_USER_DEPRECATED);
        $item    = $this->getRoot();
        $options = $this->buildOptions($item);

        return new Folder($this, $item->id, $options);
    }

    /**
     * Fetches the "Camera Roll" folder from the current OneDrive account.
     *
     * @return Folder
     *         The "Camera Roll" folder, as a Folder instance referencing to the
     *         OneDrive "Camera Roll" folder.
     *
     * @since 1.0.0
     *
     * @deprecated 2.0.0 Superseded by
     *             \Krizalys\Onedrive\Client::getSpecialFolder('cameraroll').
     *
     * @see \Krizalys\Onedrive\Client::getSpecialFolder()
     */
    public function fetchCameraRoll()
    {
        $message = sprintf(
            '%s() is deprecated and will be removed in version 3; use'
                . ' \Krizalys\Onedrive\Client::getSpecialFolder(\'cameraroll\')'
                . ' instead.',
            __METHOD__
        );

        @trigger_error($message, E_USER_DEPRECATED);
        $item    = $this->getSpecialFolder('cameraroll');
        $options = $this->buildOptions($item);

        return new Folder($this, $item->id, $options);
    }

    /**
     * Fetches the "Documents" folder from the current OneDrive account.
     *
     * @return Folder
     *         The "Documents" folder, as a Folder instance referencing to the
     *         OneDrive "Documents" folder.
     *
     * @since 1.0.0
     *
     * @deprecated 2.0.0 Superseded by
     *             \Krizalys\Onedrive\Client::getSpecialFolder('documents').
     *
     * @see \Krizalys\Onedrive\Client::getSpecialFolder()
     */
    public function fetchDocs()
    {
        $message = sprintf(
            '%s() is deprecated and will be removed in version 3; use'
                . ' \Krizalys\Onedrive\Client::getSpecialFolder(\'documents\')'
                . ' instead.',
            __METHOD__
        );

        @trigger_error($message, E_USER_DEPRECATED);
        $item    = $this->getSpecialFolder('documents');
        $options = $this->buildOptions($item);

        return new Folder($this, $item->id, $options);
    }

    /**
     * Fetches the "Pictures" folder from the current OneDrive account.
     *
     * @return Folder
     *         The "Pictures" folder, as a Folder instance referencing to the
     *         OneDrive "Pictures" folder.
     *
     * @since 1.0.0
     *
     * @deprecated 2.0.0 Superseded by
     *             \Krizalys\Onedrive\Client::getSpecialFolder().
     *
     * @see \Krizalys\Onedrive\Client::getSpecialFolder()
     */
    public function fetchPics()
    {
        $message = sprintf(
            '%s() is deprecated and will be removed in version 3; use'
                . ' \Krizalys\Onedrive\Client::getSpecialFolder(\'photos\')'
                . ' instead.',
            __METHOD__
        );

        @trigger_error($message, E_USER_DEPRECATED);
        $item    = $this->getSpecialFolder('photos');
        $options = $this->buildOptions($item);

        return new Folder($this, $item->id, $options);
    }

    /**
     * Fetches the properties of a drive item in the current OneDrive account.
     *
     * @param null|string $driveItemId
     *        The drive item ID, or null to fetch the OneDrive root folder.
     *        Default: null.
     *
     * @return object
     *         The properties of the drive item fetched.
     *
     * @since 1.0.0
     *
     * @deprecated 2.0.0 Superseded by
     *             \Krizalys\Onedrive\Client::getDriveItemById().
     *
     * @see \Krizalys\Onedrive\Client::getDriveItemById()
     */
    public function fetchProperties($driveItemId = null)
    {
        $message = sprintf(
            '%s() is deprecated and will be removed in version 3;'
                . ' use \Krizalys\Onedrive\Client::getDriveItemById() instead',
            __METHOD__
        );

        @trigger_error($message, E_USER_DEPRECATED);
        $drive = $this->getMyDrive();

        $item = $driveItemId !== null ?
            $this->getDriveItemById($drive->id, $driveItemId)
            : $drive->getRoot();

        $options = $this->buildOptions(
            $item,
            [
                'id'        => $item->id,
                'parent_id' => $driveItemId,
            ]
        );

        return (object) $options;
    }

    /**
     * Fetches the drive items in a folder in the current OneDrive account.
     *
     * This operation is supported only on folders (as opposed to files): it
     * fails if `$parentId` does not refer to a folder.
     *
     * @param null|string $driveItemId
     *        The drive item ID, or null to fetch the OneDrive root folder.
     *        Default: null.
     *
     * @return array
     *         The drive items in the folder fetched, as DriveItem instances
     *         referencing OneDrive drive items.
     *
     * @since 2.0.0
     *
     * @deprecated 2.0.0 Superseded by
     *             \Krizalys\Onedrive\Proxy\DriveItemProxy::children.
     *
     * @see \Krizalys\Onedrive\Proxy\DriveItemProxy::children
     */
    public function fetchDriveItems($driveItemId = null)
    {
        $message = sprintf(
            '%s() is deprecated and will be removed in version 3;'
                . ' use \Krizalys\Onedrive\Proxy\DriveItemProxy::children'
                . ' instead.',
            __METHOD__
        );

        @trigger_error($message, E_USER_DEPRECATED);
        $drive = $this->getMyDrive();

        $item = $driveItemId !== null ?
            $this->getDriveItemById($drive->id, $driveItemId)
            : $drive->getRoot();

        return array_map(function (DriveItemProxy $item) use ($driveItemId) {
            $options = $this->buildOptions($item, ['parent_id' => $driveItemId]);

            return $this->isFolder($item) ?
                new Folder($this, $item->id, $options)
                : new File($this, $item->id, $options);
        }, $item->children);
    }

    /**
     * Updates the properties of a drive item in the current OneDrive account.
     *
     * @param string $driveItemId
     *        The unique ID of the drive item to update.
     * @param array|object $properties
     *        The properties to update. Default: [].
     * @param bool $temp
     *        Option to allow save to a temporary file in case of large files.
     *
     * @throws \Exception
     *         Thrown on I/O errors.
     *
     * @since 2.0.0
     *
     * @deprecated 2.0.0 Superseded by
     *             \Krizalys\Onedrive\Proxy\DriveItemProxy::rename().
     *
     * @see \Krizalys\Onedrive\Proxy\DriveItemProxy::rename()
     */
    public function updateDriveItem($driveItemId, $properties = [], $temp = false)
    {
        $message = sprintf(
            '%s() is deprecated and will be removed in version 3;'
                . ' use \Krizalys\Onedrive\Proxy\DriveItemProxy::rename()'
                . ' instead',
            __METHOD__
        );

        @trigger_error($message, E_USER_DEPRECATED);
        $drive = $this->getMyDrive();

        $item = $driveItemId !== null ?
            $this->getDriveItemById($drive->id, $driveItemId)
            : $drive->getRoot();

        $options = (array) $properties;

        if (array_key_exists('name', $options)) {
            $name = $options['name'];
            unset($options['name']);
        } else {
            $name = $item->name;
        }

        $item    = $item->rename($name, $options);
        $options = $this->buildOptions($item, ['parent_id' => $driveItemId]);

        return new Folder($this, $item->id, $options);
    }

    /**
     * Moves a drive item into another folder.
     *
     * `$destinationId` must refer to a folder.
     *
     * @param string $driveItemId
     *        The unique ID of the drive item to move.
     * @param null|string $destinationId
     *        The unique ID of the folder into which to move the drive item, or
     *        null to move it to the OneDrive root folder. Default: null.
     *
     * @since 2.0.0
     *
     * @deprecated 2.0.0 Superseded by
     *             \Krizalys\Onedrive\Proxy\DriveItemProxy::move().
     *
     * @see \Krizalys\Onedrive\Proxy\DriveItemProxy::move()
     */
    public function moveDriveItem($driveItemId, $destinationId = null)
    {
        $message = sprintf(
            '%s() is deprecated and will be removed in version 3;'
                . ' use \Krizalys\Onedrive\Proxy\DriveItemProxy::move()'
                . ' instead.',
            __METHOD__
        );

        @trigger_error($message, E_USER_DEPRECATED);
        $drive = $this->getMyDrive();
        $item  = $this->getDriveItemById($drive->id, $driveItemId);

        $destination = $destinationId !== null ?
            $this->getDriveItemById($drive->id, $destinationId)
            : $drive->getRoot();

        $item->move($destination);
    }

    /**
     * Copies a file into another folder.
     *
     * This operation is supported only on files (as opposed to folders): it
     * fails if `$driveItemId` does not refer to a file.
     *
     * Additionally, `$destinationId` must refer to a folder.
     *
     * @param string $driveItemId
     *        The unique ID of the file to copy.
     * @param null|string $destinationId
     *        The unique ID of the folder into which to copy the file, or null
     *        to copy it to the OneDrive root folder. Default: null.
     *
     * @since 1.0.0
     *
     * @deprecated 2.0.0 Superseded by
     *             \Krizalys\Onedrive\Proxy\DriveItemProxy::copy().
     *
     * @see \Krizalys\Onedrive\Proxy\DriveItemProxy::copy()
     */
    public function copyFile($driveItemId, $destinationId = null)
    {
        $message = sprintf(
            '%s() is deprecated and will be removed in version 3;'
                . ' use \Krizalys\Onedrive\Proxy\DriveItemProxy::copy()'
                . ' instead.',
            __METHOD__
        );

        @trigger_error($message, E_USER_DEPRECATED);
        $drive = $this->getMyDrive();
        $item  = $this->getDriveItemById($drive->id, $driveItemId);

        $destination = $destinationId !== null ?
            $this->getDriveItemById($drive->id, $destinationId)
            : $drive->getRoot();

        $item->copy($destination);
    }

    /**
     * Deletes a drive item in the current OneDrive account.
     *
     * @param string $driveItemId
     *        The unique ID of the drive item to delete.
     *
     * @since 2.0.0
     *
     * @deprecated 2.0.0 Superseded by
     *             \Krizalys\Onedrive\Proxy\DriveItemProxy::delete().
     *
     * @see \Krizalys\Onedrive\Proxy\DriveItemProxy::delete()
     */
    public function deleteDriveItem($driveItemId)
    {
        $message = sprintf(
            '%s() is deprecated and will be removed in version 3;'
                . ' use \Krizalys\Onedrive\Proxy\DriveItemProxy::delete()'
                . ' instead',
            __METHOD__
        );

        @trigger_error($message, E_USER_DEPRECATED);
        $drive = $this->getMyDrive();
        $item  = $this->getDriveItemById($drive->id, $driveItemId);
        $item->delete();
    }

    /**
     * Fetches the quota of the current OneDrive account.
     *
     * @return object
     *         An object with the following properties:
     *           - 'quota' (int) The total space, in bytes ;
     *           - 'available' (int) The available space, in bytes.
     *
     * @since 1.0.0
     *
     * @deprecated 2.0.0 Superseded by
     *             \Krizalys\Onedrive\Proxy\DriveProxy::quota.
     *
     * @see \Krizalys\Onedrive\Proxy\DriveProxy::quota
     */
    public function fetchQuota()
    {
        $message = sprintf(
            '%s() is deprecated and will be removed in version 3;'
                . ' use \Krizalys\Onedrive\Proxy\DriveProxy::quota instead',
            __METHOD__
        );

        @trigger_error($message, E_USER_DEPRECATED);
        $drive = $this->getMyDrive();
        $quota = $drive->quota;

        return (object) [
            'quota'     => $quota->total,
            'available' => $quota->remaining,
        ];
    }

    /**
     * Fetches the recent documents uploaded to the current OneDrive account.
     *
     * @return object
     *         An object with the following properties:
     *           - 'data' (array) The list of the recent documents uploaded.
     *
     * @since 1.0.0
     *
     * @deprecated 2.0.0 Superseded by
     *             \Krizalys\Onedrive\Client::getRecent().
     *
     * @see \Krizalys\Onedrive\Client::getRecent()
     */
    public function fetchRecentDocs()
    {
        $message = sprintf(
            '%s() is deprecated and will be removed in version 3;'
                . ' use \Krizalys\Onedrive\Client::getRecent() instead',
            __METHOD__
        );

        @trigger_error($message, E_USER_DEPRECATED);
        $items = $this->getRecent();

        return (object) [
            'data' => array_map(function (DriveItemProxy $item) {
                return (object) $this->buildOptions($item);
            }, $items),
        ];
    }

    /**
     * Fetches the drive items shared with the current OneDrive account.
     *
     * @return object
     *         An object with the following properties:
     *           - 'data' (array) The list of the shared drive items.
     *
     * @since 1.0.0
     *
     * @deprecated 2.0.0 Superseded by
     *             \Krizalys\Onedrive\Client::getShared().
     *
     * @see \Krizalys\Onedrive\Client::getShared()
     */
    public function fetchShared()
    {
        $message = sprintf(
            '%s() is deprecated and will be removed in version 3;'
                . ' use \Krizalys\Onedrive\Client::getShared() instead',
            __METHOD__
        );

        @trigger_error($message, E_USER_DEPRECATED);
        $items = $this->getShared();

        return (object) [
            'data' => array_map(function (DriveItemProxy $item) {
                return (object) $this->buildOptions($item);
            }, $items),
        ];
    }

    /**
     * Checks whether a given drive item is a folder.
     *
     * @param DriveItemProxy $item
     *        The drive item.
     *
     * @return bool
     *         Whether the drive item is a folder.
     *
     * @since 2.0.0
     *
     * @deprecated 2.0.0 Deprecated dependency.
     */
    public function isFolder(DriveItemProxy $item)
    {
        return $item->folder !== null || $item->specialFolder !== null;
    }

    /**
     * Builds options for legacy File and Folder constructors.
     *
     * @param DriveItemProxy $item
     *        The drive item.
     * @param array $options
     *        The options.
     *
     * @return array
     *         The options.
     *
     * @since 2.0.0
     *
     * @deprecated 2.0.0 Deprecated dependency.
     */
    public function buildOptions(DriveItemProxy $item, array $options = [])
    {
        $defaultOptions = [
            'from' => (object) [
                'name' => null,
                'id'   => null,
            ],
        ];

        if ($item->id !== null) {
            $defaultOptions['id'] = $item->id;
        }

        if ($item->parentReference->id !== null) {
            $defaultOptions['parent_id'] = $item->parentReference->id;
        }

        if ($item->name !== null) {
            $defaultOptions['name'] = $item->name;
        }

        if ($item->description !== null) {
            $defaultOptions['description'] = $item->description;
        }

        if ($item->size !== null) {
            $defaultOptions['size'] = $item->size;
        }

        if ($item->createdDateTime !== null) {
            $defaultOptions['created_time'] = $item->createdDateTime->format(self::LEGACY_DATETIME_FORMAT);
        }

        if ($item->lastModifiedDateTime !== null) {
            $defaultOptions['updated_time'] = $item->lastModifiedDateTime->format(self::LEGACY_DATETIME_FORMAT);
        }

        return $defaultOptions + $options;
    }
}

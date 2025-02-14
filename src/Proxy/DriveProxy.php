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

use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\Drive;
use Microsoft\Graph\Model\DriveItem;

/**
 * A proxy to a \Microsoft\Graph\Model\Drive instance.
 *
 * @property-read string $driveType
 *                The drive type.
 * @property-read \Krizalys\Onedrive\Proxy\IdentitySetProxy $owner
 *                The owner.
 * @property-read \Krizalys\Onedrive\Proxy\QuotaProxy $quota
 *                The quota.
 * @property-read \Krizalys\Onedrive\Proxy\SharepointIdsProxy $sharePointIds
 *                The SharePoint IDs.
 * @property-read \Krizalys\Onedrive\Proxy\SystemFacetProxy $system
 *                The system facet.
 * @property-read \Krizalys\Onedrive\Proxy\DriveItemProxy[] $items
 *                The items.
 * @property-read \Krizalys\Onedrive\Proxy\GraphListProxy $list
 *                The list.
 * @property-read \Krizalys\Onedrive\Proxy\DriveItemProxy $root
 *                The root.
 * @property-read \Krizalys\Onedrive\Proxy\DriveItemProxy special
 *                The special.
 *
 * @since 2.0.0
 *
 * @link https://github.com/microsoftgraph/msgraph-sdk-php/blob/dev/src/Model/Drive.php
 */
class DriveProxy extends BaseItemProxy
{
    /**
     * Constructor.
     *
     * @param Graph $graph
     *        The Microsoft Graph.
     * @param Drive $drive
     *        The drive.
     *
     * @since 2.0.0
     */
    public function __construct(Graph $graph, Drive $drive)
    {
        parent::__construct($graph, $drive);
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
        $drive = $this->entity;

        switch ($name) {
            case 'driveType':
                return $drive->getDriveType();

            case 'owner':
                $owner = $drive->getOwner();
                return $owner !== null ? new IdentitySetProxy($this->graph, $owner) : null;

            case 'quota':
                $quota = $drive->getQuota();
                return $quota !== null ? new QuotaProxy($this->graph, $quota) : null;

            case 'sharePointIds':
                $sharePointIds = $drive->getSharePointIds();
                return $sharePointIds !== null ? new SharepointIdsProxy($this->graph, $sharePointIds) : null;

            case 'system':
                $system = $drive->getSystem();
                return $system !== null ? new SystemFacetProxy($this->graph, $system) : null;

            case 'items':
                $items = $drive->getItems();

                return $items !== null ? array_map(function (DriveItem $item) {
                    return new DriveItemProxy($this->graph, $item);
                }, $items) : null;

            case 'list':
                $list = $drive->getList();
                return $list !== null ? new GraphListProxy($this->graph, $list) : null;

            case 'root':
                $root = $drive->getRoot();
                return $root !== null ? new DriveItemProxy($this->graph, $root) : null;

            case 'special':
                $special = $drive->getSpecial();
                return $special !== null ? new DriveItemProxy($this->graph, $special) : null;

            default:
                return parent::__get($name);
        }
    }

    /**
     * Gets the root of this instance.
     *
     * @return DriveItemProxy
     *         The root.
     *
     * @since 2.0.0
     *
     * @link https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/driveitem_get?view=odsp-graph-online
     *       Get a DriveItem resource
     */
    public function getRoot()
    {
        $driveLocator = "/drives/{$this->id}";
        $itemLocator  = '/items/root';
        $endpoint     = "$driveLocator$itemLocator";

        $response = $this
            ->graph
            ->createRequest('GET', $endpoint)
            ->execute();

        $status = $response->getStatus();

        if ($status != 200) {
            throw new \Exception("Unexpected status code produced by 'GET $endpoint': $status");
        }

        $driveItem = $response->getResponseAsObject(DriveItem::class);

        return new DriveItemProxy($this->graph, $driveItem);
    }
}

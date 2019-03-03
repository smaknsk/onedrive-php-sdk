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
use Microsoft\Graph\Model\RemoteItem;

/**
 * A proxy to a \Microsoft\Graph\Model\RemoteItem instance.
 *
 * @since 2.0.0
 *
 * @link https://github.com/microsoftgraph/msgraph-sdk-php/blob/dev/src/Model/RemoteItem.php
 */
class RemoteItemProxy extends EntityProxy
{
    /**
     * Constructor.
     *
     * @param Graph $graph
     *        The Microsoft Graph.
     * @param RemoteItem $remoteItem
     *        The remote item.
     *
     * @since 2.0.0
     */
    public function __construct(Graph $graph, RemoteItem $remoteItem)
    {
        parent::__construct($graph, $remoteItem);
    }
}

<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Varun Patil <radialapps@gmail.com>
 * @author Varun Patil <radialapps@gmail.com>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Memories\ClustersBackend;

use OCA\Memories\Db\TimelineQuery;
use OCA\Memories\Exceptions;
use OCA\Memories\Util;

class AlbumsBackend extends Backend
{
    protected TimelineQuery $timelineQuery;

    public function __construct(
        TimelineQuery $timelineQuery
    ) {
        $this->timelineQuery = $timelineQuery;
    }

    public function appName(): string
    {
        return 'Albums';
    }

    public function isEnabled(): bool
    {
        return Util::albumsIsEnabled();
    }

    public function clusterName(string $name)
    {
        return explode('/', $name)[1];
    }

    public function getClusters(): array
    {
        /** @var \OCP\IRequest $request */
        $request = \OC::$server->get(\OCP\IRequest::class);

        // Run actual query
        $list = [];
        $t = (int) $request->getParam('t', 0);
        if ($t & 1) { // personal
            $list = array_merge($list, $this->timelineQuery->getAlbums(Util::getUID()));
        }
        if ($t & 2) { // shared
            $list = array_merge($list, $this->timelineQuery->getAlbums(Util::getUID(), true));
        }

        // Remove elements with duplicate album_id
        $seenIds = [];
        $list = array_filter($list, function ($item) use (&$seenIds) {
            if (\in_array($item['album_id'], $seenIds, true)) {
                return false;
            }
            $seenIds[] = $item['album_id'];

            return true;
        });

        // Convert $list to sequential array
        return array_values($list);
    }

    public function getPhotos(string $name, ?int $limit = null): array
    {
        // Get album
        $album = $this->timelineQuery->getAlbumIfAllowed(Util::getUID(), $name);
        if (null === $album) {
            throw Exceptions::NotFound("album {$name}");
        }

        // Get files
        $id = (int) $album['album_id'];

        return $this->timelineQuery->getAlbumPhotos($id, $limit) ?? [];
    }
}

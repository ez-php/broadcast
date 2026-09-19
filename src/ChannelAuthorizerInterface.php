<?php

declare(strict_types=1);

namespace EzPhp\Broadcast;

/**
 * Interface ChannelAuthorizerInterface
 *
 * Optional access-control hook checked by Broadcaster before a publish.
 * Not a presence-channel/member-list system — a single yes/no decision
 * for "may this event be published to this channel at all."
 *
 * @package EzPhp\Broadcast
 */
interface ChannelAuthorizerInterface
{
    /**
     * @param string $channel The target channel name.
     *
     * @return bool True to allow the publish, false to deny it.
     */
    public function authorize(string $channel): bool;
}

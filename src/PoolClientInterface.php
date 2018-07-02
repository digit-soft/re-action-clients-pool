<?php

namespace Reaction\ClientsPool;

use Evenement\EventEmitterInterface;

/**
 * Interface ClientInterface
 * @package Reaction\ClientsPool
 *
 * @property integer $createdAt
 */
interface PoolClientInterface extends EventEmitterInterface
{
    const CLIENT_POOL_EVENT_CLOSE = 'client_close';
    const CLIENT_POOL_EVENT_CHANGE_STATE = 'change_state';
    const CLIENT_POOL_EVENT_CHANGE_QUEUE = 'change_queue';

    const CLIENT_POOL_STATE_READY = 0;
    const CLIENT_POOL_STATE_BUSY = 1;
    const CLIENT_POOL_STATE_NOT_READY = 2;
    const CLIENT_POOL_STATE_CLOSING = 3;

    /**
     * Get client unique ID
     * @return string
     */
    public function getClientId();

    /**
     * Get client state
     * @return string
     */
    public function getClientState();

    /**
     * Get queue count
     * @return int
     */
    public function getClientQueueCount();

    /**
     * Close client
     */
    public function clientClose();
}
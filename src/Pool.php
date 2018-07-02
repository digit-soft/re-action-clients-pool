<?php

namespace Reaction\ClientsPool;

use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use Reaction\Base\Component;
use Reaction\Helpers\ArrayHelper;

/**
 * Class Pool
 * @package Reaction\ClientsPool
 */
class Pool extends Component implements PoolInterface
{
    /**
     * @var int|null Time to live client
     */
    public $clientTtl = null;
    /**
     * @var int Maximum clients count
     */
    public $maxCount = 30;
    /**
     * @var int|null Maximum client queue count
     */
    public $maxQueueCount = null;
    /**
     * @var array|\Closure Client config array used to create clients
     */
    public $clientConfig = [];
    /**
     * @var LoopInterface Event loop used
     */
    public $loop;

    /**
     * @var PoolClientInterface[] Clients pool
     */
    protected $_clients = [];
    /**
     * @var string[] Client states data
     */
    protected $_clientsStates = [];
    /**
     * @var string[] Client queue counters
     */
    protected $_clientsQueueCounters = [];
    /**
     * @var TimerInterface
     */
    protected $_clientsCleanupTimer;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->createCleanupTimer();
    }

    /**
     * Get client from pool
     * @return PoolClientInterface
     */
    public function getClient()
    {
        if (($client = $this->getClientIdle()) !== null) {
            return $client;
        } elseif ($this->maxCount > count($this->_clients)) {
            return $this->createClient();
        } elseif (($client = $this->getClientLeastBusy()) !== null) {
            return $client;
        }
        return $this->createClient();
    }

    /**
     * Get client with empty queue
     * @return PoolClientInterface|null
     */
    public function getClientIdle()
    {
        if (!empty($this->_clientsStates)) {
            $states = $this->_clientsStates;
            asort($states);
            foreach ($states as $clientId => $state) {
                if ($state === PoolClientInterface::CLIENT_POOL_STATE_READY && isset($this->_clients[$clientId])) {
                    return $this->_clients[$clientId];
                }
            }
        }
        return null;
    }

    /**
     * Get least busy client
     * @return PoolClientInterface|null
     */
    public function getClientLeastBusy()
    {
        if (!empty($this->_clientsQueueCounters)) {
            $counters = $this->_clientsQueueCounters;
            asort($counters);
            $minCounter = reset($counters);
            if (isset($this->maxQueueCount) && $minCounter >= $this->maxQueueCount && !$this->isReachedMaxClients()) {
                return null;
            }
            $clientId = key($counters);
            return isset($this->_clients[$clientId]) ? $this->_clients[$clientId] : null;
        }
        return null;
    }

    /**
     * Create client
     * @param bool  $addToPool
     * @return PoolClientInterface
     */
    public function createClient($addToPool = true)
    {
        $config = $this->clientConfig;
        /** @var PoolClientInterface $client */
        if (!is_array($config) && $config instanceof \Closure) {
            $client = $config();
        } elseif (is_array($config) && ArrayHelper::isIndexed($config)) {
            $client = \Reaction::create(...$config);
        } else {
            $client = \Reaction::create($config);
        }
        $client->createdAt = time();
        if ($addToPool) {
            $clientId = $client->getClientId();
            $this->_clients[$clientId] = $client;
            //Bind event handlers to client
            $this->bindClientEvents($client);
        }
        return $client;
    }

    /**
     * Check that clients max count reached
     * @return bool
     */
    public function isReachedMaxClients()
    {
        return isset($this->maxCount) && $this->maxCount <= count($this->_clients);
    }

    /**
     * Close all clients/connections
     */
    public function closeAll()
    {
        foreach ($this->_clients as $client) {
            $client->clientClose();
        }
    }

    /**
     * Bind event handlers to client instance
     * @param PoolClientInterface $client
     */
    protected function bindClientEvents(PoolClientInterface $client)
    {
        $clientId = $client->getClientId();
        $this->_clientsStates[$clientId] = PoolClientInterface::CLIENT_POOL_STATE_READY;
        $this->_clientsQueueCounters[$clientId] = 0;

        //Remove client from pool on close
        $client->once(PoolClientInterface::CLIENT_POOL_EVENT_CLOSE, function() use ($client, $clientId) {
            unset($this->_clients[$clientId]);
            unset($this->_clientsStates[$clientId]);
            unset($this->_clientsQueueCounters[$clientId]);
            $client->removeAllListeners(PoolClientInterface::CLIENT_POOL_EVENT_CHANGE_STATE);
            $client->removeAllListeners(PoolClientInterface::CLIENT_POOL_EVENT_CHANGE_QUEUE);
        });
        //Change client state
        $client->on(PoolClientInterface::CLIENT_POOL_EVENT_CHANGE_STATE, function($state) use ($client, $clientId) {
            $this->_clientsStates[$clientId] = $state;
        });
        //Change client queue count
        $client->on(PoolClientInterface::CLIENT_POOL_EVENT_CHANGE_QUEUE, function($queueCount) use ($client, $clientId) {
            $this->_clientsQueueCounters[$clientId] = $queueCount;
        });
    }

    /**
     * Create cleanup timer if Client TTL is configured
     */
    protected function createCleanupTimer()
    {
        if (!isset($this->clientTtl)) {
            return;
        }
        $this->_clientsCleanupTimer = $this->loop->addPeriodicTimer(1, function($timer) {
            $expireTime = time() - $this->clientTtl;
            foreach ($this->_clients as $client) {
                if ($client->createdAt < $expireTime) {
                    $client->clientClose();
                }
            }
        });
    }
}
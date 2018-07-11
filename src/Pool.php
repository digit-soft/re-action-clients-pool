<?php

namespace Reaction\ClientsPool;

use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use Reaction\Base\Component;
use Reaction\Helpers\ArrayHelper;

/**
 * Generic `PoolInterface` implementation.
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
     * @var TimerInterface Cleanup timer instance
     */
    protected $_clientsCleanupTimer;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        $this->createCleanupTimer();
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getClientIdle()
    {
        if (!empty($this->_clientsStates)) {
            $states = $this->_clientsStates;
            asort($states);
            foreach ($states as $clientId => $state) {
                if ($state === PoolClientInterface::CLIENT_POOL_STATE_READY && $this->clientExists($clientId)) {
                    return $this->_clients[$clientId];
                }
            }
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientLeastBusy()
    {
        $counters = !empty($this->_clientsQueueCounters)
            ? array_filter($this->_clientsQueueCounters, function($value, $key) {
                return $this->getClientState($key) <= PoolClientInterface::CLIENT_POOL_STATE_BUSY;
            }, ARRAY_FILTER_USE_BOTH)
            : $this->_clientsQueueCounters;
        if (!empty($counters)) {
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
     * {@inheritdoc}
     */
    public function createClient($addToPool = true)
    {
        $config = $this->clientConfig;
        if (!is_array($config) && $config instanceof \Closure) {
            $config = $config();
        }
        /** @var PoolClientInterface $client */
        if (!is_array($config) && $config instanceof PoolClientInterface) {
            $client = $config;
        } elseif (is_array($config) && ArrayHelper::isIndexed($config)) {
            $client = \Reaction::create(...$config);
        } else {
            $client = \Reaction::create($config);
        }
        $client->createdAt = time();
        if ($addToPool) {
            $client->pool = $this;
            $clientId = $client->getClientId();
            $this->_clients[$clientId] = $client;
            //Bind event handlers to client
            $this->bindClientEvents($client);
        }
        return $client;
    }

    /**
     * {@inheritdoc}
     */
    public function isReachedMaxClients()
    {
        return isset($this->maxCount) && $this->maxCount <= count($this->_clients);
    }

    /**
     * {@inheritdoc}
     */
    public function clientExists($id)
    {
        return isset($this->_clients[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function closeAll()
    {
        foreach ($this->_clients as $client) {
            $client->clientClose();
        }
    }

    /**
     * Get client state
     * @param string $id
     * @return null|string
     */
    protected function getClientState($id)
    {
        return $this->clientExists($id) ? $this->_clientsStates[$id] : null;
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
        $this->_clientsCleanupTimer = $this->loop->addPeriodicTimer(3, function($timer) {
            $expireTime = time() - $this->clientTtl;
            foreach ($this->_clients as $client) {
                if ($client->createdAt < $expireTime) {
                    $client->clientClose();
                }
            }
        });
    }
}
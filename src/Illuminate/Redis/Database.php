<?php namespace Illuminate\Redis;

use Predis\Client;
use Predis\Response\Status;

class Database {

	/**
	 * The host address of the database.
	 *
	 * @var array
	 */
	protected $clients;

	/**
	 * Create a new Redis connection instance.
	 *
	 * @param  array  $servers
	 * @return void
	 */
	public function __construct(array $servers = array())
	{
		if (isset($servers['cluster']) && $servers['cluster'])
		{
			$this->clients = $this->createAggregateClient($servers);
		}
		else
		{
			$this->clients = $this->createSingleClients($servers);
		}
	}

	/**
	 * Create a new aggregate client supporting sharding.
	 *
	 * @param  array  $servers
	 * @return array
	 */
	protected function createAggregateClient(array $servers)
	{
		$servers = array_except($servers, array('cluster'));

		return array('default' => new Client(array_values($servers)));
	}

	/**
	 * Create an array of single connection clients.
	 *
	 * @param  array  $servers
	 * @return array
	 */
	protected function createSingleClients(array $servers)
	{
		$clients = array();

		foreach ($servers as $key => $server)
		{
			$clients[$key] = new Client($server);
		}

		return $clients;
	}

	/**
	 * Get a specific Redis connection instance.
	 *
	 * @param  string  $name
	 * @return \Predis\ClientInterface
	 */
	public function connection($name = 'default')
	{
		return $this->clients[$name ?: 'default'];
	}

	/**
	 * Run a command against the Redis database.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return mixed
	 */
	public function command($method, array $parameters = array())
	{
		$result = call_user_func_array([$this->clients['default'], $method], $parameters);

		if (in_array(strtolower($method), ['zrange', 'zrevrange', 'zrangebyscore', 'zrevrangebyscore']) &&
			isset($parameters[3]) && 'withscores' == strtolower($parameters[3]) &&
			is_array($result)
		) {
			$result2 = [];
			foreach ($result as $key => $value) {
				$result2[] = [$key, $value];
			}
			$result = $result2;
		}

		if ($result instanceof Status) {
			$result = $result->getPayload();
		}

		return $result;
	}

	/**
	 * Dynamically make a Redis command.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return mixed
	 */
	public function __call($method, $parameters)
	{
		return $this->command($method, $parameters);
	}

}

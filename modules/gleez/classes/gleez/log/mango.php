<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * MongoDB log writer
 *
 * ### System Requirements
 *
 * - PHP 5.3 or higher
 * - MondoDB 2.4 or higher
 * - PHP-extension MongoDB 1.4 or higher
 *
 * @package    Gleez\Logging
 * @author     Sergey Yakovlev - Gleez
 * @version    0.2.0
 * @copyright  (c) 2011-2013 Gleez Technologies
 * @license    http://gleezcms.org/license  Gleez CMS License
 */
class Gleez_Log_Mango extends Log_Writer {

	/**
	 * Collection for log
	 * Use [Capped Collection](http://docs.mongodb.org/manual/core/capped-collections/)
	 * to support high-bandwidth inserts
	 * @var string
	 */
	protected $_collection;

	/**
	 * Database instance name
	 * @var string
	 */
	protected $_name;

	/**
	 * Class constructor
	 *
	 * Creates a new MongoDb logger using Gleez [Mango]
	 *
	 * Example:<br>
	 * <code>
	 *   $writer = new Log_Mango($collection);
	 * </code>
	 *
	 * @param   string  $collection  Collection Name [Optional]
	 * @param   string  $name        Database instance name [Optional]
	 *
	 * @throws  Mango_Exception
	 */
	public function __construct($collection = 'Logs', $name = 'default')
	{
		if ( ! extension_loaded('mongo'))
		{
			throw new Mango_Exception('The php-mongo extension is not installed or is disabled.');
		}

		$this->_collection  = $collection;
		$this->_name        = $name;
	}

	/**
	 * Writes each of the messages into the MongoDB collection
	 *
	 * Example:<br>
	 * <code>
	 *   $writer->write($messages);
	 * </code>
	 *
	 * @param   array  $messages  An array of log messages
	 *
	 * @uses    Arr::merge
	 * @uses    Request::$client_ip
	 * @uses    Request::$user_agent
	 * @uses    Request::uri
	 * @uses    Request::initial
	 * @uses    Text::plain
	 * @uses    Log_Writer::$strace_level
	 */
	public function write(array $messages)
	{
		// Descriptive array
		$info = array(
			'hostname'   => Request::$client_ip,
			'user_agent' => Request::$user_agent,
			'url'        => Text::plain(Request::initial()->uri()),
			'refer'      => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
		);

		$logs      = array();
		$exception = NULL;

		foreach ($messages as $message)
		{
			$message['level'] = $this->_log_levels[$message['level']];
			$message['time']  = new MongoDate(strtotime($message['time']));

			if (isset($message['additional']['exception']))
			{
				$exception = $message['additional']['exception'];

				// Re-use as much as possible, just resetting the body to the trace
				$message['body']  = $exception->getTraceAsString();
				$message['level'] = $this->_log_levels[Log_Writer::$strace_level];
			}

			unset($message['additional'], $message['trace']);

			// Merging descriptive array and the current message
			$logs[] = Arr::merge($info, $message);
		}

		Mango::instance($this->_name)->{$this->_collection}->batchInsert($logs);
	}
}
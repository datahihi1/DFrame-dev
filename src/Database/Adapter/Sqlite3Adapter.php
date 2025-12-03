<?php
namespace DFrame\Database\Adapter;

use DFrame\Database\Interfaces\AdapterInterface;

use function \is_float;
use function \is_int;
use function \is_null;
use function \is_string;

/**
 * #### SQLite3 Database Adapter using SQLite3 extension
 * **Require**: the `sqlite3` PHP extension.
 */
class Sqlite3Adapter implements AdapterInterface
{
	protected $conn;

	/**
	 * Get connection
	 */
	public function connect(array $config)
	{
		if (!extension_loaded('sqlite3')) {
			throw new \Exception('SQLite3 extension is not loaded.');
		}
		$this->conn = new \SQLite3(ROOT_DIR . 'app/database/' . ($config['database'] ?? ':memory:'));
	}

	public function disconnect()
	{
		if ($this->conn) {
			$this->conn->close();
		}
	}

	// Updated query method to support parameter binding (like PdoSqliteAdapter)
	public function query($sql, $params = [])
	{
		$stmt = $this->conn->prepare($sql);
		if ($stmt === false) {
			return false;
		}

		// Bind parameters: support numeric (0-based array) and named (assoc) params
		foreach ($params as $key => $value) {
			if (is_int($key)) {
				// SQLite3 bind indexes are 1-based
				$stmt->bindValue($key + 1, $value, $this->getSqlite3Type($value));
			} else {
				$param = (strpos($key, ':') === 0) ? $key : ':' . $key;
				$stmt->bindValue($param, $value, $this->getSqlite3Type($value));
			}
		}

		return $stmt->execute();
	}

	// Helper to map PHP value types to SQLITE3_* constants
	protected function getSqlite3Type($value)
	{
		if (is_int($value)) {
			return \SQLITE3_INTEGER;
		} elseif (is_float($value)) {
			return \SQLITE3_FLOAT;
		} elseif ($value === null) {
			return \SQLITE3_NULL;
		} elseif (is_string($value)) {
			return \SQLITE3_TEXT;
		} else {
			return \SQLITE3_TEXT;
		}
	}

	public function fetch($result, $type = 'assoc')
	{
		switch ($type) {
			case 'num':
				return $result->fetchArray(SQLITE3_NUM);
			case 'both':
				return $result->fetchArray(SQLITE3_BOTH);
			case 'object':
				return $result->fetchObject();
			case 'assoc':
			default:
				return $result->fetchArray(SQLITE3_ASSOC);
		}
	}

	public function fetchAll($result, $type = 'assoc')
	{
		$data = [];
		while ($row = $this->fetch($result, $type)) {
			$data[] = $row;
		}
		return $data;
	}

	public function getError()
	{
		return $this->conn?->lastErrorMsg();
	}
	public function lastInsertId()
	{
		return $this->conn->lastInsertRowID();
	}

	public function beginTransaction()
	{
		$this->conn->exec('BEGIN');
	}

	public function commit()
	{
		$this->conn->exec('COMMIT');
	}

	public function rollback()
	{
		$this->conn->exec('ROLLBACK');
	}
}
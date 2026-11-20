# Mocks and unit helpers

Prefer a real in-memory SQLite database or a hand-written fake over a mock object.

## In-memory databases (Unit suite)

- `Database\Memory::database()` — one `Database` on a fresh in-memory SQLite.
- `Database\Memory::manager(?Layout, ?QueryLog)` — a `DatabaseManager` for a layout; pass a `QueryLog` to use
  `QueryAssert` with `$log` as the last argument.
- `ORM\Memory::orm($schema)` — an ORM on top of that.

## Wrap drivers (real database, instrumented)

`#[WrapDriver]` on a database test class puts `default` on `Mock\<Driver>WrapDriver`. `$this->wrapDriver()`
returns it:

| Knob | Effect |
|---|---|
| `disconnectCalls` | number of `disconnect()` calls |
| `exceptionOnTransactionBegin = N` | the next N `beginTransaction()` calls throw `ConnectionException` (the driver retries once after reconnecting, so use 2 to see a failure) |
| `setQueryCallback(fn(\PDOStatement $st, ?array $params): bool)` | runs instead of every statement's `execute()` |

The harness calls `setDefaults()` after every test. Outside a suite: `new Mock\TestPDO(...)` and
`Mock\TestPDOStatement` are the PDO wrappers behind them.

## Stub driver (no database)

```php
$pdo = (new FakePDO())->willReturn([['id' => 1]])->willThrow(new \PDOException('gone'));
$db = new Database('default', '', StubDriver::make($pdo));
$db->select()->from('users')->fetchAll();
$pdo->queries();      // executed SQL
$pdo->executed;       // [['sql' => …, 'params' => …], …]
```

`StubDriver::make(?PDOInterface, ?HandlerInterface, ?BuilderInterface, ?DriverConfig)` uses the SQLite
handler and compiler, `getType()` is `test`, and exceptions are rethrown unmapped. Without a PDO it runs on
in-memory SQLite.

## Mockery

`Config` registers `Testo\Bridge\Mockery\MockeryPlugin` in every suite when `testo/bridge-mockery` is
installed: expectations are verified after each test and count as assertions. Without the plugin they go
unverified. Load `testo-test-doubles` for the details.

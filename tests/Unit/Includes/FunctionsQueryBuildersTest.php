<?php

use PHPUnit\Framework\TestCase;

final class FunctionsQueryBuildersTest extends TestCase
{
    private mixed $dbBackup;

    protected function setUp(): void
    {
        global $db;
        $this->dbBackup = $db ?? null;
    }

    protected function tearDown(): void
    {
        global $db;
        $db = $this->dbBackup;
    }

    public function testResolveLegacyEventNameReturnsEmptyForInvalidId(): void
    {
        $conn = new FunctionsFakeMysqliConnection();
        $this->useConnection($conn);

        $result = resolveLegacyEventNameFromEventId(0);

        $this->assertSame('', $result);
        $this->assertSame([], $conn->preparedSql);
    }

    public function testResolveLegacyEventNameReturnsTrimmedNameFromDatabase(): void
    {
        $conn = new FunctionsFakeMysqliConnection();
        $conn->eventNames[7] = '  Liga Futsal  ';
        $this->useConnection($conn);

        $result = resolveLegacyEventNameFromEventId(7);

        $this->assertSame('Liga Futsal', $result);
        $this->assertTrue($this->preparedSqlContains($conn, 'SELECT name FROM events WHERE id = ? LIMIT 1'));
    }

    public function testResolveLegacyEventNameReturnsEmptyWhenPrepareFails(): void
    {
        $conn = new FunctionsFakeMysqliConnection();
        $conn->failPreparePatterns[] = 'SELECT name FROM events';
        $this->useConnection($conn);

        $result = resolveLegacyEventNameFromEventId(11);

        $this->assertSame('', $result);
    }

    public function testGetAllMatchesUsesSafeOrderingAndDefaultPagination(): void
    {
        $conn = new FunctionsFakeMysqliConnection();
        $conn->mainRows = [
            ['id' => 10, 'team1_name' => 'Alpha', 'team2_name' => 'Beta'],
            ['id' => 11, 'team1_name' => 'Gamma', 'team2_name' => 'Delta'],
        ];
        $conn->countTotal = 82;
        $this->useConnection($conn);

        $result = getAllMatches([
            'order_by' => 'unknown_column',
            'order_dir' => 'sideways',
        ]);

        $this->assertCount(2, $result['matches']);
        $this->assertSame(82, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(40, $result['per_page']);
        $this->assertSame(3.0, $result['total_pages']);

        $main = $this->findExecutedStatement($conn, 'LIMIT ? OFFSET ?');
        $this->assertSame('ii', $main['types']);
        $this->assertSame([40, 0], $main['params']);
        $this->assertStringContainsString(
            "(c.match_status = 'completed' OR (c.challenger_score IS NOT NULL OR c.opponent_score IS NOT NULL))",
            $main['sql']
        );
        $this->assertStringContainsString('ORDER BY c.challenge_date DESC', $main['sql']);

        $count = $this->findExecutedStatement($conn, 'SELECT COUNT(*) as total FROM challenges c');
        $this->assertSame('', $count['types']);
        $this->assertSame([], $count['params']);
        $this->assertFalse($this->preparedSqlContains($conn, 'SELECT name FROM events WHERE id = ? LIMIT 1'));
    }

    public function testGetAllMatchesResolvesLegacyEventAndBindsAllFilters(): void
    {
        $conn = new FunctionsFakeMysqliConnection();
        $conn->eventNames[3] = 'Futsal U-16';
        $conn->mainRows = [['id' => 200, 'event_name' => 'Futsal U-16']];
        $conn->countTotal = 1;
        $this->useConnection($conn);

        $result = getAllMatches([
            'status' => 'schedule',
            'event_id' => 3,
            'team_id' => 9,
            'week' => 22,
            'page' => 2,
            'per_page' => 15,
            'order_by' => 'score1',
            'order_dir' => 'asc',
        ]);

        $this->assertSame(2, $result['page']);
        $this->assertSame(15, $result['per_page']);
        $this->assertSame(1, $result['total']);
        $this->assertSame(1.0, $result['total_pages']);

        $main = $this->findExecutedStatement($conn, 'LIMIT ? OFFSET ?');
        $this->assertSame('siiiii', $main['types']);
        $this->assertSame(['Futsal U-16', 9, 9, 22, 15, 15], $main['params']);
        $this->assertStringContainsString('c.sport_type = ?', $main['sql']);
        $this->assertStringContainsString('(c.challenger_id = ? OR c.opponent_id = ?)', $main['sql']);
        $this->assertStringContainsString('WEEK(c.challenge_date) = ?', $main['sql']);
        $this->assertStringContainsString('ORDER BY c.challenger_score ASC', $main['sql']);
        $this->assertStringContainsString(
            "(c.match_status = 'scheduled' OR (c.status = 'accepted' AND c.match_status IS NULL))",
            $main['sql']
        );

        $count = $this->findExecutedStatement($conn, 'SELECT COUNT(*) as total FROM challenges c');
        $this->assertSame('siii', $count['types']);
        $this->assertSame(['Futsal U-16', 9, 9, 22], $count['params']);
        $this->assertTrue($this->preparedSqlContains($conn, 'SELECT name FROM events WHERE id = ? LIMIT 1'));
    }

    public function testGetAllMatchesPrefersEventNameOverLegacyEventIdLookup(): void
    {
        $conn = new FunctionsFakeMysqliConnection();
        $conn->mainRows = [];
        $conn->countTotal = 0;
        $this->useConnection($conn);

        getAllMatches([
            'event' => 'Basket U-18',
            'event_id' => 99,
            'per_page' => 10,
        ]);

        $main = $this->findExecutedStatement($conn, 'LIMIT ? OFFSET ?');
        $this->assertSame('sii', $main['types']);
        $this->assertSame(['Basket U-18', 10, 0], $main['params']);
        $this->assertFalse($this->preparedSqlContains($conn, 'SELECT name FROM events WHERE id = ? LIMIT 1'));
    }

    public function testGetScheduledMatchesNormalizesLimitAndUsesAscendingOrder(): void
    {
        $conn = new FunctionsFakeMysqliConnection();
        $conn->mainRows = [];
        $conn->countTotal = 0;
        $this->useConnection($conn);

        $matches = getScheduledMatches(0);

        $this->assertSame([], $matches);

        $main = $this->findExecutedStatement($conn, 'LIMIT ? OFFSET ?');
        $this->assertSame('ii', $main['types']);
        $this->assertSame([1, 0], $main['params']);
        $this->assertStringContainsString('ORDER BY c.challenge_date ASC', $main['sql']);
        $this->assertStringContainsString(
            "(c.match_status = 'scheduled' OR (c.status = 'accepted' AND c.match_status IS NULL))",
            $main['sql']
        );
    }

    public function testGetCompletedMatchesNormalizesLimitAndUsesDescendingOrder(): void
    {
        $conn = new FunctionsFakeMysqliConnection();
        $conn->mainRows = [];
        $conn->countTotal = 0;
        $this->useConnection($conn);

        $matches = getCompletedMatches(-10);

        $this->assertSame([], $matches);

        $main = $this->findExecutedStatement($conn, 'LIMIT ? OFFSET ?');
        $this->assertSame('ii', $main['types']);
        $this->assertSame([1, 0], $main['params']);
        $this->assertStringContainsString('ORDER BY c.challenge_date DESC', $main['sql']);
        $this->assertStringContainsString(
            "(c.match_status = 'completed' OR (c.challenger_score IS NOT NULL OR c.opponent_score IS NOT NULL))",
            $main['sql']
        );
    }

    private function useConnection(FunctionsFakeMysqliConnection $connection): void
    {
        global $db;
        $db = new FunctionsFakeDb($connection);
    }

    private function findExecutedStatement(FunctionsFakeMysqliConnection $connection, string $sqlNeedle): array
    {
        foreach ($connection->executedStatements as $statement) {
            if (str_contains($statement['sql'], $sqlNeedle)) {
                return $statement;
            }
        }

        $this->fail("No executed statement contains '{$sqlNeedle}'.");
    }

    private function preparedSqlContains(FunctionsFakeMysqliConnection $connection, string $needle): bool
    {
        foreach ($connection->preparedSql as $sql) {
            if (str_contains($sql, $needle)) {
                return true;
            }
        }

        return false;
    }
}

final class FunctionsFakeDb
{
    private FunctionsFakeMysqliConnection $connection;

    public function __construct(FunctionsFakeMysqliConnection $connection)
    {
        $this->connection = $connection;
    }

    public function getConnection(): FunctionsFakeMysqliConnection
    {
        return $this->connection;
    }
}

final class FunctionsFakeMysqliConnection
{
    public array $preparedSql = [];
    public array $executedStatements = [];
    public array $failPreparePatterns = [];
    public array $eventNames = [];
    public array $mainRows = [];
    public int $countTotal = 0;
    public array $queries = [];

    public function prepare(string $sql)
    {
        $this->preparedSql[] = $sql;

        foreach ($this->failPreparePatterns as $pattern) {
            if (str_contains($sql, $pattern)) {
                return false;
            }
        }

        return new FunctionsFakeMysqliStatement($this, $sql);
    }

    public function executePrepared(string $sql, string $types, array $params): FunctionsFakeMysqliResult
    {
        $this->executedStatements[] = [
            'sql' => $sql,
            'types' => $types,
            'params' => $params,
        ];

        if (str_contains($sql, 'SELECT name FROM events WHERE id = ? LIMIT 1')) {
            $eventId = (int)($params[0] ?? 0);
            if (!array_key_exists($eventId, $this->eventNames)) {
                return new FunctionsFakeMysqliResult([]);
            }

            return new FunctionsFakeMysqliResult([
                ['name' => $this->eventNames[$eventId]],
            ]);
        }

        if (str_contains($sql, 'SELECT COUNT(*) as total FROM challenges c')) {
            return new FunctionsFakeMysqliResult([
                ['total' => $this->countTotal],
            ]);
        }

        if (str_contains($sql, 'FROM challenges c') && str_contains($sql, 'LIMIT ? OFFSET ?')) {
            return new FunctionsFakeMysqliResult($this->mainRows);
        }

        return new FunctionsFakeMysqliResult([]);
    }

    public function query(string $sql): FunctionsFakeMysqliResult
    {
        $this->queries[] = $sql;
        return new FunctionsFakeMysqliResult([]);
    }
}

final class FunctionsFakeMysqliStatement
{
    private FunctionsFakeMysqliConnection $connection;
    private string $sql;
    private string $bindTypes = '';
    private array $boundParams = [];
    private ?FunctionsFakeMysqliResult $result = null;

    public function __construct(FunctionsFakeMysqliConnection $connection, string $sql)
    {
        $this->connection = $connection;
        $this->sql = $sql;
    }

    public function bind_param(string $types, ...$params): bool
    {
        $this->bindTypes = $types;
        $this->boundParams = $params;
        return true;
    }

    public function execute(): bool
    {
        $this->result = $this->connection->executePrepared($this->sql, $this->bindTypes, $this->boundParams);
        return true;
    }

    public function get_result(): FunctionsFakeMysqliResult
    {
        return $this->result ?? new FunctionsFakeMysqliResult([]);
    }

    public function close(): bool
    {
        return true;
    }
}

final class FunctionsFakeMysqliResult
{
    public int $num_rows;
    private array $rows;
    private int $position = 0;

    public function __construct(array $rows)
    {
        $this->rows = array_values($rows);
        $this->num_rows = count($this->rows);
    }

    public function fetch_assoc(): ?array
    {
        if ($this->position >= $this->num_rows) {
            return null;
        }

        $row = $this->rows[$this->position];
        $this->position++;

        return $row;
    }
}

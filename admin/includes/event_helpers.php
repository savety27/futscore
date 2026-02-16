<?php

if (!function_exists('adminTableExists')) {
    function adminTableExists(PDO $conn, $tableName) {
        try {
            $stmt = $conn->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('getDynamicEventOptions')) {
    function getDynamicEventOptions(PDO $conn) {
        $rawOptions = [];

        if (adminTableExists($conn, 'event_taxonomy')) {
            try {
                $stmt = $conn->prepare("SELECT DISTINCT legacy_event_name AS event_name FROM event_taxonomy WHERE legacy_event_name IS NOT NULL AND legacy_event_name <> ''");
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (is_array($rows)) {
                    $rawOptions = array_merge($rawOptions, $rows);
                }
            } catch (PDOException $e) {
                // Ignore taxonomy source errors and continue with legacy sources.
            }
        }

        $sourceQueries = [
            "SELECT DISTINCT event_name AS event_name FROM team_events WHERE event_name IS NOT NULL AND event_name <> ''",
            "SELECT DISTINCT sport_type AS event_name FROM challenges WHERE sport_type IS NOT NULL AND sport_type <> ''",
            "SELECT DISTINCT sport_type AS event_name FROM teams WHERE sport_type IS NOT NULL AND sport_type <> ''"
        ];

        foreach ($sourceQueries as $query) {
            try {
                $stmt = $conn->prepare($query);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (is_array($rows)) {
                    $rawOptions = array_merge($rawOptions, $rows);
                }
            } catch (PDOException $e) {
                // Ignore failed source and continue.
            }
        }

        $uniqueOptions = [];
        foreach ($rawOptions as $option) {
            $value = trim((string) $option);
            if ($value === '') {
                continue;
            }
            $uniqueOptions[$value] = true;
        }

        $options = array_keys($uniqueOptions);
        natcasesort($options);

        return array_values($options);
    }
}

if (!function_exists('mergeTeamPrimarySportsIntoEventsMap')) {
    function mergeTeamPrimarySportsIntoEventsMap(array $teams, array &$teamEventsMap) {
        foreach ($teams as $team) {
            $teamId = isset($team['id']) ? (int) $team['id'] : 0;
            $primarySport = trim((string) ($team['sport_type'] ?? ''));

            if ($teamId <= 0 || $primarySport === '') {
                continue;
            }

            if (!isset($teamEventsMap[$teamId])) {
                $teamEventsMap[$teamId] = [];
            }

            if (!in_array($primarySport, $teamEventsMap[$teamId], true)) {
                $teamEventsMap[$teamId][] = $primarySport;
            }
        }
    }
}

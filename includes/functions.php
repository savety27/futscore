<?php
require_once 'db.php';

// ========== FUNGSI UTAMA ==========

/**
 * Format tanggal ke format yang mudah dibaca
 */
function formatDate($date) {
    if (empty($date) || $date == '0000-00-00 00:00:00' || $date == '0000-00-00') {
        return 'Tanggal tidak tersedia';
    }
    
    $timestamp = strtotime($date);
    if (!$timestamp) {
        return 'Tanggal tidak valid';
    }
    
    $months = array(
        'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    );
    
    $day = date('d', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);
    
    return "$day $month $year";
}

/**
 * Format tanggal dan waktu ke format yang mudah dibaca
 */
function formatDateTime($datetime) {
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return 'Tanggal tidak tersedia';
    }
    
    $timestamp = strtotime($datetime);
    if (!$timestamp) {
        return 'Tanggal tidak valid';
    }
    
    $months = array(
        'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    );
    
    $day = date('d', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);
    $time = date('H:i', $timestamp);
    
    return "$day $month $year, $time";
}

/**
 * Membuat slug dari teks
 */
function createSlug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    
    if (empty($text)) {
        return 'n-a';
    }
    
    return $text;
}

// ========== FUNGSI BERITA ==========

/**
 * Mendapatkan berita berdasarkan slug
 */
function getNewsBySlug($slug) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT * FROM berita WHERE slug = ? AND status = 'published'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

/**
 * Mendapatkan berita terkait
 */
function getRelatedNews($currentNewsId, $limit = 3) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT * FROM berita WHERE id != ? AND status = 'published' ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $currentNewsId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $relatedNews = [];
    while ($row = $result->fetch_assoc()) {
        $relatedNews[] = $row;
    }
    return $relatedNews;
}

/**
 * Menambah jumlah view berita
 */
function incrementNewsViews($id) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "UPDATE berita SET views = views + 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

/**
 * Mendapatkan berita terbaru
 */
function getLatestNews($limit = 5) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT * FROM berita WHERE status = 'published' ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $news = [];
    while ($row = $result->fetch_assoc()) {
        $news[] = $row;
    }
    return $news;
}

/**
 * Mendapatkan berita populer
 */
function getPopularNews($limit = 5) {
    global $db;
    $conn = $db->getConnection();
    
    if (!$conn) {
        return [];
    }
    
    $sql = "SELECT * FROM berita WHERE status = 'published' ORDER BY views DESC, created_at DESC LIMIT ?";
    
    try {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $news = [];
            while ($row = $result->fetch_assoc()) {
                $news[] = $row;
            }
            return $news;
        }
    } catch (Exception $e) {
        error_log("Error getting popular news: " . $e->getMessage());
    }
    
    // Fallback
    $fallbackSql = "SELECT * FROM berita WHERE status = 'published' ORDER BY views DESC, created_at DESC LIMIT {$limit}";
    $result = $conn->query($fallbackSql);
    
    $news = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $news[] = $row;
        }
    }
    
    return $news;
}

/**
 * Mereset semua view berita ke 0
 */
function resetNewsViewsToZero() {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "UPDATE berita SET views = 0 WHERE status = 'published'";
    return $conn->query($sql);
}

/**
 * Mendapatkan jumlah total berita
 */
function getTotalNewsCount() {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT COUNT(*) as total FROM berita WHERE status = 'published'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['total'];
}

/**
 * Mendapatkan berita berdasarkan kategori
 */
function getNewsByCategory($category, $limit = 10) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT * FROM berita WHERE tag LIKE ? AND status = 'published' ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $searchTag = "%$category%";
    $stmt->bind_param("si", $searchTag, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $news = [];
    while ($row = $result->fetch_assoc()) {
        $news[] = $row;
    }
    return $news;
}

/**
 * Mencari berita
 */
function searchNews($keyword, $limit = 10) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT * FROM berita WHERE (judul LIKE ? OR konten LIKE ? OR tag LIKE ?) AND status = 'published' ORDER BY created_at DESC LIMIT ?";
    $searchTerm = "%$keyword%";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $searchTerm, $searchTerm, $searchTerm, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $news = [];
    while ($row = $result->fetch_assoc()) {
        $news[] = $row;
    }
    return $news;
}

// ========== FUNGSI MATCH ==========

/**
 * Mendapatkan semua match dengan filter dan pagination
 */
function getAllMatches($params = []) {
    global $db;
    $conn = $db->getConnection();
    
    // Default parameters
    $defaults = [
        'status' => 'result', // 'schedule' or 'result'
        'event_id' => 0,
        'team_id' => 0,
        'week' => 0,
        'page' => 1,
        'per_page' => 40,
        'order_by' => 'match_date',
        'order_dir' => 'DESC'
    ];
    
    $params = array_merge($defaults, $params);
    
    // Build WHERE clause
    $whereConditions = [];
    $bindParams = [];
    $bindTypes = '';
    
    // Status filter
    if ($params['status'] === 'schedule') {
        $whereConditions[] = "m.status = 'scheduled'";
    } else {
        $whereConditions[] = "m.status = 'completed'";
    }
    
    // Event filter
    if ($params['event_id'] > 0) {
        $whereConditions[] = "m.event_id = ?";
        $bindParams[] = $params['event_id'];
        $bindTypes .= 'i';
    }
    
    // Team filter
    if ($params['team_id'] > 0) {
        $whereConditions[] = "(m.team1_id = ? OR m.team2_id = ?)";
        $bindParams[] = $params['team_id'];
        $bindParams[] = $params['team_id'];
        $bindTypes .= 'ii';
    }
    
    // Week filter
    if ($params['week'] > 0) {
        $whereConditions[] = "WEEK(m.match_date) = ?";
        $bindParams[] = $params['week'];
        $bindTypes .= 'i';
    }
    
    $whereClause = count($whereConditions) > 0 ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Calculate pagination
    $offset = ($params['page'] - 1) * $params['per_page'];
    
    // Build main query
    $sql = "SELECT m.*, t1.name as team1_name, t1.logo as team1_logo, 
                   t2.name as team2_name, t2.logo as team2_logo, 
                   e.name as event_name
            FROM matches m
            LEFT JOIN teams t1 ON m.team1_id = t1.id
            LEFT JOIN teams t2 ON m.team2_id = t2.id
            LEFT JOIN events e ON m.event_id = e.id
            $whereClause
            ORDER BY m.{$params['order_by']} {$params['order_dir']}
            LIMIT ? OFFSET ?";
    
    $bindParams[] = $params['per_page'];
    $bindParams[] = $offset;
    $bindTypes .= 'ii';
    
    $stmt = $conn->prepare($sql);
    if ($bindTypes) {
        $stmt->bind_param($bindTypes, ...$bindParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $matches = [];
    while ($row = $result->fetch_assoc()) {
        $matches[] = $row;
    }
    $stmt->close();
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM matches m $whereClause";
    $countStmt = $conn->prepare($countSql);
    
    if ($bindTypes && !empty($bindParams)) {
        // Remove LIMIT and OFFSET params for count query
        $countParams = array_slice($bindParams, 0, count($bindParams) - 2);
        $countTypes = substr($bindTypes, 0, -2);
        
        if (!empty($countTypes)) {
            $countStmt->bind_param($countTypes, ...$countParams);
        }
    }
    
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    return [
        'matches' => $matches,
        'total' => $total,
        'page' => $params['page'],
        'per_page' => $params['per_page'],
        'total_pages' => ceil($total / $params['per_page'])
    ];
}

/**
 * Mendapatkan detail match berdasarkan ID
 */
function getMatchById($id) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT m.*, t1.name as team1_name, t1.logo as team1_logo, 
                   t2.name as team2_name, t2.logo as team2_logo, 
                   e.name as event_name, e.description as event_description
            FROM matches m
            LEFT JOIN teams t1 ON m.team1_id = t1.id
            LEFT JOIN teams t2 ON m.team2_id = t2.id
            LEFT JOIN events e ON m.event_id = e.id
            WHERE m.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

/**
 * Mendapatkan pertandingan yang dijadwalkan
 */
function getScheduledMatches($limit = 10) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT m.*, t1.name as team1_name, t1.logo as team1_logo, 
                   t2.name as team2_name, t2.logo as team2_logo, e.name as event_name
            FROM matches m
            LEFT JOIN teams t1 ON m.team1_id = t1.id
            LEFT JOIN teams t2 ON m.team2_id = t2.id
            LEFT JOIN events e ON m.event_id = e.id
            WHERE m.status = 'scheduled' 
            ORDER BY m.match_date ASC 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $matches = [];
    while ($row = $result->fetch_assoc()) {
        $matches[] = $row;
    }
    return $matches;
}

/**
 * Mendapatkan pertandingan yang selesai
 */
function getCompletedMatches($limit = 10) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT m.*, t1.name as team1_name, t1.logo as team1_logo, 
                   t2.name as team2_name, t2.logo as team2_logo, e.name as event_name
            FROM matches m
            LEFT JOIN teams t1 ON m.team1_id = t1.id
            LEFT JOIN teams t2 ON m.team2_id = t2.id
            LEFT JOIN events e ON m.event_id = e.id
            WHERE m.status = 'completed' 
            ORDER BY m.match_date DESC 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $matches = [];
    while ($row = $result->fetch_assoc()) {
        $matches[] = $row;
    }
    return $matches;
}

/**
 * Mendapatkan tantangan (challenge/match) yang dijadwalkan
 */
function getScheduledChallenges($limit = 10) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT c.*, t1.name as challenger_name, t1.logo as challenger_logo, 
                   t2.name as opponent_name, t2.logo as opponent_logo, v.name as venue_name
            FROM challenges c
            LEFT JOIN teams t1 ON c.challenger_id = t1.id
            LEFT JOIN teams t2 ON c.opponent_id = t2.id
            LEFT JOIN venues v ON c.venue_id = v.id
            WHERE (c.match_status = 'scheduled' OR (c.status = 'accepted' AND c.match_status IS NULL))
              AND (c.challenger_score IS NULL AND c.opponent_score IS NULL)
            ORDER BY c.challenge_date ASC 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $challenges = [];
    while ($row = $result->fetch_assoc()) {
        $challenges[] = $row;
    }
    return $challenges;
}

/**
 * Mendapatkan tantangan (challenge/match) yang sudah selesai
 */
function getCompletedChallenges($limit = 10) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT c.*, t1.name as challenger_name, t1.logo as challenger_logo, 
                   t2.name as opponent_name, t2.logo as opponent_logo, v.name as venue_name
            FROM challenges c
            LEFT JOIN teams t1 ON c.challenger_id = t1.id
            LEFT JOIN teams t2 ON c.opponent_id = t2.id
            LEFT JOIN venues v ON c.venue_id = v.id
            WHERE c.match_status = 'completed'
               OR (c.challenger_score IS NOT NULL OR c.opponent_score IS NOT NULL)
            ORDER BY c.challenge_date DESC 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $challenges = [];
    while ($row = $result->fetch_assoc()) {
        $challenges[] = $row;
    }
    return $challenges;
}

/**
 * Mendapatkan tantangan terbaru (untuk card section)
 */
  function getLatestChallenges($limit = 5) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT c.*, t1.name as challenger_name, t1.logo as challenger_logo, 
                   t2.name as opponent_name, t2.logo as opponent_logo, v.name as venue_name
            FROM challenges c
            LEFT JOIN teams t1 ON c.challenger_id = t1.id
            LEFT JOIN teams t2 ON c.opponent_id = t2.id
            LEFT JOIN venues v ON c.venue_id = v.id
            ORDER BY c.challenge_date DESC 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $challenges = [];
    while ($row = $result->fetch_assoc()) {
        $challenges[] = $row;
    }
    return $challenges;
  }
  
  /**
   * Mendapatkan semua challenge dengan filter dan pagination
   */
  function getAllChallenges($params = []) {
      global $db;
      $conn = $db->getConnection();
      
      // Default parameters
      $defaults = [
          'status' => 'result', // 'schedule' or 'result'
          'event' => '',
          'team_id' => 0,
          'week' => 0,
          'page' => 1,
          'per_page' => 40,
          'order_by' => 'challenge_date',
          'order_dir' => 'DESC'
      ];
      
      $params = array_merge($defaults, $params);
      
      // Build WHERE clause
      $whereConditions = [];
      $bindParams = [];
      $bindTypes = '';
      
      // Status filter
      if ($params['status'] === 'schedule') {
          $whereConditions[] = "(c.match_status = 'scheduled' OR (c.status = 'accepted' AND c.match_status IS NULL))";
          $whereConditions[] = "(c.challenger_score IS NULL AND c.opponent_score IS NULL)";
      } else {
          $whereConditions[] = "(c.match_status = 'completed' OR (c.challenger_score IS NOT NULL OR c.opponent_score IS NOT NULL))";
      }
      
      // Event filter (sport_type)
      if (!empty($params['event'])) {
          $whereConditions[] = "c.sport_type = ?";
          $bindParams[] = $params['event'];
          $bindTypes .= 's';
      }
      
      // Team filter
      if ($params['team_id'] > 0) {
          $whereConditions[] = "(c.challenger_id = ? OR c.opponent_id = ?)";
          $bindParams[] = $params['team_id'];
          $bindParams[] = $params['team_id'];
          $bindTypes .= 'ii';
      }
      
      // Week filter
      if ($params['week'] > 0) {
          $whereConditions[] = "WEEK(c.challenge_date) = ?";
          $bindParams[] = $params['week'];
          $bindTypes .= 'i';
      }
      
      $whereClause = count($whereConditions) > 0 ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
      
      // Calculate pagination
      $offset = ($params['page'] - 1) * $params['per_page'];
      
      // Build main query
      $sql = "SELECT c.*, t1.name as challenger_name, t1.logo as challenger_logo, 
                     t2.name as opponent_name, t2.logo as opponent_logo, 
                     v.name as venue_name
              FROM challenges c
              LEFT JOIN teams t1 ON c.challenger_id = t1.id
              LEFT JOIN teams t2 ON c.opponent_id = t2.id
              LEFT JOIN venues v ON c.venue_id = v.id
              $whereClause
              ORDER BY c.{$params['order_by']} {$params['order_dir']}
              LIMIT ? OFFSET ?";
      
      $bindParams[] = $params['per_page'];
      $bindParams[] = $offset;
      $bindTypes .= 'ii';
      
      $stmt = $conn->prepare($sql);
      if ($bindTypes) {
          $stmt->bind_param($bindTypes, ...$bindParams);
      }
      $stmt->execute();
      $result = $stmt->get_result();
      
      $matches = [];
      while ($row = $result->fetch_assoc()) {
          $matches[] = $row;
      }
      $stmt->close();
      
      // Get total count for pagination
      $countSql = "SELECT COUNT(*) as total FROM challenges c $whereClause";
      $countStmt = $conn->prepare($countSql);
      
      if ($bindTypes && !empty($bindParams)) {
          // Remove LIMIT and OFFSET params for count query
          $countParams = array_slice($bindParams, 0, count($bindParams) - 2);
          $countTypes = substr($bindTypes, 0, -2);
          
          if (!empty($countTypes)) {
              $countStmt->bind_param($countTypes, ...$countParams);
          }
      }
      
      $countStmt->execute();
      $countResult = $countStmt->get_result();
      $total = $countResult->fetch_assoc()['total'];
      $countStmt->close();
      
      return [
          'matches' => $matches,
          'total' => $total,
          'page' => $params['page'],
          'per_page' => $params['per_page'],
          'total_pages' => ceil($total / $params['per_page'])
      ];
  }

/**
 * Mendapatkan match goals
 */
function getMatchGoals($matchId) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT g.*, p.name as player_name, p.jersey_number,
                   t.name as team_name, t.logo as team_logo
            FROM goals g
            LEFT JOIN players p ON g.player_id = p.id
            LEFT JOIN teams t ON g.team_id = t.id
            WHERE g.match_id = ?
            ORDER BY g.minute";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $matchId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $goals = [];
    while ($row = $result->fetch_assoc()) {
        $goals[] = $row;
    }
    return $goals;
}

/**
 * Mendapatkan match lineups
 */
function getMatchLineups($matchId) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT l.*, p.name as player_name, p.photo as player_photo,
                   p.jersey_number, t.name as team_name, t.logo as team_logo
            FROM lineups l
            LEFT JOIN players p ON l.player_id = p.id
            LEFT JOIN teams t ON l.team_id = t.id
            WHERE l.match_id = ?
            ORDER BY l.team_id, l.is_starting DESC, p.jersey_number";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $matchId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $lineups = [];
    while ($row = $result->fetch_assoc()) {
        $lineups[] = $row;
    }
    return $lineups;
}

/**
 * Mendapatkan satu tantangan berdasarkan ID
 */
function getChallengeById($id) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT c.*, t1.name as challenger_name, t1.logo as challenger_logo, 
                   t2.name as opponent_name, t2.logo as opponent_logo, v.name as venue_name
            FROM challenges c
            LEFT JOIN teams t1 ON c.challenger_id = t1.id
            LEFT JOIN teams t2 ON c.opponent_id = t2.id
            LEFT JOIN venues v ON c.venue_id = v.id
            WHERE c.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

// ========== FUNGSI TIM ==========

/**
 * Mendapatkan tim
 */
function getTeams($limit = 10) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT * FROM teams ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $teams = [];
    while ($row = $result->fetch_assoc()) {
        $teams[] = $row;
    }
    return $teams;
}

/**
 * Mendapatkan tim berdasarkan ID
 */
function getTeamById($id) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT * FROM teams WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

/**
 * Mendapatkan tim pemenang terbaru
 */
function getRecentWinners($limit = 5) {
    global $db;
    $conn = $db->getConnection();
    
    // Mengambil data pemenang dari tabel challenges yang berisi hasil pertandingan
    // Diurutkan berdasarkan tanggal kemenangan terbaru (bukan jumlah kemenangan terbanyak)
    $sql = "SELECT t.*, 
                   COUNT(c.id) as total_wins,
                   CONCAT(COUNT(c.id), ' Wins') as achievement,
                   MAX(c.challenge_date) as latest_win_date
            FROM teams t
            JOIN challenges c ON t.id = c.winner_team_id
            WHERE c.status = 'completed'
            GROUP BY t.id
            ORDER BY latest_win_date DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $winners = [];
    while ($row = $result->fetch_assoc()) {
        $winners[] = $row;
    }
    return $winners;
}

/**
 * Mendapatkan team stats
 */
function getTeamStats($teamId) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT t.*,
                   COUNT(DISTINCT m.id) as total_matches,
                   SUM(CASE 
                       WHEN m.team1_id = t.id AND m.score1 > m.score2 THEN 1
                       WHEN m.team2_id = t.id AND m.score2 > m.score1 THEN 1
                       ELSE 0
                   END) as wins,
                   SUM(CASE 
                       WHEN m.team1_id = t.id AND m.score1 = m.score2 THEN 1
                       WHEN m.team2_id = t.id AND m.score2 = m.score1 THEN 1
                       ELSE 0
                   END) as draws,
                   SUM(CASE 
                       WHEN m.team1_id = t.id AND m.score1 < m.score2 THEN 1
                       WHEN m.team2_id = t.id AND m.score2 < m.score1 THEN 1
                       ELSE 0
                   END) as losses,
                   SUM(CASE 
                       WHEN m.team1_id = t.id THEN m.score1
                       WHEN m.team2_id = t.id THEN m.score2
                       ELSE 0
                   END) as goals_for,
                   SUM(CASE 
                       WHEN m.team1_id = t.id THEN m.score2
                       WHEN m.team2_id = t.id THEN m.score1
                       ELSE 0
                   END) as goals_against
            FROM teams t
            LEFT JOIN matches m ON (t.id = m.team1_id OR t.id = m.team2_id) 
                               AND m.status = 'completed'
            WHERE t.id = ?
            GROUP BY t.id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teamId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $team = $result->fetch_assoc();
        
        // Calculate totals
        $team['total_goals_for'] = $team['goals_for'] ?? 0;
        $team['total_goals_against'] = $team['goals_against'] ?? 0;
        $team['goal_difference'] = $team['total_goals_for'] - $team['total_goals_against'];
        $team['points'] = (($team['wins'] ?? 0) * 3) + ($team['draws'] ?? 0);
        
        return $team;
    }
    
    return null;
}

/**
 * Mendapatkan semua tim (untuk listing)
 */
function getAllTeams() {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT * FROM teams WHERE is_active = 1 ORDER BY name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $teams = [];
    while ($row = $result->fetch_assoc()) {
        $teams[] = $row;
    }
    return $teams;
}

/**
 * Mendapatkan pemain berdasarkan team ID
 */
function getPlayersByTeamId($teamId, $eventId = null) {
    global $db;
    $conn = $db->getConnection();
    
    if ($eventId) {
        // Get players who participated in specific event
        $sql = "SELECT DISTINCT p.*, t.name as team_name, t.logo as team_logo
                FROM players p
                LEFT JOIN teams t ON p.team_id = t.id
                LEFT JOIN lineups l ON p.id = l.player_id
                LEFT JOIN matches m ON l.match_id = m.id
                WHERE p.team_id = ? AND m.event_id = ? AND p.status = 'active'
                ORDER BY p.position, p.jersey_number";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $teamId, $eventId);
    } else {
        // Get all active players for the team
        $sql = "SELECT p.*, t.name as team_name, t.logo as team_logo
                FROM players p
                LEFT JOIN teams t ON p.team_id = t.id
                WHERE p.team_id = ? AND p.status = 'active'
                ORDER BY p.position, p.jersey_number";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $teamId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $players = [];
    while ($row = $result->fetch_assoc()) {
        // Calculate age
        if (!empty($row['birth_date']) && $row['birth_date'] != '0000-00-00') {
            $birthDate = new DateTime($row['birth_date']);
            $today = new DateTime('today');
            $age = $birthDate->diff($today)->y;
            $row['age'] = $age;
        } else {
            $row['age'] = 'N/A';
        }
        $players[] = $row;
    }
    return $players;
}

/**
 * Mendapatkan staff tim berdasarkan team ID
 */
function getTeamStaffByTeamId($teamId) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT s.*, t.name as team_name, t.logo as team_logo
            FROM team_staff s
            LEFT JOIN teams t ON s.team_id = t.id
            WHERE s.team_id = ?
            ORDER BY s.position, s.name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teamId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }
    return $staff;
}

// ========== FUNGSI PEMAIN ==========

/**
 * Mendapatkan pemain
 */
function getPlayers($limit = 10) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT p.*, t.name as team_name, t.logo as team_logo 
            FROM players p 
            LEFT JOIN teams t ON p.team_id = t.id 
            ORDER BY p.created_at DESC 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $players = [];
    while ($row = $result->fetch_assoc()) {
        // Calculate age from birth_date
        if (!empty($row['birth_date']) && $row['birth_date'] != '0000-00-00') {
            $birthDate = new DateTime($row['birth_date']);
            $today = new DateTime('today');
            $age = $birthDate->diff($today)->y;
            $row['age'] = $age;
        } else {
            $row['age'] = 'N/A';
        }
        $players[] = $row;
    }
    return $players;
}

/**
 * Mendapatkan pemain berdasarkan ID
 */
function getPlayerById($id) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT p.*, t.name as team_name, t.logo as team_logo 
            FROM players p 
            LEFT JOIN teams t ON p.team_id = t.id 
            WHERE p.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $player = $result->fetch_assoc();
        
        // Calculate age
        if (!empty($player['birth_date']) && $player['birth_date'] != '0000-00-00') {
            $birthDate = new DateTime($player['birth_date']);
            $today = new DateTime('today');
            $age = $birthDate->diff($today)->y;
            $player['age'] = $age;
        } else {
            $player['age'] = 'N/A';
        }
        
        return $player;
    }
    return null;
}

/**
 * Mendapatkan player stats
 */
function getPlayerStats($playerId) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT p.*, t.name as team_name, t.logo as team_logo,
                   (SELECT COUNT(*) FROM goals WHERE player_id = p.id) as total_goals,
                   (SELECT COUNT(*) FROM lineups WHERE player_id = p.id AND is_starting = 1) as starts,
                   (SELECT COUNT(*) FROM lineups WHERE player_id = p.id AND is_starting = 0) as subs
            FROM players p
            LEFT JOIN teams t ON p.team_id = t.id
            WHERE p.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $playerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $player = $result->fetch_assoc();
        
        // Calculate age
        if (!empty($player['birth_date']) && $player['birth_date'] != '0000-00-00') {
            $birthDate = new DateTime($player['birth_date']);
            $today = new DateTime('today');
            $age = $birthDate->diff($today)->y;
            $player['age'] = $age;
        } else {
            $player['age'] = 'N/A';
        }
        
        return $player;
    }
    
    return null;
}

/**
 * Mendapatkan players dengan ulang tahun hari ini
 */
function getBirthdayPlayers($limit = 5) {
    global $db;
    $conn = $db->getConnection();
    
    $today = date('m-d');
    
    $sql = "SELECT p.*, t.name as team_name, t.logo as team_logo,
                   DATE_FORMAT(p.birth_date, '%d %M') as birthday_formatted,
                   YEAR(CURDATE()) - YEAR(p.birth_date) as age
            FROM players p
            LEFT JOIN teams t ON p.team_id = t.id
            WHERE DATE_FORMAT(p.birth_date, '%m-%d') = ?
            ORDER BY p.birth_date
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $today, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $players = [];
    while ($row = $result->fetch_assoc()) {
        $players[] = $row;
    }
    return $players;
}

// ========== FUNGSI EVENT ==========

/**
 * Mendapatkan semua events
 */
function getEvents($limit = 10) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT * FROM events ORDER BY start_date DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    return $events;
}

/**
 * Mendapatkan event berdasarkan ID
 */
function getEventById($id) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT * FROM events WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

// ========== FUNGSI STAFF TIM ==========

/**
 * Mendapatkan staff tim
 */
function getTeamStaff($limit = 10) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT s.*, t.name as team_name, t.logo as team_logo 
            FROM team_staff s 
            LEFT JOIN teams t ON s.team_id = t.id 
            ORDER BY s.created_at DESC 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }
    return $staff;
}

// ========== FUNGSI OFFICIALS ==========

/**
 * Mendapatkan officials
 */
function getOfficials($limit = 10) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT * FROM officials ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $officials = [];
    while ($row = $result->fetch_assoc()) {
        $officials[] = $row;
    }
    return $officials;
}

// ========== FUNGSI TRANSFER ==========

/**
 * Mendapatkan player transfer
 */
function getPlayerTransfers($limit = 5) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT tr.*, p.name as player_name, p.photo as player_photo,
                   t1.name as from_team_name, t2.name as to_team_name,
                   t1.logo as from_team_logo, t2.logo as to_team_logo
            FROM transfers tr
            LEFT JOIN players p ON tr.player_id = p.id
            LEFT JOIN teams t1 ON tr.from_team_id = t1.id
            LEFT JOIN teams t2 ON tr.to_team_id = t2.id
            ORDER BY tr.transfer_date DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transfers = [];
    while ($row = $result->fetch_assoc()) {
        $transfers[] = $row;
    }
    return $transfers;
}

// ========== FUNGSI STATISTIK ==========

/**
 * Mendapatkan top scorers
 */
function getTopScorers($limit = 10, $eventId = 0) {
    global $db;
    $conn = $db->getConnection();
    
    if ($eventId > 0) {
        $sql = "SELECT p.id, p.name, p.photo, p.jersey_number,
                       t.name as team_name, t.logo as team_logo,
                       COUNT(g.id) as goals
                FROM goals g
                INNER JOIN matches m ON g.match_id = m.id
                INNER JOIN players p ON g.player_id = p.id
                INNER JOIN teams t ON p.team_id = t.id
                WHERE m.event_id = ?
                GROUP BY p.id
                ORDER BY goals DESC, p.name
                LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $eventId, $limit);
    } else {
        $sql = "SELECT p.id, p.name, p.photo, p.jersey_number,
                       t.name as team_name, t.logo as team_logo,
                       COUNT(g.id) as goals
                FROM goals g
                INNER JOIN players p ON g.player_id = p.id
                INNER JOIN teams t ON p.team_id = t.id
                GROUP BY p.id
                ORDER BY goals DESC, p.name
                LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $limit);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $scorers = [];
    while ($row = $result->fetch_assoc()) {
        $scorers[] = $row;
    }
    return $scorers;
}

/**
 * Mendapatkan standings/klasemen
 */
function getStandings($eventId) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT t.id, t.name, t.logo,
                   COUNT(DISTINCT m.id) as matches_played,
                   SUM(CASE 
                       WHEN m.team1_id = t.id AND m.score1 > m.score2 THEN 1
                       WHEN m.team2_id = t.id AND m.score2 > m.score1 THEN 1
                       ELSE 0
                   END) as wins,
                   SUM(CASE 
                       WHEN m.team1_id = t.id AND m.score1 = m.score2 THEN 1
                       WHEN m.team2_id = t.id AND m.score2 = m.score1 THEN 1
                       ELSE 0
                   END) as draws,
                   SUM(CASE 
                       WHEN m.team1_id = t.id AND m.score1 < m.score2 THEN 1
                       WHEN m.team2_id = t.id AND m.score2 < m.score1 THEN 1
                       ELSE 0
                   END) as losses,
                   SUM(CASE 
                       WHEN m.team1_id = t.id THEN m.score1
                       WHEN m.team2_id = t.id THEN m.score2
                       ELSE 0
                   END) as goals_for,
                   SUM(CASE 
                       WHEN m.team1_id = t.id THEN m.score2
                       WHEN m.team2_id = t.id THEN m.score1
                       ELSE 0
                   END) as goals_against
            FROM teams t
            LEFT JOIN matches m ON (t.id = m.team1_id OR t.id = m.team2_id) 
                               AND m.event_id = ? 
                               AND m.status = 'completed'
            WHERE EXISTS (
                SELECT 1 FROM matches m2 
                WHERE (m2.team1_id = t.id OR m2.team2_id = t.id) 
                AND m2.event_id = ?
            )
            GROUP BY t.id
            ORDER BY (wins * 3 + draws) DESC, (goals_for - goals_against) DESC, goals_for DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $eventId, $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $standings = [];
    $position = 1;
    
    while ($row = $result->fetch_assoc()) {
        $row['position'] = $position++;
        $row['points'] = ($row['wins'] * 3) + $row['draws'];
        $row['goal_difference'] = $row['goals_for'] - $row['goals_against'];
        $standings[] = $row;
    }
    
    return $standings;
}

// ========== FUNGSI HELPER ==========

/**
 * Helper function untuk safe escape string
 */
function sanitize($data) {
    global $db;
    $conn = $db->getConnection();
    return mysqli_real_escape_string($conn, $data);
}

/**
 * Helper function untuk validasi email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Helper function untuk truncate text
 */
function truncateText($text, $length = 100) {
    if (strlen($text) > $length) {
        $text = substr($text, 0, $length);
        $text = substr($text, 0, strrpos($text, ' '));
        $text .= '...';
    }
    return $text;
}

/**
 * Helper function untuk mendapatkan bulan dalam bahasa Indonesia
 */
function getIndonesianMonth($monthNumber) {
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    return $months[$monthNumber] ?? '';
}

/**
 * Log aktivitas
 */
function logActivity($action, $details = '', $userId = null) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt->bind_param("issss", $userId, $action, $details, $ipAddress, $userAgent);
    return $stmt->execute();
}

/**
 * Mendapatkan bulan dalam bahasa Indonesia (pendek)
 */
function getShortIndonesianMonth($monthNumber) {
    $months = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    return $months[$monthNumber] ?? '';
}

/**
 * Mendapatkan hari dalam bahasa Indonesia
 */
function getIndonesianDay($dayNumber) {
    $days = [
        0 => 'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'
    ];
    return $days[$dayNumber] ?? '';
}

/**
 * Mendapatkan hari dalam bahasa Indonesia (pendek)
 */
function getShortIndonesianDay($dayNumber) {
    $days = [
        0 => 'Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'
    ];
    return $days[$dayNumber] ?? '';
}

/**
 * Format tanggal lengkap dalam bahasa Indonesia
 */
function formatIndonesianDate($date, $withDay = false) {
    if (empty($date) || $date == '0000-00-00 00:00:00') {
        return 'Tanggal tidak tersedia';
    }
    
    $timestamp = strtotime($date);
    if (!$timestamp) {
        return 'Tanggal tidak valid';
    }
    
    $day = date('d', $timestamp);
    $month = getIndonesianMonth(date('n', $timestamp));
    $year = date('Y', $timestamp);
    
    if ($withDay) {
        $dayOfWeek = getIndonesianDay(date('w', $timestamp));
        return "$dayOfWeek, $day $month $year";
    }
    
    return "$day $month $year";
}

/**
 * Format tanggal dan waktu lengkap dalam bahasa Indonesia
 */
function formatIndonesianDateTime($datetime, $withDay = false) {
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return 'Tanggal tidak tersedia';
    }
    
    $timestamp = strtotime($datetime);
    if (!$timestamp) {
        return 'Tanggal tidak valid';
    }
    
    $day = date('d', $timestamp);
    $month = getIndonesianMonth(date('n', $timestamp));
    $year = date('Y', $timestamp);
    $time = date('H:i', $timestamp);
    
    if ($withDay) {
        $dayOfWeek = getIndonesianDay(date('w', $timestamp));
        return "$dayOfWeek, $day $month $year $time";
    }
    
    return "$day $month $year, $time";
}

/**
 * Mendapatkan usia dari tanggal lahir
 */
function calculateAge($birthDate) {
    if (empty($birthDate) || $birthDate == '0000-00-00') {
        return 'N/A';
    }
    
    $birthDate = new DateTime($birthDate);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

/**
 * Mendapatkan format waktu yang lalu
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'baru saja';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return "$minutes menit yang lalu";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "$hours jam yang lalu";
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return "$days hari yang lalu";
    } elseif ($diff < 31104000) {
        $months = floor($diff / 2592000);
        return "$months bulan yang lalu";
    } else {
        $years = floor($diff / 31104000);
        return "$years tahun yang lalu";
    }
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

/**
 * Validate URL
 */
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL);
}

/**
 * Get file extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Validate image file
 */
function isValidImage($file) {
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extension = getFileExtension($file);
    return in_array($extension, $allowedExtensions);
}

/**
 * Generate unique filename
 */
function generateUniqueFilename($originalFilename) {
    $extension = getFileExtension($originalFilename);
    $timestamp = time();
    $randomString = generateRandomString(6);
    return "file_{$timestamp}_{$randomString}.{$extension}";
}

/**
 * Format number with commas
 */
function formatNumber($number) {
    return number_format($number, 0, ',', '.');
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Redirect dengan pesan
 */
function redirectWithMessage($url, $type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
    header("Location: $url");
    exit();
}

/**
 * Get flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Display flash message
 */
function displayFlashMessage() {
    $message = getFlashMessage();
    if ($message) {
        $type = $message['type'];
        $text = $message['message'];
        return "<div class='alert alert-{$type}'>{$text}</div>";
    }
    return '';
}

/**
 * Debug function
 */
function debug($data) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}

/**
 * Log error
 */
function logError($message, $file = '', $line = '') {
    $logMessage = date('Y-m-d H:i:s') . " - ";
    if ($file) $logMessage .= "File: $file - ";
    if ($line) $logMessage .= "Line: $line - ";
    $logMessage .= "Message: $message" . PHP_EOL;
    
    error_log($logMessage, 3, 'error_log.txt');
}

/**
 * Clean input
 */
function cleanInput($input) {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input);
    return $input;
}

/**
 * Validate required fields
 */
function validateRequired($fields, $data) {
    $errors = [];
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            $errors[$field] = "Field {$field} harus diisi";
        }
    }
    return $errors;
}

/**
 * Validate email
 */
function validateEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Format email tidak valid";
    }
    return null;
}

/**
 * Validate phone number
 */
function validatePhone($phone) {
    if (!preg_match('/^[0-9+\-\s()]{10,}$/', $phone)) {
        return "Format nomor telepon tidak valid";
    }
    return null;
}

/**
 * Validate numeric
 */
function validateNumeric($value, $field) {
    if (!is_numeric($value)) {
        return "Field {$field} harus berupa angka";
    }
    return null;
}

/**
 * Validate date
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    if ($d && $d->format($format) === $date) {
        return null;
    }
    return "Format tanggal tidak valid";
}

/**
 * Get current URL
 */
function getCurrentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    return $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Get base URL
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $path = dirname($script);
    
    return $protocol . "://" . $host . $path;
}

/**
 * Get client IP address
 */
function getClientIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Send JSON response
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Get pagination links
 */
function getPaginationLinks($total, $perPage, $currentPage, $urlPattern) {
    $totalPages = ceil($total / $perPage);
    
    $links = [];
    $range = 2;
    
    // Previous
    if ($currentPage > 1) {
        $links[] = [
            'page' => $currentPage - 1,
            'label' => '&laquo;',
            'active' => false,
            'url' => str_replace('{page}', $currentPage - 1, $urlPattern)
        ];
    }
    
    // Pages
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == 1 || $i == $totalPages || ($i >= $currentPage - $range && $i <= $currentPage + $range)) {
            $links[] = [
                'page' => $i,
                'label' => $i,
                'active' => ($i == $currentPage),
                'url' => str_replace('{page}', $i, $urlPattern)
            ];
        } elseif ($i == $currentPage - $range - 1 || $i == $currentPage + $range + 1) {
            $links[] = [
                'page' => null,
                'label' => '...',
                'active' => false,
                'url' => '#'
            ];
        }
    }
    
    // Next
    if ($currentPage < $totalPages) {
        $links[] = [
            'page' => $currentPage + 1,
            'label' => '&raquo;',
            'active' => false,
            'url' => str_replace('{page}', $currentPage + 1, $urlPattern)
        ];
    }
    
    return $links;
}

/**
 * Generate breadcrumb
 */
function generateBreadcrumb($items) {
    $breadcrumb = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    
    foreach ($items as $index => $item) {
        $isLast = ($index === count($items) - 1);
        
        if ($isLast) {
            $breadcrumb .= '<li class="breadcrumb-item active" aria-current="page">' . $item['label'] . '</li>';
        } else {
            $breadcrumb .= '<li class="breadcrumb-item"><a href="' . $item['url'] . '">' . $item['label'] . '</a></li>';
        }
    }
    
    $breadcrumb .= '</ol></nav>';
    return $breadcrumb;
}

/**
 * Format currency
 */
function formatCurrency($amount, $currency = 'IDR') {
    switch ($currency) {
        case 'IDR':
            return 'Rp ' . number_format($amount, 0, ',', '.');
        case 'USD':
            return '$' . number_format($amount, 2, '.', ',');
        default:
            return number_format($amount, 2, '.', ',');
    }
}

/**
 * Get excerpt from content
 */
function getExcerpt($content, $length = 150) {
    $excerpt = strip_tags($content);
    
    if (strlen($excerpt) > $length) {
        $excerpt = substr($excerpt, 0, $length);
        $lastSpace = strrpos($excerpt, ' ');
        if ($lastSpace !== false) {
            $excerpt = substr($excerpt, 0, $lastSpace);
        }
        $excerpt .= '...';
    }
    
    return $excerpt;
}

/**
 * Highlight search terms in text
 */
function highlightSearchTerms($text, $keywords) {
    if (empty($keywords)) {
        return $text;
    }
    
    $keywords = explode(' ', $keywords);
    foreach ($keywords as $keyword) {
        if (strlen($keyword) > 2) {
            $text = preg_replace("/\b($keyword)\b/i", '<span class="highlight">$1</span>', $text);
        }
    }
    
    return $text;
}

/**
 * Get SEO meta tags
 */
function getSeoMetaTags($title, $description, $keywords = '', $image = '') {
    $metaTags = '';
    
    // Title
    $metaTags .= '<title>' . htmlspecialchars($title) . '</title>' . PHP_EOL;
    
    // Description
    if (!empty($description)) {
        $metaTags .= '<meta name="description" content="' . htmlspecialchars($description) . '">' . PHP_EOL;
    }
    
    // Keywords
    if (!empty($keywords)) {
        $metaTags .= '<meta name="keywords" content="' . htmlspecialchars($keywords) . '">' . PHP_EOL;
    }
    
    // Open Graph
    $metaTags .= '<meta property="og:title" content="' . htmlspecialchars($title) . '">' . PHP_EOL;
    if (!empty($description)) {
        $metaTags .= '<meta property="og:description" content="' . htmlspecialchars($description) . '">' . PHP_EOL;
    }
    if (!empty($image)) {
        $metaTags .= '<meta property="og:image" content="' . htmlspecialchars($image) . '">' . PHP_EOL;
    }
    $metaTags .= '<meta property="og:type" content="website">' . PHP_EOL;
    $metaTags .= '<meta property="og:url" content="' . getCurrentUrl() . '">' . PHP_EOL;
    
    // Twitter Card
    $metaTags .= '<meta name="twitter:card" content="summary_large_image">' . PHP_EOL;
    $metaTags .= '<meta name="twitter:title" content="' . htmlspecialchars($title) . '">' . PHP_EOL;
    if (!empty($description)) {
        $metaTags .= '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">' . PHP_EOL;
    }
    if (!empty($image)) {
        $metaTags .= '<meta name="twitter:image" content="' . htmlspecialchars($image) . '">' . PHP_EOL;
    }
    
    return $metaTags;
}

// ========== FUNGSI UNTUK WEBSITE FUTBOL ==========

/**
 * Mendapatkan pertandingan mendatang untuk widget
 */
function getUpcomingMatches($limit = 5) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT m.*, t1.name as team1_name, t1.logo as team1_logo, 
                   t2.name as team2_name, t2.logo as team2_logo, 
                   e.name as event_name, e.color as event_color
            FROM matches m
            LEFT JOIN teams t1 ON m.team1_id = t1.id
            LEFT JOIN teams t2 ON m.team2_id = t2.id
            LEFT JOIN events e ON m.event_id = e.id
            WHERE m.status = 'scheduled' 
            AND m.match_date > NOW()
            ORDER BY m.match_date ASC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $matches = [];
    while ($row = $result->fetch_assoc()) {
        $matches[] = $row;
    }
    return $matches;
}

/**
 * Mendapatkan hasil pertandingan terbaru untuk widget
 */
function getRecentResults($limit = 5) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT m.*, t1.name as team1_name, t1.logo as team1_logo, 
                   t2.name as team2_name, t2.logo as team2_logo, 
                   e.name as event_name
            FROM matches m
            LEFT JOIN teams t1 ON m.team1_id = t1.id
            LEFT JOIN teams t2 ON m.team2_id = t2.id
            LEFT JOIN events e ON m.event_id = e.id
            WHERE m.status = 'completed' 
            ORDER BY m.match_date DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $matches = [];
    while ($row = $result->fetch_assoc()) {
        $matches[] = $row;
    }
    return $matches;
}

/**
 * Mendapatkan statistik tim untuk widget
 */
function getTeamStatistics() {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT t.id, t.name, t.logo,
                   COUNT(DISTINCT m.id) as total_matches,
                   SUM(CASE 
                       WHEN m.team1_id = t.id AND m.score1 > m.score2 THEN 1
                       WHEN m.team2_id = t.id AND m.score2 > m.score1 THEN 1
                       ELSE 0
                   END) as wins,
                   SUM(CASE 
                       WHEN m.team1_id = t.id AND m.score1 = m.score2 THEN 1
                       WHEN m.team2_id = t.id AND m.score2 = m.score1 THEN 1
                       ELSE 0
                   END) as draws,
                   SUM(CASE 
                       WHEN m.team1_id = t.id AND m.score1 < m.score2 THEN 1
                       WHEN m.team2_id = t.id AND m.score2 < m.score1 THEN 1
                       ELSE 0
                   END) as losses,
                   SUM(CASE 
                       WHEN m.team1_id = t.id THEN m.score1
                       WHEN m.team2_id = t.id THEN m.score2
                       ELSE 0
                   END) as goals_for,
                   SUM(CASE 
                       WHEN m.team1_id = t.id THEN m.score2
                       WHEN m.team2_id = t.id THEN m.score1
                       ELSE 0
                   END) as goals_against
            FROM teams t
            LEFT JOIN matches m ON (t.id = m.team1_id OR t.id = m.team2_id) 
                               AND m.status = 'completed'
            GROUP BY t.id
            ORDER BY (wins * 3 + draws) DESC, (goals_for - goals_against) DESC
            LIMIT 10";
    
    $result = $conn->query($sql);
    
    $stats = [];
    while ($row = $result->fetch_assoc()) {
        $row['points'] = ($row['wins'] * 3) + $row['draws'];
        $row['goal_difference'] = $row['goals_for'] - $row['goals_against'];
        $stats[] = $row;
    }
    
    return $stats;
}

/**
 * Mendapatkan pemain terbaik (top performers)
 */
function getTopPerformers($limit = 5) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT p.id, p.name, p.photo, p.position, p.jersey_number,
                   t.name as team_name, t.logo as team_logo,
                   (SELECT COUNT(*) FROM goals WHERE player_id = p.id) as goals,
                   (SELECT COUNT(*) FROM lineups WHERE player_id = p.id) as appearances,
                   ROUND(
                       (SELECT COUNT(*) FROM goals WHERE player_id = p.id) * 100.0 / 
                       NULLIF((SELECT COUNT(*) FROM lineups WHERE player_id = p.id), 0), 
                       2
                   ) as efficiency
            FROM players p
            LEFT JOIN teams t ON p.team_id = t.id
            WHERE EXISTS (SELECT 1 FROM goals g WHERE g.player_id = p.id)
            OR EXISTS (SELECT 1 FROM lineups l WHERE l.player_id = p.id)
            GROUP BY p.id
            ORDER BY goals DESC, appearances DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $players = [];
    while ($row = $result->fetch_assoc()) {
        $players[] = $row;
    }
    return $players;
}

/**
 * Mendapatkan event aktif
 */
function getActiveEvents() {
    global $db;
    $conn = $db->getConnection();
    
    $today = date('Y-m-d');
    $sql = "SELECT * FROM events 
            WHERE start_date <= ? AND end_date >= ? 
            AND registration_status = 'open'
            ORDER BY start_date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $today, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    return $events;
}

/**
 * Mendapatkan countdown untuk pertandingan berikutnya
 */
function getNextMatchCountdown() {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT m.*, t1.name as team1_name, t1.logo as team1_logo, 
                   t2.name as team2_name, t2.logo as team2_logo, 
                   e.name as event_name
            FROM matches m
            LEFT JOIN teams t1 ON m.team1_id = t1.id
            LEFT JOIN teams t2 ON m.team2_id = t2.id
            LEFT JOIN events e ON m.event_id = e.id
            WHERE m.status = 'scheduled' 
            AND m.match_date > NOW()
            ORDER BY m.match_date ASC 
            LIMIT 1";
    
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $match = $result->fetch_assoc();
        
        // Calculate countdown
        $matchDateTime = new DateTime($match['match_date']);
        $now = new DateTime();
        $interval = $now->diff($matchDateTime);
        
        $match['countdown'] = [
            'days' => $interval->days,
            'hours' => $interval->h,
            'minutes' => $interval->i,
            'seconds' => $interval->s,
            'total_seconds' => $interval->days * 86400 + $interval->h * 3600 + $interval->i * 60 + $interval->s
        ];
        
        return $match;
    }
    
    return null;
}

/**
 * Mendapatkan statistik website
 */
function getWebsiteStats() {
    global $db;
    $conn = $db->getConnection();
    
    $stats = [];
    
    // Total news
    $sql = "SELECT COUNT(*) as total FROM berita WHERE status = 'published'";
    $result = $conn->query($sql);
    $stats['total_news'] = $result->fetch_assoc()['total'];
    
    // Total matches
    $sql = "SELECT COUNT(*) as total FROM matches WHERE status = 'completed'";
    $result = $conn->query($sql);
    $stats['total_matches'] = $result->fetch_assoc()['total'];
    
    // Total teams
    $sql = "SELECT COUNT(*) as total FROM teams";
    $result = $conn->query($sql);
    $stats['total_teams'] = $result->fetch_assoc()['total'];
    
    // Total players
    $sql = "SELECT COUNT(*) as total FROM players";
    $result = $conn->query($sql);
    $stats['total_players'] = $result->fetch_assoc()['total'];
    
    // Total events
    $sql = "SELECT COUNT(*) as total FROM events";
    $result = $conn->query($sql);
    $stats['total_events'] = $result->fetch_assoc()['total'];
    
    // Total goals
    $sql = "SELECT COUNT(*) as total FROM goals";
    $result = $conn->query($sql);
    $stats['total_goals'] = $result->fetch_assoc()['total'];
    
    return $stats;
}

/**
 * Mendapatkan trending news
 */
function getTrendingNews($limit = 5) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT n.*, 
                   (n.views / NULLIF(DATEDIFF(NOW(), n.created_at), 0)) as trend_score
            FROM berita n
            WHERE n.status = 'published'
            AND n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY trend_score DESC, n.views DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $news = [];
    while ($row = $result->fetch_assoc()) {
        $news[] = $row;
    }
    return $news;
}

/**
 * Mendapatkan live matches (pertandingan yang sedang berlangsung)
 */
function getLiveMatches() {
    global $db;
    $conn = $db->getConnection();
    
    $now = date('Y-m-d H:i:s');
    $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $twoHoursLater = date('Y-m-d H:i:s', strtotime('+2 hours'));
    
    $sql = "SELECT m.*, t1.name as team1_name, t1.logo as team1_logo, 
                   t2.name as team2_name, t2.logo as team2_logo, 
                   e.name as event_name
            FROM matches m
            LEFT JOIN teams t1 ON m.team1_id = t1.id
            LEFT JOIN teams t2 ON m.team2_id = t2.id
            LEFT JOIN events e ON m.event_id = e.id
            WHERE m.status = 'scheduled' 
            AND m.match_date BETWEEN ? AND ?
            ORDER BY m.match_date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $oneHourAgo, $twoHoursLater);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $matches = [];
    while ($row = $result->fetch_assoc()) {
        // Calculate match progress
        $matchTime = new DateTime($row['match_date']);
        $currentTime = new DateTime();
        
        if ($matchTime > $currentTime) {
            $row['status'] = 'upcoming';
            $row['progress'] = 0;
        } else {
            $row['status'] = 'live';
            $diff = $currentTime->getTimestamp() - $matchTime->getTimestamp();
            $row['progress'] = min(90, round($diff / 60)); // Progress in minutes (max 90)
        }
        
        $matches[] = $row;
    }
    
    return $matches;
}

/**
 * Mendapatkan head-to-head statistik antara dua tim
 */
function getHeadToHeadStats($team1Id, $team2Id) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT 
               COUNT(*) as total_matches,
               SUM(CASE 
                   WHEN (team1_id = ? AND team2_id = ? AND score1 > score2) OR 
                        (team1_id = ? AND team2_id = ? AND score2 > score1) THEN 1
                   ELSE 0
               END) as team1_wins,
               SUM(CASE 
                   WHEN (team1_id = ? AND team2_id = ? AND score2 > score1) OR 
                        (team1_id = ? AND team2_id = ? AND score1 > score2) THEN 1
                   ELSE 0
               END) as team2_wins,
               SUM(CASE 
                   WHEN score1 = score2 THEN 1
                   ELSE 0
               END) as draws,
               SUM(CASE 
                   WHEN team1_id = ? THEN score1
                   WHEN team2_id = ? THEN score2
                   ELSE 0
               END) as team1_goals,
               SUM(CASE 
                   WHEN team1_id = ? THEN score2
                   WHEN team2_id = ? THEN score1
                   ELSE 0
               END) as team2_goals
            FROM matches 
            WHERE ((team1_id = ? AND team2_id = ?) OR (team1_id = ? AND team2_id = ?))
            AND status = 'completed'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiiiiiiiiiiii", 
        $team1Id, $team2Id, $team2Id, $team1Id,
        $team1Id, $team2Id, $team2Id, $team1Id,
        $team1Id, $team1Id,
        $team1Id, $team1Id,
        $team1Id, $team2Id, $team2Id, $team1Id
    );
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stats = $result->fetch_assoc();
        
        // Calculate percentages
        if ($stats['total_matches'] > 0) {
            $stats['team1_win_percentage'] = round(($stats['team1_wins'] / $stats['total_matches']) * 100, 1);
            $stats['team2_win_percentage'] = round(($stats['team2_wins'] / $stats['total_matches']) * 100, 1);
            $stats['draw_percentage'] = round(($stats['draws'] / $stats['total_matches']) * 100, 1);
        } else {
            $stats['team1_win_percentage'] = 0;
            $stats['team2_win_percentage'] = 0;
            $stats['draw_percentage'] = 0;
        }
        
        return $stats;
    }
    
    return [
        'total_matches' => 0,
        'team1_wins' => 0,
        'team2_wins' => 0,
        'draws' => 0,
        'team1_goals' => 0,
        'team2_goals' => 0,
        'team1_win_percentage' => 0,
        'team2_win_percentage' => 0,
        'draw_percentage' => 0
    ];
}

/**
 * Mendapatkan formasi tim terakhir
 */
function getTeamLastFormation($teamId) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT m.id as match_id, m.match_date,
                   GROUP_CONCAT(DISTINCT p.position ORDER BY p.position) as positions,
                   COUNT(DISTINCT p.id) as player_count
            FROM matches m
            INNER JOIN lineups l ON m.id = l.match_id
            INNER JOIN players p ON l.player_id = p.id
            WHERE (m.team1_id = ? OR m.team2_id = ?)
            AND m.status = 'completed'
            AND l.is_starting = 1
            AND p.position IS NOT NULL
            AND p.position != ''
            GROUP BY m.id
            ORDER BY m.match_date DESC
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $teamId, $teamId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $formation = $result->fetch_assoc();
        
        // Parse positions to determine formation
        $positions = explode(',', $formation['positions']);
        $positionCount = array_count_values($positions);
        
        // Determine formation (e.g., 4-4-2)
        $defenders = isset($positionCount['DEF']) ? $positionCount['DEF'] : 
                    (isset($positionCount['DF']) ? $positionCount['DF'] : 0);
        $midfielders = isset($positionCount['MID']) ? $positionCount['MID'] : 
                      (isset($positionCount['MF']) ? $positionCount['MF'] : 0);
        $forwards = isset($positionCount['FWD']) ? $positionCount['FWD'] : 
                   (isset($positionCount['FW']) ? $positionCount['FW'] : 0);
        
        $formation['formation'] = "{$defenders}-{$midfielders}-{$forwards}";
        $formation['defenders'] = $defenders;
        $formation['midfielders'] = $midfielders;
        $formation['forwards'] = $forwards;
        
        return $formation;
    }
    
    return null;
}

/**
 * Mendapatkan injury/suspension list
 */
function getInjurySuspensionList($limit = 10) {
    // This is a placeholder function since we don't have an injuries table
    // In a real application, you would have an injuries/suspensions table
    return [];
}

/**
 * Mendapatkan transfer rumors
 */
function getTransferRumors($limit = 5) {
    global $db;
    $conn = $db->getConnection();
    
    // Since we don't have a transfer_rumors table, we'll return recent transfers
    return getPlayerTransfers($limit);
}

/**
 * Mendapatkan match preview
 */
function getMatchPreview($matchId) {
    global $db;
    $conn = $db->getConnection();
    
    $match = getMatchById($matchId);
    if (!$match) {
        return null;
    }
    
    // Get head-to-head stats
    $h2h = getHeadToHeadStats($match['team1_id'], $match['team2_id']);
    
    // Get team stats
    $team1Stats = getTeamStats($match['team1_id']);
    $team2Stats = getTeamStats($match['team2_id']);
    
    // Get recent form
    $team1Form = getTeamRecentForm($match['team1_id'], 5);
    $team2Form = getTeamRecentForm($match['team2_id'], 5);
    
    // Get probable lineups
    $team1Formation = getTeamLastFormation($match['team1_id']);
    $team2Formation = getTeamLastFormation($match['team2_id']);
    
    // Compile preview
    $preview = [
        'match' => $match,
        'head_to_head' => $h2h,
        'team1_stats' => $team1Stats,
        'team2_stats' => $team2Stats,
        'team1_recent_form' => $team1Form,
        'team2_recent_form' => $team2Form,
        'team1_probable_formation' => $team1Formation ? $team1Formation['formation'] : 'Unknown',
        'team2_probable_formation' => $team2Formation ? $team2Formation['formation'] : 'Unknown'
    ];
    
    return $preview;
}

/**
 * Mendapatkan recent form tim
 */
function getTeamRecentForm($teamId, $limit = 5) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT m.*,
                   CASE 
                       WHEN (m.team1_id = ? AND m.score1 > m.score2) OR 
                            (m.team2_id = ? AND m.score2 > m.score1) THEN 'W'
                       WHEN (m.team1_id = ? AND m.score1 < m.score2) OR 
                            (m.team2_id = ? AND m.score2 < m.score1) THEN 'L'
                       ELSE 'D'
                   END as result
            FROM matches m
            WHERE (m.team1_id = ? OR m.team2_id = ?)
            AND m.status = 'completed'
            ORDER BY m.match_date DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiiiii", $teamId, $teamId, $teamId, $teamId, $teamId, $teamId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $form = [];
    $points = 0;
    
    while ($row = $result->fetch_assoc()) {
        $form[] = $row['result'];
        if ($row['result'] == 'W') $points += 3;
        if ($row['result'] == 'D') $points += 1;
    }
    
    return [
        'form' => array_reverse($form), // Oldest first
        'points' => $points,
        'matches' => count($form)
    ];
}

/**
 * Mendapatkan betting odds (placeholder)
 */
function getBettingOdds($matchId) {
    // This is a placeholder function
    // In a real application, you would integrate with a betting API
    return [
        'home_win' => rand(150, 250) / 100,
        'draw' => rand(200, 350) / 100,
        'away_win' => rand(250, 450) / 100,
        'source' => 'Bet365',
        'updated' => date('Y-m-d H:i:s')
    ];
}

/**
 * Mendapatkan weather forecast for match
 */
function getWeatherForecast($location, $date) {
    // This is a placeholder function
    // In a real application, you would integrate with a weather API
    $conditions = ['Sunny', 'Partly Cloudy', 'Cloudy', 'Rainy', 'Stormy'];
    $temperatures = ['20-25°C', '22-28°C', '18-23°C', '15-20°C', '25-30°C'];
    $humidity = ['60%', '70%', '75%', '80%', '65%'];
    
    $random = rand(0, 4);
    
    return [
        'condition' => $conditions[$random],
        'temperature' => $temperatures[$random],
        'humidity' => $humidity[$random],
        'wind' => rand(5, 20) . ' km/h',
        'provider' => 'Weather.com'
    ];
}

/**
 * Mendapatkan fan predictions
 */
function getFanPredictions($matchId) {
    global $db;
    $conn = $db->getConnection();
    
    // This is a placeholder - in a real app you'd have a predictions table
    $sql = "SELECT 
               (SELECT COUNT(*) FROM predictions WHERE match_id = ? AND predicted_winner = team1_id) as team1_votes,
               (SELECT COUNT(*) FROM predictions WHERE match_id = ? AND predicted_winner = team2_id) as team2_votes,
               (SELECT COUNT(*) FROM predictions WHERE match_id = ? AND predicted_winner = 0) as draw_votes
            FROM predictions 
            WHERE match_id = ? 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $matchId, $matchId, $matchId, $matchId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $predictions = $result->fetch_assoc();
        $total = $predictions['team1_votes'] + $predictions['team2_votes'] + $predictions['draw_votes'];
        
        if ($total > 0) {
            $predictions['team1_percentage'] = round(($predictions['team1_votes'] / $total) * 100, 1);
            $predictions['team2_percentage'] = round(($predictions['team2_votes'] / $total) * 100, 1);
            $predictions['draw_percentage'] = round(($predictions['draw_votes'] / $total) * 100, 1);
        } else {
            $predictions['team1_percentage'] = 0;
            $predictions['team2_percentage'] = 0;
            $predictions['draw_percentage'] = 0;
        }
        
        $predictions['total_votes'] = $total;
        return $predictions;
    }
    
    // Return mock data if no predictions table
    return [
        'team1_votes' => rand(100, 500),
        'team2_votes' => rand(100, 500),
        'draw_votes' => rand(50, 200),
        'team1_percentage' => rand(30, 60),
        'team2_percentage' => rand(30, 60),
        'draw_percentage' => rand(10, 30),
        'total_votes' => rand(300, 1000)
    ];
}

/**
 * Mendapatkan expert analysis
 */
function getExpertAnalysis($matchId) {
    // This is a placeholder function
    $analyses = [
        "Tim tuan rumah memiliki rekor kandang yang kuat musim ini.",
        "Tim tamu sedang dalam performa terbaik mereka dengan 3 kemenangan beruntun.",
        "Pertandingan ini diperkirakan akan berjalan ketat dengan sedikit peluang gol.",
        "Kedua tim memiliki cedera pemain penting yang bisa mempengaruhi hasil.",
        "Head-to-head menunjukkan keunggulan bagi tim tuan rumah.",
        "Kondisi cuaca diperkirakan mendukung permainan cepat.",
        "Wasit yang memimpin memiliki kecenderungan memberikan kartu kuning yang tinggi.",
        "Ini adalah derby lokal yang selalu menghasilkan pertandingan panas."
    ];
    
    $randomAnalysis = $analyses[array_rand($analyses)];
    
    return [
        'analysis' => $randomAnalysis,
        'expert' => 'John Doe',
        'expert_title' => 'Analis Sepak Bola',
        'confidence' => rand(60, 95) . '%',
        'predicted_score' => rand(0, 3) . '-' . rand(0, 2)
    ];
}

/**
 * Mendapatkan TV/streaming information
 */
function getBroadcastInfo($matchId) {
    $broadcasters = ['TVRI', 'RCTI', 'SCTV', 'Indosiar', 'MNCTV', 'Trans TV', 'Net TV', 'Kompas TV'];
    $streaming = ['Vidio', 'Mola TV', 'RCTI+', 'SCTV Live', 'Indosiar Live'];
    
    return [
        'tv_broadcasters' => array_slice($broadcasters, 0, rand(1, 3)),
        'streaming_platforms' => array_slice($streaming, 0, rand(1, 3)),
        'kickoff_time' => date('H:i', strtotime('+' . rand(1, 24) . ' hours')),
        'timezone' => 'WIB'
    ];
}

/**
 * Mendapatkan ticket information
 */
function getTicketInfo($matchId) {
    $categories = ['VIP', 'Tribune', 'East Stand', 'West Stand', 'North Stand', 'South Stand'];
    
    $tickets = [];
    foreach ($categories as $category) {
        $tickets[] = [
            'category' => $category,
            'price' => 'Rp ' . number_format(rand(50000, 500000), 0, ',', '.'),
            'availability' => rand(0, 100) > 30 ? 'Available' : 'Sold Out',
            'remaining' => rand(0, 5000)
        ];
    }
    
    return [
        'tickets' => $tickets,
        'box_office' => 'Stadion Utama',
        'online_booking' => 'https://ticket.example.com',
        'contact' => '(021) 12345678'
    ];
}

/**
 * Mendapatkan stadium information
 */
function getStadiumInfo($stadiumName) {
    $stadiums = [
        'Gelora Bung Karno' => [
            'capacity' => '77,193',
            'city' => 'Jakarta',
            'opened' => '1962',
            'surface' => 'Grass',
            'dimensions' => '105m x 68m'
        ],
        'Jakarta International Stadium' => [
            'capacity' => '82,000',
            'city' => 'Jakarta',
            'opened' => '2022',
            'surface' => 'Hybrid grass',
            'dimensions' => '105m x 68m'
        ],
        'Stadion Utama' => [
            'capacity' => '30,000',
            'city' => 'Unknown',
            'opened' => '2000',
            'surface' => 'Grass',
            'dimensions' => '100m x 65m'
        ]
    ];
    
    return $stadiums[$stadiumName] ?? [
        'capacity' => 'Unknown',
        'city' => 'Unknown',
        'opened' => 'Unknown',
        'surface' => 'Unknown',
        'dimensions' => 'Unknown'
    ];
}

/**
 * Mendapatkan social media buzz
 */
function getSocialMediaBuzz($matchId) {
    $hashtags = ['#DerbyKota', '#MatchOfTheDay', '#BigGame', '#LocalDerby', '#HotMatch'];
    
    return [
        'trending_hashtags' => array_slice($hashtags, 0, rand(2, 5)),
        'twitter_mentions' => rand(1000, 50000),
        'instagram_posts' => rand(500, 20000),
        'facebook_shares' => rand(1000, 30000),
        'tiktok_views' => rand(50000, 500000)
    ];
}

/**
 * Mendapatkan historical moments
 */
function getHistoricalMoments($team1Id, $team2Id) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT m.*, t1.name as team1_name, t2.name as team2_name
            FROM matches m
            LEFT JOIN teams t1 ON m.team1_id = t1.id
            LEFT JOIN teams t2 ON m.team2_id = t2.id
            WHERE ((m.team1_id = ? AND m.team2_id = ?) OR (m.team1_id = ? AND m.team2_id = ?))
            AND m.status = 'completed'
            ORDER BY ABS(m.score1 - m.score2) DESC, m.match_date DESC
            LIMIT 5";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $team1Id, $team2Id, $team2Id, $team1Id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $moments = [];
    while ($row = $result->fetch_assoc()) {
        $moments[] = [
            'date' => formatDate($row['match_date']),
            'score' => $row['score1'] . ' - ' . $row['score2'],
            'teams' => $row['team1_name'] . ' vs ' . $row['team2_name'],
            'type' => $row['score1'] == $row['score2'] ? 'Draw' : 
                     (abs($row['score1'] - $row['score2']) >= 3 ? 'Big Win' : 'Close Match'),
            'goal_difference' => abs($row['score1'] - $row['score2'])
        ];
    }
    
    return $moments;
}

/**
 * Mendapatkan player milestones
 */
function getPlayerMilestones($matchId) {
    global $db;
    $conn = $db->getConnection();
    
    $sql = "SELECT p.*, t.name as team_name,
                   (SELECT COUNT(*) FROM goals g WHERE g.player_id = p.id) as career_goals,
                   (SELECT COUNT(*) FROM lineups l WHERE l.player_id = p.id) as career_appearances
            FROM players p
            LEFT JOIN teams t ON p.team_id = t.id
            WHERE p.id IN (
                SELECT DISTINCT player_id FROM lineups WHERE match_id = ?
                UNION
                SELECT DISTINCT player_id FROM goals WHERE match_id = ?
            )
            ORDER BY career_goals DESC, career_appearances DESC
            LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $matchId, $matchId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $milestones = [];
    while ($row = $result->fetch_assoc()) {
        // Check for potential milestones
        $milestone = [];
        
        // 50th appearance
        if ($row['career_appearances'] == 49) {
            $milestone[] = 'Potential 50th appearance';
        }
        
        // 100th appearance
        if ($row['career_appearances'] == 99) {
            $milestone[] = 'Potential 100th appearance';
        }
        
        // 10th goal
        if ($row['career_goals'] == 9) {
            $milestone[] = 'Potential 10th career goal';
        }
        
        // 50th goal
        if ($row['career_goals'] == 49) {
            $milestone[] = 'Potential 50th career goal';
        }
        
        if (!empty($milestone)) {
            $milestones[] = [
                'player' => $row['name'],
                'team' => $row['team_name'],
                'milestones' => $milestone,
                'current_goals' => $row['career_goals'],
                'current_appearances' => $row['career_appearances']
            ];
        }
    }
    
    return $milestones;
}

// End of functions.php
?>
